<?php

namespace Clients\Service\Factory;


use Clients\Service\Members;
use Files\Service\Files;
use Forms\Service\Pdf;
use Psr\Container\ContainerInterface;
use Laminas\View\Renderer\PhpRenderer;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;

/**
 * Class DbFactory
 * @package Officio
 */
class MembersPuaFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Members::class     => $container->get(Members::class),
            Company::class     => $container->get(Company::class),
            Files::class       => $container->get(Files::class),
            Pdf::class         => $container->get(Pdf::class),
            PhpRenderer::class => $container->get(PhpRenderer::class),
            Encryption::class  => $container->get(Encryption::class),
        ];
    }

}