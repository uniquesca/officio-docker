<?php

namespace Superadmin\Controller\Factory;

use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Service\Tickets;
use Psr\Container\ContainerInterface;

/**
 * This is the factory for TicketsController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class TicketsControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class => $container->get(Company::class),
            Tickets::class => $container->get(Tickets::class)
        ];
    }

}

