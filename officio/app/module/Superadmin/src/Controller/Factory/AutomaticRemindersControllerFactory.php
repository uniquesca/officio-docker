<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\Clients;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\AutomaticReminders;
use Officio\Service\Company;

/**
 * This is the factory for AutomaticRemindersController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class AutomaticRemindersControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class => $container->get(Company::class),
            Clients::class => $container->get(Clients::class),
            AutomaticReminders::class => $container->get(AutomaticReminders::class)
        ];
    }

}
