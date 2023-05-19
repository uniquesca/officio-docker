<?php

namespace Officio\Service\AutomaticReminders\Factory;

use Clients\Service\Clients;
use Clients\Service\Members;
use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Mailer\Service\Mailer;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Service\SystemTriggers;
use Officio\Service\Users;
use Tasks\Service\Tasks;

class ActionsFactory extends BaseServiceFactory {

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class        => $container->get(Company::class),
            Members::class        => $container->get(Members::class),
            Users::class          => $container->get(Users::class),
            Country::class        => $container->get(Country::class),
            Files::class          => $container->get(Files::class),
            Clients::class        => $container->get(Clients::class),
            Mailer::class         => $container->get(Mailer::class),
            SystemTriggers::class => $container->get(SystemTriggers::class),
            Tasks::class          => $container->get(Tasks::class),
        ];
    }

}