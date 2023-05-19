<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\Clients;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Psr\Container\ContainerInterface;
use Prospects\Service\CompanyProspects;

/**
 * This is the factory for ManageCompanyProspectsController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageCompanyProspectsControllerFactory extends BaseControllerFactory
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
