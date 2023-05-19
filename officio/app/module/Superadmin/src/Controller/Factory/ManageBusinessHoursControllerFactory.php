<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\BusinessHours;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;

/**
 * This is the factory for ManageBusinessHoursController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageBusinessHoursControllerFactory extends BaseControllerFactory
{
    protected function retrieveAdditionalServiceList(ContainerInterface $container) {
        return [
            BusinessHours::class => $container->get(BusinessHours::class)
        ];
    }
}
