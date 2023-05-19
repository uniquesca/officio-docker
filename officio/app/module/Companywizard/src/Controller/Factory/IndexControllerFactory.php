<?php

namespace Companywizard\Controller\Factory;

use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Common\Service\Country;
use Officio\Service\Company;
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
            Company::class => $container->get(Company::class),
            Country::class => $container->get(Country::class),
            Prospects::class => $container->get(Prospects::class),
            Roles::class => $container->get(Roles::class)
        ];
    }

}
