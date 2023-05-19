<?php

namespace Officio\Service\Factory;

use Clients\Service\ClientsFileStatusHistory;
use Clients\Service\Members;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;
use Officio\Service\SystemTriggers;
use Tasks\Service\Tasks;

/**
 * Class LogFactory
 * @package Officio
 */
class AutomaticRemindersFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class                  => $container->get(Company::class),
            Members::class                  => $container->get(Members::class),
            SystemTriggers::class           => $container->get(SystemTriggers::class),
            Tasks::class                    => $container->get(Tasks::class),
            ClientsFileStatusHistory::class => $container->get(ClientsFileStatusHistory::class),
        ];
    }

}