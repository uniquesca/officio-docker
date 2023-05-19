<?php

namespace Clients\Service\Factory;

use Clients\Service\Clients;
use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Common\Service\Encryption;
use Officio\Service\Tickets;
use Officio\Service\Users;

/**
 * Class MembersVevoFactory
 * @package Officio
 */
class MembersVevoFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Clients::class    => $container->get(Clients::class),
            Company::class    => $container->get(Company::class),
            Country::class    => $container->get(Country::class),
            Tickets::class    => $container->get(Tickets::class),
            Files::class      => $container->get(Files::class),
            Users::class      => $container->get(Users::class),
            Encryption::class => $container->get(Encryption::class),
        ];
    }

}