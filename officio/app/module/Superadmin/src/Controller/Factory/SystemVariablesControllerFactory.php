<?php

namespace Superadmin\Controller\Factory;

use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;

/**
 * This is the factory for SystemVariablesController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class SystemVariablesControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
        ];
    }

}
