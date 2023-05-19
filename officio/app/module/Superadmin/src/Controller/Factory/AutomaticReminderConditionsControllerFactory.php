<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\Clients;
use Forms\Service\Forms;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\AutomaticReminders;
use Officio\Service\Company;

/**
 * This is the factory for AutomaticReminderConditionsController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class AutomaticReminderConditionsControllerFactory extends BaseControllerFactory
{
    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            AutomaticReminders::class => $container->get(AutomaticReminders::class),
            Clients::class            => $container->get(Clients::class),
            Company::class            => $container->get(Company::class),
            Forms::class              => $container->get(Forms::class),
        ];
    }
}

