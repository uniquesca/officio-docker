<?php

namespace Clients\Service\Factory;

use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Common\Service\Encryption;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Service\Roles;
use Officio\Service\SystemTriggers;

class MembersServiceFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class        => $container->get(Company::class),
            Country::class        => $container->get(Country::class),
            Files::class          => $container->get(Files::class),
            Roles::class          => $container->get(Roles::class),
            SystemTriggers::class => $container->get(SystemTriggers::class),
            Encryption::class     => $container->get(Encryption::class),
        ];
    }

}