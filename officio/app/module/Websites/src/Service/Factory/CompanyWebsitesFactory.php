<?php


namespace Websites\Service\Factory;


use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;
use Officio\Service\SystemTriggers;

class CompanyWebsitesFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Files::class => $container->get(Files::class),
            Company::class => $container->get(Company::class),
            SystemTriggers::class => $container->get(SystemTriggers::class),
        ];
    }

}