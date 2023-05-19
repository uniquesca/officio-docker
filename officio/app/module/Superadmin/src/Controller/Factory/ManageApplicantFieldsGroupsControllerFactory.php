<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\Clients;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\Roles;

/**
 * This is the factory for ManageApplicantFieldsGroupsController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageApplicantFieldsGroupsControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class    => $container->get(Company::class),
            Clients::class    => $container->get(Clients::class),
            Roles::class      => $container->get(Roles::class),
            Encryption::class => $container->get(Encryption::class),
        ];
    }

}
