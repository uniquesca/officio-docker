<?php

namespace Clients\Service\Clients;

use Clients\Service\Clients;
use Exception;
use Files\Service\Files;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Officio\Common\Json;
use Officio\Common\Service\BaseService;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Common\Service\Encryption;
use Officio\Service\Roles;
use Officio\Common\Service\Settings;
use Officio\Service\Users;
use Officio\Common\ServiceContainerHolder;
use Officio\Common\SubServiceInterface;
use Laminas\Validator\EmailAddress;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Fields extends BaseService implements SubServiceInterface
{

    use ServiceContainerHolder;

    /** @var Clients */
    protected $_parent;

    /** @var Company */
    protected $_company;

    /** @var Country */
    protected $_country;

    /** @var Files */
    protected $_files;

    /** @var Roles */
    protected $_roles;

    /** @var Encryption */
    protected $_encryption;

    /** @var array */
    private $_arrLoadedData = array();

    public function initAdditionalServices(array $services)
    {
        $this->_company    = $services[Company::class];
        $this->_country    = $services[Country::class];
        $this->_files      = $services[Files::class];
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
     * Load all fields list for specific company (don't load/check access rights, etc.)
     *
     * @param int $companyId
     * @param bool $booIdsOnly
     * @return array
     */
    public function getCompanyFields($companyId, $booIdsOnly = false)
    {
        $select = (new Select())
            ->from(array('f' => 'client_form_fields'))
            ->columns($booIdsOnly ? ['field_id'] : [Select::SQL_STAR])
            ->where(['f.company_id' => (int)$companyId])
            ->order('f.label');

        return $booIdsOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
    }


    /**
     * Load a list of grouped fields for a specific Immigration Program
     *
     * @param int $caseTemplateId
     * @param bool $booCheckRoleAccessRights true to check current user's roles access rights, false to ignore
     * @return array
     */
    public function getGroupedCompanyFields($caseTemplateId, $booCheckRoleAccessRights = true)
    {
        $arrResult = array();

        try {
            $arrAllCaseFieldsAllowed = $this->_parent->getFieldTypes()->getFieldTypes();
            $arrIds = array();
            foreach ($arrAllCaseFieldsAllowed as $arrCaseFieldInfo) {
                $arrIds[] = (int)$arrCaseFieldInfo['id'];
            }

            $arrCompanyFields = array();
            $arrCaseTemplateInfo = $this->_parent->getCaseTemplates()->getTemplateInfo($caseTemplateId);
            if (count($arrIds) && count($arrCaseTemplateInfo)) {
                $arrCompanyFields = $this->getFields(
                    array(
                        'select' => [
                            'f'  => ['field_title' => 'label', 'company_field_id', 'field_id', 'type', 'encrypted', 'required', 'disabled', 'maxlength', 'custom_height', 'min_value', 'max_value', 'multiple_values', 'can_edit_in_gui', 'skip_access_requirements', 'sync_with_default'],
                            'fg' => ['group_title' => 'title', 'group_id', 'cols_count', 'group_collapsed' => 'collapsed', 'show_title'],
                            'o'  => ['use_full_row'],
                            'fa' => ['field_access' => 'status']
                        ],

                        'where' => [
                            (new Where())
                                ->in('f.type', $arrIds)
                                ->equalTo('fg.client_type_id', (int)$caseTemplateId)
                        ],

                        'order'                    => ['fg.order ASC', 'o.field_order ASC'],
                        'unassigned'               => false,
                        'booCheckRoleAccessRights' => $booCheckRoleAccessRights,
                    ),
                    false,
                    $arrCaseTemplateInfo['company_id']
                );
            }

            $arrGroupedFields = array();
            $arrDefaultFieldsToShow = array(
                $this->getStaticColumnNameByFieldId('fName'),
                $this->getStaticColumnNameByFieldId('lName'),
                'username',
                'file_number',
                'division',
                'file_status',
                'categories',
                'date_client_signed'
            );

            foreach ($arrCompanyFields as $arrCompanyFieldInfo) {
                if (!array_key_exists($arrCompanyFieldInfo['group_id'], $arrGroupedFields)) {
                    $arrGroupedFields[$arrCompanyFieldInfo['group_id']] = array(
                        'group_id'            => $arrCompanyFieldInfo['group_id'],
                        'group_title'         => $arrCompanyFieldInfo['group_title'],
                        'group_cols_count'    => $arrCompanyFieldInfo['cols_count'],
                        'group_access'        => $arrCompanyFieldInfo['field_access'],
                        'group_collapsed'     => $arrCompanyFieldInfo['group_collapsed'],
                        'group_show_title'    => $arrCompanyFieldInfo['show_title'],
                        'group_repeatable'    => 'N',
                        'group_contact_block' => 'N',
                        'fields'              => array()
                    );
                }

                $arrFieldInfo = array(
                    'field_id'                       => $arrCompanyFieldInfo['field_id'],
                    'field_unique_id'                => $arrCompanyFieldInfo['company_field_id'],
                    'field_name'                     => $arrCompanyFieldInfo['field_title'],
                    'field_type'                     => $this->_parent->getFieldTypes()->getStringFieldTypeById($arrCompanyFieldInfo['type']),
                    'field_required'                 => $arrCompanyFieldInfo['required'],
                    'field_disabled'                 => $arrCompanyFieldInfo['disabled'],
                    'field_encrypted'                => $arrCompanyFieldInfo['encrypted'],
                    'field_custom_height'            => $arrCompanyFieldInfo['custom_height'],
                    'field_maxlength'                => $arrCompanyFieldInfo['maxlength'],
                    'field_min_value'                => $arrCompanyFieldInfo['min_value'],
                    'field_max_value'                => $arrCompanyFieldInfo['max_value'],
                    'field_use_full_row'             => $arrCompanyFieldInfo['use_full_row'] == 'Y',
                    'field_access'                   => $arrCompanyFieldInfo['field_access'],
                    'field_column_show'              => in_array($arrCompanyFieldInfo['company_field_id'], $arrDefaultFieldsToShow),
                    'field_multiple_values'          => $arrCompanyFieldInfo['multiple_values'] == 'Y',
                    'field_can_edit_in_gui'          => $arrCompanyFieldInfo['can_edit_in_gui'] == 'Y',
                    'field_skip_access_requirements' => $arrCompanyFieldInfo['skip_access_requirements'] == 'Y',
                    'field_sync_with_default'        => $arrCompanyFieldInfo['sync_with_default'],
                );

                // Make sure that Visa Subclass is checked as required (even if it is not), but if it is used in the settings to generate case number
                if ($arrFieldInfo['field_type'] == 'categories' && $arrFieldInfo['field_required'] == 'N') {
                    if ($this->_parent->getCaseNumber()->isSubClassSettingEnabled($arrCaseTemplateInfo['company_id'])) {
                        $arrFieldInfo['field_required'] = 'Y';
                    }
                }

                $arrGroupedFields[$arrCompanyFieldInfo['group_id']]['fields'][] = $arrFieldInfo;
            }

            $arrCompanyGroups = $this->getGroups(true, $caseTemplateId, $arrCaseTemplateInfo['company_id'], $booCheckRoleAccessRights);
            foreach ($arrCompanyGroups as $arrCompanyGroupInfo) {
                if (array_key_exists($arrCompanyGroupInfo['group_id'], $arrGroupedFields)) {
                    $arrResult[] = $arrGroupedFields[$arrCompanyGroupInfo['group_id']];
                } else {
                    // E.g. Dependats group will be loaded here,
                    // so use access rights from the client_form_group_access table
                    // otherwise use access rights from the field
                    $arrResult[] = array(
                        'group_id'            => $arrCompanyGroupInfo['group_id'],
                        'group_title'         => $arrCompanyGroupInfo['title'],
                        'group_cols_count'    => $arrCompanyGroupInfo['cols_count'],
                        'group_access'        => $arrCompanyGroupInfo['status'],
                        'group_collapsed'     => $arrCompanyGroupInfo['collapsed'],
                        'group_show_title'    => $arrCompanyGroupInfo['show_title'],
                        'group_repeatable'    => 'N',
                        'group_contact_block' => 'N',
                        'fields'              => array()
                    );
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array_values($arrResult);
    }

    public function getGroupedFieldsOptions($arrGroupedFields)
    {
        $arrFieldIds = array();
        foreach ($arrGroupedFields as $arrGroups) {
            foreach ($arrGroups as $arrGroupInfo) {
                if (is_array($arrGroupInfo) && array_key_exists('fields', $arrGroupInfo)) {
                    foreach ($arrGroupInfo['fields'] as $arrFieldInfo) {
                        $arrFieldIds[] = $arrFieldInfo['field_id'];
                    }
                }
            }
        }

        $arrFieldIds = array_unique($arrFieldIds);

        $arrOptions = array();
        if (count($arrFieldIds)) {
            $arrAllOptions = $this->getFieldsOptions($arrFieldIds);
            foreach ($arrAllOptions as $arrOptionInfo) {
                $arrOptions[$arrOptionInfo['field_id']][] = array(
                    'option_id'      => $arrOptionInfo['form_default_id'],
                    'option_name'    => $arrOptionInfo['value'],
                    'option_deleted' => $arrOptionInfo['deleted'] == 'Y',
                );
            }
        }

        return $arrOptions;
    }

    /**
     * Load field data for specific case and field ids
     *
     * @param int|array $fieldId
     * @param int $memberId
     * @param bool $booDecodeData - tru to decrypt encrypted data
     * @return array
     */
    public function getFieldData($fieldId, $memberId = 0, $booDecodeData = true)
    {
        $arrValues = array();
        if (!empty($fieldId)) {
            $select = (new Select())
                ->from(array('d' => 'client_form_data'))
                ->join(array('f' => 'client_form_fields'), 'd.field_id = f.field_id', 'encrypted', Select::JOIN_LEFT)
                ->where(
                    [
                        'd.field_id' => $fieldId
                    ]
                );

            if (!empty($memberId)) {
                $select->where(
                    [
                        'd.member_id' => $memberId
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

    public function getFieldDataValue($fieldId, $memberId)
    {
        $strValue = '';
        if (!empty($fieldId) && !empty($memberId)) {
            $select = (new Select())
                ->from(array('d' => 'client_form_data'))
                ->join(array('f' => 'client_form_fields'), 'd.field_id = f.field_id', 'encrypted', Select::JOIN_LEFT)
                ->where(
                    [
                        'd.field_id' => (int)$fieldId,
                        'd.member_id' => (int)$memberId
                    ]
                );

            $arrSaved = $this->_db2->fetchRow($select);

            if (is_array($arrSaved) && count($arrSaved)) {
                $strValue = $arrSaved['encrypted'] == 'Y' ? $this->_encryption->decode($arrSaved['value']) : $arrSaved['value'];
            }
        }

        return $strValue;
    }

    public function getClientsFieldDataValue($fieldId, $arrMemberIds)
    {
        $arrResult = array();
        if (!empty($fieldId) && is_array($arrMemberIds) && count($arrMemberIds)) {
            $select = (new Select())
                ->from(array('d' => 'client_form_data'))
                ->columns(array('member_id', 'value'))
                ->join(array('f' => 'client_form_fields'), 'd.field_id = f.field_id', 'encrypted', Select::JOIN_LEFT)
                ->where(
                    [
                        (new Where())
                            ->equalTo('d.field_id', (int)$fieldId)
                            ->in('d.member_id', $arrMemberIds)
                    ]
                );

            $arrResult = $this->_db2->fetchAssoc($select);

            foreach ($arrResult as $key => $arrRow) {
                $arrResult[$key]['value'] = $arrRow['encrypted'] == 'Y' ? $this->_encryption->decode($arrRow['value']) : $arrRow['value'];
            }
        }

        return $arrResult;
    }

    /**
     * Load saved data for specific case for specific fields
     *
     * @param int $caseId
     * @param array $arrFieldIds
     * @param bool $booDecode true to decode encrypted fields
     * @return array
     */
    public function getClientFieldsValues($caseId, $arrFieldIds, $booDecode = true)
    {
        $arrResult = array();
        if (!empty($caseId) && is_array($arrFieldIds) && count($arrFieldIds)) {
            $select = (new Select())
                ->from(array('d' => 'client_form_data'))
                ->join(array('f' => 'client_form_fields'), 'd.field_id = f.field_id', array('company_field_id', 'encrypted'), Select::JOIN_LEFT)
                ->where(
                    [
                        'd.field_id' => $arrFieldIds,
                        'd.member_id' => $caseId
                    ]
                );

            $arrResult = $this->_db2->fetchAll($select);

            if ($booDecode) {
                foreach ($arrResult as $key => $arrRow) {
                    if ($arrRow['encrypted'] == 'Y') {
                        $arrResult[$key]['value'] = $this->_encryption->decode($arrRow['value']);
                    }
                }
            }
        }

        return $arrResult;
    }

    public function getFieldValueByCompanyFieldId($companyFieldId, $companyId = 0)
    {
        $companyId = empty($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId;
        $select = (new Select())
            ->from(array('fd' => 'client_form_default'))
            ->columns(array('field_id', 'value', 'form_default_id'))
            ->join(array('f' => 'client_form_fields'), 'f.field_id = fd.field_id', [], Select::JOIN_LEFT)
            ->where(
                [
                    'f.company_field_id' => $companyFieldId,
                    'f.company_id' => (int)$companyId
                ]
            )
            ->order('fd.order ASC');

        return $this->_db2->fetchAll($select);
    }

    public function assignedToField($mode, $memberId, $field)
    {
        $result          = '';
        $marked          = ($field['required'] == 'Y' && $field['status'] == 'F') ? '<span class="error"> *</span>' : '';
        $id              = ($field['field_id'] ? 'id="field-' . $memberId . '-' . $field['field_id'] . '"' : '');
        $defaultAssignTo = ($mode == 'add' ? 'user:' . $this->_auth->getCurrentUserId() : $field['value']);

        /** @var Users $users */
        $users      = $this->_serviceContainer->get(Users::class);
        $assignList = $users->getAssignList('profile', null, true); // select default values

        if ($field['status'] == 'F') {
            $result .= '<select class="profile-select" ' . $id . ' name="field-' . $field['field_id'] . '">';

            foreach ($assignList as $assign) {
                $sel    = $assign['assign_to_id'] == $defaultAssignTo ? 'selected' : '';
                $result .= '<option value="' . $assign['assign_to_id'] . '" ' . $sel . '>' . $assign['assign_to_name'] . '</option>';
            }

            $result .= '</select>';
        } else {
            $option = '';
            foreach ($assignList as $assign) {
                if ($field['value'] == $assign['assign_to_id']) {
                    $option = $assign['assign_to_name'];
                }
            }

            $result = (empty($option) ? '&nbsp;-' : '<span class="profile-text">' . $option . '</span>');
        }

        return '<b>' . $field['label'] . '</b>' . $marked . '<br />' . $result;
    }

    public function RMAField($mode, $memberId, $field)
    {
        $result = '';
        $marked = ($field['required'] == 'Y' && $field['status'] == 'F') ? '<span class="error"> *</span>' : '';
        $id     = ($field['field_id'] ? 'id="field-' . $memberId . '-' . $field['field_id'] . '"' : '');

        /** @var Users $oUsers */
        $oUsers           = $this->_serviceContainer->get(Users::class);
        $arrRMAAssignedTo = $oUsers->getAssignedToUsers(true, null, 0, true);
        $arrRMAAssignedTo = array_merge(array(array('option_id' => '', 'option_name' => '- Select -')), $arrRMAAssignedTo);
        $defaultAssignTo  = ($mode == 'add' ? $this->_auth->getCurrentUserId() : $field['value']);

        if ($field['status'] == 'F') {
            $result .= '<select class="profile-select" ' . $id . ' name="field-' . $field['field_id'] . '">';

            foreach ($arrRMAAssignedTo as $assign) {
                $sel    = $assign['option_id'] == $defaultAssignTo ? 'selected' : '';
                $result .= '<option value="' . $assign['option_id'] . '" ' . $sel . '>' . $assign['option_name'] . '</option>';
            }

            $result .= '</select>';
        } else {
            $option = '';
            foreach ($arrRMAAssignedTo as $assign) {
                if ($field['value'] == $assign['option_id']) {
                    $option = $assign['option_name'];
                }
            }

            $result = (empty($option) ? '&nbsp;-' : '<span class="profile-text">' . $option . '</span>');
        }

        return '<b>' . $field['label'] . '</b>' . $marked . '<br />' . $result;
    }

    public function getStaticCompanyFieldId()
    {
        return array_keys($this->getStaticFieldsMapping());
    }

    public function isStaticField($company_field_id)
    {
        return in_array($company_field_id, $this->getStaticCompanyFieldId());
    }

    public function getStaticColumnName($companyFieldId)
    {
        $arrMapping = $this->getStaticFieldsMapping();
        return $arrMapping[$companyFieldId] ?? false;
    }

    public function getStaticFieldsMapping($booDBFieldsOnly = false)
    {
        $arrMapping = array(
            'username'              => 'username',
            'password'              => 'password',
            'email'                 => 'emailAddress',
            'first_name'            => 'fName',
            'given_names'           => 'fName',
            'last_name'             => 'lName',
            'family_name'           => 'lName',
            'entity_name'           => 'lName',
            'file_number'           => 'fileNumber',
            'agent'                 => 'agent_id',
            'division'              => 'division',
            'created_on'            => 'regTime',
            'case_internal_id'      => 'case_internal_id',
            'applicant_internal_id' => 'applicant_internal_id',
            'case_type'             => 'client_type_id',
        );

        return $booDBFieldsOnly ? array_unique(array_values($arrMapping)) : $arrMapping;
    }

    public function getStaticColumnNameByFieldId($fieldId)
    {
        $booAustralia = $this->_config['site_version']['version'] == 'australia';

        $arrMapping = array(
            'username'       => 'username',
            'password'       => 'password',
            'emailAddress'   => 'email',
            'fName'          => $booAustralia ? 'given_names' : 'first_name',
            'lName'          => $booAustralia ? 'family_name' : 'last_name',
            'fileNumber'     => 'file_number',
            'agent_id'       => 'agent',
            'division'       => 'division',
            'regTime'        => 'created_on',
            'client_type_id' => 'case_type',
        );

        return array_key_exists($fieldId, $arrMapping) ? $arrMapping[$fieldId] : false;
    }

    public function getStaticFields($booAsIdVal = false)
    {
        $select = (new Select())
            ->from('client_form_fields')
            ->where(
                [
                    'company_field_id' => $this->getStaticCompanyFieldId(),
                    'company_id'       => $this->_auth->getCurrentUserCompanyId()
                ]
            );

        $arrFields = $this->_db2->fetchAll($select);

        if ($booAsIdVal) {
            $arrFields = Settings::arrayColumnAsKey('field_id', $arrFields, 'label');
        }

        return $arrFields;
    }

    public function getCompanyGroups($companyId, $booOnlyAssigned = false, $caseTemplateId = 0)
    {
        $select = (new Select())
            ->from('client_form_groups')
            ->where(['company_id' => $companyId])
            ->order(array('order ASC', 'assigned ASC'));

        if ($booOnlyAssigned) {
            $select->where->equalTo('assigned', 'A');
        }

        if (!empty($caseTemplateId)) {
            $select->where(['client_type_id' => $caseTemplateId]);
        }

        return $this->_db2->fetchAll($select);
    }

    public function getAllGroupsAndFields($companyId, $caseTemplateId, $booOnlyAssigned = false)
    {
        $arrResult = array();

        $booCanClientLogin = $this->_company->getPackages()->canCompanyClientLogin($companyId);

        $arrGroups = $this->getCompanyGroups($companyId, $booOnlyAssigned, $caseTemplateId);
        if (is_array($arrGroups) && count($arrGroups) > 0) {
            // Load Fields
            $arrGroupIds = array();
            $unassignedGroupId = 0;
            foreach ($arrGroups as $groupInfo) {
                $arrGroupIds[] = $groupInfo['group_id'];

                $arrResult[$groupInfo['group_id']] = array(
                    'name'       => $groupInfo['title'],
                    'assigned'   => $groupInfo['assigned'],
                    'cols_count' => $groupInfo['cols_count'],
                    'collapsed'  => $groupInfo['collapsed'],
                    'show_title' => $groupInfo['show_title'],
                    'fields'     => array()
                );

                if ($groupInfo['assigned'] == 'U') {
                    $unassignedGroupId = $groupInfo['group_id'];
                }
            }

            $select = (new Select())
                ->from(array('o' => 'client_form_order'))
                ->join(array('f' => 'client_form_fields'), 'f.field_id = o.field_id', Select::SQL_STAR, Select::JOIN_LEFT)
                ->where(['o.group_id' => $arrGroupIds])
                ->order(array('o.field_order ASC'));

            $arrFields = $this->_db2->fetchAll($select);

            $arrFieldsInGroups = array();
            if (count($arrFields) > 0) {
                foreach ($arrFields as $fieldInfo) {
                    $arrFieldsInGroups[] = $fieldInfo['field_id'];

                    $arrResult[$fieldInfo['group_id']]['fields'][] = $this->checkFieldInfo($fieldInfo, $booCanClientLogin);
                }
            }


            // Load all fields for this company
            $arrAllCompanyFields = $this->getCompanyFields($companyId);
            foreach ($arrAllCompanyFields as $arrAllCompanyFieldInfo) {
                if (!in_array($arrAllCompanyFieldInfo['field_id'], $arrFieldsInGroups)) {
                    $arrAllCompanyFieldInfo = $this->checkFieldInfo($arrAllCompanyFieldInfo, $booCanClientLogin);
                    if ($arrAllCompanyFieldInfo && !empty($unassignedGroupId)) {
                        $arrResult[$unassignedGroupId]['fields'][] = $arrAllCompanyFieldInfo;
                    }
                }
            }
        }

        return $arrResult;
    }

    private function checkFieldInfo($fieldInfo, $booCanClientLogin)
    {
        if ($this->isStaticField($fieldInfo['company_field_id'])) { // This is a static field
            $fieldInfo['required'] = 'Y';
            $fieldInfo['readonly'] = $fieldInfo['company_field_id'] != 'division' ? 'Y' : 'N';
            $fieldInfo['disabled'] = 'N';
            $fieldInfo['blocked'] = 'N';

            // Hide username/password fields if company does not have access to 'Client Login' functionality
            if (!$booCanClientLogin && in_array($fieldInfo['company_field_id'], array('username', 'password'))) {
                return false;
            }
        } else { //dynamic field
            $fieldInfo['readonly'] = 'N';
        }

        return $fieldInfo;
    }

    public function getFieldsInfo($arrFieldIds)
    {
        $arrResult = array();
        if (is_array($arrFieldIds) && count($arrFieldIds)) {
            $select = (new Select())
                ->from('client_form_fields')
                ->where(['field_id' => $arrFieldIds]);

            $arrResult = $this->_db2->fetchAssoc($select);
        }

        return $arrResult;
    }

    /**
     * Load case field info by id
     *
     * @param int $fieldId
     * @return array|\ArrayObject|null
     */
    public function getFieldInfoById($fieldId)
    {
        $select = (new Select())
            ->from(array('f' => 'client_form_fields'))
            ->join(array('ft' => 'field_types'), 'ft.field_type_id = f.type', array('field_type_text_id'), Select::JOIN_LEFT)
            ->where(['f.field_id' => (int)$fieldId]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load case field's label by id
     *
     * @param $fieldId
     * @return string
     */
    public function getFieldName($fieldId)
    {
        $arrFieldInfo = $this->getFieldInfoById($fieldId);

        return isset($arrFieldInfo['label']) ? $arrFieldInfo['label'] : '';
    }

    /**
     * Insert new access rights for roles only once
     *
     * @param int $companyId
     * @param string $fieldId
     * @param bool $booUpdateForAdmin
     * @param array $arrFieldDefaultAccess
     */
    public function allowFieldAccessForCompanyAdmin($companyId, $fieldId, $booUpdateForAdmin, $arrFieldDefaultAccess = array())
    {
        $arrNewAccessRights = [];

        $arrAdminRolesIds = [];
        if ($booUpdateForAdmin) {
            // Allow access to this new field to ALL admin roles
            $arrAdminRolesIds = $this->_roles->getCompanyRoles($companyId, 0, false, true, array('admin'));
            if (is_array($arrAdminRolesIds) && !empty($arrAdminRolesIds)) {
                foreach ($arrAdminRolesIds as $adminRoleId) {
                    $arrNewAccessRights[] = array(
                        'role_id'  => $adminRoleId,
                        'field_id' => $fieldId,
                        'status'   => 'F'
                    );
                }
            }
        }

        // Allow access to this new field to other roles defined by the company admin
        foreach ($arrFieldDefaultAccess as $roleId => $access) {
            if (!empty($roleId) && !in_array($roleId, $arrAdminRolesIds) && !empty($access)) {
                $arrNewAccessRights[] = array(
                    'role_id'  => $roleId,
                    'field_id' => $fieldId,
                    'status'   => $access
                );
            }
        }

        foreach ($arrNewAccessRights as $arrNewAccessRightsRow) {
            $this->_db2->insert('client_form_field_access', $arrNewAccessRightsRow);
        }
    }

    public function getDateFieldsByCaseTypes($caseTypeIds)
    {
        $dateTypes = array(
            $this->_parent->getFieldTypes()->getFieldTypeId('date'),
            $this->_parent->getFieldTypes()->getFieldTypeId('rdate')
        );

        return $this->getFields(
            array(
                'select' => [
                    'f'     => ['label', 'field_id'],
                ],
                'where' => [(new Where())->in('f.type', $dateTypes)->in('fg.client_type_id', $caseTypeIds)],
                'order' => ['f.label ASC', 'fg.order ASC', 'f.field_id ASC']
            ),
            false
        );
    }

    public function getDateFields($booDefaultFields = false)
    {
        $dateTypes = array(
            $this->_parent->getFieldTypes()->getFieldTypeId('date'),
            $this->_parent->getFieldTypes()->getFieldTypeId('rdate')
        );

        $where = (new Where())->in('f.type', $dateTypes);
        if ($booDefaultFields) {
            $where->equalTo('fg.company_id', 0);
        }

        return $this->getFields(
            array(
                'select' => [
                    'f' => ['label',  'field_id']
                ],
                'where' => [$where],
                'order' => ['f.label ASC', 'fg.order ASC', 'f.field_id ASC']
            ),
            false
        );
    }

    public function getEmailFields($member_id)
    {
        $type = $this->_parent->getFieldTypes()->getFieldTypeId('email');
        $fields = $this->getFields(
            array(
                'select' => [
                    'f' => ['label', 'field_id'],
                    'd' => ['value']
                ],
                'where' => [(new Where())->equalTo('d.member_id', $member_id)->equalTo('f.type', $type)],
                'order' => ["f.field_id ASC"]
            )
        );

        $emails = array();
        foreach ($fields as $f) {
            $emails[] = $f['value'];
        }

        return $emails;
    }


    /**
     * Load 'file status' field options for a specific company
     *
     * @param int $companyId
     * @return array
     */
    public function getFileStatusOptions($companyId)
    {
        $arrGroupedStatuses = [];

        $arrStatuses = $this->getParent()->getCaseStatuses()->getCompanyCaseStatuses($companyId);
        foreach ($arrStatuses as $arrStatusInfo) {
            $arrGroupedStatuses[] = [
                'id'    => $arrStatusInfo['client_status_id'],
                'label' => $arrStatusInfo['client_status_name'],
            ];
        }

        return $arrGroupedStatuses;
    }

    /**
     * Load readable values for fields by their ids
     *
     * @param int $companyId
     * @param array $arrFields
     * @param bool $booIsApplicant false if fields are loaded for case
     * @param bool $booFormatDates
     * @param bool $booWithCountryNames
     * @param bool $booFormatStaff
     * @param bool $booParseAgents
     * @param bool $booFormatForXfdf
     * @param bool $booEnhancedValues
     * @return array
     */
    public function completeFieldsData($companyId, $arrFields, $booIsApplicant, $booFormatDates = true, $booWithCountryNames = true, $booFormatStaff = true, $booParseAgents = true, $booFormatForXfdf = false, $booEnhancedValues = false)
    {
        $count = count($arrFields);
        for ($i = 0; $i < $count; $i++) {
            $arrFieldInfo = $arrFields[$i];

            $arrFieldInfo['value'] = isset($arrFieldInfo['value']) ? trim($arrFieldInfo['value']) : '';
            if ($arrFieldInfo['value'] === '') {
                $arrFields[$i]['value'] = '-';
                continue;
            }

            list($value,) = $this->getFieldReadableValue(
                $companyId,
                $this->_auth->getCurrentUserDivisionGroupId(),
                $arrFieldInfo,
                $arrFieldInfo['value'],
                $booIsApplicant,
                $booFormatDates,
                $booWithCountryNames,
                $booFormatStaff,
                $booParseAgents,
                $booFormatForXfdf
            );

            $arrFields[$i]['value'] = $value;

            if ($booEnhancedValues) {
               if (in_array($arrFieldInfo['field_type_text_id'], array('date', 'rdate', 'date_repeatable'))) {
                   $arrFieldInfoCopy = $arrFieldInfo;

                   $arrFieldInfo['field_type_text_id'] = $arrFieldInfo['type'] = 'date_age_number';
                   $arrFieldInfo['company_field_id']   .= '_age_number';
                   list($arrFieldInfo['value'],) = $this->getFieldReadableValue(
                       $companyId,
                       $this->_auth->getCurrentUserDivisionGroupId(),
                       $arrFieldInfo,
                       $arrFieldInfo['value'],
                       $booIsApplicant,
                       $booFormatDates,
                       $booWithCountryNames,
                       $booFormatStaff,
                       $booParseAgents,
                       $booFormatForXfdf
                   );
                   $arrFields[] = $arrFieldInfo;

                   $arrFieldInfoCopy['field_type_text_id'] = $arrFieldInfoCopy['type'] = 'date_age_word';
                   $arrFieldInfoCopy['company_field_id']   .= '_age_word';
                   list($arrFieldInfoCopy['value'],) = $this->getFieldReadableValue(
                       $companyId,
                       $this->_auth->getCurrentUserDivisionGroupId(),
                       $arrFieldInfoCopy,
                       $arrFieldInfoCopy['value'],
                       $booIsApplicant,
                       $booFormatDates,
                       $booWithCountryNames,
                       $booFormatStaff,
                       $booParseAgents,
                       $booFormatForXfdf
                   );
                   $arrFields[] = $arrFieldInfoCopy;
               }
           }
        }

        return $arrFields;
    }

    /**
     * Load readable value by its saved value (e.g. from other table)
     *
     * @param int $companyId
     * @param int $divisionGroupId
     * @param array $arrFieldInfo
     * @param string $fieldValue
     * @param bool $booApplicant
     * @param bool $booFormatDates
     * @param bool $booWithCountryNames
     * @param bool $booFormatStaff
     * @param bool $booParseAgents
     * @param bool $booFormatForXfdf
     * @return array of string readable value and boolean if value is correct
     */
    public function getFieldReadableValue(
        $companyId,
        $divisionGroupId,
        $arrFieldInfo,
        $fieldValue,
        $booApplicant = false,
        $booFormatDates = true,
        $booWithCountryNames = true,
        $booFormatStaff = true,
        $booParseAgents = true,
        $booFormatForXfdf = false
    ) {
        $booCorrectValue = false;
        $readableValue = $fieldValue;

        // Replace values with their readable values
        if (isset($arrFieldInfo['field_type_text_id'])) {
            $fieldType = $arrFieldInfo['field_type_text_id'];
        } else {
            $type      = $arrFieldInfo['type'] ?? $arrFieldInfo['field_type'];
            $fieldType = is_numeric($type) ? $this->_parent->getFieldTypes()->getStringFieldTypeById($type) : $type;
        }

        switch ($fieldType) {
            case 'agent_id':
            case 'agent':
            case 'agents':
                // Cache agents list
                if (!array_key_exists('agents', $this->_arrLoadedData)) {
                    $this->_arrLoadedData['agents'] = array();
                    if ($booParseAgents) {
                        $arrAgents = $this->_parent->getAgents();
                        foreach ($arrAgents as $arrAgentInfo) {
                            $this->_arrLoadedData['agents'][$arrAgentInfo['agent_id']] = $this->_parent->generateAgentName($arrAgentInfo, false);
                        }
                    }
                }

                if (empty($fieldValue) || isset($this->_arrLoadedData['agents'][$fieldValue])) {
                    $booCorrectValue = true;
                }

                if (isset($this->_arrLoadedData['agents'][$fieldValue])) {
                    $readableValue = $this->_arrLoadedData['agents'][$fieldValue];
                }
                break;

            case 'country':
                // Cache countries list
                if (!array_key_exists('countries', $this->_arrLoadedData)) {
                    $this->_arrLoadedData['countries'] = $this->_country->getCountries(true);
                }
                if (empty($fieldValue) || array_key_exists($fieldValue, $this->_arrLoadedData['countries']) || in_array($fieldValue, $this->_arrLoadedData['countries'])) {
                    $booCorrectValue = true;
                }

                if ($booWithCountryNames && array_key_exists($fieldValue, $this->_arrLoadedData['countries'])) {
                    $readableValue = $this->_arrLoadedData['countries'][$fieldValue];
                }
                break;

            case 'office':
            case 'division':
            case 'office_multi':
                // Cache offices list
                if (!array_key_exists('offices', $this->_arrLoadedData)) {
                    $this->_arrLoadedData['offices'] = array();

                    $arrOffices = $this->_parent->getDivisions();
                    foreach ($arrOffices as $arrOfficeInfo) {
                        $this->_arrLoadedData['offices'][$arrOfficeInfo['division_id']] = $arrOfficeInfo;
                    }
                }

                if ($fieldType == 'office_multi') {
                    $arrValues = array();
                    $arrFieldValues = explode(',', $fieldValue);
                    foreach ($arrFieldValues as $officeId) {
                        if (array_key_exists($officeId, $this->_arrLoadedData['offices'])) {
                            $arrValues[] = $this->_arrLoadedData['offices'][$officeId]['name'];
                        }
                    }

                    if (empty($fieldValue) || count($arrValues) == count($arrFieldValues)) {
                        $booCorrectValue = true;
                    }

                    $readableValue = implode(', ', $arrValues);
                } else {
                    if (empty($fieldValue) || array_key_exists($fieldValue, $this->_arrLoadedData['offices'])) {
                        $booCorrectValue = true;
                    }

                    if (array_key_exists($fieldValue, $this->_arrLoadedData['offices'])) {
                        $readableValue = $this->_arrLoadedData['offices'][$fieldValue]['name'];
                    }
                }
                break;

            case 'assigned':
            case 'assigned_to':
            case 'staff_responsible_rma':
                // Cache members list
                if (!array_key_exists('company_members', $this->_arrLoadedData)) {
                    $this->_arrLoadedData['company_members'] = array();

                    $arrMemberIds = $this->_company->getCompanyMembersIds($companyId, 'admin_and_user');
                    $arrMembers   = $this->_parent->getMembersInfo($arrMemberIds, false);
                    foreach ($arrMembers as $arrMemberInfo) {
                        $this->_arrLoadedData['company_members'][$arrMemberInfo[0]] = $arrMemberInfo[1];
                    }
                }

                if ($fieldType == 'staff_responsible_rma') {
                    if (empty($fieldValue) || array_key_exists($fieldValue, $this->_arrLoadedData['company_members'])) {
                        $booCorrectValue = true;
                    }

                    if (array_key_exists($fieldValue, $this->_arrLoadedData['company_members'])) {
                        $readableValue = $this->_arrLoadedData['company_members'][$fieldValue];
                    }
                } else {
                    if ($booFormatStaff) {
                        $readableValue = $this->getAssignLabelByValue($companyId, $divisionGroupId, $fieldValue, $this->_arrLoadedData['company_members']);
                        if (empty($fieldValue) || !empty($readableValue)) {
                            $booCorrectValue = true;
                        }
                    } else {
                        // Who knows...
                        $booCorrectValue = true;
                    }
                }
                break;

            case 'related_case_selection':
                // Cache related cases options
                if (!array_key_exists('related_case_selection', $this->_arrLoadedData)) {
                    $this->_arrLoadedData['related_case_selection'] = array();
                    $arrCaseSection = $this->_parent->getApplicantFields()->getRelatedCaseOptions(0);
                    foreach ($arrCaseSection as $arrCaseWithParent) {
                        $this->_arrLoadedData['related_case_selection'][$arrCaseWithParent['case_id']] = $arrCaseWithParent['case_and_applicant_name'];
                    }
                }

                if (empty($fieldValue) || array_key_exists($fieldValue, $this->_arrLoadedData['related_case_selection'])) {
                    $booCorrectValue = true;
                }

                if (array_key_exists($fieldValue, $this->_arrLoadedData['related_case_selection'])) {
                    $readableValue = $this->_arrLoadedData['related_case_selection'][$fieldValue];
                }
                break;

            case 'active_users':
                // Cache active users list
                if (!array_key_exists('active_users', $this->_arrLoadedData)) {
                    $this->_arrLoadedData['active_users'] = array();

                    /** @var Users $oUsers */
                    $oUsers = $this->_serviceContainer->get(Users::class);

                    $arrActiveUsers = $oUsers->getAssignedToUsers(false);
                    foreach ($arrActiveUsers as $arrActiveUserInfo) {
                        $this->_arrLoadedData['active_users'][$arrActiveUserInfo['option_id']] = $arrActiveUserInfo;
                    }
                }

                if (empty($fieldValue) || in_array($fieldValue, array_keys($this->_arrLoadedData['active_users']))) {
                    $booCorrectValue = true;
                }

                if (in_array($fieldValue, array_keys($this->_arrLoadedData['active_users']))) {
                    $readableValue = $this->_arrLoadedData['active_users'][$fieldValue]['option_name'];
                }
                break;

            case 'authorized_agents':
                // Cache divisions groups
                if (!array_key_exists('authorized_agents', $this->_arrLoadedData)) {
                    $this->_arrLoadedData['authorized_agents'] = array();


                    $arrDivisionsGroups = $this->_company->getCompanyDivisions()->getDivisionsGroups($companyId, false);
                    foreach ($arrDivisionsGroups as $arrDivisionGroupInfo) {
                        $this->_arrLoadedData['authorized_agents'][$arrDivisionGroupInfo['division_group_id']] = $arrDivisionGroupInfo['division_group_company'];
                    }
                }

                if (empty($fieldValue) || array_key_exists($fieldValue, $this->_arrLoadedData['authorized_agents'])) {
                    $booCorrectValue = true;
                }

                if (array_key_exists($fieldValue, $this->_arrLoadedData['authorized_agents'])) {
                    $readableValue = $this->_arrLoadedData['authorized_agents'][$fieldValue];
                }
                break;

            case 'contact_sales_agent':
                // Cache contact sales agents list
                if (!array_key_exists('contact_sales_agent', $this->_arrLoadedData)) {
                    $this->_arrLoadedData['contact_sales_agent'] = array();

                    $memberTypeId = $this->_parent->getMemberTypeIdByName('contact');
                    $applicantTypeId = $this->_parent->getApplicantTypes()->getTypeIdByName($companyId, $memberTypeId, 'Sales Agent');
                    $arrAllContacts = $this->_parent->getApplicants('contact', $applicantTypeId);

                    foreach ($arrAllContacts as $arrContactInfo) {
                        $this->_arrLoadedData['contact_sales_agent'][$arrContactInfo['user_id']] = $arrContactInfo['user_name'];
                    }
                }

                if (empty($fieldValue) || array_key_exists($fieldValue, $this->_arrLoadedData['contact_sales_agent'])) {
                    $booCorrectValue = true;
                }

                if (array_key_exists($fieldValue, $this->_arrLoadedData['contact_sales_agent'])) {
                    $readableValue = $this->_arrLoadedData['contact_sales_agent'][$fieldValue];
                }
                break;

            case 'case_type':
                if (!array_key_exists('case_types', $this->_arrLoadedData)) {
                    $this->_arrLoadedData['case_types'] = array();

                    $arrCaseTypes = $this->_parent->getCaseTemplates()->getTemplates($companyId, false, null, false, false);
                    foreach ($arrCaseTypes as $arrCaseTypeInfo) {
                        $this->_arrLoadedData['case_types'][$arrCaseTypeInfo['case_template_id']] = $arrCaseTypeInfo['case_template_name'];
                    }
                }

                if (empty($fieldValue) || in_array($fieldValue, array_keys($this->_arrLoadedData['case_types']))) {
                    $booCorrectValue = true;
                }

                if (in_array($fieldValue, array_keys($this->_arrLoadedData['case_types']))) {
                    $readableValue = $this->_arrLoadedData['case_types'][$fieldValue];
                }
                break;

            case 'categories':
                // Cache categories
                if (!array_key_exists('categories', $this->_arrLoadedData)) {
                    $this->_arrLoadedData['categories'] = array();

                    $arrCategories = $this->_parent->getCaseCategories()->getCompanyCaseCategories($companyId);
                    foreach ($arrCategories as $arrCategoryInfo) {
                        $this->_arrLoadedData['categories'][$arrCategoryInfo['client_category_id']] = $arrCategoryInfo;
                    }
                }

                if (empty($fieldValue) || in_array($fieldValue, array_keys($this->_arrLoadedData['categories']))) {
                    $booCorrectValue = true;
                }

                if (in_array($fieldValue, array_keys($this->_arrLoadedData['categories']))) {
                    $readableValue = $this->_arrLoadedData['categories'][$fieldValue]['client_category_name'];
                }
                break;

            case 'case_status':
                // Cache case statuses
                if (!array_key_exists('case_status', $this->_arrLoadedData)) {
                    $this->_arrLoadedData['case_status'] = array();

                    $arrCompanyStatuses = $this->_parent->getCaseStatuses()->getCompanyCaseStatuses($companyId);
                    foreach ($arrCompanyStatuses as $arrCategoryInfo) {
                        $this->_arrLoadedData['case_status'][$arrCategoryInfo['client_status_id']] = $arrCategoryInfo;
                    }
                }

                if (empty($fieldValue)) {
                    $booCorrectValue = true;
                } else {
                    $arrCaseStatusesIds = explode(',', $fieldValue);

                    $arrLabels = [];
                    foreach ($arrCaseStatusesIds as $caseStatusId) {
                        if (in_array($caseStatusId, array_keys($this->_arrLoadedData['case_status']))) {
                            $arrLabels[] = $this->_arrLoadedData['case_status'][$caseStatusId]['client_status_name'];
                        }
                    }

                    if (count($arrLabels) == count($arrCaseStatusesIds)) {
                        $readableValue   = implode(', ', $arrLabels);
                        $booCorrectValue = true;
                    }
                }
                break;

            case 'select':
            case 'combobox':
            case 'combo':
            case 'radio':
                if ($booApplicant) {
                    // Cache options
                    if (!array_key_exists('client_combo_options', $this->_arrLoadedData)) {
                        $this->_arrLoadedData['client_combo_options'] = array();

                        $arrAllOptions = $this->_parent->getApplicantFields()->getCompanyAllFieldsOptions($companyId);
                        foreach ($arrAllOptions as $arrOptionInfo) {
                            $this->_arrLoadedData['client_combo_options'][$arrOptionInfo['applicant_form_default_id']] = $arrOptionInfo['value'];
                        }
                    }

                    if (empty($fieldValue) || isset($this->_arrLoadedData['client_combo_options'][$fieldValue])) {
                        $booCorrectValue = true;
                    }

                    if (isset($this->_arrLoadedData['client_combo_options'][$fieldValue])) {
                        $readableValue = $this->_arrLoadedData['client_combo_options'][$fieldValue];
                    }
                } else {
                    // Cache options
                    if (!array_key_exists('case_combo_options', $this->_arrLoadedData)) {
                        $this->_arrLoadedData['case_combo_options'] = array();

                        $arrAllOptions = $this->getCompanyAllFieldsOptions($companyId);
                        foreach ($arrAllOptions as $arrOptionInfo) {
                            $this->_arrLoadedData['case_combo_options'][$arrOptionInfo['form_default_id']] = $arrOptionInfo['value'];
                        }
                    }

                    if (empty($fieldValue) || isset($this->_arrLoadedData['case_combo_options'][$fieldValue])) {
                        $booCorrectValue = true;
                    }

                    if (isset($this->_arrLoadedData['case_combo_options'][$fieldValue])) {
                        $readableValue = $this->_arrLoadedData['case_combo_options'][$fieldValue];
                    }
                }
                break;

            case 'multiple_combo':
                $arrFieldValues    = explode(',', $fieldValue);
                $arrReadableValues = array();

                if ($booApplicant) {
                    // Cache options
                    if (!array_key_exists('client_multiple_combo_options', $this->_arrLoadedData)) {
                        $this->_arrLoadedData['client_multiple_combo_options'] = array();

                        $arrAllOptions = $this->_parent->getApplicantFields()->getCompanyAllFieldsOptions($companyId);
                        foreach ($arrAllOptions as $arrOptionInfo) {
                            $this->_arrLoadedData['client_multiple_combo_options'][$arrOptionInfo['applicant_form_default_id']] = $arrOptionInfo['value'];
                        }
                    }

                    if (empty($fieldValue)) {
                        $booCorrectValue = true;
                    } else {
                        $booCorrectAllOptions = true;
                        foreach ($arrFieldValues as $fieldVal) {
                            if (!isset($this->_arrLoadedData['client_multiple_combo_options'][$fieldVal])) {
                                $booCorrectAllOptions = false;
                                break;
                            }
                            $arrReadableValues[] = $this->_arrLoadedData['client_multiple_combo_options'][$fieldVal];
                        }
                        if ($booCorrectAllOptions) {
                            $booCorrectValue = true;
                        }
                    }
                } else {
                    // Cache options
                    if (!array_key_exists('case_multiple_combo_options', $this->_arrLoadedData)) {
                        $this->_arrLoadedData['case_multiple_combo_options'] = array();

                        $arrAllOptions = $this->getCompanyAllFieldsOptions($companyId);
                        foreach ($arrAllOptions as $arrOptionInfo) {
                            $this->_arrLoadedData['case_multiple_combo_options'][$arrOptionInfo['form_default_id']] = $arrOptionInfo['value'];
                        }
                    }

                    if (empty($fieldValue)) {
                        $booCorrectValue = true;
                    } else {
                        $booCorrectAllOptions = true;
                        foreach ($arrFieldValues as $fieldVal) {
                            if (!isset($this->_arrLoadedData['case_multiple_combo_options'][$fieldVal])) {
                                $booCorrectAllOptions = false;
                                break;
                            }
                            $arrReadableValues[] = $this->_arrLoadedData['case_multiple_combo_options'][$fieldVal];
                        }
                        if ($booCorrectAllOptions) {
                            $booCorrectValue = true;
                        }
                    }
                }

                $readableValue = implode(', ', $arrReadableValues);
                break;

            case 'date':
            case 'rdate':
            case 'date_repeatable':
                if (empty($fieldValue)) {
                    $booCorrectValue = true;
                } else {
                    $dateFormatFull = $this->_settings->variableGet('dateFormatFull');
                    if (Settings::isValidDateFormat($fieldValue, $dateFormatFull)) {
                        $booCorrectValue = true;
                    }
                }

                if ($booFormatDates) {
                    $strDateFormat = $booFormatForXfdf ? Settings::DATE_XFDF : '';
                    $readableValue = $this->_settings->formatDate($fieldValue, true, $strDateFormat);
                }
                break;

            case 'date_age_number':
            case 'date_age_word':
                if (empty($fieldValue)) {
                    $booCorrectValue = true;
                } else {
                    $dateFormatFull = $this->_settings->variableGet('dateFormatFull');
                    if (Settings::isValidDateFormat($fieldValue, $dateFormatFull)) {
                        $booCorrectValue = true;
                    }
                }

                if ($booFormatDates) {
                    $readableValue = Settings::calculateAgeForDate($fieldValue, $fieldType == 'date_age_number');
                }
                break;

            case 'office_change_date_time':
                $booCorrectValue = true;

                if ($booFormatDates) {
                    $strDateTimeFormat = $booFormatForXfdf ? 'd-m-Y H:i:s' : 'd M Y H:i:s';
                    $readableValue = $this->_settings->formatDateTime($fieldValue, $strDateTimeFormat);
                }
                break;

            case 'employee' :
                // Cache list of employees
                if (!array_key_exists('employee', $this->_arrLoadedData)) {
                    $this->_arrLoadedData['employee'] = array();

                    $arrAssignedCases = $this->_auth->isCurrentUserSuperadmin() ? array() : $this->_parent->getClientsList();
                    $arrAssignedCases = $this->_parent->getCasesListWithParents($arrAssignedCases);

                    foreach ($arrAssignedCases as $arrCaseInfo) {
                        $this->_arrLoadedData['employee'][$arrCaseInfo['clientId']] = $arrCaseInfo['clientFullName'];
                    }
                }

                if (empty($fieldValue) || isset($this->_arrLoadedData['employee'][$fieldValue])) {
                    $booCorrectValue = true;
                }

                if (isset($this->_arrLoadedData['employee'][$fieldValue])) {
                    $readableValue = $this->_arrLoadedData['employee'][$fieldValue];
                }
                break;

            case 'employer_contacts':
            case 'employer_engagements':
            case 'employer_legal_entities':
            case 'employer_locations':
            case 'employer_third_party_representatives':
                // Cache list of employees
                if (!array_key_exists('employer_options', $this->_arrLoadedData)) {
                    $arrEmployerIds = $this->_company->getCompanyMembersIds($companyId, 'employer');
                    $arrOptions     = $this->_parent->getApplicantFields()->getEmployerRepeatableFieldsGrouped($fieldType, $arrEmployerIds);

                    foreach ($arrOptions as $arrOptionInfo) {
                        $this->_arrLoadedData['employer_options'][$arrOptionInfo['option_id']] = $arrOptionInfo['option_name'];
                    }
                }

                if (empty($fieldValue) || isset($this->_arrLoadedData['employer_options'][$fieldValue])) {
                    $booCorrectValue = true;
                }

                if (isset($this->_arrLoadedData['employer_options'][$fieldValue])) {
                    $readableValue = $this->_arrLoadedData['employer_options'][$fieldValue];
                }
                break;

            case 'immigration_office' :
            case 'visa_office' :
                if (!isset($this->_arrLoadedData[$fieldType])) {
                    $this->_arrLoadedData[$fieldType] = $this->getCompanyFieldOptions($companyId, 0, 'combo', $fieldType);
                }

                if (empty($fieldValue)) {
                    $booCorrectValue = true;
                }

                foreach ($this->_arrLoadedData[$fieldType] as $arrOptionInfo) {
                    if ($fieldValue == $arrOptionInfo['option_id']) {
                        $readableValue = $arrOptionInfo['option_name'];
                        $booCorrectValue = true;
                        break;
                    }
                }
                break;

            case 'list_of_occupations' :
                if (!isset($this->_arrLoadedData[$fieldType])) {
                    $this->_arrLoadedData[$fieldType] = $this->_parent->getApplicantFields()->getListOfOccupations();
                }

                if (empty($fieldValue)) {
                    $booCorrectValue = true;
                }

                foreach ($this->_arrLoadedData[$fieldType] as $arrOptionInfo) {
                    if ($fieldValue == $arrOptionInfo['option_id']) {
                        $readableValue = $arrOptionInfo['option_name'];
                        $booCorrectValue = true;
                        break;
                    }
                }
                break;

            case 'multiple_text_fields' :
            case 'reference' :
                try {
                    json_decode($fieldValue);
                    if (json_last_error() == JSON_ERROR_NONE) {
                        $arrValues     = Json::decode($fieldValue, Json::TYPE_ARRAY);
                        $readableValue = is_array($arrValues) ? implode(', ', $arrValues) : $arrValues;
                    }
                    $booCorrectValue = true;
                } catch (Exception $e) {
                }
                break;

            // 'text','password','number','checkbox','email',''phone','fax','memo', 'photo', 'image', 'file', 'auto_calculated'
            default:
                $booCorrectValue = true;
                break;
        }

        return array($readableValue, $booCorrectValue);
    }

    /**
     * Load dependents list in relation to the website version
     *
     * @param bool $booOnlyVisible
     * @return array
     */
    public function getDependantFields($booOnlyVisible = true)
    {
        $booAustralia = $this->_config['site_version']['version'] == 'australia';

        // Options list for Relationship is dependent to the site version
        $arrRelationshipOptions   = array();
        $arrRelationshipOptions[] = array(
            'option_id'              => 'parent',
            'option_name'            => 'Parent',
            'option_max_count'       => 4,
            'option_max_count_error' => 'Only four Parents can be recorded for each case.',
        );

        $spouseLabel              = $this->_config['site_version']['dependants']['fields']['relationship']['options']['spouse']['label'];
        $arrRelationshipOptions[] = array(
            'option_id'              => 'spouse',
            'option_name'            => $spouseLabel,
            'option_max_count'       => 1,
            'option_max_count_error' => 'Only one ' . $spouseLabel . ' can be recorded for each case.',
        );

        if (!empty($this->_config['site_version']['dependants']['fields']['relationship']['options']['siblings']['show'])) {
            $siblingsCount            = (int)$this->_config['site_version']['dependants']['fields']['relationship']['options']['siblings']['count'];
            $arrRelationshipOptions[] = array(
                'option_id'              => 'sibling',
                'option_name'            => 'Sibling',
                'option_max_count'       => $siblingsCount,
                'option_max_count_error' => "Only $siblingsCount Siblings can be recorded for each case.",
            );
        }

        $childrenCount            = (int)$this->_config['site_version']['dependants']['fields']['relationship']['children_count'];
        $arrRelationshipOptions[] = array(
            'option_id'              => 'child',
            'option_name'            => 'Child',
            'option_max_count'       => $childrenCount,
            'option_max_count_error' => "Only $childrenCount Children can be recorded for each case.",
        );

        if (!empty($this->_config['site_version']['dependants']['fields']['relationship']['options']['other']['show'])) {
            $otherDependantsCount     = (int)$this->_config['site_version']['dependants']['fields']['relationship']['options']['other']['count'];
            $arrRelationshipOptions[] = array(
                'option_id'              => 'other',
                'option_name'            => 'Other',
                'option_max_count'       => $otherDependantsCount,
                'option_max_count_error' => "Only $otherDependantsCount \"Other Dependants\" can be recorded for each case.",
            );
        }

        // Different marital status options - depends on the config
        $arrMaritalStatusOptions = array();

        if (!empty($this->_config['site_version']['dependants']['fields']['marital_status']['options']['single']['show'])) {
            $arrMaritalStatusOptions[] = array(
                'option_id'   => 'single',
                'option_name' => 'Single'
            );
        }

        if (!empty($this->_config['site_version']['dependants']['fields']['marital_status']['options']['married']['show'])) {
            $arrMaritalStatusOptions[] = array(
                'option_id'   => 'married',
                'option_name' => 'Married'
            );
        }

        if (!empty($this->_config['site_version']['dependants']['fields']['marital_status']['options']['engaged']['show'])) {
            $arrMaritalStatusOptions[] = array(
                'option_id'   => 'engaged',
                'option_name' => 'Engaged'
            );
        }

        if (!empty($this->_config['site_version']['dependants']['fields']['marital_status']['options']['widowed']['show'])) {
            $arrMaritalStatusOptions[] = array(
                'option_id'   => 'widowed',
                'option_name' => 'Widowed'
            );
        }

        if (!empty($this->_config['site_version']['dependants']['fields']['marital_status']['options']['separated']['show'])) {
            $arrMaritalStatusOptions[] = array(
                'option_id'   => 'separated',
                'option_name' => 'Separated'
            );
        }

        if (!empty($this->_config['site_version']['dependants']['fields']['marital_status']['options']['divorced']['show'])) {
            $arrMaritalStatusOptions[] = array(
                'option_id'   => 'divorced',
                'option_name' => 'Divorced'
            );
        }

        // Custom checks for the JRCC field
        $booShowJRCCResultField = false;
        if (!empty($this->_config['site_version']['dependants']['fields']['jrcc_result']['show'])) {
            $booShowJRCCResultField = true;
            if (!empty($this->_config['site_version']['dependants']['fields']['jrcc_result']['show_for_non_agent_only']) && $this->_auth->isCurrentUserAuthorizedAgent()) {
                $booShowJRCCResultField = false;
            }
        }

        // Custom checks for the "Include in minute" checkbox
        $booShowIncludeInMinuteField = false;
        if (!empty($this->_config['site_version']['dependants']['fields']['include_in_minute_checkbox']['show'])) {
            $booShowIncludeInMinuteField = true;

            if (!empty($this->_config['site_version']['dependants']['fields']['include_in_minute_checkbox']['show_for_non_agent_only']) && $this->_auth->isCurrentUserAuthorizedAgent()) {
                $booShowIncludeInMinuteField = false;
            }
        }

        $arrFields = array(
            array(
                'field_id'       => 'relationship',
                'field_name'     => 'Relation',
                'field_type'     => 'combo',
                'field_options'  => $arrRelationshipOptions,
                'field_required' => 'Y',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['relationship']['show'])
            ),

            array(
                'field_id'       => 'lName',
                'field_name'     => $this->_company->getCurrentCompanyDefaultLabel('last_name'),
                'field_type'     => 'text',
                'field_required' => 'Y',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['last_name']['show'])
            ),

            array(
                'field_id'       => 'middle_name',
                'field_name'     => 'Middle Name',
                'field_type'     => 'text',
                'field_required' => 'N',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['middle_name']['show'])
            ),

            array(
                'field_id'       => 'fName',
                'field_name'     => $this->_company->getCurrentCompanyDefaultLabel('first_name'),
                'field_type'     => 'text',
                'field_required' => 'Y',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['first_name']['show'])
            ),

            array(
                'field_id'       => 'DOB',
                'field_name'     => 'Date of birth (DOB)',
                'field_type'     => 'date',
                'field_required' => $booAustralia ? 'Y' : 'N',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['dob']['show'])
            ),

            array(
                'field_id'       => 'sex',
                'field_name'     => 'Gender',
                'field_type'     => 'combo',
                'field_options'  => array(
                    array(
                        'option_id'   => 'M',
                        'option_name' => 'Male'
                    ),
                    array(
                        'option_id'   => 'F',
                        'option_name' => 'Female'
                    )
                ),
                'field_required' => 'N',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['sex']['show'])
            ),

            array(
                'field_id'            => 'migrating',
                'field_name'          => 'Migrating?',
                'field_type'          => 'combo',
                'field_options'       => array(
                    array(
                        'option_id'   => 'yes',
                        'option_name' => 'Yes'
                    ),
                    array(
                        'option_id'   => 'no',
                        'option_name' => 'No'
                    )
                ),
                'field_default_value' => 'yes',
                'field_required'      => 'N',
                'field_disabled'      => 'N',
                'field_visible'       => !empty($this->_config['site_version']['dependants']['fields']['migrating']['show'])
            ),

            array(
                'field_id'       => 'passport_num',
                'field_name'     => 'Passport Number',
                'field_type'     => 'text',
                'field_required' => !empty($this->_config['site_version']['dependants']['fields']['passport_num']['required']) ? 'Y' : 'N',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['passport_num']['show'])
            ),

            array(
                'field_id'       => 'nationality',
                'field_name'     => 'Nationality',
                'field_type'     => 'text',
                'field_required' => 'N',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['nationality']['show'])
            ),

            array(
                'field_id'       => 'passport_date',
                'field_name'     => 'Passport Expiry Date',
                'field_type'     => 'date',
                'field_required' => 'N',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['passport_date']['show'])
            ),

            array(
                'field_id'       => 'medical_expiration_date',
                'field_name'     => 'Medical Expiry Date',
                'field_type'     => 'date',
                'field_required' => 'N',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['medical_expiration_date']['show'])
            ),

            array(
                'field_id'        => 'uci',
                'field_name'      => 'Unique Client Identifier (UCI)',
                'field_type'      => 'text',
                'field_maxlength' => 26,
                'field_required'  => 'N',
                'field_disabled'  => 'N',
                'field_visible'   => !empty($this->_config['site_version']['dependants']['fields']['uci']['show'])
            ),

            array(
                'field_id'       => 'photo',
                'field_name'     => 'Photo',
                'field_type'     => 'photo',
                'field_required' => 'N',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['photo']['show'])
            ),

            array(
                'field_id'       => 'dependent_id',
                'field_name'     => 'Dependent_id',
                'field_type'     => 'hidden',
                'field_required' => 'N',
                'field_disabled' => 'N',
                'field_visible'  => true
            ),

            array(
                'field_id'       => 'profession',
                'field_name'     => $this->_config['site_version']['dependants']['fields']['profession']['label'],
                'field_type'     => 'text',
                'field_required' => 'N',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['profession']['show'])
            ),

            array(
                'field_id'       => 'place_of_birth',
                'field_name'     => 'Place of birth',
                'field_type'     => 'text',
                'field_required' => 'N',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['place_of_birth']['show'])
            ),

            array(
                'field_id'       => 'country_of_birth',
                'field_name'     => 'Country of Birth',
                'field_type'     => 'country',
                'field_required' => empty($this->_config['site_version']['dependants']['fields']['country_of_birth']['required']) ? 'N' : 'Y',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['country_of_birth']['show'])
            ),

            array(
                'field_id'       => 'marital_status',
                'field_name'     => 'Marital Status',
                'field_type'     => 'combo',
                'field_options'  => $arrMaritalStatusOptions,
                'field_required' => empty($this->_config['site_version']['dependants']['fields']['marital_status']['required']) ? 'N' : 'Y',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['marital_status']['show'])
            ),

            array(
                'field_id'       => 'spouse_name',
                'field_name'     => 'Name of spouse',
                'field_type'     => 'text',
                'field_required' => 'N',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['spouse_name']['show'])
            ),

            array(
                'field_id'       => 'country_of_residence',
                'field_name'     => 'Country of Residence',
                'field_type'     => 'country',
                'field_required' => 'N',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['country_of_residence']['show'])
            ),

            array(
                'field_id'       => 'country_of_citizenship',
                'field_name'     => 'Country of Citizenship',
                'field_type'     => 'country',
                'field_required' => 'N',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['country_of_citizenship']['show'])
            ),

            array(
                'field_id'       => 'passport_issuing_country',
                'field_name'     => 'Passport Issuing Country',
                'field_type'     => 'country',
                'field_required' => 'N',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['passport_issuing_country']['show'])
            ),

            array(
                'field_id'            => 'third_country_visa',
                'field_name'          => 'Third Country Visa',
                'field_type'          => 'radio',
                'field_options'       => array(
                    array(
                        'option_id'   => 'Y',
                        'option_name' => 'Yes'
                    ),
                    array(
                        'option_id'   => 'N',
                        'option_name' => 'No'
                    )
                ),
                'field_required'      => 'N',
                'field_disabled'      => 'N',
                'field_default_value' => 'N',
                'field_visible'       => !empty($this->_config['site_version']['dependants']['fields']['third_country_visa']['show'])
            ),

            array(
                'field_id'       => 'jrcc_result',
                'field_name'     => 'JRCC Result',
                'field_type'     => 'combo',
                'field_options'  => array(
                    array(
                        'option_id'   => 'nothing_derogatory',
                        'option_name' => 'Nothing Derogatory at this time'
                    ),
                    array(
                        'option_id'   => 'unable_to_verify_identity',
                        'option_name' => 'Unable to verify identity'
                    ),
                    array(
                        'option_id'   => 'cleared',
                        'option_name' => 'Cleared'
                    ),
                    array(
                        'option_id'   => 'financial_irregularities',
                        'option_name' => 'Financial Irregularities'
                    ),
                    array(
                        'option_id'   => 'security_threat',
                        'option_name' => 'Security Threat'
                    ),
                    array(
                        'option_id'   => 'us_visa_denial',
                        'option_name' => 'US Visa Denial'
                    ),
                    array(
                        'option_id'   => 'uk_visa_denial',
                        'option_name' => 'UK Visa Denial'
                    ),
                    array(
                        'option_id'   => 'visa_revoked',
                        'option_name' => 'Visa Revoked'
                    ),
                    array(
                        'option_id'   => 'rejected_by_other_cip',
                        'option_name' => 'Rejected by Other CIP'
                    ),
                    array(
                        'option_id'   => 'third_country_visa_refused',
                        'option_name' => 'Third country visa refused'
                    ),
                    array(
                        'option_id'   => 'passport_reported_lost_stolen',
                        'option_name' => 'Passport reported lost/stolen'
                    )
                ),
                'field_required' => 'N',
                'field_disabled' => 'N',
                'field_visible'  => $booShowJRCCResultField
            ),

            array(
                'field_id'            => 'include_in_minute_checkbox',
                'field_name'          => 'Include in Minute',
                'field_type'          => 'checkbox',
                'field_required'      => 'N',
                'field_disabled'      => 'N',
                'field_default_value' => true,
                'field_visible'       => $booShowIncludeInMinuteField
            ),

            array(
                'field_id'            => 'main_applicant_address_is_the_same',
                'field_name'          => "Address is same as the main applicant's address",
                'field_type'          => 'checkbox',
                'field_required'      => 'N',
                'field_disabled'      => 'N',
                'field_default_value' => true,
                'field_visible'       => !empty($this->_config['site_version']['dependants']['fields']['main_applicant_address_is_the_same']['show'])
            ),

            array(
                'field_id'       => 'address',
                'field_name'     => 'Address',
                'field_type'     => empty($this->_config['site_version']['dependants']['fields']['address']['multiline']) ? 'text' : 'memo',
                'field_required' => 'Y',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['address']['show'])
            ),

            array(
                'field_id'       => 'city',
                'field_name'     => 'City',
                'field_type'     => 'text',
                'field_required' => 'Y',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['city']['show'])
            ),

            array(
                'field_id'       => 'country',
                'field_name'     => 'Country',
                'field_type'     => 'country',
                'field_required' => 'Y',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['country']['show'])
            ),

            array(
                'field_id'       => 'region',
                'field_name'     => 'Region',
                'field_type'     => 'text',
                'field_required' => 'N',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['region']['show'])
            ),

            array(
                'field_id'       => 'postal_code',
                'field_name'     => 'Postal Code',
                'field_type'     => 'text',
                'field_required' => 'N',
                'field_disabled' => 'N',
                'field_visible'  => !empty($this->_config['site_version']['dependants']['fields']['postal_code']['show'])
            ),
        );

        $arrResult = array();
        foreach ($arrFields as $arrFieldInfo) {
            if ($arrFieldInfo['field_visible'] || !$booOnlyVisible) {
                $arrFieldInfo['field_unique_id']   = $arrFieldInfo['field_id'];
                $arrFieldInfo['field_group']       = 'Dependants';
                $arrFieldInfo['field_column_show'] = false;

                $arrResult[] = $arrFieldInfo;
            }
        }

        return $arrResult;
    }

    public function getDependentFieldLabelById($fieldId)
    {
        $fieldLabel = '';
        $arrFields = $this->getDependantFields();

        foreach ($arrFields as $arrFieldInfo) {
            if ($arrFieldInfo['field_unique_id'] == $fieldId) {
                $fieldLabel = $arrFieldInfo['field_name'];
                break;
            }
        }

        return $fieldLabel;
    }

    public function getDependentRelationshipLabelById($relationshipId)
    {
        return $this->getDependentFieldOptionLabel('relationship', $relationshipId);
    }

    public function getDependentFieldOptionLabel($fieldId, $fieldValue)
    {
        $optionLabel = '';
        $arrFields = $this->getDependantFields();

        foreach ($arrFields as $arrFieldInfo) {
            if ($arrFieldInfo['field_unique_id'] == $fieldId) {
                $optionLabel = $this->getDependentFieldReadableValue($arrFieldInfo, $fieldValue);
                break;
            }
        }

        return $optionLabel;
    }

    public function getDependentFieldReadableValue($arrFieldInfo, $fieldValue, $strDefaultEmpty = '*blank*')
    {
        if ($fieldValue == '') {
            return $strDefaultEmpty;
        }

        $readableValue = $fieldValue;
        switch ($arrFieldInfo['field_type']) {
            case 'combo':
                foreach ($arrFieldInfo['field_options'] as $arrOptionInfo) {
                    if ($arrOptionInfo['option_id'] == $fieldValue) {
                        $readableValue = $arrOptionInfo['option_name'];
                        break;
                    }
                }
                break;

            case 'date':
                $readableValue = Settings::isDateEmpty($fieldValue) ? $strDefaultEmpty : $this->_settings->formatDate($fieldValue);
                break;

            case 'date_age_word':
                $readableValue = Settings::isDateEmpty($fieldValue) ? $strDefaultEmpty : Settings::calculateAgeForDate($fieldValue, false);
                break;

            case 'checkbox':
                $readableValue = $fieldValue == 'Y' ? 'Checked' : 'Unchecked';
                break;

            case 'text':
            default:
                break;
        }

        return $readableValue;
    }

    /**
     * Generate a string representation of dependants array
     *
     * @param array $arrDependents
     * @return string
     */
    public function getReadableDependantsRows($arrDependents)
    {
        // Dependants list will be provided if needed
        $arrDependentsList = array();
        try {
            // A format for each dependant's row we'll show one by one
            $dependantRowFormat = $this->_config['site_version']['dependants']['template_row_format'];

            if (!empty($dependantRowFormat) && !empty($arrDependents)) {
                // Load the list of all possible dependant's fields
                $arrUserAllowedDependentFields = $this->getDependantFields(false);

                foreach ($arrDependents as $arrDependentInfo) {
                    $arrReadableDependantFields = array();
                    foreach ($arrUserAllowedDependentFields as $arrUserAllowedDependentFieldInfo) {
                        $fieldId = $arrUserAllowedDependentFieldInfo['field_id'];

                        if ($arrUserAllowedDependentFieldInfo['field_visible']) {
                            // Try to load / use a saved dependent's field's value if it is visible (user has access)

                            // For date fields - also calculate/prepare "age number" and "age word" fields
                            if ($arrUserAllowedDependentFieldInfo['field_type'] === 'date') {
                                $arrUserAllowedDependentFieldInfo['field_type']       = 'date_age_number';
                                $arrReadableDependantFields[$fieldId . '_age_number'] = $this->getDependentFieldReadableValue($arrUserAllowedDependentFieldInfo, $arrDependentInfo[$fieldId], '');

                                $arrUserAllowedDependentFieldInfo['field_type']     = 'date_age_word';
                                $arrReadableDependantFields[$fieldId . '_age_word'] = $this->getDependentFieldReadableValue($arrUserAllowedDependentFieldInfo, $arrDependentInfo[$fieldId], '');

                                $arrUserAllowedDependentFieldInfo['field_type'] = 'date';
                            }
                            $arrReadableDependantFields[$fieldId] = $this->getDependentFieldReadableValue($arrUserAllowedDependentFieldInfo, $arrDependentInfo[$fieldId], '');

                            // For the child - check his/her gender and use "Son" or "Daughter" term
                            if ($fieldId === 'relationship') {
                                if (isset($arrDependentInfo[$fieldId]) && $arrDependentInfo[$fieldId] == 'child') {
                                    switch ($arrDependentInfo['sex']) {
                                        case 'M':
                                            $arrReadableDependantFields['relationship'] = $this->_tr->translate('Son');
                                            break;

                                        case 'F':
                                            $arrReadableDependantFields['relationship'] = $this->_tr->translate('Daughter');
                                            break;

                                        default:
                                            break;
                                    }
                                }
                            }
                        } else {
                            // Otherwise, just show an empty string
                            $arrReadableDependantFields[$fieldId] = '';
                        }
                    }

                    $strDependantRow = $this->_settings::sprintfAssoc(
                        $dependantRowFormat,
                        $arrReadableDependantFields
                    );

                    // Remove extra spaces
                    $strDependantRow = preg_replace('!\s+!', ' ', $strDependantRow);
                    $strDependantRow = str_replace('()', '', $strDependantRow);
                    $strDependantRow = trim(str_replace(' ,', ',', $strDependantRow));

                    // If there is nothing to show - just skip
                    // E.g. there is no access to all fields
                    if (!empty($strDependantRow) && $strDependantRow != ',') {
                        $arrDependentsList[] = $strDependantRow;
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return implode('; ', $arrDependentsList);
    }


    /**
     * Load fields list for the specific company and current user has access to
     *
     * @param int $companyId
     * @param int $caseType
     * @return array
     */
    public function getCaseTemplateFields($companyId, $caseType)
    {
        return $this->getFields(
            array(
                'select' => [
                    'f'  => ['*'],
                    'o'  => ['field_id'],
                    'fa' => ['status', 'field_access' => 'status']
                ],

                'where'      => [(new Where())->equalTo('fg.client_type_id', $caseType)],
                'unassigned' => true,
            ),
            false,
            $companyId
        );
    }

    /**
     * Load fields (for which current user has access to) values for specific cases
     *
     * @param int $companyId
     * @param array $arrCaseIds
     * @param array $arrFieldIds
     * @return array
     */
    public function getFieldsDataForUserAllowedFields($companyId, $arrCaseIds, $arrFieldIds)
    {
        $arrSavedData = array();

        if (count($arrCaseIds)) {
            $arrUserAllowedFieldIds = $this->getUserAllowedFieldIds($companyId);
            $arrFieldIds = array_intersect($arrFieldIds, $arrUserAllowedFieldIds);
            if (count($arrFieldIds)) {
                $select = (new Select())
                    ->from(array('d' => 'client_form_data'))
                    ->columns(array('member_id', 'value'))
                    ->join(array('f' => 'client_form_fields'), 'f.field_id = d.field_id', array('company_field_id', 'encrypted', 'type'), Select::JOIN_LEFT)
                    ->join(array('ft' => 'field_types'), 'ft.field_type_id = f.type', array('field_type_text_id'), Select::JOIN_LEFT)
                    ->where(
                        [
                            'd.field_id' => $arrFieldIds,
                            'd.member_id' => $arrCaseIds
                        ]
                    );

                $arrSavedData = $this->_db2->fetchAll($select);

                foreach ($arrSavedData as $key => $arrClientSavedData) {
                    if ($arrClientSavedData['encrypted'] == 'Y' && $arrClientSavedData['value'] != '') {
                        $arrSavedData[$key]['value'] = $this->_encryption->decode($arrClientSavedData['value']);
                    }
                }

                $arrSavedData = $this->completeFieldsData($companyId, $arrSavedData, false);
            }
        }

        return $arrSavedData;
    }

    /**
     * Get/check access to cases fields for the current user
     *
     * @param int $companyId
     * @param int $memberId member id for which we want to load the list of assigned roles
     * @param bool $booTextFieldId true to load text ids instead their int ids
     * @return array
     */
    public function getUserAllowedFieldIds($companyId, $memberId = 0, $booTextFieldId = false)
    {
        $columns = $booTextFieldId ? [] : ['field_id'];
        $select = (new Select())
            ->from(array('fa' => 'client_form_field_access'))
            ->columns($columns)
            ->join(array('fo' => 'client_form_order'), 'fo.field_id = fa.field_id', [], Select::JOIN_LEFT)
            ->join(array('fg' => 'client_form_groups'), 'fg.group_id = fo.group_id', [], Select::JOIN_LEFT)
            ->where(
                [
                    'fg.company_id' => (int)$companyId,
                    'fg.assigned' => 'A'
                ]
            );

        if (!$this->_auth->isCurrentUserSuperadmin()) {
            $arrRoles = $this->_parent->getMemberRoles($memberId);
            if (is_array($arrRoles) && !empty($arrRoles)) {
                $select->where(
                    [
                        'fa.role_id' => $arrRoles
                    ]
                );
            }
        }

        if ($booTextFieldId) {
            $select->join(array('f' => 'client_form_fields'), 'f.field_id = fa.field_id', 'company_field_id');
        }

        return array_unique($this->_db2->fetchCol($select));
    }

    /**
     * Check if current member has access to the specific field
     *
     * @param string $textFieldId
     * @return bool
     */
    public function hasCurrentMemberAccessToField($textFieldId)
    {
        // Make sure that a current user has access (read-only or full) to the field
        $identity             = $this->_auth->getIdentity();
        $arrUserAllowedFields = $identity->user_allowed_fields ?? array();

        return in_array($textFieldId, $arrUserAllowedFields);
    }

    /**
     * Check if current member has access to the specific field by int id
     *
     * @param int $fieldId
     * @return bool
     */
    public function hasCurrentMemberAccessToFieldById($fieldId)
    {
        $booHasAccess = false;
        if ($this->_auth->isCurrentUserSuperadmin()) {
            $booHasAccess = true;
        } else {
            $arrFieldInfo = $this->getFieldInfoById($fieldId);
            if (isset($arrFieldInfo['company_id']) && $arrFieldInfo['company_id'] == $this->_auth->getCurrentUserCompanyId()) {
                $booHasAccess = true;
            }
        }

        return $booHasAccess;
    }


    /**
     * Load a list of grouped fields based on the provided filtering data
     *
     * @param array $options
     * @param bool $booSelectFromData
     * @param null|int $companyId
     * @return array
     */
    public function getFields($options = array(), $booSelectFromData = true, $companyId = null)
    {
        $query = '';
        try {
            if (!empty($options['select'])) {
                $selectFg = $options['select']['fg'] ?? ["*"];
                $selectO  = $options['select']['o'] ?? [];
                //f.field_id and f.type is important fields
                $selectF = $options['select']['f'] ?? ['field_id', 'type'];
                //ft.field_type_text_id is important field
                $selectFt = $options['select']['ft'] ?? ['field_type_text_id'];
                $selectFa = $options['select']['fa'] ?? [];
                $selectD  = $options['select']['d'] ?? [];

                if (!in_array('field_id', $selectF) && !in_array('*', $selectF)) {
                    $selectF[] = 'field_id';
                }

                if (!in_array('type', $selectF) && !in_array('*', $selectF)) {
                    $selectF[] = 'type';
                }

                if (!in_array('field_type_text_id', $selectFt) && !in_array('*', $selectFt)) {
                    $selectFt[] = 'field_type_text_id';
                }
            } else {
                $selectFg = ["*"];
                $selectO  = [];
                //f.field_id and f.type is important fields
                $selectF = ['field_id', 'type'];
                //ft.field_type_text_id is important field
                $selectFt = ['field_type_text_id'];
                $selectFa = [];
                $selectD  = [];
            }

            // If we need to load saved data - we also need to decrypt it if it was encrypted
            if ($booSelectFromData && !in_array('encrypted', $selectF) && !in_array('*', $selectF)) {
                $selectF[] = 'encrypted';
            }

            // special conditions
            $booWithCountryNames  = $options['booWithCountryNames'] ?? false;
            $booFormatDates       = $options['booFormatDates'] ?? false;
            $booFormatStaff       = $options['booFormatStaff'] ?? false;
            $clientFormDataOption = isset($options['clientFormDataOption']) ? " AND " . $options['clientFormDataOption'] : '';

            $companyId = is_null($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId;

            // Get fields
            $select = (new Select())
                ->quantifier(Select::QUANTIFIER_DISTINCT)
                ->from(['fg' => 'client_form_groups'])
                ->columns($selectFg)
                ->join(['o' => 'client_form_order'], 'o.group_id = fg.group_id', $selectO, Select::JOIN_LEFT)
                ->join(['f' => 'client_form_fields'], 'f.field_id = o.field_id', $selectF, Select::JOIN_LEFT)
                ->join(['ft' => 'field_types'], 'ft.field_type_id = f.type', $selectFt, Select::JOIN_LEFT)
                ->join(['fa' => 'client_form_field_access'], 'fa.field_id = o.field_id', $selectFa, Select::JOIN_LEFT)
                ->where(['fg.company_id' => $companyId]);

            if (!empty(isset($options['order']))) {
                $select->order($options['order']);
            }

            if (isset($options['unassigned']) && $options['unassigned']) {
                $select->where->equalTo('fg.assigned', 'A');
            }

            // Join data table only when required
            if ($booSelectFromData) {
                $select->join(['d' => 'client_form_data'], new PredicateExpression(sprintf('d.field_id = f.field_id %s', $clientFormDataOption)), $selectD, Select::JOIN_LEFT);
            }

            //role access
            $booCheckRoleAccessRights = $options['booCheckRoleAccessRights'] ?? true;
            if ($booCheckRoleAccessRights) {
                $arrRoles = $this->_parent->getMemberRoles();
                if (is_array($arrRoles) && !empty($arrRoles) && !$this->_auth->isCurrentUserSuperadmin()) {
                    $select->where
                        ->in('fa.role_id', $arrRoles);
                }
            }

            if (!empty($options['where'])) {
                $select->where->addPredicates($options['where']);
            }

            $fields = $this->_db2->fetchAll($select);

            // Remove fields duplicates
            // And leave fields with high access rights
            // I.e. with Full Access instead of Read only
            $arrFilteredFields = array();
            foreach ($fields as $arrFieldInfo) {
                $memberId = is_array($arrFieldInfo) && array_key_exists('member_id', $arrFieldInfo) ? $arrFieldInfo['member_id'] : 0;
                if ((is_array($arrFilteredFields) && !array_key_exists($memberId, $arrFilteredFields)) || (is_array($arrFilteredFields[$memberId]) && !array_key_exists($arrFieldInfo['field_id'], $arrFilteredFields[$memberId]))) {
                    $arrFilteredFields[$memberId][$arrFieldInfo['field_id']] = $arrFieldInfo;
                } elseif (is_array($arrFieldInfo) && array_key_exists('field_access', $arrFieldInfo) && $arrFieldInfo['field_access'] != $arrFilteredFields[$memberId][$arrFieldInfo['field_id']]['field_access'] && $arrFieldInfo['field_access'] == 'F') {
                    $arrFilteredFields[$memberId][$arrFieldInfo['field_id']] = $arrFieldInfo;
                }
            }
            $fields = array();
            foreach ($arrFilteredFields as $arrMemberFilteredFieldData) {
                foreach ($arrMemberFilteredFieldData as $arrFieldData) {
                    $fields[] = $arrFieldData;
                }
            }

            foreach ($fields as $key => $arrClientSavedData) {
                if (is_array($arrClientSavedData) && array_key_exists('encrypted', $arrClientSavedData) && array_key_exists('value', $arrClientSavedData) && $arrClientSavedData['encrypted'] == 'Y') {
                    $fields[$key]['value'] = $this->_encryption->decode($arrClientSavedData['value']);
                }
            }

            if ($booSelectFromData) {
                $fields = $this->completeFieldsData($companyId, $fields, false, $booFormatDates, $booWithCountryNames, $booFormatStaff);
            }
        } catch (Exception $e) {
            $fields = array();
            $this->_log->debugErrorToFile($e->getMessage(), $query . PHP_EOL . $e->getTraceAsString());
        }

        return $fields;
    }

    /**
     * Load accounting fields used in advanced search
     *
     * @param bool $booIdsOnly
     * @param bool $booAddPrefix
     * @return array
     */
    public function getAccountingFields($booIdsOnly = false, $booAddPrefix = true)
    {
        $arrColumns = array();

        if ($this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled()) {
            $arrColumns[] = array(
                'field_id'           => '',
                'field_unique_id'    => 'total_fees_paid',
                'field_name'         => 'Total Fees Paid',
                'field_type'         => 'text',
                'field_encrypted'    => 'N',
                'field_required'     => 'Y',
                'field_disabled'     => 'N',
                'field_access'       => 'F',
                'field_column_show'  => '',
                'field_use_full_row' => ''
            );
        }

        $arrColumns[] = array(
            'field_id'           => '',
            'field_unique_id'    => 'total_fees',
            'field_name'         => 'Total Fees',
            'field_type'         => 'text',
            'field_encrypted'    => 'N',
            'field_required'     => 'Y',
            'field_disabled'     => 'N',
            'field_access'       => 'F',
            'field_column_show'  => '',
            'field_use_full_row' => ''
        );

        $taLabel = $this->_company->getCurrentCompanyDefaultLabel('trust_account');

        $arrColumns[] = array(
            'field_id'           => '',
            'field_unique_id'    => 'trust_account_summary_secondary',
            'field_name'         => 'Secondary ' . $taLabel . ' Summary',
            'field_type'         => 'text',
            'field_encrypted'    => 'N',
            'field_required'     => 'Y',
            'field_disabled'     => 'N',
            'field_access'       => 'F',
            'field_column_show'  => '',
            'field_use_full_row' => ''
        );

        $arrColumns[] = array(
            'field_id'           => '',
            'field_unique_id'    => 'trust_account_summary_primary',
            'field_name'         => 'Primary ' . $taLabel . ' Summary',
            'field_type'         => 'text',
            'field_encrypted'    => 'N',
            'field_required'     => 'Y',
            'field_disabled'     => 'N',
            'field_access'       => 'F',
            'field_column_show'  => '',
            'field_use_full_row' => ''
        );

        $arrColumns[] = array(
            'field_id'           => '',
            'field_unique_id'    => 'outstanding_balance_secondary',
            'field_name'         => 'Secondary ' . $taLabel . ' Outstand. Balance',
            'field_type'         => 'text',
            'field_encrypted'    => 'N',
            'field_required'     => 'Y',
            'field_disabled'     => 'N',
            'field_access'       => 'F',
            'field_column_show'  => '',
            'field_use_full_row' => ''
        );

        $arrColumns[] = array(
            'field_id'           => '',
            'field_unique_id'    => 'outstanding_balance_primary',
            'field_name'         => 'Primary ' . $taLabel . ' Outstand. Balance',
            'field_type'         => 'text',
            'field_encrypted'    => 'N',
            'field_required'     => 'Y',
            'field_disabled'     => 'N',
            'field_access'       => 'F',
            'field_column_show'  => '',
            'field_use_full_row' => ''
        );

        if ($booIdsOnly) {
            $arrResult = array();
            foreach ($arrColumns as $arrColumnInfo) {
                $prefix = $booAddPrefix ? 'accounting_' : '';

                $arrResult[] = $prefix . $arrColumnInfo['field_unique_id'];
            }
        } else {
            $arrResult = $arrColumns;
        }

        return $arrResult;
    }

    public function getClientProfile($mode, $memberId = null, $caseTemplateId = null, $companyId = null)
    {
        $companyId = is_null($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId;

        // Get client static data
        $clientInfo = ($mode == 'edit' ? $this->_parent->getClientInfo($memberId, true) : array());
        if ($clientInfo === false) {
            return false;
        }

        // Load case template id
        // And load groups/fields assigned to this template
        if (empty($caseTemplateId)) {
            if (empty($memberId) || !array_key_exists('client_type_id', $clientInfo) || empty($clientInfo['client_type_id'])) {
                $caseTemplateId = $this->_parent->getCaseTemplates()->getDefaultCompanyCaseTemplate($companyId);
            } else {
                $caseTemplateId = $clientInfo['client_type_id'];
            }
        }

        // Get groups
        $groups = $this->getGroups(true, $caseTemplateId, $companyId);
        if (empty($groups)) {
            return false;
        }

        //groups access
        $arrGroupsWhere = Settings::arrayColumnAsKey('group_id', $groups, 'group_id');

        //get fields
        $select = (new Select())
            ->from(array('o' => 'client_form_order'))
            ->columns(array('order_id', 'group_id', 'field_id'))
            ->join(
                array('f' => 'client_form_fields'),
                'f.field_id = o.field_id',
                array('type', 'label', 'maxlength', 'custom_height', 'min_value', 'max_value', 'encrypted', 'required', 'company_field_id', 'multiple_values', 'can_edit_in_gui'),
                Select::JOIN_LEFT
            )
            ->join(array('fa' => 'client_form_field_access'), 'fa.field_id = o.field_id ', array('status'), Select::JOIN_LEFT)
            ->where(['o.group_id' => $arrGroupsWhere])
            ->order(array('o.field_order ASC', 'f.field_id ASC'));

        //role access
        if (!$this->_auth->isCurrentUserSuperadmin()) {
            $arrRoles = $this->_parent->getMemberRoles();
            if (is_array($arrRoles) && !empty($arrRoles)) {
                $select->where(['fa.role_id' => $arrRoles]);
            }
        }

        $booCheckIfDataEncrypted = false;
        if ($mode == 'edit' && !empty($memberId)) {
            $select->join(array('d' => 'client_form_data'), new PredicateExpression('d.field_id = f.field_id AND d.member_id ='. $memberId), array('value'), Select::JOIN_LEFT);
            $booCheckIfDataEncrypted = true;
        }

        //get fields
        $fields = $this->_db2->fetchAll($select);
        if (empty($fields)) {
            return false;
        }

        // Decrypt saved data, if needed
        if ($booCheckIfDataEncrypted) {
            foreach ($fields as $key => $arrSavedInfo) {
                if ($arrSavedInfo['encrypted'] == 'Y') {
                    $fields[$key]['value'] = $this->_encryption->decode($arrSavedInfo['value']);
                }
            }
        }

        //group fields by status
        $fields = $this->groupFieldsByStatus($fields);

        $profile = $fields_ids = array();
        $isAllowAddClient = 0;
        $isAllGroupsReadOnly = true;

        $arrFieldNames = array(
            $this->getStaticColumnNameByFieldId('fName'),
            $this->getStaticColumnNameByFieldId('lName'),
            'case_name'
        );

        //group fields by group_id and order
        foreach ($groups as $group) {
            $group_id = $group['group_id'];

            //dependants group
            if ($group['title'] == 'Dependants') {
                $profile[$group_id]['fields'] = array();
            }

            $is_editable_fields = false;

            //add fields to group
            foreach ($fields as $field) {
                if ($field['group_id'] == $group_id) {
                    $field_id = $field['field_id'];

                    //add value option to field info
                    if ($mode == 'add') {
                        $field['value'] = '';
                    } //add value to static fields
                    elseif ($this->isStaticField($field['company_field_id'])) {
                        $field['value'] = $clientInfo[$this->getStaticColumnName($field['company_field_id'])];
                    }

                    if ($this->_parent->getFieldTypes()->getFieldTypeId('reference') == $field['type'] && $field['value'] != '') {
                        $booMultipleValues = isset($field['multiple_values']) && $field['multiple_values'] == 'Y';
                        $field['value'] = $this->prepareReferenceField($field['value'], $booMultipleValues);
                    }

                    if ($this->_parent->getFieldTypes()->getFieldTypeId('multiple_text_fields') == $field['type'] && $field['value'] != '') {
                        if (!(is_array(json_decode($field['value'], true)) && json_last_error() == JSON_ERROR_NONE)) {
                            $arrValues = array($field['value']);
                            $field['value'] = Json::encode($arrValues);
                        }
                    }

                    //save field info
                    $profile[$group_id]['fields'][] = $field;

                    //save field ID
                    $fields_ids[] = $field_id;

                    //check if the first/last name exists (allow adding a new client)
                    if (in_array($field['company_field_id'], $arrFieldNames)) {
                        ++$isAllowAddClient;
                    }

                    //check or group has at least one editable fields
                    $is_editable_fields = ($is_editable_fields ? true : ($field['status'] == 'F'));

                    //check or all groups are read only
                    if ($is_editable_fields && $isAllGroupsReadOnly) {
                        $isAllGroupsReadOnly = false;
                    }
                }
            }

            //save group info
            if (isset($profile[$group_id])) {
                $profile[$group_id] = array_merge($group, array('is_editable_fields' => $is_editable_fields), $profile[$group_id]);
            }
        }

        //kill keys
        $profileArr = array();
        foreach ($profile as $p) {
            $profileArr[] = $p;
        }

        // Get default value for fields
        $defaultValues = array();
        if (is_array($fields_ids) && count($fields_ids)) {
            $select = (new Select())
                ->from('client_form_default')
                ->columns(array('field_id', 'value'))
                ->where(['field_id' => $fields_ids])
                ->order('order');

            $defaultValues = $this->_db2->fetchAll($select);
        }

        foreach ($profileArr as $group_key => $group) {
            foreach ($group['fields'] as $field_key => $field) {
                foreach ($defaultValues as $default) {
                    if ($default['field_id'] == $field['field_id']) {
                        $profileArr[$group_key]['fields'][$field_key]['default'][] = $default['value'];
                    }
                }
            }
        }

        // check if client can log in
        $canClientLogin = $this->_company->getPackages()->canCompanyClientLogin($companyId);

        // Get footer
        $footer = '';
        if ($mode == 'edit') {
            $footer = $this->generateClientFooter($memberId, $clientInfo);
        }

        return array(
            'groups' => $profileArr,
            'can_client_login' => $canClientLogin,
            'allow_add_client' => (!empty($profileArr) && !empty($fields_ids) && $isAllowAddClient >= 1 && !$isAllGroupsReadOnly),
            'footer' => $footer,
            'last_update_time' => array_key_exists('modified_on', $clientInfo) ? $clientInfo['modified_on'] : '',
            'tab_name' => $mode == 'edit' ? $clientInfo['full_name_with_file_num'] : 'Add new Case',
            'client_type_id' => $mode == 'edit' ? $clientInfo['client_type_id'] : $this->_parent->getCaseTemplates()->getDefaultCompanyCaseTemplate($companyId),
        );
    }

    public function generateClientFooter($memberId, $clientInfo = null)
    {
        $clientInfo   = empty($clientInfo) ? $this->_parent->getClientInfo($memberId) : $clientInfo;
        $strCreatedOn = $this->_settings->formatDate($clientInfo['regTime'] ?? '');

        if (!empty($clientInfo['modified_by'])) {
            $arrModifiedByInfo = $this->_parent->getMemberInfo($clientInfo['modified_by']);
        }

        $strModifiedBy = $strModifiedOn = '';
        if (!empty($arrModifiedByInfo['full_name'])) {
            $strModifiedBy = $arrModifiedByInfo['full_name'];
            $strModifiedOn = $this->_settings->formatDate($clientInfo['modified_on']);
        }

        //generate footer
        $strCreatedOn = htmlspecialchars('Created on: ' . $strCreatedOn);

        $strCreatedBy = '';
        if (!empty($clientInfo['added_by_member_id'])) {
            $arrCreatedBy = $this->_parent->getMemberInfo($clientInfo['added_by_member_id']);
            if (isset($arrCreatedBy['full_name']) && !empty($arrCreatedBy['full_name'])) {
                $strCreatedBy .= ' by ' . $arrCreatedBy['full_name'];
            }

            $oCompanyDivisions = $this->_company->getCompanyDivisions();
            if ($oCompanyDivisions->isAuthorizedAgentsManagementEnabled() && !empty($clientInfo['division_group_id'])) {
                $arrDivisionGroupInfo = $oCompanyDivisions->getDivisionsGroupInfo($clientInfo['division_group_id']);
                if (isset($arrDivisionGroupInfo['division_group_company']) && !empty($arrDivisionGroupInfo['division_group_company'])) {
                    $strCreatedBy = empty($strCreatedBy) ? $strCreatedBy : $strCreatedBy . '&nbsp;';
                    $strCreatedBy .= '(' . $arrDivisionGroupInfo['division_group_company'] . ')';
                }
            }
        }

        $strCreatedBy .= empty($strModifiedBy) ? '' : '<span style="padding: 0 15px">|</span>' . htmlspecialchars('Last modified by: ' . $strModifiedBy . ' on ' . $strModifiedOn);

        return '<div align="center" class="profile-footer">' . $strCreatedOn . $strCreatedBy . '</div>';
    }

    /**
     * Group fields by access status
     *
     * @param $fields
     * @return array
     */
    public function groupFieldsByStatus($fields)
    {
        $arrFields = array();
        foreach ($fields as $field) {
            $fid = $field['field_id'];
            // Get already saved value
            $value = isset($arrFields[$fid]) && isset($arrFields[$fid]['value']) ? $arrFields[$fid]['value'] : null;
            $arrFields[$fid] = isset($arrFields[$fid]) ? ($arrFields[$fid]['status'] == 'R' ? $field : $arrFields[$fid]) : $field;

            // Group values (can be several values for same field)
            if (!is_null($value) && $value != $field['value']) {
                if (is_array($arrFields[$fid]['value'])) {
                    $arrFields[$fid]['value'][] = $field['value'];
                } else {
                    $arrFields[$fid]['value'] = array($value, $field['value']);
                }
            }
        }

        // Reset keys
        return array_values($arrFields);
    }

    public function getDependents($arrMemberIds, $booGroup = true, $booOrder = true)
    {
        $select = (new Select())
            ->from(array('d' => 'client_form_dependents'))
            ->where(['d.member_id' => $arrMemberIds]);

        if ($booOrder) {
            $select->order('d.line ASC');
        }

        $arrDependents = $this->_db2->fetchAll($select);

        $arrResult = array();
        if ($booGroup) {
            foreach ($arrDependents as $arrDependentInfo) {
                $arrResult[$arrDependentInfo['relationship']][$arrDependentInfo['line']] = $arrDependentInfo;
            }
        } else {
            $arrResult = $arrDependents;
        }

        return $arrResult;
    }

    public function updateDependentInfo($caseId, $arrNewDependentData, $arrReceivedFiles, $arrDependentsIdsFromGui)
    {
        $arrCreatedFilesDependentIds = array();

        try {
            $booLocal           = $this->_auth->isCurrentUserCompanyStorageLocal();
            $booInsertDependent = false;
            $dependantsPath     = $this->_files->getCompanyDependantsPath($this->_auth->getCurrentUserCompanyId(), $booLocal);

            $arrSavedDependentIds = $this->_parent->getDependentIdsByMemberId($caseId);
            $arrNewIds = array();
            $result = false;
            foreach ($arrNewDependentData as $relationship => $arrDependantInfo) {
                foreach ($arrDependantInfo as $line => $arrData) {
                    if (isset($arrData['dependent_id']) && in_array($arrData['dependent_id'], $arrSavedDependentIds)) {
                        unset($arrData['changed']);
                        $this->_parent->updateDependents($caseId, $arrData['dependent_id'], $arrData);
                    } else {
                        $booInsertDependent = true;
                        unset($arrData['changed']);
                        $arrData['member_id']    = $caseId;
                        $arrData['relationship'] = $relationship;

                        $lastInsertedId = $this->_parent->updateDependents($caseId, $arrData['dependent_id'], $arrData);

                        $arrNewDependentData[$relationship][$line]['dependent_id'] = $lastInsertedId;
                        if (!key_exists($lastInsertedId, $arrDependentsIdsFromGui)) {
                            $arrDependentsIdsFromGui[] = $lastInsertedId;
                        }
                    }

                    $arrNewIds[] = $arrData['dependent_id'];
                }
            }

            $idsToDelete = array_diff($arrSavedDependentIds, $arrNewIds);
            if (!empty($idsToDelete)) {
                $this->_parent->deleteDependents($caseId, $idsToDelete);
            }

            if (!empty($this->_config['site_version']['dependants']['fields']['photo']['show'])) {
                $arrNames = $_FILES['field_file_case_dependants_photo']['name'] ?? [];
                foreach ($arrReceivedFiles as $arrFiles) {
                    $countFiles = count($arrFiles['name']);
                    for ($i = 0; $i < $countFiles; $i++) {
                        $fileName = $arrNames[$i];
                        if (empty($fileName)) {
                            continue;
                        }

                        if (isset($arrDependentsIdsFromGui[$i]) && !empty($arrDependentsIdsFromGui[$i])) {
                            $result = $this->_files->saveImage($dependantsPath . '/' . $caseId . '/' . $arrDependentsIdsFromGui[$i], 'field_file_case_dependants_photo', 'original', null, $booLocal, 0, true);

                            if (empty($result['error'])) {
                                $this->_parent->updateDependents($caseId, $arrDependentsIdsFromGui[$i], array('photo' => $fileName));
                                $arrCreatedFilesDependentIds[] = $arrDependentsIdsFromGui[$i];
                            }
                            $result = $result['error'];
                        }
                    }
                }

                $booSuccess = empty($result);
            } else {
                $booSuccess = true;
            }
        } catch (Exception $e) {
            $booSuccess = false;
            $booInsertDependent = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'success' => $booSuccess,
            'booInsertDependent' => $booInsertDependent,
            'arrCreatedFilesDependentIds' => $arrCreatedFilesDependentIds
        );
    }

    public function getProfileSyncFields($applicant = 'main_applicant')
    {
        $arrProfileSyncFields = array();

        switch ($applicant) {
            case 'spouse':
            case 'child1':
            case 'child2':
            case 'child3':
            case 'child4':
            case 'child5':
            case 'child6':
            case 'parent1':
            case 'parent2':
            case 'parent3':
            case 'parent4':
            case 'sibling1':
            case 'sibling2':
            case 'sibling3':
            case 'sibling4':
            case 'sibling5':
            case 'other': // From old CA
            case 'other1':
            case 'other2':
                $arrDependantFields = $this->getDependantFields();
                foreach ($arrDependantFields as $arrDependantFieldInfo) {
                    if ($arrDependantFieldInfo['field_id'] == 'relationship') {
                        continue;
                    }

                    $arrProfileSyncFields[] = array(
                        'id'            => $arrDependantFieldInfo['field_id'],
                        'value'         => $arrDependantFieldInfo['field_name'],
                        'type'          => $arrDependantFieldInfo['field_type'],
                        'field_options' => $arrDependantFieldInfo['field_options'] ?? array(),
                    );
                }
                break;

            default:
                $arrComboFields  = array('categories', 'office', 'office_multi', 'combo', 'combobox');
                $companyId       = $this->_auth->getCurrentUserCompanyId();
                $divisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();

                // IA/Employer fields
                $arrApplicantFields = $this->_parent->getApplicantFields()->getCompanyAllFields($companyId);
                foreach ($arrApplicantFields as $arrApplicantFieldInfo) {
                    $booDynamic = false;
                    $fieldId    = $this->getStaticColumnName($arrApplicantFieldInfo['applicant_field_unique_id']);
                    if ($fieldId === false) {
                        $booDynamic = true;
                        $fieldId    = $arrApplicantFieldInfo['applicant_field_unique_id'];
                    }

                    $arrOptions = null;
                    $fieldType = $arrApplicantFieldInfo['type'];
                    if (in_array($fieldType, $arrComboFields)) {
                        if (in_array($fieldType, ['combo', 'combobox'])) {
                            $arrApplicantOptions = $this->_parent->getApplicantFields()->getFieldsOptions(array($arrApplicantFieldInfo['applicant_field_id']));
                            foreach ($arrApplicantOptions as $arrOption) {
                                $arrOptions[] = [
                                    'option_id'      => $arrOption['applicant_form_default_id'],
                                    'option_name'    => $arrOption['value'],
                                    'option_order'   => $arrOption['order'],
                                    'option_deleted' => 'N'
                                ];
                            }
                        } else {
                            $arrOptions = $this->getCompanyFieldOptions($companyId, $divisionGroupId, $fieldType);
                        }

                        $fieldType = 'combo';
                    }

                    $arrThisFieldInfo = array(
                        'id' => $fieldId,
                        'value' => $arrApplicantFieldInfo['label'],
                        'dynamic' => $booDynamic ? 1 : 0,
                        'type' => $fieldType,
                        'field_options' => $arrOptions,
                        'group' => 'Client Fields'
                    );

                    if (is_null($arrThisFieldInfo['field_options'])) {
                        unset($arrThisFieldInfo['field_options']);
                    }

                    $arrProfileSyncFields[] = $arrThisFieldInfo;
                }

                // Case fields
                $arrCaseFields = $this->getCompanyFields($companyId);
                foreach ($arrCaseFields as $arrCaseFieldInfo) {
                    $booDynamic = false;
                    $fieldId = $this->getStaticColumnName($arrCaseFieldInfo['company_field_id']);
                    if ($fieldId === false) {
                        $booDynamic = true;
                        $fieldId = $arrCaseFieldInfo['company_field_id'];
                    }

                    $arrOptions = null;
                    $fieldType  = $this->_parent->getFieldTypes()->getStringFieldTypeById($arrCaseFieldInfo['type']);
                    if (in_array($fieldType, $arrComboFields)) {
                        $arrOptions = $this->getCompanyFieldOptions($companyId, $divisionGroupId, $fieldType, $arrCaseFieldInfo['company_field_id']);
                        $fieldType  = 'combo';
                    }

                    $arrThisFieldInfo = array(
                        'id'            => $fieldId,
                        'value'         => $arrCaseFieldInfo['label'],
                        'dynamic'       => $booDynamic ? 1 : 0,
                        'type'          => $fieldType,
                        'field_options' => $arrOptions,
                        'group'         => 'Case Fields'
                    );

                    if (is_null($arrThisFieldInfo['field_options'])) {
                        unset($arrThisFieldInfo['field_options']);
                    }

                    $arrProfileSyncFields[] = $arrThisFieldInfo;
                }

                // Extra/specific fields
                $RMA       = $this->_company->getCurrentCompanyDefaultLabel('rma');
                $RMANumber = $this->_company->getCurrentCompanyDefaultLabel('rma_number');
                $firstName = $this->_company->getCurrentCompanyDefaultLabel('first_name');
                $lastName  = $this->_company->getCurrentCompanyDefaultLabel('last_name');

                $arrProfileSyncFields[] = array('id' => 'registered_migrant_agent_family_name', 'value' => $RMA . ' - ' . $lastName, 'dynamic' => true, 'type' => 'text', 'group' => 'Extra Fields');
                $arrProfileSyncFields[] = array('id' => 'registered_migrant_agent_given_name', 'value' => $RMA . ' - ' . $firstName, 'dynamic' => true, 'type' => 'text', 'group' => 'Extra Fields');
                $arrProfileSyncFields[] = array('id' => 'registered_migrant_agent_marn', 'value' => $RMA . ' - ' . $RMANumber, 'dynamic' => true, 'type' => 'text', 'group' => 'Extra Fields');
                $arrProfileSyncFields[] = array('id' => 'dependant1_family_name', 'value' => 'Dependent 1 - ' . $lastName, 'dynamic' => true, 'type' => 'text', 'group' => 'Extra Fields');
                $arrProfileSyncFields[] = array('id' => 'dependant2_family_name', 'value' => 'Dependent 2 - ' . $lastName, 'dynamic' => true, 'type' => 'text', 'group' => 'Extra Fields');
                $arrProfileSyncFields[] = array('id' => 'dependant3_family_name', 'value' => 'Dependent 3 - ' . $lastName, 'dynamic' => true, 'type' => 'text', 'group' => 'Extra Fields');
                $arrProfileSyncFields[] = array('id' => 'dependant4_family_name', 'value' => 'Dependent 4 - ' . $lastName, 'dynamic' => true, 'type' => 'text', 'group' => 'Extra Fields');
                $arrProfileSyncFields[] = array('id' => 'dependant5_family_name', 'value' => 'Dependent 5 - ' . $lastName, 'dynamic' => true, 'type' => 'text', 'group' => 'Extra Fields');
                $arrProfileSyncFields[] = array('id' => 'dependant1_given_name', 'value' => 'Dependent 1 - ' . $firstName, 'dynamic' => true, 'type' => 'text', 'group' => 'Extra Fields');
                $arrProfileSyncFields[] = array('id' => 'dependant2_given_name', 'value' => 'Dependent 2 - ' . $firstName, 'dynamic' => true, 'type' => 'text', 'group' => 'Extra Fields');
                $arrProfileSyncFields[] = array('id' => 'dependant3_given_name', 'value' => 'Dependent 3 - ' . $firstName, 'dynamic' => true, 'type' => 'text', 'group' => 'Extra Fields');
                $arrProfileSyncFields[] = array('id' => 'dependant4_given_name', 'value' => 'Dependent 4 - ' . $firstName, 'dynamic' => true, 'type' => 'text', 'group' => 'Extra Fields');
                $arrProfileSyncFields[] = array('id' => 'dependant5_given_name', 'value' => 'Dependent 5 - ' . $firstName, 'dynamic' => true, 'type' => 'text', 'group' => 'Extra Fields');
                break;
        }

        return $arrProfileSyncFields;
    }

    /**
     * These mapping types can be used to correctly process specific values
     * e.g. from several date field's parts - can be collected one date field
     *
     * @return array
     */
    public function getProfileSyncMappingType()
    {
        return array(
            array('id' => 'country', 'value' => 'Country'),
            array('id' => 'date_day', 'value' => 'Date: day'),
            array('id' => 'date_month', 'value' => 'Date: month'),
            array('id' => 'date_year', 'value' => 'Date: year'),
        );
    }

    public function getEditableFields($companyId, $booKeysOnly = true)
    {
        $arrFields = array();

        if ($this->_config['site_version']['version'] == 'australia') {
            $arrFields['immigration_office'] = 'Office';
        } else {
            $arrFields['visa_office'] = 'Office';
        }

        return $booKeysOnly ? array_keys($arrFields) : $arrFields;
    }

    /**
     * Get company field options by text field id
     *
     * @param int $companyId
     * @param int $divisionGroupId
     * @param string $fieldType
     * @param string $fieldId
     * @param int $caseTemplateId
     * @return array
     */
    public function getCompanyFieldOptions($companyId, $divisionGroupId, $fieldType, $fieldId = '', $caseTemplateId = null)
    {
        $arrResult = array();

        switch ($fieldType) {
            case 'office':
            case 'office_multi':
            case 'divisions':
                $arrDivisions = $this->_company->getDivisions($companyId, $divisionGroupId);
                if (is_array($arrDivisions) && !empty($arrDivisions)) {
                    foreach ($arrDivisions as $arrDivisionInfo) {
                        $arrResult[] = array(
                            'option_id'      => $arrDivisionInfo['division_id'],
                            'option_name'    => $arrDivisionInfo['name'],
                            'option_order'   => $arrDivisionInfo['order'],
                            'option_deleted' => 'N'
                        );
                    }
                }
                break;

            case 'categories':
                if (empty($caseTemplateId)) {
                    $arrCategories = $this->_parent->getCaseCategories()->getCompanyCaseCategories($companyId);
                    if (is_array($arrCategories) && !empty($arrCategories)) {
                        $order = 0;
                        foreach ($arrCategories as $arrCategoryInfo) {
                            $arrResult[] = array(
                                'option_id'      => $arrCategoryInfo['client_category_id'],
                                'option_name'    => $arrCategoryInfo['client_category_name'],
                                'option_order'   => $order++,
                                'option_deleted' => 'N'
                            );
                        }
                    }
                } else {
                    $arrCompanyCategoriesGrouped = $this->_parent->getCaseCategories()->getCategoriesGroupedByCaseTypes($companyId);
                    foreach ($arrCompanyCategoriesGrouped as $caseTypeId => $arrCategories) {
                        if ($caseTypeId == $caseTemplateId) {
                            $order = 0;
                            foreach ($arrCategories as $arrCategoryInfo) {
                                $arrResult[] = array(
                                    'option_id'      => $arrCategoryInfo['option_id'],
                                    'option_name'    => $arrCategoryInfo['option_name'],
                                    'option_order'   => $order++,
                                    'option_deleted' => 'N'
                                );
                            }
                            break;
                        }
                    }
                }
                break;

            case 'case_status':
                if (empty($caseTemplateId)) {
                    $arrCompanyStatuses = $this->_parent->getCaseStatuses()->getCompanyCaseStatuses($companyId);
                    if (is_array($arrCompanyStatuses) && !empty($arrCompanyStatuses)) {
                        $order = 0;
                        foreach ($arrCompanyStatuses as $arrCompanyStatusInfo) {
                            $arrResult[] = array(
                                'option_id'      => $arrCompanyStatusInfo['client_status_id'],
                                'option_name'    => $arrCompanyStatusInfo['client_status_name'],
                                'option_order'   => $order++,
                                'option_deleted' => 'N'
                            );
                        }
                    }
                } else {
                    $arrCaseTemplates       = $this->_parent->getCaseTemplates()->getTemplates($companyId);
                    $arrCompanyCaseStatuses = $this->_parent->getCaseStatuses()->getCompanyCaseStatusesGrouped($companyId, $arrCaseTemplates);

                    if (isset($arrCompanyCaseStatuses['case_types'][$caseTemplateId])) {
                        $order = 0;
                        foreach ($arrCompanyCaseStatuses['case_types'][$caseTemplateId] as $arrCompanyStatusInfo) {
                            $arrResult[] = array(
                                'option_id'      => $arrCompanyStatusInfo['option_id'],
                                'option_name'    => $arrCompanyStatusInfo['option_name'],
                                'option_order'   => $order++,
                                'option_deleted' => 'N'
                            );
                        }
                    }
                }
                break;

            case 'immigration_office' :
            case 'visa_office' :
                $arrVacs = $this->_parent->getCaseVACs()->getList($companyId);

                $order = 0;
                foreach ($arrVacs as $arrVacInfo) {
                    $label = $arrVacInfo['client_vac_city'];
                    if (!empty($arrVacInfo['client_vac_country'])) {
                        $label .= ', ' . $arrVacInfo['client_vac_country'];
                    }

                    $arrResult[] = array(
                        'option_id'      => $arrVacInfo['client_vac_id'],
                        'option_name'    => $label,
                        'option_link'    => $arrVacInfo['client_vac_link'],
                        'option_order'   => $order++,
                        'option_deleted' => $arrVacInfo['client_vac_deleted'] == 'Y'
                    );
                }
                break;

            // combo or radio
            default:
                $thisFieldId = $this->getCompanyFieldId($companyId, $fieldId, false);
                $arrResult   = $this->getFieldOptions($thisFieldId);
                break;
        }

        return $arrResult;
    }

    public function getFieldOptions($fieldId)
    {
        $arrResult = array();
        if (!empty($fieldId)) {
            $select = (new Select())
                ->from('client_form_default')
                ->columns(array('option_id' => 'form_default_id', 'option_name' => 'value', 'option_order' => 'order', 'option_deleted' => 'deleted'))
                ->where(['field_id' => (int)$fieldId])
                ->order('order');

            $arrResult = $this->_db2->fetchAll($select);
        }

        return $arrResult;
    }

    public function getFieldsOptions($arrFieldsIds)
    {
        if (!is_array($arrFieldsIds)) {
            $arrFieldsIds = array($arrFieldsIds);
        }

        $arrResult = array();
        if (count($arrFieldsIds)) {
            $select = (new Select())
                ->from('client_form_default')
                ->where(['field_id' => $arrFieldsIds])
                ->order('order');

            $arrResult = $this->_db2->fetchAll($select);
        }

        return $arrResult;
    }


    public function getCompanyFieldId($companyId, $fieldId, $checkIsEditable = true)
    {
        if ($checkIsEditable && !in_array($fieldId, $this->getEditableFields($companyId))) {
            return 0;
        } else {
            return $this->getCompanyFieldIdByUniqueFieldId($fieldId, $companyId);
        }
    }

    /**
     * Get Field id(s) by a string type
     *
     * @param int $companyId
     * @param string $strFieldType
     * @return array
     */
    public function getFieldIdByType($companyId, $strFieldType)
    {
        $select = (new Select())
            ->from('client_form_fields')
            ->columns(['field_id'])
            ->where(
                [
                    (new Where())
                        ->in('type', [$this->_parent->getFieldTypes()->getFieldTypeId($strFieldType)])
                        ->equalTo('company_id', (int)$companyId)
                ]
            );

        return $this->_db2->fetchCol($select);
    }

    /**
     * Check if first/last name is valid
     * Note: please make sure that filterName will be updated if this regexp will be changed
     *
     * @param string $name
     * @return bool true if valid, otherwise false
     */
    public static function validName($name)
    {
        return preg_match('/\A[.\(\)\'\"0-9a-zA-Z-\x{00C0}\x{00C1}\x{00C2}\x{00C3}\x{00C4}\x{00C5}\x{00C6}\x{00C7}\x{00C8}\x{00C9}\x{00CA}\x{00CB}\x{00CC}\x{00CD}\x{00CE}\x{00CF}\x{00D5}\x{00D6}\x{1EA5} ]*\Z/iu', $name);
    }

    /**
     * Try to remove invalid chars from the first/last name
     * Note: please make sure that validName will be updated if this regexp will be changed
     *
     * @param string $name
     * @return string
     */
    public static function filterName($name)
    {
        return preg_replace('/[^.\(\)\'\"0-9a-zA-Z-\x{00C0}\x{00C1}\x{00C2}\x{00C3}\x{00C4}\x{00C5}\x{00C6}\x{00C7}\x{00C8}\x{00C9}\x{00CA}\x{00CB}\x{00CC}\x{00CD}\x{00CE}\x{00CF}\x{00D5}\x{00D6}\x{1EA5} ]/iu', '', $name);
    }

    public function validNumber($strNumber)
    {
        return preg_match("/^[0-9]+$/", $strNumber);
    }

    public function validPhone($strPhoneNumber)
    {
        return preg_match("/^[0-9a-zA-Z\-+().,: ]+$/", $strPhoneNumber);
    }


    /**
     * Check if username is correct
     * Rules: length must be from 3 to 64 characters.
     * You may use letters, numbers, underscores, @ sign and dot (.).
     *
     * @param string $username
     * @return int
     */
    public static function validUserName($username)
    {
        // Important: this file is in ANSI as UTF8 encoding,
        // in Zend Studio some chars are not correctly showed below (in this regex)
        return preg_match("/^[a-zA-Z0-9_.@\-àÀâÂäÄáÁéÉèÈêÊëËìÌîÎïÏòÒôÔöÖùÙûÛüÜçÇ’ñ]{3,64}$/u", $username);
    }

    /**
     * Load a list of groups for a specific company / Immigration Program
     *
     * @param bool $booOnlyAssigned true if only assigned groups should be loaded
     * @param int $caseTemplateId
     * @param int $companyId
     * @param bool $booCheckRoleAccessRights
     * @param array $arrRoleIds
     * @return array
     */
    public function getGroups($booOnlyAssigned = true, $caseTemplateId = 0, $companyId = null, $booCheckRoleAccessRights = true, $arrRoleIds = [])
    {
        $companyId = is_null($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId;

        $select = (new Select())
            ->from(array('g' => 'client_form_groups'))
            ->join(array('ga' => 'client_form_group_access'), 'ga.group_id = g.group_id', 'status', Select::JOIN_LEFT)
            ->where(['g.company_id' => (int)$companyId])
            ->order('g.order ASC');

        // Don't check for access rights if this is not needed
        if ($booCheckRoleAccessRights) {
            $arrRoleIds = empty($arrRoleIds) ? $this->_parent->getMemberRoles() : $arrRoleIds;
            if (count($arrRoleIds)) {
                $select->where(['ga.role_id' => $arrRoleIds]);
            }
        }

        if ($booOnlyAssigned) {
            $select->where->equalTo('g.assigned', 'A');
        }

        if (!empty($caseTemplateId)) {
            $select->where->equalTo('g.client_type_id', (int)$caseTemplateId);
        }

        $groupsArr = $this->_db2->fetchAll($select);

        //group by access
        $groups = array();
        foreach ($groupsArr as $group) {
            $group_id = $group['group_id'];
            $groups[$group_id] = isset($groups[$group_id]) ? ($groups[$group_id]['status'] == 'R' ? $group : $groups[$group_id]) : $group;
        }

        //kill indexes
        $result = array();
        foreach ($groups as $group) {
            $result[] = $group;
        }

        return $result;
    }

    /**
     * Get access to the Dependents group (for a specific case type, company and/or role)
     *
     * @param int $caseTemplateId
     * @param int $companyId
     * @param int $roleId
     * @return string empty - no access, R - read only, F - full access
     */
    public function getAccessToDependants($caseTemplateId = 0, $companyId = null, $roleId = null)
    {
        $access = '';

        // Load all groups for the current company, take the max
        $arrRoleIds = empty($roleId) ? [] : [$roleId];
        $arrGroups  = $this->getGroups(true, $caseTemplateId, $companyId, true, $arrRoleIds);
        foreach ($arrGroups as $arrGroupInfo) {
            if ($arrGroupInfo['title'] == 'Dependants') {
                if (empty($access) || $arrGroupInfo['status'] == 'F') {
                    $access = $arrGroupInfo['status'];
                }

                if ($access == 'F') {
                    break;
                }
            }
        }

        return $access;
    }

    //user:all --> All Staff, etc...
    public function getAssignLabelByValue($companyId, $divisionGroupId, $value, $arrMembers = array())
    {
        $data = explode(':', $value ?? '');
        if (!empty($data[0]) && !empty($data[1])) {
            if ($data[0] == 'user') {
                if ($data[1] == 'all') {
                    return 'All staff';
                } else {
                    if (array_key_exists($data[1], $arrMembers)) {
                        $name = $arrMembers[$data[1]];
                    } else {
                        $userInfo = $this->_parent->getMemberInfo($data[1]);
                        $name = $userInfo['full_name'];
                    }

                    return $name;
                }
            } elseif ($data[0] == 'role') {
                return 'All ' . $this->_roles->getRoleName($data[1], $companyId, $divisionGroupId) . ' staff';
            } elseif ($data[0] == 'assigned') {
                if ($data[1] == 4) {
                    return 'The staff responsible for Sales/Marketing';
                } elseif ($data[1] == 5) {
                    return 'The staff responsible for Processing';
                } elseif ($data[1] == 6) {
                    return 'The staff responsible for Accounting';
                }
            }
        }

        return '';
    }

    /**
     * Load int field id for a specific company by provided string unique field id
     *
     * @param string $uniqueFieldId
     * @param int $companyId
     * @return int field id, empty if not found
     */
    public function getCompanyFieldIdByUniqueFieldId($uniqueFieldId, $companyId = 0)
    {
        $arrFieldInfo = $this->getCompanyFieldInfoByUniqueFieldId($uniqueFieldId, $companyId, false);

        return isset($arrFieldInfo['field_id']) ? (int)$arrFieldInfo['field_id'] : 0;
    }

    /**
     * Load field info by specific field id
     *
     * @param string $uniqueFieldId
     * @param int $companyId
     * @param bool $booLoadTestTypeId
     * @return array
     */
    public function getCompanyFieldInfoByUniqueFieldId($uniqueFieldId, $companyId = 0, $booLoadTestTypeId = true)
    {
        $companyId = empty($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId;

        $select = (new Select())
            ->from(array('f' => 'client_form_fields'))
            ->where([
                'company_id'       => (int)$companyId,
                'company_field_id' => $uniqueFieldId
            ]);

        if ($booLoadTestTypeId) {
            $select->join(array('ft' => 'field_types'), 'ft.field_type_id = f.type', array('field_type_text_id'), Select::JOIN_LEFT);
        }

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load all fields info with specific field id
     *
     * @param string $uniqueFieldId
     * @return array
     */
    public function getAllFieldsInfoByUniqueFieldId($uniqueFieldId)
    {
        $select = (new Select())
            ->from(array('f' => 'client_form_fields'))
            ->join(array('c' => 'company'), 'f.company_id = c.company_id', 'companyName')
            ->where(['f.company_field_id' => $uniqueFieldId]);

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load client status field id for a specific company
     *
     * @param bool|int $companyId if false or empty - load for the current user's company
     * @return int field id, empty if not found
     */
    public function getClientStatusFieldId($companyId = 0)
    {
        $companyId = empty($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId;

        return $this->getCompanyFieldIdByUniqueFieldId('Client_file_status', $companyId);
    }

    /**
     * Load list of "case status" field ids for all companies
     *
     * @return array
     */
    public function getAllCompaniesClientStatusFieldsIds()
    {
        $select = (new Select())
            ->from('client_form_fields')
            ->columns(['field_id'])
            ->where(['company_field_id' => 'Client_file_status']);

        return $this->_db2->fetchCol($select);
    }

    /**
     * Load label of the file status case's field
     *
     * @param int $companyId
     * @return string
     */
    public function getCaseStatusFieldLabel($companyId = null)
    {
        $companyId         = is_null($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId;
        $arrFileStatusInfo = $this->getCompanyFieldInfoByUniqueFieldId('file_status', $companyId);

        return $arrFileStatusInfo['label'] ?? 'Case Status';
    }


    /**
     * Load option value for specific option by its id
     * @param $intOptionId
     * @return string
     */
    public function getDefaultFieldOptionValue($intOptionId)
    {
        $optionValue = '';
        if (!empty($intOptionId)) {
            $select = (new Select())
                ->from('client_form_default')
                ->columns(['value'])
                ->where(['form_default_id' => (int)$intOptionId]);

            $optionValue = $this->_db2->fetchOne($select);
        }

        return $optionValue;
    }

    public function getDefaultFieldOptionDetails($intOptionId)
    {
        $arrDetails = array();
        if (!empty($intOptionId)) {
            $select = (new Select())
                ->from('client_form_default')
                ->where(['form_default_id' => (int)$intOptionId]);

            $arrDetails = $this->_db2->fetchRow($select);
        }

        return $arrDetails;
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
            ->from(array('d' => 'client_form_default'))
            ->join(array('f' => 'client_form_fields'), 'd.field_id = f.field_id', [], Select::JOIN_LEFT)
            ->where(['f.company_id' => (int)$companyId]);

        return $this->_db2->fetchAll($select);
    }

    /**
     * Create default fields and groups for new company
     *
     * @param int $fromCompanyId
     * @param int $toCompanyId
     * @param array $arrMappingRoles - mapping between old and new roles
     * @param array $arrMappingCaseTemplates - mapping between old and new case templates
     * @return array
     */
    public function createDefaultGroupsAndFields($fromCompanyId, $toCompanyId, $arrMappingRoles, $arrMappingCaseTemplates)
    {
        $arrMappingDefaults = $arrMappingFields = $arrMappingGroups = array();

        try {
            $select = (new Select())
                ->from('client_form_groups')
                ->where(['company_id' => (int)$fromCompanyId]);

            $arrDefaultGroups = $this->_db2->fetchAll($select);

            if (!empty($arrDefaultGroups)) {
                foreach ($arrDefaultGroups as $defaultGroupInfo) {
                    // Create the same group
                    $caseTemplateId = is_array($arrMappingCaseTemplates) && array_key_exists($defaultGroupInfo['client_type_id'], $arrMappingCaseTemplates) ? $arrMappingCaseTemplates[$defaultGroupInfo['client_type_id']] : new Expression('NULL');

                    $arrInsertGroups = array(
                        'parent_group_id' => $defaultGroupInfo['group_id'],
                        'company_id'      => $toCompanyId,
                        'client_type_id'  => $caseTemplateId,
                        'title'           => $defaultGroupInfo['title'],
                        'order'           => $defaultGroupInfo['order'],
                        'cols_count'      => $defaultGroupInfo['cols_count'],
                        'collapsed'       => $defaultGroupInfo['collapsed'],
                        'show_title'      => $defaultGroupInfo['show_title'],
                        'regTime'         => time(),
                        'assigned'        => $defaultGroupInfo['assigned']
                    );

                    $newGroupId = $this->_db2->insert('client_form_groups', $arrInsertGroups);

                    $arrMappingGroups[$defaultGroupInfo['group_id']] = $newGroupId;

                    // Get all fields and other related info for this group
                    $select = (new Select())
                        ->from(array('o' => 'client_form_order'))
                        ->columns(array('group_id', 'field_id', 'use_full_row', 'field_order'))
                        ->join(
                            array('f' => 'client_form_fields'),
                            'f.field_id = o.field_id',
                            array('company_field_id', 'type', 'label', 'maxlength', 'custom_height', 'required', 'disabled', 'encrypted', 'blocked', 'min_value', 'max_value'),
                            Select::JOIN_LEFT
                        )
                        ->where(['o.group_id' => $defaultGroupInfo['group_id']]);

                    $arrDefaultGroupFields = $this->_db2->fetchAll($select);
                    if (is_array($arrDefaultGroupFields)) {
                        foreach ($arrDefaultGroupFields as $arrDefaultFieldInfo) {
                            // Create field
                            if (array_key_exists($arrDefaultFieldInfo['field_id'], $arrMappingFields)) {
                                $newFieldId = $arrMappingFields[$arrDefaultFieldInfo['field_id']];
                            } elseif (empty($arrDefaultFieldInfo['type'])) {
                                $newFieldId = $arrDefaultFieldInfo['field_id'];
                            } else {
                                $arrInsertFieldInfo = array(
                                    'company_id'       => $toCompanyId,
                                    'parent_field_id'  => $arrDefaultFieldInfo['field_id'],
                                    'company_field_id' => $arrDefaultFieldInfo['company_field_id'],
                                    'type'             => $arrDefaultFieldInfo['type'],
                                    'label'            => $arrDefaultFieldInfo['label'],
                                    'maxlength'        => $arrDefaultFieldInfo['maxlength'],
                                    'custom_height'    => $arrDefaultFieldInfo['custom_height'],
                                    'min_value'        => $arrDefaultFieldInfo['min_value'],
                                    'max_value'        => $arrDefaultFieldInfo['max_value'],
                                    'encrypted'        => $arrDefaultFieldInfo['encrypted'],
                                    'required'         => $arrDefaultFieldInfo['required'],
                                    'disabled'         => $arrDefaultFieldInfo['disabled'],
                                    'blocked'          => $arrDefaultFieldInfo['blocked'],
                                );

                                $newFieldId = $this->_db2->insert('client_form_fields', $arrInsertFieldInfo);
                            }

                            // Create field order
                            $this->placeFieldInGroup($newGroupId, $newFieldId, $arrDefaultFieldInfo['use_full_row'] == 'Y', $arrDefaultFieldInfo['field_order']);

                            if (!array_key_exists($arrDefaultFieldInfo['field_id'], $arrMappingFields)) {
                                // Create default values for this new field
                                $select = (new Select())
                                    ->from(array('d' => 'client_form_default'))
                                    ->where(['d.field_id' => $arrDefaultFieldInfo['field_id']]);

                                $arrDefaultFieldDefaultValues = $this->_db2->fetchAll($select);

                                foreach ($arrDefaultFieldDefaultValues as $arrDefaultFieldDefaultInfo) {
                                    $arrMappingDefaults[$arrDefaultFieldDefaultInfo['form_default_id']] = $this->createDefaultOption(
                                        $newFieldId,
                                        $arrDefaultFieldDefaultInfo['form_default_id'],
                                        $arrDefaultFieldDefaultInfo['value'],
                                        $arrDefaultFieldDefaultInfo['order']
                                    );
                                }

                                $arrMappingFields[$arrDefaultFieldInfo['field_id']] = $newFieldId;
                            }
                        }
                    }
                }

                //create group access
                if (is_array($arrMappingRoles) && !empty($arrMappingRoles) && is_array($arrMappingGroups) && !empty($arrMappingGroups)) {
                    foreach ($arrMappingRoles as $oldRoleId => $newRoleId) {
                        foreach ($arrMappingGroups as $oldGroupId => $newGroupId) {
                            $select = (new Select())
                                ->from('client_form_group_access')
                                ->where(
                                    [
                                        'role_id' => $oldRoleId,
                                        'group_id'=> $oldGroupId
                                    ]
                                );

                            $arrDefaultGroupAccess = $this->_db2->fetchAll($select);

                            if (is_array($arrDefaultGroupAccess) && !empty($arrDefaultGroupAccess)) {
                                foreach ($arrDefaultGroupAccess as $arrDefaultGroupAccessInfo) {
                                    $this->createGroupAccessRecord($newRoleId, $newGroupId, $arrDefaultGroupAccessInfo['status']);
                                }
                            }
                        }
                    }
                }

                // Now create a copy for fields access
                if (is_array($arrMappingRoles) && !empty($arrMappingRoles) &&
                    is_array($arrMappingFields) && !empty($arrMappingFields)
                ) {
                    $values = array();
                    foreach ($arrMappingRoles as $oldRoleId => $newRoleId) {
                        $select = (new Select())
                            ->from(array('a' => 'client_form_field_access'))
                            ->columns(array('old_field_id' => 'field_id', Select::SQL_STAR))
                            ->where(
                                [
                                    (new Where())
                                        ->in('a.field_id', array_keys($arrMappingFields))
                                        ->equalTo('a.role_id', $oldRoleId)
                                ]
                            );

                        $arrDefaultFieldAccessArray = $this->_db2->fetchAll($select);

                        if (is_array($arrDefaultFieldAccessArray) && count($arrDefaultFieldAccessArray)) {
                            foreach ($arrDefaultFieldAccessArray as $arrDefaultFieldAccess) {
                                $values[] = array(
                                    'role_id'  => $newRoleId,
                                    'field_id' => $arrMappingFields[$arrDefaultFieldAccess['old_field_id']],
                                    'status'   => $arrDefaultFieldAccess['status']
                                );
                            }
                        }
                    }

                    $this->createFieldAccessRights($values);
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return array(
            'mappingGroups' => $arrMappingGroups,
            'mappingFields' => $arrMappingFields,
            'mappingDefaults' => $arrMappingDefaults
        );
    }

    /**
     * Place a field in a specific group
     *
     * @param int $groupId
     * @param int $fieldId
     * @param bool $booUseFullRow
     * @param int|null $fieldOrder if null - automatically calculate the order
     */
    public function placeFieldInGroup($groupId, $fieldId, $booUseFullRow, $fieldOrder = null)
    {
        if (is_null($fieldOrder)) {
            $select = (new Select())
                ->from('client_form_order')
                ->columns(['field_order' => new Expression('IFNULL(MAX(field_order) + 1, 1)')])
                ->where(['group_id' => (int)$groupId]);

            $fieldOrder = $this->_db2->fetchOne($select);
        }

        $this->_db2->insert(
            'client_form_order',
            [
                'group_id'     => $groupId,
                'field_id'     => $fieldId,
                'use_full_row' => $booUseFullRow ? 'Y' : 'N',
                'field_order'  => $fieldOrder
            ]
        );
    }

    /**
     * Get the list of fields order for specific groups
     *
     * @param array $arrGroupIds
     * @return array
     */
    public function getFieldsOrderInGroups($arrGroupIds)
    {
        $arrSavedGroupedOrders = [];
        if (!empty($arrGroupIds)) {
            $select = (new Select())
                ->from('client_form_order')
                ->where(['group_id' => $arrGroupIds])
                ->order('field_order');

            $arrSavedOrders = $this->_db2->fetchAll($select);

            foreach ($arrSavedOrders as $arrSavedOrderInfo) {
                $arrSavedGroupedOrders[$arrSavedOrderInfo['group_id']][$arrSavedOrderInfo['field_id']] = array(
                    'field_use_full_row' => $arrSavedOrderInfo['use_full_row'] == 'Y',
                    'field_id'           => $arrSavedOrderInfo['field_id']
                );
            }
        }

        return $arrSavedGroupedOrders;
    }

    /**
     * Update group access from the placed fields in this group
     *
     * @param int $companyId - is used only to clear the cache
     * @param array $arrGroupIds
     * @return void
     */
    public function updateGroupAccessFromFields($companyId, $arrGroupIds)
    {
        if (!empty($arrGroupIds)) {
            // Set the access rights to the group in relation to the placed fields in this group
            $select = (new Select())
                ->from(array('fa' => 'client_form_field_access'))
                ->columns(['role_id', 'status'])
                ->join(array('fo' => 'client_form_order'), 'fo.field_id = fa.field_id', ['group_id'], Select::JOIN_LEFT)
                ->join(array('fg' => 'client_form_groups'), 'fg.group_id = fo.group_id', ['title'], Select::JOIN_LEFT)
                ->where(
                    [
                        'fg.group_id' => $arrGroupIds
                    ]
                );

            $arrGroupsAccess = $this->_db2->fetchAll($select);

            if (!empty($arrGroupsAccess)) {
                // Get the max access right for each role
                $arrGroupedAccessRights = [];
                foreach ($arrGroupsAccess as $arrAllAccessRights) {
                    if ($arrAllAccessRights['title'] != 'Dependants') {
                        $groupId = $arrAllAccessRights['group_id'];
                        $roleId  = $arrAllAccessRights['role_id'];
                        if (!isset($arrGroupedAccessRights[$roleId][$groupId]) || $arrAllAccessRights['status'] == 'F') {
                            $arrGroupedAccessRights[$roleId][$groupId] = $arrAllAccessRights['status'];
                        }
                    }
                }

                $this->saveGroupsAccessForRoles($arrGroupedAccessRights);

                $this->_roles->clearCompanyRolesCache($companyId);
            }
        }
    }

    /**
     * Create group access record
     *
     * @param int $roleId
     * @param int $groupId
     * @param string $status
     * @return int|null
     */
    public function createGroupAccessRecord($roleId, $groupId, $status)
    {
        return $this->_db2->insert(
            'client_form_group_access',
            [
                'role_id' => (int)$roleId,
                'group_id' => (int)$groupId,
                'status' => $status
            ]
        );
    }

    /**
     * Create field access rights records and update roles access in relation to the default settings
     *
     * @param array $arrAllRights
     */
    public function createFieldAccessRights($arrAllRights)
    {
        $arrGroupedRights = array();
        foreach ($arrAllRights as $arrRights) {
            $this->_db2->insert(
                'client_form_field_access',
                [
                    'role_id'  => (int)$arrRights['role_id'],
                    'field_id' => (int)$arrRights['field_id'],
                    'status'   => $arrRights['status']
                ]
            );

            $arrGroupedRights[$arrRights['role_id']][$arrRights['field_id']] = $arrRights['status'];
        }

        $this->updateDefaultAccessRightsForRole($arrGroupedRights);
    }

    /**
     * Load company required fields - for specific template
     *
     * @param int $companyId
     * @param int $caseTemplateId
     * @param bool $booSkipAccessRights if true - load fields which are required and "skip_access_requirements" is set to N
     * @return array of ids for all required fields
     */
    public function getCompanyRequiredFields($companyId, $caseTemplateId, $booSkipAccessRights = false)
    {
        $subSelect = (new Select())
            ->from('client_form_fields')
            ->columns(['field_id'])
            ->where(
                [
                    'required' => 'Y',
                    'company_id' => (int)$companyId
                ]
            );

        if ($booSkipAccessRights) {
            $subSelect->where->equalTo('skip_access_requirements', 'N');
        }

        $select = (new Select())
            ->from(array('o' => 'client_form_order'))
            ->columns(['field_id'])
            ->join(array('g' => 'client_form_groups'), 'o.group_id = g.group_id', [], Select::JOIN_LEFT)
            ->where(
                [
                    (new Where())
                        ->in('o.field_id', $subSelect)
                        ->equalTo('g.client_type_id', $caseTemplateId)
                        ->equalTo('g.assigned', 'A')
                ]
            );

        return $this->_db2->fetchCol($select);
    }

    /**
     * Load company required for submission fields - for specific template
     *
     * @param int $companyId
     * @param int $caseTemplateId
     * @return array of ids for all required fields
     */
    public function getCompanyRequiredForSubmissionFields($companyId, $caseTemplateId)
    {
        $subSelect = (new Select())
            ->from('client_form_fields')
            ->columns(['field_id'])
            ->where(
                [
                    'required_for_submission' => 'Y',
                    'company_id'              => (int)$companyId
                ]
            );

        $select = (new Select())
            ->from(array('o' => 'client_form_order'))
            ->columns(['field_id'])
            ->join(array('g' => 'client_form_groups'), 'o.group_id = g.group_id', [], Select::JOIN_LEFT)
            ->where(
                [
                    (new Where())
                        ->in('o.field_id', $subSelect)
                        ->equalTo('g.client_type_id', $caseTemplateId)
                        ->equalTo('g.assigned', 'A')
                ]
            );

        return $this->_db2->fetchCol($select);
    }

    /**
     * Check if field is in specific group
     *
     * @param $fieldId
     * @param $groupId
     * @return bool true if field is assigned to the group
     */
    public function isFieldInGroup($fieldId, $groupId)
    {
        $booInGroup = false;

        // Check if this group exists in this company
        $select = (new Select())
            ->from('client_form_order')
            ->columns(array('fields_count' => new Expression('COUNT(*)')))
            ->where(
                [
                    'field_id' => (int)$fieldId,
                    'group_id' => (int)$groupId
                ]
            );

        $totalGroups = $this->_db2->fetchOne($select);

        if ($totalGroups > 0) {
            $booInGroup = true;
        }

        return $booInGroup;
    }

    /**
     * Check if the group is related to the company
     *
     * @param $companyId
     * @param $groupId
     * @param bool $booAssignedOnly
     * @return bool true if it is related
     */
    public function isGroupInCompany($companyId, $groupId, $booAssignedOnly = true)
    {
        $booInCompany = false;

        // Check if this group exists in this company
        $select = (new Select())
            ->from('client_form_groups')
            ->columns(array('groups_count' => new Expression('COUNT(*)')))
            ->where(
                [
                    'company_id' => (int)$companyId,
                    'group_id'   => (int)$groupId
                ]
            );

        if ($booAssignedOnly) {
            $select->where->equalTo('assigned', 'A'); // Only assigned groups can be deleted
        }

        $totalGroups = $this->_db2->fetchOne($select);

        if ($totalGroups > 0) {
            $booInCompany = true;
        }

        return $booInCompany;
    }

    /**
     * Load information about the field for the company
     *
     * @param $fieldId
     * @param $companyId
     * @param $caseTemplateId
     * @return array with field info
     */
    public function getFieldInfo($fieldId, $companyId, $caseTemplateId = null)
    {
        $arrFieldInfo = $this->getFieldInfoById($fieldId);
        if (empty($arrFieldInfo)) {
            return [];
        }

        $arrDefaults = $this->getCompanyFieldOptions($companyId, 0, $arrFieldInfo['field_type_text_id'], $arrFieldInfo['company_field_id'], $caseTemplateId);
        foreach ($arrDefaults as $defaultOption) {
            $arrFieldInfo['default_val'][] = array(
                $defaultOption['option_id'],
                $defaultOption['option_name'],
                $defaultOption['option_order'],
                isset($defaultOption['option_deleted']) && $defaultOption['option_deleted'] == 'Y',
            );
        }

        $arrFieldTypes = $this->_parent->getFieldTypes()->getFieldTypes();
        foreach ($arrFieldTypes as $fType) {
            if ($fType['id'] == $arrFieldInfo['type']) {
                $arrFieldInfo['type_label'] = $fType['label'];
                $arrFieldInfo['booWithMaxLength'] = $fType['booWithMaxLength'];
                $arrFieldInfo['booWithOptions'] = $fType['booWithOptions'];
                $arrFieldInfo['booWithDefaultValue'] = $fType['booWithDefaultValue'];
                $arrFieldInfo['booWithCustomHeight'] = $fType['booWithCustomHeight'];
                break;
            }
        }

        if (!is_null($caseTemplateId)) {
            $select = (new Select())
                ->from(array('o' => 'client_form_order'))
                ->columns(['use_full_row'])
                ->join(array('g' => 'client_form_groups'), 'g.group_id = o.group_id', Select::SQL_STAR, Select::JOIN_LEFT)
                ->where(
                    [
                        'o.field_id'       => (int)$fieldId,
                        'g.client_type_id' => (int)$caseTemplateId,
                        'g.company_id'     => (int)$companyId
                    ]
                );

            $useFullRow                   = $this->_db2->fetchOne($select);
            $arrFieldInfo['use_full_row'] = $useFullRow == 'Y' ? 'Y' : 'N';
        } else {
            $arrFieldInfo['use_full_row'] = 'N';
        }

        return $arrFieldInfo;
    }

    /**
     * Delete case profile field(s)
     *
     * @param int|array $fieldId
     * @return bool true on success
     */
    public function deleteField($fieldId)
    {
        $booTransactionStarted = false;

        try {
            $this->_db2->getDriver()->getConnection()->beginTransaction();
            $booTransactionStarted = true;

            // Delete field
            $this->_db2->delete('client_form_data', ['field_id' => $fieldId]);
            $this->_db2->delete('client_form_order', ['field_id' => $fieldId]);
            $this->_db2->delete('client_form_field_access', ['field_id' => $fieldId]);
            $this->_db2->delete('client_form_default', ['field_id' => $fieldId]);
            $this->_db2->delete('client_form_fields', ['field_id' => $fieldId]);

            $this->_db2->getDriver()->getConnection()->commit();
            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            if ($booTransactionStarted) {
                $this->_db2->getDriver()->getConnection()->rollback();
            }
        }

        return $booSuccess;
    }

    public function deleteGroup($groupId)
    {
        $booSuccess = false;
        try {
            $groupId = is_array($groupId) ? $groupId : array($groupId);
            if (count($groupId)) {
                $arrTables = array('client_form_order', 'client_form_group_access', 'client_form_groups');
                foreach ($arrTables as $table) {
                    $this->_db2->delete($table, ['group_id' => $groupId]);
                }
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    public function deleteAllCompanyGroups($companyId)
    {
        $arrGroups = $this->getCompanyGroups($companyId);

        $arrIds = array();
        foreach ($arrGroups as $arrGroupInfo) {
            $arrIds[] = $arrGroupInfo['group_id'];
        }
        $this->deleteGroup($arrIds);
    }

    /**
     * Set access to the new Dependants group the same as it is set for already created groups
     *
     * @param int $companyId
     * @param int $groupId
     * @return void
     */
    private function setAccessToDependants($companyId, $groupId)
    {
        // Find max access rights for all roles -> save the same for the new group
        $select = (new Select())
            ->from(array('g' => 'client_form_groups'))
            ->columns(['group_id'])
            ->join(array('ga' => 'client_form_group_access'), 'ga.group_id = g.group_id', array('role_id', 'status'))
            ->where([
                'g.company_id' => (int)$companyId,
                'g.title'      => 'Dependants'
            ]);

        $arrAllAccessRights = $this->_db2->fetchAll($select);

        // Get the max access right for each role
        $arrGroupedAccessRights = [];
        foreach ($arrAllAccessRights as $accessRight) {
            if (!isset($arrGroupedAccessRights[$accessRight['role_id']])) {
                $arrGroupedAccessRights[$accessRight['role_id']] = $accessRight['status'];
            } elseif ($accessRight['status'] == 'F') {
                $arrGroupedAccessRights[$accessRight['role_id']] = 'F';
            }
        }

        foreach ($arrGroupedAccessRights as $roleId => $newAccess) {
            $this->createGroupAccessRecord($roleId, $groupId, $newAccess);
        }

        $this->_roles->clearCompanyRolesCache($companyId);
    }

    /**
     * Create a new fields group for the company
     *
     * @param int $companyId
     * @param string $groupName
     * @param int $groupColsCount
     * @param int $caseTemplateId
     * @param bool $isGroupCollapsed
     * @param bool $isGroupTitleVisible
     * @param string $assigned
     * @return false|int on success group id, otherwise false
     */
    public function createGroup($companyId, $groupName, $groupColsCount, $caseTemplateId, $isGroupCollapsed, $isGroupTitleVisible, $assigned = 'A')
    {
        try {
            $maxOrder  = -1;
            $arrGroups = $this->getCompanyGroups($companyId, true, $caseTemplateId);
            foreach ($arrGroups as $arrGroupInfo) {
                $maxOrder = max($maxOrder, $arrGroupInfo['order']);
            }

            $groupId = $this->_db2->insert(
                'client_form_groups',
                [
                    'company_id'     => $companyId,
                    'client_type_id' => $caseTemplateId,
                    'title'          => $groupName,
                    'order'          => $assigned == 'A' ? $maxOrder + 1 : 100,
                    'cols_count'     => $groupColsCount,
                    'regTime'        => time(),
                    'assigned'       => $assigned,
                    'collapsed'      => $isGroupCollapsed ? 'Y' : 'N',
                    'show_title'     => $isGroupTitleVisible ? 'Y' : 'N'
                ],
                0
            );

            if ($groupName == 'Dependants') {
                $this->setAccessToDependants($companyId, $groupId);
            }

            if ($companyId == $this->_company->getDefaultCompanyId()) {
                $arrCaseTemplates = $this->_parent->getCaseTemplates()->getCaseTemplatesByParentId($caseTemplateId);
                foreach ($arrCaseTemplates as $templateInfo) {
                    $newGroupId = $this->_db2->insert(
                        'client_form_groups',
                        [
                            'company_id'      => $templateInfo['company_id'],
                            'client_type_id'  => $templateInfo['client_type_id'],
                            'parent_group_id' => $groupId,
                            'title'           => $groupName,
                            'order'           => $assigned == 'A' ? $maxOrder + 1 : 100,
                            'cols_count'      => $groupColsCount,
                            'regTime'         => time(),
                            'assigned'        => $assigned,
                            'collapsed'       => $isGroupCollapsed ? 'Y' : 'N',
                            'show_title'      => $isGroupTitleVisible ? 'Y' : 'N'
                        ]
                    );

                    if ($groupName == 'Dependants') {
                        $this->setAccessToDependants($templateInfo['company_id'], $newGroupId);
                    }
                }
            }
        } catch (Exception $e) {
            $groupId = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $groupId;
    }

    /**
     * Update fields' group's info
     *
     * @param int $groupId
     * @param array $arrGroupInfo
     * @param bool $booParentGroup true if a parent group's id was passed
     * @return bool
     */
    public function updateGroupInfo($groupId, $arrGroupInfo, $booParentGroup = false)
    {
        try {
            $arrWhere = array();
            if ($booParentGroup) {
                $arrWhere['parent_group_id'] = (int)$groupId;
            } else {
                $arrWhere['group_id'] = (int)$groupId;
            }

            $this->_db2->update('client_form_groups', $arrGroupInfo, $arrWhere);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }


    public function updateGroup($companyId, $groupId, $groupName, $groupColsCount, $isGroupCollapsed, $isGroupTitleVisible)
    {
        try {
            $this->updateGroupInfo(
                $groupId,
                array(
                    'title'      => $groupName,
                    'cols_count' => $groupColsCount,
                    'collapsed'  => $isGroupCollapsed ? 'Y' : 'N',
                    'show_title' => $isGroupTitleVisible ? 'Y' : 'N'
                )
            );

            if ($companyId == $this->_company->getDefaultCompanyId()) {
                $this->updateGroupInfo(
                    $groupId,
                    array(
                        'title'      => $groupName,
                        'cols_count' => $groupColsCount,
                        'collapsed'  => $isGroupCollapsed ? 'Y' : 'N',
                        'show_title' => $isGroupTitleVisible ? 'Y' : 'N'
                    ),
                    true
                );
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    public function getClientCategories($booAddDate = false)
    {
        $categories = array();

        if ($booAddDate) {
            $categories[] = array(
                'cId'      => 0,
                'cFieldId' => 0,
                'cType'    => 'date',
                'cName'    => 'A Specific Date'
            );
        }

        $id = 1;

        $arrDateFields = $this->getDateFields();
        foreach ($arrDateFields as $arrFieldInfo) {
            $categories[] = array(
                'cId'      => $id++,
                'cFieldId' => $arrFieldInfo['field_id'],
                'cType'    => 'profile_date',
                'cName'    => $arrFieldInfo['label']
            );
        }

        $companyId         = $this->_auth->getCurrentUserCompanyId();
        $arrFileStatusInfo = $this->getCompanyFieldInfoByUniqueFieldId('file_status', $companyId);
        if (!empty($arrFileStatusInfo)) {
            $arrStatuses = $this->getParent()->getCaseStatuses()->getCompanyCaseStatuses($companyId);
            foreach ($arrStatuses as $arrStatusInfo) {
                $categories[] = array(
                    'cId'       => $id++,
                    'cFieldId'  => $arrFileStatusInfo['field_id'],
                    'cOptionId' => $arrStatusInfo['client_status_id'],
                    'cType'     => 'file_status',
                    'cName'     => $arrStatusInfo['client_status_name']
                );
            }
        }

        return $categories;
    }

    public function getMaritalStatuses()
    {
        return array('Married', 'Engaged', 'De-Facto/Common Law', 'Common Law');
    }

    /**
     * @param $arrFields
     * @param $companyFieldId
     * @param bool $booIdOnly
     * @param bool $booCaseFields
     * @return array field info or id
     */
    public function getFieldId($arrFields, $companyFieldId, $booIdOnly = true, $booCaseFields = true)
    {
        $booSuccess = false;
        $result = array();

        $strFieldIdKey = $booCaseFields ? 'company_field_id' : 'applicant_field_unique_id';
        $fieldIdKey = $booCaseFields ? 'field_id' : 'applicant_field_id';
        foreach ($arrFields as $fieldInfo) {
            if ($fieldInfo[$strFieldIdKey] == $companyFieldId) {
                $booSuccess = true;
                $result = $booIdOnly ? $fieldInfo[$fieldIdKey] : $fieldInfo;
                break;
            }
        }

        // Field is incorrect/not found
        if (!$booSuccess) {
            $result = sprintf(
                '<div style="color: red;">' .
                $this->_tr->translate('Field <em>%s</em> was not found for this company') .
                '</div>',
                $companyFieldId
            );
        }

        return array('success' => $booSuccess, 'result' => $result);
    }

    /**
     * Load groups ids where provided field ids are placed to
     *
     * @param array $arrFieldsIds
     * @return array
     */
    public function getGroupsByFieldsIds($arrFieldsIds)
    {
        $arrGroupIds = array();
        if (!empty($arrFieldsIds)) {
            $select = (new Select())
                ->from('client_form_order')
                ->columns(['group_id'])
                ->where([
                    (new Where())->in('field_id', $arrFieldsIds)
                ])
                ->group('group_id');

            $arrGroupIds = $this->_db2->fetchCol($select);
        }

        return $arrGroupIds;
    }

    /**
     * Load field value by its text value
     *
     * @param $arrFields
     * @param $strFieldId
     * @param $fieldVal
     * @param int $clientNum
     * @param int $companyId
     * @param bool $booCaseFields
     * @param bool $booReturnValueOnly
     * @param bool $booImport
     * Note: this is not PHP date constants, but ISO.
     * Check details here: http://framework.zend.com/manual/1.12/en/zend.date.constants.html
     * @return array|int|string
     */
    public function getFieldValue($arrFields, $strFieldId, $fieldVal, $clientNum = null, $companyId = 0, $booCaseFields = true, $booReturnValueOnly = false, $booImport = true)
    {
        $booCheckEmail = false;
        $booCheckPhone = false;

        $strError = '';
        $strResultValue = '';

        try {
            // Check if field exists, get its id
            $arrFieldInfoResult = $this->getFieldId($arrFields, $strFieldId, false, $booCaseFields);

            $arrFieldInfo = array();
            if (!$arrFieldInfoResult['success']) {
                $strError = $arrFieldInfoResult['result'];
            } else {
                $arrFieldInfo = $arrFieldInfoResult['result'];
            }

            // If field is required and value is empty - return error
            $fieldVal = trim($fieldVal ?? '');
            if (empty($strError) && $arrFieldInfo['required'] == 'Y' && empty($fieldVal) && !is_numeric($fieldVal)) {
                $strError = sprintf(
                    $this->_tr->translate('Empty value for <em>%s</em>'),
                    $strFieldId
                );
            }

            // Check for data correctness for specific field types
            if (empty($strError)) {
                switch ($arrFieldInfo['type']) {
                    // Date
                    case 'date':
                    case 'rdate':
                    case 'date_repeatable':
                    case $this->_parent->getFieldTypes()->getFieldTypeId('date'):
                    case $this->_parent->getFieldTypes()->getFieldTypeId('rdate'):
                    case $this->_parent->getFieldTypes()->getFieldTypeId('date_repeatable'):
                        if ($fieldVal) {
                            //if ($booImport) {
                            //    if (Settings::isValidDateFormat($fieldVal, 'm/d/y')) {
                            //        $strResultValue = $this->_settings->reformatDate($fieldVal, 'm/d/y', Settings::DATE_UNIX);
                            //    } else {
                            //        if (Settings::isValidDateFormat($fieldVal, $dateFormatFull)) {
                            //            $strResultValue = $this->_settings->reformatDate($fieldVal, $dateFormatFull, Settings::DATE_UNIX);
                            //        } else {
                            //            $strError = sprintf('Incorrect date <em>%s</em>.', $fieldVal);
                            //        }
                            //    }
                            //} else {
                            if (!$timestamp = strtotime($fieldVal)) {
                                $strError = sprintf('Incorrect date <em>%s</em>.', $fieldVal);
                            } else {
                                $strResultValue = date(Settings::DATE_UNIX, $timestamp);
                            }
                            //}
                        }
                        break;

                    // Email
                    case 'email':
                    case $this->_parent->getFieldTypes()->getFieldTypeId('email'):
                        if ($booCheckEmail) {
                            $validator = new EmailAddress();
                            if (!empty($fieldVal) && !$validator->isValid($fieldVal)) {
                                $strError = sprintf($this->_tr->translate('<em>%s</em> is incorrect.'), $fieldVal);
                            }
                        }

                        $strResultValue = $fieldVal;
                        break;

                    // Combo
                    case 'combo':
                    case 'combobox':
                    case $this->_parent->getFieldTypes()->getFieldTypeId('combo'):
                    case $this->_parent->getFieldTypes()->getFieldTypeId('combobox'):
                        if ($arrFieldInfo['required'] == 'Y' || ($arrFieldInfo['required'] == 'N' && $fieldVal != '')) {
                            $booCorrectOption = false;
                            if ($booCaseFields) {
                                $arrOptions = $this->getFieldOptions($arrFieldInfo['field_id']);
                                foreach ($arrOptions as $arrOption) {
                                    if ($arrOption['option_name'] == $fieldVal || ($booImport && strtolower($arrOption['option_name'] ?? '') == strtolower($fieldVal ?? ''))) {
                                        $booCorrectOption = true;
                                        $strResultValue = $arrOption['option_id'];
                                        break;
                                    }
                                }
                            } else {
                                $arrOptions = $this->_parent->getApplicantFields()->getFieldsOptions(array($arrFieldInfo['applicant_field_id']));
                                foreach ($arrOptions as $arrOption) {
                                    if ($arrOption['value'] == $fieldVal || ($booImport && strtolower($arrOption['value'] ?? '') == strtolower($fieldVal ?? ''))) {
                                        $booCorrectOption = true;
                                        $strResultValue = $arrOption['applicant_form_default_id'];
                                        break;
                                    }
                                }
                            }

                            if (!$booCorrectOption) {
                                $strError = sprintf($this->_tr->translate('Option <em>%s</em> is not correct.'), $fieldVal);
                            }
                        } else {
                            $strResultValue = $fieldVal;
                        }
                        break;

                    case 'multiple_combo':
                    case $this->_parent->getFieldTypes()->getFieldTypeId('multiple_combo'):
                        if ($arrFieldInfo['required'] == 'Y' || ($arrFieldInfo['required'] == 'N' && $fieldVal != '')) {
                            $arrFieldValues = explode(',', $fieldVal);
                            $booCorrectValue = true;
                            $arrValues = array();
                            if ($booCaseFields) {
                                $arrOptions = $this->getFieldOptions($arrFieldInfo['field_id']);

                                foreach ($arrFieldValues as $fieldValue) {
                                    $booCorrectOption = false;
                                    foreach ($arrOptions as $arrOption) {
                                        if ($arrOption['option_name'] == $fieldValue || ($booImport && strtolower($arrOption['option_name'] ?? '') == strtolower($fieldValue ?? ''))) {
                                            $arrValues[] = $arrOption['option_id'];
                                            $booCorrectOption = true;
                                            break;
                                        }
                                    }
                                    if (!$booCorrectOption) {
                                        $booCorrectValue = false;
                                    }
                                }
                            } else {
                                $arrOptions = $this->_parent->getApplicantFields()->getFieldsOptions(array($arrFieldInfo['applicant_field_id']));

                                foreach ($arrFieldValues as $fieldValue) {
                                    $booCorrectOption = false;
                                    foreach ($arrOptions as $arrOption) {
                                        if ($arrOption['value'] == $fieldValue || ($booImport && strtolower($arrOption['value'] ?? '') == strtolower($fieldValue))) {
                                            $arrValues[] = $arrOption['applicant_form_default_id'];
                                            $booCorrectOption = true;
                                            break;
                                        }
                                    }
                                    if (!$booCorrectOption) {
                                        $booCorrectValue = false;
                                    }
                                }
                            }
                            $strResultValue = implode(',', $arrValues);

                            if (!$booCorrectValue) {
                                $strError = sprintf($this->_tr->translate('Field value <em>%s</em> is not correct.'), $fieldVal);
                            }
                        } else {
                            $strResultValue = $fieldVal;
                        }
                        break;


                    // Categories - special combobox
                    case 'categories':
                    case $this->_parent->getFieldTypes()->getFieldTypeId('categories'):
                        $booCorrectOption = false;
                        $arrCategories    = $this->_parent->getCaseCategories()->getCompanyCaseCategories($companyId);

                        foreach ($arrCategories as $arrCategoryOption) {
                            if ($arrCategoryOption['client_category_name'] == $fieldVal) {
                                $booCorrectOption = true;
                                $strResultValue = $arrCategoryOption['client_category_id'];
                                break;
                            }
                        }

                        if (!$booCorrectOption) {
                            $strError = sprintf($this->_tr->translate('Option <em>%s</em> is not correct.'), $fieldVal);
                        }
                        break;

                    // Case Status - a special combobox
                    case 'case_status':
                    case $this->_parent->getFieldTypes()->getFieldTypeId('case_status'):
                        $booCorrectOption = false;
                        $arrCaseStatuses  = $this->_parent->getCaseStatuses()->getCompanyCaseStatuses($companyId);

                        foreach ($arrCaseStatuses as $arrCaseStatusInfo) {
                            if ($arrCaseStatusInfo['client_status_name'] == $fieldVal) {
                                $booCorrectOption = true;
                                $strResultValue   = $arrCaseStatusInfo['client_status_id'];
                                break;
                            }
                        }

                        if (!$booCorrectOption) {
                            $strError = sprintf($this->_tr->translate('Option <em>%s</em> is not correct.'), $fieldVal);
                        }
                        break;

                    // Number
                    case 'number':
                    case $this->_parent->getFieldTypes()->getFieldTypeId('number'):
                        if (!$this->validNumber($fieldVal)) {
                            $strError = sprintf($this->_tr->translate('Number <em>%s</em> is not correct.'), $fieldVal);
                        }
                        $strResultValue = $fieldVal;
                        break;

                    // Phone
                    case 'phone':
                    case $this->_parent->getFieldTypes()->getFieldTypeId('phone'):
                        if ($booCheckPhone && !$this->validPhone($fieldVal)) {
                            $strError = sprintf($this->_tr->translate('Phone <em>%s</em> is not correct.'), $fieldVal);
                        }
                        $strResultValue = $fieldVal;
                        break;

                    // Checkbox/Radio
                    case 'radio':
                    case 'checkbox':
                    case $this->_parent->getFieldTypes()->getFieldTypeId('radio'):
                    case $this->_parent->getFieldTypes()->getFieldTypeId('checkbox'):
                        // If value is not 'no' , 'n', 'false' and not empty - that's means that it is checked
                        if ($strFieldId == 'Client_file_status' && $fieldVal == 'Active') {
                            $strResultValue = $fieldVal;
                        } elseif (!empty($fieldVal) && !in_array(strtolower($fieldVal ?? ''), array('n', 'no', 'false'))) {
                            $strResultValue = 'yes';
                        }
                        break;

                    // Agent
                    case 'agent':
                    case $this->_parent->getFieldTypes()->getFieldTypeId('agent'):
                        $cacheIdAgents = 'import_agents_' . $companyId;
                        if (!($arrAgents = $this->_cache->getItem($cacheIdAgents))) {
                            $arrAgents = $this->_parent->getAgents(false, $companyId);
                            $this->_cache->setItem($cacheIdAgents, $arrAgents);
                        }

                        if ($fieldVal == '') {
                            $strResultValue = $fieldVal;
                        } else {
                            $booCorrectAgent = false;
                            foreach ($arrAgents as $agent) {
                                if (trim($agent['fName'] . ' ' . $agent['lName']) == $fieldVal) {
                                    $strResultValue = $agent['agent_id'];
                                    $booCorrectAgent = true;
                                    break;
                                }
                            }

                            if (!$booCorrectAgent) {
                                $strError = sprintf($this->_tr->translate('Agent <em>%s</em> is not correct.'), $fieldVal);
                            }
                        }
                        break;

                    // Staff Responsible
                    case 'assigned_to':
                    case $this->_parent->getFieldTypes()->getFieldTypeId('assigned_to'):
                        $cacheIdStaffResponsible = 'import_assigned_to_' . $companyId;
                        if (!($arrAssignedTo = $this->_cache->getItem($cacheIdStaffResponsible))) {
                            /** @var Users $users */
                            $users         = $this->_serviceContainer->get(Users::class);
                            $arrAssignedTo = $users->getAssignList('search', null, $booImport);
                            $this->_cache->setItem($cacheIdStaffResponsible, $arrAssignedTo);
                        }

                        if ($fieldVal == '') {
                            $strResultValue = $fieldVal;
                        } else {
                            $id = 0;
                            foreach ($arrAssignedTo as $arrAssignedToInfo) {
                                if ($arrAssignedToInfo['assign_to_name'] == $fieldVal || $arrAssignedToInfo['assign_to_id'] == $fieldVal) {
                                    $id = $arrAssignedToInfo['assign_to_id'];
                                    break;
                                }
                            }

                            if (!$id) {
                                $strError = sprintf($this->_tr->translate('Staff Responsible <em>%s</em> is not correct.'), $fieldVal);
                            }
                            $strResultValue = $id;
                        }
                        break;

                    // RMA
                    case 'staff_responsible_rma':
                    case $this->_parent->getFieldTypes()->getFieldTypeId('staff_responsible_rma'):
                        $cacheIdRMA = 'import_rma_' . $companyId;
                        if (!($arrRMAAssignedTo = $this->_cache->getItem($cacheIdRMA))) {
                            /** @var Users $oUsers */
                            $oUsers = $this->_serviceContainer->get(Users::class);

                            $arrRMAAssignedTo = $oUsers->getAssignedToUsers(true, null, 0, $booImport);
                            $this->_cache->setItem($cacheIdRMA, $arrRMAAssignedTo);
                        }

                        if ($fieldVal == '') {
                            $strResultValue = $fieldVal;
                        } else {
                            $id = 0;
                            foreach ($arrRMAAssignedTo as $arrAssignedToInfo) {
                                if ($arrAssignedToInfo['option_name'] == trim($fieldVal) || $arrAssignedToInfo['option_id'] == trim($fieldVal)) {
                                    $id = $arrAssignedToInfo['option_id'];
                                    break;
                                }
                            }

                            if (!$id) {
                                $strError = sprintf(
                                    $this->_tr->translate('%s <em>%s</em> is not correct.'),
                                    $this->_company->getCurrentCompanyDefaultLabel('rma'),
                                    $fieldVal
                                );
                            }
                            $strResultValue = $id;
                        }
                        break;

                    // 'Country'

                    // Division
                    case 'division':
                    case 'office':
                    case 'office_multi':
                    case $this->_parent->getFieldTypes()->getFieldTypeId('division'):
                    case $this->_parent->getFieldTypes()->getFieldTypeId('office'):
                    case $this->_parent->getFieldTypes()->getFieldTypeId('office_multi'):
                        $divisions = $this->_parent->getDivisions();

                        $division_id = 0;
                        foreach ($divisions as $d) {
                            if ($d['name'] == $fieldVal) {
                                $division_id = $d['division_id'];
                                break;
                            }
                        }

                        if (!$division_id) {
                            $strError = sprintf(
                                $this->_tr->translate('%s <em>%s</em> is not correct.'),
                                $this->_company->getCurrentCompanyDefaultLabel('office'),
                                $fieldVal
                            );
                        }

                        $strResultValue = $division_id;
                        break;

                    // Don't check these fields
                    case $this->_parent->getFieldTypes()->getFieldTypeId('text'):
                    case $this->_parent->getFieldTypes()->getFieldTypeId('memo'):
                    case $this->_parent->getFieldTypes()->getFieldTypeId('password'):
                    default:
                        $strResultValue = $fieldVal;
                        break;
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError) && !is_null($clientNum)) {
            $strError .= sprintf(' (client row #%d)', $clientNum + 1);
        }

        return $booReturnValueOnly ? $strResultValue : array('error' => !empty($strError), 'error-msg' => $strError, 'result' => $strResultValue);
    }

    /**
     * Save access rights for form groups for specific role
     *
     * @param array $arrGroupedAccessRights
     * @return bool
     */
    public function saveGroupsAccessForRoles($arrGroupedAccessRights)
    {
        if (empty($arrGroupedAccessRights)) {
            return false;
        }

        $select = (new Select())
            ->from(array('a' => 'client_form_group_access'))
            ->where(['a.role_id' => array_keys($arrGroupedAccessRights)]);

        $arrAllRolesAccess = $this->_db2->fetchAll($select);

        // Group saved roles access rights for fields groups
        $arrAllRolesAccessGrouped = [];
        foreach ($arrAllRolesAccess as $arrRoleAccess) {
            $arrAllRolesAccessGrouped[$arrRoleAccess['role_id']][$arrRoleAccess['group_id']] = $arrRoleAccess;
        }

        // Group/prepare records that we want to create/update/delete
        // So, we'll process them in one batch
        $arrAccessToDelete     = [];
        $arrAccessToCreate     = [];
        $arrAccessToUpdateRead = [];
        $arrAccessToUpdateFull = [];
        foreach ($arrGroupedAccessRights as $roleId => $arrGroups) {
            foreach ($arrGroups as $groupId => $newAccess) {
                $booIsSaved  = isset($arrAllRolesAccessGrouped[$roleId][$groupId]);
                $savedAccess = isset($arrAllRolesAccessGrouped[$roleId][$groupId]) ? $arrAllRolesAccessGrouped[$roleId][$groupId]['status'] : '';

                if ($savedAccess === $newAccess) {
                    // No changes
                    continue;
                }

                if ($booIsSaved) {
                    // Update or delete
                    switch ($newAccess) {
                        case 'F':
                            $arrAccessToUpdateFull[] = $arrAllRolesAccessGrouped[$roleId][$groupId]['access_id'];
                            break;

                        case 'R':
                            $arrAccessToUpdateRead[] = $arrAllRolesAccessGrouped[$roleId][$groupId]['access_id'];
                            break;

                        default:
                            $arrAccessToDelete[] = $arrAllRolesAccessGrouped[$roleId][$groupId]['access_id'];
                            break;
                    }
                } else {
                    // Create
                    $arrAccessToCreate[] = sprintf(
                        '(%d, %d, %s)',
                        $roleId,
                        $groupId,
                        $this->_db2->getPlatform()->quoteValue($newAccess)
                    );
                }
            }
        }

        if (!empty($arrAccessToCreate)) {
            $sql = sprintf("INSERT INTO client_form_group_access (`role_id`, `group_id`, `status`) VALUES %s", implode(',', $arrAccessToCreate));
            $this->_db2->query($sql, Adapter::QUERY_MODE_EXECUTE);
        }

        if (!empty($arrAccessToUpdateFull)) {
            $this->_db2->update(
                'client_form_group_access',
                ['status' => 'F'],
                ['access_id' => $arrAccessToUpdateFull]
            );
        }

        if (!empty($arrAccessToUpdateRead)) {
            $this->_db2->update(
                'client_form_group_access',
                ['status' => 'R'],
                ['access_id' => $arrAccessToUpdateRead]
            );
        }

        if (!empty($arrAccessToDelete)) {
            $this->_db2->delete(
                'client_form_group_access',
                ['access_id' => $arrAccessToDelete]
            );
        }

        return true;
    }

    /**
     * Load grouped list of fields for specific company
     *
     * @param $companyId
     * @return array
     */
    public function getGroupedFieldsByCompanyId($companyId)
    {
        // Load correct fields list from DB
        $arrGroupedFields = array();

        $arrCaseTemplates = $this->_parent->getCaseTemplates()->getTemplates($companyId);
        foreach ($arrCaseTemplates as $arrCaseTemplateInfo) {
            $arrGroupedFields[] = array(
                'template_id' => $arrCaseTemplateInfo['case_template_id'],
                'template_name' => $arrCaseTemplateInfo['case_template_name'],
                'fields' => $this->getAllGroupsAndFields($companyId, $arrCaseTemplateInfo['case_template_id'], true)
            );
        }

        return $arrGroupedFields;
    }

    /**
     * Prepare Reference type field on loading
     *
     * @param $fieldValue
     * @param $booMultipleValues
     * @return array
     */
    public function prepareReferenceField($fieldValue, $booMultipleValues)
    {
        $arrResult = array();
        if (is_string($fieldValue) && is_array(json_decode($fieldValue, true)) && (json_last_error() == JSON_ERROR_NONE)) {
            $arrValues = Json::decode($fieldValue, Json::TYPE_ARRAY);
            if ($booMultipleValues) {
                $arrResult['value'] = $fieldValue;
            } else {
                $arrResult['value'] = $arrValues[0] ?? '';
            }
        } else {
            $arrValues = array($fieldValue);
            if ($booMultipleValues) {
                $arrResult['value'] = Json::encode($arrValues);
            } else {
                $arrResult['value'] = $fieldValue;
            }
        }

        foreach ($arrValues as $value) {
            $booWrongValue = $booApplicant = $booCase = $booField = false;
            $referenceApplicantId = $referenceCaseId = $referenceCaseTypeId = 0;
            $referenceApplicantType = $referenceApplicantName = $referenceCaseName = $referenceText = $referenceFieldName = '';
            $arrReferenceApplicantInfo = $arrReferenceCaseInfo = array();

            if (preg_match('/^individual_profile_([\d]+)_field_(.+)$/i', $value, $regs)) {
                $referenceApplicantId = $regs[1];
                $referenceFieldName = $regs[2];
                $referenceApplicantType = 'individual';
                $booField = $booApplicant = true;
            } else {
                if (preg_match('/^individual_profile_([\d]+)$/i', $value, $regs)) {
                    $referenceApplicantId = $regs[1];
                    $referenceApplicantType = 'individual';
                    $booApplicant = true;
                } else {
                    if (preg_match('/^employer_profile_([\d]+)_field_(.+)$/i', $value, $regs)) {
                        $referenceApplicantId = $regs[1];
                        $referenceFieldName = $regs[2];
                        $referenceApplicantType = 'employer';
                        $booField = $booApplicant = true;
                    } else {
                        if (preg_match('/^employer_profile_([\d]+)$/i', $value, $regs)) {
                            $referenceApplicantId = $regs[1];
                            $referenceApplicantType = 'employer';
                            $booApplicant = true;
                        } else {
                            if (preg_match('/^case_([\d]+)_field_(.+)$/i', $value, $regs)) {
                                $referenceCaseId = $regs[1];
                                $referenceFieldName = $regs[2];
                                $booField = $booCase = true;
                            } else {
                                if (preg_match('/^case_([\d]+)$/i', $value, $regs)) {
                                    $referenceCaseId = $regs[1];
                                    $booCase = true;
                                } else {
                                    $booWrongValue = true;
                                    $referenceText = 'Incorrect reference';
                                }
                            }
                        }
                    }
                }
            }

            if ($booApplicant && !empty($referenceApplicantId) && !$booWrongValue) {
                if (!$this->_parent->hasCurrentMemberAccessToMember($referenceApplicantId)) {
                    $booWrongValue = true;
                    $referenceText = 'Insufficient access rights to the client';
                } else {
                    $arrReferenceApplicantInfo = $this->_parent->getClientInfo($referenceApplicantId);
                    if ($arrReferenceApplicantInfo) {
                        $referenceMemberTypeId = $arrReferenceApplicantInfo['userType'];
                        if ($this->_parent->getMemberTypeNameById($referenceMemberTypeId) != $referenceApplicantType) {
                            $booWrongValue = true;
                            $referenceText = 'Incorrect client id';
                        } else {
                            $referenceText = $referenceApplicantName = $arrReferenceApplicantInfo['full_name'];
                        }
                    } else {
                        $booWrongValue = true;
                        $referenceText = 'Incorrect client id';
                    }
                }
            }

            if ($booCase && !empty($referenceCaseId) && !$booWrongValue) {
                if (!$this->_parent->hasCurrentMemberAccessToMember($referenceCaseId)) {
                    $booWrongValue = true;
                    $referenceText = 'Insufficient access rights to the case';
                } else {
                    $arrReferenceCaseInfo  = $this->_parent->getClientInfo($referenceCaseId);
                    $referenceMemberTypeId = $arrReferenceCaseInfo['userType'];

                    if ($this->_parent->getMemberTypeNameById($referenceMemberTypeId) != 'case') {
                        $booWrongValue = true;
                        $referenceText = 'Incorrect case id';
                    } else {
                        $referenceCaseName   = $arrReferenceCaseInfo['full_name_with_file_num'];
                        $referenceText       = 'Case #' . $referenceCaseName;
                        $referenceCaseTypeId = $arrReferenceCaseInfo['client_type_id'];
                        $arrParentIds        = $this->_parent->getParentsForAssignedApplicant($referenceCaseId);

                        if (!empty($arrParentIds)) {
                            $referenceApplicantId            = $arrParentIds[0];
                            $arrReferenceParentApplicantInfo = $this->_parent->getClientInfo($referenceApplicantId);

                            if (!empty($arrReferenceParentApplicantInfo)) {
                                $referenceApplicantName = $arrReferenceParentApplicantInfo['full_name'];
                                $referenceMemberTypeId  = $arrReferenceParentApplicantInfo['userType'];
                                $referenceApplicantType = $this->_parent->getMemberTypeNameById($referenceMemberTypeId);
                            }
                        }
                        if (empty($referenceApplicantName)) {
                            $booWrongValue = true;
                            $referenceText = 'Incorrect case id';
                        }
                    }
                }
            }

            if ($booField && !$booWrongValue) {
                $referenceReadableValue = '';
                if ($booCase) {
                    $referenceFieldId      = $this->getCompanyFieldIdByUniqueFieldId($referenceFieldName, $arrReferenceCaseInfo['company_id']);
                    $arrReferenceFieldInfo = $this->getFieldInfo($referenceFieldId, $arrReferenceCaseInfo['company_id'], $arrReferenceCaseInfo['client_type_id']);

                    if (!empty($arrReferenceFieldInfo) && isset($arrReferenceFieldInfo['field_id'])) {
                        $referenceFieldValue = $this->getFieldDataValue($referenceFieldId, $referenceCaseId);
                        list($referenceReadableValue,) = $this->getFieldReadableValue(
                            $arrReferenceCaseInfo['company_id'],
                            $this->_auth->getCurrentUserDivisionGroupId(),
                            $arrReferenceFieldInfo,
                            $referenceFieldValue
                        );
                    } else {
                        $booWrongValue = true;
                        $referenceText = 'Incorrect field name';
                    }
                } else {
                    $arrGroupedCompanyFields = $this->_parent->getApplicantFields()->getGroupedCompanyFields(
                        $arrReferenceApplicantInfo['company_id'],
                        $arrReferenceApplicantInfo['userType'],
                        $arrReferenceApplicantInfo['applicant_type_id'],
                        false
                    );

                    $arrReferenceFieldInfo = array();

                    foreach ($arrGroupedCompanyFields as $arrGroupInfo) {
                        if (!isset($arrGroupInfo['fields'])) {
                            continue;
                        }

                        foreach ($arrGroupInfo['fields'] as $arrCompanyFieldInfo) {
                            if ($arrCompanyFieldInfo['field_unique_id'] == $referenceFieldName) {
                                $referenceFieldId = $arrCompanyFieldInfo['field_id'];
                                if ($arrGroupInfo['group_contact_block'] == 'Y') {
                                    $referenceClientId = $this->_parent->getAssignedContact($referenceApplicantId, $arrGroupInfo['group_id']);
                                } else {
                                    $referenceClientId = $referenceApplicantId;
                                }

                                if (!empty($referenceClientId)) {
                                    $arrReferenceFieldInfo = $this->_parent->getApplicantFields()->getFieldInfo($referenceFieldId, $arrReferenceApplicantInfo['company_id']);

                                    if (!empty($arrReferenceFieldInfo)) {
                                        $referenceFieldValue = $this->_parent->getApplicantFields()->getFieldDataValue($referenceClientId, $referenceFieldId);
                                        list($referenceReadableValue,) = $this->getFieldReadableValue(
                                            $arrReferenceApplicantInfo['company_id'],
                                            $this->_auth->getCurrentUserDivisionGroupId(),
                                            $arrReferenceFieldInfo,
                                            $referenceFieldValue,
                                            true
                                        );
                                    } else {
                                        $booWrongValue = true;
                                        $referenceText = 'Incorrect field name';
                                    }
                                }
                                break 2;
                            }
                        }
                    }
                    if (empty($arrReferenceFieldInfo)) {
                        $booWrongValue = true;
                        $referenceText = 'Incorrect field name';
                    }
                }

                if (isset($referenceReadableValue)) {
                    $referenceText = $referenceReadableValue;
                }
            }

            $arrResult[] = array(
                'applicantId'   => $referenceApplicantId,
                'applicantName' => $referenceApplicantName,
                'applicantType' => $referenceApplicantType,
                'caseId'        => $referenceCaseId,
                'caseName'      => $referenceCaseName,
                'caseType'      => $referenceCaseTypeId,
                'reference'     => $referenceText,
                'value'         => $value,
                'booWrongValue' => $booWrongValue
            );
        }

        return $arrResult;
    }

    /**
     * Create/update field's details
     *
     * @param int $updateGroupId
     * @param int $updateFieldId
     * @param array $arrFieldInfo
     * @param array $arrFieldTypeInfo
     * @param array $arrFieldOptions
     * @param bool $booFieldWasEncrypted
     * @param bool $booFieldIsEncrypted
     * @param array $arrFieldDefaultAccess
     * @param array $arrAllFieldsInfoWithUniqueFieldId
     * @return array
     */
    public function saveField($updateGroupId, $updateFieldId, $arrFieldInfo, $arrFieldTypeInfo, $arrFieldOptions, $booFieldWasEncrypted, $booFieldIsEncrypted, $arrFieldDefaultAccess, $arrAllFieldsInfoWithUniqueFieldId)
    {
        $strError              = '';
        $booTransactionStarted = false;

        try {
            $this->_db2->getDriver()->getConnection()->beginTransaction();
            $booTransactionStarted = true;

            $companyId         = $arrFieldInfo['company_id'];
            $booDefaultCompany = $companyId == $this->_company->getDefaultCompanyId();
            $booNewField       = empty($updateFieldId);
            $fieldUseFullRow   = $arrFieldInfo['use_full_row'];
            $booWithOptions    = $arrFieldTypeInfo['booWithDefaultValue'] || $arrFieldTypeInfo['booWithImageSettings'] || $arrFieldTypeInfo['booWithComboOptions'] || $arrFieldTypeInfo['booAutoCalcField'];
            unset($arrFieldInfo['use_full_row']);

            if ($booNewField) {
                // This is a new field, create it
                $updateFieldId = $this->_db2->insert('client_form_fields', $arrFieldInfo);


                // Allow access for this field for company admin + default selected
                $this->allowFieldAccessForCompanyAdmin($companyId, $updateFieldId, true, $arrFieldDefaultAccess);

                // Create record in field orders table
                $this->placeFieldInGroup($updateGroupId, $updateFieldId, $fieldUseFullRow);
                $this->updateGroupAccessFromFields($companyId, [$updateGroupId]);

                // Now insert new default values
                $arrCompanyNewOptions = [];
                if ($booWithOptions) {
                    foreach ($arrFieldOptions as $arrFieldOptionInfo) {
                        $arrFieldOptionInfo['parent_option_id'] = $this->createDefaultOption($updateFieldId, null, $arrFieldOptionInfo['name'], $arrFieldOptionInfo['order']);

                        $arrCompanyNewOptions[] = $arrFieldOptionInfo;
                    }
                }

                // Check if this is 'Default' company - create this field for all companies, set access rights and after that place the field in the group
                if ($booDefaultCompany) {
                    $arrCompaniesIds = $this->_company->getAllCompanies(true);

                    // Create a mapping between the default role name -> access right,
                    // So later we'll identify the company role by name
                    $arrFieldDefaultAccessGrouped = [];
                    if (!empty($arrFieldDefaultAccess)) {
                        $arrDefaultRoles = $this->_roles->getCompanyRoles($companyId, 0, false, false, ['admin', 'user', 'individual_client', 'employer_client']);
                        foreach ($arrFieldDefaultAccess as $roleId => $roleAccess) {
                            foreach ($arrDefaultRoles as $arrDefaultRoleinfo) {
                                if ($roleId == $arrDefaultRoleinfo['role_id']) {
                                    $roleName = strtolower($arrDefaultRoleinfo['role_name']);

                                    $arrFieldDefaultAccessGrouped[$roleName] = $roleAccess;
                                    break;
                                }
                            }
                        }
                    }

                    $arrCreatedFields = [];
                    foreach ($arrCompaniesIds as $newFieldCompanyId) {
                        // Check if the field is already created for this company
                        $booAlreadyCreated = false;
                        foreach ($arrAllFieldsInfoWithUniqueFieldId as $arrCreatedFieldInfo) {
                            if ($arrCreatedFieldInfo['company_id'] == $newFieldCompanyId) {
                                $booAlreadyCreated = true;

                                // Link company's created field to the new default field
                                $this->_db2->update(
                                    'client_form_fields',
                                    ['parent_field_id' => $updateFieldId],
                                    ['field_id' => $arrCreatedFieldInfo['field_id']]
                                );

                                $arrCreatedFields[$newFieldCompanyId] = $arrCreatedFieldInfo['field_id'];
                                break;
                            }
                        }

                        if (!$booAlreadyCreated) {
                            // 1. Create a new field for the company
                            $companyNewFieldInfo                      = $arrFieldInfo;
                            $companyNewFieldInfo['company_id']        = $newFieldCompanyId;
                            $companyNewFieldInfo['parent_field_id']   = $updateFieldId;
                            $companyNewFieldInfo['company_field_id']  = $arrFieldInfo['company_field_id'];
                            $companyNewFieldInfo['sync_with_default'] = 'Yes';
                            unset($companyNewFieldInfo['field_id']);

                            $newFieldId = $this->_db2->insert('client_form_fields', $companyNewFieldInfo);

                            $arrCreatedFields[$newFieldCompanyId] = $newFieldId;


                            if (!empty($arrFieldDefaultAccessGrouped)) {
                                // 2. Allow access to this new field for roles that were selected under the 'Access Rights' tab
                                $arrCompanyFieldDefaultAccess = [];

                                $arrCompanyUserRoles = $this->_roles->getCompanyRoles($newFieldCompanyId, 0, false, false, ['admin', 'user', 'individual_client', 'employer_client']);
                                foreach ($arrCompanyUserRoles as $arrCompanyUserRoleInfo) {
                                    $roleName = strtolower($arrCompanyUserRoleInfo['role_name']);
                                    if (isset($arrFieldDefaultAccessGrouped[$roleName])) {
                                        $arrCompanyFieldDefaultAccess[$arrCompanyUserRoleInfo['role_id']] = $arrFieldDefaultAccessGrouped[$roleName];
                                    }
                                }

                                if (!empty($arrCompanyFieldDefaultAccess)) {
                                    $this->allowFieldAccessForCompanyAdmin($newFieldCompanyId, $newFieldId, false, $arrCompanyFieldDefaultAccess);
                                }
                            }

                            // 3. Insert default values
                            if ($booWithOptions) {
                                foreach ($arrCompanyNewOptions as $arrOptionInfo) {
                                    $this->createDefaultOption($newFieldId, $arrOptionInfo['parent_option_id'], $arrOptionInfo['name'], $arrOptionInfo['order']);
                                }
                            }
                        }
                    }

                    // 4. Get all children groups and place fields in these groups
                    $arrChildrenGroups = $this->getGroupsByParentId($updateGroupId);
                    if (is_array($arrChildrenGroups) && !empty($arrChildrenGroups)) {
                        foreach ($arrChildrenGroups as $childrenGroup) {
                            $this->placeFieldInGroup($childrenGroup['group_id'], $arrCreatedFields[$childrenGroup['company_id']], $fieldUseFullRow);
                            $this->updateGroupAccessFromFields($childrenGroup['company_id'], [$childrenGroup['group_id']]);
                        }
                    }
                }
            } else {
                $booOfficeField = $arrFieldInfo['company_field_id'] == 'division';
                unset($arrFieldInfo['company_id'], $arrFieldInfo['company_field_id']);

                // If field encryption setting was changed - we need to update data, i.e.:
                // * if data was already encrypted - we need decrypt and save it
                // * if data wasn't encrypted - we need encrypt and save it
                if ($arrFieldTypeInfo['booCanBeEncrypted'] && $booFieldWasEncrypted != $booFieldIsEncrypted) {
                    $arrFieldsIds = array($updateFieldId);

                    if ($booDefaultCompany) {
                        $arrChildrenFieldsIds = $this->getFieldsByParentId($updateFieldId, true);
                        if (is_array($arrChildrenFieldsIds) && !empty($arrChildrenFieldsIds)) {
                            $arrFieldsIds = $arrFieldsIds + $arrChildrenFieldsIds;
                        }
                    }
                    $arrSavedData = $this->getFieldData($arrFieldsIds, 0, false);

                    foreach ($arrSavedData as $arrSavedDataRow) {
                        $updatedValue = $booFieldWasEncrypted ?
                            $this->_encryption->decode($arrSavedDataRow['value']) :
                            $this->_encryption->encode($arrSavedDataRow['value']);

                        $this->_db2->update(
                            'client_form_data',
                            ['value' => $updatedValue],
                            [
                                'member_id' => (int)$arrSavedDataRow['member_id'],
                                'field_id'  => (int)$arrSavedDataRow['field_id']
                            ]
                        );
                    }
                }

                if ($booOfficeField) {
                    // Update this specific field info
                    $oCompanyDivisions = $this->_company->getCompanyDivisions();

                    $arrDivisionFieldsToUpdate   = array();
                    $arrDivisionFieldsToUpdate[] = array(
                        'company_id'        => $companyId,
                        'division_group_id' => $oCompanyDivisions->getCompanyMainDivisionGroupId($companyId)
                    );

                    if ($booDefaultCompany) {
                        $arrChildrenDivisionFieldsToUpdate = $this->getFieldsByParentId($updateFieldId);
                        if (is_array($arrChildrenDivisionFieldsToUpdate) && !empty($arrChildrenDivisionFieldsToUpdate)) {
                            foreach ($arrChildrenDivisionFieldsToUpdate as $childrenField) {
                                $arrDivisionFieldsToUpdate[] = array(
                                    'company_id'        => $childrenField['company_id'],
                                    'division_group_id' => $oCompanyDivisions->getCompanyMainDivisionGroupId($childrenField['company_id'])
                                );
                            }
                        }
                    }

                    foreach ($arrDivisionFieldsToUpdate as $divisionFieldToUpdate) {
                        $arrOptionsIds = array();
                        if (!empty($arrFieldOptions) && is_array($arrFieldOptions)) {
                            foreach ($arrFieldOptions as $arrOptionInfo) {
                                $arrOptionsIds[] = $oCompanyDivisions->createUpdateDivision(
                                    $divisionFieldToUpdate['company_id'],
                                    $divisionFieldToUpdate['division_group_id'],
                                    $arrOptionInfo['id'],
                                    $arrOptionInfo['name'],
                                    $arrOptionInfo['order']
                                );
                            }
                        }

                        $oCompanyDivisions->deleteCompanyDivisions($divisionFieldToUpdate['company_id'], $divisionFieldToUpdate['division_group_id'], $arrOptionsIds);
                    }
                } else {
                    // Update field
                    $this->_db2->update('client_form_fields', $arrFieldInfo, ['field_id' => $updateFieldId]);

                    $this->_db2->update(
                        'client_form_order',
                        ['use_full_row' => $fieldUseFullRow ? 'Y' : 'N'],
                        [
                            'group_id' => $updateGroupId,
                            'field_id' => $updateFieldId
                        ]
                    );

                    // Update/Create new default values
                    if ($booWithOptions) {
                        $this->updateFieldDefaultOptions($booDefaultCompany, $updateFieldId, $arrFieldOptions, !$arrFieldTypeInfo['booWithComboOptions']);
                    }

                    if ($booDefaultCompany) {
                        $arrChildrenFieldsToUpdate = $this->getFieldsByParentId($updateFieldId);
                        $arrChildrenGroupsToUpdate = $this->getGroupsByParentId($updateGroupId);

                        foreach ($arrChildrenFieldsToUpdate as $childrenField) {
                            $arrCompanyFieldInfo = $arrFieldInfo;
                            if ($childrenField['sync_with_default'] === 'No') {
                                continue;
                            } elseif ($childrenField['sync_with_default'] === 'Label') {
                                // Don't change the label
                                unset($arrCompanyFieldInfo['label']);
                            }
                            unset($arrCompanyFieldInfo['sync_with_default']);

                            $this->_db2->update('client_form_fields', $arrCompanyFieldInfo, ['field_id' => $childrenField['field_id']]);

                            foreach ($arrChildrenGroupsToUpdate as $arrGroupInfo) {
                                if ($arrGroupInfo['parent_group_id'] == $updateGroupId && $childrenField['company_id'] == $arrGroupInfo['company_id']) {
                                    $this->_db2->update(
                                        'client_form_order',
                                        [
                                            'use_full_row' => $fieldUseFullRow ? 'Y' : 'N'
                                        ],
                                        [
                                            'field_id' => $childrenField['field_id'],
                                            'group_id' => $arrGroupInfo['group_id']
                                        ]
                                    );
                                }
                            }
                        }
                    }
                }
            }

            $this->updateDefaultAccessRights($booNewField, $companyId, $updateFieldId, $arrFieldDefaultAccess);

            if (empty($strError)) {
                $this->_db2->getDriver()->getConnection()->commit();
                $booTransactionStarted = false;
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError) && $booTransactionStarted) {
            $this->_db2->getDriver()->getConnection()->rollback();
        }

        return array($strError, $updateFieldId);
    }

    /**
     * Get a list of linked options to the parent option
     *
     * @param int $defaultOptionParentId
     * @return array
     */
    public function getDefaultOptionsByParentId($defaultOptionParentId)
    {
        $select = (new Select())
            ->from('client_form_default')
            ->columns(array('field_id', 'form_default_id'))
            ->where(['parent_form_default_id' => $defaultOptionParentId]);

        return $this->_db2->fetchAll($select);
    }

    /**
     * Create field's option record
     *
     * @param int $fieldId
     * @param int|null $parentOptionId
     * @param string $optionValue
     * @param int $optionOrder
     * @return int created option id
     */
    public function createDefaultOption($fieldId, $parentOptionId, $optionValue, $optionOrder)
    {
        $arrFieldsDefaultInsert = [
            'field_id'               => $fieldId,
            'parent_form_default_id' => $parentOptionId,
            'value'                  => $optionValue,
            'order'                  => $optionOrder,
        ];

        return $this->_db2->insert('client_form_default', $arrFieldsDefaultInsert);
    }

    /**
     * Update field option record(s)
     *
     * @param int|array $optionId
     * @param array $arrOptionUpdate
     * @return int|array passed id(s)
     */
    public function updateDefaultOptionInfo($optionId, $arrOptionUpdate)
    {
        $this->_db2->update('client_form_default', $arrOptionUpdate, ['form_default_id' => $optionId]);

        return $optionId;
    }

    /**
     * Update field option record(s)
     *
     * @param int|array $optionId
     * @param string $optionValue
     * @param int $optionOrder
     * @return int|array passed id(s)
     */
    public function updateDefaultOption($optionId, $optionValue, $optionOrder)
    {
        $arrOptionUpdate = [
            'value' => $optionValue,
            'order' => $optionOrder,
        ];

        return $this->updateDefaultOptionInfo($optionId, $arrOptionUpdate);
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
            $count = $this->_db2->delete('client_form_default', ['form_default_id' => $arrDeleteOptionsIds]);
        }

        return $count;
    }

    /**
     * Mark field option record(s) as deleted / not deleted
     *
     * @param int|array $arrOptionIds
     * @param bool $booAsDeleted
     */
    public function markDefaultOptionsAsDeleted($arrOptionIds, $booAsDeleted)
    {
        $arrOptionUpdate = [
            'deleted' => $booAsDeleted ? 'Y' : 'N',
        ];

        $this->updateDefaultOptionInfo($arrOptionIds, $arrOptionUpdate);
    }

    /**
     * Update field's option records
     *
     * @param bool $booDefaultCompany true if this is a default company and changes must be applied to all other companies too
     * @param int $fieldId
     * @param array $arrOptions
     * @param bool $booInsertAndUpdateOnly
     * if true - we'll delete options and create the correct list again (should be used for fields with default value, images, autocalculated fields).
     * if false - we'll create/update records based on their provided ids. If deleted records are used in case's profile - mark them as deleted (should be used for combos, radios).
     * @return bool true if changes were done/saved or false if already saved options are the same as the new options
     */
    public function updateFieldDefaultOptions($booDefaultCompany, $fieldId, $arrOptions, $booInsertAndUpdateOnly = false)
    {
        $arrSavedOptionsIds       = [];
        $arrSavedOptionsFormatted = [];
        $arrSavedOptions          = $this->getFieldOptions($fieldId);
        foreach ($arrSavedOptions as $arrSavedOptionInfo) {
            $arrSavedOptionsIds[] = $arrSavedOptionInfo['option_id'];

            // Prepare the same format as we receive
            if ($booInsertAndUpdateOnly) {
                $arrSavedOptionsFormatted[] = [
                    'name'  => $arrSavedOptionInfo['option_name'],
                    'order' => $arrSavedOptionInfo['option_order'],
                ];
            } else {
                $arrSavedOptionsFormatted[] = [
                    'id'    => $arrSavedOptionInfo['option_id'],
                    'name'  => $arrSavedOptionInfo['option_name'],
                    'order' => $arrSavedOptionInfo['option_order'],
                ];
            }
        }

        // Don't try to update if no changes were made
        if ($arrSavedOptionsFormatted == $arrOptions) {
            return false;
        }

        $arrChildFieldsIdsToUpdate = [];
        if ($booDefaultCompany) {
            // Get the list of fields we want to update (ignore if syncing is turned off)
            $arrChildrenFieldsToUpdate = $this->getFieldsByParentId($fieldId);
            foreach ($arrChildrenFieldsToUpdate as $childrenField) {
                if ($childrenField['sync_with_default'] === 'No') {
                    continue;
                }

                $arrChildFieldsIdsToUpdate[] = $childrenField['field_id'];
            }
        }

        if ($booInsertAndUpdateOnly) {
            $arrFieldsToUpdate = [$fieldId];
            if ($booDefaultCompany) {
                foreach ($arrChildFieldsIdsToUpdate as $childFieldId) {
                    $arrFieldsToUpdate[] = $childFieldId;

                    $arrSavedOptions = $this->getFieldOptions($childFieldId);
                    foreach ($arrSavedOptions as $arrSavedOptionInfo) {
                        $arrSavedOptionsIds[] = $arrSavedOptionInfo['option_id'];
                    }
                }
            }

            $this->deleteDefaultOptions($arrSavedOptionsIds);
            foreach ($arrOptions as $arrOptionInfo) {
                foreach ($arrFieldsToUpdate as $fieldIdToUpdate) {
                    $this->createDefaultOption($fieldIdToUpdate, null, $arrOptionInfo['name'], $arrOptionInfo['order']);
                }
            }
        } else {
            $arrOptionsIds = [];
            foreach ($arrOptions as $arrOptionInfo) {
                if (empty($arrOptionInfo['id'])) {
                    // A new option - create it
                    $createdOptionId = $this->createDefaultOption($fieldId, null, $arrOptionInfo['name'], $arrOptionInfo['order']);
                    $arrOptionsIds[] = $createdOptionId;

                    if ($booDefaultCompany) {
                        foreach ($arrChildFieldsIdsToUpdate as $childFieldId) {
                            $this->createDefaultOption($childFieldId, $createdOptionId, $arrOptionInfo['name'], $arrOptionInfo['order']);
                        }
                    }
                } else {
                    // Not a new option - update it
                    $arrOptionsIds[]      = $arrOptionInfo['id'];
                    $arrOptionIdsToUpdate = [$arrOptionInfo['id']];

                    if ($booDefaultCompany) {
                        $arrChildOptions = $this->getDefaultOptionsByParentId($arrOptionInfo['id']);
                        foreach ($arrChildOptions as $arrChildOptionInfo) {
                            if (in_array($arrChildOptionInfo['field_id'], $arrChildFieldsIdsToUpdate)) {
                                $arrOptionIdsToUpdate[] = $arrChildOptionInfo['form_default_id'];
                            }
                        }
                    }

                    $this->updateDefaultOption($arrOptionIdsToUpdate, $arrOptionInfo['name'], $arrOptionInfo['order']);
                }
            }


            $arrDeletedOptions       = array_diff($arrSavedOptionsIds, $arrOptionsIds);
            $arrMarkAsDeletedOptions = [];
            $arrDeleteOptions        = [];
            if (!empty($arrDeletedOptions)) {
                if ($booDefaultCompany) {
                    // All options for default company are not used, we want to delete them
                    $arrDeleteOptions = $arrDeletedOptions;

                    // Check if deleted option is used in some companies
                    // if yes - mark as deleted that option and remove the link
                    // if no - simply delete
                    foreach ($arrDeleteOptions as $deletedOptionId) {
                        $arrChildOptions = $this->getDefaultOptionsByParentId($deletedOptionId);

                        $arrRemoveLinkForOptions = [];
                        foreach ($arrChildOptions as $arrChildOptionInfo) {
                            if (in_array($arrChildOptionInfo['field_id'], $arrChildFieldsIdsToUpdate)) {
                                if ($this->isCaseFieldOptionUsed($arrChildOptionInfo['field_id'], $arrChildOptionInfo['form_default_id'])) {
                                    $arrMarkAsDeletedOptions[] = $arrChildOptionInfo['form_default_id'];
                                    $arrRemoveLinkForOptions[] = $arrChildOptionInfo['form_default_id'];
                                } else {
                                    $arrDeleteOptions[] = $arrChildOptionInfo['form_default_id'];
                                }
                            }
                        }

                        // Remove link, so parent records can be safely deleted
                        if (!empty($arrRemoveLinkForOptions)) {
                            $this->updateDefaultOptionInfo($arrRemoveLinkForOptions, ['parent_form_default_id' => null]);
                        }
                    }
                } else {
                    foreach ($arrDeletedOptions as $deletedOptionId) {
                        if ($this->isCaseFieldOptionUsed($fieldId, $deletedOptionId)) {
                            $arrMarkAsDeletedOptions[] = $deletedOptionId;
                        } else {
                            $arrDeleteOptions[] = $deletedOptionId;
                        }
                    }
                }
            }

            if (!empty($arrMarkAsDeletedOptions)) {
                $this->markDefaultOptionsAsDeleted($arrMarkAsDeletedOptions, true);
            }

            if (!empty($arrDeleteOptions)) {
                $this->deleteDefaultOptions($arrDeleteOptions);
            }
        }

        return true;
    }

    /**
     * Check if case field's option is used (saved in the case's profile)
     *
     * @param int $fieldId
     * @param int $deletedOptionId
     * @return bool
     */
    public function isCaseFieldOptionUsed($fieldId, $deletedOptionId)
    {
        $select = (new Select())
            ->from('client_form_data')
            ->columns(['member_id'])
            ->where([
                'field_id' => (int)$fieldId,
                new PredicateExpression('FIND_IN_SET(?, value) > 0', $deletedOptionId)
            ]);

        $arrCaseIds = $this->_db2->fetchCol($select);

        return !empty($arrCaseIds);
    }

    /**
     * Clear all "marked as deleted" options if they are not used anymore
     */
    public function clearAllDeletedNotUsedOptions()
    {
        $select = (new Select())
            ->from('client_form_default')
            ->columns(array('field_id', 'form_default_id'))
            ->where(['deleted' => 'Y']);

        $arrDefaultDeletedOptions = $this->_db2->fetchAll($select);

        $arrDeleteOptions = [];
        foreach ($arrDefaultDeletedOptions as $arrDefaultDeletedOptionInfo) {
            if (!$this->isCaseFieldOptionUsed($arrDefaultDeletedOptionInfo['field_id'], $arrDefaultDeletedOptionInfo['form_default_id'])) {
                $arrDeleteOptions[] = $arrDefaultDeletedOptionInfo['form_default_id'];
            }
        }

        $this->deleteDefaultOptions($arrDeleteOptions);
    }

    /**
     * Create/update default access rights for a specific field
     *
     * @param bool $booNewField
     * @param int $companyId
     * @param int $caseFieldId
     * @param array $arrFieldDefaultAccess
     */
    public function updateDefaultAccessRights($booNewField, $companyId, $caseFieldId, $arrFieldDefaultAccess)
    {
        // Check if we really changed something, if not - do nothing
        $booMakeChanges = true;
        if (!$booNewField) {
            $arrFieldDefaultAccessRightsSorted = [];
            $arrFieldDefaultAccessRightsSaved  = $this->getFieldDefaultAccessRights($companyId, $caseFieldId);
            foreach ($arrFieldDefaultAccessRightsSaved as $arrFieldDefaultAccessRightInfo) {
                $roleId = empty($arrFieldDefaultAccessRightInfo['role_id']) ? 0 : $arrFieldDefaultAccessRightInfo['role_id'];

                $arrFieldDefaultAccessRightsSorted[$roleId] = $arrFieldDefaultAccessRightInfo['access'];
            }
            ksort($arrFieldDefaultAccessRightsSorted);

            // Remove empty/no access records
            $arrNewFieldDefaultAccessRightsSorted = [];
            foreach ($arrFieldDefaultAccess as $roleId => $roleAccess) {
                if (!empty($roleAccess)) {
                    $arrNewFieldDefaultAccessRightsSorted[$roleId] = $roleAccess;
                }
            }

            if ($arrFieldDefaultAccessRightsSorted == $arrNewFieldDefaultAccessRightsSorted) {
                $booMakeChanges = false;
            }
        }

        if (!$booMakeChanges) {
            return;
        }

        $this->_db2->delete('client_form_fields_access_default', ['client_field_id' => (int)$caseFieldId]);


        $arrRoleIds = array_filter(array_keys($arrFieldDefaultAccess));
        if (!empty($arrRoleIds)) {
            $this->_db2->delete(
                'client_form_field_access',
                [
                    'role_id'  => $arrRoleIds,
                    'field_id' => (int)$caseFieldId
                ]
            );
        }

        foreach ($arrFieldDefaultAccess as $roleId => $access) {
            if (empty($access)) {
                continue;
            }

            // Update default access rights for the role
            $arrNewAccessRights = array(
                'client_field_id' => $caseFieldId,
                'role_id'         => empty($roleId) ? null : $roleId,
                'access'          => $access,
                'updated_on'      => date('Y-m-d H:i:s'),
            );

            $this->_db2->insert('client_form_fields_access_default', $arrNewAccessRights);


            // Update access rights for the role
            if (!empty($roleId)) {
                $arrFieldAccess = array(
                    'role_id'  => $roleId,
                    'field_id' => (int)$caseFieldId,
                    'status'   => $access,
                );

                $this->_db2->insert('client_form_field_access', $arrFieldAccess);
            }
        }
    }

    /**
     * Update default access rights
     *
     * @param $arrAccessRights
     */
    public function updateDefaultAccessRightsForRole($arrAccessRights)
    {
        foreach ($arrAccessRights as $roleId => $arrFieldsAccess) {
            $this->_db2->delete('client_form_fields_access_default', ['role_id' => (int)$roleId]);

            $arrCreatedUpdatedFields = array();
            foreach ($arrFieldsAccess as $fieldId => $accessLevel) {
                if (empty($accessLevel)) {
                    continue;
                }

                if (!in_array($fieldId, $arrCreatedUpdatedFields)) {
                    $this->_db2->insert(
                        'client_form_fields_access_default',
                        [
                            'client_field_id' => $fieldId,
                            'role_id'         => $roleId,
                            'access'          => $accessLevel,
                            'updated_on'      => date('Y-m-d H:i:s'),
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
     * @param int $caseFieldId
     * @param bool $booLoadLatest
     * @return array
     */
    public function getFieldDefaultAccessRights($companyId, $caseFieldId, $booLoadLatest = true)
    {
        // For the new field - try to load last saved field's settings
        if (empty($caseFieldId) && $booLoadLatest) {
            $arrCompanyRoles = $this->_roles->getCompanyRoles($companyId, 0, false, true, ['admin', 'user', 'individual_client', 'employer_client']);

            if (!empty($arrCompanyRoles)) {
                $select = (new Select())
                    ->from('client_form_fields_access_default')
                    ->columns(['client_field_id'])
                    ->where(['role_id' => $arrCompanyRoles])
                    ->order('updated_on DESC')
                    ->limit(1);

                $caseFieldId = $this->_db2->fetchOne($select);
            }
        }

        $arrFieldDefaultAccessRights = array();
        if (!empty($caseFieldId)) {
            $select = (new Select())
                ->from('client_form_fields_access_default')
                ->columns(array('role_id', 'access'))
                ->where(['client_field_id' => (int)$caseFieldId]);

            $arrFieldDefaultAccessRights = $this->_db2->fetchAll($select);
        }

        return $arrFieldDefaultAccessRights;
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
        if (empty($roleId)) {
            $arrFieldsAccessRules = $this->getDefaultAccessRightsForNewRole($companyId);
        } else {
            // Load Fields Level Access
            $select = (new Select())
                ->from(array('f' => 'client_form_field_access'))
                ->columns(array('field_id', 'status'))
                ->where(['f.role_id' => $roleId]);

            $arrFieldsAccessRules = $this->_db2->fetchAll($select);
        }

        return $arrFieldsAccessRules;
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

        $arrAllCompanyClientFieldsIds = $this->getCompanyFields($companyId, true);

        if (!empty($arrAllCompanyClientFieldsIds)) {
            $select = (new Select())
                ->from('client_form_fields_access_default')
                ->columns(array('field_id' => 'client_field_id', 'status' => 'access'))
                ->where([(new Where())->isNull('role_id')])
                ->where(['client_field_id' => $arrAllCompanyClientFieldsIds]);

            $arrGroupedResult = $this->_db2->fetchAll($select);
        }

        return $arrGroupedResult;
    }

    /**
     * Create default access rights for all fields for all roles
     * @param int|null $companyId - if provided, filter/load for this company only
     */
    public function createDefaultFieldsAccessForCompany($companyId = null)
    {
        // ******* APPLICANTS' fields default access
        $select = (new Select())
            ->from(array('fa' => 'applicant_form_fields_access'))
            ->columns(array('role_id', 'applicant_field_id', 'status'))
            ->join(array('r' => 'acl_roles'), 'r.role_id = fa.role_id', 'role_regTime');

        if (!empty($companyId)) {
            $select->where->equalTo('r.company_id', (int)$companyId);
        }

        $arrSaved = $this->_db2->fetchAll($select);

        $arrAlreadySaved = array();
        foreach ($arrSaved as $arrSavedInfo) {
            if (isset($arrAlreadySaved[$arrSavedInfo['role_id']][$arrSavedInfo['applicant_field_id']])) {
                continue;
            }
            $arrAlreadySaved[$arrSavedInfo['role_id']][$arrSavedInfo['applicant_field_id']] = '';

            $this->_db2->insert(
                'applicant_form_fields_access_default',
                [
                    'applicant_field_id' => (int)$arrSavedInfo['applicant_field_id'],
                    'role_id'            => (int)$arrSavedInfo['role_id'],
                    'access'             => $arrSavedInfo['status'],
                    'updated_on'         => date('Y-m-d H:i:s', $arrSavedInfo['role_regTime'])
                ],
                null,
                false
            );
        }


        // ******* CASES' fields default access
        $select = (new Select())
            ->from(array('fa' => 'client_form_field_access'))
            ->columns(array('role_id', 'field_id', 'status'))
            ->join(array('r' => 'acl_roles'), 'r.role_id = fa.role_id', 'role_regTime');

        if (!empty($companyId)) {
            $select->where->equalTo('r.company_id', (int)$companyId);
        }

        $arrSaved = $this->_db2->fetchAll($select);

        $arrAlreadySaved = array();
        foreach ($arrSaved as $arrSavedInfo) {
            if (isset($arrAlreadySaved[$arrSavedInfo['role_id']][$arrSavedInfo['field_id']])) {
                continue;
            }
            $arrAlreadySaved[$arrSavedInfo['role_id']][$arrSavedInfo['field_id']] = '';

            $this->_db2->insert(
                'client_form_fields_access_default',
                [
                    'client_field_id' => (int)$arrSavedInfo['field_id'],
                    'role_id'         => (int)$arrSavedInfo['role_id'],
                    'access'          => $arrSavedInfo['status'],
                    'updated_on'      => date('Y-m-d H:i:s', $arrSavedInfo['role_regTime'])
                ],
                null,
                false
            );
        }
    }

    /**
     * Load a list if "child" groups for a specific "parent" group(s)
     *
     * @param int|array $parentGroupId
     * @return array
     */
    public function getGroupsByParentId($parentGroupId)
    {
        $select = (new Select())
            ->from('client_form_groups')
            ->where(['parent_group_id' => $parentGroupId]);

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load a list if "child" fields for a specific "parent" field
     *
     * @param int $parentFieldId
     * @param bool $booIdsOnly true to load ids only or false to load all details
     * @return array
     */
    public function getFieldsByParentId($parentFieldId, $booIdsOnly = false)
    {
        $select = (new Select())
            ->from('client_form_fields')
            ->columns($booIdsOnly ? ['field_id'] : [Select::SQL_STAR])
            ->where(['parent_field_id' => (int)$parentFieldId]);

        return $booIdsOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
    }

}
