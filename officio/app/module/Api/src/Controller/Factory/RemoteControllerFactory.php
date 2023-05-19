<?php

namespace Api\Controller\Factory;

use Clients\Service\Clients;
use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\AuthHelper;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;

class RemoteControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class    => $container->get(Company::class),
            Clients::class    => $container->get(Clients::class),
            AuthHelper::class => $container->get(AuthHelper::class),
            Files::class      => $container->get(Files::class),
            Encryption::class => $container->get(Encryption::class),
        ];
    }

}