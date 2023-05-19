<?php

namespace Links\Controller\Factory;

use Psr\Container\ContainerInterface;
use Links\Service\Links;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Service\Roles;

/**
 * This is the factory for IndexController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class IndexControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Links::class => $container->get(Links::class),
            Company::class => $container->get(Company::class),
            Roles::class => $container->get(Roles::class),
        ];
    }

}
