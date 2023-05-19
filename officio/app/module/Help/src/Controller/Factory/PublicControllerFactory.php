<?php

namespace Help\Controller\Factory;

use Help\Service\Help;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;

/**
 * This is the factory for PublicController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class PublicControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Help::class => $container->get(Help::class)
        ];
    }

}
