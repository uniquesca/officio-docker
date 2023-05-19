<?php

namespace Superadmin\Controller\Factory;

use Psr\Container\ContainerInterface;
use Officio\Service\AutomatedBillingErrorCodes;
use Officio\BaseControllerFactory;
use Officio\Service\Company;

/**
 * This is the factory for AccountsController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class AccountsControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            AutomatedBillingErrorCodes::class => $container->get(AutomatedBillingErrorCodes::class),
            Company::class => $container->get(Company::class)
        ];
    }

}