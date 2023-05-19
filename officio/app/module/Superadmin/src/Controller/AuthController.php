<?php

namespace Superadmin\Controller;

use Laminas\Session\SessionManager;
use Laminas\View\Model\ViewModel;
use Officio\Service\AuthHelper;
use Officio\BaseController;

/**
 * Auth controller is used to check if user is logged in,
 * if user is not registered - redirect to login page
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class AuthController extends BaseController
{

    /** @var AuthHelper */
    protected $_authHelper;

    /** @var SessionManager */
    protected $_session;

    public function initAdditionalServices(array $services)
    {
        $this->_session    = $services[SessionManager::class];
        $this->_authHelper = $services[AuthHelper::class];
    }

    /**
     * Default action, automatically redirect to home page
     *
     */
    public function indexAction()
    {
        return $this->redirect()->toRoute('home');
    }

    /**
     * Login user: check received POST info , check if credentials are correct
     * If credentials are incorrect - error message will be returned
     *
     */
    public function loginAction()
    {
        $view = new ViewModel();

        if ($this->_auth->getCurrentUserId() > 0) {
            // The user is already authorized - redirect to the home page
            if ($this->getRequest()->isPost()) {
                // Js will redirect correctly
                $view->setTerminal(true);
                $view->setTemplate('layout/plain');
                $view->setVariable('content', '');
                return $view;
            } else {
                return $this->redirect()->toRoute('home');
            }
        }

        $username   = '';
        $errMessage = '';

        if ($this->getRequest()->isPost()) {
            $username = substr(trim($this->params()->fromPost('username', '')), 0, 50);
            $password = substr(trim($this->params()->fromPost('password', '')), 0, $this->_settings->passwordMaxLength);

            $arrResult = $this->_authHelper->login($username, $password, true, true);

            if ($arrResult['success']) {
                // All is okay, user logged in

                $memberId = $this->_auth->getCurrentUserId();
                if (isset($memberId) && !empty($memberId)) {
                    $this->_members->updateLoggedInOption($memberId, 'Y');
                }

                return $this->redirect()->toRoute('home');
            } else {
                // Set up error message
                $errMessage = $arrResult['message'];
            }
        }

        $view->setVariable('errorMessage', empty($errMessage) ? '&nbsp;' : $errMessage);
        $this->layout()->setVariable('booUseOtherContent', true);
        $view->setVariable('userName', $username);
        $view->setVariable('autocomplete', $this->_config['security']['autocompletion']['enabled']);

        $view->setVariable('passwordMaxLength', $this->_settings->passwordMaxLength);

        return $view;
    }

    /**
     * Logout user from the website and redirect to home page
     *
     */
    public function logoutAction()
    {
        // Log this event: success logout
        $this->_members->checkMemberAndLogout($this->_auth->getCurrentUserId());

        // Log user out
        $this->_authHelper->logout();

        return $this->redirect()->toUrl('/superadmin/');
    }
}