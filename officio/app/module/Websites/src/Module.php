<?php

namespace Websites;

use Laminas\EventManager\EventInterface;
use Laminas\ModuleManager\Feature\ConfigProviderInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\View\HelperPluginManager;
use Officio\Common\Service\Settings;
use Officio\Service\SystemTriggersListener;
use Websites\Service\CompanyWebsites;

class Module implements ConfigProviderInterface, SystemTriggersListener
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
        list($module, $controller, $action) = Settings::getModuleControllerAction($e);
        if ($module === 'websites') {
            if(!in_array($action, ['login', 'logout', 'send-message'])) {
                // Redirect to the correct action if needed
                $routeMatch = $e->getRouteMatch();

                $page = $e->getRouteMatch()->getParam('page');
                if (in_array($page, ['login', 'logout'])) {
                    $routeMatch->setParam('action', $page);
                } else {
                    //$entranceName = $e->getRouteMatch()->getParam('entrance');
                    // $old          = $companyWebsites->builderIsNew($entranceName);
                    $arrPages     = ['homepage', 'about', 'canada', 'immigration', 'assessment', 'contact'];
                    // if (!empty($old)) {
                    //     $routeMatch->setParam('action', 'new');
                    //     if (in_array($action, $arrPages)) {
                    //         $routeMatch->setParam('page', $action);
                    //     }
                    // } elseif (in_array($action, $arrPages)) {
                    if (in_array($action, $arrPages)) {
                        $routeMatch->setParam('action', 'index');
                        $routeMatch->setParam('page', $action);
                    }
                }
            }


            $viewModel = $e->getViewModel(); // View model here is actually a layout
            $viewModel->setTemplate('layout/websites');

            $serviceManager = $e->getApplication()->getServiceManager();
            /** @var HelperPluginManager $viewHelper */
            $viewHelper = $serviceManager->get('ViewHelperManager');
            $headLink   = $viewHelper->get('headLink');
            $headLink->appendStylesheet($viewModel->getVariable('topCssUrl') . '/websites.css');
        }
    }

    public function getSystemTriggerListeners()
    {
        return [CompanyWebsites::class];
    }

}
