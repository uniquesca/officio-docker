<?php


namespace Officio\Service\Factory;

use Psr\Container\ContainerInterface;
use Laminas\View\HelperPluginManager;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;

/**
 * Class Navigation
 * @package Officio
 */
class NavigationFactory extends BaseServiceFactory
{
    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class             => $container->get(Company::class),
            HelperPluginManager::class => $container->get('ViewHelperManager'),
            'router'                   => $container->get('router'),
        ];
    }
}