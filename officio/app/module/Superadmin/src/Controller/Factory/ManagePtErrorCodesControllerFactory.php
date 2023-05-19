<?php

namespace Superadmin\Controller\Factory;

use Psr\Container\ContainerInterface;
use Officio\Service\AutomatedBillingErrorCodes;
use Officio\BaseControllerFactory;

/**
 * This is the factory for ManagePtErrorCodesController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManagePtErrorCodesControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            AutomatedBillingErrorCodes::class => $container->get(AutomatedBillingErrorCodes::class)
        ];
    }

}
