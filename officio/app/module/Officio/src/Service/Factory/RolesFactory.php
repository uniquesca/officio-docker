<?php

namespace Officio\Service\Factory;

use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;

class RolesFactory extends BaseServiceFactory {

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Files::class => $container->get(Files::class)
        ];
    }

}