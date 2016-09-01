<?php

namespace Statamic\Addons\Workshop;

use Statamic\API\URL;
use Statamic\API\Form;
use Statamic\API\Page;
use Statamic\API\Crypt;
use Statamic\API\Entry;
use Statamic\API\Content;
use Statamic\API\Request;
use Statamic\API\Fieldset;
use Statamic\API\User; //DANIELSON
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
    public $fields = [];

    /**
     * Fields that should be stripped out as meta data.
     *
     * @var array
     */
    private $meta = [
        'id',
        'collection',
        'date',
        'fieldset',
        'order',
        'published',
        'parent',
        'redirect',
        'slug',
        'slugify',
        'user', //DANIELSON
        'username' //DANIELSON
    ];

    /**
     * The content's id
     *
     * @var string
     */
    private $id;

    /**
     * An entry's optional date.
     *
     * @var string
     */
    private $date;

    /**
     * The content's slug. By default will be a slugifed 'title'.
     *
     * @var string
     */
    private $slug;

    /**
     * An entry's collection. Where it belongs.
     *
     * @var string
     */
    private $collection;

    /**
     * The fieldset. The thing that rules them all.
     *
     * @var string
     */
    private $fieldset;

    /**
     * An entry or page's Order key.
     *
     * @var string
     */
    private $order;

    /**
     * A page's optional parent page.
     *
     * @var string
     */
    private $parent = '/';

    /**
     * The published status of the content.
     *
     * @var string
     */
    private $published = true;

    /**
     * The URL to redirect the user to upon success
     *
     * @var string
     */
    private $redirect;

    /**
     * The field to slugify to create the slug.
     *
     * @var string
     */
    private $slugify = 'title';

    //DANIELSON
    /**
     * The user. By default will be current user.
     *
     * @var string
     */
    private $user;

    //DANIELSON
    /**
     * The username of the person being edited.
     *
     * @var string
     */
    private $username;

    /**
     * Manipulate common request data across all types
     * of content, big and small.
     *
     * @return void
     */
    public function init()
    {
        if ( ! $this->isAllowed()) {
            return redirect()->back();
        }

        $this->fields = Request::all();

        $this->filter();

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
        if ( ! $this->collection) {
            // TODO: Throw an exception and/or return an error message
            dd('Come on now. You need a collection.');
        }

        $validator = $this->runValidation();

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator, 'workshop');
        }

        $this->factory = Entry::create($this->slug)
                        ->collection($this->collection->path())
                        ->with($this->fields);

        if ($this->collection->order() == 'date') {
            $this->factory->date($this->date);
        } elseif ($this->order) {
            $this->factory->order($this->order);
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
        $validator = $this->runValidation();

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

    //DANIELSON
    /**
     * Update a user.
     *
     * @return request
     */
    public function postUserUpdate()
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
        $validator = $this->runValidation();

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }

        if ($this->username) {//DANIELSON - just remove the if/else to reset this
            $user = $this->getUser();
            $data = array_merge($user->data(), $this->fields);
            $user->data($data);
            $user->save();

            if ($this->redirect) {//DANIELSON - stole this bit from the save() function, below
                return redirect($this->getRedirect());
            };
            return redirect()->back();

        } else {
            $data = array_merge($this->factory->data(), $this->fields);
            $this->factory->data($data);
            return $this->save();
        }

    }

    /**
     * Get the Validator instance
     *
     * @return mixed
     */
    private function runValidation()
    {
        if ($this->username) {//DANIELSON
            $fields = array_merge($this->fields, ['username' => 'required']);
        } else {
            $fields = array_merge($this->fields, ['slug' => 'required']);
        }
        $builder = new ValidationBuilder(['fields' => $fields], $this->fieldset);

        $builder->build();

        return \Validator::make(['fields' => $fields], $builder->rules());
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

        if ($this->redirect) {
            return redirect($this->getRedirect());
        };

        return redirect()->back();
    }

    private function startFactory()
    {
        if ($this->id) {
            $this->factory = Content::uuidRaw($this->id);
        }
    }

    //DANIELSON
    /**
     * Get user content by id, falling back to the current user
     *
     * @return Content
     */
    private function getUser()
    {
        $username = $this->username;

        if ($username) {
            $user = User::whereUsername($username);
        } else {
            $user = User::getCurrent();
        }

        return $user;
    }

    /**
     * Set the slug based on another field. Defaults to title.
     *
     * @return void
     */
    private function slugify()
    {
        if ($this->username) {//DANIELSON
            $slugify = 'username';
        }

        $sluggard = array_get($this->fields, $this->slugify, current($this->fields));

        $this->slug = Stringy::slugify($sluggard);
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
            if (in_array($key, $this->meta)) {
                $this->{$key} = $field;
                unset($this->fields[$key]);
            }
        }

        // And override those with special meta fields set
        // on the tag itself as parameters
        if (array_get($this->fields, '_meta')) {
            $meta = Crypt::decrypt($this->fields['_meta']);

            foreach ($meta as $key => $field) {
                if (in_array($key, $this->meta)) {
                    $this->{$key} = $field;
                }
            }
            unset($this->fields['_meta']);
        }
    }

    /**
     * Find and set the Fieldset object, if there is one.
     *
     * @return void
     */
    private function setFieldsetAndMore()
    {
        // Set that collection
        if ($this->collection) {
            $this->collection = Content::collection($this->collection);
        }

        // Set that fieldset
        if ($this->fieldset) {
            $this->fieldset = Fieldset::get($this->fieldset);
        } elseif ($this->username) {//DANIELSON
            $user = $this->getUser();
            $this->fieldset = $user->fieldset();
        } elseif ($this->factory) {
            $this->fieldset = $this->factory->fieldset();
        } elseif ($this->collection) {
            $this->fieldset = $this->collection->fieldset();
        }

        // Drop any field that's not in the fieldset
        if ($this->fieldset && $this->getConfig('whitelist')) {
            $this->fields = array_intersect_key($this->fields, array_flip(array_keys($this->fieldset->fields())));
        }
    }

    /**
     * Find and set the redirect URL.
     *
     * @return void
     */
    private function getRedirect()
    {
        if ($this->redirect == 'url') {
            return $this->factory->urlPath();
        }

        return $this->redirect;
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
