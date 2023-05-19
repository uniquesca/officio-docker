<?php

namespace Superadmin\Controller;

use Clients\Service\Clients;
use Exception;
use Files\Service\Files;
use Laminas\Db\Sql\Select;
use Laminas\Filter\StripTags;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Common\Service\Settings;
use Officio\PagingManager;
use Officio\Common\Service\AccessLogs;
use Officio\Service\Company;
use Officio\Service\Roles;

/**
 * Roles Controller
 *
 * @author    Uniques Software Corp.
 */
class RolesController extends BaseController
{

    /** @var Roles */
    protected $_roles;

    /** @var AccessLogs */
    protected $_accessLogs;

    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_clients;

    /** @var Files */
    protected $_files;

    public function initAdditionalServices(array $services)
    {
        $this->_accessLogs = $services[AccessLogs::class];
        $this->_company = $services[Company::class];
        $this->_clients = $services[Clients::class];
        $this->_roles = $services[Roles::class];
        $this->_files = $services[Files::class];
    }

    private function _getCurrentRoleCompanyId($roleId)
    {
        $companyId = 0;

        // For superadmin we need load company id from the role's info
        if ($this->_auth->isCurrentUserSuperadmin() && !empty($roleId) && is_numeric($roleId)) {
            $arrRoleInfo = $this->_roles->getRoleInfo($roleId);
            if (is_array($arrRoleInfo) && array_key_exists('company_id', $arrRoleInfo)) {
                $companyId = $arrRoleInfo['company_id'];
            }
        }

        if (empty($companyId) || !is_numeric($companyId)) {
            $companyId = $this->_auth->getCurrentUserCompanyId();
        }

        return $companyId;
    }

    private function _getSubmittedFolderIds()
    {
        $arrPostData = $this->params()->fromPost();
        $arrFolders  = array();
        foreach ($arrPostData as $field => $access) {
            if (!is_array($field) && !is_object($field) && strpos($field, 'folder-') !== false) {
                $arrFolders[substr($field, 7)] = $access;
            }
        }

        return $arrFolders;
    }

