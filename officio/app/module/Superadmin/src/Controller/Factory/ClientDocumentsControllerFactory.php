<?php

namespace Superadmin\Controller\Factory;

use Documents\Service\Documents;
use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;

/**
 * This is the factory for ClientDocumentsController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ClientDocumentsControllerFactory extends BaseControllerFactory
{
    protected function retrieveAdditionalServiceList(ContainerInterface $container) {
        return [
            Documents::class  => $container->get(Documents::class),
            Company::class    => $container->get(Company::class),
            Files::class      => $container->get(Files::class),
            Encryption::class => $container->get(Encryption::class),
        ];
    }
}
