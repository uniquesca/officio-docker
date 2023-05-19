<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\Clients;
use Officio\Service\Company;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;

/**
 * This is the factory for BaseControllerFactory. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageVacControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Clients::class => $container->get(Clients::class),
            Company::class => $container->get(Company::class),
        ];
    }

}
