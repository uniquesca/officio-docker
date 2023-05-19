<?php

namespace Help;

use Help\Service\Help;
use Laminas\EventManager\EventInterface;
use Laminas\ModuleManager\Feature\ConfigProviderInterface;
use Laminas\Mvc\MvcEvent;
use Officio\Common\InitializableListener;
use Officio\Templates\SystemTemplates;

class Module implements ConfigProviderInterface, InitializableListener
{

    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function onBootstrap(EventInterface $e)
    {
        // $e is an instance of MvcEvent always
        /** @var MvcEvent $e */
        $application  = $e->getApplication();
        $eventManager = $application->getEventManager();

        $eventManager->attach(MvcEvent::EVENT_DISPATCH, [$this, 'onDispatch'], 100);
    }

    public function onDispatch(MvcEvent $e)
    {
        // Get fully qualified class name of the controller.
        $controllerClass = $e->getRouteMatch()->getParam('controller');
        // Get module name of the controller.
        $module = strtolower(substr($controllerClass, 0, strpos($controllerClass, '\\')));

        if ($module === 'help') {
            $viewModel = $e->getViewModel(); // View model here is actually a layout
            $viewModel->setTemplate('layout/api');
        }
    }

    /**
     * @inheritdoc
     */
    public function getListeners(string $class)
    {
        $listeners = [
            SystemTemplates::class => [
                SystemTemplates::EVENT_GET_AVAILABLE_FIELDS => [Help::class]
            ]
        ];
        return $listeners[$class] ?? [];
    }
}
