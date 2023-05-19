<?php

namespace Officio\Service\Factory;

use Clients\Service\Members;
use Psr\Container\ContainerInterface;
use Mailer\Service\Mailer;
use Officio\Common\Service\Factory\BaseServiceFactory;

class SmsFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Members::class => $container->get(Members::class),
            Mailer::class  => $container->get(Mailer::class)
        ];
    }

}