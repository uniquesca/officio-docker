<?php

namespace Superadmin\Controller\Factory;

use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;

/**
 * This is the factory for ManageInvoicesController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageInvoicesControllerFactory extends BaseControllerFactory
{
    protected function retrieveAdditionalServiceList(ContainerInterface $container) {
        return [
            Company::class => $container->get(Company::class)
        ];
    }
}
