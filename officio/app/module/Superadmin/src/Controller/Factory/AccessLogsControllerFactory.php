<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\Clients;
use Officio\Service\Company;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\AccessLogs;
use Officio\BaseControllerFactory;

/**
 * This is the factory for AccessLogsController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class AccessLogsControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            AccessLogs::class => $container->get(AccessLogs::class),
            Clients::class    => $container->get(Clients::class),
            Company::class    => $container->get(Company::class)
        ];
    }

}

