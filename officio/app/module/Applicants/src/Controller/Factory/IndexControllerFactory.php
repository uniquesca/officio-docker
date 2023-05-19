<?php

namespace Applicants\Controller\Factory;

use Clients\Service\Clients;
use Clients\Service\ClientsVisaSurvey;
use Forms\Service\Pdf;
use Psr\Container\ContainerInterface;
use Officio\BaseControllerFactory;
use Officio\Common\Service\Encryption;
use Officio\Service\Company;
use Officio\Service\Payment\Stripe;
use Tasks\Service\Tasks;
use Officio\Service\SystemTriggers;

/**
 * This is the factory for IndexController. Its purpose is to instantiate the controller
 * and inject dependencies into its constructor.
 */
class IndexControllerFactory extends BaseControllerFactory
{

    protected function retrieveAdditionalServiceList(ContainerInterface $container)
    {
        return [
            Company::class => $container->get(Company::class),
            Clients::class => $container->get(Clients::class),
            SystemTriggers::class => $container->get(SystemTriggers::class),
            Tasks::class => $container->get(Tasks::class),
            Pdf::class => $container->get(Pdf::class),
            ClientsVisaSurvey::class => $container->get(ClientsVisaSurvey::class),
            Stripe::class => $container->get(Stripe::class),
            Encryption::class => $container->get(Encryption::class),
        ];
    }

}
