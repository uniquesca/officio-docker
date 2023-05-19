<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\Clients;
use Forms\Service\Forms;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;

/**
 * This is the factory for FormsMapsController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class FormsMapsControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Clients::class => $container->get(Clients::class),
            Forms::class   => $container->get(Forms::class)
        ];
    }

}

