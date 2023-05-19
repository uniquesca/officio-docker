<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\Clients;
use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Country;
use Officio\Service\AuthHelper;
use Officio\Common\Service\AccessLogs;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Clients\Service\MembersVevo;
use Officio\Common\Service\Encryption;
use Officio\Service\Roles;
use Officio\Service\Users;
use Officio\Service\Tickets;

/**
 * This is the factory for ManageMembersController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageMembersControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            AccessLogs::class  => $container->get(AccessLogs::class),
            Company::class     => $container->get(Company::class),
            Clients::class     => $container->get(Clients::class),
            Users::class       => $container->get(Users::class),
            AuthHelper::class  => $container->get(AuthHelper::class),
            Country::class     => $container->get(Country::class),
            MembersVevo::class => $container->get(MembersVevo::class),
            Tickets::class     => $container->get(Tickets::class),
            Files::class       => $container->get(Files::class),
            Roles::class       => $container->get(Roles::class),
            Encryption::class  => $container->get(Encryption::class),
        ];
    }

}
