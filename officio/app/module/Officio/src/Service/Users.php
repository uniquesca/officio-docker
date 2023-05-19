<?php

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */

namespace Officio\Service;

use Clients\Service\Members;
use Exception;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\EventManager\EventInterface;
use Laminas\Uri\UriFactory;
use Officio\Templates\SystemTemplates;
use OpensslCryptor\Cryptor;

class Users extends Members
{

    /** @var SystemTemplates */
    protected $_systemTemplates;

    public function initAdditionalServices(array $services)
    {
        parent::initAdditionalServices($services);
        $this->_systemTemplates = $services[SystemTemplates::class];
    }

    public function init()
    {
        $this->_systemTemplates->getEventManager()->attach(SystemTemplates::EVENT_GET_AVAILABLE_FIELDS, [$this, 'getSystemTemplateFields']);
    }

    /**
     * Load user info by provided member id
     *
     * @param null $memberId
     * @return array
     */
    public function getUserInfo($memberId = null)
    {
        try {
            if (!isset($memberId)) {
                $memberId = $this->_auth->getCurrentUserId();
            }

            $select = (new Select())
                ->from(array('m' => 'members'))
                ->join(array('u' => 'users'), 'u.member_id = m.member_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER)
                ->where(['m.member_id' => (int)$memberId]);

            $arrUserInfo = $this->_db2->fetchRow($select);
            $arrUserInfo = static::generateMemberName($arrUserInfo);
        } catch (Exception $e) {
            $arrUserInfo = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrUserInfo;
    }

    /**
     * Load members list via provided options
     *
     * @param int|null $companyId
     * @param bool $booRMAOnly true to load users which have 'RMA' checkbox checked
     * @param bool $booActive true to load active users
     * @return array
     */
    public function getSpecifyUsersInfo($companyId = null, $booRMAOnly = false, $booActive = false)
    {
        $arrResult = array();

        try {
            $select = (new Select())
                ->from(array('m' => 'members'))
                ->join(array('u' => 'users'), 'u.member_id = m.member_id', array('user_id', 'notes', 'activationCode', 'city', 'state', 'country', 'homePhone', 'workPhone', 'mobilePhone', 'fax', 'zip', 'address', 'code'), Select::JOIN_LEFT_OUTER)
                ->where([
                    'm.userType'   => Members::getMemberType('admin_and_staff'),
                    'm.company_id' => is_null($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId
                ])
                ->group(array('m.member_id'));

            $memberId               = null;
            $booCheckForActiveUsers = $booActive;
            if (!is_null($companyId)) {
                $memberId = $this->_company->getCompanyAdminId($companyId);

                if ($this->_company->isHideInactiveUsersEnabledToCompany($companyId)) {
                    $booActive = true;
                }
            }

            list($oStructQuery, $booUseDivisionsTable) = $this->getMemberStructureQuery($memberId, $booCheckForActiveUsers);

            if ($booUseDivisionsTable) {
                $select->join(array('md' => 'members_divisions'), 'md.member_id = m.member_id', [], Select::JOIN_LEFT);
            }

            if (!empty($oStructQuery)) {
                $select->where([$oStructQuery]);
            }

            if ($booRMAOnly) {
                $select->where(['u.user_is_rma' => 'Y']);
            }

            if ($booActive) {
                $select->where(['m.status' => 1]);
            }

            $arrResult = $this->_db2->fetchAll($select);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrResult;
    }

    /**
     * Load active users for the specific company
     *
     * @param bool $booRMAOnly true to load users which have 'RMA' checkbox checked
     * @param null $companyId
     * @param int $memberId
     * @param bool $booActive
     * @return array
     */
    public function getAssignedToUsers($booRMAOnly = true, $companyId = null, $memberId = 0, $booActive = false)
    {
        $arrAssign = array();

        try {
            $arrUsers = $this->getSpecifyUsersInfo($companyId, $booRMAOnly, $booActive);
            if (is_array($arrUsers) && count($arrUsers)) {
                foreach ($arrUsers as $userInfo) {
                    if ($memberId == $userInfo['member_id']) {
                        continue;
                    }

                    $userInfo = static::generateMemberName($userInfo);

                    $arrAssign[] = array(
                        'option_id'   => $userInfo['member_id'],
                        'option_name' => $userInfo['full_name'],
                        'status'      => $userInfo['status']
                    );
                }

                usort($arrAssign, function ($a, $b) {
                    return strcmp(strtolower($a['option_name'] ?? ''), strtolower($b['option_name'] ?? ''));
                });
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrAssign;
    }

    /**
     * Load options for 'assigned to' field
     *
     * @param string $whereWillBeShowed
     * @param null $companyId
     * @param bool $booActive
     * @return array
     */
    public function getAssignList($whereWillBeShowed, $companyId = null, $booActive = false)
    {
        $booSuperadmin = $this->_auth->isCurrentUserSuperadmin();
        switch ($whereWillBeShowed) {
            case 'profile':
                $booShowPleaseSelect = true;
                $booForProfile       = true;
                break;

            case 'search':
                $booShowPleaseSelect = false;
                $booForProfile       = true;
                break;

            case 'export':
                $booShowPleaseSelect = false;
                $booForProfile       = true;
                $booSuperadmin       = false;
                break;

            case 'reminder':
            default:
                $booShowPleaseSelect = false;
                $booForProfile       = false;
                break;
        }

        $arrAssign = array();

        if ($booShowPleaseSelect) {
            $arrAssign[] = array('assign_to_id' => '', 'assign_to_name' => '- Select -', 'status' => '1');
        }

        // All users
        $arrAssign[] = array('assign_to_id' => 'user:all', 'assign_to_name' => 'All staff', 'status' => '1');

        // Company users list (if not superadmin)
        if (!$booSuperadmin) {
            $arrUsers = $this->getSpecifyUsersInfo($companyId, false, $booActive);
            if (is_array($arrUsers) && count($arrUsers) > 0) {
                foreach ($arrUsers as $arrUserInfo) {
                    $arrUserInfo = static::generateMemberName($arrUserInfo);
                    $arrAssign[] = array(
                        'assign_to_id'   => 'user:' . $arrUserInfo['member_id'],
                        'assign_to_name' => $arrUserInfo['full_name'],
                        'status'         => $arrUserInfo['status']
                    );
                }
            }
        }

        if (!$booForProfile) { //for reminders
            //Profile Fields
            $arrAssign[] = array('assign_to_id' => 'assigned:4', 'assign_to_name' => 'The staff responsible for Sales/Marketing', 'status' => '1');
            $arrAssign[] = array('assign_to_id' => 'assigned:5', 'assign_to_name' => 'The staff responsible for Processing', 'status' => '1');
            $arrAssign[] = array('assign_to_id' => 'assigned:6', 'assign_to_name' => 'The staff responsible for Accounting', 'status' => '1');
            $arrAssign[] = array('assign_to_id' => 'assigned:7', 'assign_to_name' => $this->_company->getCurrentCompanyDefaultLabel('rma'), 'status' => '1');
        }

        // Get Roles list
        $arrRoles = $this->getRoles();
        if (is_array($arrRoles) && count($arrRoles) > 0) {
            foreach ($arrRoles as $roleInfo) {
                if (!in_array($roleInfo['role_type'], array('individual_client', 'employer_client'))) {
                    $arrAssign[] = array(
                        'assign_to_id'   => 'role:' . $roleInfo['role_id'],
                        'assign_to_name' => 'All ' . $roleInfo['role_name'] . ' staff',
                        'status'         => '1'
                    );
                }
            }
        }

        usort($arrAssign, function ($a, $b) {
            return strcmp(strtolower($a['assign_to_name'] ?? ''), strtolower($b['assign_to_name'] ?? ''));
        });

        return $arrAssign;
    }


    /**
     * @param array $arrMemberInfo
     * @param string $strTimeZone
     * @param array $arrUserInfo
     * @return array
     */
    public function createUser($arrMemberInfo, $strTimeZone, $arrUserInfo = array())
    {
        try {
            $signature = array_key_exists('email_signature', $arrMemberInfo) ? $arrMemberInfo['email_signature'] : '';
            $emailSign = array_key_exists('emailsign', $arrMemberInfo) ? $arrMemberInfo ['emailsign'] : '';
            unset($arrMemberInfo['email_signature'], $arrMemberInfo['emailsign']);


            $arrMemberInfo['status']        = 1;
            $arrMemberInfo['login_enabled'] = 'Y';
            $arrMemberInfo['regTime']       = time();

            if (strlen($arrMemberInfo['password'] ?? '')) {
                if (isset($arrMemberInfo['hashed_password']) && $arrMemberInfo['hashed_password'] === true) {
                    unset($arrMemberInfo['hashed_password']);
                } else {
                    $arrMemberInfo['password'] = $this->_encryption->hashPassword($arrMemberInfo['password']);
                }
            }

            if (!isset($arrMemberInfo['division_group_id']) && isset($arrMemberInfo['company_id'])) {
                $arrMemberInfo['division_group_id'] = $this->_company->getCompanyDivisions()->getCompanyMainDivisionGroupId($arrMemberInfo['company_id']);
            }

            $memberId = $this->_db2->insert('members', $arrMemberInfo);


            $arrUserInfo['member_id'] = $memberId;

            if (isset($arrUserInfo['country']) && empty($arrUserInfo['country'])) {
                $arrUserInfo['country'] = null;
            }

            // activationCode is a required field
            $arrUserInfo['activationCode'] = empty($arrUserInfo['activationCode']) ? '' : $arrUserInfo['activationCode'];

            $this->_db2->insert('users', $arrUserInfo);

            $this->_systemTriggers->triggerUserCreated($memberId);

            // Create smtp settings
            $arrUpdateSMTPInfo = array(
                'member_id'       => $memberId,
                'email'           => $arrMemberInfo['emailAddress'],
                'email_signature' => $signature,
                'friendly_name'   => $emailSign,
                'timezone'        => $strTimeZone,
                'is_default'      => true,
                'inc_enabled'     => false,
                'out_use_own'     => false
            );
            $this->updateMemberSMTPSettings($arrUpdateSMTPInfo);

            // Create default folders on new user creation
            if (isset($arrMemberInfo['company_id']) && !empty($arrMemberInfo['company_id'])) {
                $this->_files->mkNewMemberFolders($memberId, $arrMemberInfo['company_id'], $this->_company->isCompanyStorageLocationLocal($arrMemberInfo['company_id']), false);
            }

            $booError = false;
        } catch (Exception $e) {
            $memberId = false;
            $booError = true;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array('error' => $booError, 'member_id' => $memberId);
    }

    /**
     * Update user's info
     *
     * @param int $memberId
     * @param array $arrUserInfo
     * @return bool true on success
     */
    public function updateUser($memberId, $arrUserInfo)
    {
        try {
            if (isset($arrUserInfo['country']) && empty($arrUserInfo['country'])) {
                $arrUserInfo['country'] = null;
            }

            // Make sure that user's record exists in the DB
            $select = (new Select())
                ->from(array('u' => 'users'))
                ->where(['u.member_id' => (int)$memberId]);

            $arrSavedUserInfo = $this->_db2->fetchRow($select);

            if (empty($arrSavedUserInfo)) {
                $arrUserInfo['member_id'] = $memberId;

                $this->_db2->insert('users', $arrUserInfo);
            } else {
                $this->_db2->update('users', $arrUserInfo, ['member_id' => $memberId]);
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $booSuccess;
    }

    public function getCompanyActiveUsers($arrActiveCompanyIds)
    {
        if (!is_array($arrActiveCompanyIds) || !count($arrActiveCompanyIds)) {
            return array();
        }

        $select = (new Select())
            ->from('members')
            ->columns(['member_id'])
            ->where(['members.company_id' => $arrActiveCompanyIds, 'members.status' => 1]);

        return $this->_db2->fetchCol($select);
    }

    /**
     * Check if communication with LMS server is turned on
     *
     * @param bool $booCheckCurrentUserAccess true to check if current user has access to the rule
     * @return bool
     */
    public function isLmsEnabled($booCheckCurrentUserAccess)
    {
        $booEnabled = false;
        $settings   = $this->_config['lms'];
        if (!empty($settings['enabled']) && !empty($settings['url'])) {
            $validator  = UriFactory::factory($settings['url']);
            $booEnabled = $validator->isValid();
        }

        if ($booEnabled && $booCheckCurrentUserAccess) {
            $booEnabled = $this->_acl->isAllowed('lms-view');
        }

        return $booEnabled;
    }

    /**
     * Check if communication with LMS server is turned on and is in the test mode
     *
     * @return bool
     */
    public function isLmsInTestMode()
    {
        $booInTestMode = false;
        if ($this->isLmsEnabled(false)) {
            $booInTestMode = (bool)$this->_config['lms']['test_mode'];
        }

        return $booInTestMode;
    }

    /**
     * Get LMS login url for a specific user
     *
     * @param int $memberId
     * @param string $redirectUrl
     * @return string empty on error or if communication with LMS is turned off
     */
    public function getLmsLoginUrl($memberId, $redirectUrl)
    {
        $lmsUrl = '';

        try {
            $settings = $this->_config['lms'];

            // Don't try to send a request if communication wasn't turned on
            if (!self::isLmsEnabled(true)) {
                $strError = $this->_tr->translate('Communication with LMS server is turned off.');
            }

            if (self::isLmsInTestMode()) {
                $strError = $this->_tr->translate('Communication with LMS server is in the test mode.');
            }

            $arrMemberInfo = $this->getUserInfo($memberId);
            if (!isset($arrMemberInfo['company_id'])) {
                $strError = $this->_tr->translate('User not found.');
            }

            // Try to register the user on the LMS side if wasn't registered yet
            if (empty($strError) && empty($arrMemberInfo['lms_user_id'])) {
                $strError = $this->createOrUpdateLmsUser($memberId);
                if (empty($strError)) {
                    $arrMemberInfo = $this->getUserInfo($memberId);
                }
            }

            if (empty($strError) && !empty($arrMemberInfo['lms_user_id'])) {
                $arrData = array(
                    'lms_user_id' => $arrMemberInfo['lms_user_id'],
                    'expiration'  => time() + 120 // 120 seconds expiration
                );

                if (!empty($redirectUrl)) {
                    $arrData['redirect_url'] = $redirectUrl;
                }

                $encryptedString = Cryptor::Encrypt(json_encode($arrData), $settings['auth_key']);
                $lmsUrl          = rtrim($settings['url'] ?? '', '/') . '/wp-login.php?action=login&apilogin=' . urlencode($encryptedString);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $lmsUrl;
    }

    /**
     * Try to enable already created LMS user
     *
     * @param int $memberId
     * @return string error message, empty on success
     */
    public function enableLmsUserUpdate($memberId)
    {
        $strError = '';

        try {
            $settings = $this->_config['lms'];

            // Don't try to send a request if communication wasn't turned on
            if (!self::isLmsEnabled(true)) {
                $strError = $this->_tr->translate('Communication with LMS server is turned off.');
            }

            if (self::isLmsInTestMode()) {
                $strError = $this->_tr->translate('Communication with LMS server is in the test mode.');
            }

            $arrMemberInfo = $this->getUserInfo($memberId);
            if (empty($strError) && !isset($arrMemberInfo['company_id'])) {
                $strError = $this->_tr->translate('User not found.');
            }

            if (empty($strError) && empty($arrMemberInfo['lms_user_id'])) {
                $strError = $this->_tr->translate('User not registered on the LMS side.');
            }

            if (empty($strError)) {
                $arrPost = array(
                    'lms_user_id' => $arrMemberInfo['lms_user_id'],
                );

                // Update LMS user
                $url = rtrim($settings['url'] ?? '', '/') . '/wp-json/api/v1/enableOneTimeUpdate';
                list($strError, $response) = $this->sendRequestToLms($url, $arrPost);

                if (empty($strError)) {
                    if (empty($response)) {
                        $strError = $this->_tr->translate('No response from the LMS server.');
                    } else {
                        $data = json_decode($response);
                        if (!isset($data->status) || $data->status !== 'success') {
                            $strError = $data->message ?? $this->_tr->translate('No response');
                        }
                    }
                }


                if (!empty($strError)) {
                    $error = sprintf(
                        $this->_tr->translate('User %s with id #%s from the company #%s was not enabled in the LMS.'),
                        $arrMemberInfo['lName'] . ' ' . $arrMemberInfo['fName'],
                        $memberId,
                        $arrMemberInfo['company_id']
                    );

                    $details = $this->_tr->translate('URL: ') . $url . PHP_EOL;
                    $details .= $this->_tr->translate('Params: ') . print_r($arrPost, true) . PHP_EOL;
                    $details .= $this->_tr->translate('Response: ') . print_r($response, true) . PHP_EOL;
                    $this->_log->debugErrorToFile($error, $details, 'lms');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

    /**
     * Create/update LMS user
     *
     * @param int $memberId
     * @return string error message, empty on success
     */
    public function createOrUpdateLmsUser($memberId)
    {
        $strError = '';

        try {
            $settings = $this->_config['lms'];

            // Don't try to send a request if communication wasn't turned on
            if (!self::isLmsEnabled(true)) {
                $strError = $this->_tr->translate('Communication with LMS server is turned off.');
            }

            if (self::isLmsInTestMode()) {
                $strError = $this->_tr->translate('Communication with LMS server is in the test mode.');
            }

            $arrMemberInfo = $this->getUserInfo($memberId);
            if (empty($strError) && !isset($arrMemberInfo['company_id'])) {
                $strError = $this->_tr->translate('User not found.');
            }

            $arrCompanyInfo = $this->_company->getCompanyDetailsInfo($arrMemberInfo['company_id']);
            if (empty($strError) && !isset($arrCompanyInfo['subscription'])) {
                $strError = $this->_tr->translate('Incorrectly selected company.');
            }

            if (empty($strError)) {
                $cpdHours = 1000;
                switch ($arrCompanyInfo['subscription']) {
                    case 'pro':
                    case 'pro13':
                        $subscription = 'pro';
                        break;

                    case 'ultimate':
                    case 'ultimate_plus':
                        $subscription = 'ultimate';
                        break;

                    case 'immi_club':
                        $subscription = 'immi_club';
                        break;

                    case 'lite':
                    case 'starter':
                    case 'solo':
                    default:
                        $subscription = 'solo';
                        break;
                }

                $country = '';
                if (!empty($arrMemberInfo['country'])) {
                    $arrCountries = $this->_country->getCountries(true);
                    // TODO: fix to array('' => '-- Please select --') + $arrCountries)
                    $arrCountries = array_merge(array('-- Please select --'), $arrCountries);
                    if (in_array($arrMemberInfo['country'], array_keys($arrCountries))) {
                        $country = $arrCountries[$arrMemberInfo['country']];
                    }
                }

                if (empty($arrMemberInfo['lms_user_id'])) {
                    $arrPost = array(
                        'lms_user_id'       => $arrMemberInfo['lms_user_id'],
                        'first_name'        => $arrMemberInfo['fName'],
                        'last_name'         => $arrMemberInfo['lName'],
                        'address'           => $arrMemberInfo['address'],
                        'city'              => $arrMemberInfo['city'],
                        'province'          => $arrMemberInfo['state'],
                        'country'           => $country,
                        'zip'               => $arrMemberInfo['zip'],
                        'phone'             => empty($arrMemberInfo['workPhone']) ? $arrMemberInfo['mobilePhone'] : $arrMemberInfo['workPhone'],
                        'email'             => $arrMemberInfo['emailAddress'],
                        'regulatory_id'     => $arrMemberInfo['user_migration_number'],
                        'subscription_plan' => $subscription,
                        'cpd_hours'         => $cpdHours,
                    );
                } else {
                    $arrPost = array(
                        'lms_user_id'       => $arrMemberInfo['lms_user_id'],
                        'subscription_plan' => $subscription,
                        'cpd_hours'         => $cpdHours,
                    );
                }

                if (empty($arrMemberInfo['lms_user_id'])) {
                    // Create LMS user
                    $url = rtrim($settings['url'] ?? '', '/') . '/wp-json/api/v1/userCreate';
                    list($strError, $response) = $this->sendRequestToLms($url, $arrPost);

                    if (empty($strError)) {
                        if (empty($response)) {
                            $strError = $this->_tr->translate('No response from the LMS server.');
                        } else {
                            $data = json_decode($response);

                            if (isset($data->status) && $data->status === 'success') {
                                $this->updateUser($memberId, array('lms_user_id' => $data->lms_user_id));
                            } else {
                                $strError = $data->message ?? $this->_tr->translate('No response');
                            }
                        }

                        if (!empty($strError)) {
                            $error = sprintf(
                                $this->_tr->translate('User %s with id #%s from the company #%s was not created in the LMS.'),
                                $arrMemberInfo['lName'] . ' ' . $arrMemberInfo['fName'],
                                $memberId,
                                $arrMemberInfo['company_id']
                            );

                            $details = $this->_tr->translate('URL: ') . $url . PHP_EOL;
                            $details .= $this->_tr->translate('Params: ') . print_r($arrPost, true) . PHP_EOL;
                            $details .= $this->_tr->translate('Response: ') . print_r($response, true) . PHP_EOL;
                            $this->_log->debugErrorToFile($error, $details, 'lms');
                        }
                    }
                } else {
                    // Update LMS user
                    $url = rtrim($settings['url'] ?? '', '/') . '/wp-json/api/v1/userUpdate';
                    list($strError, $response) = $this->sendRequestToLms($url, $arrPost);

                    if (empty($strError)) {
                        if (empty($response)) {
                            $strError = $this->_tr->translate('No response from the LMS server.');
                        } else {
                            $data = json_decode($response);
                            if (!isset($data->status) || $data->status !== 'success') {
                                $strError = $data->message ?? $this->_tr->translate('No response');
                            }
                        }
                    }


                    if (!empty($strError)) {
                        $error = sprintf(
                            $this->_tr->translate('User %s with id #%s from the company #%s was not updated in the LMS.'),
                            $arrMemberInfo['lName'] . ' ' . $arrMemberInfo['fName'],
                            $memberId,
                            $arrMemberInfo['company_id']
                        );

                        $details = $this->_tr->translate('URL: ') . $url . PHP_EOL;
                        $details .= $this->_tr->translate('Params: ') . print_r($arrPost, true) . PHP_EOL;
                        $details .= $this->_tr->translate('Response: ') . print_r($response, true) . PHP_EOL;
                        $this->_log->debugErrorToFile($error, $details, 'lms');
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

    /**
     * Update all company LMS users if subscription was changed
     *
     * @param int $companyId
     * @return string error empty on success
     */
    public function massCompanyUsersUpdateInLms($companyId)
    {
        $strError = '';

        try {
            // Don't try to send a request if communication wasn't turned on
            if (!self::isLmsEnabled(false)) {
                $strError = $this->_tr->translate('Communication with LMS server is turned off.');
            }

            if (self::isLmsInTestMode()) {
                $strError = $this->_tr->translate('Communication with LMS server is in the test mode.');
            }

            $arrCompanyInfo = $this->_company->getCompanyDetailsInfo($companyId);
            if (empty($strError) && !isset($arrCompanyInfo['subscription'])) {
                $strError = $this->_tr->translate('Incorrectly selected company.');
            }

            // Load the list of all company users that have LMS id set
            $arrLMSUserIds = array();
            if (empty($strError)) {
                $select = (new Select())
                    ->from(array('m' => 'members'))
                    ->columns([])
                    ->join(array('u' => 'users'), 'u.member_id = m.member_id', 'lms_user_id')
                    ->where(
                        [
                            (new Where())
                                ->isNotNull('u.lms_user_id')
                                ->equalTo('m.company_id', (int)$companyId)
                        ]
                    );


                $arrLMSUserIds = $this->_db2->fetchCol($select);
            }

            if (empty($strError) && !empty($arrLMSUserIds)) {
                switch ($arrCompanyInfo['subscription']) {
                    case 'pro':
                    case 'pro13':
                        $subscription = 'pro';
                        break;

                    case 'ultimate':
                    case 'ultimate_plus':
                        $subscription = 'ultimate';
                        break;

                    case 'immi_club':
                        $subscription = 'immi_club';
                        break;

                    case 'lite':
                    case 'starter':
                    case 'solo':
                    default:
                        $subscription = 'solo';
                        break;
                }

                $arrPost = array(
                    'user_ids'          => json_encode($arrLMSUserIds),
                    'subscription_plan' => $subscription,
                );

                $url = rtrim($this->_config['lms']['url'] ?? '', '/') . '/wp-json/api/v1/usersBulkUpdate';
                list($strError, $response) = $this->sendRequestToLms($url, $arrPost);

                if (empty($strError)) {
                    if (empty($response)) {
                        $strError = $this->_tr->translate('No response from the LMS server.');
                    } else {
                        $data = json_decode($response);

                        if (!isset($data->status) || $data->status !== 'success') {
                            $strError = $data->message ?? $this->_tr->translate('No response');
                        }
                    }
                }

                if (!empty($strError)) {
                    $details = $this->_tr->translate('URL: ') . $url . PHP_EOL;
                    $details .= $this->_tr->translate('Params: ') . print_r($arrPost, true) . PHP_EOL;
                    $details .= $this->_tr->translate('Response: ') . print_r($response, true) . PHP_EOL;
                    $this->_log->debugErrorToFile(
                        $this->_tr->translate('Mass users update was not done in the LMS.'),
                        $details,
                        'lms'
                    );
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

    /**
     * A helper method - sends request to the LMS server and returns a result
     *
     * @param string $url
     * @param array $arrPost
     * @return array
     */
    public function sendRequestToLms($url, $arrPost)
    {
        $strError = '';

        $cr = curl_init($url);

        curl_setopt($cr, CURLOPT_TIMEOUT, 30);
        curl_setopt($cr, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($cr, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($cr, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($cr, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($cr, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($cr, CURLOPT_POSTFIELDS, $arrPost);

        $settings = $this->_config['lms'];

        // Don't check for SSL certificates (e.g. self signed)
        if (empty($settings['check_ssl'])) {
            curl_setopt($cr, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($cr, CURLOPT_SSL_VERIFYHOST, false);
        }

        // Custom Officio header
        $arrHeaders = array(
            'X-Officio-Auth-Key: ' . base64_encode($settings['auth_key'])
        );
        curl_setopt($cr, CURLOPT_HTTPHEADER, $arrHeaders);

        $r = curl_exec($cr);
        if ($z = curl_error($cr)) {
            $errorNumber = curl_errno($cr);
            if ($errorNumber == 28) {
                $strError = $this->_tr->translate('Operation timeout. The specified time-out period was reached according to the conditions.');
            } else {
                $strError = $this->_tr->translate('Internal error');
            }
            $this->_log->debugErrorToFile('', 'Curl error: ' . $z . ' Url: ' . $url, 'lms');
        } elseif (!empty($settings['log_enabled'])) {
            $details = $this->_tr->translate('URL: ') . $url . PHP_EOL;
            $details .= $this->_tr->translate('Params: ') . print_r($arrPost, true) . PHP_EOL;
            $details .= $this->_tr->translate('Response: ') . print_r($r, true) . PHP_EOL;

            $this->_log->debugToFile($details, 1, 2, 'lms-' . date('Y_m_d') . '.log');
        }
        curl_close($cr);

        return array($strError, $r);
    }

    /**
     * Provides list of fields available for system templates.
     * @param EventInterface $e
     * @return array
     */
    public function getSystemTemplateFields(EventInterface $e)
    {
        $templateType = $e->getParam('templateType');

        // User info
        $arrUserFields = array(
            array('name' => 'user: first name', 'label' => 'First Name'),
            array('name' => 'user: last name', 'label' => 'Last Name'),
            array('name' => 'user: username', 'label' => 'Username'),
            array('name' => 'user: password', 'label' => 'Password'),
            array('name' => 'user: email', 'label' => 'Email'),
            array('name' => 'user: password hash', 'label' => 'Password Recovery Hash')
        );

        foreach ($arrUserFields as &$field3) {
            $field3['n']     = 2;
            $field3['group'] = $templateType == 'mass_email' ? 'User information' : 'New User information';
        }
        unset($field3);

        return $arrUserFields;
    }

}
