<?php

namespace Superadmin\Controller\Factory;

use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Comms\Service\Mailer;
use Officio\Service\Company;
use Prospects\Service\Prospects;
use Officio\Templates\SystemTemplates;

/**
 * This is the factory for ManageTemplatesController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class ManageTemplatesControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class         => $container->get(Company::class),
            Prospects::class       => $container->get(Prospects::class),
            Mailer::class          => $container->get(Mailer::class),
            SystemTemplates::class => $container->get(SystemTemplates::class),
        ];
    }

}

