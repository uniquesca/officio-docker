<?php

namespace Superadmin\Controller\Factory;

use Clients\Service\Clients;
use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Laminas\Cache\Service\StorageAdapterFactoryInterface;
use Notes\Service\Notes;
use Officio\BaseControllerFactory;
use Officio\Service\Company;

/**
 * This is the factory for ImportClientNotesController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ImportClientNotesControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class                        => $container->get(Company::class),
            Clients::class                        => $container->get(Clients::class),
            Notes::class                          => $container->get(Notes::class),
            Files::class                          => $container->get(Files::class),
            StorageAdapterFactoryInterface::class => $container->get(StorageAdapterFactoryInterface::class),
        ];
    }

}
