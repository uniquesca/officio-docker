<?php

namespace Api;

use Laminas\EventManager\EventInterface;
use Laminas\ModuleManager\Feature\ConfigProviderInterface;
use Laminas\Mvc\MvcEvent;

class Module implements ConfigProviderInterface
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
        $eventManager = $application->getEventManager();

        $eventManager->attach(MvcEvent::EVENT_DISPATCH, [$this, 'onDispatch'], 100);
    }

    public function onDispatch(MvcEvent $e)
    {
        // Get fully qualified class name of the controller.
        $controllerClass = $e->getRouteMatch()->getParam('controller', '');
        // Get module name of the controller.
        $module = strtolower(substr($controllerClass, 0, strpos($controllerClass, '\\')));

        if ($module === 'api') {
            $viewModel = $e->getViewModel(); // View model here is actually a layout
            $viewModel->setTemplate('layout/api');
        }
    }
}
