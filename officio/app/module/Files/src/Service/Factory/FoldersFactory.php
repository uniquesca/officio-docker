<?php


namespace Files\Service\Factory;

use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;

class FoldersFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
        ];
    }

}