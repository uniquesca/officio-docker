<?php

namespace TrustAccount\Controller\Factory;

use Clients\Service\Clients;
use Officio\Service\Company;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Templates\Service\Templates;

/**
 * This is the factory for AssignController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class AssignControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class   => $container->get(Company::class),
            Clients::class   => $container->get(Clients::class),
            Templates::class => $container->get(Templates::class),
        ];
    }

}
