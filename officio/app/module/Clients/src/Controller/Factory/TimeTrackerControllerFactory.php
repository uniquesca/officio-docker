<?php

namespace Clients\Controller\Factory;

use Clients\Service\Clients;
use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Clients\Service\TimeTracker;
use Officio\Service\Company;
use Officio\Service\GstHst;

/**
 * This is the factory for TimeTrackerController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class TimeTrackerControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class => $container->get(Company::class),
            Clients::class => $container->get(Clients::class),
            TimeTracker::class => $container->get(TimeTracker::class),
            GstHst::class => $container->get(GstHst::class),
            Files::class => $container->get(Files::class)
        ];
    }

}
