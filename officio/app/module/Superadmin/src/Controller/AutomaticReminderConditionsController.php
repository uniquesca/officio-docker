<?php

namespace Superadmin\Controller;

use Clients\Service\Clients;
use Exception;
use Forms\Service\Forms;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Officio\BaseController;
use Officio\Service\AutomaticReminders;
use Officio\Service\Company;

/**
 * Automatic Reminder Conditions Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class AutomaticReminderConditionsController extends BaseController
{
    /** @var  AutomaticReminders */
    private $_automaticReminders;

    /** @var Clients */
    protected $_clients;

    /** @var Company */
    protected $_company;

    /** @var Forms */
    protected $_forms;

    public function initAdditionalServices(array $services)
    {
        $this->_automaticReminders = $services[AutomaticReminders::class];
        $this->_clients            = $services[Clients::class];
        $this->_company            = $services[Company::class];
        $this->_forms              = $services[Forms::class];
    }

    public function getGridAction()
    {
        $strError      = '';
        $arrConditions = array();
        $booHasChangedFieldConditions = false;

        try {
            $reminderId      = (int)$this->params()->fromPost('reminder_id');
            /** @var array $arrConditionIds */
            $arrConditionIds = Json::decode($this->params()->fromPost('condition_ids'), Json::TYPE_ARRAY);
            if (!is_array($arrConditionIds)) {
                $strError = $this->_tr->translate('Incorrect conditions.');
            }

            if (empty($strError) && !empty($reminderId) && !$this->_automaticReminders->hasAccessToReminder($reminderId)) {
                $strError = $this->_tr->translate('Insufficient access rights to reminder.');
            }

            if (empty($strError) && count($arrConditionIds)) {
                foreach ($arrConditionIds as $conditionId) {
                    if (!$this->_automaticReminders->getConditions()->hasAccessToCondition($conditionId)) {
                        $strError = $this->_tr->translate('Insufficient access rights to condition.');
                        break;
                    }
                }
            }

            if (empty($strError) && (!empty($reminderId) || count($arrConditionIds))) {
                $arrConditions = $this->_automaticReminders->getConditions()->getReminderConditions(
                    $this->_auth->getCurrentUserCompanyId(),
                    $reminderId,
                    $arrConditionIds
                );
                $booHasChangedFieldConditions = $this->_automaticReminders->getConditions()->hasChangedFieldConditions($reminderId);
            }

        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'    => empty($strError),
            'rows'       => $arrConditions,
            'totalCount' => count($arrConditions),
            'booHasChangedFieldConditions' => $booHasChangedFieldConditions
        );
        return new JsonModel($arrResult);
    }

    public function getAction()
    {
        $strError         = '';
        $arrConditionInfo = array();

        try {
            $reminderId  = (int)$this->params()->fromPost('reminder_id');
            $conditionId = (int)$this->params()->fromPost('condition_id');

            if (empty($strError) && !empty($reminderId) && !$this->_automaticReminders->hasAccessToReminder($reminderId)) {
                $strError = $this->_tr->translate('Insufficient access rights to reminder.');
            }

            if (empty($strError) && !$this->_automaticReminders->getConditions()->hasAccessToCondition($conditionId)) {
                $strError = $this->_tr->translate('Insufficient access rights to condition.');
            }

            if (empty($strError)) {
                $arrConditionInfo = $this->_automaticReminders->getConditions()->getCondition(
                    $this->_auth->getCurrentUserCompanyId(),
                    $reminderId,
                    $conditionId
                );

                if (!empty($arrConditionInfo['automatic_reminder_condition_settings'])) {
                    $arrConditionInfo['automatic_reminder_condition_settings'] = Json::decode($arrConditionInfo['automatic_reminder_condition_settings'], Json::TYPE_ARRAY);
                }
            }

        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array_merge(
            $arrConditionInfo,
            array('success' => empty($strError), 'msg' => $strError)
        );
        return new JsonModel($arrResult);
    }

    public function saveAction()
    {
        set_time_limit(2 * 60); // 2 minutes, no more
        ini_set('memory_limit', '-1');
        session_write_close();

        $strError = $reminderConditionId = '';
        $filter   = new StripTags();

        try {
            $reminderId                 = (int)$this->params()->fromPost('reminder_id');
            $reminderConditionId        = (int)$this->params()->fromPost('reminder_condition_id');
            $conditionType              = $filter->filter($this->params()->fromPost('type'));
            $arrConditionSettingsToSave = array();

            if (empty($strError) && !empty($reminderId) && !$this->_automaticReminders->hasAccessToReminder($reminderId)) {
                $strError = $this->_tr->translate('Insufficient access rights to reminder.');
            }

            if (empty($strError) && !empty($reminderConditionId) && !$this->_automaticReminders->getConditions()->hasAccessToCondition($reminderConditionId)) {
                $strError = $this->_tr->translate('Insufficient access rights to condition.');
            }

            $arrConditionSettings = empty($strError) ? $this->_automaticReminders->getConditions()->getConditionSettings(true) : array();

            if (empty($strError) && !in_array($conditionType, $arrConditionSettings['condition_types'])) {
                $strError = $this->_tr->translate('Incorrectly checked radio buttons.');
            }


            if (empty($strError)) {
                $arrApplicantSettings = $this->_clients->getSettings(
                    $this->_auth->getCurrentUserId(),
                    $this->_auth->getCurrentUserCompanyId(),
                    $this->_auth->getCurrentUserDivisionGroupId(),
                    false,
                    false,
                    true
                );
                switch ($conditionType) {
                    case 'CLIENT_PROFILE':
                        $arrConditionSettingsToSave = array(
                            'number' => (int)$this->params()->fromPost('number'),
                            'days'   => $filter->filter($this->params()->fromPost('days')),
                            'ba'     => $filter->filter($this->params()->fromPost('ba')),
                            'prof'   => (int)$this->params()->fromPost('prof'),
                        );

                        if (!is_numeric($arrConditionSettingsToSave['number']) || $arrConditionSettingsToSave['number'] < 0) {
                            $strError = $this->_tr->translate('Incorrect number.');
                        }

                        if (empty($strError) && !in_array($arrConditionSettingsToSave['days'], $arrConditionSettings['calendar_combo_days'])) {
                            $strError = $this->_tr->translate('Incorrectly selected days field option.');
                        }

                        if (empty($strError) && !in_array($arrConditionSettingsToSave['ba'], $arrConditionSettings['calendar_combo_before_after'])) {
                            $strError = $this->_tr->translate('Incorrectly selected before/after field option.');
                        }

                        if (empty($strError) && !in_array($arrConditionSettingsToSave['prof'], $arrConditionSettings['client_date_fields'])) {
                            $strError = $this->_tr->translate('Incorrectly selected date field related to the client.');
                        }
                        break;

                    case 'PROFILE':
                        $arrConditionSettingsToSave = array(
                            'number' => (int)$this->params()->fromPost('number'),
                            'days'   => $filter->filter($this->params()->fromPost('days')),
                            'ba'     => $filter->filter($this->params()->fromPost('ba')),
                            'prof'   => (int)$this->params()->fromPost('prof'),
                        );

                        if (!is_numeric($arrConditionSettingsToSave['number']) || $arrConditionSettingsToSave['number'] < 0) {
                            $strError = $this->_tr->translate('Incorrect number.');
                        }

                        if (empty($strError) && !in_array($arrConditionSettingsToSave['days'], $arrConditionSettings['calendar_combo_days'])) {
                            $strError = $this->_tr->translate('Incorrectly selected days field option.');
                        }

                        if (empty($strError) && !in_array($arrConditionSettingsToSave['ba'], $arrConditionSettings['calendar_combo_before_after'])) {
                            $strError = $this->_tr->translate('Incorrectly selected before/after field option.');
                        }

                        if (empty($strError) && !in_array($arrConditionSettingsToSave['prof'], $arrConditionSettings['case_date_fields'])) {
                            $strError = $this->_tr->translate('Incorrectly selected date field related to the case.');
                        }
                        break;

                    case 'FILESTATUS':
                        $arrConditionSettingsToSave = array(
                            'number'      => (int)$this->params()->fromPost('number'),
                            'days'        => $filter->filter($this->params()->fromPost('days')),
                            'ba'          => $filter->filter($this->params()->fromPost('ba')),
                            'file_status' => (int)$this->params()->fromPost('file_status'),
                        );

                        if (!is_numeric($arrConditionSettingsToSave['number']) || $arrConditionSettingsToSave['number'] < 0) {
                            $strError = $this->_tr->translate('Incorrect number.');
                        }

                        if (empty($strError) && !in_array($arrConditionSettingsToSave['days'], $arrConditionSettings['calendar_combo_days'])) {
                            $strError = $this->_tr->translate('Incorrectly selected days field option.');
                        }

                        if (empty($strError) && !in_array($arrConditionSettingsToSave['ba'], $arrConditionSettings['calendar_combo_before_after'])) {
                            $strError = $this->_tr->translate('Incorrectly selected before/after field option.');
                        }

                        if (empty($strError) && !in_array($arrConditionSettingsToSave['file_status'], $arrConditionSettings['file_status'])) {
                            $strError = $this->_tr->translate('Incorrectly selected case status field option.');
                        }
                        break;

                    case 'BASED_ON_FIELD':
                        $arrConditionSettingsToSave = array(
                            'based_on_field_member_type' => $this->params()->fromPost('based_on_field_member_types_combo'),
                            'based_on_field_field_id'    => $this->params()->fromPost('based_on_field_fields_combo'),
                            'based_on_field_condition'   => $this->params()->fromPost('based_on_field_conditions_combo'),
                        );

                        $arrSupportedMemberTypes = array();
                        foreach ($arrApplicantSettings['search_for'] as $searchForInfo) {
                            if ($searchForInfo['search_for_id'] != 'accounting') {
                                $arrSupportedMemberTypes[] = $searchForInfo['search_for_id'];
                            }
                        }
                        if (!in_array($arrConditionSettingsToSave['based_on_field_member_type'], $arrSupportedMemberTypes)) {
                            $strError = $this->_tr->translate('Incorrectly selected member type.');
                        }

                        $fieldType = '';
                        if (empty($strError)) {
                            $booFoundField = false;

                            switch ($arrConditionSettingsToSave['based_on_field_member_type']) {
                                case 'case':
                                    foreach ($arrApplicantSettings['case_group_templates'] as $arrGroups) {
                                        foreach ($arrGroups as $arrGroupedFields) {
                                            foreach ($arrGroupedFields['fields'] as $arrFieldInfo) {
                                                if ($arrFieldInfo['field_id'] == $arrConditionSettingsToSave['based_on_field_field_id']) {
                                                    $fieldType     = $arrFieldInfo['field_type'];
                                                    $booFoundField = true;
                                                    break 3;
                                                }
                                            }
                                        }
                                    }
                                    break;

                                default:
                                    foreach ($arrApplicantSettings['groups_and_fields'][$arrConditionSettingsToSave['based_on_field_member_type']] as $arrBlockInfo) {
                                        foreach ($arrBlockInfo['fields'] as $arrFieldsGroups) {
                                            foreach ($arrFieldsGroups['fields'] as $arrFieldInfo) {
                                                if ($arrFieldInfo['field_id'] == $arrConditionSettingsToSave['based_on_field_field_id']) {
                                                    $fieldType     = $arrFieldInfo['field_type'];
                                                    $booFoundField = true;
                                                    break 3;
                                                }
                                            }
                                        }
                                    }
                                    break;
                            }

                            if (!$booFoundField) {
                                $strError = $this->_tr->translate('Incorrectly selected field.');
                            }
                        }

                        if (empty($strError)) {
                            switch ($fieldType) {
                                case 'float':
                                case 'number':
                                case 'auto_calculated':
                                    $arrConditions = $arrApplicantSettings['filters']['number'];
                                    break;

                                case 'date':
                                case 'date_repeatable':
                                    $arrConditions = $arrApplicantSettings['filters']['date'];
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
                                case 'case_status':
                                case 'country':
                                    $arrConditions = $arrApplicantSettings['filters']['combo'];
                                    break;

                                case 'checkbox':
                                    $arrConditions = $arrApplicantSettings['filters']['checkbox'];
                                    break;

                                case 'multiple_text_fields':
                                    $arrConditions = $arrApplicantSettings['filters']['multiple_text_fields'];
                                    break;

                                // case 'textfield':
                                // case 'special':
                                default:
                                    $arrConditions = $arrApplicantSettings['filters']['text'];
                                    break;
                            }

                            $booCorrectCondition = false;
                            foreach ($arrConditions as $arrConditionInfo) {
                                if ($arrConditionInfo[0] == $arrConditionSettingsToSave['based_on_field_condition']) {
                                    $booCorrectCondition = true;
                                    break;
                                }
                            }

                            if (!$booCorrectCondition) {
                                $strError = $this->_tr->translate('Incorrectly selected condition.');
                            }
                        }

                        if (empty($strError)) {
                            $fieldCondition = $arrConditionSettingsToSave['based_on_field_condition'];
                            switch ($fieldType) {
                                case 'short_date':
                                case 'date':
                                case 'date_repeatable':
                                    if (in_array($fieldCondition, array('is', 'is_not', 'is_before', 'is_after', 'is_between_today_and_date', 'is_between_date_and_today'))) {
                                        $arrConditionSettingsToSave['based_on_field_date'] = $this->params()->fromPost('based_on_field_date');
                                        if (!strtotime($arrConditionSettingsToSave['based_on_field_date'])) {
                                            $strError = $this->_tr->translate('Incorrectly selected date.');
                                        }
                                    } elseif ($fieldCondition == 'is_between_2_dates') {
                                        $arrConditionSettingsToSave['based_on_field_date_range_from'] = $this->params()->fromPost('based_on_field_date_range_from');
                                        $arrConditionSettingsToSave['based_on_field_date_range_to']   = $this->params()->fromPost('based_on_field_date_range_to');

                                        if (!strtotime($arrConditionSettingsToSave['based_on_field_date_range_from'])) {
                                            $strError = $this->_tr->translate('Incorrectly selected date range (date from).');
                                        }

                                        if (empty($strError) && !strtotime($arrConditionSettingsToSave['based_on_field_date_range_to'])) {
                                            $strError = $this->_tr->translate('Incorrectly selected date range (date to).');
                                        }

                                        if (empty($strError) && strtotime($arrConditionSettingsToSave['based_on_field_date_range_from']) > strtotime($arrConditionSettingsToSave['based_on_field_date_range_to'])) {
                                            $strError = $this->_tr->translate('Date from must be less than date to.');
                                        }
                                    } elseif (in_array($fieldCondition, array('is_in_the_next', 'is_in_the_previous'))) {
                                        $arrConditionSettingsToSave['based_on_field_date_number'] = $this->params()->fromPost('based_on_field_date_number');
                                        $arrConditionSettingsToSave['based_on_field_date_period'] = $this->params()->fromPost('based_on_field_date_period');

                                        if (!is_numeric($arrConditionSettingsToSave['based_on_field_date_number']) || $arrConditionSettingsToSave['based_on_field_date_number'] < 0) {
                                            $strError = $this->_tr->translate('Incorrect number.');
                                        }

                                        if (empty($strError) && !in_array($arrConditionSettingsToSave['based_on_field_date_period'], $arrConditionSettings['condition_date_next_filter'])) {
                                            $strError = $this->_tr->translate('Incorrect date number period.');
                                        }
                                    }
                                    break;

                                case 'combo':
                                case 'agents':
                                case 'office':
                                case 'office_multi':
                                case 'assigned_to':
                                case 'staff_responsible_rma':
                                case 'categories':
                                case 'case_status':
                                case 'contact_sales_agent':
                                case 'authorized_agents':
                                case 'employer_contacts':
                                case 'country':
                                    if (in_array($fieldCondition, array('is_one_of', 'is_none_of'))) {
                                        $arrConditionSettingsToSave['based_on_field_multiple_combo'] = $this->params()->fromPost('based_on_field_multiple_combo');
                                        $arrMultipleOptions = explode(';', $arrConditionSettingsToSave['based_on_field_multiple_combo'] ?? '');
                                        $booMultipleCombo = true;
                                    } else {
                                        $arrConditionSettingsToSave['based_on_field_combo'] = $this->params()->fromPost('based_on_field_combo');
                                        $arrMultipleOptions = array();
                                        $booMultipleCombo = false;
                                    }

                                    switch ($fieldType) {
                                        case 'combo':
                                            $arrOptions = $arrApplicantSettings['options'][$arrConditionSettingsToSave['based_on_field_member_type']][$arrConditionSettingsToSave['based_on_field_field_id']];
                                            break;

                                        case 'office_multi':
                                            $arrOptions = $arrApplicantSettings['options']['general']['office'];
                                            break;

                                        case 'case_status':
                                            $arrOptions = $arrApplicantSettings['options']['general']['case_statuses']['all'];
                                            break;

                                        default:
                                            $arrOptions = $arrApplicantSettings['options']['general'][$fieldType];
                                            break;
                                    }

                                    $booFoundOption = false;
                                    foreach ($arrOptions as $arrOptionInfo) {
                                        if ($booMultipleCombo) {
                                            if (in_array($arrOptionInfo['option_id'], $arrMultipleOptions)) {
                                                $booFoundOption = true;
                                            }
                                            $arrMultipleOptions = explode(';', $arrConditionSettingsToSave['based_on_field_multiple_combo'] ?? '');
                                        } elseif ($arrOptionInfo['option_id'] == $arrConditionSettingsToSave['based_on_field_combo']) {
                                            $booFoundOption = true;
                                            break;
                                        }
                                    }

                                    if (!$booFoundOption) {
                                        $strError = $this->_tr->translate('Incorrectly selected combobox option.');
                                    }
                                    break;

                                // case 'text':
                                // case 'password':
                                // case 'email':
                                // case 'phone':
                                // case 'memo':
                                // case 'float':
                                // case 'number':
                                // case 'auto_calculated':
                                default:
                                    if (in_array($fieldType, array('float', 'number', 'auto_calculated')) || in_array($fieldCondition, array('contains', 'does_not_contain', 'is', 'is_not', 'starts_with', 'ends_with'))) {
                                        $arrConditionSettingsToSave['based_on_field_textfield'] = $this->params()->fromPost('based_on_field_textfield');
                                    } else if (in_array($fieldCondition, array('is_one_of', 'is_none_of'))) {
                                        $arrConditionSettingsToSave['based_on_field_textarea'] = $this->params()->fromPost('based_on_field_textarea');
                                    }
                                    break;
                            }
                        }
                        break;

                    case 'CHANGED_FIELD':
                        $arrConditionSettingsToSave = array(
                            'changed_field_member_type' => $this->params()->fromPost('changed_field_member_types_combo'),
                            'changed_field_field_id'    => $this->params()->fromPost('changed_field_fields_combo')
                        );

                        $arrSupportedMemberTypes = array();
                        foreach ($arrApplicantSettings['search_for'] as $searchForInfo) {
                            if ($searchForInfo['search_for_id'] != 'accounting') {
                                $arrSupportedMemberTypes[] = $searchForInfo['search_for_id'];
                            }
                        }
                        if (!in_array($arrConditionSettingsToSave['changed_field_member_type'], $arrSupportedMemberTypes)) {
                            $strError = $this->_tr->translate('Incorrectly selected member type.');
                        }

                        if (empty($strError)) {
                            $booFoundField = false;

                            switch ($arrConditionSettingsToSave['changed_field_member_type']) {
                                case 'case':
                                    foreach ($arrApplicantSettings['case_group_templates'] as $arrGroups) {
                                        foreach ($arrGroups as $arrGroupedFields) {
                                            foreach ($arrGroupedFields['fields'] as $arrFieldInfo) {
                                                if ($arrFieldInfo['field_id'] == $arrConditionSettingsToSave['changed_field_field_id']) {
                                                    $booFoundField = true;
                                                    break 3;
                                                }
                                            }
                                        }
                                    }
                                    break;

                                default:
                                    foreach ($arrApplicantSettings['groups_and_fields'][$arrConditionSettingsToSave['changed_field_member_type']] as $arrBlockInfo) {
                                        foreach ($arrBlockInfo['fields'] as $arrFieldsGroups) {
                                            foreach ($arrFieldsGroups['fields'] as $arrFieldInfo) {
                                                if ($arrFieldInfo['field_id'] == $arrConditionSettingsToSave['changed_field_field_id']) {
                                                    $booFoundField = true;
                                                    break 3;
                                                }
                                            }
                                        }
                                    }
                                    break;
                            }

                            if (!$booFoundField) {
                                $strError = $this->_tr->translate('Incorrectly selected field.');
                            }
                        }
                        break;

                    case 'CASE_TYPE':
                        $arrConditionSettingsToSave = array(
                            'case_type' => $this->params()->fromPost('case_type')
                        );

                        $arrCaseTemplates = $this->_clients->getCaseTemplates()->getTemplates($this->_auth->getCurrentUserCompanyId(), true);

                        if (!in_array($arrConditionSettingsToSave['case_type'], $arrCaseTemplates)) {
                            $caseTypeTerm = $this->_company->getCurrentCompanyDefaultLabel('case_type');
                            $strError     = $this->_tr->translate('Incorrectly selected') . ' ' . $caseTypeTerm;
                        }
                        break;

                    case 'CASE_HAS_FORM':
                        $arrConditionSettingsToSave = array(
                            'form_id' => (int)$this->params()->fromPost('form_id')
                        );

                        $arrFormVersions = $this->_forms->getFormVersion()->getFormVersionsWithUniqueFormId();

                        $booCorrectCondition = false;
                        foreach ($arrFormVersions as $formVersion) {
                            if ($formVersion['form_id'] == $arrConditionSettingsToSave['form_id']) {
                                $booCorrectCondition = true;
                                break;
                            }
                        }

                        if (!$booCorrectCondition) {
                            $strError = $this->_tr->translate('Incorrectly selected form.');
                        }
                        break;

                    default:
                        $strError = $this->_tr->translate('Unsupported condition type.');
                        break;

                }
            }

            if (empty($strError)) {
                $reminderConditionId = $this->_automaticReminders->getConditions()->save(
                    $this->_auth->getCurrentUserCompanyId(),
                    $reminderId,
                    $reminderConditionId,
                    $this->_automaticReminders->getConditions()->getConditionTypeIdByTextId($conditionType),
                    $arrConditionSettingsToSave
                );

                if (!$reminderConditionId) {
                    $strError = $this->_tr->translate('Internal error.');
                }
            }

        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'      => empty($strError),
            'msg'          => $strError,
            'condition_id' => $reminderConditionId
        );
        return new JsonModel($arrResult);
    }

    public function deleteAction()
    {
        $strError = '';
        try {
            $reminderId  = (int)$this->params()->fromPost('reminder_id');
            $conditionId = (int)Json::decode($this->params()->fromPost('condition_id'), Json::TYPE_ARRAY);

            if (empty($strError) && !empty($reminderId) && !$this->_automaticReminders->hasAccessToReminder($reminderId)) {
                $strError = $this->_tr->translate('Insufficient access rights to reminder.');
            }

            if (empty($strError) && !$this->_automaticReminders->getConditions()->hasAccessToCondition($conditionId)) {
                $strError = $this->_tr->translate('Insufficient access rights to reminder.');
            }

            if (empty($strError) && !$this->_automaticReminders->getConditions()->delete($this->_auth->getCurrentUserCompanyId(), $reminderId, $conditionId)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = [
            'success' => empty($strError),
            'error'   => $strError
        ];

        return new JsonModel($arrResult);
    }
}