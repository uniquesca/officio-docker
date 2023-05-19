<?php

namespace Officio\Service;

use Clients\Service\Clients;
use Exception;
use Files\Service\Files;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Officio\Common\Service\BaseService;
use Officio\Common\ServiceContainerHolder;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Roles extends BaseService
{

    use ServiceContainerHolder;

    /** @var Files */
    protected $_files;

    // @Note: if must be changed - please rename all these already created roles
    public static $agentAdminRoleName = 'Agent Admin';
    public static $agentUserRoleName = 'Agent Support Staff';
    public static $agentSubagentRoleName = 'Agent Subagent';

    public function initAdditionalServices(array $services)
    {
        $this->_files = $services[Files::class];
    }


    /**
     * Load default roles list
     *
     * @param bool $booAllCols true to load all columns
     * @param bool $booAdminAndUserOnly true to load admin and user roles only
     * @return array roles
     */
    public function getDefaultRoles($booAllCols = false, $booAdminAndUserOnly = true)
    {
        $arrCols = $booAllCols ? [Select::SQL_STAR] : ['role_id', 'role_name', 'role_type'];
        $arrWhere = [];
        $arrWhere['role_visible'] = 1;
        $arrWhere['company_id'] = 0;

        if ($booAdminAndUserOnly) {
            $arrWhere['role_type'] = array('admin', 'user');
        }

        $select = (new Select())
            ->from('acl_roles')
            ->columns($arrCols)
            ->where($arrWhere)
            ->order('role_type');

        return $this->_db2->fetchAll($select);
    }


    /**
     * Load array of rule ids of the Client's Time Tracker controller
     *
     * @return array
     */
    public function getTimeTrackerRules()
    {
        return $this->getModuleRules('clients', 'time-tracker');
    }

    /**
     * Load array of rule ids of the Marketplace
     *
     * @param string $rulesType
     * @return array
     */
    public function getMarketplaceRules($rulesType = 'all')
    {
        switch ($rulesType) {
            case 'manage':
                $arrRules = array('manage-marketplace');
                break;

            case 'view':
                $arrRules = array('marketplace-view');
                break;

            case 'all':
            default:
                $arrRules = array('manage-marketplace', 'marketplace-view');
                break;
        }

        $select = (new Select())
            ->from('acl_rules')
            ->columns(['rule_id'])
            ->where(['rule_check_id' => $arrRules]);

        $startRuleId = $this->_db2->fetchCol($select);

        $arrRuleIds = array();
        if (!empty($startRuleId)) {
            $arrRuleIds = $this->_getSubRules($startRuleId);
        }

        return $arrRuleIds;
    }

    /**
     * Load rules ids (and their sub rules) for specific "rule check id"
     *
     * @param string $ruleCheckId
     * @return array
     */
    private function getRulesByCheckRuleId($ruleCheckId)
    {
        $select = (new Select())
            ->from('acl_rules')
            ->columns(['rule_id'])
            ->where(['rule_check_id' => $ruleCheckId]);

        $startRuleId = $this->_db2->fetchOne($select);

        $arrRuleIds = array();
        if (!empty($startRuleId)) {
            $arrRuleIds = $this->_getSubRules($startRuleId);
        }

        return $arrRuleIds;
    }

    /**
     * Load rule ids for Allow Export functionality
     *
     * @return array rule id
     */
    public function getAllowExportRules()
    {
        return $this->getRulesByCheckRuleId('allow-export');
    }


    /**
     * Load rule ids for Allow Import functionality
     * @param bool $booImportBcpnp
     * @return array rule id
     */
    public function getAllowImportRules($booImportBcpnp = false)
    {
        $ruleCheckId = $booImportBcpnp ? 'import-bcpnp' : 'import-clients-view';

        $select = (new Select())
            ->from('acl_rules')
            ->columns(['rule_id'])
            ->where(['rule_check_id' => $ruleCheckId]);

        return [$this->_db2->fetchOne($select)];
    }

    /**
     * Load array of rule ids for specific module/controller
     *
     * @param string $module
     * @param string $resource
     * @return array
     */
    public function getModuleRules($module, $resource)
    {
        $cacheId = str_replace('-', '_', 'acl_' . $module . '_' . $resource . '_rule_ids');

        if (!($arrIds = $this->_cache->getItem($cacheId))) {
            $select = (new Select())
                ->from('acl_rule_details')
                ->columns(['rule_id'])
                ->where([
                    'module_id'   => $module,
                    'resource_id' => $resource
                ])
                ->group('rule_id');

            $arrIds = $this->_db2->fetchCol($select);

            $this->_cache->setItem($cacheId, $arrIds);
        }

        return $arrIds;
    }

    /**
     * Load information about specific role(s)
     *
     * @param int|array $roleId
     * @return array
     */
    public function getRoleInfo($roleId)
    {
        $select = (new Select())
            ->from('acl_roles')
            ->where(['role_id' => $roleId]);

        return is_array($roleId) ? $this->_db2->fetchAll($select) : $this->_db2->fetchRow($select);
    }

    /**
     * Load detailed information about specific role
     *
     * @param int $companyId
     * @param $divisionGroupId
     * @param int $roleId
     * @param $booIsSuperadmin
     * @param $fieldAccessRules
     * @return array
     */
    public function loadRoleInfoWithAccess($companyId, $divisionGroupId, $roleId, $booIsSuperadmin, $fieldAccessRules)
    {
        $arrRoleInfo = array(
            'role_id'          => 0,
            'can_edit_admin'   => 0,
            'role_name'        => '',
            'role_type'        => '',
            'role_parent_id'   => '',
            'company_id'       => $companyId,

            // Module Level Access
            'arrRulesIds'      => array(),

            // Fields Level Access
            'arrViewFieldsIds' => array(),
            'arrFullFieldsIds' => array(),
        );

        if (!empty($roleId) && $this->hasAccessToRole($companyId, $divisionGroupId, $roleId, $booIsSuperadmin)) {
            // Load Role information
            $select = (new Select())
                ->from(array('r' => 'acl_roles'))
                ->where(['r.role_id' => $roleId]);

            $arrRoleInfo = $this->_db2->fetchRow($select);


            // Load Modules Level Access
            $arrAssignedRules = array();
            if (!empty($arrRoleInfo['role_parent_id'])) {
                $select = (new Select())
                    ->from(array('a' => 'acl_role_access'))
                    ->columns(['rule_id'])
                    ->where(['a.role_id' => $arrRoleInfo['role_parent_id']]);

                $arrAssignedRules = $this->_db2->fetchCol($select);
            }

            $arrRoleInfo['arrRulesIds'] = $arrAssignedRules;
        }

        $arrViewFieldsIds = array();
        $arrFullFieldsIds = array();
        foreach ($fieldAccessRules as $field) {
            if ($field['status'] == 'F') {
                $arrFullFieldsIds[] = $field['field_id'];
            } else {
                $arrViewFieldsIds[] = $field['field_id'];
            }
        }

        $arrRoleInfo['arrViewFieldsIds'] = $arrViewFieldsIds;
        $arrRoleInfo['arrFullFieldsIds'] = $arrFullFieldsIds;

        return $arrRoleInfo;
    }


    /**
     * Toggle access to specific module for specific company
     *
     * @param $companyId
     * @param $booActivate
     * @param $booSuperAdmin
     * @param $strModule
     * @param string $strRulesType
     * @return bool true on success
     */
    public function toggleModuleAccess($companyId, $booActivate, $booSuperAdmin, $strModule, $strRulesType = 'all')
    {
        try {
            $companyRoles = $this->getCompanyRoles($companyId, 0);

            $roleNames = $booSuperAdmin ? 'superadmin' : 'admin|processing|accounting';
            $roleType  = $strRulesType == 'manage' ? 'admin' : '';

            $arrSelectedRoles = array();
            $arrAllRoles      = array();
            if (!empty($companyRoles) && is_array($companyRoles)) {
                foreach ($companyRoles as $role) {
                    $arrAllRoles[] = $role['role_parent_id'];
                    if (preg_match('/^(.*)' . $roleNames . '(.*)$/i', $role['role_name'], $regs) && ((!empty($roleType) && $role['role_type'] == $roleType) || empty($roleType))) {
                        $arrSelectedRoles[] = $role['role_parent_id'];
                    }
                }
            }

            if (count($arrSelectedRoles)) {
                // Load all rule ids we want to enable/disable
                switch ($strModule) {
                    case 'marketplace':
                        $arrRuleIds = $this->getMarketplaceRules($strRulesType);
                        break;

                    case 'time-tracker':
                        $arrRuleIds = $this->getTimeTrackerRules();
                        break;

                    default:
                        throw new Exception('Incorrectly passed module.');
                        break;
                }


                if (count($arrRuleIds)) {
                    if ($booActivate) {
                        foreach ($arrSelectedRoles as $strRoleId) {
                            foreach ($arrRuleIds as $ruleId) {
                                $this->_db2->insert(
                                    'acl_role_access',
                                    [
                                        'role_id' => $strRoleId,
                                        'rule_id' => $ruleId
                                    ],
                                    null,
                                    false
                                );
                            }
                        }
                    } else {
                        $arrToDelete = array();
                        foreach ($arrAllRoles as $strRoleId) {
                            foreach ($arrRuleIds as $ruleId) {
                                $arrToDelete[] = (new Where())
                                    ->nest()
                                    ->equalTo('role_id', $strRoleId)
                                    ->and
                                    ->equalTo('rule_id', $ruleId)
                                    ->unnest();
                            }
                        }

                        $this->_db2->delete(
                            'acl_role_access',
                            [(new Where())->addPredicates($arrToDelete, Where::OP_OR)]
                        );
                    }

                    $this->_cache->removeItem('acl_role_access' . $companyId);
                }
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Load company roles list (try to use cache if possible)
     *
     * @param int $companyId
     * @param ?int $divisionGroupId
     * @param bool $booShowWithoutGroup
     * @param bool $booIdOnly
     * @param array $arrRoleTypes
     * @return array|false|string
     */
    public function getCompanyRoles($companyId, $divisionGroupId = 0, $booShowWithoutGroup = false, $booIdOnly = false, $arrRoleTypes = array())
    {
        $arrResult = array();

        $cacheId = 'company_roles_' . $companyId;
        if (!($arrRoles = $this->_cache->getItem($cacheId))) {
            // Not in cache
            $select = (new Select())
                ->from(array('r' => 'acl_roles'))
                ->where(['company_id' => (int)$companyId])
                ->order('role_id');

            $arrRoles = $this->_db2->fetchAll($select);
            $this->_cache->setItem($cacheId, $arrRoles);
        }

        if (count($arrRoleTypes)) {
            $arrRolesWithDefinedRoleTypes = array();
            foreach ($arrRoles as $arrRoleInfo) {
                if (in_array($arrRoleInfo['role_type'], $arrRoleTypes)) {
                    $arrRolesWithDefinedRoleTypes[] = $arrRoleInfo;
                }
            }
            $arrRoles = $arrRolesWithDefinedRoleTypes;
        }

        if (!empty($divisionGroupId)) {
            foreach ($arrRoles as $key => $arrRoleInfo) {
                $booShow = false;
                if ((empty($arrRoleInfo['division_group_id']) && $booShowWithoutGroup) || (!empty($arrRoleInfo['division_group_id']) && $arrRoleInfo['division_group_id'] == $divisionGroupId)) {
                    $booShow = true;
                }

                if (!$booShow) {
                    unset($arrRoles[$key]);
                }
            }
        }

        if ($booIdOnly) {
            foreach ($arrRoles as $arrRoleInfo) {
                $arrResult[] = $arrRoleInfo['role_id'];
            }
        } else {
            $arrResult = $arrRoles;
        }

        return $arrResult;
    }

    /**
     * Load paged list of company roles
     *
     * @param int $companyId
     * @param int $companyDivisionGroupId
     * @param $booIsSuperAdmin
     * @param $loggedAsSuperadmin
     * @param string $roleType
     * @param string $roleStatus
     * @param string $roleName
     * @param string $order
     * @param string $dir
     * @param int $start
     * @param int $limit
     * @return array
     */
    public function getCompanyRolesPaged($companyId, $companyDivisionGroupId, $booIsSuperAdmin, $loggedAsSuperadmin, $roleType, $roleStatus, $roleName, $order = '', $dir = '', $start = 0, $limit = 0)
    {

        try {
            $select = (new Select())
                ->from('acl_roles')
                ->where(['company_id' => $companyId]);

            if (!empty($companyDivisionGroupId)) {
                if ($booIsSuperAdmin || $loggedAsSuperadmin) {
                    $select->where(
                        [
                            (new Where())
                                ->nest()
                                ->isNull('division_group_id')
                                ->or
                                ->equalTo('division_group_id', (int)$companyDivisionGroupId)
                                ->unnest()
                        ]
                    );
                } else {
                    $select->where(['division_group_id' => (int)$companyDivisionGroupId]);
                }
            }

            if ($roleType == 'superadmin') {
                $select->where(['role_type' => 'superadmin']);
            } else {
                $select->where(['role_visible' => 1]);
                $select->where([(new Where())->notEqualTo('role_type', 'superadmin')]);
            }


            // Admin can view only roles related to his company
            if (!$booIsSuperAdmin) {
                $select->where(['role_type' => ['user', 'individual_client', 'employer_client', 'admin']]);
            } else {
                // Hide CRM roles
                $select->where([(new Where())->notIn('role_type', ['crmuser'])]);
            }


            if (!empty($roleName)) {
                $select->where([(new Where())->like('role_name', "%$roleName%")]);
            }

            if (!empty($roleStatus)) {
                $select->where(['role_status' => $roleStatus]);
            }


            if (!empty($order)) {
                if (!in_array($order, array('role_id', 'role_name', 'role_regTime', 'role_status'))) {
                    $order = 'role_id';
                }

                $dir = strtoupper($dir);
                if (!in_array($dir, array('ASC', 'DESC'))) {
                    $dir = 'DESC';
                }

                $select->order($order . ' ' . $dir);
            } else {
                $select->order('role_id DESC');
            }

            if (!empty($start) && !empty($limit)) {
                $select->limit($start)->offset($limit);
            }

            $arrRoles   = $this->_db2->fetchAll($select);
            $rolesCount = $this->_db2->fetchResultsCount($select);
        } catch (Exception $e) {
            $arrRoles   = array();
            $rolesCount = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($arrRoles, $rolesCount);
    }


    /**
     * Clean company related roles from cache
     *
     * @param int $companyId
     * @return bool true on success
     */
    public function clearCompanyRolesCache($companyId)
    {
        try {
            $arrCacheIds = array(
                'company_roles_' . $companyId,
                'acl_roles' . $companyId,
                'acl_role_access' . $companyId,
                'acl_rule_details',
                'acl_rules'
            );

            foreach ($arrCacheIds as $cacheId) {
                $this->_cache->removeItem($cacheId);
            }

            /** @var Clients $clients */
            $clients = $this->_serviceContainer->get(Clients::class);
            $clients->getApplicantFields()->clearCache($companyId, $clients->getMemberTypeIdByName('individual'));
            $clients->getApplicantFields()->clearCache($companyId, $clients->getMemberTypeIdByName('employer'));

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Load users/admins roles list for specific company
     *
     * @param int $companyId
     * @param $divisionGroupId
     * @param $booShowWithoutGroup
     * @return array
     */
    public function getCompanyMemberRoles($companyId, $divisionGroupId, $booShowWithoutGroup)
    {
        $arrResult = array();
        $arrRoles  = $this->getCompanyRoles($companyId, $divisionGroupId, $booShowWithoutGroup);
        foreach ($arrRoles as $arrRoleInfo) {
            if (!empty($arrRoleInfo['company_id']) && $arrRoleInfo['role_visible'] == 1 && in_array($arrRoleInfo['role_type'], array('user', 'admin'))) {
                $arrResult[] = $arrRoleInfo;
            }
        }

        return $arrResult;
    }

    /**
     * Load role name
     *
     * @param int $roleId
     * @param int $companyId
     * @param int $divisionGroupId
     * @return string
     */
    public function getRoleName($roleId, $companyId, $divisionGroupId)
    {
        $strRoleName     = '';

        $arrRoles        = $this->getCompanyRoles($companyId, $divisionGroupId);
        foreach ($arrRoles as $arrRoleInfo) {
            if ($arrRoleInfo['role_id'] == $roleId) {
                $strRoleName = $arrRoleInfo['role_name'];
                break;
            }
        }

        return $strRoleName;
    }


    /**
     * Check if current user has access to role:
     * 1. If this is a new role (empty id)
     * 2. If user is not superadmin and this role was created in his company
     * 3. If user is superadmin and role type is not 'superadmin'
     * 4. If user is superadmin and role type is 'superadmin' and user id is 1 (Main superuser)
     *
     * @param $companyId
     * @param $divisionGroupId
     * @param int $roleId
     * @param $isSuperadmin
     * @return bool true if user has access to the role
     */
    public function hasAccessToRole($companyId, $divisionGroupId, $roleId, $isSuperadmin)
    {
        $booHasAccess = false;

        if (empty($roleId)) {
            $booHasAccess = true;
        } elseif (is_numeric($roleId)) {
            if ($isSuperadmin) {
                $arrRoleInfo = $this->getRoleInfo($roleId);
                if (is_array($arrRoleInfo) && array_key_exists('role_type', $arrRoleInfo)) {
                    if ($arrRoleInfo['role_type'] == 'superadmin') {
                        $booHasAccess = $this->_acl->isAllowed('manage-superadmin-roles');
                    } else {
                        $booHasAccess = true;
                    }
                }
            } else {
                $arrCompanyRoles = $this->getCompanyRoles($companyId, $divisionGroupId, false, true);
                if (in_array($roleId, $arrCompanyRoles)) {
                    $booHasAccess = true;
                }
            }
        }

        return $booHasAccess;
    }

    /**
     * Load all role text ids
     *
     * @return array
     */
    public function loadParentRoles()
    {
        $select = (new Select())
            ->from(array('r' => 'acl_roles'))
            ->columns(['role_parent_id']);

        return $this->_db2->fetchCol($select);
    }

    /**
     * Generate text role id for specific company
     *
     * @param $companyId
     * @return string
     */
    public function generateTextRoleId($companyId)
    {
        $arrRoles = $this->loadParentRoles();

        $booUnique     = false;
        $count         = 0;
        $strTextRoleId = '';
        while (!$booUnique) {
            $strTextRoleId = 'company_' . $companyId . '_role';
            if ($count) {
                $strTextRoleId .= '_' . $count;
            }

            if (!in_array($strTextRoleId, $arrRoles)) {
                $booUnique = true;
            } else {
                $count++;
            }
        }

        return $strTextRoleId;
    }

    /**
     * Load child rules for specific parent rule
     *
     * @param $ruleId
     * @return array
     */
    private function _getSubRules($ruleId)
    {
        $arrResultIds = (array)$ruleId;

        $select = (new Select())
            ->from('acl_rules')
            ->columns(['rule_id'])
            ->where(['rule_parent_id' => $ruleId]);

        $arrSubRules = $this->_db2->fetchCol($select);

        if (is_array($arrSubRules) && count($arrSubRules)) {
            $arrSubSubRules = $this->_getSubRules($arrSubRules);
            if (!empty($arrSubSubRules)) {
                $arrResultIds = array_merge($arrResultIds, $arrSubSubRules);
            }
        }

        return array_unique($arrResultIds);
    }

    public function getRuleIdsByCheckIds($arrCheckRulesIds)
    {
        $arrEditRoleRulesIds = array();
        if (!empty($arrCheckRulesIds)) {
            $select = (new Select())
                ->from(array('r' => 'acl_rules'))
                ->columns(['rule_id'])
                ->where(['r.rule_check_id' => $arrCheckRulesIds]);

            $arrEditRoleRulesIds = $this->_db2->fetchCol($select);
        }

        return $arrEditRoleRulesIds;
    }


    public function getAssignedRulesIds($arrRolesIds)
    {
        $arrAssignedRulesIds = array();
        if (!empty($arrRolesIds)) {
            $select = (new Select())
                ->from(array('a' => 'acl_role_access'))
                ->columns(['rule_id'])
                ->where(['a.role_id' => $arrRolesIds])
                ->group('rule_id');

            $arrAssignedRulesIds = $this->_db2->fetchCol($select);
        }

        return $arrAssignedRulesIds;
    }


    /**
     * Get role type by selected rules
     *
     * @param $arrSelectedRules
     * @param $roleName
     * @param string $type
     * @return string
     */
    public function getUserType($arrSelectedRules, $roleName, $type = 'company')
    {
        if ($type == 'superadmin') {
            return 'superadmin';
        }
        /// Rules which 100% identify which role type is

        // @TODO: update these rules or load them from DB
        $arrIdentifyRules = array(
            'admin' => array(4),
            'user'  => array(5)
        );

        foreach ($arrIdentifyRules as $ruleId => $arrRules) {
            $result = array_intersect($arrRules, $arrSelectedRules);

            if (count($result) > 0) {
                return $ruleId;
            }
        }

        return preg_match('/^.*employer.*$/si', $roleName) ? 'employer_client' : 'individual_client';
    }

    /**
     * Load child rules for specific parent rule
     *
     * @param $ruleId
     * @param array $arrRules
     * @return array
     */
    private function _getChildRules($ruleId, &$arrRules)
    {
        $result = array();
        foreach ($arrRules as $theRule) {
            if ($theRule['rule_parent_id'] == $ruleId) {
                $theRule['children'] = $this->_getChildRules($theRule['rule_id'], $arrRules);
                $result[]            = $theRule;
            }
        }

        return $result;
    }

    /**
     * Load list of excluded (not allowed) rules for the company
     *
     * @param null|int $companyId
     * @return array
     */
    public function getCompanyExcludedRules($companyId = null)
    {
        $arrExcludeIds = array();

        try {
            // These settings we load from the company details table
            $booIsCheckedExporting    = null;
            $booIsCheckedTimeTracking = null;
            $booIsCheckedMarketing    = null;
            $booIsCheckedImportBCPNP  = null;

            if (!empty($companyId)) {
                // Check if checkbox 'Time Tracking' / 'MP module' / 'Company Export' is checked,
                // if not - don't show rules even if they are in the package
                $select = (new Select())
                    ->from(array('d' => 'company_details'))
                    ->where(['d.company_id' => (int)$companyId]);

                $arrCompanyInfo = $this->_db2->fetchRow($select);

                $booIsCheckedExporting    = isset($arrCompanyInfo['allow_export']) && ($arrCompanyInfo['allow_export'] == 'Y');
                $booIsCheckedTimeTracking = isset($arrCompanyInfo['time_tracker_enabled']) && ($arrCompanyInfo['time_tracker_enabled'] == 'Y');
                $booIsCheckedMarketing    = isset($arrCompanyInfo['marketplace_module_enabled']) && ($arrCompanyInfo['marketplace_module_enabled'] == 'Y');
                $booIsCheckedImportBCPNP  = isset($arrCompanyInfo['allow_import_bcpnp']) && ($arrCompanyInfo['allow_import_bcpnp'] == 'Y');
            }

            // These settings we load from the config file
            $booIsPUAEnabled                     = (bool)$this->_config['site_version']['pua_enabled'];
            $booIsCheckABNEnabled                = (bool)$this->_config['site_version']['check_abn_enabled'];
            $booDocumentsChecklistEnabled        = !empty($this->_config['site_version']['documents_checklist_enabled']);
            $booIsGeneratePdfLetterEnabled       = (bool)$this->_config['site_version']['custom_templates_settings']['comfort_letter']['enabled'];
            $isAuthorizedAgentsManagementEnabled = !empty($this->_config['site_version']['authorised_agents_management_enabled']);

            /** @var Users $oUsers */
            $oUsers = $this->_serviceContainer->get(Users::class);
            if (!$oUsers->isLmsEnabled(false)) {
                $arrExcludeIds = array_merge($arrExcludeIds, $this->getRulesByCheckRuleId('lms-view'));
            }

            if ($booIsCheckedExporting === false) {
                $arrExcludeIds = array_merge($arrExcludeIds, $this->getAllowExportRules());
            }

            if ($booIsCheckedTimeTracking === false) {
                $arrExcludeIds = array_merge($arrExcludeIds, $this->getTimeTrackerRules());
            }

            if ($booIsCheckedMarketing === false) {
                $arrExcludeIds = array_merge($arrExcludeIds, $this->getMarketplaceRules());
            }

            if (!$isAuthorizedAgentsManagementEnabled) {
                $arrExcludeIds = array_merge($arrExcludeIds, $this->getRulesByCheckRuleId('define-authorised-agents'));
                $arrExcludeIds = array_merge($arrExcludeIds, $this->getRulesByCheckRuleId('generate-con'));
            }

            if (!$booIsGeneratePdfLetterEnabled) {
                $arrExcludeIds = array_merge($arrExcludeIds, $this->getRulesByCheckRuleId('generate-pdf-letter'));
            }

            if ($booIsCheckedImportBCPNP === false) {
                $arrExcludeIds = array_merge($arrExcludeIds, $this->getRulesByCheckRuleId('import-bcpnp'));
            }

            if (!$booIsPUAEnabled) {
                $arrExcludeIds = array_merge($arrExcludeIds, $this->getRulesByCheckRuleId('pua-planning'));
            }

            if (!$booIsCheckABNEnabled) {
                $arrExcludeIds = array_merge($arrExcludeIds, $this->getRulesByCheckRuleId('abn-check'));
            }

            if (!$booDocumentsChecklistEnabled) {
                $arrExcludeIds = array_merge($arrExcludeIds, $this->getRulesByCheckRuleId('client-documents-checklist-view'));
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrExcludeIds;
    }


    /**
     * Load acl rules list for specific company
     *
     * @param int $companyId
     * @param bool $booSuperadminOnly
     * @return array
     * @throws Exception
     */
    public function getRulesByCompanyId($companyId, $booSuperadminOnly = false)
    {
        $strWhere = 'r.module_id = m.module_id AND r.rule_visible = 1';
        if (!$booSuperadminOnly) {
            $strWhere .= ' AND r.superadmin_only != 1';
        }

        // We don't want show CRM rules here - only when edit CRM roles
        $booHideCRMRules = true;
        if ($booHideCRMRules) {
            $strWhere .= ' AND r.module_id != "crm"';
        }

        $select = (new Select())
            ->from(array('m' => 'acl_modules'))
            ->columns(array('module_id', 'module_name'))
            ->join(array('r' => 'acl_rules'), new PredicateExpression($strWhere), array('rule_id', 'rule_parent_id', 'rule_check_id', 'rule_description', 'rule_visible', 'rule_order'), Select::JOIN_LEFT_OUTER)
            ->order(array('r.rule_order', 'm.module_name DESC', 'r.rule_id', 'r.rule_parent_id'));

        // Show rules in relation to company packages
        if (is_numeric($companyId) && !empty($companyId)) {
            $select2 = (new Select())
                ->from(array('d' => 'packages_details'))
                ->columns(['rule_id'])
                ->join(array('p' => 'company_packages'), 'd.package_id = p.package_id', [], Select::JOIN_LEFT_OUTER)
                ->where(['p.company_id' => $companyId]);

            $arrPackageRules = $this->_db2->fetchCol($select2);

            if (is_array($arrPackageRules) && count($arrPackageRules)) {
                // Filter - exclude rules we don't want to show
                $arrExcludeIds = $this->getCompanyExcludedRules($companyId);

                if (is_array($arrExcludeIds) && count($arrExcludeIds)) {
                    $arrFiltered = array();
                    foreach ($arrPackageRules as $checkRuleId) {
                        if (!in_array($checkRuleId, $arrExcludeIds)) {
                            $arrFiltered[] = $checkRuleId;
                        }
                    }
                    $arrPackageRules = $arrFiltered;
                }

                $select->where(['r.rule_id' => $arrPackageRules]);
            }
        }

        $rules = $this->_db2->fetchAll($select);
        if (empty($rules)) {
            throw new Exception('There are no roles and rules in DB');
        }

        $arrRules = array();
        foreach ($rules as $theRule) {
            if (empty($theRule['rule_parent_id']) && !empty($theRule['rule_id'])) {
                // This is the beginning of the section
                $RuleWithChildren = $theRule;
                $RuleWithChildren['children'] = $this->_getChildRules($theRule['rule_id'], $rules);
                $arrRules[] = $RuleWithChildren;
            }
        }

        return $arrRules;
    }

    /**
     * Get role type in relation to access rights
     *
     * @param $roleType
     * @return string
     */
    public function getRoleTypeByAccess($roleType)
    {
        if (!in_array($roleType, array('superadmin', 'company'))) {
            $roleType = 'company';
        }

        if ($roleType == 'superadmin' && !$this->_acl->isAllowed('manage-superadmin-roles')) {
            $roleType = 'company';
        }

        return $roleType;
    }

    /**
     * Update specific role's info
     *
     * @param int|array $roleId
     * @param array $arrRoleInfo
     * @return bool true on success
     */
    public function updateRoleDetails($roleId, $arrRoleInfo)
    {
        try {
            $this->_db2->update('acl_roles', $arrRoleInfo, ['role_id' => $roleId]);

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $booSuccess = false;
        }

        return $booSuccess;
    }

    /**
     * Get role id by name and type
     *
     * @param int $companyId
     * @param string $roleName
     * @param string $roleType
     * @return int role id, empty if not found
     */
    public function getCompanyRoleIdByNameAndType($companyId, $roleName, $roleType)
    {
        $roleId = 0;

        try {
            $arrCompanyRoles = $this->getCompanyRoles($companyId);
            foreach ($arrCompanyRoles as $arrCompanyRoleInfo) {
                if ($arrCompanyRoleInfo['role_name'] == $roleName && (empty($roleType) || $arrCompanyRoleInfo['role_type'] == $roleType)) {
                    $roleId = $arrCompanyRoleInfo['role_id'];
                    break;
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $roleId;
    }

    /**
     * Check if role (with specific name and type) exists for specific company
     * If not - create it
     *
     * @param int $companyId
     * @param string $roleName
     * @param string $roleType
     * @return int role id
     */
    public function createRoleIfDoesNotExists($companyId, $roleName, $roleType)
    {
        try {
            $roleId = $this->getCompanyRoleIdByNameAndType($companyId, $roleName, '');

            if (empty($roleId)) {
                $arrRoleInfo = array(
                    'company_id'        => $companyId,
                    'division_group_id' => null,
                    'role_name'         => $roleName,
                    'role_type'         => $roleType,
                    'role_parent_id'    => $this->generateTextRoleId($companyId),
                    'role_child_id'     => 'guest',
                    'role_visible'      => 1,
                    'role_status'       => 1,
                    'role_regTime'      => time(),
                );

                $roleId = $this->_db2->insert('acl_roles', $arrRoleInfo);

                $this->clearCompanyRolesCache($companyId);
            }
        } catch (Exception $e) {
            $roleId = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $roleId;
    }


}
