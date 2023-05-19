<?php

namespace Notes\Service\Factory;

use Clients\Service\Clients;
use Files\Service\Files;
use Psr\Container\ContainerInterface;
use Laminas\View\HelperPluginManager;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\SystemTriggers;
use Tasks\Service\Tasks;

class NotesFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Clients::class             => $container->get(Clients::class),
            Company::class             => $container->get(Company::class),
            Files::class               => $container->get(Files::class),
            SystemTriggers::class      => $container->get(SystemTriggers::class),
            Tasks::class               => $container->get(Tasks::class),
            Encryption::class          => $container->get(Encryption::class),
            HelperPluginManager::class => $container->get('ViewHelperManager'),
        ];
    }

}