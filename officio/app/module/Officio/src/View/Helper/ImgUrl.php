<?php

namespace Officio\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class ImgUrl extends AbstractHelper
{

    protected $path;
    protected $view;

    public function __invoke($path)
    {
        if (!empty($path)) {
            $this->path = $path;
        }

        return $this->getImgUrl();
    }


    /**
     * Take hex value of md5 of $path. Get the ord value of the last
     * hex char. Output it mod $NUM_ALIASES
     * @param $path
     * @param $NUM_ALIASES
     * @return int
     */
    private function path_to_origin_suffix($path, $NUM_ALIASES = 3)
    {
        if ($NUM_ALIASES <= 1) {
            return 0;
        }

        $hex = md5($path);

        return ord($hex[31] ?? '') % $NUM_ALIASES;
    }


    public function getImgUrl()
    {
        $view = $this->getView()->layout();
        $path = $this->path;

        // Remove full path, if it exists
        $path = str_replace($view->getVariable('imagesUrl'), '', $path);
        $path = str_replace($view->getVariable('topImagesUrl'), '', $path);

        $pos = strpos($path, '/');
        if ($pos === false || $pos != 0) {
            $path = sprintf('/%s', $path);
        }

        if (!empty($view->getVariable('booUseGeneralImgUrl'))) {
            $imgUrl = $view->getVariable('imagesUrl') . $path;
        } else {
            $suffix = $this->path_to_origin_suffix($path, $view->getVariable('staticAliasesCount'));
            $suffix += 1;

            $array  = explode('.', $view->getVariable('staticUrl') ?? '', 2);
            $host   = "$array[0]$suffix.$array[1]";
            $imgUrl = $host . '/images' . $path;
        }

        return $imgUrl;
    }
}