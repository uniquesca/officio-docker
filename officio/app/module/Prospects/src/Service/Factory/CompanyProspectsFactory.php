<?php


namespace Prospects\Service\Factory;


use Clients\Service\Clients;
use Files\Service\Files;
use Forms\Service\Pdf;
use Psr\Container\ContainerInterface;
use Laminas\View\Renderer\PhpRenderer;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Common\Service\Encryption;
use Officio\Service\SystemTriggers;
use Officio\Templates\SystemTemplates;
use Tasks\Service\Tasks;

class CompanyProspectsFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Clients::class         => $container->get(Clients::class),
            Files::class           => $container->get(Files::class),
            Company::class         => $container->get(Company::class),
            Country::class         => $container->get(Country::class),
            Pdf::class             => $container->get(Pdf::class),
            SystemTriggers::class  => $container->get(SystemTriggers::class),
            Tasks::class           => $container->get(Tasks::class),
            PhpRenderer::class     => $container->get(PhpRenderer::class),
            Encryption::class      => $container->get(Encryption::class),
            SystemTemplates::class => $container->get(SystemTemplates::class),
        ];
    }

}