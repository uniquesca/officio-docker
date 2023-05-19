<?php

namespace Officio\Service;

use Officio\Common\Service\BaseService;

/**
 * Class AngularApplicationHost
 * @package Officio\Service
 */
class AngularApplicationHost extends BaseService
{

    /**
     * Serves iframe html code pointing to the angular application
     * @param $iframeUrl
     * @param array $attributes
     * @param false $print
     * @return string
     */
    public function hostViaIframe($iframeUrl, $attributes = [], $print = false)
    {
        if (isset($attributes['src'])) {
            unset($attributes['src']);
        }
        foreach ($attributes as $attr => &$value) {
            $value = sprintf('%s="%s"', addslashes($attr ?? ''), addslashes($value ?? ''));
        }
        $attrStr    = implode(' ', $attributes);
        $iframeCode = '<iframe src="' . addslashes($iframeUrl ?? '') . '" ' . $attrStr . '></iframe>';
        if ($print) {
            echo $iframeCode;
            exit;
        }
        return $iframeCode;
    }

    /**
     * Wraps HTML code into an iframe with given attributes
     * @param $html
     * @param array $attributes
     * @return string
     */
    public function wrapHtmlIntoIframe($html, $attributes = [])
    {
        // We don't allow src here as we are going to use srcdoc instead
        if (isset($attributes['src'])) {
            unset($attributes['src']);
        }

        // Prepare attributes
        if (!isset($attributes['frameBorder'])) {
            $attributes['frameBorder'] = 0;
        }
        foreach ($attributes as $attr => &$value) {
            $value = sprintf('%s="%s"', $attr, $value);
        }
        $attrStr = implode(' ', $attributes);

        return '<iframe srcdoc="' . htmlspecialchars($html ?? '') . '" ' . $attrStr . '></iframe>';
    }

    /**
     * Extracts entry point (index.html) from Angular application and processes it, so it can be embedded.
     * @param $path
     * @param $baseUrl
     * @return string|string[]|null
     * @throws \Exception
     */
    public function getEntryHtml($path, $baseUrl)
    {
        if (!is_dir($path)) {
            throw new \Exception('Angular application path has to exist and be readable.');
        }

        // Make sure base URL ends with slash
        if (substr($baseUrl ?? '', -1) !== '/') {
            $baseUrl .= '/';
        }

        $dirContents = scandir($path);
        $html        = '';
        $css         = $js = [];
        foreach ($dirContents as $item) {
            $file = $path . '/' . $item;
            if (is_dir($file)) {
                continue;
            }

            // Getting entry HTML
            if ($item === 'index.html') {
                $html = htmlspecialchars_decode(file_get_contents($file));
                continue;
            }

            $fileType = pathinfo($file, PATHINFO_EXTENSION);
            if (mb_strtolower($fileType) == 'css') {
                $css[] = $baseUrl . '/' . $item;
            } elseif (mb_strtolower($fileType) == 'js') {
                $js[] = $baseUrl . '/' . $item;
            }
        }

        // Let's replace base URL, script and styles URLs in the resulting HTML
        $patterns     = [
            'base' => '/(<base href=").*?("(?:.|\s)*?>)/m',
            'css' => '/(<link(?:.|\s)*?href=")(.*?)("(?:.|\s)*?>)/m',
            'js' => '/(<script(?:.|\s)*?src=")(.*?)("(?:.|\s)*?>)/m'
        ];
        $replacements = [
            'base' => sprintf('$1%s$2', $baseUrl),
            'css' => sprintf('$1%s$2$3', $baseUrl),
            'js' => sprintf('$1%s$2$3', $baseUrl)
        ];
        return preg_replace($patterns, $replacements, $html);
    }

    /**
     * Renders JS <script></script> tags with the code putting config into session storage, so
     * these variables can be used by Angular application.
     * @param $config
     * @return string
     */
    public function renderConfigurationScript($config)
    {
        foreach ($config as $var => &$value) {
            if (is_string($value)) {
                $value = sprintf('window.sessionStorage.setItem(\'%s\', \'%s\');', $var, $value);
            } elseif (is_numeric($value)) {
                $value = sprintf('window.sessionStorage.setItem(\'%s\', %s);', $var, $value);
            } else {
                unset($config[$var]);
            }
        }

        return sprintf('<script type="text/javascript">%s</script>', implode("\n", $config));
    }


}