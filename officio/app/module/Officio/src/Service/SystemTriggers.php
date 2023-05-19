<?php

namespace Officio\Service;

use Clients\Service\Clients;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ResponseCollection;
use Laminas\ModuleManager\ModuleManager;
use Laminas\ServiceManager\ServiceManager;
use Officio\Common\Service\BaseService;


/**
 * Handles system-wide events. If a modules has a listener which is a service, module class has to implement interface SystemTriggersListener,
 * so it will initialize all the necessary services when an event happens, otherwise an event handler is not guaranteed to execute.
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class SystemTriggers extends BaseService implements EventManagerAwareInterface
{

    public const EVENT_FORM_COMPLETE = 'case_mark_form_as_complete';
    public const EVENT_DOCUMENT_UPLOADED = 'case_uploads_documents';
    public const EVENT_ADDITIONAL_DOCUMENT_UPLOADED = 'upload_additional_documents';
    public const EVENT_FIELD_VALUES_CHANGED = 'fields_changed';
    public const EVENT_FIELD_VALUE_CHANGED = 'field_value_change';
    public const EVENT_FILE_STATUS_CHANGED = 'case_status_changed';
    public const EVENT_TRANPAGE_PAYMENT_RECEIVED = 'payments_have_received';
    public const EVENT_PAYMENT_ADDED = 'payment_added';
    public const EVENT_PAYMENT_IS_DUE = 'payment_is_due';
    public const EVENT_PROCESS_SCHEDULED_REMINDER_ACTIONS = 'process_scheduler_reminders';
    public const EVENT_PROFILE_DATE_FIELD_CHANGED = 'profile_date_field_changed';
    public const EVENT_CLIENT_DATE_FIELDS_CHANGED = 'client_date_fields_changed';
    public const EVENT_PROFILE_UPDATED = 'client_or_case_profile_update';
    public const EVENT_CASE_CREATED = 'case_creation';
    public const EVENT_CRON = 'cron';
    public const EVENT_PROCESS_TASK_SMS = 'process_task_sms';

    public const EVENT_COMPANY_ENABLE_PROSPECTS = 'company_enable_prospects';
    public const EVENT_COMPANY_DELETE = 'company_delete';
    public const EVENT_COMPANY_COPY_DEFAULT_SETTINGS = 'company_copy_default_settings';
    public const EVENT_COMPANY_CREATE_DEFAULT_SECTIONS = 'company_create_default_sections';

    public const EVENT_USER_CREATED = 'user_created';
    public const EVENT_MEMBER_DELETED = 'member_deleted';

    /** @var EventManagerInterface */
    private $_eventManager;

    /** @var array */
    private $_listeners = [];

    /** @var bool */
    private $_listenersInitialized = false;

    /** @var ModuleManager */
    protected $_moduleManager;

    /** @var ServiceManager */
    protected $_serviceManager;

    public function initAdditionalServices(array $services)
    {
        $this->_moduleManager = $services['ModuleManager'];
        $this->_serviceManager = $services['ServiceManager'];
    }

    /**
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        if (!$this->_eventManager) {
            $this->setEventManager(new EventManager());
        }
        return $this->_eventManager;
    }

    public function setEventManager(EventManagerInterface $eventManager)
    {
        $eventManager->setIdentifiers(
            [
                __CLASS__,
                get_class($this)
            ]
        );
        $this->_eventManager = $eventManager;
    }

    public function init() {
        $this->scanForListeners();
    }

    private function _triggerListenersCreation() {
        if (!$this->_listenersInitialized) {
            foreach ($this->_listeners as $listener) {
                $this->_serviceManager->get($listener);
            }
            $this->_listenersInitialized = true;
        }
    }

    protected function scanForListeners() {
        $modules = $this->_moduleManager->getLoadedModules();
        foreach ($modules as $module) {
            if ($module instanceof SystemTriggersListener) {
                $this->_listeners = array_merge($this->_listeners, $module->getSystemTriggerListeners());
            }
        }
    }

    public function triggerUserCreated($memberId) {
        $this->_triggerListenersCreation();
        $this->getEventManager()->trigger(SystemTriggers::EVENT_USER_CREATED, $this, [
            'id' => $memberId,
        ]);
    }

    public function triggerMemberDeleted($companyId, $memberIds) {
        $this->_triggerListenersCreation();
        $this->getEventManager()->trigger(SystemTriggers::EVENT_MEMBER_DELETED, $this, [
            'id' => $memberIds,
            'companyId' => $companyId,
        ]);
    }

    /**
     * Delete company event
     * @param $companyIds
     */
    public function triggerCompanyDelete($companyIds) {
        $this->_triggerListenersCreation();
        $this->getEventManager()->trigger(self::EVENT_COMPANY_DELETE, $this, [
            'id' => $companyIds
        ]);
    }

    /**
     * @param ?int $fromCompanyId
     * @param $toCompanyId
     * @param $companyDefaultSettings
     * @param $folderMapping
     */
    public function triggerCreateCompanyDefaultSections($fromCompanyId, $toCompanyId, $companyDefaultSettings, $folderMapping) {
        $this->_triggerListenersCreation();
        $this->getEventManager()->trigger(self::EVENT_COMPANY_CREATE_DEFAULT_SECTIONS, $this, [
            'fromId'         => $fromCompanyId,
            'toId'           => $toCompanyId,
            'settings'       => $companyDefaultSettings,
            'foldersMapping' => $folderMapping
        ]);
    }

    /**
     * Create default settings on new company creation
     *
     * @param int $fromCompanyId
     * @param int $toCompanyId
     * @param array $mappingRoles
     * @param array $mappingDefaultCategories
     * @param array $mappingDefaultCaseStatuses
     * @param array $arrMappingDefaultCaseStatusLists
     * @param array $arrMappingCaseTemplates
     * @return ResponseCollection
     */
    public function triggerCopyCompanyDefaultSettings($fromCompanyId, $toCompanyId, $mappingRoles, $mappingDefaultCategories, $mappingDefaultCaseStatuses, $arrMappingDefaultCaseStatusLists, $arrMappingCaseTemplates)
    {
        $this->_triggerListenersCreation();
        return $this->getEventManager()->trigger(self::EVENT_COMPANY_COPY_DEFAULT_SETTINGS, $this, [
            'fromId'                        => $fromCompanyId,
            'toId'                          => $toCompanyId,
            'mappingRoles'                  => $mappingRoles,
            'mappingDefaultCategories'      => $mappingDefaultCategories,
            'mappingDefaultCaseStatuses'    => $mappingDefaultCaseStatuses,
            'mappingDefaultCaseStatusLists' => $arrMappingDefaultCaseStatusLists,
            'mappingCaseTemplates'          => $arrMappingCaseTemplates,
        ]);
    }

    /**
     * Company prospects got turned on
     * @param $companyId
     */
    public function triggerEnableCompanyProspects($companyId) {
        $this->_triggerListenersCreation();
        $this->getEventManager()->trigger(self::EVENT_COMPANY_ENABLE_PROSPECTS, $this, [
            'company_id' => $companyId
        ]);
    }

    /**
     * Action: Client Form marked as Complete
     *
     * @param $memberId
     * @param $companyId
     */
    public function triggerFormComplete($companyId, $memberId)
    {
        $this->_triggerListenersCreation();
        $this->getEventManager()->trigger(self::EVENT_FORM_COMPLETE, $this,
            [
                'company_id' => $companyId,
                'member_id' => $memberId
            ]
        );
    }

    /**
     * Action: Client uploaded file(s)
     *
     * @param $companyId
     * @param $memberId
     */
    public function triggerUploadedDocuments($companyId, $memberId)
    {
        $this->_triggerListenersCreation();
        $this->getEventManager()->trigger(self::EVENT_DOCUMENT_UPLOADED, $this,
            [
              'company_id' => $companyId,
              'member_id' => $memberId
            ]
        );
    }

    /**
     * Action: New file(s) uploaded to the Additional Documents Folder
     *
     * @param $companyId
     * @param $memberId
     */
    public function triggerUploadedAdditionalDocuments($companyId, $memberId)
    {
        $this->_triggerListenersCreation();
        $this->getEventManager()->trigger(self::EVENT_ADDITIONAL_DOCUMENT_UPLOADED, $this, [
            'company_id' => $companyId,
            'member_id' => $memberId
        ]);
    }

    /**
     * Action: New payment(s) have received (paid by credit card)
     * @param $companyId
     * @param $memberId
     */
    public function triggerTranpagePaymentReceived($companyId, $memberId)
    {
        $this->_triggerListenersCreation();
        $this->getEventManager()->trigger(self::EVENT_TRANPAGE_PAYMENT_RECEIVED, $this, [
            'company_id' => $companyId,
            'member_id' => $memberId
        ]);
    }

    /**
     * Action: Client/case profile was updated
     * @param $companyId
     * @param $memberId
     */
    public function triggerProfileUpdate($companyId, $memberId)
    {
        $this->_triggerListenersCreation();
        $this->getEventManager()->trigger(self::EVENT_PROFILE_UPDATED, $this,
            [
              'company_id' => $companyId,
              'member_id' => $memberId
            ]
        );
    }

    /**
     * Action: Case creation
     *
     * @param int $companyId
     * @param int $memberId
     * @param int $authorId
     * @param string $authorName
     */
    public function triggerCaseCreation($companyId, $memberId, $authorId, $authorName)
    {
        $this->_triggerListenersCreation();
        $this->getEventManager()->trigger(self::EVENT_CASE_CREATED, $this, [
                'company_id'  => $companyId,
                'member_id'   => $memberId,
                'author_id'   => $authorId,
                'author_name' => $authorName,
            ]
        );
    }

    /**
     * Action: Field value changed
     * @param $companyId
     * @param $memberId
     * @param $fieldId
     */
    public function triggerFieldValueChange($companyId, $memberId, $fieldId)
    {
        $this->_triggerListenersCreation();
        $this->getEventManager()->trigger(self::EVENT_FIELD_VALUE_CHANGED, $this,
          [
              'company_id' => $companyId,
              'member_id' => $memberId,
              'field_id' => $fieldId
          ]
        );
    }

    /**
     * Action: Cron was processed
     */
    public function triggerCronReminders()
    {
        $this->_triggerListenersCreation();
        $this->getEventManager()->trigger(self::EVENT_CRON, $this);
    }

    /**
     * Process automatic reminders which were scheduled to be run in future
     */
    public function triggerProcessScheduledReminderActions()
    {
        $this->_triggerListenersCreation();
        $this->getEventManager()->trigger(self::EVENT_PROCESS_SCHEDULED_REMINDER_ACTIONS, $this);
    }

    /**
     * Action: Added New Payment
     * Result: Call triggerFileStatusChanged() if current options selected in case profile 'File Status' field
     * @param $memberId
     * @param $newValue
     */
    public function triggerPaymentAdded($memberId, $newValue)
    {
        $this->_triggerListenersCreation();
        $this->getEventManager()->trigger(self::EVENT_PAYMENT_ADDED, $this, [
            'member_id' => $memberId,
            'new_value' => $newValue
        ]);
    }

    /**
     * Action: Case Status changed
     * Result:
     * 1. Insert new Fees Due
     * 2. Process auto tasks which trigger is "Case File Status changed"
     * @param int $memberId
     * @param array $arrOldFileStatuses
     * @param array $arrNewFileStatuses
     * @param int $authorId
     * @param string $authorName
     * @param array $arrPSRecords
     * @return bool
     */
    public function triggerFileStatusChanged($memberId, $arrOldFileStatuses, $arrNewFileStatuses, $authorId = 0, $authorName = '', $arrPSRecords = [])
    {
        if (empty($arrOldFileStatuses) && empty($arrNewFileStatuses)) {
            return true;
        }

        $this->_triggerListenersCreation();
        $this->getEventManager()->trigger(self::EVENT_FILE_STATUS_CHANGED, $this, [
            'member_id'    => $memberId,
            'author_id'    => $authorId,
            'author_name'  => $authorName,
            'old_statuses' => $arrOldFileStatuses,
            'new_statuses' => $arrNewFileStatuses,
            'ps_records'   => $arrPSRecords
        ]);

        return true;
    }

    /**
     * Generate changes between new and old (already saved) data
     *
     * Such actions will be logged:
     * 1. Client/case changes in profile
     * 2. Dependents section for case
     * 3. Client/case file fields
     * 4. Client login/pass change
     * 5. Some static fields? (Case File #)
     * 6. Update offices from other places
     *
     * What is not done:
     * 5. Update case status from other places
     *
     * @param int $companyId
     * @param array $arrAllFieldsData
     * @param bool $booTriggerAutomaticTasks
     * @param string $triggeredBy
     */
    public function triggerFieldBulkChanges($companyId, $arrAllFieldsData, $booTriggerAutomaticTasks = false, $triggeredBy = '')
    {
        $this->_triggerListenersCreation();
        $results = $this->getEventManager()->trigger(self::EVENT_FIELD_VALUES_CHANGED, $this, [
            'company_id' => $companyId,
            'changes' => $arrAllFieldsData,
            'triggered_by' => $triggeredBy
        ]);

        $arrLogGroupedByClients = [];
        $results->rewind();
        while ($results->valid()) {
            $result = $results->current();
            if (is_array($result) && isset($result[Clients::class])) {
                $arrLogGroupedByClients = $result[Clients::class];
                break;
            }

            $results->next();
        }

        if ($booTriggerAutomaticTasks) {
            foreach ($arrLogGroupedByClients as $clientId => $arrClientNotes) {
                foreach ($arrClientNotes as $strNote) {
                    if (isset($strNote['field_id'])) {
                        $this->triggerFieldValueChange($companyId, $clientId, $strNote['field_id']);
                    }
                }
            }
        }
    }

    /**
     * Case's date fields were changed - automatically process:
     *  - tasks based on the fields
     *  - records from PS (Payment Schedule) table based on date fields
     * @param $memberId
     * @param $modifiedFields
     */
    public function triggerClientDateFieldsChanged($memberId, $modifiedFields)
    {
        $this->_triggerListenersCreation();
        $this->getEventManager()->trigger(self::EVENT_CLIENT_DATE_FIELDS_CHANGED, $this, [
            'member_id' => $memberId,
            'modified_fields' => $modifiedFields
        ]);

        $this->triggerProfileDateFieldChanged($memberId);
    }

    /**
     * New payment was added in PS table or client's profile was updated (date field was changed)
     * 1. Load all PS records with date fields
     * 2. Load all client's data for these date fields
     * 3. Compare found values with 'now'
     * 4. Mark all found records as due + create tasks
     * @param int $memberId
     */
    public function triggerProfileDateFieldChanged($memberId = null)
    {
        $this->_triggerListenersCreation();
        $this->getEventManager()->trigger(self::EVENT_PROFILE_DATE_FIELD_CHANGED, $this, [
            'member_id' => $memberId
        ]);
    }

    /**
     * Payment from Payment Schedule Table (based on date) is due
     * @param int $memberId
     */
    public function triggerPaymentScheduleDateIsDue($memberId = 0)
    {
        $arrWhere              = [];
        $arrWhere[]            = (new Where())->isNotNull('ps.based_on_date');
        $arrWhere[]            = (new Where())->isNull('ps.based_on_profile_date_field');
        $arrWhere[]            = (new Where())->lessThanOrEqualTo('ps.based_on_date', date('Y-m-d H:i:s'));
        $arrWhere['ps.status'] = 0;

        if (!empty($memberId)) {
            $arrWhere['ps.member_id'] = (int)$memberId;
        }

        // 1. Load all PS records with date fields
        $select = (new Select())
            ->from(array('ps' => 'u_payment_schedule'))
            ->join(array('ta' => 'members_ta'), new PredicateExpression('ta.member_id = ps.member_id AND ta.order = 0'), ['primary_company_ta_id' => 'company_ta_id'], Select::JOIN_LEFT)
            ->join(array('m' => 'members'), 'm.member_id = ps.member_id', 'company_id')
            ->where($arrWhere);

        $arrPSRecords = $this->_db2->fetchAll($select);

        foreach ($arrPSRecords as $key => $arrPSRecord) {
            // Use client's primary T/A if PS record isn't assigned to a specific T/A
            $arrPSRecords[$key]['company_ta_id'] = empty($arrPSRecord['company_ta_id']) ? $arrPSRecord['primary_company_ta_id'] : $arrPSRecord['company_ta_id'];

            // Set payment record's date to the date set in the PS record
            $arrPSRecords[$key]['payment_date_of_event'] = $arrPSRecord['based_on_date'];

            unset($arrPSRecords[$key]['primary_company_ta_id']);
        }

        $this->_triggerListenersCreation();
        $this->getEventManager()->trigger(
            self::EVENT_PAYMENT_IS_DUE,
            $this,
            [
                'member_id'  => $memberId,
                'ps_records' => $arrPSRecords
            ]
        );
    }

    public function triggerTaskSms($booShowResults = true)
    {
        $this->_triggerListenersCreation();
        $results = $this->getEventManager()->trigger(self::EVENT_PROCESS_TASK_SMS, $this);
        if ($booShowResults) {
            $results->rewind();
            while ($results->valid()) {
                echo $results->current();
                $results->next();
            }
        }
    }
}
