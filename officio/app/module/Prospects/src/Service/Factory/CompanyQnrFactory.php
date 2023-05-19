<?php


namespace Prospects\Service\Factory;

use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Service\SystemTriggers;

class CompanyQnrFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            'ViewHelperManager' => $container->get('ViewHelperManager'),
            Country::class => $container->get(Country::class),
            Company::class => $container->get(Company::class),
            SystemTriggers::class => $container->get(SystemTriggers::class),
        ];
    }

}