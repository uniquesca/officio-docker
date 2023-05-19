<?php

namespace Clients\Service\Clients\Factory;

use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Common\Service\Encryption;
use Officio\Service\Roles;

class FieldsFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class    => $container->get(Company::class),
            Files::class      => $container->get(Files::class),
            Country::class    => $container->get(Country::class),
            Roles::class      => $container->get(Roles::class),
            Encryption::class => $container->get(Encryption::class),
        ];
    }

}