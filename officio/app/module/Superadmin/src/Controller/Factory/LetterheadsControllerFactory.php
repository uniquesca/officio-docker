<?php

namespace Superadmin\Controller\Factory;

use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Service\Letterheads;

/**
 * This is the factory for LetterheadsController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class LetterheadsControllerFactory extends BaseControllerFactory {

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Letterheads::class => $container->get(Letterheads::class),
            Company::class => $container->get(Company::class),
            Files::class => $container->get(Files::class)
        ];
    }
}
