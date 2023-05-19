<?php

namespace Clients\Service\Clients\Factory;

use Clients\Service\Clients;
use Files\Service\Files;
use Forms\Service\Pdf;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Service\SystemTriggers;

class TrustAccountFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Clients::class        => $container->get(Clients::class),
            Company::class        => $container->get(Company::class),
            Files::class          => $container->get(Files::class),
            Pdf::class            => $container->get(Pdf::class),
            SystemTriggers::class => $container->get(SystemTriggers::class),
            Country::class        => $container->get(Country::class),
        ];
    }

}