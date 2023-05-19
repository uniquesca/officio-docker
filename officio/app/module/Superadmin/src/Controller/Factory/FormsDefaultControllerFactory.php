<?php

namespace Superadmin\Controller\Factory;

use Files\Service\Files;
use Forms\Service\Forms;
use Forms\Service\Pdf;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Common\Service\Encryption;

/**
 * This is the factory for FormsDefaultController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class FormsDefaultControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Files::class      => $container->get(Files::class),
            Forms::class      => $container->get(Forms::class),
            Pdf::class        => $container->get(Pdf::class),
            Encryption::class => $container->get(Encryption::class),
        ];
    }

}

