<?php

namespace Tasks\Controller\Factory;

use Clients\Service\Clients;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Prospects\Service\CompanyProspects;
use Tasks\Service\Tasks;

/**
 * This is the factory for IndexController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class IndexControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class          => $container->get(Company::class),
            Clients::class          => $container->get(Clients::class),
            Tasks::class            => $container->get(Tasks::class),
            CompanyProspects::class => $container->get(CompanyProspects::class)
        ];
    }

}
