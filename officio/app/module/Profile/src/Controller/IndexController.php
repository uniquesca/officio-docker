<?php

namespace Profile\Controller;

use Exception;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Common\Service\AccessLogs;
use Officio\Service\AuthHelper;
use Officio\Common\Service\Encryption;
use Officio\Service\Users;
use Laminas\Validator\EmailAddress;

/**
 * Profile Index Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class IndexController extends BaseController
{
    /** @var AccessLogs */
    protected $_accessLogs;

    /** @var Users */
    protected $_users;

    /** @var AuthHelper */
    protected $_authHelper;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_accessLogs = $services[AccessLogs::class];
        $this->_users      = $services[Users::class];
        $this->_authHelper = $services[AuthHelper::class];
        $this->_encryption = $services[Encryption::class];
    }

    public function indexAction()
    {
        $view = new ViewModel(
            ['content' => null]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');
        return $view;
    }

    public function loadAction()
    {
        $view = new JsonModel();

        $strError = '';
        $arrData  = array();

        try {
            $arrFieldsToShow = array(
                'fName',
                'lName',
                'emailAddress',
                'username',
                'show_special_announcements'
            );

            $arrMemberInfo = $this->_members->getMemberInfo();
            foreach ($arrFieldsToShow as $key) {
                if (isset($arrMemberInfo[$key])) {
                    $value = $arrMemberInfo[$key];
                    if ($key === 'show_special_announcements') {
                        $value = $arrMemberInfo[$key] === 'Y';
                    }

                    $arrData[$key] = $value;
                }
            }

            $arrData['queueShowInLeftPanel'] = $this->_auth->getIdentity()->queue_show_in_left_panel == 'Y';
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'data'    => $arrData,
            'success' => empty($strError),
            'message' => $strError
        );

        return $view->setVariables($arrResult);
    }

    public function saveAction()
    {
        $strError            = '';
        $booViewPanel        = false;
        $booHasAccessToQueue = false;
        $booSettingsChanged  = false;

        try {
            $booHasAccessToQueue = $this->_acl->isAllowed('clients-queue-run');

            // Get and check incoming params
            $arrParams = $this->findParams();

            // Filter incoming data
            $filter = new StripTags();
            foreach ($arrParams as $key => $val) {
                $arrParams[$key] = $filter->filter(trim($val));
            }

            $booCanChangeName = !$this->_auth->isCurrentUserClient();
            
            // Check if all fields are filled
            $arrAllFieldsToSave = array(
                'oldPassword',
            );

            if ($booCanChangeName) {
                $arrAllFieldsToSave[] = 'fName';
                $arrAllFieldsToSave[] = 'lName';
            }

            foreach ($arrAllFieldsToSave as $key) {
                if (empty($arrParams[$key])) {
                    $strError = $this->_tr->translate('Please fill all required fields.');
                }
            }


            // Check if password is correct
            $memberId            = $this->_auth->getCurrentUserId();
            $arrUpdateMemberInfo = array();
            $arrSavedMemberInfo  = array();
            if (empty($strError)) {
                $arrSavedMemberInfo = $this->_members->getMemberInfo($memberId);

                if (!$this->_encryption->checkPasswords($arrParams['oldPassword'], $arrSavedMemberInfo['password'])) {
                    $strError = $this->_tr->translate('Incorrect current password.');
                }

                // Update password only if it is not empty
                if (empty($strError) && isset($arrParams['newPassword']) && strlen($arrParams['newPassword'] ?? '')) {
                    $arrErrors = array();
                    $this->_authHelper->isPasswordValid($arrParams['newPassword'], $arrErrors, $arrSavedMemberInfo['username'], $memberId);
                    $strError = implode(', ', $arrErrors);

                    if (empty($strError) && $arrParams['newPassword'] != $arrParams['oldPassword']) {
                        $arrUpdateMemberInfo['password']             = $this->_encryption->hashPassword($arrParams['newPassword']);
                        $arrUpdateMemberInfo['password_change_date'] = time();
                    }
                }
            }

            // Check if email is correct
            if (empty($strError)) {
                $booCanUpdateEmail = $this->_members->canUpdateMemberEmailAddress($memberId);
                if ($booCanUpdateEmail) {
                    if (empty($arrParams['emailAddress'])) {
                        $strError = $this->_tr->translate('Please enter email address');
                    }

                    if (empty($strError)) {
                        $validator = new EmailAddress();
                        if (!$validator->isValid($arrParams['emailAddress'])) {
                            // Email is invalid; print the reasons
                            foreach ($validator->getMessages() as $message) {
                                $strError .= "$message\n";
                            }
                        }
                    }
                }

                if (empty($strError) && $booCanUpdateEmail) {
                    $arrUpdateMemberInfo['emailAddress'] = $arrParams['emailAddress'];
                }
            }

            // If everything is ok - save
            if (empty($strError)) {
                if ($booCanChangeName) {
                    $arrMemberFieldsToSave = array(
                        'fName',
                        'lName',
                    );

                    foreach ($arrMemberFieldsToSave as $key) {
                        $arrUpdateMemberInfo[$key] = $arrParams[$key];
                    }
                }

                // "Show special announcement messages" checkbox
                $arrUpdateMemberInfo['show_special_announcements'] = isset($arrParams['show_special_announcements']) ? 'Y' : 'N';

                if (!empty($arrUpdateMemberInfo)) {
                    $booSaved = $this->_members->updateMemberData($memberId, $arrUpdateMemberInfo);
                } else {
                    $booSaved = true;
                }

                if ($booSaved && !$this->_auth->isCurrentUserSuperadmin() && !$this->_auth->isCurrentUserClient()) {
                    $booViewPanel = isset($arrParams['queueShowInLeftPanel']);

                    $booSettingsChanged                                   = $this->_auth->getIdentity()->queue_show_in_left_panel != $booViewPanel;
                    $this->_auth->getIdentity()->queue_show_in_left_panel = $booViewPanel;

                    $booSaved = $this->_users->updateUser($memberId, array('queue_show_in_left_panel' => $booViewPanel && $booHasAccessToQueue ? 'Y' : 'N'));
                }

                if (!$booSaved) {
                    $strError = $this->_tr->translate('Internal error.');
                } else {
                    // Send confirmation email to this user
                    if (isset($arrUpdateMemberInfo['password'])) {
                        // Email must be provided to send emails
                        if (!isset($arrUpdateMemberInfo['emailAddress']) && isset($arrSavedMemberInfo['emailAddress'])) {
                            $arrUpdateMemberInfo['emailAddress'] = $arrSavedMemberInfo['emailAddress'];
                        }

                        $this->_authHelper->triggerPasswordHasBeenChanged(array_merge($arrUpdateMemberInfo, array('member_id' => $memberId)));
                    }

                    // Log this action
                    $arrLog = array(
                        'log_section'           => 'user',
                        'log_action'            => 'edit',
                        'log_description'       => '{2} profile was updated by {1}',
                        'log_company_id'        => $this->_auth->getCurrentUserCompanyId(),
                        'log_created_by'        => $memberId,
                        'log_action_applied_to' => $memberId,
                    );

                    if (!$this->_accessLogs->saveLog($arrLog)) {
                        $strError = $this->_tr->translate('Internal error.');
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return Json result
        $arrResult = array(
            'success'          => empty($strError),
            'message'          => empty($strError) ? '' : $strError,
            'updated_name'     => $this->_members->getCurrentMemberName(),
            'view_queue_panel' => $booHasAccessToQueue && $booViewPanel,
            'settings_changed' => $booSettingsChanged,
        );

        return new JsonModel($arrResult);
    }

    public function saveQuickMenuSettingsAction()
    {
        $strError = '';

        try {
            $type        = $this->params()->fromPost('type');
            $arrSettings = Json::decode($this->params()->fromPost('settings'));

            if (!in_array($type, ['quick_links', 'mouse_over_settings'])) {
                $strError = $this->_tr->translate('Incorrect incoming info.');
            }

            // Filter incoming data
            $filter = new StripTags();
            foreach ($arrSettings as $key => $val) {
                $arrSettings[$key] = $filter->filter(trim($val));
            }

            if (!is_array($arrSettings)) {
                $arrSettings = null;
            }

            if (empty($strError)) {
                $userId = $this->_auth->getCurrentUserId();

                $arrCurrentUserInfo      = $this->_users->getUserInfo($userId);
                $arrSavedSettings        = isset($arrCurrentUserInfo['quick_menu_settings']) ? Json::decode($arrCurrentUserInfo['quick_menu_settings'], Json::TYPE_ARRAY) : [];
                $arrSavedSettings[$type] = $arrSettings;

                $booSaved = $this->_users->updateUser($userId, array('quick_menu_settings' => Json::encode($arrSavedSettings)));
                if (!$booSaved) {
                    $strError = $this->_tr->translate('Internal error.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return Json result
        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? '' : $strError
        );

        return new JsonModel($arrResult);
    }

    public function toggleDailyNotificationsAction()
    {
        $strError = '';

        try {
            if (!$this->_members->toggleDailyNotifications($this->_auth->getCurrentUserId(), (bool)$this->params()->fromPost('enable', 0))) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }
}
