<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\Clients;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Psr\Container\ContainerInterface;

/**
 * This is the factory for TrustAccountSettingsController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class TrustAccountSettingsControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class => $container->get(Company::class),
            Clients::class => $container->get(Clients::class),
        ];
    }

}

