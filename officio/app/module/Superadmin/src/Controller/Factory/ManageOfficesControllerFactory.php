<?php

namespace Superadmin\Controller\Factory;

use Files\Service\Files;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\Roles;
use Psr\Container\ContainerInterface;

/**
 * This is the factory for ManageOfficesController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageOfficesControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class    => $container->get(Company::class),
            Files::class      => $container->get(Files::class),
            Roles::class      => $container->get(Roles::class),
            Encryption::class => $container->get(Encryption::class),
        ];
    }

}

