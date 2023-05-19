<?php

namespace Officio\Service\Factory;

use Clients\Service\Clients;
use Files\Service\Files;
use Forms\Service\Forms;
use Officio\Service\AuthHelper;
use Officio\BaseControllerFactory;
use Psr\Container\ContainerInterface;

/**
 * Class BcpnpFactory
 * @package Officio
 */
class BcpnpFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            AuthHelper::class => $container->get(AuthHelper::class),
            Clients::class    => $container->get(Clients::class),
            Files::class      => $container->get(Files::class),
            Forms::class      => $container->get(Forms::class),
        ];
    }

}