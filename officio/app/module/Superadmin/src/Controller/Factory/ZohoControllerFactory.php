<?php

namespace Superadmin\Controller\Factory;

use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\ZohoKeys;

/**
 * This is the factory for ZohoController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ZohoControllerFactory extends BaseControllerFactory
{
    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            ZohoKeys::class => $container->get(ZohoKeys::class)
        ];
    }
}
