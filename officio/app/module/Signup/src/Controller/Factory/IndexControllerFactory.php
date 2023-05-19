<?php

namespace Signup\Controller\Factory;

use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Service\CompanyCreator;
use Officio\Common\Service\Country;
use Officio\Common\Service\Encryption;
use Officio\Service\GstHst;
use Officio\Service\PricingCategories;
use Officio\Service\Roles;
use Prospects\Service\Prospects;

/**
 * This is the factory for IndexController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class IndexControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class           => $container->get(Company::class),
            CompanyCreator::class    => $container->get(CompanyCreator::class),
            PricingCategories::class => $container->get(PricingCategories::class),
            Country::class           => $container->get(Country::class),
            Prospects::class         => $container->get(Prospects::class),
            Roles::class             => $container->get(Roles::class),
            Encryption::class        => $container->get(Encryption::class),
            GstHst::class            => $container->get(GstHst::class),
        ];
    }

}
