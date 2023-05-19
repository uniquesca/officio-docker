<?php

namespace TrustAccount\Controller\Factory;

use Clients\Service\Clients;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Users;

/**
 * This is the factory for EditController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class EditControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Users::class => $container->get(Users::class),
            Clients::class => $container->get(Clients::class)
        ];
    }

}
