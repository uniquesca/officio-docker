<?php

namespace Officio\Service\Factory;

use Clients\Service\Factory\MembersServiceFactory;
use Psr\Container\ContainerInterface;
use Officio\Templates\SystemTemplates;

class UsersServiceFactory extends MembersServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        $services                         = parent::retrieveAdditionalServiceList($container);
        $services[SystemTemplates::class] = $container->get(SystemTemplates::class);
        return $services;
    }

}