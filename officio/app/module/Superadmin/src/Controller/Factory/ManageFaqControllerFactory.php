<?php

namespace Superadmin\Controller\Factory;

use Files\Service\Files;
use Help\Service\Help;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;

/**
 * This is the factory for ManageFaqController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageFaqControllerFactory extends BaseControllerFactory
{
    protected function retrieveAdditionalServiceList(ContainerInterface $container) {
        return [
            Help::class => $container->get(Help::class),
            Files::class => $container->get(Files::class)
        ];
    }
}

