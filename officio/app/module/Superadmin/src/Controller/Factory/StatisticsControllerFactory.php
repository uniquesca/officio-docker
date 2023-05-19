<?php

namespace Superadmin\Controller\Factory;

use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Statistics;

/**
 * This is the factory for StatisticsController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class StatisticsControllerFactory extends BaseControllerFactory
{
    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Statistics::class => $container->get(Statistics::class)
        ];
    }
}
