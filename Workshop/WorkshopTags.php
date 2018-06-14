<?php

namespace Statamic\Addons\Workshop;

use Statamic\API\Str;
use Statamic\API\URL;
use Statamic\API\Crypt;
use Statamic\API\Entry;
use Statamic\API\Helper;
use Statamic\API\Content;
use Statamic\API\Globals;
use Statamic\Extend\Tags;
use Stringy\StaticStringy as Stringy;

class WorkshopTags extends Tags
{
    /**
     * Fields that can be overridden with tag parameters
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
        'slugify'
    ];

    /**
     * The content's id
     *
     * @var string
     */
    private $id;

    /**
     * The middleman. The camelCase handler. The dude.
     * We are using workshop:noun:verb syntax, and
     * this does the magic transformation.
     *
     * @param string $method
     * @param array  $args
     * @return method
     */
    public function __call($method, $args)
    {
        $method = Stringy::camelize(str_replace(':', '_', $method));

        if ($this->isAllowed() && method_exists($this, $method)) {
            return $this->$method();
        }
    }

    /**
     * The {{ workshop:entry:create }} tag
     *
     * @return string
     */
    public function entryCreate()
    {
        $data = $this->getErrorsAndSuccess();

        $html = $this->formOpen('entryCreate');
        $html .= $this->getMetaFields();
        $html .= $this->parse($data);
        $html .= '</form>';

        return $html;
    }

    /**
     * The {{ workshop:entry:create }} tag
     *
     * @return string
     */
    public function entryEdit()
    {
        $entry = $this->getContent();

        $data = array_merge($this->getErrorsAndSuccess(), $entry->data());

        $html = $this->formOpen('entryUpdate');
        $html .= $this->getMetaFields();
        $html .= $this->parse($data);
        $html .= '</form>';

        return $html;
    }

    /**
     * The {{ workshop:entry:delete }} tag
     *
     * @return string
     */
    public function entryDelete()
    {
        $entry = $this->getContent();

        $data = array_merge($this->getErrorsAndSuccess(), $entry->data());

        $html = $this->formOpen('entryDelete');
        $html .= $this->getMetaFields();
        $html .= $this->parse($data);
        $html .= '</form>';

        return $html;
    }

    /**
     * The {{ workshop:page:create }} tag
     *
     * @return string
     */
    public function pageCreate()
    {
        $data = $this->getErrorsAndSuccess();

        $html = $this->formOpen('pageCreate');
        $html .= $this->getMetaFields();
        $html .= $this->parse($data);
        $html .= '</form>';

        return $html;
    }

    /**
     * The {{ workshop:entry:create }} tag
     *
     * @return string
     */
    public function pageEdit()
    {
        $page = $this->getContent();

        $data = array_merge($this->getErrorsAndSuccess(), $page->data());

        $html = $this->formOpen('pageUpdate');
        $html .= $this->getMetaFields();
        $html .= $this->parse($data);
        $html .= '</form>';

        return $html;
    }

    /**
     * The {{ workshop:page:delete }} tag
     *
     * @return string
     */
    public function pageDelete()
    {
        $page = $this->getContent();

        $data = array_merge($this->getErrorsAndSuccess(), $page->data());

        $html = $this->formOpen('pageDelete');
        $html .= $this->getMetaFields();
        $html .= $this->parse($data);
        $html .= '</form>';

        return $html;
    }

    /**
     * The {{ workshop:global:edit }} tag
     *
     * @return string
     */
    public function globalEdit()
    {
        if ($this->get('id')) {
            $global = $this->getContent();

        } else {
            $handle = $this->get(['handle', 'slug', 'set'], 'global');
            $global = Globals::getBySlug($handle);
            $this->id = $global->id();
        }

        $data = array_merge($this->getErrorsAndSuccess(), $global->data());

        $html = $this->formOpen('globalUpdate');
        $html .= $this->getMetaFields();
        $html .= $this->parse($data);
        $html .= '</form>';

        return $html;
    }

    /**
     * Maps to {{ form:success }}
     *
     * @return bool
     */
    public function success()
    {
        return $this->flash->exists('success');
    }

    /**
     * Get content by URL or id, falling back to the current page/entry
     *
     * @return Content
     */
    private function getContent()
    {
        $url = $this->get('url', URL::getCurrent());

        $content = ($this->get('id')) ? Content::uuidRaw($this->get('id')) : Content::getRaw($url);

        $this->id = $content->id();

        return $content;
    }

    /**
     * Encypts any special meta fields set as tag parameters
     * and sets them in a special HTML hidden input field.
     *
     * @return string
     */
    private function getMetaFields()
    {
        $meta = array_intersect_key($this->parameters, array_flip($this->meta));
        $meta['id'] = $this->id;

        return '<input type="hidden" name="_meta" value="'. Crypt::encrypt($meta) .'" />';
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

    /**
     * Does this form have errors?
     *
     * @return bool
     */
    private function hasErrors()
    {
        return (session()->has('errors'))
               ? session()->get('errors')->hasBag('workshop')
               : false;
    }

    /**
     * Get any errors or success messages ready to parse
     *
     * @return array
     */
    private function getErrorsAndSuccess()
    {
        $data = [];

        if ($this->hasErrors()) {
            $data['errors'] = ($bag = session('errors')->getBag('workshop'))->all();
            $data['error'] = collect($bag)->mapWithKeys(function ($errors, $field) {
                return [Str::removeLeft($field, 'fields.') => $errors[0]];
            })->all();
        }

        if ($this->flash->exists('success')) {
            $data['success'] = true;
        }

        return $data;
    }
}
