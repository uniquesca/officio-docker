<?php

namespace News\Service\Factory;

use Clients\Service\Members;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;

/**
 * Class NewsFactory
 * @package News\Service\Factory
 */
class NewsFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Members::class => $container->get(Members::class),
        ];
    }

}