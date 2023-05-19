<?php

namespace Superadmin;

use Officio\Common\Service\AuthenticationService;
use Laminas\EventManager\EventInterface;
use Laminas\ModuleManager\Feature\ConfigProviderInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\View\HelperPluginManager;
use Officio\Common\Service\Settings;
use Officio\Service\Navigation;

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
        $application  = $e->getApplication();
        $eventManager = $application->getEventManager();

        $eventManager->attach(MvcEvent::EVENT_DISPATCH, [$this, 'onDispatch'], 100);
    }

    public function onDispatch(MvcEvent $e)
    {
        list($module, $controller, $action) = Settings::getModuleControllerAction($e);
        if ($module === 'superadmin') {
            $serviceManager = $e->getApplication()->getServiceManager();
            /** @var HelperPluginManager $viewHelper */
            $viewHelper = $serviceManager->get('ViewHelperManager');

            $layout     = $e->getViewModel(); // View model here is actually a layout
            $headLink   = $viewHelper->get('headLink');
            $headScript = $viewHelper->get('headScript');

            $booUseSuperadminScripts = true;
            if ($controller == 'company-website' && in_array($action, array('builder', 'homepage', 'about', 'canada', 'assessment', 'immigration', 'contact'))) {
                $layout->setTemplate('layout/websites');
                $headLink->appendStylesheet($layout->getVariable('topCssUrl') . '/websites.css');
            } else {
                /** @var AuthenticationService $auth */
                $auth     = $serviceManager->get('auth');
                $identity = $auth->getIdentity();

                if (isset($identity->userType) && ($identity->userType == 2)) {
                    // Admin
                    // This layout will be used in frame
                    $layout->setTemplate('layout/admin');
                    /** @var Navigation $navigation */
                    $navigation = $serviceManager->get(Navigation::class);
                    $layout->setVariable('adminNavigation', $navigation->getAdminNavigation());
                    // Setting title
                    $viewHelper->get('HeadTitle')->append('Admin');
                } else {
                    // Superadmin
                    if ($controller == 'index' && $action == 'home' && !empty($identity)) {
                        // Setting doc type
                        $viewHelper->get('Doctype')->setDoctype('XHTML5');

                        $layout->setTemplate('layout/superadmin_home');
                        $booUseSuperadminScripts = false;
                    } else {
                        $layout->setTemplate('layout/superadmin');
                        if (empty($identity)) {
                            $layout->setVariable('booShowHeaderAndFooter', true);
                        } else {
                            /** @var Navigation $navigation */
                            $navigation = $serviceManager->get(Navigation::class);
                            $layout->setVariable('adminNavigation', $navigation->getAdminNavigation());
                        }
                    }

                    // Setting title
                    $viewHelper->get('HeadTitle')->append('Super Admin');
                }


                if ($booUseSuperadminScripts) {
                    $headLink->appendStylesheet($layout->getVariable('cssUrl') . '/main.css');
                    $headScript->appendFile($layout->getVariable('jsUrl') . '/main.js');
                } else {
                    $headLink->appendStylesheet($layout->getVariable('topCssUrl') . '/main.css');
                    $headLink->appendStylesheet($layout->getVariable('topCssUrl') . '/ie_fix.css', 'screen', 'IE');
                    $headScript->appendFile($layout->getVariable('topJsUrl') . '/main.js');
                }

                $headLink->appendStylesheet($layout->getVariable('topCssUrl') . '/themes/' . $layout->getVariable('theme') . '.css');
                $headLink->appendStylesheet($layout->getVariable('topBaseUrl') . '/assets/plugins/line-awesome/dist/line-awesome/css/line-awesome.min.css');
            }
        }
    }
}
