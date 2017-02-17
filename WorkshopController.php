<?php

namespace Statamic\Addons\Workshop;

use Statamic\API\Collection;
use Statamic\API\URL;
use Statamic\API\Form;
use Statamic\API\Page;
use Statamic\API\Crypt;
use Statamic\API\Entry;
use Statamic\API\Content;
use Statamic\API\Request;
use Statamic\API\Fieldset;
use Statamic\Extend\Listener;
use Illuminate\Http\Response;
use Statamic\Extend\Controller;
use Stringy\StaticStringy as Stringy;
use Statamic\CP\Publish\ValidationBuilder;

class WorkshopController extends Controller
{
    /**
     * The factory object to work with.
     *
     * @var array
     */
    public $factory;

    /**
     * The data with which to create a content file.
     *
     * @var array
     */
    public $fields;

    /**
     * Meta attributes that describe the content, but will not necessarily be saved to file.
     *
     * These can be set through html fields, or tag parameters. Parameters will take priority.
     *
     * @var array
     */
    private $meta;

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

        // Set all the meta attributes and their defaults.
        $this->meta = [
            'id'         => null,          // The content's id
            'collection' => null,  // An entry's collection. Where it belongs.
            'date'       => null,        // An entry's optional date.
            'fieldset'   => null,    // The fieldset. The thing that rules them all.
            'order'      => null,       // An entry or page's Order key.
            'published'  => true,   // The published status of the content.
            'parent'     => '/',       // A page's optional parent page.
            'redirect'   => null,    // The URL to redirect the user to upon success
            'slug'       => null,        // The content's slug. By default will be a slugifed 'title'.
            'slugify'    => 'title',  // The field to slugify to create the slug.
        ];

        // Set the fields to be added to the content file, then filter out any meta fields.
        $this->fields = Request::all();
        $this->filter();

        // Initialize the content factory if editing.
        $this->startFactory();

        $this->setFieldsetAndMore();

        $this->slugify();
    }

    /**
     * Create an entry in a collection.
     *
     * @return request
     */
    public function postEntryCreate()
    {
        if ( ! $this->meta['collection']) {
            // TODO: Throw an exception and/or return an error message
            dd('Come on now. You need a collection.');
        }

        $validator = $this->getValidator();

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator, 'workshop');
        }

        $this->factory = Entry::create($this->meta['slug'])
                        ->collection($this->meta['collection']->path())
                        ->published($this->meta['published'])
                        ->with($this->fields);

        if ($this->meta['collection']->order() == 'date') {
            $this->factory->date($this->meta['date']);
        } elseif ($this->meta['order']) {
            $this->factory->order($this->meta['order']);
        }

        $this->factory = $this->factory->get();

        return $this->save();
    }

    /**
     * Update an entry in a collection.
     *
     * @return request
     */
    public function postEntryUpdate()
    {
        return $this->update();
    }

    /**
     * Create a page.
     *
     * @return request
     */
    public function postPageCreate()
    {
        $validator = $this->getValidator();

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }

        $url = URL::assemble($this->parent, $this->slug);

        $this->factory = Page::create($url)
                        ->with($this->fields)
                        ->get();

        return $this->save();
    }

    /**
     * Update a page.
     *
     * @return request
     */
    public function postPageUpdate()
    {
        return $this->update();
    }

    /**
     * Update a global.
     *
     * @return request
     */
    public function postGlobalUpdate()
    {
        return $this->update();
    }

    /**
     * Update a content file with new data.
     *
     * @return request
     */
    private function update()
    {
        $validator = $this->getValidator();

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }

        $data = array_merge($this->factory->data(), $this->fields);

        $this->factory->data($data);

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

        $builder = new ValidationBuilder(['fields' => $fields], $this->meta['fieldset']);

        $builder->build();

        $rules = $builder->rules();

        // Ensure the title (or slugify-able field, really) is required.
        $sluggard = array_filter(explode('|', array_get($rules, "fields.{$this->meta['slugify']}")));
        $sluggard[] = 'required';
        $rules["fields.{$this->meta['slugify']}"] = join('|', $sluggard);

        return \Validator::make(['fields' => $fields], $rules, [], $builder->attributes());
    }

    /**
     * Save the factory object, run the hook,
     * and redirect as needed.
     *
     * @return mixed
     */
    private function save()
    {
        $this->factory->ensureId();

        $this->factory->save();

        $this->flash->put('success', true);

        if ($this->meta['redirect']) {
            return redirect($this->getRedirect());
        };

        return redirect()->back();
    }

    private function startFactory()
    {
        if ($this->meta['id']) {
            $this->factory = Content::uuidRaw($this->meta['id']);
        }
    }

    /**
     * Set the slug based on another field. Defaults to title.
     *
     * @return void
     */
    private function slugify()
    {
        $sluggard = array_get($this->fields, $this->meta['slugify'], current($this->fields));

        $this->meta['slug'] = Stringy::slugify($sluggard);
    }

    /**
     * Filter out any meta fields from the request object and
     * and assign them to class variables, leaving you with
     * a nice and clean $fields variable to work with.
     *
     * @return void
     */
    private function filter()
    {
        // Filter the HTML form data first
        foreach ($this->fields as $key => $field) {
            if (in_array($key, array_keys($this->meta))) {
                $this->meta[$key] = $this->formatValue($field);
                unset($this->fields[$key]);
            }
        }

        // And override those with special meta fields set
        // on the tag itself as parameters
        if (array_get($this->fields, '_meta')) {
            $meta = Crypt::decrypt($this->fields['_meta']);

            foreach ($meta as $key => $field) {
                if (in_array($key, array_keys($this->meta))) {
                    $this->meta[$key] = $this->formatValue($field);
                }
            }
            unset($this->fields['_meta']);
        }
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
     * Find and set the Fieldset object, if there is one.
     *
     * @return void
     */
    private function setFieldsetAndMore()
    {
        // If a collection was specified, change from the string to the actual object.
        if ($this->meta['collection']) {
            $this->meta['collection'] = Collection::whereHandle($this->meta['collection']);
        }

        // Set that fieldset
        if ($this->meta['fieldset']) {
            $this->meta['fieldset'] = Fieldset::get($this->meta['fieldset']);
        } elseif ($this->factory) {
            $this->meta['fieldset'] = $this->factory->fieldset();
        } elseif ($this->meta['collection']) {
            $this->meta['fieldset'] = $this->meta['collection']->fieldset();
        }

        // Drop any field that's not in the fieldset
        if ($this->meta['fieldset'] && $this->getConfig('whitelist')) {
            $whitelist = array_keys($this->meta['fieldset']->fields());
            $whitelist[] = 'title';
            $this->fields = array_intersect_key($this->fields, array_flip($whitelist));
        }
    }

    /**
     * Find and set the redirect URL.
     *
     * @return void
     */
    private function getRedirect()
    {
        if ($this->meta['redirect'] == 'url') {
            return $this->factory->urlPath();
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
