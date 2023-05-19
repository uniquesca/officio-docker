<?php

namespace Tasks\Service\Factory;

use Clients\Service\Clients;
use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Mailer\Service\Mailer;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;

/**
 * Class LogFactory
 * @package Officio
 */
class TasksFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Clients::class             => $container->get(Clients::class),
            Files::class               => $container->get(Files::class),
            Company::class             => $container->get(Company::class),
            Mailer::class              => $container->get(Mailer::class),
        ];
    }

}