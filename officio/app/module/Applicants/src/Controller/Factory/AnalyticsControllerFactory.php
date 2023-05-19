<?php

namespace Applicants\Controller\Factory;

use Clients\Service\Clients;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Clients\Service\Analytics;

/**
 * This is the factory for IndexController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class AnalyticsControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Analytics::class => $container->get(Analytics::class),
            Clients::class => $container->get(Clients::class)
        ];
    }

}