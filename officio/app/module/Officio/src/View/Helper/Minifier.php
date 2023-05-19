<?php

namespace Officio\View\Helper;

use Laminas\View\Helper\HeadScript;
use Minify_Cache_File;
use Minify_Controller_Files;
use Minify_Env;
use Minify_Source_Factory;
use Officio\Minify;
use Officio\Common\Service\Log;
use Tholu\Packer\Packer;
use stdClass;

class Minifier extends HeadScript
{

    private $cacheDir;
    private $combine;
    private $obfuscate;

    /** @var Log */
    protected $_log;

    public function __construct(Log $log, array $minifierConfig)
    {
        parent::__construct();
        $this->_log      = $log;
        $this->cacheDir  = '/' . trim($minifierConfig['cache_dir'], '/') . '/';
        $this->combine   = (bool)$minifierConfig['enabled'];
        $this->obfuscate = (bool)$minifierConfig['js_obfuscation_enabled'];
    }

    public function minify($groupId, $getCacheContent = false)
    {
        $view = $this->getView();

        $return = '';
        $indent = false;

        $arrGroupConfig = require 'config/minify.config.php';
        if (array_key_exists($groupId, $arrGroupConfig)) {
            $groupType = strpos($groupId, 'css') === false ? 'js' : 'css';

            if ($view && !$getCacheContent) {
                $layout     = $view->layout();
                $headScript = $this->getView()->headScript();

                $indent = $headScript->getIndent();

                if ($this->getView()) {
                    $useCdata = $this->getView()->doctype()->isXhtml() ? true : false;
                } else {
                    $useCdata = $headScript->useCdata ? true : false;
                }
                $escapeStart = ($useCdata) ? '//<![CDATA[' : '//<!--';
                $escapeEnd   = ($useCdata) ? '//]]>' : '//-->';
            }

            $items = array();

            if ($this->combine || $getCacheContent) {
                $cache = new Minify_Cache_File('public/' . $this->cacheDir);
                $minify = new Minify($cache);
                $env           = new Minify_Env();
                $sourceFactory = new Minify_Source_Factory($env, array(), $cache);
                $controller    = new Minify_Controller_Files($env, $sourceFactory);

                // setup serve and controller options
                $options = array(
                    'concatOnly'    => 1,
                    'files'         => $arrGroupConfig[$groupId],
                    // 'maxAge'       => 60 * 60 * 24 * 30 * 12, // ~ a year
                    'minifier'     => 'Minify::nullMinifier',
                    'fileExtension' => $groupType
                );

                // handle request
                $cacheResult = $minify->cache($controller, $options);
                if (empty($cacheResult['cacheId'])) {
                    $this->_log->debugErrorToFile('Minification failed', print_r($arrGroupConfig[$groupId], true), 'minify');
                } else {
                    $minifiedCachePath = $this->cacheDir . $cacheResult['cacheId'];

                    // Additionally pack JS code
                    if ($cacheResult['recached'] && !in_array($groupId, array('ext', 'jq', 'jquery-ui')) && $this->obfuscate && $groupType == 'js') {
                        $content = file_get_contents('public/' . $minifiedCachePath);
                        $packer  = new Packer($content, 'Normal', true, false, false);
                        $content = $packer->pack();
                        file_put_contents('public/' . $minifiedCachePath, $content);
                    }

                    if ($getCacheContent) {
                        $return = file_get_contents('public/' . $minifiedCachePath);
                    } else {
                        $item = new stdClass();
                        if ($groupType == 'js') {
                            $item->source             = '';
                            $item->type               = 'text/javascript';
                            $item->attributes ['src'] = $layout->getVariable('topBaseUrl') . $minifiedCachePath . '?v=' . filemtime('public/' . $minifiedCachePath);
                            $items[]                  = $this->itemToString($item, $indent, $escapeStart, $escapeEnd);
                        } else {
                            $item->rel  = 'stylesheet';
                            $item->type = 'text/css';
                            $item->href = $layout->getVariable('topBaseUrl') . $minifiedCachePath . '?v=' . filemtime('public/' . $minifiedCachePath);
                            $items[]    = $this->view->headLink()->itemToString($item);
                        }
                    }
                }
            } else {
                $publicPath = realpath(getcwd() . '/public/');
                if ($groupType == 'js') {
                    foreach ($arrGroupConfig[$groupId] as $filePath) {
                        $item                     = new stdClass();
                        $item->source             = '';
                        $item->type               = 'text/javascript';
                        $item->attributes ['src'] = str_replace($publicPath, '', realpath($filePath));
                        $items[] = parent::itemToString($item, $indent, $escapeStart, $escapeEnd);
                    }
                } else {
                    foreach ($arrGroupConfig[$groupId] as $filePath) {
                        $item       = new stdClass();
                        $item->rel  = 'stylesheet';
                        $item->type = 'text/css';
                        $item->href = str_replace($publicPath, '', realpath($filePath));
                        $items[] = $this->view->headLink()->itemToString($item);
                    }
                }
            }

            if (!$getCacheContent && $view) {
                return $indent . implode($headScript->escape($headScript->getSeparator()) . $indent, $items);
            }
        }

        return $return;
    }

}
