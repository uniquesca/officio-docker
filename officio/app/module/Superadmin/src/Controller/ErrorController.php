<?php

/**
 * ErrorController - The default error controller class
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */

namespace Superadmin\Controller;

use Laminas\Http\PhpEnvironment\Response;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;

class ErrorController extends BaseController
{
    /**
     * This action handles
     *    - Application errors
     *    - Errors in the controller chain arising from missing
     *      controller classes and/or action methods
     */
    public function errorAction ()
    {
        $view = new ViewModel();
        $view->setTerminal(true);

        $view->setVariable('title','Error');

        $errors = $this->findParam('error_handler');
        $booShowError = (bool) $this->_settings['show_error_details'];

        switch ($errors->type) {
            default:
                // application error
                /** @var Response $response */
                $response = $this->getResponse();
                $response->setStatusCode(500);
                $view->setVariable('message','Application error');
                $view->setVariable('code',500);
                $view->setVariable('info', $booShowError ? $errors->exception : '');
                
                $this->_log->debugErrorToFile($errors->exception->getMessage(), $errors->exception->getTraceAsString());
                break;
        }

        return $view;
    }

    public function accessDeniedAction ()
    {
        $view = new ViewModel();
        $this->layout()->setVariable('title', 'Access denied');

        return $view;
    }

}