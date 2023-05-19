<?php

namespace Templates\Service\Factory;

use Clients\Service\Clients;
use Documents\Service\Documents;
use Files\Service\Files;
use Forms\Service\Pdf;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Common\Service\Encryption;
use Officio\Service\SystemTriggers;
use Prospects\Service\CompanyProspects;

class TemplatesFactory extends BaseServiceFactory
{
    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Clients::class          => $container->get(Clients::class),
            Company::class          => $container->get(Company::class),
            CompanyProspects::class => $container->get(CompanyProspects::class),
            Country::class          => $container->get(Country::class),
            Documents::class        => $container->get(Documents::class),
            Files::class            => $container->get(Files::class),
            SystemTriggers::class   => $container->get(SystemTriggers::class),
            Pdf::class              => $container->get(Pdf::class),
            Encryption::class       => $container->get(Encryption::class),
        ];
    }

}