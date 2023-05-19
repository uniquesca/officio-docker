<?php

namespace Superadmin\Controller;

use Exception;
use Laminas\Filter\StripTags;
use Clients\Service\Clients;
use Clients\Service\Clients\Fields;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Service\ConditionalFields;

/**
 * Conditional Fields Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ConditionalFieldsController extends BaseController
{

    /** @var Clients */
    protected $_clients;

    /** @var Fields */
    protected $_fields;

    /** @var ConditionalFields */
    private $_conditionalFields;

    /** @var Company */
    private $_company;

    /** @var StripTags */
    private $_filter;

    public function initAdditionalServices(array $services)
    {
        $this->_filter            = new StripTags();
        $this->_clients           = $services[Clients::class];
        $this->_fields            = $this->_clients->getFields();
        $this->_company           = $services[Company::class];
        $this->_conditionalFields = $services[ConditionalFields::class];
    }

    /**
     * Load list of conditions for a specific field
     */
    public function listAction()
    {
        $strError         = '';
        $arrConditions    = array();
        $arrGroupedFields = array();

        try {
            $caseTemplateId = (int)$this->params()->fromPost('case_template_id');
            $fieldId        = str_replace('field_', '', $this->_filter->filter($this->params()->fromPost('field_id', '')));

            if (empty($strError) && !$this->_clients->getCaseTemplates()->hasAccessToTemplate($caseTemplateId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $companyId    = $this->_auth->getCurrentUserCompanyId();
            $arrFieldInfo = [];
            if (empty($strError)) {
                $arrFieldInfo = $this->_fields->getFieldInfo($fieldId, $companyId, $caseTemplateId);

                if (empty($arrFieldInfo)) {
                    $strError = $this->_tr->translate('Incorrectly selected field.');
                }
            }

            if (empty($strError)) {
                $arrFieldOptions = $this->_fields->getCompanyFieldOptions($companyId, 0, $arrFieldInfo['field_type_text_id'], $arrFieldInfo['company_field_id'], $caseTemplateId);
                $arrConditions   = $this->_conditionalFields->getFieldConditions($caseTemplateId, $fieldId, $arrFieldOptions);

                $arrGroupedFields = $this->_fields->getGroupedCompanyFields($caseTemplateId, false);
            }
        } catch (Exception $e) {
            $arrConditions = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'error'            => $strError,
            'totalCount'       => count($arrConditions),
            'rows'             => $arrConditions,
            'arrGroupedFields' => $arrGroupedFields
        );

        return new JsonModel($arrResult);
    }

    /**
     * Create/update field condition(s) record
     */
    public function saveAction()
    {
        set_time_limit(10 * 60); // 10 min
        ini_set('memory_limit', '-1');

        $strError              = '';
        $arrReadableConditions = [];

        try {
            $companyId      = $this->_auth->getCurrentUserCompanyId();
            $caseTemplateId = (int)$this->params()->fromPost('case_template_id');
            $fieldId        = str_replace('field_', '', $this->_filter->filter($this->params()->fromPost('field_id', '')));
            $fieldOptionId  = $this->_filter->filter($this->params()->fromPost('condition_option_id', ''));

            $arrHiddenGroups = $this->params()->fromPost('condition_groups_hidden', array());
            // The list of groups that should be kept
            $strIgnoreGroups = $this->params()->fromPost('hidden_groups_ignore', '');
            $arrIgnoreGroups = strlen($strIgnoreGroups) ? explode(';', $strIgnoreGroups) : [];

            $arrHiddenFields = $this->params()->fromPost('condition_fields_hidden', array());
            // The list of fields that should be kept
            $strIgnoreFields = $this->params()->fromPost('hidden_fields_ignore', '');
            $arrIgnoreFields = strlen($strIgnoreFields) ? explode(';', $strIgnoreFields) : [];

            $mode        = $this->params()->fromPost('mode');
            $booEditMode = $mode === 'edit';
            if (!in_array($mode, ['add', 'edit'])) {
                $strError = $this->_tr->translate('Incorrect incoming info.');
            }

            // Check if current user has access to the selected case template
            if (empty($strError) && !$this->_clients->getCaseTemplates()->hasAccessToTemplate($caseTemplateId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            // Check if selected field is in the selected case template
            $arrFieldInfo = [];
            if (empty($strError)) {
                $arrFieldInfo = $this->_fields->getFieldInfo($fieldId, $companyId, $caseTemplateId);

                if (empty($arrFieldInfo)) {
                    $strError = $this->_tr->translate('Incorrectly selected field.');
                }
            }

            // Check if selected option is in the selected field
            $arrFieldOptionIds  = [];
            $arrSelectedOptions = [];
            $arrFieldOptions    = [];
            if (empty($strError)) {
                if ($arrFieldInfo['field_type_text_id'] === 'checkbox') {
                    $booFound = in_array($fieldOptionId, array(0, 'checked'));

                    $arrFieldOptionIds[] = $fieldOptionId;
                } else {
                    $arrFieldOptions = $this->_fields->getCompanyFieldOptions($companyId, 0, $arrFieldInfo['field_type_text_id'], $arrFieldInfo['company_field_id'], $caseTemplateId);

                    $arrFieldOptionIds = explode(';', $fieldOptionId);
                    foreach ($arrFieldOptionIds as $fieldOptionId) {
                        if (empty($fieldOptionId)) {
                            $arrSelectedOptions[] = $fieldOptionId;
                        } else {
                            foreach ($arrFieldOptions as $arrFieldOptionInfo) {
                                if ($arrFieldOptionInfo['option_id'] == $fieldOptionId) {
                                    $arrSelectedOptions[$fieldOptionId] = $arrFieldOptionInfo['option_name'];
                                    break;
                                }
                            }
                        }
                    }

                    $booFound = !empty($arrFieldOptionIds) && count($arrSelectedOptions) == count($arrFieldOptionIds);
                }

                if (!$booFound) {
                    $strError = $this->_tr->translate('Incorrectly selected field option(s).');
                }
            }

            // At least one group and/or field must be selected
            if (empty($strError) && !is_array($arrHiddenGroups)) {
                $strError = $this->_tr->translate('Incorrectly selected group(s).');
            }

            if (empty($strError) && !is_array($arrHiddenFields)) {
                $strError = $this->_tr->translate('Incorrectly selected field(s).');
            }

            if (empty($strError) && empty($arrHiddenGroups) && empty($arrHiddenFields) && empty($arrIgnoreFields) && empty($arrIgnoreGroups)) {
                $strError = $this->_tr->translate('Please check at least one group or field.');
            }


            // Check if all provided groups are in the same case template
            $arrGroupedFields = array();
            if (empty($strError)) {
                $arrGroupedFields = $this->_fields->getGroupedCompanyFields($caseTemplateId, false);

                $arrCheckGroups = array_merge($arrHiddenGroups, $arrIgnoreGroups);
                foreach ($arrCheckGroups as $groupId) {
                    $booFoundGroup = false;
                    foreach ($arrGroupedFields as $arrCompanyGroup) {
                        if ($arrCompanyGroup['group_id'] == $groupId) {
                            $booFoundGroup = true;
                            break;
                        }
                    }

                    if (!$booFoundGroup) {
                        $strError = $this->_tr->translate('Incorrectly selected group(s).');
                        break;
                    }
                }
            }

            // Check if all provided fields are in the same case template
            if (empty($strError)) {
                $arrCheckFields = array_merge($arrHiddenFields, $arrIgnoreFields);
                foreach ($arrCheckFields as $groupFieldId) {
                    $booFoundField = false;
                    foreach ($arrGroupedFields as $arrCompanyGroup) {
                        if (isset($arrCompanyGroup['fields'])) {
                            foreach ($arrCompanyGroup['fields'] as $arrGroupFieldInfo) {
                                if ($arrGroupFieldInfo['field_id'] == $groupFieldId) {
                                    $booFoundField = true;
                                    break 2;
                                }
                            }
                        }
                    }

                    if (!$booFoundField) {
                        $strError = $this->_tr->translate('Incorrectly selected field(s).');
                        break;
                    }
                }
            }

            $arrGroupedChanges = [];
            if (empty($strError)) {
                $arrSavedConditions = $this->_conditionalFields->getFieldConditions($caseTemplateId, $fieldId, $arrFieldOptions);

                foreach ($arrFieldOptionIds as $arrFieldOptionId) {
                    $conditionId = 0;

                    $conditionHiddenFields = $arrHiddenFields;
                    $conditionHiddenGroups = $arrHiddenGroups;

                    foreach ($arrSavedConditions as $arrSavedConditionInfo) {
                        if ($arrSavedConditionInfo['field_option_id'] == $arrFieldOptionId) {
                            $conditionId = $arrSavedConditionInfo['field_condition_id'];

                            if ($booEditMode) {
                                // Change for selected only
                                foreach ($arrSavedConditionInfo['field_condition_hidden_fields'] as $arrSavedHiddenFieldId) {
                                    if (in_array($arrSavedHiddenFieldId, $arrIgnoreFields)) {
                                        $conditionHiddenFields[] = $arrSavedHiddenFieldId;
                                    }
                                }

                                foreach ($arrSavedConditionInfo['field_condition_hidden_groups'] as $arrSavedHiddenGroupId) {
                                    if (in_array($arrSavedHiddenGroupId, $arrIgnoreGroups)) {
                                        $conditionHiddenGroups[] = $arrSavedHiddenGroupId;
                                    }
                                }
                            } else {
                                // Add to exisiting, don't change if already added
                                foreach ($arrSavedConditionInfo['field_condition_hidden_fields'] as $arrSavedHiddenFieldId) {
                                    $conditionHiddenFields[] = $arrSavedHiddenFieldId;
                                }

                                foreach ($arrSavedConditionInfo['field_condition_hidden_groups'] as $arrSavedHiddenGroupId) {
                                    $conditionHiddenGroups[] = $arrSavedHiddenGroupId;
                                }
                            }

                            $conditionHiddenFields = array_unique($conditionHiddenFields);
                            $conditionHiddenGroups = array_unique($conditionHiddenGroups);
                            break;
                        }
                    }

                    // Remove fields if they are already hidden because of the parent groups
                    foreach ($conditionHiddenGroups as $groupId) {
                        foreach ($arrGroupedFields as $arrCompanyGroup) {
                            if ($arrCompanyGroup['group_id'] == $groupId) {
                                if (isset($arrCompanyGroup['fields'])) {
                                    foreach ($arrCompanyGroup['fields'] as $arrGroupFieldInfo) {
                                        $key = array_search($arrGroupFieldInfo['field_id'], $conditionHiddenFields);
                                        if ($key !== false) {
                                            unset($conditionHiddenFields[$key]);
                                        }
                                    }
                                }
                                break;
                            }
                        }
                    }

                    if ($arrFieldInfo['field_type_text_id'] === 'checkbox') {
                        $fieldOptionValue = empty($arrFieldOptionId) ? null : 'checked';
                        $fieldOptionLabel = ''; // is not used
                    } else {
                        $fieldOptionValue = empty($arrFieldOptionId) ? null : $arrFieldOptionId;
                        $fieldOptionLabel = empty($arrSelectedOptions[$arrFieldOptionId]) ? null : $arrSelectedOptions[$arrFieldOptionId];
                    }

                    $arrGroupedChanges[] = [
                        'condition_id'       => $conditionId,
                        'field_option_value' => $fieldOptionValue,
                        'field_option_label' => $fieldOptionLabel,
                        'hidden_groups'      => $conditionHiddenGroups,
                        'hidden_fields'      => $conditionHiddenFields,
                    ];
                }
            }

            if (empty($strError)) {
                $strError = $this->_conditionalFields->saveConditionsInBatch(
                    $this->_company,
                    $this->_clients,
                    $this->_fields,
                    $companyId,
                    $caseTemplateId,
                    $arrFieldInfo,
                    $arrGroupedChanges
                );
            }

            if (empty($strError)) {
                $arrReadableConditions = $this->_conditionalFields->getCaseTypeReadableConditions($this->_fields, $companyId, $caseTemplateId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'    => empty($strError),
            'message'    => empty($strError) ? $this->_tr->translate('Done!') : $strError,
            'conditions' => $arrReadableConditions,
        );

        return new JsonModel($arrResult);
    }

    /**
     * Delete field conditions record(s)
     */
    public function deleteAction()
    {
        $strError              = '';
        $arrReadableConditions = [];
        $booTransactionStarted = false;

        try {
            $caseTemplateId = (int)$this->params()->fromPost('case_template_id');
            $fieldId        = str_replace('field_', '', $this->_filter->filter($this->params()->fromPost('field_id', '')));
            $arrConditions  = Json::decode($this->params()->fromPost('records'), Json::TYPE_ARRAY);

            if (empty($strError) && !$this->_clients->getCaseTemplates()->hasAccessToTemplate($caseTemplateId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $companyId    = $this->_auth->getCurrentUserCompanyId();
            $arrFieldInfo = [];
            if (empty($strError)) {
                $arrFieldInfo = $this->_fields->getFieldInfo($fieldId, $companyId, $caseTemplateId);

                if (empty($arrFieldInfo)) {
                    $strError = $this->_tr->translate('Incorrectly selected field.');
                }
            }

            if (empty($strError) && (empty($arrConditions) || !is_array($arrConditions))) {
                $strError = $this->_tr->translate('Incorrectly selected field conditions.');
            }

            if (empty($strError)) {
                $arrFieldOptions    = $this->_fields->getCompanyFieldOptions($companyId, 0, $arrFieldInfo['field_type_text_id'], $arrFieldInfo['company_field_id'], $caseTemplateId);
                $arrSavedConditions = $this->_conditionalFields->getFieldConditions($caseTemplateId, $fieldId, $arrFieldOptions);

                foreach ($arrConditions as $fieldConditionIdToCheck) {
                    $booFound = false;
                    foreach ($arrSavedConditions as $arrSavedConditionInfo) {
                        if ($fieldConditionIdToCheck == $arrSavedConditionInfo['field_condition_id']) {
                            $booFound = true;
                            break;
                        }
                    }

                    if (!$booFound) {
                        $strError = $this->_tr->translate('Incorrectly selected field conditions.');
                    }
                }
            }


            if (empty($strError)) {
                $this->_db2->getDriver()->getConnection()->beginTransaction();
                $booTransactionStarted = true;

                $booSuccess = $this->_conditionalFields->deleteFieldConditions($arrConditions);
                if (!$booSuccess) {
                    $strError = $this->_tr->translate('Internal error.');
                }
            }

            if (empty($strError)) {
                $this->_db2->getDriver()->getConnection()->commit();
                $booTransactionStarted = false;

                $arrReadableConditions = $this->_conditionalFields->getCaseTypeReadableConditions($this->_fields, $companyId, $caseTemplateId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError) && $booTransactionStarted) {
            $this->_db2->getDriver()->getConnection()->rollback();
        }

        if (!empty($strError)) {
            $this->getResponse()->setStatusCode(400);
        }

        $arrResult = array(
            'success'    => empty($strError),
            'message'    => empty($strError) ? $this->_tr->translate('Done!') : $strError,
            'conditions' => $arrReadableConditions,
        );

        return new JsonModel($arrResult);
    }
}