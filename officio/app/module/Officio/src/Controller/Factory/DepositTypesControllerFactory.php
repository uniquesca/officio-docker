<?php

namespace Officio\Controller\Factory;

use Clients\Service\Clients;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;

/**
 * This is the factory for DepositTypesController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class DepositTypesControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Clients::class => $container->get(Clients::class)
        ];
    }

}
