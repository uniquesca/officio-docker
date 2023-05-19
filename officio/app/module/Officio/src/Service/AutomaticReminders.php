<?php

namespace Officio\Service;

use Clients\Service\ClientsFileStatusHistory;
use Exception;
use Laminas\Db\Sql\Select;
use Laminas\EventManager\EventInterface;
use Laminas\ServiceManager\ServiceManager;
use Officio\Service\AutomaticReminders\Actions;
use Officio\Service\AutomaticReminders\Conditions;
use Officio\Service\AutomaticReminders\Triggers;
use Officio\Common\SubServiceOwner;
use Tasks\Service\Tasks;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class AutomaticReminders extends SubServiceOwner
{

    /** @var Company */
    protected $_company;

    /** @var Actions */
    protected $_actions;

    /** @var Conditions */
    protected $_conditions;

    /** @var Triggers */
    protected $_triggers;

    /** @var SystemTriggers */
    protected $_systemTriggers;

    /** @var Tasks */
    protected $_tasks;

    /** @var ClientsFileStatusHistory */
    protected $_clientsFileStatusHistory;

    public function initAdditionalServices(array $services)
    {
        $this->_company                  = $services[Company::class];
        $this->_systemTriggers           = $services[SystemTriggers::class];
        $this->_tasks                    = $services[Tasks::class];
        $this->_clientsFileStatusHistory = $services[ClientsFileStatusHistory::class];
    }

    public function init() {
        // Autotasks should be triggered in the end, so we give all of them low priority
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_COMPANY_DELETE, [$this, 'onDeleteCompany'], -100);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_COMPANY_CREATE_DEFAULT_SECTIONS, [$this, 'onCreateDefaultCompanySections'], -100);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_FORM_COMPLETE, [$this, 'onFormComplete'], -100);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_DOCUMENT_UPLOADED, [$this, 'onDocumentUploaded'], -100);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_CASE_CREATED, [$this, 'onCaseCreated'], -100);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_CRON, [$this, 'onCron'], -100);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_PROCESS_SCHEDULED_REMINDER_ACTIONS, [$this, 'onProcessScheduledReminderActions'], -100);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_TRANPAGE_PAYMENT_RECEIVED, [$this, 'onPaymentReceived'], -100);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_FIELD_VALUE_CHANGED, [$this, 'onFieldValueChanged'], -100);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_PROFILE_UPDATED, [$this, 'onProfileUpdated'], -100);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_ADDITIONAL_DOCUMENT_UPLOADED, [$this, 'onAdditionalDocumentUploaded'], -100);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_FILE_STATUS_CHANGED, [$this, 'onFileStatusChanged'], -100);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_PAYMENT_IS_DUE, [$this, 'onPaymentIsDue'], -100);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_PROFILE_DATE_FIELD_CHANGED, [$this, 'onProfileDateFieldChanged'], -100);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_CLIENT_DATE_FIELDS_CHANGED, [$this, 'onClientDateFieldValuesChanged'], -100);
    }

    public function onProfileDateFieldChanged(EventInterface $event) {
        try {
            $memberId = $event->getParam('member_id');
            $arrDuePSRecords = $event->getParam('due_ps_records', []);

            // Mark all found records as due + create tasks
            if (is_array($arrDuePSRecords) && count($arrDuePSRecords)) {
                $arrDuePSRecordsIds = array_map(function($n) { return $n['payment_schedule_id']; }, $arrDuePSRecords);

                // * 1. Find all cases which have PS records assigned to these date fields
                $select = (new Select())
                    ->from(array('ps' => 'u_payment_schedule'))
                    ->columns(['member_id'])
                    ->join(array('m' => 'members'), 'm.member_id = ps.member_id', 'company_id')
                    ->where(['ps.payment_schedule_id' => $arrDuePSRecordsIds]);

                if (!is_null($memberId)) {
                    $select->where(['ps.member_id' => (int)$memberId]);
                }

                $arrCases = $this->_db2->fetchAll($select);

                // * 2. For these cases - group their company ids
                $arrCasesGroupedByCompanies = array();
                foreach ($arrCases as $arrCaseInfo) {
                    $arrCasesGroupedByCompanies[$arrCaseInfo['company_id']][] = $arrCaseInfo['member_id'];
                }

                $triggerTypeTextId = 'payment_due';
                $arrAllReminders   = array();
                foreach ($arrCasesGroupedByCompanies as $companyId => $arrCasesIds) {
                    $arrCasesIds = array_unique($arrCasesIds);

                    if (count($arrCasesIds)) {
                        // * 3. For these companies load their auto tasks with trigger type payment_due
                        $arrCompanyReminders = $this->getTriggers()->getRemindersByTriggerType($triggerTypeTextId, $companyId);

                        foreach ($arrCasesIds as $caseId) {
                            foreach ($arrCompanyReminders as $key => $arrReminderInfo) {
                                $arrCompanyReminders[$key]['member_id'] = $caseId;
                            }
                        }

                        // * 4. Filter auto tasks by due conditions for these cases
                        $arrCompanyReminders = $this->getConditions()->filterRemindersByDueConditions($arrCompanyReminders, $companyId);

                        if (count($arrCompanyReminders)) {
                            $arrAllReminders = array_merge($arrAllReminders, $arrCompanyReminders);
                        }
                    }
                }

                // * 5. Process all actions for filtered auto tasks
                foreach ($arrAllReminders as $arrReminderInfo) {
                    $this->getActions()->processAutomaticReminderActions(
                        $arrReminderInfo,
                        $triggerTypeTextId
                    );
                }
            }
        }
        catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * Action: New file(s) uploaded to the Additional Documents Folder
     * @param EventInterface $event
     */
    public function onAdditionalDocumentUploaded(EventInterface $event)
    {
        try {
            $companyId = $event->getParam('company_id');
            $memberId = $event->getParam('member_id');

            // Load all reminders which trigger is "New documents uploaded to the Additional Documents Folder"
            $arrReminders = $this->getTriggers()->getRemindersByTriggerType(SystemTriggers::EVENT_ADDITIONAL_DOCUMENT_UPLOADED, $companyId);
            foreach ($arrReminders as $key => $arrReminderInfo) {
                $arrReminders[$key]['member_id'] = $memberId;
            }

            // Filter reminders (make sure that all triggers in each reminder are due/true
            $arrReminders = $this->getConditions()->filterRemindersByDueConditions($arrReminders, $companyId);

            // Run actions for filtered reminders
            $arrLogRecords = array();
            foreach ($arrReminders as $arrReminderInfo) {
                $this->getActions()->processAutomaticReminderActions(
                    $arrReminderInfo,
                    SystemTriggers::EVENT_ADDITIONAL_DOCUMENT_UPLOADED
                );

                $arrLogRecords [] = $arrReminderInfo['reminder'] . '(' . $arrReminderInfo['automatic_reminder_id'] . ':' . $arrReminderInfo['member_id'] . ')';
            }

            if (count($arrLogRecords)) {
                $strLogMessage = 'are due: ' . implode(', ', $arrLogRecords);
            } else {
                $strLogMessage = '- nothing to process';
            }
            $this->_log->saveToCronLog(strtoupper(SystemTriggers::EVENT_ADDITIONAL_DOCUMENT_UPLOADED) . ' auto tasks ' . $strLogMessage);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * Action: Client/case profile was updated
     * @param EventInterface $event
     */
    public function onProfileUpdated(EventInterface $event)
    {
        try {
            $companyId = $event->getParam('company_id');
            $memberId = $event->getParam('member_id');

            // Load all reminders which trigger is "Client/Case profile update"
            $arrReminders = $this->getTriggers()->getRemindersByTriggerType(SystemTriggers::EVENT_PROFILE_UPDATED, $companyId);
            foreach ($arrReminders as $key => $arrReminderInfo) {
                $arrReminders[$key]['member_id'] = $memberId;
            }

            // Filter reminders (make sure that all triggers in each reminder are due/true
            $arrReminders = $this->getConditions()->filterRemindersByDueConditions($arrReminders, $companyId);

            // Run actions for filtered reminders
            $arrLogRecords = array();
            foreach ($arrReminders as $arrReminderInfo) {
                $this->getActions()->processAutomaticReminderActions(
                    $arrReminderInfo,
                    SystemTriggers::EVENT_PROFILE_UPDATED
                );

                $arrLogRecords [] = $arrReminderInfo['reminder'] . '(' . $arrReminderInfo['automatic_reminder_id'] . ':' . $arrReminderInfo['member_id'] . ')';
            }

            if (count($arrLogRecords)) {
                $strLogMessage = 'are due: ' . implode(', ', $arrLogRecords);
            } else {
                $strLogMessage = '- nothing to process';
            }
            $this->_log->saveToCronLog(strtoupper(SystemTriggers::EVENT_PROFILE_UPDATED) . ' auto tasks ' . $strLogMessage);

        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * @param EventInterface $event
     */
    public function onFileStatusChanged(EventInterface $event)
    {
        try {
            $memberId              = $event->getParam('member_id');
            $authorId              = $event->getParam('author_id');
            $authorName            = $event->getParam('author_name');
            $arrOldFileStatuses    = $event->getParam('old_statuses');
            $arrNewFileStatuses    = $event->getParam('new_statuses');
            $booFileStatusChanged  = $arrOldFileStatuses != $arrNewFileStatuses;

            // New file status can be blank
            $arrNewFileStatusesNames = empty($arrNewFileStatuses) ? 'Blank' : implode(', ', $arrNewFileStatuses);

            // Task message that will be used during the task creation
            if (!empty($arrOldFileStatuses)) {
                $taskMessage = 'Case status changed from "' . implode(', ', $arrOldFileStatuses) . '" to "' . $arrNewFileStatusesNames . '"';
            } else {
                $taskMessage = 'Case status changed to "' . $arrNewFileStatusesNames . '"';
            }

            $companyId = $this->_company->getMemberCompanyId($memberId);

            // 2. Process all auto tasks which trigger is "Case File Status changed"
            $arrTriggerTypes = array();
            if ($booFileStatusChanged) {
                $arrTriggerTypes[] = 'case_file_status_changed';

                if (empty($authorId)) {
                    // Not logged in / cron
                    $authorId   = null;
                    $authorName = 'Officio - Auto Task';
                }

                $this->_clientsFileStatusHistory->saveClientFileStatusHistory(
                    $memberId,
                    $arrNewFileStatuses,
                    $authorId,
                    $authorName
                );
            }

            // 3. Process auto tasks based on the payment schedule
            $arrPSRecords = $event->getParam('ps_records');
            if (!empty($arrPSRecords)) {
                $arrTriggerTypes[] = 'payment_due';
            }

            foreach ($arrTriggerTypes as $triggerTypeTextId) {
                $arrReminders = $this->getTriggers()->getRemindersByTriggerType($triggerTypeTextId, $companyId);
                foreach ($arrReminders as $key => $arrReminderInfo) {
                    $arrReminders[$key]['member_id'] = $memberId;
                }

                // Filter reminders (make sure that all triggers in each reminder are due/true
                $arrReminders = $this->getConditions()->filterRemindersByDueConditions($arrReminders, $companyId);

                // Run actions for filtered reminders
                foreach ($arrReminders as $arrReminderInfo) {
                    $this->getActions()->processAutomaticReminderActions(
                        $arrReminderInfo,
                        $triggerTypeTextId,
                        $taskMessage
                    );
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    public function onFormComplete(EventInterface $event) {
        try {
            $companyId = $event->getParam('company_id');
            $memberId = $event->getParam('member_id');

            // Load all reminders which trigger is "Case marked a form as Complete"
            $arrReminders = $this->getTriggers()->getRemindersByTriggerType(SystemTriggers::EVENT_FORM_COMPLETE, $companyId);
            foreach ($arrReminders as $key => $arrReminderInfo) {
                $arrReminders[$key]['member_id'] = $memberId;
            }

            // Filter reminders (make sure that all triggers in each reminder are due/true
            $arrReminders = $this->getConditions()->filterRemindersByDueConditions($arrReminders, $companyId);

            // Run actions for filtered reminders
            $arrLogRecords = array();
            foreach ($arrReminders as $arrReminderInfo) {
                $this->getActions()->processAutomaticReminderActions(
                    $arrReminderInfo,
                    SystemTriggers::EVENT_FORM_COMPLETE
                );

                $arrLogRecords [] = $arrReminderInfo['reminder'] . '(' . $arrReminderInfo['automatic_reminder_id'] . ':' . $arrReminderInfo['member_id'] . ')';
            }

            if (count($arrLogRecords)) {
                $strLogMessage = 'are due: ' . implode(', ', $arrLogRecords);
            } else {
                $strLogMessage = '- nothing to process';
            }
            $this->_log->saveToCronLog(strtoupper(SystemTriggers::EVENT_FORM_COMPLETE) . ' auto tasks ' . $strLogMessage);
        }
        catch (Exception $exception) {
            $this->_log->debugErrorToFile($exception->getMessage(), $exception->getTraceAsString());
        }
    }

    public function onDocumentUploaded(EventInterface $event) {
        try {
            $companyId = $event->getParam('company_id');
            $memberId = $event->getParam('member_id');

            // Load all reminders which trigger is "Case uploads Documents"
            $arrReminders = $this->getTriggers()->getRemindersByTriggerType(SystemTriggers::EVENT_DOCUMENT_UPLOADED, $companyId);
            foreach ($arrReminders as $key => $arrReminderInfo) {
                $arrReminders[$key]['member_id'] = $memberId;
            }

            // Filter reminders (make sure that all triggers in each reminder are due/true
            $arrReminders = $this->getConditions()->filterRemindersByDueConditions($arrReminders, $companyId);

            // Run actions for filtered reminders
            $arrLogRecords = array();
            foreach ($arrReminders as $arrReminderInfo) {
                $this->getActions()->processAutomaticReminderActions(
                    $arrReminderInfo,
                    SystemTriggers::EVENT_DOCUMENT_UPLOADED
                );

                $arrLogRecords [] = $arrReminderInfo['reminder'] . '(' . $arrReminderInfo['automatic_reminder_id'] . ':' . $arrReminderInfo['member_id'] . ')';
            }

            if (count($arrLogRecords)) {
                $strLogMessage = 'are due: ' . implode(', ', $arrLogRecords);
            } else {
                $strLogMessage = '- nothing to process';
            }
            $this->_log->saveToCronLog(strtoupper(SystemTriggers::EVENT_DOCUMENT_UPLOADED) . ' auto tasks ' . $strLogMessage);
        }
        catch (Exception $exception) {
            $this->_log->debugErrorToFile($exception->getMessage(), $exception->getTraceAsString());
        }
    }

    /**
     * Action: Case creation
     * @param EventInterface $event
     */
    public function onCaseCreated(EventInterface $event)
    {
        try {
            $companyId  = $event->getParam('company_id');
            $memberId   = $event->getParam('member_id');

            // Load all reminders which trigger is "Case Creation"
            $arrReminders = $this->getTriggers()->getRemindersByTriggerType(SystemTriggers::EVENT_CASE_CREATED, $companyId);
            foreach ($arrReminders as $key => $arrReminderInfo) {
                $arrReminders[$key]['member_id'] = $memberId;
            }

            // Filter reminders (make sure that all triggers in each reminder are due/true
            $arrReminders = $this->getConditions()->filterRemindersByDueConditions($arrReminders, $companyId);

            // Run actions for filtered reminders
            $arrLogRecords = array();
            foreach ($arrReminders as $arrReminderInfo) {
                $this->getActions()->processAutomaticReminderActions(
                    $arrReminderInfo,
                    SystemTriggers::EVENT_CASE_CREATED
                );

                $arrLogRecords [] = $arrReminderInfo['reminder'] . '(' . $arrReminderInfo['automatic_reminder_id'] . ':' . $arrReminderInfo['member_id'] . ')';
            }

            if (count($arrLogRecords)) {
                $strLogMessage = 'are due: ' . implode(', ', $arrLogRecords);
            } else {
                $strLogMessage = '- nothing to process';
            }
            $this->_log->saveToCronLog(strtoupper(SystemTriggers::EVENT_CASE_CREATED) . ' auto tasks ' . $strLogMessage);

        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * Action: Cron was processed
     * @param EventInterface $event
     */
    public function onCron(EventInterface $event)
    {
        try {
            // TODO: make sure that GV will change the conditions to be sure that reminder will not work again
            // Load all reminders which trigger is "Cron"
            $arrReminders = $this->getTriggers()->getRemindersByTriggerType(SystemTriggers::EVENT_CRON);

            // Filter reminders (make sure that all conditions in each reminder are due/true)
            // And search for cases/clients that met conditions' requirements (for each reminder)
            $arrReminders = $this->getConditions()->filterRemindersByDueConditionsForAllCompanies($arrReminders);

            // Run actions for filtered reminders
            $arrLogRecords = array();
            foreach ($arrReminders as $arrReminderInfo) {
                $this->getActions()->processAutomaticReminderActions(
                    $arrReminderInfo,
                    SystemTriggers::EVENT_CRON
                );

                $arrLogRecords [] = $arrReminderInfo['reminder'] . '(' . $arrReminderInfo['automatic_reminder_id'] . ':' . $arrReminderInfo['member_id'] . ')';
            }

            if (count($arrLogRecords)) {
                $strLogMessage = 'are due: ' . implode(', ', $arrLogRecords);
            } else {
                $strLogMessage = '- nothing to process';
            }
            $this->_log->saveToCronLog(strtoupper(SystemTriggers::EVENT_CRON) . ' auto tasks ' . $strLogMessage);

        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * Process automatic reminders which were scheduled to be run in future
     * @param EventInterface $event
     */
    public function onProcessScheduledReminderActions(EventInterface $event)
    {
        try {
            $this->getActions()->processScheduledReminderActions();
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    public function onClientDateFieldValuesChanged(EventInterface $event) {
        try {
            $memberId = $event->getParam('member_id');
            $modifiedFields = $event->getParam('modified_fields');

            // Get tasks to update
            $select = (new Select())
                ->from('u_tasks')
                ->columns(['task_id', 'number', 'days', 'ba', 'prof'])
                ->where([
                    'member_id' => (int)$memberId,
                    'type'      => 'P',
                    'prof'      => array_keys($modifiedFields),
                    'is_due'    => 'N'
                ]);

            $arrTasks = $this->_db2->fetchAll($select);

            // Get new "due on" date and update task
            foreach ($arrTasks as $arrTaskInfo) {
                $date = $this->getConditions()->calculateProfDate(
                    array(
                        'member_id' => $memberId,
                        'days'      => $arrTaskInfo['days'],
                        'number'    => $arrTaskInfo['number'],
                        'ba'        => $arrTaskInfo['ba'],
                        'prof'      => $arrTaskInfo['prof'],
                        'prof-date' => $modifiedFields[$arrTaskInfo['prof']]
                    )
                );

                $this->_tasks->changeDueOn($arrTaskInfo['task_id'], $date);
            }
        }
        catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * Action: Field value changed
     * @param EventInterface $event
     */
    public function onFieldValueChanged(EventInterface $event)
    {
        try {
            $companyId = $event->getParam('company_id');
            $memberId = $event->getParam('member_id');
            $fieldId = $event->getParam('field_id');

            if (!$memberId) return;

            // Load all reminders which trigger is "Field Value Changed"
            $arrReminders = $this->getTriggers()->getRemindersByTriggerType(SystemTriggers::EVENT_FIELD_VALUE_CHANGED, $companyId);
            foreach ($arrReminders as $key => $arrReminderInfo) {
                $arrReminders[$key]['member_id'] = $memberId;
                $arrReminders[$key]['field_id'] = $fieldId;
            }

            // Filter reminders (make sure that all triggers in each reminder are due/true
            $arrReminders = $this->getConditions()->filterRemindersByDueConditions($arrReminders, $companyId);

            // Run actions for filtered reminders
            $arrLogRecords = array();
            foreach ($arrReminders as $arrReminderInfo) {
                $this->getActions()->processAutomaticReminderActions(
                    $arrReminderInfo,
                    SystemTriggers::EVENT_FIELD_VALUE_CHANGED
                );

                $arrLogRecords [] = $arrReminderInfo['reminder'] . '(' . $arrReminderInfo['automatic_reminder_id'] . ':' . $arrReminderInfo['member_id'] . ')';
            }

            if (count($arrLogRecords)) {
                $strLogMessage = 'are due: ' . implode(', ', $arrLogRecords);
            } else {
                $strLogMessage = '- nothing to process';
            }
            $this->_log->saveToCronLog(strtoupper(SystemTriggers::EVENT_FIELD_VALUE_CHANGED) . ' auto tasks ' . $strLogMessage);

        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * Action: New payment(s) have received (paid by credit card)
     * @param EventInterface $event
     */
    public function onPaymentReceived(EventInterface $event)
    {
        try {
            $companyId = $event->getParam('company_id');
            $memberId = $event->getParam('member_id');

            // Load all reminders which trigger is "New payments have received"
            $arrReminders = $this->getTriggers()->getRemindersByTriggerType(SystemTriggers::EVENT_TRANPAGE_PAYMENT_RECEIVED, $companyId);
            foreach ($arrReminders as $key => $arrReminderInfo) {
                $arrReminders[$key]['member_id'] = $memberId;
            }

            // Filter reminders (make sure that all triggers in each reminder are due/true
            $arrReminders = $this->getConditions()->filterRemindersByDueConditions($arrReminders, $companyId);

            // Run actions for filtered reminders
            $arrLogRecords = array();
            foreach ($arrReminders as $arrReminderInfo) {
                $this->getActions()->processAutomaticReminderActions(
                    $arrReminderInfo,
                    SystemTriggers::EVENT_TRANPAGE_PAYMENT_RECEIVED
                );

                $arrLogRecords [] = $arrReminderInfo['reminder'] . '(' . $arrReminderInfo['automatic_reminder_id'] . ':' . $arrReminderInfo['member_id'] . ')';
            }

            if (count($arrLogRecords)) {
                $strLogMessage = 'are due: ' . implode(', ', $arrLogRecords);
            } else {
                $strLogMessage = '- nothing to process';
            }
            $this->_log->saveToCronLog(strtoupper(SystemTriggers::EVENT_TRANPAGE_PAYMENT_RECEIVED) . ' auto tasks ' . $strLogMessage);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * @return Actions
     */
    public function getActions()
    {
        if (is_null($this->_actions)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_actions = $this->_serviceContainer->build(Actions::class, ['parent' => $this]);
            } else {
                $this->_actions = $this->_serviceContainer->get(Actions::class);
                $this->_actions->setParent($this);
            }
        }

        return $this->_actions;
    }

    /**
     * @return Conditions
     */
    public function getConditions()
    {
        if (is_null($this->_conditions)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_conditions = $this->_serviceContainer->build(Conditions::class, ['parent' => $this]);
            } else {
                $this->_conditions = $this->_serviceContainer->get(Conditions::class);
                $this->_conditions->setParent($this);
            }
        }

        return $this->_conditions;
    }

    /**
     * @return Triggers
     */
    public function getTriggers()
    {
        if (is_null($this->_triggers)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_triggers = $this->_serviceContainer->build(Triggers::class, ['parent' => $this]);
            } else {
                $this->_triggers = $this->_serviceContainer->get(Triggers::class);
                $this->_triggers->setParent($this);
            }
        }

        return $this->_triggers;
    }

    public function onDeleteCompany(EventInterface $e)
    {
        $companyId = $e->getParam('id');
        if (!is_array($companyId)) $companyId = array($companyId);

        $this->deleteCompanyReminders($companyId);
    }

    public function onCreateDefaultCompanySections(EventInterface $e) {
        $toCompanyId = $e->getParam('toId');
        $settings = $e->getParam('settings');

        // Create default Automatic Tasks
        $this->createDefaultAutomaticReminders(0, $toCompanyId, $settings);
    }


    /**
     * Payment from Payment Schedule Table (based on date) is due
     * @param EventInterface $event
     */
    public function onPaymentIsDue(EventInterface $event) {
        try {
            $psRecords = $event->getParam('ps_records', []);

            if (!is_array($psRecords) && !($psRecords instanceof \Countable)) {
                throw new Exception('Wrong ps_records param');
            }

            $arrCasesGroupedByCompanies = array();
            foreach ($psRecords as $arrPSRecordInfo) {
                $arrCasesGroupedByCompanies[$arrPSRecordInfo['company_id']][] = $arrPSRecordInfo['member_id'];
            }

            $triggerTypeTextId = 'payment_due';
            $arrAllReminders   = array();
            foreach ($arrCasesGroupedByCompanies as $companyId => $arrCasesIds) {
                $arrCasesIds = array_unique($arrCasesIds);

                if (count($arrCasesIds)) {
                    // 3. For these companies load their auto tasks with trigger type payment_due
                    $arrCompanyReminders = $this->getTriggers()->getRemindersByTriggerType($triggerTypeTextId, $companyId);

                    foreach ($arrCasesIds as $caseId) {
                        foreach ($arrCompanyReminders as $key => $arrReminderInfo) {
                            $arrCompanyReminders[$key]['member_id'] = $caseId;
                        }
                    }

                    // 4. Filter auto tasks by due conditions for these cases
                    $arrCompanyReminders = $this->getConditions()->filterRemindersByDueConditions($arrCompanyReminders, $companyId);

                    if (count($arrCompanyReminders)) {
                        $arrAllReminders = array_merge($arrAllReminders, $arrCompanyReminders);
                    }
                }
            }

            // 5. Process all actions for all filtered auto tasks
            foreach ($arrAllReminders as $arrReminderInfo) {
                $this->getActions()->processAutomaticReminderActions(
                    $arrReminderInfo,
                    $triggerTypeTextId
                );
            }

            $strLogMessage = sprintf(
                'Payment Schedule Specific Date: %d PS records and %d automatic tasks processed',
                count($psRecords),
                count($arrAllReminders)
            );
            $this->_log->saveToCronLog($strLogMessage);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * Check if current user access to reminder
     *
     * @param int $reminderId
     * @return bool true if user has access
     */
    public function hasAccessToReminder($reminderId)
    {
        $booHasAccess = false;
        try {
            $arrReminderInfo = $this->getReminderInfo($reminderId);
            if ($this->_auth->isCurrentUserSuperadmin() || (is_array($arrReminderInfo) && array_key_exists('company_id', $arrReminderInfo) && $arrReminderInfo['company_id'] == $this->_auth->getCurrentUserCompanyId())) {
                $booHasAccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $booHasAccess;
    }

    /**
     * Load saved information of the reminder
     *
     * @param int $reminderId
     * @return array
     */
    public function getReminderInfo($reminderId)
    {
        $select = (new Select())
            ->from('automatic_reminders')
            ->where(['automatic_reminder_id' => (int)$reminderId]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Load company reminders list
     *
     * @param int $companyId
     * @param int $start
     * @param int $limit
     * @return array
     */
    public function getCompanyReminders($companyId, $start = 0, $limit = 0)
    {
        $select = (new Select())
            ->from(['r' => 'automatic_reminders'])
            ->where(['r.company_id' => (int)$companyId])
            ->order(['r.create_date', 'r.automatic_reminder_id']);

        if (!empty($limit)) {
            $select->limit($limit)->offset($start);
        }

        $arrReminders = $this->_db2->fetchAll($select);
        $totalCount   = $this->_db2->fetchResultsCount($select);

        return array($arrReminders, $totalCount);
    }

    /**
     * Load list of used reminder names for specific company (last X)
     *
     * @param $companyId
     * @return array
     */
    public function getCompanyReminderNames($companyId)
    {
        $select = (new Select())
            ->from(['r' => 'automatic_reminders'])
            ->columns(['reminder'])
            ->where(['r.company_id' => (int)$companyId])
            ->order('reminder')
            ->group('reminder')
            ->limit(1000);

        $arrReminders = $this->_db2->fetchCol($select);

        $arrReminderNames = array();
        foreach ($arrReminders as $reminderName) {
            $arrReminderNames[] = array($reminderName);
        }

        return $arrReminderNames;
    }

    /**
     * Load list of reminders formatted to be showed in the GUI
     *
     * @param $start
     * @param $limit
     * @return array
     */
    public function getGrid($start, $limit)
    {
        $companyId = $this->_auth->getCurrentUserCompanyId();
        list($arrReminders, $totalCount) = $this->getCompanyReminders($companyId, $start, $limit);

        $arrResult   = array();
        foreach ($arrReminders as $arrReminderInfo) {
            list($arrTriggers, ,) = $this->getTriggers()->getReminderTriggers($companyId, $arrReminderInfo['automatic_reminder_id']);

            $arrTriggerTypes = [];
            foreach ($arrTriggers as $arrTriggerInfo) {
                $arrTriggerTypes[] = $arrTriggerInfo['trigger_type_id'];
            }

            $arrResult[] = array(
                'reminder_id'   => $arrReminderInfo['automatic_reminder_id'],
                'reminder'      => $arrReminderInfo['reminder'],
                'triggers'      => $this->getTriggers()->getReadableReminderTriggers($arrTriggers),
                'trigger_types' => $arrTriggerTypes,
                'conditions'    => $this->getConditions()->getReadableReminderConditions($companyId, $arrReminderInfo['automatic_reminder_id']),
                'actions'       => $this->getActions()->getReadableReminderActions($companyId, $arrReminderInfo['automatic_reminder_id'])
            );
        }

        return array($arrResult, $totalCount);
    }

    /**
     * Create/update reminder
     *
     * @param int $companyId
     * @param int $reminderId
     * @param string $reminder
     * @param bool $booActiveClientsOnly
     * @return array
     */
    public function createUpdateReminder($companyId, $reminderId, $reminder, $booActiveClientsOnly)
    {
        $strError = '';

        try {
            $reminderId = empty($reminderId) ? 0 : $reminderId;

            $arrReminderInfo = array(
                'company_id'          => $companyId,
                'reminder'            => $reminder,
                'active_clients_only' => $booActiveClientsOnly ? 'Y' : 'N',
                'create_date'         => date('Y-m-d H:i:s')
            );

            if (empty($reminderId)) {
                $reminderId = $this->_db2->insert('automatic_reminders', $arrReminderInfo);
            } else {
                unset($arrReminderInfo['create_date'], $arrReminderInfo['company_id']);
                $this->_db2->update('automatic_reminders', $arrReminderInfo, ['automatic_reminder_id' => $reminderId]);
            }
        } catch (Exception $e) {
            $reminderId = 0;
            $strError   = 'Internal error.';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($strError, $reminderId);
    }

    /**
     * Delete specific automatic reminder
     *
     * @param int $companyId
     * @param int $reminderId
     * @return int
     */
    public function deleteReminder($companyId, $reminderId)
    {
        return $this->_db2->delete(
            'automatic_reminders',
            [
                'company_id'            => (int)$companyId,
                'automatic_reminder_id' => (int)$reminderId
            ]
        );
    }

    /**
     * Delete all reminders related to specific company(s)
     *
     * @param int|array $companyId
     */
    public function deleteCompanyReminders($companyId)
    {
        if (!is_array($companyId)) {
            $companyId = array($companyId);
        }

        if (!empty($companyId)) {
            $this->_db2->delete('automatic_reminders', ['company_id' => $companyId]);
        }
    }

    /**
     * Load detailed info (with additional fields) about automatic reminder
     *
     * @param $companyId
     * @param int $reminderId
     * @return array
     */
    public function getDetailedReminderInfo($companyId, $reminderId)
    {
        try {
            $booShowChangedFieldCondition = $booLastFieldValueChangedTrigger = false;
            // Don't do extra request for new reminder
            if (!empty($reminderId)) {
                list($arrTriggers, $booShowChangedFieldCondition, $booLastFieldValueChangedTrigger) = $this->getTriggers()->getReminderTriggers($companyId, $reminderId);
                $arrActions      = $this->getActions()->getReminderActions($companyId, $reminderId);
                $arrConditions   = $this->getConditions()->getReminderConditions($companyId, $reminderId, array(), true);
                $arrReminderInfo = $this->getReminderInfo($reminderId);
            } else {
                $arrTriggers     = array();
                $arrActions      = array();
                $arrConditions   = array();
                $arrReminderInfo = array();
            }

            $reminderInfo = array(
                'reminder'  => $arrReminderInfo,
                'reminders' => $this->getCompanyReminderNames($companyId),

                'triggers' => array(
                    'rows'                            => $arrTriggers,
                    'totalCount'                      => count($arrTriggers),
                    'booShowChangedFieldCondition'    => $booShowChangedFieldCondition,
                    'booLastFieldValueChangedTrigger' => $booLastFieldValueChangedTrigger
                ),

                'actions' => array(
                    'rows'       => $arrActions,
                    'totalCount' => count($arrActions)
                ),

                'conditions' => array(
                    'rows'                         => $arrConditions,
                    'totalCount'                   => count($arrConditions),
                    'booHasChangedFieldConditions' => $this->getConditions()->hasChangedFieldConditions($reminderId)
                )
            );

        } catch (Exception $e) {
            $reminderInfo = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $reminderInfo;
    }

    /**
     * Create default automatic reminders for specific company
     *
     * @param int $fromCompanyId
     * @param int $toCompanyId
     * @param $arrCompanyDefaultSettings
     */
    public function createDefaultAutomaticReminders($fromCompanyId, $toCompanyId, $arrCompanyDefaultSettings)
    {
        list($arrDefaultReminders,) = $this->getCompanyReminders($fromCompanyId);
        if (is_array($arrDefaultReminders)) {
            // Duplicate reminders + their triggers, actions and conditions
            foreach ($arrDefaultReminders as $defaultReminderInfo) {
                $defaultReminderId                            = $defaultReminderInfo['automatic_reminder_id'];
                $defaultReminderInfo['automatic_reminder_id'] = 0;
                $defaultReminderInfo['company_id']            = $toCompanyId;
                $defaultReminderInfo['create_date']           = date('Y-m-d');

                $newReminderId = $this->_db2->insert('automatic_reminders', $defaultReminderInfo);

                $this->getTriggers()->createDefaultAutomaticReminderTriggers(
                    $fromCompanyId,
                    $toCompanyId,
                    $defaultReminderId,
                    $newReminderId
                );

                $this->getConditions()->createDefaultAutomaticReminderConditions(
                    $fromCompanyId,
                    $toCompanyId,
                    $defaultReminderId,
                    $newReminderId,
                    $arrCompanyDefaultSettings
                );

                $this->getActions()->createDefaultAutomaticReminderActions(
                    $fromCompanyId,
                    $toCompanyId,
                    $defaultReminderId,
                    $newReminderId,
                    $arrCompanyDefaultSettings
                );
            }
        }
    }
}
