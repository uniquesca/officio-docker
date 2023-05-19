<?php

namespace Templates\Controller\Factory;

use Clients\Service\Clients;
use Documents\Service\Documents;
use Files\Service\Files;
use Forms\Service\Forms;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Templates\SystemTemplates;
use Prospects\Service\CompanyProspects;
use Templates\Service\Templates;

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
            Files::class            => $container->get(Files::class),
            CompanyProspects::class => $container->get(CompanyProspects::class),
            Templates::class        => $container->get(Templates::class),
            Forms::class            => $container->get(Forms::class),
            Encryption::class       => $container->get(Encryption::class),
            SystemTemplates::class  => $container->get(SystemTemplates::class),
        ];
    }

}
