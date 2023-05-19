<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\Clients;
use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\AccessLogs;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Service\Roles;

/**
 * This is the factory for RolesController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class RolesControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            AccessLogs::class => $container->get(AccessLogs::class),
            Company::class => $container->get(Company::class),
            Clients::class => $container->get(Clients::class),
            Files::class => $container->get(Files::class),
            Roles::class => $container->get(Roles::class)
        ];
    }

}
