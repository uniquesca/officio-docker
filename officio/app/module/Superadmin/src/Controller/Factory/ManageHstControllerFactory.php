<?php

namespace Superadmin\Controller\Factory;

use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Service\GstHst;

/**
 * This is the factory for ManageHstController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageHstControllerFactory extends BaseControllerFactory
{
    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            GstHst::class => $container->get(GstHst::class),
        ];
    }
}
