<?php

namespace TrustAccount\Controller\Factory;

use Clients\Service\Clients;
use Files\Service\Files;
use Officio\Comms\Service\Mailer;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;

/**
 * This is the factory for ImportController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ImportControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class => $container->get(Company::class),
            Clients::class => $container->get(Clients::class),
            Files::class   => $container->get(Files::class),
            Mailer::class  => $container->get(Mailer::class),
        ];
    }

}
