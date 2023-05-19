<?php

namespace Superadmin\Controller\Factory;

use Psr\Container\ContainerInterface;
use Laminas\Session\SessionManager;
use Officio\Service\AuthHelper;
use Officio\BaseControllerFactory;

/**
 * This is the factory for AuthController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class AuthControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            SessionManager::class => $container->get(SessionManager::class),
            AuthHelper::class     => $container->get(AuthHelper::class),
        ];
    }

}
