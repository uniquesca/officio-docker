<?php


namespace Prospects\Service\Factory;

use Officio\Comms\Service\Mailer;
use Psr\Container\ContainerInterface;
use Laminas\View\HelperPluginManager;
use Laminas\View\Renderer\PhpRenderer;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Service\GstHst;
use Officio\Service\PricingCategories;
use Officio\Templates\SystemTemplates;

class ProspectsFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class             => $container->get(Company::class),
            PricingCategories::class   => $container->get(PricingCategories::class),
            Country::class             => $container->get(Country::class),
            SystemTemplates::class     => $container->get(SystemTemplates::class),
            GstHst::class              => $container->get(GstHst::class),
            HelperPluginManager::class => $container->get('ViewHelperManager'),
            PhpRenderer::class         => $container->get(PhpRenderer::class),
            Mailer::class              => $container->get(Mailer::class),
            'payment'                  => $container->get('payment'),
        ];
    }

}