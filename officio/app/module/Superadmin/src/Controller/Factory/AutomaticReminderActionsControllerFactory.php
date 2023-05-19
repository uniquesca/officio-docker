<?php

namespace Superadmin\Controller\Factory;

use Officio\BaseControllerFactory;
use Psr\Container\ContainerInterface;
use Officio\Service\AutomaticReminders;

/**
 * This is the factory for AutomaticReminderActionsController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class AutomaticReminderActionsControllerFactory extends BaseControllerFactory
{
    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            AutomaticReminders::class => $container->get(AutomaticReminders::class)
        ];
    }
}

