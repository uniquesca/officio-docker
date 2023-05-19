<?php

namespace Officio\View\Helper;
use Laminas\View\Helper\HeadLink;
use Minify_Cache_File;
use Minify_Controller_Files;
use Minify_Env;
use Minify_Source_Factory;
use Officio\Minify;
use Officio\Common\Service\Log;
use stdClass;

class MinStyleSheets extends HeadLink
{
    private $cacheDir;
    private $combine;

    /** @var Log */
    protected $_log;

    public function __construct(Log $log, array $minifierConfig)
    {
        parent::__construct();
        $this->_log     = $log;
        $this->cacheDir = '/' . trim($minifierConfig['cache_dir'], '/') . '/';
        $this->combine  = (bool)$minifierConfig['enabled'];
    }

    public function minStyleSheets()
    {
        if ($this->combine) {
            return $this->toString();
        } else {
            return $this->view->headLink();
        }
    }

    public function toString($indent = null)
    {
        $items                     = array();
        $arrCssFiles               = array();
        $arrConditionalStylesheets = array();

        $layout = $this->getView()->layout();
        /** @var HeadLink $headLink */
        $headLink = $this->getView()->headLink();

        foreach ($headLink->getIterator() as $item) {
            if ($item->type == 'text/css' && $item->conditionalStylesheet === false) {
                $arrCssFiles[$item->media][] = str_replace($layout->getVariable('topBaseUrl'), 'public/', $item->href);
            } else {
                $arrConditionalStylesheets[] = $item;
            }
        }

        if (!empty($arrCssFiles)) {
            $cache         = new Minify_Cache_File('public/' . $this->cacheDir);
            $minify        = new Minify($cache);
            $env           = new Minify_Env();
            $sourceFactory = new Minify_Source_Factory($env, array(), $cache);
            $controller    = new Minify_Controller_Files($env, $sourceFactory);

            foreach ($arrCssFiles as $media => $styles) {
                // setup serve and controller options
                $options = array(
                    'concatOnly'   => 1,
                    'files'        => $styles,
                    // 'maxAge'       => 60 * 60 * 24 * 30 * 12, // ~ a year
                    'minifier'     => 'Minify::nullMinifier',
                    'fileExtension' => 'css'
                );

                // Cache CSS
                $cacheResult = $minify->cache($controller, $options);
                if (empty($cacheResult['cacheId'])) {
                    $logDetails = 'topBaseUrl: ' . $layout->getVariable('topBaseUrl') . PHP_EOL;
                    foreach ($styles as $path) {
                        if (!file_exists($path)) {
                            $logDetails .= 'FILE DOES NOT EXISTS: ' . $path . PHP_EOL;
                        }
                    }
                    $logDetails .= 'All files: ' . PHP_EOL . print_r($styles, true) . PHP_EOL;
                    $this->_log->debugErrorToFile('CSS minification failed', $logDetails, 'minify');
                } else {
                    $cssFilePath = $this->cacheDir . $cacheResult['cacheId'];

                    $item                        = new stdClass();
                    $item->rel                   = 'stylesheet';
                    $item->type                  = 'text/css';
                    $item->href                  = $layout->getVariable('topBaseUrl') . $cssFilePath . '?v=' . filemtime('public/' . $cssFilePath);
                    $item->media                 = $media;
                    $item->conditionalStylesheet = false;
                    $items[]                     = $this->itemToString($item);
                }
            }

            // Show conditional css at the bottom
            foreach ($arrConditionalStylesheets as $item) {
                $items[] = $this->itemToString($item);
            }
        }

        return $indent . implode($headLink->escape($headLink->getContainer()->getSeparator()) . $indent, $items);
    }
}
