<?php

namespace Clients\Service\Factory;

use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;
use Officio\Service\Roles;
use Officio\Service\Users;

class BusinessHoursFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class => $container->get(Company::class),
            Roles::class => $container->get(Roles::class),
            Users::class => $container->get(Users::class)
        ];
    }

}