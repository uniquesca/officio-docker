<?php

namespace Officio\Controller\Factory;

use Clients\Service\Clients;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Common\Service\Encryption;
use Officio\Service\Company;
use Officio\Service\Payment\Stripe;
use Officio\Service\Payment\TranPage;
use Officio\Service\SystemTriggers;

/**
 * This is the factory for IndexController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class TranPageControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class => $container->get(Company::class),
            Clients::class => $container->get(Clients::class),
            SystemTriggers::class => $container->get(SystemTriggers::class),
            Stripe::class => $container->get(Stripe::class),
            TranPage::class => $container->get(TranPage::class),
            Encryption::class => $container->get(Encryption::class),
        ];
    }

}
