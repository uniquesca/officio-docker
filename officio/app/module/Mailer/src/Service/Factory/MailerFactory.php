<?php

namespace Mailer\Service\Factory;

use Files\Service\Files;
use Forms\Service\Forms;
use Forms\Service\Pdf;
use Officio\Comms\Service\Mailer;
use Officio\Service\AuthHelper;
use Officio\Service\OAuth2Client;
use Psr\Container\ContainerInterface;
use Laminas\View\HelperPluginManager;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;

class MailerFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            OAuth2Client::class        => $container->get(OAuth2Client::class),
            Company::class             => $container->get(Company::class),
            Files::class               => $container->get(Files::class),
            Pdf::class                 => $container->get(Pdf::class),
            Forms::class               => $container->get(Forms::class),
            Encryption::class          => $container->get(Encryption::class),
            Mailer::class              => $container->get(Mailer::class),
            HelperPluginManager::class => $container->get('ViewHelperManager'),
        ];
    }

}