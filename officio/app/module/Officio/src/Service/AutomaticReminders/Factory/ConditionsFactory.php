<?php

namespace Officio\Service\AutomaticReminders\Factory;

use Clients\Service\Clients;
use Forms\Service\Forms;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;
use Officio\Common\Service\Country;

class ConditionsFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Clients::class => $container->get(Clients::class),
            Company::class => $container->get(Company::class),
            Country::class => $container->get(Country::class),
            Forms::class   => $container->get(Forms::class),
        ];
    }

}