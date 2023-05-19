<?php

namespace Officio\Service\Factory;

use Clients\Service\BusinessHours;
use Clients\Service\Clients;
use PhpParser\Node\Expr\AssignOp\Mod;
use Psr\Container\ContainerInterface;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Session\SessionManager;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Common\Service\AccessLogs;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\Roles;
use Officio\Service\Users;
use Officio\Templates\SystemTemplates;

/**
 * Class AuthHelperFactory
 * @package Officio
 */
class AuthHelperFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        /** @var ModuleManager $moduleManager */
        $moduleManager = $container->get(ModuleManager::class);
        $apiAuth       = ($moduleManager->getModule('Officio\\Api2')) ? $container->get('api-auth') : null;

        return [
            SessionManager::class  => $container->get(SessionManager::class),
            AccessLogs::class      => $container->get(AccessLogs::class),
            Users::class           => $container->get(Users::class),
            Clients::class         => $container->get(Clients::class),
            Company::class         => $container->get(Company::class),
            BusinessHours::class   => $container->get(BusinessHours::class),
            Roles::class           => $container->get(Roles::class),
            SystemTemplates::class => $container->get(SystemTemplates::class),
            ModuleManager::class   => $container->get(ModuleManager::class),
            Encryption::class      => $container->get(Encryption::class),
            'api-auth'             => $apiAuth,
        ];
    }

}