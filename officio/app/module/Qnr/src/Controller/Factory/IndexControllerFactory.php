<?php

namespace Qnr\Controller\Factory;

use Psr\Container\ContainerInterface;
use Mailer\Service\Mailer;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Templates\SystemTemplates;
use Prospects\Service\CompanyProspects;

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
            CompanyProspects::class => $container->get(CompanyProspects::class),
            Mailer::class           => $container->get(Mailer::class),
            SystemTemplates::class  => $container->get(SystemTemplates::class)
        ];
    }

}
