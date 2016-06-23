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
     * The {{ workshop:page:create }} tag
     *
     * @return string|array
     */
    public function pageCreate()
    {
        $data = [];

        $html = $this->formOpen('page.create');
        
        $html .= $this->parse($data);

        $html .= '</form>';

        return $html;
    }
}
