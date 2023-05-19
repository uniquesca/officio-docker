<?php

namespace Forms\Service\Factory;

use Files\Service\Files;
use Laminas\ModuleManager\ModuleManager;
use Officio\PdfTron\Service\PdfTronPython;
use Psr\Container\ContainerInterface;
use Laminas\View\HelperPluginManager;
use Officio\Common\Service\Factory\BaseServiceFactory;

class FormsFactory extends BaseServiceFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        $classes = [
            Files::class               => $container->get(Files::class),
            HelperPluginManager::class => $container->get('ViewHelperManager'),
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