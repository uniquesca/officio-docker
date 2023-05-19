<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\MembersPua;
use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Service\Users;

/**
 * This is the factory for ManageMembersPuaController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageMembersPuaControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class => $container->get(Company::class),
            Users::class => $container->get(Users::class),
            MembersPua::class => $container->get(MembersPua::class),
            Files::class => $container->get(Files::class)
        ];
    }

}
