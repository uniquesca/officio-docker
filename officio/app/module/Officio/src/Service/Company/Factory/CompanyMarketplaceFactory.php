<?php

namespace Officio\Service\Company\Factory;

use Officio\Comms\Service\Mailer;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Common\Service\Encryption;
use Officio\Service\Roles;

/**
 * Class CompanyMarketplaceFactory
 * @package Officio
 */
class CompanyMarketplaceFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Roles::class      => $container->get(Roles::class),
            Encryption::class => $container->get(Encryption::class),
            Mailer::class     => $container->get(Mailer::class),
        ];
    }

}