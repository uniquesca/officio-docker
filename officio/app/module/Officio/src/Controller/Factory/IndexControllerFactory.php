<?php

namespace Officio\Controller\Factory;

use Clients\Service\Clients;
use Laminas\View\HelperPluginManager;
use Psr\Container\ContainerInterface;
use Laminas\ModuleManager\ModuleManager;
use Mailer\Service\Mailer;
use Officio\Email\RabbitMqHelper;
use Officio\Service\AuthHelper;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Officio\Service\GstHst;
use Officio\Service\Letterheads;
use Officio\Service\Navigation;
use Officio\Service\Sms;
use Officio\Service\SummaryNotifications;
use Officio\Service\Users;
use Officio\Service\SystemTriggers;
use Prospects\Service\CompanyProspects;
use Tasks\Service\Tasks;

/**
 * This is the factory for IndexController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class IndexControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class              => $container->get(Company::class),
            Clients::class              => $container->get(Clients::class),
            Users::class                => $container->get(Users::class),
            SummaryNotifications::class => $container->get(SummaryNotifications::class),
            AuthHelper::class           => $container->get(AuthHelper::class),
            GstHst::class               => $container->get(GstHst::class),
            Navigation::class           => $container->get(Navigation::class),
            Letterheads::class          => $container->get(Letterheads::class),
            CompanyProspects::class     => $container->get(CompanyProspects::class),
            Mailer::class               => $container->get(Mailer::class),
            SystemTriggers::class       => $container->get(SystemTriggers::class),
            Sms::class                  => $container->get(Sms::class),
            Tasks::class                => $container->get(Tasks::class),
            ModuleManager::class        => $container->get(ModuleManager::class),
            RabbitMqHelper::class       => $container->get(RabbitMqHelper::class),
            HelperPluginManager::class  => $container->get('ViewHelperManager'),
        ];
    }

}
