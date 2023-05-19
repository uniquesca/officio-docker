<?php

namespace Websites\Controller\Factory;

use Officio\Comms\Service\Mailer;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Websites\Service\CompanyWebsites;

/**
 * This is the factory for IndexController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class IndexControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class         => $container->get(Company::class),
            Mailer::class          => $container->get(Mailer::class),
            CompanyWebsites::class => $container->get(CompanyWebsites::class)
        ];
    }

}
