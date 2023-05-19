<?php

namespace Superadmin\Controller\Factory;

use Forms\Service\Forms;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;

/**
 * This is the factory for UrlCheckerController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class UrlCheckerControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Forms::class => $container->get(Forms::class),
        ];
    }

}

