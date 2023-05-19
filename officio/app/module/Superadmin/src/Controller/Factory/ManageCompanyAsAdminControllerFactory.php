<?php

namespace Superadmin\Controller\Factory;

use Officio\Service\AuthHelper;
use Officio\BaseControllerFactory;
use Psr\Container\ContainerInterface;

/**
 * This is the factory for ManageCompanyAsAdminController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageCompanyAsAdminControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            AuthHelper::class => $container->get(AuthHelper::class),
        ];
    }

}
