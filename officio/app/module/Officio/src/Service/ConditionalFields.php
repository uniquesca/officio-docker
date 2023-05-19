<?php

namespace Officio\Service;

use Clients\Service\Clients;
use Clients\Service\Clients\Fields;
use Exception;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ConditionalFields extends BaseService
{

    public function initAdditionalServices(array $services)
    {
    }

    /**
     * Get field condition info
     *
     * @param int $conditionId
     * @return array
     */
    public function getConditionInfo($conditionId)
    {
        $select = (new Select())
            ->from(array('fc' => 'client_form_field_conditions'))
            ->where([
                'fc.field_condition_id' => (int)$conditionId
            ]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load a list if "child" conditions for a specific "parent" field condition grouped by company id
     *
     * @param int $parentFieldConditionId
     * @return array
     */
    public function getConditionsByParentId($parentFieldConditionId)
    {
        $select = (new Select())
            ->from(array('fc' => 'client_form_field_conditions'))
            ->columns(['field_condition_id'])
            ->join(array('ct' => 'client_types'), 'ct.client_type_id = fc.client_type_id', ['company_id'])
            ->where(['fc.parent_field_condition_id' => (int)$parentFieldConditionId]);

        $arrSavedConditions = $this->_db2->fetchAll($select);

        $arrGroupedConditions = [];
        foreach ($arrSavedConditions as $arrSavedConditionInfo) {
            $arrGroupedConditions[$arrSavedConditionInfo['company_id']][] = $arrSavedConditionInfo['field_condition_id'];
        }

        return $arrGroupedConditions;
    }

    /**
     * Load list of field conditions for a specific case template and field
     *
     * @param int $caseTemplateId
     * @param int $fieldId
     * @param array $arrFieldOptions
     * @return array
     */
    public function getFieldConditions($caseTemplateId, $fieldId, $arrFieldOptions)
    {
        $arrConditions = array();

        try {
            if (!empty($caseTemplateId) && !empty($fieldId)) {
                $select = (new Select())
                    ->from(array('fc' => 'client_form_field_conditions'))
                    ->join(array('f' => 'client_form_fields'), 'f.field_id = fc.field_id', [], Select::JOIN_LEFT_OUTER)
                    ->join(array('ft' => 'field_types'), 'f.type = ft.field_type_id', 'field_type_text_id', Select::JOIN_LEFT_OUTER)
                    ->where([
                        'fc.client_type_id' => (int)$caseTemplateId,
                        'fc.field_id'       => (int)$fieldId
                    ]);

                $arrConditions = $this->_db2->fetchAll($select);

                foreach ($arrConditions as $key => $arrConditionInfo) {
                    $fieldOptionId    = 0;
                    $fieldOptionLabel = $this->_tr->translate('-- NOT SELECTED --');
                    switch ($arrConditionInfo['field_type_text_id']) {
                        case 'checkbox':
                            if ($arrConditionInfo['field_option_value'] == 'checked') {
                                $fieldOptionId    = 'checked';
                                $fieldOptionLabel = $this->_tr->translate('Checked');
                            } else {
                                $fieldOptionLabel = $this->_tr->translate('Unchecked');
                            }
                            break;

                        default:
                            if (strlen($arrConditionInfo['field_option_value'] ?? '')) {
                                $fieldOptionId         = $arrConditionInfo['field_option_value'] ?? '';
                                $arrSavedOptions       = explode(';', $fieldOptionId);
                                $arrSavedOptionsLabels = [];
                                foreach ($arrSavedOptions as $arrSavedOptionId) {
                                    if (empty($arrSavedOptionId)) {
                                        $arrSavedOptionsLabels[] = $this->_tr->translate('-- NOT SELECTED --');
                                    } else {
                                        foreach ($arrFieldOptions as $arrFieldOptionInfo) {
                                            if ($arrFieldOptionInfo['option_id'] == $arrSavedOptionId) {
                                                $arrSavedOptionsLabels[] = $arrFieldOptionInfo['option_name'];
                                            }
                                        }
                                    }
                                }

                                $fieldOptionLabel = implode('<br>', $arrSavedOptionsLabels);
                            }
                            break;
                    }

                    $arrConditions[$key]['field_option_id']    = $fieldOptionId;
                    $arrConditions[$key]['field_option_label'] = $fieldOptionLabel;

                    unset($arrConditions[$key]['field_option_value']);

                    $arrConditionHiddenFields = $this->getConditionHiddenFields($caseTemplateId, $arrConditionInfo['field_condition_id']);

                    $arrGroupedFields   = array();
                    $arrHiddenFieldsIds = array();
                    foreach ($arrConditionHiddenFields as $arrConditionHiddenFieldInfo) {
                        $arrHiddenFieldsIds[] = $arrConditionHiddenFieldInfo['field_id'];

                        $arrGroupedFields[$arrConditionHiddenFieldInfo['title']][] = $arrConditionHiddenFieldInfo['label'];
                    }
                    $arrConditions[$key]['field_condition_hidden_fields'] = $arrHiddenFieldsIds;

                    $arrConditions[$key]['field_condition_hidden_groups_and_fields'] = '';
                    foreach ($arrGroupedFields as $groupLabel => $arrFields) {
                        $arrConditions[$key]['field_condition_hidden_groups_and_fields'] .= sprintf(
                            '%s: %s<br>',
                            $groupLabel,
                            implode(', ', $arrFields)
                        );
                    }

                    $arrConditionHiddenGroups = $this->getConditionHiddenGroups($caseTemplateId, $arrConditionInfo['field_condition_id']);

                    $arrHiddenGroupsIds = array();
                    foreach ($arrConditionHiddenGroups as $arrGroupInfo) {
                        $arrHiddenGroupsIds[] = $arrGroupInfo['group_id'];

                        $arrConditions[$key]['field_condition_hidden_groups_and_fields'] .= sprintf(
                            '%s %s<br>',
                            $arrGroupInfo['title'],
                            $this->_tr->translate('(entire group)')
                        );
                    }
                    $arrConditions[$key]['field_condition_hidden_groups'] = $arrHiddenGroupsIds;
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrConditions;
    }

    /**
     * Create/update fields conditions
     *
     * @param Company $oCompany
     * @param Clients $oClients
     * @param Fields $oFields
     * @param int $companyId
     * @param int $caseTemplateId
     * @param array $arrFieldInfo
     * @param array $arrConditions
     * @return string
     */
    public function saveConditionsInBatch($oCompany, $oClients, $oFields, $companyId, $caseTemplateId, $arrFieldInfo, $arrConditions)
    {
        $strError              = '';
        $booTransactionStarted = false;

        try {
            $booDefaultCompany = $companyId == $oCompany->getDefaultCompanyId();

            // Preload companies info only once
            $arrCaseTemplates              = [];
            $arrAllCompaniesFields         = [];
            $arrAllCompaniesDivisions      = [];
            $arrAllCompaniesOptionsGrouped = [];
            if ($booDefaultCompany) {
                $arrCaseTemplates      = $oClients->getCaseTemplates()->getCaseTemplatesByParentId($caseTemplateId);
                $arrAllCompaniesFields = $oFields->getFieldsByParentId($arrFieldInfo['field_id']);

                $arrAllFieldsIds = [];
                foreach ($arrAllCompaniesFields as $arrCompanyFieldInfo) {
                    $arrAllFieldsIds[] = $arrCompanyFieldInfo['field_id'];
                }

                $arrAllCompaniesOptions = $oFields->getFieldsOptions($arrAllFieldsIds);
                foreach ($arrAllCompaniesOptions as $arrAllCompaniesOptionInfo) {
                    $arrAllCompaniesOptionsGrouped[$arrAllCompaniesOptionInfo['field_id']][] = $arrAllCompaniesOptionInfo;
                }

                if ($arrFieldInfo['company_field_id'] == 'divisions') {
                    $arrAllCompaniesDivisions = $oCompany->getCompanyDivisions()->getAllCompaniesDivisions();
                }
            }

            $this->_db2->getDriver()->getConnection()->beginTransaction();
            $booTransactionStarted = true;

            foreach ($arrConditions as $arrConditionInfo) {
                $fieldOptionValue = $arrConditionInfo['field_option_value'];
                $fieldOptionLabel = $arrConditionInfo['field_option_label'];
                $arrHiddenGroups  = $arrConditionInfo['hidden_groups'];
                $arrHiddenFields  = $arrConditionInfo['hidden_fields'];

                $conditionId = $this->saveFieldConditions(
                    $arrConditionInfo['condition_id'],
                    null,
                    $caseTemplateId,
                    $arrFieldInfo['field_id'],
                    $fieldOptionValue,
                    $arrHiddenGroups,
                    $arrHiddenFields
                );

                if (empty($conditionId)) {
                    $strError = $this->_tr->translate('Internal error.');
                }

                if (empty($strError) && $booDefaultCompany) {
                    $arrGroupedInfoByCompanies = [];

                    foreach ($arrCaseTemplates as $arrCaseTemplateInfo) {
                        $arrGroupedInfoByCompanies[$arrCaseTemplateInfo['company_id']]['client_type_id'] = $arrCaseTemplateInfo['client_type_id'];
                    }

                    foreach ($arrAllCompaniesFields as $arrCompanyFieldInfo) {
                        $arrGroupedInfoByCompanies[$arrCompanyFieldInfo['company_id']]['field_id'] = $arrCompanyFieldInfo['field_id'];
                        $arrGroupedInfoByCompanies[$arrCompanyFieldInfo['company_id']]['options']  = null;
                    }

                    $booUseOptionAsIs   = is_null($fieldOptionValue) || $arrFieldInfo['field_type_text_id'] === 'checkbox';
                    $arrAllCompaniesIds = array_keys($arrGroupedInfoByCompanies);

                    if (!$booUseOptionAsIs) {
                        if (empty($fieldOptionValue)) {
                            foreach ($arrAllCompaniesIds as $companyId) {
                                $arrGroupedInfoByCompanies[$companyId]['options'][] = $fieldOptionValue;
                            }
                        } else {
                            switch ($arrFieldInfo['company_field_id']) {
                                case 'divisions':
                                    foreach ($arrAllCompaniesDivisions as $companyId => $arrDivisionInfo) {
                                        if ($fieldOptionLabel == $arrDivisionInfo['name']) {
                                            $arrGroupedInfoByCompanies[$companyId]['options'][] = $arrDivisionInfo['division_id'];
                                        }
                                    }
                                    break;

                                case 'categories':
                                    $arrAllCompaniesCaseCategories = $oClients->getCaseCategories()->getCaseCategoriesByParentId($fieldOptionValue);
                                    foreach ($arrAllCompaniesCaseCategories as $arrCompanyCaseCategoryInfo) {
                                        $arrGroupedInfoByCompanies[$arrCompanyCaseCategoryInfo['company_id']]['options'][] = $arrCompanyCaseCategoryInfo['client_category_id'];
                                    }
                                    break;

                                case 'file_status':
                                    $arrAllCompaniesCaseStatuses = $oClients->getCaseStatuses()->getCaseStatusesByParentId($fieldOptionValue);
                                    foreach ($arrAllCompaniesCaseStatuses as $arrCompanyCaseStatusInfo) {
                                        $arrGroupedInfoByCompanies[$arrCompanyCaseStatusInfo['company_id']]['options'][] = $arrCompanyCaseStatusInfo['client_status_id'];
                                    }
                                    break;

                                default:
                                    foreach ($arrAllCompaniesFields as $arrCompanyFieldInfo) {
                                        $arrCompanyOptions = $arrAllCompaniesOptionsGrouped[$arrCompanyFieldInfo['field_id']] ?? [];
                                        foreach ($arrCompanyOptions as $arrCompanyOptionInfo) {
                                            if ($fieldOptionLabel == $arrCompanyOptionInfo['value']) {
                                                $arrGroupedInfoByCompanies[$arrCompanyFieldInfo['company_id']]['options'][] = $arrCompanyOptionInfo['form_default_id'];
                                            }
                                        }
                                    }
                                    break;
                            }
                        }
                    }


                    foreach ($arrHiddenGroups as $hiddenGroupId) {
                        $arrCompaniesHiddenGroups = $oFields->getGroupsByParentId($hiddenGroupId);
                        foreach ($arrCompaniesHiddenGroups as $arrCompanyHiddenGroupInfo) {
                            $arrGroupedInfoByCompanies[$arrCompanyHiddenGroupInfo['company_id']]['hidden_groups'][] = $arrCompanyHiddenGroupInfo['group_id'];
                        }
                    }

                    foreach ($arrHiddenFields as $hiddenFieldId) {
                        $arrCompaniesHiddenFields = $oFields->getFieldsByParentId($hiddenFieldId);
                        foreach ($arrCompaniesHiddenFields as $arrCompanyHiddenFieldInfo) {
                            $arrGroupedInfoByCompanies[$arrCompanyHiddenFieldInfo['company_id']]['hidden_fields'][] = $arrCompanyHiddenFieldInfo['field_id'];
                        }
                    }

                    $arrAllSavedConditions = $this->getConditionsByParentId($conditionId);

                    foreach ($arrGroupedInfoByCompanies as $companyId => $arrCompanyCondition) {
                        if (!isset($arrCompanyCondition['client_type_id']) || !isset($arrCompanyCondition['field_id'])) {
                            continue;
                        }

                        if (!$booUseOptionAsIs && is_null($arrCompanyCondition['options'])) {
                            // This means that there are no linked options for this field
                            continue;
                        }

                        $arrCompanyConditions = $arrAllSavedConditions[$companyId] ?? [0];
                        foreach ($arrCompanyConditions as $companyConditionId) {
                            $booSuccess = $this->saveFieldConditions(
                                $companyConditionId,
                                $conditionId,
                                $arrCompanyCondition['client_type_id'],
                                $arrCompanyCondition['field_id'],
                                $booUseOptionAsIs ? $fieldOptionValue : implode(';', $arrCompanyCondition['options']),
                                $arrCompanyCondition['hidden_groups'] ?? [],
                                $arrCompanyCondition['hidden_fields'] ?? [],
                            );

                            if (!$booSuccess) {
                                $strError = $this->_tr->translate('Internal error.');
                                break;
                            }
                        }
                    }
                }
            }

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

        return $strError;
    }

    /**
     * Create / update field conditions record
     *
     * @param int $fieldConditionId
     * @param int $caseTemplateId
     * @param int $fieldId
     * @param int $fieldOptionValue
     * @param array $arrHiddenGroups
     * @param array $arrHiddenFields
     * @return bool true on success, otherwise false
     */
    public function saveFieldConditions($fieldConditionId, $fieldParentConditionId, $caseTemplateId, $fieldId, $fieldOptionValue, $arrHiddenGroups, $arrHiddenFields)
    {
        try {
            if (empty($fieldConditionId)) {
                $fieldConditionId = $this->_db2->insert(
                    'client_form_field_conditions',
                    [
                        'parent_field_condition_id' => empty($fieldParentConditionId) ? null : (int)$fieldParentConditionId,
                        'client_type_id'            => (int)$caseTemplateId,
                        'field_id'                  => (int)$fieldId,
                        'field_option_value'        => $fieldOptionValue,
                    ]
                );
            } else {
                $this->_db2->update(
                    'client_form_field_conditions',
                    [
                        'field_option_value' => $fieldOptionValue,
                    ],
                    ['field_condition_id' => (int)$fieldConditionId]
                );

                $this->_db2->delete('client_form_field_condition_hidden_groups', ['field_condition_id' => (int)$fieldConditionId]);
                $this->_db2->delete('client_form_field_condition_hidden_fields', ['field_condition_id' => (int)$fieldConditionId]);
            }

            foreach ($arrHiddenGroups as $groupId) {
                $this->_db2->insert(
                    'client_form_field_condition_hidden_groups',
                    [
                        'field_condition_id' => (int)$fieldConditionId,
                        'group_id'           => (int)$groupId,
                    ]
                );
            }

            foreach ($arrHiddenFields as $fieldId) {
                $this->_db2->insert(
                    'client_form_field_condition_hidden_fields',
                    [
                        'field_condition_id' => (int)$fieldConditionId,
                        'field_id'           => (int)$fieldId,
                    ]
                );
            }
        } catch (Exception $e) {
            $fieldConditionId = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $fieldConditionId;
    }

    /**
     * Load list of hidden fields for a specific case template and field condition record
     *
     * @param int $caseTemplateId
     * @param int $fieldConditionId
     * @return array
     */
    public function getConditionHiddenFields($caseTemplateId, $fieldConditionId)
    {
        $arrHiddenFields = array();
        if (!empty($caseTemplateId) && !empty($fieldConditionId)) {
            $select = (new Select())
                ->from(array('hf' => 'client_form_field_condition_hidden_fields'))
                ->columns(['field_id'])
                ->join(array('f' => 'client_form_fields'), 'f.field_id = hf.field_id', 'label', Select::JOIN_LEFT_OUTER)
                ->join(array('fo' => 'client_form_order'), 'fo.field_id = hf.field_id', 'group_id', Select::JOIN_LEFT_OUTER)
                ->join(array('fg' => 'client_form_groups'), 'fg.group_id = fo.group_id', 'title', Select::JOIN_LEFT_OUTER)
                ->where([
                    'fg.client_type_id'     => (int)$caseTemplateId,
                    'hf.field_condition_id' => (int)$fieldConditionId
                ])
                ->order(array('fg.order ASC', 'fo.field_order ASC'));

            $arrHiddenFields = $this->_db2->fetchAll($select);
        }

        return $arrHiddenFields;
    }

    /**
     * Load list of hidden groups for a specific case template and field condition record
     *
     * @param int $caseTemplateId
     * @param int $fieldConditionId
     * @return array
     */
    public function getConditionHiddenGroups($caseTemplateId, $fieldConditionId)
    {
        $arrHiddenGroups = array();
        if (!empty($caseTemplateId) && !empty($fieldConditionId)) {
            $select = (new Select())
                ->from(array('hfg' => 'client_form_field_condition_hidden_groups'))
                ->columns(['group_id'])
                ->join(array('fg' => 'client_form_groups'), 'fg.group_id = hfg.group_id', 'title', Select::JOIN_LEFT_OUTER)
                ->where([
                    'fg.client_type_id'      => (int)$caseTemplateId,
                    'hfg.field_condition_id' => (int)$fieldConditionId
                ])
                ->order(array('fg.order ASC'));

            $arrHiddenGroups = $this->_db2->fetchAll($select);
        }

        return $arrHiddenGroups;
    }

    /**
     * Delete field condition records
     *
     * @param array $arrConditions
     * @return bool true on success
     */
    public function deleteFieldConditions($arrConditions)
    {
        $booSuccess = false;
        if (!empty($arrConditions)) {
            try {
                $this->_db2->delete('client_form_field_conditions', ['field_condition_id' => $arrConditions]);

                $booSuccess = true;
            } catch (Exception $e) {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }
        }

        return $booSuccess;
    }

    /**
     * Load a list of conditional fields grouped by case types
     *
     * @param int|array $arrCaseTemplatesIds
     * @return array
     */
    public function getGroupedConditionalFields($arrCaseTemplatesIds)
    {
        $arrGroupedConditionalFields = array();

        try {
            if (!empty($arrCaseTemplatesIds)) {
                $select = (new Select())
                    ->from(array('fc' => 'client_form_field_conditions'))
                    ->join(array('f' => 'client_form_fields'), 'f.field_id = fc.field_id', [], Select::JOIN_LEFT_OUTER)
                    ->join(array('ft' => 'field_types'), 'f.type = ft.field_type_id', 'field_type_text_id', Select::JOIN_LEFT_OUTER)
                    ->join(array('g' => 'client_form_field_condition_hidden_groups'), 'fc.field_condition_id = g.field_condition_id', array('hide_group_id' => 'group_id'), Select::JOIN_LEFT_OUTER)
                    ->join(array('hf' => 'client_form_field_condition_hidden_fields'), 'fc.field_condition_id = hf.field_condition_id', array('hide_field_id' => 'field_id'), Select::JOIN_LEFT_OUTER)
                    ->where(['fc.client_type_id' => $arrCaseTemplatesIds]);

                $arrConditions = $this->_db2->fetchAll($select);

                foreach ($arrConditions as $arrConditionInfo) {
                    if (!empty($arrConditionInfo['field_option_value'])) {
                        if ($arrConditionInfo['field_type_text_id'] == 'checkbox') {
                            $arrOptions = [$arrConditionInfo['field_option_value']];
                        } else {
                            $arrOptions = explode(';', $arrConditionInfo['field_option_value'] ?? '');
                        }
                    } else {
                        $arrOptions = [0];
                    }

                    foreach ($arrOptions as $optionId) {
                        if (!empty($arrConditionInfo['hide_group_id'])) {
                            if (!isset($arrGroupedConditionalFields[$arrConditionInfo['client_type_id']][$arrConditionInfo['field_id']][$optionId]['hide_groups'])) {
                                $arrGroupedConditionalFields[$arrConditionInfo['client_type_id']][$arrConditionInfo['field_id']][$optionId]['hide_groups'] = array();
                            }

                            $arrGroupedConditionalFields[$arrConditionInfo['client_type_id']][$arrConditionInfo['field_id']][$optionId]['hide_groups'][] = $arrConditionInfo['hide_group_id'];
                        }

                        if (!empty($arrConditionInfo['hide_field_id'])) {
                            if (!isset($arrGroupedConditionalFields[$arrConditionInfo['client_type_id']][$arrConditionInfo['field_id']][$optionId]['hide_fields'])) {
                                $arrGroupedConditionalFields[$arrConditionInfo['client_type_id']][$arrConditionInfo['field_id']][$optionId]['hide_fields'] = array();
                            }

                            $arrGroupedConditionalFields[$arrConditionInfo['client_type_id']][$arrConditionInfo['field_id']][$optionId]['hide_fields'][] = $arrConditionInfo['hide_field_id'];
                        }

                        if (isset($arrGroupedConditionalFields[$arrConditionInfo['client_type_id']][$arrConditionInfo['field_id']][$optionId]['hide_groups'])) {
                            $arrGroupedConditionalFields[$arrConditionInfo['client_type_id']][$arrConditionInfo['field_id']][$optionId]['hide_groups'] = $this->_settings::arrayUnique(
                                $arrGroupedConditionalFields[$arrConditionInfo['client_type_id']][$arrConditionInfo['field_id']][$optionId]['hide_groups']
                            );
                        }

                        if (isset($arrGroupedConditionalFields[$arrConditionInfo['client_type_id']][$arrConditionInfo['field_id']][$optionId]['hide_fields'])) {
                            $arrGroupedConditionalFields[$arrConditionInfo['client_type_id']][$arrConditionInfo['field_id']][$optionId]['hide_fields'] = $this->_settings::arrayUnique(
                                $arrGroupedConditionalFields[$arrConditionInfo['client_type_id']][$arrConditionInfo['field_id']][$optionId]['hide_fields']
                            );
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrGroupedConditionalFields;
    }

    /**
     * Get readable conditions for fields and groups in a specific case template
     *
     * @param Fields $oFields
     * @param int $companyId
     * @param int $caseTemplateId
     * @return array
     */
    public function getCaseTypeReadableConditions($oFields, $companyId, $caseTemplateId)
    {
        $arrGroupsWithConditions = [
            'fields' => [],
            'groups' => [],
        ];

        try {
            $select = (new Select())
                ->from(array('fc' => 'client_form_field_conditions'))
                ->join(array('f' => 'client_form_fields'), 'f.field_id = fc.field_id', array('source_field_label' => 'label', 'company_field_id'), Select::JOIN_LEFT_OUTER)
                ->join(array('ft' => 'field_types'), 'f.type = ft.field_type_id', 'field_type_text_id', Select::JOIN_LEFT_OUTER)
                ->where(['fc.client_type_id' => $caseTemplateId]);

            $arrConditions = $this->_db2->fetchAll($select);

            // Get grouped fields/groups for each condition
            $arrHiddenGroups = [];
            $arrHiddenFields = [];
            if (!empty($arrConditions)) {
                $arrConditionsIds = array_column($arrConditions, 'field_condition_id');

                $select = (new Select())
                    ->from(array('g' => 'client_form_field_condition_hidden_groups'))
                    ->columns(['field_condition_id', 'hide_group_id' => 'group_id'])
                    ->where(['g.field_condition_id' => $arrConditionsIds]);

                $arrHiddenGroupsSaved = $this->_db2->fetchAll($select);

                foreach ($arrHiddenGroupsSaved as $arrHiddenGroupSavedInfo) {
                    $arrHiddenGroups[$arrHiddenGroupSavedInfo['field_condition_id']][] = $arrHiddenGroupSavedInfo['hide_group_id'];
                }

                $select = (new Select())
                    ->from(array('f' => 'client_form_field_condition_hidden_fields'))
                    ->columns(['field_condition_id', 'hide_field_id' => 'field_id'])
                    ->where(['f.field_condition_id' => $arrConditionsIds]);

                $arrHiddenFieldsSaved = $this->_db2->fetchAll($select);

                foreach ($arrHiddenFieldsSaved as $arrHiddenFieldSavedInfo) {
                    $arrHiddenFields[$arrHiddenFieldSavedInfo['field_condition_id']][] = $arrHiddenFieldSavedInfo['hide_field_id'];
                }
            }

            $arrCachedFieldsOptions = [];
            foreach ($arrConditions as $arrConditionInfo) {
                $arrSavedOptionsLabels = [];

                switch ($arrConditionInfo['field_type_text_id']) {
                    case 'checkbox':
                        if ($arrConditionInfo['field_option_value'] == 'checked') {
                            $arrSavedOptionsLabels = [$this->_tr->translate('Checked')];
                        } else {
                            $arrSavedOptionsLabels = [$this->_tr->translate('Unchecked')];
                        }
                        break;

                    default:
                        if (strlen($arrConditionInfo['field_option_value'] ?? '')) {
                            if (!isset($arrCachedFieldsOptions[$arrConditionInfo['field_id']])) {
                                $arrCachedFieldsOptions[$arrConditionInfo['field_id']] = $oFields->getCompanyFieldOptions($companyId, 0, $arrConditionInfo['field_type_text_id'], $arrConditionInfo['company_field_id'], $caseTemplateId);
                            }
                            $arrFieldOptions = $arrCachedFieldsOptions[$arrConditionInfo['field_id']];

                            $fieldOptionId   = $arrConditionInfo['field_option_value'] ?? '';
                            $arrSavedOptions = explode(';', $fieldOptionId);
                            foreach ($arrSavedOptions as $arrSavedOptionId) {
                                if (empty($arrSavedOptionId)) {
                                    $arrSavedOptionsLabels[] = $this->_tr->translate('-- NOT SELECTED --');
                                } else {
                                    foreach ($arrFieldOptions as $arrFieldOptionInfo) {
                                        if ($arrFieldOptionInfo['option_id'] == $arrSavedOptionId) {
                                            $arrSavedOptionsLabels[] = str_replace(["'", '"'], '`', $arrFieldOptionInfo['option_name']);
                                        }
                                    }
                                }
                            }
                        } else {
                            $arrSavedOptionsLabels[] = $this->_tr->translate('-- NOT SELECTED --');
                        }
                        break;
                }

                if (isset($arrHiddenGroups[$arrConditionInfo['field_condition_id']])) {
                    foreach ($arrHiddenGroups[$arrConditionInfo['field_condition_id']] as $hideGroupId) {
                        if (!empty($hideGroupId)) {
                            if (isset($arrGroupsWithConditions['groups'][$hideGroupId])) {
                                $arrGroupsWithConditions['groups'][$hideGroupId] .= '<br><br><br>';
                            } else {
                                $arrGroupsWithConditions['groups'][$hideGroupId] = '';
                            }

                            $arrGroupsWithConditions['groups'][$hideGroupId] .= sprintf(
                                $this->_tr->translate('Hide Group when %s is:%s'),
                                $arrConditionInfo['source_field_label'],
                                '<br> * ' . implode('<br> * ', $arrSavedOptionsLabels)
                            );
                        }
                    }
                }

                if (isset($arrHiddenFields[$arrConditionInfo['field_condition_id']])) {
                    foreach ($arrHiddenFields[$arrConditionInfo['field_condition_id']] as $hideFieldId) {
                        if (!empty($hideFieldId)) {
                            if (isset($arrGroupsWithConditions['fields'][$hideFieldId])) {
                                $arrGroupsWithConditions['fields'][$hideFieldId] .= '<br><br><br>';
                            } else {
                                $arrGroupsWithConditions['fields'][$hideFieldId] = '';
                            }

                            $arrGroupsWithConditions['fields'][$hideFieldId] .= sprintf(
                                $this->_tr->translate('Hide Field when %s is:%s'),
                                $arrConditionInfo['source_field_label'],
                                '<br> * ' . implode('<br> * ', $arrSavedOptionsLabels)
                            );
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrGroupsWithConditions;
    }

    /**
     * Create conditionals from the default company
     *
     * @param $arrMappingCaseTemplates
     * @param $arrMappingGroups
     * @param $arrMappingFields
     * @param $arrMappingCategories
     * @param $arrMappingCaseStatuses
     * @param $arrMappingDefaults
     * @return bool
     */
    public function createDefaultConditionalFields($arrMappingCaseTemplates, $arrMappingGroups, $arrMappingFields, $arrMappingCategories, $arrMappingCaseStatuses, $arrMappingDefaults)
    {
        try {
            if (empty($arrMappingCaseTemplates)) {
                // Can't be here
                return false;
            }

            $select = (new Select())
                ->from(array('fc' => 'client_form_field_conditions'))
                ->join(array('f' => 'client_form_fields'), 'f.field_id = fc.field_id', [], Select::JOIN_LEFT_OUTER)
                ->join(array('ft' => 'field_types'), 'f.type = ft.field_type_id', 'field_type_text_id', Select::JOIN_LEFT_OUTER)
                ->where([
                    'fc.client_type_id' => array_keys($arrMappingCaseTemplates)
                ]);

            $arrDefaultConditions = $this->_db2->fetchAll($select);

            foreach ($arrDefaultConditions as $arrDefaultConditionInfo) {
                // Convert saved value if needed
                $savedValue = $arrDefaultConditionInfo['field_option_value'] ?? '';
                if (!empty($savedValue) && $arrDefaultConditionInfo['field_type_text_id'] !== 'checkbox') {
                    $arrSavedOptionsConverted = [];
                    switch ($arrDefaultConditionInfo['field_type_text_id']) {
                        case 'categories':
                            $arrWhat = $arrMappingCategories;
                            break;

                        case 'case_status':
                            $arrWhat = $arrMappingCaseStatuses;
                            break;

                        default:
                            $arrWhat = $arrMappingDefaults;
                            break;
                    }

                    $arrSavedOptions = explode(';', $savedValue);
                    foreach ($arrSavedOptions as $arrSavedOptionId) {
                        if (empty($arrSavedOptionId)) {
                            $arrSavedOptionsConverted[] = $arrSavedOptionId;
                        } else {
                            $arrSavedOptionsConverted[] = $arrWhat[$arrSavedOptionId];
                        }
                    }
                    $savedValue = implode(';', $arrSavedOptionsConverted);
                }

                // Convert saved condition's groups
                $select = (new Select())
                    ->from(array('hfg' => 'client_form_field_condition_hidden_groups'))
                    ->columns(['group_id'])
                    ->where([
                        'hfg.field_condition_id' => (int)$arrDefaultConditionInfo['field_condition_id']
                    ]);

                $arrSavedConditionHiddenGroupsIds = $this->_db2->fetchCol($select);

                $arrHiddenGroups = [];
                foreach ($arrSavedConditionHiddenGroupsIds as $arrSavedConditionHiddenGroupId) {
                    $arrHiddenGroups[] = $arrMappingGroups[$arrSavedConditionHiddenGroupId];
                }


                // Convert saved condition's fields
                $select = (new Select())
                    ->from(array('f' => 'client_form_field_condition_hidden_fields'))
                    ->columns(['field_id'])
                    ->where([
                        'f.field_condition_id' => (int)$arrDefaultConditionInfo['field_condition_id']
                    ]);

                $arrSavedConditionHiddenFieldsIds = $this->_db2->fetchCol($select);

                $arrHiddenFields = [];
                foreach ($arrSavedConditionHiddenFieldsIds as $arrSavedConditionHiddenFieldId) {
                    $arrHiddenFields[] = $arrMappingFields[$arrSavedConditionHiddenFieldId];
                }


                // Create the condition!
                $this->saveFieldConditions(
                    0,
                    $arrDefaultConditionInfo['field_condition_id'],
                    $arrMappingCaseTemplates[$arrDefaultConditionInfo['client_type_id']],
                    $arrMappingFields[$arrDefaultConditionInfo['field_id']],
                    $savedValue,
                    $arrHiddenGroups,
                    $arrHiddenFields
                );
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

}
