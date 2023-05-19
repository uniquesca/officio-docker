<?php

namespace Officio\Service\Company\Factory;

use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Common\Service\Encryption;
use Officio\Service\Roles;

/**
 * Class CompanyDivisionsFactory
 * @package Officio
 */
class CompanyDivisionsFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Roles::class      => $container->get(Roles::class),
            Encryption::class => $container->get(Encryption::class),
        ];
    }

}