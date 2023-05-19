<?php

namespace Documents\Service\Factory;

use Clients\Service\Members;
use Documents\Service\Phpdocx;
use Files\Service\Files;
use Forms\Service\Pdf;
use Psr\Container\ContainerInterface;
use Laminas\View\HelperPluginManager;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Common\Service\Encryption;
use Officio\Service\Letterheads;

class DocumentsFactory extends BaseServiceFactory {

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Members::class             => $container->get(Members::class),
            Letterheads::class         => $container->get(Letterheads::class),
            Files::class               => $container->get(Files::class),
            Pdf::class                 => $container->get(Pdf::class),
            Phpdocx::class             => $container->get(Phpdocx::class),
            Encryption::class          => $container->get(Encryption::class),
            HelperPluginManager::class => $container->get('ViewHelperManager'),
        ];
    }

}