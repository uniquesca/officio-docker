<?php

namespace Officio\Controller\Factory;

use Files\Service\Files;
use Laminas\ModuleManager\ModuleManager;
use Officio\Service\OAuth2Client;
use Psr\Container\ContainerInterface;
use Laminas\Session\SessionManager;
use Officio\Auth\Service\SecondFactorAuthenticator;
use Officio\Common\Service\Encryption;
use Officio\Comms\Service\Mailer;
use Officio\BaseControllerFactory;
use Officio\Common\Service\AccessLogs;
use Officio\Service\AuthHelper;
use Officio\Service\Company;
use Officio\Templates\SystemTemplates;

/**
 * This is the factory for AuthController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class AuthControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            SessionManager::class            => $container->get(SessionManager::class),
            AccessLogs::class                => $container->get(AccessLogs::class),
            Encryption::class                => $container->get(Encryption::class),
            AuthHelper::class                => $container->get(AuthHelper::class),
            OAuth2Client::class              => $container->get(OAuth2Client::class),
            Company::class                   => $container->get(Company::class),
            SystemTemplates::class           => $container->get(SystemTemplates::class),
            Files::class                     => $container->get(Files::class),
            Mailer::class                    => $container->get(Mailer::class),
            SecondFactorAuthenticator::class => $container->get(SecondFactorAuthenticator::class),
            ModuleManager::class             => $container->get(ModuleManager::class),
        ];
    }

}
