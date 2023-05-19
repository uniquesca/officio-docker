<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\Clients;
use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Laminas\Cache\Service\StorageAdapterFactoryInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;

/**
 * This is the factory for ImportClientsController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ImportClientsControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class                        => $container->get(Company::class),
            Clients::class                        => $container->get(Clients::class),
            Files::class                          => $container->get(Files::class),
            StorageAdapterFactoryInterface::class => $container->get(StorageAdapterFactoryInterface::class),
        ];
    }

}
