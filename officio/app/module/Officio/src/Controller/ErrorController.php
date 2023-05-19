<?php

namespace Officio\Controller;

use Laminas\View\Model\ViewModel;
use Officio\BaseController;

/**
 * ErrorController - The default error controller class
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ErrorController extends BaseController
{

    /**
     * This action handles
     *    - Application errors
     *    - Errors in the controller chain arising from missing
     *      controller classes and/or action methods
     */
    public function errorAction()
    {
        $view = new ViewModel();

        $view->setVariable('title', 'Error');

        $errors       = $this->findParam('error_handler');
        $booShowError = (bool)$this->_settings['show_error_details'];

        switch ($errors->type) {
            case 'EXCEPTION_NO_RESOURCE_DEFINED':
                $this->getResponse()->setStatusCode(404);
                $view->setVariable('message', _('Page not found'));
                $view->setVariable('code', 404);
                $errorMsg = sprintf(_('Resource not defined: "%s" . "%s"'), $errors->moduleName, $errors->controllerName);

                $view->setVariable('info', '');

                $this->_log->debugErrorToFile('', trim($errorMsg), 'resource');
                break;

            default:
                // application error
                $this->getResponse()->setStatusCode(500);
                $view->setVariable('message', _('Application error'));
                $view->setVariable('code', 500);
                $view->setVariable('info', $booShowError && isset($errors->exception) ? $errors->exception : '');

                $this->_log->debugErrorToFile($errors->exception->getMessage(), $errors->exception->getTraceAsString());
                break;
        }

        return $view;
    }

    public function accessDeniedAction()
    {
        return new ViewModel(
            ['title' => 'Access denied']
        );
    }

}
