<?php

namespace Notes\Controller\Factory;

use Clients\Service\Clients;
use Files\Service\Files;
use Officio\Common\Service\AccessLogs;
use Psr\Container\ContainerInterface;
use Notes\Service\Notes;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;

/**
 * This is the factory for IndexController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class IndexControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Clients::class    => $container->get(Clients::class),
            Company::class    => $container->get(Company::class),
            Notes::class      => $container->get(Notes::class),
            Files::class      => $container->get(Files::class),
            Encryption::class => $container->get(Encryption::class),
            AccessLogs::class => $container->get(AccessLogs::class),
        ];
    }

}
