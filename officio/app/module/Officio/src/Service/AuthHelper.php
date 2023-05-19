<?php

namespace Officio\Service;

use Clients\Service\BusinessHours;
use Clients\Service\Clients;
use Exception;
use Laminas\Authentication\Adapter\AbstractAdapter;
use Laminas\Authentication\Adapter\DbTable\CallbackCheckAdapter;
use Laminas\Authentication\Storage\NonPersistent;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Session\SessionManager;
use Officio\Api2\Authentication\ApiAuthenticationService;
use Officio\Api2\Model\AccessToken;
use Officio\Common\Service\BaseService;
use Officio\Common\Service\AccessLogs;
use Officio\Common\Service\Encryption;
use Officio\Common\Service\Settings;
use Officio\Common\ServiceContainerHolder;
use Officio\Templates\Model\SystemTemplate;
use Officio\Templates\SystemTemplates;
use Uniques\Php\StdLib\DateTimeTools;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class AuthHelper extends BaseService
{

    use ServiceContainerHolder;

    /** @var SessionManager */
    private $_session;

    /** @var AccessLogs */
    private $_accessLogs;

    /** @var Clients */
    private $_clients;

    /** @var Users */
    private $_users;

    /** @var Company */
    protected $_company;

    /** @var BusinessHours */
    protected $_businessHours;

    /** @var Roles */
    protected $_roles;

    /** @var SystemTemplates */
    protected $_systemTemplates;

    /** @var ModuleManager */
    protected $_moduleManager;

    /** @var Encryption */
    protected $_encryption;

    /** @var null|ApiAuthenticationService */
    protected $_apiAuth;

    public function initAdditionalServices(array $services)
    {
        $this->_accessLogs      = $services[AccessLogs::class];
        $this->_session         = $services[SessionManager::class];
        $this->_clients         = $services[Clients::class];
        $this->_users           = $services[Users::class];
        $this->_company         = $services[Company::class];
        $this->_businessHours   = $services[BusinessHours::class];
        $this->_roles           = $services[Roles::class];
        $this->_systemTemplates = $services[SystemTemplates::class];
        $this->_moduleManager   = $services[ModuleManager::class];
        $this->_encryption      = $services[Encryption::class];
        $this->_apiAuth         = $services['api-auth'];
    }


    /**
     * 1. Store old password to members_last_passwords table.
     * 2. Send 'password was changed' email to the member
     *
     * @note in config send_password_changed_email must be set to 1 to send email
     * @param array $arrMemberInfo
     * @return bool true if email was sent (but this doesn't mean that it was received)
     */
    public function triggerPasswordHasBeenChanged($arrMemberInfo)
    {
        $booSent = false;

        try {
            // Check config setting
            if (!$this->_config['security']['send_password_changed_email']) {
                $booSent = true;
            } elseif (is_array($arrMemberInfo) && array_key_exists('member_id', $arrMemberInfo) && array_key_exists('emailAddress', $arrMemberInfo)) {
                // Get required template
                $template = SystemTemplate::loadOne(['title' => 'Password Changed']);
                if ($template) {
                    // Parse template, replace required info
                    $replacements      = $this->_clients->getTemplateReplacements($arrMemberInfo);
                    $processedTemplate = $this->_systemTemplates->processTemplate($template, $replacements, ['to', 'subject', 'template']);
                    $result            = $this->_systemTemplates->sendTemplate($processedTemplate);

                    // Send parsed template
                    $booSent = $result['sent'] ?? false;
                }
            }

            $this->storeOldPasswordToHistory($arrMemberInfo['member_id']);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSent;
    }

    /**
     * Prepares identity for saving into the storage
     * @param $data
     * @param bool $updateLastLogin
     */
    public function prepareIdentity($data, $updateLastLogin = false)
    {
        //get member type and company
        $arrMemberRoles       = $this->_clients->getMemberRoles($data->member_id, false);
        $userInfo             = $this->_users->getUserInfo($data->member_id);
        $arrCompanyInfo       = $this->_company->getCompanyAndDetailsInfo($data->company_id);
        $arrUserAllowedFields = $this->_clients->getFields()->getUserAllowedFieldIds($data->company_id, $data->member_id, true);
        $isLocal              = $this->_company->isCompanyStorageLocationLocal($data->company_id);

        // Get current user's time zone
        // If not set - use from the company's settings
        $userTimeZone = $arrCompanyInfo['companyTimeZone'];
        if (!empty($userInfo['timeZone'])) {
            $arrTimeZones = array_keys($this->_settings->getWebmailTimeZones(false));
            if (array_key_exists($userInfo['timeZone'], $arrTimeZones)) {
                $userTimeZone = $arrTimeZones[$userInfo['timeZone']];
            }
        }

        $clientTypeId             = $this->_clients::getMemberType('client');
        $adminTypeId              = $this->_clients::getMemberType('admin');
        $adminAndSuperadminTypeId = $this->_clients::getMemberType('superadmin_admin');
        $superadminTypeId         = $this->_clients::getMemberType('superadmin');
        $currentUserType          = $data->userType ?? false;

        $data->is_client                   = in_array($currentUserType, $clientTypeId);
        $data->is_admin                    = in_array($currentUserType, $adminAndSuperadminTypeId);
        $data->is_strict_admin             = in_array($currentUserType, $adminTypeId);
        $data->is_superadmin               = in_array($currentUserType, $superadminTypeId);
        $data->user_role                   = $arrMemberRoles;
        $data->user_allowed_fields         = $arrUserAllowedFields;
        $data->company_id                  = $userInfo['company_id'];
        $data->company_name                = $arrCompanyInfo['companyName'];
        $data->company_storage_local       = $isLocal;
        $data->queue_show_in_left_panel    = 'Y'; // $arrMemberInfo['queue_show_in_left_panel'];
        $data->company_timezone            = $arrCompanyInfo['companyTimeZone'];
        $data->user_timezone               = $userTimeZone;
        $data->employers_module_enabled    = $arrCompanyInfo['employers_module_enabled'];
        $data->log_client_changes_enabled  = $arrCompanyInfo['log_client_changes_enabled'];
        $data->default_label_office        = $arrCompanyInfo['default_label_office'];
        $data->default_label_trust_account = $arrCompanyInfo['default_label_trust_account'];
        $data->full_name                   = $userInfo['full_name'];
        $data->division_group_id           = $data->is_superadmin ? null : $userInfo['division_group_id'];

        if (isset($data->company_id)) {
            $company = $this->_company->getCompanyInfo($data->company_id);
            if ($company) {
                $data->company_name = $company['companyName'];
            }
        }

        //get divisions
        $data->is_division = false;
        $data->divisions   = false;
        if (!$data->is_admin) {
            $data->is_division = $this->_company->hasCompanyDivisions($data->company_id);
            if ($data->is_division) {
                $data->divisions = $this->_clients->getMemberDivisions($data->member_id);
            }
        }

        // Determining whether user is an authorized agent
        $data->is_authorized_agent = false;
        if ($this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled()) {
            foreach ($data->user_role as $role) {
                if (in_array($role['role_name'], array(Roles::$agentAdminRoleName, Roles::$agentUserRoleName, Roles::$agentSubagentRoleName))) {
                    $data->is_authorized_agent = true;
                    break;
                }
            }
        }

        // Set timeout only if cookie is not bound to the session
        if ($this->_config['security']['session_timeout'] > 0) {
            $data->timeout = time() + $this->_config['security']['session_timeout'];
        }

        // Update last login date
        if ($updateLastLogin) {
            $data->lastLogin = $this->_clients->updateLastLogin($data->member_id);
        }

        return $data;
    }

    /**
     * Check Lockout Policy:
     * 1. User tries to log in X times in a row (same day).
     * 2. On X+1 time we mark user account as disabled
     * 3. Send email to Officio support with details
     *
     * @param int $memberId
     * @param string $previouslyDisabledOnDate
     */
    public function checkLockoutPolicy($memberId, $previouslyDisabledOnDate)
    {
        $intAllowedTries = (int)$this->_config['security']['account_lockout_policy'];
        $intSecondsDelay = (int)$this->_config['security']['account_lockout_time'];
        if ($intAllowedTries) {
            $arrLogs = $this->_accessLogs->getMemberTodayFailedLoginTries($memberId);
            if (count($arrLogs) >= $intAllowedTries && (empty($previouslyDisabledOnDate) || (strtotime($previouslyDisabledOnDate) + $intSecondsDelay < time()))) {
                // Lock user's profile
                $this->_clients->updateMemberData($memberId, array('login_temporary_disabled_on' => date('c')));

                // Send email to support
                // Get required template
                $template = SystemTemplate::loadOne(['title' => 'User account locked']);
                if ($template) {
                    // Parse template, replace required info
                    $replacements      = $this->_clients->getTemplateReplacements($memberId);
                    $processedTemplate = $this->_systemTemplates->processTemplate($template, $replacements, ['to', 'subject', 'template']);
                    $this->_systemTemplates->sendTemplate($processedTemplate);
                }
            }
        }
    }

    /**
     * Checks whether a member is allowed to log in
     * @param array $arrMemberInfo
     * @return bool|string true in case user can log in, error message otherwise
     */
    public function canLogin($arrMemberInfo)
    {
        // User/client must have an assigned role to login
        $arrMemberRoleIds = $this->_clients->getMemberRoles($arrMemberInfo['member_id']);
        if (!count($arrMemberRoleIds)) {
            $strError = $this->_tr->translate('You can not login because role is not assigned to you.');
        } else {
            // Check if there is at least one active role
            $booIsActiveRole = false;
            $arRoles         = $this->_roles->getRoleInfo($arrMemberRoleIds);
            foreach ($arRoles as $arRoleDetails) {
                if ($arRoleDetails['role_status']) {
                    $booIsActiveRole = true;
                    break;
                }
            }

            if (!$booIsActiveRole) {
                $strError = $this->_tr->translate('You can not login because role(s) assigned to you is inactive.');
            }
        }

        // If this is a client - we need to check if his company has
        // access to package (where client login is allowed)
        if (empty($strError) && $this->_clients->isMemberClient($arrMemberInfo['userType'])) {
            // This is a client
            if (!empty($arrMemberInfo['company_id']) && $this->_company->getPackages()->canCompanyClientLogin($arrMemberInfo['company_id'])) {
            } else {
                $strError = $this->_tr->translate('You can not login because this is not allowed for your company');
            }
        }

        // Check if company is active + if account is not expired
        if (empty($strError)) {
            $intErrorNum = $this->_company->getCompanySubscriptions()->canClientAndAgentLogin($arrMemberInfo);
            if (!empty($intErrorNum)) {
                $strError = sprintf($this->_tr->translate('Access denied. Error %d.'), $intErrorNum);
            }
        }

        // Check if user/client can log in (not during company closing time)
        if (empty($strError) && !$this->_businessHours->areUserBusinessHoursNow($arrMemberInfo['member_id'])) {
            $strError = $this->_tr->translate('Access is denied during non-office hours.<br/>Please try again later.');
        }

        // Check if account was locked
        if (empty($strError)) {
            $intAllowedTries          = (int)$this->_config['security']['account_lockout_policy'];
            $intSecondsDelay          = (int)$this->_config['security']['account_lockout_time'];
            $previouslyDisabledOnDate = $arrMemberInfo['login_temporary_disabled_on'];
            if (!empty($intAllowedTries) && !empty($previouslyDisabledOnDate) && (strtotime($previouslyDisabledOnDate) + $intSecondsDelay > time())) {
                $strError = sprintf(
                    $this->_tr->translate('There were %d consecutive failed login attempts. For security reasons, your account is suspended for %s.'),
                    $intAllowedTries,
                    DateTimeTools::convertSecondsToHumanReadable($intSecondsDelay)
                );
            }
        }

        return !empty($strError) ? $strError : true;
    }

    /**
     * Save login result to the log
     *
     * @param $result
     * @param bool $isApiLogin
     * @param bool $isSuperadminLogin
     * @param string $username
     * @param int|null $companyId
     * @param int|null $memberId
     */
    public function loginRecordToAccessLog($result, $isApiLogin, $isSuperadminLogin, $username, $companyId = null, $memberId = null)
    {
        // Log this event
        $descriptionSuccess = $isApiLogin
            ? '{1} logged in via API successfully'
            : '{1} logged in successfully';

        $descriptionFailure = $isApiLogin
            ? sprintf('Login failed via API with username %s', $username)
            : sprintf('Login failed with username %s', $username);

        $arrLog = array(
            'log_section'     => $isSuperadminLogin ? 'superadmin_login' : 'login',
            'log_action'      => $result ? 'success' : 'fail',
            'log_description' => $result ? $descriptionSuccess : $descriptionFailure,
        );

        if ($companyId) {
            $arrLog['log_company_id'] = $companyId;
        }

        if ($memberId) {
            $arrLog['log_created_by'] = $memberId;
        }

        /** @var AccessLogs $oAccessLogs */
        $oAccessLogs = $this->_serviceContainer->get(AccessLogs::class);
        $oAccessLogs->saveLog($arrLog);
    }

    /**
     * Sets last user name cookie for autocompletion (if enabled)
     * @param string $username
     */
    public function setLastUsernameCookie($username)
    {
        if (!empty($this->_config['security']['autocompletion']['enabled'])) {
            Settings::setCookie(
                'lastUserName',
                $username,
                strtotime('+ 1 year'),
                '/',
                '',
                (bool)$this->_config['session_config']['cookie_secure'],
                (bool)$this->_config['session_config']['cookie_httponly']
            );
        }
    }

    /**
     * Login user with provided login info
     *
     * @param string $username
     * @param string $password
     * @param bool $booCheckIfCanLogin true to check if user can log in
     * @param bool $booSuperadmin true to check if user type is superadmin
     *
     * @param bool $booPasswordHash
     * @return array with login info
     */
    public function login($username, $password, $booCheckIfCanLogin = true, $booSuperadmin = false, $booPasswordHash = false)
    {
        $booLoggedIn   = false;
        $strError      = '';
        $arrMemberInfo = array();

        try {
            if (empty($username)) {
                $strError = $this->_tr->translate('Please provide a username');
            } elseif (empty($password)) {
                $strError = $this->_tr->translate('Please provide a password');
            } else {
                $arrMemberInfo = $this->_clients->getMemberInfoByUsername($username, $booSuperadmin);
                if (!$arrMemberInfo) {
                    $strError = $this->_tr->translate('Incorrect username or password.');
                } elseif ($booCheckIfCanLogin) {
                    $canLogin = $this->canLogin($arrMemberInfo);
                    if ($canLogin !== true) {
                        $strError = $canLogin;
                    }
                }

                // User/client can login
                if (empty($strError)) {
                    // Set the input credential values to authenticate against
                    $authAdapter = $this->_auth->getAdapter();
                    if (!$authAdapter instanceof AbstractAdapter) {
                        throw new Exception('Authentication adapter is not initialized.');
                    }

                    $authAdapter->setIdentity($username);
                    $authAdapter->setCredential($password);

                    if ($booPasswordHash && ($authAdapter instanceof CallbackCheckAdapter)) {
                        // We have password passed already hashed, so the callback should be different here
                        // Should we check here for the case our authAdapter isn't CallbackCheckAdapter?
                        $callbackFn = function ($hash, $password) {
                            return (strcmp($password, $hash) == 0);
                        };
                        $authAdapter->setCredentialValidationCallback($callbackFn);
                    }

                    $result = $this->_auth->authenticate($authAdapter);
                    if ($result->isValid()) {
                        if ($booSuperadmin) {
                            // Log superadmin out as soon as tab/browser is closed
                            // This also regenerates session ID which is required for
                            // session fixation
                            $this->_session->forgetMe();
                        } else {
                            // Prevent session fixation
                            $this->_session->regenerateId();
                        }

                        // Store database row to auth's storage system
                        // (not the password though!)
                        $data = $authAdapter->getResultRowObject(null, 'password');
                        $data = $this->prepareIdentity($data, $booCheckIfCanLogin || $booSuperadmin);
                        $this->_auth->getStorage()->write($data);

                        // Generate access token
                        if ($this->_moduleManager->getModule('Officio\\Api2')) {
                            // TODO We should do this via event system and let module do it itself
                            $accessToken = new AccessToken(
                                [
                                    'last_login'    => date('c'),
                                    'granted_at'    => date('c'),
                                    'member_id'     => $data->member_id,
                                    'session_id'    => $this->_session->getId(),
                                    'session_bound' => 1,
                                    'user_agent'    => $this->_tools->getClientUserAgent(),
                                    'user_os'       => $this->_tools->getClientOs(),
                                    'last_ip'       => $this->_tools->getClientIp()
                                ]
                            );
                            $accessToken->generateAccessToken();
                            $accessToken->save();
                        }

                        // Save last used login
                        if (!$booSuperadmin) {
                            $this->setLastUsernameCookie($username);
                        }

                        $booLoggedIn = true;
                    } else {
                        if (is_array($arrMemberInfo) && array_key_exists('member_id', $arrMemberInfo) && !empty($arrMemberInfo['status'])) {
                            // Login failed, check if we need to disable user's account
                            $this->checkLockoutPolicy($arrMemberInfo['member_id'], $arrMemberInfo['login_temporary_disabled_on']);
                        }
                    }
                }
            }

            // Make sure that user was logged out
            if ($booLoggedIn) {
                $this->_clients->checkMemberAndLogout($arrMemberInfo['member_id']);
            }

            // Add access log record
            if (!empty($username) && !empty($password)) {
                $companyId = $arrMemberInfo['company_id'] ?? null;
                $memberId  = $arrMemberInfo['member_id'] ?? null;
                $this->loginRecordToAccessLog($booLoggedIn, false, $booSuperadmin, $username, $companyId, $memberId);
            }
        } catch (Exception $e) {
            // Something wrong, save debug info
            $strError = 'Internal error.';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'auth');
        }


        if (!$booLoggedIn && empty($strError)) {
            $strError = $this->_tr->translate('Incorrect username or password.');
        }

        return array(
            'success'       => $booLoggedIn,
            'message'       => $strError,
            'arrMemberInfo' => $arrMemberInfo
        );
    }

    /**
     * oAuth login
     * Search user by the provided IDIR
     * If GUID is set - should be the same as the provided GUID
     * If GUID not set - save the provided GUID for the found user
     *
     * @param array $arrResourceOwner
     * @param bool $booSuperadmin
     * @return array
     */
    public function oauthLogin($arrResourceOwner, $booSuperadmin)
    {
        $booLoggedIn   = false;
        $strError      = '';
        $oAuthSettings = $this->_config['security']['oauth_login'];

        // Try to find the member by IDIR
        $arrMemberInfo = $this->_clients->getMemberInfoByIdir($arrResourceOwner['idir_username'], $booSuperadmin);
        if (empty($arrMemberInfo)) {
            $strError = sprintf(
                $this->_tr->translate('The entered %s did not match with any of the users in the system. Please try again or contact the site admin.'),
                $oAuthSettings['single_sign_on_label'],
            );
        }

        if (empty($strError)) {
            // Make sure that GUID is the same too
            if (empty($arrMemberInfo['oauth_guid'])) {
                // Set the new GUID if wasn't set yet
                $this->_db2->update(
                    'members',
                    ['oauth_guid' => $arrResourceOwner['idir_user_guid']],
                    ['member_id' => $arrMemberInfo['member_id']]
                );
            } elseif ($arrMemberInfo['oauth_guid'] != $arrResourceOwner['idir_user_guid']) {
                $strError = sprintf(
                    $this->_tr->translate('The previously saved %s for this %s does not match. Please check with the site admin.'),
                    $oAuthSettings['guid_label'],
                    $oAuthSettings['single_sign_on_label'],
                );
            }
        }

        if (empty($strError)) {
            list('success' => $booLoggedIn, 'message' => $strError, 'arrMemberInfo' => $arrMemberInfo) = $this->login($arrMemberInfo['username'], $arrMemberInfo['password'], true, $booSuperadmin, true);
        }

        return array(
            'success'       => $booLoggedIn,
            'message'       => $strError,
            'arrMemberInfo' => $arrMemberInfo
        );
    }

    /**
     * Method for logging user in without session. This method doesn't support superadmin login.
     * @param string $username
     * @param string $password
     * @param bool $booCheckIfCanLogin true to check if user can log in
     * @return AccessToken|bool access token or false in case of failure
     */
    public function apiLogin($username, $password, $booCheckIfCanLogin = true)
    {
        $canLogin    = true;
        $accessToken = false;
        $data        = false;

        if (empty($username) || empty($password)) {
            return false;
        }

        if (is_null($this->_apiAuth)) {
            throw new \RuntimeException('Officio\\Api2 modules seems to be missing, please install and enable it before using API login.');
        }

        try {
            // Set the input credential values to authenticate against
            $authAdapter = $this->_auth->getAdapter();
            if (!$authAdapter instanceof AbstractAdapter) {
                throw new Exception('Authentication adapter is not initialized.');
            }

            $arrMemberInfo = $this->_clients->getMemberInfoByUsername($username);
            if (!$arrMemberInfo) {
                $canLogin = false;
            }

            // In some cases we need check if current user/client can log in
            if ($canLogin && $booCheckIfCanLogin) {
                $loginAllowed = $this->canLogin($arrMemberInfo);
                if ($loginAllowed !== true) {
                    $canLogin = false;
                }
            }

            if ($canLogin) {
                // Set non-persistent storage for the identity
                $storage = new NonPersistent();
                $this->_auth->setStorage($storage);

                // Authenticate
                $authAdapter->setIdentity($username);
                $authAdapter->setCredential($password);
                $result = $this->_apiAuth->authenticate($authAdapter);
                if ($result->isValid()) {
                    // Store database row to auth's storage system
                    // (not the password though!)
                    $data = $authAdapter->getResultRowObject(null, 'password');
                    $data = $this->prepareIdentity($data);
                    $storage->write($data);

                    $accessToken = new AccessToken(
                        [
                            'last_login' => date('c'),
                            'granted_at' => date('c'),
                            // Let's grant API access tokens for a year, might review this in the future though,
                            // maybe even make this configurable
                            'expires_at' => date('c', time() + 365 * 24 * 3600),
                            'member_id'  => $data->member_id,
                            'user_agent' => $this->_tools->getClientUserAgent(),
                            'user_os'    => $this->_tools->getClientOs(),
                            'last_ip'    => $this->_tools->getClientIp()
                        ]
                    );
                    $accessToken->generateAccessToken();
                    $accessToken->save();
                } else {
                    if (is_array($arrMemberInfo) && array_key_exists('member_id', $arrMemberInfo) && !empty($arrMemberInfo['status'])) {
                        // Login failed, check if we need to disable user's account
                        $this->checkLockoutPolicy($arrMemberInfo['member_id'], $arrMemberInfo['login_temporary_disabled_on']);
                    }
                }
            }

            // Add access log record
            $this->loginRecordToAccessLog($accessToken, true, false, $username, $data ? $data->company_id : null, $data ? $data->member_id : null);
        } catch (Exception $e) {
            // Something wrong, save debug info
            $accessToken = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'auth');
        }

        return $accessToken;
    }

    /**
     * Replace the superadmin's access token
     * when logging in as a company user.
     *
     * @param $member_id
     */
    public function switchSuperAdminAccessToken($member_id)
    {
        // Check is Officio API2 module is loaded
        if (!$this->_moduleManager->getModule('Officio\\Api2')) {
            return;
        }

        // Wipe all access tokens bound to this session
        $sessionId    = $this->_session->getId();
        $accessTokens = AccessToken::loadBySessionId($sessionId);
        if ($accessTokens) {
            AccessToken::bulkDeleteByIds(array_keys($accessTokens));
        }

        // Generate new access token for masked user
        $accessToken = new AccessToken(
            [
                'last_login'    => date('c'),
                'granted_at'    => date('c'),
                'member_id'     => $member_id,
                'session_id'    => $this->_session->getId(),
                'session_bound' => 1,
                'user_agent'    => $this->_tools->getClientUserAgent(),
                'user_os'       => $this->_tools->getClientOs(),
                'last_ip'       => $this->_tools->getClientIp()
            ]
        );
        $accessToken->generateAccessToken();
        $accessToken->save();
    }

    /**
     * Logs user out
     */
    public function logout()
    {
        $this->_auth->clearIdentity();

        // Wipe all access tokens bound to this session
        $sessionId = $this->_session->getId();

        // Check is Officio API2 module is loaded
        if ($this->_moduleManager->getModule('Officio\\Api2')) {
            $accessTokens = AccessToken::loadBySessionId($sessionId);
            if ($accessTokens) {
                AccessToken::bulkDeleteByIds(array_keys($accessTokens));
            }

            // Cleanup access tokens
            AccessToken::cleanupTokens();
        }

        // Destroy session data and regenerate ID
        $this->_session->destroy();
    }

    /**
     * Delete expired hash records from DB
     */
    public function deleteExpiredHashes()
    {
        $this->_db2->delete('members_password_retrievals', [(new Where())->lessThan('expiration', time())]);
    }

    /**
     * Save new password by hash
     *
     * @param $password
     * @param $hash
     * @return int member id of updated record, empty on error
     */
    public function setNewPasswordAndDeleteRecoveryHash($password, $hash)
    {
        $memberId = 0;
        try {
            $retrievalsInfo = $this->getInfoFromPasswordRecoveryByHash($hash);

            if (!empty($retrievalsInfo)) {
                $memberId = $retrievalsInfo['member_id'];

                $this->_db2->update(
                    'members',
                    [
                        'password'             => $this->_encryption->hashPassword($password),
                        'password_change_date' => time()
                    ],
                    ['member_id' => $memberId]
                );

                $this->_db2->delete('members_password_retrievals', ['id' => $retrievalsInfo['id']]);
            }
        } catch (Exception $e) {
            $memberId = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $memberId;
    }

    /**
     * Load password recovery info by hash
     *
     * @param string $hash
     * @return array
     */
    public function getInfoFromPasswordRecoveryByHash($hash)
    {
        $select = (new Select())
            ->from(['r' => 'members_password_retrievals'])
            ->columns(['member_id', 'id'])
            ->where(['r.hash' => $hash, (new Where())->greaterThanOrEqualTo('r.expiration', time())]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Generate and save in DB password recovery hash for specific member
     *
     * @param int $memberId
     * @return bool|string new hash on success, false if failed
     */
    public function generatePasswordRecoveryHash($memberId)
    {
        $hash = false;

        try {
            $select = (new Select())
                ->from(['p' => 'members_password_retrievals'])
                ->where([
                    'p.member_id' => (int)$memberId,
                    (new Where())->greaterThanOrEqualTo('p.expiration', time())
                ]);

            $retrievalsInfo = $this->_db2->fetchAll($select);

            if (empty($retrievalsInfo)) {
                $select = (new Select())
                    ->from('members_password_retrievals')
                    ->columns(['hash']);

                $allHashes = $this->_db2->fetchCol($select);

                do {
                    $hash = sha1(time() . mt_rand(0, 1000000));
                } while (in_array($hash, $allHashes));

                $retrievalsInfo = array(
                    'member_id'  => $memberId,
                    'hash'       => $hash,
                    'expiration' => time() + 86400 * 3, # 3 days
                );
                $this->_db2->insert('members_password_retrievals', $retrievalsInfo);
            } else {
                $hash = $retrievalsInfo[0]['hash'];
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $hash;
    }

    /**
     * Check if password is valid
     *
     * @param $password
     * @param array $arrErrors - errors will be returned here
     * @param null $userName
     * @param null $memberId
     * @return bool
     */
    public function isPasswordValid($password, &$arrErrors = array(), $userName = null, $memberId = null)
    {
        $minLength = $this->_settings->passwordMinLength;
        $maxLength = $this->_settings->passwordMaxLength;

        $isValid = true;
        if (strlen($password ?? '') < $minLength) {
            $arrErrors[] = sprintf($this->_tr->translate('Minimum password length: %d symbols.'), $minLength);
            $isValid     = false;
        }

        if (strlen($password ?? '') > $maxLength) {
            $arrErrors[] = sprintf($this->_tr->translate('Maximum password length: %d symbols.'), $maxLength);
            $isValid     = false;
        }

        if ($this->_config['security']['password_high_secure']) {// check extra cases of password validity

            if (!preg_match('/[0-9]+/', $password)) {
                $arrErrors[] = $this->_tr->translate('Password should contain numbers.');
                $isValid     = false;
            }


            # must have mix case of characters
            if (!preg_match('/[a-z]+/', $password) || !preg_match('/[A-Z]+/', $password)) {
                $arrErrors[] = $this->_tr->translate('Password should contain mix case characters.');
                $isValid     = false;
            }

            if (in_array(strtolower($password ?? ''), $this->commonPasswords)) {
                $arrErrors[] = $this->_tr->translate("Please don't use common passwords.");
                $isValid     = false;
            }

            if (!empty($userName)) {
                if (mb_strpos($password, $userName) !== false) {
                    $arrErrors[] = $this->_tr->translate("Password can't contain your username.");
                    $isValid     = false;
                }
            }

            if (!empty($memberId) && strlen($password ?? '') && $this->isPasswordSameAsPrev($memberId, $password)) {
                $arrErrors[] = $this->_tr->translate('New password should be different from the current or the last few passwords.');
                $isValid     = false;
            }
        }

        return $isValid;
    }

    /**
     * Load member's last used passwords
     *
     * @param int $memberId
     * @return array
     */
    public function getMemberLastPasswords($memberId)
    {
        $select = (new Select())
            ->from(['m' => 'members_last_passwords'])
            ->where(['m.member_id' => (int)$memberId])
            ->order('timestamp DESC');

        return $this->_db2->fetchAll($select);
    }

    /**
     * Check if provided password is the same as previously used (for specific member)
     *
     * @param int $memberId
     * @param string $newPassword
     * @return bool true if it is the same
     */
    public function isPasswordSameAsPrev($memberId, $newPassword)
    {
        $booSame = false;

        // fetch last passwords list
        $arrPasswordsList = $this->getMemberLastPasswords($memberId);

        // fetch current password
        $select = (new Select())
            ->from(['m' => 'members'])
            ->columns(['password'])
            ->where(['m.member_id' => (int)$memberId]);

        $currentPassword = $this->_db2->fetchOne($select);

        // add current password to the list of prev passwords
        $arrPasswordsList[] = array(
            'timestamp' => 0,
            'password'  => $currentPassword
        );

        // compare prev passwords with new one
        foreach ($arrPasswordsList as $arrPasswordInfo) {
            if ($this->_encryption->checkPasswords($newPassword, $arrPasswordInfo['password'])) {
                $booSame = true;
                break;
            }
        }

        return $booSame;
    }

    /**
     * Save current user's password as already used
     *
     * @param int $memberId
     * @return bool true on success
     */
    public function storeOldPasswordToHistory($memberId)
    {
        try {
            // fetch last passwords list
            $oldPasswordsList      = $this->getMemberLastPasswords($memberId);
            $intSavePasswordsCount = (int)$this->_config['security']['password_aging']['save_passwords_count'];
            $intSavePasswordsCount = empty($intSavePasswordsCount) ? 3 : $intSavePasswordsCount;

            if (count($oldPasswordsList) >= $intSavePasswordsCount) {
                // Delete all old passwords (more than X)
                $this->_db2->delete(
                    'members_last_passwords',
                    [
                        'member_id' => $memberId,
                        (new Where())->lessThanOrEqualTo('timestamp', (int)$oldPasswordsList[$intSavePasswordsCount - 1]['timestamp'])
                    ]
                );
            }

            // fetch current password
            $select = (new Select())
                ->from(['m' => 'members'])
                ->columns(['password'])
                ->where(['m.member_id' => (int)$memberId]);

            $oldPasswordHash = $this->_db2->fetchOne($select);

            $this->_db2->insert(
                'members_last_passwords',
                [
                    'member_id' => $memberId,
                    'password'  => $oldPasswordHash,
                    'timestamp' => time(),
                ]
            );

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /** @var array list of common used passwords */
    private $commonPasswords = array(
        'password',
        '123456',
        '12345678',
        '1234',
        'qwerty',
        '12345',
        'dragon',
        'pussy',
        'baseball',
        'football',
        'letmein',
        'monkey',
        '696969',
        'abc123',
        'mustang',
        'michael',
        'shadow',
        'master',
        'jennifer',
        '111111',
        '2000',
        'jordan',
        'superman',
        'harley',
        '1234567',
        'fuckme',
        'hunter',
        'fuckyou',
        'trustno1',
        'ranger',
        'buster',
        'thomas',
        'tigger',
        'robert',
        'soccer',
        'fuck',
        'batman',
        'test',
        'pass',
        'killer',
        'hockey',
        'george',
        'charlie',
        'andrew',
        'michelle',
        'love',
        'sunshine',
        'jessica',
        'asshole',
        '6969',
        'pepper',
        'daniel',
        'access',
        '123456789',
        '654321',
        'joshua',
        'maggie',
        'starwars',
        'silver',
        'william',
        'dallas',
        'yankees',
        '123123',
        'ashley',
        '666666',
        'hello',
        'amanda',
        'orange',
        'biteme',
        'freedom',
        'computer',
        'sexy',
        'thunder',
        'nicole',
        'ginger',
        'heather',
        'hammer',
        'summer',
        'corvette',
        'taylor',
        'fucker',
        'austin',
        '1111',
        'merlin',
        'matthew',
        '121212',
        'golfer',
        'cheese',
        'princess',
        'martin',
        'chelsea',
        'patrick',
        'richard',
        'diamond',
        'yellow',
        'bigdog',
        'secret',
        'asdfgh',
        'sparky',
        'cowboy',
    );
}
