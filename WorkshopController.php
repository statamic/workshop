<?php

namespace Statamic\Addons\Workshop;

use Statamic\API\URL;
use Statamic\API\Form;
use Statamic\API\Page;
use Statamic\API\Crypt;
use Statamic\API\Entry;
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
    public $fields = [];
    
    /**
     * Fields that should be stripped out as meta data.
     *
     * @var array
     */
    private $meta = [
        'collection',
        'date',
        'fieldset',
        'published',
        'parent',
        'redirect',
        'slug',
        'slugify'
    ];
    
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
    
    /**
     * Manipulate common request data across all types
     * of content, big and small.
     * 
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->fields = Request::all();
        
        $this->filter();

        $this->setFieldset();
        
        $this->slugify();
    }

    
    /**
     * Create an entry in a collection.
     * 
     * @return request
     */
    public function entryCreate()
    {
        if ( ! $this->collection) {
            // TODO: Throw an exception and/or return an error message
            dd('Come on now. You need a collection.');
        }
        
        $validator = $this->runValidation();
        
        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }

        $this->factory = Entry::create($this->slug)
                        ->collection($this->collection)
                        ->with($this->fields)
                        ->date()
                        ->get();

        return $this->save();
    }

    /**
     * Create an entry in a collection.
     * 
     * @return request
     */
    public function entryUpdate()
    {
        $validator = $this->runValidation();

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }

        $this->factory = Entry::create($this->slug)
                        ->collection($this->collection)
                        ->with($this->fields)
                        ->date()
                        ->get();

        return $this->save();
    }

    /**
     * Create a page.
     * 
     * @return request
     */
    public function pageCreate()
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
     * Get the Validator instance
     *
     * @return mixed
     */
    public function runValidation()
    {
        $fields = array_merge($this->fields, ['slug' => 'required']);
        
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
    public function save()
    {
        $this->factory->save();
        
        $this->flash->put('success', true);

        event('content.saved', $this->factory);

        if ($this->redirect) {
            return redirect($this->getRedirect());
        };

        return redirect()->back();
    }

    /**
     * Set the slug based on another field. Defaults to title.
     * 
     * @return void
     */
    public function slugify()
    {
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
    private function setFieldset()
    {
        if ($this->fieldset) {
            $this->fieldset = Fieldset::get($this->fieldset);
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
    }
}