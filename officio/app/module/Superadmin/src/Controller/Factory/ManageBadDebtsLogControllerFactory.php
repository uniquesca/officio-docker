<?php

namespace Superadmin\Controller\Factory;

use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Prospects\Service\Prospects;

/**
 * This is the factory for ManageBadDebtsLogController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageBadDebtsLogControllerFactory extends BaseControllerFactory
{
    protected function retrieveAdditionalServiceList(ContainerInterface $container) {
        return [
            Company::class => $container->get(Company::class),
            Prospects::class => $container->get(Prospects::class)
        ];
    }
}

