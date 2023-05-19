<?php

namespace Clients\Service\Clients\Factory;

use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;

class ClientsDependentsFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class => $container->get(Company::class),
            Files::class => $container->get(Files::class),
        ];
    }

}