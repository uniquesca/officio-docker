<?php

namespace Applicants\Controller\Factory;

use Clients\Service\Clients;
use Officio\Common\Service\AccessLogs;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Clients\Service\Analytics;
use Officio\Service\Company;

/**
 * This is the factory for IndexController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class SearchControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class    => $container->get(Company::class),
            Clients::class    => $container->get(Clients::class),
            Analytics::class  => $container->get(Analytics::class),
            AccessLogs::class => $container->get(AccessLogs::class)
        ];
    }

}
