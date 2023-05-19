<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\Clients;
use Forms\Service\Forms;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\ConditionalFields;
use Officio\Service\Roles;
use Templates\Service\Templates;

/**
 * This is the factory for ManageFieldsGroupsController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageFieldsGroupsControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class           => $container->get(Company::class),
            Clients::class           => $container->get(Clients::class),
            Templates::class         => $container->get(Templates::class),
            Roles::class             => $container->get(Roles::class),
            Forms::class             => $container->get(Forms::class),
            Encryption::class        => $container->get(Encryption::class),
            ConditionalFields::class => $container->get(ConditionalFields::class),
        ];
    }

}
