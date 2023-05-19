<?php

namespace Documents\Controller\Factory;

use Clients\Service\Clients;
use Documents\Service\Documents;
use Files\Service\Files;
use Officio\Common\Service\AccessLogs;
use Psr\Container\ContainerInterface;
use Mailer\Service\Mailer;
use Notes\Service\Notes;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\Letterheads;
use Officio\Service\SystemTriggers;
use Prospects\Service\CompanyProspects;
use Templates\Service\Templates;

/**
 * This is the factory for IndexController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class IndexControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class          => $container->get(Company::class),
            Clients::class          => $container->get(Clients::class),
            Notes::class            => $container->get(Notes::class),
            Documents::class        => $container->get(Documents::class),
            Letterheads::class      => $container->get(Letterheads::class),
            Files::class            => $container->get(Files::class),
            CompanyProspects::class => $container->get(CompanyProspects::class),
            Templates::class        => $container->get(Templates::class),
            Mailer::class           => $container->get(Mailer::class),
            SystemTriggers::class   => $container->get(SystemTriggers::class),
            Encryption::class       => $container->get(Encryption::class),
            AccessLogs::class       => $container->get(AccessLogs::class),
        ];
    }

}
