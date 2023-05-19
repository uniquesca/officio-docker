<?php

namespace Officio\Service\Factory;

use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;

/**
 * Class SettingsFactory
 * @package Officio
 */
class SystemTriggersFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            'ModuleManager' => $container->get('ModuleManager'),
            'ServiceManager' => $container
        ];
    }

}