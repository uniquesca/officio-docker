<?php

namespace Applicants\Controller\Factory;

use Clients\Service\Clients;
use Clients\Service\ClientsReferrals;
use Clients\Service\ClientsFileStatusHistory;
use Clients\Service\MembersVevo;
use Documents\Service\Documents;
use Files\Service\Files;
use Forms\Service\Dominica;
use Forms\Service\Pdf;
use Officio\Common\Service\AccessLogs;
use Psr\Container\ContainerInterface;
use Notes\Service\Notes;
use Officio\BaseControllerFactory;
use Officio\Service\AuthHelper;
use Officio\Service\AutomaticReminders;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Common\Service\Encryption;
use Officio\Service\SystemTriggers;
use Officio\Templates\SystemTemplates;
use Prospects\Service\CompanyProspects;
use Templates\Service\Templates;

/**
 * This is the factory for IndexController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ProfileControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Dominica::class                 => $container->get(Dominica::class),
            Company::class                  => $container->get(Company::class),
            Clients::class                  => $container->get(Clients::class),
            CompanyProspects::class         => $container->get(CompanyProspects::class),
            AutomaticReminders::class       => $container->get(AutomaticReminders::class),
            AuthHelper::class               => $container->get(AuthHelper::class),
            Country::class                  => $container->get(Country::class),
            MembersVevo::class              => $container->get(MembersVevo::class),
            Files::class                    => $container->get(Files::class),
            Pdf::class                      => $container->get(Pdf::class),
            Notes::class                    => $container->get(Notes::class),
            Documents::class                => $container->get(Documents::class),
            Templates::class                => $container->get(Templates::class),
            SystemTemplates::class          => $container->get(SystemTemplates::class),
            SystemTriggers::class           => $container->get(SystemTriggers::class),
            Encryption::class               => $container->get(Encryption::class),
            ClientsReferrals::class         => $container->get(ClientsReferrals::class),
            ClientsFileStatusHistory::class => $container->get(ClientsFileStatusHistory::class),
            AccessLogs::class               => $container->get(AccessLogs::class),
        ];
    }

}
