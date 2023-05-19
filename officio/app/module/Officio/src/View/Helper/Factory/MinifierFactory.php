<?php

namespace Officio\View\Helper\Factory;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MinifierFactory implements FactoryInterface
{

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('config');
        $log    = $container->get('log');
        return new $requestedName($log, $config['minify']);
    }

}