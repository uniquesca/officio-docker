<?php

namespace Officio\Service\AutomaticReminders;

use Clients\Service\Members;
use Exception;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Officio\Common\Service\BaseService;
use Officio\Service\AutomaticReminders;
use Officio\Common\SubServiceInterface;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class Triggers extends BaseService implements SubServiceInterface
{
    /** @var Members */
    protected $_members;

    /** @var AutomaticReminders */
    private $_parent;

    public function initAdditionalServices(array $services)
    {
        $this->_members = $services[Members::class];
    }

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent() {
        return $this->_parent;
    }

    /**
     * Check if current user access to specific reminder's trigger
     *
     * @param int $reminderTriggerId
     * @return bool true if has access
     */
    public function hasAccessToTrigger($reminderTriggerId)
    {
        $booHasAccess = false;
        try {
            $arrTriggerInfo = $this->getTrigger(0, 0, $reminderTriggerId);
            if ($this->_auth->isCurrentUserSuperadmin() || (isset($arrTriggerInfo['company_id']) && $arrTriggerInfo['company_id'] == $this->_auth->getCurrentUserCompanyId())) {
                $booHasAccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booHasAccess;
    }

    public function isCorrectTriggerType($triggerTypeId)
    {
        $booCorrect      = false;
        $arrTriggerTypes = $this->getTriggerTypes();
        foreach ($arrTriggerTypes as $arrTypeInfo) {
            if ($triggerTypeId == $arrTypeInfo['automatic_reminder_trigger_type_id']) {
                $booCorrect = true;
                break;
            }
        }

        return $booCorrect;
    }


    public function getTriggerSettings()
    {
        return array(
            'arrTypes' => $this->getTriggerTypes()
        );
    }


    /**
     * Load saved information about the trigger
     *
     * @param $companyId
     * @param int $reminderId
     * @param int $triggerId
     * @return array
     */
    public function getTrigger($companyId, $reminderId, $triggerId)
    {
        $arrWhere = [];
        $arrWhere['automatic_reminder_trigger_id'] = (int)$triggerId;

        if (!empty($companyId)) {
            $arrWhere['company_id'] = (int)$companyId;
        }

        if (!empty($reminderId)) {
            $arrWhere['automatic_reminder_id'] = (int)$reminderId;
        }

        $select = (new Select())
            ->from('automatic_reminder_triggers')
            ->where($arrWhere);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load saved information about the trigger
     *
     * @param $companyId
     * @param int $reminderId
     * @param array $arrTriggerIds
     * @param bool $booDeleteOldTriggers
     * @return array
     */
    public function getReminderTriggers($companyId, $reminderId, $arrTriggerIds = array(), $booDeleteOldTriggers = false)
    {
        $booShowChangedFieldCondition = $booLastFieldValueChangedTrigger = false;
        try {
            if ($booDeleteOldTriggers) {
                $this->_db2->delete(
                    'automatic_reminder_triggers',
                    [
                        (new Where())
                            ->isNull('automatic_reminder_id')
                            ->and
                            ->lessThan('automatic_reminder_trigger_create_date', date('Y-m-d')),
                        'company_id' => (int)$companyId
                    ]
                );
            }

            $arrWhere = [];
            $arrWhere['company_id'] = (int)$companyId;

            if (!empty($reminderId)) {
                $arrWhere['automatic_reminder_id'] = (int)$reminderId;
            }

            if (!empty($arrTriggerIds)) {
                $arrWhere['automatic_reminder_trigger_id'] = $arrTriggerIds;
            }

            $select = (new Select())
                ->from('automatic_reminder_triggers')
                ->where($arrWhere);

            $arrSavedReminderTriggers = $this->_db2->fetchAll($select);

            $arrReminderTriggers = array();
            $fieldValueChangedTriggersCounter = 0;
            foreach ($arrSavedReminderTriggers as $key => $reminderTrigger) {
                if ($this->getTriggerInternalTypeById($reminderTrigger['automatic_reminder_trigger_type_id']) == 'field_value_change') {
                    $booShowChangedFieldCondition = true;
                    $fieldValueChangedTriggersCounter++;
                }
                $arrReminderTriggers[$key] = array(
                    'trigger_id'      => $reminderTrigger['automatic_reminder_trigger_id'],
                    'trigger_text'    => $this->getTriggerTypeById($reminderTrigger['automatic_reminder_trigger_type_id']),
                    'trigger_type_id' => $reminderTrigger['automatic_reminder_trigger_type_id'],
                );
            }

            $booLastFieldValueChangedTrigger = $fieldValueChangedTriggersCounter == 1;
        } catch (Exception $e) {
            $arrReminderTriggers = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($arrReminderTriggers, $booShowChangedFieldCondition, $booLastFieldValueChangedTrigger);
    }

    /**
     * Load list of triggers in readable format for specific reminder
     *
     * @param array $arrTriggers
     * @return string
     */
    public function getReadableReminderTriggers($arrTriggers)
    {
        if (empty($arrTriggers)) {
            $strReadableActions = '<span style="color: red;">There are no defined triggers</span>';
        } else {
            $strReadableActions = '';
            foreach ($arrTriggers as $arrTriggerInfo) {
                if (!empty($strReadableActions)) {
                    $strReadableActions .= '<br/>';
                }
                $strReadableActions .= $arrTriggerInfo['trigger_text'];
            }
        }

        return $strReadableActions;
    }

    /**
     * Load list of trigger types
     *
     * @return array
     */
    public function getTriggerTypes()
    {
        $id = 'auto_reminder_trigger_types';
        if (!($data = $this->_cache->getItem($id))) {
            // Not in cache
            $select = (new Select())
                ->from('automatic_reminder_trigger_types')
                ->order('automatic_reminder_trigger_type_order');

            $data = $this->_db2->fetchAll($select);
            $this->_cache->setItem($id, $data);
        }

        return $data;
    }

    /**
     * @param int $triggerTypeId
     * @return string
     */
    public function getTriggerTypeById($triggerTypeId)
    {
        $select = (new Select())
            ->from('automatic_reminder_trigger_types')
            ->columns(['automatic_reminder_trigger_type_name'])
            ->where(['automatic_reminder_trigger_type_id' => (int)$triggerTypeId]);

        return $this->_db2->fetchOne($select);
    }

    /**
     * @param int $triggerTypeId
     * @return string
     */
    public function getTriggerInternalTypeById($triggerTypeId)
    {
        $select = (new Select())
            ->from('automatic_reminder_trigger_types')
            ->columns(['automatic_reminder_trigger_type_internal_id'])
            ->where(['automatic_reminder_trigger_type_id' => (int)$triggerTypeId]);

        return $this->_db2->fetchOne($select);
    }

    /**
     * @param int $triggerTypeTextId
     * @return string
     */
    public function getTriggerTypeIdByTextId($triggerTypeTextId)
    {
        $select = (new Select())
            ->from('automatic_reminder_trigger_types')
            ->columns(['automatic_reminder_trigger_type_id'])
            ->where(['automatic_reminder_trigger_type_internal_id' => $triggerTypeTextId]);

        return $this->_db2->fetchOne($select);
    }

    /**
     * Delete specific automatic reminder trigger
     *
     * @param $companyId
     * @param int $triggerId
     * @return int
     */
    public function delete($companyId, $triggerId)
    {
        return $this->_db2->delete(
            'automatic_reminder_triggers',
            [
                'automatic_reminder_trigger_id' => (int)$triggerId,
                'company_id'                    => (int)$companyId
            ]
        );
    }

    /**
     * Create/update + assign trigger to reminder
     *
     * @param int $companyId
     * @param int $reminderId
     * @param int $triggerId
     * @param int $triggerTypeId
     * @return string
     */
    public function save($companyId, $reminderId, $triggerId, $triggerTypeId)
    {
        try {
            $arrData = array(
                'automatic_reminder_trigger_type_id' => (int)$triggerTypeId,
            );

            if (empty($triggerId)) {
                if (!empty($reminderId)) {
                    $arrData['automatic_reminder_id'] = $reminderId;
                }

                $arrData['company_id']                             = (int)$companyId;
                $arrData['automatic_reminder_trigger_create_date'] = date('Y-m-d');

                $triggerId = $this->_db2->insert('automatic_reminder_triggers', $arrData);
            } else {
                $arrWhere = [
                    'company_id'                    => (int)$companyId,
                    'automatic_reminder_trigger_id' => (int)$triggerId,
                ];

                if (empty($reminderId)) {
                    $arrWhere['automatic_reminder_id'] = null;
                } else {
                    $arrWhere['automatic_reminder_id'] = (int)$reminderId;
                }

                $this->_db2->update('automatic_reminder_triggers', $arrData, $arrWhere);

                $this->deleteDependentConditions($reminderId);
            }
        } catch (Exception $e) {
            $triggerId = '';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $triggerId;
    }

    /**
     * Delete conditions which depend on trigger
     *
     * @param int $reminderId
     */
    public function deleteDependentConditions($reminderId)
    {
        $select = (new Select())
            ->from('automatic_reminder_triggers')
            ->where(['automatic_reminder_id' => (int)$reminderId]);

        $arrSavedReminderTriggers = $this->_db2->fetchAll($select);

        $booDeleteChangedFieldConditions = true;
        foreach ($arrSavedReminderTriggers as $reminderTrigger) {
            if ($this->getTriggerInternalTypeById($reminderTrigger['automatic_reminder_trigger_type_id']) == 'field_value_change') {
                $booDeleteChangedFieldConditions = false;
                break;
            }
        }

        if ($booDeleteChangedFieldConditions) {
            $this->_db2->delete(
                'automatic_reminder_conditions',
                [
                    'automatic_reminder_id'                => (int)$reminderId,
                    'automatic_reminder_condition_type_id' => $this->_parent->getConditions()->getConditionTypeIdByTextId('CHANGED_FIELD')
                ]
            );
        }
    }

    /**
     * Create default automatic reminders' triggers for specific company
     *
     * @param $fromCompanyId
     * @param int $toCompanyId
     * @param int $defaultReminderId
     * @param int $reminderId
     */
    public function createDefaultAutomaticReminderTriggers($fromCompanyId, $toCompanyId, $defaultReminderId, $reminderId)
    {
        list($arrDefaultAutomaticReminderTriggers, , ) = $this->getReminderTriggers($fromCompanyId, $defaultReminderId);

        // Create same triggers
        foreach ($arrDefaultAutomaticReminderTriggers as $arrTriggerInfo) {
            $arrTriggerInfo['automatic_reminder_trigger_type_id']     = $arrTriggerInfo['trigger_type_id'];
            $arrTriggerInfo['company_id']                             = $toCompanyId;
            $arrTriggerInfo['automatic_reminder_id']                  = $reminderId;
            $arrTriggerInfo['automatic_reminder_trigger_create_date'] = date('Y-m-d');

            unset($arrTriggerInfo['automatic_reminder_trigger_id'], $arrTriggerInfo['trigger_id'], $arrTriggerInfo['trigger_text'], $arrTriggerInfo['trigger_type_id']);

            $this->_db2->insert('automatic_reminder_triggers', $arrTriggerInfo);
        }
    }

    /**
     * Assign trigger(s) to a specific reminder
     *
     * @param int $companyId
     * @param int $reminderId
     * @param array $arrTriggerTypes
     * @return bool true on success
     */
    public function assignToReminder($companyId, $reminderId, $arrTriggerTypes)
    {
        try {
            list($arrSavedAutomaticReminderTriggers, ,) = $this->getReminderTriggers($companyId, $reminderId);

            $arrToDelete = [];
            $arrSavedTypeIds = [];
            foreach ($arrSavedAutomaticReminderTriggers as $arrSavedAutomaticReminderTriggerInfo) {
                $arrSavedTypeIds[] = $arrSavedAutomaticReminderTriggerInfo['trigger_type_id'];

                if (!in_array($arrSavedAutomaticReminderTriggerInfo['trigger_type_id'], $arrTriggerTypes)) {
                    $arrToDelete[] = $arrSavedAutomaticReminderTriggerInfo['trigger_id'];
                }
            }

            if (!empty($arrToDelete)) {
                $this->_db2->delete(
                    'automatic_reminder_triggers',
                    [
                        'automatic_reminder_trigger_id' => $arrToDelete
                    ]
                );
            }
            $booSuccess = true;

            foreach ($arrTriggerTypes as $triggerTypeId) {
                if (!in_array($triggerTypeId, $arrSavedTypeIds)) {
                    $triggerId = $this->save($companyId, $reminderId, 0, $triggerTypeId);
                    if (empty($triggerId)) {
                        $booSuccess = false;
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }


    /**
     * Load reminders by assigned triggers with specific type
     * Also make sure that reminder will have at least one action and one condition assigned
     *
     * @param $triggerTypeName
     * @param $companyId, if null - load for all companies
     * @return array
     */
    public function getRemindersByTriggerType($triggerTypeName, $companyId = null)
    {
        // @Note: use separate requests to DB because we need to be sure that it will work quickly on huge reminders count

        // Load reminders that have at least one action
        $arrWhere = [];

        if (!is_null($companyId)) {
            $arrWhere['a.company_id'] = $companyId;
        }

        $select = (new Select())
            ->from(array('a' => 'automatic_reminder_actions'))
            ->columns(['automatic_reminder_id'])
            ->where($arrWhere)
            ->group('automatic_reminder_id');

        $arrCorrectReminderIds = $this->_db2->fetchCol($select);

            // Load reminders for passed specific trigger type
        // for active companies that are not in trial mode OR which next billing date is in the future.
        // All found reminders must have at least one action and one condition
        $arrAllReminders = array();
        if (count($arrCorrectReminderIds)) {
            $arrWhere = [];
            $arrWhere['c.Status'] = 1;
            $arrWhere[] = (new Where())
                ->nest()
                ->isNull('cd.next_billing_date')
                ->or
                ->greaterThanOrEqualTo('cd.next_billing_date', date('Y-m-d'))
                ->unnest();
            $arrWhere['r.automatic_reminder_id'] = $arrCorrectReminderIds;
            $arrWhere['tt.automatic_reminder_trigger_type_internal_id'] = $triggerTypeName;

            if (!is_null($companyId)) {
                $arrWhere['r.company_id'] = $companyId;
            }

            $select = (new Select())
                ->from(array('r' => 'automatic_reminders'))
                ->join(array('c' => 'company'), 'r.company_id = c.company_id', [], Select::JOIN_LEFT_OUTER)
                ->join(array('cd' => 'company_details'), 'c.company_id = cd.company_id', [], Select::JOIN_LEFT_OUTER)
                ->join(array('t' => 'automatic_reminder_triggers'), 't.automatic_reminder_id = r.automatic_reminder_id', [])
                ->join(array('tt' => 'automatic_reminder_trigger_types'), 'tt.automatic_reminder_trigger_type_id = t.automatic_reminder_trigger_type_id', [])
                ->where($arrWhere);

            $arrAllReminders = $this->_db2->fetchAll($select);
        }

        return $arrAllReminders;
    }

}
