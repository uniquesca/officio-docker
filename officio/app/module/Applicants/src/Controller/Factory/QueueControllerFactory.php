<?php

namespace Applicants\Controller\Factory;

use Clients\Service\Clients;
use Clients\Service\MembersQueues;
use Officio\Common\Service\AccessLogs;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Service\Users;
use Officio\Service\SystemTriggers;

/**
 * This is the factory for QueueController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class QueueControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class        => $container->get(Company::class),
            Clients::class        => $container->get(Clients::class),
            Users::class          => $container->get(Users::class),
            MembersQueues::class  => $container->get(MembersQueues::class),
            SystemTriggers::class => $container->get(SystemTriggers::class),
            AccessLogs::class     => $container->get(AccessLogs::class),
        ];
    }

}
