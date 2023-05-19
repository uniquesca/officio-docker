<?php

namespace Forms\Service\Factory;

use Files\Service\Files;
use Laminas\ModuleManager\ModuleManager;
use Officio\Comms\Service\Mailer;
use Officio\PdfTron\Service\PdfTronPython;
use Forms\Service\Forms;
use Psr\Container\ContainerInterface;
use Laminas\View\Renderer\PhpRenderer;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Common\Service\Encryption;
use Officio\Templates\SystemTemplates;

class PdfFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        $classes = [
            Files::class           => $container->get(Files::class),
            Forms::class           => $container->get(Forms::class),
            SystemTemplates::class => $container->get(SystemTemplates::class),
            PhpRenderer::class     => $container->get(PhpRenderer::class),
            Encryption::class      => $container->get(Encryption::class),
            Mailer::class          => $container->get(Mailer::class),
        ];
        /** @var ModuleManager $moduleManager */
        $moduleManager = $container->get(ModuleManager::class);
        $pdfTronModule = $moduleManager->getModule('Officio\PdfTron');
        if ($pdfTronModule) {
            $classes[PdfTronPython::class] = $container->get(PdfTronPython::class);
        }
        return $classes;
    }

}