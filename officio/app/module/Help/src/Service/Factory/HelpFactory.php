<?php

namespace Help\Service\Factory;

use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;
use Officio\Service\Users;
use Officio\Templates\SystemTemplates;

class HelpFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Users::class => $container->get(Users::class),
            Company::class => $container->get(Company::class),
            SystemTemplates::class => $container->get(SystemTemplates::class),
        ];
    }

}