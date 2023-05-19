<?php

namespace Officio\Service\Factory;

use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;

/**
 * Class StatisticsFactory
 * @package Officio
 */
class StatisticsFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            'debugDb' => $container->get('debugDb')
        ];
    }

}