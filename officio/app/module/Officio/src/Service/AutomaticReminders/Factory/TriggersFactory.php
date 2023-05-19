<?php

namespace Officio\Service\AutomaticReminders\Factory;

use Clients\Service\Members;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;

class TriggersFactory extends BaseServiceFactory {

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Members::class => $container->get(Members::class),
        ];
    }

}