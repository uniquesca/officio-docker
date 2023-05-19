<?php

namespace Superadmin\Controller\Factory;

use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Psr\Container\ContainerInterface;
use Officio\Service\PricingCategories;

/**
 * This is the factory for ManagePricingController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManagePricingControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class => $container->get(Company::class),
            PricingCategories::class => $container->get(PricingCategories::class)
        ];
    }

}
