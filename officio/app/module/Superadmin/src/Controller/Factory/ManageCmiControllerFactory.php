<?php

namespace Superadmin\Controller\Factory;

use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;

/**
 * This is the factory for ManageCmiController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageCmiControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container) {
        return [
            Company::class => $container->get(Company::class),
            Files::class => $container->get(Files::class),
        ];
    }

}
