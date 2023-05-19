<?php

namespace Files;

use Laminas\EventManager\EventInterface;
use Laminas\ModuleManager\Feature\BootstrapListenerInterface;
use Laminas\ModuleManager\Feature\ConfigProviderInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\Mvc\ResponseSender\SendResponseEvent;
use Laminas\Mvc\SendResponseListener;

class Module implements ConfigProviderInterface, BootstrapListenerInterface
{

    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function onBootstrap(EventInterface $e)
    {
        // $e is an instance of MvcEvent always
        /** @var MvcEvent $e */
        $application = $e->getApplication();
        $serviceManager = $application->getServiceManager();
        /** @var SendResponseListener $sendResponseListener */
        $sendResponseListener = $serviceManager->get('Laminas\Mvc\SendResponseListener');
        $sendResponseListener->getEventManager()->attach(SendResponseEvent::EVENT_SEND_RESPONSE, new BufferedStreamResponseSender());
    }

}
