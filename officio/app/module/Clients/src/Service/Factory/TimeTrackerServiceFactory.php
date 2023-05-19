<?php

namespace Clients\Service\Factory;

use Clients\Service\Clients;
use Clients\Service\Members;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;

class TimeTrackerServiceFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Members::class => $container->get(Members::class),
            Clients::class => $container->get(Clients::class),
        ];
    }

}