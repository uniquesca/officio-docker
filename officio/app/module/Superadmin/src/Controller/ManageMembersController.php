<?php

namespace Superadmin\Controller;

use Clients\Service\Clients;
use Clients\Service\Clients\Fields;
use Clients\Service\Members;
use Clients\Service\MembersVevo;
use Exception;
use Files\Service\Files;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\HelperPluginManager;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Email\Models\MailAccount;
use Officio\Common\Service\AccessLogs;
use Officio\Service\AuthHelper;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Common\Service\Encryption;
use Officio\Service\Roles;
use Officio\Service\Tickets;
use Officio\Service\Users;
use Laminas\Validator\EmailAddress;
use Officio\View\Helper\MessageBox;

/**
 * Manage Users Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ManageMembersController extends BaseController
{

    /** @var Company */
    protected $_company;

    /** @var Tickets */
    protected $_tickets;

    /** @var AccessLogs */
    protected $_accessLogs;

    /** @var Users */
    protected $_users;

    /** @var Clients */
    protected $_clients;

    /** @var AuthHelper */
    protected $_authHelper;

    /** @var Country */
    protected $_country;

    /** @var MembersVevo */
    protected $_membersVevo;

    /** @var Files */
    protected $_files;

    /** @var Roles */
    protected $_roles;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_accessLogs  = $services[AccessLogs::class];
        $this->_users       = $services[Users::class];
        $this->_clients     = $services[Clients::class];
        $this->_company     = $services[Company::class];
        $this->_authHelper  = $services[AuthHelper::class];
        $this->_country     = $services[Country::class];
        $this->_membersVevo = $services[MembersVevo::class];
        $this->_tickets     = $services[Tickets::class];
        $this->_files       = $services[Files::class];
        $this->_roles       = $services[Roles::class];
        $this->_encryption  = $services[Encryption::class];

        error_reporting(E_ALL & E_STRICT);
    }

    public function indexAction()
    {
        $view = new ViewModel();

        if ($this->_auth->isCurrentUserSuperadmin()) {
            $title = $this->_tr->translate('Manage All Company Users');
        } else {
            $title = $this->_tr->translate('Users');
        }
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $companyId       = $this->_getCompanyId();
        $booIsSuperAdmin = $this->_auth->isCurrentUserSuperadmin();
        $divisionGroupId = $booIsSuperAdmin ? 0 : $this->_auth->getCurrentUserDivisionGroupId();

        // Load list of Offices
        $arrDivisions        = array();
        $arrCompanyDivisions = $this->_company->getDivisions($companyId, $divisionGroupId);
        foreach ($arrCompanyDivisions as $arrCompanyDivisionInfo) {
            $arrDivisions[] = array(
                'division_id'   => $arrCompanyDivisionInfo['division_id'],
                'division_name' => $arrCompanyDivisionInfo['name']
            );
        }

        // Load list of Roles
        $arrRolesOrder = array();
        $arrRoles      = array();

        $booShowWithoutGroup = $this->_auth->isCurrentUserSuperadmin() || $this->_auth->isCurrentUserSuperadminMaskedAsAdmin();
        if (!$booShowWithoutGroup) {
            // Current user is system - don't show
            $arrDivisionGroupInfo = $this->_company->getCompanyDivisions()->getDivisionsGroupInfo($this->_auth->getCurrentUserDivisionGroupId());
            if (isset($arrDivisionGroupInfo['division_group_is_system'])) {
                $booShowWithoutGroup = $arrDivisionGroupInfo['division_group_is_system'] == 'N';
            }
        }

        $arrCompanyRoles = $this->_roles->getCompanyRoles($companyId, $divisionGroupId, $booShowWithoutGroup);
        $booAgentRole    = $this->_auth->isCurrentUserAuthorizedAgent();
        foreach ($arrCompanyRoles as $key => $arrCompanyRoleInfo) {
            if ($booAgentRole) {
                $arrCompanyRoleInfo['role_name'] = str_replace('Agent', '', $arrCompanyRoleInfo['role_name'] ?? '');
            }

            $arrRoles[] = array(
                'role_id'   => $arrCompanyRoleInfo['role_id'],
                'role_name' => $arrCompanyRoleInfo['role_name']
            );

            $arrRolesOrder[$key] = $arrCompanyRoleInfo['role_name'];
        }

        // Sort roles by order
        array_multisort($arrRolesOrder, SORT_ASC, $arrRoles);

        // Calculate active users count and all related things
        if ($booIsSuperAdmin) {
            $freeUsersCount          = 100500;
            $activeUsersCount        = 1;
            $pricePerUser            = 0;
            $booCanAddUsersOverLimit = 1;
        } else {
            $arrCompanyDetails = $this->_company->getCompanyDetailsInfo($companyId);
            $freeUsersCount    = $arrCompanyDetails['free_users'];
            $activeUsersCount  = $this->_company->calculateActiveUsers($companyId);
            $arrPrices         = $this->_company->getCompanyPrices($companyId, false, $arrCompanyDetails['pricing_category_id']);

            $arrSubscriptions = $this->_company->getPackages()->getSubscriptionsList(true, true);
            $subscriptionId   = in_array($arrCompanyDetails['subscription'], $arrSubscriptions) ? $arrCompanyDetails['subscription'] : 'lite';
            $subscriptionId   = $subscriptionId === 'ultimate_plus' ? 'ultimate' : $subscriptionId;

            $name = ucfirst($subscriptionId);

            // Load if users count can be increased over the limit
            $pricePerUser            = $arrPrices['package' . $name . 'UserLicenseMonthly'];
            $booCanAddUsersOverLimit = $arrPrices['package' . $name . 'AddUsersOverLimit'];
        }

        $arrSettings = array(
            'booEditInNewTab'     => $booIsSuperAdmin,
            'membersPerPageCount' => Members::$membersPerPage,
            'officeLabel'         => $this->_company->getCompanyDefaultLabel($companyId, 'office'),
            'first_name_label'    => $this->_company->getCurrentCompanyDefaultLabel('first_name'),
            'last_name_label'     => $this->_company->getCurrentCompanyDefaultLabel('last_name'),
            'companyId'           => $companyId,
            'arrRoles'            => $arrRoles,
            'arrDivisions'        => $arrDivisions,

            'access' => array(
                'view'            => $this->_acl->isAllowed('manage-members-view-details'),
                'add'             => $this->_acl->isAllowed('manage-members-add') && !$booIsSuperAdmin,
                'edit'            => $this->_acl->isAllowed('manage-members-edit'),
                'delete'          => $this->_acl->isAllowed('manage-members-delete'),
                'login-as-member' => $this->_acl->isAllowed('manage-company-as-admin'),
            ),

            'arrCompanyDetails' => array(
                'usersLimitInPackage'     => (int)$freeUsersCount,
                'pricePerUserLicense'     => $pricePerUser,
                'currentUsersCount'       => $activeUsersCount,
                'booCanAddUsersOverLimit' => (int)$booCanAddUsersOverLimit
            ),

        );

        $view->setVariable('arrSettings', $arrSettings);

        return $view;
    }

    /**
     * Load current logged in user company id
     * For superadmin company id can be passed from params
     *
     * @return int company id
     */
    private function _getCompanyId()
    {
        $companyId = $this->findParam('company_id', 0);
        // Company ID can be also passed as a route param
        if (!$companyId) {
            $companyId = $this->params('company_id', 0);
        }
        if ($this->_auth->isCurrentUserSuperadmin() && is_numeric($companyId) && !empty($companyId)) {
            // Yes! we want to use it!
        } else {
            $companyId = $this->_auth->getCurrentUserCompanyId();
        }

        return $companyId;
    }

    /**
     * Load list of saved Members records from DB
     */
    public function listAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            $view = new ViewModel(
                [
                    'content' => $this->_tr->translate('Insufficient access rights.')
                ]
            );
            $view->setTerminal(true);
            $view->setTemplate('layout/plain');

            return $view;
        }

        $view = new JsonModel();

        $arrResult = array(
            'rows'       => array(),
            'totalCount' => 0
        );

        try {
            $sort  = $this->findParam('sort');
            $dir   = $this->findParam('dir');
            $start = $this->findParam('start', 0);
            $limit = Members::$membersPerPage;

            $arrFilterData = array(
                'filter_email'               => Json::decode($this->findParam('filter_email'), Json::TYPE_ARRAY),
                'filter_first_name'          => Json::decode($this->findParam('filter_first_name'), Json::TYPE_ARRAY),
                'filter_last_name'           => Json::decode($this->findParam('filter_last_name'), Json::TYPE_ARRAY),
                'filter_username'            => Json::decode($this->findParam('filter_username'), Json::TYPE_ARRAY),
                'filter_role'                => Json::decode($this->findParam('filter_role'), Json::TYPE_ARRAY),
                'filter_division'            => Json::decode($this->findParam('filter_division'), Json::TYPE_ARRAY),
                'filter_hide_inactive_users' => Json::decode($this->findParam('filter_hide_inactive_users'), Json::TYPE_ARRAY)
            );

            $booIsAgentRole  = $this->_auth->isCurrentUserAuthorizedAgent();
            $booIsSuperAdmin = $this->_auth->isCurrentUserSuperadmin();
            $divisionGroupId = $booIsSuperAdmin ? 0 : $this->_auth->getCurrentUserDivisionGroupId();

            list($arrMembers, $arrResult['totalCount']) = $this->_members->getMembersList($this->_getCompanyId(), $divisionGroupId, $arrFilterData, $sort, $dir, $start, $limit);
            foreach ($arrMembers as $arrMemberInfo) {
                $strRoles       = '';
                $arrMemberRoles = $this->_members->getMemberRoles($arrMemberInfo['member_id'], false);
                if (is_array($arrMemberRoles)) {
                    foreach ($arrMemberRoles as $roleInfo) {
                        if ($booIsAgentRole) {
                            $roleInfo['role_name'] = str_replace('Agent', '', $roleInfo['role_name'] ?? '');
                        }

                        $strRoles .= $roleInfo['role_name'] . '<br>';
                    }
                }

                $strDivisions     = '';
                $arrUserDivisions = $this->_members->getMemberDivisionsInfo($arrMemberInfo['member_id']);
                foreach ($arrUserDivisions as $arrUserDivisionInfo) {
                    $strDivisions .= $arrUserDivisionInfo['name'] . '<br>';
                }

                $arrResult['rows'][] = array(
                    'member_id'         => $arrMemberInfo['member_id'],
                    'company_id'        => $arrMemberInfo['company_id'],
                    'member_first_name' => $arrMemberInfo['fName'],
                    'member_last_name'  => $arrMemberInfo['lName'],
                    'member_username'   => $arrMemberInfo['username'],
                    'member_email'      => $arrMemberInfo['emailAddress'],
                    'member_role'       => $strRoles,
                    'member_office'     => $strDivisions,
                    'member_status'     => $arrMemberInfo['status'],
                    'member_created_on' => date('Y-m-d H:i:s', $arrMemberInfo['regTime']),
                );
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables($arrResult);
    }

    public function changeStatusAction()
    {
        $view     = new JsonModel();
        $strError = '';

        try {
            $companyId       = $this->_getCompanyId();
            $currentMemberId = $this->_auth->getCurrentUserId();

            $arrMemberIds = Json::decode($this->findParam('arrMemberIds'), Json::TYPE_ARRAY);
            if ((!is_array($arrMemberIds) || empty($arrMemberIds))) {
                $strError = $this->_tr->translate('Incorrectly selected users.');
            }

            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($arrMemberIds)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            // User can not activate/deactivate himself
            $booActivate = (int)Json::decode($this->findParam('booActivate'), Json::TYPE_ARRAY);
            if (empty($strError) && in_array($currentMemberId, $arrMemberIds)) {
                $strError = $booActivate ? $this->_tr->translate('You cannot activate yourself.') : $this->_tr->translate('You cannot deactivate yourself.');
            }

            if (empty($strError) && !$this->_members->toggleMemberStatus($arrMemberIds, $companyId, $currentMemberId, $booActivate ? 1 : 0)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('Done!') : $strError,
        );

        return $view->setVariables($arrResult);
    }

    public function deleteAction()
    {
        $view = new JsonModel();

        try {
            $companyId       = $this->_getCompanyId();
            $currentMemberId = $this->_auth->getCurrentUserId();

            // We temporary don't allow to delete users
            $strError = $this->_tr->translate('Delete user is not allowed because users are referenced in various notes, tasks and other history of records.');

            $arrMemberIds = Json::decode($this->findParam('arrMemberIds'), Json::TYPE_ARRAY);
            if (empty($strError) && (!is_array($arrMemberIds) || empty($arrMemberIds))) {
                $strError = $this->_tr->translate('Incorrectly selected users.');
            }

            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($arrMemberIds)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            // User can not delete himself
            if (empty($strError) && in_array($currentMemberId, $arrMemberIds)) {
                $strError = $this->_tr->translate('You cannot delete yourself.');
            }

            if (empty($strError) && !$this->_members->deleteMember($companyId, $arrMemberIds, [], 'user')) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('Done!') : $strError,
        );

        return $view->setVariables($arrResult);
    }

    private function _loadMemberInfo($member_id = 0)
    {
        $arrMemberInfo = array(
            'arrRoles'          => array(),
            'company_id'        => '',
            'division_group_id' => '',
            'userType'          => '',
            'username'          => '',
            'password'          => '',
            'emailAddress'      => '',
            'activationCode'    => '',

            'fName'                      => '',
            'lName'                      => '',
            'notes'                      => '',
            'address'                    => '',
            'city'                       => '',
            'state'                      => '',
            'country'                    => '',
            'zip'                        => '',
            'workPhone'                  => '',
            'homePhone'                  => '',
            'fax'                        => '',
            'divisions_access_to'        => array(),
            'divisions_responsible_for'  => array(),
            'divisions_pull_from'        => array(),
            'divisions_push_to'          => array(),
            'emailsign'                  => '',
            'email_signature'            => '',
            'user_is_rma'                => 'N',
            'user_migration_number'      => '',
            'enable_daily_notifications' => 'Y',
            'vevo_members'               => array()
        );

        $companyId = 0;
        if (!$this->_auth->isCurrentUserSuperadmin()) {
            $companyId = $this->_auth->getCurrentUserCompanyId();

            $arrMemberInfo['company_id']        = $companyId;
            $arrMemberInfo['division_group_id'] = $this->_auth->getCurrentUserDivisionGroupId();
        }


        if (!empty($member_id)) {
            // Load from db
            $arrWhere                = [];
            $arrWhere['m.member_id'] = $member_id;

            // If this is not a superadmin - check if member is in his company
            if (!$this->_auth->isCurrentUserSuperadmin()) {
                $arrWhere['m.company_id'] = $companyId;
            }

            $select = (new Select())
                ->from(['m' => 'members'])
                ->join(['u' => 'users'], 'm.member_id = u.member_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER)
                ->where($arrWhere);

            $arrMemberInfo = $this->_db2->fetchRow($select);

            // Decrypt password to show it 
            $arrMemberInfo['password'] = $this->_encryption->decodeHashedPassword($arrMemberInfo['password']);
            
            
            $arrMemberInfo['divisions_access_to']       = $this->_members->getMemberDivisions($member_id);
            $arrMemberInfo['divisions_responsible_for'] = $this->_members->getMemberDivisions($member_id, 'responsible_for');
            $arrMemberInfo['divisions_pull_from']       = $this->_members->getMemberDivisions($member_id, 'pull_from');
            $arrMemberInfo['divisions_push_to']         = $this->_members->getMemberDivisions($member_id, 'push_to');
            $arrMemberInfo['arrRoles']                  = $this->_members->getMemberRoles($member_id);
            $arrMemberInfo['vevo_members']              = $this->_membersVevo->getMembersToVevoMappingList($member_id);
        }
        
        return $arrMemberInfo;
    }


    private function _loadCompaniesList()
    {
        $arrWhere           = [];
        $arrWhere['status'] = 1;

        if (!$this->_auth->isCurrentUserSuperadmin()) {
            $arrWhere['company_id'] = $this->_auth->getCurrentUserCompanyId();
        }

        $select = (new Select())
            ->from('company')
            ->columns(['company_id', 'companyName'])
            ->where($arrWhere);

        $arrRolesList = $this->_db2->fetchAll($select);
        $arrPlease[]  = array('company_id' => '', 'companyName' => '-- Please select --');

        return array_merge($arrPlease, $arrRolesList);
    }

    private function _CreateUpdateMember($memberId = 0, $booEnabledTimeTracker = false)
    {
        $msgError = '';

        try {
            if (!$this->getRequest()->isPost()) {
                $msgError = $this->_tr->translate('Parameters were sent incorrectly.');
            }

            $filter = new StripTags();
            $arrRoles = $this->params()->fromPost('arrRoles');

            $arrMemberInfo = array(
                'arrRoles'                   => $arrRoles,
                'userType'                   => $filter->filter($this->params()->fromPost('userType')),
                'company_id'                 => $filter->filter($this->params()->fromPost('company_id')),
                'username'                   => $filter->filter($this->params()->fromPost('username')),
                'divisions_access_to'        => array_filter(explode(',', $this->params()->fromPost('divisions_access_to', ''))),
                'divisions_responsible_for'  => array_filter(explode(',', $this->params()->fromPost('divisions_responsible_for', ''))),
                'divisions_pull_from'        => array_filter(explode(',', $this->params()->fromPost('divisions_pull_from', ''))),
                'divisions_push_to'          => array_filter(explode(',', $this->params()->fromPost('divisions_push_to', ''))),

                'activationCode'             => $filter->filter($this->params()->fromPost('activationCode')),

                'fName'                      => $filter->filter($this->params()->fromPost('fName')),
                'lName'                      => $filter->filter($this->params()->fromPost('lName')),
                'emailAddress'               => trim($filter->filter($this->params()->fromPost('emailAddress', ''))),
                'notes'                      => $filter->filter($this->params()->fromPost('notes')),
                'enable_daily_notifications' => $filter->filter($this->params()->fromPost('enable_daily_notifications')) === 'Y' ? 'Y' : 'N',
                'emailsign'                  => $filter->filter($this->params()->fromPost('emailsign')),
                'email_signature'            => $filter->filter($this->params()->fromPost('email_signature')),
                'user_is_rma'                => $filter->filter($this->params()->fromPost('user_is_rma')),
                'user_migration_number'      => trim($filter->filter($this->params()->fromPost('user_migration_number', ''))),

                'address'                    => $filter->filter($this->params()->fromPost('address')),
                'city'                       => $filter->filter($this->params()->fromPost('city')),
                'state'                      => $filter->filter($this->params()->fromPost('state')),
                'country'                    => $filter->filter($this->params()->fromPost('country')),
                'zip'                        => $filter->filter($this->params()->fromPost('zip')),
                'workPhone'                  => $filter->filter($this->params()->fromPost('workPhone')),
                'homePhone'                  => $filter->filter($this->params()->fromPost('homePhone')),
                'mobilePhone'                => $filter->filter($this->params()->fromPost('mobilePhone')),
                'fax'                        => $filter->filter($this->params()->fromPost('fax')),
                'timeZone'                   => $filter->filter($this->params()->fromPost('timeZone')),

                'time_tracker_enable'        => $filter->filter($this->params()->fromPost('time_tracker_enable', 'N')),
                'time_tracker_disable_popup' => $filter->filter($this->params()->fromPost('time_tracker_disable_popup', 'N')),
                'time_tracker_rate'          => $filter->filter($this->params()->fromPost('time_tracker_rate', 0)),
                'time_tracker_round_up'      => $filter->filter($this->params()->fromPost('time_tracker_round_up', 0)),
                'vevo_login'                 => $filter->filter($this->params()->fromPost('vevo_login')),
                'vevo_password'              => $filter->filter($this->params()->fromPost('vevo_password')),
                'vevo_members'               => array_filter(explode(',', $this->params()->fromPost('vevo_members', '')))
            );

            $arrSavedMemberInfo = empty($memberId) ? [] : $this->_members->getMemberInfo($memberId);
            if (!empty($this->_config['security']['oauth_login']['enabled'])) {
                $arrMemberInfo['oauth_idir'] = trim($filter->filter($this->params()->fromPost('oauth_idir')) ?? '');
                $arrMemberInfo['oauth_guid'] = empty($arrSavedMemberInfo['oauth_guid']) ? null : $arrSavedMemberInfo['oauth_guid'];

                if (!empty($arrMemberInfo['oauth_idir']) && $arrMemberInfo['oauth_idir'] != $arrSavedMemberInfo['oauth_idir']) {
                    $arrMemberInfo['oauth_guid'] = null;
                }

                if (empty($msgError) && empty($arrMemberInfo['oauth_idir'])) {
                    $msgError = $this->_config['security']['oauth_login']['single_sign_on_label'] . $this->_tr->translate(' is a required field.');
                }
            }

            if (empty($memberId) || $this->_acl->isAllowed('manage-members-edit')) {
                $password = $filter->filter($this->params()->fromPost('password'));
                if (empty($memberId)) {//add
                    $arrMemberInfo['password'] = $password;
                } elseif (!empty($password)) { //update
                    $arrMemberInfo['password'] = $password;
                    $arrMemberInfo['password_change_date'] = time();
                }
            }

            // Check received data

            // Check User Info
            $companyId       = '';
            $divisionGroupId = 0;
            $booIsSuperAdmin = $this->_auth->isCurrentUserSuperadmin();
            if (empty($msgError)) {
                if (!$booIsSuperAdmin) {
                    $companyId                          = $this->_auth->getCurrentUserCompanyId();
                    $arrMemberInfo['company_id']        = $companyId;
                    $arrMemberInfo['division_group_id'] = $divisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();
                } else {
                    // Superadmin
                    if (empty($memberId)) {
                        $msgError = $this->_tr->translate('Please login as company admin to add a new user');
                    } else {
                        // Load previously saved member id
                        $companyId                          = $arrSavedMemberInfo['company_id'];
                        $arrMemberInfo['company_id']        = $arrSavedMemberInfo['company_id'];
                        $arrMemberInfo['division_group_id'] = $divisionGroupId = $arrSavedMemberInfo['division_group_id'];
                    }
                }
            }

            if (empty($msgError) && empty($arrMemberInfo['arrRoles'])) {
                $msgError = $this->_tr->translate('Please select a role');
            }

            $booShowWithoutGroup = $this->_auth->isCurrentUserSuperadmin() || $this->_auth->isCurrentUserSuperadminMaskedAsAdmin();
            if (!$booShowWithoutGroup) {
                // Current user is system - don't show
                $arrDivisionGroupInfo = $this->_company->getCompanyDivisions()->getDivisionsGroupInfo($this->_auth->getCurrentUserDivisionGroupId());
                if (isset($arrDivisionGroupInfo['division_group_is_system'])) {
                    $booShowWithoutGroup = $arrDivisionGroupInfo['division_group_is_system'] == 'N';
                }
            }

            if (empty($msgError)) {
                if (!is_array($arrMemberInfo['arrRoles'])) {
                    $arrMemberInfo['arrRoles'] = array($arrMemberInfo['arrRoles']);
                }

                $booCorrect = false;
                $rolesList  = $this->_roles->getCompanyMemberRoles($companyId, $divisionGroupId, $booShowWithoutGroup);

                foreach ($rolesList as $role) {
                    if (in_array($role['role_id'], $arrMemberInfo['arrRoles'])) {
                        $booCorrect = true;
                        break;
                    }
                }

                if (!$booCorrect) {
                    $msgError = $this->_tr->translate('Incorrectly selected role');
                }
            }


            // If this is a company admin - he can not unassign his admin's role
            $currentMemberId = $this->_auth->getCurrentUserId();
            if (empty($msgError) && !empty($memberId) && !$booIsSuperAdmin && $currentMemberId == $memberId) {
                // Get updated list of assigned roles
                $select = (new Select())
                    ->from(['r' => 'acl_rules'])
                    ->columns(['rule_id'])
                    ->where(['r.rule_check_id' => ['manage-members', 'manage-members-edit']]);

                $arrEditMemberRulesIds = $this->_db2->fetchCol($select);

                // Check if some of these roles has access to (this) edit member page
                if (is_array($arrEditMemberRulesIds) && !empty($arrEditMemberRulesIds)) {
                    $countEditRules = count($arrEditMemberRulesIds);


                    $arrAssignedTextRolesIds = $this->_members->getTextRolesByIds($arrMemberInfo['arrRoles']);

                    $arrAssignedRulesIds = array();
                    if (is_array($arrAssignedTextRolesIds) && !empty($arrAssignedTextRolesIds)) {
                        $select = (new Select())
                            ->from(['a' => 'acl_role_access'])
                            ->columns(['rule_id'])
                            ->where(['a.role_id' => $arrAssignedTextRolesIds])
                            ->group('rule_id');

                        $arrAssignedRulesIds = $this->_db2->fetchCol($select);
                    }

                    $booCorrectRole      = false;
                    $arrCurrentIntersect = array_intersect($arrAssignedRulesIds, $arrEditMemberRulesIds);

                    if (count($arrCurrentIntersect) == $countEditRules) {
                        $booCorrectRole = true;
                    }

                    if (!$booCorrectRole) {
                        $msgError = $this->_tr->translate('Must be at least one assigned role with access rights to this page');
                    }
                }
            }

            // Deny to unassign admin's role if there are no more admins with access to "edit roles" rule
            if (empty($msgError) && !empty($memberId)) {
                $booCorrectRole = false;

                $arrAdminMembers = $this->_members->getMemberByRoleIds($this->_roles->getCompanyRoles($companyId, 0, false, true, array('admin')));
                if (is_array($arrAdminMembers) && !empty($arrAdminMembers)) {
                    $adminsCanEditRolesCounter = 0;
                    foreach ($arrAdminMembers as $adminMemberId) {
                        if ($adminMemberId != $memberId && $this->_acl->isMemberAllowed($adminMemberId, 'admin-roles-edit')) {
                            $adminsCanEditRolesCounter++;
                        }
                    }

                    if ($adminsCanEditRolesCounter > 0) {
                        $booCorrectRole = true;
                    }
                }

                if (!$booCorrectRole) {
                    $select = (new Select())
                        ->from(['r' => 'acl_rules'])
                        ->columns(['rule_id'])
                        ->where(['r.rule_check_id' => ['admin-roles-edit']]);

                    $arrEditRolesRulesIds = $this->_db2->fetchCol($select);

                    // Check if some of these roles has access to (this) edit member page
                    if (is_array($arrEditRolesRulesIds) && !empty($arrEditRolesRulesIds)) {
                        $countEditRules          = count($arrEditRolesRulesIds);
                        $arrAssignedTextRolesIds = $this->_members->getTextRolesByIds($arrMemberInfo['arrRoles']);

                        $arrAssignedRulesIds = array();
                        if (is_array($arrAssignedTextRolesIds) && !empty($arrAssignedTextRolesIds)) {
                            $select = (new Select())
                                ->from(['a' =>'acl_role_access'])
                                ->columns(['rule_id'])
                                ->where(['a.role_id' => $arrAssignedTextRolesIds])
                                ->group('rule_id');

                            $arrAssignedRulesIds = $this->_db2->fetchCol($select);
                        }

                        $booCorrectRole      = false;
                        $arrCurrentIntersect = array_intersect($arrAssignedRulesIds, $arrEditRolesRulesIds);

                        if (count($arrCurrentIntersect) == $countEditRules) {
                            $booCorrectRole = true;
                        }

                        if (!$booCorrectRole) {
                            $msgError = $this->_tr->translate('Must be at least one assigned role with access rights to edit roles');
                        }
                    }
                }
            }

            if (empty($arrMemberInfo['username']) && empty($msgError)) {
                $msgError = $this->_tr->translate('Please enter user name');
            }

            if (empty($msgError) && !Fields::validUserName($arrMemberInfo['username'])) {
                $msgError = $this->_tr->translate('Incorrect characters in username');
            }

            if (empty($msgError) && $this->_members->isUsernameAlreadyUsed($arrMemberInfo['username'], $memberId)) {
                $msgError = $this->_tr->translate('Duplicate username, please choose another');
            }

            if (empty($memberId) && empty($arrMemberInfo['password']) && empty($msgError)) {
                $msgError = $this->_tr->translate('Please enter password');
            }

            if (empty($msgError)) {
                $vevoAccountFromDb       = $this->_users->getUserInfo($memberId);
                $booEmptyVevoCredentials = empty($vevoAccountFromDb['vevo_login']) && empty($vevoAccountFromDb['vevo_password']);

                if ((empty($memberId) || $booEmptyVevoCredentials) && !empty($arrMemberInfo['vevo_password']) && empty($arrMemberInfo['vevo_login'])) {
                    $msgError = $this->_tr->translate('Please enter ImmiAccount login');
                }

                if (empty($msgError) && (empty($memberId) || $booEmptyVevoCredentials) && !empty($arrMemberInfo['vevo_login']) && empty($arrMemberInfo['vevo_password'])) {
                    $msgError = $this->_tr->translate('Please enter ImmiAccount password');
                }
            }

            $errMsg = array();
            if (empty($msgError) && (empty($memberId) || (!empty($arrMemberInfo['password']))) && !$this->_authHelper->isPasswordValid($arrMemberInfo['password'], $errMsg, $arrMemberInfo['username'], $memberId)) {
                $msgError = array_shift($errMsg); // get first error message
            }

            // Check email address only on user add
            $booCanUpdateEmail = $this->_members->canUpdateMemberEmailAddress($memberId);
            if (empty($msgError) && (empty($memberId) || $booCanUpdateEmail)) {
                if (empty($arrMemberInfo['emailAddress'])) {
                    $msgError = $this->_tr->translate('Please enter email address');
                }

                if (empty($msgError)) {
                    $validator = new EmailAddress();
                    if (!$validator->isValid($arrMemberInfo['emailAddress'])) {
                        // email is invalid; print the reasons
                        foreach ($validator->getMessages() as $message) {
                            $msgError .= "$message\n";
                        }
                    }
                }
            }

            if (empty($msgError) && (!empty($memberId) && !$booCanUpdateEmail)) {
                $arrOldMemberInfo              = $this->_members->getMemberInfo($memberId);
                $arrMemberInfo['emailAddress'] = $arrOldMemberInfo['emailAddress'];
            }

            if (empty($msgError) && empty($arrMemberInfo['fName'])) {
                $msgError = $this->_tr->translate('Please enter first name');
            }

            if (empty($msgError) && empty($arrMemberInfo['lName'])) {
                $msgError = $this->_tr->translate('Please enter last name');
            }

            if (empty($msgError) && (!is_numeric($arrMemberInfo['timeZone']) || $arrMemberInfo['timeZone'] < 0)) {
                $msgError = $this->_tr->translate('Please select time zone');
            }

            if (empty($msgError)) {
                if (empty($arrMemberInfo['country'])) {
                    $arrMemberInfo['country'] = 0;
                } else {
                    $arrCountries = $this->_country->getCountries(true);
                    if (!in_array($arrMemberInfo['country'], array_keys($arrCountries))) {
                        $arrMemberInfo['country'] = 0;
                    }
                }
            }

            if (empty($msgError)) {
                if ($booEnabledTimeTracker) {
                    $arrMemberInfo['time_tracker_enable']        = $arrMemberInfo['time_tracker_enable'] == 'Y' ? 'Y' : 'N';
                    $arrMemberInfo['time_tracker_disable_popup'] = $arrMemberInfo['time_tracker_disable_popup'] == 'Y' ? 'Y' : 'N';

                    $arrMemberInfo['time_tracker_rate'] = !is_numeric($arrMemberInfo['time_tracker_rate']) ? 0 : $arrMemberInfo['time_tracker_rate'];
                    if ($arrMemberInfo['time_tracker_rate'] < 0 || $arrMemberInfo['time_tracker_rate'] > 1000) {
                        $msgError = $this->_tr->translate('Incorrectly entered rate per hour (time tracker).');
                    }

                    if (empty($msgError) && !in_array($arrMemberInfo['time_tracker_round_up'], array(0, 15, 30, 60))) {
                        $msgError = $this->_tr->translate('Incorrectly selected round up option (time tracker).');
                    }
                } else {
                    $arrMemberInfo['time_tracker_enable']        = 'N';
                    $arrMemberInfo['time_tracker_disable_popup'] = 'N';
                    $arrMemberInfo['time_tracker_rate']          = null;
                    $arrMemberInfo['time_tracker_round_up']      = 0;
                }
            }

            if (empty($msgError)) {
                $arrDivisionsAccessTo       = is_array($arrMemberInfo['divisions_access_to']) ? $arrMemberInfo['divisions_access_to'] : array();
                $arrDivisionsResponsibleFor = is_array($arrMemberInfo['divisions_responsible_for']) ? $arrMemberInfo['divisions_responsible_for'] : array();

                // Office must be selected, so if it is empty - select all offices in ths group
                if (empty($arrDivisionsAccessTo)) {
                    $arrDivisionsAccessTo = $arrMemberInfo['divisions_access_to'] = $this->_company->getCompanyDivisions()->getDivisionsByGroupId($divisionGroupId);
                }

                if ($booIsSuperAdmin) {
                    $officeLabel = "Agent's Office";
                } else {
                    $officeLabel = $this->_company->getCurrentCompanyDefaultLabel('office');
                }

                foreach ($arrDivisionsResponsibleFor as $divisionResponsibleFor) {
                    if (!in_array($divisionResponsibleFor, $arrDivisionsAccessTo)) {
                        $msgError = $this->_tr->translate('Please select for the "' . $officeLabel . 's user is responsible for" field only those queues access is granted for.');
                    }
                }
            }

            if (!empty($msgError)) {
                $arrMemberInfo['error'] = $msgError;
                return $arrMemberInfo;
            }


            // Load user type in relation to role
            $arrMemberInfo['userType'] = $this->_members->getUserTypeByRolesIds($arrMemberInfo['arrRoles']);

            $arrUserInfo = array(
                'activationCode'        => $arrMemberInfo['activationCode'],
                'notes'                 => $arrMemberInfo['notes'],
                'address'               => $arrMemberInfo['address'],
                'timeZone'              => $arrMemberInfo['timeZone'],
                'city'                  => $arrMemberInfo['city'],
                'state'                 => $arrMemberInfo['state'],
                'country'               => $arrMemberInfo['country'],
                'zip'                   => $arrMemberInfo['zip'],
                'workPhone'             => $arrMemberInfo['workPhone'],
                'homePhone'             => $arrMemberInfo['homePhone'],
                'mobilePhone'           => $arrMemberInfo['mobilePhone'],
                'fax'                   => $arrMemberInfo['fax'],
                'user_is_rma'           => $arrMemberInfo['user_is_rma'] ? 'Y' : 'N',
                'user_migration_number' => $arrMemberInfo['user_is_rma'] ? $arrMemberInfo['user_migration_number'] : '',

                'time_tracker_enable'        => $arrMemberInfo['time_tracker_enable'],
                'time_tracker_disable_popup' => $arrMemberInfo['time_tracker_disable_popup'],
                'time_tracker_rate'          => $arrMemberInfo['time_tracker_rate'],
                'time_tracker_round_up'      => $arrMemberInfo['time_tracker_round_up']
            );

            $vevoAccountFromDb = $this->_users->getUserInfo($memberId);

            if (empty($arrMemberInfo['vevo_login'])) {
                //Delete login and password from DB for this account
                $arrUserInfo['vevo_login']    = '';
                $arrUserInfo['vevo_password'] = '';
            } else {
                $arrUserInfo['vevo_login'] = $arrMemberInfo['vevo_login'];
                if (!empty($vevoAccountFromDb['vevo_login'])) {
                    //Saved in DB
                    if (!empty($arrMemberInfo['vevo_password'])) {
                        $arrUserInfo['vevo_password'] = $this->_encryption->encode($arrMemberInfo['vevo_password']);
                    } else {
                        unset($arrMemberInfo['vevo_password']);
                    }
                } else {
                    if (empty($arrMemberInfo['vevo_password'])) {
                        unset($arrMemberInfo['vevo_password']);
                    } else {
                        $arrUserInfo['vevo_login']    = $arrMemberInfo['vevo_login'];
                        $arrUserInfo['vevo_password'] = $this->_encryption->encode($arrMemberInfo['vevo_password']);
                    }
                }
            }

            $arrResult = $arrMemberInfo;
            foreach ($arrUserInfo as $key => $val) {
                unset($arrMemberInfo[$key]);
            }

            $arrRoles = $arrMemberInfo['arrRoles'];
            unset($arrMemberInfo['arrRoles']);

            $arrDivisionsAccessTo = is_array($arrMemberInfo['divisions_access_to']) ? $arrMemberInfo['divisions_access_to'] : array();
            unset($arrMemberInfo['divisions_access_to']);

            $arrDivisionsResponsibleFor = is_array($arrMemberInfo['divisions_responsible_for']) ? $arrMemberInfo['divisions_responsible_for'] : array();
            unset($arrMemberInfo['divisions_responsible_for']);

            $arrDivisionsPullFrom = is_array($arrMemberInfo['divisions_pull_from']) ? $arrMemberInfo['divisions_pull_from'] : array();
            unset($arrMemberInfo['divisions_pull_from']);

            $arrDivisionsPushTo = is_array($arrMemberInfo['divisions_push_to']) ? $arrMemberInfo['divisions_push_to'] : array();
            unset($arrMemberInfo['divisions_push_to']);

            $arrVevoMembers = $arrMemberInfo['vevo_members'];
            unset($arrMemberInfo['vevo_members']);

            if (!empty($memberId)) {
                if (isset($arrMemberInfo['password'])) {
                    // Send confirmation email to this user
                    $this->_authHelper->triggerPasswordHasBeenChanged(array_merge($arrMemberInfo, array('member_id' => $memberId)));
                    $arrMemberInfo['password'] = $this->_encryption->hashPassword($arrMemberInfo['password']);
                }

                // On user edit - don't update email address, if user has access to Email tab
                unset($arrMemberInfo['email_signature']);
                unset($arrMemberInfo['emailsign']);
                if (!$booCanUpdateEmail) {
                    unset($arrMemberInfo['emailAddress']);
                }

                // Also save notes about the changes - for superadmin only
                if ($booIsSuperAdmin) {
                    $arrChangesData = $this->_company->createArrChangesData($arrMemberInfo, 'members', $companyId, $memberId);
                    $arrChangesData = array_merge($arrChangesData, $this->_company->createArrChangesData($arrUserInfo, 'users', $companyId, $memberId));
                    $arrChangesData = array_merge($arrChangesData, $this->_company->createArrChangesData($arrVevoMembers, 'members_vevo_mapping', $companyId, $memberId));
                    $this->_tickets->addTicketsWithCompanyChanges($arrChangesData, $companyId);
                }


                if (isset($arrMemberInfo['division_group_id']) && empty($arrMemberInfo['division_group_id'])) {
                    $arrMemberInfo['division_group_id'] = null;
                }

                $this->_db2->update('members', $arrMemberInfo, ['member_id' => $memberId]);

                $this->_users->updateUser($memberId, $arrUserInfo);

                $arrMemberInfo['division_group_id'] = $divisionGroupId;


                // Update divisions:
                $arrMemberDivisionsAccessTo    = array();
                $arrOldMemberDivisionsAccessTo = $this->_members->getMemberDivisions($memberId);
                $arrCompanyDivisions           = $this->_loadCompanyDivisions($memberId);

                $arrOfficesAccessToAdded = array();
                if (is_array($arrDivisionsAccessTo) && count($arrDivisionsAccessTo) > 0) {
                    foreach ($arrDivisionsAccessTo as $division) {
                        if ($this->isMemberDivisionInCompanyDivisions($division, $arrCompanyDivisions)) {
                            // Insert new records
                            if (!in_array($division, $arrOldMemberDivisionsAccessTo)) {
                                $arrOfficesAccessToAdded[] = $division;
                            }

                            $arrMemberDivisionsAccessTo[] = $division;
                        }
                    }

                    $this->_clients->updateApplicantOffices($memberId, $arrOfficesAccessToAdded, false, true, false, 'access_to', null, $divisionGroupId);
                }

                // Delete all member's divisions which are not in the company's divisions list
                $arrWhereDelete              = array();
                $arrWhereDelete['member_id'] = $memberId;
                $arrWhereDelete['type']      = "access_to";
                if (count($arrMemberDivisionsAccessTo) > 0) {
                    $arrWhereDelete[] = (new Where())->notIn('division_id', $arrMemberDivisionsAccessTo);
                }

                $this->_db2->delete('members_divisions', $arrWhereDelete);

                $arrResult['divisions_access_to'] = $arrMemberDivisionsAccessTo;

                $arrOfficesResponsibleForAdded       = array();
                $arrMemberDivisionsResponsibleFor    = array();
                $arrOldMemberDivisionsResponsibleFor = $this->_members->getMemberDivisions($memberId, 'responsible_for');

                if (is_array($arrDivisionsResponsibleFor) && count($arrDivisionsResponsibleFor) > 0) {
                    foreach ($arrDivisionsResponsibleFor as $division) {
                        if ($this->isMemberDivisionInCompanyDivisions($division, $arrCompanyDivisions)) {
                            // Insert new records
                            if (!in_array($division, $arrOldMemberDivisionsResponsibleFor)) {
                                $arrOfficesResponsibleForAdded[] = $division;
                            }

                            $arrMemberDivisionsResponsibleFor[] = $division;
                        }
                    }

                    $this->_clients->updateApplicantOffices($memberId, $arrOfficesResponsibleForAdded, false, true, false, 'responsible_for');
                }

                // Delete all member's divisions which are not in the company's divisions list
                $arrWhereDelete              = array();
                $arrWhereDelete['member_id'] = $memberId;
                $arrWhereDelete['type']      = "responsible_for";
                if (count($arrMemberDivisionsResponsibleFor) > 0) {
                    $arrWhereDelete[] = (new Where())->notIn('division_id', $arrMemberDivisionsResponsibleFor);
                }

                $this->_db2->delete('members_divisions', $arrWhereDelete);

                $arrResult['divisions_responsible_for'] = $arrMemberDivisionsResponsibleFor;

                $arrOfficesPullFromAdded       = array();
                $arrMemberDivisionsPullFrom    = array();
                $arrOldMemberDivisionsPullFrom = $this->_members->getMemberDivisions($memberId, 'pull_from');

                if (is_array($arrDivisionsPullFrom) && count($arrDivisionsPullFrom) > 0) {
                    foreach ($arrDivisionsPullFrom as $division) {
                        if ($this->isMemberDivisionInCompanyDivisions($division, $arrCompanyDivisions)) {
                            // Insert new records
                            if (!in_array($division, $arrOldMemberDivisionsPullFrom)) {
                                $arrOfficesPullFromAdded[] = $division;
                            }

                            $arrMemberDivisionsPullFrom[] = $division;
                        }
                    }

                    $this->_clients->updateApplicantOffices($memberId, $arrOfficesPullFromAdded, false, true, false, 'pull_from');
                }

                // Delete all member's divisions which are not in the company's divisions list
                $arrWhereDelete              = array();
                $arrWhereDelete['member_id'] = $memberId;
                $arrWhereDelete['type']      = "pull_from";
                if (count($arrMemberDivisionsPullFrom) > 0) {
                    $arrWhereDelete[] = (new Where())->notIn('division_id', $arrMemberDivisionsPullFrom);
                }

                $this->_db2->delete('members_divisions', $arrWhereDelete);

                $arrResult['divisions_pull_from'] = $arrMemberDivisionsPullFrom;

                $arrOfficesPushToAdded       = array();
                $arrMemberDivisionsPushTo    = array();
                $arrOldMemberDivisionsPushTo = $this->_members->getMemberDivisions($memberId, 'push_to');

                if (is_array($arrDivisionsPushTo) && count($arrDivisionsPushTo) > 0) {
                    foreach ($arrDivisionsPushTo as $division) {
                        if ($this->isMemberDivisionInCompanyDivisions($division, $arrCompanyDivisions)) {
                            // Insert new records
                            if (!in_array($division, $arrOldMemberDivisionsPushTo)) {
                                $arrOfficesPushToAdded[] = $division;
                            }

                            $arrMemberDivisionsPushTo[] = $division;
                        }
                    }

                    $this->_clients->updateApplicantOffices($memberId, $arrOfficesPushToAdded, false, true, false, 'push_to');
                }

                // Delete all member's divisions which are not in the company's divisions list
                $arrWhereDelete              = array();
                $arrWhereDelete['member_id'] = $memberId;
                $arrWhereDelete['type']      = "push_to";
                if (count($arrMemberDivisionsPushTo) > 0) {
                    $arrWhereDelete[] = (new Where())->notIn('division_id', $arrMemberDivisionsPushTo);
                }

                $this->_db2->delete('members_divisions', $arrWhereDelete);

                $arrResult['divisions_push_to'] = $arrMemberDivisionsPushTo;

                $this->_users->createOrUpdateLmsUser($memberId);

                // Log offices list changes
                $arrOfficesRemoved = array_diff($arrOldMemberDivisionsAccessTo, $arrDivisionsAccessTo);
                if (count($arrOfficesAccessToAdded) || count($arrOfficesRemoved)) {
                    $arrAllCompanyDivisions = $this->_company->getDivisions($arrMemberInfo['company_id'], $arrMemberInfo['division_group_id']);

                    $arrOfficeActions = array();

                    if (count($arrOfficesAccessToAdded)) {
                        $arrRolesNamesToAdd = array();
                        foreach ($arrAllCompanyDivisions as $arrOfficeInfo) {
                            if (in_array($arrOfficeInfo['division_id'], $arrOfficesAccessToAdded)) {
                                $arrRolesNamesToAdd[] = $arrOfficeInfo['name'];
                            }
                        }
                        $arrOfficeActions[] = sprintf('added: %s', implode(', ', $arrRolesNamesToAdd));
                    }

                    if (count($arrOfficesRemoved)) {
                        $arrRolesNamesToDelete = array();
                        foreach ($arrAllCompanyDivisions as $arrOfficeInfo) {
                            if (in_array($arrOfficeInfo['division_id'], $arrOfficesRemoved)) {
                                $arrRolesNamesToDelete[] = $arrOfficeInfo['name'];
                            }
                        }
                        $arrOfficeActions[] = sprintf('removed: %s', implode(', ', $arrRolesNamesToDelete));
                    }

                    // For <user> offices were added: Office1, Office2, removed: Office3, Office4 by <admin>
                    $strLogOfficeDescription = sprintf(
                        'For {2} %s %s by {1}',
                        count($arrOfficesRemoved) + count($arrOfficesAccessToAdded) == 1 ? 'office was' : 'offices were',
                        implode(' and ', $arrOfficeActions)
                    );

                    $arrLog = array(
                        'log_section'           => 'user',
                        'log_action'            => 'office_change',
                        'log_description'       => $strLogOfficeDescription,
                        'log_company_id'        => $companyId,
                        'log_created_by'        => $currentMemberId,
                        'log_action_applied_to' => $memberId,
                    );
                    $this->_accessLogs->saveLog($arrLog);
                }

                $this->_db2->delete('members_vevo_mapping', ['from_member_id' => (int)$memberId]);

                // Log this action
                $arrLog = array(
                    'log_section'           => 'user',
                    'log_action'            => 'edit',
                    'log_description'       => '{2} profile was updated by {1}',
                    'log_company_id'        => $companyId,
                    'log_created_by'        => $currentMemberId,
                    'log_action_applied_to' => $memberId,
                );
                $this->_accessLogs->saveLog($arrLog);
            } else {
                $userCreationResult = $this->_users->createUser($arrMemberInfo, $this->_auth->getCurrentUserCompanyTimezone(), $arrUserInfo);
                if (!$userCreationResult['error']) {
                    $arrResult['member_id'] = $memberId = $userCreationResult['member_id'];

                    // Create divisions
                    $arrMemberDivisionsAccessTo = array();
                    if (is_array($arrDivisionsAccessTo) && count($arrDivisionsAccessTo) > 0) {
                        $arrCompanyDivisions = $this->_loadCompanyDivisions($memberId);
                        foreach ($arrDivisionsAccessTo as $division) {
                            if ($this->isMemberDivisionInCompanyDivisions($division, $arrCompanyDivisions)) {
                                $arrMemberDivisionsAccessTo[] = $division;
                            }
                        }
                    }
                    $this->_clients->updateApplicantOffices($memberId, $arrMemberDivisionsAccessTo);
                    $arrResult['divisions_access_to'] = $arrMemberDivisionsAccessTo;

                    // Log this action
                    $arrLog = array(
                        'log_section'           => 'user',
                        'log_action'            => 'add',
                        'log_description'       => '{2} profile was created by {1}',
                        'log_company_id'        => $companyId,
                        'log_created_by'        => $currentMemberId,
                        'log_action_applied_to' => $memberId,
                    );
                    $this->_accessLogs->saveLog($arrLog);

                    $arrMemberDivisionsResponsibleFor = array();
                    if (is_array($arrDivisionsResponsibleFor) && count($arrDivisionsResponsibleFor) > 0) {
                        $arrCompanyDivisions = $this->_loadCompanyDivisions($memberId);
                        foreach ($arrDivisionsResponsibleFor as $division) {
                            if ($this->isMemberDivisionInCompanyDivisions($division, $arrCompanyDivisions)) {
                                $arrMemberDivisionsResponsibleFor[] = $division;
                            }
                        }
                    }
                    $this->_clients->updateApplicantOffices($memberId, $arrMemberDivisionsResponsibleFor, true, true, false, 'responsible_for');
                    $arrResult['divisions_responsible_for'] = $arrMemberDivisionsResponsibleFor;

                    $arrMemberDivisionsPullFrom = array();
                    if (is_array($arrDivisionsPullFrom) && count($arrDivisionsPullFrom) > 0) {
                        $arrCompanyDivisions = $this->_loadCompanyDivisions($memberId);
                        foreach ($arrDivisionsPullFrom as $division) {
                            if ($this->isMemberDivisionInCompanyDivisions($division, $arrCompanyDivisions)) {
                                $arrMemberDivisionsPullFrom[] = $division;
                            }
                        }
                    }
                    $this->_clients->updateApplicantOffices($memberId, $arrMemberDivisionsPullFrom, true, true, false, 'pull_from');
                    $arrResult['divisions_pull_from'] = $arrMemberDivisionsPullFrom;

                    $arrMemberDivisionsPushTo = array();
                    if (is_array($arrDivisionsPushTo) && count($arrDivisionsPushTo) > 0) {
                        $arrCompanyDivisions = $this->_loadCompanyDivisions($memberId);
                        foreach ($arrDivisionsPushTo as $division) {
                            if ($this->isMemberDivisionInCompanyDivisions($division, $arrCompanyDivisions)) {
                                $arrMemberDivisionsPushTo[] = $division;
                            }
                        }
                    }
                    $this->_clients->updateApplicantOffices($memberId, $arrMemberDivisionsPushTo, true, true, false, 'push_to');
                    $arrResult['divisions_push_to'] = $arrMemberDivisionsPushTo;

                    $this->_users->createOrUpdateLmsUser($memberId);
                } else {
                    $arrResult['error'] = 'Internal error';
                }
            }

            if (!empty($memberId)) {
                foreach ($arrVevoMembers as $toMemberId) {
                    $this->_db2->insert(
                        'members_vevo_mapping',
                        [
                            'from_member_id' => (int)$memberId,
                            'to_member_id'   => (int)$toMemberId
                        ]
                    );
                }
            }

            $arrResult['vevo_members'] = $arrVevoMembers;

            if (!empty($memberId)) {
                $this->_members->updateMemberRoles($memberId, $arrRoles);
            }

            $arrResult['error'] = empty($arrResult['error']) ? '' : $arrResult['error'];
        } catch (Exception $e) {
            $arrResult['error'] = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $arrResult;
    }

    private function _loadCompanyDivisions($memberId = 0)
    {
        $arrResult = array();

        if (!empty($memberId)) {
            $arrMemberInfo   = $this->_members->getMemberInfo($memberId);
            $companyId       = $arrMemberInfo['company_id'];
            $divisionGroupId = $arrMemberInfo['division_group_id'];
        } else {
            $companyId       = $this->_auth->getCurrentUserCompanyId();
            $divisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();
        }

        if ($this->_company->hasCompanyDivisions($companyId)) {
            $arrResult = $this->_company->getDivisions($companyId, $divisionGroupId);
        }

        return $arrResult;
    }

    private function isMemberDivisionInCompanyDivisions($memberDivision, $arrCompanyDivisions)
    {
        $booResult = false;
        if (is_array($arrCompanyDivisions)) {
            foreach ($arrCompanyDivisions as $companyDivisionInfo) {
                if ($companyDivisionInfo['division_id'] == $memberDivision) {
                    $booResult = true;
                    break;
                }
            }
        }

        return $booResult;
    }
    
    
    public function addAction ()
    {
        $view = new ViewModel();

        $title = $this->_tr->translate('New user');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $booIsCurrentMemberSuperAdmin = $this->_auth->isCurrentUserSuperadmin();
        $booCanEditMember             = $this->_acl->isAllowed('manage-members-edit');
                      
        if($booIsCurrentMemberSuperAdmin) {
            // Superadmin can't use this functionality - need login as company admin
            /** @var HelperPluginManager $viewHelperManager */
            $viewHelperManager = $this->_serviceManager->get('ViewHelperManager');
            /** @var MessageBox $messageBox */
            $messageBox = $viewHelperManager->get('messageBox');
            $strError = $messageBox('Please login as company admin to add a new user', true);
            $view->setTemplate('layout/plain');
            $view->setTerminal(true);
            $view->setVariable('content', $strError);
            return $view;
        } else {
            if($this->getRequest()->isPost()) {
                
                // Save received data
                $arrMemberInfo = $this->_CreateUpdateMember();

                if (empty($arrMemberInfo['error'])) {
                    // Create FTP folder
                    if (empty($arrMemberInfo['member_id'])) {
                        $arrMemberInfo['error'] = 'Internal error.';
                    } else {
                        $this->_files->mkNewMemberFolders(
                            $arrMemberInfo['member_id'],
                            $arrMemberInfo['company_id'],
                            $this->_company->isCompanyStorageLocationLocal($arrMemberInfo['company_id']),
                            false
                        );

                        // Go to 'Edit this member' page
                        return $this->redirect()->toUrl('/superadmin/manage-members/edit?' . http_build_query(['status' => 1, 'member_id' => $arrMemberInfo['member_id']]));
                    }
                }
            } else {
               $arrMemberInfo = $this->_loadMemberInfo();
            }

            //get countries
            $arrCountries = $this->_country->getCountries(true);
            // TODO: fix to array('' => '-- Please select --') + $arrCountries)
            $arrCountries = array_merge(array('-- Please select --'), $arrCountries);

            $view->setVariable('edit_member_id', 0);
            $view->setVariable('arrMemberInfo', $arrMemberInfo);
            $view->setVariable('CompaniesList', $this->_loadCompaniesList());

            $view->setVariable('arrTimeZones', $this->_settings->getWebmailTimeZones());

            if(!array_key_exists('error',$arrMemberInfo))
                $arrMemberInfo['error']='';

            $view->setVariable('edit_error_message', $arrMemberInfo['error']);
            $view->setVariable('confirmation_message', '');
            $view->setVariable('btnHeading', "Create new user");
            $view->setVariable('booIsCurrentMemberSuperAdmin', $booIsCurrentMemberSuperAdmin);
            $view->setVariable('booCanEditMember', $booCanEditMember);
            $view->setVariable('booCanAddMailAccount', false);
            $view->setVariable('passwordMinLength', $this->_settings->passwordMinLength);
            $view->setVariable('passwordMaxLength', $this->_settings->passwordMaxLength);
            $view->setVariable('arrCountry', $arrCountries);
            $view->setVariable('arrDivisions', $this->_loadCompanyDivisions());
            $view->setVariable('arrActiveUsers', $this->_users->getAssignedToUsers(false, $arrMemberInfo['company_id'], 0, true));
            $view->setVariable('booEmptyVevoCredentials', true);
        }

        $view->setVariable('officeLabel', $this->_company->getCurrentCompanyDefaultLabel('office'));

        $companyId       = $this->_auth->getCurrentUserCompanyId();
        $divisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();
        $booAgentRole    = $this->_auth->isCurrentUserAuthorizedAgent();

        $booShowWithoutGroup = $this->_auth->isCurrentUserSuperadminMaskedAsAdmin();
        if (!$booShowWithoutGroup) {
            // Current user is system - don't show
            $arrDivisionGroupInfo = $this->_company->getCompanyDivisions()->getDivisionsGroupInfo($this->_auth->getCurrentUserDivisionGroupId());
            if (isset($arrDivisionGroupInfo['division_group_is_system'])) {
                $booShowWithoutGroup = $arrDivisionGroupInfo['division_group_is_system'] == 'N';
            }
        }

        $arrRoles        = $this->_roles->getCompanyMemberRoles($companyId, $divisionGroupId, $booShowWithoutGroup);

        if ($booAgentRole) {
            foreach ($arrRoles as $key => $roleInfo) {
                $arrRoles[$key]['role_name'] = str_replace('Agent', '', $roleInfo['role_name'] ?? '');
            }
        }

        $view->setVariable('RolesList', $arrRoles);
        $view->setVariable('booAgentRole', $booAgentRole);
        $view->setVariable('booCanChangePassword', true);

        // oAuth settings
        $view->setVariable('booUseOAuth', !empty($this->_config['security']['oauth_login']['enabled']));
        $view->setVariable('oAuthSSOFieldLabel', $this->_config['security']['oauth_login']['single_sign_on_label']);
        $view->setVariable('oAuthGUIDFieldLabel', $this->_config['security']['oauth_login']['guid_label']);

        return $view;
    }

    public function editAction ()
    {
        $view = new ViewModel();

        $msgConfirmation = '';

        // Get member Id
        $member_id = $this->findParam('member_id');

        //validate member Id
        if (!is_numeric($member_id)) {
            return $this->redirect()->toUrl('/superadmin/manage-members/');
        }

        if (!$this->_members->hasCurrentMemberAccessToMember($member_id)) {
            $view->setTerminal(true);
            $view->setTemplate('layout/plain');
            $view->setVariables(
                [
                    'content' => 'Insufficient access rights'
                ],
                true
            );

            return $view;
        }

        $booEnabledTimeTracker = $this->_acl->isMemberAllowed($member_id, 'clients-time-tracker');
        $booCanEditMember      = $this->_acl->isAllowed('manage-members-edit');
        $booCanChangePassword  = $this->_acl->isAllowed('manage-members-change-password');
        $booShowEmailTab       = $this->_acl->isAllowed('mail-view');
        if ($this->getRequest()->isPost()) {
            //save received data
            $arrMemberInfo = $this->_CreateUpdateMember($member_id, $booEnabledTimeTracker);

            //result message
            if (empty($arrMemberInfo['error'])) {
                $msgConfirmation = "User's Info was successfully updated";

                // Load some info that was not provided in the post
                $arrSavedMemberInfo           = $this->_loadMemberInfo($member_id);
                $arrMemberInfo['lms_user_id'] = $arrSavedMemberInfo['lms_user_id'];
            }
        } else {
            $arrMemberInfo = $this->_loadMemberInfo($member_id);
        
            $status = $this->findParam('status');
            if(is_numeric($status)) {
                $msgConfirmation = "User was successfully created";
            }            
        }

        if (!array_key_exists('error', $arrMemberInfo)) {
            $arrMemberInfo['error'] = '';
        }
        
        $arrCountries = $this->_country->getCountries(true);
        // TODO: fix to array('' => '-- Please select --') + $arrCountries)
        $arrCountries = array_merge(array('-- Please select --'), $arrCountries);
        $view->setVariable('arrCountry', $arrCountries);

        $booIsCurrentMemberSuperAdmin = $this->_auth->isCurrentUserSuperadmin();

        $view->setVariable('userDoesntHaveEmailTab', $this->_members->canUpdateMemberEmailAddress($member_id));

        $strTitle = 'Edit User';
        if ($booIsCurrentMemberSuperAdmin) {
            if (empty($arrMemberInfo['company_id'])) {
                $companyName = 'Default company';
            } else {
                $arrCompanyInfo = $this->_company->getCompanyInfo($arrMemberInfo['company_id']);
                $companyName    = $arrCompanyInfo['companyName'];
            }

            $strTitle .= sprintf(' (%s)', $companyName);
        }

        $vevoAccountFromDb       = $this->_users->getUserInfo($member_id);
        $booEmptyVevoCredentials = empty($vevoAccountFromDb['vevo_login']) && empty($vevoAccountFromDb['vevo_password']);

        $this->layout()->setVariable('title', $strTitle);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($strTitle);
        $view->setVariable('edit_member_id', $member_id);
        $view->setVariable('arrMemberInfo', $arrMemberInfo);
        $view->setVariable('CompaniesList', $this->_loadCompaniesList());
        $view->setVariable('arrTimeZones', $this->_settings->getWebmailTimeZones());
        $view->setVariable('edit_error_message', $arrMemberInfo['error']);
        $view->setVariable('confirmation_message', $msgConfirmation);
        $view->setVariable('btnHeading', "Update User Account");
        $view->setVariable('booIsCurrentMemberSuperAdmin', $booIsCurrentMemberSuperAdmin);
        $view->setVariable('booShowLeftPanel', !$booIsCurrentMemberSuperAdmin);
        $view->setVariable('booCanEditMember', $booCanEditMember);
        $view->setVariable('booCanChangePassword', $booCanChangePassword);

        $booShowEnableLMSUser = !empty($arrMemberInfo['lms_user_id']) && ($booIsCurrentMemberSuperAdmin || $this->_auth->isCurrentUserSuperadminMaskedAsAdmin()) && $this->_users->isLmsEnabled(true);
        $view->setVariable('booShowEnableLMSUser', $booShowEnableLMSUser);

        $mailSettings         = $this->_config['mail'];
        $booCanAddMailAccount = $this->_members->hasMemberAccessToMail($member_id) && $mailSettings->enabled;
        $view->setVariable('booCanAddMailAccount', $booCanAddMailAccount);
        $view->setVariable('passwordMinLength', $this->_settings->passwordMinLength);
        $view->setVariable('passwordMaxLength', $this->_settings->passwordMaxLength);
        $view->setVariable('arrDivisions', $this->_loadCompanyDivisions($member_id));
        $view->setVariable('arrActiveUsers', $this->_users->getAssignedToUsers(false, $arrMemberInfo['company_id'], $member_id));
        $view->setVariable('booEmptyVevoCredentials', $booEmptyVevoCredentials);
        $view->setVariable('booShowEmailTab', $booShowEmailTab);

        // oAuth settings
        $view->setVariable('booUseOAuth', !empty($this->_config['security']['oauth_login']['enabled']));
        $view->setVariable('oAuthSSOFieldLabel', $this->_config['security']['oauth_login']['single_sign_on_label']);
        $view->setVariable('oAuthGUIDFieldLabel', $this->_config['security']['oauth_login']['guid_label']);

        $extJsAccounts = array();
        if ($this->_acl->isAllowed('mail-view')) {
            if ($this->_serviceManager->get('config')['mail']['enabled']) {
                $accounts = MailAccount::getAccounts($member_id);

                // Prepare array to special view
                foreach ($accounts as $acc) {
                    $extJsAccounts [] = array(
                        'account_id'       => $acc['id'],
                        'account_name'     => $acc['email'],
                        'signature'        => $acc['signature'],
                        'is_default'       => $acc['is_default'],
                        'auto_check'       => $acc['auto_check'],
                        'auto_check_every' => $acc['auto_check_every'],
                        'per_page'         => $acc['per_page'],
                        'inc_enabled'      => $acc['inc_enabled'],
                    );
                }
            }
        }
        $arrMailSettings = array(
            'hide_send_button' => (bool)$this->_serviceManager->get('config')['mail']['hide_send_button'],
            'accounts'         => $extJsAccounts
        );
        $view->setVariable('arrMailSettings', $arrMailSettings);

        $booShowBusinessHoursTab = $this->_acl->isAllowed('manage-members-business-hours');
        $arrBusinessHoursAccess  = array();
        if ($booShowBusinessHoursTab) {
            $arrBusinessHoursAccess = array(
                'view-workdays'   => $this->_acl->isAllowed('manage-members-business-hours-workdays-view'),
                'update-workdays' => $this->_acl->isAllowed('manage-members-business-hours-workdays-update'),
                'view-holidays'   => $this->_acl->isAllowed('manage-members-business-hours-holidays-view'),
                'add-holidays'    => $this->_acl->isAllowed('manage-members-business-hours-holidays-add'),
                'edit-holidays'   => $this->_acl->isAllowed('manage-members-business-hours-holidays-edit'),
                'delete-holidays' => $this->_acl->isAllowed('manage-members-business-hours-holidays-delete'),
            );
        }
        $view->setVariable('arrBusinessHoursAccess', $arrBusinessHoursAccess);
        $view->setVariable('booShowBusinessHoursTab', $booShowBusinessHoursTab);

        // Check Time Tracker options/access
        $view->setVariable('booEnabledTimeTracker', $booEnabledTimeTracker);

        if ($booIsCurrentMemberSuperAdmin) {
            $officeLabel = "Agent's Office";
        } else {
            $officeLabel = $this->_company->getCurrentCompanyDefaultLabel('office');
        }

        $view->setVariable('officeLabel', $officeLabel);

        $booShowWithoutGroup = $this->_auth->isCurrentUserSuperadmin() || $this->_auth->isCurrentUserSuperadminMaskedAsAdmin();
        if (!$booShowWithoutGroup) {
            // Current user is system - don't show
            $arrDivisionGroupInfo = $this->_company->getCompanyDivisions()->getDivisionsGroupInfo($this->_auth->getCurrentUserDivisionGroupId());
            if (isset($arrDivisionGroupInfo['division_group_is_system'])) {
                $booShowWithoutGroup = $arrDivisionGroupInfo['division_group_is_system'] == 'N';
            }
        }

        $booAgentRole = $this->_auth->isCurrentUserAuthorizedAgent();
        $arrRoles     = $this->_roles->getCompanyMemberRoles($arrMemberInfo['company_id'], $arrMemberInfo['division_group_id'], $booShowWithoutGroup);

        if ($booAgentRole) {
            foreach ($arrRoles as $key => $roleInfo) {
                $arrRoles[$key]['role_name'] = str_replace('Agent', '', $roleInfo['role_name'] ?? '');
            }
        }

        $view->setVariable('RolesList', $arrRoles);
        $view->setVariable('booAgentRole', $booAgentRole);

        return $view;
    }

    public function changePasswordAction()
    {
        if ($this->_acl->isAllowed('manage-members-change-password')) {
            $view = new JsonModel();

            // Get member Id
            $memberId   = (int)$this->findParam('member_id');
            $booSuccess = false;
            $msgError   = '';
            //validate member Id
            if (!is_numeric($memberId)) {
                return $this->redirect()->toUrl('/superadmin/manage-members/');
            }

            if (!$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $msgError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($msgError) && !$this->getRequest()->isPost()) {
                $msgError = $this->_tr->translate('Parameters were sent incorrectly.');
            }

            if (empty($msgError)) {
                $select = (new Select())
                    ->from('members')
                    ->where(['member_id' => (int)$memberId]);

                $result = $this->_db2->fetchRow($select);

                if (empty($result)) {
                    $msgError = $this->_tr->translate('There is no user with this id.');
                }
                if (empty($msgError)) {
                    $filter   = new StripTags();
                    $password = $filter->filter($this->findParam('password'));

                    if (!empty($password)) {
                        $arrMemberInfo['password']             = $password;
                        $arrMemberInfo['password_change_date'] = time();
                    } else {
                        $msgError = $this->_tr->translate('Please enter the password.');
                    }

                    if (empty($msgError) && isset($arrMemberInfo['password'])) {
                        $arrErrorsMsg = array();
                        if (!$this->_authHelper->isPasswordValid($arrMemberInfo['password'], $arrErrorsMsg)) {
                            $msgError = array_shift($arrErrorsMsg); // get first error message
                        }

                        if (empty($msgError)) {
                            $password = $arrMemberInfo['password'];

                            $arrMemberInfo['password']   = $this->_encryption->hashPassword($password);
                            $companyId                   = $this->_company->getMemberCompanyId($memberId);
                            $arrMemberInfo['company_id'] = $companyId;
                            $arrChangesData              = $this->_company->createArrChangesData($arrMemberInfo, 'members', $companyId, $memberId);
                            $this->_tickets->addTicketsWithCompanyChanges($arrChangesData, $companyId);

                            $this->_db2->update('members', $arrMemberInfo, ['member_id' => $memberId]);

                            // Send confirmation email to this user
                            $arrMemberInfo['password']     = $password;
                            $arrMemberInfo['emailAddress'] = $result['emailAddress'];
                            $this->_authHelper->triggerPasswordHasBeenChanged(array_merge($arrMemberInfo, array('member_id' => $memberId)));

                            $booSuccess = true;
                        }
                    }
                }
            }
            $view->setVariables(array('success' => $booSuccess, 'msgError' => $msgError));
        } else {
            $view = new ViewModel(
                [
                    'content' => 'Insufficient access rights'
                ]
            );
            $view->setTerminal(true);
            $view->setTemplate('layout/plain');
        }

        return $view;
    }

    public function checkIsUserExistsAction()
    {
        $view     = new JsonModel();
        $username = '';
        $strError = '';
        if ($this->_acl->isAllowed('manage-members-add')) {
            // Get username
            $username = $firstUserName = $this->findParam('username');
            $field    = $this->findParam('field');
            if (empty($username)) {
                $strError = 'Username is empty.';
            } else {
                if (Fields::validUserName($username)) {
                    $booIsUserExists = $this->_members->isUsernameAlreadyUsed($username);
                    $index           = 0;
                    // Find new username if previous exists only for Email field on blur event
                    if ($booIsUserExists && $field == 'email') {
                        while ($booIsUserExists) {
                            $index++;
                            $booIsUserExists = $this->_members->isUsernameAlreadyUsed($username . $index);
                        }
                        $username .= $index;
                    }

                    if ($booIsUserExists && $field == 'username' && $firstUserName != $username) {
                        // If triggered on change event of Username field;
                        $strError = 'Username is used by another user. Please choose another.';
                    }
                } else {
                    $strError = 'Invalid username.';
                }
            }
        } else {
            $strError = 'Insufficient access rights.';
        }

        return $view->setVariables(
            array(
                'strError' => $strError,
                'username' => $username
            )
        );
    }

    public function checkVevoAccountAction()
    {
        $view = new JsonModel();
        try {
            $filter = new StripTags();

            $login    = trim($filter->filter(Json::decode($this->findParam('login', ''), Json::TYPE_ARRAY)));
            $password = trim($filter->filter(Json::decode($this->findParam('password', ''), Json::TYPE_ARRAY)));

            $memberId = $this->findParam('member_id');

            if (!empty($memberId) && !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strMessage = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strMessage)) {
                $strMessage = $this->_membersVevo->checkMemberVevoCredentials($login, $password, $memberId);
            }
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strMessage),
            'message' => empty($strMessage) ? 'All credentials are correct' : $strMessage
        );

        return $view->setVariables($arrResult);
    }

    public function changeVevoCredentialsAction()
    {
        $view = new JsonModel();
        try {
            $filter = new StripTags();

            $login    = trim($filter->filter(Json::decode($this->findParam('login', ''), Json::TYPE_ARRAY)));
            $password = trim($filter->filter(Json::decode($this->findParam('password', ''), Json::TYPE_ARRAY)));

            $memberId = $this->findParam('member_id');

            if (!$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strMessage = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strMessage)) {
                $strMessage = $this->_membersVevo->changeMemberVevoCredentials($login, $password, $memberId);
            }
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strMessage),
            'message' => $strMessage
        );

        return $view->setVariables($arrResult);
    }

    public function enableLmsUserAction()
    {
        $strError = '';

        try {
            $memberId = $this->params()->fromPost('member_id', 0);
            if (!empty($memberId)) {
                $memberId = Json::decode($memberId);
            }

            if (empty($memberId) || !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $strError = $this->_users->enableLmsUserUpdate($memberId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        // Return json result
        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }
}
