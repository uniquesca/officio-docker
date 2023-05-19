<?php

namespace System\Controller\Factory;

use Clients\Service\Clients;
use Files\Service\Files;
use Officio\Comms\Service\Mailer;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Common\Service\Encryption;
use Officio\Service\Roles;
use Officio\Service\Users;
use Prospects\Service\CompanyProspects;
use Tasks\Service\Tasks;

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
            Users::class            => $container->get(Users::class),
            Country::class          => $container->get(Country::class),
            Files::class            => $container->get(Files::class),
            Roles::class            => $container->get(Roles::class),
            Tasks::class            => $container->get(Tasks::class),
            CompanyProspects::class => $container->get(CompanyProspects::class),
            Encryption::class       => $container->get(Encryption::class),
            Mailer::class           => $container->get(Mailer::class),
            'payment'               => $container->get('payment'),
        ];
    }

}
