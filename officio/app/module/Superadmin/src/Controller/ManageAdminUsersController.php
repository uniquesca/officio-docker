<?php

namespace Superadmin\Controller;

use Clients\Service\Clients;
use Clients\Service\Clients\Fields;
use Clients\Service\Members;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Filter\StripTags;
use Laminas\Validator\EmailAddress;
use Laminas\View\Model\ViewModel;
use Officio\Common\Service\AccessLogs;
use Officio\BaseController;
use Officio\PagingManager;
use Officio\Service\AuthHelper;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\Users;

/**
 * Manage Admin Users Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ManageAdminUsersController extends BaseController
{
    /** @var StripTags */
    private $_filter;

    /** @var AccessLogs */
    protected $_accessLogs;

    /** @var Users */
    protected $_users;

    /** @var Company */
    protected $_company;

    /** @var AuthHelper */
    protected $_authHelper;

    /** @var Clients */
    protected $_clients;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_company    = $services[Company::class];
        $this->_clients    = $services[Clients::class];
        $this->_accessLogs = $services[AccessLogs::class];
        $this->_users      = $services[Users::class];
        $this->_authHelper = $services[AuthHelper::class];
        $this->_encryption = $services[Encryption::class];

        $this->_filter = new StripTags();
    }

    /**
     * The default action - show super admin users list
     */
    public function indexAction()
    {
        $view = new ViewModel();

        $title = $this->_tr->translate('Manage SuperAdmin Users');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $view->setVariable('booCanAddAdminUser', $this->_acl->isAllowed('manage-admin-user-add'));
        $view->setVariable('booCanEditAdminUser', $this->_acl->isAllowed('manage-admin-user-edit'));
        $view->setVariable('booCanDeleteAdminUser', $this->_acl->isAllowed('manage-admin-user-delete'));

        $view->setVariable('srchLimit', $srchLimit = $this->_filter->filter($this->params()->fromQuery('srchLimit')));
        $view->setVariable('srchName', $srchName = $this->_filter->filter($this->params()->fromQuery('srchName')));
        $view->setVariable('srchEmail', $srchEmail = $this->_filter->filter($this->params()->fromQuery('srchEmail')));
        $view->setVariable('srchUsername', $srchUsername = $this->_filter->filter($this->params()->fromQuery('srchUsername')));
        $view->setVariable('srchStatus', $srchStatus = $this->_filter->filter($this->params()->fromQuery('srchStatus')));
        $view->setVariable('order_by', $order_by = $this->_filter->filter($this->params()->fromQuery('order_by')));
        $view->setVariable('order_by2', $order_by2 = $this->_filter->filter($this->params()->fromQuery('order_by2')));

        $errMsg = $confirmationMsg = '';

        $currentMemberId        = $this->_auth->getCurrentUserId();
        $currentMemberCompanyId = $this->_auth->getCurrentUserCompanyId();
        $view->setVariable('currentMemberId', $currentMemberId);

        $section = $this->_filter->filter($this->findParam('section'));
        if ($this->getRequest()->isPost() && $section == 'admin_users') {
            // Check received data
            $action = $this->_filter->filter($this->findParam('listingAction'));
            $delIDs = $this->findParam('delIDs');

            // This code is for delete/activate/deactivate listings.
            if (in_array($action, array('delete', 'deactivate', 'activate'))) {
                // firstly check whether user has selected any checkbox or not.
                if (!is_array($delIDs)) {
                    $errMsg = $this->_tr->translate("Please select checkboxes to delete or to activate/deactivate any listing");
                }

                if (empty($errMsg) && in_array($currentMemberId, $delIDs)) {
                    $errMsg = sprintf($this->_tr->translate('You cannot %s yourself.'), $this->_tr->translate($action));
                }

                if (empty($errMsg)) {
                    // User has selected checkboxes then check whether its a delete action or activate/deactivate
                    switch ($action) {
                        case 'delete':
                            if ($this->_acl->isAllowed('manage-admin-user-delete')) {
                                if (!$this->_members->deleteMember($currentMemberCompanyId, $delIDs, [], 'superadmin')) {
                                    $errMsg = $this->_tr->translate('An error occurred. Please contact to web site administrator.');
                                } else {
                                    $confirmationMsg = $this->_tr->translate('Selected Users were successfully deleted');
                                }
                            }
                            break;

                        case 'activate':
                            if ($this->_members->toggleMemberStatus($delIDs, $currentMemberCompanyId, $currentMemberId, true)) {
                                $confirmationMsg = $this->_tr->translate('Selected Users were successfully activated');
                            }
                            break;

                        case 'deactivate':
                            if ($this->_members->toggleMemberStatus($delIDs, $currentMemberCompanyId, $currentMemberId, false)) {
                                $confirmationMsg = $this->_tr->translate('Selected Users were successfully deactivated');
                            }
                            break;

                        default:
                            // Do nothing
                            break;
                    }
                }
            }
        }


        $pagingSize = empty($srchLimit) ? 25 : $srchLimit;

        $routeMatch = $this->getEvent()->getRouteMatch();
        $routeName  = $routeMatch->getMatchedRouteName();
        $params     = $routeMatch->getParams();
        $baseUrl    = $this->url()->fromRoute($routeName, $params);
        $paging     = new PagingManager($baseUrl, $pagingSize);

        $select  = (new Select())
            ->from('members');

        if (!empty($srchName)) {
            $select->where(
                function (Where $where) use ($srchName) {
                    $where
                        ->nest()
                        ->like('fName', '%' . $srchName . '%')
                        ->or
                        ->like('lName', '%' . $srchName . '%')
                        ->unnest();
                }
            );
        }

        if (! empty($srchEmail)) {
            $select->where(function (Where $where) use ($srchEmail) {
                $where->like('emailAddress', "%$srchEmail%");
            });
        }

        if (! empty($srchUsername)) {
            $select->where(function (Where $where, $srchUsername) {
                $where->like('username', "%$srchUsername%");
            });
        }

        if ($srchStatus != '') {
            $select->where(['status' => $srchStatus]);
        }

        // Show superadmin users only
        $arrUserTypes = Members::getMemberType('superadmin');
        if (count($arrUserTypes) > 0) {
            $select->where(['userType' => $arrUserTypes]);
        }

        if (!empty($order_by)) {
            $select->order($order_by . ' ' . $order_by2);
        } else {
            $select->order('member_id DESC');
        }

        $select->limit($paging->getOffset());
        $select->offset($paging->getStart());

        $results = $this->_db2->fetchAll($select);

        if (count($results) == 0) {
            $confirmationMsg = $this->_tr->translate('There are no records matching your query');
        } else {
            $view->setVariable('results', $results );

            $totalRecords = $this->_db2->fetchResultsCount($select);

            $view->setVariable('sn', $paging->getStart());
            $view->setVariable('pagingStr', $paging->doPaging($totalRecords));

            $view->setVariable('totalRecords', $totalRecords);
        }

        //get error messages
        $view->setVariable('error_message', $errMsg);
        $view->setVariable('confirmation_message', $confirmationMsg);

        return $view;
    }

    private function _loadAdminUserInfo($memberId = 0) {
        $arrAdminUserInfo = array(
            'username'     => '',
            'password'     => '',
            'fName'        => '',
            'lName'        => '',
            'timeZone'     => '',
            'memberRole'   => '',
            'emailAddress' => '',
            'mobilePhone'  => '',
            'workPhone'    => '',
            'homePhone'    => '',
            'notes'        => '',
        );

        if (!empty($memberId)) {
            // Load from db
            $select = (new Select())
                ->from(['m' => 'members'])
                ->join(['u' => 'users'], 'm.member_id = u.member_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER)
                ->join(['r' => 'members_roles'], 'm.member_id = r.member_id', ['memberRole' => 'role_id'], Select::JOIN_LEFT_OUTER)
                ->where(['m.member_id' => $memberId]);

            $arrAdminUserInfo = $this->_db2->fetchRow($select);

            // Decrypt password to show it
            $arrAdminUserInfo['password'] = $this->_encryption->decodeHashedPassword($arrAdminUserInfo['password']);
        }

        return $arrAdminUserInfo;
    }

    private function _CreateUpdateUserAdmin($memberId)
    {
        $msgError = '';

        $arrAdminUserInfo = array(
            'username'     => $this->_filter->filter($this->findParam('username')),
            'memberRole'   => $this->_filter->filter($this->findParam('memberRole')),
            'timeZone'     => $this->_filter->filter($this->findParam('timeZone')),
            'fName'        => $this->_filter->filter($this->findParam('fName')),
            'lName'        => $this->_filter->filter($this->findParam('lName')),
            'emailAddress' => $this->_filter->filter($this->findParam('emailAddress')),
            'mobilePhone'  => $this->_filter->filter($this->findParam('mobilePhone')),
            'workPhone'    => $this->_filter->filter($this->findParam('workPhone')),
            'homePhone'    => $this->_filter->filter($this->findParam('homePhone')),
            'notes'        => $this->_filter->filter($this->findParam('notes'))
        );

        $password = $this->_filter->filter($this->findParam('password'));
        if (empty($memberId)) {//add
            $arrAdminUserInfo['password'] = $password;
        } elseif (!empty($password)) {
            $arrAdminUserInfo['password']             = $password; // update password
            $arrAdminUserInfo['password_change_date'] = time();
        }

        // Check received data

        // Check User Info
        if (empty($arrAdminUserInfo['username']) && empty($msgError)) {
            $msgError = $this->_tr->translate('Please enter user name');
        }

        if (empty($msgError) && !Fields::validUserName($arrAdminUserInfo['username'])) {
            $msgError = 'Incorrect characters in username';
        }

        if (empty($msgError) && $this->_members->isUsernameAlreadyUsed($arrAdminUserInfo['username'], $memberId)) {
            $msgError = $this->_tr->translate('Duplicate username, please choose another');
        }

        if (empty($memberId) && empty($arrAdminUserInfo['password']) && empty($msgError)) {
            $msgError = $this->_tr->translate('Please enter password');
        }

        $errMsg = array();
        if (empty($msgError) && (empty($memberId) || (!empty($arrAdminUserInfo['password']))) && !$this->_authHelper->isPasswordValid($arrAdminUserInfo['password'], $errMsg, $arrAdminUserInfo['username'], $memberId)) {
            $msgError = array_shift($errMsg); // get first error message
        }

        if (empty($arrAdminUserInfo['fName']) && empty($msgError)) {
            $msgError = $this->_tr->translate('Please enter admin first name');
        }

        if (empty($arrAdminUserInfo['lName']) && empty($msgError)) {
            $msgError = $this->_tr->translate('Please enter admin last name');
        }

        if (empty($arrAdminUserInfo['emailAddress']) && empty($msgError)) {
            $msgError = $this->_tr->translate('Please enter admin email address');
        }

        if (empty($msgError) && (!is_numeric($arrAdminUserInfo['timeZone']) || $arrAdminUserInfo['timeZone'] < 0)) {
            $msgError = $this->_tr->translate('Please select time zone');
        }

        if (empty($msgError)) {
            $validator = new EmailAddress();
            if (!$validator->isValid($arrAdminUserInfo['emailAddress'])) {
                // email is invalid; print the reasons
                foreach ($validator->getMessages() as $message) {
                    $msgError .= "$message\n";
                }
            }
        }

        // Check received role
        if (empty($msgError)) {
            $arrRoles = $this->_members->getSuperAdminRoles();
            if (!is_array($arrRoles) || !count($arrRoles)) {
                $msgError = $this->_tr->translate('There are no superadmin roles.');
            } else {
                $arrRoleIds = array_keys($arrRoles);
                if (!in_array($arrAdminUserInfo['memberRole'], $arrRoleIds)) {
                    $msgError = $this->_tr->translate('Incorrectly selected role.');
                }
            }
        }

        if (!empty($msgError)) {
            $arrAdminUserInfo['error'] = $msgError;
            return $arrAdminUserInfo;
        }

        $arrUserInfo = array(
            'mobilePhone' => $arrAdminUserInfo['mobilePhone'],
            'workPhone'   => $arrAdminUserInfo['workPhone'],
            'homePhone'   => $arrAdminUserInfo['homePhone'],
            'timeZone'    => $arrAdminUserInfo['timeZone'],
            'notes'       => $arrAdminUserInfo['notes']
        );

        $arrResult = $arrAdminUserInfo;
        foreach ($arrUserInfo as $key => $val) {
            unset($arrAdminUserInfo[$key]);
        }

        // We'll save in another table
        $selectedRole = $arrAdminUserInfo['memberRole'];
        unset($arrAdminUserInfo['memberRole']);


        if (isset($arrAdminUserInfo['password'])) {
            if (!empty($memberId)) {
                // Send confirmation email to this user
                $this->_authHelper->triggerPasswordHasBeenChanged(array_merge($arrAdminUserInfo, array('member_id' => $memberId)));
            }

            $arrAdminUserInfo['password'] = $this->_encryption->hashPassword($arrAdminUserInfo['password']);
        }

        $companyId       = $this->_auth->getCurrentUserCompanyId();
        $currentMemberId = $this->_auth->getCurrentUserId();
        if (!empty($memberId)) {
            $this->_db2->update('members', $arrAdminUserInfo, ['member_id' => $memberId]);

            $this->_users->updateUser($memberId, $arrUserInfo);

            // Log this action
            $arrLog = array(
                'log_section'           => 'user',
                'log_action'            => 'edit',
                'log_description'       => '{2} profile was updated by {1}',
                'log_company_id'        => $companyId,
                'log_created_by'        => $currentMemberId,
                'log_action_applied_to' => $memberId,
            );
        } else {
            $arrUserTypes                      = Members::getMemberType('superadmin');
            $arrAdminUserInfo['userType']      = $arrUserTypes[0];
            $arrAdminUserInfo['company_id']    = 0;
            $arrAdminUserInfo['login_enabled'] = 'Y';
            $arrAdminUserInfo['status']        = 1;
            $arrAdminUserInfo['regTime']       = time();

            $memberId  = $this->_db2->insert('members', $arrAdminUserInfo);

            $arrResult['member_id'] = $arrAdminUserInfo['member_id'] = $arrUserInfo['member_id'] = $memberId;

            $this->_db2->insert('users', $arrUserInfo);

            //set default search
            $this->_clients->getSearch()->setMemberDefaultSearch($memberId);

            // Log this action
            $arrLog = array(
                'log_section'           => 'user',
                'log_action'            => 'add',
                'log_description'       => '{2} profile was created by {1}',
                'log_company_id'        => $companyId,
                'log_created_by'        => $currentMemberId,
                'log_action_applied_to' => $memberId,
            );
        }

        $this->_accessLogs->saveLog($arrLog);

        $this->_company->updateTimeTracker(0, true, true);

        // Create/update role for superadmin
        $this->_members->updateMemberRoles($memberId, array($selectedRole));

        $arrResult['error'] = '';

        return $arrResult;
    }

    public function addAction()
    {
        $view = new ViewModel();

        $memberId = 0;
        $msgError = '';

        if ($this->getRequest()->isPost()) {
            // Save received data
            $arrAdminUserInfo = $this->_CreateUpdateUserAdmin($memberId);

            if (empty($arrAdminUserInfo['error'])) {
                // Go to 'Edit this user' page
                return $this->redirect()->toUrl('/superadmin/manage-admin-users/edit?' . http_build_query(['member_id' => $arrAdminUserInfo['member_id'], 'status' => 1]));
            }

            $msgError = $arrAdminUserInfo['error'];
        } else {
            $arrAdminUserInfo = $this->_loadAdminUserInfo($memberId);
        }

        $title = $this->_tr->translate('Add new admin user');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $view->setVariable('edit_admin_user_id', $memberId);
        $view->setVariable('arrAdminUserInfo', $arrAdminUserInfo);

        $view->setVariable('edit_error_message', $msgError);
        $view->setVariable('confirmation_message', '');

        $view->setVariable('passwordMinLength', $this->_settings->passwordMinLength);
        $view->setVariable('passwordMaxLength', $this->_settings->passwordMaxLength);

        $view->setVariable('arrTimeZones', $this->_settings->getWebmailTimeZones());
        $view->setVariable('arrRoles', $this->_members->getSuperAdminRoles(true));

        $view->setVariable('btnHeading', "Create New Admin User");

        return $view;
    }

    public function editAction()
    {
        $view = new ViewModel();

        $title = $this->_tr->translate('Edit Admin User');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $msgError = $msgConfirmation = '';

        if ($this->getRequest()->isPost()) {

            $memberId = $this->_filter->filter($this->findParam('member_id'));

            // Save received data
            $arrAdminUserInfo = $this->_CreateUpdateUserAdmin($memberId);
            $msgError         = $arrAdminUserInfo['error'];
            if (empty($msgError)) {
                $msgConfirmation = "SuperAdmin was successfully updated";
            }

        } else {
            $memberId = $this->findParam('member_id');
            if (!is_numeric($memberId)) {
                return $this->redirect()->toUrl('/superadmin/manage-admin-users/');
            }
            $arrAdminUserInfo = $this->_loadAdminUserInfo($memberId);

            $status = $this->_filter->filter($this->findParam('status'));
            if (is_numeric($status) && $status == '1') {
                $msgConfirmation = "SuperAdmin was successfully created";
            }
        }

        $view->setVariable('edit_admin_user_id', $memberId);
        $view->setVariable('arrAdminUserInfo', $arrAdminUserInfo);

        $view->setVariable('edit_error_message', $msgError);
        $view->setVariable('confirmation_message', $msgConfirmation);
        $view->setVariable('btnHeading', "Update Admin User");

        $view->setVariable('passwordMinLength', $this->_settings->passwordMinLength);
        $view->setVariable('passwordMaxLength', $this->_settings->passwordMaxLength);

        $view->setVariable('arrTimeZones', $this->_settings->getWebmailTimeZones());
        $view->setVariable('arrRoles', $this->_members->getSuperAdminRoles());

        return $view;
    }

    public function deleteAction()
    {
        // This is a stub, real delete action is in index controller
        return $this->redirect()->toUrl('/superadmin/manage-admin-users/');
    }

}
