<?php

namespace Superadmin\Controller\Factory;

use Files\Service\Files;
use Officio\BaseControllerFactory;
use Psr\Container\ContainerInterface;
use Templates\Service\Templates;

/**
 * This is the factory for SharedTemplatesController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class SharedTemplatesControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Files::class => $container->get(Files::class),
            Templates::class => $container->get(Templates::class)
        ];
    }

}
