<?php

namespace Superadmin\Controller\Factory;

use Psr\Container\ContainerInterface;
use News\Service\News;
use Officio\BaseControllerFactory;

/**
 * This is the factory for NewsController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class NewsControllerFactory extends BaseControllerFactory
{
    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            News::class => $container->get(News::class)
        ];
    }
}
