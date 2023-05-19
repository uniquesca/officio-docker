<?php

namespace SpecialOffer\Controller\Factory;

use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Common\Service\Country;

/**
 * This is the factory for IndexController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class IndexControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class => $container->get(Company::class),
            Country::class => $container->get(Country::class),
        ];
    }

}