    /**
     * Create/update specific role's info + related things (e.g. access to modules, folders, etc.)
     *
     * @param int $companyId
     * @param $divisionGroupId
     * @param int|string $roleId
     * @param array $arrPost
     * @param array $arrFolders
     * @param string $type
     * @param $booAgentRole
     * @return array
     */
    private function _createUpdateRoleDetails($companyId, $divisionGroupId, $roleId, $arrPost, $arrFolders, $type, $booAgentRole)
    {
        try {
            $filter = new StripTags();

            $msgError                = '';
            $booUpdate               = !empty($roleId);
            $companyAccessFieldsList = $this->_clients->getFields()->getGroupedFieldsByCompanyId($companyId);

            $arrViewFieldsIds        = [];
            $arrFullFieldsIds        = [];
            $arrGroupedViewFieldsIds = [];
            $arrGroupedFullFieldsIds = [];

            if (count($_POST, COUNT_RECURSIVE) >= ini_get('max_input_vars')) {
                $msgError = sprintf(
                    $this->_tr->translate('Form has too many fields, contact developers to increase limits (max %d are allowed).'),
                    ini_get('max_input_vars')
                );
            }

            $dependentsGroupsIds   = [];
            $dependentsGroupAccess = '';
            foreach ($companyAccessFieldsList as $arrAccessList) {
                foreach ($arrAccessList['fields'] as $groupId => $arrGroupInfo) {
                    if ($arrGroupInfo['name'] == 'Dependants') {
                        $dependentsGroupsIds[] = $groupId;

                        $fieldName = 'case_field_' . self::getDepenendentsGroupFieldId();
                        if (isset($arrPost[$fieldName])) {
                            if ($arrPost[$fieldName] === '1') {
                                $arrViewFieldsIds[]    = self::getDepenendentsGroupFieldId();
                                $dependentsGroupAccess = 'R';
                            } elseif ($arrPost[$fieldName] === '2') {
                                $arrFullFieldsIds[]    = self::getDepenendentsGroupFieldId();
                                $dependentsGroupAccess = 'F';
                            }
                        }
                    } else {
                         foreach ($arrGroupInfo['fields'] as $checkFieldList) {
                            $fieldName = 'case_field_' . $checkFieldList['field_id'];
                            if (isset($arrPost[$fieldName])) {
                                if ($arrPost[$fieldName] === '1') {
                                    $arrViewFieldsIds[] = $checkFieldList['field_id'];

                                    $arrGroupedViewFieldsIds[$arrAccessList['template_id']][] = $checkFieldList['field_id'];
                                } elseif ($arrPost[$fieldName] === '2') {
                                    $arrFullFieldsIds[] = $checkFieldList['field_id'];

                                    $arrGroupedFullFieldsIds[$arrAccessList['template_id']][] = $checkFieldList['field_id'];
                                }
                            }
                        }
                    }
                }
            }

            // Don't allow renaming Agent roles OR create another one with the same name
            $roleName = isset($arrPost['roleName']) ? $filter->filter($arrPost['roleName']) : '';
            if (empty($msgError) && $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled()) {
                if (!empty($roleId)) {
                    $savedRoleName = $this->_roles->getRoleName($roleId, $companyId, 0);
                    if (in_array($savedRoleName, array(Roles::$agentAdminRoleName, Roles::$agentUserRoleName, Roles::$agentSubagentRoleName))) {
                        $roleName = $savedRoleName;
                    }
                } elseif (in_array($roleName, array(Roles::$agentAdminRoleName, Roles::$agentUserRoleName, Roles::$agentSubagentRoleName))) {
                    $savedRoleId = $this->_roles->getCompanyRoleIdByNameAndType($companyId, $roleName, '');
                    if (!empty($savedRoleId)) {
                        $msgError = sprintf($this->_tr->translate('Only one role can be created with "%s" name'), $roleName);
                    }
                }
            }

            $arrRoleInfo = array(
                'error'             => '',
                'role_id'           => $roleId,
                'role_name'         => $roleName,
                'role_type'         => '',
                'role_parent_id'    => '',
                'company_id'        => $companyId,
                'division_group_id' => $divisionGroupId,

                // Module Level Access
                'arrRulesIds'      => $arrPost['add'],

                // Fields Level Access
                'arrViewFieldsIds' => empty($arrViewFieldsIds) || !is_array($arrViewFieldsIds) ? [] : $arrViewFieldsIds,
                'arrFullFieldsIds' => empty($arrFullFieldsIds) || !is_array($arrFullFieldsIds) ? [] : $arrFullFieldsIds,

                'arrGroupedViewFieldsIds' => empty($arrGroupedViewFieldsIds) || !is_array($arrGroupedViewFieldsIds) ? [] : $arrGroupedViewFieldsIds,
                'arrGroupedFullFieldsIds' => empty($arrGroupedFullFieldsIds) || !is_array($arrGroupedFullFieldsIds) ? [] : $arrGroupedFullFieldsIds,
            );

            $booIsCurrentMemberSuperAdmin = $this->_auth->isCurrentUserSuperadmin();
            if (empty($msgError) && !$this->_roles->hasAccessToRole($companyId, $divisionGroupId, $roleId, $booIsCurrentMemberSuperAdmin)) {
                $msgError = sprintf($this->_tr->translate('Insufficient access rights to role %d'), $roleId);
            }

            if (empty($msgError) && empty($arrRoleInfo['role_name']) && !$booUpdate) {
                $msgError = $this->_tr->translate('Role Title can not be empty');
            }

            // Check if current user can edit this role and required rules are checked
            $currentMemberId = $this->_auth->getCurrentUserId();
            if (empty($msgError) && $booUpdate && !$booIsCurrentMemberSuperAdmin) {

                // Company admin can edit only his company's roles
                $arrCompanyRolesIds = $this->_clients->getRoles(true, true);
                if (!is_array($arrCompanyRolesIds) || !in_array($roleId, $arrCompanyRolesIds)) {
                    $msgError = $this->_tr->translate('Insufficient access rights');
                }

                // Check if 'edit role' rule is checked in one of assigned role
                // If admin edit his own role - he can not turn off 'Edit role' functionality
                if (empty($msgError)) {

                    $arrCurrentMemberRolesIds = $this->_clients->getMemberRoles('', false);
                    if (is_array($arrCurrentMemberRolesIds)) {

                        // Get roles list for current user
                        $arrAssignedRolesIds     = array();
                        $arrAssignedTextRolesIds = array();
                        foreach ($arrCurrentMemberRolesIds as $arrAssignedRoleInfo) {
                            $arrAssignedRolesIds[] = $arrAssignedRoleInfo['role_id'];
                            if ($roleId != $arrAssignedRoleInfo['role_id']) {
                                $arrAssignedTextRolesIds[] = $arrAssignedRoleInfo['role_parent_id'];
                            }
                        }

                        if (in_array($roleId, $arrAssignedRolesIds)) {
                            // Current user want update his own role

                            // Get rules ids for 'edit role' task
                            if ($type == 'superadmin') {
                                $arrRules = array('manage-superadmin-roles', 'manage-superadmin-roles-edit');
                            } else {
                                $arrRules = array('admin-roles-view', 'admin-roles-edit', 'admin-roles-view-details');
                            }

                            $arrEditRoleRulesIds = $this->_roles->getRuleIdsByCheckIds($arrRules);

                            if (is_array($arrEditRoleRulesIds)) {
                                $countEditRules = count($arrEditRoleRulesIds);

                                // Check rules for current edit role
                                $booCorrectCurrentRole = false;
                                if (is_array($arrRoleInfo['arrRulesIds']) && !empty($arrRoleInfo['arrRulesIds'])) {
                                    $arrCurrentIntersect = array_intersect($arrRoleInfo['arrRulesIds'], $arrEditRoleRulesIds);
                                    if (count($arrCurrentIntersect) == $countEditRules) {
                                        $booCorrectCurrentRole = true;
                                    }
                                }


                                // Get rules list for all (except of this) roles
                                $arrAssignedRulesIds = $this->_roles->getAssignedRulesIds($arrAssignedTextRolesIds);

                                // Check rules for other previously saved roles
                                $booCorrectSavedRoles = false;
                                if (is_array($arrAssignedRulesIds) && !empty($arrAssignedRulesIds)) {
                                    $arrSavedIntersect = array_intersect($arrAssignedRulesIds, $arrEditRoleRulesIds);
                                    if (count($arrSavedIntersect) == $countEditRules) {
                                        $booCorrectSavedRoles = true;
                                    }
                                }


                                if (!$booCorrectCurrentRole && !$booCorrectSavedRoles) {
                                    // That's mean that 'edit role' rules were not checked in this role, nor in other saved
                                    $msgError = $this->_tr->translate('It is not possible to uncheck `Manage Roles` and `Edit Roles` access settings for own role.');
                                }
                            }
                        }
                    }
                }
            }

            if (!is_array($arrRoleInfo['arrRulesIds'])) {
                $arrRoleInfo['arrRulesIds'] = array();
            }

            // Check if 'Edit/Save Role' access right is checked, but one required field is not checked
            $arrMarkFields = array();
            if (empty($msgError)) {
                // Get rules ids for 'new client' rule
                $select = (new Select())
                    ->from(['r' => 'acl_rules'])
                    ->columns(['rule_id'])
                    ->where(['r.rule_check_id' => ['clients-profile-new']]);

                $arrEditProfileRulesIds = $this->_db2->fetchCol($select);

                if (is_array($arrEditProfileRulesIds)) {
                    $countEditProfileRules = count($arrEditProfileRulesIds);

                    // Check rules for current edit role
                    $booEditProfileRuleChecked = false;
                    if (is_array($arrRoleInfo['arrRulesIds']) && !empty($arrRoleInfo['arrRulesIds'])) {
                        $arrCurrentIntersect = array_intersect($arrRoleInfo['arrRulesIds'], $arrEditProfileRulesIds);
                        if (count($arrCurrentIntersect) == $countEditProfileRules) {
                            $booEditProfileRuleChecked = true;
                        }
                    }

                    $booError = false;
                    // Now check if all required fields has R&W access rights
                    if ($booEditProfileRuleChecked) {
                        // Load required fields only from 'assigned' group
                        $arrTemplates = $this->_clients->getCaseTemplates()->getTemplates($companyId);

                        foreach ($arrTemplates as $arrTemplateInfo) {
                            $caseTemplateId              = $arrTemplateInfo['case_template_id'];
                            $arrCompanyRequiredFieldsIds = $this->_clients->getFields()->getCompanyRequiredFields($companyId, $caseTemplateId, true);

                            if (is_array($arrCompanyRequiredFieldsIds) && !empty($arrCompanyRequiredFieldsIds)) {
                                if (is_array($arrRoleInfo['arrGroupedFullFieldsIds']) && array_key_exists($caseTemplateId, $arrRoleInfo['arrGroupedFullFieldsIds'])) {
                                    // There are 'required' fields
                                    $countRequiredFields = count($arrCompanyRequiredFieldsIds);
                                    $arrCurrentIntersect = array_intersect($arrRoleInfo['arrGroupedFullFieldsIds'][$caseTemplateId], $arrCompanyRequiredFieldsIds);
                                    if (count($arrCurrentIntersect) != $countRequiredFields) {
                                        // Not all required fields are checked
                                        $booError = true;

                                        $arrMarkFields = Settings::arrayUnique(array_merge($arrMarkFields, array_diff($arrCompanyRequiredFieldsIds, $arrCurrentIntersect)));
                                    }
                                } else {
                                    // There are no fields with 'full' access rights (selected by user)
                                    $booError      = true;
                                    $arrMarkFields = Settings::arrayUnique(array_merge($arrMarkFields, $arrCompanyRequiredFieldsIds));
                                }
                            }
                        }
                    }

                    if ($booError) {
                        $msgError = $this->_tr->translate(
                            'The highlighted fields below are required fields on Profile tab. ' .
                            'These fields have to be given Read & Write access if you want to allow the role ' .
                            'to save the profile or add new Case i.e. the "New/Edit Profile" access.'
                        );
                    }
                }
            }

            if (!empty($msgError)) {
                $arrRoleInfo['error']       = $msgError;
                $arrRoleInfo['mark_fields'] = $arrMarkFields;

                return $arrRoleInfo;
            }

            $booUpdateRoleType = true;
            $newRoleType = $this->_roles->getUserType($arrRoleInfo['arrRulesIds'], $arrRoleInfo['role_name'], $type);
            if ($booUpdate) {
                $select = (new Select())
                    ->from('acl_roles')
                    ->columns(['role_type'])
                    ->where(['role_id' => $roleId]);

                $oldRoleType = $this->_db2->fetchOne($select);

                // It is not possible to change the role type FROM the client or superadmin type
                // Also, user/admin cannot be changed TO the client or superadmin type
                if (in_array($oldRoleType, array('individual_client', 'employer_client', 'client', 'superadmin')) || (in_array($oldRoleType, array('user', 'crmuser', 'admin')) && in_array($newRoleType, array('individual_client', 'employer_client', 'client', 'superadmin')))) {
                    // Can not be updated
                    $booUpdateRoleType        = false;
                    $arrRoleInfo['role_type'] = $oldRoleType;
                }
            }

            if ($booUpdateRoleType) {
                $arrRoleInfo['role_type'] = $newRoleType;
            }


            if (!$booUpdate) {
                // Generate role type
                $arrRoleInfo['role_parent_id'] = $this->_roles->generateTextRoleId($arrRoleInfo['company_id']);
            }


            // 1. Register/Update Role
            $data = array('role_type' => $arrRoleInfo['role_type']);

            if (!empty($arrRoleInfo['role_name']) || !$booUpdate) {
                $data['role_name'] = $arrRoleInfo['role_name'];
            }

            if ($booUpdate) {
                $this->_db2->update('acl_roles', $data, ['role_id' => $roleId]);

                if ($booUpdateRoleType) {
                    // Recheck all members with this assigned role
                    // If usertype is same as calculated based on roles
                    $arrMembersWithThisRole = $this->_clients->getMemberByRoleIds(array($roleId));

                    if (is_array($arrMembersWithThisRole) && !empty($arrMembersWithThisRole)) {
                        // We need select all roles for these members and check if we need update user type
                        $select = (new Select())
                            ->from(['m' => 'members'])
                            ->columns(['member_id', 'userType', 'company_id'])
                            ->join(['mr' => 'members_roles'], 'm.member_id = mr.member_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER)
                            ->join(['r' => 'acl_roles'], 'mr.role_id = r.role_id', ['role_type'], Select::JOIN_LEFT_OUTER)
                            ->where(['m.member_id' => $arrMembersWithThisRole]);

                        $arrMemberRoles = $this->_db2->fetchAll($select);

                        if (is_array($arrMemberRoles) && !empty($arrMemberRoles)) {
                            $arrGroupedMembers = array();

                            $arrUserTypes = $this->_clients->getUserTypes();

                            // Group info by members
                            foreach ($arrMemberRoles as $arrMemberRoleInfo) {
                                if (is_array($arrUserTypes) && array_key_exists($arrMemberRoleInfo['userType'], $arrUserTypes)) {
                                    $arrGroupedMembers[$arrMemberRoleInfo['member_id']]['str_user_type']           = $arrUserTypes[$arrMemberRoleInfo['userType']];
                                    $arrGroupedMembers[$arrMemberRoleInfo['member_id']]['company_id']              = $arrMemberRoleInfo['company_id'];
                                    $arrGroupedMembers[$arrMemberRoleInfo['member_id']]['arrRoles'][]['role_type'] = $arrMemberRoleInfo['role_type'];
                                }
                            }

                            // Check each member user type
                            foreach ($arrGroupedMembers as $member_id => $arrMemberInfo) {
                                $correctUserType = $this->_clients->getUserTypeByRoles($arrMemberInfo['arrRoles']);
                                if ($correctUserType != $arrMemberInfo['str_user_type']) {
                                    // That's mean that member type is not same as calculated on roles
                                    // We need update to correct one

                                    $correctUserTypeId = $this->_clients->getUsertypeIdByRoleType($correctUserType);

                                    if (!empty($correctUserTypeId)) {
                                        $this->_db2->update('members', ['userType' => $correctUserTypeId], ['member_id' => $member_id]);

                                        // Also, if previously was 'admin' usertype and now is 'user' -
                                        // we need allow access to all offices for this member
                                        if ($arrMemberInfo['str_user_type'] == 'admin' && !$booAgentRole) {
                                            // Load all offices list for member's company
                                            $arrCompanyOffices = $this->_company->getDivisions($arrMemberInfo['company_id'], $arrMemberInfo['division_group_id'], true);

                                            // Now get already used offices for this member
                                            $arrMemberOffices = $this->_clients->getMemberDivisions($member_id);

                                            $arrNotCreatedOffices = array_diff($arrCompanyOffices, $arrMemberOffices);

                                            if (!empty($arrNotCreatedOffices)) {
                                                $this->_clients->updateApplicantOffices($member_id, $arrNotCreatedOffices, false);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // Load role text id for this role
                // (because during updating this field is disabled and is not received here)
                $arrNewRoleInfo                = $this->_roles->getRoleInfo($roleId);
                $arrRoleInfo['role_parent_id'] = $roleTextId = $arrNewRoleInfo['role_parent_id'];
                $arrRoleInfo['company_id']     = $arrNewRoleInfo['company_id'];
                $arrRoleInfo['role_name']      = $arrNewRoleInfo['role_name'];
            } else {
                $data['role_parent_id']    = $roleTextId = $arrRoleInfo['role_parent_id'];
                $data['company_id']        = $arrRoleInfo['company_id'];
                $data['division_group_id'] = empty($arrRoleInfo['division_group_id']) ? null : $arrRoleInfo['division_group_id'];

                // Default Values
                $data['role_visible']  = $type == 'superadmin' ? 0 : 1;
                $data['role_child_id'] = 'guest';
                $data['role_regTime']  = time();

                $arrRoleInfo['role_id'] = $roleId = $this->_db2->insert('acl_roles', $data);
            }

            $arrLog = array(
                'log_section'     => 'role',
                'log_action'      => $booUpdate ? 'edit' : 'add',
                'log_description' => $booUpdate ? sprintf('Role %s was updated by {1}', $arrRoleInfo['role_name']) : sprintf('Role %s was created by {1}', $arrRoleInfo['role_name']),
                'log_company_id'  => $companyId,
                'log_created_by'  => $currentMemberId,
            );

            $this->_accessLogs->saveLog($arrLog);

            // 2. Save Modules Level Access
            // Delete all access rights for this role
            $this->_db2->delete('acl_role_access', ['role_id' => $roleTextId]);

            if (is_array($arrRoleInfo['arrRulesIds']) && !empty($arrRoleInfo['arrRulesIds'])) {
                // Make sure that access to the "Admin" tab (for superadmin) is enabled,
                // if specific inner rules are enabled
                $arrSubRulesToCheck    = array('manage-superadmin-roles', 'manage-admin-user-view');
                $arrSubRulesIdsToCheck = $this->_roles->getRuleIdsByCheckIds($arrSubRulesToCheck);
                if (!empty($arrSubRulesIdsToCheck) && !empty(array_intersect($arrSubRulesIdsToCheck, $arrRoleInfo['arrRulesIds']))) {
                    $mustBeChecked = $this->_roles->getRuleIdsByCheckIds(array('admin-tab-view'));
                    if (!empty($mustBeChecked) && !in_array($mustBeChecked[0], $arrRoleInfo['arrRulesIds'])) {
                        $arrRoleInfo['arrRulesIds'][] = $mustBeChecked[0];
                    }
                }

                $select = (new Select())
                    ->from('acl_rule_details')
                    ->columns(['rule_id'])
                    ->group('rule_id');

                $arrUniqueIds = $this->_db2->fetchCol($select);

                // Insert new access rights
                $arrRulesInserted = [];
                foreach ($arrRoleInfo['arrRulesIds'] as $accessRuleId) {
                    if (in_array($accessRuleId, $arrUniqueIds) && !in_array($accessRuleId, $arrRulesInserted)) {
                        $arrInsert = array(
                            'role_id' => $roleTextId,
                            'rule_id' => $accessRuleId
                        );
                        $this->_db2->insert('acl_role_access', $arrInsert);

                        // Prevent duplicate tries
                        $arrRulesInserted[] = $accessRuleId;
                    }
                }
            }


            // 3. Save Fields Level Access
            // Delete all access rights for this role
            $this->_db2->delete('client_form_field_access', ['role_id' => $arrRoleInfo['role_id']]);

            // Prepare fields
            $arrFieldsValues         = array();
            $arrCaseFieldsWithAccess = array();
            foreach ($arrRoleInfo['arrGroupedViewFieldsIds'] as $caseTemplateId => $arrAccessFields) {
                foreach ($arrAccessFields as $fieldId) {
                    $arrFieldsValues[] = array(
                        'role_id'        => $arrRoleInfo['role_id'],
                        'field_id'       => $fieldId,
                        'client_type_id' => $caseTemplateId,
                        'status'         => 'R'
                    );

                    $arrCaseFieldsWithAccess[] = $fieldId;
                }
            }

            foreach ($arrRoleInfo['arrGroupedFullFieldsIds'] as $caseTemplateId => $arrAccessFields) {
                foreach ($arrAccessFields as $fieldId) {
                    $arrFieldsValues[] = array(
                        'role_id'        => $arrRoleInfo['role_id'],
                        'field_id'       => $fieldId,
                        'client_type_id' => $caseTemplateId,
                        'status'         => 'F'
                    );

                    $arrCaseFieldsWithAccess[] = $fieldId;
                }
            }

            // Insert new access rights (all at once)
            $this->_clients->getFields()->createFieldAccessRights($arrFieldsValues);


            // Save applicant access
            $booHasAccessToEmployers = $booIsCurrentMemberSuperAdmin ? $this->_company->isEmployersModuleEnabledToCompany($companyId) : $this->_auth->isCurrentMemberCompanyEmployerModuleEnabled();

            $arrTypes = $this->_clients->getAllowedMemberTypes($booHasAccessToEmployers, true);

            $arrAccessRights = array();
            foreach ($arrPost as $paramName => $paramValue) {
                if (preg_match('/^applicant_field_(\d+)_(\d+)_(\d+)$/i', $paramName, $regs) && in_array($regs[1], $arrTypes) && in_array($paramValue, array('F', 'R'))) {
                    $arrAccessRights[$regs[2]][$regs[3]] = $paramValue;
                }
            }

            $this->_clients->getApplicantFields()->updateRoleAccess($roleId, $arrAccessRights);

            // Save group access
            $arrGroupsPrepared   = array();
            $arrGroupsWithAccess = $this->_clients->getFields()->getGroupsByFieldsIds($arrCaseFieldsWithAccess);
            foreach ($arrGroupsWithAccess as $groupId) {
                $arrGroupsPrepared[$groupId] = 'F';
            }

            $dependentsGroupsIds = array_unique($dependentsGroupsIds);
            foreach ($dependentsGroupsIds as $groupId) {
                $arrGroupsPrepared[$groupId] = $dependentsGroupAccess;
            }

            $arrAssignedGroups = $this->_clients->getFields()->getCompanyGroups($companyId, true);
            foreach ($arrAssignedGroups as $arrGroupInfo) {
                if (!isset($arrGroupsPrepared[$arrGroupInfo['group_id']])) {
                    $arrGroupsPrepared[$arrGroupInfo['group_id']] = '';
                }
            }

            $this->_clients->getFields()->saveGroupsAccessForRoles([$roleId => $arrGroupsPrepared]);

            //save default folders
            $companyId            = empty($companyId) && $this->_auth->isCurrentUserSuperadmin() ? null : $companyId;
            $arrRoleInfo['error'] = $this->_files->getFolders()->getFolderAccess()->saveDefaultFoldersAccess($companyId, $roleId, $arrFolders);

            $companyId = empty($companyId) ? 0 : $companyId;
            $this->_roles->clearCompanyRolesCache($companyId);
        } catch (Exception $e) {
            $arrRoleInfo = array('error' => 'Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $arrRoleInfo;
    }

    /**
     * The default action - show roles list
     */
    public function indexAction()
    {
        $view = new ViewModel();

        $msgError = $msgConfirmation = '';
        $title    = $this->_tr->translate('Manage Roles');

        try {
            // Check if current user has access to manage superadmin roles to prevent managing with URL
            $type = $this->_roles->getRoleTypeByAccess($this->params()->fromQuery('type', 'company'));
            $view->setVariable('type', $type);

            $filter = new StripTags();

            $srchName = $filter->filter($this->params()->fromQuery('srchName'));
            $view->setVariable('srchName', $srchName);

            $srchStatus = $filter->filter($this->params()->fromQuery('srchStatus'));
            $view->setVariable('srchStatus', $srchStatus);

            $order_by = $filter->filter($this->params()->fromQuery('order_by'));
            $view->setVariable('order_by', $order_by);

            $order_by2 = $filter->filter($this->params()->fromQuery('order_by2'));
            $view->setVariable('order_by2', $order_by2);

            if ($type == 'superadmin') {
                $currentRole         = $this->_members->getMemberRoles('', false);
                $currentRoleParentId = $currentRole[0]['role_parent_id'];
                $view->setVariable('currentRoleParentId', $currentRoleParentId);
            }

            $booIsSuperAdmin = $this->_auth->isCurrentUserSuperadmin();
            $view->setVariable('booIsSuperAdmin', $booIsSuperAdmin);

            if ($type == 'superadmin') {
                $view->setVariable('booShowLeftPanel', $this->_acl->isAllowed('manage-superadmin-roles'));
            } else {
                $view->setVariable('booShowLeftPanel', $this->_acl->isAllowed('admin-roles-edit'));
            }

            $companyId              = $currentCompanyId = $this->_auth->getCurrentUserCompanyId();
            $companyDivisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();
            $currentMemberId        = $this->_auth->getCurrentUserId();

            if ($booIsSuperAdmin && is_numeric($companyId) && !empty($companyId)) {
                $companyId = $this->params()->fromQuery('company_id', 0);
                // Company ID can be also passed via route
                if (!$companyId) {
                    $companyId = $this->params('company_id', 0);
                }
                $companyDivisionGroupId = 0;
            }

            $view->setVariable('companyId', $companyId);

            $section = $this->params()->fromPost('section');
            if ($this->getRequest()->isPost() && $section == 'roles') {
                // Check received data
                $action      = $filter->filter($this->params()->fromPost('listingAction'));
                $arrRolesIds = $this->params()->fromPost('delIDs');

                // This code is for delete/activate/dactivate listings.
                if (empty($msgError) && in_array($action, array('delete', 'deactivate', 'activate'))) {
                    // firstly check whether user has selected any checkbox or not.
                    if (!is_array($arrRolesIds) || !count($arrRolesIds)) {
                        $msgError = $this->_tr->translate("Please select checkboxes to delete or to activate/deactivate any listing");
                    } else {
                        foreach ($arrRolesIds as $roleId) {
                            if (!$this->_roles->hasAccessToRole($currentCompanyId, $companyDivisionGroupId, $roleId, $booIsSuperAdmin)) {
                                $msgError = sprintf($this->_tr->translate('Insufficient access rights to role %d'), $roleId);
                                break;
                            }
                        }
                        // If this is Admin - check which roles can be edited/activated/disabled
                        if (empty($msgError)) {
                            // User can not delete/deactivate a role assigned to him
                            $arrCurrentMemberRoles = $this->_members->getMemberRoles($currentMemberId);

                            // Check if there are own roles selected
                            $arrIntersect = array_intersect($arrCurrentMemberRoles, $arrRolesIds);
                            if ($action == 'delete' && !empty($arrIntersect)) {
                                $msgError = $this->_tr->translate("It is not possible delete the role(s) if it is assigned to you.");
                            }

                            if (empty($msgError) && $action == 'deactivate' && !empty($arrIntersect)) {
                                $msgError = $this->_tr->translate("It is not possible deactivate the role(s) if it is assigned to you.");
                            }
                        }

                        // Don't allow to delete a role(s) if it is assigned to some user
                        if (empty($msgError) && $action == 'delete') {
                            $select = (new Select())
                                ->from('members_roles')
                                ->columns(['role_id'])
                                ->where(['role_id' => $arrRolesIds]);

                            $ids = $this->_db2->fetchCol($select);
                            if (count($ids)) {
                                $msgError = $this->_tr->translate("It is not possible delete the role(s) if it is assigned to user.");
                            }
                        }

                        // Make sure that all roles were correctly selected
                        $arrCheckedRoleIds = [];
                        if (empty($msgError)) {
                            $where = [];
                            $where['role_id'] = $arrRolesIds;

                            if ($type == 'superadmin') {
                                $where['role_type'] = ['superadmin'];
                            } else {
                                $where['role_type'] = ['user', 'admin'];
                            }

                            if ($companyId !== false) {
                                $where['company_id'] = $companyId;
                            }

                            if (!empty($companyDivisionGroupId)) {
                                $where['division_group_id'] = $companyDivisionGroupId;
                            }

                            $select = (new Select())
                                ->from('acl_roles')
                                ->columns(['role_id'])
                                ->where($where);

                            $arrCheckedRoleIds = $this->_db2->fetchCol($select);

                            if (count($arrCheckedRoleIds) == 0) {
                                $msgError = $this->_tr->translate("Incorrectly selected roles");
                            } else {
                                $arrRolesIds = $arrCheckedRoleIds;
                            }
                        }

                        if (empty($msgError)) {
                            $arrRoles = $this->_roles->getRoleInfo($arrRolesIds);

                            $arrRoleNames = array();
                            foreach ($arrRoles as $arrRoleInfo) {
                                $arrRoleNames[] = $arrRoleInfo['role_name'];
                            }

                            switch ($action) {
                                case 'delete':
                                    if ($type == 'superadmin') {
                                        $isAllowed = 'manage-superadmin-roles-delete';
                                    } else {
                                        $isAllowed = 'admin-roles-delete';
                                    }

                                    if ($this->_acl->isAllowed($isAllowed)) {
                                        $arrWhere = array();
                                        foreach ($arrRoles as $arrRoleInfo) {
                                            $arrWhere['role_id'] = $arrRoleInfo['role_parent_id'];
                                        }

                                        $this->_db2->delete('acl_role_access', $arrWhere);
                                        $this->_db2->delete('acl_roles', ['role_id' => $arrRolesIds]);
                                        // Delete Fields Access based on role too
                                        $this->_db2->delete('client_form_field_access', ['role_id' => $arrRolesIds]);

                                        // Delete group access
                                        $this->_db2->delete('client_form_group_access', ['role_id' => $arrRolesIds]);


                                        // Delete folder access
                                        $this->_files->getFolders()->getFolderAccess()->deleteByRoleIds($arrRolesIds);

                                        $msgConfirmation = $this->_tr->translate('Selected Roles were successfully deleted');

                                        $arrLog = array(
                                            'log_section'     => 'role',
                                            'log_action'      => 'delete',
                                            'log_description' => count($arrRoleNames) == 1 ? sprintf('Role was deleted by {1}: %s', $arrRoleNames[0]) : sprintf('Roles were deleted by {1}: %s', implode(', ', $arrRoleNames)),
                                            'log_company_id'  => $companyId,
                                            'log_created_by'  => $currentMemberId,
                                        );
                                        $this->_accessLogs->saveLog($arrLog);
                                    }
                                    break;

                                case 'activate':
                                    $this->_db2->update('acl_roles', ['role_status' => 1], ['role_id' => $arrCheckedRoleIds]);

                                    $msgConfirmation = $this->_tr->translate('Selected Roles were successfully activated');

                                    $arrLog = array(
                                        'log_section'     => 'role',
                                        'log_action'      => 'status_change',
                                        'log_description' => count($arrRoleNames) == 1 ? sprintf('Role was activated by {1}: %s', $arrRoleNames[0]) : sprintf('Roles were activated by {1}: %s', implode(', ', $arrRoleNames)),
                                        'log_company_id'  => $companyId,
                                        'log_created_by'  => $currentMemberId,
                                    );
                                    $this->_accessLogs->saveLog($arrLog);
                                    break;

                                case 'deactivate':
                                    $this->_db2->update('acl_roles', ['role_status' => 0], ['role_id' => $arrCheckedRoleIds]);

                                    $msgConfirmation = $this->_tr->translate('Selected Roles were successfully deactivated');

                                    $arrLog = array(
                                        'log_section'     => 'role',
                                        'log_action'      => 'status_change',
                                        'log_description' => count($arrRoleNames) == 1 ? sprintf('Role was deactivated by {1}: %s', $arrRoleNames[0]) : sprintf('Roles were deactivated by {1}: %s', implode(', ', $arrRoleNames)),
                                        'log_company_id'  => $companyId,
                                        'log_created_by'  => $currentMemberId,
                                    );
                                    $this->_accessLogs->saveLog($arrLog);
                                    break;

                                default:
                                    // Do nothing
                                    break;
                            }

                            $this->_roles->clearCompanyRolesCache($companyId);
                        }
                    }
                }
            }

            // define the pagesize
            $routeMatch         = $this->getEvent()->getRouteMatch();
            $routeName          = $routeMatch->getMatchedRouteName();
            $params             = $routeMatch->getParams();
            $baseUrl            = $this->url()->fromRoute($routeName, $params);
            $pagingSize         = 75;
            $paging             = new PagingManager($baseUrl, $pagingSize, PagingManager::DIGG_PAGING, 'page-roles');
            $booIsSuperAdmin    = $this->_auth->isCurrentUserSuperadmin();
            $loggedAsSuperadmin = $this->_auth->isCurrentUserSuperadminMaskedAsAdmin();

            /** @var array $arrRoles */
            list($arrRoles, $rolesCount) = $this->_roles->getCompanyRolesPaged(
                $companyId,
                $companyDivisionGroupId,
                $booIsSuperAdmin,
                $loggedAsSuperadmin,
                $type,
                $srchStatus,
                $srchName,
                $order_by,
                $order_by2,
                $paging->getOffset(),
                $paging->getStart()
            );
            $view->setVariable('booIsAuthorisedAgentsManagementEnabled', $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled());

            if (count($arrRoles) == 0) {
                $msgError = $this->_tr->translate('Sorry no records found.');
            } else {
                $view->setVariable('results', $arrRoles);
                $view->setVariable('sn', $paging->getStart());
                $view->setVariable('pagingStr', $paging->doPaging($rolesCount));
                $view->setVariable('totalRecords', $rolesCount);
            }

            // Show messages
            $status = $this->params()->fromQuery('status');
            if (is_numeric($status) && $status == '2') {
                $msgError = $this->_tr->translate('Insufficient access rights');
            }

            // Access rights
            if ($type == 'superadmin') {
                $view->setVariable('booCanEditRole', $this->_acl->isAllowed('manage-superadmin-roles-edit'));
                $view->setVariable('booCanAddRole', $this->_acl->isAllowed('manage-superadmin-roles-add'));
                $view->setVariable('booCanViewRoleDetails', $this->_acl->isAllowed('manage-superadmin-roles-edit'));
                $view->setVariable('booCanDeleteRole', $this->_acl->isAllowed('manage-superadmin-roles-delete'));
            } else {
                $view->setVariable('booCanEditRole', $this->_acl->isAllowed('admin-roles-edit'));
                $view->setVariable('booCanAddRole', $this->_acl->isAllowed('admin-roles-add'));
                $view->setVariable('booCanViewRoleDetails', $this->_acl->isAllowed('admin-roles-view-details'));
                $view->setVariable('booCanDeleteRole', $this->_acl->isAllowed('admin-roles-delete'));
            }

            if ($type == 'superadmin') {
                $title = $this->_tr->translate('Manage Super Admin Roles');
            } else {
                $title = $this->_tr->translate('Roles');
            }

        } catch (Exception $e) {
            $msgError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $view->setVariable('msgError', $msgError);
        $view->setVariable('msgConfirmation', $msgConfirmation);

        return $view;
    }

    /**
     * A tmp field name that will be used for the whole Dependants group
     * @return string
     */
    private static function getDepenendentsGroupFieldId()
    {
        return 'dependants';
    }

    /**
     * Load the list of case fields grouped by templates
     *
     * @param int $companyId
     * @return array
     */
    private function getCaseTypeFields($companyId)
    {
        $arrGroupedFieldsByCaseTypes = $this->_clients->getFields()->getGroupedFieldsByCompanyId($companyId);

        $arrAllCaseFieldsFields = array();
        foreach ($arrGroupedFieldsByCaseTypes as $arrCaseTypeInfo) {
            foreach ($arrCaseTypeInfo['fields'] as $arrGroupInfo) {
                $groupedName = $arrCaseTypeInfo['template_name'] . ' - ' . $arrGroupInfo['name'];
                if ($arrGroupInfo['name'] == 'Dependants') {
                    $fieldId = self::getDepenendentsGroupFieldId();

                    $arrAllCaseFieldsFields[$fieldId] = array(
                        'field_id'                 => $fieldId,
                        'field_label'              => $arrGroupInfo['name'],
                        'case_type_and_group_name' => array($groupedName),
                    );
                } else {
                    foreach ($arrGroupInfo['fields'] as $arrFieldInfo) {
                        if (isset($arrAllCaseFieldsFields[$arrFieldInfo['field_id']])) {
                            $arrAllGroupedNames   = $arrAllCaseFieldsFields[$arrFieldInfo['field_id']]['case_type_and_group_name'];
                            $arrAllGroupedNames[] = $groupedName;
                            sort($arrAllGroupedNames);

                            $arrAllCaseFieldsFields[$arrFieldInfo['field_id']]['case_type_and_group_name'] = $arrAllGroupedNames;
                        } else {
                            $arrAllCaseFieldsFields[$arrFieldInfo['field_id']] = array(
                                'field_id'                 => $arrFieldInfo['field_id'],
                                'field_label'              => $arrFieldInfo['label'],
                                'case_type_and_group_name' => array($groupedName),
                            );
                        }
                    }
                }
            }
        }

        $arrSortFieldLabels = array();
        foreach ($arrAllCaseFieldsFields as $key => $row) {
            $arrSortFieldLabels[$key] = strtolower($row['field_label'] ?? '');
        }
        array_multisort($arrSortFieldLabels, SORT_ASC, $arrAllCaseFieldsFields);

        return $arrAllCaseFieldsFields;
    }

    public function addAction()
    {
        $view = new ViewModel();

        $msgError            = '';
        $msgConfirmation     = '';
        $arrAssignedRulesIds = array();

        try {
            //Check if current user has access to manage superadmin roles to prevent managing with URL
            $type = $this->_roles->getRoleTypeByAccess($this->params()->fromQuery('type', 'company'));
            $view->setVariable('type', $type);

            $arrMarkFields = array();

            $currentCompanyId             = $this->_auth->getCurrentUserCompanyId();
            $divisionGroupId              = $this->_auth->getCurrentUserDivisionGroupId();
            $booIsCurrentMemberSuperAdmin = $this->_auth->isCurrentUserSuperadminMaskedAsAdmin();
            if ($this->getRequest()->isPost()) {
                // Check received data
                $roleId      = 0;
                $arrPostData = $this->params()->fromPost();

                $companyId       = $this->_getCurrentRoleCompanyId($roleId);
                $divisionGroupId = $this->_auth->isCurrentUserSuperadmin()
                    ? $this->_company->getCompanyDivisions()->getCompanyMainDivisionGroupId($companyId)
                    : $this->_auth->getCurrentUserDivisionGroupId();

                $arrRoleInfo = $this->_createUpdateRoleDetails(
                    $companyId,
                    $divisionGroupId,
                    $roleId,
                    $arrPostData,
                    $this->_getSubmittedFolderIds(),
                    $type,
                    false
                );

                if (empty($arrRoleInfo['error'])) {
                    // Go to 'Edit this role' page
                    return $this->redirect()->toUrl('/superadmin/roles/edit?' . http_build_query(['roleid' => $arrRoleInfo['role_id'], 'status' => 1, 'type' => $type]));
                }

                $roleId           = $arrRoleInfo['role_id'];
                $msgError         = $arrRoleInfo['error'];
                $arrMarkFields    = array_key_exists('mark_fields', $arrRoleInfo) ? $arrRoleInfo['mark_fields'] : array();
                $arrFoldersAccess = $this->_files->getFolders()->getSubmittedFoldersInfo($this->_getSubmittedFolderIds());
            } else {
                $cloneRoleId = (int)$this->params()->fromQuery('clone_roleid');
                if (!empty($cloneRoleId)) {
                    $roleId = $cloneRoleId;
                } else {
                    $roleId = 0;
                }

                // Load info about role
                $companyId         = $this->_getCurrentRoleCompanyId($roleId);
                // Load Fields Level Access
                $fieldsAccessRules = $this->_clients->getFields()->getRoleAllowedFields($companyId, $roleId);
                $arrRoleInfo       = $this->_roles->loadRoleInfoWithAccess($currentCompanyId, $divisionGroupId, $roleId, $booIsCurrentMemberSuperAdmin, $fieldsAccessRules);
                $arrFoldersAccess  = $this->_files->getFolders()->getDefaultFoldersByRoleId($this->_getCurrentRoleCompanyId($arrRoleInfo['role_id']), $arrRoleInfo['role_id']);

                // Reset some role's info
                $arrRoleInfo['role_id']        = 0;
                $arrRoleInfo['can_edit_admin'] = 0;
                $arrRoleInfo['role_name']      = empty($arrRoleInfo['role_name']) ? '' : 'Copy of ' . $arrRoleInfo['role_name'];
                $arrRoleInfo['role_type']      = '';
                $arrRoleInfo['role_parent_id'] = '';
                $arrRoleInfo['company_id']     = '';
            }
            $arrRoleInfo['company_id'] = $this->_getCurrentRoleCompanyId(0);

            $booIsCurrentMemberSuperAdmin = $this->_auth->isCurrentUserSuperadmin();
            if ($booIsCurrentMemberSuperAdmin) {
                $booHasAccessToEmployers = $this->_company->isEmployersModuleEnabledToCompany($arrRoleInfo['company_id']);
            } else {
                $booHasAccessToEmployers = $this->_auth->isCurrentMemberCompanyEmployerModuleEnabled();
            }

            $view->setVariable('booHasAccessToEmployers', $booHasAccessToEmployers);

            $view->setVariable('arrApplicantFields', $this->_clients->getApplicantFields()->getGroupedApplicantFields($arrRoleInfo['company_id'], $roleId, $booHasAccessToEmployers));

            $view->setVariable('edit_role_id', $arrRoleInfo['role_id']);


            if ($type == 'superadmin') {
                $view->setVariable('booCanEditRole', $this->_acl->isAllowed('manage-superadmin-roles-edit'));
            } else {
                $view->setVariable('booCanEditRole', $this->_acl->isAllowed('admin-roles-edit'));
            }

            $view->setVariable('arrRoleInfo', $arrRoleInfo);

            $arrAssignedRulesIds = $arrRoleInfo['arrRulesIds'];

            $view->setVariable('arrFolders', $arrFoldersAccess);
            $view->setVariable('arrMarkFields', $arrMarkFields);

            $view->setVariable('arrAllCaseFieldsFields', $this->getCaseTypeFields($arrRoleInfo['company_id']));
            $view->setVariable('officeLabel', $this->_company->getCompanyDefaultLabel($arrRoleInfo['company_id'], 'office'));
            $view->setVariable('taLabel', $this->_company->getCompanyDefaultLabel($arrRoleInfo['company_id'], 'trust_account'));
            $view->setVariable('booEditMyself', false);

            $superadminOnly = !($type == 'company' || !$this->_auth->isCurrentUserSuperadmin());
            $view->setVariable('arrRules', $this->_roles->getRulesByCompanyId($arrRoleInfo['company_id'], $superadminOnly));

            $view->setVariable('booIsAuthorisedAgentsManagementEnabled', $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled());

        } catch (Exception $e) {
            $msgError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $title = $this->_tr->translate('Add new role');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);
        $view->setVariable('btnHeading', $title);
        $view->setVariable('booCanRenameRole', true);
        $view->setVariable('edit_error_message', $msgError);
        $view->setVariable('confirmation_message', $msgConfirmation);
        $view->setVariable('caseTypeFieldLabel', $this->_company->getCurrentCompanyDefaultLabel('case_type'));
        $view->setVariable('caseTypeFieldLabelPlural', $this->_company->getCurrentCompanyDefaultLabel('case_type', true));
        $view->setVariable('edit_assigned_rules', $arrAssignedRulesIds);

        return $view;
    }

    public function editAction()
    {
        $view = new ViewModel();

        $msgError            = '';
        $msgConfirmation     = '';
        $booCanRenameRole    = false;
        $strTitle            = 'Edit role';
        $arrAssignedRulesIds = [];

        try {
            $arrMarkFields = array();
            //Check if current user has access to manage superadmin roles to prevent managing with URL
            $type = $this->_roles->getRoleTypeByAccess($this->params()->fromQuery('type', 'company'));
            $view->setVariable('type', $type);
            $roleId           = $this->params()->fromQuery('roleid');
            $currentCompanyId = $this->_auth->getCurrentUserCompanyId();
            $divisionGroupId  = $this->_auth->getCurrentUserDivisionGroupId();
            $isSuperadmin     = $this->_auth->isCurrentUserSuperadmin();

            $booIsCurrentMemberSuperAdmin = $this->_auth->isCurrentUserSuperadmin();
            if ($this->getRequest()->isPost()) {
                // Check if current user can edit this role
                if (empty($roleId) || !$this->_roles->hasAccessToRole($currentCompanyId, $divisionGroupId, $roleId, $isSuperadmin)) {
                    return $this->redirect()->toUrl('/superadmin/roles/index?' . http_build_query(['status' => 2, 'type' => $type]));
                }

                // Save received data
                $arrPostData = $this->params()->fromPost();


                $companyId     = $this->_getCurrentRoleCompanyId($roleId);
                $savedRoleName = $this->_roles->getRoleName($roleId, $companyId, 0);
                if (in_array($savedRoleName, array(Roles::$agentAdminRoleName, Roles::$agentUserRoleName, Roles::$agentSubagentRoleName))) {
                    $divisionGroupId = null;
                    $booAgentRole    = true;
                } else {
                    $divisionGroupId = $this->_auth->isCurrentUserSuperadmin() ? $this->_company->getCompanyDivisions()->getCompanyMainDivisionGroupId($companyId) : $this->_auth->getCurrentUserDivisionGroupId();
                    $booAgentRole    = false;
                }

                $arrRoleInfo = $this->_createUpdateRoleDetails(
                    $companyId,
                    $divisionGroupId,
                    $roleId,
                    $arrPostData,
                    $this->_getSubmittedFolderIds(),
                    $type,
                    $booAgentRole
                );

                $roleId         = $arrRoleInfo['role_id'];
                $msgError      = $arrRoleInfo['error'];
                $arrMarkFields = array_key_exists('mark_fields', $arrRoleInfo) ? $arrRoleInfo['mark_fields'] : array();
                if (empty($msgError)) {
                    $msgConfirmation = $this->_tr->translate('Role was successfully updated');
                }
            } else {
                if (empty($roleId)) {
                    return $this->redirect()->toUrl('/superadmin/roles/index?' . http_build_query(['type' => $type]));
                } elseif (!$this->_roles->hasAccessToRole($currentCompanyId, $divisionGroupId, $roleId, $isSuperadmin)) {
                    return $this->redirect()->toUrl('/superadmin/roles/index?' . http_build_query(['status' => 2, 'type' => $type]));
                }

                $companyId         = $this->_getCurrentRoleCompanyId($roleId);
                // Load Fields Level Access
                $fieldsAccessRules = $this->_clients->getFields()->getRoleAllowedFields($companyId, $roleId);
                $arrRoleInfo       = $this->_roles->loadRoleInfoWithAccess($currentCompanyId, $divisionGroupId, $roleId, $isSuperadmin, $fieldsAccessRules);

                // Simulate access to the Dependants group as a field
                $dependantsAccess = $this->_clients->getFields()->getAccessToDependants(0, $companyId, $roleId);
                if ($dependantsAccess == 'F') {
                    $arrRoleInfo['arrFullFieldsIds'][] = self::getDepenendentsGroupFieldId();
                } elseif ($dependantsAccess == 'R') {
                    $arrRoleInfo['arrViewFieldsIds'][] = self::getDepenendentsGroupFieldId();
                }

                $status = $this->params()->fromQuery('status');
                if (is_numeric($status) && $status == '1') {
                    $msgConfirmation = $this->_tr->translate('Role was successfully created');
                }
            }

            $arrFoldersAccess = $this->_files->getFolders()->getDefaultFoldersByRoleId($this->_getCurrentRoleCompanyId($roleId), $roleId);
            if ($booIsCurrentMemberSuperAdmin) {
                if (empty($arrRoleInfo['company_id'])) {
                    $companyName             = 'Default company';
                    $booHasAccessToEmployers = true;
                } else {
                    $arrCompanyInfo          = $this->_company->getCompanyInfo($arrRoleInfo['company_id']);
                    $companyName             = $arrCompanyInfo['companyName'];
                    $booHasAccessToEmployers = $this->_company->isEmployersModuleEnabledToCompany($arrRoleInfo['company_id']);
                }

                $strTitle .= sprintf(' (%s)', $companyName);
            } else {
                $booHasAccessToEmployers = $this->_auth->isCurrentMemberCompanyEmployerModuleEnabled();
            }

            $view->setVariable('arrApplicantFields', $this->_clients->getApplicantFields()->getGroupedApplicantFields($arrRoleInfo['company_id'], $roleId, $booHasAccessToEmployers));

            $view->setVariable('edit_role_id', $roleId);
            $view->setVariable('arrRoleInfo', $arrRoleInfo);

            $arrAssignedRulesIds = $arrRoleInfo['arrRulesIds'];

            $view->setVariable('role_type', $arrRoleInfo['role_type']);

            $booCanRenameRole = !in_array($arrRoleInfo['role_type'], array('individual_client', 'employer_client', 'client'));
            if ($booCanRenameRole && $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled() && in_array($arrRoleInfo['role_name'], array(Roles::$agentAdminRoleName, Roles::$agentUserRoleName, Roles::$agentSubagentRoleName))) {
                $booCanRenameRole = false;
            }

            if ($type == 'superadmin') {
                $view->setVariable('booCanEditRole', $this->_acl->isAllowed('manage-superadmin-roles-edit'));
                $view->setVariable('booShowLeftPanel', $this->_acl->isAllowed('manage-superadmin-roles-edit'));
            } else {
                $view->setVariable('booCanEditRole', $this->_acl->isAllowed('admin-roles-edit'));
                $view->setVariable('booShowLeftPanel', $this->_acl->isAllowed('admin-roles-edit'));
            }
            $view->setVariable('arrFolders', $arrFoldersAccess);
            $view->setVariable('arrMarkFields', $arrMarkFields);

            $view->setVariable('arrAllCaseFieldsFields', $this->getCaseTypeFields($arrRoleInfo['company_id']));

            $superadminOnly = !($type == 'company' || !$this->_auth->isCurrentUserSuperadmin());
            $view->setVariable('arrRules', $this->_roles->getRulesByCompanyId($arrRoleInfo['company_id'], $superadminOnly));
            $view->setVariable('officeLabel', $this->_company->getCompanyDefaultLabel($arrRoleInfo['company_id'], 'office'));
            $view->setVariable('taLabel', $this->_company->getCompanyDefaultLabel($arrRoleInfo['company_id'], 'trust_account'));
            $view->setVariable('booIsAuthorisedAgentsManagementEnabled', $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled());

            $booEditMyself = false;
            $currentRoleIds = $this->_members->getMemberRoles();
            foreach ($currentRoleIds as $id) {
                if ($id == $roleId) {
                    $booEditMyself = true;
                    break;
                }
            }
            $view->setVariable('booEditMyself', $booEditMyself);
        } catch (Exception $e) {
            $msgError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('booCanRenameRole', $booCanRenameRole);
        $view->setVariable('edit_error_message', $msgError);
        $view->setVariable('confirmation_message', $msgConfirmation);
        $view->setVariable('caseTypeFieldLabel', $this->_company->getCurrentCompanyDefaultLabel('case_type'));
        $view->setVariable('caseTypeFieldLabelPlural', $this->_company->getCurrentCompanyDefaultLabel('case_type', true));
        $view->setVariable('edit_assigned_rules', $arrAssignedRulesIds);
        $view->setVariable('btnHeading', $this->_tr->translate('Update Role'));

        $this->layout()->setVariable('title', $strTitle);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($strTitle);

        return $view;
    }

    public function deleteAction()
    {
        //Check if current user has access to manage superadmin roles to prevent managing with URL
        $type = $this->_roles->getRoleTypeByAccess($this->params()->fromQuery('type', 'company'));
        // This is a stub, real delete action is in index controller
        return $this->redirect()->toUrl('/superadmin/roles/index?' . http_build_query(['type' => $type]));
    }

    public function checkRoleAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        $booValid = false;

        try {

            $checkRoleId = $this->findParam('roleId');

            $arrRoles = $this->_roles->loadParentRoles();
            if (!in_array($checkRoleId, $arrRoles)) {
                $booValid = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('content', $booValid ? 'true' : 'false');
        return $view;
    }
}
