<?php

namespace Statamic\Addons\Workshop;

use Statamic\API\Entry;
use Statamic\API\Helper;
use Statamic\API\Content;
use Statamic\Extend\Tags;
use Stringy\StaticStringy as Stringy;

class WorkshopTags extends Tags
{
    /**
     * The middleman. The camelCase handler. The dude.
     * We are using workshop:noun:verb syntax, so this
     * does the magic transformation.
     *
     * @param string $method
     * @param array  $args
     * @return method
     */
    public function __call($method, $args)
    {
        $method = Stringy::camelize(str_replace(':', '_', $method));
        
        if (method_exists($this, $method)) {
            return $this->$method();
        }
    }
    

    /**
     * The {{ workshop:entry:create }} tag
     *
     * @return string|array
     */
    public function entryCreate()
    {
        $data = [];

        $html = $this->formOpen('entry.create');
        
        $html .= $this->parse($data);

        $html .= '</form>';

        return $html;
    }
    
    /**
     * Open a form tag
     *
     * @param  string $action
     * @return string
     */
    protected function formOpen($action)
    {
        $attr_str = '';
        if ($attrs = $this->getList('attr')) {
            foreach ($attrs as $attr) {
                list($param, $value) = explode(':', $attr);
                $attr_str .= $param . '="' . $value . '" ';
            }
        }

        if ($this->getBool('files')) {
            $attr_str .= 'enctype="multipart/form-data"';
        }

        $action = $this->eventUrl($action);

        $html = '<form method="POST" action="'.$action.'" '.$attr_str.'>';

        return $html;
    }
}
