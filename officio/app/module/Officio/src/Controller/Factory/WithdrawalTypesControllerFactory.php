<?php

namespace Officio\Controller\Factory;

use Clients\Service\Clients;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;

/**
 * This is the factory for WithdrawalTypesController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class WithdrawalTypesControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Clients::class => $container->get(Clients::class)
        ];
    }

}
