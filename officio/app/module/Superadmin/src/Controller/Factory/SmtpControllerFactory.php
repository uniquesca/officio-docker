<?php

namespace Superadmin\Controller\Factory;

use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Common\Service\Encryption;

/**
 * This is the factory for SmtpController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class SmtpControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Encryption::class => $container->get(Encryption::class)
        ];
    }

}
