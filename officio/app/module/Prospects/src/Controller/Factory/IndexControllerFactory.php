<?php

namespace Prospects\Controller\Factory;

use Clients\Service\Clients;
use Documents\Service\Documents;
use Files\Service\Files;
use Forms\Service\Pdf;
use Psr\Container\ContainerInterface;
use Mailer\Service\Mailer;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Common\Service\Encryption;
use Prospects\Service\CompanyProspects;
use Tasks\Service\Tasks;
use Officio\Templates\SystemTemplates;

/**
 * This is the factory for IndexController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class IndexControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class          => $container->get(Company::class),
            Clients::class          => $container->get(Clients::class),
            Documents::class        => $container->get(Documents::class),
            Country::class          => $container->get(Country::class),
            Files::class            => $container->get(Files::class),
            CompanyProspects::class => $container->get(CompanyProspects::class),
            Pdf::class              => $container->get(Pdf::class),
            Mailer::class           => $container->get(Mailer::class),
            Tasks::class            => $container->get(Tasks::class),
            SystemTemplates::class  => $container->get(SystemTemplates::class),
            Encryption::class       => $container->get(Encryption::class),
        ];
    }

}
