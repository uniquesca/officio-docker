<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\Clients;
use Files\Service\Files;
use Forms\Service\Pdf;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Service\SystemTriggers;

/**
 * This is the factory for ImportBcpnpController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ImportBcpnpControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class => $container->get(Company::class),
            Clients::class => $container->get(Clients::class),
            Files::class => $container->get(Files::class),
            Pdf::class => $container->get(Pdf::class),
            SystemTriggers::class => $container->get(SystemTriggers::class),
        ];
    }

}
