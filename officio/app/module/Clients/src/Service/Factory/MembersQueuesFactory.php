<?php

namespace Clients\Service\Factory;


use Clients\Service\Clients;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;

/**
 * Class DbFactory
 * @package Officio
 */
class MembersQueuesFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Clients::class => $container->get(Clients::class),
            Company::class => $container->get(Company::class),
        ];
    }

}