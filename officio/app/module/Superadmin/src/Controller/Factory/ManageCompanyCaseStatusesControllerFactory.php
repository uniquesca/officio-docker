<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\Clients;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;

/**
 * This is the factory for ManageCompanyCaseStatusesController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageCompanyCaseStatusesControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Clients::class => $container->get(Clients::class),
            Company::class => $container->get(Company::class),
        ];
    }

}
