<?php

namespace Mailer\Controller\Factory;

use Clients\Service\Clients;
use Files\Service\Files;
use Officio\Service\OAuth2Client;
use Psr\Container\ContainerInterface;
use Mailer\Service\Mailer;
use Officio\Comms\Service\Mailer as CommsMailer;
use Officio\BaseControllerFactory;
use Officio\Email\ServerSuggestions;
use Officio\Common\Service\Encryption;

/**
 * This is the factory for SettingsController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class SettingsControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Clients::class           => $container->get(Clients::class),
            Files::class             => $container->get(Files::class),
            Mailer::class            => $container->get(Mailer::class),
            CommsMailer::class       => $container->get(CommsMailer::class),
            Encryption::class        => $container->get(Encryption::class),
            OAuth2Client::class      => $container->get(OAuth2Client::class),
            ServerSuggestions::class => $container->get(ServerSuggestions::class),
        ];
    }

}
