<?php

namespace Officio\Service\Factory;

use Files\Service\Files;
use Officio\Service\Company;
use Psr\Container\ContainerInterface;
use Officio\Common\Service\Factory\BaseServiceFactory;

/**
 * Class LetterheadsFactory
 * @package Officio
 */
class LetterheadsFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class => $container->get(Company::class),
            Files::class => $container->get(Files::class),
        ];
    }

}