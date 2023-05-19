<?php

namespace Documents\Controller\Factory;

use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;

/**
 * This is the factory for ManagerController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManagerControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Files::class => $container->get(Files::class)
        ];
    }

}
