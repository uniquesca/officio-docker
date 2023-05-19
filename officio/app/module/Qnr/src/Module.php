<?php

namespace Qnr;

use Laminas\EventManager\EventInterface;
use Laminas\ModuleManager\Feature\ConfigProviderInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\View\HelperPluginManager;

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
        $controllerClass = $e->getRouteMatch()->getParam('controller');
        // Get module name of the controller.
        $module = strtolower(substr($controllerClass, 0, strpos($controllerClass, '\\')));

        if ($module === 'qnr') {
            $viewModel = $e->getViewModel(); // View model here is actually a layout
            $viewModel->setTemplate('layout/qnr');


            $serviceManager = $e->getApplication()->getServiceManager();
            /** @var HelperPluginManager $viewHelper */
            $viewHelper = $serviceManager->get('ViewHelperManager');

            // Setting doc type
            $viewHelper->get('Doctype')->setDoctype('XHTML5');

            // Setting content type and character set
            $headMeta = $viewHelper->get('headMeta');
            $headMeta->appendHttpEquiv('Content-Type', 'text/html; charset=UTF-8');
            $headMeta->appendHttpEquiv('Content-Language', 'en-US');
            $headMeta->appendHttpEquiv('X-UA-Compatible', 'IE=Edge');

            // Setting title
            $viewHelper->get('HeadTitle')->append('Questionnaire');

            $viewHelper->get('headScript')->appendFile($viewModel->getVariable('jsUrl') . '/main.js');
        }
    }
}
