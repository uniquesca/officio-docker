<?php

namespace Forms\Controller\Factory;

use Clients\Service\Clients;
use Files\Service\Files;
use Forms\Service\Forms;
use Forms\Service\Pdf;
use Officio\Common\Service\AccessLogs;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\SystemTriggers;

/**
 * This is the factory for IndexController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class IndexControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class        => $container->get(Company::class),
            Clients::class        => $container->get(Clients::class),
            Files::class          => $container->get(Files::class),
            Pdf::class            => $container->get(Pdf::class),
            AccessLogs::class     => $container->get(AccessLogs::class),
            SystemTriggers::class => $container->get(SystemTriggers::class),
            Forms::class          => $container->get(Forms::class),
            Encryption::class     => $container->get(Encryption::class),
        ];
    }

}
