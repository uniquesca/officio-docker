<?php

namespace Forms\Service\Factory;

use Clients\Service\Clients;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;

class XfdfDbSyncFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Clients::class => $container->get(Clients::class),
        ];
    }

}