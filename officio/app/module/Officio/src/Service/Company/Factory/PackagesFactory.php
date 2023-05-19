<?php

namespace Officio\Service\Company\Factory;

use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Roles;
use Officio\Service\SystemTriggers;

/**
 * Class PackagesFactory
 * @package Officio
 */
class PackagesFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Roles::class => $container->get(Roles::class),
            SystemTriggers::class => $container->get(SystemTriggers::class),
        ];
    }

}