<?php

namespace Officio\View\Helper;
use Laminas\View\Helper\HeadScript;
use Officio\Common\Service\Log;

class MinJs extends HeadScript
{

    /** @var Log */
    protected $_log;

    public function __construct(Log $log)
    {
        parent::__construct();
        $this->_log = $log;
    }

    public function __toString()
    {
        $layout = $this->getView()->layout();

        /** @var HeadScript $headLink */
        $headScript = $this->getView()->headScript();

        $items  = array();
        $indent = $headScript->getIndent();

        if ($this->view) {
            $useCdata = $this->view->doctype()->isXhtml() ? true : false;
        } else {
            $useCdata = $this->useCdata ? true : false;
        }
        $escapeStart = ($useCdata) ? '//<![CDATA[' : '//<!--';
        $escapeEnd   = ($useCdata) ? '//]]>' : '//-->';

        $defaultWeight = 100;

        $weight = $defaultWeight + 100;
        foreach ($headScript->getIterator() as $item) {
            // get script weight
            if (isset($item->attributes['weight'])) {
                $w = (int)$item->attributes['weight'];
            } else {
                $w = &$weight;
            }
            while (isset($items[$w])) {
                ++$w;
            }

            if ($this->_isNeedToMinify($item)) {
                $path = 'public/' . str_ireplace($layout->getVariable('topBaseUrl'), '', $item->attributes ['src']);

                if (file_exists($path)) {
                    if (strpos($item->attributes ['src'], '?') === false) {
                        $item->attributes ['src'] .= '?' . filemtime($path);
                    } else {
                        $item->attributes ['src'] .= '&' . filemtime($path);
                    }
                }
            }
            $items[$w] = $this->itemToString($item, $indent, $escapeStart, $escapeEnd);
        }

        return $indent . implode($headScript->escape($headScript->getSeparator()) . $indent, $items);
    }

    protected function _isNeedToMinify($item)
    {
        return isset($item->attributes ['src'])
            && !empty($item->attributes ['src'])
            && !isset($item->attributes['minify_disabled']);
    }
}