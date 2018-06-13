<?php

namespace Statamic\Addons\Workshop;

use Statamic\API\URL;
use Statamic\API\Page;
use Statamic\API\Path;
use Statamic\API\Asset;
use Statamic\API\Crypt;
use Statamic\API\Entry;
use Statamic\API\Config;
use Statamic\API\Content;
use Statamic\API\Request;
use Statamic\API\Fieldset;
use Statamic\API\Collection;
use Statamic\Extend\Controller;
use Stringy\StaticStringy as Stringy;
use Illuminate\Http\RedirectResponse;
use Statamic\CP\Publish\ValidationBuilder;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class WorkshopController extends Controller
{
    /**
     * The content object we're dealing with. Page, Entry, etc.
     *
     * @var \Statamic\Contracts\Data\Content\Content
     */
    public $content;

    /**
     * The data with which to create a content file.
     *
     * @var array
     */
    public $fields;

    /**
     * The fieldset being used for the content.
     *
     * @var \Statamic\Contracts\CP\Fieldset
     */
    private $fieldset;

    /**
     * Meta attributes that describe the content, but will not necessarily be saved to file.
     *
     * These can be set through html fields, or tag parameters. Parameters will take priority.
     *
     * @var array
     */
    private $meta = [
        'id'         => null,
        'collection' => null,
        'date'       => null,
        'fieldset'   => null,
        'order'      => null,
        'published'  => true,
        'parent'     => '/',
        'redirect'   => null,
        'slug'       => null,
        'slugify'    => 'title',
    ];

    /**
     * Manipulate common request data across all types
     * of content, big and small.
     *
     * @return mixed
     */
    public function init()
    {
        if ( ! $this->isAllowed()) {
            return redirect()->back();
        }

        $this->initializeFields();
        $this->initializeSlug();
    }

    /**
     * Initialize the fields that were submitted.
     *
     * @return void
     */
    private function initializeFields()
    {
        $fields = $this->filter(Request::except(['_token']));

        $this->fields = array_filter($fields);
    }

    /**
     * Make sure we have a slug.
     *
     * @return void
     */
    private function initializeSlug()
    {
        // If the slug was set manually, either through a field or through the tag parameter, we'll just use that.
        if ($this->meta['slug']) {
            return;
        }

        // Get the value from which we want to slugify. By default it's "title".
        // If it was overridden and the field doesn't exist for whatever reason, we'll use the first field.
        $sluggard = array_get($this->fields, $this->meta['slugify'], current($this->fields));

        $this->meta['slug'] = Stringy::slugify($sluggard);
    }

    /**
     * Get the default fieldset for the content type
     *
     * @param string $type
     * @return string
     */
    private function getDefaultFieldset($type)
    {
        $typeDefault = Config::get("theming.default_{$type}_fieldset");

        if (Fieldset::exists($typeDefault)) {
            return $typeDefault;
        }

        return Config::get('theming.default_fieldset');
    }

    /**
     * Create an entry in a collection.
     *
     * @return RedirectResponse
     */
    public function postEntryCreate()
    {
        if ( ! $this->meta['collection']) {
            return back()->withInput()->withErrors(['A collection is required.'], 'workshop');
        }

        $collection = Collection::whereHandle($this->meta['collection']);

        // If a fieldset was specified, use that, otherwise use the one from the collection.
        $this->fieldset = ($this->meta['fieldset']) ? Fieldset::get($this->meta['fieldset']) : $collection->fieldset();

        $validator = $this->getValidator();
        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator, 'workshop');
        }

        $this->uploadFiles();

        $factory = Entry::create($this->meta['slug'])
            ->collection($collection->path())
            ->published($this->meta['published'])
            ->with($this->whitelist($this->fields));

        // If the collection is date based, set the date to what was specified (if nothing was specified the
        // current time will be used). If the collection is not date based, set the order if one was provided.
        if ($collection->order() == 'date') {
            $factory->date($this->meta['date']);
        } elseif ($this->meta['order']) {
            $factory->order($this->meta['order']);
        }

        $this->content = $factory->get();

        return $this->save();
    }

    /**
     * Upload files
     *
     * @return void
     */
    private function uploadFiles()
    {
        collect($this->request->files->all())->map(function ($files, $key) {
            // Discard files that don't match to a field in the fieldset.
            if (! $field = array_get($this->fieldset->fields(), $key)) {
                return;
            }

            $files = collect(is_array($files) ? $files : [$files])
                ->map(function ($file) use ($field) {
                    return $this->uploadFile($file, $field);
                });

            return (array_get($field, 'max_files') === 1)
                ? $files->first()
                : $files->all();
        })->filter()->each(function ($value, $field) {
            // Replace the field value with the value from the newly uploaded asset(s).
            $this->fields[$field] = $value;
        });
    }

    /**
     * Upload a single file
     *
     * @param UploadedFile $file  The uploaded file
     * @param array $config       The field config
     */
    private function uploadFile($file, $config)
    {
        // Not an asset field? Bye.
        if (array_get($config, 'type') !== 'assets') {
            return;
        }

        $path = Path::assemble(array_get($config, 'folder'), $file->getClientOriginalName());

        $asset = Asset::create()
            ->container(array_get($config, 'container'))
            ->path(ltrim($path, '/'))
            ->get();

        $asset->upload($file);
        $asset->save();

        return $asset->url();
    }

    /**
     * Update an entry in a collection.
     *
     * @return RedirectResponse
     */
    public function postEntryUpdate()
    {
        $this->content = Content::find($this->meta['id']);

        // If a fieldset was specified, use that. Otherwise, use the associated entry's fieldset.
        $this->fieldset = ($this->meta['fieldset'])
            ? Fieldset::get($this->meta['fieldset'])
            : $this->content->fieldset();

        return $this->update();
    }

    /**
     * Create a page.
     *
     * @return RedirectResponse
     */
    public function postPageCreate()
    {
        $this->fieldset = Fieldset::get(
            $this->meta['fieldset'] ?: $this->getDefaultFieldset('page')
        );

        $validator = $this->getValidator();
        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }

        $this->uploadFiles();

        $url = URL::assemble($this->meta['parent'], $this->meta['slug']);

        $this->content = Page::create($url)
            ->with($this->whitelist($this->fields))
            ->get();

        return $this->save();
    }

    /**
     * Update a page.
     *
     * @return RedirectResponse
     */
    public function postPageUpdate()
    {
        // If a fieldset was specified, use that. Otherwise, use the associated entry's fieldset.
        $this->fieldset = ($this->meta['fieldset'])
            ? Fieldset::get($this->meta['fieldset'])
            : $this->content->fieldset();

        return $this->update();
    }

    /**
     * Update a global.
     *
     * @return RedirectResponse
     */
    public function postGlobalUpdate()
    {
        return $this->update();
    }

    /**
     * Update a content file with new data.
     *
     * @return RedirectResponse
     */
    private function update()
    {
        $validator = $this->getValidator();

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }

        $this->uploadFiles();

        $data = array_merge($this->content->data(), $this->whitelist($this->fields));

        $this->content->data($data);

        return $this->save();
    }

    /**
     * Get the Validator instance
     *
     * @return mixed
     */
    private function getValidator()
    {
        $fields = $this->fields;

        $builder = new ValidationBuilder(['fields' => $fields], $this->fieldset);

        $builder->build();

        $rules = $builder->rules();

        // Ensure the title (or slugify-able field, really) is required.
        $sluggard = array_filter(explode('|', array_get($rules, "fields.{$this->meta['slugify']}")));
        $sluggard[] = 'required';
        $rules["fields.{$this->meta['slugify']}"] = join('|', $sluggard);

        return \Validator::make(['fields' => $fields], $rules, [], $builder->attributes());
    }

    /**
     * Save the content object, run the hook, and redirect as needed.
     *
     * @return RedirectResponse
     */
    private function save()
    {
        $this->content->ensureId();

        $this->content->save();

        $this->flash->put('success', true);

        if ($this->meta['redirect']) {
            return redirect($this->getRedirect());
        };

        return redirect()->back();
    }

    /**
     * Filter out any meta fields from the submitted data and assign them within the meta array.
     *
     * @param array $fields
     * @return array
     */
    private function filter($fields)
    {
        // Filter the HTML form data first
        foreach ($fields as $key => $field) {
            if (in_array($key, array_keys($this->meta))) {
                $this->meta[$key] = $this->formatValue($field);
                unset($fields[$key]);
            }
        }

        // And override those with special meta fields set on the tag itself as parameters
        if (array_get($fields, '_meta')) {
            $meta = Crypt::decrypt($fields['_meta']);

            foreach ($meta as $key => $field) {
                if (in_array($key, array_keys($this->meta))) {
                    $this->meta[$key] = $this->formatValue($field);
                }
            }
            unset($fields['_meta']);
        }

        return $fields;
    }

    /**
     * Format a value
     *
     * @param mixed $value
     * @return mixed
     */
    private function formatValue($value)
    {
        switch ($value) {
            case 'true':
                return true;
            case 'false':
                return false;
            default:
                return $value;
        }
    }

    /**
     * Clean the submitted fields and only leave the whitelisted ones.
     *
     * @param array $fields
     * @return array
     */
    private function whitelist($fields)
    {
        if (! $this->getConfig('whitelist')) {
            return $fields;
        }

        $whitelist = array_keys($this->fieldset->fields());

        $whitelist[] = 'title';

        return array_intersect_key($this->fields, array_flip($whitelist));
    }

    /**
     * Find and set the redirect URL.
     *
     * @return string
     */
    private function getRedirect()
    {
        if ($this->meta['redirect'] == 'url') {
            return $this->content->urlPath();
        }

        return $this->meta['redirect'];
    }

    /**
     * Checks to see if the user is allowed to use the Workshop.
     * Not everyone is so lucky.
     *
     * @return bool
     */
    private function isAllowed()
    {
        if ($this->getConfig('enforce_auth') && ! \Auth::check()) {
            return false;
        };

        return true;
    }
}
