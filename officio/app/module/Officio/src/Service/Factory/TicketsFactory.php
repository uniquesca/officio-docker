<?php

namespace Officio\Service\Factory;


use Clients\Service\Members;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;

/**
 * Class TicketsFactory
 * @package Officio
 */
class TicketsFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Members::class => $container->get(Members::class),
            Company::class => $container->get(Company::class),
            Country::class => $container->get(Country::class)
        ];
    }

}