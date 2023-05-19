<?php

namespace Superadmin\Controller\Factory;

use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Prospects\Service\CompanyProspects;
use Websites\Service\CompanyWebsites;

/**
 * This is the factory for CompanyWebsiteController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class CompanyWebsiteControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class => $container->get(Company::class),
            Files::class => $container->get(Files::class),
            CompanyWebsites::class => $container->get(CompanyWebsites::class),
            CompanyProspects::class => $container->get(CompanyProspects::class)
        ];
    }

}
