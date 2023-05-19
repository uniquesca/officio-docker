<?php

namespace Officio\Service\Company\Factory;

use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\AutomatedBillingLog;
use Officio\Service\GstHst;
use Officio\Templates\SystemTemplates;

/**
 * Class CompanyDivisionsFactory
 * @package Officio
 */
class CompanyInvoiceFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            SystemTemplates::class     => $container->get(SystemTemplates::class),
            GstHst::class              => $container->get(GstHst::class),
            AutomatedBillingLog::class => $container->get(AutomatedBillingLog::class),
            'payment'                  => $container->get('payment'),
        ];
    }

}