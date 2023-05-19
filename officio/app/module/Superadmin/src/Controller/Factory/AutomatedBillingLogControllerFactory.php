<?php

namespace Superadmin\Controller\Factory;

use Officio\BaseControllerFactory;
use Psr\Container\ContainerInterface;
use Officio\Service\AutomatedBillingLog;

/**
 * This is the factory for AutomatedBillingLogController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class AutomatedBillingLogControllerFactory extends BaseControllerFactory
{
    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            AutomatedBillingLog::class => $container->get(AutomatedBillingLog::class)
        ];
    }
}
