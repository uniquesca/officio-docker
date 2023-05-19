<?php

namespace Officio\Service\Factory;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Class EntityAccessHandlerFactory
 * @package Officio\Service\Factory
 */
class EntityAccessHandlerFactory implements FactoryInterface
{

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $auth = $container->get('auth');
        return new $requestedName($auth->getIdentity());
    }

}