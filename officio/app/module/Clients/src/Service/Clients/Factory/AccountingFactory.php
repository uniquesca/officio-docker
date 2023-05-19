<?php

namespace Clients\Service\Clients\Factory;

use Documents\Service\Documents;
use Files\Service\Files;
use Forms\Service\Pdf;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\GstHst;
use Officio\Service\SystemTriggers;
use Officio\Templates\SystemTemplates;

class AccountingFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class         => $container->get(Company::class),
            Files::class           => $container->get(Files::class),
            GstHst::class          => $container->get(GstHst::class),
            Pdf::class             => $container->get(Pdf::class),
            SystemTriggers::class  => $container->get(SystemTriggers::class),
            Documents::class       => $container->get(Documents::class),
            Encryption::class      => $container->get(Encryption::class),
            SystemTemplates::class => $container->get(SystemTemplates::class)
        ];
    }

}