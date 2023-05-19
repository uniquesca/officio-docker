<?php

namespace Api\Controller\Factory;

use Clients\Service\Analytics;
use Clients\Service\Clients;
use Help\Service\Help;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Common\Service\AccessLogs;
use Officio\Comms\Service\Mailer;
use Officio\Service\AuthHelper;
use Officio\Service\AutomatedBillingLog;
use Officio\Service\AutomaticReminders;
use Officio\Service\Company;
use Officio\Service\CompanyCreator;
use Officio\Common\Service\Encryption;
use Officio\Service\PricingCategories;
use Officio\Service\Roles;
use Officio\Service\Users;
use Prospects\Service\Prospects;

class IndexControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            AccessLogs::class          => $container->get(AccessLogs::class),
            Company::class             => $container->get(Company::class),
            CompanyCreator::class      => $container->get(CompanyCreator::class),
            Clients::class             => $container->get(Clients::class),
            Users::class               => $container->get(Users::class),
            Analytics::class           => $container->get(Analytics::class),
            AutomatedBillingLog::class => $container->get(AutomatedBillingLog::class),
            AutomaticReminders::class  => $container->get(AutomaticReminders::class),
            PricingCategories::class   => $container->get(PricingCategories::class),
            AuthHelper::class          => $container->get(AuthHelper::class),
            Help::class                => $container->get(Help::class),
            Prospects::class           => $container->get(Prospects::class),
            Roles::class               => $container->get(Roles::class),
            Encryption::class          => $container->get(Encryption::class),
            Mailer::class              => $container->get(Mailer::class),
        ];
    }

}
