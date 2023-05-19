<?php

namespace Superadmin\Controller\Factory;

use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Email\ServerSuggestions;

/**
 * This is the factory for ManageDefaultMailServersController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageDefaultMailServersControllerFactory extends BaseControllerFactory
{
    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            ServerSuggestions::class => $container->get(ServerSuggestions::class),
        ];
    }
}
