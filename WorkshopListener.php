<?php

namespace Statamic\Addons\Workshop;

use Statamic\API\Form;
use Statamic\API\Crypt;
use Statamic\API\Entry;
use Statamic\API\Request;
use Statamic\API\Fieldset;
use Statamic\Extend\Listener;
use Illuminate\Http\Response;
use Stringy\StaticStringy as Stringy;
use Statamic\CP\Publish\ValidationBuilder;

class WorkshopListener extends Listener
{
    /**
     * The events to be listened for, and the methods to call.
     *
     * @var array
     */
    public $events = [
        'Workshop.entry.create'   => 'entryCreate',
        'Workshop.entry.edit'     => 'entryEdit',
        'Workshop.entry.delete'   => 'entryDelete',
        'Workshop.page.create'    => 'pageCreate',
        'Workshop.page.edit'      => 'pageEdit',
        'Workshop.page.delete'    => 'pageDelete'
    ];
    
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
    private $parent;
    
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
        $validator = $this->runValidation();

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }

        $entry = Entry::create($this->slug)
                        ->collection($this->collection)
                        ->with($this->fields)
                        ->date()
                        ->get();

        $entry->save();

        event('content.saved', $entry);

        if ($this->redirect) {
            return redirect($redirect);
        };

        return redirect()->back();
    }
    
        /**
         * Get the Validator instance
         *
         * @return mixed
         */
        public function runValidation()
        {
            $builder = new ValidationBuilder(['fields' => $this->fields], $this->fieldset);

            $builder->build();
            
            return \Validator::make($this->fields, $builder->rules());
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
     * Filter out any meta fields from the request object
     * and assign them to class variables, leaving you a
     * a nice and clean $fields variable to work with.
     * 
     * @return void
     */
    private function filter()
    {
        foreach ($this->fields as $key => $field) {
            if (in_array($key, $this->meta)) {
                $this->{$key} = $field;
                unset($this->fields[$key]);
            }
        }
    }
    
    private function setFieldset()
    {
        if ($this->fieldset) {
            $this->fieldset = Fieldset::get($this->fieldset);
        }
    }
}