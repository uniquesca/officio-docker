<?php

namespace Officio\Service\Factory;

use Clients\Service\Analytics;
use Clients\Service\Clients;
use Clients\Service\Members;
use Officio\Comms\Service\Mailer;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;
use Officio\Service\Roles;
use Officio\Service\Users;
use Prospects\Service\Prospects;
use Officio\Templates\SystemTemplates;

/**
 * Class CompanyCreatorFactory
 * @package Officio
 */
class CompanyCreatorFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class         => $container->get(Company::class),
            Mailer::class          => $container->get(Mailer::class),
            Prospects::class       => $container->get(Prospects::class),
            Roles::class           => $container->get(Roles::class),
            Clients::class         => $container->get(Clients::class),
            Members::class         => $container->get(Members::class),
            Users::class           => $container->get(Users::class),
            Analytics::class       => $container->get(Analytics::class),
            SystemTemplates::class => $container->get(SystemTemplates::class),
        ];
    }

}
