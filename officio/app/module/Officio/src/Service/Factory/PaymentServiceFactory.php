<?php

namespace Officio\Service\Factory;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Officio\Service\Payment\PaymentechService;
use Officio\Service\Payment\PayWayService;

class PaymentServiceFactory implements FactoryInterface
{

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('config');
        $log    = $container->get('log');
        if ($config['payment']['method'] == 'payway') {
            return new PayWayService($config, $log);
        } else {
            return new PaymentechService($config, $log);
        }
    }

}