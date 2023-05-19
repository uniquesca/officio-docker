<?php

namespace System\Controller\Factory;

use Clients\Service\Clients;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Prospects\Service\CompanyProspects;

/**
 * This is the factory for ImportController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ImportControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class => $container->get(Company::class),
            Clients::class => $container->get(Clients::class),
            CompanyProspects::class => $container->get(CompanyProspects::class),
        ];
    }

}
