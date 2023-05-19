<?php

namespace Clients\Service\Clients;

use Clients\Service\Clients;
use Clients\Service\Members;
use Exception;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Officio\Common\Service\BaseService;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\Roles;
use Officio\Common\Service\Settings;
use Officio\Common\SubServiceInterface;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class ApplicantFields extends BaseService implements SubServiceInterface
{

    /** @var Clients */
    protected $_parent;

    /** @var Company */
    protected $_company;

    /** @var Roles */
    protected $_roles;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_company    = $services[Company::class];
        $this->_roles      = $services[Roles::class];
        $this->_encryption = $services[Encryption::class];
    }

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent()
    {
        return $this->_parent;
    }

    /**
     * Clear cache for fields/groups
     *
     * @param int $companyId
     * @param int $memberType - 2 (individual), 3 (employer)
     */
    public function clearCache($companyId, $memberType)
    {
        $arrTypes = $this->_parent->getApplicantTypes()->getTypes($companyId, true, $memberType);
        $arrTypes = !is_array($arrTypes) || !count($arrTypes) ? array(0) : array_merge($arrTypes, array(0));

        foreach ($arrTypes as $applicantTypeId) {
            $arrCacheToClear = array(
                'applicant_blocks_' . $companyId . '_' . $memberType . '_' . $applicantTypeId,
                'applicant_groups_' . $companyId . '_' . $memberType . '_' . $applicantTypeId,
                'applicant_fields_' . $companyId . '_' . $memberType . '_' . $applicantTypeId,
            );

            foreach ($arrCacheToClear as $cacheId) {
                $this->_cache->removeItem($cacheId);
            }
        }
    }

    /**
     * Load company groups list
     *
     * @param int $companyId
     * @param int $memberType
     * @param int $applicantType
     * @return array
     */
    public function getCompanyGroups($companyId, $memberType, $applicantType = 0)
    {
        $applicantType = (int)$applicantType;
        $cacheId       = 'applicant_groups_' . $companyId . '_' . $memberType . '_' . $applicantType;
        if (!($data = $this->_cache->getItem($cacheId))) {
            $select = (new Select())
                ->from(array('g' => 'applicant_form_groups'))
                ->join(array('b' => 'applicant_form_blocks'), 'g.applicant_block_id = b.applicant_block_id', array('repeatable', 'contact_block'), Select::JOIN_RIGHT)
                ->where(
                    [
                        'g.company_id'     => (int)$companyId,
                        'b.member_type_id' => (int)$memberType
                    ]
                )
                ->order(array('b.order ASC', 'g.order ASC'));

            if (!empty($applicantType)) {
                $select->where->equalTo('b.applicant_type_id', (int)$applicantType);
            }

            $data = $this->_db2->fetchAll($select);

            $this->_cache->setItem($cacheId, $data);
        }

        return $data;
    }

    /**
     * Load company fields groups list in specific block
     *
     * @param int $companyId
     * @param int $memberTypeId
     * @param int $blockId
     * @return array
     */
    public function getCompanyGroupsInBlock($companyId, $memberTypeId, $blockId)
    {
        $arrAllGroups = $this->getCompanyGroups($companyId, $memberTypeId);

        $arrFilteredGroups = array();
        foreach ($arrAllGroups as $arrGroupInfo) {
            if ($arrGroupInfo['applicant_block_id'] == $blockId) {
                $arrFilteredGroups[] = $arrGroupInfo;
            }
        }

        return $arrFilteredGroups;
    }

    /**
     * Load company groups list
     *
     * @param int $companyId
     * @param int $memberTypeId
     * @param int $applicantTypeId
     * @return array
     */
    public function getCompanyBlocks($companyId, $memberTypeId, $applicantTypeId = 0)
    {
        $cacheId = 'applicant_blocks_' . $companyId . '_' . $memberTypeId . '_' . $applicantTypeId;
        if (!($data = $this->_cache->getItem($cacheId))) {
            $select = (new Select())
                ->from(array('b' => 'applicant_form_blocks'))
                ->where(
                    [
                        'b.company_id'     => (int)$companyId,
                        'b.member_type_id' => (int)$memberTypeId
                    ]
                )
                ->order(array('b.order ASC'));

            if (!empty($applicantTypeId)) {
                $select->where->equalTo('b.applicant_type_id', (int)$applicantTypeId);
            }

            $data = $this->_db2->fetchAll($select);

            $this->_cache->setItem($cacheId, $data);
        }

        return $data;
    }

    /**
     * Check if company has created company blocks
     *
     * @param int $companyId
     * @return bool
     */
    public function hasCompanyBlocks($companyId)
    {
        $select = (new Select())
            ->from(array('b' => 'applicant_form_blocks'))
            ->where(['b.company_id' => (int)$companyId])
            ->order(['b.order ASC']);

        $data = $this->_db2->fetchAll($select);

        return is_array($data) && count($data);
    }

    /**
     * Load all fields list for specific company (don't load/check access rights, etc.)
     *
     * @param int $companyId
     * @param bool $booIdsOnly
     * @return array
     */
    public function getCompanyAllFields($companyId, $booIdsOnly = false)
    {
        $select = (new Select())
            ->from(array('f' => 'applicant_form_fields'))
            ->columns([$booIdsOnly ? 'applicant_field_id' : Select::SQL_STAR])
            ->where(['f.company_id' => (int)$companyId])
            ->order('f.label');

        return $booIdsOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
    }

    /**
     * Load all fields list for a specific company (don't load/check access rights, etc.),
     * but make sure that the field is placed in some group
     *
     * @param int $companyId
     * @return array
     */
    public function getCompanyAllFieldsInAssignedGroups($companyId)
    {
        // Make sure that field is in some group
        $select = (new Select())
            ->columns([Select::SQL_STAR])
            ->from(array('f' => 'applicant_form_fields'))
            ->join(array('o' => 'applicant_form_order'), 'o.applicant_field_id = f.applicant_field_id', [])
            ->where(['f.company_id' => (int)$companyId])
            ->order('f.label');

        return $this->_db2->fetchAll($select);
    }

    /**
     * Get company fields list
     *
     * @param int $companyId
     * @param int $memberTypeId
     * @param int $applicantTypeId
     * @return array of company fields
     */
    public function getCompanyFields($companyId, $memberTypeId, $applicantTypeId = 0)
    {
        $applicantTypeId = empty($applicantTypeId) ? 0 : $applicantTypeId;

        $cacheId = 'applicant_fields_' . $companyId . '_' . $memberTypeId . '_' . $applicantTypeId;
        if (!($arrCompanyFields = $this->_cache->getItem($cacheId))) {
            // Load assigned fields (can be from other type, e.g. Contact)
            $select = (new Select())
                ->from(array('f' => 'applicant_form_fields'))
                ->join(array('o' => 'applicant_form_order'), 'o.applicant_field_id = f.applicant_field_id', 'use_full_row', Select::JOIN_LEFT)
                ->join(array('g' => 'applicant_form_groups'), 'g.applicant_group_id = o.applicant_group_id', array('group_title' => 'title', 'group_id' => 'applicant_group_id'), Select::JOIN_RIGHT)
                ->join(array('b' => 'applicant_form_blocks'), 'g.applicant_block_id = b.applicant_block_id', array('applicant_block_id', 'repeatable', 'contact_block'), Select::JOIN_RIGHT)
                ->where(
                    [
                        'f.company_id'     => (int)$companyId,
                        'b.member_type_id' => (int)$memberTypeId
                    ]
                )
                ->order(array('b.order ASC', 'g.order ASC', 'o.field_order'));

            if (!empty($applicantTypeId)) {
                $select->where->equalTo('b.applicant_type_id', (int)$applicantTypeId);
            }

            $arrAssignedFields = $this->_db2->fetchAll($select);

            $arrAssignedFieldsIds = array();
            if (count($arrAssignedFields)) {
                foreach ($arrAssignedFields as $arrAssignedFieldInfo) {
                    $arrAssignedFieldsIds[] = $arrAssignedFieldInfo['applicant_field_id'];
                }
            }

            // Load unassigned fields - for this type only
            $select = (new Select())
                ->from(array('f' => 'applicant_form_fields'))
                ->where(
                    [
                        'f.company_id'     => (int)$companyId,
                        'f.member_type_id' => (int)$memberTypeId
                    ]
                )
                ->order('f.label');

            if (count($arrAssignedFieldsIds)) {
                $select->where->notIn('f.applicant_field_id', $arrAssignedFieldsIds);
            }

            $arrUnAssignedFields = $this->_db2->fetchAll($select);
            foreach ($arrUnAssignedFields as &$arrUnAssignedFieldInfo) {
                $arrUnAssignedFieldInfo['group_title']   = '';
                $arrUnAssignedFieldInfo['group_id']      = 0;
                $arrUnAssignedFieldInfo['contact_block'] = 'N';
                $arrUnAssignedFieldInfo['repeatable']    = 'N';
            }
            unset($arrUnAssignedFieldInfo);

            $arrCompanyFields = array_merge($arrAssignedFields, $arrUnAssignedFields);

            $this->_cache->setItem($cacheId, $arrCompanyFields);
        }

        // A special check for the CLient Profile ID field - to show it or not
        if (!$this->_company->isCompanyClientProfileIdEnabled($companyId)) {
            foreach ($arrCompanyFields as $key => $arrCompanyFieldInfo) {
                if ($arrCompanyFieldInfo['type'] == 'client_profile_id') {
                    unset($arrCompanyFields[$key]);
                }
            }
        }

        return $arrCompanyFields;
    }

    /**
     * Create block for the company
     * @param $companyId
     * @param $memberType
     * @param $blockType
     * @param $applicantTypeId
     * @param $booRepeatable
     * @return int - block id, zero on error
     */
    public function createBlock($companyId, $memberType, $blockType, $applicantTypeId = false, $booRepeatable = false)
    {
        try {
            $maxOrder  = -1;
            $arrBlocks = $this->getCompanyBlocks($companyId, $memberType);
            foreach ($arrBlocks as $arrBlockInfo) {
                $maxOrder = max($maxOrder, $arrBlockInfo['order']);
            }

            $arrInsert = array(
                'member_type_id' => $memberType,
                'company_id'     => $companyId,
                'contact_block'  => $blockType == 'contact' ? 'Y' : 'N',
                'repeatable'     => $booRepeatable ? 'Y' : 'N',
                'order'          => $maxOrder + 1
            );

            if ($applicantTypeId) {
                $arrInsert['applicant_type_id'] = $applicantTypeId;
            }

            $blockId = $this->_db2->insert('applicant_form_blocks', $arrInsert);
            $this->clearCache($companyId, $memberType);
        } catch (Exception $e) {
            $blockId = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $blockId;
    }

    /**
     * Update block info
     *
     * @param $companyId
     * @param $memberType
     * @param $blockId
     * @param $booIsBlockRepeatable
     * @return bool true on success
     */
    public function updateBlock($companyId, $memberType, $blockId, $booIsBlockRepeatable)
    {
        try {
            $this->_db2->update(
                'applicant_form_blocks',
                ['repeatable' => $booIsBlockRepeatable ? 'Y' : 'N'],
                ['applicant_block_id' => (int)$blockId]
            );
            $this->clearCache($companyId, $memberType);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Load info about a specific block
     * @param $blockId
     * @return array
     */
    public function getBlockInfo($blockId)
    {
        $select = (new Select())
            ->from(array('b' => 'applicant_form_blocks'))
            ->where(['b.applicant_block_id' => (int)$blockId]);

        return $this->_db2->fetchRow($select);
    }


    /**
     * Delete specific block
     * @param $companyId
     * @param $memberType
     * @param $blockId
     * @return bool
     */
    public function deleteBlock($companyId, $memberType, $blockId)
    {
        try {
            $this->_db2->delete('applicant_form_blocks', ['applicant_block_id' => (int)$blockId]);
            $this->clearCache($companyId, $memberType);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Load field ids list for specific block
     * @param $blockId
     * @return array
     */
    public function getBlockFields($blockId)
    {
        $select = (new Select())
            ->from(array('o' => 'applicant_form_order'))
            ->columns(['applicant_field_id'])
            ->join(array('g' => 'applicant_form_groups'), 'g.applicant_group_id = o.applicant_group_id', [], Select::JOIN_RIGHT)
            ->join(array('b' => 'applicant_form_blocks'), 'g.applicant_block_id = b.applicant_block_id', [], Select::JOIN_RIGHT)
            ->where(['b.applicant_block_id' => (int)$blockId]);

        return $this->_db2->fetchCol($select);
    }

    /**
     * Load group ids related to a specific block
     * @param $blockId
     * @return array
     */
    public function getBlockGroups($blockId)
    {
        $select = (new Select())
            ->from(array('g' => 'applicant_form_groups'))
            ->columns(['applicant_group_id'])
            ->where(['g.applicant_block_id' => (int)$blockId]);

        return $this->_db2->fetchCol($select);
    }


    /**
     * Load allowed fields list for current user
     * @param null $userId
     * @return array
     */
    public function getUserAllowedFields($userId = null)
    {
        $arrRoles = $this->getParent()->getMemberRoles($userId);

        $arrAllowedFields = array();
        if (is_array($arrRoles) && count($arrRoles)) {
            $select = (new Select())
                ->from(array('fa' => 'applicant_form_fields_access'))
                ->columns(array('applicant_group_id', 'applicant_field_id', 'status'))
                ->where(['fa.role_id' => $arrRoles]);

            $arrAllAllowedFields = $this->_db2->fetchAll($select);

            // Make sure that we'll use the highest access right
            // E.g. if there are 2 roles assigned and one role has R access, another role has F access ->
            // As a result, the user will have the F access
            $arrGroupedAccess = array();
            foreach ($arrAllAllowedFields as $arrAllAllowedFieldInfo) {
                if (!isset($arrGroupedAccess[$arrAllAllowedFieldInfo['applicant_group_id']][$arrAllAllowedFieldInfo['applicant_field_id']])) {
                    $arrGroupedAccess[$arrAllAllowedFieldInfo['applicant_group_id']][$arrAllAllowedFieldInfo['applicant_field_id']] = $arrAllAllowedFieldInfo['status'];
                } elseif ($arrGroupedAccess[$arrAllAllowedFieldInfo['applicant_group_id']][$arrAllAllowedFieldInfo['applicant_field_id']] == 'R' && $arrAllAllowedFieldInfo['status'] == 'F') {
                    $arrGroupedAccess[$arrAllAllowedFieldInfo['applicant_group_id']][$arrAllAllowedFieldInfo['applicant_field_id']] = 'F';
                }
            }

            foreach ($arrGroupedAccess as $groupId => $arrAccess) {
                foreach ($arrAccess as $fieldId => $status) {
                    $arrAllowedFields[] = array(
                        'applicant_group_id' => $groupId,
                        'applicant_field_id' => $fieldId,
                        'status'             => $status,
                    );
                }
            }
        }

        return $arrAllowedFields;
    }

    /**
     * Load allowed fields for a specific role
     * For the new role - load "default access rights"
     *
     * @param int $companyId
     * @param int $roleId
     * @return array
     */
    public function getRoleAllowedFields($companyId, $roleId)
    {
        $arrGroupedResult = array();
        if (empty($roleId)) {
            $arrGroupedResult = $this->getDefaultAccessRightsForNewRole($companyId);
        } else {
            $select = (new Select())
                ->from(array('fa' => 'applicant_form_fields_access'))
                ->where(['fa.role_id' => (int)$roleId]);

            $arrFieldsRights = $this->_db2->fetchAll($select);

            foreach ($arrFieldsRights as $arrFieldRightsDetails) {
                $arrGroupedResult[$arrFieldRightsDetails['applicant_group_id']][$arrFieldRightsDetails['applicant_field_id']] = $arrFieldRightsDetails['status'];
            }
        }

        return $arrGroupedResult;
    }

    /**
     * Load date fields list for the current user (has access to)
     * @param bool $booSuperAdmin
     * @return array
     */
    public function getDateFields($booSuperAdmin)
    {
        $arrDateFields = array();

        try {
            $arrAllowedFieldIds = array();
            if (!$booSuperAdmin) {
                $arrAllowedFields = $this->getUserAllowedFields();
                foreach ($arrAllowedFields as $arrAllowedFieldInfo) {
                    $arrAllowedFieldIds[] = $arrAllowedFieldInfo['applicant_field_id'];
                }
            }

            if ($booSuperAdmin || count($arrAllowedFieldIds)) {
                $select = (new Select())
                    ->from(array('f' => 'applicant_form_fields'))
                    ->columns(array('label', 'field_id' => 'applicant_field_id'))
                    ->where(
                        [
                            (new Where())
                                ->in('f.type', array('date', 'date_repeatable', 'office_change_date_time'))
                                ->equalTo('f.company_id', $this->_auth->getCurrentUserCompanyId())
                        ]
                    )
                    ->order(array('f.label ASC', 'f.applicant_field_id ASC'));

                if (!$booSuperAdmin) {
                    $arrAllowedFieldIds = array_unique($arrAllowedFieldIds);
                    $select->where(
                        [
                            'f.applicant_field_id' => $arrAllowedFieldIds
                        ]
                    );
                }

                $arrDateFields = $this->_db2->fetchAll($select);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrDateFields;
    }

    /**
     * Load grouped company fields
     * @param int $companyId
     * @param int $memberType
     * @param int $applicantTypeId
     * @param bool $booCheckAccessRights
     * @param bool $booCreateNewClient
     * @return array
     */
    public function getGroupedCompanyFields($companyId, $memberType, $applicantTypeId, $booCheckAccessRights = true, $booCreateNewClient = false)
    {
        // Load ordered and grouped fields
        $select = (new Select())
            ->from(array('o' => 'applicant_form_order'))
            ->join(array('g' => 'applicant_form_groups'), 'g.applicant_group_id = o.applicant_group_id', array('group_title' => 'title', 'group_id' => 'applicant_group_id', 'group_collapsed' => 'collapsed', 'cols_count'), Select::JOIN_RIGHT)
            ->join(array('b' => 'applicant_form_blocks'), 'g.applicant_block_id = b.applicant_block_id', array('group_repeatable' => 'repeatable', 'group_contact_block' => 'contact_block'), Select::JOIN_RIGHT)
            ->where(
                [
                    'b.company_id'     => (int)$companyId,
                    'b.member_type_id' => (int)$memberType
                ]
            )
            ->order(array('b.order ASC', 'g.order ASC', 'o.field_order'));

        if (!empty($applicantTypeId)) {
            $select->where->equalTo('b.applicant_type_id', $applicantTypeId);
        }

        $arrGroupedData = $this->_db2->fetchAll($select);

        $booIsSuperAdmin = $booCheckAccessRights ? $this->_auth->isCurrentUserSuperadmin() : true;

        // Get all company fields
        $arrCompanyFields = $this->getCompanyFields($companyId, $memberType, $applicantTypeId);

        // Load access rights to the fields, in relation to the current user role(s)
        $arrAllowedFields = $booCheckAccessRights ? $this->getUserAllowedFields() : array();

        // Special check for client login possibility
        switch ($memberType) {
            case $this->_parent->getMemberTypeIdByName('individual'):
                $booCanLogin = $booCheckAccessRights ? $this->_acl->isAllowed('clients-individual-client-login') : true;
                break;

            case $this->_parent->getMemberTypeIdByName('employer'):
                $booCanLogin = $booCheckAccessRights ? $this->_acl->isAllowed('clients-employer-client-login') : true;
                break;

            default:
                $booCanLogin = false;
                break;
        }

        $arrResult              = array();
        $arrDefaultFieldsToShow = array('first_name', 'last_name', 'given_names', 'family_name', 'entity_name', 'file_number', 'DOB', 'passport_exp_date');
        foreach ($arrGroupedData as $arrGroupedInfo) {
            if (!array_key_exists($arrGroupedInfo['group_id'], $arrResult)) {
                $arrResult[$arrGroupedInfo['group_id']] = array(
                    'group_id'            => $arrGroupedInfo['group_id'],
                    'group_title'         => $arrGroupedInfo['group_title'],
                    'group_collapsed'     => $arrGroupedInfo['group_collapsed'],
                    'group_show_title'    => 'Y',
                    'group_repeatable'    => $arrGroupedInfo['group_repeatable'],
                    'group_contact_block' => $arrGroupedInfo['group_contact_block'],
                    'group_cols_count'    => $arrGroupedInfo['cols_count'],
                    'group_access'        => 'R', // Will be not used for now
                    'fields'              => array()
                );
            }

            $arrFieldInfo = array();
            foreach ($arrCompanyFields as $arrCompanyFieldInfo) {
                if ($arrCompanyFieldInfo['applicant_field_id'] == $arrGroupedInfo['applicant_field_id']) {
                    // Check if this field can be showed for the current user
                    if ($booIsSuperAdmin) {
                        $access = 'F';
                    } else {
                        $access = '';
                        foreach ($arrAllowedFields as $arrAllowedFieldInfo) {
                            if ($arrAllowedFieldInfo['applicant_group_id'] == $arrGroupedInfo['group_id'] && $arrAllowedFieldInfo['applicant_field_id'] == $arrCompanyFieldInfo['applicant_field_id']) {
                                $access = $arrAllowedFieldInfo['status'];
                                break;
                            }
                        }

                        // During new client creation the office must be set
                        if ($booCreateNewClient && empty($access) && $arrCompanyFieldInfo['applicant_field_unique_id'] == 'office') {
                            $access = 'F';
                        }
                    }

                    // Login fields are also based on the 'role access' setting
                    if (!$booCanLogin && in_array($arrCompanyFieldInfo['applicant_field_unique_id'], array('username', 'password', 'disable_login'))) {
                        $access = '';
                    }

                    if (!empty($access)) {
                        $arrFieldInfo = array(
                            'field_id'              => $arrCompanyFieldInfo['applicant_field_id'],
                            'field_unique_id'       => $arrCompanyFieldInfo['applicant_field_unique_id'],
                            'field_name'            => $arrCompanyFieldInfo['label'],
                            'field_type'            => $arrCompanyFieldInfo['type'],
                            'field_encrypted'       => $arrCompanyFieldInfo['encrypted'],
                            'field_required'        => $arrCompanyFieldInfo['required'],
                            'field_custom_height'   => $arrCompanyFieldInfo['custom_height'],
                            'field_disabled'        => $arrCompanyFieldInfo['disabled'],
                            'field_maxlength'       => $arrCompanyFieldInfo['maxlength'],
                            'field_access'          => $access,
                            'field_column_show'     => in_array($arrCompanyFieldInfo['applicant_field_unique_id'], $arrDefaultFieldsToShow) && $arrGroupedInfo['group_repeatable'] == 'N',
                            'field_use_full_row'    => $arrGroupedInfo['use_full_row'] == 'Y',
                            'field_multiple_values' => $arrCompanyFieldInfo['multiple_values'] == 'Y',
                            'field_can_edit_in_gui' => $arrCompanyFieldInfo['can_edit_in_gui'] == 'Y'
                        );
                    }
                    break;
                }
            }

            if (count($arrFieldInfo)) {
                $arrResult[$arrGroupedInfo['group_id']]['fields'][] = $arrFieldInfo;
            }
        }

        return array_values($arrResult);
    }


    /**
     * Load all groups and fields for specific company, member type, applicant type
     * @param int $companyId
     * @param int $memberTypeId
     * @param int $applicantTypeId
     * @param bool $booOnlyAssigned
     * @return array
     *
     * Sample $arrResult:
     * $arrResult = array(
     *       'blocks' => array(
     *           block_id_1 => array(
     *               'contact_block' => 'Y',
     *               'repeatable' => 'N',
     *               'data' => array(
     *                   group_id => array(
     *                       collapsed => 'Y',
     *                       fields => array(
     *                          ...
     *                       )
     *                   )
     *               )
     *           ),
     *
     *           ...
     *       ),
     *
     *       'available_fields' => array(
     *      )
     *   );
     *
     */
    public function getAllGroupsAndFields($companyId, $memberTypeId, $applicantTypeId, $booOnlyAssigned = false)
    {
        $arrBlocks           = array();
        $arrUnassignedFields = array();
        $arrPlacedFieldsIds  = array();
        try {
            $booCanClientLogin = $this->_company->getPackages()->canCompanyClientLogin($companyId);

            // Load fields list
            $arrFieldsGrouped = array();
            $arrCompanyFields = $this->getCompanyFields($companyId, $memberTypeId, $applicantTypeId);

            foreach ($arrCompanyFields as &$arrFieldInfo) {
                // Hide username/password fields if company has no access to 'Client Login' functionality
                if (!$booCanClientLogin && in_array($arrFieldInfo['applicant_field_unique_id'], array('username', 'password'))) {
                    continue;
                }

                $arrFieldInfo['readonly'] = false;

                $arrFieldsGrouped[$arrFieldInfo['applicant_field_id']] = $arrFieldInfo;
            }
            unset($arrFieldInfo);

            $arrAllBlocks = $this->getCompanyBlocks($companyId, $memberTypeId, $applicantTypeId);
            foreach ($arrAllBlocks as $arrBlockInfo) {
                $arrBlockGroups = array();

                // Load groups for the current block
                $arrGroups = $this->getCompanyGroupsInBlock($companyId, $memberTypeId, $arrBlockInfo['applicant_block_id']);
                if (is_array($arrGroups) && count($arrGroups) > 0) {
                    $arrGroupIds = Settings::arrayColumnAsKey('applicant_group_id', $arrGroups, 'applicant_group_id');

                    // Save group info
                    foreach ($arrGroups as $groupInfo) {
                        $arrBlockGroups[$groupInfo['applicant_group_id']]['group_id']        = $groupInfo['applicant_group_id'];
                        $arrBlockGroups[$groupInfo['applicant_group_id']]['group_name']      = $groupInfo['title'];
                        $arrBlockGroups[$groupInfo['applicant_group_id']]['group_collapsed'] = $groupInfo['collapsed'];
                        $arrBlockGroups[$groupInfo['applicant_group_id']]['cols_count']      = $groupInfo['cols_count'];
                    }


                    // Load fields assigned to these groups
                    $select = (new Select())
                        ->from(array('o' => 'applicant_form_order'))
                        ->where(['o.applicant_group_id' => $arrGroupIds])
                        ->order(array('o.field_order ASC'));

                    $arrFieldsOrder = $this->_db2->fetchAll($select);

                    // Generate list of fields (grouped, assigned)
                    foreach ($arrFieldsOrder as $arrOrderInfo) {
                        if (!isset($arrFieldsGrouped[$arrOrderInfo['applicant_field_id']])) {
                            // How this can be?
                            continue;
                        }

                        $arrFieldInfo                 = $arrFieldsGrouped[$arrOrderInfo['applicant_field_id']];
                        $arrFieldInfo['use_full_row'] = $arrOrderInfo['use_full_row'];

                        // Make sure that default settings are set
                        $arrFieldInfo['readonly'] = $arrFieldInfo['readonly'] ?? false;
                        $arrFieldInfo['blocked']  = $arrFieldInfo['blocked'] ?? false;
                        $arrFieldInfo['required'] = $arrFieldInfo['required'] ?? false;
                        $arrFieldInfo['disabled'] = $arrFieldInfo['disabled'] ?? false;

                        $arrBlockGroups[$arrOrderInfo['applicant_group_id']]['group_fields'][] = $arrFieldInfo;

                        $arrPlacedFieldsIds[] = $arrOrderInfo['applicant_field_id'];
                    }
                }

                $arrBlocks[] = array(
                    'block_id'            => $arrBlockInfo['applicant_block_id'],
                    'block_is_contact'    => $arrBlockInfo['contact_block'],
                    'block_is_repeatable' => $arrBlockInfo['repeatable'],
                    'block_groups'        => $arrBlockGroups,
                );
            }

            // Unassigned fields
            if (!$booOnlyAssigned) {
                foreach ($arrCompanyFields as $arrCompanyFieldInfo) {
                    if (!in_array($arrCompanyFieldInfo['applicant_field_id'], $arrPlacedFieldsIds)) {
                        $arrUnassignedFields[] = $arrCompanyFieldInfo;
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'blocks'           => $arrBlocks,
            'available_fields' => $arrUnassignedFields,
        );
    }

    /**
     * Load fields info by specific field ids
     * @param array|int $arrFieldIds
     * @return array
     */
    public function getFieldsInfo($arrFieldIds)
    {
        $select = (new Select())
            ->from(array('f' => 'applicant_form_fields'))
            ->where(['f.applicant_field_id' => $arrFieldIds]);

        return $this->_db2->fetchAssoc($select);
    }

    /**
     * Load information about the field
     *
     * @param int $fieldId
     * @param int $companyId
     * @param bool $booLoadSimpleInfo - true to load info only from one table
     * @return array
     */
    public function getFieldInfo($fieldId, $companyId, $booLoadSimpleInfo = false)
    {
        $select = (new Select())
            ->from(array('f' => 'applicant_form_fields'))
            ->columns(array(Select::SQL_STAR, 'company_field_id' => 'applicant_field_unique_id'))
            ->where(['f.applicant_field_id' => (int)$fieldId]);

        $arrFieldInfo = $this->_db2->fetchRow($select);

        if (!$booLoadSimpleInfo) {
            if ($arrFieldInfo['applicant_field_unique_id'] != 'office') {
                $select = (new Select())
                    ->from(array('d' => 'applicant_form_default'))
                    ->columns(array('form_default_id' => 'applicant_form_default_id', 'field_id' => 'applicant_field_id', 'value', 'order'))
                    ->where(['d.applicant_field_id' => (int)$fieldId])
                    ->order('d.order');
            } else {
                $select = (new Select())
                    ->from(array('d' => 'divisions'))
                    ->columns(array('form_default_id' => 'division_id', 'value' => 'name', 'order'))
                    ->where(['d.company_id' => $companyId])
                    ->order('d.order');
            }
            $arrDefaults = $this->_db2->fetchAll($select);

            foreach ($arrDefaults as $defaultOption) {
                $arrFieldInfo['default_val'][] = array(
                    $defaultOption['form_default_id'],
                    $defaultOption['value'],
                    $defaultOption['order']
                );
            }

            $arrFieldTypes = $this->_parent->getFieldTypes()->getFieldTypes('applicant');
            foreach ($arrFieldTypes as $fType) {
                if ($fType['text_id'] == $arrFieldInfo['type']) {
                    $arrFieldInfo['type_label']          = $fType['label'];
                    $arrFieldInfo['booWithMaxLength']    = $fType['booWithMaxLength'];
                    $arrFieldInfo['booWithOptions']      = $fType['booWithOptions'];
                    $arrFieldInfo['booWithDefaultValue'] = $fType['booWithDefaultValue'];
                    $arrFieldInfo['booWithCustomHeight'] = $fType['booWithCustomHeight'];
                    break;
                }
            }

            if (is_array($arrFieldInfo) && array_key_exists('type', $arrFieldInfo)) {
                $arrFieldInfo['type'] = $this->_parent->getFieldTypes()->getFieldTypeIdByTextId(
                    $arrFieldInfo['type'],
                    $this->_parent->getMemberTypeNameById($arrFieldInfo['member_type_id'])
                );
            }
        }

        return $arrFieldInfo;
    }

    /**
     * Load field's label in relation to its id
     * @param $fieldId
     * @return string
     */
    public function getFieldName($fieldId)
    {
        $arrFieldInfo = $this->getFieldsInfo($fieldId);
        return $arrFieldInfo[$fieldId]['label'] ?? '';
    }

    /**
     * Delete field for the company
     *
     * @param $fieldId
     * @param $companyId
     * @return bool true on success
     */
    public function deleteField($fieldId, $companyId)
    {
        $booSuccess = false;
        try {
            // Check if this field exists in this company
            $arrFieldInfo = $this->getFieldInfo($fieldId, $companyId);
            if (is_array($arrFieldInfo) && count($arrFieldInfo)) {
                // Delete field
                $this->_db2->delete('applicant_form_data', ['applicant_field_id' => (int)$fieldId]);
                $this->_db2->delete('applicant_form_order', ['applicant_field_id' => (int)$fieldId]);
                $this->_db2->delete('applicant_form_fields_access', ['applicant_field_id' => (int)$fieldId]);
                $this->_db2->delete('applicant_form_default', ['applicant_field_id' => (int)$fieldId]);
                $this->_db2->delete('applicant_form_fields', ['applicant_field_id' => (int)$fieldId]);

                $this->clearCache($companyId, $arrFieldInfo['member_type_id']);
                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Unassign field for the company
     *
     * @param $fieldId
     * @param $groupId
     * @param $companyId
     * @return bool true on success
     */
    public function unassignField($fieldId, $groupId, $companyId)
    {
        $booSuccess = false;
        try {
            // Check if this field exists in this company
            $arrFieldInfo = $this->getFieldInfo($fieldId, $companyId);
            $arrWhere     = array();
            if (is_array($arrFieldInfo) && count($arrFieldInfo)) {
                // Unassign field
                $arrWhere['applicant_field_id'] = $fieldId;
                if (!empty($groupId)) {
                    $arrWhere['applicant_group_id'] = $groupId;
                }

                $this->_db2->delete('applicant_form_order', $arrWhere);
                $this->_db2->delete('applicant_form_fields_access', $arrWhere);

                $this->clearCache($companyId, $arrFieldInfo['member_type_id']);
                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Check if group exists in the company
     *
     * @param int $companyId
     * @param int $groupId
     * @return bool
     */
    public function isGroupInCompany($companyId, $groupId)
    {
        $booExists = false;

        $arrGroupInfo = $this->getGroupInfoById($groupId);
        if (is_array($arrGroupInfo) && count($arrGroupInfo) && $arrGroupInfo['company_id'] == $companyId) {
            $booExists = true;
        }

        return $booExists;
    }

    /**
     * Check if field exists in the group
     *
     * @param $fieldId
     * @param $groupId
     * @return bool
     */
    public function isFieldInGroup($fieldId, $groupId)
    {
        $booInGroup = false;

        $select = (new Select())
            ->from('applicant_form_order')
            ->columns(array('fields_count' => new Expression('COUNT(*)')))
            ->where(
                [
                    'applicant_field_id' => (int)$fieldId,
                    'applicant_group_id' => (int)$groupId
                ]
            );

        $totalGroups = $this->_db2->fetchOne($select);

        if ($totalGroups > 0) {
            $booInGroup = true;
        }

        return $booInGroup;
    }

    /**
     * Load order info by field id and group id
     * @param $fieldId
     * @param $groupId
     * @return array
     */
    public function getOrderInfo($fieldId, $groupId)
    {
        $select = (new Select())
            ->from('applicant_form_order')
            ->where(
                [
                    'applicant_field_id' => (int)$fieldId,
                    'applicant_group_id' => (int)$groupId
                ]
            );

        return $this->_db2->fetchRow($select);
    }


    /**
     * Check if group contains fields
     *
     * @param $groupId
     * @return bool
     */
    public function hasGroupFields($groupId)
    {
        $booHasFields = false;

        $select = (new Select())
            ->from('applicant_form_order')
            ->columns(array('fields_count' => new Expression('COUNT(*)')))
            ->where(['applicant_group_id' => (int)$groupId]);

        $totalGroups = $this->_db2->fetchOne($select);

        if ($totalGroups > 0) {
            $booHasFields = true;
        }

        return $booHasFields;
    }

    /**
     * Get assigned fields for the group
     *
     * @param $groupId
     * @return array of field ids
     */
    public function getGroupFields($groupId)
    {
        $select = (new Select())
            ->from('applicant_form_order')
            ->columns(['applicant_field_id'])
            ->where(['applicant_group_id' => (int)$groupId]);

        return $this->_db2->fetchCol($select);
    }

    /**
     * Create fields group
     *
     * @param int $companyId
     * @param int $memberTypeId
     * @param int $applicantBlockId
     * @param string $groupName
     * @param bool $isGroupCollapsed
     * @param int $colsCount
     * @return int|bool - group id on success, false on failure
     */
    public function createGroup($companyId, $memberTypeId, $applicantBlockId, $groupName, $isGroupCollapsed, $colsCount = 3)
    {
        try {
            $maxOrder  = -1;
            $arrGroups = $this->getCompanyGroupsInBlock($companyId, $memberTypeId, $applicantBlockId);
            foreach ($arrGroups as $arrGroupInfo) {
                $maxOrder = max($maxOrder, $arrGroupInfo['order']);
            }

            $groupId = $this->_db2->insert(
                'applicant_form_groups',
                [
                    'applicant_block_id' => $applicantBlockId,
                    'company_id'         => $companyId,
                    'title'              => $groupName,
                    'cols_count'         => (int)$colsCount,
                    'collapsed'          => $isGroupCollapsed ? 'Y' : 'N',
                    'order'              => $maxOrder + 1
                ]
            );

            $arrBlockInfo = $this->getBlockInfo($applicantBlockId);
            $this->clearCache($companyId, $arrBlockInfo['member_type_id']);
        } catch (Exception $e) {
            $groupId = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $groupId;
    }

    /**
     * Update fields group info
     *
     * @param $memberTypeId
     * @param $groupId
     * @param $groupName
     * @param bool $isGroupCollapsed
     * @param int $colsCount
     * @return bool true on success
     */
    public function updateGroup($memberTypeId, $groupId, $groupName, $isGroupCollapsed, $colsCount = 3)
    {
        $booSuccess = false;

        try {
            $arrGroupInfo = $this->getGroupInfoById($groupId);
            if (is_array($arrGroupInfo) && count($arrGroupInfo)) {
                $this->_db2->update(
                    'applicant_form_groups',
                    [
                        'title'      => $groupName,
                        'cols_count' => (int)$colsCount,
                        'collapsed'  => $isGroupCollapsed ? 'Y' : 'N',
                    ],
                    ['applicant_group_id' => $groupId]
                );

                // Clear cache for company we updated group
                $this->clearCache($arrGroupInfo['company_id'], $memberTypeId);

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Delete group
     *
     * @param int $groupId
     * @return bool true on success
     */
    public function deleteGroup($groupId)
    {
        $booSuccess = false;
        try {
            $arrGroupInfo = $this->getGroupInfoById($groupId);
            if (is_array($arrGroupInfo) && count($arrGroupInfo)) {
                // Clear all related tables
                $arrTables = array('applicant_form_order', 'applicant_form_groups');
                foreach ($arrTables as $table) {
                    $this->_db2->delete($table, ['applicant_group_id' => $groupId]);
                }

                // Check if we need delete parent block
                $arrParentBlockGroups = $this->getBlockGroups($arrGroupInfo['applicant_block_id']);
                if (!count($arrParentBlockGroups)) {
                    $this->deleteBlock($arrGroupInfo['company_id'], $arrGroupInfo['member_type_id'], $arrGroupInfo['applicant_block_id']);
                } else {
                    $this->clearCache($arrGroupInfo['company_id'], $arrGroupInfo['member_type_id']);
                }

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Load company group info by its id
     *
     * @param int $groupId
     * @return array
     */
    public function getGroupInfoById($groupId)
    {
        $select = (new Select())
            ->from(array('g' => 'applicant_form_groups'))
            ->join(array('b' => 'applicant_form_blocks'), 'g.applicant_block_id = b.applicant_block_id', 'member_type_id', Select::JOIN_RIGHT)
            ->where(['g.applicant_group_id' => (int)$groupId]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load company id(s) be specific fields group id(s)
     *
     * @param array|int $groupId
     * @return array
     */
    public function getCompanyIdByGroupId($groupId)
    {
        $select = (new Select())
            ->from('applicant_form_groups')
            ->columns(['company_id'])
            ->where(['applicant_group_id' => $groupId])
            ->group('company_id');

        return $this->_db2->fetchCol($select);
    }

    /**
     * Load int field id for specific company by provided string unique field id
     *
     * @param string $uniqueFieldId
     * @param int|array $memberType
     * @param int $companyId
     * @return int field id
     */
    public function getCompanyFieldIdByUniqueFieldId($uniqueFieldId, $memberType, $companyId = 0)
    {
        if (empty($companyId)) {
            $companyId = $this->_auth->getCurrentUserCompanyId();
        }

        $select = (new Select())
            ->from('applicant_form_fields')
            ->columns(['applicant_field_id'])
            ->where(
                [
                    'company_id'                => (int)$companyId,
                    'member_type_id'            => $memberType,
                    'applicant_field_unique_id' => $uniqueFieldId
                ]
            );

        return $this->_db2->fetchOne($select);
    }

    /**
     * Load options list for specific field(s)
     *
     * @param array|int $arrFieldsIds
     * @return array
     */
    public function getFieldsOptions($arrFieldsIds)
    {
        $arrResult = array();
        if (!empty($arrFieldsIds)) {
            $select = (new Select())
                ->from('applicant_form_default')
                ->where(['applicant_field_id' => $arrFieldsIds])
                ->order('order');

            $arrResult = $this->_db2->fetchAll($select);
        }

        return $arrResult;
    }

    /**
     * Load options list for specific field (find by unique id)
     *
     * @param string $companyFieldId
     * @param int $companyId
     * @return array
     */
    public function getFieldValueByCompanyFieldId($companyFieldId, $companyId = 0)
    {
        $companyId = empty($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId;
        $select    = (new Select())
            ->from(array('fd' => 'applicant_form_default'))
            ->join(array('f' => 'applicant_form_fields'), 'f.applicant_field_id = fd.applicant_field_id', [], Select::JOIN_LEFT)
            ->where(
                [
                    'f.company_id'                => (int)$companyId,
                    'f.applicant_field_unique_id' => $companyFieldId
                ]
            )
            ->order('fd.order ASC');

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load options list for all fields for specific company
     * @param int $companyId
     * @return array
     */
    public function getCompanyAllFieldsOptions($companyId = 0)
    {
        $companyId = empty($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId;

        $select = (new Select())
            ->from(array('d' => 'applicant_form_default'))
            ->join(array('f' => 'applicant_form_fields'), 'd.applicant_field_id = f.applicant_field_id', [], Select::JOIN_LEFT)
            ->where(['f.company_id' => (int)$companyId]);

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load grouped options list for specific field(s)
     * @param array $arrFields
     * @param bool $booTheseAreIds true if provided array contains ids already
     * @return array
     */
    public function getGroupedFieldsOptions($arrFields, $booTheseAreIds = false)
    {
        $arrFieldIds = array();
        if ($booTheseAreIds) {
            $arrFieldIds = $arrFields;
        } else {
            foreach ($arrFields as $arrGroupedGroups) {
                if (is_array($arrGroupedGroups) && array_key_exists('fields', $arrGroupedGroups)) {
                    foreach ($arrGroupedGroups['fields'] as $arrGroupInfo) {
                        foreach ($arrGroupInfo['fields'] as $arrFieldInfo) {
                            $arrFieldIds[] = $arrFieldInfo['field_id'];
                        }
                    }
                }
            }

            $arrFieldIds = array_unique($arrFieldIds);
        }

        $arrOptions = array();
        if (count($arrFieldIds)) {
            $arrAllOptions = $this->getFieldsOptions($arrFieldIds);
            foreach ($arrAllOptions as $arrOptionInfo) {
                $arrOptions[$arrOptionInfo['applicant_field_id']][] = array(
                    'option_id'   => $arrOptionInfo['applicant_form_default_id'],
                    'option_name' => $arrOptionInfo['value'],
                );
            }
        }

        return $arrOptions;
    }

    /**
     * Load search types list (used in advanced search)
     *
     * @param bool $booIdOnly
     * @param bool $booExceptCases
     * @return array
     */
    public function getAdvancedSearchTypesList($booIdOnly = false, $booExceptCases = false)
    {
        $arrTypes = array();

        $arrTypes[] = array(
            'search_for_id'    => 'accounting',
            'search_for_name'  => $this->_tr->translate('Accounting'),
            'search_for_group' => $this->_tr->translate('Accounting')
        );

        // Show employer option only if current company has access to it
        if ($this->_auth->isCurrentUserSuperadmin() || $this->_auth->isCurrentMemberCompanyEmployerModuleEnabled()) {
            $arrTypes[] = array(
                'search_for_id'    => 'employer',
                'search_for_name'  => $this->_tr->translate('Employers'),
                'search_for_group' => $this->_tr->translate('Employer Client Fields')
            );
        }

        $arrTypes[] = array(
            'search_for_id'    => 'individual',
            'search_for_name'  => $this->_tr->translate('Individuals'),
            'search_for_group' => $this->_tr->translate('Individual Client Fields')
        );

        if (!$booExceptCases) {
            $arrTypes[] = array(
                'search_for_id'    => 'case',
                'search_for_name'  => $this->_tr->translate('Cases'),
                'search_for_group' => $this->_tr->translate('Case Fields')
            );

            if ($this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled()) {
                $arrTypes[] = array(
                    'search_for_id'    => 'tag_percentage',
                    'search_for_name'  => $this->_tr->translate('Tag Percentage'),
                    'search_for_group' => $this->_tr->translate('Tag Percentage Fields')
                );
            }
        }

        if ($booIdOnly) {
            $arrResult = array();
            foreach ($arrTypes as $arrTypeInfo) {
                $arrResult[] = $arrTypeInfo['search_for_id'];
            }
        } else {
            $arrResult = $arrTypes;
        }

        return $arrResult;
    }

    /**
     * Load filter combobox options for advanced search and auto tasks' conditions
     *
     * @param bool $booForAutomaticReminderConditions
     * @return array
     */
    public function getSearchFiltersList($booForAutomaticReminderConditions = false)
    {
        $arrSearchFilters = array(
            'yes_no' => array(
                array('yes', $this->_tr->translate('Yes')),
                array('no', $this->_tr->translate('No'))
            ),

            'combo' => array(
                array('is', $this->_tr->translate('is')),
                array('is_one_of', $this->_tr->translate('is one of')),
                array('is_not', $this->_tr->translate("is not")),
                array('is_none_of', $this->_tr->translate('is none of')),
            ),

            'billing_frequency' => array(
                array(0, $this->_tr->translate('Not set')),
                array(1, $this->_tr->translate('Monthly')),
                array(2, $this->_tr->translate('Annually')),
                array(3, $this->_tr->translate('Biannually')),
            ),

            'number' => array(
                array('equal', '='),
                array('not_equal', '<>'),
                array('less', '<'),
                array('less_or_equal', '<='),
                array('more', '>'),
                array('more_or_equal', '>='),
            ),

            'text' => array(
                array('contains', $this->_tr->translate('contains')),
                array('does_not_contain', $this->_tr->translate("does not contain")),
                array('is', $this->_tr->translate('is')),
                array('is_not', $this->_tr->translate("is not")),
                array('starts_with', $this->_tr->translate('starts with')),
                array('ends_with', $this->_tr->translate('ends with')),
                array('is_empty', $this->_tr->translate('is empty')),
                array('is_not_empty', $this->_tr->translate('is not empty')),
            ),

            'multiple_text_fields' => array(
                array('contains', $this->_tr->translate('contains')),
                array('does_not_contain', $this->_tr->translate("does not contain")),
                array('is_empty', $this->_tr->translate('is empty')),
                array('is_not_empty', $this->_tr->translate('is not empty')),
            ),

            'date' => array(
                array('is', $this->_tr->translate('is')),
                array('is_not', $this->_tr->translate('is not')),
                array('is_before', $this->_tr->translate('is before')),
                array('is_after', $this->_tr->translate('is after')),
                array('is_empty', $this->_tr->translate('is empty')),
                array('is_not_empty', $this->_tr->translate('is not empty')),
                array('is_between_2_dates', $this->_tr->translate('is between 2 dates')),
                array('is_between_today_and_date', $this->_tr->translate('is between today and a date')),
                array('is_between_date_and_today', $this->_tr->translate('is between a date and today')),
                array('is_in_the_next', $booForAutomaticReminderConditions ? $this->_tr->translate('less the period is prior to today') : $this->_tr->translate('is in the next')),
                array('is_in_the_previous', $booForAutomaticReminderConditions ? $this->_tr->translate('is in the next (or already passed)') : $this->_tr->translate('is in the previous')),
                array('is_since_start_of_the_year_to_now', $this->_tr->translate('is since the start of the year to now')),
                array('is_from_today_to_the_end_of_year', $this->_tr->translate('is from today to the end of the year')),
                array('is_in_this_month', $this->_tr->translate('is in this month')),
                array('is_in_this_year', $this->_tr->translate('is in this year')),
            ),

            'date_repeatable' => array(
                array('is', $this->_tr->translate('is')),
                array('is_not', $this->_tr->translate('is not')),
                array('is_before', $this->_tr->translate('is before')),
                array('is_after', $this->_tr->translate('is after')),
                array('is_empty', $this->_tr->translate('is empty')),
                array('is_not_empty', $this->_tr->translate('is not empty')),
                array('is_between_2_dates', $this->_tr->translate('is between 2 dates (ignore year)')),
                array('is_between_today_and_date', $this->_tr->translate('is between today and a date (ignore year)')),
                array('is_between_date_and_today', $this->_tr->translate('is between a date and today (ignore year)')),
                array('is_in_the_next', $booForAutomaticReminderConditions ? $this->_tr->translate('less the period is prior to today (ignore year)') : $this->_tr->translate('is in the next (ignore year)')),
                array('is_in_the_previous', $booForAutomaticReminderConditions ? $this->_tr->translate('is in the next (or already passed) (ignore year)') : $this->_tr->translate('is in the previous (ignore year)')),
                array('is_since_start_of_the_year_to_now', $this->_tr->translate('is since the start of the year to now')),
                array('is_from_today_to_the_end_of_year', $this->_tr->translate('is from today to the end of the year')),
                array('is_in_this_month', $this->_tr->translate('is in this month (ignore year)')),
                array('is_in_this_year', $this->_tr->translate('is in this year')),
            ),

            'checkbox' => array(
                array('is_not_empty', $this->_tr->translate('Is Checked')),
                array('is_empty', $this->_tr->translate('Is Not Checked'))
            ),
        );

        if ($booForAutomaticReminderConditions) {
            $arrSearchFilters['is'][]   = array('is', $this->_tr->translate('is'));
            $arrSearchFilters['text'][] = array('is_one_of', $this->_tr->translate('is one of'));
            $arrSearchFilters['text'][] = array('is_none_of', $this->_tr->translate('is none of'));
        }

        return $arrSearchFilters;
    }

    /**
     * Load filter option label for specific field type and option id
     *
     * @param $filterTypeId
     * @param $filterOptionId
     * @param $booForAutomaticReminderConditions
     * @return string
     */
    public function getSearchFilterLabelByTypeAndId($filterTypeId, $filterOptionId, $booForAutomaticReminderConditions = false)
    {
        $filterOptionLabel = '';
        $arrFilters        = $this->getSearchFiltersList($booForAutomaticReminderConditions);

        switch ($filterTypeId) {
            case 'float':
            case 'number':
            case 'auto_calculated':
                $fieldTypeForCondition = 'number';
                break;

            case 'date':
            case 'date_repeatable':
                $fieldTypeForCondition = 'date';
                break;

            case 'combo':
            case 'agents':
            case 'office':
            case 'office_multi':
            case 'assigned_to':
            case 'staff_responsible_rma':
            case 'contact_sales_agent':
            case 'authorized_agents':
            case 'employer_contacts':
            case 'categories':
            case 'case_type':
            case 'case_status':
            case 'country':
                $fieldTypeForCondition = 'combo';
                break;

            case 'checkbox':
                $fieldTypeForCondition = 'checkbox';
                break;

            case 'multiple_text_fields':
                $fieldTypeForCondition = 'multiple_text_fields';
                break;

            // case 'textfield':
            // case 'special':
            default:
                $fieldTypeForCondition = 'text';
                break;
        }

        $arrFilterOptions = $arrFilters[$fieldTypeForCondition] ?? array();
        foreach ($arrFilterOptions as $arrFilterOptionInfo) {
            if ($arrFilterOptionInfo[0] == $filterOptionId) {
                $filterOptionLabel = $arrFilterOptionInfo[1];
                break;
            }
        }

        return $filterOptionLabel;
    }


    /**
     * Load option value for specific option by its id
     * @param $intOptionId
     * @return string
     */
    public function getDefaultFieldOptionValue($intOptionId)
    {
        $select = (new Select())
            ->from('applicant_form_default')
            ->columns(['value'])
            ->where(['applicant_form_default_id' => $intOptionId]);

        return $this->_db2->fetchOne($select);
    }

    /**
     * Allow full access rights to ALL "admin" roles AND to all other roles defined for the field
     *
     * @param int $companyId
     * @param int $fieldId
     * @param int $groupId
     * @param array $arrFieldDefaultAccess
     */
    public function allowFieldAccessForCompanyAdmin($companyId, $fieldId, $groupId, $arrFieldDefaultAccess = array())
    {
        $arrNewAccessRights = array();

        // Allow access to this new field to ALL admin roles
        $arrAdminRolesIds = $this->_roles->getCompanyRoles($companyId, 0, false, true, array('admin'));
        if (is_array($arrAdminRolesIds) && !empty($arrAdminRolesIds)) {
            foreach ($arrAdminRolesIds as $adminRoleId) {
                $arrNewAccessRights[] = array(
                    'role_id'            => $adminRoleId,
                    'applicant_group_id' => $groupId,
                    'applicant_field_id' => $fieldId,
                    'status'             => 'F'
                );
            }
        }

        // Allow access to this new field to other roles defined by the company admin
        foreach ($arrFieldDefaultAccess as $roleId => $access) {
            if (!empty($roleId) && !in_array($roleId, $arrAdminRolesIds) && !empty($access)) {
                $arrNewAccessRights[] = array(
                    'role_id'            => $roleId,
                    'applicant_group_id' => $groupId,
                    'applicant_field_id' => $fieldId,
                    'status'             => $access
                );
            }
        }

        foreach ($arrNewAccessRights as $arrNewAccessRightsRow) {
            $this->_db2->insert('applicant_form_fields_access', $arrNewAccessRightsRow);
        }
    }

    /**
     * @param int $roleId
     * @param array $arrAccessRights
     * @return bool
     */
    public function updateRoleAccess($roleId, $arrAccessRights)
    {
        try {
            $this->_db2->delete('applicant_form_fields_access', ['role_id' => (int)$roleId]);
            foreach ($arrAccessRights as $groupId => $arrFieldsAccess) {
                foreach ($arrFieldsAccess as $fieldId => $accessLevel) {
                    $rights = array(
                        'role_id'            => (int)$roleId,
                        'applicant_group_id' => (int)$groupId,
                        'applicant_field_id' => (int)$fieldId,
                        'status'             => $accessLevel,
                    );

                    $this->_db2->insert('applicant_form_fields_access', $rights);
                }
            }

            $this->updateDefaultAccessRightsForRole($roleId, $arrAccessRights);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Create a copy of fields, groups and access rights from the default company
     *
     * @param int $fromCompanyId - company id we need to load settings from
     * @param int $toCompanyId - we need to create copy for
     * @param array $arrRoles - we need to allow full access to the fields
     * @return array
     */
    public function createDefaultCompanyFieldsAndGroups($fromCompanyId, $toCompanyId, $arrRoles)
    {
        $query           = '';
        $booSuccess      = false;
        $arrMappingTypes = $arrMappingFields = $arrMappingGroups = $arrMappingBlocks = $arrMappingDefaults = array();

        try {
            // Create types copy
            $select = (new Select())
                ->from('applicant_types')
                ->where(['company_id' => (int)$fromCompanyId]);

            $arrDefaultTypes = $this->_db2->fetchAll($select);
            if (is_array($arrDefaultTypes)) {
                foreach ($arrDefaultTypes as $defaultTypeInfo) {
                    $arrInsertTypes = array(
                        'member_type_id'      => $defaultTypeInfo['member_type_id'],
                        'company_id'          => $toCompanyId,
                        'applicant_type_name' => $defaultTypeInfo['applicant_type_name'],
                        'is_system'           => $defaultTypeInfo['is_system']
                    );

                    $arrMappingTypes[$defaultTypeInfo['applicant_type_id']] = $this->_db2->insert('applicant_types', $arrInsertTypes);
                }
            }


            // Create blocks copy
            $select = (new Select())
                ->from('applicant_form_blocks')
                ->where(['company_id' => (int)$fromCompanyId]);

            $arrDefaultBlocks = $this->_db2->fetchAll($select);

            if (is_array($arrDefaultBlocks)) {
                foreach ($arrDefaultBlocks as $defaultBlockInfo) {
                    $arrInsertBlocks = array(
                        'member_type_id'    => $defaultBlockInfo['member_type_id'],
                        'applicant_type_id' => empty($defaultBlockInfo['applicant_type_id']) ? null : $arrMappingTypes[$defaultBlockInfo['applicant_type_id']],
                        'company_id'        => $toCompanyId,
                        'contact_block'     => $defaultBlockInfo['contact_block'],
                        'repeatable'        => $defaultBlockInfo['repeatable'],
                        'order'             => $defaultBlockInfo['order']
                    );

                    $arrMappingBlocks[$defaultBlockInfo['applicant_block_id']] = $this->_db2->insert('applicant_form_blocks', $arrInsertBlocks);
                }
            }

            // Create groups copy
            $select = (new Select())
                ->from('applicant_form_groups')
                ->where(['company_id' => (int)$fromCompanyId]);

            $arrDefaultGroups = $this->_db2->fetchAll($select);

            if (is_array($arrDefaultGroups)) {
                foreach ($arrDefaultGroups as $defaultGroupInfo) {
                    $arrInsertGroups = array(
                        'applicant_block_id' => $arrMappingBlocks[$defaultGroupInfo['applicant_block_id']],
                        'company_id'         => $toCompanyId,
                        'title'              => $defaultGroupInfo['title'],
                        'cols_count'         => $defaultGroupInfo['cols_count'],
                        'collapsed'          => $defaultGroupInfo['collapsed'],
                        'order'              => $defaultGroupInfo['order']
                    );

                    $arrMappingGroups[$defaultGroupInfo['applicant_group_id']] = $this->_db2->insert('applicant_form_groups', $arrInsertGroups);
                }
            }

            // Create fields copy
            $select = (new Select())
                ->from('applicant_form_fields')
                ->where(['company_id' => (int)$fromCompanyId]);

            $arrDefaultFields = $this->_db2->fetchAll($select);

            if (is_array($arrDefaultFields)) {
                foreach ($arrDefaultFields as $defaultFieldInfo) {
                    $defaultFieldId = $defaultFieldInfo['applicant_field_id'];
                    unset($defaultFieldInfo['applicant_field_id']);
                    $defaultFieldInfo['company_id']    = $toCompanyId;
                    $arrMappingFields[$defaultFieldId] = $this->_db2->insert('applicant_form_fields', $defaultFieldInfo);
                }
            }

            // Copy fields options
            if (is_array($arrMappingFields) && count($arrMappingFields)) {
                $select = (new Select())
                    ->from('applicant_form_default')
                    ->where(
                        [
                            (new Where())->in('applicant_field_id', array_keys($arrMappingFields))
                        ]
                    );

                $arrDefaultFieldsOptions = $this->_db2->fetchAll($select);

                if (is_array($arrDefaultFieldsOptions)) {
                    foreach ($arrDefaultFieldsOptions as $defaultFieldOptionInfo) {
                        $arrMappingDefaults[$defaultFieldOptionInfo['applicant_form_default_id']] = $this->_db2->insert(
                            'applicant_form_default',
                            [
                                'applicant_field_id' => (int)$arrMappingFields[$defaultFieldOptionInfo['applicant_field_id']],
                                'value'              => $defaultFieldOptionInfo['value'],
                                'order'              => (int)$defaultFieldOptionInfo['order']
                            ],
                            null,
                            false
                        );
                    }
                }
            }

            // Copy fields location
            if (is_array($arrDefaultGroups) && count($arrDefaultGroups)) {
                $select = (new Select())
                    ->from('applicant_form_order')
                    ->where(
                        [
                            (new Where())->in('applicant_group_id', array_keys($arrMappingGroups))
                        ]
                    );

                $arrDefaultOrder = $this->_db2->fetchAll($select);

                if (is_array($arrDefaultOrder)) {
                    foreach ($arrDefaultOrder as $defaultOrderInfo) {
                        $this->_db2->insert(
                            'applicant_form_order',
                            [
                                'applicant_group_id' => (int)$arrMappingGroups[$defaultOrderInfo['applicant_group_id']],
                                'applicant_field_id' => (int)$arrMappingFields[$defaultOrderInfo['applicant_field_id']],
                                'use_full_row'       => $defaultOrderInfo['use_full_row'],
                                'field_order'        => (int)$defaultOrderInfo['field_order']
                            ],
                            null,
                            false
                        );
                    }
                }
            }

            // Copy fields access
            if (is_array($arrMappingFields) && count($arrMappingFields) && count($arrRoles)) {
                $select = (new Select())
                    ->from('applicant_form_fields_access')
                    ->where(
                        [
                            (new Where())->in('applicant_field_id', array_keys($arrMappingFields))
                        ]
                    );

                $arrDefaultFieldsAccess = $this->_db2->fetchAll($select);

                if (is_array($arrDefaultFieldsAccess)) {
                    foreach ($arrDefaultFieldsAccess as $defaultFieldAccessInfo) {
                        if (isset($arrRoles[$defaultFieldAccessInfo['role_id']])) {
                            $this->_db2->insert(
                                'applicant_form_fields_access',
                                [
                                    'role_id'            => (int)$arrRoles[$defaultFieldAccessInfo['role_id']],
                                    'applicant_group_id' => (int)$arrMappingGroups[$defaultFieldAccessInfo['applicant_group_id']],
                                    'applicant_field_id' => (int)$arrMappingFields[$defaultFieldAccessInfo['applicant_field_id']],
                                    'status'             => $defaultFieldAccessInfo['status']
                                ],
                                null,
                                false
                            );
                        }
                    }
                }
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString() . $query);
        }


        return array(
            'success'         => $booSuccess,
            'mappingTypes'    => $arrMappingTypes,
            'mappingFields'   => $arrMappingFields,
            'mappingGroups'   => $arrMappingGroups,
            'mappingBlocks'   => $arrMappingBlocks,
            'mappingDefaults' => $arrMappingDefaults
        );
    }

    /**
     * Copy blocks, groups, fields order and fields access from one Applicant type to another one
     * @param int $fromApplicantTypeId
     * @param int $toApplicantTypeId
     * @return bool true on success, otherwise false
     */
    public function createApplicantTypeCopy($fromApplicantTypeId, $toApplicantTypeId)
    {
        try {
            $arrMappingBlocks = array();
            $arrMappingGroups = array();

            // Copy blocks
            $select = (new Select())
                ->from('applicant_form_blocks')
                ->where(['applicant_type_id' => (int)$fromApplicantTypeId]);

            $arrDefaultBlocks = $this->_db2->fetchAll($select);

            if (is_array($arrDefaultBlocks)) {
                foreach ($arrDefaultBlocks as $defaultBlockInfo) {
                    $arrInsertBlocks = array(
                        'member_type_id'    => $defaultBlockInfo['member_type_id'],
                        'applicant_type_id' => $toApplicantTypeId,
                        'company_id'        => $defaultBlockInfo['company_id'],
                        'contact_block'     => $defaultBlockInfo['contact_block'],
                        'repeatable'        => $defaultBlockInfo['repeatable'],
                        'order'             => $defaultBlockInfo['order']
                    );

                    $arrMappingBlocks[$defaultBlockInfo['applicant_block_id']] = $this->_db2->insert('applicant_form_blocks', $arrInsertBlocks);
                }
            }

            // Copy groups
            $arrDefaultGroups = array();
            if (is_array($arrMappingBlocks) && count($arrMappingBlocks)) {
                $select = (new Select())
                    ->from('applicant_form_groups')
                    ->where(
                        [
                            (new Where())->in('applicant_block_id', array_keys($arrMappingBlocks))
                        ]
                    );

                $arrDefaultGroups = $this->_db2->fetchAll($select);
            }

            if (is_array($arrDefaultGroups) && count($arrDefaultGroups)) {
                foreach ($arrDefaultGroups as $defaultGroupInfo) {
                    $arrInsertGroups = array(
                        'applicant_block_id' => $arrMappingBlocks[$defaultGroupInfo['applicant_block_id']],
                        'company_id'         => $defaultGroupInfo['company_id'],
                        'title'              => $defaultGroupInfo['title'],
                        'cols_count'         => $defaultGroupInfo['cols_count'],
                        'collapsed'          => $defaultGroupInfo['collapsed'],
                        'order'              => $defaultGroupInfo['order']
                    );

                    $arrMappingGroups[$defaultGroupInfo['applicant_group_id']] = $this->_db2->insert('applicant_form_groups', $arrInsertGroups);
                }
            }

            // Copy fields location
            if (is_array($arrDefaultGroups) && count($arrDefaultGroups)) {
                $select = (new Select())
                    ->from('applicant_form_order')
                    ->where(
                        [
                            (new Where())->in('applicant_group_id', array_keys($arrMappingGroups))
                        ]
                    );

                $arrDefaultOrder = $this->_db2->fetchAll($select);

                if (is_array($arrDefaultOrder)) {
                    foreach ($arrDefaultOrder as $defaultOrderInfo) {
                        $this->_db2->insert(
                            'applicant_form_order',
                            [
                                'applicant_group_id' => (int)$arrMappingGroups[$defaultOrderInfo['applicant_group_id']],
                                'applicant_field_id' => (int)$defaultOrderInfo['applicant_field_id'],
                                'use_full_row'       => $defaultOrderInfo['use_full_row'],
                                'field_order'        => (int)$defaultOrderInfo['field_order']
                            ]
                        );
                    }
                }
            }

            // Copy fields access
            if (is_array($arrMappingGroups) && count($arrMappingGroups)) {
                $select = (new Select())
                    ->from('applicant_form_fields_access')
                    ->where(
                        [
                            (new Where())->in('applicant_group_id', array_keys($arrMappingGroups))
                        ]
                    );

                $arrDefaultFieldsAccess = $this->_db2->fetchAll($select);

                if (is_array($arrDefaultFieldsAccess)) {
                    foreach ($arrDefaultFieldsAccess as $defaultFieldAccessInfo) {
                        $this->_db2->insert(
                            'applicant_form_fields_access',
                            [
                                'role_id'            => (int)$defaultFieldAccessInfo['role_id'],
                                'applicant_group_id' => (int)$arrMappingGroups[$defaultFieldAccessInfo['applicant_group_id']],
                                'applicant_field_id' => (int)$defaultFieldAccessInfo['applicant_field_id'],
                                'status'             => $defaultFieldAccessInfo['status']
                            ]
                        );
                    }
                }
            }

            $arrApplicantTypeInfo = $this->_parent->getApplicantTypes()->getTypeInfo($fromApplicantTypeId);
            if (is_array($arrApplicantTypeInfo) && count($arrApplicantTypeInfo)) {
                $this->clearCache($arrApplicantTypeInfo['company_id'], $arrApplicantTypeInfo['member_type_id']);
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Load saved value for specific client for specific field
     *
     * @param $memberId
     * @param $fieldId
     * @param int $row
     * @return string
     */
    public function getFieldDataValue($memberId, $fieldId, $row = 0)
    {
        $strValue = '';
        if (!empty($fieldId) && !empty($memberId)) {
            $select = (new Select())
                ->from(array('d' => 'applicant_form_data'))
                ->columns(['value'])
                ->join(array('f' => 'applicant_form_fields'), 'd.applicant_field_id = f.applicant_field_id', 'encrypted', Select::JOIN_LEFT)
                ->where(
                    [
                        'd.applicant_field_id' => (int)$fieldId,
                        'd.applicant_id'       => $memberId,
                        'd.row'                => (int)$row
                    ]
                );

            $arrData = $this->_db2->fetchRow($select);

            // Decode field value if it was encoded
            if (is_array($arrData) && array_key_exists('value', $arrData) && array_key_exists('encrypted', $arrData)) {
                $strValue = $arrData['encrypted'] == 'Y' ? $this->_encryption->decode($arrData['value']) : $arrData['value'];
            }
        }

        return $strValue;
    }

    /**
     * Load field data for specific applicant and field ids
     *
     * @param int $fieldId
     * @param int $memberId
     * @param bool $booDecodeData - tru to decrypt encrypted data
     * @return array
     */
    public function getFieldData($fieldId, $memberId = 0, $booDecodeData = true)
    {
        $arrValues = array();
        if (!empty($fieldId)) {
            $select = (new Select())
                ->from(array('d' => 'applicant_form_data'))
                ->join(array('f' => 'applicant_form_fields'), 'd.applicant_field_id = f.applicant_field_id', array('encrypted', 'type'), Select::JOIN_LEFT)
                ->where(['d.applicant_field_id' => (int)$fieldId]);

            if (!empty($memberId)) {
                $select->where(
                    [
                        'd.applicant_id' => $memberId
                    ]
                );
            }

            $arrValues = $this->_db2->fetchAll($select);


            if ($booDecodeData) {
                foreach ($arrValues as $key => $arrValueInfo) {
                    if ($arrValueInfo['encrypted'] == 'Y') {
                        $arrValues[$key]['value'] = $this->_encryption->decode($arrValueInfo['value']);
                    }
                }
            }
        }

        return $arrValues;
    }

    /**
     * Load saved data for specific applicant for specific fields
     *
     * @param int $applicantId
     * @param array $arrFieldIds
     * @param bool $booDecodeData true to decode encrypted fields
     * @return array
     */
    public function getApplicantFieldsData($applicantId, $arrFieldIds, $booDecodeData = true)
    {
        $arrValues = array();
        if (!empty($applicantId) && is_array($arrFieldIds) && count($arrFieldIds)) {
            $select = (new Select())
                ->from(array('d' => 'applicant_form_data'))
                ->join(array('f' => 'applicant_form_fields'), 'd.applicant_field_id = f.applicant_field_id', array('applicant_field_unique_id', 'encrypted'), Select::JOIN_LEFT)
                ->where(
                    [
                        'd.applicant_id'       => (int)$applicantId,
                        'd.applicant_field_id' => $arrFieldIds
                    ]
                );

            $arrValues = $this->_db2->fetchAll($select);

            if ($booDecodeData) {
                foreach ($arrValues as $key => $arrValueInfo) {
                    if ($arrValueInfo['encrypted'] == 'Y') {
                        $arrValues[$key]['value'] = $this->_encryption->decode($arrValueInfo['value']);
                    }
                }
            }
        }

        return $arrValues;
    }

    /**
     * Load repeatable fields data list for employer
     *
     * @param string $type - can be
     * 'employer_contacts',
     * 'employer_engagements',
     * 'employer_legal_entities',
     * 'employer_locations',
     * 'employer_third_party_representatives'
     *
     * @param int|array $employerId - we need load data saved for
     * @return array
     */
    public function getEmployerRepeatableFieldsGrouped($type, $employerId)
    {
        $arrData = array();
        if (!empty($employerId) && (is_numeric($employerId) || is_array($employerId))) {
            // Get employer saved info
            $select = (new Select())
                ->from(array('d' => 'applicant_form_data'))
                ->join(array('f' => 'applicant_form_fields'), 'd.applicant_field_id = f.applicant_field_id', array('applicant_field_unique_id', 'encrypted'))
                ->where(['d.applicant_id' => $employerId])
                ->order('d.row ASC');

            $arrSavedData = $this->_db2->fetchAll($select);

            if (is_array($arrSavedData) && count($arrSavedData)) {
                // Decrypt saved value if needed
                foreach ($arrSavedData as $key => $arrValueInfo) {
                    if ($arrValueInfo['encrypted'] == 'Y') {
                        $arrSavedData[$key]['value'] = $this->_encryption->decode($arrValueInfo['value']);
                    }
                }

                // Get fields list we need to load and use during grouping
                switch ($type) {
                    case 'employer_contacts':
                        $booUseAUNames = false;
                        foreach ($arrSavedData as $arrSavedDataInfo) {
                            if (in_array($arrSavedDataInfo['applicant_field_unique_id'], array('given_names', 'family_name'))) {
                                $booUseAUNames = true;
                                break;
                            }
                        }

                        if ($booUseAUNames) {
                            $arrFieldsToGroup = array('given_names', 'family_name');
                            $strFormat        = '%given_names %family_name';
                        } else {
                            $arrFieldsToGroup = array('first_name', 'last_name');
                            $strFormat        = '%first_name %last_name';
                        }
                        break;

                    case 'employer_engagements':
                        $arrFieldsToGroup = array('engagement_name', 'engagement_number');
                        $strFormat        = '%engagement_name (%engagement_number)';
                        break;

                    case 'employer_legal_entities':
                        $arrFieldsToGroup = array('entity_legal_name_common');
                        $strFormat        = '%entity_legal_name_common';
                        break;

                    case 'employer_locations':
                        $arrFieldsToGroup = array('other_address_line_1');
                        $strFormat        = '%other_address_line_1';
                        break;

                    case 'employer_third_party_representatives':
                        $arrFieldsToGroup = array('contractor_legal_company_name_common');
                        $strFormat        = '%contractor_legal_company_name_common';
                        break;

                    default:
                        $arrFieldsToGroup = array();
                        $strFormat        = '';
                        break;
                }

                $arrEmployerData = array();
                foreach ($arrSavedData as $arrSavedDataInfo) {
                    if (in_array($arrSavedDataInfo['applicant_field_unique_id'], $arrFieldsToGroup)) {
                        $rowId = empty($arrSavedDataInfo['row_id']) ? $arrSavedDataInfo['applicant_id'] : $arrSavedDataInfo['row_id'];

                        $arrEmployerData[$rowId][$arrSavedDataInfo['applicant_field_unique_id']] = $arrSavedDataInfo['value'];
                    }
                }

                foreach ($arrEmployerData as $rowId => $arrEmployerDataSaved) {
                    // Prepare empty value, make sure that key exists in the array
                    foreach ($arrFieldsToGroup as $field) {
                        if (is_array($arrEmployerDataSaved) && !array_key_exists($field, $arrEmployerDataSaved)) {
                            $arrEmployerDataSaved[$field] = '';
                        }
                    }

                    $label = trim(Settings::sprintfAssoc($strFormat, $arrEmployerDataSaved));
                    if (!empty($label) && $label != '()') {
                        $arrData[] = array(
                            'option_id'   => $rowId,
                            'option_name' => $label
                        );
                    }
                }
            }
        }

        return $arrData;
    }

    /**
     * Load cases list with their parents
     * @param array $arrAllCaseIds
     * @return array
     */
    public function getCasesWithParents($arrAllCaseIds)
    {
        $arrResult = array();
        if (is_array($arrAllCaseIds) && count($arrAllCaseIds)) {
            // Get parents for cases
            $arrCasesParents = $this->_parent->getParentsForAssignedApplicants($arrAllCaseIds);

            // Get cases info - to generate name
            $select = (new Select())
                ->from(array('m' => 'members'))
                ->join(array('c' => 'clients'), 'm.member_id = c.member_id', array('fileNumber', 'client_type_id'), Select::JOIN_LEFT)
                ->where(['m.member_id' => $arrAllCaseIds]);

            $arrCasesData = $this->_db2->fetchAll($select);

            foreach ($arrCasesData as $arrCaseData) {
                $caseId      = $arrCaseData['member_id'];
                $arrCaseData = $this->_parent->generateClientName($arrCaseData);

                // Get parent IA/Employer name
                $parentName    = '';
                $arrParentInfo = array();
                if (array_key_exists($caseId, $arrCasesParents) && is_array($arrCasesParents[$caseId])) {
                    $arrParentInfo              = $arrCasesParents[$caseId];
                    $arrParentInfo['full_name'] = $this->_parent->generateApplicantName($arrParentInfo);

                    $parentName = $arrParentInfo['full_name'];
                }

                $arrResult[] = array(
                    'case_id'                 => $caseId,
                    'case_name'               => $arrCaseData['full_name_with_file_num'],
                    'case_type'               => $arrCaseData['client_type_id'],
                    'applicant_id'            => array_key_exists('parent_member_id', $arrParentInfo) ? $arrParentInfo['parent_member_id'] : 0,
                    'applicant_name'          => array_key_exists('full_name', $arrParentInfo) ? $arrParentInfo['full_name'] : '',
                    'applicant_type'          => array_key_exists('member_type_name', $arrParentInfo) ? $arrParentInfo['member_type_name'] : 0,
                    'case_and_applicant_name' => empty($parentName) ? $arrCaseData['full_name_with_file_num'] : $parentName . ' (' . $arrCaseData['full_name_with_file_num'] . ')',
                );
            }

            // Sort cases list
            $arrOptions = array();
            foreach ($arrResult as $key => $row) {
                $arrOptions[$key] = $row['case_and_applicant_name'];
            }
            array_multisort($arrOptions, SORT_ASC, $arrResult);
        }

        return $arrResult;
    }

    /**
     * Load cases list:
     * a) cases that are for the same employer
     * b) cases that don't have an IA and are active at the moment
     *
     * @param $companyId
     * @param $employerId
     * @param $caseId
     * @return array
     */
    public function getEmployerCaseLinks($companyId, $employerId, $caseId = 0)
    {
        $arrResult = array();
        try {
            $arrCaseTemplates      = array();
            $arrSavedCaseTemplates = $this->_parent->getCaseTemplates()->getTemplates($companyId);
            foreach ($arrSavedCaseTemplates as $arrSavedCaseTemplateInfo) {
                if ($arrSavedCaseTemplateInfo['case_template_employer_sponsorship'] == 'Y') {
                    $arrCaseTemplates[] = $arrSavedCaseTemplateInfo['case_template_id'];
                }
            }

            if (!empty($employerId) && is_numeric($employerId) && is_array($arrCaseTemplates) && count($arrCaseTemplates)) {
                // Get this employer assigned cases
                $select = (new Select())
                    ->from(array('r' => 'members_relations'))
                    ->columns(['child_member_id'])
                    ->join(array('m' => 'members'), 'm.member_id = r.child_member_id', [], Select::JOIN_LEFT)
                    ->join(array('c' => 'clients'), 'c.member_id = r.child_member_id', [], Select::JOIN_LEFT)
                    ->where(
                        [
                            'm.userType'         => $this->_parent->getMemberTypeIdByName('case'),
                            'c.client_type_id'   => $arrCaseTemplates,
                            'r.parent_member_id' => (int)$employerId
                        ]
                    );

                if (!empty($caseId)) {
                    $select->where->notEqualTo('r.child_member_id', (int)$caseId);
                }

                $arrAllCaseIds = $this->_db2->fetchCol($select);
                $arrAllCaseIds = array_map('intval', $arrAllCaseIds);

                // Don't show employer cases that are linked to IAs too
                if (!empty($arrAllCaseIds)) {
                    $select = (new Select())
                        ->from(array('r' => 'members_relations'))
                        ->columns(['child_member_id'])
                        ->join(array('m' => 'members'), 'm.member_id = r.parent_member_id', [], Select::JOIN_LEFT)
                        ->where(
                            [
                                'm.userType'        => $this->_parent->getMemberTypeIdByName('individual'),
                                'r.child_member_id' => $arrAllCaseIds
                            ]
                        );

                    $arrIndividualCasesIds = $this->_db2->fetchCol($select);
                    $arrIndividualCasesIds = array_map('intval', $arrIndividualCasesIds);

                    if (!empty($arrIndividualCasesIds)) {
                        $arrAllCaseIds = array_diff($arrAllCaseIds, $arrIndividualCasesIds);
                    }
                }

                // Generate result
                $arrResult = $this->getCasesWithParents($arrAllCaseIds);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrResult;
    }

    /**
     * Load cases list except of this case parent assigned cases
     *
     * @param int|null $parentId
     * @param int|null $companyId
     * @return array
     */
    public function getRelatedCaseOptions($parentId = null, $companyId = null)
    {
        $arrResult = array();

        try {
            if (!is_null($parentId)) {
                $companyId = is_null($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId;

                // Get all cases which are not related to the current parent client id
                $select = (new Select())
                    ->from(array('r' => 'members_relations'))
                    ->columns(['child_member_id'])
                    ->join(array('m' => 'members'), 'm.member_id = r.child_member_id', [], Select::JOIN_LEFT)
                    ->join(array('m2' => 'members'), 'm2.member_id = r.parent_member_id', [], Select::JOIN_LEFT)
                    ->join(array('c' => 'clients'), 'c.member_id = r.child_member_id', [], Select::JOIN_LEFT)
                    ->where(
                        [
                            (new Where())
                                ->equalTo('m.company_id', (int)$companyId)
                                ->notEqualTo('r.parent_member_id', (int)$parentId)
                        ]
                    )
                    ->where(
                        [
                            'm.userType'  => $this->_parent->getMemberTypeIdByName('case'),
                            'm2.userType' => array($this->_parent->getMemberTypeIdByName('employer'))
                        ]
                    );

                $arrAllCaseIds = $this->_db2->fetchCol($select);

                // Generate result
                $arrResult = $this->getCasesWithParents($arrAllCaseIds);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrResult;
    }

    /**
     * Get options list for 'Assigned Related Case' field
     * @param int $companyId
     * @param int $caseId
     * @return array
     */
    public function getAssignedRelatedCaseOptions($companyId, $caseId)
    {
        $arrResult = array();
        try {
            if (!empty($caseId) && is_numeric($caseId)) {
                // Search for related_case_selection field for the current company
                $arrFieldIds = $this->_parent->getFields()->getFieldIdByType($companyId, 'related_case_selection');

                if (is_array($arrFieldIds) && count($arrFieldIds)) {
                    // Get cases which have saved specific case in this field
                    $select = (new Select())
                        ->from(array('d' => 'client_form_data'))
                        ->columns(['member_id'])
                        ->where(
                            [
                                'd.field_id' => $arrFieldIds,
                                'd.value'    => (int)$caseId
                            ]
                        );

                    $arrAllCaseIds = $this->_db2->fetchCol($select);

                    // Generate result
                    $arrResult = $this->getCasesWithParents($arrAllCaseIds);
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrResult;
    }

    /**
     * Get username field id for specific client (will be based on the member type)
     * @param $applicantId
     * @return int
     */
    public function getUsernameFieldId($applicantId)
    {
        $usernameFieldId = 0;

        $arrMemberInfo = $this->getParent()->getMemberInfo($applicantId);
        if (is_array($arrMemberInfo) && array_key_exists('userType', $arrMemberInfo)) {
            $usernameFieldId = $this->getCompanyFieldIdByUniqueFieldId('username', $arrMemberInfo['userType'], $arrMemberInfo['company_id']);
        }

        return $usernameFieldId;
    }

    /**
     * Update labels for all 'office' fields of the company
     * @param $companyId
     * @param $newOfficeLabel
     * @return bool true on success
     */
    public function renameOfficeFields($companyId, $newOfficeLabel)
    {
        $booSuccess = false;
        if (is_numeric($companyId)) {
            // Update labels for all fields
            $this->_db2->update(
                'applicant_form_fields',
                ['label' => $newOfficeLabel],
                [
                    'type'       => ['office', 'office_multi'],
                    'company_id' => (int)$companyId
                ]
            );

            // Clear cache for these updated fields
            $select = (new Select())
                ->from(array('f' => 'applicant_form_fields'))
                ->columns(['member_type_id'])
                ->where(
                    [
                        'type'       => ['office', 'office_multi'],
                        'company_id' => (int)$companyId
                    ]
                )
                ->group('member_type_id');

            $arrMemberTypes = $this->_db2->fetchCol($select);

            foreach ($arrMemberTypes as $memberTypeId) {
                $this->clearCache($companyId, $memberTypeId);
            }

            $booSuccess = true;
        }

        return $booSuccess;
    }

    /**
     * Load NOC list for a special field type
     * @return array
     */
    public function getListOfOccupations()
    {
        $select = (new Select())
            ->from(array('n' => 'company_prospects_noc'))
            ->columns(array('option_id' => 'noc_title', 'option_name' => new Expression("TRIM(CONCAT (IFNULL(n.noc_code,''), ' - ', IFNULL(n.noc_title,'')))")))
            ->order('noc_title');

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load grouped list of fields for specific company and role
     * @param int $companyId
     * @param int $roleId
     * @param $booHasAccessToEmployers
     * @return array
     */
    public function getGroupedApplicantFields($companyId, $roleId, $booHasAccessToEmployers)
    {
        // Get and prepare applicant fields
        $arrApplicantFieldsGrouped = array();
        $oApplicantTypes           = $this->_parent->getApplicantTypes();
        $arrFieldsRights           = $this->getRoleAllowedFields($companyId, $roleId);
        $arrApplicantAllowedTypes  = $this->_parent->getAllowedMemberTypes($booHasAccessToEmployers);
        foreach ($arrApplicantAllowedTypes as $arrTypeInfo) {
            $arrApplicantTypes = $oApplicantTypes->getTypes($companyId, false, $arrTypeInfo['tab_id']);
            if (!is_array($arrApplicantTypes) || !count($arrApplicantTypes)) {
                $arrApplicantTypes = array(
                    array(
                        'applicant_type_id'   => 0,
                        'applicant_type_name' => ''
                    )
                );
            }

            $arrGroupedFields = array();
            foreach ($arrApplicantTypes as $arrApplicantTypeInfo) {
                $arrApplicantFields = $this->getCompanyFields($companyId, $arrTypeInfo['tab_id'], $arrApplicantTypeInfo['applicant_type_id']);

                $arrApplicantTypeFields = array();
                foreach ($arrApplicantFields as $f) {
                    // Skip not assigned fields
                    if (!empty($f['group_id'])) {
                        $f['rights'] = '';
                        if (array_key_exists($f['group_id'], $arrFieldsRights) && is_array($arrFieldsRights[$f['group_id']]) && array_key_exists($f['applicant_field_id'], $arrFieldsRights[$f['group_id']])) {
                            $f['rights'] = $arrFieldsRights[$f['group_id']][$f['applicant_field_id']];
                        }
                        $arrApplicantTypeFields[$f['group_id']][] = $f;
                    }
                }

                $arrGroupedFields[] = array(
                    'type_id'   => $arrApplicantTypeInfo['applicant_type_id'],
                    'type_name' => $arrApplicantTypeInfo['applicant_type_name'],
                    'fields'    => $arrApplicantTypeFields
                );
            }

            $arrApplicantFieldsGrouped[] = array(
                'tab_text_id'    => $arrTypeInfo['tab_text_id'],
                'tab_id'         => $arrTypeInfo['tab_id'],
                'tab_title'      => $arrTypeInfo['tab_title'],
                'grouped_fields' => $arrGroupedFields
            );
        }

        return $arrApplicantFieldsGrouped;
    }

    public function saveField(
        $companyId,
        $updateGroupId,
        $fieldId,
        $updateFieldId,
        $fieldType,
        $fieldCompanyId,
        $arrFieldsInsert,
        $fieldOptions,
        $fieldUseFullRow,
        $fieldImageWidth,
        $fieldImageHeight,
        $booWithDefaultValue,
        $fieldDefaultValue,
        $booAllowsEncryption,
        $booWasEncrypted,
        $booOfficeField,
        $booCreateForOtherCompanies = false
    ) {
        $strError = '';

        try {
            $memberTypeId   = $arrFieldsInsert['member_type_id'];
            $memberType     = $this->_parent->getMemberTypeNameById($memberTypeId);
            $fieldEncrypted = $arrFieldsInsert['encrypted'] === 'Y' ? true : false;

            if (empty($strError)) {
                if (empty($fieldId)) {
                    $arrFieldsInsert['company_id']                = $companyId;
                    $arrFieldsInsert['applicant_field_unique_id'] = $fieldCompanyId;

                    // This is a new field, create it
                    $updateFieldId = $this->_db2->insert('applicant_form_fields', $arrFieldsInsert);


                    // Allow access for this field for company admin
                    if (!empty($updateGroupId)) {
                        $this->allowFieldAccessForCompanyAdmin($companyId, $updateFieldId, $updateGroupId);
                    }

                    // Create record in field orders table
                    if (!empty($updateGroupId)) {
                        $query  = sprintf('(SELECT IFNULL(MAX(o.field_order) + 1, 1) FROM %s as o WHERE applicant_group_id = %d)', 'applicant_form_order', $updateGroupId);
                        $maxRow = new Expression($query);

                        $arrOrderInsert = array(
                            'applicant_group_id' => $updateGroupId,
                            'applicant_field_id' => $updateFieldId,
                            'use_full_row'       => $fieldUseFullRow ? 'Y' : 'N',
                            'field_order'        => $maxRow
                        );
                        $this->_db2->insert('applicant_form_order', $arrOrderInsert);
                    }

                    // Now insert new default values
                    $arrFieldsDefaultInsert = array(
                        'applicant_field_id' => $updateFieldId
                    );

                    if ($fieldType == $this->_parent->getFieldTypes()->getFieldTypeId('photo')) {
                        $fieldOptions = array(
                            array(
                                'name'  => $fieldImageWidth,
                                'order' => 0
                            ),
                            array(
                                'name'  => $fieldImageHeight,
                                'order' => 1
                            )
                        );
                    }

                    if ($booWithDefaultValue) {
                        $arrFieldsDefaultInsert['value'] = $fieldDefaultValue;
                        $arrFieldsDefaultInsert['order'] = 0;
                        $this->_db2->insert('applicant_form_default', $arrFieldsDefaultInsert);
                    } else {
                        if (!empty($fieldOptions) && is_array($fieldOptions)) {
                            foreach ($fieldOptions as $arrOptionInfo) {
                                $arrFieldsDefaultInsert['value'] = $arrOptionInfo['name'];
                                $arrFieldsDefaultInsert['order'] = $arrOptionInfo['order'];
                                $this->_db2->insert('applicant_form_default', $arrFieldsDefaultInsert);
                            }
                        }
                    }


                    // Check if this is 'Default' company - create field for all other companies
                    if (empty($companyId) && $booCreateForOtherCompanies) {
                        // 1. Get all companies list
                        $select = (new Select())
                            ->from(array('c' => 'company'))
                            ->columns(array('c.company_id'))
                            ->where(
                                [
                                    (new Where())
                                        ->notEqualTo('c.company_id', 0)
                                ]
                            );

                        $arrCompanies = $this->_db2->fetchAll($select);

                        if (is_array($arrCompanies) && !empty($arrCompanies)) {
                            foreach ($arrCompanies as $companyInfo) {
                                $newFieldCompanyId = $companyInfo['company_id'];

                                // 2. Generate field id
                                $newCompanyFieldId = $arrFieldsInsert['applicant_field_unique_id'];

                                // Check if this field id is unique
                                $booUnique = false;
                                $count     = 0;
                                while (!$booUnique) {
                                    $testFieldId = $newCompanyFieldId;
                                    if (!empty($count)) {
                                        $testFieldId .= '_' . $count;
                                    }

                                    $savedId = $this->getCompanyFieldIdByUniqueFieldId($testFieldId, $memberTypeId, $newFieldCompanyId);
                                    if (empty($savedId)) {
                                        $booUnique         = true;
                                        $newCompanyFieldId = $testFieldId;
                                    } else {
                                        $count++;
                                    }
                                }


                                // 3. Create new field for company
                                $companyNewFieldInfo                              = $arrFieldsInsert;
                                $companyNewFieldInfo['company_id']                = $newFieldCompanyId;
                                $companyNewFieldInfo['applicant_field_unique_id'] = $newCompanyFieldId;
                                unset($companyNewFieldInfo['applicant_field_id']);
                                $newFieldId = $this->_db2->insert('applicant_form_fields', $companyNewFieldInfo);

                                // 4. Insert default values
                                $arrFieldsDefaultInsert                       = array();
                                $arrFieldsDefaultInsert['applicant_field_id'] = $newFieldId;
                                if ($booWithDefaultValue) {
                                    $arrFieldsDefaultInsert['value'] = $fieldDefaultValue;
                                    $arrFieldsDefaultInsert['order'] = 0;
                                    $this->_db2->insert('applicant_form_default', $arrFieldsDefaultInsert);
                                } else {
                                    if (!empty($fieldOptions) && is_array($fieldOptions)) {
                                        foreach ($fieldOptions as $arrOptionInfo) {
                                            $arrFieldsDefaultInsert['value'] = $arrOptionInfo['name'];
                                            $arrFieldsDefaultInsert['order'] = $arrOptionInfo['order'];
                                            $this->_db2->insert('applicant_form_default', $arrFieldsDefaultInsert);
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else {
                    // If field encryption setting was changed - we need to update data, i.e.:
                    // * if data was already encrypted - we need decrypt and save it
                    // * if data wasn't encrypted - we need encrypt and save it
                    if ($booAllowsEncryption && $booWasEncrypted != $fieldEncrypted) {
                        $arrSavedData = $this->getFieldData($updateFieldId, 0, false);
                        foreach ($arrSavedData as $arrSavedDataRow) {
                            $updatedValue = $booWasEncrypted ?
                                $this->_encryption->decode($arrSavedDataRow['value']) :
                                $this->_encryption->encode($arrSavedDataRow['value']);

                            // Generate 'Where'
                            $arrWhere                       = [];
                            $arrWhere['applicant_id']       = (int)$arrSavedDataRow['applicant_id'];
                            $arrWhere['applicant_field_id'] = (int)$arrSavedDataRow['applicant_field_id'];

                            if (!empty($arrSavedDataRow['row'])) {
                                $arrWhere['row'] = (int)$arrSavedDataRow['row'];
                            }

                            if (!empty($arrSavedDataRow['row_id'])) {
                                $arrWhere['row_id'] = $arrSavedDataRow['row_id'];
                            }

                            $this->_db2->update('applicant_form_data', ['value' => $updatedValue], $arrWhere);
                        }
                    }


                    if ($booOfficeField) {
                        // Update this specific field info

                        // Update field label only
                        $this->_db2->update(
                            'applicant_form_fields',
                            ['label' => $arrFieldsInsert['label']],
                            ['applicant_field_id' => $updateFieldId]
                        );

                        $arrOptionsIds     = array();
                        $oCompanyDivisions = $this->_company->getCompanyDivisions();
                        $divisionGroupId   = $oCompanyDivisions->getCompanyMainDivisionGroupId($companyId);
                        if (!empty($fieldOptions) && is_array($fieldOptions)) {
                            foreach ($fieldOptions as $arrOptionInfo) {
                                $arrOptionsIds[] = $oCompanyDivisions->createUpdateDivision(
                                    $companyId,
                                    $divisionGroupId,
                                    $arrOptionInfo['id'],
                                    $arrOptionInfo['name'],
                                    $arrOptionInfo['order']
                                );
                            }
                        }

                        $oCompanyDivisions->deleteCompanyDivisions($companyId, $divisionGroupId, $arrOptionsIds);

                        $where = array();
                        if (count($arrOptionsIds) > 0) {
                            $where[] = (new Where())->notIn('division_id', $arrOptionsIds);
                        }

                        // Delete assigned divisions for this company for all company members
                        $arrMembersIds = $this->_company->getCompanyMembersIds($companyId);
                        if (is_array($arrMembersIds)) {
                            $where['member_id'] = $arrMembersIds;
                            $this->_db2->delete('members_divisions', $where);
                        }
                    } else {
                        // Update field
                        $this->_db2->update('applicant_form_fields', $arrFieldsInsert, ['applicant_field_id' => $updateFieldId]);


                        // Update order info
                        $this->_db2->update(
                            'applicant_form_order',
                            ['use_full_row' => $fieldUseFullRow ? 'Y' : 'N'],
                            [
                                'applicant_field_id' => $updateFieldId,
                                'applicant_group_id' => $updateGroupId
                            ]
                        );

                        if ($memberType == 'internal_contact') {
                            $select = (new Select())
                                ->from('applicant_form_groups')
                                ->columns(['applicant_group_id'])
                                ->where(['company_id' => (int)$companyId]);

                            $arrApplicantFormGroupsIds = $this->_db2->fetchCol($select);

                            if (is_array($arrApplicantFormGroupsIds)) {
                                $this->_db2->update(
                                    'applicant_form_order',
                                    ['use_full_row' => $fieldUseFullRow ? 'Y' : 'N'],
                                    [
                                        'applicant_field_id' => $updateFieldId,
                                        'applicant_group_id' => $arrApplicantFormGroupsIds
                                    ]
                                );
                            }
                        }

                        // Update/Create new default values
                        $arrOptionsIds = array();
                        if ($booWithDefaultValue) {
                            $arrFieldsDefaultInsert['applicant_field_id'] = $updateFieldId;
                            $arrFieldsDefaultInsert['value']              = $fieldDefaultValue;
                            $arrFieldsDefaultInsert['order']              = 0;

                            $arrOptionsIds[] = $this->_db2->insert('applicant_form_default', $arrFieldsDefaultInsert);
                        } else {
                            if (!empty($fieldOptions) && is_array($fieldOptions)) {
                                foreach ($fieldOptions as $arrOptionInfo) {
                                    if ($arrOptionInfo['id'] == 0) {
                                        // New option - create it
                                        $arrOptionsIds[] = $this->_db2->insert(
                                            'applicant_form_default',
                                            array(
                                                'applicant_field_id' => $updateFieldId,
                                                'value'              => $arrOptionInfo['name'],
                                                'order'              => $arrOptionInfo['order']
                                            )
                                        );
                                    } else {
                                        // Not a new option - update it
                                        $this->_db2->update(
                                            'applicant_form_default',
                                            [
                                                'value' => $arrOptionInfo['name'],
                                                'order' => $arrOptionInfo['order']
                                            ],
                                            ['applicant_form_default_id' => $arrOptionInfo['id']]
                                        );

                                        $arrOptionsIds[] = $arrOptionInfo['id'];
                                    }
                                }
                            }
                        }

                        // update image field
                        if ($fieldType == $this->_parent->getFieldTypes()->getFieldTypeId('photo')) {
                            $this->_db2->update(
                                'applicant_form_default',
                                ['value' => $fieldImageWidth],
                                [
                                    'applicant_field_id' => $updateFieldId,
                                    'order'              => 0
                                ]
                            );

                            $this->_db2->update(
                                'applicant_form_default',
                                ['value' => $fieldImageHeight],
                                [
                                    'applicant_field_id' => $updateFieldId,
                                    'order'              => 1
                                ]
                            );
                        }

                        $arrApplicantIds = array();

                        $arrWhereDelete = array();
                        if (count($fieldOptions) > 0 && count($arrOptionsIds) > 0) {
                            $arrWhereDelete[] = (new Where())->notIn('applicant_form_default_id', $arrOptionsIds);

                            $select = (new Select())
                                ->from('applicant_form_data')
                                ->where(
                                    [
                                        (new Where())
                                            ->notIn('value', $arrOptionsIds)
                                            ->equalTo('applicant_field_id', (int)$updateFieldId)
                                    ]
                                );

                            $arrApplicantIds = $this->_db2->fetchCol($select);
                        }

                        if (empty($arrApplicantIds)) {
                            // Delete all not used default options
                            $arrWhereDelete['applicant_field_id'] = $updateFieldId;
                            $this->_db2->delete('applicant_form_default', $arrWhereDelete);
                        } else {
                            $strApplicantFullNames = '';
                            $count                 = min(count($arrApplicantIds), 10);
                            for ($i = 0; $i < $count; $i++) {
                                $memberId     = $arrApplicantIds[$i];
                                $memberTypeId = $this->_parent->getMemberTypeByMemberId($memberId);
                                if (in_array($memberTypeId, Members::getMemberType('internal_contact'))) {
                                    $arrParents = $this->_parent->getParentsForAssignedApplicant($memberId);
                                    if (count($arrParents)) {
                                        $memberId = $arrParents[0];
                                    }
                                }

                                $arrApplicantInfo = $this->_parent->getMemberInfo($memberId);
                                if (isset($arrApplicantInfo['full_name'])) {
                                    $strApplicantFullNames .= '<br/>' . $arrApplicantInfo['full_name'];
                                }
                            }

                            $strError = $this->_tr->translate('Deleted option(s) is (are) selected as value(s) for next applicants:' . $strApplicantFullNames);
                        }
                    }
                }

                $this->clearCache($companyId, $memberTypeId);
                if ($memberType == 'internal_contact') {
                    $this->clearCache($companyId, $this->_parent->getMemberTypeIdByName('individual'));
                    $this->clearCache($companyId, $this->_parent->getMemberTypeIdByName('employer'));
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($strError, $updateFieldId);
    }

    /**
     * @param int $companyId
     * @param int $memberTypeId
     * @param int $blockId
     * @param int $groupId
     * @param array $arrFieldsAdded
     * @param array $arrFieldsRemoved
     * @return string
     */
    public function toggleContactFields($companyId, $memberTypeId, $blockId, $groupId, $arrFieldsAdded, $arrFieldsRemoved)
    {
        $strError = '';
        try {
            $contactTypeId  = $this->_parent->getMemberTypeIdByName('internal_contact');
            $arrAllFields   = $this->getCompanyFields($companyId, $contactTypeId);
            $arrAllFieldIds = $this->_settings::arrayColumnAsKey('applicant_field_id', $arrAllFields, 'applicant_field_id');

            foreach ($arrFieldsAdded as $addedFieldId) {
                if (!in_array($addedFieldId, $arrAllFieldIds)) {
                    $strError = $this->_tr->translate('Incorrectly checked field.');
                    break;
                }
            }

            foreach ($arrFieldsRemoved as $removedFieldId) {
                if (!in_array($removedFieldId, $arrAllFieldIds)) {
                    $strError = $this->_tr->translate('Incorrectly unchecked field.');
                    break;
                }
            }

            if (empty($strError)) {
                // Unassign specific fields
                $arrGroupIds = $this->getBlockGroups($blockId);
                if (is_array($arrGroupIds) && count($arrGroupIds) && count($arrFieldsRemoved)) {
                    foreach ($arrFieldsRemoved as $removedFieldId) {
                        $this->unassignField($removedFieldId, $arrGroupIds, $companyId);
                    }
                }

                // Place new fields into the first column
                if (count($arrFieldsAdded)) {
                    $select = (new Select())
                        ->from('applicant_form_order')
                        ->columns(['infull' => new Expression('IFNULL(MAX(field_order), 0)')])
                        ->where(['applicant_group_id' => (int)$groupId]);

                    $maxRow = (int)$this->_db2->fetchOne($select);
                    foreach ($arrFieldsAdded as $addedFieldId) {
                        $useFullRow = 'N';
                        foreach ($arrAllFields as $field) {
                            if ($field['applicant_field_id'] == $addedFieldId) {
                                $useFullRow = $field['use_full_row'];
                                break;
                            }
                        }

                        $this->_db2->insert(
                            'applicant_form_order',
                            [
                                'applicant_group_id' => (int)$groupId,
                                'applicant_field_id' => (int)$addedFieldId,
                                'use_full_row'       => $useFullRow,
                                'field_order'        => ++$maxRow
                            ]
                        );
                    }
                }

                $this->clearCache($companyId, $memberTypeId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

    /**
     * Create/update default access rights for a specific field
     *
     * @param int $applicantFieldId
     * @param array $arrFieldDefaultAccess
     */
    public function updateDefaultAccessRights($applicantFieldId, $arrFieldDefaultAccess)
    {
        $this->_db2->delete('applicant_form_fields_access_default', ['applicant_field_id' => (int)$applicantFieldId]);

        $arrRoleIds = array_filter(array_keys($arrFieldDefaultAccess));
        if (!empty($arrRoleIds)) {
            $this->_db2->delete(
                'applicant_form_fields_access',
                [
                    'role_id'            => $arrRoleIds,
                    'applicant_field_id' => (int)$applicantFieldId
                ]
            );
        }

        // Load all groups where this field is placed to
        $select = (new Select())
            ->from(array('o' => 'applicant_form_order'))
            ->columns(array('applicant_group_id', 'applicant_field_id'))
            ->where(
                [
                    'o.applicant_field_id' => (int)$applicantFieldId
                ]
            );

        $arrFieldsAndGroups = $this->_db2->fetchAll($select);

        foreach ($arrFieldDefaultAccess as $roleId => $access) {
            if (empty($access)) {
                continue;
            }

            $arrNewAccessRights = array(
                'applicant_field_id' => $applicantFieldId,
                'role_id'            => empty($roleId) ? null : $roleId,
                'access'             => $access,
                'updated_on'         => date('Y-m-d H:i:s'),
            );

            $this->_db2->insert('applicant_form_fields_access_default', $arrNewAccessRights);


            // Update access rights for the role
            if (!empty($roleId)) {
                foreach ($arrFieldsAndGroups as $arrFieldsAndGroupsRow) {
                    $rights = array(
                        'role_id'            => $roleId,
                        'applicant_group_id' => (int)$arrFieldsAndGroupsRow['applicant_group_id'],
                        'applicant_field_id' => (int)$arrFieldsAndGroupsRow['applicant_field_id'],
                        'status'             => $access,
                    );

                    $this->_db2->insert('applicant_form_fields_access', $rights);
                }
            }
        }
    }

    /**
     * Update default access rights for a specific role
     *
     * @param $roleId
     * @param $arrAccessRights
     */
    public function updateDefaultAccessRightsForRole($roleId, $arrAccessRights)
    {
        $this->_db2->delete('applicant_form_fields_access_default', ['role_id' => (int)$roleId]);

        $arrCreatedUpdatedFields = array();
        foreach ($arrAccessRights as $arrFieldsAccess) {
            foreach ($arrFieldsAccess as $fieldId => $accessLevel) {
                if (empty($accessLevel)) {
                    continue;
                }

                if (!in_array($fieldId, $arrCreatedUpdatedFields)) {
                    $this->_db2->insert(
                        'applicant_form_fields_access_default',
                        [
                            'applicant_field_id' => $fieldId,
                            'role_id'            => $roleId,
                            'access'             => $accessLevel,
                            'updated_on'         => date('Y-m-d H:i:s'),
                        ]
                    );

                    $arrCreatedUpdatedFields[] = $fieldId;
                }
            }
        }
    }


    /**
     * Load list of access rights for a specific field
     * If $booLoadLatest passed and this is a new field (not created yet) - load settings from the latest updated field of the company
     *
     * @param int $companyId
     * @param int $applicantFieldId
     * @param bool $booLoadLatest
     * @return array
     */
    public function getFieldDefaultAccessRights($companyId, $applicantFieldId, $booLoadLatest = true)
    {
        // For the new field - try to load last saved field's settings
        if (empty($applicantFieldId) && $booLoadLatest) {
            $arrCompanyRoles = $this->_roles->getCompanyRoles($companyId, 0, false, true);

            if (!empty($arrCompanyRoles)) {
                $select = (new Select())
                    ->from('applicant_form_fields_access_default')
                    ->columns(['applicant_field_id'])
                    ->where(
                        [
                            'role_id' => $arrCompanyRoles
                        ]
                    )
                    ->order('updated_on DESC')
                    ->limit(1);

                $applicantFieldId = $this->_db2->fetchOne($select);
            }
        }

        $arrFieldDefaultAccessRights = array();
        if (!empty($applicantFieldId)) {
            $select = (new Select())
                ->from('applicant_form_fields_access_default')
                ->columns(array('role_id', 'access'))
                ->where(
                    [
                        'applicant_field_id' => (int)$applicantFieldId
                    ]
                );

            $arrFieldDefaultAccessRights = $this->_db2->fetchAll($select);
        }

        return $arrFieldDefaultAccessRights;
    }

    /**
     * Get "default access rights" for the new role in relation to the previously saved default access rights
     *
     * @param int $companyId
     * @return array
     */
    public function getDefaultAccessRightsForNewRole($companyId)
    {
        $arrGroupedResult = array();

        $arrAllCompanyApplicantFieldsIds = $this->getCompanyAllFields($companyId, true);

        if (!empty($arrAllCompanyApplicantFieldsIds)) {
            $select = (new Select())
                ->from('applicant_form_fields_access_default')
                ->columns(array('applicant_field_id', 'access'))
                ->where(
                    [
                        (new Where())->isNull('role_id')
                    ]
                )
                ->where(
                    [
                        'applicant_field_id' => $arrAllCompanyApplicantFieldsIds
                    ]
                );

            $arrFieldsDefaultAccessRights = $this->_db2->fetchAll($select);

            if (!empty($arrFieldsDefaultAccessRights)) {
                $arrApplicantFieldsAccessGrouped = array();
                foreach ($arrFieldsDefaultAccessRights as $arrFieldsDefaultAccessRightsRow) {
                    $arrApplicantFieldsAccessGrouped[$arrFieldsDefaultAccessRightsRow['applicant_field_id']] = $arrFieldsDefaultAccessRightsRow['access'];
                }

                $select = (new Select())
                    ->from(array('o' => 'applicant_form_order'))
                    ->columns(array('applicant_group_id', 'applicant_field_id'))
                    ->where(
                        [
                            (new Where())->in('o.applicant_field_id', array_keys($arrApplicantFieldsAccessGrouped))
                        ]
                    );

                $arrFieldsAndGroups = $this->_db2->fetchAll($select);

                foreach ($arrFieldsAndGroups as $arrFieldsAndGroupsRow) {
                    $arrGroupedResult[$arrFieldsAndGroupsRow['applicant_group_id']][$arrFieldsAndGroupsRow['applicant_field_id']] = $arrApplicantFieldsAccessGrouped[$arrFieldsAndGroupsRow['applicant_field_id']];
                }
            }
        }

        return $arrGroupedResult;
    }

    /**
     * Get the saved Client Profile ID field's value for a specific client
     *
     * @param int $companyId
     * @param int $clientId
     * @return string
     */
    public function getClientSavedClientProfileId($companyId, $clientId)
    {
        $clientProfileId = '';

        $clientProfileIdFieldId = $this->getCompanyFieldIdByUniqueFieldId('client_profile_id', Members::getMemberType('internal_contact'), $companyId);
        if (!empty($clientProfileIdFieldId)) {
            $arrInternalContactsIds = $this->_parent->getAssignedContacts($clientId, true);

            $clientProfileId = trim($this->getFieldDataValue($arrInternalContactsIds, $clientProfileIdFieldId));
        }

        return $clientProfileId;
    }


    /**
     * Check if current member has access to the specific field by int id
     *
     * @param int $fieldId
     * @return bool true if current user has access
     */
    public function hasCurrentMemberAccessToFieldById($fieldId)
    {
        $booHasAccess = false;
        if ($this->_auth->isCurrentUserSuperadmin()) {
            $booHasAccess = true;
        } else {
            $companyId    = $this->_auth->getCurrentUserCompanyId();
            $arrFieldInfo = $this->getFieldInfo($fieldId, $companyId, true);
            if (isset($arrFieldInfo['company_id']) && $arrFieldInfo['company_id'] == $companyId) {
                $booHasAccess = true;
            }
        }

        return $booHasAccess;
    }


    /**
     * Check if client field's option is used (saved in the client's profile)
     *
     * @param int $fieldId
     * @param int $deletedOptionId
     * @return array
     */
    public function getClientsThatUseFieldOption($fieldId, $deletedOptionId)
    {
        $select = (new Select())
            ->from('applicant_form_data')
            ->columns(['applicant_id'])
            ->where([
                'applicant_field_id' => (int)$fieldId,
                new PredicateExpression('FIND_IN_SET(?, value) > 0', $deletedOptionId)
            ]);

        return Settings::arrayUnique($this->_db2->fetchCol($select));
    }


    /**
     * Delete field option record(s)
     *
     * @param array $arrDeleteOptionsIds
     * @return int
     */
    public function deleteDefaultOptions($arrDeleteOptionsIds)
    {
        $count = 0;
        if (!empty($arrDeleteOptionsIds)) {
            $count = $this->_db2->delete('applicant_form_default', ['applicant_form_default_id' => $arrDeleteOptionsIds]);
        }

        return $count;
    }


    /**
     * Create/update/delete client field's options
     *
     * @param int $fieldId
     * @param array $arrFieldOptions
     * @return bool|string error string, true if there were changes or false if no changes were done
     */
    public function updateFieldDefaultOptions($fieldId, $arrFieldOptions)
    {
        $arrSavedOptionsIds       = [];
        $arrSavedOptionsNames     = [];
        $arrSavedOptionsFormatted = [];
        $arrSavedOptions          = $this->getFieldsOptions($fieldId);
        foreach ($arrSavedOptions as $arrSavedOptionInfo) {
            $arrSavedOptionsIds[] = $arrSavedOptionInfo['applicant_form_default_id'];

            $arrSavedOptionsNames[$arrSavedOptionInfo['applicant_form_default_id']] = $arrSavedOptionInfo['value'];

            // Prepare the same format as we receive
            $arrSavedOptionsFormatted[] = [
                'id'    => $arrSavedOptionInfo['applicant_form_default_id'],
                'name'  => $arrSavedOptionInfo['value'],
                'order' => $arrSavedOptionInfo['order'],
            ];
        }

        // Don't try to update if no changes were made
        if ($arrSavedOptionsFormatted == $arrFieldOptions) {
            return false;
        }

        // Make sure that deleted options are not used by clients
        $arrOptionsIdsOnly = array();
        if (!empty($arrFieldOptions) && is_array($arrFieldOptions)) {
            foreach ($arrFieldOptions as $arrOptionInfo) {
                if (!empty($arrOptionInfo['id'])) {
                    $arrOptionsIdsOnly[] = $arrOptionInfo['id'];
                }
            }
        }
        $arrDeletedOptions = array_diff($arrSavedOptionsIds, $arrOptionsIdsOnly);

        $arrOptionsUsedByClients = [];
        if (!empty($arrDeletedOptions)) {
            foreach ($arrDeletedOptions as $deletedOptionId) {
                $arrApplicantIds = $this->getClientsThatUseFieldOption($fieldId, $deletedOptionId);
                if (!empty($arrApplicantIds)) {
                    $arrOptionsUsedByClients[] = $arrSavedOptionsNames[$deletedOptionId];
                }
            }
        }

        if (!empty($arrOptionsUsedByClients)) {
            return $this->_tr->translatePlural(
                'This option was not deleted because it is selected as value for applicant(s): ' . implode(', ', $arrOptionsUsedByClients),
                'Such options were not deleted because they are selected as values for applicant(s): ' . implode(', ', $arrOptionsUsedByClients),
                count($arrOptionsUsedByClients)
            );
        }


        // Create/update options
        if (!empty($arrFieldOptions) && is_array($arrFieldOptions)) {
            foreach ($arrFieldOptions as $arrOptionInfo) {
                if (empty($arrOptionInfo['id'])) {
                    // A new option - create it
                    $this->_db2->insert(
                        'applicant_form_default',
                        [
                            'applicant_field_id' => $fieldId,
                            'value'              => $arrOptionInfo['name'],
                            'order'              => $arrOptionInfo['order']
                        ]
                    );
                } else {
                    // Not a new option - update it
                    $this->_db2->update(
                        'applicant_form_default',
                        [
                            'value' => $arrOptionInfo['name'],
                            'order' => $arrOptionInfo['order']
                        ],
                        ['applicant_form_default_id' => $arrOptionInfo['id']]
                    );
                }
            }
        }

        // Delete removed options
        if (!empty($arrDeletedOptions)) {
            $this->deleteDefaultOptions($arrDeletedOptions);
        }

        return true;
    }
}
