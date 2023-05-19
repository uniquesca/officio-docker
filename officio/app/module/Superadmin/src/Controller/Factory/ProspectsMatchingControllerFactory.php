<?php

namespace Superadmin\Controller\Factory;

use Files\Service\Files;
use Officio\BaseControllerFactory;
use Psr\Container\ContainerInterface;

/**
 * This is the factory for ProspectsMatchingController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ProspectsMatchingControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Files::class => $container->get(Files::class),
        ];
    }

}
