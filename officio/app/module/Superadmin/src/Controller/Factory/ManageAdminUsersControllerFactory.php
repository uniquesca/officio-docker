<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\Clients;
use Psr\Container\ContainerInterface;
use Officio\Service\AuthHelper;
use Officio\Common\Service\AccessLogs;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\Users;

/**
 * This is the factory for ManageAdminUsersController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageAdminUsersControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            AccessLogs::class => $container->get(AccessLogs::class),
            Company::class    => $container->get(Company::class),
            Clients::class    => $container->get(Clients::class),
            Users::class      => $container->get(Users::class),
            AuthHelper::class => $container->get(AuthHelper::class),
            Encryption::class => $container->get(Encryption::class),
        ];
    }

}