<?php

namespace Officio\Service\AutomaticReminders;

use Clients\Service\Clients;
use Clients\Service\Members;
use DateInterval;
use DateTime;
use Exception;
use Forms\Service\Forms;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Officio\Common\Json;
use Officio\Common\Service\BaseService;
use Officio\Service\AutomaticReminders;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Common\Service\Settings;
use Officio\Common\SubServiceInterface;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class Conditions extends BaseService implements SubServiceInterface
{

    /** @var Clients */
    protected $_clients;

    /** @var Company */
    protected $_company;

    /** @var Country */
    protected $_country;

    /** @var Forms */
    protected $_forms;

    /** @var AutomaticReminders */
    private $_parent;

    public function initAdditionalServices(array $services)
    {
        $this->_clients = $services[Clients::class];
        $this->_company = $services[Company::class];
        $this->_country = $services[Country::class];
        $this->_forms   = $services[Forms::class];
    }

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent() {
        return $this->_parent;
    }

    /**
     * Check if current user access to specific condition
     *
     * @param int $reminderConditionId
     * @return bool true if the user has access
     */
    public function hasAccessToCondition($reminderConditionId)
    {
        $booHasAccess = false;
        try {
            $arrConditionInfo = $this->getCondition(0, 0, $reminderConditionId);
            if ($this->_auth->isCurrentUserSuperadmin() || (isset($arrConditionInfo['company_id']) && $arrConditionInfo['company_id'] == $this->_auth->getCurrentUserCompanyId())) {
                $booHasAccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booHasAccess;
    }

    /**
     * Load saved information of the condition
     *
     * @param $companyId
     * @param int $reminderId
     * @param int $conditionId
     * @return array
     */
    public function getCondition($companyId, $reminderId, $conditionId)
    {
        $arrWhere = [];
        $arrWhere['c.automatic_reminder_condition_id'] = (int)$conditionId;

        if (!empty($companyId)) {
            $arrWhere['c.company_id'] = (int)$companyId;
        }

        if (!empty($reminderId)) {
            $arrWhere['c.automatic_reminder_id'] = (int)$reminderId;
        }

        $select = (new Select())
            ->from(array('c' => 'automatic_reminder_conditions'))
            ->join(array('t' => 'automatic_reminder_condition_types'), 't.automatic_reminder_condition_type_id = c.automatic_reminder_condition_type_id', 'automatic_reminder_condition_type_internal_id', Select::JOIN_LEFT_OUTER)
            ->where($arrWhere);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load conditions list for list of reminders
     *
     * @param array $arrReminderIds
     * @param null $companyId
     * @return array
     */
    public function getRemindersConditions($arrReminderIds, $companyId = null)
    {
        $arrConditions = array();

        if (is_array($arrReminderIds) && count($arrReminderIds)) {
            $arrWhere = [];
            $arrWhere['c.automatic_reminder_id'] = $arrReminderIds;

            if (!is_null($companyId)) {
                $arrWhere['c.company_id'] = (int)$companyId;
            }

            $select = (new Select())
                ->from(array('c' => 'automatic_reminder_conditions'))
                ->join(array('t' => 'automatic_reminder_condition_types'), 'c.automatic_reminder_condition_type_id = t.automatic_reminder_condition_type_id', 'automatic_reminder_condition_type_internal_id', Select::JOIN_LEFT_OUTER)
                ->where($arrWhere);

            $arrConditions = $this->_db2->fetchAll($select);
        }

        return $arrConditions;
    }


    /**
     * Load saved information of the condition
     *
     * @param $companyId
     * @param int $reminderId
     * @param array $conditionIds
     * @param bool $booClearOldConditions
     * @return array
     */
    public function getReminderConditions($companyId, $reminderId, $conditionIds = array(), $booClearOldConditions = false)
    {
        try {
            if ($booClearOldConditions) {
                $this->_db2->delete(
                    'automatic_reminder_conditions',
                    [
                        (new Where())
                            ->isNull('automatic_reminder_id')
                            ->and
                            ->lessThan('automatic_reminder_condition_create_date', date('Y-m-d')),
                        'company_id' => (int)$companyId
                    ]
                );
            }

            $arrReminderConditions = array();
            if (!empty($reminderId) || !empty($conditionIds)) {
                $arrWhere = [];
                $arrWhere['c.company_id'] = (int)$companyId;

                if (!empty($reminderId)) {
                    $arrWhere['c.automatic_reminder_id'] = (int)$reminderId;
                }

                if (!empty($conditionIds)) {
                    $arrWhere['c.automatic_reminder_condition_id'] = $conditionIds;
                }

                $select = (new Select())
                    ->from(array('c' => 'automatic_reminder_conditions'))
                    ->join(array('t' => 'automatic_reminder_condition_types'), 'c.automatic_reminder_condition_type_id = t.automatic_reminder_condition_type_id', 'automatic_reminder_condition_type_internal_id', Select::JOIN_LEFT_OUTER)
                    ->where($arrWhere);

                $arrReminderConditions = $this->_db2->fetchAll($select);
            }

            foreach ($arrReminderConditions as $key => $reminderCondition) {
                $arrReminderConditions[$key]['condition_id']   = $reminderCondition['automatic_reminder_condition_id'];
                $arrReminderConditions[$key]['condition_text'] = $this->getReadableCondition($reminderCondition);
            }
        } catch (Exception $e) {
            $arrReminderConditions = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrReminderConditions;
    }

    /**
     * Delete specific automatic reminder condition
     *
     * @param $companyId
     * @param $reminderId
     * @param int $conditionId
     * @return int
     */
    public function delete($companyId, $reminderId, $conditionId)
    {
        $arrWhere = [
            'company_id'                      => (int)$companyId,
            'automatic_reminder_condition_id' => (int)$conditionId
        ];

        if (empty($reminderId)) {
            $arrWhere[] = (new Where())->isNull('automatic_reminder_id');
        } else {
            $arrWhere['automatic_reminder_id'] = (int)$reminderId;
        }

        return $this->_db2->delete(
            'automatic_reminder_conditions',
            $arrWhere
        );
    }

    /**
     * Check if reminder has conditions "Changed field is"
     *
     * @param $reminderId
     * @return bool
     */
    public function hasChangedFieldConditions($reminderId)
    {
        $select = (new Select())
            ->from('automatic_reminder_conditions')
            ->where([
                'automatic_reminder_id'                => (int)$reminderId,
                'automatic_reminder_condition_type_id' => $this->getConditionTypeIdByTextId('CHANGED_FIELD')
            ]);

        $arrConditions = $this->_db2->fetchAll($select);

        return (count($arrConditions) > 0);
    }

    /**
     * Create/update condition
     *
     * @param $companyId
     * @param $reminderId
     * @param $reminderConditionId
     * @param $conditionTypeId
     * @param $arrConditionSettings
     * @return string
     */
    public function save($companyId, $reminderId, $reminderConditionId, $conditionTypeId, $arrConditionSettings)
    {
        try {
            $arrData = array(
                'company_id'                               => $companyId,
                'automatic_reminder_condition_type_id'     => $conditionTypeId,
                'automatic_reminder_condition_settings'    => Json::encode($arrConditionSettings),
                'automatic_reminder_condition_create_date' => date('Y-m-d H:i:s'),
            );

            if (empty($reminderConditionId)) {
                if (!empty($reminderId)) {
                    $arrData['automatic_reminder_id'] = $reminderId;
                }

                $reminderConditionId = $this->_db2->insert('automatic_reminder_conditions', $arrData);
            } else {
                unset($arrData['company_id'], $arrData['automatic_reminder_condition_create_date']);

                $this->_db2->update(
                    'automatic_reminder_conditions',
                    $arrData,
                    [
                        'automatic_reminder_id'           => (int)$reminderId,
                        'automatic_reminder_condition_id' => (int)$reminderConditionId
                    ]
                );
            }
        } catch (Exception $e) {
            $reminderConditionId = '';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $reminderConditionId;
    }


    /**
     * Create default automatic reminders for specific company
     *
     * @param $fromCompanyId
     * @param $toCompanyId
     * @param int $defaultReminderId
     * @param int $reminderId
     * @param array $arrCompanyDefaultSettings
     * @throws Exception
     */
    public function createDefaultAutomaticReminderConditions($fromCompanyId, $toCompanyId, $defaultReminderId, $reminderId, $arrCompanyDefaultSettings)
    {
        $arrDefaultAutomaticReminderConditions = $this->getReminderConditions($fromCompanyId, $defaultReminderId);

        $arrMappingClientGroupsAndFields = $arrCompanyDefaultSettings['arrMappingClientGroupsAndFields'];
        $arrMappingCaseGroupsAndFields   = $arrCompanyDefaultSettings['arrMappingCaseGroupsAndFields'];
        $arrMappingRoles                 = $arrCompanyDefaultSettings['arrMappingRoles'];
        $arrMappingDefaultCategories     = $arrCompanyDefaultSettings['arrMappingDefaultCategories'];
        $arrMappingDefaultCaseStatuses   = $arrCompanyDefaultSettings['arrMappingDefaultCaseStatuses'];
        $arrMappingCaseTemplates         = $arrCompanyDefaultSettings['arrMappingCaseTemplates'];

        // Update fields based on company settings
        foreach ($arrDefaultAutomaticReminderConditions as $arrConditionInfo) {
            $arrConditionSettings = Json::decode($arrConditionInfo['automatic_reminder_condition_settings'], Json::TYPE_ARRAY);

            $booCreateThisCondition = false;
            switch ($arrConditionInfo['automatic_reminder_condition_type_internal_id']) {
                case 'PROFILE':
                    if (isset($arrMappingCaseGroupsAndFields['mappingFields'][$arrConditionSettings['prof']])) {
                        $arrConditionSettings['prof'] = $arrMappingCaseGroupsAndFields['mappingFields'][$arrConditionSettings['prof']];
                        $booCreateThisCondition = true;
                    }
                    break;

                case 'CLIENT_PROFILE':
                    if (isset($arrMappingClientGroupsAndFields['mappingFields'][$arrConditionSettings['prof']])) {
                        $arrConditionSettings['prof'] = $arrMappingClientGroupsAndFields['mappingFields'][$arrConditionSettings['prof']];
                        $booCreateThisCondition = true;
                    }
                    break;

                case 'FILESTATUS':
                    if (isset($arrMappingCaseGroupsAndFields['mappingDefaults'][$arrConditionSettings['file_status']])) {
                        $arrConditionSettings['file_status'] = $arrMappingCaseGroupsAndFields['mappingDefaults'][$arrConditionSettings['file_status']];
                        $booCreateThisCondition = true;
                    }
                    break;

                case 'BASED_ON_FIELD':
                    $booCase = $arrConditionSettings['based_on_field_member_type'] == 'case';
                    $fieldType = '';

                    if ($booCase) {
                        if (isset($arrMappingCaseGroupsAndFields['mappingFields'][$arrConditionSettings['based_on_field_field_id']])) {
                            $arrConditionSettings['based_on_field_field_id'] = $arrMappingCaseGroupsAndFields['mappingFields'][$arrConditionSettings['based_on_field_field_id']];

                            $arrFieldInfo = $this->_clients->getFields()->getFieldsInfo(array($arrConditionSettings['based_on_field_field_id']));

                            if (isset($arrFieldInfo[$arrConditionSettings['based_on_field_field_id']])) {
                                $arrFieldInfo = $arrFieldInfo[$arrConditionSettings['based_on_field_field_id']];
                                if (isset($arrFieldInfo['type'])) {
                                    $fieldType = $this->_clients->getFieldTypes()->getStringFieldTypeById($arrFieldInfo['type']);
                                }
                            }
                        }

                    } elseif (isset($arrMappingClientGroupsAndFields['mappingFields'][$arrConditionSettings['based_on_field_field_id']])) {
                        $arrConditionSettings['based_on_field_field_id'] = $arrMappingClientGroupsAndFields['mappingFields'][$arrConditionSettings['based_on_field_field_id']];

                        $arrFieldInfo = $this->_clients->getApplicantFields()->getFieldsInfo(array($arrConditionSettings['based_on_field_field_id']));

                        if (isset($arrFieldInfo[$arrConditionSettings['based_on_field_field_id']])) {
                            $arrFieldInfo = $arrFieldInfo[$arrConditionSettings['based_on_field_field_id']];
                            if (isset($arrFieldInfo['type'])) {
                                $fieldType = $arrFieldInfo['type'];
                            }
                        }
                    }

                    if (empty($fieldType)) {
                        break;
                    }

                    $booMultipleCombo = in_array($arrConditionSettings['based_on_field_condition'], array('is_one_of', 'is_none_of'));

                    $arrDefaultFieldValues = array();
                    $arrCorrectFieldValues = array();

                    $booCreateThisCondition = true;

                    switch ($fieldType) {
                        case 'combo':
                            if ($booMultipleCombo) {
                                $arrDefaultFieldValues = explode(';', $arrConditionSettings['based_on_field_multiple_combo'] ?? '');
                            }
                            $arrFieldsMapping = $booCase ? $arrMappingCaseGroupsAndFields['mappingDefaults'] : $arrMappingClientGroupsAndFields['mappingDefaults'];
                            if (!$booMultipleCombo) {
                                if (isset($arrFieldsMapping[$arrConditionSettings['based_on_field_combo']])) {
                                    $arrConditionSettings['based_on_field_combo'] = $arrFieldsMapping[$arrConditionSettings['based_on_field_combo']];
                                } else {
                                    // Cannot be here
                                    $booCreateThisCondition = false;
                                }
                            } else {
                                foreach ($arrDefaultFieldValues as $fieldVal) {
                                    if (isset($arrFieldsMapping[$fieldVal])) {
                                        $arrCorrectFieldValues[] = $arrFieldsMapping[$fieldVal];
                                    }
                                }
                                if (!empty($arrCorrectFieldValues)) {
                                    $arrConditionSettings['based_on_field_multiple_combo'] = implode(';', $arrCorrectFieldValues);
                                } else {
                                    // Cannot be here
                                    $booCreateThisCondition = false;
                                }
                            }
                            break;

                        case 'categories':
                            if ($booMultipleCombo) {
                                $arrDefaultFieldValues = explode(';', $arrConditionSettings['based_on_field_multiple_combo'] ?? '');
                            }
                            if (!$booMultipleCombo) {
                                if (isset($arrMappingDefaultCategories[$arrConditionSettings['based_on_field_combo']])) {
                                    $arrConditionSettings['based_on_field_combo'] = $arrMappingDefaultCategories[$arrConditionSettings['based_on_field_combo']];
                                } else {
                                    // Cannot be here
                                    $booCreateThisCondition = false;
                                }
                            } else {
                                foreach ($arrDefaultFieldValues as $fieldVal) {
                                    if (isset($arrMappingDefaultCategories[$fieldVal])) {
                                        $arrCorrectFieldValues[] = $arrMappingDefaultCategories[$fieldVal];
                                    }
                                }
                                if (!empty($arrCorrectFieldValues)) {
                                    $arrConditionSettings['based_on_field_multiple_combo'] = implode(';', $arrCorrectFieldValues);
                                } else {
                                    // Cannot be here
                                    $booCreateThisCondition = false;
                                }
                            }
                            break;

                        case 'case_status':
                            if ($booMultipleCombo) {
                                $arrDefaultFieldValues = explode(';', $arrConditionSettings['based_on_field_multiple_combo'] ?? '');
                            }
                            if (!$booMultipleCombo) {
                                if (isset($arrMappingDefaultCaseStatuses[$arrConditionSettings['based_on_field_combo']])) {
                                    $arrConditionSettings['based_on_field_combo'] = $arrMappingDefaultCaseStatuses[$arrConditionSettings['based_on_field_combo']];
                                } else {
                                    // Cannot be here
                                    $booCreateThisCondition = false;
                                }
                            } else {
                                foreach ($arrDefaultFieldValues as $fieldVal) {
                                    if (isset($arrMappingDefaultCaseStatuses[$fieldVal])) {
                                        $arrCorrectFieldValues[] = $arrMappingDefaultCaseStatuses[$fieldVal];
                                    }
                                }
                                if (!empty($arrCorrectFieldValues)) {
                                    $arrConditionSettings['based_on_field_multiple_combo'] = implode(';', $arrCorrectFieldValues);
                                } else {
                                    // Cannot be here
                                    $booCreateThisCondition = false;
                                }
                            }
                            break;

                        case 'assigned_to':
                            if ($booMultipleCombo) {
                                $arrDefaultFieldValues = explode(';', $arrConditionSettings['based_on_field_multiple_combo'] ?? '');
                            }
                            $booCreateThisCondition = false;

                            if (!$booMultipleCombo) {
                                if (preg_match('/^role:(\d+)$/', $arrConditionSettings['based_on_field_combo'], $regs)) {
                                    if (isset($arrMappingRoles[$regs[1]])) {
                                        $arrConditionSettings['based_on_field_combo'] = 'role:' . $arrMappingRoles[$regs[1]];
                                        $booCreateThisCondition = true;
                                    }
                                }
                            } else {
                                foreach ($arrDefaultFieldValues as $fieldVal) {
                                    if (preg_match('/^role:(\d+)$/', $fieldVal, $regs)) {
                                        if (isset($arrMappingRoles[$regs[1]])) {
                                            $arrCorrectFieldValues[] = 'role:' . $arrMappingRoles[$regs[1]];
                                        }
                                    }
                                }

                                if (!empty($arrCorrectFieldValues)) {
                                    $arrConditionSettings['based_on_field_multiple_combo'] = implode(';', $arrCorrectFieldValues);
                                    $booCreateThisCondition = true;
                                }
                            }
                            break;

                        case 'country':
                            if ($booMultipleCombo) {
                                $arrDefaultFieldValues = explode(';', $arrConditionSettings['based_on_field_multiple_combo'] ?? '');
                            }
                            $arrCountries = $this->_country->getCountries(true);

                            if (!$booMultipleCombo) {
                                if (!(isset($arrCountries[$arrConditionSettings['based_on_field_combo']])
                                    || in_array($arrConditionSettings['based_on_field_combo'], $arrCountries))) {
                                    // Cannot be here
                                    $booCreateThisCondition = false;
                                }
                            } else {
                                foreach ($arrDefaultFieldValues as $fieldVal) {
                                    if (isset($arrCountries[$fieldVal]) || in_array($fieldVal, $arrCountries)) {
                                        $arrCorrectFieldValues[] = $fieldVal;
                                    }
                                }
                                if (!empty($arrCorrectFieldValues)) {
                                    $arrConditionSettings['based_on_field_multiple_combo'] = implode(';', $arrCorrectFieldValues);
                                } else {
                                    // Cannot be here
                                    $booCreateThisCondition = false;
                                }
                            }
                            break;

                        case 'agents':
                        case 'active_users':
                        case 'office':
                        case 'office_multi':
                        case 'staff_responsible_rma':
                        case 'contact_sales_agent':
                        case 'authorized_agents':
                        case 'employer_contacts':
                            // These field types are not supported
                            $booCreateThisCondition = false;
                            break;

                        default:
                            break;
                    }
                    break;

                case 'CHANGED_FIELD':
                    $booCase = $arrConditionSettings['changed_field_member_type'] == 'case';
                    if ($booCase) {
                        if (isset($arrMappingCaseGroupsAndFields['mappingFields'][$arrConditionSettings['changed_field_field_id']])) {
                            $arrConditionSettings['changed_field_field_id'] = $arrMappingCaseGroupsAndFields['mappingFields'][$arrConditionSettings['changed_field_field_id']];
                        }
                    } elseif (isset($arrMappingClientGroupsAndFields['mappingFields'][$arrConditionSettings['changed_field_field_id']])) {
                        $arrConditionSettings['changed_field_field_id'] = $arrMappingClientGroupsAndFields['mappingFields'][$arrConditionSettings['changed_field_field_id']];
                    }
                    $booCreateThisCondition = true;
                    break;

                case 'CASE_TYPE':
                    if (isset($arrMappingCaseTemplates[$arrConditionSettings['case_type']])) {
                        $arrConditionSettings['case_type'] = $arrMappingCaseTemplates[$arrConditionSettings['case_type']];
                        $booCreateThisCondition = true;
                    }
                    break;

                case 'CASE_HAS_FORM':
                    $booCreateThisCondition = true;
                    break;

                default:
                    // Incorrect condition type?
                    break;
            }

            if ($booCreateThisCondition) {
                $this->save(
                    $toCompanyId,
                    $reminderId,
                    0,
                    $arrConditionInfo['automatic_reminder_condition_type_id'],
                    $arrConditionSettings
                );
            }
        }
    }

    /**
     * Get string status of the specific condition
     *
     * @param array $arrConditionInfo
     * @return string
     */
    public function getReadableCondition($arrConditionInfo)
    {
        $arrConditionSettings = empty($arrConditionInfo['automatic_reminder_condition_settings']) ? array() : Json::decode($arrConditionInfo['automatic_reminder_condition_settings'], Json::TYPE_ARRAY);

        $number = $days = $ba = '';
        if (isset($arrConditionSettings['number'])) {
            $number = $arrConditionSettings['number'];
        }

        if (isset($arrConditionSettings['days'])) {
            $days = $this->_getConditionLabel($this->_getConditionComboDays(), $arrConditionSettings['days']);
        }

        if (isset($arrConditionSettings['ba'])) {
            $ba = $this->_getConditionLabel($this->_getConditionBeforeAfter(), $arrConditionSettings['ba']);
        }

        $strReadableCondition = 'Unknown condition type';
        switch ($arrConditionInfo['automatic_reminder_condition_type_internal_id']) {
            case 'CLIENT_PROFILE':
                $suffix               = $this->_clients->getApplicantFields()->getFieldName($arrConditionSettings['prof']);
                $strReadableCondition = $suffix . ' ' . $ba . ' ' . $number . ' ' . $days;
                break;

            case 'PROFILE':
                $suffix               = $this->_clients->getFields()->getFieldName($arrConditionSettings['prof']);
                $strReadableCondition = $suffix . ' ' . $ba . ' ' . $number . ' ' . $days;
                break;

            case 'FILESTATUS':
                $arrCaseStatusInfo    = $this->_clients->getCaseStatuses()->getCompanyCaseStatusInfo($arrConditionSettings['file_status']);
                $suffix               = $this->_clients->getFields()->getCaseStatusFieldLabel() . ' is changed to "' . $arrCaseStatusInfo['client_status_name'] . '"';
                $strReadableCondition = $number . ' ' . $days . ' ' . $ba . ' ' . $suffix;
                break;

            case 'BASED_ON_FIELD':
                $memberType = $arrConditionSettings['based_on_field_member_type'];
                $booCase    = $memberType == 'case';
                if ($booCase) {
                    $arrFieldInfo = $this->_clients->getFields()->getFieldsInfo(array($arrConditionSettings['based_on_field_field_id']));
                    if (isset($arrFieldInfo[$arrConditionSettings['based_on_field_field_id']])) {
                        $arrFieldInfo = $arrFieldInfo[$arrConditionSettings['based_on_field_field_id']];

                        $fieldLabel = $arrFieldInfo['label'];
                        $fieldType  = $this->_clients->getFieldTypes()->getStringFieldTypeById($arrFieldInfo['type']);
                    } else {
                        // Can't be here...
                        return 'FIELD NOT FOUND';
                    }
                } else {
                    $arrFieldInfo = $this->_clients->getApplicantFields()->getFieldsInfo($arrConditionSettings['based_on_field_field_id']);
                    if (isset($arrFieldInfo[$arrConditionSettings['based_on_field_field_id']])) {
                        $arrFieldInfo = $arrFieldInfo[$arrConditionSettings['based_on_field_field_id']];
                        $fieldLabel   = $arrFieldInfo['label'];
                        $fieldType    = $arrFieldInfo['type'];
                    } else {
                        // Can't be here...
                        return 'FIELD NOT FOUND';
                    }
                }

                $fieldCondition         = $arrConditionSettings['based_on_field_condition'];
                $fieldConditionReadable = $this->_clients->getApplicantFields()->getSearchFilterLabelByTypeAndId(
                    $fieldType,
                    $arrConditionSettings['based_on_field_condition'],
                    true
                );

                $fieldValue = '';
                switch ($fieldType) {
                    case 'short_date':
                    case 'date':
                    case 'date_repeatable':
                        if (in_array($fieldCondition, array('is', 'is_not', 'is_before', 'is_after', 'is_between_today_and_date', 'is_between_date_and_today'))) {
                            $fieldValue = $arrConditionSettings['based_on_field_date'];
                        } elseif ($fieldCondition == 'is_between_2_dates') {
                            $fieldValue = $arrConditionSettings['based_on_field_date_range_from'] . ' & ' . $arrConditionSettings['based_on_field_date_range_to'];
                        } elseif (in_array($fieldCondition, array('is_in_the_next', 'is_in_the_previous'))) {
                            $fieldValue = $arrConditionSettings['based_on_field_date_number'] . ' ' . $this->_getConditionLabel($this->_getConditionDateNumberFilters(), $arrConditionSettings['based_on_field_date_period']);
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
                            $arrFieldValues = explode(';', $arrConditionSettings['based_on_field_multiple_combo'] ?? '');
                            foreach ($arrFieldValues as $value) {
                                list($readableValue,) = $this->_clients->getFields()->getFieldReadableValue(
                                    $arrConditionInfo['company_id'],
                                    $this->_auth->getCurrentUserDivisionGroupId(),
                                    $arrFieldInfo,
                                    $value,
                                    !$booCase
                                );
                                $fieldValue .= $readableValue;
                                if ($value !== end($arrFieldValues)) {
                                    $fieldValue .= ', ';
                                }
                            }
                        } else {
                            list($fieldValue,) = $this->_clients->getFields()->getFieldReadableValue(
                                $arrConditionInfo['company_id'],
                                $this->_auth->getCurrentUserDivisionGroupId(),
                                $arrFieldInfo,
                                $arrConditionSettings['based_on_field_combo'],
                                !$booCase
                            );
                        }
                        break;

                    case 'yes_no':
                    case 'checkbox':
                        $fieldValue = '';
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
                        if (in_array($fieldCondition, array('is_one_of', 'is_none_of'))) {
                            $fieldValue = $arrConditionSettings['based_on_field_textarea'] ?? '';
                            $fieldValue = preg_replace("/\r\n|\n|\r/", ", ", $fieldValue);
                        } else {
                            $fieldValue = $arrConditionSettings['based_on_field_textfield'] ?? '';
                        }
                        break;
                }

                $strReadableCondition = trim(sprintf(
                    '%s for %s %s %s',
                    $fieldLabel,
                    ucfirst($memberType),
                    $fieldConditionReadable,
                    $fieldValue
                ));
                break;

            case 'CHANGED_FIELD':
                $memberType = $arrConditionSettings['changed_field_member_type'];
                $booCase    = $memberType == 'case';
                if ($booCase) {
                    $arrFieldInfo = $this->_clients->getFields()->getFieldsInfo(array($arrConditionSettings['changed_field_field_id']));
                } else {
                    $arrFieldInfo = $this->_clients->getApplicantFields()->getFieldsInfo($arrConditionSettings['changed_field_field_id']);
                }

                if (isset($arrFieldInfo[$arrConditionSettings['changed_field_field_id']])) {
                    $arrFieldInfo = $arrFieldInfo[$arrConditionSettings['changed_field_field_id']];

                    $strReadableCondition = trim(sprintf(
                        'Changed field is %s for %s',
                        $arrFieldInfo['label'],
                        ucfirst($memberType)
                    ));
                } else {
                    // Can't be here...
                    $strReadableCondition = 'FIELD NOT FOUND';
                }
                break;

            case 'CASE_TYPE':
                $caseTemplateInfo = $this->_clients->getCaseTemplates()->getTemplateInfo($arrConditionSettings['case_type']);
                if (isset($caseTemplateInfo['client_type_name'])) {
                    $caseTypeTerm         = $this->_company->getCurrentCompanyDefaultLabel('case_type');
                    $strReadableCondition = $caseTypeTerm . ' is ' . $caseTemplateInfo['client_type_name'];
                }
                break;

            case 'CASE_HAS_FORM':
                $formVersionInfo = $this->_forms->getFormVersion()->getLatestFormInfo($arrConditionSettings['form_id']);
                if (isset($formVersionInfo['file_name'])) {
                    $strReadableCondition = 'Form assigned to a case is ' . $formVersionInfo['file_name'];
                }
                break;

            default:
                // Cannot be here
                break;
        }

        return $strReadableCondition;
    }

    /**
     * Load list of conditions in readable format for specific reminder
     *
     * @param $companyId
     * @param $reminderId
     * @return string
     */
    public function getReadableReminderConditions($companyId, $reminderId)
    {
        $arrConditions = $this->getReminderConditions($companyId, $reminderId);

        if (empty($arrConditions)) {
            $strReadableConditions = '<span style="color: red;">There are no defined conditions</span>';
        } else {
            $strReadableConditions = '';
            foreach ($arrConditions as $arrConditionInfo) {
                if (!empty($strReadableConditions)) {
                    $strReadableConditions .= '<br/>';
                }
                $strReadableConditions .= $this->getReadableCondition($arrConditionInfo);
            }
        }

        return $strReadableConditions;
    }

    /**
     * Assign condition(s) to reminder (if they were not assigned yet)
     *
     * @param int $companyId
     * @param int $reminderId
     * @param array $arrConditionIds
     * @return bool
     */
    public function assignToReminder($companyId, $reminderId, $arrConditionIds)
    {
        try {
            if (count($arrConditionIds)) {
                $this->_db2->update(
                    'automatic_reminder_conditions',
                    ['automatic_reminder_id' => $reminderId],
                    [
                        'company_id'                      => $companyId,
                        'automatic_reminder_condition_id' => $arrConditionIds,
                        'automatic_reminder_id'           => null
                    ]
                );
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Load internal text id of condition type by its int id
     *
     * @param int $intConditionType
     * @return string
     */
    public function getConditionTypeTextIdById($intConditionType)
    {
        $strConditionType  = '';
        $arrConditionTypes = $this->_getConditionTypes();
        foreach ($arrConditionTypes as $arrConditionTypeInfo) {
            if ($intConditionType == $arrConditionTypeInfo['automatic_reminder_condition_type_id']) {
                $strConditionType = $arrConditionTypeInfo['automatic_reminder_condition_type_internal_id'];
                break;
            }
        }

        return $strConditionType;
    }

    /**
     * Load int id of condition type by its text id
     *
     * @param $strConditionType
     * @return int
     */
    public function getConditionTypeIdByTextId($strConditionType)
    {
        $intTypeId         = 0;
        $arrConditionTypes = $this->_getConditionTypes();
        foreach ($arrConditionTypes as $arrConditionTypeInfo) {
            if ($strConditionType == $arrConditionTypeInfo['automatic_reminder_condition_type_internal_id']) {
                $intTypeId = $arrConditionTypeInfo['automatic_reminder_condition_type_id'];
                break;
            }
        }

        return $intTypeId;
    }

    /**
     * Load list of condition types
     *
     * @return array
     */
    private function _getConditionTypes()
    {
        $id = 'auto_reminder_condition_types';
        if (!($data = $this->_cache->getItem($id))) {
            // Not in cache
            $select = (new Select())
                ->from('automatic_reminder_condition_types')
                ->order('automatic_reminder_condition_type_order');

            $data = $this->_db2->fetchAll($select);
            $this->_cache->setItem($id, $data);
        }

        return $data;
    }

    /**
     * Load list of conditions related to "date number filter"
     *
     * @return array
     */
    private function _getConditionDateNumberFilters()
    {
        return array(
            array('field_id' => 'D', 'label' => 'Calendar Day(s)'),
            array('field_id' => 'BD', 'label' => 'Business Day(s)'),
            array('field_id' => 'W', 'label' => 'Week(s)'),
            array('field_id' => 'M', 'label' => 'Month(s)'),
            array('field_id' => 'Y', 'label' => 'Year(s)'),
        );
    }

    /**
     * Load list of conditions related to "days combo"
     *
     * @return array
     */
    private function _getConditionComboDays()
    {
        return array(
            array('field_id' => 'CALENDAR', 'label' => 'Calendar days'),
            array('field_id' => 'BUSINESS', 'label' => 'Business days'),
        );
    }

    /**
     * Load list of conditions related to "before/after combo"
     *
     * @return array
     */
    private function _getConditionBeforeAfter()
    {
        return array(
            array('field_id' => 'AFTER', 'label' => 'less the period is prior to today'),
            array('field_id' => 'BEFORE', 'label' => 'is in the next (or already passed)'),
        );
    }

    /**
     * Get filter label by its filter id
     *
     * @param $arrFilters
     * @param $filterId
     * @return string
     */
    private function _getConditionLabel($arrFilters, $filterId)
    {
        $filterLabel = '';
        foreach ($arrFilters as $arrFilterInfo) {
            if ($arrFilterInfo['field_id'] == $filterId) {
                $filterLabel = $arrFilterInfo['label'];
                break;
            }
        }

        return $filterLabel;
    }

    /**
     * Load settings required to show condition's details
     *
     * @param bool $booIdsOnly - used during data checking and saving
     * @return array
     */
    public function getConditionSettings($booIdsOnly = false)
    {
        $booSuperAdmin = $this->_auth->isCurrentUserSuperadmin();
        $companyId     = $this->_auth->getCurrentUserCompanyId();

        $arrSettings = array(
            'file_status'        => $this->_clients->getFields()->getFileStatusOptions($companyId),
            'case_types'         => $this->_clients->getCaseTemplates()->getTemplates($companyId),
            'forms'              => $this->_forms->getFormVersion()->getFormVersionsWithUniqueFormId(),
            'case_date_fields'   => $this->_clients->getFields()->getDateFields($booSuperAdmin),
            'client_date_fields' => $this->_clients->getApplicantFields()->getDateFields($booSuperAdmin),
            'filters'            => $this->_clients->getApplicantFields()->getSearchFiltersList(true),

            'calendar_combo_days'         => $this->_getConditionComboDays(),
            'calendar_combo_before_after' => $this->_getConditionBeforeAfter(),
            'condition_date_next_filter'  => $this->_getConditionDateNumberFilters(),
            'condition_types'             => $this->_getConditionTypes(),
        );

        if ($booIdsOnly) {
            $arrResult = array();

            foreach ($arrSettings['file_status'] as $arrFileStatusInfo) {
                $arrResult['file_status'][] = $arrFileStatusInfo['id'];
            }

            foreach ($arrSettings['case_date_fields'] as $arrCaseDateFieldInfo) {
                $arrResult['case_date_fields'][] = $arrCaseDateFieldInfo['field_id'];
            }

            foreach ($arrSettings['client_date_fields'] as $arrClientDateFieldInfo) {
                $arrResult['client_date_fields'][] = $arrClientDateFieldInfo['field_id'];
            }

            foreach ($arrSettings['calendar_combo_days'] as $arrOptionInfo) {
                $arrResult['calendar_combo_days'][] = $arrOptionInfo['field_id'];
            }

            foreach ($arrSettings['calendar_combo_before_after'] as $arrOptionInfo) {
                $arrResult['calendar_combo_before_after'][] = $arrOptionInfo['field_id'];
            }

            foreach ($arrSettings['condition_date_next_filter'] as $arrOptionInfo) {
                $arrResult['condition_date_next_filter'][] = $arrOptionInfo['field_id'];
            }

            foreach ($arrSettings['condition_types'] as $arrOptionInfo) {
                $arrResult['condition_types'][] = $arrOptionInfo['automatic_reminder_condition_type_internal_id'];
            }
        } else {
            $arrResult = $arrSettings;
        }

        return $arrResult;
    }

    /**
     * For list of reminders load their assigned conditions
     * and check if all conditions for each reminder are due/correct
     *
     * @param array $arrReminders
     * @param int $companyId
     * @return array
     */
    public function filterRemindersByDueConditions($arrReminders, $companyId)
    {
        $arrFilteredReminders = array();

        //Prevent duplication in automatic_reminder_schedule and automatic_reminder_processed
        foreach ($arrReminders as $key => $reminder) {
            $ids = $this->_filterMembersByProcessedReminders((int)$reminder['automatic_reminder_id'], array($reminder['member_id']));
            if (count($ids) == 0 ) {
                unset($arrReminders[$key]);
            }
        }

        // Group/extract ids to load all at once
        $arrReminderIds = array();
        foreach ($arrReminders as $arrReminderInfo) {
            $arrReminderIds[] = $arrReminderInfo['automatic_reminder_id'];
        }
        $arrReminderIds = array_unique($arrReminderIds);

        // Load conditions for ALL company reminders at once
        $arrRemindersConditions = $this->getRemindersConditions($arrReminderIds, $companyId);

        // Group them by reminders
        $arrGroupedConditions = array();
        foreach ($arrRemindersConditions as $arrConditionInfo) {
            $arrGroupedConditions[$arrConditionInfo['automatic_reminder_id']][] = $arrConditionInfo;
        }

        foreach ($arrReminders as $arrReminderInfo) {
            $booAllConditionsAreDue             = true;
            $arrReminderInfo['calculated_date'] = 0;

            if (isset($arrGroupedConditions[$arrReminderInfo['automatic_reminder_id']]) && count($arrGroupedConditions[$arrReminderInfo['automatic_reminder_id']])) {
                foreach ($arrGroupedConditions[$arrReminderInfo['automatic_reminder_id']] as $arrConditionInfo) {
                    if (isset($arrReminderInfo['field_id'])) {
                        $arrConditionInfo['field_id'] = $arrReminderInfo['field_id'];
                    }
                    list($booIsDue, $calculatedDate, $arrReminderInfo['save_to_processed']) = $this->isConditionDueForMember($arrReminderInfo['company_id'], $arrReminderInfo['member_id'], $arrConditionInfo, $arrReminderInfo['active_clients_only'] == 'Y');
                    $calculatedDate = empty($calculatedDate) ? 0 : strtotime($calculatedDate);
                    $arrReminderInfo['calculated_date'] = $arrReminderInfo['calculated_date'] < $calculatedDate ? $calculatedDate : $arrReminderInfo['calculated_date'];

                    // Don't do other checks if at least one condition failed
                    if (!$booIsDue) {
                        $booAllConditionsAreDue = false;
                        break;
                    }
                }
            }

            if ($booAllConditionsAreDue) {
                $arrReminderInfo['calculated_date'] = Settings::isDateEmpty($arrReminderInfo['calculated_date']) ? '' : date('Y-m-d', $arrReminderInfo['calculated_date']);
                
                $arrFilteredReminders[] = $arrReminderInfo;
            }
        }

        return $arrFilteredReminders;
    }

    /**
     * For list of reminders load their assigned conditions
     * and check if all conditions for each reminder are due/correct
     *
     * @param array $arrReminders
     * @return array
     */
    public function filterRemindersByDueConditionsForAllCompanies($arrReminders)
    {
        $arrFilteredReminders = array();

        // Group/extract ids to load all at once
        $arrReminderIds = array();
        foreach ($arrReminders as $arrReminderInfo) {
            $arrReminderIds[] = $arrReminderInfo['automatic_reminder_id'];
        }
        $arrReminderIds = Settings::arrayUnique($arrReminderIds);

        // Load conditions for ALL reminders at once (don't filter by companies)
        $arrRemindersConditions = $this->getRemindersConditions($arrReminderIds);

        // Group them by reminders
        $arrGroupedConditions = array();
        foreach ($arrRemindersConditions as $arrConditionInfo) {
            $arrGroupedConditions[$arrConditionInfo['automatic_reminder_id']][] = $arrConditionInfo;
        }

        foreach ($arrReminders as $arrReminderInfo) {
            if (!isset($arrGroupedConditions[$arrReminderInfo['automatic_reminder_id']])) {
                continue;
            }

            $booAllConditionsAreDue       = false;
            $arrThisReminderDueConditions = array();
            $booSaveToProcessed           = false;
            if (count($arrGroupedConditions[$arrReminderInfo['automatic_reminder_id']])) {
                $booAllConditionsAreDue = true;
                foreach ($arrGroupedConditions[$arrReminderInfo['automatic_reminder_id']] as $arrConditionInfo) {
                    $booActiveOnly = isset($arrReminderInfo['active_clients_only']) && $arrReminderInfo['active_clients_only'] == 'Y';

                    list($arrMembersAndDates, $booSaveToProcessedCondition) = $this->isConditionDueForCompany($arrReminderInfo['company_id'], $arrConditionInfo, $booActiveOnly);
                    $booSaveToProcessed = $booSaveToProcessed || $booSaveToProcessedCondition;

                    $booIsDue = count($arrMembersAndDates) > 0;
                    if ($booIsDue) {
                        $arrThisReminderDueConditions[] = $arrMembersAndDates;
                    } else {
                        // Don't do other checks if at least one condition failed
                        $booAllConditionsAreDue = false;
                        break;
                    }
                }
            }

            if ($booAllConditionsAreDue) {
                // Make sure that member is returned/filtered in ALL conditions
                $arrGroupedByMembers = array();
                foreach ($arrThisReminderDueConditions as $arrMembersAndDates) {
                    foreach ($arrMembersAndDates as $memberId => $dueDate) {
                        $arrGroupedByMembers[$memberId][] = empty($dueDate) ? time() : strtotime($dueDate);
                    }
                }

                // Use max calculated date for each member's condition
                $conditionsCount = count($arrThisReminderDueConditions);
                foreach ($arrGroupedByMembers as $memberId => $arrDates) {
                    if (count($arrDates) == $conditionsCount) {
                        $arrThisConditionParams = array(
                            'calculated_date'   => date('Y-m-d', max($arrDates)),
                            'save_to_processed' => $booSaveToProcessed,
                            'member_id'         => $memberId
                        );
                        $arrFilteredReminders[] = array_merge($arrReminderInfo, $arrThisConditionParams);
                    }
                }
            }
        }

        return $arrFilteredReminders;
    }

    /**
     * Load list of clients/cases that were already processed for a specific auto task
     *
     * @param int $reminderId
     * @param int|array $arrMembersIds
     * @param bool $booCheckDateRepeatableYear
     * @return array
     */
    private function _getReminderProcessedClients($reminderId, $arrMembersIds, $booCheckDateRepeatableYear = false)
    {
        $arrFilteredMemberIds = array();
        if (!empty($reminderId) && !empty($arrMembersIds)) {
            $arrWhere = [];

            $arrWhere['automatic_reminder_id'] = $reminderId;
            $arrWhere['member_id']             = $arrMembersIds;

            if ($booCheckDateRepeatableYear) {
                $arrWhere['rp.year'] = date('Y');
            }

            $select = (new Select())
                ->from(array('rp' => 'automatic_reminders_processed'))
                ->columns(['member_id'])
                ->where($arrWhere);

            $arrFilteredMemberIds = $this->_db2->fetchCol($select);
        }

        return $arrFilteredMemberIds;
    }


    /**
     * Filter members to be sure that reminder wasn't processed for them yet
     * Make sure that repeatable dates can be processed each year
     *
     * @param int $reminderId
     * @param array $arrMemberIds
     * @param bool $booCheckDateRepeatableYear
     * @return array
     */
    private function _filterMembersByProcessedReminders($reminderId, $arrMemberIds, $booCheckDateRepeatableYear = false)
    {
        $arrFilteredMemberIds = array();

        if (count($arrMemberIds)) {
            $where = [];
            $where['automatic_reminder_id'] = $reminderId;

            if ($booCheckDateRepeatableYear) {
                $where['rp.year'] = date('Y');
            }

            $subSelect = (new Select())
                ->from(array('rp' => 'automatic_reminders_processed'))
                ->columns(['member_id'])
                ->where($where);

            $select = (new Select())
                ->from(array('m' => 'members'))
                ->columns(['member_id'])
                ->where([
                    'm.status'    => 1,
                    'm.member_id' => $arrMemberIds,
                    (new Where())->notIn('m.member_id', $subSelect)
                ]);

            $arrFilteredMemberIds = $this->_db2->fetchCol($select);
        }

        return $arrFilteredMemberIds;
    }


    /**
     * Search for cases/clients for which condition is due (the condition is true)
     *
     * @param int $companyId
     * @param array $arrConditionInfo
     * @param bool $booActiveCasesOnly
     * @return array
     * @throws Exception
     */
    public function isConditionDueForCompany($companyId, $arrConditionInfo, $booActiveCasesOnly)
    {
        $arrMembersAndDates   = array();
        $booSaveToProcessed   = false;
        $arrConditionSettings = empty($arrConditionInfo['automatic_reminder_condition_settings']) ? array() : Json::decode($arrConditionInfo['automatic_reminder_condition_settings'], Json::TYPE_ARRAY);

        switch ($arrConditionInfo['automatic_reminder_condition_type_internal_id']) {
            case 'CLIENT_PROFILE':
                $arrFieldInfo = $this->_clients->getApplicantFields()->getFieldInfo($arrConditionSettings['prof'], $companyId, true);

                if (isset($arrFieldInfo['type'])) {
                    $arrClientsSavedData = $this->_clients->getApplicantFields()->getFieldData($arrConditionSettings['prof']);

                    if (count($arrClientsSavedData)) {
                        // Make sure that this reminder wasn't processed already for these clients
                        $arrMemberIds = array();
                        foreach ($arrClientsSavedData as $arrClientsSavedDataInfo) {
                            $arrMemberIds[] = $arrClientsSavedDataInfo['applicant_id'];
                        }

                        $arrMemberIds = array_unique($arrMemberIds);
                        $arrParents   = $this->_clients->getParentsForAssignedApplicants($arrMemberIds);
                        foreach ($arrMemberIds as $key => $memberId) {
                            if (isset($arrParents[$memberId])) {
                                $arrMemberIds[] = $arrParents[$memberId]['parent_member_id'];
                                unset($arrMemberIds[$key]);
                            }
                        }
                        $arrFilteredMemberIds = $this->_filterMembersByProcessedReminders(
                            (int)$arrConditionInfo['automatic_reminder_id'],
                            Settings::arrayUnique($arrMemberIds),
                            $arrFieldInfo['type'] == 'date_repeatable'
                        );

                        foreach ($arrClientsSavedData as $arrClientsSavedDataInfo) {
                            $applicantId = $arrClientsSavedDataInfo['applicant_id'];
                            if (isset($arrParents[$applicantId])) {
                                $applicantId = $arrParents[$applicantId]['parent_member_id'];
                            }

                            if (!in_array($applicantId, $arrFilteredMemberIds)) {
                                continue;
                            }

                            // Calculate due date
                            $calculatedDate = $this->calculateProfDate(
                                array(
                                    'days'       => $arrConditionSettings['days'],
                                    'number'     => $arrConditionSettings['number'],
                                    'ba'         => $arrConditionSettings['ba'],
                                    'prof'       => $arrConditionSettings['prof'],
                                    'prof-date'  => $arrClientsSavedDataInfo['value'],
                                    'field_type' => $arrClientsSavedDataInfo['type']
                                )
                            );

                            if (!Settings::isDateEmpty($calculatedDate) && strtotime($calculatedDate) <= strtotime("now")) {
                                $booUseThisApplicant = true;

                                // Make sure that this auto task wasn't processed for this client's cases
                                $arrAssignedCases = $this->_clients->getAssignedApplicants($applicantId, Members::getMemberType('case'));

                                // Filter inactive cases
                                if ($booActiveCasesOnly) {
                                    $arrAssignedCases = $this->_clients->getActiveClientsList($arrAssignedCases, true, $companyId);
                                    if (empty($arrAssignedCases)) {
                                        $booUseThisApplicant = false;
                                    }
                                }

                                if (!empty($arrAssignedCases)) {
                                    $arrProcessedCases = $this->_getReminderProcessedClients(
                                        (int)$arrConditionInfo['automatic_reminder_id'],
                                        $arrAssignedCases,
                                        $arrFieldInfo['type'] == 'date_repeatable'
                                    );

                                    if (!empty($arrProcessedCases)) {
                                        $booUseThisApplicant = false;
                                    }
                                }

                                if ($booUseThisApplicant) {
                                    $arrMembersAndDates[$applicantId] = $calculatedDate;
                                }
                            }
                        }
                    }

                    $booSaveToProcessed = true;
                }
                break;

            case 'PROFILE':
                $fieldId      = $arrConditionSettings['prof'];
                $arrFieldInfo = $this->_clients->getFields()->getFieldsInfo(array($fieldId));

                if (isset($arrFieldInfo[$fieldId])) {
                    $arrFieldInfo = $arrFieldInfo[$fieldId];
                    $fieldType    = $this->_clients->getFieldTypes()->getStringFieldTypeById($arrFieldInfo['type']);

                    $arrCasesSavedData = $this->_clients->getFields()->getFieldData($arrConditionSettings['prof']);

                    // Make sure that this reminder wasn't processed already for these cases
                    $arrMemberIds = array();
                    foreach ($arrCasesSavedData as $arrCasesSavedDataInfo) {
                        $arrMemberIds[] = $arrCasesSavedDataInfo['member_id'];
                    }

                    // Filter inactive cases
                    if ($booActiveCasesOnly && !empty($arrMemberIds)) {
                        $arrMemberIds = $this->_clients->getActiveClientsList($arrMemberIds, true, $companyId);
                    }

                    $arrFilteredMemberIds = $this->_filterMembersByProcessedReminders(
                        (int)$arrConditionInfo['automatic_reminder_id'],
                        Settings::arrayUnique($arrMemberIds),
                        $fieldType == 'date_repeatable'
                    );

                    foreach ($arrCasesSavedData as $arrCasesSavedDataInfo) {
                        if (!in_array($arrCasesSavedDataInfo['member_id'], $arrFilteredMemberIds)) {
                            continue;
                        }

                        // Calculate due date
                        $calculatedDate = $this->calculateProfDate(
                            array(
                                'member_id' => $arrCasesSavedDataInfo['member_id'],
                                'days'      => $arrConditionSettings['days'],
                                'number'    => $arrConditionSettings['number'],
                                'ba'        => $arrConditionSettings['ba'],
                                'prof'      => $arrConditionSettings['prof'],
                                'prof-date' => $arrCasesSavedDataInfo['value']
                            )
                        );

                        if (!Settings::isDateEmpty($calculatedDate) && strtotime($calculatedDate) <= strtotime("now")) {
                            $arrMembersAndDates[$arrCasesSavedDataInfo['member_id']] = $calculatedDate;
                        }
                    }

                    $booSaveToProcessed = true;
                }
                break;

            case 'FILESTATUS':
                $fileStatusFieldId = $this->_clients->getFields()->getCompanyFieldIdByUniqueFieldId('file_status', $companyId);
                if (!empty($fileStatusFieldId)) {
                    $arrCasesSavedData = $this->_clients->getFields()->getFieldData($fileStatusFieldId);

                    foreach ($arrCasesSavedData as $arrCasesSavedDataInfo) {
                        // Are the same case status options?
                        $arrSavedCaseStatuses = empty($arrCasesSavedDataInfo['value']) ? [] : explode(',', $arrCasesSavedDataInfo['value'] ?? '');
                        if (in_array($arrConditionSettings['file_status'], $arrSavedCaseStatuses)) {
                            $calculatedDate = $this->calculateProfDate(
                                array(
                                    'member_id' => $arrCasesSavedDataInfo['member_id'],
                                    'days'      => $arrConditionSettings['days'],
                                    'number'    => $arrConditionSettings['number'],
                                    'ba'        => $arrConditionSettings['ba'],
                                    'prof-date' => date('Y-m-d')
                                )
                            );

                            if (!Settings::isDateEmpty($calculatedDate) && strtotime($calculatedDate) <= strtotime('now')) {
                                $booCorrect = true;

                                // Filter inactive cases
                                if ($booActiveCasesOnly) {
                                    $booCorrect = !empty($this->_clients->getActiveClientsList([$arrCasesSavedDataInfo['member_id']], true, $companyId));
                                }

                                if ($booCorrect) {
                                    $arrMembersAndDates[$arrCasesSavedDataInfo['member_id']] = $calculatedDate;
                                }
                            }
                        }
                    }
                }
                break;

            case 'BASED_ON_FIELD':
                $booIsFieldBasedOnCase = $arrConditionSettings['based_on_field_member_type'] == 'case';

                if (in_array($arrConditionSettings['based_on_field_condition'], array('is_empty', 'does_not_contain'))) {
                    // Load all cases/clients - required for specific conditions
                    if ($booIsFieldBasedOnCase) {
                        // Get all cases
                        $arrMemberIdsToCheck = $this->_clients->getMembersByCompanyAndType($companyId, 'case');
                    } else {
                        // Get all clients
                        $arrMemberIdsToCheck = $this->_clients->getMembersByCompanyAndType($companyId, 'individual_employer_internal_contact');
                    }
                } else {
                    $fieldId             = $arrConditionSettings['based_on_field_field_id'];
                    $arrMemberIdsToCheck = array();
                    if ($booIsFieldBasedOnCase) {
                        // Load cases that have filled this specific field
                        $arrCasesOrClientsSavedData = $this->_clients->getFields()->getFieldData($fieldId);
                        foreach ($arrCasesOrClientsSavedData as $arrCaseOrClientSavedDataInfo) {
                            $arrMemberIdsToCheck[] = $arrCaseOrClientSavedDataInfo['member_id'];
                        }
                    } else {
                        // Load clients that have filled this specific field
                        $arrCasesOrClientsSavedData = $this->_clients->getApplicantFields()->getFieldData($fieldId);
                        foreach ($arrCasesOrClientsSavedData as $arrCaseOrClientSavedDataInfo) {
                            $arrMemberIdsToCheck[] = $arrCaseOrClientSavedDataInfo['applicant_id'];
                        }
                    }
                }

                foreach ($arrMemberIdsToCheck as $memberId) {
                    if (!empty($memberId)) {
                        list($booIsDueForCaseOrClient, $calculatedDate,) = $this->isConditionDueForMember($companyId, $memberId, $arrConditionInfo, $booActiveCasesOnly);
                        if ($booIsDueForCaseOrClient) {
                            $arrMembersAndDates[$memberId] = $calculatedDate;
                        }
                    }
                }
                break;

            case 'CASE_TYPE':
                $arrClients = $this->_clients->getClientsByCaseTemplateId($arrConditionSettings['case_type'], $companyId);

                // Get cases ids only
                $arrFoundCases = [];
                foreach ($arrClients as $arrClientInfo) {
                    $arrFoundCases[] = $arrClientInfo['member_id'];
                }

                // Filter inactive cases
                if ($booActiveCasesOnly && !empty($arrFoundCases)) {
                    $arrFoundCases = $this->_clients->getActiveClientsList($arrFoundCases, true, $companyId);
                }

                foreach ($arrFoundCases as $caseId) {
                    $arrMembersAndDates[$caseId] = '';
                }
                break;

            case 'CASE_HAS_FORM':
                $arrFormVersions   = $this->_forms->getFormVersion()->getFormVersionsByFormId($arrConditionSettings['form_id']);

                $arrFormVersionIds = [];
                foreach ($arrFormVersions as $formVersion) {
                    $arrFormVersionIds[] = $formVersion['form_version_id'];
                }

                $arrClientIds = $this->_forms->getFormAssigned()->getMembersByAssignedFormVersionIds($arrFormVersionIds);

                // Filter inactive cases
                if ($booActiveCasesOnly && !empty($arrClientIds)) {
                    $arrClientIds = $this->_clients->getActiveClientsList($arrClientIds, true, $companyId);
                }

                foreach ($arrClientIds as $clientId) {
                    $arrMembersAndDates[$clientId] = '';
                }
                break;

            default:
                break;
        }

        foreach ($arrMembersAndDates as $memberId => $date) {
            $memberTypeId       = $this->_clients->getMemberTypeByMemberId($memberId);
            $parentMemberId     = 0;
            $booInternalContact = false;

            if (in_array($memberTypeId, Members::getMemberType('internal_contact'))) {
                $booInternalContact = true;
                $arrParents         = $this->_clients->getParentsForAssignedApplicant($memberId);
                if (count($arrParents)) {
                    $parentMemberId = $arrParents[0];
                }
            } elseif (in_array($memberTypeId, Members::getMemberType('individual')) || in_array($memberTypeId, Members::getMemberType('employer'))) {
                $parentMemberId = $memberId;
            }

            if ($parentMemberId) {
                $arrMembersAndDates[$parentMemberId] = $date;

                // Don't load cases lists if this auto task is for the Client only
                if ($arrConditionInfo['automatic_reminder_condition_type_internal_id'] != 'CLIENT_PROFILE') {
                    $arrAssignedCaseIds = $this->_clients->getAssignedCases($parentMemberId);

                    // Filter inactive cases
                    if ($booActiveCasesOnly && !empty($arrAssignedCaseIds)) {
                        $arrAssignedCaseIds = $this->_clients->getActiveClientsList($arrAssignedCaseIds, true, $companyId);
                    }

                    if (!empty($arrAssignedCaseIds)) {
                        foreach ($arrAssignedCaseIds as $assignedCaseId) {
                            $arrMembersAndDates[$assignedCaseId] = $date;
                        }
                    }
                }

                if ($booInternalContact) {
                    unset($arrMembersAndDates[$memberId]);
                }
            }
        }

        return array($arrMembersAndDates, $booSaveToProcessed);
    }


    /**
     * Check if condition is due (the condition is true) for specific case/client
     *
     * @param int $companyId
     * @param int $memberId - case or client id
     * @param array $arrConditionInfo
     * @param bool $booActiveCasesOnly
     * @return array
     * @throws Exception
     */
    public function isConditionDueForMember($companyId, $memberId, $arrConditionInfo, $booActiveCasesOnly)
    {
        $booIsDue             = false;
        $calculatedDate       = '';
        $booSaveToProcessed   = false;
        $arrConditionSettings = empty($arrConditionInfo['automatic_reminder_condition_settings']) ? array() : Json::decode($arrConditionInfo['automatic_reminder_condition_settings'], Json::TYPE_ARRAY);

        $booDoCheck           = true;
        $memberTypeId         = $this->_clients->getMemberTypeByMemberId($memberId);
        $memberTypeName       = $this->_clients->getMemberTypeNameById($memberTypeId);
        $booIsMemberCase      = in_array($memberTypeId, Members::getMemberType('case'));
        $booIsInternalContact = in_array($memberTypeId, Members::getMemberType('internal_contact'));


        $caseId          = 0;
        $arrParents      = [];
        $arrApplicantIds = [];
        if ($booIsMemberCase) {
            $caseId = $memberId;

            list(, $arrApplicantIds) = $this->_clients->getParentsForAssignedCases(array($caseId));
            $arrParents = $this->_clients->getParentsForAssignedApplicants(array($memberId), false, false);

            if ($booActiveCasesOnly) {
                // Make sure that this case is active
                $arrActiveCasesIds = $this->_clients->getActiveClientsList([$caseId], true, $companyId);
                if (empty($arrActiveCasesIds)) {
                    $booDoCheck = false;
                }
            }
        } else {
            if ($booIsInternalContact) {
                // Load client ids for the internal contact
                $arrParents = $this->_clients->getParentsForAssignedApplicants(array($memberId), false, false);

                foreach ($arrParents as $arrParentInfo) {
                    $arrApplicantIds[] = $arrParentInfo['parent_member_id'];
                }
            } else {
                // This is a client
                $arrApplicantIds = [$memberId];
            }

            foreach ($arrApplicantIds as $parentClientId) {
                $arrAssignedCasesIds = $this->_clients->getAssignedCases($parentClientId);
                if (!empty($arrAssignedCasesIds)) {
                    if ($booActiveCasesOnly) {
                        // Check if there is at least one active case for the client
                        $arrActiveCasesIds = $this->_clients->getActiveClientsList($arrAssignedCasesIds, true, $companyId);
                        if (!empty($arrActiveCasesIds)) {
                            // Get the first active case
                            $caseId = $arrActiveCasesIds[0];
                            break;
                        }
                    } else {
                        // Get the first found case
                        $caseId = $arrAssignedCasesIds[0];
                        break;
                    }
                }
            }

            if ($booActiveCasesOnly && empty($caseId)) {
                $booDoCheck = false;
            }
        }


        if ($booDoCheck) {
            switch ($arrConditionInfo['automatic_reminder_condition_type_internal_id']) {
                case 'CLIENT_PROFILE':
                    $clientFieldValue = '';
                    foreach ($arrApplicantIds as $applicantId) {
                        $arrApplicantInfo = $this->_clients->getClientShortInfo($applicantId);
                        if (!empty($arrApplicantInfo)) {
                            $arrGroupedCompanyFields = $this->_clients->getApplicantFields()->getGroupedCompanyFields($companyId, $arrApplicantInfo['userType'], $arrApplicantInfo['applicant_type_id'], false);

                            foreach ($arrGroupedCompanyFields as $arrGroupInfo) {
                                if (!isset($arrGroupInfo['fields'])) {
                                    continue;
                                }

                                $fieldId = $arrConditionSettings['prof'];
                                foreach ($arrGroupInfo['fields'] as $arrCompanyFieldInfo) {
                                    if ($arrCompanyFieldInfo['field_id'] == $fieldId) {
                                        if ($arrGroupInfo['group_contact_block'] == 'Y') {
                                            $updateClientId = $this->_clients->getAssignedContact($applicantId, $arrGroupInfo['group_id']);
                                        } else {
                                            $updateClientId = $applicantId;
                                        }

                                        if (!empty($updateClientId)) {
                                            $clientFieldValue = $this->_clients->getApplicantFields()->getFieldDataValue($updateClientId, $fieldId);
                                        }
                                        break 3;
                                    }
                                }
                            }
                        }
                    }

                    if (!empty($clientFieldValue)) {
                        $arrFieldInfo = $this->_clients->getApplicantFields()->getFieldInfo($arrConditionSettings['prof'], $companyId, true);

                        if (isset($arrFieldInfo['type'])) {
                            // Calculate due date
                            $calculatedDate = $this->calculateProfDate(
                                array(
                                    'days'       => $arrConditionSettings['days'],
                                    'number'     => $arrConditionSettings['number'],
                                    'ba'         => $arrConditionSettings['ba'],
                                    'prof'       => $arrConditionSettings['prof'],
                                    'prof-date'  => $clientFieldValue,
                                    'field_type' => $arrFieldInfo['type']
                                )
                            );

                            if (!Settings::isDateEmpty($calculatedDate) && strtotime($calculatedDate) <= strtotime("now")) {
                                $booIsDue = true;
                                $booSaveToProcessed = true;
                            }
                        }
                    }
                    break;

                case 'PROFILE':
                    $caseFieldValue = $this->_clients->getFields()->getFieldDataValue($arrConditionSettings['prof'], $memberId);

                    if (!empty($caseFieldValue)) {
                        // Calculate due date
                        $calculatedDate = $this->calculateProfDate(
                            array(
                                'member_id' => $memberId,
                                'days'      => $arrConditionSettings['days'],
                                'number'    => $arrConditionSettings['number'],
                                'ba'        => $arrConditionSettings['ba'],
                                'prof'      => $arrConditionSettings['prof'],
                                'prof-date' => $caseFieldValue
                            )
                        );

                        if (!Settings::isDateEmpty($calculatedDate) && strtotime($calculatedDate) <= strtotime("now")) {
                            $booIsDue = true;
                            $booSaveToProcessed = true;
                        }
                    }
                    break;

                case 'FILESTATUS':
                    $fileStatusFieldId = $this->_clients->getFields()->getCompanyFieldIdByUniqueFieldId('file_status', $companyId);
                    if (!empty($fileStatusFieldId)) {
                        $caseFileStatus = $this->_clients->getFields()->getFieldDataValue($fileStatusFieldId, $memberId) ?? '';

                        // Are the same case status options?
                        $arrSavedCaseStatuses = empty($caseFileStatus) ? [] : explode(',', $caseFileStatus);
                        if (in_array($arrConditionSettings['file_status'], $arrSavedCaseStatuses)) {
                            $calculatedDate = $this->calculateProfDate(
                                array(
                                    'member_id' => $memberId,
                                    'days'      => $arrConditionSettings['days'],
                                    'number'    => $arrConditionSettings['number'],
                                    'ba'        => $arrConditionSettings['ba'],
                                    'prof-date' => date("Y-m-d")
                                )
                            );

                            if (!Settings::isDateEmpty($calculatedDate)) {
                                $booIsDue = true;
                            }
                        }
                    }
                    break;

                case 'BASED_ON_FIELD':
                    $booIsFieldBasedOnCase = $arrConditionSettings['based_on_field_member_type'] == 'case';

                    if ($booIsFieldBasedOnCase) {
                        // Field's condition is based on the case's field
                        // If this is not a case - find assigned case and use him
                        if (!$booIsMemberCase) {
                            $memberId = $caseId;
                        }
                    } elseif ($booIsMemberCase || $booIsInternalContact) {
                        // Field's condition is based on the client's field
                        // If this is not a client - find case's parent with specific type and use him
                        $memberId = 0;
                        foreach ($arrParents as $arrParentInfo) {
                            if ($arrParentInfo['member_type_name'] == $arrConditionSettings['based_on_field_member_type']) {
                                $memberId = $arrParents[$memberId]['parent_member_id'];
                                break;
                            }
                        }
                    }

                    $fieldType       = null;
                    $savedFieldValue = '';
                    if (!empty($memberId)) {
                        $fieldId = $arrConditionSettings['based_on_field_field_id'];

                        if ($booIsFieldBasedOnCase) {
                            $savedFieldValue = $this->_clients->getFields()->getFieldDataValue($fieldId, $memberId);
                            $arrFieldInfo    = $this->_clients->getFields()->getFieldsInfo(array($fieldId));

                            if (isset($arrFieldInfo[$fieldId])) {
                                $arrFieldInfo = $arrFieldInfo[$fieldId];

                                $fieldType = $this->_clients->getFieldTypes()->getStringFieldTypeById($arrFieldInfo['type']);
                            }

                        } else {
                            $arrClientInfo = $this->_clients->getClientShortInfo($memberId);
                            if (!empty($arrClientInfo)) {
                                $arrGroupedCompanyFields = $this->_clients->getApplicantFields()->getGroupedCompanyFields($companyId, $arrClientInfo['userType'], $arrClientInfo['applicant_type_id'], false);

                                foreach ($arrGroupedCompanyFields as $arrGroupInfo) {
                                    if (!isset($arrGroupInfo['fields'])) {
                                        continue;
                                    }

                                    foreach ($arrGroupInfo['fields'] as $arrCompanyFieldInfo) {
                                        if ($arrCompanyFieldInfo['field_id'] == $fieldId) {
                                            if ($arrGroupInfo['group_contact_block'] == 'Y') {
                                                $updateClientId = $this->_clients->getAssignedContact($memberId, $arrGroupInfo['group_id']);
                                            } else {
                                                $updateClientId = $memberId;
                                            }

                                            if (!empty($updateClientId)) {
                                                $savedFieldValue = $this->_clients->getApplicantFields()->getFieldDataValue($updateClientId, $fieldId);
                                                $fieldType       = $arrCompanyFieldInfo['field_type'];
                                            }
                                            break 2;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if (!is_null($fieldType)) {
                        $booEmptySavedValue = strlen($savedFieldValue) === 0;
                        $booDateField = $booMultipleComboField = $booTextareaField = false;

                        switch ($fieldType) {
                            case 'short_date':
                            case 'date':
                            case 'date_repeatable':
                                $textFieldValue = $arrConditionSettings['based_on_field_date'] ?? '';
                                $booDateField = true;
                                break;

                            case 'combo':
                            case 'agents':
                            case 'assigned_to':
                            case 'staff_responsible_rma':
                            case 'categories':
                            case 'case_status':
                            case 'contact_sales_agent':
                            case 'authorized_agents':
                            case 'employer_contacts':
                            case 'country':
                                if (in_array($arrConditionSettings['based_on_field_condition'], array('is_one_of', 'is_none_of'))) {
                                    $textFieldValue = $arrConditionSettings['based_on_field_multiple_combo'] ?? '';
                                } else {
                                    $textFieldValue = $arrConditionSettings['based_on_field_combo'] ?? '';
                                }
                                break;

                            case 'office':
                            case 'office_multi':
                            case 'multiple_combo':
                                if (in_array($arrConditionSettings['based_on_field_condition'], array('is_one_of', 'is_none_of'))) {
                                    $textFieldValue = $arrConditionSettings['based_on_field_multiple_combo'] ?? '';
                                } else {
                                    $textFieldValue = $arrConditionSettings['based_on_field_combo'] ?? '';
                                }
                                $booMultipleComboField = true;
                                break;

                            case 'checkbox':
                                /// Not used
                                $textFieldValue = '';
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
                                if (in_array($arrConditionSettings['based_on_field_condition'], array('is_one_of', 'is_none_of'))) {
                                    $textFieldValue = $arrConditionSettings['based_on_field_textarea'] ?? '';
                                    $booTextareaField = true;
                                } else {
                                    $textFieldValue = $arrConditionSettings['based_on_field_textfield'] ?? '';
                                }

                                break;
                        }

                        switch ($arrConditionSettings['based_on_field_condition']) {
                            case "is" :
                            case "equal" :
                                if ($booDateField) {
                                    $booIsDue = strtotime($savedFieldValue) == strtotime($textFieldValue);
                                } elseif ($booMultipleComboField) {
                                    $arrOptionIds = explode(",", $savedFieldValue ?? '');
                                    if (in_array($textFieldValue, $arrOptionIds)) {
                                        $booIsDue = true;
                                    }
                                } else {
                                    $booIsDue = $savedFieldValue == $textFieldValue;
                                }
                                break;

                            case "is_one_of" :
                                if ($booTextareaField) {
                                    $arrValues = preg_split("/\r\n|\n|\r/", $textFieldValue);
                                    if (in_array($savedFieldValue, $arrValues)) {
                                        $booIsDue = true;
                                        break;
                                    }
                                } else {
                                    $arrOptionIds = explode(";", $textFieldValue ?? '');
                                    if ($booMultipleComboField) {
                                        $arrSavedOptionIds = explode(",", $savedFieldValue ?? '');
                                        foreach ($arrSavedOptionIds as $savedOptionId) {
                                            if (in_array($savedOptionId, $arrOptionIds)) {
                                                $booIsDue = true;
                                                break;
                                            }
                                        }
                                    } elseif (in_array($savedFieldValue, $arrOptionIds)) {
                                        $booIsDue = true;
                                    }
                                }
                                break;

                            case "is_not" :
                            case "not_equal" :
                                if ($booDateField) {
                                    $booIsDue = strtotime($savedFieldValue) != strtotime($textFieldValue);
                                } elseif ($booMultipleComboField) {
                                    $arrOptionIds = explode(",", $savedFieldValue ?? '');
                                    if (!in_array($textFieldValue, $arrOptionIds)) {
                                        $booIsDue = true;
                                    }
                                } else {
                                    $booIsDue = $savedFieldValue != $textFieldValue;
                                }
                                break;

                            case "is_none_of" :
                                if ($booTextareaField) {
                                    $arrValues = preg_split("/\r\n|\n|\r/", $textFieldValue);
                                    if (!in_array($savedFieldValue, $arrValues)) {
                                        $booIsDue = true;
                                        break;
                                    }
                                } else {
                                    $arrOptionIds = explode(";", $textFieldValue ?? '');
                                    if ($booMultipleComboField) {
                                        $arrSavedOptionIds = explode(",", $savedFieldValue ?? '');
                                        $booIsDue = true;
                                        foreach ($arrSavedOptionIds as $savedOptionId) {
                                            if (in_array($savedOptionId, $arrOptionIds)) {
                                                $booIsDue = false;
                                                break;
                                            }
                                        }
                                    } elseif (!in_array($savedFieldValue, $arrOptionIds)) {
                                        $booIsDue = true;
                                    }
                                }
                                break;

                            case "contains" :
                                $booIsDue = !$booEmptySavedValue && strpos($savedFieldValue, $textFieldValue) !== false;
                                break;

                            case "does_not_contain" :
                                $booIsDue = $booEmptySavedValue || strpos($savedFieldValue, $textFieldValue) === false;
                                break;

                            case "starts_with" :
                                // http://stackoverflow.com/a/5711839/284602
                                $booIsDue = !$booEmptySavedValue && $this->_settings->checkStringBeginsWith($savedFieldValue, $textFieldValue);
                                break;

                            case "ends_with" :
                                // http://stackoverflow.com/a/5711839/284602
                                $booIsDue = !$booEmptySavedValue && $this->_settings->checkStringEndsWith($savedFieldValue, $textFieldValue);
                                break;

                            case "is_empty" :
                                $booIsDue = $booEmptySavedValue || empty($savedFieldValue);
                                break;

                            case "is_not_empty" :
                                $booIsDue = !$booEmptySavedValue && !empty($savedFieldValue);
                                break;

                            case "is_before" :
                                $booIsDue = !$booEmptySavedValue && strtotime($savedFieldValue) < strtotime($textFieldValue);
                                break;

                            case "less" :
                                $booIsDue = !$booEmptySavedValue && $savedFieldValue < $textFieldValue;
                                break;

                            case "is_after" :
                                $booIsDue = !$booEmptySavedValue && strtotime($savedFieldValue) > strtotime($textFieldValue);
                                break;

                            case "more" :
                                $booIsDue = !$booEmptySavedValue && $savedFieldValue > $textFieldValue;
                                break;

                            case "less_or_equal" :
                                $booIsDue = !$booEmptySavedValue && $savedFieldValue <= $textFieldValue;
                                break;

                            case "more_or_equal" :
                                $booIsDue = !$booEmptySavedValue && $savedFieldValue >= $textFieldValue;
                                break;

                            case "is_between_2_dates" :
                                $booIsDue = !$booEmptySavedValue && strtotime($savedFieldValue) >= strtotime($arrConditionSettings['based_on_field_date_range_from']) && strtotime($savedFieldValue) <= strtotime($arrConditionSettings['based_on_field_date_range_to']);
                                break;

                            case "is_in_the_next" :
                                if (!$booEmptySavedValue) {
                                    $date = new DateTime();
                                    $date->add(new DateInterval('P' . $arrConditionSettings['based_on_field_date_number'] . $arrConditionSettings['based_on_field_date_period']));
                                    $booIsDue = strtotime($savedFieldValue) >= strtotime('today') && strtotime($savedFieldValue) <= $date->getTimestamp();
                                }
                                break;

                            case "is_in_the_previous" :
                                if (!$booEmptySavedValue) {
                                    $date = new DateTime();
                                    $date->add(new DateInterval('P' . $arrConditionSettings['based_on_field_date_number'] . $arrConditionSettings['based_on_field_date_period']));
                                    $booIsDue = strtotime($savedFieldValue) >= $date->getTimestamp() && strtotime($savedFieldValue) <= strtotime('today');
                                }
                                break;

                            case "is_between_today_and_date" :
                                $booIsDue = !$booEmptySavedValue && strtotime($savedFieldValue) >= strtotime('today') && strtotime($savedFieldValue) <= strtotime($textFieldValue);
                                break;

                            case "is_between_date_and_today" :
                                $booIsDue = !$booEmptySavedValue && strtotime($savedFieldValue) >= strtotime($textFieldValue) && strtotime($savedFieldValue) <= strtotime('today');
                                break;

                            case "is_since_start_of_the_year_to_now" :
                                $booIsDue = !$booEmptySavedValue && strtotime($savedFieldValue) >= strtotime(date('Y-01-01')) && strtotime($savedFieldValue) <= strtotime('today');
                                break;

                            case "is_from_today_to_the_end_of_year" :
                                $booIsDue = !$booEmptySavedValue && strtotime($savedFieldValue) >= strtotime('today') && strtotime($savedFieldValue) <= strtotime(date('Y-12-31'));
                                break;

                            case "is_in_this_month" :
                                $booIsDue = !$booEmptySavedValue && strtotime($savedFieldValue) >= strtotime(date('Y-m-d', strtotime(date('m') . "/1/" . date('Y')))) && strtotime($savedFieldValue) <= strtotime('next month', strtotime(date('m/01/y')) - 1);
                                break;

                            case "is_in_this_year" :
                                $booIsDue = !$booEmptySavedValue && strtotime($savedFieldValue) >= strtotime(date('Y-1-1')) && strtotime($savedFieldValue) <= strtotime(date('Y-12-31'));
                                break;

                            default:
                                // Cannot be here...
                                break;
                        }
                    }
                    break;

                case 'CHANGED_FIELD':
                    if (isset($arrConditionInfo['field_id'])) {
                        $booIsFieldBasedOnCase = $arrConditionSettings['changed_field_member_type'] == 'case';

                        if ($booIsInternalContact && !$booIsFieldBasedOnCase) {
                            foreach ($arrParents as $arrParentInfo) {
                                $memberTypeName = $arrParentInfo['member_type_name'];
                                break;
                            }
                        }

                        if ($memberTypeName == $arrConditionSettings['changed_field_member_type']) {
                            $conditionSettingsFieldId = $arrConditionSettings['changed_field_field_id'];
                            $booIsDue = $arrConditionInfo['field_id'] == $conditionSettingsFieldId;
                        }
                    }
                    break;

                case 'CASE_TYPE':
                    $arrClientInfo = $this->_clients->getClientInfo($caseId);
                    if (!empty($arrClientInfo) && $arrClientInfo['client_type_id'] == $arrConditionSettings['case_type']) {
                        $booIsDue = true;
                    }
                    break;

                case 'CASE_HAS_FORM':
                    $arrFormVersions   = $this->_forms->getFormVersion()->getFormVersionsByFormId($arrConditionSettings['form_id']);

                    $arrFormVersionIds = [];
                    foreach ($arrFormVersions as $formVersion) {
                        $arrFormVersionIds[] = $formVersion['form_version_id'];
                    }

                    $booIsDue = $this->_forms->getFormAssigned()->hasMemberFormAssigned($caseId, $arrFormVersionIds);
                    break;

                default:
                    // Cannot be here
                    break;
            }
        }

        return array($booIsDue, $calculatedDate, $booSaveToProcessed);
    }

    private function calculateBusinessDate($date, $days, $ba)
    {
        $i      = 0;
        $cal_eq = 0;
        $td     = '';

        while ($i < $days) {
            ++$cal_eq;

            $td = getdate(strtotime($date . ($ba == 'CALENDAR' ? "-" : "+") . $cal_eq . " days "));

            if ($td['wday'] == 5 || $td['wday'] == 6) {
                continue;
            }

            ++$i;
        }

        return $td['year'] . '-' . $td['mon'] . '-' . $td['mday'];
    }

    /**
     * Calculate profile date in relation to the condition's settings
     *
     * @param $options
     * @param bool $booPastRepeatableDate
     * @return string
     */
    public function calculateProfDate($options, $booPastRepeatableDate = false)
    {
        //get due_on date
        $prof_date = $options['prof-date'] ?? '';
        if (empty($options['prof-date']) && !empty($options['prof']) && !empty($options['member_id'])) {
            $prof_date = $this->_clients->getFields()->getFieldDataValue($options['prof'], $options['member_id']);
        }

        // Selected date field is not set
        if (empty($prof_date)) {
            return '';
        }

        // Selected date field is set, use that date
        if ($options['days'] == 'CALENDAR') { //calendar days
            $date = date("Y-m-d", strtotime($prof_date . ($options['ba'] == 'BEFORE' ? " - " : " + ") . $options['number'] . " days "));
        } else { //business
            $date = $this->calculateBusinessDate($prof_date, $options['number'], $options['ba']);
        }

        if (!empty($options['prof'])) {
            $repeatableDateType = $this->_clients->getFieldTypes()->getFieldTypeId('date_repeatable');
            $fieldType          = $options['field_type'] ?? '';
            if (empty($fieldType)) {
                $select = (new Select())
                    ->from('client_form_fields')
                    ->columns(['type'])
                    ->where(
                        [
                            'field_id' => $options['prof']
                        ]
                    );

                $fieldType = $this->_db2->fetchOne($select);
            }

            $now = strtotime(date("Y-m-d"));
            if ((is_numeric($fieldType) && $repeatableDateType == $fieldType) || $fieldType == 'date_repeatable') { //is repeatable date
                $rdate = explode('-', $date ?? '');
                $i     = 0;
                while (true) {
                    //get date
                    $i++;
                    $date = strtotime($rdate[1] . '/' . $rdate[2] . '/' . (date('Y', strtotime("+$i years"))));
                    if ($date >= $now) { //next year or leap year
                        break;
                    }
                }

                // get date in past mode (only for repeatable dates)
                if ($booPastRepeatableDate) {
                    $date = strtotime(date("m/d/Y", $date) . " -1 YEAR");
                }

                $date = date("Y-m-d", $date);
            }
        }

        return $date;
    }
}
