<?php

namespace Officio\Service\Factory;

use Files\Service\Files;
use Officio\Comms\Service\Mailer;
use Psr\Container\ContainerInterface;
use Laminas\View\HelperPluginManager;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Common\Service\Country;
use Officio\Common\Service\Encryption;
use Officio\Service\Roles;
use Officio\Service\AutomatedBillingLog;
use Officio\Service\SystemTriggers;
use Officio\Service\PricingCategories;
use Officio\Service\GstHst;
use Officio\Templates\SystemTemplates;

/**
 * Class LogFactory
 * @package Officio
 */
class CompanyFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            GstHst::class              => $container->get(GstHst::class),
            Mailer::class              => $container->get(Mailer::class),
            PricingCategories::class   => $container->get(PricingCategories::class),
            AutomatedBillingLog::class => $container->get(AutomatedBillingLog::class),
            Files::class               => $container->get(Files::class),
            Roles::class               => $container->get(Roles::class),
            Country::class             => $container->get(Country::class),
            SystemTriggers::class      => $container->get(SystemTriggers::class),
            Encryption::class          => $container->get(Encryption::class),
            SystemTemplates::class     => $container->get(SystemTemplates::class),
            HelperPluginManager::class => $container->get('ViewHelperManager'),
        ];
    }

}