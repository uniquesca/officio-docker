<?php

namespace Clients\Controller\Factory;

use Clients\Service\Clients;
use Documents\Service\Documents;
use Files\Service\Files;
use Forms\Service\Pdf;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\GstHst;
use Officio\Service\Users;
use Templates\Service\Templates;

/**
 * This is the factory for AccountingController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class AccountingControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class    => $container->get(Company::class),
            Clients::class    => $container->get(Clients::class),
            Users::class      => $container->get(Users::class),
            GstHst::class     => $container->get(GstHst::class),
            Documents::class  => $container->get(Documents::class),
            Files::class      => $container->get(Files::class),
            Templates::class  => $container->get(Templates::class),
            Encryption::class => $container->get(Encryption::class),
            Pdf::class        => $container->get(Pdf::class),
        ];
    }

}
