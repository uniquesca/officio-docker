<?php

namespace Superadmin\Controller\Factory;

use Officio\BaseControllerFactory;
use Officio\Service\AutomaticReminders;
use Psr\Container\ContainerInterface;

/**
 * This is the factory for AutomaticReminderTriggersController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class AutomaticReminderTriggersControllerFactory extends BaseControllerFactory
{
    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            AutomaticReminders::class => $container->get(AutomaticReminders::class)
        ];
    }
}
