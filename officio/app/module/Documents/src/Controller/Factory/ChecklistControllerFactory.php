<?php

namespace Documents\Controller\Factory;

use Clients\Service\Clients;
use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;

/**
 * This is the factory for ChecklistController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ChecklistControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Clients::class => $container->get(Clients::class),
            Files::class => $container->get(Files::class)
        ];
    }

}
