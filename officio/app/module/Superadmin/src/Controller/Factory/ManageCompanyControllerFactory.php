<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\Analytics;
use Clients\Service\Clients;
use Files\Service\Files;
use Officio\Common\Service\Encryption;
use Officio\Service\Users;
use Psr\Container\ContainerInterface;
use Mailer\Service\Mailer;
use Officio\BaseControllerFactory;
use Officio\Service\AutomaticReminders;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Service\GstHst;
use Officio\Service\Tickets;
use Prospects\Service\CompanyProspects;
use Prospects\Service\Prospects;
use Officio\Templates\SystemTemplates;

/**
 * This is the factory for ManageCompanyController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageCompanyControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Analytics::class          => $container->get(Analytics::class),
            Company::class            => $container->get(Company::class),
            Clients::class            => $container->get(Clients::class),
            Users::class              => $container->get(Users::class),
            AutomaticReminders::class => $container->get(AutomaticReminders::class),
            GstHst::class             => $container->get(GstHst::class),
            Country::class            => $container->get(Country::class),
            Files::class              => $container->get(Files::class),
            Tickets::class            => $container->get(Tickets::class),
            Prospects::class          => $container->get(Prospects::class),
            CompanyProspects::class   => $container->get(CompanyProspects::class),
            Mailer::class             => $container->get(Mailer::class),
            SystemTemplates::class    => $container->get(SystemTemplates::class),
            'payment'                 => $container->get('payment'),
            Encryption::class         => $container->get(Encryption::class),
        ];
    }

}
