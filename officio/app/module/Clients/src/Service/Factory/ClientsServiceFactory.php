<?php

namespace Clients\Service\Factory;

use Forms\Service\Forms;
use Forms\Service\Pdf;
use Help\Service\Help;
use Psr\Container\ContainerInterface;
use Mailer\Service\Mailer;
use Officio\Templates\SystemTemplates;

class ClientsServiceFactory extends MembersServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        $services                         = parent::retrieveAdditionalServiceList($container);
        $services[Pdf::class]             = $container->get(Pdf::class);
        $services[Mailer::class]          = $container->get(Mailer::class);
        $services[Forms::class]           = $container->get(Forms::class);
        $services[Help::class]            = $container->get(Help::class);
        $services[SystemTemplates::class] = $container->get(SystemTemplates::class);
        return $services;
    }

}