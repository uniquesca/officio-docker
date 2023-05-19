<?php

namespace Superadmin\Controller\Factory;

use Officio\BaseControllerFactory;
use Psr\Container\ContainerInterface;
use Superadmin\Service\SuperadminSearch;

/**
 * This is the factory for AdvancedSearchController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class AdvancedSearchControllerFactory extends BaseControllerFactory
{
    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            SuperadminSearch::class => $container->get(SuperadminSearch::class)
        ];
    }
}