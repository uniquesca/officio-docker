<?php

namespace Officio\Controller;

use Exception;
use Files\Model\FileInfo;
use Files\Service\Files;
use Laminas\Captcha\Image;
use Laminas\Filter\StripTags;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Session\Container;
use Laminas\Session\SessionManager;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\Api2\Authentication\ApiAuthenticationService;
use Officio\Api2\Model\AccessToken;
use Officio\Auth\Service\SecondFactorAuthenticator;
use Officio\BaseController;
use Officio\Common\Service\AccessLogs;
use Officio\Common\Service\Encryption;
use Officio\Comms\Service\Mailer;
use Officio\Service\AuthHelper;
use Officio\Service\Company;
use Officio\Service\OAuth2Client;
use Officio\Templates\Model\SystemTemplate;
use Officio\Templates\SystemTemplates;
use Laminas\Validator\EmailAddress;

/**
 * Auth controller is used to check if user is logged in,
 * if user is not registered - redirect to login page
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class AuthController extends BaseController
{
    /** @var AccessLogs */
    protected $_accessLogs;

    /** @var AuthHelper */
    protected $_authHelper;

    /** @var Company $_company */
    protected $_company;

    /** @var SystemTemplates */
    protected $_systemTemplates;

    /** @var SessionManager */
    protected $_session;

    /** @var Files */
    protected $_files;

    /** @var Mailer */
    protected $_mailer;

    /** @var SecondFactorAuthenticator */
    protected $_twofa;

    /** @var Encryption */
    protected $_encryption;

    /** @var ModuleManager */
    protected $_moduleManager;

    /** @var OAuth2Client */
    protected $_oauth2Client;

    public function initAdditionalServices(array $services)
    {
        $this->_session         = $services[SessionManager::class];
        $this->_accessLogs      = $services[AccessLogs::class];
        $this->_encryption      = $services[Encryption::class];
        $this->_authHelper      = $services[AuthHelper::class];
        $this->_oauth2Client    = $services[OAuth2Client::class];
        $this->_company         = $services[Company::class];
        $this->_systemTemplates = $services[SystemTemplates::class];
        $this->_files           = $services[Files::class];
        $this->_mailer          = $services[Mailer::class];
        $this->_twofa           = $services[SecondFactorAuthenticator::class];
        $this->_moduleManager   = $services[ModuleManager::class];
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

        $booRedirect = $this->params()->fromQuery('redirect');
        if (is_null($booRedirect)) {
            $booRedirect = $this->params('redirect');
        }
        if (is_null($booRedirect)) {
            $booRedirect = true;
        }

        $requestParams = $this->params()->fromRoute();
        if ($this->getRequest()->isXmlHttpRequest() && $booRedirect && isset($requestParams['logged_out_from'])) {
            list($module, $controller, $action) = explode('_', $requestParams['logged_out_from'] ?? '');
            if ($module != 'officio' || $controller != 'auth' || $action != 'login') {
                $requestParams['logged_out'] = $requestParams['logged_out'] ?? '';

                switch ($requestParams['logged_out']) {
                    case 'other_pc':
                        $reason = $this->_tr->translate('You have been logged out because you logged in on another computer or browser.');
                        break;

                    case 'timeout':
                        $reason = $this->_tr->translate('You have been logged out because session has timed out.');
                        break;

                    case 'business_hours':
                        $reason = $this->_tr->translate('Access is denied during non-office hours.<br/>Please try again later.');
                        break;

                    case 'access_denied':
                        $reason = $this->_tr->translate('Insufficient access rights.');
                        break;

                    default:
                        $reason = $this->_tr->translate('You have been logged out from this session.');
                        break;
                }

                if (!headers_sent()) {
                    $this->getResponse()->setStatusCode(401);
                }

                $view->setTerminal(true);
                $view->setTemplate('layout/plain');
                return $view->setVariable('content', $reason);
            }
        }

        $message = '';
        $config = $this->_config;
        if ($this->getRequest()->isPost()) {
            // collect the data from the user
            $username = substr(trim($this->params()->fromPost('username', '')), 0, 50);
            $password = substr(trim($this->params()->fromPost('password', '')), 0, $this->_settings->passwordMaxLength);

            $arrResult = $this->_authHelper->login($username, $password);
            $message   = $arrResult['message'];

            if ($arrResult['success']) {
                $memberId = $this->_auth->getCurrentUserId();
                if (isset($memberId) && !empty($memberId)) {
                    $this->_members->updateLoggedInOption($memberId, 'Y');
                }

                // Check if we need show any trial/renew/other dialog
                $oCompanySubscriptions = $this->_company->getCompanySubscriptions();
                $strSubscriptionNotice = $oCompanySubscriptions->checkCompanyStatus($arrResult['arrMemberInfo']);
                $oCompanySubscriptions->createSubscriptionCookie($strSubscriptionNotice);

                if ($booRedirect && $this->params()->fromPost('redirect_iframe', false)) {
                    return $this->redirect()->toUrl('/');
                }

                $view->setTerminal(true);
                $view->setTemplate('layout/plain');
                $view->setVariable('content', '');
            }
        }

        if (!$this->getRequest()->isXmlHttpRequest() && $booRedirect) {
            $arrLoginCompanyInfo = null;

            $loginHash = $this->params()->fromQuery('id');
            if (!empty($loginHash)) {
                $companyId = $this->_company->getCompanyIdByHash($loginHash);
                if (!empty($companyId)) {
                    $arrCompanyInfo = $this->_company->getCompanyInfo($companyId);

                    $arrLoginCompanyInfo = [
                        'companyLogo'    => $arrCompanyInfo['companyLogo'],
                        'companyLogoUrl' => $this->_company->getCompanyLogoLink($arrCompanyInfo),
                        'companyName'    => $arrCompanyInfo['companyName']
                    ];
                }
            }

            // Hide logo when needed
            $no_logo = $this->params()->fromQuery('no_logo');
            $this->layout()->setVariable('showTopLogo', is_null($no_logo));

            $this->layout()->setVariable('protocol', $config['urlSettings']['protocol']);
            $this->layout()->setVariable('googleTagManagerContainerId', $this->_config['site_version']['google_tag_manager']['container_id']);
            $this->layout()->setVariable('showSSLCertificateCheckImage', !empty($config['site_version']['show_ssl_certificate_check_image']));
            $this->layout()->setVariable('showPositivesslSSLCertificateCheckImage', !empty($config['site_version']['show_positivessl_ssl_certificate_check_image']));
            $this->layout()->setVariable('message', $message);
            $this->layout()->setVariable('autocomplete', $config['security']['autocompletion']['enabled']);
            $this->layout()->setVariable('passwordMaxLength', $this->_settings->passwordMaxLength);
            $this->layout()->setVariable('title', $this->_tr->translate("Log in"));
            $this->layout()->setVariable('companyInfo', $arrLoginCompanyInfo);

            $title = $this->_tr->translate('Client Login');
            $this->layout()->setVariable('title', $title);
            $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

            $this->layout()->setVariable('booUseOAuth', !empty($this->_config['security']['oauth_login']['enabled']));
            $this->layout()->setVariable('oAuthLoginButtonLabel', $this->_config['security']['oauth_login']['login_button_label']);
            $this->layout()->setVariable('oAuthIDIRLabel', $this->_config['security']['oauth_login']['single_sign_on_label']);
        } else {
            $view->setTerminal(true);
            $view->setTemplate('layout/plain');
            $view->setVariable('content', $message);
        }

        return $view;
    }

    public function oauthLoginAction()
    {
        if ($this->_auth->getCurrentUserId() > 0) {
            // The user is already authorized - redirect to the home page
            return $this->redirect()->toRoute('home');
        }

        $oAuthSettings = $this->_config['security']['oauth_login'];
        if (empty($oAuthSettings['enabled'])) {
            return $this->_unauthorizedResponse();
        }

        $provider = $this->_oauth2Client->getOAuthProvider();
        if (!is_null($provider)) {
            $authUrl = $provider->getAuthorizationUrl();

            $_SESSION['oauth2state'] = $provider->getState();

            return $this->redirect()->toUrl($authUrl);
        }

        $response = $this->getResponse();
        $response->setStatusCode(400);
        $response->setReasonPhrase('Bad Request');
        return $response;
    }

    public function oauthCallbackAction()
    {
        try {
            $oAuthSettings = $this->_config['security']['oauth_login'];

            $state = $this->params()->fromQuery('state', '');
            if (empty($oAuthSettings['enabled']) || empty($state) || ($state !== $_SESSION['oauth2state'])) {
                if (isset($_SESSION['oauth2state'])) {
                    unset($_SESSION['oauth2state']);
                }

                $strError = $this->_tr->translate('Incorrect incoming info.');
            } else {
                $provider = $this->_oauth2Client->getOAuthProvider();

                // Try to get an access token using the authorization code grant.
                $accessToken = $provider->getAccessToken('authorization_code', [
                    'code' => $this->params()->fromQuery('code', '')
                ]);

                if ($accessToken->hasExpired()) {
                    $accessToken = $provider->getAccessToken('refresh_token', [
                        'refresh_token' => $accessToken->getRefreshToken()
                    ]);
                }

                // Using the access token, we may look up details about the resource owner.
                $resourceOwner = $provider->getResourceOwner($accessToken);

                $arrResult = $this->_authHelper->oauthLogin($resourceOwner->toArray(), false);
                $strError  = $arrResult['message'];

                if ($arrResult['success']) {
                    $memberId = $this->_auth->getCurrentUserId();
                    if (isset($memberId) && !empty($memberId)) {
                        $this->_members->updateLoggedInOption($memberId, 'Y');
                    }

                    // Check if we need to show any trial/renew/other dialog
                    $oCompanySubscriptions = $this->_company->getCompanySubscriptions();
                    $strSubscriptionNotice = $oCompanySubscriptions->checkCompanyStatus($arrResult['arrMemberInfo']);
                    $oCompanySubscriptions->createSubscriptionCookie($strSubscriptionNotice);

                    return $this->redirect()->toUrl('/');
                }
            }
        } catch (Exception $e) {
            // Failed to get the access token or user details.
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = [
            'showTopLogo'  => true,
            'errorMessage' => $strError
        ];

        return new ViewModel($arrResult);
    }

    /**
     * Same as loginAction, however adjusted for API authentication.
     * Returns Access Token in case of success, otherwise 401 Unauthorizeds
     * TODO: This should be part of Officio\Api2 module, not this, but currently there is a dependency problem
     */
    public function apiLoginAction()
    {
        if (!$this->_moduleManager->getModule('Officio\\Api2')) {
            return $this->_unauthorizedResponse();
        }

        $view     = new JsonModel();
        $response = $this->getResponse();
        if ($this->getRequest()->isPost()) {
            // collect the data from the user
            $filter   = new StripTags();
            $username = substr(trim($filter->filter($this->params()->fromPost('username', ''))), 0, 100);
            $password = substr(trim($filter->filter($this->params()->fromPost('password', ''))), 0, 100);

            if (empty($username) || empty($password)) {
                $response->setStatusCode(401);
                $response->setReasonPhrase('Unauthorized');
                return $response;
            } else {
                $accessToken = $this->_authHelper->apiLogin($username, $password);
                if ($accessToken) {
                    $view->setVariable('access_token', $accessToken);
                    return $view;
                } else {
                    $response->setStatusCode(401);
                    $response->setReasonPhrase('Unauthorized');
                    return $response;
                }
            }
        } else {
            $response->setStatusCode(400);
            $response->setReasonPhrase('Bad Request');
            return $response;
        }
    }

    protected function _unauthorizedResponse()
    {
        $response = $this->getResponse();
        $response->setStatusCode(401);
        $response->setReasonPhrase('Unauthorized');
        return $response;
    }

    /**
     * Deletes Access Token if it's found and valid
     */
    public function apiLogoutAction()
    {
        if (!$this->_moduleManager->getModule('Officio\\Api2')) {
            return $this->_unauthorizedResponse();
        }

        // We don't want to force devs to submit access token via body explicitly, so
        // let's receive it via header, it's there anyways if we got here already.
        // The code below is basically a copy of Http::authenticate() + Http::_basicAuth() methods.
        $headerName = 'Authorization';
        $header     = $this->getRequest()->getHeader($headerName);
        if (!$header) {
            return $this->_unauthorizedResponse();
        }

        $authHeader = $header->getFieldValue();
        if (!$authHeader) {
            return $this->_unauthorizedResponse();
        }

        list(, $authHeaderCredentials) = explode(' ', $authHeader ?? '');
        if (empty($authHeaderCredentials)) {
            return $this->_unauthorizedResponse();
        }

        $auth = base64_decode($authHeaderCredentials);
        if (!ctype_print($auth)) {
            return $this->_unauthorizedResponse();
        }

        $pos = strpos($auth, ':');
        if ($pos === false) {
            return $this->_unauthorizedResponse();
        }
        list($token,) = explode(':', $auth ?? '', 2);
        if (empty($token)) {
            return $this->_unauthorizedResponse();
        }

        $accessToken = AccessToken::loadOne([
            'access_token' => $token
        ]);
        if (!$accessToken) {
            return $this->_unauthorizedResponse();
        }

        $accessToken->delete();

        return new JsonModel();
    }

    /**
     * Logout user from the web site and redirect to home page
     *
     */
    public function logoutAction()
    {
        // Log this event: success logout
        $this->_members->checkMemberAndLogout($this->_auth->getCurrentUserId());

        // Log user out
        $this->_authHelper->logout();

        if (isset($_SESSION['oauth2state'])) {
            unset($_SESSION['oauth2state']);
        }

        $booRedirect = $this->findParam('redirect');
        if (is_null($booRedirect)) {
            $booRedirect = $this->params('redirect');
        }
        if (is_null($booRedirect)) {
            $booRedirect = true;
        }
        if ($booRedirect) {
            return $this->redirect()->toRoute('home');
        }
    }

    public function retrievePasswordFormAction()
    {
        $captcha = new Image();
        $captcha->setTimeout('300')
            ->setWordlen('4')
            ->setWidth('225')
            ->setHeight('80')
            ->setFontSize('48')
            ->setGcFreq('100')
            ->setFont(getcwd() . DIRECTORY_SEPARATOR . $this->_config['directory']['captcha_font_path'] . DIRECTORY_SEPARATOR . $this->_config['directory']['captcha_font'])
            ->setImgDir($this->_config['directory']['captcha_images_path'])
            ->generate();
        $captchaId = $captcha->getId();
        $message = '<a href="#" onclick="updateCaptchaText(); return false;"><img src="' . $this->layout()->getVariable('baseUrl') . '/captcha/images/' . $captchaId . '.png" alt="" /></a>' .
            '<input type="hidden" value="' . $captchaId . '" id="captcha-id" />';

        $view = new ViewModel([
            'content' => $message
        ]);
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }

    public function recoveryAction()
    {
        $view = new ViewModel();

        $filter = new StripTags();

        $view->setVariable('passwordMaxLength', $this->_settings->passwordMaxLength);
        if (strtolower($_SERVER['REQUEST_METHOD']) == 'post') {
            $password1 = $filter->filter(trim($this->findParam('password1', '')));
            $password2 = $filter->filter(trim($this->findParam('password2', '')));

            $view->setVariable('hash', trim($this->findParam('hash', '')));

            if (empty($view->getVariable('hash'))) {
                return $this->redirect()->toRoute('home');
            }

            $strError = '';
            if ($password1 != $password2) {
                $strError = $this->_tr->translate('Passwords are not matched!');
            }

            if ((empty($password1) || empty($password2)) && empty($strError)) {
                $strError = $this->_tr->translate('Please enter the password');
            }

            $errMsg   = array();
            $memberId = 0;
            if (empty($strError)) {
                $retrievalsInfo = $this->_authHelper->getInfoFromPasswordRecoveryByHash($view->getVariable('hash'));
                if (!isset($retrievalsInfo['member_id'])) {
                    $strError = $this->_tr->translate('Incorrect or expired hash.');

                    $arrLog = array(
                        'log_section'     => 'login',
                        'log_action'      => 'reset_password_hash_fail',
                        'log_description' => sprintf('Incorrect hash provided: %s', $view->getVariable('hash')),
                    );
                    $this->_accessLogs->saveLog($arrLog);
                } else {
                    $memberId = $retrievalsInfo['member_id'];
                }
            }

            if (empty($strError)) {
                $arrMemberInfo = $this->_members->getMemberInfo($memberId);
                if (!$this->_authHelper->isPasswordValid($password1, $errMsg, $arrMemberInfo['username'], $memberId)) {
                    $strError = array_shift($errMsg); // get the first error message
                }

                if (empty($strError)) {
                    $arrMemberInfo['password'] = $password1;

                    // Send confirmation email to this user
                    // store old password to history table
                    $this->_authHelper->triggerPasswordHasBeenChanged($arrMemberInfo);

                    // store new passwords to `members` table
                    $memberId = $this->_authHelper->setNewPasswordAndDeleteRecoveryHash($password1, $view->getVariable('hash'));

                    if ($memberId) {
                        $view->setVariable('message', $this->_tr->translate(
                                'Password has been changed. <a href="' . $this->layout()->getVariable('baseUrl') . '">Please login now.</a>'
                            ));

                        $arrLog = array(
                            'log_section'     => 'login',
                            'log_action'      => 'reset_password_success',
                            'log_description' => 'Password was reset for {1}',
                            'log_company_id'  => $arrMemberInfo['company_id'],
                            'log_created_by'  => $memberId,
                        );
                        $this->_accessLogs->saveLog($arrLog);
                    } else {
                        $strError = $this->_tr->translate('Some error was occurred');
                    }
                }
            }

            $view->setVariable('errorMessage', $strError);
        } else {
            $hash = trim($this->params()->fromQuery('hash', ''));
            if (empty($hash)) {
                return $this->redirect()->toRoute('home');
            }

            $retrievalsInfo = $this->_authHelper->getInfoFromPasswordRecoveryByHash($hash);

            if (empty($retrievalsInfo)) {
                $arrLog = array(
                    'log_section'     => 'login',
                    'log_action'      => 'reset_password_hash_fail',
                    'log_description' => sprintf('Incorrect hash provided: %s', $hash),
                );
                $this->_accessLogs->saveLog($arrLog);

                return $this->redirect()->toRoute('home');
            }

            $view->setVariable('hash', $hash);
        }
        $view->setTerminal(true);
        $view->setTemplate('auth/recovery');
        return $view;
    }

    public function retrievePasswordAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);

        $message = '';

        $filter = new StripTags();
        $captchaInput = $this->findParam('captchaInput', '');
        $captchaId = $this->findParam('captchaId');
        $email = $filter->filter($this->findParam('email'));

        $emailValidator = new EmailAddress();
        if (!$emailValidator->isValid($email)) {
            $message = $this->_tr->translate('invalid_email');
        }

        if (!empty($captchaInput) && !empty($captchaId) && empty($message)) {
            $captchaWord = new Container('Laminas_Form_Captcha_' . $captchaId);
            if ($captchaWord->word && strtoupper($captchaInput) == strtoupper((string)$captchaWord->word)) {
                $membersInfo = $this->_members->getMembersByEmail($email);
                if (!empty($membersInfo)) {
                    $booAtLeastOneEmailSent = false;
                    foreach ($membersInfo as $memberInfo) {
                        if (empty($memberInfo['status'])) {
                            $message = $this->_tr->translate(
                                'This account currently is not active.<br/>Please contact your company admin to renew your password and update your account.'
                            );
                        } elseif (!empty($memberInfo['username'])) {
                            $hash = $this->_authHelper->generatePasswordRecoveryHash($memberInfo['member_id']);
                            if (!$hash) {
                                $message = $this->_tr->translate('Internal error. Please try again latter.');
                                break;
                            }

                            // send email to user
                            $memberInfo['hash'] = $hash;

                            $template          = SystemTemplate::loadOne(['title' => 'Forgotten Email']);
                            $replacements      = $this->_members->getTemplateReplacements($memberInfo);
                            $replacements      += $this->_systemTemplates->getGlobalTemplateReplacements();
                            $processedTemplate = $this->_systemTemplates->processTemplate($template, $replacements, ['to', 'subject', 'template']);
                            $this->_systemTemplates->sendTemplate($processedTemplate);

                            $arrLog = array(
                                'log_section'     => 'login',
                                'log_action'      => 'forgotten_email_sent',
                                'log_description' => sprintf('Forgotten Email was sent to %s for {1}', $email),
                                'log_company_id'  => $memberInfo['company_id'],
                                'log_created_by'  => $memberInfo['member_id'],
                            );
                            $this->_accessLogs->saveLog($arrLog);

                            $booAtLeastOneEmailSent = true;
                        } else {
                            $message = sprintf(
                                $this->_tr->translate(
                                    "No active account associated to %s was found. Please contact your company admin to provide you with the required information."
                                ),
                                $email
                            );
                        }
                    }

                    // If email was sent at least one time - don't show an error message
                    if ($booAtLeastOneEmailSent) {
                        $message = '';
                    }
                } else {
                    $message = $this->_tr->translate('invalid_email');
                }
            } else {
                $message = $this->_tr->translate('invalid_captcha');
            }
        } else {
            $message = $this->_tr->translate('Captcha Error. Please try again latter.');
        }


        if (empty($message)) {
            $message = $this->_tr->translate(
                'Thank you. Your login details were sent to your e-mail. Please also ensure to check your spam folder.'
            );
        } else {
            switch ($message) {
                case 'invalid_email':
                    $msgToLog = sprintf('Invalid email: %s', $email);
                    break;

                case 'invalid_captcha':
                    $msgToLog = sprintf('Invalid captcha entered for %s email', $email);
                    break;

                default:
                    $msgToLog = $message;
                    break;
            }
            $arrLog = array(
                'log_section' => 'login',
                'log_action' => 'reset_password_fail',
                'log_description' => $msgToLog
            );
            $this->_accessLogs->saveLog($arrLog);
        }

        $view->setVariables(
            [
                'content' => $message
            ],
            true
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');
        return $view;
    }

    public function getClientCompanyLogoAction()
    {
        $strError = '';
        try {
            $hash      = $this->params()->fromQuery('id');
            $companyId = $this->_company->getCompanyIdByHash($hash);

            if (empty($companyId)) {
                $strError = $this->_tr->translate('Incorrect incoming info.');
            }

            if (empty($strError)) {
                $arrCompanyInfo = $this->_company->getCompanyInfo($companyId);
                if (!empty($arrCompanyInfo['companyLogo'])) {
                    $fileInfo = $this->_files->getCompanyLogo($companyId, $arrCompanyInfo['companyLogo'], $this->_company->isCompanyStorageLocationLocal($companyId));
                    if ($fileInfo instanceof FileInfo) {
                        if ($fileInfo->local) {
                            return $this->downloadFile($fileInfo->path, $fileInfo->name, $fileInfo->mime, true, false);
                        } else {
                            $url = $this->_files->getCloud()->getFile($fileInfo->path, $fileInfo->name, true, false);
                            if ($url) {
                                return $this->redirect()->toUrl($url);
                            }
                        }
                    }
                } else {
                    $strError = $this->_tr->translate('No logo was uploaded for the company.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $view = new ViewModel(
            ['content' => $strError]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }
}
