<?php

namespace Files\Service\Factory;

use Psr\Container\ContainerInterface;
use Laminas\View\HelperPluginManager;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Common\Service\Encryption;

class FilesFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Encryption::class          => $container->get(Encryption::class),
            HelperPluginManager::class => $container->get('ViewHelperManager')
        ];
    }

}