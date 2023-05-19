<?php

namespace Forms\Controller\Factory;

use Clients\Service\Clients;
use Files\Service\Files;
use Forms\Service\Forms;
use Forms\Service\Pdf;
use Forms\Service\XfdfDbSync;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\AuthHelper;
use Officio\Service\Company;


/**
 * This is the factory for AngularFormsController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class AngularFormsControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            AuthHelper::class => $container->get(AuthHelper::class),
            Company::class    => $container->get(Company::class),
            Clients::class    => $container->get(Clients::class),
            Files::class      => $container->get(Files::class),
            Pdf::class        => $container->get(Pdf::class),
            XfdfDbSync::class => $container->get(XfdfDbSync::class),
            Forms::class      => $container->get(Forms::class),
        ];
    }

}
