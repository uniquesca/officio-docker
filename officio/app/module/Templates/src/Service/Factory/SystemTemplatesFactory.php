<?php

namespace Templates\Service\Factory;

use Psr\Container\ContainerInterface;
use Laminas\View\HelperPluginManager;
use Officio\Common\Service\Factory\BaseServiceFactory;

class SystemTemplatesFactory extends BaseServiceFactory
{
    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            HelperPluginManager::class => $container->get('ViewHelperManager'),
        ];
    }

}