<?php

namespace UformsV2\Controller\Factory;

use Psr\Container\ContainerInterface;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Session\SessionManager;
use Officio\BaseControllerFactory;
use Officio\Service\AngularApplicationHost;
use Files\Service\Files;
use Forms\Service\Forms;

/**
 * This is the factory for IndexController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class IndexControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            SessionManager::class         => $container->get(SessionManager::class),
            AngularApplicationHost::class => $container->get(AngularApplicationHost::class),
            ModuleManager::class          => $container->get(ModuleManager::class),
            Forms::class                  => $container->get(Forms::class),
            Files::class                  => $container->get(Files::class),
        ];
    }

}
