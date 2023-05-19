<?php

namespace Api\Controller\Factory;

use Clients\Service\Clients;
use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\AuthHelper;
use Officio\Service\AutomaticReminders;
use Officio\Service\Company;
use Officio\Service\SystemTriggers;
use Templates\Service\Templates;

class GvControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class            => $container->get(Company::class),
            Clients::class            => $container->get(Clients::class),
            AutomaticReminders::class => $container->get(AutomaticReminders::class),
            AuthHelper::class         => $container->get(AuthHelper::class),
            Files::class              => $container->get(Files::class),
            Templates::class          => $container->get(Templates::class),
            SystemTriggers::class     => $container->get(SystemTriggers::class),
        ];
    }

}