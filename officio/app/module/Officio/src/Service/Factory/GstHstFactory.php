<?php

namespace Officio\Service\Factory;

use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Common\Service\Country;

/**
 * Class GstHstFactory
 * @package Officio
 */
class GstHstFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Country::class => $container->get(Country::class),
        ];
    }

}