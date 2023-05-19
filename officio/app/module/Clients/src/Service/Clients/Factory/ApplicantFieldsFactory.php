<?php

namespace Clients\Service\Clients\Factory;

use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\Roles;

class ApplicantFieldsFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class    => $container->get(Company::class),
            Roles::class      => $container->get(Roles::class),
            Encryption::class => $container->get(Encryption::class),
        ];
    }

}