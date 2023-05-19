<?php

namespace Profile\Controller\Factory;

use Psr\Container\ContainerInterface;
use Officio\Common\Service\AccessLogs;
use Officio\BaseControllerFactory;
use Officio\Service\AuthHelper;
use Officio\Common\Service\Encryption;
use Officio\Service\Users;

/**
 * This is the factory for IndexController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class IndexControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            AccessLogs::class => $container->get(AccessLogs::class),
            Users::class      => $container->get(Users::class),
            AuthHelper::class => $container->get(AuthHelper::class),
            Encryption::class => $container->get(Encryption::class)
        ];
    }

}
