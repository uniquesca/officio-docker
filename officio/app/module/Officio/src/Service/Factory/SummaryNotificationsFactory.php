<?php

namespace Officio\Service\Factory;

use Clients\Service\Members;
use Psr\Container\ContainerInterface;
use News\Service\News;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;
use Prospects\Service\CompanyProspects;
use Tasks\Service\Tasks;
use Officio\Templates\SystemTemplates;

/**
 * Class StatisticsFactory
 * @package Officio
 */
class SummaryNotificationsFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Members::class          => $container->get(Members::class),
            Company::class          => $container->get(Company::class),
            Tasks::class            => $container->get(Tasks::class),
            News::class             => $container->get(News::class),
            SystemTemplates::class  => $container->get(SystemTemplates::class),
            CompanyProspects::class => $container->get(CompanyProspects::class),
        ];
    }

}