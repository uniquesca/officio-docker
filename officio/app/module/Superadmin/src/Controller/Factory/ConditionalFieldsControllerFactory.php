<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\Clients;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Service\ConditionalFields;

/**
 * This is the factory for ConditionalFieldsController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ConditionalFieldsControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Clients::class           => $container->get(Clients::class),
            Company::class           => $container->get(Company::class),
            ConditionalFields::class => $container->get(ConditionalFields::class)
        ];
    }

}

