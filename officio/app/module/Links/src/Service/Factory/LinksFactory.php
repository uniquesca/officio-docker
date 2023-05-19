<?php

namespace Links\Service\Factory;

use Clients\Service\Members;
use Psr\Container\ContainerInterface;
use Laminas\View\HelperPluginManager;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Roles;

class LinksFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Members::class             => $container->get(Members::class),
            Roles::class               => $container->get(Roles::class),
            HelperPluginManager::class => $container->get('ViewHelperManager'),
        ];
    }

}