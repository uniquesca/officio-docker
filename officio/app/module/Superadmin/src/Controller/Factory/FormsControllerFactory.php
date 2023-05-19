<?php

namespace Superadmin\Controller\Factory;

use Files\Service\Files;
use Forms\Service\Forms;
use Forms\Service\Pdf;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;

/**
 * This is the factory for FormsController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class FormsControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Files::class => $container->get(Files::class),
            Forms::class => $container->get(Forms::class),
            Pdf::class => $container->get(Pdf::class),
        ];
    }

}
