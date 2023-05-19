<?php

namespace Homepage\Controller\Factory;

use Clients\Service\Clients;
use Officio\Service\Users;
use Psr\Container\ContainerInterface;
use Links\Service\Links;
use News\Service\News;
use Notes\Service\Notes;
use Officio\BaseControllerFactory;
use Officio\Service\Company;
use Prospects\Service\CompanyProspects;
use Rss\Service\Rss;
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
            Clients::class          => $container->get(Clients::class),
            Users::class            => $container->get(Users::class),
            Notes::class            => $container->get(Notes::class),
            Company::class          => $container->get(Company::class),
            Links::class            => $container->get(Links::class),
            News::class             => $container->get(News::class),
            Rss::class              => $container->get(Rss::class),
            Tasks::class            => $container->get(Tasks::class),
            CompanyProspects::class => $container->get(CompanyProspects::class)
        ];
    }
}
