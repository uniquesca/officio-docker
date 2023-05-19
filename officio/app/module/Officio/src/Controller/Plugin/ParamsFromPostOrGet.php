<?php

namespace Officio\Controller\Plugin;

use Laminas\Mvc\Controller\Plugin\Params;

/**
 * Class ParamsFromPostOrGet
 * @package Officio\Controller\Plugin
 * @deprecated
 */
class ParamsFromPostOrGet extends Params {

    /**
     * Looks up for parameter(s) in POST, and if not found in GET (query string).
     * It's deprecated, in the new code make sure param can be passed only via one
     * of those methods, not both.
     * @param null $param
     * @param null $default
     * @return $this|mixed|ParamsFromPostOrGet|null
     * @deprecated
     */
    public function __invoke($param = null, $default = null)
    {
        if ($param == null) {
            return $this;
        }

        $value = $this->fromPost($param);
        if ($value === null) {
            return $this->fromQuery($param, $default);
        }

        return $value;
    }

}