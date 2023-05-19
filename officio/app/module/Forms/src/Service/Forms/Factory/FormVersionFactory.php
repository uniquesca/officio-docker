<?php

namespace Forms\Service\Forms\Factory;

use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;

class FormVersionFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Files::class => $container->get(Files::class)
        ];
    }

}