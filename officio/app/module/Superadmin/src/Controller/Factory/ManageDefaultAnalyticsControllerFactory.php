<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\Clients;
use Officio\BaseControllerFactory;
use Clients\Service\Analytics;
use Officio\Service\Company;
use Psr\Container\ContainerInterface;

/**
 * This is the factory for ManageDefaultAnalyticsController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageDefaultAnalyticsControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class => $container->get(Company::class),
            Clients::class => $container->get(Clients::class),
            Analytics::class => $container->get(Analytics::class),
        ];
    }

}

