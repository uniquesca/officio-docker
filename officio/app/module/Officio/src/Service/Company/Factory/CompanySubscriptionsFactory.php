<?php

namespace Officio\Service\Company\Factory;

use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\GstHst;
use Officio\Templates\SystemTemplates;

/**
 * Class CompanySubscriptionsFactory
 * @package Officio
 */
class CompanySubscriptionsFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            SystemTemplates::class => $container->get(SystemTemplates::class),
            GstHst::class          => $container->get(GstHst::class),
            'payment'              => $container->get('payment'),
        ];
    }

}