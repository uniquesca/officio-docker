<?php

namespace News\Controller\Factory;

use Psr\Container\ContainerInterface;
use News\Service\News;
use Officio\BaseControllerFactory;

/**
 * This is the factory for IndexController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class IndexControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            News::class => $container->get(News::class)
        ];
    }

}
