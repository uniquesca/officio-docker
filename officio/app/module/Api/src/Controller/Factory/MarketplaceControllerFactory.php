<?php

namespace Api\Controller\Factory;

use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\AuthHelper;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Common\Service\Encryption;
use Prospects\Service\CompanyProspects;

class MarketplaceControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class          => $container->get(Company::class),
            AuthHelper::class       => $container->get(AuthHelper::class),
            Country::class          => $container->get(Country::class),
            CompanyProspects::class => $container->get(CompanyProspects::class),
            Encryption::class       => $container->get(Encryption::class),
        ];
    }

}