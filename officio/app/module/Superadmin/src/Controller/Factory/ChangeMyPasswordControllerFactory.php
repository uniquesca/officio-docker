<?php

namespace Superadmin\Controller\Factory;

use Psr\Container\ContainerInterface;
use Officio\Service\AuthHelper;
use Officio\BaseControllerFactory;
use Officio\Common\Service\Encryption;

/**
 * This is the factory for ChangeMyPasswordController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ChangeMyPasswordControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            AuthHelper::class => $container->get(AuthHelper::class),
            Encryption::class => $container->get(Encryption::class),
        ];
    }

}
