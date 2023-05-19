<?php

namespace Clients\Service;

use Clients\Service\Clients\Accounting;
use Clients\Service\Clients\ApplicantFields;
use Clients\Service\Clients\ApplicantTypes;
use Clients\Service\Clients\CaseCategories;
use Clients\Service\Clients\CaseNumber;
use Clients\Service\Clients\CaseStatuses;
use Clients\Service\Clients\CaseTemplates;
use Clients\Service\Clients\CaseVACs;
use Clients\Service\Clients\ClientsDependentsChecklist;
use Clients\Service\Clients\Fields;
use Clients\Service\Clients\FieldTypes;
use Clients\Service\Clients\Search;
use DateTime;
use Exception;
use Forms\Service\Forms;
use Forms\Service\Pdf;
use Help\Service\Help;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;
use Laminas\Db\Sql\Predicate\Predicate;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\TableIdentifier;
use Laminas\Db\Sql\Where;
use Laminas\EventManager\EventInterface;
use Laminas\Filter\StripTags;
use Uniques\Php\StdLib\FileTools;
use Officio\Common\Json;
use Laminas\ServiceManager\ServiceManager;
use Mailer\Service\Mailer;
use Officio\Common\Service\AccessLogs;
use Officio\Service\AuthHelper;
use Officio\Service\AutomaticReminders\Actions;
use Officio\Service\ConditionalFields;
use Officio\Common\Service\Settings;
use Officio\Service\SystemTriggers;
use Officio\Service\Users;
use Officio\Templates\SystemTemplates;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Prospects\Service\CompanyProspects;
use Tasks\Service\Tasks;
use Templates\Service\Templates;
use Laminas\Validator\EmailAddress;

/**
 * Clients' related functionality
 * E.g.: load client's profile info
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Clients extends Members
{

    public static $exportClientsLimit = 10000;

    /** @var Fields */
    protected $_fields;

    /** @var Tasks */
    protected $_tasks;

    /** @var ConditionalFields */
    protected $_conditionalFields;

    /** @var ApplicantFields */
    protected $_applicantFields;

    /** @var ApplicantTypes */
    protected $_applicantTypes;

    /** @var FieldTypes */
    protected $_fieldTypes;

    /** @var CaseTemplates */
    protected $_caseTemplates;

    /** @var CaseVACs */
    protected $_caseVACs;

    /** @var CaseCategories */
    protected $_caseCategories;

    /** @var CaseStatuses */
    protected $_caseStatuses;

    /** @var CaseNumber */
    protected $_caseNumber;

    /** @var ClientsDependentsChecklist */
    protected $_clientDependents;

    /** @var Accounting */
    protected $_accounting;

    /** @var Pdf */
    protected $_pdf;

    /** @var Mailer */
    protected $_mailer;

    /** @var Search */
    protected $_search;

    /** @var Forms */
    protected $_forms;

    /** @var Help */
    protected $_help;

    /** @var SystemTemplates */
    protected $_systemTemplates;

    public function initAdditionalServices(array $services)
    {
        parent::initAdditionalServices($services);
        $this->_forms           = $services[Forms::class];
        $this->_help            = $services[Help::class];
        $this->_pdf             = $services[Pdf::class];
        $this->_mailer          = $services[Mailer::class];
        $this->_systemTemplates = $services[SystemTemplates::class];
    }

    public function init()
    {
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_COMPANY_COPY_DEFAULT_SETTINGS, [$this, 'onCopyCompanyDefaultSettings']);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_COMPANY_DELETE, [$this, 'onDeleteCompany']);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_PAYMENT_ADDED, [$this, 'onPaymentAdded']);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_PAYMENT_IS_DUE, [$this, 'onPaymentIsDue']);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_FILE_STATUS_CHANGED, [$this, 'onFileStatusChanged']);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_PROCESS_TASK_SMS, [$this, 'onProcessTaskSms']);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_PROFILE_DATE_FIELD_CHANGED, [$this, 'onProfileDateFieldChanged']);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_MEMBER_DELETED, [$this, 'onDeleteMember']);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_USER_CREATED, [$this, 'onCreateUser']);
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_FIELD_VALUES_CHANGED, [$this, 'onFieldsBulkChanges']);

        $this->_systemTemplates->getEventManager()->attach(SystemTemplates::EVENT_GET_AVAILABLE_FIELDS, [$this, 'getSystemTemplateFields']);
    }

    /**
     * Get instance of the Search class
     *
     * @return Search
     */
    public function getSearch()
    {
        if (is_null($this->_search)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_search = $this->_serviceContainer->build(Search::class, ['parent' => $this]);
            } else {
                $this->_search = $this->_serviceContainer->get(Search::class);
                $this->_search->setParent($this);
            }
        }

        return $this->_search;
    }

    /**
     * Get instance of the Tasks class
     *
     * @return Tasks
     */
    public function getTasks()
    {
        if (is_null($this->_tasks)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_tasks = $this->_serviceContainer->build(Tasks::class, ['parent' => $this]);
            } else {
                $this->_tasks = $this->_serviceContainer->get(Tasks::class);
                $this->_tasks->setParent($this);
            }
        }

        return $this->_tasks;
    }

    /**
     * Get instance of the Fields class
     *
     * @return Fields
     */
    public function getFields()
    {
        if (is_null($this->_fields)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_fields = $this->_serviceContainer->build(Fields::class, ['parent' => $this]);
            } else {
                $this->_fields = $this->_serviceContainer->get(Fields::class);
                $this->_fields->setParent($this);
            }
        }

        return $this->_fields;
    }

    /**
     * Get instance of the ConditionalFields class
     *
     * @return ConditionalFields
     */
    public function getConditionalFields()
    {
        if (is_null($this->_conditionalFields)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_conditionalFields = $this->_serviceContainer->build(ConditionalFields::class, ['parent' => $this]);
            } else {
                $this->_conditionalFields = $this->_serviceContainer->get(ConditionalFields::class);
                $this->_conditionalFields->setParent($this);
            }
        }

        return $this->_conditionalFields;
    }

    /**
     * Get instance of the ApplicantFields class
     *
     * @return ApplicantFields
     */
    public function getApplicantFields()
    {
        if (is_null($this->_applicantFields)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_applicantFields = $this->_serviceContainer->build(ApplicantFields::class, ['parent' => $this]);
            } else {
                $this->_applicantFields = $this->_serviceContainer->get(ApplicantFields::class);
                $this->_applicantFields->setParent($this);
            }
        }
        return $this->_applicantFields;
    }

    /**
     * Get instance of the ApplicantTypes class
     *
     * @return ApplicantTypes
     */
    public function getApplicantTypes()
    {
        if (is_null($this->_applicantTypes)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_applicantTypes = $this->_serviceContainer->build(ApplicantTypes::class, ['parent' => $this]);
            } else {
                $this->_applicantTypes = $this->_serviceContainer->get(ApplicantTypes::class);
                $this->_applicantTypes->setParent($this);
            }
        }

        return $this->_applicantTypes;
    }

    /**
     * Get instance of the CaseTemplates class
     *
     * @return CaseTemplates
     */
    public function getCaseTemplates()
    {
        if (is_null($this->_caseTemplates)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_caseTemplates = $this->_serviceContainer->build(CaseTemplates::class, ['parent' => $this]);
            } else {
                $this->_caseTemplates = $this->_serviceContainer->get(CaseTemplates::class);
                $this->_caseTemplates->setParent($this);
            }
        }

        return $this->_caseTemplates;
    }

    /**
     * Get instance of the CaseVACs class
     *
     * @return CaseVACs
     */
    public function getCaseVACs()
    {
        if (is_null($this->_caseVACs)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_caseVACs = $this->_serviceContainer->build(CaseVACs::class, ['parent' => $this]);
            } else {
                $this->_caseVACs = $this->_serviceContainer->get(CaseVACs::class);
                $this->_caseVACs->setParent($this);
            }
        }

        return $this->_caseVACs;
    }

    /**
     * Get instance of the CaseCategories class
     *
     * @return CaseCategories
     */
    public function getCaseCategories()
    {
        if (is_null($this->_caseCategories)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_caseCategories = $this->_serviceContainer->build(CaseCategories::class, ['parent' => $this]);
            } else {
                $this->_caseCategories = $this->_serviceContainer->get(CaseCategories::class);
                $this->_caseCategories->setParent($this);
            }
        }

        return $this->_caseCategories;
    }

    /**
     * Get instance of the CaseStatuses class
     *
     * @return CaseStatuses
     */
    public function getCaseStatuses()
    {
        if (is_null($this->_caseStatuses)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_caseStatuses = $this->_serviceContainer->build(CaseStatuses::class, ['parent' => $this]);
            } else {
                $this->_caseStatuses = $this->_serviceContainer->get(CaseStatuses::class);
                $this->_caseStatuses->setParent($this);
            }
        }

        return $this->_caseStatuses;
    }

    /**
     * Get instance of the FieldTypes class
     *
     * @return FieldTypes
     */
    public function getFieldTypes()
    {
        if (is_null($this->_fieldTypes)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_fieldTypes = $this->_serviceContainer->build(FieldTypes::class, ['parent' => $this]);
            } else {
                $this->_fieldTypes = $this->_serviceContainer->get(FieldTypes::class);
                $this->_fieldTypes->setParent($this);
            }
        }

        return $this->_fieldTypes;
    }

    /**
     * Get instance of the CaseNumber class
     *
     * @return CaseNumber
     */
    public function getCaseNumber()
    {
        if (is_null($this->_caseNumber)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_caseNumber = $this->_serviceContainer->build(CaseNumber::class, ['parent' => $this]);
            } else {
                $this->_caseNumber = $this->_serviceContainer->get(CaseNumber::class);
                $this->_caseNumber->setParent($this);
            }
        }

        return $this->_caseNumber;
    }

    /**
     * Get instance of the ClientsDependentsChecklist class
     *
     * @return ClientsDependentsChecklist
     */
    public function getClientDependents()
    {
        if (is_null($this->_clientDependents)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_clientDependents = $this->_serviceContainer->build(ClientsDependentsChecklist::class, ['parent' => $this]);
            } else {
                $this->_clientDependents = $this->_serviceContainer->get(ClientsDependentsChecklist::class);
                $this->_clientDependents->setParent($this);
            }
        }

        return $this->_clientDependents;
    }

    /**
     * Get instance of the Accounting class
     *
     * @return Accounting
     */
    public function getAccounting()
    {
        if (is_null($this->_accounting)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_accounting = $this->_serviceContainer->build(Accounting::class, ['parent' => $this]);
            } else {
                $this->_accounting = $this->_serviceContainer->get(Accounting::class);
                $this->_accounting->setParent($this);
            }
        }

        return $this->_accounting;
    }

    public function onCreateUser(EventInterface $event)
    {
        try {
            $id = $event->getParam('id');
            $this->getSearch()->setMemberDefaultSearch($id);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    public function onDeleteMember(EventInterface $event)
    {
        try {
            $arrayIds = $event->getParam('id');
            $this->getSearch()->deleteMembersDefaultSearch($arrayIds);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * Payment from Payment Schedule Table (based on date) is due
     * @param EventInterface $event
     */
    public function onPaymentIsDue(EventInterface $event)
    {
        try {
            $psRecords = $event->getParam('ps_records');

            // Insert new Fees Due and mark payments as complete
            // We need to do this before auto task(s) will be processed - to be sure that template(s) will have correct processed values
            if (!empty($psRecords)) {
                $this->getAccounting()->insertFinancialTransactions($psRecords);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    public function onProcessTaskSms(EventInterface $event)
    {
        $results = [];

        try {
            $companyId = $this->_config['sms']['company_id']; // We need to add this feature for the company "Premiers" company id: 738

            $fieldId = $this->getFields()->getCompanyFieldId($companyId, 'phone_m', false);

            $arrTasks = array();
            if (!empty($fieldId)) {
                // fetch all tasks with 'is_due'='Y' and sms_processed=0
                $select = (new Select())
                    ->from(array('t' => 'u_tasks'))
                    ->columns(array('task_id', 'task', 'member_id'))
                    ->join(array('m' => 'members'), 'm.member_id = t.member_id', array('lName', 'fName', 'emailAddress'), Select::JOIN_LEFT)
                    ->join(array('fd' => 'client_form_data'), 'm.member_id = fd.member_id', array('mobilePhone' => 'value'), Select::JOIN_LEFT)
                    ->join(array('f' => 'client_form_fields'), 'fd.field_id = f.field_id', 'encrypted', Select::JOIN_LEFT)
                    ->where(
                        [
                            'fd.field_id'     => $fieldId,
                            't.is_due'        => 'Y',
                            't.sms_processed' => 0,
                            't.notify_client' => 'Y',
                            (new Where())->isNotNull('m.member_id')
                        ]
                    );

                // If needed - load for specific company only
                if (!empty($companyId)) {
                    $select->where(['m.company_id' => $companyId]);
                }

                $arrTasks = $this->_db2->fetchAssoc($select);

                // Decrypt data if needed
                foreach ($arrTasks as $key => $arrTaskInfo) {
                    if ($arrTaskInfo['encrypted'] == 'Y') {
                        $arrTasks[$key]['mobilePhone'] = $this->_encryption->decode($arrTaskInfo['mobilePhone']);
                    }
                }
            }

            if (count($arrTasks)) {
                $arrTaskIds = array();
                foreach ($arrTasks as $arrTaskInfo) {
                    $arrTaskInfo  = static::generateMemberName($arrTaskInfo);
                    $arrTaskIds[] = $arrTaskInfo['task_id'];

                    if (!empty($arrTaskInfo['mobilePhone'])) {
                        $this->_db2->insert(
                            'u_sms',
                            [
                                'number'  => $arrTaskInfo['mobilePhone'],
                                'message' => $arrTaskInfo['full_name'] . ': ' . $arrTaskInfo['task'],
                                'email'   => $arrTaskInfo['emailAddress'],
                            ]
                        );

                        $results[] = "<div>sms processed (tel: <i>{$arrTaskInfo['mobilePhone']}</i>, text: <i>{$arrTaskInfo['task']}</i>)</div>";
                    } else {
                        $results[] = "<div style='color:red;'>member # <b>{$arrTaskInfo['member_id']}</b> didn't provide his tel number</div>";
                    }
                }

                if (count($arrTaskIds)) {
                    $this->_db2->update('u_tasks', ['sms_processed' => 1], ['task_id' => $arrTaskIds]);
                }
            } else {
                $results[] = '<div>No tasks to process</div>';
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $results;
    }

    /**
     * New payment was added in PS table or client's profile was updated (date field was changed)
     * 1. Load all PS records with date fields
     * 2. Load all client's data for these date fields
     * 3. Compare found values with 'now'
     * 4. Mark all found records as due + create tasks
     * @param EventInterface $event
     * @return array[]
     */
    public function onProfileDateFieldChanged(EventInterface $event)
    {
        $arrDuePSRecords = [];

        try {
            $memberId = $event->getParam('member_id');

            // 1. Load all PS records with date fields
            $select = (new Select())
                ->from(array('ps' => 'u_payment_schedule'))
                ->join(array('ta' => 'members_ta'), new PredicateExpression('ta.member_id = ps.member_id AND ta.order = 0'), ['primary_company_ta_id' => 'company_ta_id'], Select::JOIN_LEFT)
                ->where(
                    [
                        (new Where())->isNotNull('ps.based_on_profile_date_field'),
                        'ps.status' => 0
                    ]
                );

            // Load records list for specific member(s)
            $arrMemberIds = array();
            if (!is_null($memberId)) {
                $arrMemberIds[] = $memberId;

                $arrEmployers = $this->getParentsForAssignedApplicant($memberId, $this->getMemberTypeIdByName('employer'));

                if (is_array($arrEmployers) && count($arrEmployers)) {
                    $employerId = $arrEmployers[0];

                    if ($this->hasCurrentMemberAccessToMember($employerId)) {
                        $companyId = $this->_auth->getCurrentUserCompanyId();

                        list($arrAssignedCases,) = $this->getApplicantAssignedCases($companyId, $employerId, true, $memberId, null, null);

                        foreach ($arrAssignedCases as $arrCaseInfo) {
                            $arrMemberIds[] = $arrCaseInfo['child_member_id'];
                        }
                    }
                }

                $select->where(
                    [
                        'ps.member_id' => $arrMemberIds
                    ]
                );
            }

            $arrPSRecords = $this->_db2->fetchAll($select);

            // 2. Load all client's data for these date fields
            $arrDuePSRecords = array();
            foreach ($arrPSRecords as $arrPSRecord) {
                // Use client's primary T/A if PS record isn't assigned to a specific T/A
                $arrPSRecord['company_ta_id'] = empty($arrPSRecord['company_ta_id']) ? $arrPSRecord['primary_company_ta_id'] : $arrPSRecord['company_ta_id'];
                unset($arrPSRecord['primary_company_ta_id']);

                $profileFieldId = $arrPSRecord['based_on_profile_date_field'];
                if (is_null($memberId)) {
                    $currentValue = $this->getFields()->getFieldDataValue($profileFieldId, $arrPSRecord['member_id']);
                } else {
                    $currentValue = $this->getFields()->getFieldDataValue($profileFieldId, $memberId);
                }

                if (!empty($currentValue)) {
                    // 3. Compare found values with 'now'
                    $strCurrentTime = strtotime($currentValue);
                    if ($strCurrentTime <= time()) {
                        // Collect all due records

                        // Set payment record's date to what is set in the field
                        $arrPSRecord['payment_date_of_event'] = date('Y-m-d H:i:s', $strCurrentTime);

                        $arrDuePSRecords[] = $arrPSRecord;
                    }
                }
            }

            if (count($arrDuePSRecords)) {
                // Insert new Fees Due (if any)
                // We need to do this before task(s) will be created - to be sure that template(s) will have correct processed values
                $this->getAccounting()->insertFinancialTransactions($arrDuePSRecords);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $event->setParam('due_ps_records', $arrDuePSRecords);
        return [__CLASS__ => $arrDuePSRecords];
    }

    /**
     * Action: Added New Payment
     * Result: Call triggerFileStatusChanged() if current options selected in case profile 'File Status' field
     * @param EventInterface $event
     */
    public function onPaymentAdded(EventInterface $event)
    {
        try {
            $memberId = $event->getParam('member_id');
            $newValue = $event->getParam('new_value');

            $companyId         = $this->_company->getMemberCompanyId($memberId);
            $arrFileStatusInfo = $this->getFields()->getCompanyFieldInfoByUniqueFieldId('file_status', $companyId);
            $fileStatusFieldId = $arrFileStatusInfo['field_id'] ?? 0;


            if (!empty($fileStatusFieldId)) {
                $oldValue = $this->getFields()->getFieldDataValue($fileStatusFieldId, $memberId);

                if ($oldValue == $newValue) {
                    $this->_systemTriggers->triggerFileStatusChanged(
                        $memberId,
                        $this->getCaseStatuses()->getCaseStatusesNames($oldValue),
                        $this->getCaseStatuses()->getCaseStatusesNames($newValue),
                        $this->_auth->getCurrentUserId(),
                        $this->_auth->hasIdentity() ? $this->_auth->getIdentity()->full_name : ''
                    );
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    public function onFileStatusChanged(EventInterface $event)
    {
        $arrPSRecords = [];

        try {
            $memberId           = $event->getParam('member_id');
            $arrNewFileStatuses = $event->getParam('new_statuses');

            // Insert new Fees Due based on this specific case file status
            $select = (new Select())
                ->from(array('s' => 'u_payment_schedule'))
                ->columns(array('member_id', 'company_ta_id', 'payment_schedule_id', 'amount', 'description', 'gst', 'gst_province_id', 'gst_tax_label'))
                ->join(array('ta' => 'members_ta'), new PredicateExpression('ta.member_id = s.member_id AND ta.order = 0'), ['primary_company_ta_id' => 'company_ta_id'], Select::JOIN_LEFT)
                ->where(
                    [
                        's.status'           => 0,
                        's.based_on_account' => array_keys($arrNewFileStatuses),
                        's.member_id'        => (int)$memberId
                    ]
                );

            $arrPSRecords = $this->_db2->fetchAll($select);

            foreach ($arrPSRecords as $key => $arrPSRecord) {
                // Use client's primary T/A if PS record isn't assigned to a specific T/A
                $arrPSRecords[$key]['company_ta_id'] = empty($arrPSRecord['company_ta_id']) ? $arrPSRecord['primary_company_ta_id'] : $arrPSRecord['company_ta_id'];
                unset($arrPSRecords[$key]['primary_company_ta_id']);

                // Set payment record's date to now
                $arrPSRecords[$key]['payment_date_of_event'] = date('Y-m-d H:i:s');
            }

            $this->getAccounting()->insertFinancialTransactions($arrPSRecords);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $event->setParam('ps_records', $arrPSRecords);
        return [__CLASS__ => $arrPSRecords];
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
     * @param EventInterface $event
     * @return array Grouped log
     */
    public function onFieldsBulkChanges(EventInterface $event)
    {
        $arrLogGroupedByClients = array();

        try {
            $companyId        = $event->getParam('company_id');
            $arrAllFieldsData = $event->getParam('changes');

            if (is_array($arrAllFieldsData) && count($arrAllFieldsData)) {
                // Log format for client's fields changes
                $strFieldChangedLogFormat = '%label changed from %from to %to';

                // Log format for dependents changes
                $strDependentLogFormat        = '%label for %relationship was changed from %from to %to';
                $strDependentRemovedLogFormat = '%relationship record was removed';
                $strDependentAddedLogFormat   = '%relationship record was added';

                // Log format for images/files changes
                $strFileChangedLogFormat = 'File for %label was added/updated';
                $strFileDeletedLogFormat = 'File for %label was removed';

                $arrDependentFields = $this->getFields()->getDependantFields();

                $arrClientIdsRegenerateCompanyAgentPayments = array();
                foreach ($arrAllFieldsData as $clientId => $arrChanges) {
                    /*
                     * There are such possible cases:
                     *
                     * 1. Deleted/cleared field's data
                     * 2. Added new field data
                     * 3. Changed field's data
                     */
                    $booIsApplicantField = $arrChanges['booIsApplicant'];

                    // These are cases #1 and #3 (cleared or changed field's data)
                    $arrChanges['arrOldData'] = $arrChanges['arrOldData'] ?? array();
                    $arrChanges['arrNewData'] = $arrChanges['arrNewData'] ?? array();
                    foreach ($arrChanges['arrOldData'] as $arrFieldOldData) {
                        $readableValueSetFrom = $arrFieldOldData['value'];
                        $readableValueSetTo   = null;

                        $booFoundFieldData = false;
                        foreach ($arrChanges['arrNewData'] as $arrFieldNewData) {
                            if ($arrFieldOldData['field_id'] == $arrFieldNewData['field_id']) {
                                // For applicant fields check rows
                                if ($booIsApplicantField && $arrFieldOldData['row'] != $arrFieldNewData['row']) {
                                    continue;
                                }

                                if ($arrFieldOldData['value'] != $arrFieldNewData['value']) {
                                    $readableValueSetTo = $arrFieldNewData['value'];
                                }

                                $booFoundFieldData = true;
                                break;
                            }
                        }

                        if (!$booFoundFieldData) {
                            $readableValueSetTo = '';
                        }

                        if (!is_null($readableValueSetTo) && $readableValueSetFrom != $readableValueSetTo) {
                            $arrFieldInfo = $booIsApplicantField ? $this->getApplicantFields()->getFieldInfo($arrFieldOldData['field_id'], $companyId) : $this->getFields()->getFieldInfoById($arrFieldOldData['field_id']);

                            if ($readableValueSetFrom == '') {
                                $readableValueSetFrom = '*blank*';
                            } else {
                                list($readableValueSetFrom,) = $this->getFields()->getFieldReadableValue($companyId, $this->_auth->getCurrentUserDivisionGroupId(), $arrFieldInfo, $readableValueSetFrom, $booIsApplicantField);
                            }

                            if ($readableValueSetTo == '') {
                                $readableValueSetTo = '*blank*';
                            } else {
                                list($readableValueSetTo,) = $this->getFields()->getFieldReadableValue($companyId, $this->_auth->getCurrentUserDivisionGroupId(), $arrFieldInfo, $readableValueSetTo, $booIsApplicantField);
                            }

                            // Prepare log
                            $arrParams = array(
                                'label' => $arrFieldInfo['label'],
                                'from'  => $readableValueSetFrom,
                                'to'    => $readableValueSetTo,
                            );

                            $arrLogGroupedByClients[$clientId][] = array(
                                'message'  => Settings::sprintfAssoc($strFieldChangedLogFormat, $arrParams),
                                'field_id' => $arrFieldOldData['field_id']
                            );
                        }
                    }

                    // This is case #2 - added new data
                    foreach ($arrChanges['arrNewData'] as $arrFieldNewData) {
                        $booFoundFieldData = false;
                        foreach ($arrChanges['arrOldData'] as $arrFieldOldData) {
                            if (isset($arrFieldOldData['row']) && isset($arrFieldNewData['row'])) {
                                if ($arrFieldOldData['field_id'] == $arrFieldNewData['field_id'] && $arrFieldOldData['row'] == $arrFieldNewData['row']) {
                                    $booFoundFieldData = true;
                                    break;
                                }
                            } elseif ($arrFieldOldData['field_id'] == $arrFieldNewData['field_id']) {
                                $booFoundFieldData = true;
                                break;
                            }
                        }

                        if (!$booFoundFieldData) {
                            $arrFieldInfo = $booIsApplicantField ? $this->getApplicantFields()->getFieldInfo($arrFieldNewData['field_id'], $companyId) : $this->getFields()->getFieldInfoById($arrFieldNewData['field_id']);
                            $readableValueSetFrom = '*blank*';

                            if ($arrFieldNewData['value'] == '') {
                                $readableValueSetTo = '*blank*';
                            } else {
                                list($readableValueSetTo,) = $this->getFields()->getFieldReadableValue($companyId, $this->_auth->getCurrentUserDivisionGroupId(), $arrFieldInfo, $arrFieldNewData['value'], $booIsApplicantField);
                            }

                            if ($readableValueSetFrom != $readableValueSetTo) {
                                // Prepare log
                                $arrParams = array(
                                    'label' => $arrFieldInfo['label'],
                                    'from' => $readableValueSetFrom,
                                    'to' => $readableValueSetTo,
                                );

                                $arrLogGroupedByClients[$clientId][] = array(
                                    'message' => Settings::sprintfAssoc($strFieldChangedLogFormat, $arrParams),
                                    'field_id' => $arrFieldNewData['field_id']
                                );
                            }
                        }
                    }

                    // Merge/check dependents changes
                    if (isset($arrChanges['arrOldDependants']) || isset($arrChanges['arrNewDependants'])) {
                        // Make sure that all records are correct
                        $arrChanges['arrOldDependants'] = $arrChanges['arrOldDependants'] ?? array();
                        $arrChanges['arrNewDependants'] = $arrChanges['arrNewDependants'] ?? array();

                        // Removed or changed dependent's info
                        foreach ($arrChanges['arrOldDependants'] as $oldDependentType => $arrOldDependents) {
                            $booDependentRowFound = false;
                            foreach ($arrChanges['arrNewDependants'] as $newDependentType => $arrNewDependents) {
                                if ($oldDependentType == $newDependentType) {
                                    foreach ($arrOldDependents as $key => $arrDependentInfo) {
                                        if (isset($arrNewDependents[$key])) {
                                            // Check values
                                            foreach ($arrDependentFields as $arrDependentFieldInfo) {
                                                $valueFrom = $arrOldDependents[$key][$arrDependentFieldInfo['field_unique_id']] ?? '';
                                                $valueTo   = $arrNewDependents[$key][$arrDependentFieldInfo['field_unique_id']] ?? '';
                                                if ($valueFrom != $valueTo) {
                                                    // Prepare log
                                                    $arrParams = array(
                                                        'relationship' => $this->getFields()->getDependentRelationshipLabelById($oldDependentType),
                                                        'label'        => $this->getFields()->getDependentFieldLabelById($arrDependentFieldInfo['field_unique_id']),
                                                        'from'         => $this->getFields()->getDependentFieldReadableValue($arrDependentFieldInfo, $valueFrom),
                                                        'to'           => $this->getFields()->getDependentFieldReadableValue($arrDependentFieldInfo, $valueTo),
                                                    );

                                                    $arrLogGroupedByClients[$clientId][] = array(
                                                        'message' => Settings::sprintfAssoc($strDependentLogFormat, $arrParams)
                                                    );

                                                    if ($arrDependentFieldInfo['field_unique_id'] == 'relationship' || $arrDependentFieldInfo['field_unique_id'] == 'DOB') {
                                                        $arrClientIdsRegenerateCompanyAgentPayments[] = $clientId;
                                                    }
                                                }
                                            }

                                            $booDependentRowFound = true;
                                        } else {
                                            $booDependentRowFound = false;
                                        }
                                    }
                                }
                            }

                            if (!$booDependentRowFound) {
                                // Prepare log
                                $arrParams = array(
                                    'relationship' => $this->getFields()->getDependentRelationshipLabelById($oldDependentType),
                                );

                                $arrLogGroupedByClients[$clientId][] = array(
                                    'message' => Settings::sprintfAssoc($strDependentRemovedLogFormat, $arrParams)
                                );

                                $arrClientIdsRegenerateCompanyAgentPayments[] = $clientId;
                            }
                        }

                        // Added new dependent's row
                        foreach ($arrChanges['arrNewDependants'] as $newDependentType => $arrNewDependents) {
                            $booDependentRowFound = false;
                            foreach ($arrChanges['arrOldDependants'] as $oldDependentType => $arrOldDependents) {
                                if ($oldDependentType == $newDependentType) {
                                    foreach ($arrNewDependents as $key => $arrDependentInfo) {
                                        if (isset($arrOldDependents[$key])) {
                                            $booDependentRowFound = true;
                                        } else {
                                            $booDependentRowFound = false;
                                            break;
                                        }
                                    }
                                }
                            }

                            if (!$booDependentRowFound) {
                                // Prepare log
                                $arrParams = array(
                                    'relationship' => $this->getFields()->getDependentRelationshipLabelById($newDependentType),
                                );

                                $arrLogGroupedByClients[$clientId][] = array(
                                    'message' => Settings::sprintfAssoc($strDependentAddedLogFormat, $arrParams)
                                );

                                $arrClientIdsRegenerateCompanyAgentPayments[] = $clientId;
                            }
                        }
                    }


                    // Merge/check files changes
                    if (isset($arrChanges['arrNewFiles'])) {
                        foreach ($arrChanges['arrNewFiles'] as $arrFileInfo) {
                            $arrFieldInfo = $booIsApplicantField ? $this->getApplicantFields()->getFieldInfo($arrFileInfo['field_id'], $companyId) : $this->getFields()->getFieldInfoById($arrFileInfo['field_id']);

                            // Prepare log
                            $arrParams = array(
                                'label' => $arrFieldInfo['label'],
                            );

                            $arrLogGroupedByClients[$clientId][] = array(
                                'message' => Settings::sprintfAssoc($strFileChangedLogFormat, $arrParams),
                                'field_id' => $arrFileInfo['field_id']
                            );
                        }
                    }

                    if (isset($arrChanges['arrDeletedFiles'])) {
                        foreach ($arrChanges['arrDeletedFiles'] as $arrFileInfo) {
                            $arrFieldInfo = $booIsApplicantField ? $this->getApplicantFields()->getFieldInfo($arrFileInfo['field_id'], $companyId) : $this->getFields()->getFieldInfoById($arrFileInfo['field_id']);

                            // Prepare log
                            $arrParams = array(
                                'label' => $arrFieldInfo['label'],
                            );

                            $arrLogGroupedByClients[$clientId][] = array(
                                'message' => Settings::sprintfAssoc($strFileDeletedLogFormat, $arrParams),
                                'field_id' => $arrFileInfo['field_id']
                            );
                        }
                    }


                    // Static messages, generated before
                    if (isset($arrChanges['arrStaticMessages'])) {
                        foreach ($arrChanges['arrStaticMessages'] as $strMessage) {
                            $arrLogGroupedByClients[$clientId][] = array(
                                'message' => $strMessage
                            );
                        }
                    }
                }

                if (!empty($arrClientIdsRegenerateCompanyAgentPayments)) {
                    $arrClientIdsRegenerateCompanyAgentPayments = array_unique($arrClientIdsRegenerateCompanyAgentPayments);

                    foreach ($arrClientIdsRegenerateCompanyAgentPayments as $clientId) {
                        if ($this->isMemberCaseById($clientId)) {
                            $caseId = $clientId;
                        } else {
                            $arrCases = $this->getAssignedCases($clientId);
                            $caseId = count($arrCases) ? $arrCases[0] : 0;
                        }

                        $this->getAccounting()->regenerateCompanyAgentPayments($companyId, $caseId);
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $event->setParam('grouped_log', $arrLogGroupedByClients);
        return [__CLASS__ => $arrLogGroupedByClients];
    }

    public function onCopyCompanyDefaultSettings(EventInterface $e)
    {
        $fromCompanyId           = $e->getParam('fromId');
        $toCompanyId             = $e->getParam('toId');
        $arrMappingRoles         = $e->getParam('mappingRoles');
        $arrMappingCategories    = $e->getParam('mappingDefaultCategories');
        $arrMappingCaseStatuses  = $e->getParam('mappingDefaultCaseStatuses');
        $arrMappingCaseTemplates = $e->getParam('mappingCaseTemplates');

        $result = [];

        // Create default Groups and Fields
        $result['caseGroupsAndFields'] = $arrMappingCaseGroupsAndFields = $this->getFields()->createDefaultGroupsAndFields($fromCompanyId, $toCompanyId, $arrMappingRoles, $arrMappingCaseTemplates);

        // Generate default access rights for fields
        $this->getFields()->createDefaultFieldsAccessForCompany($toCompanyId);

        // Generate default fields conditions
        $this->getConditionalFields()->createDefaultConditionalFields(
            $arrMappingCaseTemplates,
            $arrMappingCaseGroupsAndFields['mappingGroups'],
            $arrMappingCaseGroupsAndFields['mappingFields'],
            $arrMappingCategories,
            $arrMappingCaseStatuses,
            $arrMappingCaseGroupsAndFields['mappingDefaults']
        );

        // Automatically create Applicant blocks, groups and fields
        $result['applicantGroupsAndFields'] = $arrMappingClientGroupsAndFields = $this->getApplicantFields()->createDefaultCompanyFieldsAndGroups($fromCompanyId, $toCompanyId, $arrMappingRoles);

        // Create default Searches
        $this->getSearch()->createDefaultSearches($fromCompanyId, $toCompanyId, $arrMappingCaseGroupsAndFields, $arrMappingClientGroupsAndFields, $arrMappingCategories, $arrMappingCaseStatuses);

        return [__CLASS__ => $result];
    }

    public function onDeleteCompany(EventInterface $e)
    {
        $companyId = $e->getParam('id');
        if (!is_array($companyId)) {
            $companyId = array($companyId);
        }

        $this->getFields()->deleteAllCompanyGroups($companyId);

        $select = (new Select())
            ->from('company_ta')
            ->columns(['company_ta_id'])
            ->where(['company_id' => $companyId]);

        $result = $this->_db2->fetchAll($select);

        $arrCompanyTAIds = array_column($result, 'company_ta_id');
        if (count($arrCompanyTAIds) > 0) {
            $this->getAccounting()->getTrustAccount()->deleteCompanyTAReconciliation($arrCompanyTAIds);
        }
    }

    /**
     * Generate client name from the provided info
     * @param array $arrClientInfo
     * @param false $booFormatFileNumber
     * @return array
     *
     * @examples:
     * John, Watson (12233)
     * (12233)
     * John, Watson 12233
     * 12233
     */
    public function generateClientName($arrClientInfo, $booFormatFileNumber = false)
    {
        $arrResult = $arrClientInfo;

        // Don't show comma if first/last name is empty
        $arrName          = array();
        $arrNameShortened = array();

        $arrClientInfo['lName'] = isset($arrClientInfo['lName']) ? trim($arrClientInfo['lName']) : '';
        if ($arrClientInfo['lName'] != '') {
            $arrName[]          = $arrClientInfo['lName'];
            $arrNameShortened[] = mb_strtoupper(mb_substr($arrClientInfo['lName'], 0, 3, 'UTF-8'));
        }

        $arrClientInfo['fName'] = isset($arrClientInfo['fName']) ? trim($arrClientInfo['fName']) : '';
        if ($arrClientInfo['fName'] != '') {
            $arrName[]          = $arrClientInfo['fName'];
            $arrNameShortened[] = mb_strtoupper(mb_substr($arrClientInfo['fName'], 0, 3, 'UTF-8'));
        }

        $arrResult['full_name']           = implode(', ', $arrName);
        $arrResult['full_name_shortened'] = implode('.', $arrNameShortened);

        $fileNumber = '';
        if (isset($arrClientInfo['fileNumber']) && $arrClientInfo['fileNumber'] !== '' && $this->getFields()->hasCurrentMemberAccessToField('file_number')) {
            if ($booFormatFileNumber) {
                $fileNumber = '(' . $arrClientInfo['fileNumber'] . ')';
            } else {
                $fileNumber = $arrClientInfo['fileNumber'];
            }
        }

        $arrResult['full_name_with_file_num']           = trim($arrResult['full_name'] . ' ' . $fileNumber);
        $arrResult['full_name_with_file_num_shortened'] = trim($arrResult['full_name_shortened'] . ' ' . $fileNumber);

        return $arrResult;
    }

    /**
     * Generate case name from the provided case id
     * Also, return parent client id for the case
     *
     * @param int $caseId
     * @param bool $booShortClientName
     * @return array
     */
    public function getCaseAndClientName($caseId, $booShortClientName = false)
    {
        $clientId = $caseId;
        $caseName = '';

        $arrCaseInfo = $this->getClientShortInfo($caseId);
        if (!empty($arrCaseInfo)) {
            $caseName = $booShortClientName ? $arrCaseInfo['full_name_with_file_num_shortened'] : $arrCaseInfo['full_name_with_file_num'];
            $caseName = trim($caseName);

            // Generate case name and use parent's name
            $arrParents = $this->getParentsForAssignedApplicants([$caseId]);
            if (is_array($arrParents) && array_key_exists($caseId, $arrParents)) {
                $arrParentInfo = $arrParents[$caseId];
                $clientId      = $arrParentInfo['parent_member_id'];
                $arrParentInfo = $this->generateClientName($arrParentInfo);

                $fullName = $booShortClientName ? $arrParentInfo['full_name_shortened'] : $arrParentInfo['full_name'];
                if (strlen($caseName)) {
                    $caseName = $fullName . ' (' . $caseName . ')';
                } else {
                    $caseName = $fullName;
                }
            }
        }

        return [$caseName, $clientId];
    }

    /**
     * Load cases' static info (from the members and clients tables)
     *
     * @param array $arrCasesIds
     * @param bool $booLoadCasesParents
     * @return array
     */
    public function getCasesStaticInfo($arrCasesIds, $booLoadCasesParents = true)
    {
        $arrResultGrouped = array();

        if (count($arrCasesIds)) {
            $select = (new Select())
                ->from(array('m' => 'members'))
                ->columns(array(Select::SQL_STAR, 'regTime' => new Expression('DATE(FROM_UNIXTIME(regTime))')))
                ->join(array('c' => 'clients'), 'c.member_id = m.member_id', array('case_internal_id' => 'member_id', 'client_id', 'added_by_member_id', 'fileNumber', 'agent_id', 'forms_locked', 'client_type_id'), Select::JOIN_LEFT)
                ->where(['m.member_id' => $arrCasesIds]);

            $arrResult = $this->_db2->fetchAll($select);

            $arrParents = $booLoadCasesParents ? $this->getParentsForAssignedApplicants($arrCasesIds) : [];

            $arrResultGrouped = [];
            foreach ($arrResult as $clientInfo) {
                if (isset($arrParents[$clientInfo['member_id']]['parent_member_id'])) {
                    $clientInfo[$clientInfo['member_id']]['applicant_internal_id'] = $arrParents[$clientInfo['member_id']]['parent_member_id'];
                }

                $arrResultGrouped[$clientInfo['member_id']] = $clientInfo;
            }
        }

        return $arrResultGrouped;
    }


    /**
     * Load clients' list from DB
     *
     * @param bool $companyTAId
     * @param array $arrShowOnlyClients
     * @param null|array $arrDivisions
     * @param bool $booSort
     * @param bool $booUseDistinct
     * @param bool $booGenerateName
     * @param null $userId
     * @param bool $booFormatFileNumber
     * @param string $memberTypeName
     * @return array
     */
    public function getClientsList($companyTAId = false, $arrShowOnlyClients = array(), $arrDivisions = null, $booSort = true, $booUseDistinct = true, $booGenerateName = true, $userId = null, $booFormatFileNumber = false, $memberTypeName = 'all')
    {
        $arrMemberTypes = Members::getMemberType('case');

        $select = (new Select())
            ->from(array('m' => 'members'))
            ->join(array('c' => 'clients'), 'c.member_id = m.member_id', array('case_internal_id' => 'member_id', 'client_id', 'added_by_member_id', 'fileNumber', 'agent_id', 'forms_locked', 'client_type_id'), Select::JOIN_LEFT)
            ->join(array('md' => 'members_divisions'), 'md.member_id = m.member_id', [], Select::JOIN_LEFT)
            ->join(array('mr' => 'members_relations'), 'mr.child_member_id = m.member_id', [], Select::JOIN_LEFT)
            ->where(
                [
                    (new Where())
                        ->isNotNull('c.member_id')
                        ->isNotNull('mr.child_member_id')
                ]
            )
            ->where(
                [
                    'md.type'    => 'access_to',
                    'm.userType' => $arrMemberTypes
                ]
            );

        if ($booUseDistinct) {
            $select->quantifier(Select::QUANTIFIER_DISTINCT);
        }

        if ($booSort) {
            $select->order(array('m.lName ASC', 'm.fName ASC'));
        }

        list($structQuery,) = $this->getMemberStructureQuery($userId);
        if (!empty($structQuery)) {
            $select->where([$structQuery]);
        }


        if ($companyTAId) {
            // Need a separate request to speed up
            $select2 = (new Select())
                ->from(array('mta' => 'members_ta'))
                ->columns(['member_id'])
                ->where(['mta.company_ta_id' => $companyTAId]);

            $arrMembers = $this->_db2->fetchCol($select2);

            $select->where(
                [
                    'm.member_id' => empty($arrMembers) ? array(0) : $arrMembers
                ]
            );
        }

        if (!empty($arrShowOnlyClients)) {
            $select->where(
                [
                    'm.member_id' => $arrShowOnlyClients
                ]
            );
        }

        if (!is_null($arrDivisions)) {
            if (empty($arrDivisions)) {
                $arrDivisions = array(0);
            }

            $select->where(
                [
                    'md.division_id' => $arrDivisions
                ]
            );
        }

        if (in_array($memberTypeName, ['individual', 'employer'])) {
            $select->join(array('m2' => 'members'), 'mr.parent_member_id = m2.member_id', []);

            $select->where(
                [
                    'm2.userType' => $this->getMemberTypeIdByName($memberTypeName)
                ]
            );
        }

        $result = $this->_db2->fetchAssoc($select);

        $arrChildMemberIds = array_keys($result);

        $arrParents = $this->getParentsForAssignedApplicants($arrChildMemberIds);

        $arrClients = array();
        foreach ($result as $key => $clientInfo) {
            if (isset($arrParents[$key]['parent_member_id'])) {
                $result[$key]['applicant_internal_id'] = $arrParents[$key]['parent_member_id'];

                // Use a first/last name of the parent to generate a correct client/case name
                if ($booFormatFileNumber) {
                    $result[$key]['fName'] = $clientInfo['fName'] = $arrParents[$key]['fName'];
                    $result[$key]['lName'] = $clientInfo['lName'] = $arrParents[$key]['lName'];
                }
            }

            if ($booGenerateName && $clientInfo['member_id']) {
                $arrClients[$clientInfo['member_id']] = $this->generateClientName($clientInfo, $booFormatFileNumber);
            }
        }

        if (!$booGenerateName) {
            $arrClients = $result;
        } elseif (count($arrClients) && $booFormatFileNumber) {
            // Sort array by name
            $arrNames = array();
            foreach ($arrClients as $key => $row) {
                $arrNames[$key] = strtolower($row['full_name_with_file_num'] ?? '');
            }
            array_multisort($arrNames, SORT_ASC, $arrClients);
        }

        return $arrClients;
    }

    /**
     * Load All clients list for specific company (don't check access rights, e.g. offices)
     *
     * @param int $companyId
     * @param int $start
     * @param int|null $limit
     * @return array
     */
    public function getAllClientsList($companyId, $start = 0, $limit = null)
    {
        $select = (new Select())
            ->from(array('m' => 'members'))
            ->join(array('c' => 'clients'), 'c.member_id = m.member_id', array('client_id', 'added_by_member_id', 'fileNumber', 'agent_id', 'forms_locked'), Select::JOIN_LEFT)
            ->where(
                [
                    'm.userType'   => self::getMemberType('case'),
                    'm.company_id' => (int)$companyId
                ]
            )
            ->order(array('m.lName ASC', 'm.fName ASC'));

        if (!empty($limit)) {
            $select
                ->limit($limit)
                ->offset($start);
        }

        $result = $this->_db2->fetchAll($select);

        $arrClients = array();
        foreach ($result as $clientInfo) {
            if ($clientInfo['member_id']) {
                $arrClients[] = $this->generateClientName($clientInfo);
            }
        }

        return $arrClients;
    }

    /**
     * Filter clients' list by their status
     * (only 'Active' clients will be returned)
     * @param array $arrClientsIds
     * @param bool $booIdsOnly true to load ids only
     * @param int $companyId company id to load status field id from
     * @param bool $booAll true to load also not active clients
     * @return array with filtered clients' info
     */
    public function getActiveClientsList($arrClientsIds = array(), $booIdsOnly = false, $companyId = 0, $booAll = false)
    {
        if ($booIdsOnly) {
            $select = (new Select())
                ->from(array('m' => 'members'))
                ->columns(array('member_id'));
        } else {
            $select = (new Select())
                ->from(array('m' => 'members'))
                ->columns(array('member_id', 'fName', 'lName', 'emailAddress'))
                ->join(array('c' => 'clients'), 'c.member_id = m.member_id', 'fileNumber', Select::JOIN_LEFT)
                ->order(array('m.lName', 'm.fName'));
        }

        $select->where(['m.userType' => self::getMemberType('case')]);

        if (!empty($companyId)) {
            $select->where->equalTo('m.company_id', (int)$companyId);
        }

        if (is_array($arrClientsIds) && count($arrClientsIds)) {
            $select->where(['m.member_id' => $arrClientsIds]);
        }

        if (!$booAll) {
            $clientStatusFieldId = $this->getFields()->getClientStatusFieldId($companyId);
            if (!empty($clientStatusFieldId)) {
                $select->join(array('fd' => 'client_form_data'), 'fd.member_id = m.member_id', [], Select::JOIN_LEFT)
                    ->where
                    ->equalTo('fd.field_id', $clientStatusFieldId)
                    ->equalTo('fd.value', 'Active');
            }
        }

        return $booIdsOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAssoc($select);
    }

    /**
     * Load active only cases for specific company
     * If cases ids list will be provided - result will be filtered based on them
     *
     * @param int $companyId
     * @param array $arrMemberIds
     * @return array
     */
    public function getCompanyActiveClientsList($companyId, $arrMemberIds = null)
    {
        $clientStatusFieldId = $this->getFields()->getClientStatusFieldId($companyId);
        if (!empty($clientStatusFieldId)) {
            // The field is assigned to one company only + it is related to the Immigration Program only,
            // So we can simply call client_form_data table only (to speed up)
            $select = (new Select())
                ->from(array('fd' => 'client_form_data'))
                ->columns(array('member_id'))
                ->where(
                    [
                        'fd.field_id' => $clientStatusFieldId,
                        'fd.value'    => 'Active'
                    ]
                );
        } else {
            // Select all cases for this company (cause all of them are active if this field is absent)
            $select = (new Select())
                ->from(array('m' => 'members'))
                ->columns(array('member_id'))
                ->where(
                    [
                        'm.userType'   => self::getMemberType('case'),
                        'm.company_id' => (int)$companyId
                    ]
                );
        }

        $arrFoundCaseIds = $this->_db2->fetchCol($select);

        if (is_null($arrMemberIds)) {
            $arrResult = $arrFoundCaseIds;
        } else {
            $arrResult = array_intersect($arrFoundCaseIds, $arrMemberIds);
        }

        return $arrResult;
    }

    /**
     * Load inactive cases list from specified members list
     *
     * @param array $arrMemberIds
     * @param int $companyId
     * @return array
     */
    public function getInactiveClientsFromList($arrMemberIds = array(), $companyId = 0)
    {
        $arrInactiveClientIds = array();

        if (empty($companyId)) {
            $clientStatusFieldId = $this->getFields()->getAllCompaniesClientStatusFieldsIds();
        } else {
            $clientStatusFieldId = $this->getFields()->getClientStatusFieldId($companyId);
        }

        if (!empty($clientStatusFieldId)) {
            $select = (new Select())
                ->from(array('d' => 'client_form_data'))
                ->columns(['member_id'])
                ->join(array('f' => 'client_form_fields'), 'f.field_id = d.field_id', [], Select::JOIN_LEFT)
                ->where(['f.field_id' => $clientStatusFieldId])
                ->where([(new Where())->notEqualTo('d.value', 'Active')]);

            if (is_array($arrMemberIds) && count($arrMemberIds)) {
                $select->where(['d.member_id' => $arrMemberIds]);
            }

            $arrInactiveClientIds = $this->_db2->fetchCol($select);
        }

        return $arrInactiveClientIds;
    }

    /**
     * Load only required client's info with generate name fields
     *
     * @param int $memberId
     * @return array
     */
    public function getClientShortInfo($memberId)
    {
        $arrMemberInfo = array();
        if (is_numeric($memberId) && !empty($memberId)) {
            $select = (new Select())
                ->from(array('m' => 'members'))
                ->columns(array('fName', 'lName', 'userType', 'company_id', 'emailAddress'))
                ->join(array('c' => 'clients'), 'c.member_id = m.member_id', Select::SQL_STAR,Select::JOIN_LEFT)
                ->where(['m.member_id' => (int)$memberId]);

            $arrMemberInfo = $this->_db2->fetchRow($select);
            $arrMemberInfo = $this->generateClientName($arrMemberInfo);
        }

        return $arrMemberInfo;
    }


    /**
     * Load client's info
     *
     * @param $arrMemberIds
     * @param bool $booDecodePassword
     * @param int $memberType
     * @return array|false - false if something is wrong
     */
    public function getClientsInfo($arrMemberIds, $booDecodePassword = false, $memberType = 0)
    {
        if (empty($arrMemberIds)) {
            return false;
        }

        $memberType = empty($memberType) ? Members::getMemberType('client') : $memberType;

        $select = (new Select())
            ->from(array('m' => 'members'))
            ->quantifier(Select::QUANTIFIER_DISTINCT)
            ->join(
                array('c' => 'clients'),
                'c.member_id = m.member_id',
                array(
                    'case_internal_id' => 'member_id',
                    'client_id',
                    'added_by_member_id',
                    'fileNumber',
                    'agent_id',
                    'forms_locked',
                    'modified_by',
                    'modified_on',
                    'client_type_id',
                    'applicant_type_id',
                    'case_number_of_parent_client',
                    'case_number_of_parent_employer',
                    'case_number_in_company',
                    'case_number_with_same_case_type_in_company'
                ),
                Select::JOIN_LEFT
            )
            ->join(array('m2' => 'members'), 'c.agent_id = m2.member_id', array('agent_fName' => 'fName', 'agent_lName' => 'lName'), Select::JOIN_LEFT)
            ->join(array('md' => 'members_divisions'), new PredicateExpression("md.member_id = m.member_id AND md.type = 'access_to'"), array('division' => 'division_id'), Select::JOIN_LEFT)
            ->where(
                [
                    'm.member_id' => $arrMemberIds,
                    'm.userType'  => $memberType
                ]
            );

        $arrClientsInfo = $this->_db2->fetchAll($select);

        $arrParents = $this->getParentsForAssignedApplicants($arrMemberIds);

        foreach ($arrClientsInfo as $key => $clientInfo) {
            if (isset($arrParents[$clientInfo['member_id']]['parent_member_id'])) {
                $arrClientsInfo[$key]['applicant_internal_id'] = $arrParents[$clientInfo['member_id']]['parent_member_id'];
            }

            if ($booDecodePassword) {
                if (empty($clientInfo['username'])) {
                    $arrClientsInfo[$key]['password'] = '';
                } else {
                    $arrClientsInfo[$key]['password'] = $this->_encryption->decodeHashedPassword($clientInfo['password']);
                }
            }

            if (!empty($clientInfo)) {
                $arrClientsInfo[$key] = $this->generateClientName($arrClientsInfo[$key]);
            }
        }

        return $arrClientsInfo;
    }

    /**
     * Get all clients by case template id (client type)
     *
     * @param int $caseTemplateId
     * @param int $companyId
     * @return array of clients
     */
    public function getClientsByCaseTemplateId($caseTemplateId, $companyId = null)
    {
        $select = (new Select())
            ->from(array('c' => 'clients'))
            ->where(['c.client_type_id' => (int)$caseTemplateId]);

        if ($companyId) {
            $select->join(array('m' => 'members'), 'c.member_id = m.member_id', [], Select::JOIN_LEFT);
            $select->where->equalTo('m.company_id', (int)$companyId);
        }

        return $this->_db2->fetchAll($select);
    }

    /**
     * Get all clients by applicant type id (applicant type)
     *
     * @param int $applicantTypeId
     * @return array of clients
     */
    public function getClientsByApplicantTypeId($applicantTypeId)
    {
        $select = (new Select())
            ->from('clients')
            ->where(['applicant_type_id' => (int)$applicantTypeId]);

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load clients list which have email address
     * @return array
     */
    public function getClientsWithEmails()
    {
        $arrResult = array();

        $companyId = $this->_auth->getCurrentUserCompanyId();
        if (empty($companyId)) {
            return $arrResult;
        }

        $arrClients = $this->getClientsList();
        $arrClients = $this->getCasesListWithParents($arrClients);

        if (is_array($arrClients) && !empty($arrClients)) {
            foreach ($arrClients as $arrClientInfo) {
                $arrResult[$arrClientInfo['clientId']] = array(
                    'member_id' => $arrClientInfo['clientId'],
                    'full_name' => $arrClientInfo['clientName'],
                    'full_name_with_num' => $arrClientInfo['clientFullName'],
                    'emails' => empty($arrClientInfo['emailAddresses']) ? array() : array($arrClientInfo['emailAddresses'])
                );
            }

            // Get clients' emails
            $select = (new Select())
                ->from(array('f' => 'client_form_fields'))
                ->columns(['field_id'])
                ->where(
                    [
                        'f.disabled'   => 'N',
                        'f.blocked'    => 'N',
                        'f.company_id' => $companyId,
                        'f.type'       => $this->getFieldTypes()->getFieldTypeId('email')
                    ]
                );

            $arrEmailFieldIds = $this->_db2->fetchCol($select);

            if (is_array($arrEmailFieldIds) && count($arrEmailFieldIds)) {
                $arrEmails = $this->getClientSavedData(array_keys($arrResult), $arrEmailFieldIds);

                foreach ($arrEmails as $arrEmailInfo) {
                    $arrResult[$arrEmailInfo['member_id']]['emails'][] = $arrEmailInfo['value'];
                }
            }
        }

        return $arrResult;
    }

    /**
     * Load clients' saved data
     *
     * @param array $arrMemberIds
     * @param array $arrFieldIds
     * @param bool $booDecode
     * @return array
     */
    public function getClientSavedData($arrMemberIds = array(), $arrFieldIds = array(), $booDecode = true)
    {
        $select = (new Select())
            ->from(array('d' => 'client_form_data'))
            ->join(array('f' => 'client_form_fields'), 'd.field_id = f.field_id', array('encrypted', 'company_field_id', 'type'), Select::JOIN_LEFT);

        if (is_array($arrMemberIds) && count($arrMemberIds)) {
            $select->where(['d.member_id' => $arrMemberIds]);
        }

        if (is_array($arrFieldIds) && count($arrFieldIds)) {
            $select->where(['d.field_id' => $arrFieldIds]);
        }

        $arrData = $this->_db2->fetchAll($select);

        if ($booDecode) {
            foreach ($arrData as $key => $arrDataRow) {
                $arrData[$key]['value'] = $arrDataRow['encrypted'] == 'Y' ? $this->_encryption->decode($arrDataRow['value']) : $arrDataRow['value'];
            }
        }

        return $arrData;
    }

    /**
     * Load client id by field and value (for specific company)
     *
     * @param int $companyId
     * @param int $fieldId
     * @param string $fieldVal
     * @return array
     */
    public function getClientIdBySavedField($companyId, $fieldId, $fieldVal)
    {
        $select = (new Select())
            ->from(array('d' => 'client_form_data'))
            ->columns(['member_id'])
            ->join(array('f' => 'client_form_fields'), 'd.field_id = f.field_id', [], Select::JOIN_LEFT)
            ->where(
                [
                    'f.company_id' => (int)$companyId,
                    'd.field_id'   => (int)$fieldId,
                    'd.value'      => $fieldVal
                ]
            );

        return $this->_db2->fetchCol($select);
    }

    /**
     * Get the list of cases which have linked employer cases for a specific company
     *
     * @param int $companyId
     * @return array
     */
    public function getCasesThatHaveLinkedEmployerCases($companyId)
    {
        $select = (new Select())
            ->from(array('c' => 'clients'))
            ->columns(['member_id'])
            ->join(['m' => 'members'], 'c.member_id = m.member_id', [], Select::JOIN_LEFT)
            ->where(
                [
                    'm.company_id' => (int)$companyId,
                    (new Where())->isNotNull('c.employer_sponsorship_case_id')
                ]
            );

        return $this->_db2->fetchCol($select);
    }

    /**
     * Get the list of cases which have linked employer cases for a specific company
     *
     * @param array $arrMemberIds
     * @return array
     */
    public function getCasesLinkedEmployerCases($arrMemberIds)
    {
        $arrLinkedEmployerCases = [];

        if (!empty($arrMemberIds)) {
            $select = (new Select())
                ->from(array('c' => 'clients'))
                ->columns(['member_id', 'employer_sponsorship_case_id'])
                ->join(['c2' => 'clients'], 'c.employer_sponsorship_case_id = c2.member_id', ['linked_case_file_number' => 'fileNumber'], Select::JOIN_LEFT)
                ->where(
                    [
                        'c.member_id' => $arrMemberIds,
                        (new Where())->isNotNull('c.employer_sponsorship_case_id')
                    ]
                );

            $arrLinks = $this->_db2->fetchAll($select);

            foreach ($arrLinks as $arrSavedLinkInfo) {
                $arrLinkedEmployerCases[$arrSavedLinkInfo['member_id']] = [
                    'linkedCaseId' => $arrSavedLinkInfo['employer_sponsorship_case_id'],
                    'fileNumber'   => $arrSavedLinkInfo['linked_case_file_number'],
                ];
            }
        }

        return $arrLinkedEmployerCases;
    }

    /**
     * Get linked id of the "Employer Case" for a specific case
     *
     * @param int $caseId
     * @return int
     */
    public function getCaseLinkedEmployerCaseId($caseId)
    {
        $select = (new Select())
            ->from(array('c' => 'clients'))
            ->columns(['employer_sponsorship_case_id'])
            ->where(
                [
                    'c.member_id' => (int)$caseId,
                ]
            );

        $linkedEmployerCaseId = $this->_db2->fetchOne($select);

        return empty($linkedEmployerCaseId) ? 0 : (int)$linkedEmployerCaseId;
    }

    /**
     * Link/unlink 2 cases
     *
     * @param int $masterCaseId
     * @param int|null $linkedCaseId
     * @return bool true on success
     */
    public function linkUnlinkCases($masterCaseId, $linkedCaseId)
    {
        try {
            $this->_db2->update(
                'clients',
                [
                    'employer_sponsorship_case_id' => empty($linkedCaseId) ? null : $linkedCaseId,
                ],
                ['member_id' => $masterCaseId]
            );

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Check and link 2 cases
     *
     * @param bool $booSkipConfirmation true to skip the "confirmation" message
     * @param int $caseIdLinkFrom
     * @param int $caseIdLinkTo
     * @param bool $booUseDBTransaction
     * @return array
     */
    public function linkCaseToCase($booSkipConfirmation, $caseIdLinkFrom, $caseIdLinkTo, $booUseDBTransaction = true)
    {
        $strMessage            = '';
        $messageType           = 'error';
        $booTransactionStarted = false;
        $caseFromEmployerId    = 0;

        try {
            $oCompanyDivisions = $this->_company->getCompanyDivisions();
            if (!$this->hasCurrentMemberAccessToMember($caseIdLinkFrom) || !$oCompanyDivisions->canCurrentMemberEditClient($caseIdLinkFrom)) {
                $strMessage = $this->_tr->translate('Insufficient access rights to the case.');
            }

            if (empty($strMessage) && (!$this->hasCurrentMemberAccessToMember($caseIdLinkTo) || !$oCompanyDivisions->canCurrentMemberEditClient($caseIdLinkTo))) {
                $strMessage = $this->_tr->translate('Insufficient access rights to the case.');
            }

            if (empty($strMessage)) {
                $arrSavedData = $this->getCasesLinkedEmployerCases(array($caseIdLinkTo));
                if (!empty($arrSavedData)) {
                    $strMessage = $this->_tr->translate('This case is already assigned.');
                }
            }

            $booAssignCaseToEmployer = false;
            if (empty($strMessage)) {
                // Check if case is already assigned to the employer
                $arrParents = $this->getParentsForAssignedApplicant($caseIdLinkFrom, $this->getMemberTypeIdByName('employer'));
                if (!empty($arrParents)) {
                    $caseFromEmployerId = $arrParents[0];
                }

                if (empty($caseFromEmployerId)) {
                    $strMessage = $this->_tr->translate('Current case is not assigned to the Employer.');
                } else {
                    $arrParents = $this->getParentsForAssignedApplicant($caseIdLinkTo, $this->getMemberTypeIdByName('employer'));
                    if (empty($arrParents)) {
                        // Not assinged -> ask if not asked yet
                        if (!$booSkipConfirmation) {
                            $messageType = 'confirmation';
                            $strMessage  = $this->_tr->translate('Are you sure you want to link this case to this employer?');
                        }

                        $booAssignCaseToEmployer = true;
                    } elseif ($arrParents[0] != $caseFromEmployerId) {
                        $strMessage = $this->_tr->translate('Cases are already assigned to different Employers.');
                    }
                }
            }

            if (empty($strMessage)) {
                if ($booUseDBTransaction) {
                    $this->_db2->getDriver()->getConnection()->beginTransaction();
                    $booTransactionStarted = true;
                }

                if (!$this->linkUnlinkCases($caseIdLinkTo, $caseIdLinkFrom)) {
                    $strMessage = $this->_tr->translate('Employer case link was not set.');
                }

                if ($booAssignCaseToEmployer) {
                    $arrAssignData = array(
                        'applicant_id' => $caseFromEmployerId,
                        'case_id'      => $caseIdLinkTo,
                    );

                    if (!$this->assignCaseToApplicant($arrAssignData)) {
                        $strMessage = $this->_tr->translate('Internal error.');
                    }

                    if (empty($strMessage)) {
                        $this->calculateAndUpdateCaseNumberForEmployer($caseFromEmployerId, $caseIdLinkTo);
                    }
                }

                if (empty($strMessage) && $booUseDBTransaction) {
                    $this->_db2->getDriver()->getConnection()->commit();
                    $booTransactionStarted = false;
                }
            }
        } catch (Exception $e) {
            $messageType = 'error';
            $strMessage  = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strMessage) && $messageType == 'error' && $booTransactionStarted && $booUseDBTransaction) {
            $this->_db2->getDriver()->getConnection()->rollback();
        }

        return [$messageType, $strMessage, $caseFromEmployerId];
    }

    /**
     * Load client info by provided id
     *
     * @param $memberId
     * @param bool $booDecodePassword
     * @return array|false
     */
    public function getClientInfo($memberId, $booDecodePassword = false)
    {
        $clientInfo = empty($memberId) ? false : $this->getClientsInfo(array($memberId), $booDecodePassword);

        return $clientInfo ? $clientInfo[0] : false;
    }


    /**
     * Load client + case name
     *
     * @param $memberId
     * @return string
     */
    public function generateClientAndCaseName($memberId)
    {
        $arrClientInfo = $this->getClientInfo($memberId);
        $arrParentInfo = $this->getParentsForAssignedApplicants(array($memberId));
        if (is_array($arrParentInfo) && array_key_exists($memberId, $arrParentInfo)) {
            $caseAndClientName = $this->generateApplicantName($arrParentInfo[$memberId]) . ' (' . $arrClientInfo['full_name_with_file_num'] . ')';
        } else {
            $caseAndClientName = $arrClientInfo['full_name_with_file_num'];
        }

        return $caseAndClientName;
    }

    /**
     * Check if specific user can access a client
     *
     * @param $clientMemberId
     * @param int $memberId
     * @return bool true on success
     */
    public function isAlowedClient($clientMemberId, $memberId = null)
    {
        // Don't allow access by default
        $booCanAccess = false;

        // If client id is correct
        if (is_numeric($clientMemberId) && !empty($clientMemberId) && ($memberId || $this->_auth->hasIdentity())) {
            if ($clientMemberId == $memberId || $this->_auth->isCurrentUserSuperadmin()) {
                // Superadmin can access it
                // Or client can access himself o_O
                $booCanAccess = true;
            } else {
                $select = (new Select())
                    ->quantifier(Select::QUANTIFIER_DISTINCT)
                    ->from(['m' => 'members'])
                    ->columns(['member_id'])
                    ->where(
                        [
                            'm.member_id' => $clientMemberId
                        ]
                    );

                list($oWhere, $booUseDivisionsTable) = $this->getMemberStructureQuery($memberId);
                if ($booUseDivisionsTable) {
                    $select->join(array('md' => 'members_divisions'), 'md.member_id = m.member_id', [], Select::JOIN_LEFT);
                }

                if (!empty($oWhere)) {
                    $select->where([$oWhere]);
                }

                $result = $this->_db2->fetchOne($select);

                if (!empty($result)) {
                    $booCanAccess = true;
                }
            }
        }

        return $booCanAccess;
    }

    /**
     * Check if specific client is locked
     *
     * @param $memberId
     * @return bool true if client is locked
     */
    public function isLockedClient($memberId)
    {
        $booLockedClient = true;

        if (is_numeric($memberId) && !empty($memberId)) {
            $select = (new Select())
                ->from('clients')
                ->columns(['forms_locked'])
                ->where(['member_id' => (int)$memberId]);

            $booLocked = $this->_db2->fetchOne($select);
            if (empty($booLocked)) {
                $booLockedClient = false;
            }
        }

        return $booLockedClient;
    }


    /**
     * Load hardcoded array of family member info
     *
     * @param bool $booAsKeyVal
     * @return array
     */
    public function getFamilyMembers($booAsKeyVal = true)
    {
        $arrClientFamilyMembers = array();

        $row = 0;
        $arrClientFamilyMembers[] = array(
            'id' => 'main_applicant',
            'value' => 'Main Applicant',
            'order' => $row++
        );

        $arrClientFamilyMembers[] = array(
            'id' => 'spouse',
            'value' => 'Spouse',
            'order' => $row++
        );


        // Load relationship types (which can be used in case's profile)
        $arrSkipTypes = array('spouse');
        $arrDependentFields = $this->getFields()->getDependantFields();
        foreach ($arrDependentFields as $arrDependentFieldInfo) {
            if ($arrDependentFieldInfo['field_id'] == 'relationship') {
                foreach ($arrDependentFieldInfo['field_options'] as $arrOptionInfo) {
                    if (!in_array($arrOptionInfo['option_id'], $arrSkipTypes)) {
                        for ($i = 1; $i <= $arrOptionInfo['option_max_count']; $i++) {
                            $arrClientFamilyMembers[] = array(
                                'id' => $arrOptionInfo['option_id'] . $i,
                                'value' => $arrOptionInfo['option_name'] . ' ' . $i,
                                'order' => $row++
                            );
                        }
                    }
                }
                break;
            }
        }

        $arrClientFamilyMembers[] = array(
            'id' => 'sponsor',
            'value' => 'Sponsor (Non-spousal)',
            'order' => $row++
        );

        $arrClientFamilyMembers[] = array(
            'id' => 'employer',
            'value' => 'Employer',
            'order' => $row
        );

        // Sort by order
        foreach ($arrClientFamilyMembers as $key => $row) {
            $order[$key] = $row['order'];
        }
        array_multisort($order, SORT_ASC, $arrClientFamilyMembers);

        // How result will be returned
        if ($booAsKeyVal) {
            $arrResult = array();
            foreach ($arrClientFamilyMembers as $memberInfo) {
                $arrResult[$memberInfo['id']] = $memberInfo['value'];
            }
        } else {
            $arrResult = $arrClientFamilyMembers;
        }

        return $arrResult;
    }

    /**
     * Load family members for specific client
     *
     * @param $clientMemberId
     * @param bool $booForAssign
     * @return array
     */
    public function getFamilyMembersForClient($clientMemberId, $booForAssign = false)
    {
        // Get Info about this client
        $thisMemberInfo = $this->getClientInfo($clientMemberId);

        $booHideMainOptions = false;
        $strEmployerName = '';

        // Load parent Employer info
        $arrParents = $this->getParentsForAssignedApplicants(array($clientMemberId), false, false);
        if ($this->_auth->isCurrentMemberCompanyEmployerModuleEnabled() && !$booForAssign) {
            foreach ($arrParents as $arrParentInfo) {
                if ($arrParentInfo['member_type_name'] == 'employer') {
                    $arrParentEmployer = static::generateMemberName($arrParentInfo);
                    $strEmployerName   = $arrParentEmployer['full_name'];
                }
            }

            // When this is EMPLOYER only case - we don't need "main applicant" and "Sponsor" options
            $booHideMainOptions = !empty($strEmployerName) && count($arrParents) == 1;
        }


        // Load main parent's info:
        // 1. If there is only one assigned - use him
        // 2. If there is employer and individual assigned - use individual
        // 3. If there are no assigned parents - this is an error
        $parentsCount = count($arrParents);
        if (empty($parentsCount)) {
            // Cannot be here
            $arrMainParent = array(
                'full_name_with_file_num' => 'UNASSIGNED',
                'lName' => 'UNASSIGNED',
                'fName' => 'UNASSIGNED',
            );
        } elseif ($parentsCount == 1) {
            $arrMainParent = $this->generateClientName($arrParents[0]);
        } else {
            $arrMainParent = $this->generateClientName($arrParents[0]);
            foreach ($arrParents as $arrParentInfo) {
                if ($arrParentInfo['member_type_name'] == 'individual') {
                    $arrMainParent = $this->generateClientName($arrParentInfo);
                }
            }
        }


        $arrClientFamilyMembers = array();

        $lineCount = 0;
        if (!$booHideMainOptions) {
            $arrClientFamilyMembers[] = array(
                'id' => 'main_applicant',
                'real_id' => 0,
                'value' => 'Main Applicant' . ($booForAssign ? ' - ' . $arrMainParent['full_name_with_file_num'] : ''),
                'lName' => $arrMainParent['lName'],
                'fName' => $arrMainParent['fName'],
                'order' => $lineCount++
            );
        }

        // Get filled family members
        $arrAssignedFamilyMembers = $this->getFields()->getDependents(array($clientMemberId), false);

        // These ids are in 'FormAssign' table
        if (is_array($arrAssignedFamilyMembers) && count($arrAssignedFamilyMembers) > 0) {
            $arrCounts = array();
            foreach ($arrAssignedFamilyMembers as $fm) {
                $fm = $this->generateClientName($fm);

                switch ($fm['relationship']) {
                    case 'spouse':
                        $arrClientFamilyMembers[] = array(
                            'id' => 'spouse',
                            'real_id' => $fm['dependent_id'],
                            'value' => 'Spouse' . ($booForAssign ? ' - ' . $fm['full_name'] : ''),
                            'lName' => $fm['lName'],
                            'fName' => $fm['fName'],
                            'DOB' => $fm['DOB'],
                            'order' => $lineCount++
                        );
                        break;

                    case 'parent':
                    case 'sibling':
                    case 'child':
                        $count                          = array_key_exists($fm['relationship'], $arrCounts) ? $arrCounts[$fm['relationship']] + 1 : 1;
                        $arrCounts[$fm['relationship']] = $count;
                        $arrClientFamilyMembers[]       = array(
                            'id'      => $fm['relationship'] . $count,
                            'real_id' => $fm['dependent_id'],
                            'value'   => ucfirst($fm['relationship']) . ' - ' . $fm['full_name'],
                            'lName'   => $fm['lName'],
                            'fName'   => $fm['fName'],
                            'DOB'     => $fm['DOB'],
                            'order'   => $lineCount++
                        );
                        break;

                    default:
                        break;
                }
            }
        }

        if (!$booHideMainOptions) {
            $arrClientFamilyMembers[] = array(
                'id' => 'sponsor',
                'value' => 'Sponsor (Non-spousal)',
                'lName' => '',
                'fName' => '',
                'order' => $lineCount++
            );
        }

        // Use Employer record, if case is assigned to Employer
        if (!empty($strEmployerName)) {
            // Get value from 'Employer Sponsorship Case' field
            $caseEmployerLinkFieldId = $this->getFields()->getCompanyFieldIdByUniqueFieldId('link_to_employer_case', $thisMemberInfo['company_id']);
            $arrSavedValues = $this->getFields()->getClientsFieldDataValue($caseEmployerLinkFieldId, array($clientMemberId));

            // If this field is filled - load that case's info and use its name
            if (is_array($arrSavedValues) && count($arrSavedValues)) {
                $assignedCaseInfo = $this->getClientInfo($arrSavedValues[$clientMemberId]['value']);
                $caseName = is_array($assignedCaseInfo) && count($assignedCaseInfo) ? $assignedCaseInfo['full_name_with_file_num'] : '';
            }

            // Otherwise, show the current case's name
            if (empty($caseName)) {
                $caseName = $thisMemberInfo['full_name_with_file_num'];
            }

            $arrClientFamilyMembers[] = array(
                'id' => 'employer',
                'value' => 'Employer - ' . $strEmployerName . ' (' . $caseName . ')',
                'lName' => '',
                'fName' => '',
                'order' => $lineCount++
            );
        }

        $arrClientFamilyMembers[] = array(
            'id' => 'other1',
            'value' => 'Other',
            'lName' => '',
            'fName' => '',
            'order' => $lineCount
        );

        // Sort by order
        foreach ($arrClientFamilyMembers as $key => $row) {
            $order[$key] = $row['order'];
        }
        array_multisort($order, SORT_ASC, $arrClientFamilyMembers);

        return $arrClientFamilyMembers;
    }

    /**
     * Get Family member's name for a specific client
     *
     * @param int $memberId
     * @param string $familyMemberId
     * @return array|string[]
     */
    public function getFamilyMemberNamesForTheClient($memberId, $familyMemberId)
    {
        $fName = '';
        $lName = '';

        $arrFamilyMembers = $this->getFamilyMembersForClient($memberId);
        foreach ($arrFamilyMembers as $familyMemberInfo) {
            if (preg_match('/^other\d*$/', $familyMemberId)) {
                $lName = 'Other';
                break;
            } elseif ($familyMemberInfo['id'] == $familyMemberId) {
                $fName = $familyMemberInfo['fName'];
                $lName = $familyMemberInfo['lName'];
                break;
            }
        }

        return array('fName' => $fName, 'lName' => $lName);
    }

    /**
     * Generate a "where" for the query based on the case + access rights
     *
     * @param bool $booCaseOnly
     * @return Predicate
     */
    public function getStructureQueryForClient($booCaseOnly = true)
    {
        $arrTypes = [
            $this->getMemberTypeIdByName('case')
        ];

        if (!$booCaseOnly) {
            $arrTypes[] = $this->getMemberTypeIdByName('individual');
            $arrTypes[] = $this->getMemberTypeIdByName('employer');
        }

        $where = (new Where())
            ->nest()
            ->in('m.userType', $arrTypes);

        list($query,) = $this->getMemberStructureQuery();
        if (!empty($query)) {
            $where->andPredicate($query);
        }

        return $where->unnest();
    }

    /**
     * Load offices assigned to the client(s)
     * @param $clients
     * @return array
     */
    public function getClientsDivision($clients)
    {
        if (!is_array($clients) || empty($clients)) {
            return array();
        }

        $select = (new Select())
            ->from(array('d' => 'divisions'))
            ->join(array('md' => 'members_divisions'), 'md.division_id = d.division_id', array('member_id'), Select::JOIN_LEFT)
            ->where(
                [
                    'md.member_id' => $clients,
                    'md.type'      => 'access_to'
                ]
            );

        return $this->_db2->fetchAll($select);
    }

    /**
     * Client can be deleted only when there is no T/A info assigned to him
     *
     * @param int $memberId
     * @return bool true if client can be deleted, otherwise false
     */
    public function canDeleteClient($memberId)
    {
        $select = (new Select())
            ->from('u_assigned_deposits')
            ->columns(array('deposits_count' => new Expression('COUNT(*)')))
            ->where(
                [
                    (new Where())
                        ->isNotNull('trust_account_id')
                        ->equalTo('member_id', (int)$memberId)
                ]
            );

        $countDeposits = (int)$this->_db2->fetchOne($select);


        $select = (new Select())
            ->from('u_invoice')
            ->columns(array('invoices_count' => new Expression('COUNT(*)')))
            ->where(['member_id' => (int)$memberId]);

        $countInvoices = (int)$this->_db2->fetchOne($select);


        $select = (new Select())
            ->from('u_payment')
            ->columns(array('payments_count' => new Expression('COUNT(*)')))
            ->where(
                [
                    (new Where())
                        ->isNotNull('trust_account_id')
                        ->equalTo('member_id', (int)$memberId)
                ]
            );

        $countPayments = (int)$this->_db2->fetchOne($select);


        $select = (new Select())
            ->from('u_assigned_withdrawals')
            ->columns(array('withdrawals_count' => new Expression('COUNT(*)')))
            ->where(['returned_payment_member_id' => (int)$memberId]);

        $countWithdrawals = (int)$this->_db2->fetchOne($select);

        return empty($countWithdrawals) && empty($countDeposits) && empty($countInvoices) && empty($countPayments);
    }


    /**
     * Delete client and all related info
     *
     * @param int $memberId
     * @param bool $booSaveInLog true to save this action in log
     * @param Actions $automaticReminderActions true to save this action in log
     * @return bool true if client was deleted successfully
     */
    public function deleteClient($memberId, $booSaveInLog, Actions $automaticReminderActions)
    {
        $booSuccess = false;

        try {
            $arrMemberInfo = $this->getClientInfo($memberId);
            $companyId = $arrMemberInfo['company_id'];
            $booLocal = $this->_company->isCompanyStorageLocationLocal($companyId);

            // Delete relations
            $this->_db2->delete(
                'members_relations',
                [
                    (new Where())
                        ->nest()
                        ->equalTo('parent_member_id', (int)$memberId)
                        ->or
                        ->equalTo('child_member_id', (int)$memberId)
                        ->unnest()
                ]
            );

            // Find all revisions and delete them
            $arrAssignedFormIds = $this->_forms->getFormAssigned()->getAssignedFormIdsByClientId($memberId);
            if (is_array($arrAssignedFormIds) && count($arrAssignedFormIds)) {
                $arrRevisionIds = $this->_forms->getFormRevision()->getRevisionIdsByFormAssignedIds($arrAssignedFormIds);
                if (is_array($arrRevisionIds) && count($arrRevisionIds)) {
                    $this->_forms->getFormRevision()->deleteRevision($memberId, $arrRevisionIds);
                }
            }
            // And after that delete all assigned forms
            $this->_db2->delete('form_assigned', ['client_member_id' => $memberId]);


            // Delete folders/files
            // Always delete local and remote files/folders, if any
            $this->_files->deleteFolder($this->_files->getMemberFolder($companyId, $memberId, false, $booLocal), false);
            $this->_files->deleteFolder($this->_files->getMemberFolder($companyId, $memberId, true, $booLocal), true);

            $automaticReminderActions->deleteClientActions($memberId);
            $this->deleteClientAllDependents($memberId);

            $this->_db2->delete('client_form_data', ['member_id' => (int)$memberId]);
            $this->_db2->delete('members_divisions', ['member_id' => (int)$memberId]);

            $this->_db2->delete(
                'members_last_access',
                [
                    (new Where())
                        ->nest()
                        ->equalTo('member_id', (int)$memberId)
                        ->or
                        ->equalTo('view_member_id', $memberId)
                        ->unnest()
                ]
            );

            $this->_db2->delete('members_roles', ['member_id' => (int)$memberId]);
            $this->_db2->delete('u_assigned_deposits', ['member_id' => $memberId]);
            $this->_db2->delete('u_assigned_withdrawals', ['returned_payment_member_id' => $memberId]);
            $this->_db2->delete('u_invoice', ['member_id' => (int)$memberId]);

            // Delete tasks
            $this->getTasks()->deleteMemberTasks($memberId);

            $booLocal            = $this->_company->isCompanyStorageLocationLocal($arrMemberInfo['company_id']);
            $noteAttachmentsPath = $this->_files->getClientNoteAttachmentsPath($arrMemberInfo['company_id'], $memberId, $booLocal);
            $this->_files->deleteFolder($noteAttachmentsPath, $booLocal);

            $this->_db2->delete('u_invoice', ['member_id' => $memberId]);
            $this->_db2->delete('u_payment', ['member_id' => $memberId]);
            $this->_db2->delete('u_payment_schedule', ['member_id' => $memberId]);
            $this->_db2->delete('members_ta', ['member_id' => $memberId]);
            $this->_db2->delete('clients', ['member_id' => $memberId]);
            $this->_db2->delete('time_tracker', ['track_member_id' => $memberId]);
            $this->_db2->delete('members', ['member_id' => $memberId]);

            if ($booSaveInLog) {
                $arrLog = array(
                    'log_section'     => 'user',
                    'log_action'      => 'delete',
                    'log_description' => sprintf('{1} deleted %s case', $arrMemberInfo['full_name_with_file_num']),
                    'log_company_id'  => $arrMemberInfo['company_id'],
                    'log_created_by'  => $this->_auth->getCurrentUserId(),
                );

                /** @var AccessLogs $accessLogs */
                $accessLogs = $this->_serviceContainer->get(AccessLogs::class);
                $accessLogs->saveLog($arrLog);
            }


            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Create a duplicate for specific client
     *
     * @param int $memberId
     * @return bool true on success
     */
    public function duplicateClient($memberId)
    {
        try {
            // members
            $select = (new Select())
                ->from('members')
                ->where(['member_id' => (int)$memberId]);

            $source_data = $this->_db2->fetchRow($select);

            unset($source_data['member_id'], $source_data['lastLogin']);
            $source_data['regTime'] = time();

            $newMemberId = $this->_db2->insert('members', $source_data);
            $companyId   = $source_data['company_id'];

            // members_roles
            $select = (new Select())
                ->from('members_roles')
                ->where(['member_id' => (int)$memberId]);

            $source_data = $this->_db2->fetchAll($select);

            if (count($source_data)) {
                foreach ($source_data as $sd) {
                    $sd['member_id'] = $newMemberId;
                    $this->_db2->insert('members_roles', $sd);
                }
            }

            // members_divisions
            $source_data = $this->getMemberDivisions($memberId);

            if (count($source_data)) {
                $this->updateApplicantOffices($newMemberId, $source_data, false);
            }

            // clients
            $source_data = $this->getClientInfoOnly($memberId);

            unset($source_data['client_id']);
            $source_data['member_id'] = $newMemberId;
            $this->_db2->insert('clients', $source_data);

            // client_form_data
            $arrSavedData = $this->getClientSavedData(array($memberId), array(), false);
            if (count($arrSavedData)) {
                $arrToSave = array();
                foreach ($arrSavedData as $arrSavedDataRow) {
                    $arrToSave[$arrSavedDataRow['field_id']] = $arrSavedDataRow['value'];
                }

                $this->saveClientData($newMemberId, $arrToSave, false);
            }

            // dependents
            $source_data = $this->getFields()->getDependents(array($memberId), false);
            if (count($source_data)) {
                foreach ($source_data as $sd) {
                    $sd['member_id'] = $newMemberId;
                    $this->_db2->insert('client_form_dependents', $sd);
                }
            }

            // create default folders
            $booLocal = $this->_company->isCompanyStorageLocationLocal($companyId);
            $this->_files->mkNewMemberFolders(
                $newMemberId,
                $companyId,
                $booLocal
            );

            // transfer the XFDF data
            $source_folder = $this->_files->getClientXFDFFTPFolder($memberId);
            $destination_folder = $this->_files->getClientXFDFFTPFolder($newMemberId);

            $this->_files->createFTPDirectory($destination_folder);
            foreach (glob($source_folder . DIRECTORY_SEPARATOR . '*') as $file) {
                $filename = basename($file);

                copy($file, $destination_folder . DIRECTORY_SEPARATOR . $filename);
            }

            $this->_systemTriggers->triggerCaseCreation(
                $companyId,
                $memberId,
                $this->_auth->getCurrentUserId(),
                $this->_auth->hasIdentity() ? $this->_auth->getIdentity()->full_name : ''
            );

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Load client info only (from clients table)
     *
     * @param int $memberId
     * @return array
     */
    public function getClientInfoOnly($memberId)
    {
        $select = (new Select())
            ->from('clients')
            ->where(['member_id' => (int)$memberId]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Update client in DB
     *
     * @param array $arrClientAndCaseInfo
     * @param int $companyId
     * @param array $arrCompanyFields
     * @param int $fieldIdToSaveLog
     * @param null $loggedInAsMemberId
     * @return bool true on success, otherwise false
     */
    public function updateClient($arrClientAndCaseInfo, $companyId, $arrCompanyFields, $fieldIdToSaveLog = 0, $loggedInAsMemberId = null)
    {
        try {
            $memberId = $arrClientAndCaseInfo['member_id'];
            $arrCaseInfo = $arrClientAndCaseInfo['case'];
            $arrWhere = ['member_id' => (int)$memberId];

            // Log which fields were updated/changed
            $arrLog = array();

            // Collect all fields which must be updated in xfdf files
            $arrPdfInfoUpdate = array();

            // Update members table
            $arrMembersUpdate = array();

            $arrFieldsToSkip = array('regTime');
            $arrSavedMemberInfo = $this->getMemberInfo($memberId);
            $arrMemberInsertInfo = $arrCaseInfo['members'];
            foreach ($arrMemberInsertInfo as $fieldId => $fieldVal) {
                if (in_array($fieldId, $arrFieldsToSkip) || $fieldVal == '') {
                    continue;
                }

                if ($arrSavedMemberInfo && array_key_exists($fieldId, $arrSavedMemberInfo) && $arrSavedMemberInfo[$fieldId] != $arrMemberInsertInfo[$fieldId]) {
                    // Generate log record - will be used later to be saved in a special field
                    $staticFieldId = $this->getFields()->getStaticColumnNameByFieldId($fieldId);
                    if ($staticFieldId) {
                        foreach ($arrCompanyFields as $arrFieldInfo) {
                            if ($arrFieldInfo['company_field_id'] == $staticFieldId) {
                                list($readableValueSetTo,) = $this->getFields()->getFieldReadableValue($companyId, $this->_auth->getCurrentUserDivisionGroupId(), $arrFieldInfo, $arrMemberInsertInfo[$fieldId]);
                                list($readableValueSetFrom,) = $this->getFields()->getFieldReadableValue($companyId, $this->_auth->getCurrentUserDivisionGroupId(), $arrFieldInfo, $arrSavedMemberInfo[$fieldId]);

                                if (!empty($arrSavedMemberInfo[$fieldId])) {
                                    $arrLog[] = $arrFieldInfo['label'] . ' changed from ' . $readableValueSetFrom . ' to ' . $readableValueSetTo;
                                }

                                $arrPdfInfoUpdate[$fieldId] = $readableValueSetTo;
                                break;
                            }
                        }
                    }

                    $arrMembersUpdate[$fieldId] = $fieldVal;
                }
            }

            if (is_array($arrMembersUpdate) && count($arrMembersUpdate)) {
                $this->_db2->update('members', $arrMembersUpdate, ['member_id' => $memberId]);
            }


            // Update clients table,
            // log which fields were updated/changed
            $arrClientsUpdate = array();
            $arrClientInsertInfo = $arrCaseInfo['clients'];
            $arrFieldsToSkip = array('added_by_member_id');
            $arrSavedClientInfo = $this->getClientShortInfo($memberId);
            foreach ($arrClientInsertInfo as $fieldId => $fieldVal) {
                if (in_array($fieldId, $arrFieldsToSkip) || $fieldVal == '') {
                    continue;
                }

                if (array_key_exists($fieldId, $arrSavedClientInfo) && $arrSavedClientInfo[$fieldId] != $arrClientInsertInfo[$fieldId]) {
                    // Generate log record - will be used later to be saved in a special field
                    $staticFieldId = $this->getFields()->getStaticColumnNameByFieldId($fieldId);
                    if ($staticFieldId) {
                        foreach ($arrCompanyFields as $arrFieldInfo) {
                            if ($arrFieldInfo['company_field_id'] == $staticFieldId) {
                                list($readableValueSetTo,) = $this->getFields()->getFieldReadableValue($companyId, $this->_auth->getCurrentUserDivisionGroupId(), $arrFieldInfo, $arrClientInsertInfo[$fieldId]);
                                list($readableValueSetFrom,) = $this->getFields()->getFieldReadableValue($companyId, $this->_auth->getCurrentUserDivisionGroupId(), $arrFieldInfo, $arrSavedClientInfo[$fieldId]);

                                if (!empty($arrSavedClientInfo[$fieldId])) {
                                    $arrLog[] = $arrFieldInfo['label'] . ' changed from ' . $readableValueSetFrom . ' to ' . $readableValueSetTo;
                                }

                                $arrPdfInfoUpdate[$fieldId] = $readableValueSetTo;
                                break;
                            }
                        }
                    }

                    $arrClientsUpdate[$fieldId] = $fieldVal;
                }
            }

            if (is_array($arrClientsUpdate) && count($arrClientsUpdate)) {
                $this->_db2->update('clients', $arrClientsUpdate, ['member_id' => $memberId]);
            }


            // Update clients data table,
            // log which fields were updated/changed
            $arrClientsDataUpdate = array();
            $arrClientDataInsertInfo = $arrCaseInfo['client_form_data'];

            $select = (new Select())
                ->from(array('d' => 'client_form_data'))
                ->columns(array('field_id', 'value'))
                ->where($arrWhere);

            $arrSavedClientData = $this->_db2->fetchAssoc($select);

            // Skip several log records - they will be added in the previous update sections
            $arrFieldsToSkip = array('first_name', 'last_name', 'file_number', 'division');
            foreach ($arrClientDataInsertInfo as $fieldId => $fieldVal) {
                if ($fieldVal == '') {
                    continue;
                }

                $savedValue = array_key_exists($fieldId, $arrSavedClientData) ? $arrSavedClientData[$fieldId]['value'] : '';
                if ($savedValue != $arrClientDataInsertInfo[$fieldId]) {
                    // Generate log record - will be used later to be saved in a special field
                    foreach ($arrCompanyFields as $arrFieldInfo) {
                        if (in_array($arrFieldInfo['company_field_id'], $arrFieldsToSkip)) {
                            continue;
                        }

                        if ($arrFieldInfo['field_id'] == $fieldId) {
                            list($readableValueSetTo,) = $this->getFields()->getFieldReadableValue($companyId, $this->_auth->getCurrentUserDivisionGroupId(), $arrFieldInfo, $arrClientDataInsertInfo[$fieldId]);
                            list($readableValueSetFrom,) = $this->getFields()->getFieldReadableValue($companyId, $this->_auth->getCurrentUserDivisionGroupId(), $arrFieldInfo, $savedValue);

                            if (!empty($savedValue)) {
                                $arrLog[] = $arrFieldInfo['label'] . ' changed from ' . $readableValueSetFrom . ' to ' . $readableValueSetTo;
                            }

                            $arrPdfInfoUpdate[$fieldId] = $readableValueSetTo;
                            break;
                        }
                    }

                    $arrClientsDataUpdate[$fieldId] = $fieldVal;
                }
            }

            if (is_array($arrClientsDataUpdate) && count($arrClientsDataUpdate)) {
                foreach ($arrClientsDataUpdate as $fieldId => $fieldVal) {
                    $this->_db2->delete(
                        'client_form_data',
                        [
                            'member_id' => (int)$memberId,
                            'field_id'  => (int)$fieldId
                        ]
                    );

                    $this->_db2->insert(
                        'client_form_data',
                        [
                            'member_id' => $memberId,
                            'field_id'  => $fieldId,
                            'value'     => $fieldVal
                        ]
                    );
                }
            }


            // Save log records in a specific field
            if (!empty($fieldIdToSaveLog) && is_array($arrLog) && count($arrLog)) {
                $savedLogValue = $this->getFields()->getFieldDataValue($fieldIdToSaveLog, $memberId);

                if (!is_null($loggedInAsMemberId)) {
                    $memberInfo = $this->getMemberInfo($loggedInAsMemberId);
                    $memberName = $memberInfo['full_name'];
                } else {
                    $memberName = $this->getCurrentMemberName(true);
                }

                $strValue = $savedLogValue;
                $strValue .= PHP_EOL .
                    str_repeat('-', 70) . PHP_EOL .
                    implode(PHP_EOL, $arrLog) . PHP_EOL . PHP_EOL .
                    sprintf('Last updated by %s on %s.', $memberName, date('M d, Y')) . PHP_EOL .
                    str_repeat('-', 70);

                $this->_db2->delete(
                    'client_form_data',
                    [
                        'member_id' => (int)$memberId,
                        'field_id'  => (int)$fieldIdToSaveLog
                    ]
                );

                $this->_db2->insert(
                    'client_form_data',
                    [
                        'member_id' => $memberId,
                        'field_id'  => $fieldIdToSaveLog,
                        'value'     => $strValue
                    ]
                );
            }


            // Don't update empty fields
            foreach ($arrPdfInfoUpdate as $key => $val) {
                if (empty($val)) {
                    unset($arrPdfInfoUpdate[$key]);
                }
            }

            // Update xfdf files
            $memberTypeId = $this->getMemberTypeIdByName('case');
            $this->_pdf->updateXfdfOnProfileUpdate($companyId, $memberId, $arrPdfInfoUpdate, $memberTypeId);


            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Create/update client + create a new case
     *
     * @param array $arrClientAndCaseInfo
     * @param int $companyId
     * @param int $divisionGroupId
     * @param bool $booTriggerCaseCreation true to run auto tasks based on case creation
     * @param bool $booUpdateClientIfExists true to update client if exists in DB, otherwise always create a new one
     * @return array
     */
    public function createClient($arrClientAndCaseInfo, $companyId, $divisionGroupId, $booTriggerCaseCreation = false, $booUpdateClientIfExists = false)
    {
        $strError               = '';
        $caseId                 = 0;
        $arrCreatedClientIds    = array();
        $arrCreatedAllClientIds = array();

        try {
            // Will be used to save some info to the case's info
            $arrMainClientInfo = $arrClientAndCaseInfo['arrMainClientInfo'] ?? array();

            $applicantId = 0;
            if (isset($arrClientAndCaseInfo['applicantId'])) {
                $applicantId = $arrClientAndCaseInfo['applicantId'];
            }

            $createdBy = 0;
            if (isset($arrClientAndCaseInfo['createdBy'])) {
                $createdBy = $arrClientAndCaseInfo['createdBy'];
            }

            if (isset($arrClientAndCaseInfo['arrParentsIds'])) {
                $arrCreatedClientIds = $arrClientAndCaseInfo['arrParentsIds'];
            }

            if (isset($arrClientAndCaseInfo['arrParentClientAndInternalContactIds'])) {
                $arrCreatedAllClientIds = $arrClientAndCaseInfo['arrParentClientAndInternalContactIds'];
            }

            foreach ($arrClientAndCaseInfo['arrParents'] as $arrParentClientInfo) {
                $arrClientInfo = $arrParentClientInfo['arrParentClientInfo'];
                $arrContacts   = $arrParentClientInfo['arrInternalContacts'];

                if ($arrClientInfo['memberTypeId'] == $this->getMemberTypeIdByName('individual') || (isset($arrClientInfo['arrParents']) && count($arrClientAndCaseInfo['arrParents']) == 1)) {
                    $arrMainClientInfo = $arrClientInfo;
                }

                // Try to find already created client
                $existingParentClientId = 0;
                if ($booUpdateClientIfExists) {
                    $existingParentClientId = $this->getApplicantByInfo($companyId, $arrClientInfo);
                }

                if ($existingParentClientId) {
                    $arrUpdateApplicant = $this->updateApplicant(
                        $companyId,
                        $arrClientInfo['createdBy'],
                        $existingParentClientId,
                        $arrClientInfo['arrApplicantData'],
                        $arrClientInfo['arrOffices']
                    );

                    if (!$arrUpdateApplicant['success']) {
                        $strError = $this->_tr->translate('Internal error [cannot update client].');
                    } else {
                        $arrCreatedClientIds[] = $existingParentClientId;
                    }
                } else {
                    $applicantId = $this->createApplicant(
                        $companyId,
                        $divisionGroupId,
                        $arrClientInfo['createdBy'],
                        $arrClientInfo['memberTypeId'],
                        $arrClientInfo['applicantTypeId'] ?? null,
                        $arrClientInfo['arrApplicantData'],
                        $arrClientInfo['arrOffices'],
                        isset($arrClientInfo['createdOn']) && !empty($arrClientInfo['createdOn']) ? $arrClientInfo['createdOn'] : null
                    );

                    if (!$applicantId) {
                        $strError = $this->_tr->translate('Internal error [cannot create client].');
                    }

                    if (empty($strError)) {
                        $contactTypeId = $this->getMemberTypeIdByName('internal_contact');
                        $arrContactIds = array();
                        foreach ($arrContacts as $arrContactInfo) {
                            if (is_array($arrContactInfo['data']) && count($arrContactInfo['data'])) {
                                // Create a new contact
                                $contactId = $this->createApplicant(
                                    $companyId,
                                    $divisionGroupId,
                                    $arrClientInfo['createdBy'],
                                    $contactTypeId,
                                    null,
                                    $arrContactInfo['data'],
                                    $arrClientInfo['arrOffices'],
                                    isset($arrClientInfo['createdOn']) && !empty($arrClientInfo['createdOn']) ? $arrClientInfo['createdOn'] : null
                                );

                                $arrContactIds[]          = $contactId;
                                $arrCreatedAllClientIds[] = $contactId;

                                if (!$contactId) {
                                    $strError = $this->_tr->translate('Internal error [cannot create internal contact].');
                                    break;
                                }

                                // Assign to the applicant in specific section
                                foreach ($arrContactInfo['parent_group_id'] as $parentGroupId) {
                                    $row           = $this->getRowForApplicant($applicantId, $parentGroupId);
                                    $arrAssignData = array(
                                        'applicant_id' => $applicantId,
                                        'contact_id'   => $contactId,
                                        'group_id'     => $parentGroupId,
                                        'row'          => is_null($row) ? 0 : $row + 1,
                                    );
                                    if (!$this->assignContactToApplicant($arrAssignData)) {
                                        $strError = $this->_tr->translate('Internal error [cannot assign internal contact].');
                                        break 2;
                                    }
                                }
                            }
                        }

                        if (empty($strError) && !$this->updateAssignedContacts($applicantId, $arrContactIds)) {
                            $strError = $this->_tr->translate('Internal error.');
                        }


                        // Update applicant's name from the 'main contact'
                        if (empty($strError) && !$this->updateApplicantFromMainContact($companyId, $applicantId, $arrClientInfo['memberTypeId'], $arrClientInfo['applicantTypeId'])) {
                            $strError = $this->_tr->translate('Internal error [cannot update client from internal contact].');
                        }

                        // Automatically mark this new client as viewed (if we don't create a case)
                        // so will be showed in the left section
                        if (empty($strError) && !isset($arrClientAndCaseInfo['case'])) {
                            $this->saveLastViewedClient($createdBy, $applicantId);
                        }

                        $arrCreatedClientIds[]    = $applicantId;
                        $arrCreatedAllClientIds[] = $applicantId;
                    }
                }
            }

            // Create case
            if (empty($strError) && isset($arrClientAndCaseInfo['case'])) {
                $arrCaseInfo         = $arrClientAndCaseInfo['case'];
                $arrMemberInsertInfo = $arrCaseInfo['members'];
                if (empty($arrMemberInsertInfo['regTime'])) {
                    $arrMemberInsertInfo['regTime'] = time();
                }

                if (!isset($arrMemberInsertInfo['division_group_id']) && isset($arrMemberInsertInfo['company_id'])) {
                    if ($arrMemberInsertInfo['company_id'] == $this->_auth->getCurrentUserCompanyId()) {
                        $arrMemberInsertInfo['division_group_id'] = $this->_auth->getCurrentUserDivisionGroupId();
                    } else {
                        $arrMemberInsertInfo['division_group_id'] = $this->_company->getCompanyDivisions()->getCompanyMainDivisionGroupId($arrMemberInsertInfo['company_id']);
                    }
                }

                $caseId = $this->_db2->insert('members', $arrMemberInsertInfo);

                // Create record in clients table
                $arrClientInsertInfo              = $arrCaseInfo['clients'];
                $arrClientInsertInfo['member_id'] = $caseId;
                if (array_key_exists('client_type_id', $arrClientInsertInfo) && empty($arrClientInsertInfo['client_type_id'])) {
                    unset($arrClientInsertInfo['client_type_id']);
                }
                $parentClientCasesCount = $this->getClientCaseMaxNumber($applicantId, false);
                $parentClientCasesCount = empty($parentClientCasesCount) ? 1 : $parentClientCasesCount + 1;

                $employerId = 0;
                foreach ($arrCreatedClientIds as $parentClientId) {
                    $arrParentInfo = $this->getMemberInfo($parentClientId);
                    if ($arrParentInfo['userType'] == $this->getMemberTypeIdByName('employer')) {
                        $employerId = $parentClientId;
                        break;
                    }
                }

                if (empty($employerId)) {
                    $parentEmployerCasesCount = null;
                } else {
                    $parentEmployerCasesCount = $this->getClientCaseMaxNumber($employerId, true);
                    $parentEmployerCasesCount = empty($parentEmployerCasesCount) ? 1 : $parentEmployerCasesCount + 1;
                }

                $casesCountInCompany = $this->getCompanyCaseMaxNumber($companyId);
                $casesCountInCompany = empty($casesCountInCompany) ? 1 : $casesCountInCompany + 1;

                $arrClientInsertInfo['case_number_of_parent_client']   = $parentClientCasesCount;
                $arrClientInsertInfo['case_number_of_parent_employer'] = $parentEmployerCasesCount;
                $arrClientInsertInfo['case_number_in_company']         = $casesCountInCompany;

                if (array_key_exists('client_type_id', $arrClientInsertInfo)) {
                    $casesCountWithSameCaseTypeInCompany = $this->getCompanyCaseMaxNumber($companyId, $arrClientInsertInfo['client_type_id']);
                    $casesCountWithSameCaseTypeInCompany = empty($casesCountWithSameCaseTypeInCompany) ? 1 : $casesCountWithSameCaseTypeInCompany + 1;

                    $arrClientInsertInfo['case_number_with_same_case_type_in_company'] = $casesCountWithSameCaseTypeInCompany;
                }

                $this->_db2->insert('clients', $arrClientInsertInfo);

                // Insert division
                if (isset($arrCaseInfo['members_divisions'])) {
                    $arrDivisionInsertInfo = $arrCaseInfo['members_divisions'];
                    if (is_array($arrDivisionInsertInfo) && !empty($arrDivisionInsertInfo)) {
                        $this->updateApplicantOffices($caseId, array_values($arrDivisionInsertInfo), false);
                    }
                }

                // Insert client's data
                $arrClientDataInsertInfo = array();
                if (isset($arrCaseInfo['client_form_data'])) {
                    $arrClientDataInsertInfo = $arrCaseInfo['client_form_data'];
                    $this->saveClientData($caseId, $arrClientDataInsertInfo);
                }

                // Create notes
                if (isset($arrCaseInfo['u_notes'])) {
                    $arrClientNotes = $arrCaseInfo['u_notes'];
                    if (is_array($arrClientNotes) && !empty($arrClientNotes)) {
                        /** @var CompanyProspects $companyProspects * */
                        $companyProspects = $this->_serviceContainer->get(CompanyProspects::class);

                        $booLocal = $this->_auth->isCurrentUserCompanyStorageLocal();

                        foreach ($arrClientNotes as $arrNoteInfo) {
                            $arrNoteInfo['member_id'] = $caseId;
                            if (!array_key_exists('author_id', $arrNoteInfo)) {
                                $arrNoteInfo['author_id'] = $caseId;
                            }

                            if (!array_key_exists('create_date', $arrNoteInfo)) {
                                $arrNoteInfo['create_date'] = date('c');
                            }


                            $arrNoteInfo['note'] = str_replace(array('\n\r', '\r\n'), PHP_EOL, $arrNoteInfo['note']);

                            $prospectId = $arrNoteInfo['prospect_id'] ?? 0;
                            $oldNoteId  = $arrNoteInfo['note_id'] ?? 0;
                            unset($arrNoteInfo['prospect_id'], $arrNoteInfo['note_id']);

                            $noteId = $this->_db2->insert('u_notes', $arrNoteInfo);

                            if (!empty($prospectId) && !empty($oldNoteId)) {
                                $fileAttachments = $companyProspects->getNoteAttachments($oldNoteId);

                                foreach ($fileAttachments as $attachment) {
                                    $arrInsertAttachmentInfo = array(
                                        'note_id'   => (int)$noteId,
                                        'member_id' => (int)$caseId,
                                        'name'      => $attachment['name'],
                                        'size'      => $attachment['size']
                                    );

                                    $attachmentId = $this->_db2->insert('client_notes_attachments', $arrInsertAttachmentInfo);

                                    $oldPath = $this->_files->getProspectNoteAttachmentsPath($companyId, $prospectId, $booLocal) . '/' . $oldNoteId . '/' . $attachment['id'];
                                    $newPath = $this->_files->getClientNoteAttachmentsPath($companyId, $caseId, $booLocal) . '/' . $noteId . '/' . $attachmentId;
                                    $this->_files->moveFile($oldPath, $newPath, $booLocal);
                                }
                            }
                        }
                    }
                }

                // Create tasks
                if (isset($arrCaseInfo['u_tasks'])) {
                    $arrClientTasks = $arrCaseInfo['u_tasks'];
                    if (is_array($arrClientTasks) && !empty($arrClientTasks)) {
                        foreach ($arrClientTasks as $arrTaskInfo) {
                            $arrTaskInfo['member_id'] = $caseId;
                            $this->_db2->insert('u_tasks', $arrTaskInfo);
                        }
                    }
                }

                // Create dependants
                if (isset($arrCaseInfo['client_form_dependents'])) {
                    $arrClientDependants = $arrCaseInfo['client_form_dependents'];
                    if (is_array($arrClientDependants) && !empty($arrClientDependants)) {
                        foreach ($arrClientDependants as $arrDependantInfo) {
                            $arrInsertInfo = array(
                                'member_id'    => $caseId,
                                'relationship' => $arrDependantInfo['relationship'],
                                'line'         => $arrDependantInfo['line'],
                                'fName'        => $arrDependantInfo['fName'],
                                'lName'        => $arrDependantInfo['lName'],
                                'DOB'          => array_key_exists('DOB', $arrDependantInfo) ? $arrDependantInfo['DOB'] : null,
                                'migrating'    => array_key_exists('migrating', $arrDependantInfo) ? $arrDependantInfo['migrating'] : ''
                            );

                            $this->_db2->insert('client_form_dependents', $arrInsertInfo);
                        }
                    }
                }

                // If company has only 1 T/A, create accounting with this T/A for client
                $this->createClientTAIfCompanyHasOneTA($caseId, $companyId);

                // Now we need create xfdf files...

                $arrCreatedAllClientIds = array_unique($arrCreatedAllClientIds);
                $arrApplicantSavedData  = $this->getApplicantData($companyId, $arrCreatedAllClientIds, false, true, false, true);

                $arrApplicantInfoToSave = array();
                foreach ($arrApplicantSavedData as $arrSavedData) {
                    $fieldName = $arrSavedData['applicant_field_unique_id'];
                    if ($this->getFields()->isStaticField($fieldName)) {
                        $fieldName = $this->getFields()->getStaticColumnName($fieldName);
                    }

                    $arrApplicantInfoToSave[$fieldName] = $arrSavedData['value'];
                }

                $arrFieldIds   = array_keys($arrClientDataInsertInfo);
                $arrFieldsInfo = $this->getFields()->getFieldsInfo($arrFieldIds);

                // Insert all at once
                $arrClientInfoToSave = array();
                foreach ($arrClientDataInsertInfo as $fieldId => $fieldVal) {
                    if (!empty($fieldVal) || is_numeric($fieldVal)) {
                        $fieldName = $arrFieldsInfo[$fieldId]['company_field_id'];
                        if ($this->getFields()->isStaticField($fieldName)) {
                            $fieldName = $this->getFields()->getStaticColumnName($fieldName);
                        }
                        $arrClientInfoToSave[$fieldName] = $fieldVal;
                    }
                }

                if (isset($arrClientDependants) && is_array($arrClientDependants)) {
                    foreach ($arrClientDependants as $arrDependantInfo) {
                        $arrDependantInfo['DOB'] = $this->_settings->formatDate($arrDependantInfo['DOB'], true, Settings::DATE_XFDF);

                        $arrClientInfoToSave['dependents'][$arrDependantInfo['relationship']][] = $arrDependantInfo;
                    }
                }

                $arrClientInfoToSave['emailAddress'] = array_key_exists('emailAddress', $arrMainClientInfo) ? $arrMainClientInfo['emailAddress'] : '';
                $arrClientInfoToSave['fName']        = array_key_exists('fName', $arrMainClientInfo) ? $arrMainClientInfo['fName'] : '';
                $arrClientInfoToSave['lName']        = array_key_exists('lName', $arrMainClientInfo) ? $arrMainClientInfo['lName'] : '';
                $arrClientInfoToSave['fileNumber']   = array_key_exists('fileNumber', $arrClientInsertInfo) ? $arrClientInsertInfo['fileNumber'] : '';
                $arrClientInfoToSave['agent_id']     = array_key_exists('agent_id', $arrClientInsertInfo) ? $arrClientInsertInfo['agent_id'] : '';

                $arrClientInfoToSave['division'] = '';
                if (isset($arrDivisionInsertInfo) && is_array($arrDivisionInsertInfo)) {
                    $arrClientInfoToSave['division'] = implode(',', array_values($arrDivisionInsertInfo));
                }

                $arrSaveDataToXfdf = array_merge($arrApplicantInfoToSave, $arrClientInfoToSave);

                $memberTypeId = $this->getMemberTypeByMemberId($applicantId);
                $this->_pdf->updateXfdfOnProfileUpdate($companyId, $caseId, $arrSaveDataToXfdf, $memberTypeId);
            }

            // Assign case to the parent applicant(s)
            if (empty($strError) && count($arrCreatedClientIds) && !empty($caseId)) {
                foreach ($arrCreatedClientIds as $parentClientId) {
                    $arrAssignData = array(
                        'applicant_id' => $parentClientId,
                        'case_id'      => $caseId,
                    );

                    if (!$this->assignCaseToApplicant($arrAssignData)) {
                        $strError = $this->_tr->translate('Internal error.');
                    }
                }
            }

            if (empty($strError) && !empty($caseId)) {
                // Automatically mark this new case as viewed
                // so will be showed in the left section
                $arrParentClients = $this->getParentsForAssignedApplicant($caseId);
                $this->saveLastViewedClient($createdBy, $caseId, $arrParentClients);

                // Trigger case creation auto task
                if ($booTriggerCaseCreation) {
                    /** @var Users $oUsers */
                    $oUsers      = $this->_serviceContainer->get(Users::class);
                    $arrUserInfo = $oUsers->getUserInfo($createdBy);
                    $this->_systemTriggers->triggerCaseCreation(
                        $companyId,
                        $caseId,
                        $createdBy,
                        $arrUserInfo['full_name']
                    );
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $strError = $this->_tr->translate('Internal error.');
        }

        return array(
            'strError'               => $strError,
            'caseId'                 => $caseId,
            'arrCreatedClientIds'    => $arrCreatedClientIds,
            'arrAllCreatedClientIds' => $arrCreatedAllClientIds,
        );
    }

    /**
     * Update client info
     * @param int $memberId
     * @param array $arrUpdate
     * @return bool true on success
     */
    public function updateClientInfo($memberId, $arrUpdate)
    {
        try {
            if (is_array($arrUpdate) && count($arrUpdate)) {
                $this->_db2->update('clients', $arrUpdate, ['member_id' => $memberId]);
            }
            $boSuccess = true;
        } catch (Exception $e) {
            $boSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $boSuccess;
    }

    /**
     * Clear saved values for the case for the specific field(s)
     *
     * @param int $memberId
     * @param array $arrFieldIds
     * @return bool true on success
     */
    public function clearClientFieldData($memberId, $arrFieldIds)
    {
        $booSuccess = false;

        try {
            $this->_db2->delete(
                'client_form_data',
                [
                    'member_id' => (int)$memberId,
                    'field_id'  => $arrFieldIds
                ]
            );

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Save case data
     *
     * @param int $memberId
     * @param array $arrData in 'field id' => 'field value' format
     * @param bool $booEncryptCheck if false - don't check if data must be encrypted
     * @return array of bool (true on success), array of old data (before update)
     */
    public function saveClientData($memberId, $arrData, $booEncryptCheck = true)
    {
        $booSuccess = false;
        $arrOldData = array();
        $arrNewData = array();

        try {
            if (is_array($arrData) && count($arrData)) {
                $arrFieldIds = array_keys($arrData);

                // Load already saved data
                $arrOldData = $this->getFields()->getClientFieldsValues($memberId, $arrFieldIds);

                // Clear previously saved data (for received fields)
                $this->clearClientFieldData($memberId, $arrFieldIds);

                // Load info about fields - will be used to determine if we need encrypt data
                $arrFieldsInfo = $this->getFields()->getFieldsInfo($arrFieldIds);

                // Prepare data to the 'global' format, so it looks similar to what we have for clients
                $arrDataFormatted = array();
                foreach ($arrData as $fieldId => $fieldVal) {
                    if (!isset($arrFieldsInfo[$fieldId])) {
                        continue;
                    }

                    $arrDataFormatted[$fieldId] = $arrFieldsInfo[$fieldId];
                    $arrDataFormatted[$fieldId]['field_unique_id'] = $arrFieldsInfo[$fieldId]['company_field_id'];
                    $arrDataFormatted[$fieldId]['field_type'] = $this->getFieldTypes()->getStringFieldTypeById($arrFieldsInfo[$fieldId]['type']);
                    $arrDataFormatted[$fieldId]['value'] = $fieldVal;
                }

                // Calculate values for auto calc fields
                $arrDataFormatted = $this->_recalculateAutoCalcFields($memberId, $arrDataFormatted);

                foreach ($arrDataFormatted as $arrDataToSave) {
                    $fieldId = $arrDataToSave['field_id'];
                    $fieldVal = $arrDataToSave['value'];

                    if (!isset($arrFieldsInfo[$fieldId])) {
                        continue;
                    }

                    if (!empty($fieldVal) || is_numeric($fieldVal)) {
                        $booEncryptedField = $arrFieldsInfo[$fieldId]['encrypted'] == 'Y';
                        if ($booEncryptCheck && $booEncryptedField) {
                            $fieldVal = $this->_encryption->encode($fieldVal);
                        }

                        $this->_db2->insert(
                            'client_form_data',
                            [
                                'member_id' => (int)$memberId,
                                'field_id'  => (int)$fieldId,
                                'value'     => $fieldVal
                            ]
                        );

                        $arrNewData[] = array(
                            'member_id' => $memberId,
                            'field_id'  => $fieldId,
                            'value'     => $booEncryptedField ? $this->_encryption->decode($fieldVal) : $fieldVal,
                            'encrypted' => $arrFieldsInfo[$fieldId]['encrypted'],
                        );
                    }
                }
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array('success' => $booSuccess, 'arrOldData' => $arrOldData, 'arrNewData' => $arrNewData);
    }


    /**
     * Load assigned forms list for specific client
     *
     * @param int $memberId
     * @param string $sort
     * @param string $dir
     * @param int $start
     * @param int $limit
     * @return array
     */
    public function getClientFormsList($memberId, $sort = '', $dir = '', $start = 0, $limit = 100)
    {
        $arrFormattedForms = array();
        $totalCount        = 0;
        $booLocked         = true;

        try {
            // Get family members for this client
            $arrFamilyMembers = $this->getFamilyMembersForClient($memberId);
            $arrFamilyMembers = array_merge($arrFamilyMembers, $this->getFamilyMembersForClient($memberId, true));

            // Load assigned pdf forms which have several versions
            $subSelect = (new Select())
                ->from(['fa' => 'form_assigned'])
                ->columns([])
                ->join(['fv' => 'form_version'], 'fa.form_version_id = fv.form_version_id', ['form_version_id', 'form_id'], Select::JOIN_LEFT)
                ->where(['fa.client_member_id' => $memberId])
                ->group('fa.form_version_id');

            $selectFormToAssign = (new Select())
                ->from(['test' => $subSelect])
                ->columns([Select::SQL_STAR, new Expression('COUNT(test.form_id)')])
                ->group('test.form_id')
                ->having('COUNT( test.form_id ) > 1');

            $arrFormTooAssigns = $this->_db2->fetchAll($selectFormToAssign);

            $arrFormIds = array();
            foreach ($arrFormTooAssigns as $arrFormTooAssign) {
                $arrFormIds[] = $arrFormTooAssign['form_id'];
            }

            // Get assigned forms for this member
            $arrResult        = $this->_forms->getFormAssigned()->fetchByClientForms($memberId, $sort, $dir, $start, $limit);
            $arrAssignedForms = $arrResult['rows'];
            $totalCount       = $arrResult['totalCount'];

            $arrMemberInfo         = $this->getMemberInfo($memberId);
            $booEnabledAnnotations = $this->_company->areAnnotationsEnabledForCompany($arrMemberInfo['company_id']);

            // Get help article id that we'll show in the pdftron (xod)
            $helpArticleId = 0;
            if ($this->_acl->isAllowed('help-view')) {
                $arrContextIdInfo = $this->_help->getContextIdInfoByTextId('form_filling');
                if (!empty($arrContextIdInfo)) {
                    $arrArticles = $this->_help->getContextTagsByTextId($arrContextIdInfo['faq_context_id']);
                    if (!empty($arrArticles)) {
                        // Use the first found help article
                        $helpArticleId = $arrArticles[0]['faq_id'];
                    }
                }
            }

            foreach ($arrAssignedForms as $assignedFormInfo) {
                // Get name of 'Updated By' person
                if (!empty($assignedFormInfo['updated_by'])) {
                    $arrUpdateUserInfo  = static::generateMemberName($assignedFormInfo);
                    $updateUserFullName = $arrUpdateUserInfo['full_name'];
                } else {
                    $updateUserFullName = '';
                }

                // Get Family Member Name and Type
                $familyMemberType  = '';
                $familyMemberLName = '';
                $familyMemberFName = '';
                $familyMemberId    = '';
                foreach ($arrFamilyMembers as $familyMember) {
                    if ($familyMember['id'] == $assignedFormInfo['family_member_id']) {
                        $familyMemberLName = $familyMember['lName'];
                        $familyMemberFName = $familyMember['fName'];
                        $familyMemberType  = $familyMember['value'];
                        $familyMemberId    = $familyMember['id'];
                        break;
                    }
                }

                if (in_array($assignedFormInfo['form_id'], $arrFormIds)) {
                    $versionDate      = date('Y-m-d', strtotime($assignedFormInfo['version_date']));
                    $fileName         = $assignedFormInfo['file_name'] . ' <span style="color:#8E9093;">' . $versionDate . '</span>';
                    $fileNameStripped = $assignedFormInfo['file_name'] . ' ' . $versionDate;
                } else {
                    $fileName         = $assignedFormInfo['file_name'];
                    $fileNameStripped = $assignedFormInfo['file_name'];
                }


                $booLoadRevisions = $assignedFormInfo['use_revision'] == 'Y';
                $arrRevisions     = array();
                if ($booLoadRevisions) {
                    $arrFormRevisions = $this->_forms->getFormRevision()->getAssignedFormRevisions($assignedFormInfo['form_assigned_id']);
                    if (count($arrFormRevisions)) {
                        foreach ($arrFormRevisions as $arrFormRevisionInfo) {
                            $name = sprintf(
                                '#%d: <b>%s</b> by %s',
                                $arrFormRevisionInfo['form_revision_number'],
                                $arrFormRevisionInfo['uploaded_on'],
                                $arrFormRevisionInfo['fName'] . ' ' . $arrFormRevisionInfo['lName']
                            );

                            $arrRevisions[] = array(
                                'id'   => $arrFormRevisionInfo['form_revision_id'],
                                'name' => $name
                            );
                        }
                    }
                }

                // Check if the form version is latest for each assigned pdf form
                $booLatest = true;
                if ($assignedFormInfo['form_type'] != 'bar') {
                    $arrLatestVersionInfo = $this->_forms->getFormVersion()->getLatestFormVersionInfo($assignedFormInfo['form_version_id']);
                    $booLatest            = $arrLatestVersionInfo['form_version_id'] == $assignedFormInfo['form_version_id'];
                }

                $arrFormattedForms[] = array(
                    'locked'                      => $assignedFormInfo['forms_locked'],
                    'client_form_id'              => $assignedFormInfo['form_assigned_id'],
                    'client_form_version_id'      => $assignedFormInfo['form_version_id'],
                    'client_form_version_latest'  => $booLatest,
                    'client_form_format'          => $this->_forms->getFormVersion()->getFormFormat($assignedFormInfo),
                    'client_form_pdf_exists'      => $this->_forms->getFormVersion()->isFormVersionPdf($assignedFormInfo['form_version_id']),
                    'client_form_annotations'     => $booEnabledAnnotations ? '1' : '0',
                    'client_form_help_article_id' => $helpArticleId,
                    'client_form_type'            => $assignedFormInfo['form_type'],
                    'family_member_lname'         => $familyMemberLName,
                    'family_member_fname'         => $familyMemberFName,
                    'family_member_type'          => $familyMemberType,
                    'family_member_alias'         => $assignedFormInfo['assign_alias'],
                    'family_member_id'            => $familyMemberId,
                    'file_name'                   => $fileName,
                    'file_name_stripped'          => $fileNameStripped,
                    'date_assigned'               => $assignedFormInfo['assign_date'],
                    'date_completed'              => $assignedFormInfo['completed_date'],
                    'date_finalized'              => $assignedFormInfo['finalized_date'],
                    'updated_by'                  => $updateUserFullName,
                    'updated_on'                  => $assignedFormInfo['last_update_date'],
                    'file_size'                   => $assignedFormInfo['size'],
                    'use_revision'                => $assignedFormInfo['use_revision'],
                    'arr_revisions'               => $arrRevisions
                );
            }

            $booLocked = $this->isClientFormsLocked($memberId);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'rows'       => $arrFormattedForms,
            'totalCount' => $totalCount,
            'booLocked'  => $booLocked
        );
    }


    /**
     * check assigned officio-form to member
     *
     * @param $memberId
     * @return array
     */
    public function getOfficioFormAssigned($memberId)
    {
        $formSelect = (new Select())
            ->from(['fa' => 'form_assigned'])
            ->columns([])
            ->join(['fv' => 'form_version'], 'fa.form_version_id = fv.form_version_id', ['form_version_id', 'form_id', 'form_type'], Select::JOIN_LEFT)
            ->where([
                'fa.client_member_id' => $memberId,
                'fv.form_type' => 'officio-form'
            ])
            ->group('fa.form_version_id');

        return $this->_db2->fetchAll($formSelect);
    }

    /**
     * Check if forms for specific client are locked
     *
     * @param $memberId
     * @return bool true if locked
     */
    public function isClientFormsLocked($memberId)
    {
        $select = (new Select())
            ->from('clients')
            ->columns(['forms_locked'])
            ->where(['member_id' => (int)$memberId]);

        $booIsLocked = $this->_db2->fetchOne($select);

        return (bool)$booIsLocked;
    }

    /**
     * Update email address for specific member
     *
     * @param $memberId
     */
    public function setPrimaryEmailAsDefaultMail($memberId)
    {
        $select = (new Select())
            ->from('eml_accounts')
            ->columns(['email'])
            ->where(
                [
                    'member_id' => (int)$memberId,
                    'is_default' => 'Y'
                ]
            );

        $defaultEmailAddress = $this->_db2->fetchOne($select);

        if (!empty($defaultEmailAddress)) {
            $this->_db2->update(
                'members',
                ['emailAddress' => $defaultEmailAddress],
                ['member_id' => (int)$memberId]
            );
        }
    }

    /**
     * Generate random password
     * @return string
     */
    public function generatePass()
    {
        // Removing l, 1, 0 and o, since they can be confusing for users
        $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHIJKMNPQRSTUVWXYZ23456789_';

        $regex = $this->_settings->getPasswordRegex();
        do {
            $password = '';
            $passwordLength = mt_rand($this->_settings->passwordMinLength, $this->_settings->passwordMaxLength);
            while (mb_strlen($password) <= $passwordLength) {
                $password .= mb_substr($chars, mt_rand(0, mb_strlen($chars) - 1), 1);
            }
            // We need to be sure that generated password is valid
        } while (!preg_match('/' . $regex . '/', $password));

        return $password;
    }

    /**
     * Get member ID(s) of a client(s) with the particular file number
     *
     * @param string $fileNumber
     * @param int $companyId
     * @param bool $booStrict
     * @param bool $booOneCaseOnly
     * @return int|array|false
     */
    public function getClientIdByFileNumber($fileNumber, $companyId, $booStrict = true, $booOneCaseOnly = true)
    {
        $select = (new Select())
            ->from(array('c' => 'clients'))
            ->columns(['member_id'])
            ->join(array('m' => 'members'), 'm.member_id = c.member_id', [])
            ->where(['m.company_id' => $companyId]);

        if ($booStrict) {
            $select->where->equalTo('c.fileNumber', $fileNumber);
        } else {
            $select->where->like('c.fileNumber', '%' . $fileNumber . '%');
        }

        return $booOneCaseOnly ? $this->_db2->fetchOne($select) : $this->_db2->fetchCol($select);
    }

    /**
     * Automatically assign T/A to the client if there is only one T/A in the company
     *
     * @param $memberId
     * @param $companyId
     */
    public function createClientTAIfCompanyHasOneTA($memberId, $companyId)
    {
        $companyTAs = $this->getAccounting()->getCompanyTA($companyId, true);
        if (count($companyTAs) == 1) {
            $this->getAccounting()->assignMemberTA($memberId, $companyTAs[0]);
        }
    }

    public function exportToExcel($arrData)
    {
        // Turn off warnings - issue when generate xls file
        error_reporting(E_ERROR);

        set_time_limit(5 * 60); // 5 minutes, no more
        ini_set('memory_limit', '512M');

        try {
            $objPHPExcel = new Spreadsheet();

            $styleArray = array(
                'font' => array(
                    'bold' => true,
                    'color' => array('rgb' => '0000FF'),
                    'size' => 16
                )
            );

            $dateFormatFull = $this->_settings->variable_get('dateFormatFull');

            $i = 0;
            foreach ($arrData as $sheetData) {
                $title = $this->_files::checkPhpExcelFileName($sheetData['title']);
                $title = (empty($title) ? 'Export Result' : $title);

                if ($i == 0) {
                    $objPHPExcel->setActiveSheetIndex(0);
                    $sheet = $objPHPExcel->getActiveSheet()->setTitle($title);
                } else {
                    $sheet = $objPHPExcel->createSheet($i);
                    $objPHPExcel->setActiveSheetIndex($i);
                    $sheet->setTitle($title);
                }
                $i++;

                // Set columns width
                foreach ($sheetData['columns'] as $col => $arrColumnInfo) {
                    $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
                }

                $row = 1;

                // Show main title
                $sheet->setCellValueByColumnAndRow(3, $row, $title);
                $strRow = $sheet->getCellByColumnAndRow(1, $row)->getCoordinate() . ':' .
                    $sheet->getCellByColumnAndRow(3, $row)->getCoordinate();
                $sheet->getStyle($strRow)->applyFromArray($styleArray);
                $sheet->getStyle($strRow)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $row++;

                // Table Headers
                foreach ($sheetData['columns'] as $col => $arrColumnInfo) {
                    $sheet->setCellValueByColumnAndRow($col + 1, $row, $arrColumnInfo['name']);
                }
                $strRow = $sheet->getCellByColumnAndRow(1, $row)->getCoordinate() . ':' .
                    $sheet->getCellByColumnAndRow(count($sheetData['columns']), $row)->getCoordinate();

                $sheet->getStyle($strRow)
                    ->getFont()
                    ->setBold(true);

                $sheet->getStyle($strRow)
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $row++;

                // Data
                $arrDateCells = array();
                foreach ($sheetData['values'] as $arrRow) {
                    foreach ($sheetData['columns'] as $col => $arrColumnInfo) {
                        $val = !empty($arrRow[$arrColumnInfo['id']]) ? $arrRow[$arrColumnInfo['id']] : '';

                        // Remember date cells to apply date format for them
                        if (!empty($val) && Settings::isValidDateFormat($val, $dateFormatFull) && strtotime($val)) {
                            $d = DateTime::createFromFormat($dateFormatFull, $val);
                            if ($d && $d->format($dateFormatFull) === $val) {
                                $arrDateCells[$col + 1] = array($col + 1, $row);

                                // @NOTE: use GMT to be sure timezone is correct...
                                $val = Date::PHPToExcel(strtotime($val . ' GMT'));
                            }
                        }
                        $sheet->setCellValueByColumnAndRow($col + 1, $row, $val);
                    }

                    // Use text format for all cells
                    $strRow = $sheet->getCellByColumnAndRow(1, $row)->getCoordinate() . ':' .
                        $sheet->getCellByColumnAndRow(count($sheetData['columns']), $row)->getCoordinate();
                    $sheet->getStyle($strRow)
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_TEXT);

                    $row++;
                }

                // Set date format for date cells
                $excelDateFormat = Settings::getExcelDateFormatFromPhpDateFormat($dateFormatFull);
                foreach ($arrDateCells as $arrCells) {
                    $sheet->getStyleByColumnAndRow($arrCells[0], $arrCells[1])
                        ->getNumberFormat()
                        ->setFormatCode($excelDateFormat);
                }
            }

            return $objPHPExcel;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return false;
    }

    public function generateRowId()
    {
        $rowIdLength = 32;
        $rowId = '';
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

        while (mb_strlen($rowId) <= $rowIdLength) {
            $rowId .= mb_substr($chars, mt_rand(0, mb_strlen($chars) - 1), 1);
        }

        return $rowId;
    }

    /**
     * Create client + case
     *
     * @param array $arrParams
     * @param $arrReceivedFiles
     * @return array
     * bool success true on success, false on error
     * string message with error details
     * int case id
     * ...
     */
    public function createClientAndCaseAtOnce($arrParams, $arrReceivedFiles)
    {
        $applicantId                          = 0;
        $applicantName                        = '';
        $employerId                           = 0;
        $employerClientId                     = 0;
        $employerName                         = '';
        $officeFieldName                      = '';
        $parentMemberType                     = '';
        $applicantUpdatedOn                   = '';
        $applicantUpdatedOnTime               = '';
        $arrApplicantNewOffices               = array();
        $arrApplicantOfficeFieldIdsWithGroups = array();
        $arrAllFieldsChangesData              = array();
        $caseId                               = 0;
        $booCreateCase                        = false;
        $booEmptyCaseCreated                  = false;
        $caseTemplateId                       = 0;
        $caseCategory                         = 0;
        $employerCaseLinkedCaseTypeId         = 0;
        $caseName                             = '';
        $changeOfficeFieldToUpdate            = array();
        $fileFieldsToUpdate                   = array();
        $imageFieldsToUpdate                  = array();
        $arrErrorFields                       = array();
        $arrRowIds                            = array();
        $booShowWelcomeMessage                = false;
        $booAllowEditApplicant                = false;
        $generatedUsername                    = '';
        $strError                             = '';
        $strErrorType                         = 'error';
        $booTransactionStarted                = false;
        $caseReferenceNumber                  = '';
        $arrDependents                        = array();
        $arrCreatedFilesDependentIds          = array();
        $arrDependentsIdsFromGui              = array();
        $investmentTypeNewValue               = '';
        $investmentTypeOldValue               = '';

        try {
            /** @var AuthHelper $oAuth */
            $oAuth = $this->_serviceContainer->get(AuthHelper::class);
            /** @var Users $oUsers */
            $oUsers            = $this->_serviceContainer->get(Users::class);
            $oCompanyDivisions = $this->_company->getCompanyDivisions();
            $oPurifier         = $this->_settings->getHTMLPurifier(false);

            $companyId           = $this->_auth->getCurrentUserCompanyId();
            $divisionGroupId     = $this->_auth->getCurrentUserDivisionGroupId();
            $currentMemberId     = $this->_auth->getCurrentUserId();
            $arrCompanyDivisions = $this->_company->getDivisions($companyId, $divisionGroupId, false, true);
            $arrOffices          = $this->getDivisions(true);

            $applicantId        = isset($arrParams['applicantId']) ? (int)$arrParams['applicantId'] : 0;
            $booCreateApplicant = empty($applicantId);

            $dateFormatFull = $this->_settings->variable_get('dateFormatFull');

            $booEmailWasBlank      = false;
            $password              = '';
            $booEmptyEmail         = $booEmptyLogin = $booEmptyPass = true;
            $booAssignedCasesExist = true;

            $startCaseNumberFrom = '';
            $booUpdateCaseNumberSettings = false;
            $arrCompanySettings = $this->getCaseNumber()->getCompanyCaseNumberSettings($companyId);

            $booApi = array_key_exists('booApi', $arrParams) ? false : true;

            $oDependentFieldsConfig        = $this->_config['site_version']['dependants']['fields'];
            $neverMarriedLabel             = $this->_config['site_version']['clients']['never_married_label'];
            $arrUserAllowedDependentFields = $this->getFields()->getDependantFields(false);

            if (empty($strError) && !empty($applicantId) && (!$this->hasCurrentMemberAccessToMember($applicantId) || !$oCompanyDivisions->canCurrentMemberEditClient($applicantId))) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $parentMemberType = $memberType = $arrParams['memberType'] ?? 0;
            $memberTypeId     = $this->getMemberTypeIdByName($memberType);
            if (empty($strError) && empty($memberTypeId)) {
                $strError = $this->_tr->translate('Incorrect incoming params.');
            }

            $applicantType = isset($arrParams['applicantType']) ? (int)$arrParams['applicantType'] : 0;
            if (empty($strError) && $memberType == 'contact' && $booCreateApplicant) {
                $arrApplicantTypeIds = $this->getApplicantTypes()->getTypes($companyId, true, $memberTypeId);
                if (!in_array($applicantType, $arrApplicantTypeIds)) {
                    $strError = $this->_tr->translate('Incorrectly selected type.');
                }
            }

            $employerId = isset($arrParams['caseEmployerId']) ? (int)$arrParams['caseEmployerId'] : 0;
            if (empty($strError) && !empty($employerId) && !$this->hasCurrentMemberAccessToMember($employerId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            // Don't allow creating more clients than it is allowed
            $companyInfo = $this->_company->getCompanyDetailsInfo($companyId);
            if (empty($strError) && $booCreateApplicant && $memberType != 'contact' && $companyInfo['free_clients'] > 0) {
                $clientsCount = $this->_company->getCompanyClientsCount($companyId);
                if ($companyInfo['free_clients'] <= $clientsCount) {
                    $strError = 'max_clients_count_reached';
                }
            }

            $caseIdLinkedTo = isset($arrParams['caseIdLinkedTo']) ? (int)$arrParams['caseIdLinkedTo'] : 0;

            $arrApplicantFields = $arrCaseFields = $arrDependentsDataGrouped = array();
            $filter = new StripTags();
            if (empty($strError)) {
                foreach ($arrParams as $paramName => $paramValue) {
                    if (preg_match('/^field_case_([\d]+)_([\d]+)$/i', $paramName, $regs)) {
                        $arrCaseFields[$regs[2]] = $paramValue;
                    } elseif (preg_match('/^field_case_dependants_([\w]+)$/', $paramName, $regs)) {
                        if (is_array($paramValue)) {
                            foreach ($paramValue as &$paramValueData) {
                                $paramValueData = trim($filter->filter($paramValueData));
                            }
                            unset($paramValueData);
                        } else {
                            $paramValue = trim($filter->filter($paramValue));
                        }

                        foreach ($paramValue as $row => $strValue) {
                            $arrDependentsDataGrouped[$row][$regs[1]] = $strValue;
                        }
                    } elseif (preg_match('/^field_' . $memberType . '_(\d+)_(\d+)$/i', $paramName, $regs)) {
                        $arrApplicantFields[$regs[1]][$regs[2]] = is_array($paramValue) ? $paramValue : array($paramValue);
                    }
                }

                $arrDependentFieldsToReset = array('address', 'city', 'country', 'region', 'postal_code');
                if (!empty($arrDependentsDataGrouped)) {
                    foreach ($arrDependentsDataGrouped as $key => $arrDependentInfo) {
                        if (!empty($arrDependentInfo['dependent_id'])) {
                            $arrDependentsIdsFromGui[] = $arrDependentInfo['dependent_id'];
                        }


                        if (!empty($oDependentFieldsConfig['main_applicant_address_is_the_same']['show'])) {
                            if (isset($arrDependentInfo['main_applicant_address_is_the_same']) && $arrDependentInfo['main_applicant_address_is_the_same'] === 'Y') {
                                $arrDependentsDataGrouped[$key]['main_applicant_address_is_the_same'] = 'Y';

                                // Reset specific fields, if they were shown
                                foreach ($arrDependentFieldsToReset as $fieldIdToReset) {
                                    if (!empty($oDependentFieldsConfig[$fieldIdToReset]['show'])) {
                                        $arrDependentsDataGrouped[$key][$fieldIdToReset] = '';
                                    }
                                }
                            } else {
                                $arrDependentsDataGrouped[$key]['main_applicant_address_is_the_same'] = 'N';
                            }
                        }

                        if (!empty($oDependentFieldsConfig['include_in_minute_checkbox']['show'])) {
                            $booCheckIncomingData = false;
                            if (!empty($oDependentFieldsConfig['include_in_minute_checkbox']['show_for_non_agent_only'])) {
                                if ($this->_auth->isCurrentUserAuthorizedAgent()) {
                                    if (empty($arrDependentInfo['dependent_id'])) {
                                        $booCheckByDefault = false;
                                        foreach ($arrUserAllowedDependentFields as $arrDependentField) {
                                            if ($arrDependentField['field_id'] == 'include_in_minute_checkbox') {
                                                $booCheckByDefault = $arrDependentField['field_default_value'];
                                                break;
                                            }
                                        }

                                        // For the new dependant - we want to check/uncheck the checkbox - based on the default settings
                                        $arrDependentsDataGrouped[$key]['include_in_minute_checkbox'] = $booCheckByDefault ? 'Y' : 'N';
                                    } else {
                                        // Don't allow to change the checkbox
                                        unset($arrDependentsDataGrouped[$key]['include_in_minute_checkbox']);
                                    }
                                } else {
                                    $booCheckIncomingData = true;
                                }
                            } else {
                                $booCheckIncomingData = true;
                            }

                            if ($booCheckIncomingData) {
                                // A regular check
                                if (isset($arrDependentInfo['include_in_minute_checkbox']) && $arrDependentInfo['include_in_minute_checkbox'] === 'Y') {
                                    $arrDependentsDataGrouped[$key]['include_in_minute_checkbox'] = 'Y';
                                } else {
                                    $arrDependentsDataGrouped[$key]['include_in_minute_checkbox'] = 'N';
                                }
                            }
                        }

                        if (!empty($oDependentFieldsConfig['spouse_name']['show'])) {
                            // Don't reset the "Name of Spouse" field if field is visible AND
                            // if the Marital status is Married, AND the dependent is NOT Partner/Spouse
                            if (isset($arrDependentInfo['relationship']) && $arrDependentInfo['relationship'] !== 'spouse' && isset($arrDependentInfo['marital_status']) && $arrDependentInfo['marital_status'] === 'married') {
                                // Do nothing, save it
                            } else {
                                // Otherwise - reset
                                $arrDependentsDataGrouped[$key]['spouse_name'] = null;
                            }
                        }
                    }
                }

                foreach ($arrReceivedFiles as $fileId => $arrFiles) {
                    $countFiles = is_array($arrFiles['name']) ? count($arrFiles['name']) : 1;
                    for ($i = 0; $i < $countFiles; $i++) {
                        $arrFiles['file_id'][$i] = $fileId;
                    }

                    if (preg_match('/^field_case_([\d]+)_([\d]+)$/i', $fileId, $regs)) {
                        $arrCaseFields[$regs[2]] = $arrFiles;
                    } elseif (preg_match('/^field_file_' . $memberType . '_([\d]+)_([\d]+)$/i', $fileId, $regs)) {
                        $arrApplicantFields[$regs[1]][$regs[2]] = $arrFiles;
                    }
                }
            }

            // Load applicant info
            $arrApplicantInfo = array();
            $arrMemberInfo = array();
            if (empty($strError) && !empty($applicantId)) {
                $arrMemberInfo = $this->getMemberInfo($applicantId);
                $parentMemberType = $this->getMemberTypeNameById($arrMemberInfo['userType']);
                $arrApplicantInfo = $this->getClientsInfo(array($applicantId), false, $arrMemberInfo['userType']);

                if (is_array($arrApplicantInfo[0])) {
                    if (array_key_exists('emailAddress', $arrApplicantInfo[0]) && empty($arrApplicantInfo[0]['emailAddress'])) {
                        $booEmailWasBlank = true;
                    }

                    if (array_key_exists('username', $arrApplicantInfo[0]) && !empty($arrApplicantInfo[0]['username'])) {
                        $booEmptyLogin = false;
                    }

                    if (array_key_exists('password', $arrApplicantInfo[0]) && !empty($arrApplicantInfo[0]['password'])) {
                        $booEmptyPass = false;
                    }
                }
            }

            // Check if profile was updated before
            $lastUpdateTime    = isset($arrParams['applicantUpdatedOn']) ? $filter->filter($arrParams['applicantUpdatedOn']) : 0;
            $booForceOverwrite = isset($arrParams['forceOverwrite']) ? (int)$filter->filter($arrParams['forceOverwrite']) : 0;
            if (empty($strError) && !empty($applicantId) && $memberType != 'case') {
                if ((!empty($arrApplicantInfo) && !empty($arrApplicantInfo[0]['modified_on']) && $arrApplicantInfo[0]['modified_on'] != $lastUpdateTime) && !$booForceOverwrite) {
                    // Client Profile was updated - so we need ask a user if we can overwrite data
                    $strError = 'last_update_time_different';
                }
            }

            // Will be used during xfdf data sync
            $arrApplicantInfoToSave = array();
            $booLocal               = $this->_auth->isCurrentUserCompanyStorageLocal();
            $companyId              = $this->_auth->getCurrentUserCompanyId();

            // Check incoming data
            $arrAllApplicantFields = array();
            $arrFieldsOptions      = array();
            if (empty($strError) && $memberType != 'case') {
                if (!$booCreateApplicant) {
                    $applicantType = $arrApplicantInfo[0]['applicant_type_id'];
                }

                $arrAllApplicantFields = $this->getApplicantFields()->getGroupedCompanyFields($companyId, $memberTypeId, $applicantType, true, $booCreateApplicant);

                // Get ids of these fields, load options list for them
                $arrFieldIds = array();
                foreach ($arrAllApplicantFields as $arrGroupInfo) {
                    if (isset($arrGroupInfo['fields'])) {
                        foreach ($arrGroupInfo['fields'] as $arrFieldInfo) {
                            $arrFieldIds[] = $arrFieldInfo['field_id'];
                        }
                    }
                }
                $arrFieldIds = array_unique($arrFieldIds);
                $arrFieldsOptions = $this->getApplicantFields()->getGroupedFieldsOptions($arrFieldIds, true);

                // 1. Check if all required fields were filled

                list($arrAllApplicantFieldsData,) = $this->getAllApplicantFieldsData($applicantId, $memberTypeId);

                $arrErrors = array();

                foreach ($arrAllApplicantFields as $arrBlockInfo) {
                    if (isset($arrBlockInfo['fields']) && is_array($arrBlockInfo['fields'])) {
                        foreach ($arrBlockInfo['fields'] as $arrFieldInfo) {
                            if ($arrFieldInfo['field_access'] != 'F' || $arrFieldInfo['field_type'] == 'office_change_date_time') {
                                if ($booCreateApplicant && in_array($arrFieldInfo['field_type'], array('office', 'office_multi'))) {
                                    // Don't set empty for the office - should go to the "if" section below
                                } else {
                                    $arrApplicantFields[$arrBlockInfo['group_id']][$arrFieldInfo['field_id']] = array();
                                }

                                if (!$booCreateApplicant) {
                                    foreach ($arrAllApplicantFieldsData as $key => $arrValues) {
                                        preg_match('/.*_([\d]*)_([\d]*)/', $key, $arrMatches);
                                        if (!empty($arrMatches) && isset($arrMatches[1]) && isset($arrMatches[2])) {
                                            if ($arrMatches[1] == $arrBlockInfo['group_id'] && $arrMatches[2] == $arrFieldInfo['field_id']) {
                                                foreach ($arrValues as $value) {
                                                    if ($arrFieldInfo['field_type'] == 'date' || $arrFieldInfo['field_type'] == 'date_repeatable') {
                                                        $value = $this->_settings->reformatDate($value, 'Y-m-d', $dateFormatFull);
                                                    }
                                                    $arrApplicantFields[$arrBlockInfo['group_id']][$arrFieldInfo['field_id']][] = $value;
                                                }
                                                break;
                                            }
                                        }
                                    }
                                }
                            }

                            if (!in_array($arrFieldInfo['field_type'], array('html_editor', 'photo', 'file'))) {
                                if (isset($arrApplicantFields[$arrBlockInfo['group_id']][$arrFieldInfo['field_id']])) {
                                    if (is_array($arrApplicantFields[$arrBlockInfo['group_id']][$arrFieldInfo['field_id']])) {
                                        foreach ($arrApplicantFields[$arrBlockInfo['group_id']][$arrFieldInfo['field_id']] as &$paramValueData1) {
                                            $paramValueData1 = trim($filter->filter($paramValueData1));
                                        }
                                        unset($paramValueData1);
                                    } else {
                                        $arrApplicantFields[$arrBlockInfo['group_id']][$arrFieldInfo['field_id']] = array(trim($filter->filter($arrApplicantFields[$arrBlockInfo['group_id']][$arrFieldInfo['field_id']] ?? '')));
                                    }
                                } else {
                                    switch ($arrFieldInfo['field_type']) {
                                        case 'office':
                                        case 'office_multi':
                                            if ($arrFieldInfo['field_required'] == 'Y') {
                                                // Automatically create applicant with access/assigned to all offices current user has access to
                                                $arrApplicantFields[$arrBlockInfo['group_id']][$arrFieldInfo['field_id']] = $arrFieldInfo['field_type'] == 'office_multi' ? array(implode(',', $arrOffices)) : array($arrOffices[0]);
                                            }
                                            break;

                                        case 'checkbox':
                                            // If checkbox value was not received - checkbox wasn't checked, set it empty
                                            $arrApplicantFields[$arrBlockInfo['group_id']][$arrFieldInfo['field_id']] = array('');
                                            break;

                                        default:
                                            break;
                                    }
                                }
                            } else {
                                if ($arrFieldInfo['field_type'] == 'html_editor') {
                                    if (is_array($arrApplicantFields[$arrBlockInfo['group_id']][$arrFieldInfo['field_id']])) {
                                        foreach ($arrApplicantFields[$arrBlockInfo['group_id']][$arrFieldInfo['field_id']] as $key => $value) {
                                            $arrApplicantFields[$arrBlockInfo['group_id']][$arrFieldInfo['field_id']][$key] = $oPurifier->purify($value);
                                        }
                                    } else {
                                        $arrApplicantFields[$arrBlockInfo['group_id']][$arrFieldInfo['field_id']] = array($oPurifier->purify($arrApplicantFields[$arrBlockInfo['group_id']][$arrFieldInfo['field_id']]));
                                    }
                                }
                            }

                            $val = array_key_exists($arrBlockInfo['group_id'], $arrApplicantFields) && array_key_exists(
                                $arrFieldInfo['field_id'],
                                $arrApplicantFields[$arrBlockInfo['group_id']]
                            ) ? $arrApplicantFields[$arrBlockInfo['group_id']][$arrFieldInfo['field_id']] : '';
                            if ($arrFieldInfo['field_required'] == 'Y') {
                                $booCorrectData = true;
                                if (is_array($val)) {
                                    foreach ($val as $valData) {
                                        if ($valData == '') {
                                            $booCorrectData = false;
                                            break;
                                        }
                                    }
                                } elseif ($val == '') {
                                    $booCorrectData = false;
                                }

                                if (!$booCorrectData && $arrBlockInfo['group_repeatable'] == 'N') {
                                    $arrErrors[] = 'Please provide value for "' . $arrFieldInfo['field_name'] . '" field';
                                }
                            }
                        }
                    }
                }

                if (count($arrErrors)) {
                    $prefix = count($arrErrors) > 1 ? '*' : '';
                    foreach ($arrErrors as $strErrorInfo) {
                        $strError .= $prefix . ' ' . $strErrorInfo . '</br>';
                    }
                }

                // 2. Check if entered data is correct
                if (empty($strError)) {
                    $emailValidator = new EmailAddress();

                    $arrAgentIds = $this->getAgents(true);

                    $arrAssignedTo = $oUsers->getAssignList('search', null, true);
                    $arrAssignedToIds = array();
                    foreach ($arrAssignedTo as $arrAssignedToInfo) {
                        $arrAssignedToIds[] = $arrAssignedToInfo['assign_to_id'];
                    }


                    $arrRMAAssignedTo = $oUsers->getAssignedToUsers(true, null, 0, true);
                    $arrRMAIds = array();
                    foreach ($arrRMAAssignedTo as $arrRMAAssignedToInfo) {
                        $arrRMAIds[] = $arrRMAAssignedToInfo['option_id'];
                    }

                    $arrActiveUsers = $oUsers->getAssignedToUsers(false, null, 0, true);
                    $arrActiveUsersIds = array();
                    foreach ($arrActiveUsers as $arrActiveUserInfo) {
                        $arrActiveUsersIds[] = $arrActiveUserInfo['option_id'];
                    }

                    $arrErrors = array();
                    foreach ($arrAllApplicantFields as $arrBlockInfo) {
                        if (array_key_exists('fields', $arrBlockInfo) && is_array($arrBlockInfo['fields'])) {
                            foreach ($arrBlockInfo['fields'] as $arrFieldInfo) {
                                $fieldId = $arrFieldInfo['field_id'];
                                $arrFieldVal = array_key_exists($arrBlockInfo['group_id'], $arrApplicantFields) && array_key_exists(
                                    $fieldId,
                                    $arrApplicantFields[$arrBlockInfo['group_id']]
                                ) ? $arrApplicantFields[$arrBlockInfo['group_id']][$fieldId] : '';
                                $arrFieldVal = (array)$arrFieldVal;

                                foreach ($arrFieldVal as $fieldKey => $fieldVal) {
                                    $readableFieldValue = $fieldVal;

                                    // Required fields were checked above
                                    if ($fieldVal != '') {
                                        switch ($arrFieldInfo['field_type']) {
                                            case 'agents':
                                                if (!in_array($fieldVal, $arrAgentIds)) {
                                                    $arrErrors[] = $this->_tr->translate('Please select an agent correctly.');
                                                }
                                                break;

                                            case 'office':
                                                $officeFieldName = $arrFieldInfo['field_name'];
                                                if (!in_array($fieldVal, $arrOffices)) {
                                                    $arrErrors[] = sprintf($this->_tr->translate('Please select value for %s field correctly.'), $arrFieldInfo['field_name']);
                                                }
                                                break;

                                            case 'office_multi':
                                                $officeFieldName = $arrFieldInfo['field_name'];
                                                $arrFieldValues = explode(',', $fieldVal);
                                                foreach ($arrFieldValues as $officeId) {
                                                    if (!in_array($officeId, $arrOffices)) {
                                                        $arrErrors[] = sprintf($this->_tr->translate('Please select value for %s field correctly.'), $arrFieldInfo['field_name']);
                                                        break;
                                                    }
                                                }

                                                if (empty($arrErrors) && !$booCreateApplicant) {
                                                    $arrClientDivisionsFromOtherGroup = array();
                                                    $arrAllClientDivisions = $this->getApplicantOffices(array($applicantId));
                                                    if ($arrMemberInfo['division_group_id'] != $divisionGroupId) {
                                                        $arrClientDivisionsFromOtherGroup = $this->getApplicantOffices(array($applicantId), $arrMemberInfo['division_group_id']);
                                                    } else {
                                                        $arrClientDivisionsFromCurrentGroup = $this->getApplicantOffices(array($applicantId), $divisionGroupId);
                                                        foreach ($arrAllClientDivisions as $divisionId) {
                                                            if (!in_array($divisionId, $arrClientDivisionsFromCurrentGroup)) {
                                                                $arrClientDivisionsFromOtherGroup[] = $divisionId;
                                                            }
                                                        }
                                                    }
                                                    $arrFieldValues = array_merge($arrFieldValues, $arrClientDivisionsFromOtherGroup);

                                                    // Search for permanent offices which were already assigned
                                                    if (!empty($arrAllClientDivisions)) {
                                                        $arrDivisionsInfo = $oCompanyDivisions->getDivisionsByIds($arrAllClientDivisions);
                                                        foreach ($arrDivisionsInfo as $arrDivisionInfo) {
                                                            if ($arrDivisionInfo['access_permanent'] == 'Y' && !in_array($arrDivisionInfo['division_id'], $arrFieldValues)) {
                                                                $arrErrors[] = sprintf(
                                                                    $this->_tr->translate('You cannot unassign <i>%s</i> for <i>%s</i> field because it is marked as permanent.'),
                                                                    $arrDivisionInfo['name'],
                                                                    $arrFieldInfo['field_name']
                                                                );
                                                            }
                                                        }
                                                    }

                                                    $arrApplicantFields[$arrBlockInfo['group_id']][$fieldId][$fieldKey] = implode(',', $arrFieldValues);
                                                }
                                                break;

                                            case 'assigned_to':
                                            case 'staff_responsible_rma':
                                            case 'active_users':

                                                foreach ($arrAllApplicantFieldsData as $key => $arrValues) {
                                                    preg_match('/.*_([\d]*)_([\d]*)/', $key, $arrMatches);
                                                    if (!empty($arrMatches) && isset($arrMatches[1]) && isset($arrMatches[2])) {
                                                        if ($arrMatches[1] == $arrBlockInfo['group_id'] && $arrMatches[2] == $arrFieldInfo['field_id']) {
                                                            foreach ($arrValues as $value) {
                                                                if ($arrFieldInfo['field_type'] == 'assigned_to') {
                                                                    $arrAssignedToIds[] = $value;
                                                                } else {
                                                                    if ($arrFieldInfo['field_type'] == 'staff_responsible_rma') {
                                                                        $arrRMAIds = $value;
                                                                    } else {
                                                                        $arrActiveUsersIds = $value;
                                                                    }
                                                                }
                                                            }
                                                            break;
                                                        }
                                                    }
                                                }

                                                if ($arrFieldInfo['field_type'] == 'assigned_to' && !in_array($fieldVal, $arrAssignedToIds)) {
                                                    $arrErrors[] = sprintf($this->_tr->translate('Please select value for %s field correctly.'), $arrFieldInfo['field_name']);
                                                }

                                                if ($arrFieldInfo['field_type'] == 'staff_responsible_rma' && !in_array($fieldVal, $arrRMAIds)) {
                                                    $arrErrors[] = sprintf($this->_tr->translate('Please select value for %s field correctly.'), $arrFieldInfo['field_name']);
                                                }

                                                if ($arrFieldInfo['field_type'] == 'active_users' && !in_array($fieldVal, $arrActiveUsersIds)) {
                                                    $arrErrors[] = sprintf($this->_tr->translate('Please select value for %s field correctly.'), $arrFieldInfo['field_name']);
                                                }
                                                break;

                                            case 'combo':
                                                $booCorrect = false;
                                                $booHasOther = false;
                                                if (array_key_exists($fieldId, $arrFieldsOptions) && count($arrFieldsOptions[$fieldId])) {
                                                    foreach ($arrFieldsOptions[$fieldId] as $arrOptionInfo) {
                                                        if ($arrOptionInfo['option_name'] == 'Other') {
                                                            $booHasOther = true;
                                                        }

                                                        if ($arrOptionInfo['option_id'] == $fieldVal) {
                                                            $booCorrect = true;
                                                            $readableFieldValue = $arrOptionInfo['option_name'];
                                                        }
                                                    }
                                                }

                                                if (!$booCorrect && !$booHasOther) {
                                                    $arrErrors[] = sprintf($this->_tr->translate('Please select value for %s field correctly.'), $arrFieldInfo['field_name']);
                                                }

                                                if ($this->_config['site_version']['validation']['check_marital_status'] && $arrFieldInfo['field_unique_id'] == 'relationship_status') {
                                                    if ($this->getApplicantFields()->getDefaultFieldOptionValue($fieldVal) == $neverMarriedLabel) {
                                                        $arrCaseIds = $this->getAssignedCases($applicantId);
                                                        if (!empty($arrCaseIds)) {
                                                            $arrDependents = $this->getFields()->getDependents($arrCaseIds, false);

                                                            if (!empty($arrDependents)) {
                                                                foreach ($arrDependents as $arrDependentInfo) {
                                                                    if ($arrDependentInfo['relationship'] == 'spouse') {
                                                                        $arrErrors[] = sprintf(
                                                                            $this->_tr->translate('You can\'t select this option for %s field. There is Partner in the Dependents section in the Case Details tab.'),
                                                                            $arrFieldInfo['field_name']
                                                                        );
                                                                        break;
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }

                                                break;

                                            case 'multiple_combo':
                                                $booCorrect = false;
                                                $arrFieldValues = explode(',', $fieldVal);
                                                if (array_key_exists($fieldId, $arrFieldsOptions) && count($arrFieldsOptions[$fieldId])) {
                                                    $arrReadableValues = array();
                                                    foreach ($arrFieldValues as $fieldValue) {
                                                        $booCorrect = false;

                                                        foreach ($arrFieldsOptions[$fieldId] as $arrOptionInfo) {
                                                            if ($arrOptionInfo['option_id'] == $fieldValue) {
                                                                $booCorrect = true;
                                                                $arrReadableValues[] = $arrOptionInfo['option_name'];
                                                            }
                                                        }
                                                        if (!$booCorrect) {
                                                            $arrErrors[] = sprintf($this->_tr->translate('Please select values for %s field correctly.'), $arrFieldInfo['field_name']);
                                                            break 2;
                                                        }
                                                    }
                                                    $readableFieldValue = implode(', ', $arrReadableValues);
                                                }
                                                if (!$booCorrect) {
                                                    $arrErrors[] = sprintf($this->_tr->translate('Please select values for %s field correctly.'), $arrFieldInfo['field_name']);
                                                }

                                                break;

                                            case 'email':
                                                if (!$emailValidator->isValid($fieldVal)) {
                                                    $arrErrors[] = sprintf($this->_tr->translate('Please enter correct email address for %s field.'), $arrFieldInfo['field_name']);
                                                } else {
                                                    if ($arrFieldInfo['field_unique_id'] == 'email') {
                                                        $booEmptyEmail = false;
                                                    }
                                                }
                                                break;

                                            case 'hyperlink':
                                                if (!filter_var($fieldVal, FILTER_VALIDATE_URL)) {
                                                    $arrErrors[] = sprintf($this->_tr->translate('Please enter correct link for %s field.'), $arrFieldInfo['field_name']);
                                                }
                                                break;

                                            case 'date':
                                            case 'date_repeatable':
                                                if (!empty($fieldVal)) {
                                                    if (Settings::isValidDateFormat($fieldVal, $dateFormatFull)) {
                                                        $readableFieldValue = $this->_settings->reformatDate($fieldVal, $dateFormatFull, Settings::DATE_XFDF);
                                                        if ($arrFieldInfo['field_unique_id'] == 'DOB') {
                                                            if ($this->_config['site_version']['validation']['check_children_age']) {
                                                                $arrCaseIds = $this->getAssignedCases($applicantId);
                                                                if (!empty($arrCaseIds)) {
                                                                    $arrDependents = $this->getFields()->getDependents($arrCaseIds);

                                                                    if (!empty($arrDependents)) {
                                                                        foreach ($arrDependents as $key => $arrRelationDependents) {
                                                                            if ($key == 'child') {
                                                                                foreach ($arrRelationDependents as $arrDependentInfo) {
                                                                                    foreach ($arrDependentInfo as $dependentFieldId => $dependentFieldValue) {
                                                                                        if ($dependentFieldId == 'DOB' &&
                                                                                            !empty($dependentFieldValue) && strtotime($fieldVal) >= strtotime($dependentFieldValue)) {
                                                                                            $arrErrors[] = $this->_tr->translate('Child\'s date of birth is before main applicant\'s date of birth');
                                                                                        }
                                                                                    }
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                            if ($this->_config['site_version']['validation']['check_date_of_birthday'] && strtotime($fieldVal) > time()) {
                                                                $arrErrors[] = $this->_tr->translate('Date of birth cannot be in the future');
                                                            }
                                                        }
                                                    } else {
                                                        $arrErrors[] = sprintf($this->_tr->translate('Please enter correct date for %s field.'), $arrFieldInfo['field_name']);
                                                    }
                                                }
                                                break;

                                            case 'password' :
                                                if ($booCreateApplicant && $oAuth->isPasswordValid($fieldVal, $arrErrors, null, $applicantId)) {
                                                    $password     = $fieldVal;
                                                    $booEmptyPass = false;
                                                }
                                                break;


                                            // 'text','number',''phone','memo', 'photo'
                                            default:
                                                // If username was provided - check if it wasn't used before
                                                if ($arrFieldInfo['field_unique_id'] == 'username' && $booCreateApplicant) {
                                                    if (!Fields::validUserName($fieldVal)) {
                                                        $arrErrors[] = $this->_tr->translate('Incorrect characters in username');
                                                    } elseif ($this->isUsernameAlreadyUsed($fieldVal)) {
                                                        $arrErrors[] = $this->_tr->translate('This username is already used, please choose another.');
                                                    } else {
                                                        $booEmptyLogin = false;
                                                    }
                                                }
                                                break;
                                        }
                                    }
                                    if ($arrBlockInfo['group_repeatable'] == 'N') {
                                        $fieldName = $arrFieldInfo['field_unique_id'];
                                        if ($this->getFields()->isStaticField($arrFieldInfo['field_unique_id'])) {
                                            $fieldName = $this->getFields()->getStaticColumnName($arrFieldInfo['field_unique_id']);
                                        }
                                        $arrApplicantInfoToSave[$fieldName] = $readableFieldValue;
                                    }
                                }

                                if ($arrFieldInfo['field_type'] == 'multiple_text_fields' || ($arrFieldInfo['field_type'] == 'reference' && $arrFieldInfo['field_multiple_values'])) {
                                    if (count($arrFieldVal) && !empty($arrFieldVal[0])) {
                                        $arrApplicantFields[$arrBlockInfo['group_id']][$fieldId] = array(Json::encode($arrFieldVal));
                                    } else {
                                        $arrApplicantFields[$arrBlockInfo['group_id']][$fieldId] = $arrFieldVal;
                                    }
                                }
                            }
                        }
                    }

                    if (count($arrErrors)) {
                        $prefix = count($arrErrors) > 1 ? '*' : '';
                        foreach ($arrErrors as $strErrorInfo) {
                            $strError .= $prefix . ' ' . $strErrorInfo . '</br>';
                        }
                    }

                    if (empty($strError) && !$booCreateApplicant && $booEmailWasBlank && !$booEmptyEmail && $booEmptyLogin && $booEmptyPass && $memberType != 'contact') {
                        // if we edited client AND filled email AND login/pass were both empty => generate login/pass
                        $generatedUsername = $arrApplicantInfoToSave['emailAddress'];
                        $counter = 1;
                        while ($this->isUsernameAlreadyUsed($generatedUsername)) {
                            $generatedUsername = $arrApplicantInfoToSave['emailAddress'] . '_' . $counter++;
                        }

                        $password                           = $this->generatePass();
                        $arrApplicantInfoToSave['username'] = $generatedUsername;
                        $arrApplicantInfoToSave['password'] = $this->_encryption->hashPassword($password);

                        $usernameFieldId = $this->getApplicantFields()->getUsernameFieldId($applicantId);
                        $this->updateApplicantCredentials($applicantId, $companyId, $usernameFieldId, $generatedUsername, $password);
                    }
                }
            }

            /*
             * Plan:
             * 1. Get all blocks and groups for this applicant type
             * 2. For each of them check where data must be saved, group data
             * 3. Save data
             */
            $arrApplicantOfficeFieldIds = array();

            if (empty($strError) && $memberType != 'case') {
                $arrGroups        = $this->getApplicantFields()->getCompanyGroups($companyId, $memberTypeId);
                $arrGroupsGrouped = $this->_settings::arrayColumnAsKey('applicant_group_id', $arrGroups);

                $arrApplicantData = array();
                $arrApplicantOffices = array();
                $arrContacts = array();

                foreach ($arrApplicantFields as $groupId => $arrGroupFieldsData) {
                    if ($arrGroupsGrouped[$groupId]['contact_block'] == 'Y') {
                        // Calculate rows count for this block
                        $rowsCount = count($arrGroupFieldsData[key($arrGroupFieldsData)]);

                        // Group data by contact rows
                        $tmpArray = array();
                        for ($i = 0; $i < $rowsCount; $i++) {
                            $contactId = $arrParams[$memberType . '_group_row_' . $groupId][$i];
                            $tmpArray[$contactId]['parent_group_id'] = array($groupId);
                            $tmpArray[$contactId]['contact_id'] = is_numeric($contactId) ? $contactId : 0;
                            $tmpArray[$contactId]['data'] = array();
                            $tmpArray[$contactId]['offices'] = array();
                            $tmpArray[$contactId]['files'] = array();

                            foreach ($arrGroupFieldsData as $fieldId => $arrValues) {
                                $booSaveToData = true;
                                $row = 0;
                                $rowId = '';
                                $fieldType = '';
                                $fieldUniqueId = '';

                                // Check if this field is an office - if yes - save these ids
                                foreach ($arrAllApplicantFields as $arrBlockInfo) {
                                    if ($arrBlockInfo['group_id'] == $groupId && (isset($arrBlockInfo['fields']) && is_array($arrBlockInfo['fields']))) {
                                        foreach ($arrBlockInfo['fields'] as $arrFieldInfo) {
                                            if ($arrFieldInfo['field_id'] == $fieldId) {
                                                $fieldType = $arrFieldInfo['field_type'];
                                                $fieldUniqueId = $arrFieldInfo['field_unique_id'];
                                                if (in_array($arrFieldInfo['field_type'], array('office_multi', 'office'))) {
                                                    foreach ($arrValues as $value) {
                                                        $tmpArray[$contactId]['offices'] = array_merge(explode(',', $value ?? ''), $tmpArray[$contactId]['offices']);
                                                        $arrApplicantOffices = array_merge($arrApplicantOffices, $tmpArray[$contactId]['offices']);
                                                    }
                                                } elseif ($arrFieldInfo['field_type'] == 'photo' && (is_array($arrValues) && array_key_exists('file_id', $arrValues))) {
                                                    $arrFileInfo = array(
                                                        'field_id' => $fieldId,
                                                        'group_id' => $groupId,
                                                        'row' => $row,
                                                        'row_id' => $rowId,
                                                        'file_id' => $arrValues['file_id'][$i],
                                                        'name' => $arrValues['name'][$i],
                                                        'tmp_name' => $arrValues['tmp_name'][$i],
                                                        'size' => $arrValues['size'][$i],
                                                        'type' => 'image'
                                                    );
                                                    $tmpArray[$contactId]['files'][] = array_merge($arrFileInfo, $tmpArray[$contactId]['files']);
                                                    $booSaveToData = false;
                                                } elseif ($arrFieldInfo['field_type'] == 'file' && (is_array($arrValues) && array_key_exists('file_id', $arrValues))) {
                                                    $arrFileInfo = array(
                                                        'field_id' => $fieldId,
                                                        'group_id' => $groupId,
                                                        'row' => $row,
                                                        'row_id' => $rowId,
                                                        'file_id' => $arrValues['file_id'][$i],
                                                        'name' => $arrValues['name'][$i],
                                                        'tmp_name' => $arrValues['tmp_name'][$i],
                                                        'size' => $arrValues['size'][$i],
                                                        'type' => 'file'
                                                    );
                                                    $tmpArray[$contactId]['files'][] = array_merge($arrFileInfo, $tmpArray[$contactId]['files']);
                                                    $booSaveToData = false;
                                                }
                                            }
                                        }
                                    }
                                }

                                if ($booSaveToData) {
                                    if (is_array($arrValues) && array_key_exists($i, $arrValues)) {
                                        $fieldVal = $arrValues[$i];
                                    } else {
                                        $fieldVal = '';
                                    }
                                    if (in_array($fieldType, array('date', 'date_repeatable')) && !empty($fieldVal)) {
                                        $fieldVal = $this->_settings->reformatDate($arrValues[$i], $dateFormatFull, Settings::DATE_UNIX);
                                    }

                                    $tmpArray[$contactId]['data'][$fieldId] = array(
                                        'field_id' => $fieldId,
                                        'field_unique_id' => $fieldUniqueId,
                                        'field_type' => $fieldType,
                                        'value' => $fieldVal,
                                        'row' => $row,
                                        'row_id' => $rowId
                                    );
                                }
                            }
                        }

                        $thisContactId = 0;
                        if (is_array($tmpArray) && count($tmpArray)) {
                            $arrKeys = array_keys($tmpArray);
                            $thisContactId = $arrKeys[0];
                        }

                        if (array_key_exists($thisContactId, $arrContacts)) {
                            $arrContacts[$thisContactId]['parent_group_id'] = is_array($arrContacts[$thisContactId]['parent_group_id']) ? $arrContacts[$thisContactId]['parent_group_id'] : array();
                            $tmpArray[$thisContactId]['parent_group_id']    = isset($tmpArray[$thisContactId]['parent_group_id']) && is_array($tmpArray[$thisContactId]['parent_group_id']) ? $tmpArray[$thisContactId]['parent_group_id'] : array();
                            $arrContacts[$thisContactId]['parent_group_id'] = array_merge($arrContacts[$thisContactId]['parent_group_id'], $tmpArray[$thisContactId]['parent_group_id']);

                            $arrContacts[$thisContactId]['data'] = is_array($arrContacts[$thisContactId]['data']) ? $arrContacts[$thisContactId]['data'] : array();
                            $tmpArray[$thisContactId]['data']    = isset($tmpArray[$thisContactId]['data']) && is_array($tmpArray[$thisContactId]['data']) ? $tmpArray[$thisContactId]['data'] : array();
                            $arrContacts[$thisContactId]['data'] = array_merge($arrContacts[$thisContactId]['data'], $tmpArray[$thisContactId]['data']);

                            $arrContacts[$thisContactId]['offices'] = is_array($arrContacts[$thisContactId]['offices']) ? $arrContacts[$thisContactId]['offices'] : array();
                            $tmpArray[$thisContactId]['offices']    = isset($tmpArray[$thisContactId]['offices']) && is_array($tmpArray[$thisContactId]['offices']) ? $tmpArray[$thisContactId]['offices'] : array();
                            $arrContacts[$thisContactId]['offices'] = array_merge($arrContacts[$thisContactId]['offices'], $tmpArray[$thisContactId]['offices']);

                            $arrContacts[$thisContactId]['files'] = is_array($arrContacts[$thisContactId]['files']) ? $arrContacts[$thisContactId]['files'] : array();
                            $tmpArray[$thisContactId]['files']    = isset($tmpArray[$thisContactId]['files']) && is_array($tmpArray[$thisContactId]['files']) ? $tmpArray[$thisContactId]['files'] : array();
                            $arrContacts[$thisContactId]['files'] = array_merge($arrContacts[$thisContactId]['files'], $tmpArray[$thisContactId]['files']);
                        } else {
                            $arrContacts = count($arrContacts) ? array_merge($arrContacts, $tmpArray) : $tmpArray;
                        }
                    } else {
                        foreach ($arrGroupFieldsData as $fieldId => $arrValues) {
                            // Check if this field is an office - if yes - save these ids
                            $fieldType = '';
                            $fieldUniqueId = '';
                            foreach ($arrAllApplicantFields as $arrBlockInfo) {
                                if ($arrBlockInfo['group_id'] == $groupId && (isset($arrBlockInfo['fields']) && is_array($arrBlockInfo['fields']))) {
                                    foreach ($arrBlockInfo['fields'] as $arrFieldInfo) {
                                        if ($arrFieldInfo['field_id'] == $fieldId) {
                                            $fieldType = $arrFieldInfo['field_type'];
                                            $fieldUniqueId = $arrFieldInfo['field_unique_id'];
                                            if (in_array($arrFieldInfo['field_type'], array('office_multi', 'office'))) {
                                                $arrApplicantOfficeFieldIds[] = $fieldId;
                                                $arrApplicantOfficeFieldIdsWithGroups[] = $groupId . '_' . $fieldId;
                                                foreach ($arrValues as $value) {
                                                    $arrApplicantOffices = array_merge(explode(',', $value ?? ''), $arrApplicantOffices);
                                                }
                                            }
                                        }
                                    }
                                }
                            }

                            foreach ($arrValues as $row => $value) {
                                $fieldVal = $value;
                                if (in_array($fieldType, array('date', 'date_repeatable')) && !empty($fieldVal)) {
                                    $fieldVal = $this->_settings->reformatDate($fieldVal, $dateFormatFull, Settings::DATE_UNIX);
                                }

                                $arrApplicantData[] = array(
                                    'field_id' => $fieldId,
                                    'field_unique_id' => $fieldUniqueId,
                                    'field_type' => $fieldType,
                                    'value' => $fieldVal,
                                    'row' => $row,
                                    'row_id' => $arrParams[$memberType . '_group_row_' . $groupId][$row]
                                );
                            }
                        }
                    }
                }

                if (empty($arrApplicantOffices) && !empty($applicantId)) {
                    $arrApplicantOffices = $this->getApplicantOffices(array($applicantId), $divisionGroupId);
                }

                // Check if child/parent offices are correct
                if (in_array($memberType, array('employer', 'individual'))) {
                    $booShowWarning         = false;
                    $warningEmployerName    = '';
                    $warningIndividualName  = '';
                    $warningOfficeFieldName = '';
                    $strWarningMessage      = $this->_tr->translate(
                        'The employer <i>"%employer"</i> and the individual applicant <i>"%individual"</i> have a related case. ' .
                        'These two clients must at least have one common <i>%office</i>.<br/><br/>' .
                        'Please update the <i>%office</i> before saving.'
                    );

                    $arrThisClientOffices = $arrApplicantOffices;
                    foreach ($arrContacts as $arrContactInfo) {
                        if (is_array($arrContactInfo) && array_key_exists('offices', $arrContactInfo) && is_array($arrContactInfo['offices']) && count($arrContactInfo['offices'])) {
                            $arrThisClientOffices = array_merge($arrContactInfo['offices'], $arrThisClientOffices);
                        }
                    }
                    $arrThisClientOffices = array_unique($arrThisClientOffices);

                    switch ($memberType) {
                        case 'employer':
                            if (!empty($applicantId)) {
                                // Find all child Cases
                                $arrEmployerAssignedCasesIds = $this->getAssignedApplicants($applicantId, $this->getMemberTypeIdByName('case'));

                                // Get parent IA for these cases
                                if (is_array($arrEmployerAssignedCasesIds) && count($arrEmployerAssignedCasesIds)) {
                                    $arrEmployerAssignedIAIds = $this->getParentsForAssignedApplicant($arrEmployerAssignedCasesIds, $this->getMemberTypeIdByName('individual'));

                                    // Load offices list for each of them
                                    // and check if there are intersections
                                    foreach ($arrEmployerAssignedIAIds as $individualId) {
                                        $arrIAInnerContacts = $this->getAssignedApplicants($individualId, $this->getMemberTypeIdByName('internal_contact'));
                                        $arrIAOffices = $this->getApplicantOffices(array_merge(array($individualId), $arrIAInnerContacts), $divisionGroupId);

                                        // Check for offices intersections, if there are no common offices - return warning
                                        $arrIntersection = array_intersect($arrIAOffices, $arrThisClientOffices);
                                        if (!count($arrIntersection)) {
                                            $booShowWarning = true;

                                            // Get IA name
                                            $arrIndividualInfo = $this->getMemberInfo($individualId);
                                            $arrIndividualInfo = $this->generateClientName($arrIndividualInfo);
                                            $warningIndividualName = trim($arrIndividualInfo['full_name'] ?? '');

                                            // Get Employer name
                                            $arrThisEmployerInfo = $this->getMemberInfo($applicantId);
                                            $arrThisEmployerInfo = $this->generateClientName($arrThisEmployerInfo);
                                            $warningEmployerName = trim($arrThisEmployerInfo['full_name'] ?? '');

                                            // Get office field name for the current Employer's profile
                                            $warningOfficeFieldName = $officeFieldName;

                                            // Don't check other IAs
                                            break;
                                        }
                                    }
                                }
                            }
                            break;

                        case 'individual':
                            if (!empty($applicantId)) {
                                // Find all child cases, search parent employers by them
                                $arrThisIndividualAssignedCasesIds = $this->getAssignedApplicants($applicantId, $this->getMemberTypeIdByName('case'));

                                // Find parent Employer
                                $arrAssignedParentEmployerIds = $this->getParentsForAssignedApplicant($arrThisIndividualAssignedCasesIds, $this->getMemberTypeIdByName('employer'));

                                foreach ($arrAssignedParentEmployerIds as $parentEmployerId) {
                                    $arrIAInnerContacts = $this->getAssignedApplicants($parentEmployerId, $this->getMemberTypeIdByName('internal_contact'));
                                    $arrParentEmployerOffices = $this->getApplicantOffices(array_merge(array($parentEmployerId), $arrIAInnerContacts), $divisionGroupId);

                                    // Check for offices intersections, if there are no common offices - return warning
                                    $arrIntersection = array_intersect($arrParentEmployerOffices, $arrThisClientOffices);
                                    if (!count($arrIntersection)) {
                                        $booShowWarning = true;

                                        // Get IA name
                                        $arrIndividualInfo = $this->getMemberInfo($applicantId);
                                        $arrIndividualInfo = $this->generateClientName($arrIndividualInfo);
                                        $warningIndividualName = trim($arrIndividualInfo['full_name'] ?? '');

                                        // Get Employer name
                                        $arrThisEmployerInfo = $this->getMemberInfo($parentEmployerId);
                                        $arrThisEmployerInfo = $this->generateClientName($arrThisEmployerInfo);
                                        $warningEmployerName = trim($arrThisEmployerInfo['full_name'] ?? '');

                                        // Get office field name for the current Employer's profile
                                        $warningOfficeFieldName = $officeFieldName;

                                        // Don't check other Employers
                                        break;
                                    }
                                }
                            } elseif (!empty($employerId)) {
                                $arrIAInnerContacts = $this->getAssignedApplicants($employerId, $this->getMemberTypeIdByName('internal_contact'));
                                $arrParentEmployerOffices = $this->getApplicantOffices(array_merge(array($employerId), $arrIAInnerContacts), $divisionGroupId);

                                // Check for offices intersections, if there are no common offices - return warning
                                $arrIntersection = array_intersect($arrParentEmployerOffices, $arrThisClientOffices);
                                if (!count($arrIntersection)) {
                                    $booShowWarning = true;

                                    // Get IA name
                                    $warningIndividualName = 'New Client';

                                    // Get Employer name
                                    $arrThisEmployerInfo = $this->getMemberInfo($employerId);
                                    $arrThisEmployerInfo = $this->generateClientName($arrThisEmployerInfo);
                                    $warningEmployerName = trim($arrThisEmployerInfo['full_name'] ?? '');

                                    // Get office field name for the current Employer's profile
                                    $warningOfficeFieldName = $officeFieldName;

                                    // Use another message for this case
                                    $strWarningMessage = $this->_tr->translate(
                                        'The employer <i>"%employer"</i> and this new individual applicant must have at least one common <i>%office</i>.' .
                                        '<br/><br/>' .
                                        'Please update the <i>%office</i> before saving.'
                                    );
                                }
                            }
                            break;

                        default:
                            break;
                    }


                    if ($booShowWarning) {
                        $strErrorType = 'warning';
                        $arrWarningParams = array(
                            'employer' => $warningEmployerName,
                            'individual' => $warningIndividualName,
                            'office' => $warningOfficeFieldName
                        );
                        $strError = Settings::sprintfAssoc($strWarningMessage, $arrWarningParams);
                    }
                }

                if (empty($strError)) {
                    $this->_db2->getDriver()->getConnection()->beginTransaction();
                    $booTransactionStarted = true;
                }

                $booUpdateClientIdSequence = false;
                $newClientIdSequence       = null;
                if (empty($strError)) {
                    // Calculate values for auto calc fields
                    $arrApplicantData = $this->_recalculateAutoCalcFields($applicantId, $arrApplicantData, $arrAllApplicantFields, $arrFieldsOptions);

                    // Generate Client Profile ID on new client creation
                    list($arrApplicantData, $booUpdateClientIdSequence, $newClientIdSequence) = $this->generateClientProfileIdValue(empty($applicantId), $companyId, $arrApplicantData, $newClientIdSequence);
                    if (empty($applicantId)) {
                        $applicantId = $this->createApplicant($companyId, $divisionGroupId, $currentMemberId, $memberTypeId, $applicantType, $arrApplicantData, $arrApplicantOffices);
                        if (!$applicantId) {
                            $strError = $this->_tr->translate('Internal error.');
                        }
                    } else {
                        $arrUpdateApplicant = $this->updateApplicant($companyId, $currentMemberId, $applicantId, $arrApplicantData, $arrApplicantOffices);

                        $arrAllFieldsChangesData[$applicantId]['booIsApplicant'] = true;
                        $arrAllFieldsChangesData[$applicantId]['arrOldData'] = $arrUpdateApplicant['arrOldData'];
                        $arrAllFieldsChangesData[$applicantId]['arrNewData'] = $arrApplicantData;

                        if (!$arrUpdateApplicant['success']) {
                            $strError = $this->_tr->translate('Internal error.');
                        }
                    }
                }

                if (empty($strError) && !empty($applicantType)) {
                    $this->updateApplicantType($applicantId, $applicantType);
                }

                if (empty($strError)) {
                    // Create contact record + assign to the applicant
                    $contactTypeId = $this->getMemberTypeIdByName('internal_contact');
                    $arrContactIds = array();
                    foreach ($arrContacts as $arrContactInfo) {
                        // Calculate values for auto calc fields
                        $arrContactInfo['data'] = $this->_recalculateAutoCalcFields($arrContactInfo['contact_id'], $arrContactInfo['data'], $arrAllApplicantFields, $arrFieldsOptions);

                        // Generate Client Profile ID on new client creation
                        list($arrContactInfo['data'], $booInternalContactUpdateClientIdSequence, $newClientIdSequence) = $this->generateClientProfileIdValue(empty($arrContactInfo['contact_id']), $companyId, $arrContactInfo['data'], $newClientIdSequence);
                        if ($booInternalContactUpdateClientIdSequence) {
                            $booUpdateClientIdSequence = true;
                        }

                        $currentContactId = 0;
                        if (empty($arrContactInfo['contact_id'])) {
                            if (count($arrContactInfo['data'])) {
                                // Create a new contact
                                $contactId = $this->createApplicant($companyId, $divisionGroupId, $currentMemberId, $contactTypeId, null, $arrContactInfo['data'], $arrContactInfo['offices'], null, $applicantId);
                                $arrContactIds[] = $contactId;
                                if (!$contactId) {
                                    $strError = $this->_tr->translate('Internal error.');
                                    break;
                                }

                                // Assign to the applicant in specific section
                                foreach ($arrContactInfo['parent_group_id'] as $parentGroupId) {
                                    $row = $this->getRowForApplicant($applicantId, $parentGroupId);

                                    $arrAssignData = array(
                                        'applicant_id' => $applicantId,
                                        'contact_id'   => $contactId,
                                        'group_id'     => $parentGroupId,
                                        'row'          => is_null($row) ? 0 : $row + 1,
                                    );
                                    if (!$this->assignContactToApplicant($arrAssignData)) {
                                        $strError = $this->_tr->translate('Internal error.');
                                        break 2;
                                    }
                                }

                                $currentContactId = $contactId;
                            }
                        } else {
                            $arrContactIds[] = $arrContactInfo['contact_id'];

                            $arrContactOffices = empty($arrContactInfo['offices']) && !empty($arrApplicantOffices) ? $arrApplicantOffices : $arrContactInfo['offices'];
                            $arrUpdateApplicant = $this->updateApplicant($companyId, $currentMemberId, $arrContactInfo['contact_id'], $arrContactInfo['data'], $arrContactOffices);

                            $arrAllFieldsChangesData[$arrContactInfo['contact_id']]['booIsApplicant'] = true;
                            $arrAllFieldsChangesData[$arrContactInfo['contact_id']]['arrOldData'] = $arrUpdateApplicant['arrOldData'];
                            $arrAllFieldsChangesData[$arrContactInfo['contact_id']]['arrNewData'] = $arrContactInfo['data'];

                            if (isset($arrUpdateApplicant['changeOfficeFieldToUpdate']['value'])) {
                                $changeOfficeFieldToUpdate = $arrUpdateApplicant['changeOfficeFieldToUpdate'];

                                foreach ($arrAllFieldsChangesData[$arrContactInfo['contact_id']]['arrNewData'] as $key => $fieldData) {
                                    if (isset($fieldData['field_type']) && $fieldData['field_type'] == 'office_change_date_time') {
                                        $arrAllFieldsChangesData[$arrContactInfo['contact_id']]['arrNewData'][$key]['value'] = $arrUpdateApplicant['changeOfficeFieldToUpdate']['value'];
                                    }
                                }
                            }

                            if (!$arrUpdateApplicant['success']) {
                                $strError = $this->_tr->translate('Internal error.');
                                break;
                            }

                            $currentContactId = $arrContactInfo['contact_id'];
                        }

                        if (!empty($currentContactId) && is_array($arrContactInfo['files']) && count($arrContactInfo['files'])) {
                            foreach ($arrContactInfo['files'] as $arrFileInfo) {
                                if (file_exists($arrFileInfo['tmp_name'])) {
                                    if ($arrFileInfo['type'] == 'image') {
                                        // get image size
                                        $size = array();
                                        $fieldDefaults = $this->getApplicantFields()->getFieldsOptions(array($arrFileInfo['field_id']));
                                        if (!empty($fieldDefaults)) {
                                            $size = array(
                                                $fieldDefaults[0]['value'],
                                                $fieldDefaults[1]['value']
                                            );
                                        }

                                        // save image
                                        $fileName = 'field-' . $arrFileInfo['field_id'];
                                        if (!empty($arrFileInfo['row_id'])) {
                                            $fileName .= '-' . $arrFileInfo['row_id'];
                                        }

                                        $result = $this->_files->saveImage(
                                            $this->_files->getPathToClientImages($companyId, $currentContactId, $booLocal),
                                            $arrFileInfo['file_id'],
                                            $fileName,
                                            $size,
                                            $booLocal,
                                            $arrFileInfo['row'],
                                            false,
                                            true
                                        );
                                        if ($result && empty($result['error'])) {
                                            $realFileName = $result['result'];

                                            $arrNewData = array(
                                                array(
                                                    'field_id' => $arrFileInfo['field_id'],
                                                    'value' => $realFileName,
                                                    'row' => $arrFileInfo['row'],
                                                    'row_id' => $arrFileInfo['row_id'],
                                                )
                                            );
                                            $this->updateApplicantData($currentContactId, $arrNewData);

                                            $imageFieldsToUpdate[] = array(
                                                'applicant_id' => $currentContactId,
                                                'field_id' => $arrFileInfo['field_id'],
                                                'full_field_id' => $arrFileInfo['file_id']
                                            );

                                            $arrAllFieldsChangesData[$currentContactId]['arrNewFiles'][] = array(
                                                'field_id' => $arrFileInfo['field_id'],
                                                'filename' => $realFileName,
                                            );
                                        } else {
                                            $this->_files->deleteClientImage($companyId, $currentContactId, $booLocal, $fileName);
                                        }
                                    } else {
                                        if ($arrFileInfo['type'] == 'file') {
                                            // save file
                                            $fileName = 'field-' . $arrFileInfo['field_id'];
                                            if (!empty($arrFileInfo['row_id'])) {
                                                $fileName .= '-' . $arrFileInfo['row_id'];
                                            }

                                            $result = $this->_files->saveClientFile(
                                                $this->_files->getPathToClientFiles($companyId, $currentContactId, $booLocal),
                                                $arrFileInfo['file_id'],
                                                $fileName,
                                                $booLocal,
                                                $arrFileInfo['row']
                                            );
                                            if ($result && $result['success']) {
                                                $arrNewData = array(
                                                    array(
                                                        'field_id' => $arrFileInfo['field_id'],
                                                        'value' => $arrFileInfo['name'],
                                                        'row' => $arrFileInfo['row'],
                                                        'row_id' => $arrFileInfo['row_id'],
                                                    )
                                                );
                                                $this->updateApplicantData($currentContactId, $arrNewData);

                                                $fileFieldsToUpdate[] = array(
                                                    'applicant_id' => $currentContactId,
                                                    'field_id' => $arrFileInfo['field_id'],
                                                    'full_field_id' => $arrFileInfo['file_id'],
                                                    'filename' => $arrFileInfo['name']
                                                );

                                                $arrAllFieldsChangesData[$currentContactId]['arrNewFiles'][] = array(
                                                    'field_id' => $arrFileInfo['field_id'],
                                                    'filename' => $arrFileInfo['filename'],
                                                );
                                            } else {
                                                $this->_files->deleteClientFile($companyId, $currentContactId, $booLocal, $fileName);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if (empty($strError) && $booUpdateClientIdSequence && !empty($newClientIdSequence)) {
                        $this->_company->updateCompanyClientProfileIdStartFrom($companyId, $newClientIdSequence);
                    }

                    if (!$this->updateAssignedContacts($applicantId, $arrContactIds)) {
                        $strError = $this->_tr->translate('Internal error.');
                    }


                    // Update applicant's name from the 'main contact'
                    if (empty($strError) && !$this->updateApplicantFromMainContact($companyId, $applicantId, $memberTypeId, $applicantType)) {
                        $strError = $this->_tr->translate('Internal error.');
                    }

                    // Update applicant's offices from the child applicants (Contacts/Cases)
                    if (empty($strError)) {
                        $arrApplicantNewOffices = $this->updateApplicantOfficesFromChildren($applicantId, $arrApplicantOfficeFieldIds);
                        if ($arrApplicantNewOffices === false) {
                            $strError = $this->_tr->translate('Internal error happened during client offices updating.');
                        }
                    }
                }
            }

            // For IA and Case profile we need create/update Case's profile
            $booUpdateCase = false;
            $clientFields = array();


            $booNeedToCheckCaseFields = false;
            switch ($memberType) {
                case 'case':
                    $booNeedToCheckCaseFields = true;
                    break;


                case 'individual':
                    if ($booCreateApplicant && !empty($arrParams['caseType'])) {
                        $booNeedToCheckCaseFields = true;
                    }
                    break;

                default:
                    break;
            }

            $arrSavedClientInfo = [];
            if (empty($strError) && $booNeedToCheckCaseFields) {
                $caseId = isset($arrParams['caseId']) ? (int)$arrParams['caseId'] : 0;
                $booCreateCase = empty($caseId);
                if (!$booCreateCase) {
                    if ($this->hasCurrentMemberAccessToMember($caseId)) {
                        $arrSavedClientInfo  = $this->getClientInfoOnly($caseId);
                        $booEmptyCaseCreated = empty($arrSavedClientInfo['client_type_id']);

                        if (!$booEmptyCaseCreated && !empty($arrSavedClientInfo['modified_on']) && $arrSavedClientInfo['modified_on'] != $lastUpdateTime && !$booForceOverwrite) {
                            // Case Profile was updated - so we need to ask a user if we can overwrite data
                            $strError = 'last_update_time_different';
                        }
                    } else {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                }


                $caseTemplateId = isset($arrParams['caseType']) ? (int)$arrParams['caseType'] : 0;
                if (empty($strError)) {
                    if (!empty($caseIdLinkedTo)) {
                        $useMemberTypeId = $this->getMemberTypeIdByName('individual');
                    } else {
                        $useMemberTypeId = empty($employerId) ? $memberTypeId : $this->getMemberTypeIdByName('employer');

                        if (!$booCreateCase && !empty($employerId)) {
                            // Check if case is assigned to the individual too -> use "individual" case types
                            $arrParents = $this->getParentsForAssignedApplicant($caseId, $this->getMemberTypeIdByName('individual'));
                            if (!empty($arrParents)) {
                                $useMemberTypeId = $this->getMemberTypeIdByName('individual');
                            }
                        }
                    }

                    $arrCompanyCaseTemplateIds = $this->getCaseTemplates()->getTemplates($companyId, true, $useMemberTypeId, $booCreateCase || $booEmptyCaseCreated);
                    if (!in_array($caseTemplateId, $arrCompanyCaseTemplateIds)) {
                        $caseTypeTerm = $this->_company->getCurrentCompanyDefaultLabel('case_type');
                        if (empty($employerId)) {
                            $strError = $this->_tr->translate('Incorrectly selected') . ' ' . $caseTypeTerm;
                        } else {
                            $strError = sprintf(
                                $this->_tr->translate('Your current case is linked to an employer. Please choose the %s that requires an employer association or first unlink the employer from the case.'),
                                $caseTypeTerm
                            );
                        }
                    } else {
                        $clientFields['client_type_id'] = $caseTemplateId;
                    }
                }

                $booUpdateCase = empty($strError);
            }

            if (empty($strError)) {
                $arrApplicantInfo = $this->getMemberInfo($applicantId);
                $arrApplicantInfo = $this->generateClientName($arrApplicantInfo);
                $applicantName    = trim($arrApplicantInfo['full_name'] ?? '');

                $usedClientId     = !empty($applicantId) && $memberType == 'case' && !empty($caseId) ? $caseId : $applicantId;
                $arrApplicantInfo = $this->getClientsInfo(array($usedClientId), false, $memberTypeId);
                if ($arrApplicantInfo) {
                    $applicantUpdatedOn     = $this->getFields()->generateClientFooter($usedClientId, $arrApplicantInfo[0]);
                    $applicantUpdatedOnTime = empty($arrApplicantInfo[0]['modified_on']) ? '' : $arrApplicantInfo[0]['modified_on'];
                }
            }

            $modifiedFields     = array();
            $fileStatusNewValue = '';
            $fileStatusOldValue = '';
            if ($booUpdateCase) {
                $arrCacheUserInfo = array();
                $arrClientInfo    = array();

                $arrErrors    = array();
                $memberFields = array();

                if ($booCreateCase || $booEmptyCaseCreated) {
                    $arrAssignedCases = $this->getApplicantAssignedCases($companyId, $applicantId, false, null, null, null);
                    if ($booEmptyCaseCreated) {
                        $booAssignedCasesExist = $arrAssignedCases[1] > 1;
                    } else {
                        $booAssignedCasesExist = !empty($arrAssignedCases[1]);
                    }
                }

                $arrClientSavedDependents = [];
                if (!$booCreateCase && !$booEmptyCaseCreated) {
                    $arrClientSavedDependents = $this->getFields()->getDependents(array($caseId));
                }

                // Select fields which available for current user
                $fields = $this->getFields()->getCaseTemplateFields($companyId, $caseTemplateId);

                // Select current client data
                $fdata = array();
                if (!$booCreateCase) {
                    $arrIds = is_array($fields) ? $this->_settings::arrayColumnAsKey('field_id', $fields, 'field_id') : array();
                    $arrIds = array_values($arrIds);

                    if (!empty($arrIds)) {
                        $fdata = $this->getFields()->getFieldData($arrIds, $caseId);

                        $dataGrouped = array();
                        foreach ($fdata as $data) {
                            if (!array_key_exists($data['field_id'], $dataGrouped)) {
                                $dataGrouped[$data['field_id']] = $data['value'];
                            } else {
                                if (is_array($dataGrouped[$data['field_id']])) {
                                    $dataGrouped[$data['field_id']][] = $data['value'];
                                } else {
                                    $dataGrouped[$data['field_id']] = array($dataGrouped[$data['field_id']], $data['value']);
                                }
                            }
                        }
                        $fdata = $dataGrouped;
                    }
                }

                $clientFileStatusFieldId = $this->getFields()->getClientStatusFieldId($companyId);
                $booClientFileStatusFieldFound = false;
                foreach ($fields as $key => $field) {
                    // Merge client field and client data
                    $fields[$key]['value'] = (!empty($fdata[$field['field_id']]) ? $fdata[$field['field_id']] : '');

                    // Make sure if the current user has access to the Case Status field
                    if ($field['field_id'] == $clientFileStatusFieldId) {
                        $booClientFileStatusFieldFound = true;

                        // Force this field to be marked as "missing/no access" -
                        // So we can force to use the default "Active" value
                        if (($booCreateCase || $booEmptyCaseCreated) && $field['field_access'] === 'R') {
                            $booClientFileStatusFieldFound = false;
                        }
                    }
                }

                // If there is no access to the Case Status Field (or Read only, so value wasn't received), but we create the case -
                // Save this case as "Active" option is selected
                if (($booCreateCase || $booEmptyCaseCreated) && !$booClientFileStatusFieldFound && !empty($clientFileStatusFieldId)) {
                    $arrFieldInfo = $this->getFields()->getFieldInfo($clientFileStatusFieldId, $companyId, $caseTemplateId);
                    $arrFieldInfo['status'] = 'F';
                    $arrFieldInfo['field_access'] = 'F';

                    $fields[] = $arrFieldInfo;

                    $arrCaseFields[$clientFileStatusFieldId] = 'on';
                }

                //run, baby, run, OMG o_O
                $arrSyncFields = $this->getFields()->getProfileSyncFields();
                $arrParentClientDivisions = $this->getMemberDivisions($applicantId);
                foreach ($fields as $field) {
                    //check access
                    if ($field['status'] != 'F' || ($field['required'] != 'Y' && !array_key_exists($field['field_id'], $arrCaseFields))) {
                        continue;
                    }

                    $booHtmlEditor = $field['type'] == $this->getFieldTypes()->getFieldTypeId('html_editor');

                    $arrCaseFields[$field['field_id']] = isset($arrCaseFields[$field['field_id']]) ? $arrCaseFields[$field['field_id']] : '';
                    if (is_array($arrCaseFields[$field['field_id']])) {
                        foreach ($arrCaseFields[$field['field_id']] as &$valueData) {
                            if ($booHtmlEditor) {
                                $valueData = $oPurifier->purify($valueData);
                            } else {
                                $valueData = trim($filter->filter($valueData));
                            }
                        }
                        unset($valueData);
                    } else {
                        if ($booHtmlEditor) {
                            $arrCaseFields[$field['field_id']] = $oPurifier->purify($arrCaseFields[$field['field_id']]);
                        } else {
                            $arrCaseFields[$field['field_id']] = trim($filter->filter($arrCaseFields[$field['field_id']] ?? ''));
                        }
                    }

                    //get field value
                    $val = $arrCaseFields[$field['field_id']];
                    if (is_array($val)) {
                        $val = implode(',', $val);
                    }

                    if ($this->getFields()->isStaticField($field['company_field_id'])) {
                        switch ($field['company_field_id']) {
                            case 'given_names' :
                            case 'first_name' :
                                if (empty($val)) {
                                    $arrErrors[] = 'Please enter ' . $field['label'];
                                } else {
                                    if (!Fields::validName($val)) {
                                        $arrErrors[] = 'Incorrect characters in ' . $field['label'];
                                    }
                                }

                                $arrClientInfo['fName'] = $memberFields['fName'] = $val;
                                break;

                            case 'family_name' :
                            case 'last_name' :
                                if (empty($val)) {
                                    $arrErrors[] = 'Please enter ' . $field['label'];
                                } elseif (!Fields::validName($val)) {
                                    $arrErrors[] = 'Incorrect characters in ' . $field['label'];
                                }

                                $arrClientInfo['lName'] = $memberFields['lName'] = $val;
                                break;

                            case 'email' :
                                $validator = new EmailAddress();
                                if (!empty($val) && !$validator->isValid($val)) {
                                    $arrErrors[] = 'Incorrect Email Address';
                                }

                                $arrClientInfo['emailAddress'] = $memberFields['emailAddress'] = $val;
                                break;

                            case 'file_number' :
                                $val = empty($val) || $val == '-' ? '' : $val;
                                if (empty($val)) {
                                    // Generate case number automatically for new cases only
                                    // and if in company settings this option is turned on
                                    if (($booCreateCase || $booEmptyCaseCreated) && $this->getCaseNumber()->isAutomaticTurnedOn($companyId) && $this->_config['site_version']['clients']['generate_case_number_on'] === 'default') {
                                        $boolIsReserved = false;
                                        $intMaxAttempts = 20;
                                        $intAttempt     = 0;

                                        $strCaseReferenceGenerationError = '';

                                        $individualClientId = $applicantId;
                                        $employerClientId   = $employerId;
                                        if (empty($employerClientId) && !empty($individualClientId)) {
                                            // Check if a passed client is employer
                                            $arrParentInfo = $this->getMemberInfo($individualClientId);
                                            if ($arrParentInfo['userType'] == $this->getMemberTypeIdByName('employer')) {
                                                $employerClientId   = $individualClientId;
                                                $individualClientId = 0;
                                            }
                                        }

                                        while (!$boolIsReserved && !$strCaseReferenceGenerationError && ($intAttempt < $intMaxAttempts)) {
                                            $intAttempt++;

                                            $arrResultGenerateCaseReference = $this->getCaseNumber()->generateCaseReference($arrParams, $individualClientId, $employerClientId, $caseId, $caseTemplateId, $intAttempt);

                                            $increment = $arrResultGenerateCaseReference['increment'];
                                            if (!empty($arrResultGenerateCaseReference['strError'])) {
                                                $arrErrors[] = $strCaseReferenceGenerationError = $arrResultGenerateCaseReference['strError'];
                                                $arrErrorFields[] = $arrResultGenerateCaseReference['subclassMarkInvalidId'];
                                            }

                                            if (!empty($arrResultGenerateCaseReference['newCaseNumber'])) {
                                                $val = $arrResultGenerateCaseReference['newCaseNumber'];
                                                $startCaseNumberFrom = $arrResultGenerateCaseReference['startCaseNumberFrom'];
                                            }

                                            if (empty($strCaseReferenceGenerationError)) {
                                                $booBasedOnCaseType = array_key_exists('cn-global-or-based-on-case-type', $arrCompanySettings) && $arrCompanySettings['cn-global-or-based-on-case-type'] === 'case-type';

                                                // do not reserve case number if it is based on the Immigration Program
                                                if (!$booBasedOnCaseType) {
                                                    $boolIsReserved = $this->getCaseNumber()->reserveFileNumber($companyId, $val, $increment);
                                                } else {
                                                    $boolIsReserved = true;
                                                }
                                            }
                                        }

                                        if (!$strCaseReferenceGenerationError && $intAttempt == $intMaxAttempts && !$boolIsReserved) {
                                            $arrErrors[] = 'Could not generate new unique file number - reached maximum number of attempts.';
                                            $this->_log->debugErrorToFile(
                                                sprintf('Could not generate new unique file number - reached maximum number of attempts. companyId = %s, applicantId = %s, caseId = %s', $companyId, $applicantId, $caseId),
                                                null,
                                                'case_number'
                                            );
                                            $val = '';
                                        } else {
                                            if (empty($strCaseReferenceGenerationError) && !empty($val)) {
                                                $caseReferenceNumber = $val;
                                                if (!empty($startCaseNumberFrom)) {
                                                    $booUpdateCaseNumberSettings = true;
                                                }
                                            }
                                        }
                                    }
                                } elseif (!CaseNumber::isValidFileNum($val)) {
                                    $arrErrors[] = 'Incorrect characters in ' . $field['label'];
                                }
                                $arrClientInfo['fileNumber'] = $clientFields['fileNumber'] = $val;


                                // Prepare to log case number changes
                                if (!empty($caseId)) {
                                    $arrAllFieldsChangesData[$caseId]['arrOldData'] = array(
                                        array(
                                            'field_id' => $field['field_id'],
                                            'row' => 0,
                                            'value' => $arrSavedClientInfo['fileNumber'],
                                        )
                                    );
                                    $arrAllFieldsChangesData[$caseId]['arrNewData'] = array(
                                        array(
                                            'field_id' => $field['field_id'],
                                            'row' => 0,
                                            'value' => $val,
                                        )
                                    );
                                }

                                break;

                            case 'agent' :
                                $arrClientInfo['agent_id'] = $clientFields['agent_id'] = $val;
                                break;

                            case 'division' :
                                if (empty($val)) {
                                    $arrErrors[] = 'Please select ' . $field['label'];
                                }

                                $arrClientInfo['division'] = $division = $val;
                                break;
                        }
                    } else {
                        // Dynamic field

                        // Save dynamic field for xfdf sync
                        foreach ($arrSyncFields as $synFieldInfo) {
                            if (is_array($synFieldInfo) && array_key_exists('dynamic', $synFieldInfo)) {
                                if ($synFieldInfo['id'] == $field['company_field_id']) {
                                    $syncFieldType = array_key_exists('type', $synFieldInfo) ? $synFieldInfo['type'] : '';
                                    switch ($syncFieldType) {
                                        case 'date':
                                            $tmpVal = $this->_settings->reformatDate($val, $dateFormatFull, Settings::DATE_UNIX);
                                            break;

                                        case 'combo':
                                            $tmpVal = $val;
                                            if (array_key_exists('field_options', $synFieldInfo)) {
                                                foreach ($synFieldInfo['field_options'] as $arrSyncFieldOptionInfo) {
                                                    if ($arrSyncFieldOptionInfo['option_id'] == $val) {
                                                        $tmpVal = $arrSyncFieldOptionInfo['option_name'];
                                                        break;
                                                    }
                                                }
                                            }
                                            break;

                                        default:
                                            $tmpVal = $val;
                                            break;
                                    }
                                    $arrClientInfo[$field['company_field_id']] = $tmpVal;
                                }
                            }
                        }

                        // For Case Name make same checks as for last name
                        // Also save case name to the last name - this is required when we use it in other places
                        if ($field['company_field_id'] == 'case_name') {
                            if (empty($val)) {
                                $arrErrors[] = 'Please enter ' . $field['label'];
                            } elseif (!Fields::validName($val)) {
                                $arrErrors[] = 'Incorrect characters in ' . $field['label'];
                            }

                            $arrClientInfo['lName'] = $memberFields['lName'] = $val;
                        }

                        //if field is required
                        if ($field['required'] == 'Y' && $val == '') {
                            if ($field['company_field_id'] == 'real_estate_project' && $this->_config['site_version']['validation']['check_investment_type']) {
                                foreach ($fields as $fieldInfo) {
                                    if ($fieldInfo['company_field_id'] == 'cbiu_investment_type') {
                                        $arrInvestmentTypeId = $fieldInfo['field_id'];
                                        $arrInvestmentTypeOptions = $this->getFields()->getFieldsOptions(array($arrInvestmentTypeId));
                                        $governmentFundOptionId = 0;

                                        foreach ($arrInvestmentTypeOptions as $arrInvestmentTypeOptionInfo) {
                                            if ($arrInvestmentTypeOptionInfo['value'] == 'Government Fund') {
                                                $governmentFundOptionId = $arrInvestmentTypeOptionInfo['form_default_id'];
                                            }
                                        }

                                        if ($arrCaseFields[$fieldInfo['field_id']] != $governmentFundOptionId) {
                                            $arrErrors[] = 'Please provide value for "' . $field['label'] . '" field';
                                        }
                                    }
                                }
                            } else {
                                $arrErrors[] = 'Please provide value for "' . $field['label'] . '" field';
                            }
                        }

                        // check image fields
                        if ($field['type'] == $this->getFieldTypes()->getFieldTypeId('image')) {
                            $fId = 'field-' . $field['field_id'];
                            if (!empty($arrReceivedFiles) && isset($arrReceivedFiles[$fId]) && file_exists($arrReceivedFiles[$fId]['tmp_name'])) {
                                if (!$this->_files->isImage($arrReceivedFiles[$fId]['type'])) {
                                    $arrErrors[] = 'Incorrect image selected in "' . $field['label'] . '" field';
                                } elseif ($arrReceivedFiles[$fId]['size'] / 1024 / 1024 > 1) { // more than 1Mb
                                    $arrErrors[] = 'Image size is too large for field "' . $field['label'] . '" (max. 1Mb)';
                                }
                            }
                        }

                        if (!empty($val)) {
                            switch ($field['type']) {
                                case $this->getFieldTypes()->getFieldTypeId('number'):
                                    if (!$this->getFields()->validNumber($val)) {
                                        $arrErrors[] = 'Incorrect number for "' . $field['label'] . '" field';
                                    }
                                    break;

                                case $this->getFieldTypes()->getFieldTypeId('email'):
                                    $validator = new EmailAddress();
                                    if (!$validator->isValid($val)) {
                                        $arrErrors[] = 'Incorrect e-mail address for "' . $field['label'] . '" field';
                                    }
                                    break;

                                case $this->getFieldTypes()->getFieldTypeId('hyperlink'):
                                    if (!filter_var($val, FILTER_VALIDATE_URL)) {
                                        $arrErrors[] = 'Incorrect link for "' . $field['label'] . '" field';
                                    }
                                    break;

                                case $this->getFieldTypes()->getFieldTypeId('phone'):
                                    if (!$this->getFields()->validPhone($val)) {
                                        $arrErrors[] = 'Incorrect phone number for "' . $field['label'] . '" field';
                                    }
                                    break;

                                case $this->getFieldTypes()->getFieldTypeId('text'):
                                    if ($field['company_field_id'] == 'zip_code' && !preg_match('/^[0-9A-Za-z]+([\s\-][0-9A-Za-z]+)?$/i', $val)) {
                                        $arrErrors[] = 'Incorrect value for "' . $field['label'] . '" field';
                                    }
                                    break;

                                case $this->getFieldTypes()->getFieldTypeId('multiple_combo'):
                                    $arrFieldOptions = $this->getFields()->getFieldOptions($field['field_id']);
                                    $booCorrect = false;
                                    $arrFieldValues = explode(',', $val);

                                    if (count($arrFieldOptions)) {
                                        foreach ($arrFieldValues as $fieldValue) {
                                            $booCorrect = false;

                                            foreach ($arrFieldOptions as $arrOptionInfo) {
                                                if ($arrOptionInfo['option_id'] == $fieldValue) {
                                                    $booCorrect = true;
                                                }
                                            }
                                            if (!$booCorrect) {
                                                $arrErrors[] = sprintf($this->_tr->translate('Please select values for %s field correctly.'), $field['label']);
                                                break 2;
                                            }
                                        }
                                    }
                                    if (!$booCorrect) {
                                        $arrErrors[] = sprintf($this->_tr->translate('Please select values for %s field correctly.'), $field['label']);
                                    }
                                    break;

                                case $this->getFieldTypes()->getFieldTypeId('date'):
                                case $this->getFieldTypes()->getFieldTypeId('rdate'):
                                    if (!Settings::isValidDateFormat($val, $dateFormatFull)) {
                                        $arrErrors[] = 'Incorrect date for "' . $field['label'] . '" field';
                                    }
                                    break;

                                case $this->getFieldTypes()->getFieldTypeId('assigned'):
                                case $this->getFieldTypes()->getFieldTypeId('staff_responsible_rma'):
                                    $userId = 0;

                                    if ($field['type'] == $this->getFieldTypes()->getFieldTypeId('assigned')) {
                                        if (preg_match('/^user:(\d+)$/', $val, $regs)) {
                                            $userId = (int)$regs[1];
                                        }
                                    } else {
                                        $userId = $val;
                                    }


                                    if (!empty($userId) && count($arrParentClientDivisions)) {
                                        // Save in cache array to avoid similar queries to DB
                                        if (!array_key_exists($userId, $arrCacheUserInfo)) {
                                            $arrTargetUserDivisions = $this->getMemberDivisions($userId);
                                            $userInfo = $oUsers->getUserInfo($userId);
                                            $booIsAdmin = $this->isMemberAdmin($userInfo['userType']);

                                            $arrCacheUserInfo[$userId] = array(
                                                'userDivisions' => $arrTargetUserDivisions,
                                                'userInfo' => $userInfo,
                                                'booIsAdmin' => $booIsAdmin
                                            );
                                        } else {
                                            $arrTargetUserDivisions = $arrCacheUserInfo[$userId]['userDivisions'];
                                            $userInfo = $arrCacheUserInfo[$userId]['userInfo'];
                                            $booIsAdmin = $arrCacheUserInfo[$userId]['booIsAdmin'];
                                        }

                                        if (!$booIsAdmin && !count(array_intersect($arrTargetUserDivisions, $arrParentClientDivisions))) {
                                            $arrNoAccessDivisions = array();
                                            foreach ($arrParentClientDivisions as $divisionId) {
                                                if (array_key_exists($divisionId, $arrCompanyDivisions)) {
                                                    $arrNoAccessDivisions[] = $arrCompanyDivisions[$divisionId]['name'];
                                                }
                                            }
                                            $arrErrors[] = $userInfo['full_name'] . ' does not have access to ' . implode(', ', $arrNoAccessDivisions) . '.';
                                        }

                                        // Prepare info for xfdf (RMA related fields)
                                        $arrClientInfo[$field['company_field_id'] . '_family_name'] = $userInfo['lName'];
                                        $arrClientInfo[$field['company_field_id'] . '_given_name'] = $userInfo['fName'];
                                        $arrClientInfo[$field['company_field_id'] . '_marn'] = $userInfo['user_migration_number'];
                                    }
                                    break;

                                default:
                                    break;
                            }
                        }
                    }
                }//end foreach

                // Clear cached info
                unset($arrSyncFields, $arrCacheUserInfo);


                //###################  dependants group //############################
                $booDependentsFullAccess   = $this->getFields()->getAccessToDependants($caseTemplateId) == 'F';
                $arrClientUpdateDependants = array();
                $arrDependentReadableInfo  = array();

                // Get allowed relationship options
                $arrRelationshipOptions = array();
                foreach ($arrUserAllowedDependentFields as $arrUserAllowedDependentFieldInfo) {
                    if ($arrUserAllowedDependentFieldInfo['field_visible'] && $arrUserAllowedDependentFieldInfo['field_id'] == 'relationship') {
                        $arrRelationshipOptions = $arrUserAllowedDependentFieldInfo['field_options'];
                        break;
                    }
                }

                if (count($arrDependentsDataGrouped) && $booDependentsFullAccess) {
                    $line = 0;

                    // Reset previously saved 'migrating' rows
                    $migratingDependentCount = 0;
                    for ($i = 1; $i <= 5; $i++) {
                        $arrClientInfo['dependant' . $i . '_family_name'] = '';
                        $arrClientInfo['dependant' . $i . '_given_name'] = '';
                    }

                    $arrErrorsShowed = array();
                    foreach ($arrDependentsDataGrouped as $arrDependantInfo) {
                        $relationship = is_array($arrDependantInfo) && array_key_exists('relationship', $arrDependantInfo) ? $arrDependantInfo['relationship'] : '';
                        if (empty($relationship)) {
                            $arrErrors[] = 'Incorrectly passed dependant data.';
                            continue;
                        }

                        // Don't allow creating more than X allowed (e.g. 1 spouse only)
                        $maxCount = 0;
                        $maxCountError = '';
                        foreach ($arrRelationshipOptions as $arrRelationshipOptionInfo) {
                            if ($arrRelationshipOptionInfo['option_id'] == $relationship) {
                                $maxCount = $arrRelationshipOptionInfo['option_max_count'];
                                $maxCountError = $arrRelationshipOptionInfo['option_max_count_error'];
                                break;
                            }
                        }

                        if (!empty($maxCount) && array_key_exists($relationship, $arrClientUpdateDependants) && !in_array($relationship, $arrErrorsShowed) && count($arrClientUpdateDependants[$relationship]) >= $maxCount) {
                            $arrErrors[] = $maxCountError;
                            $arrErrorsShowed[] = $relationship;
                            continue;
                        }

                        $arrInternalContactType = Members::getMemberType('internal_contact');
                        list($arrAllMainApplicantFieldsData,) = $this->getAllApplicantFieldsData($applicantId, $this->getMemberTypeIdByName('individual'));

                        if ($this->_config['site_version']['validation']['check_marital_status'] && $relationship == 'spouse') {
                            $applicantMaritalStatusFieldId = $this->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('relationship_status', $arrInternalContactType);

                            foreach ($arrAllMainApplicantFieldsData as $key => $arrValue) {
                                preg_match('/.*_\d*_(\d*)/', $key, $arrMatches);
                                if (!empty($arrMatches) && isset($arrMatches[1]) && $applicantMaritalStatusFieldId == $arrMatches[1]) {
                                    if ($this->getApplicantFields()->getDefaultFieldOptionValue($arrValue[0]) == $neverMarriedLabel) {
                                        $arrErrors[] = $this->_tr->translate('You can\'t add Partner because Marital Status in Profile tab is "' . $neverMarriedLabel . '"');
                                        break;
                                    }
                                }
                            }
                        }

                        $dep = array();
                        $arrReadableDependentInfo = array();
                        foreach ($arrDependantInfo as $dependantFieldId => $dependantFieldVal) {
                            $arrUserAllowedDependentField = array();
                            foreach ($arrUserAllowedDependentFields as $arrUserAllowedDependentFieldInfo) {
                                $booSkipField = false;
                                if (!$arrUserAllowedDependentFieldInfo['field_visible']) {
                                    $booSkipField = true;

                                    if (empty($arrDependantInfo['dependent_id']) && $arrUserAllowedDependentFieldInfo['field_id'] == 'include_in_minute_checkbox') {
                                        if (!empty($oDependentFieldsConfig['include_in_minute_checkbox']['show']) && !empty($oDependentFieldsConfig['include_in_minute_checkbox']['show_for_non_agent_only']) && $this->_auth->isCurrentUserAuthorizedAgent()) {
                                            // Use this field as showed - so it can be set as checked via the code above
                                            $booSkipField = false;
                                        }
                                    }
                                }

                                if ($booSkipField) {
                                    // The field is hidden, skip it
                                    continue;
                                }

                                if ($dependantFieldId == $arrUserAllowedDependentFieldInfo['field_id']) {
                                    $arrUserAllowedDependentField = $arrUserAllowedDependentFieldInfo;
                                    break;
                                }
                            }

                            if (!is_array($arrUserAllowedDependentField) || !count($arrUserAllowedDependentField)) {
                                $arrErrors[] = 'Incorrectly passed dependant field';
                                break 2;
                            }

                            if (($arrUserAllowedDependentField['field_required'] == 'Y') && ((!is_array($arrDependantInfo) || !array_key_exists($dependantFieldId, $arrDependantInfo)) || empty($dependantFieldVal))) {
                                // Some required fields can be empty (e.g. if parent checkbox is checked)
                                // So skip such required fields
                                if ($arrDependantInfo['main_applicant_address_is_the_same'] !== 'Y' || !in_array($dependantFieldId, $arrDependentFieldsToReset)) {
                                    $arrErrors[] = 'Please enter ' . $arrUserAllowedDependentField['field_name'];
                                }
                            }

                            switch ($arrUserAllowedDependentField['field_type']) {
                                case 'date':
                                    if (empty($dependantFieldVal)) {
                                        $dep[$dependantFieldId] = null;
                                        $arrReadableDependentInfo[$dependantFieldId] = '';
                                    } elseif (Settings::isValidDateFormat($dependantFieldVal, $dateFormatFull)) {
                                        $dep[$dependantFieldId] = $this->_settings->reformatDate($dependantFieldVal, $dateFormatFull, Settings::DATE_UNIX);
                                        $arrReadableDependentInfo[$dependantFieldId] = $this->_settings->reformatDate($dependantFieldVal, $dateFormatFull, Settings::DATE_XFDF);

                                        if ($arrUserAllowedDependentField['field_id'] == 'DOB') {
                                            if ($this->_config['site_version']['validation']['check_date_of_birthday'] && strtotime($dependantFieldVal) > time()) {
                                                $arrErrors[] = $this->_tr->translate('Date of birth cannot be in the future');
                                            }
                                            if ($this->_config['site_version']['validation']['check_children_age'] && $relationship == 'child') {
                                                $applicantDateOfBirthCompanyFieldId = $this->getApplicantFields()->getCompanyFieldIdByUniqueFieldId('DOB', $arrInternalContactType);

                                                foreach ($arrAllMainApplicantFieldsData as $key => $arrValue) {
                                                    preg_match('/.*_\d*_(\d*)/', $key, $arrMatches);
                                                    if (!empty($arrMatches) && isset($arrMatches[1]) && $applicantDateOfBirthCompanyFieldId == $arrMatches[1]) {
                                                        $applicantDateOfBirth = $arrValue[0];
                                                        if (strtotime($applicantDateOfBirth) >= strtotime($dependantFieldVal)) {
                                                            $arrErrors[] = $this->_tr->translate('Child\'s date of birth is before main applicant\'s date of birth');
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    } else {
                                        // invalid date
                                        $arrErrors[] = 'Incorrect value for ' . $arrUserAllowedDependentField['field_name'] . ' field';
                                    }
                                    break;

                                case 'combo':
                                    if (empty($dependantFieldVal)) {
                                        $dep[$dependantFieldId] = null;
                                        $arrReadableDependentInfo[$dependantFieldId] = '';
                                    } else {
                                        $booCorrectOption = false;
                                        if (array_key_exists('field_options', $arrUserAllowedDependentField) && is_array($arrUserAllowedDependentField['field_options']) && count($arrUserAllowedDependentField['field_options'])) {
                                            foreach ($arrUserAllowedDependentField['field_options'] as $arrDependentComboOption) {
                                                if ($arrDependentComboOption['option_id'] == $dependantFieldVal) {
                                                    $booCorrectOption = true;
                                                    $arrReadableDependentInfo[$dependantFieldId] = $arrDependentComboOption['option_name'];
                                                    break;
                                                }
                                            }
                                        }

                                        if (!$booCorrectOption) {
                                            $arrErrors[] = 'Incorrect value for ' . $arrUserAllowedDependentField['field_name'] . ' field';
                                        } else {
                                            $dep[$dependantFieldId] = $dependantFieldVal;
                                        }
                                    }
                                    break;

                                default:
                                    $dep[$dependantFieldId] = $dependantFieldVal;
                                    $arrReadableDependentInfo[$dependantFieldId] = $dependantFieldVal;
                                    break;
                            }
                        }

                        // Check if value was modified
                        $booValueChanged = false;
                        if (array_key_exists($relationship, $arrClientSavedDependents) && array_key_exists($line, $arrClientSavedDependents[$relationship])) {
                            // Skip several fields - they are not used/showed in GUI
                            foreach ($arrClientSavedDependents[$relationship][$line] as $field => $value) {
                                if (!in_array($field, array('member_id', 'line', 'sex', 'canadian')) && (!array_key_exists($field, $dep) || $value != $dep[$field])) {
                                    $booValueChanged = true;
                                }
                            }
                        } else {
                            $booValueChanged = true;
                        }

                        // Save 'migrating' fields for XFDF
                        if (isset($dep['migrating']) && strtolower($dep['migrating']) == 'yes') {
                            $arrClientInfo['dependant' . (++$migratingDependentCount) . '_family_name'] = $dep['lName'];
                            $arrClientInfo['dependant' . $migratingDependentCount . '_given_name'] = $dep['fName'];
                        }

                        $arrClientUpdateDependants[$relationship][$line] = $dep;
                        $arrClientUpdateDependants[$relationship][$line]['changed'] = $booValueChanged;
                        $arrClientUpdateDependants[$relationship][$line]['line'] = $line;

                        $arrDependentReadableInfo[$relationship][$line] = $arrReadableDependentInfo;
                        $arrDependentReadableInfo[$relationship][$line]['changed'] = $booValueChanged;
                        $arrDependentReadableInfo[$relationship][$line]['line'] = $line;
                        $line++;
                    }

                    $arrClientInfo['dependents'] = $arrDependentReadableInfo;
                }


                ################    UPDATE STATIC FIELDS    ################

                if (count($arrErrors) == 0) {
                    if (!$booTransactionStarted) {
                        $this->_db2->getDriver()->getConnection()->beginTransaction();
                        $booTransactionStarted = true;
                    }

                    if ($booCreateCase) {
                        //update members details
                        $member_type = Members::getMemberType('client');

                        // Case's division group id must be the same as the parent has
                        $divisionGroupId = $arrMemberInfo['division_group_id'] ?? $divisionGroupId;

                        $memberFields = array_merge(
                            $memberFields,
                            array(
                                'company_id'        => $companyId,
                                'division_group_id' => $divisionGroupId,
                                'userType'          => $member_type[0],
                                'regTime'           => time(),
                                'status'            => 1
                            )
                        );

                        $caseId = $this->_db2->insert('members', $memberFields);

                        // if company has only 1 T/A, create accounting with this T/A for client
                        $this->createClientTAIfCompanyHasOneTA($caseId, $companyId);

                        $parentClientCasesCount = $this->getClientCaseMaxNumber($applicantId, false);
                        $parentClientCasesCount = empty($parentClientCasesCount) ? 1 : $parentClientCasesCount + 1;

                        $parentEmployerCasesCount = null;
                        if (!empty($employerClientId)) {
                            $parentEmployerCasesCount = $this->getClientCaseMaxNumber($employerClientId, true);
                            $parentEmployerCasesCount = empty($parentEmployerCasesCount) ? 1 : $parentEmployerCasesCount + 1;
                        }

                        $casesCountInCompany = $this->getCompanyCaseMaxNumber($companyId);
                        $casesCountInCompany = empty($casesCountInCompany) ? 1 : $casesCountInCompany + 1;

                        $casesCountWithSameCaseTypeInCompany = null;

                        if (array_key_exists('client_type_id', $clientFields) && !empty($clientFields['client_type_id'])) {
                            $casesCountWithSameCaseTypeInCompany = $this->getCompanyCaseMaxNumber($companyId, $clientFields['client_type_id']);
                            $casesCountWithSameCaseTypeInCompany = empty($casesCountWithSameCaseTypeInCompany) ? 1 : $casesCountWithSameCaseTypeInCompany + 1;
                        }

                        //update client details
                        $clientFields = array_merge(
                            $clientFields,
                            array(
                                'member_id'                                  => $caseId,
                                'added_by_member_id'                         => $currentMemberId,
                                'case_number_of_parent_client'               => $parentClientCasesCount,
                                'case_number_of_parent_employer'             => $parentEmployerCasesCount,
                                'case_number_in_company'                     => $casesCountInCompany,
                                'case_number_with_same_case_type_in_company' => $casesCountWithSameCaseTypeInCompany
                            )
                        );

                        $this->_db2->insert('clients', $clientFields);
                    } else {
                        //update members details
                        if (!empty($memberFields)) {
                            $this->_db2->update('members', $memberFields, ['member_id' => $caseId]);
                        }

                        //update client details
                        if ($booEmptyCaseCreated) {
                            if (array_key_exists('client_type_id', $clientFields) && !empty($clientFields['client_type_id'])) {
                                $casesCountWithSameCaseTypeInCompany = $this->getCompanyCaseMaxNumber($companyId, $clientFields['client_type_id']);
                                $casesCountWithSameCaseTypeInCompany = empty($casesCountWithSameCaseTypeInCompany) ? 1 : $casesCountWithSameCaseTypeInCompany + 1;

                                $clientFields['case_number_with_same_case_type_in_company'] = $casesCountWithSameCaseTypeInCompany;
                            }
                        } else {
                            $clientFields['modified_by'] = $currentMemberId;
                            $clientFields['modified_on'] = date('Y-m-d H:i:s');

                            $applicantUpdatedOnTime = $clientFields['modified_on'];
                        }

                        if (!empty($clientFields)) {
                            $this->_db2->update('clients', $clientFields, ['member_id' => $caseId]);
                        }
                    } //if mode edit


                    // Assign case to the parent applicant
                    $arrAssignData = array(
                        'applicant_id' => $applicantId,
                        'case_id'      => $caseId,
                    );
                    $this->assignCaseToApplicant($arrAssignData);

                    //################  UPDATE DIVISIONS ###################################
                    if (isset($division)) {
                        $this->updateApplicantOffices($caseId, array($division));
                    }

                    //##############    UPDATE CASE FTP FOLDERS ###################
                    if ($booCreateCase) {
                        $booLocal = $this->_company->isCompanyStorageLocationLocal($companyId);
                        //create
                        $this->_files->mkNewMemberFolders(
                            $caseId,
                            $companyId,
                            $booLocal
                        );
                    }

                    //###################  UPDATE FIELDS ############################
                    $arrCaseUpdate = array();

                    foreach ($fields as $field) {
                        if (!$this->getFields()->isStaticField($field['company_field_id']) && $field['status'] == 'F') {
                            //get new value
                            $new_value = array_key_exists($field['field_id'], $arrCaseFields) ? $arrCaseFields[$field['field_id']] : '';

                            // format date (if field is datefield)
                            if (!empty($new_value) && $this->getFieldTypes()->isDateField($field['type'])) {
                                if (is_array($new_value)) {
                                    foreach ($new_value as &$valData1) {
                                        $valData1 = $this->_settings->reformatDate($valData1, $dateFormatFull, Settings::DATE_UNIX);
                                    }
                                    unset($valData1);
                                } else {
                                    $new_value = $this->_settings->reformatDate($new_value, $dateFormatFull, Settings::DATE_UNIX);
                                }
                            }

                            // Fix to save 'Active', even when checkbox is checked
                            if ($field['company_field_id'] == 'Client_file_status') {
                                if (is_array($new_value)) {
                                    foreach ($new_value as &$new_value_data) {
                                        if ($new_value_data === 'on') {
                                            $new_value_data = 'Active';
                                        }
                                    }
                                    unset($new_value_data);
                                } elseif ($new_value === 'on') {
                                    $new_value = 'Active';
                                }
                            }


                            //get  modified date fields
                            if ($this->getFieldTypes()->isDateField($field['type']) && $field['value'] != $new_value) {
                                $modifiedFields[$field['field_id']] = is_array($new_value) && count($new_value) ? $new_value[0] : $new_value;
                            }

                            // get image fields
                            if ($field['type'] == $this->getFieldTypes()->getFieldTypeId('image')) {
                                $fullFieldId = '';
                                foreach ($arrReceivedFiles as $fileId => $arrFiles) {
                                    if (preg_match('/^field_file_case_([\d]+)_([\d]+)$/i', $fileId, $regs)) {
                                        if ($regs[2] == $field['field_id']) {
                                            $fullFieldId = $fileId;
                                            break;
                                        }
                                    }
                                }

                                if (!empty($fullFieldId) && isset($arrReceivedFiles[$fullFieldId]) && file_exists($arrReceivedFiles[$fullFieldId]['tmp_name'][0])) {
                                    // get image size
                                    $fieldDefaults = $this->getFields()->getFieldsOptions(array($field['field_id']));
                                    $size = array();
                                    if (!empty($fieldDefaults)) {
                                        $size = array(
                                            $fieldDefaults[0]['value'],
                                            $fieldDefaults[1]['value']
                                        );
                                    }

                                    // save image
                                    $result = $this->_files->saveImage($this->_files->getPathToClientImages($companyId, $caseId, $booLocal), $fullFieldId, 'field-' . $field['field_id'], $size, $booLocal, 0, false, true);
                                    if ($result && empty($result['error'])) {
                                        $new_value = $result['result'];

                                        $imageFieldsToUpdate[] = array(
                                            'applicant_id' => $caseId,
                                            'field_id' => $field['field_id'],
                                            'full_field_id' => $fullFieldId
                                        );

                                        $arrAllFieldsChangesData[$caseId]['arrNewFiles'][] = array(
                                            'field_id' => $field['field_id'],
                                            'filename' => $new_value,
                                        );
                                    } else {
                                        $this->_files->deleteClientImage($companyId, $caseId, $booLocal, $fullFieldId);
                                    }
                                } elseif (!$booCreateCase && !$booEmptyCaseCreated) {
                                    // only save real file name if new image is not attached but old one already exists
                                    $new_value = $field['value'];
                                }
                            } elseif ($field['type'] == $this->getFieldTypes()->getFieldTypeId('file')) {
                                $fileId = '';

                                foreach ($arrApplicantFields as $applicantGroup) {
                                    foreach ($applicantGroup as $key => $applicantField) {
                                        if ($key == $field['field_id']) {
                                            $fileId = $applicantField['file_id'][0];
                                        }
                                    }
                                }

                                if (!empty($fileId) && file_exists($_FILES[$fileId]['tmp_name'][0])) {
                                    $fileName = 'field-' . $field['field_id'];
                                    $result = $this->_files->saveClientFile(
                                        $this->_files->getPathToClientFiles($companyId, $caseId, $booLocal),
                                        $fileId,
                                        $fileName,
                                        $booLocal,
                                        0
                                    );
                                    $new_value = '';

                                    if ($result && $result['success']) {
                                        $new_value = $_FILES[$fileId]['name'][0];

                                        $fileFieldsToUpdate[] = array(
                                            'applicant_id' => $caseId,
                                            'field_id' => $field['field_id'],
                                            'full_field_id' => $fileId,
                                            'filename' => $new_value
                                        );

                                        $arrAllFieldsChangesData[$caseId]['arrNewFiles'][] = array(
                                            'field_id' => $field['field_id'],
                                            'filename' => $new_value,
                                        );
                                    } else {
                                        $this->_files->deleteClientFile($companyId, $caseId, $booLocal, $fileName);
                                    }
                                }
                            } elseif ($field['type'] == $this->getFieldTypes()->getFieldTypeId('multiple_text_fields') || ($field['type'] == $this->getFieldTypes()->getFieldTypeId('reference') && $field['multiple_values'] == 'Y')) {
                                if (!empty($new_value)) {
                                    $new_value = Json::encode($new_value);
                                }
                            }

                            $arrCaseUpdate[$field['field_id']] = is_array($new_value) ? $new_value[0] : $new_value;

                            if ($field['company_field_id'] == 'file_status' && !empty($new_value)) {
                                $fileStatusOldValue = $this->getFields()->getFieldDataValue($field['field_id'], $caseId);
                                $fileStatusNewValue = is_array($new_value) ? $new_value[0] : $new_value;
                            }

                            if ($field['company_field_id'] == 'cbiu_investment_type' && !empty($new_value)) {
                                $investmentTypeOldValue = $this->getFields()->getFieldDataValue($field['field_id'], $caseId);
                                $investmentTypeNewValue = is_array($new_value) ? $new_value[0] : $new_value;
                            }
                        }
                    }

                    // Update client data
                    $arrUpdateCaseResult = $this->saveClientData($caseId, $arrCaseUpdate);
                    if (!$arrUpdateCaseResult['success']) {
                        $arrErrors[] = $this->_tr->translate('Internal error during case saving.');
                    }

                    $arrAllFieldsChangesData[$caseId]['booIsApplicant'] = false;
                    $arrAllFieldsChangesData[$caseId]['arrOldData']     = $arrAllFieldsChangesData[$caseId]['arrOldData'] ?? array();
                    $arrAllFieldsChangesData[$caseId]['arrOldData']     = array_merge($arrAllFieldsChangesData[$caseId]['arrOldData'], $arrUpdateCaseResult['arrOldData']);

                    $arrAllFieldsChangesData[$caseId]['arrNewData'] = $arrAllFieldsChangesData[$caseId]['arrNewData'] ?? array();
                    $arrAllFieldsChangesData[$caseId]['arrNewData'] = array_merge($arrAllFieldsChangesData[$caseId]['arrNewData'], $arrUpdateCaseResult['arrNewData']);

                    //####################    UPDATE DEPENDANTS  ########################################
                    if (!count($arrErrors) && $booDependentsFullAccess) {
                        $res = $this->getFields()->updateDependentInfo($caseId, $arrClientUpdateDependants, $arrReceivedFiles, $arrDependentsIdsFromGui);
                        $arrCreatedFilesDependentIds = $res['arrCreatedFilesDependentIds'];
                        if (!$res['success']) {
                            $arrErrors[] = $this->_tr->translate('Internal error during dependent(s) saving.');
                        } else {
                            $arrAllFieldsChangesData[$caseId]['arrOldDependants'] = $arrClientSavedDependents;
                            $arrAllFieldsChangesData[$caseId]['arrNewDependants'] = $arrClientUpdateDependants;

                            $arrDependents = $this->getDependentsByMemberId($caseId);
                        }
                    }

                    //##############  Update xfdf files related to this client #############################
                    if (!count($arrErrors)) {
                        $memberTypeId = $this->getMemberTypeByMemberId($applicantId);
                        $this->_pdf->updateXfdfOnProfileUpdate($companyId, $caseId, $arrClientInfo, $memberTypeId);
                    }

                    $arrCaseInfo = $this->getClientInfo($caseId);
                    $caseName = !empty($arrCaseInfo) ? $arrCaseInfo['full_name_with_file_num'] : '';
                } //if(count(error)==0)

                if (empty($strError) && count($arrErrors)) {
                    $prefix = count($arrErrors) > 1 ? '*' : '';
                    foreach ($arrErrors as $strErrorInfo) {
                        $strError .= $prefix . ' ' . $strErrorInfo . '</br>';
                    }
                }

                if (empty($strError) && !empty($caseId)) {
                    // Assign case to the provided Employer id
                    if (!empty($employerId)) {
                        $arrAssignData = array(
                            'applicant_id' => $employerId,
                            'case_id'      => $caseId,
                        );
                        $this->assignCaseToApplicant($arrAssignData);
                    }

                    // Automatically link to the provided case if needed
                    if (!empty($caseIdLinkedTo)) {
                        list(, $strMessage, $linkedEmployerId) = $this->linkCaseToCase(true, $caseIdLinkedTo, $caseId, false);
                        if (empty($strMessage) && !empty($linkedEmployerId)) {
                            $employerId = $linkedEmployerId;
                        }
                    }

                    // Mark this case as viewed
                    $arrParentClients = $this->getParentsForAssignedApplicant($caseId);
                    $this->saveLastViewedClient($currentMemberId, $caseId, $arrParentClients);
                }
            }

            $arrAssignedCasesIds = array();
            if (empty($strError)) {
                if (!empty($caseId)) {
                    // Update case's offices from parent records
                    if (!$this->updateCaseOfficesFromParent(array($caseId))) {
                        $strError = $this->_tr->translate('Internal error happened during case offices updating.');
                    }
                } else {
                    // Update all cases' offices of the current parent
                    $arrAssignedCasesIds = $this->getAssignedCases($applicantId);
                    $arrAssignedCasesIds = array_unique($arrAssignedCasesIds);
                    if (!$this->updateCaseOfficesFromParent($arrAssignedCasesIds)) {
                        $strError = $this->_tr->translate('Internal error happened during child cases offices updating.');
                    }
                }
            }

            // On new case creation we need save 'office changed' field's value + save note
            if (empty($strError) && $booCreateCase && !empty($applicantId) && !empty($caseId)) {
                // Load all parents for this case
                $arrParentMemberIds = $this->getParentsForAssignedApplicant(array($caseId));

                // Load assigned offices for all parents
                $arrAllOffices = $this->getApplicantOffices($arrParentMemberIds, $divisionGroupId);

                // Update offices for all parent's internal contacts
                $arrContacts = $this->getAssignedContacts($applicantId);
                $arrContactsProcessed = array();
                foreach ($arrContacts as $arrContactInfo) {
                    if (!in_array($arrContactInfo['child_member_id'], $arrContactsProcessed)) {
                        $this->updateApplicantOffices($arrContactInfo['child_member_id'], $arrAllOffices);
                        $arrContactsProcessed[] = $arrContactInfo['child_member_id'];
                    }
                }
            }

            if (empty($strError)) {
                $memberTypeId = $this->getMemberTypeByMemberId($applicantId);
                $arrAllApplicantFieldsData = $this->getAllApplicantFieldsData($applicantId, $memberType);
                $arrRowIds = $arrAllApplicantFieldsData[1];

                if (!empty($caseId)) {
                    // Get saved Case Category for this case
                    $categoriesFieldId = $this->getFields()->getFieldIdByType($companyId, 'categories');
                    if (!empty($categoriesFieldId)) {
                        $caseCategory = $this->getFields()->getFieldDataValue($categoriesFieldId[0], $caseId);
                    }
                }

                if ($booCreateCase || $booEmptyCaseCreated) {
                    // Load info about the parent client + his internal contacts
                    $arrParents = $this->getParentsForAssignedApplicants(array($caseId), false, false);

                    foreach ($arrParents as $parent) {
                        $arrInternalContactIds = $this->getAssignedApplicants($parent['parent_member_id'], $this->getMemberTypeIdByName('internal_contact'));
                        $arrApplicantIds = array_unique(array_merge(array($parent['parent_member_id']), $arrInternalContactIds));
                        $arrApplicantSavedData = $this->getApplicantData($companyId, $arrApplicantIds, false, true, false, true);

                        $arrApplicantInfoToSave = array();
                        foreach ($arrApplicantSavedData as $arrSavedData) {
                            $fieldName = $arrSavedData['applicant_field_unique_id'];
                            if ($this->getFields()->isStaticField($fieldName)) {
                                $fieldName = $this->getFields()->getStaticColumnName($fieldName);
                            }

                            $arrApplicantInfoToSave[$fieldName] = $arrSavedData['value'];
                        }

                        $this->_pdf->updateXfdfOnProfileUpdate($companyId, $caseId, $arrApplicantInfoToSave, $memberTypeId);
                    }
                } elseif ($memberType != 'case' && count($arrAssignedCasesIds)) {
                    foreach ($arrAssignedCasesIds as $assignedCaseId) {
                        $this->_pdf->updateXfdfOnProfileUpdate($companyId, $assignedCaseId, $arrApplicantInfoToSave, $memberTypeId);
                    }
                }
            }

            if (empty($strError) && !empty($employerId)) {
                $arrEmployerInfo = $this->getMemberInfo($employerId);
                $employerName = $this->generateApplicantName($arrEmployerInfo);
            }

            if (empty($strError) && ($booCreateCase || $booEmptyCaseCreated)) {
                $canClientLogin        = $this->_company->getPackages()->canCompanyClientLogin($companyId);
                $booShowWelcomeMessage = $canClientLogin && !$booAssignedCasesExist && !$booEmailWasBlank && !$booEmptyLogin && !$booEmptyPass;
            }

            // Mark this client as viewed if no cases are assigned to the client
            if (empty($strError) && !empty($applicantId) && empty($arrAssignedCasesIds)) {
                $this->saveLastViewedClient($currentMemberId, $applicantId);
            }

            if (empty($strError) && !empty($applicantId) && !$this->updateCaseEmailFromParent($applicantId)) {
                $strError = $this->_tr->translate('Internal error happened during child cases email addresses updating.');
            }

            // Assign specific form to the case (the same form can be assigned only once)
            if (empty($strError) && $booApi && $booCreateCase && !empty($caseId)) {
                $arrCaseTemplates = $this->getCaseTemplates()->getCasesTemplates(array($caseId));
                $arrCaseFormVersionIds = !isset($arrCaseTemplates[$caseId]) ? array() : $this->getCaseTemplates()->getCaseTemplateForms($arrCaseTemplates[$caseId], false);

                if (!empty($arrCaseFormVersionIds)) {
                    foreach ($arrCaseFormVersionIds as $caseFormVersionId) {
                        $this->_forms->getFormAssigned()->assignFormToCase(
                            $caseId,
                            'main_applicant',
                            array('pform' . $caseFormVersionId), // In the same format as it is received from GUI
                            '',
                            $currentMemberId
                        );
                    }
                }
            }

            if (empty($strError)) {
                if ($booTransactionStarted) {
                    $this->_db2->getDriver()->getConnection()->commit();
                    $booTransactionStarted = false;
                }

                if ($booUpdateCaseNumberSettings) {
                    $arrCompanySettings['cn-start-number-from-text'] = $startCaseNumberFrom;
                    $this->getCaseNumber()->saveCaseNumberSettings($companyId, $arrCompanySettings);
                }

                if (!empty($caseId) && !empty($investmentTypeNewValue) && $investmentTypeOldValue != $investmentTypeNewValue) {
                    $this->getAccounting()->regenerateCompanyAgentPayments($companyId, $caseId, $caseTemplateId);
                }

                if (!empty($caseId)) {
                    // Load linked case's type - will be used to identify the label for link/unlink button and related places
                    $employerLinkCaseId = $this->getCaseLinkedEmployerCaseId($caseId);
                    if (!empty($employerLinkCaseId)) {
                        $arrEmployerLinkCaseInfo      = $this->getClientInfoOnly($employerLinkCaseId);
                        $employerCaseLinkedCaseTypeId = $arrEmployerLinkCaseInfo['client_type_id'];
                    }
                }

                if ($booCreateCase || $booEmptyCaseCreated) {
                    // Call trigger 'Case Creation'
                    $this->_systemTriggers->triggerCaseCreation(
                        $companyId,
                        $caseId,
                        $currentMemberId,
                        $this->_auth->hasIdentity() ? $this->_auth->getIdentity()->full_name : ''
                    );
                }

                if ($booUpdateCase) {
                    $booValueChanged = false;
                    if (!empty($fileStatusOldValue) && !empty($fileStatusNewValue) && $fileStatusOldValue != $fileStatusNewValue) {
                        $booValueChanged = true;
                    } elseif (!empty($fileStatusOldValue) && empty($fileStatusNewValue)) {
                        $booValueChanged = true;
                    } elseif (empty($fileStatusOldValue) && !empty($fileStatusNewValue)) {
                        $booValueChanged = true;
                    }

                    // Call trigger 'Case Status changed'
                    if ($booValueChanged) {
                        $this->_systemTriggers->triggerFileStatusChanged(
                            $caseId,
                            $this->getCaseStatuses()->getCaseStatusesNames($fileStatusOldValue),
                            $this->getCaseStatuses()->getCaseStatusesNames($fileStatusNewValue),
                            $currentMemberId,
                            $this->_auth->hasIdentity() ? $this->_auth->getIdentity()->full_name : ''
                        );
                    }

                    // Update tasks based on profile date fields
                    if (!empty($modifiedFields)) {
                        $this->_systemTriggers->triggerClientDateFieldsChanged($caseId, $modifiedFields);
                    }
                }

                // Log these changes
                $this->_systemTriggers->triggerFieldBulkChanges($companyId, $arrAllFieldsChangesData, true);

                if (count($arrAllFieldsChangesData)) {
                    $memberId = $memberType == 'case' && !empty($caseId) ? $caseId : $applicantId;
                    if (!empty($memberId)) {
                        // Call trigger 'Profile Update'
                        $this->_systemTriggers->triggerProfileUpdate($companyId, $memberId);
                    }
                }
            }

            $booAllowEditApplicant = empty($applicantId) ? true : $oCompanyDivisions->canCurrentMemberEditClient($applicantId);
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError) && $booTransactionStarted) {
            $this->_db2->getDriver()->getConnection()->rollback();
        }

        if (!empty($companyId) && !empty($caseReferenceNumber)) {
            // If case file number was reserved, we try to release it
            $this->getCaseNumber()->releaseFileNumber($companyId, $caseReferenceNumber);
        }

        return array(
            'success'                     => empty($strError),
            'error_type'                  => empty($strError) ? '' : $strErrorType,
            'message'                     => empty($strError) ? $this->_tr->translate('Done.') : $strError,
            'applicantId'                 => $applicantId,
            'applicantName'               => $applicantName,
            'memberType'                  => $parentMemberType,
            'applicantOffices'            => implode(',', $arrApplicantNewOffices),
            'applicantOfficeFields'       => $arrApplicantOfficeFieldIdsWithGroups,
            'caseId'                      => $caseId,
            'caseName'                    => $caseName,
            'caseType'                    => $caseTemplateId,
            'caseCategory'                => $caseCategory,
            'caseEmployerId'              => $employerId,
            'caseEmployerName'            => $employerName,
            'applicantUpdatedOn'          => $applicantUpdatedOn,
            'applicantUpdatedOnTime'      => $applicantUpdatedOnTime,
            'changeOfficeFieldToUpdate'   => $changeOfficeFieldToUpdate,
            'fileFieldsToUpdate'          => $fileFieldsToUpdate,
            'imageFieldsToUpdate'         => $imageFieldsToUpdate,
            'arrErrorFields'              => $arrErrorFields,
            'rowIds'                      => $arrRowIds,
            'booShowWelcomeMessage'       => $booShowWelcomeMessage,
            'applicantEncodedPassword'    => !empty($password) ? $this->_encryption->encode($password) : '',
            'generatedUsername'           => $generatedUsername,
            'caseReferenceNumber'         => $caseReferenceNumber,
            'booAllowEditApplicant'       => $booAllowEditApplicant,
            'arrDependents'               => $arrDependents,
            'arrCreatedFilesDependentIds' => $arrCreatedFilesDependentIds,
            'employerCaseLinkedCaseType'  => $employerCaseLinkedCaseTypeId,
        );
    }

    public function calculateAutoCalcSum($arrFieldsOptions, $arrClientOrCaseData, $fieldId)
    {
        $sum = 0;
        foreach ($arrFieldsOptions[$fieldId] as $arrLinkedFieldInfo) {
            foreach ($arrClientOrCaseData as $key => $arrData) {
                if ($arrData['field_unique_id'] == $arrLinkedFieldInfo['option_name']) {
                    if ($arrData['field_type'] == 'auto_calculated' && !isset($arrClientOrCaseData[$key]['calculated'])) {
                        list($arrClientOrCaseData[$key]['value'], $arrClientOrCaseData[$key]['calculated']) = $this->calculateAutoCalcSum($arrFieldsOptions, $arrClientOrCaseData, $arrData['field_id']);
                    }
                    $sum += (int)$arrClientOrCaseData[$key]['value'];
                    break;
                }
            }
        }
        return array($sum, true);
    }

    /**
     * Analyze client's data and generate the Client Profile ID (if not generated before)
     *
     * @param bool $booCreateClient
     * @param int $companyId
     * @param array $arrData
     * @param string|null $newClientIdSequence
     * @return array
     */
    private function generateClientProfileIdValue($booCreateClient, $companyId, $arrData, $newClientIdSequence)
    {
        $booUpdateClientIdSequence       = false;
        $arrSavedClientProfileIdSettings = null;

        foreach ($arrData as $key => $arrFieldValueInfo) {
            if ($arrFieldValueInfo['field_type'] == 'client_profile_id') {
                if ($booCreateClient) {
                    // Load settings for Client Profile ID generation only once
                    if (is_null($arrSavedClientProfileIdSettings)) {
                        $arrSavedClientProfileIdSettings = $this->_company->getCompanyClientProfileIdSettings($companyId);
                    }

                    if ($arrSavedClientProfileIdSettings['enabled']) {
                        list($newClientIdSequence, $strGeneratedClientProfileId) = $this->_company->generateClientProfileIdFromFormat($arrSavedClientProfileIdSettings, $newClientIdSequence);

                        $arrData[$key]['value'] = $strGeneratedClientProfileId;

                        $booUpdateClientIdSequence = true;
                    } else {
                        // Generation is disabled in company settings
                        unset($arrData[$key]);
                    }
                } else {
                    // We don't want to update it
                    unset($arrData[$key]);
                }
            }
        }

        return [$arrData, $booUpdateClientIdSequence, $newClientIdSequence];
    }

    /**
     * Get list of passed fields, find 'auto calculated' fields.
     * Recalculate sum for each of them.
     * If some field value wasn't passed - try to load it from DB
     *
     * @param int $memberId
     * @param array $arrClientOrCaseData
     * @param array $arrAllFields
     * @param array $arrFieldsOptions
     * @return array
     */
    private function _recalculateAutoCalcFields($memberId, $arrClientOrCaseData, $arrAllFields = null, $arrFieldsOptions = null)
    {
        $arrMemberInfo = $this->getMemberInfo($memberId);
        if (!isset($arrMemberInfo['userType'])) {
            return $arrClientOrCaseData;
        }

        // Identify if this is a case - load fields/option in other way than for client
        $booIsCase = in_array($arrMemberInfo['userType'], Members::getMemberType('case'));

        // Load fields list, if not passed
        if (is_null($arrAllFields)) {
            $arrClientInfo = $this->getClientInfoOnly($memberId);

            if ($booIsCase) {
                $arrAllFields = $this->getFields()->getGroupedCompanyFields($arrClientInfo['client_type_id']);
            } else {
                $arrAllFields = $this->getApplicantFields()->getGroupedCompanyFields(
                    $arrMemberInfo['company_id'],
                    $arrMemberInfo['userType'],
                    $arrClientInfo['applicant_type_id']
                );
            }
        }

        // Load options list for all fields, if not passed
        if (is_null($arrFieldsOptions)) {
            if ($booIsCase) {
                $arrFieldsOptions = $this->getFields()->getGroupedFieldsOptions(array($arrAllFields));
            } else {
                $arrFieldIds = array();
                foreach ($arrAllFields as $arrGroupInfo) {
                    if (isset($arrGroupInfo['fields'])) {
                        foreach ($arrGroupInfo['fields'] as $arrFieldInfo) {
                            $arrFieldIds[] = $arrFieldInfo['field_id'];
                        }
                    }
                }

                $arrFieldsOptions = $this->getApplicantFields()->getGroupedFieldsOptions($arrFieldIds, true);
            }
        }

        if (is_array($arrFieldsOptions) && count($arrFieldsOptions)) {
            // Search for auto calc fields and calculate sum for provided data
            $arrAutoCalcFieldsSum = array();
            $arrNotFoundLinkedFields = array();
            foreach ($arrClientOrCaseData as $arrDataToSave) {
                if ($arrDataToSave['field_type'] != 'auto_calculated' || !isset($arrFieldsOptions[$arrDataToSave['field_id']])) {
                    continue;
                }

                if (!isset($arrAutoCalcFieldsSum[$arrDataToSave['field_id']])) {
                    $arrAutoCalcFieldsSum[$arrDataToSave['field_id']] = 0;
                }

                foreach ($arrFieldsOptions[$arrDataToSave['field_id']] as $arrLinkedFieldInfo) {
                    $booFoundLinkedField = false;
                    foreach ($arrClientOrCaseData as $key2 => $arrDataToSave2) {
                        if ($arrDataToSave2['field_unique_id'] == $arrLinkedFieldInfo['option_name']) {
                            if ($arrDataToSave2['field_type'] == 'auto_calculated' && !isset($arrClientOrCaseData[$key2]['calculated'])) {
                                list($arrClientOrCaseData[$key2]['value'], $arrClientOrCaseData[$key2]['calculated']) = $this->calculateAutoCalcSum($arrFieldsOptions, $arrClientOrCaseData, $arrDataToSave2['field_id']);
                            }
                            $arrAutoCalcFieldsSum[$arrDataToSave['field_id']] += (int)$arrClientOrCaseData[$key2]['value'];
                            $booFoundLinkedField = true;
                            break;
                        }
                    }

                    // Remember this field, maybe it is read only and wasn't received
                    if (!$booFoundLinkedField && !in_array($arrLinkedFieldInfo['option_name'], $arrNotFoundLinkedFields)) {
                        $arrNotFoundLinkedFields[] = $arrLinkedFieldInfo['option_name'];
                    }
                }
            }

            // Some fields can be read only, so load them from DB and calculate again
            if (!empty($memberId) && count($arrNotFoundLinkedFields)) {
                // Get ids of not found linked fields
                $arrNotFoundLinkedFieldIds = array();
                foreach ($arrAllFields as $arrBlockInfo) {
                    if (isset($arrBlockInfo['fields']) && is_array($arrBlockInfo['fields'])) {
                        foreach ($arrBlockInfo['fields'] as $arrFieldInfo) {
                            if (in_array($arrFieldInfo['field_unique_id'], $arrNotFoundLinkedFields)) {
                                $arrNotFoundLinkedFieldIds[] = $arrFieldInfo['field_id'];
                            }
                        }
                    }
                }

                if (count($arrNotFoundLinkedFieldIds)) {
                    // Load saved data for those linked not found fields
                    if ($booIsCase) {
                        $arrAlreadySavedData = $this->getFields()->getClientFieldsValues($memberId, $arrNotFoundLinkedFieldIds);
                        foreach ($arrAlreadySavedData as $key => $arrAlreadySavedFieldData) {
                            $arrAlreadySavedData[$key]['field_unique_id'] = $arrAlreadySavedFieldData['company_field_id'];
                        }
                    } else {
                        $arrAlreadySavedData = $this->getApplicantFields()->getApplicantFieldsData($memberId, $arrNotFoundLinkedFieldIds);
                        foreach ($arrAlreadySavedData as $key => $arrAlreadySavedFieldData) {
                            $arrAlreadySavedData[$key]['field_unique_id'] = $arrAlreadySavedFieldData['applicant_field_unique_id'];
                        }
                    }

                    if (count($arrAlreadySavedData)) {
                        foreach ($arrClientOrCaseData as $arrDataToSave) {
                            if ($arrDataToSave['field_type'] != 'auto_calculated' || !isset($arrFieldsOptions[$arrDataToSave['field_id']])) {
                                continue;
                            }

                            foreach ($arrFieldsOptions[$arrDataToSave['field_id']] as $arrLinkedFieldInfo) {
                                foreach ($arrAlreadySavedData as $arrAlreadySavedFieldData) {
                                    if ($arrAlreadySavedFieldData['field_unique_id'] == $arrLinkedFieldInfo['option_name']) {
                                        $arrAutoCalcFieldsSum[$arrDataToSave['field_id']] += (int)$arrAlreadySavedFieldData['value'];
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Update data with recalculated values
            foreach ($arrAutoCalcFieldsSum as $autoCalcFieldId => $autoCalcFieldVal) {
                foreach ($arrClientOrCaseData as $key => $arrDataToSave) {
                    if ($arrDataToSave['field_id'] == $autoCalcFieldId) {
                        unset($arrDataToSave['calculated']);
                        $arrDataToSave['value'] = $autoCalcFieldVal;
                        $arrClientOrCaseData[$key] = $arrDataToSave;
                        break;
                    }
                }
            }
        }

        return $arrClientOrCaseData;
    }


    /**
     * Update offices for specific clients in the company
     * For each received client id - get type and for:
     * Case     -> load parent IA/Employer or IA+Employer
     * IA       -> load assigned internal contact(s) + all cases
     * Employer -> load assigned internal contact(s) + IA + IA's internal contact(s) + all cases
     * Contact  -> load assigned internal contact(s)
     * Internal Contact -> load parents and for each of them - load all assigned (as described above)
     *
     * @param int $companyId
     * @param array $arrClientIds
     * @param array $arrOfficeIds
     * @param int $loggedInMemberId
     * @param null $divisionGroupId
     * @param bool $booTriggerAutomaticTasks
     * @param bool $booLogChanges
     * @return array
     */
    public function updateClientsOffices($companyId, $arrClientIds, $arrOfficeIds, $loggedInMemberId = null, $divisionGroupId = null, $booTriggerAutomaticTasks = true, $booLogChanges = true)
    {
        try {
            $arrInfoToLog = array();
            $arrCaseFields = $this->getFields()->getCompanyFields($companyId);
            $arrCaseOfficeTypes = array(
                $this->getFieldTypes()->getFieldTypeId('office'),
                $this->getFieldTypes()->getFieldTypeId('office_multi')
            );

            foreach ($arrClientIds as $clientId) {
                $arrMemberInfo = $this->getMemberInfo($clientId);
                if (!is_array($arrMemberInfo) || !count($arrMemberInfo)) {
                    continue;
                }

                $arrParentIds = array();
                if (in_array($arrMemberInfo['userType'], Members::getMemberType('case'))) {
                    // This is a Case - get parents (can be IA/Employer or IA+Employer)
                    $arrParents = $this->getParentsForAssignedApplicants(array($clientId), false, false);
                    foreach ($arrParents as $arrLinkInfo) {
                        $arrParentIds[] = $arrLinkInfo['parent_member_id'];
                    }
                } elseif (in_array($arrMemberInfo['userType'], Members::getMemberType('client'))) {
                    // This is not a Case, but can be IA/Employer/Contact
                    $arrParentIds[] = $clientId;
                } elseif (in_array($arrMemberInfo['userType'], Members::getMemberType('internal_contact'))) {
                    // This is Internal Contact - get real parent(s)
                    $arrParentIds = $this->getParentsForAssignedApplicant($clientId);
                } else {
                    // Hmmm, something interesting. Lets skip it.
                    continue;
                }

                // For all parents -> load assigned/related members
                $arrAllClientsToUpdate = array($clientId);
                if (!empty($arrParentIds)) {
                    foreach ($arrParentIds as $parentId) {
                        $arrAllClientsToUpdate[] = $parentId;
                    }

                    $arrAssignedApplicants = $this->getAssignedApplicants($arrParentIds);
                    foreach ($arrAssignedApplicants as $assignedApplicantId) {
                        $arrAllClientsToUpdate[] = $assignedApplicantId;
                    }


                    // Make sure that we'll load parent Employer/IA records (and all assigned internal contacts/cases for them) from loaded/found cases
                    if (!empty($arrAssignedApplicants) && !empty($this->_config['site_version']['keep_employer_and_applicant_in_one_office'])) {
                        $arrParentIds = $this->getParentsForAssignedApplicant($arrAssignedApplicants);
                        foreach ($arrParentIds as $parentId) {
                            $arrAllClientsToUpdate[] = $parentId;
                        }

                        $arrAssignedApplicants = $this->getAssignedApplicants($arrParentIds);
                        foreach ($arrAssignedApplicants as $assignedApplicantId) {
                            $arrAllClientsToUpdate[] = $assignedApplicantId;
                        }
                    }
                }
                $arrAllClientsToUpdate = Settings::arrayUnique($arrAllClientsToUpdate);

                // Update office!
                foreach ($arrAllClientsToUpdate as $clientIdToUpdate) {
                    $arrMemberInfo = $this->getMemberInfo($clientIdToUpdate);

                    // Update office field's value in the profile - in relation to the client's type
                    if (in_array($arrMemberInfo['userType'], Members::getMemberType('case'))) {
                        // Update Case's field's value
                        $arrFieldUpdate = array();
                        $arrNewData = array();
                        foreach ($arrCaseFields as $arrCaseFieldInfo) {
                            if (in_array($arrCaseFieldInfo['type'], $arrCaseOfficeTypes)) {
                                $arrFieldUpdate[$arrCaseFieldInfo['field_id']] = implode(',', $arrOfficeIds);

                                $arrNewData[] = array(
                                    'field_id' => $arrCaseFieldInfo['field_id'],
                                    'row' => 0,
                                    'value' => implode(',', $arrOfficeIds)
                                );
                            }
                        }

                        if (is_array($arrFieldUpdate) && count($arrFieldUpdate)) {
                            $arrOldData = $this->getFields()->getClientFieldsValues($clientIdToUpdate, array_keys($arrFieldUpdate));

                            $arrClientAndCaseInfo = array(
                                'member_id' => $clientIdToUpdate,
                                'case' => array(
                                    'members' => array(),
                                    'clients' => array(),
                                    'client_form_data' => $arrFieldUpdate
                                )
                            );
                            $this->updateClient($arrClientAndCaseInfo, $companyId, $arrCaseFields, 0, $loggedInMemberId);

                            // Prepare data for log
                            $arrInfoToLog[$clientIdToUpdate]['booIsApplicant'] = false;
                            $arrInfoToLog[$clientIdToUpdate]['arrOldData'] = $arrOldData;
                            $arrInfoToLog[$clientIdToUpdate]['arrNewData'] = $arrNewData;
                        }
                    } elseif (in_array($arrMemberInfo['userType'], Members::getMemberType('internal_contact'))) {
                        // For internal contact we need update parent's fields which are in contact blocks...
                        $internalClientParentIds = $this->getParentsForAssignedApplicant($clientIdToUpdate);
                        foreach ($internalClientParentIds as $internalClientParentId) {
                            $arrFieldUpdate = array();
                            $arrParentMemberInfo = $this->getMemberInfo($internalClientParentId);
                            $arrParentClientInfo = $this->getClientInfoOnly($internalClientParentId);

                            $arrCompanyFields = $this->getApplicantFields()->getCompanyFields(
                                $companyId,
                                $arrParentMemberInfo['userType'],
                                $arrParentClientInfo['applicant_type_id']
                            );
                            foreach ($arrCompanyFields as $arrCompanyFieldInfo) {
                                if ($arrCompanyFieldInfo['contact_block'] == 'Y' && in_array($arrCompanyFieldInfo['type'], array('office', 'office_multi'))) {
                                    $arrFieldUpdate[] = array(
                                        'field_id' => $arrCompanyFieldInfo['applicant_field_id'],
                                        'value' => implode(',', $arrOfficeIds),
                                        'row' => 0,
                                    );
                                }
                            }

                            if (is_array($arrFieldUpdate) && count($arrFieldUpdate)) {
                                $arrUpdateResult = $this->updateApplicantData($clientIdToUpdate, $arrFieldUpdate);

                                // Prepare data for log
                                if ($arrUpdateResult['success']) {
                                    $arrInfoToLog[$clientIdToUpdate]['booIsApplicant'] = true;
                                    $arrInfoToLog[$clientIdToUpdate]['arrOldData'] = $arrUpdateResult['arrOldData'];
                                    $arrInfoToLog[$clientIdToUpdate]['arrNewData'] = $arrFieldUpdate;
                                }
                            }
                        }
                    } else {
                        // Update Applicant's field's value
                        // for fields that are not in contact blocks
                        $arrFieldUpdate = array();
                        $arrClientInfo = $this->getClientInfoOnly($clientIdToUpdate);

                        $arrCompanyFields = $this->getApplicantFields()->getCompanyFields(
                            $companyId,
                            $arrMemberInfo['userType'],
                            $arrClientInfo['applicant_type_id']
                        );
                        foreach ($arrCompanyFields as $arrCompanyFieldInfo) {
                            if ($arrCompanyFieldInfo['contact_block'] == 'N' && in_array($arrCompanyFieldInfo['type'], array('office', 'office_multi'))) {
                                $arrFieldUpdate[] = array(
                                    'field_id' => $arrCompanyFieldInfo['applicant_field_id'],
                                    'value' => implode(',', $arrOfficeIds),
                                    'row' => 0,
                                );
                            }
                        }

                        if (is_array($arrFieldUpdate) && count($arrFieldUpdate)) {
                            $arrUpdateResult = $this->updateApplicantData($clientIdToUpdate, $arrFieldUpdate);

                            // Prepare data for log
                            if ($arrUpdateResult['success']) {
                                $arrInfoToLog[$clientIdToUpdate]['booIsApplicant'] = true;
                                $arrInfoToLog[$clientIdToUpdate]['arrOldData'] = $arrUpdateResult['arrOldData'];
                                $arrInfoToLog[$clientIdToUpdate]['arrNewData'] = $arrFieldUpdate;
                            }
                        }
                    }

                    // Update real office's value for each applicant/case
                    $this->updateApplicantOffices($clientIdToUpdate, $arrOfficeIds, true, true, false, 'access_to', null, $divisionGroupId);
                }

                if ($booTriggerAutomaticTasks) {
                    // Call 'Client/Case profile update' auto tasks
                    $this->_systemTriggers->triggerProfileUpdate($companyId, $clientId);
                }
            }

            // Log the changes
            if ($booLogChanges) {
                $this->_systemTriggers->triggerFieldBulkChanges($companyId, $arrInfoToLog, $booTriggerAutomaticTasks);
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $arrInfoToLog = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($booSuccess, $arrInfoToLog);
    }

    /**
     * Create/update IA and/or Case at once (with files, if needed)
     *
     * @param array $arrParams
     * @param array $arrReceivedFiles
     * @param bool $booAssignForm
     * @param Templates $templates
     * @return array
     * @throws Exception
     */
    public function createUpdateApplicantAndCase(array $arrParams, array $arrReceivedFiles, $booAssignForm, Templates $templates)
    {
        $strError    = '';
        $caseId      = 0;
        $applicantId = 0;
        $employerId  = 0;

        $caseReferenceNumber = '';

        try {
            // Load sync fields
            $arrFormSynFieldIds = array();
            $arrXfdfOnlyParams = array();
            $arrMappedParams = array();

            // Load Officio and sync fields - only they will be used
            // during IA/Case creation/update, other fields will be used in xfdf files only
            $strSyncFieldPrefix = Pdf::XFDF_FIELD_PREFIX;
            $strOfficioFieldPrefix = 'Officio_';
            $arrXfdfFieldsPrefixes = array(
                'NTNP_',
                'BCPNP_'
            );
            $arrSyncFormFields = array();
            foreach ($arrParams as $fieldId => $fieldVal) {
                $fieldId = trim($fieldId);

                // Check which field is related to:
                // - Sync field - field is in the mapping table
                // - Officio field, that is not in the mapping table, but must be provided
                // - Xfdf field only - will be saved in xfdf file(s) only
                if (preg_match("/^$strSyncFieldPrefix/", $fieldId)) {
                    $arrFormSynFieldIds[] = $fieldId;
                    $arrSyncFormFields[$fieldId] = $fieldVal;
                } elseif (preg_match("/^$strOfficioFieldPrefix(.*)/", $fieldId, $regs)) {
                    $arrMappedParams[$regs[1]] = $fieldVal;
                } else {
                    // Check if field + value must be saved in xfdf
                    $booXfdfField = false;

                    foreach ($arrXfdfFieldsPrefixes as $xfdfPrefix) {
                        if (preg_match("/^$xfdfPrefix/", $fieldId)) {
                            $booXfdfField = true;
                            break;
                        }
                    }
                    if ($booXfdfField) {
                        $arrSyncFormFields[$fieldId] = $fieldVal;

                        // Make sure that we'll have a value in a string format
                        $xfdfValue = $fieldVal;
                        if (!is_string($fieldVal)) {
                            if (is_array($fieldVal) && isset($fieldVal['name'])) {
                                $xfdfValue = $fieldVal['name'];
                            } else {
                                $xfdfValue = Json::encode($fieldVal);
                            }
                        }

                        $arrXfdfOnlyParams[] = array(
                            'fieldName'  => $fieldId,
                            'fieldValue' => $xfdfValue
                        );
                    }
                }
            }

            $arrMappedFields = array();
            if (count($arrFormSynFieldIds)) {
                $arrFormSynFieldIds = $this->_forms->getFormSynField()->getFieldIdsByNames($arrFormSynFieldIds);

                // Get mapped fields list
                $arrMappedFields = $this->_forms->getFormMap()->getMappedFieldsForFamilyMember('main_applicant', $arrFormSynFieldIds);
            }

            foreach ($arrMappedFields as $arrMappedFieldInfo) {
                $fieldId = $this->getFields()->getStaticColumnNameByFieldId($arrMappedFieldInfo['to_profile_field_id']);
                if (!$fieldId) {
                    $fieldId = $arrMappedFieldInfo['to_profile_field_id'];
                }

                $arrMappedParams[$fieldId] = $arrParams[$arrMappedFieldInfo['from_field_name']];
            }

            $dateFormatFull  = $this->_settings->variable_get('dateFormatFull');
            $companyId       = $this->_auth->getCurrentUserCompanyId();
            $booLocal        = $this->_company->isCompanyStorageLocationLocal($companyId);
            $divisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();

            $applicantId = empty($arrMappedParams['applicant_id']) || !is_numeric($arrMappedParams['applicant_id']) ? false : $arrMappedParams['applicant_id'];
            $caseId = empty($arrMappedParams['case_id']) || !is_numeric($arrMappedParams['case_id']) ? false : $arrMappedParams['case_id'];
            if (empty($strError) && !empty($applicantId) && !$this->hasCurrentMemberAccessToMember($applicantId)) {
                $strError = $this->_tr->translate('Insufficient access rights to applicant.');
            }

            // Create/update Employer (if needed) +IA + case
            $booCreateUpdateProfile = array_key_exists('update_profile', $arrMappedParams) ? (bool)$arrMappedParams['update_profile'] : false;
            if (empty($strError) && $booCreateUpdateProfile) {
                // Get incoming params, convert to acceptable format (as we simply pressed on Save on Client's profile)
                $memberType = array_key_exists('applicant_type', $arrMappedParams) ? $arrMappedParams['applicant_type'] : 'individual';
                if ($memberType === 'employer') {
                    $employerId = (array_key_exists('employer_id', $arrMappedParams) && is_numeric($arrMappedParams['employer_id'])) ? $arrMappedParams['employer_id'] : 0;
                    // create/update employer
                    $applicantType = 0;

                    $arrEmployerParams = array(
                        'applicantId' => $employerId,
                        'memberType' => $memberType,
                        'applicantType' => $applicantType,
                        'applicantUpdatedOn' => 0,
                        'forceOverwrite' => 1,

                        'caseId' => 0,
                        'caseType' => 0,
                        'caseEmployerId' => 0
                    );

                    $memberTypeId = $this->getMemberTypeIdByName($memberType);
                    $arrAllApplicantFields = $this->getApplicantFields()->getGroupedCompanyFields($companyId, $memberTypeId, $applicantType);
                    $arrClientFields = $this->getApplicantFields()->getCompanyFields($companyId, $this->getMemberTypeIdByName($memberType));

                    $currentRowId = '';
                    $previousBlockContact = '';
                    $previousBlockRepeatable = '';
                    $arrCollectedGroups = array();

                    // Load list of offices saved before - will be used if office wasn't passed
                    $arrSavedOffices = array();
                    if (!empty($employerId)) {
                        $arrAssignedChildren = $this->getAllAssignedApplicants($employerId, 1, $this->getMemberTypeIdByName('internal_contact'));
                        if (count($arrAssignedChildren)) {
                            $arrMemberIds = array_merge($arrAssignedChildren, array($employerId));
                            $arrSavedOffices = $this->getApplicantOffices($arrMemberIds, $divisionGroupId);
                        }
                    }

                    // Load already saved applicant's data -
                    // will be used if field wasn't passed
                    $arrApplicantSavedData = array();
                    if (!empty($employerId)) {
                        list($arrApplicantSavedData,) = $this->getAllApplicantFieldsData($employerId, $memberTypeId);
                    }

                    foreach ($arrAllApplicantFields as $arrBlockInfo) {
                        if (!isset($arrBlockInfo['fields'])) {
                            continue;
                        }

                        if ($previousBlockContact != $arrBlockInfo['group_contact_block'] || $previousBlockRepeatable != $arrBlockInfo['group_repeatable']) {
                            $currentRowId = $this->generateRowId();
                        }
                        $previousBlockContact = $arrBlockInfo['group_contact_block'];
                        $previousBlockRepeatable = $arrBlockInfo['group_repeatable'];
                        $groupId = $memberType . '_group_row_' . $arrBlockInfo['group_id'];

                        foreach ($arrBlockInfo['fields'] as $arrFieldInfo) {
                            $fieldKey = 'field_' . $memberType . '_' . $arrBlockInfo['group_id'] . '_' . $arrFieldInfo['field_id'];
                            $fieldValToSave = '';

                            // We need to have groups - to save data for specific internal clients, if needed
                            $arrCollectedGroups[$arrBlockInfo['group_id']] = $groupId;
                            if (empty($employerId) && !array_key_exists($groupId, $arrEmployerParams)) {
                                $arrEmployerParams[$groupId] = array($currentRowId);
                            }

                            // Check if value was passed for this field
                            // if not - means that field value is empty
                            if (array_key_exists($arrFieldInfo['field_unique_id'], $arrMappedParams)) {
                                if ($arrFieldInfo['field_type'] == 'multiple_combo') {
                                    $arrValues = Json::decode($arrMappedParams[$arrFieldInfo['field_unique_id']], Json::TYPE_ARRAY);
                                    $arrMappedParams[$arrFieldInfo['field_unique_id']] = implode(',', $arrValues);
                                }

                                // Convert fields data from readable format to the correct one (e.g. use ids for office field)
                                $arrFieldValResult = $this->getFields()->getFieldValue(
                                    $arrClientFields,
                                    $arrFieldInfo['field_unique_id'],
                                    trim($arrMappedParams[$arrFieldInfo['field_unique_id']] ?? ''),
                                    null,
                                    $companyId,
                                    false,
                                    false,
                                    false
                                );

                                if ($arrFieldValResult['error']) {
                                    $strError .= '<div>' . $arrFieldValResult['error-msg'] . '</div>';
                                } else {
                                    $fieldValToSave = $arrFieldValResult['result'];

                                    // Date must be in the same format as it is passed from the client side
                                    if (!empty($fieldValToSave) && in_array($arrFieldInfo['field_type'], array('date', 'date_repeatable'))) {
                                        $fieldValToSave = date($dateFormatFull, strtotime($fieldValToSave));
                                    }
                                }
                            } elseif (!empty($employerId) && in_array($arrFieldInfo['field_type'], array('office', 'office_multi'))) {
                                // Load/use saved offices
                                $fieldValToSave = is_array($arrSavedOffices) && count($arrSavedOffices) ? $arrSavedOffices : array('');
                            } elseif (!empty($employerId) && isset($arrApplicantSavedData[$fieldKey])) {
                                // Load/use saved data
                                $fieldValToSave = $arrApplicantSavedData[$fieldKey];

                                // Date must be in the same format as it is passed from the client side
                                if (in_array($arrFieldInfo['field_type'], array('date', 'date_repeatable'))) {
                                    if (is_array($fieldValToSave)) {
                                        foreach ($fieldValToSave as $key => $val) {
                                            if (!empty($val)) {
                                                $fieldValToSave[$key] = date($dateFormatFull, strtotime($val));
                                            }
                                        }
                                    } elseif (!empty($val)) {
                                        $fieldValToSave = date($dateFormatFull, strtotime($fieldValToSave));
                                    }
                                }
                            }
                            if ($arrFieldInfo['field_type'] == 'multiple_text_fields' || ($arrFieldInfo['field_type'] == 'reference' && $arrFieldInfo['field_multiple_values'])) {
                                if (is_string($fieldValToSave) && is_array(json_decode($fieldValToSave, true)) && json_last_error() == JSON_ERROR_NONE) {
                                    $multipleValue = Json::decode($fieldValToSave, Json::TYPE_ARRAY);
                                } else {
                                    $multipleValue = $fieldValToSave;
                                }
                                $fieldValToSave = $multipleValue;
                            }

                            $arrEmployerParams[$fieldKey] = is_array($fieldValToSave) ? $fieldValToSave : array($fieldValToSave);
                        }
                    }

                    if (empty($strError)) {
                        if (!empty($employerId)) {
                            foreach ($arrCollectedGroups as $groupId => $groupTextId) {
                                $assignedContactId = $this->getAssignedContact($employerId, $groupId);
                                $arrEmployerParams[$groupTextId] = array(empty($assignedContactId) ? $this->generateRowId() : $assignedContactId);
                            }
                        }

                        $arrEmployerUpdateResult = $this->createClientAndCaseAtOnce($arrEmployerParams, $arrReceivedFiles);
                        $strError                = $arrEmployerUpdateResult['success'] ? '' : $arrEmployerUpdateResult['message'];
                        $employerId              = $arrEmployerUpdateResult['success'] ? $arrEmployerUpdateResult['applicantId'] : 0;
                    }

                    // create IA
                    $applicantType = 0;
                    $memberType    = 'individual';
                    $arrIAParams   = array(
                        'applicantId'        => 0,
                        'memberType'         => $memberType,
                        'applicantType'      => $applicantType,
                        'applicantUpdatedOn' => 0,
                        'forceOverwrite'     => 1,

                        'caseId'         => 0,
                        'caseType'       => 0,
                        'caseEmployerId' => $employerId
                    );

                    // Load list of offices saved before - will be used if office wasn't passed

                    // Load already saved applicant's data -
                    // will be used if field wasn't passed

                } else {
                    $applicantType = 0;

                    $arrIAParams = array(
                        'applicantId'        => $applicantId,
                        'memberType'         => $memberType,
                        'applicantType'      => $applicantType,
                        'applicantUpdatedOn' => 0,
                        'forceOverwrite'     => 1,

                        'caseId'         => 0,
                        'caseType'       => 0,
                        'caseEmployerId' => 0
                    );

                    // Load list of offices saved before - will be used if office wasn't passed

                    // Load already saved applicant's data -
                    // will be used if field wasn't passed

                }
                $memberTypeId            = $this->getMemberTypeIdByName($memberType);
                $arrAllApplicantFields   = $this->getApplicantFields()->getGroupedCompanyFields($companyId, $memberTypeId, $applicantType);
                $arrClientFields         = $this->getApplicantFields()->getCompanyFields($companyId, $this->getMemberTypeIdByName($memberType));
                $currentRowId            = '';
                $previousBlockContact    = '';
                $previousBlockRepeatable = '';
                $arrCollectedGroups      = array();
                $arrSavedOffices         = array();
                if (!empty($applicantId)) {
                    $arrAssignedChildren = $this->getAllAssignedApplicants($applicantId, 1, $this->getMemberTypeIdByName('internal_contact'));
                    if (count($arrAssignedChildren)) {
                        $arrMemberIds    = array_merge($arrAssignedChildren, array($applicantId));
                        $arrSavedOffices = $this->getApplicantOffices($arrMemberIds, $divisionGroupId);
                    }
                }
                $arrApplicantSavedData = array();
                if (!empty($applicantId)) {
                    list($arrApplicantSavedData,) = $this->getAllApplicantFieldsData($applicantId, $memberTypeId);
                }
                foreach ($arrAllApplicantFields as $arrBlockInfo) {
                    if (!isset($arrBlockInfo['fields'])) {
                        continue;
                    }

                    if ($previousBlockContact != $arrBlockInfo['group_contact_block'] || $previousBlockRepeatable != $arrBlockInfo['group_repeatable']) {
                        $currentRowId = $this->generateRowId();
                    }
                    $previousBlockContact    = $arrBlockInfo['group_contact_block'];
                    $previousBlockRepeatable = $arrBlockInfo['group_repeatable'];
                    $groupId                 = $memberType . '_group_row_' . $arrBlockInfo['group_id'];

                    foreach ($arrBlockInfo['fields'] as $arrFieldInfo) {
                        $fieldKey       = 'field_' . $memberType . '_' . $arrBlockInfo['group_id'] . '_' . $arrFieldInfo['field_id'];
                        $fieldValToSave = '';

                        // We need to have groups - to save data for specific internal clients, if needed
                        $arrCollectedGroups[$arrBlockInfo['group_id']] = $groupId;
                        if (empty($applicantId) && !array_key_exists($groupId, $arrIAParams)) {
                            $arrIAParams[$groupId] = array($currentRowId);
                        }

                        // Check if value was passed for this field
                        // if not - means that field value is empty
                        if (array_key_exists($arrFieldInfo['field_unique_id'], $arrMappedParams)) {
                            if ($arrFieldInfo['field_type'] == 'multiple_combo') {
                                $arrValues                                         = Json::decode($arrMappedParams[$arrFieldInfo['field_unique_id']], Json::TYPE_ARRAY);
                                $arrMappedParams[$arrFieldInfo['field_unique_id']] = implode(',', $arrValues);
                            }

                            // Convert fields data from readable format to the correct one (e.g. use ids for office field)
                            $arrFieldValResult = $this->getFields()->getFieldValue(
                                $arrClientFields,
                                $arrFieldInfo['field_unique_id'],
                                trim($arrMappedParams[$arrFieldInfo['field_unique_id']] ?? ''),
                                null,
                                $companyId,
                                false,
                                false,
                                false
                            );

                            if ($arrFieldValResult['error']) {
                                $strError .= '<div>' . $arrFieldValResult['error-msg'] . '</div>';
                            } else {
                                $fieldValToSave = $arrFieldValResult['result'];

                                // Date must be in the same format as it is passed from the client side
                                if (!empty($fieldValToSave) && in_array($arrFieldInfo['field_type'], array('date', 'date_repeatable'))) {
                                    $fieldValToSave = date($dateFormatFull, strtotime($fieldValToSave));
                                }
                            }
                        } elseif (!empty($applicantId) && in_array($arrFieldInfo['field_type'], array('office', 'office_multi'))) {
                            // Load/use saved offices
                            $fieldValToSave = is_array($arrSavedOffices) && count($arrSavedOffices) ? $arrSavedOffices : array('');
                        } elseif (!empty($applicantId) && isset($arrApplicantSavedData[$fieldKey])) {
                            // Load/use saved data
                            $fieldValToSave = $arrApplicantSavedData[$fieldKey];

                            // Date must be in the same format as it is passed from the client side
                            if (in_array($arrFieldInfo['field_type'], array('date', 'date_repeatable'))) {
                                if (is_array($fieldValToSave)) {
                                    foreach ($fieldValToSave as $key => $val) {
                                        if (!empty($val)) {
                                            $fieldValToSave[$key] = date($dateFormatFull, strtotime($val));
                                        }
                                    }
                                } elseif (!empty($val)) {
                                    $fieldValToSave = date($dateFormatFull, strtotime($fieldValToSave));
                                }
                            }
                        }
                        if ($arrFieldInfo['field_type'] == 'multiple_text_fields' || ($arrFieldInfo['field_type'] == 'reference' && $arrFieldInfo['field_multiple_values'])) {
                            if (is_string($fieldValToSave) && is_array(json_decode($fieldValToSave, true)) && json_last_error() == JSON_ERROR_NONE) {
                                $multipleValue = Json::decode($fieldValToSave, Json::TYPE_ARRAY);
                            } else {
                                $multipleValue = $fieldValToSave;
                            }
                            $fieldValToSave = $multipleValue;
                        }

                        $arrIAParams[$fieldKey] = is_array($fieldValToSave) ? $fieldValToSave : array($fieldValToSave);
                    }
                }
                if (empty($strError)) {
                    if (!empty($applicantId)) {
                        foreach ($arrCollectedGroups as $groupId => $groupTextId) {
                            $assignedContactId         = $this->getAssignedContact($applicantId, $groupId);
                            $arrIAParams[$groupTextId] = array(empty($assignedContactId) ? $this->generateRowId() : $assignedContactId);
                        }
                    }

                    $arrIAUpdateResult = $this->createClientAndCaseAtOnce($arrIAParams, $arrReceivedFiles);

                    $strError    = $arrIAUpdateResult['success'] ? '' : $arrIAUpdateResult['message'];
                    $applicantId = $arrIAUpdateResult['success'] ? $arrIAUpdateResult['applicantId'] : 0;
                }
            }

            // Check if we need create/update the case
            $booCreateUpdateCase = array_key_exists('update_case', $arrMappedParams) ? (bool)$arrMappedParams['update_case'] : false;
            if (empty($strError) && $booCreateUpdateCase) {
                // Prepare case data
                $booCreateCase = empty($caseId);
                if (!empty($caseId) && !$this->hasCurrentMemberAccessToMember($caseId)) {
                    $strError = $this->_tr->translate('Insufficient access rights to case.');
                }

                $caseType = 0;
                $arrCaseFormVersionIds = array();
                $caseEmailTemplateId = 0;
                if (empty($strError)) {
                    $arrCaseTypeInfo = array();
                    if (!empty($caseId) && !isset($arrMappedParams['case_type'])) {
                        $arrCaseTemplates = $this->getCaseTemplates()->getCasesTemplates(array($caseId));

                        if (isset($arrCaseTemplates[$caseId])) {
                            $arrCaseTypeInfo = $this->getCaseTemplates()->getTemplateInfo($arrCaseTemplates[$caseId]);
                            $arrMappedParams['case_type'] = $arrCaseTypeInfo['client_type_name'];
                        }
                    } else {
                        $arrCaseTypeInfo = $this->getCaseTemplates()->getCasesTemplateInfoByName(
                            $arrMappedParams['case_type'],
                            $companyId
                        );
                    }


                    $caseType = $arrCaseTypeInfo['client_type_id'] ?? 0;

                    if (!empty($caseType)) {
                        $arrCaseFormVersionIds = $this->getCaseTemplates()->getCaseTemplateForms($caseType, false);
                    }

                    if (!empty($arrCaseFormVersionIds) && array_key_exists('form_version_date', $arrMappedParams) && strtotime($arrMappedParams['form_version_date'])) {
                        foreach ($arrCaseFormVersionIds as $caseFormVersionId) {
                            $arrFormVersionInfo = $this->_forms->getFormVersion()->getFormVersionInfo($caseFormVersionId);

                            if (isset($arrFormVersionInfo['form_id'])) {
                                $arrFormVersionInfo = $this->_forms->getFormVersion()->getOldFormVersionByFormId($arrFormVersionInfo['form_id'], $arrMappedParams['form_version_date']);
                                if (isset($arrFormVersionInfo['form_version_id'])) {
                                    $arrCaseFormVersionIds = array($arrFormVersionInfo['form_version_id']);
                                    break;
                                }
                            }
                        }
                    }

                    $caseEmailTemplateId = isset($arrCaseTypeInfo['email_template_id']) && !empty($arrCaseTypeInfo['email_template_id']) ? $arrCaseTypeInfo['email_template_id'] : 0;

                    if (empty($caseType)) {
                        $caseTypeTerm = $this->_company->getCurrentCompanyDefaultLabel('case_type');
                        $strError     = $this->_tr->translate('Incorrectly selected') . ' ' . $caseTypeTerm;
                    }
                }

                // Create/update case for just created/updated IA
                if (empty($strError)) {
                    $memberType = 'case';
                    $arrCaseParams = array(
                        'applicantId' => $applicantId,
                        'applicantUpdatedOn' => 0,
                        'forceOverwrite' => 1,
                        'memberType' => $memberType,
                        'applicantType' => 0,
                        'caseId' => $caseId,
                        'caseType' => $caseType,
                        'caseEmployerId' => $employerId,
                        'booApi' => true
                    );

                    // Load grouped fields
                    $arrGroupedCaseFields = $this->getFields()->getGroupedCompanyFields($caseType);

                    // Load all company fields for a specific Immigration Program,
                    // which are available for the current user
                    $arrCaseFields = $this->getFields()->getCaseTemplateFields($companyId, $caseType);

                    // Load already saved case's info -
                    // will be used if some data will be missed
                    $arrCaseData = array();
                    if (!empty($caseId)) {
                        $arrCaseData = $this->getFields()->getClientProfile('edit', $caseId, $caseType);
                    }

                    $currentRowId = '';
                    $previousBlockContact = '';
                    $previousBlockRepeatable = '';
                    foreach ($arrGroupedCaseFields as $arrGroupInfo) {
                        if (!isset($arrGroupInfo['fields'])) {
                            continue;
                        }

                        if ($previousBlockContact != $arrGroupInfo['group_contact_block'] || $previousBlockRepeatable != $arrGroupInfo['group_repeatable']) {
                            $currentRowId = $this->generateRowId();
                        }
                        $previousBlockContact = $arrGroupInfo['group_contact_block'];
                        $previousBlockRepeatable = $arrGroupInfo['group_repeatable'];
                        $groupId = $memberType . '_group_row_' . $arrGroupInfo['group_id'];

                        foreach ($arrGroupInfo['fields'] as $arrFieldInfo) {
                            $fieldValToSave = '';
                            if (empty($caseId) && !array_key_exists($groupId, $arrCaseParams)) {
                                $arrCaseParams[$groupId] = array($currentRowId);
                            }

                            // Check if value was passed for this field
                            // if not - means that field value is empty
                            if (array_key_exists($arrFieldInfo['field_unique_id'], $arrMappedParams)) {
                                if ($arrFieldInfo['field_type'] == 'multiple_combo') {
                                    $arrValues = Json::decode($arrMappedParams[$arrFieldInfo['field_unique_id']], Json::TYPE_ARRAY);
                                    $arrMappedParams[$arrFieldInfo['field_unique_id']] = implode(',', $arrValues);
                                }

                                // Convert fields data from readable format to the correct one (e.g. use ids for office field)
                                $arrFieldValResult = $this->getFields()->getFieldValue(
                                    $arrCaseFields,
                                    $arrFieldInfo['field_unique_id'],
                                    trim($arrMappedParams[$arrFieldInfo['field_unique_id']] ?? ''),
                                    null,
                                    $companyId,
                                    true,
                                    false,
                                    false
                                );

                                if ($arrFieldValResult['error']) {
                                    $strError .= '<div>' . $arrFieldValResult['error-msg'] . '</div>';
                                } else {
                                    $fieldValToSave = $arrFieldValResult['result'];

                                    // Date must be in the same format as it is passed from the client side
                                    if (!empty($fieldValToSave) && in_array($arrFieldInfo['field_type'], array('date', 'date_repeatable'))) {
                                        $fieldValToSave = date($dateFormatFull, strtotime($fieldValToSave));
                                    }
                                }
                            } elseif (!empty($caseId) && is_array($arrCaseData) && array_key_exists('groups', $arrCaseData)) {
                                // Try to load from saved info
                                foreach ($arrCaseData['groups'] as $arrCaseGroupInfo) {
                                    if ($arrGroupInfo['group_id'] != $arrCaseGroupInfo['group_id']) {
                                        continue;
                                    }

                                    foreach ($arrCaseGroupInfo['fields'] as $arrCaseFieldInfo) {
                                        if ($arrCaseFieldInfo['field_id'] != $arrFieldInfo['field_id']) {
                                            continue;
                                        }

                                        if (!is_null($arrCaseFieldInfo['value']) && $arrCaseFieldInfo['value'] !== '') {
                                            if (in_array($arrFieldInfo['field_type'], array('date', 'date_repeatable'))) {
                                                $fieldValToSave = date($dateFormatFull, strtotime($arrCaseFieldInfo['value']));
                                            } else {
                                                $fieldValToSave = $arrCaseFieldInfo['value'];
                                            }
                                            break 2;
                                        }
                                    }
                                }
                            }

                            if ($arrFieldInfo['field_type'] == 'multiple_text_fields' || ($arrFieldInfo['field_type'] == 'reference' && $arrFieldInfo['field_multiple_values'])) {
                                if (is_string($fieldValToSave) && is_array(json_decode($fieldValToSave, true)) && json_last_error() == JSON_ERROR_NONE) {
                                    $multipleValue = Json::decode($fieldValToSave, Json::TYPE_ARRAY);
                                } else {
                                    $multipleValue = $fieldValToSave;
                                }
                                $arrCaseParams['field_' . $memberType . '_' . $arrGroupInfo['group_id'] . '_' . $arrFieldInfo['field_id']] = $multipleValue;
                            } else {
                                $arrCaseParams['field_' . $memberType . '_' . $arrGroupInfo['group_id'] . '_' . $arrFieldInfo['field_id']] = array($fieldValToSave);
                            }
                        }
                    }

                    if (empty($strError)) {
                        $arrCaseUpdateResult = $this->createClientAndCaseAtOnce($arrCaseParams, $arrReceivedFiles);

                        $strError = $arrCaseUpdateResult['success'] ? '' : $arrCaseUpdateResult['message'];
                        $caseId = $arrCaseUpdateResult['success'] ? $arrCaseUpdateResult['caseId'] : 0;
                        $caseReferenceNumber = $arrCaseUpdateResult['success'] ? $arrCaseUpdateResult['caseReferenceNumber'] : 0;
                    }
                }

                // Assign specific form to the case (the same form can be assigned only once)
                if (empty($strError) && $booCreateCase && !empty($caseId) && !empty($arrCaseFormVersionIds)) {
                    $formFamilyMemberId = array_key_exists('form_family_member', $arrMappedParams) ? $arrMappedParams['form_family_member'] : 'main_applicant';
                    if (!in_array($formFamilyMemberId, array('main_applicant', 'other1'))) {
                        $formFamilyMemberId = 'main_applicant';
                    }
                    $otherDescription = array_key_exists('stage_number', $arrMappedParams) && $formFamilyMemberId === 'other1' ? 'Stage ' . $arrMappedParams['stage_number'] : '';

                    foreach ($arrCaseFormVersionIds as $caseFormVersionId) {
                        $this->_forms->getFormAssigned()->assignFormToCase(
                            $caseId,
                            $formFamilyMemberId,
                            array('pform' . $caseFormVersionId), // In the same format as it is received from GUI
                            $otherDescription,
                            $this->_auth->getCurrentUserId()
                        );
                    }
                }

                // Search form by name and assign
                if (empty($strError) && !empty($caseId) && $booAssignForm && array_key_exists('form_name', $arrMappedParams)) {
                    $version            = $arrMappedParams['form_version_date'] ?? 'latest';
                    $arrFoundForms      = $this->_forms->getFormVersion()->searchFormByName($arrMappedParams['form_name'], $version, false);
                    $formFamilyMemberId = array_key_exists('form_family_member', $arrMappedParams) ? $arrMappedParams['form_family_member'] : 'other1';
                    if (!in_array($formFamilyMemberId, array('main_applicant', 'other1'))) {
                        $formFamilyMemberId = 'other1';
                    }
                    $otherDescription = array_key_exists('stage_number', $arrMappedParams) && $formFamilyMemberId === 'other1' ? 'Stage ' . $arrMappedParams['stage_number'] : '';

                    if (isset($arrFoundForms[0]['form_version_id'])) {
                        $arrResultFormAssign = $this->_forms->getFormAssigned()->assignFormToCase(
                            $caseId,
                            $formFamilyMemberId,
                            array('pform' . $arrFoundForms[0]['form_version_id']), // In the same format as it is received from GUI
                            $otherDescription,
                            $this->_auth->getCurrentUserId()
                        );

                        $strError = $arrResultFormAssign['msg'];

                        if (empty($strError) && isset($arrResultFormAssign['forms_info'][0]['data']['client_form_id'])) {
                            if (isset($arrReceivedFiles['OfficioJson'])) {
                                $strNewJsonData = file_get_contents($arrReceivedFiles['OfficioJson']['tmp_name']);
                                $arrJsonData    = json_decode($strNewJsonData);
                                unlink($arrReceivedFiles['OfficioJson']['tmp_name']);
                                $booMergeData = false;
                            } else {
                                $booMergeData = true;
                                $arrJsonData  = $arrSyncFormFields;
                            }

                            $this->_forms->updateJson(
                                $caseId,
                                $formFamilyMemberId,
                                $arrResultFormAssign['forms_info'][0]['data']['client_form_id'],
                                $arrJsonData,
                                $booMergeData
                            );
                        }
                    } else {
                        $strError = $this->_tr->translate('Form was not found.');
                    }
                }

                if (empty($strError) && !empty($caseId) && count($arrXfdfOnlyParams)) {
                    // Save xfdf only data to xfdf file(s)
                    $resCode = $this->_pdf->updateXfdfFiles(
                        $caseId,
                        array('main_applicant' => $arrXfdfOnlyParams)
                    );

                    if ($resCode != Pdf::XFDF_SAVED_CORRECTLY) {
                        $strError = $this->_pdf->getCodeResultById($resCode);
                    }
                }
            }

            // Save/update form data in json format (for the case)
            if (($booCreateUpdateProfile || $booCreateUpdateCase) && empty($strError) && !empty($caseId) && !empty($arrCaseFormVersionIds) && !$booAssignForm) {
                // Use json data from json file OR from received fields (SyncA_, Xfdf-only sync fields)
                if (isset($arrReceivedFiles['OfficioJson'])) {
                    $strNewJsonData = file_get_contents($arrReceivedFiles['OfficioJson']['tmp_name']);
                    $arrJsonData = json_decode($strNewJsonData);
                    unlink($arrReceivedFiles['OfficioJson']['tmp_name']);
                    $booMergeData = false;
                } else {
                    $booMergeData = true;
                    $arrJsonData = $arrSyncFormFields;
                }

                $select = (new Select())
                    ->from('form_assigned')
                    ->columns(array('form_assigned_id', 'family_member_id', 'form_version_id'))
                    ->where(['client_member_id' => (int)$caseId]);

                $arrCaseAssignedForms = $this->_db2->fetchAll($select);

                foreach ($arrCaseFormVersionIds as $caseFormVersionId) {
                    foreach ($arrCaseAssignedForms as $caseAssignedForm) {
                        if ($caseFormVersionId === $caseAssignedForm['form_version_id']) {
                            $this->_forms->updateJson(
                                $caseId,
                                $caseAssignedForm['family_member_id'],
                                $caseAssignedForm['form_assigned_id'],
                                $arrJsonData,
                                $booMergeData
                            );
                            break;
                        }
                    }
                }
            }

            // Save/update passed files (for the case)
            $totalFilesArrivedCount = 0;
            $totalFilesSavedCount = 0;
            if (empty($strError) && !empty($caseId) && isset($arrReceivedFiles['OfficioCaseFiles'])) {
                $pathToSubmissions = $this->_files->getClientSubmissionsFolder($companyId, $caseId, $booLocal);

                // Make sure that directory already exists
                if ($booLocal) {
                    $this->_files->createFTPDirectory($pathToSubmissions);
                } else {
                    $this->_files->getCloud()->createFolder($pathToSubmissions);
                }

                // Upload files to the correct place
                foreach ($arrReceivedFiles['OfficioCaseFiles']['error'] as $key => $error) {
                    $totalFilesArrivedCount++;

                    $fileName = pathinfo($arrReceivedFiles['OfficioCaseFiles']['name'][$key], PATHINFO_BASENAME) ?? '';

                    // In some cases encoding is incorrect
                    $currentEncoding = mb_detect_encoding($fileName);
                    if ($currentEncoding != "UTF-8" || !mb_check_encoding($fileName, "UTF-8")) {
                        $fileName = utf8_encode($fileName);
                    }

                    $fileName    = FileTools::cleanupFileName($fileName);
                    $tmpFilePath = $arrReceivedFiles['OfficioCaseFiles']['tmp_name'][$key];

                    if (!$tmpFilePath || !$fileName || $error != UPLOAD_ERR_OK) {
                        continue;
                    }

                    // Make sure that a new file will not overwrite already existing files - generate a unique file name
                    $newFilePath = $this->_files->generateFileName($pathToSubmissions . '/' . $fileName, $booLocal);
                    if ($booLocal) {
                        // rename() was changed to copy() because file might be needed for other cases (@see
                        // modules/api/controllers/GvController.php). However, according to php.net "The file will be
                        // deleted from the temporary directory at the end of the request if it has not been moved away
                        // or renamed." so we are not going to overfill tmp directory by leaving files there.
                        copy($tmpFilePath, $newFilePath);

                        if (file_exists($newFilePath)) {
                            $totalFilesSavedCount++;
                        }
                    } else {
                        if ($this->_files->getCloud()->uploadFile($tmpFilePath, $newFilePath) && $this->_files->getCloud()->checkObjectExists($newFilePath)) {
                            $totalFilesSavedCount++;
                        }
                    }
                }
            }


            // Update case's specific fields - about passed/saved files (if they were passed)
            if (empty($strError) && isset($arrParams['config']['file_attachments_arrived_count'])) {
                $fieldId = $this->getFields()->getCompanyFieldIdByUniqueFieldId($arrParams['config']['file_attachments_arrived_count'], $companyId);

                if (!empty($fieldId)) {
                    $settings = array(
                        'field_id'    => $fieldId,
                        'text'        => $totalFilesArrivedCount,
                        'member_type' => 'case'
                    );

                    list($strError,) = $this->changeFieldValue($caseId, $companyId, $settings);
                }
            }

            if (empty($strError) && isset($arrParams['config']['file_attachments_saved_count'])) {
                $fieldId = $this->getFields()->getCompanyFieldIdByUniqueFieldId($arrParams['config']['file_attachments_saved_count'], $companyId);

                if (!empty($fieldId)) {
                    $settings = array(
                        'field_id'    => $fieldId,
                        'text'        => $totalFilesSavedCount,
                        'member_type' => 'case'
                    );

                    list($strError,) = $this->changeFieldValue($caseId, $companyId, $settings);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Send email to the case - on creation only
        if (empty($strError) && $booCreateCase && !empty($caseId) && !empty($caseEmailTemplateId)) {
            $arrResult = $templates->getMessage(
                $caseEmailTemplateId,
                $caseId,
                '',
                0,
                $companyId
            );

            $arrResult['friendly_name'] = '';
            $arrResult['attached'] = $templates->parseTemplateAttachments($caseEmailTemplateId, $caseId);

            if (empty($arrResult['from'])) {
                $booShowSenderName = true;
            } else {
                $booShowSenderName = false;
            }

            $senderInfo = $this->getMemberInfo();
            list($res, $email) = $this->_mailer->send($arrResult, false, $senderInfo, false, true, $booShowSenderName);

            // Save this sent email to case's documents
            if ($res === true) {
                $arrMemberInfo = $this->getMemberInfo($caseId);
                $companyId     = $arrMemberInfo['company_id'];
                $booLocal      = $this->_company->isCompanyStorageLocationLocal($companyId);
                $clientFolder  = $this->_files->getClientCorrespondenceFTPFolder($companyId, $caseId, $booLocal);
                $this->_mailer->saveRawEmailToClient($email, $arrResult['subject'], 0, $companyId, $caseId, $senderInfo, 0, $clientFolder, $booLocal);
            }
        }

        return array(
            'message'             => $strError,
            'applicantId'         => $applicantId,
            'employerId'          => $employerId,
            'caseId'              => $caseId,
            'caseReferenceNumber' => $caseReferenceNumber
        );
    }

    /**
     * Update field's value for a specific client/case
     *
     * @param int $memberId - case or client id
     * @param int $companyId
     * @param array $actionSettings
     * @param bool $booAutomaticReminderAction
     * @param bool $booOnlyActive true to load the last active case
     * @return array
     */
    public function changeFieldValue($memberId, $companyId, $actionSettings, $booAutomaticReminderAction = false, $booOnlyActive = false)
    {
        $strError = '';
        $arrAllFieldsChangesData = array();
        try {
            $memberTypeId = $this->getMemberTypeByMemberId($memberId);

            if ($booAutomaticReminderAction) {
                $userId = $this->_company->getCompanyAdminId($companyId);
            } else {
                $userId = $this->_auth->getCurrentUserId();
            }

            $fieldValue = $fieldValueConverted = null;
            if (array_key_exists('text', $actionSettings)) {
                $fieldValue = $fieldValueConverted = $actionSettings['text'];
            } elseif (array_key_exists('date', $actionSettings)) {
                $fieldValue = $this->_settings->formatDate($actionSettings['date'], true, 'Y-m-d');
                $fieldValueConverted = $this->_settings->formatDate($fieldValue);
            } elseif (array_key_exists('option', $actionSettings)) {
                $fieldValue = $fieldValueConverted = $actionSettings['option'];
            }

            if (is_null($fieldValue)) {
                $strError = $this->_tr->translate("Unsupported field's type");
                return array($strError, $arrAllFieldsChangesData);
            }

            $fieldId = $actionSettings['field_id'];


            if ($actionSettings['member_type'] == 'case') {
                // We know that we need update info for the case(s)

                // Check if field and its value are correct
                $arrFieldInfo = $this->getFields()->getFieldInfo($fieldId, $companyId);
                if (empty($arrFieldInfo)) {
                    $strError = sprintf($this->_tr->translate('Incorrectly selected case field: %d'), $fieldId);

                    return array($strError, $arrAllFieldsChangesData);
                }

                list($fieldReadableValue, $booIsFieldValueCorrect) = $this->getFields()->getFieldReadableValue(
                    $companyId,
                    $this->_auth->getCurrentUserDivisionGroupId(),
                    $arrFieldInfo,
                    $fieldValueConverted,
                    false,
                    true,
                    true,
                    true,
                    true,
                    true
                );
                if (!$booIsFieldValueCorrect) {
                    $strError = sprintf($this->_tr->translate('Incorrectly selected value for the field: %d'), $fieldId);

                    return array($strError, $arrAllFieldsChangesData);
                }

                // If this is case status field - run special trigger for it
                $booCaseStatusField = $arrFieldInfo['company_field_id'] == 'file_status';

                $booInvestmentTypeFieldId = $arrFieldInfo['company_field_id'] == 'cbiu_investment_type';

                if (isset($arrFieldInfo['field_type_text_id'])) {
                    $fieldType = $arrFieldInfo['field_type_text_id'];
                } else {
                    $fieldType = is_numeric($arrFieldInfo['type']) ? $this->getFieldTypes()->getStringFieldTypeById($arrFieldInfo['type']) : $arrFieldInfo['type'];
                }

                if ($fieldType == 'checkbox') {
                    // For case file status - save in DB only 'Active' = checked, all others mean inactive
                    $booCaseFileStatusField = $arrFieldInfo['company_field_id'] == 'Client_file_status';

                    if ($booCaseFileStatusField) {
                        if ($fieldValue == 'Active' || $fieldValue == '1') {
                            $fieldValue = 'Active';
                            $fieldReadableValue = 'Active';
                        } else {
                            $fieldValue = '';
                            $fieldReadableValue = 'Not Active';
                        }
                    } elseif ($fieldValue == '1') {
                        $fieldValue = 'on';
                        $fieldReadableValue = 'Checked';
                    } elseif ($fieldValue == '0') {
                        $fieldValue = '';
                        $fieldReadableValue = 'Not Checked';
                    }
                }

                // Load list of cases that must be updated
                $arrCasesToUpdate = array();
                if (in_array($memberTypeId, Members::getMemberType('case'))) {
                    $arrCasesToUpdate[] = $memberId;
                } else {
                    if (in_array($memberTypeId, Members::getMemberType('internal_contact'))) {
                        // Search for parents + load assigned cases for them
                        $arrClientIds = $this->getParentsForAssignedApplicant($memberId);
                    } else {
                        // Search for all assigned cases to this client
                        $arrClientIds = array($memberId);
                    }

                    $arrCasesToUpdate = Settings::arrayUnique($this->getAssignedApplicants($arrClientIds, $this->getMemberTypeIdByName('case')));
                    if ($booOnlyActive && !empty($arrCasesToUpdate)) {
                        $arrCasesToUpdate = $this->getActiveClientsList($arrCasesToUpdate, true, $companyId);
                    }
                }

                // Update field value for each of the cases
                foreach ($arrCasesToUpdate as $caseId) {
                    if ($fieldType == 'kskeydid') {
                        $fieldValue = $fieldReadableValue = $this->getGUID();
                    } else {
                        if ($fieldType == 'bcpnp_nomination_certificate_number') {
                            if ($this->getCaseNumber()->isAutomaticTurnedOn($companyId)) {
                                $arrCaseInfo = $this->getClientsInfo(array($caseId));
                                if ($arrCaseInfo && count($arrCaseInfo)) {
                                    $caseNumberSettings = $this->getCaseNumber()->getCompanyCaseNumberSettings($companyId);
                                    $caseReferenceNumberParts = explode($caseNumberSettings['cn-separator'] ?? '', $arrCaseInfo[0]['fileNumber'] ?? '');
                                    if (!empty($caseReferenceNumberParts)) {
                                        $fieldValue = $fieldReadableValue = date('Y') . '-' . preg_replace('/\D/', '', $caseReferenceNumberParts[count($caseReferenceNumberParts) - 1]);
                                    }
                                }
                            } else {
                                $fieldValue = $fieldReadableValue = '';
                            }
                        }
                    }


                    $arrOldData    = $this->getFields()->getClientFieldsValues($caseId, array($fieldId));
                    $oldFieldValue = $arrOldData[0]['value'] ?? null;

                    if (isset($fieldValue)) {
                        $booEncryptedField = $arrFieldInfo['encrypted'] == 'Y';
                        $newFieldValue     = $booEncryptedField ? $this->_encryption->decode($fieldValue) : $fieldValue;
                    } else {
                        $newFieldValue = null;
                    }

                    $booValueChanged = false;
                    if (!is_null($oldFieldValue) && !is_null($newFieldValue) && $oldFieldValue != $newFieldValue) {
                        $booValueChanged = true;
                    } elseif (!is_null($oldFieldValue) && is_null($newFieldValue)) {
                        $booValueChanged = true;
                    } elseif (is_null($oldFieldValue) && !is_null($newFieldValue)) {
                        $booValueChanged = true;
                    }

                    if ($booValueChanged) {
                        $arrUpdateCaseResult = $this->saveClientData($caseId, array($fieldId => $fieldValue));
                        if (!$arrUpdateCaseResult['success']) {
                            $strError = sprintf($this->_tr->translate('Data was not updated for this case: %d'), $caseId);
                        }

                        // Update static fields correctly
                        if (empty($strError)) {
                            $this->saveClientStaticData($caseId, $arrFieldInfo['company_field_id'], $fieldValue);
                        }

                        // Update xfdf too
                        if (empty($strError)) {
                            $arrSaveDataToXfdf = array(
                                $arrFieldInfo['company_field_id'] => $fieldReadableValue
                            );
                            if (!$this->_pdf->updateXfdfOnProfileUpdate($companyId, $caseId, $arrSaveDataToXfdf, $memberTypeId)) {
                                $strError = $this->_tr->translate('XFDF was not updated for case');
                            }
                        }

                        if (empty($strError)) {
                            // Trigger: Case Status changed
                            if ($booCaseStatusField) {
                                $this->_systemTriggers->triggerFileStatusChanged(
                                    $caseId,
                                    $this->getCaseStatuses()->getCaseStatusesNames($oldFieldValue),
                                    $this->getCaseStatuses()->getCaseStatusesNames($newFieldValue),
                                    $booAutomaticReminderAction ? 0 : $this->_auth->getCurrentUserId(),
                                    $booAutomaticReminderAction || !$this->_auth->hasIdentity() ? '' : $this->_auth->getIdentity()->full_name
                                );

                                if ($booInvestmentTypeFieldId) {
                                    $this->getAccounting()->regenerateCompanyAgentPayments($companyId, $caseId);
                                }
                            }

                            // Save to log the changes
                            if (isset($arrAllFieldsChangesData[$caseId])) {
                                $arrAllFieldsChangesData[$caseId]['arrOldData'] = array_merge($arrAllFieldsChangesData[$caseId]['arrOldData'], $arrUpdateCaseResult['arrOldData']);
                                $arrAllFieldsChangesData[$caseId]['arrNewData'] = array_merge($arrAllFieldsChangesData[$caseId]['arrNewData'], $arrUpdateCaseResult['arrNewData']);
                            } else {
                                $arrAllFieldsChangesData[$caseId]['booIsApplicant'] = false;
                                $arrAllFieldsChangesData[$caseId]['arrOldData'] = $arrUpdateCaseResult['arrOldData'];
                                $arrAllFieldsChangesData[$caseId]['arrNewData'] = $arrUpdateCaseResult['arrNewData'];
                            }
                        }
                    }

                    if (!empty($strError)) {
                        return array($strError, $arrAllFieldsChangesData);
                    }
                }
            } else {
                // We know that we need update info for the client(s)

                // Check if field and its value are correct
                $arrApplicantFieldInfo = $this->getApplicantFields()->getFieldInfo($fieldId, $companyId, true);
                if (empty($arrApplicantFieldInfo)) {
                    $strError = sprintf($this->_tr->translate('Incorrectly selected client field: %d'), $fieldId);
                    return array($strError, $arrAllFieldsChangesData);
                }

                list($fieldReadableValue, $booIsFieldValueCorrect) = $this->getFields()->getFieldReadableValue(
                    $companyId,
                    $this->_auth->getCurrentUserDivisionGroupId(),
                    array('type' => $arrApplicantFieldInfo['type']),
                    $fieldValueConverted,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true
                );

                if (!$booIsFieldValueCorrect) {
                    $strError = sprintf($this->_tr->translate('Incorrectly selected value for the field: %d'), $fieldId);
                    return array($strError, $arrAllFieldsChangesData);
                }

                if ($arrApplicantFieldInfo['type'] == 'checkbox') {
                    if ($fieldValue == '1') {
                        $fieldValue = 'on';
                        $fieldReadableValue = 'Checked';
                    } else {
                        if ($fieldValue == '0') {
                            $fieldValue = '';
                            $fieldReadableValue = 'Not Checked';
                        }
                    }
                }

                if (in_array($arrApplicantFieldInfo['type'], array('office', 'office_multi'))) {
                    $arrClientOffices = explode(',', $fieldValue ?? '');
                    $booUpdateOffices = true;
                } else {
                    $arrClientOffices = array();
                    $booUpdateOffices = false;
                }

                $arrClientIds = array();
                if (in_array($memberTypeId, Members::getMemberType('case')) || in_array($memberTypeId, Members::getMemberType('internal_contact'))) {
                    $arrClientIds = $this->getParentsForAssignedApplicant($memberId);
                } elseif (in_array($memberTypeId, Members::getMemberType('client'))) {
                    $arrClientIds[] = $memberId;
                }

                foreach ($arrClientIds as $clientId) {
                    // Search where this field is used and update
                    $arrClientInfo = $this->getClientShortInfo($clientId);
                    if (empty($arrClientInfo)) {
                        continue;
                    }
                    $arrGroupedCompanyFields = $this->getApplicantFields()->getGroupedCompanyFields($companyId, $arrClientInfo['userType'], $arrClientInfo['applicant_type_id'], false);

                    $updateClientId = null;
                    foreach ($arrGroupedCompanyFields as $arrGroupInfo) {
                        if (!isset($arrGroupInfo['fields'])) {
                            continue;
                        }

                        foreach ($arrGroupInfo['fields'] as $arrCompanyFieldInfo) {
                            if ($arrCompanyFieldInfo['field_id'] == $fieldId) {
                                if ($arrGroupInfo['group_contact_block'] == 'Y') {
                                    $updateClientId = $this->getAssignedContact($clientId, $arrGroupInfo['group_id']);
                                } else {
                                    $updateClientId = $clientId;
                                }
                                break 2;
                            }
                        }
                    }

                    if (empty($updateClientId)) {
                        // Client not found. E.g. field is assigned to another parent (IA, not to Employer).
                        continue;
                    }

                    $arrOldData = $this->getApplicantFields()->getApplicantFieldsData($updateClientId, array($fieldId));
                    foreach ($arrOldData as $key => $arrValues) {
                        $arrOldData[$key]['field_id'] = $arrValues['applicant_field_id'];
                    }

                    $oldFieldValue = $arrOldData[0]['value'] ?? null;

                    $booEncryptedField = $arrApplicantFieldInfo['encrypted'] == 'Y';
                    $newFieldValue     = $booEncryptedField ? $this->_encryption->decode($fieldValue) : $fieldValue;

                    $booValueChanged = false;

                    if (!is_null($oldFieldValue) && !is_null($newFieldValue) && $oldFieldValue != $newFieldValue) {
                        $booValueChanged = true;
                    } elseif (!is_null($oldFieldValue) && is_null($newFieldValue)) {
                        $booValueChanged = true;
                    } elseif (is_null($oldFieldValue) && !is_null($newFieldValue)) {
                        $booValueChanged = true;
                    }

                    if ($booValueChanged) {
                        $arrClientUpdate = array(
                            array(
                                'field_id'        => $fieldId,
                                'value'           => $fieldValue,
                                'field_unique_id' => $arrApplicantFieldInfo['applicant_field_unique_id'],
                                'row'             => 0,
                                'row_id'          => 0
                            )
                        );

                        if ($booUpdateOffices) {
                            list($booSuccess, $arrUpdateClientsOffice) = $this->updateClientsOffices($companyId, array($updateClientId), $arrClientOffices, null, null, false, false);
                            $arrUpdateApplicant['success'] = $booSuccess;
                            if (isset($arrUpdateClientsOffice[$updateClientId]['arrOldData'])) {
                                $arrUpdateApplicant['arrOldData'] = $arrUpdateClientsOffice[$updateClientId]['arrOldData'];
                            } else {
                                $arrUpdateApplicant['arrOldData'] = array();
                                $arrUpdateApplicant['success'] = false;
                            }
                        } else {
                            $arrUpdateApplicant = $this->updateApplicant($companyId, $userId, $updateClientId, $arrClientUpdate, $arrClientOffices, false);
                        }

                        if (!$arrUpdateApplicant['success']) {
                            $strError = $this->_tr->translate('Internal error.');
                        } else {
                            // Update static fields correctly
                            $this->saveClientStaticData($updateClientId, $arrApplicantFieldInfo['applicant_field_unique_id'], $fieldValue);
                            if ($updateClientId != $clientId) {
                                $this->saveClientStaticData($clientId, $arrApplicantFieldInfo['applicant_field_unique_id'], $fieldValue);
                            }

                            // Save to log the changes
                            if (isset($arrAllFieldsChangesData[$updateClientId])) {
                                $arrAllFieldsChangesData[$updateClientId]['arrOldData'] = array_merge($arrAllFieldsChangesData[$updateClientId]['arrOldData'], $arrUpdateApplicant['arrOldData']);
                                $arrAllFieldsChangesData[$updateClientId]['arrNewData'] = array_merge($arrAllFieldsChangesData[$updateClientId]['arrNewData'], $arrClientUpdate);
                            } else {
                                $arrAllFieldsChangesData[$updateClientId]['booIsApplicant'] = true;
                                $arrAllFieldsChangesData[$updateClientId]['arrOldData'] = $arrUpdateApplicant['arrOldData'];
                                $arrAllFieldsChangesData[$updateClientId]['arrNewData'] = $arrClientUpdate;
                            }

                            // Update xfdf too
                            $arrSaveDataToXfdf = array(
                                $arrApplicantFieldInfo['applicant_field_unique_id'] => $fieldReadableValue
                            );

                            $arrCasesToUpdate = array_unique($this->getAssignedApplicants($clientId, $this->getMemberTypeIdByName('case')));
                            foreach ($arrCasesToUpdate as $caseId) {
                                if (!$this->_pdf->updateXfdfOnProfileUpdate($companyId, $caseId, $arrSaveDataToXfdf, $memberTypeId)) {
                                    $strError = $this->_tr->translate('XFDF was not updated for case');
                                }
                            }
                        }
                    }
                }
            }

            if (!$booAutomaticReminderAction) {
                $this->_systemTriggers->triggerFieldBulkChanges($companyId, $arrAllFieldsChangesData, true);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return array($strError, $arrAllFieldsChangesData);
    }

    /**
     * Update client's data for static fields
     *
     * @param $memberId
     * @param $companyFieldId
     * @param $fieldVal
     */
    public function saveClientStaticData($memberId, $companyFieldId, $fieldVal)
    {
        if ($this->getFields()->isStaticField($companyFieldId)) {
            $fieldToUpdate = $this->getFields()->getStaticColumnName($companyFieldId);

            if ($fieldToUpdate) {
                $strTable = in_array($companyFieldId, array('file_number', 'agent')) ? 'clients' : 'members';

                // Load list of columns in this table, check if field exists in it
                $select = (new Select())
                    ->from(new TableIdentifier('Columns', 'INFORMATION_SCHEMA'))
                    ->columns(['COLUMN_NAME'])
                    ->where(['TABLE_NAME' => $strTable]);

                $arrColumns = $this->_db2->fetchCol($select);

                if (in_array($fieldToUpdate, $arrColumns)) {
                    $this->_db2->update($strTable, [$fieldToUpdate => $fieldVal], ['member_id' => (int)$memberId]);
                }
            }
        }
    }

    public function getGUID()
    {
        return sprintf('%04X%04X%04X%04X%04X%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }

    public function generateKsKey($caseId)
    {
        $strError = '';
        $newKsKey = '';

        try {
            if (!empty($caseId) && !$this->hasCurrentMemberAccessToMember($caseId)) {
                $strError = $this->_tr->translate('Incorrectly selected case.');
            }

            if (empty($strError)) {
                $newKsKey = $this->getGUID();
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'strError' => $strError,
            'newKsKey' => $newKsKey
        );
    }

    public function updateDependents($caseId, $dependentId, $arrData)
    {
        $result = false;

        try {
            if (empty($dependentId)) {
                $result = $this->_db2->insert('client_form_dependents', $arrData);
            } else {
                $result = $this->_db2->update(
                    'client_form_dependents',
                    $arrData,
                    [
                        'member_id'    => (int)$caseId,
                        'dependent_id' => (int)$dependentId
                    ]
                );
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $result;
    }

    public function getDependentIdsByMemberId($memberId)
    {
        $arrDependentIds = array();

        try {
            if (!empty($memberId)) {
                $select = (new Select())
                    ->from('client_form_dependents')
                    ->columns(['dependent_id'])
                    ->where(['member_id' => (int)$memberId]);

                $arrDependentIds = $this->_db2->fetchCol($select);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrDependentIds;
    }

    public function getDependentsByMemberId($memberId)
    {
        $arrDependents = array();

        try {
            if (!empty($memberId)) {
                $select = (new Select())
                    ->from('client_form_dependents')
                    ->where(['member_id' => (int)$memberId])
                    ->order(array('dependent_id'));

                $arrDependents = $this->_db2->fetchAll($select);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrDependents;
    }

    public function deleteClientAllDependents($arrMemberIds)
    {
        $arrMemberIds = is_array($arrMemberIds) ? $arrMemberIds : array($arrMemberIds);

        foreach ($arrMemberIds as $memberId) {
            $arrDependentsIds = $this->getDependentIdsByMemberId($memberId);
            if (!empty($arrDependentsIds)) {
                $this->deleteDependents($memberId, $arrDependentsIds);
            }
        }
    }


    public function deleteDependents($memberId, $arrDependentsIds)
    {
        try {
            if (!empty($arrDependentsIds)) {
                $result = $this->_db2->delete('client_form_dependents', ['dependent_id' => $arrDependentsIds]);

                $arrMemberInfo = $this->getMemberInfo($memberId);
                $booLocal = $this->_company->isCompanyStorageLocationLocal($arrMemberInfo['company_id']);

                if ($result) {
                    foreach ($arrDependentsIds as $dependentId) {
                        $this->_files->deleteDependentPhoto($arrMemberInfo['company_id'], $memberId, $dependentId, $booLocal);
                        $this->getClientDependents()->deleteDependentUploadedFiles($memberId, $dependentId);
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    public function getClientCurrentTemplateId($memberId)
    {
        $caseTemplateId = 0;

        try {
            $clientInfo = $this->getClientInfo($memberId, true);
            if (empty($memberId) || !$clientInfo || empty($clientInfo['client_type_id'])) {
                $caseTemplateId = $this->getCaseTemplates()->getDefaultCompanyCaseTemplate($this->_auth->getCurrentUserCompanyId());
            } else {
                $caseTemplateId = $clientInfo['client_type_id'];
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $caseTemplateId;
    }

    /**
     * Get dependent info by provided id
     *
     * @param int $dependentId
     * @return array
     */
    public function getDependentInfo($dependentId)
    {
        $select = (new Select())
            ->from('client_form_dependents')
            ->where(['dependent_id' => (int)$dependentId]);

        return $this->_db2->fetchRow($select);
    }

    public function hasCurrentMemberAccessToDependent($dependentId)
    {
        $booHasAccess = false;
        try {
            if (!empty($dependentId) && is_numeric($dependentId)) { // Prevent additional checks if id is incorrect
                if ($this->_auth->isCurrentUserSuperadmin()) {
                    $booHasAccess = true;
                } else {
                    $arrDependentInfo = $this->getDependentInfo($dependentId);

                    if (isset($arrDependentInfo['member_id']) && !empty($arrDependentInfo['member_id'])) {
                        $booHasAccess = $this->hasCurrentMemberAccessToMember($arrDependentInfo['member_id']);
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booHasAccess;
    }

    /**
     * Get count of new clients (for today and for yesterday)
     *
     * @param string $when
     * @param int $fieldIdToShow
     * @return array
     */
    public function getTodayNewClientsCount($when = 'today', $fieldIdToShow = 0)
    {
        $arrRecordsNow = array();
        $arrRecordsBefore = array();

        try {
            $companyId   = $this->_auth->getCurrentUserCompanyId();

            $select = (new Select())
                ->from(array('m' => 'members'))
                ->columns(array('member_id', 'regTime'))
                ->join(array('c' => 'clients'), 'c.member_id = m.member_id', 'client_type_id')
                ->where(
                    [
                        'm.userType' => self::getMemberType('case'),
                        'm.company_id' => $companyId
                    ]
                );

            list($structQuery, $booUseDivisionsTable) = $this->getMemberStructureQuery();

            if ($booUseDivisionsTable) {
                $select->join(array('md' => 'members_divisions'), 'md.member_id = m.member_id', [], Select::JOIN_LEFT);
            }

            if (!empty($structQuery)) {
                $select->where([$structQuery]);
            }

            if (!empty($fieldIdToShow)) {
                $select->join(
                    array('cd' => 'client_form_data'),
                    new PredicateExpression('cd.member_id = m.member_id AND cd.field_id =' . $fieldIdToShow),
                    'value',
                    Select::JOIN_LEFT
                );
            }

            switch ($when) {
                case 'today':
                    $select->where->between('m.regTime', strtotime('today'), time());
                    break;

                case 'weekly':
                    $select->where->between('m.regTime', strtotime('monday this week'), time());
                    break;

                case 'calendar_year':
                    $select->where->between('m.regTime', strtotime('first day of january this year'), time());
                    break;

                default:
                    break;
            }

            $arrRecordsNow = $this->_db2->fetchAll($select);

            $select = (new Select())
                ->from(array('m' => 'members'))
                ->columns(array('member_id', 'regTime'))
                ->join(array('md' => 'members_divisions'), 'md.member_id = m.member_id', [])
                ->join(array('c' => 'clients'), 'c.member_id = m.member_id', 'client_type_id')
                ->where(
                    [
                        'm.userType'   => self::getMemberType('case'),
                        'm.company_id' => $companyId
                    ]
                );

            if (!empty($structQuery)) {
                $select->where([$structQuery]);
            }

            if (!empty($fieldIdToShow)) {
                $select->join(
                    array('cd' => 'client_form_data'),
                    new PredicateExpression('cd.member_id = m.member_id AND field_id =' . $fieldIdToShow),
                    'value',
                    Select::JOIN_LEFT
                );
            }

            switch ($when) {
                case 'today':
                    $select->where->between('m.regTime', strtotime('yesterday') - 24 * 60 * 60, strtotime('yesterday'));
                    break;

                case 'weekly':
                    $select->where->between('m.regTime', strtotime('monday previous week'), strtotime('sunday previous week'));
                    break;

                case 'calendar_year':
                    $select->where->between('m.regTime', strtotime('first day of january previous year'), strtotime('first day of january this year'));
                    break;

                default:
                    break;
            }

            $arrRecordsBefore = $this->_db2->fetchAll($select);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($arrRecordsNow, $arrRecordsBefore);
    }


    /**
     * Load last X clients/cases accessed by the current user
     *
     * @param int $lastX
     * @return array
     */
    public function getLastViewedClientsForTabs($lastX = 20)
    {
        $select = (new Select())
            ->from(array('mla' => 'members_last_access'))
            ->columns([])
            ->join(array('m' => 'members'), 'mla.view_member_id = m.member_id', ['member_id', 'fName', 'lName'])
            ->join(array('mt' => 'members_types'), 'mt.member_type_id = m.userType', 'member_type_name')
            ->join(array('md' => 'members_divisions'), 'md.member_id = m.member_id', [])
            ->join(array('c' => 'clients'), 'c.member_id = m.member_id', array('fileNumber', 'client_type_id'), Select::JOIN_LEFT)
            ->join(array('mr2' => 'members_relations'), 'mr2.child_member_id = m.member_id', [], Select::JOIN_LEFT)
            ->join(
                array('m2' => 'members'),
                new PredicateExpression('mr2.parent_member_id = m2.member_id AND m2.userType IN (?)', self::getMemberType('individual')),
                array(
                    'individual_member_id'  => 'member_id',
                    'individual_first_name' => 'fName',
                    'individual_last_name'  => 'lName'
                ),
                Select::JOIN_LEFT
            )
            ->where(['mla.member_id' => $this->_auth->getCurrentUserId()])
            ->order('mla.access_date DESC')
            ->limit($lastX);

        // Preload employer's info too, if module is enabled
        $booEmployersEnabled = $this->_company->isEmployersModuleEnabledToCompany($this->_auth->getCurrentUserCompanyId());
        if ($booEmployersEnabled) {
            $select->join(array('mr3' => 'members_relations'), 'mr3.child_member_id = m.member_id', [], Select::JOIN_LEFT);
            $select->join(
                array('m3' => 'members'),
                new PredicateExpression('mr3.parent_member_id = m3.member_id AND m3.userType IN (?)', self::getMemberType('employer')),
                array(
                    'employer_member_id'  => 'member_id',
                    'employer_first_name' => 'fName',
                    'employer_last_name'  => 'lName'
                ),
                Select::JOIN_LEFT
            );
        }

        $structQuery = $this->getStructureQueryForClient(false);
        if (!empty($structQuery)) {
            $select->where([$structQuery]);
        }

        $arrFoundRecords = $this->_db2->fetchAll($select);

        $arrApplicants            = array();
        $booHasAccessToFileNumber = $this->getFields()->hasCurrentMemberAccessToField('file_number');
        foreach ($arrFoundRecords as $arrFoundRecord) {
            switch ($arrFoundRecord['member_type_name']) {
                case 'individual':
                case 'employer':
                case 'contact':
                    // This is not a case
                    if (!$booEmployersEnabled && $arrFoundRecord['member_type_name'] == 'employer') {
                        continue 2;
                    }

                    $arrApplicantInfo = $this->generateClientName($arrFoundRecord);

                    $arrApplicants[] = array(
                        'applicantId'   => $arrFoundRecord['member_id'],
                        'applicantName' => $arrApplicantInfo['full_name'],
                        'memberType'    => $arrFoundRecord['member_type_name']
                    );
                    break;

                default:
                    // This is a case

                    // Make sure if there is a parent (IA/Employer)
                    if (empty($arrFoundRecord['individual_member_id']) && empty($arrFoundRecord['employer_member_id'])) {
                        continue 2;
                    }

                    // Generate name of the main parent (IA or Employer)
                    if (!empty($arrFoundRecord['individual_member_id'])) {
                        $arrApplicantInfo = array(
                            'fName' => $arrFoundRecord['individual_first_name'],
                            'lName' => $arrFoundRecord['individual_last_name'],
                        );
                    } else {
                        $arrApplicantInfo = array(
                            'fName' => $arrFoundRecord['employer_first_name'],
                            'lName' => $arrFoundRecord['employer_last_name'],
                        );
                    }
                    $arrApplicantInfo = $this->generateClientName($arrApplicantInfo);

                    $arrApplicantFullInfo = array(
                        'applicantId'   => empty($arrFoundRecord['employer_member_id']) ? $arrFoundRecord['individual_member_id'] : $arrFoundRecord['employer_member_id'],
                        'applicantName' => $arrApplicantInfo['full_name'],
                        'memberType'    => empty($arrFoundRecord['employer_member_id']) ? 'individual' : 'employer',
                        'caseId'        => $arrFoundRecord['member_id'],
                        'caseName'      => $booHasAccessToFileNumber ? $arrFoundRecord['fileNumber'] : '',
                        'caseType'      => $arrFoundRecord['client_type_id'],
                    );

                    // There is Employer for this case
                    if (!empty($arrFoundRecord['employer_member_id'])) {
                        $arrApplicantInfo = array(
                            'fName' => $arrFoundRecord['employer_first_name'],
                            'lName' => $arrFoundRecord['employer_last_name'],
                        );
                        $arrApplicantInfo = $this->generateClientName($arrApplicantInfo);

                        $arrApplicantFullInfo['caseEmployerId'] = $arrFoundRecord['employer_member_id'];
                        $arrApplicantFullInfo['caseEmployerName'] = $arrApplicantInfo['full_name'];
                    }

                    $arrApplicants[] = $arrApplicantFullInfo;
                    break;
            }
        }

        return $arrApplicants;
    }

    /**
     * Generate Applicant's name
     *
     * @param array $arrApplicant
     * @param bool $booShortClientName
     * @return string generated name
     */
    public function generateApplicantName($arrApplicant, $booShortClientName = false)
    {
        $arrApplicant = is_array($arrApplicant) ? $arrApplicant : array();
        $fName = isset($arrApplicant['fName']) ? trim($arrApplicant['fName']) : '';
        $lName = isset($arrApplicant['lName']) ? trim($arrApplicant['lName']) : '';

        if ($booShortClientName) {
            $fName = $fName === '' ? '' : strtoupper(substr($fName, 0, 3));
            $lName = $lName === '' ? '' : strtoupper(substr($lName, 0, 3));

            $spacer = ($fName != '' && $lName != '') ? '.' : '';
        } else {
            $spacer = ($fName != '' && $lName != '') ? ', ' : ' ';
        }

        return trim($lName . $spacer . $fName);
    }

    /**
     * Load allowed member types - used when show access rights during role management
     * @param $booHasAccessToEmployers
     * @param bool $booOnlyId
     * @return array
     */
    public function getAllowedMemberTypes($booHasAccessToEmployers, $booOnlyId = false)
    {
        $arrApplicantAllowedTypes = array(
            array(
                'tab_text_id' => 'individual',
                'tab_id'      => $this->getMemberTypeIdByName('individual'),
                'tab_title'   => 'Individual Profile Fields'
            )
        );

        // Employer fields show only when user's company has access to it
        if ($booHasAccessToEmployers) {
            $arrApplicantAllowedTypes[] = array(
                'tab_text_id' => 'employer',
                'tab_id'      => $this->getMemberTypeIdByName('employer'),
                'tab_title'   => 'Employer Profile Fields'
            );
        }

        $arrApplicantAllowedTypes[] = array(
            'tab_text_id' => 'contact',
            'tab_id'      => $this->getMemberTypeIdByName('contact'),
            'tab_title'   => 'Contact Profile Fields'
        );


        $arrResult = array();
        if ($booOnlyId) {
            foreach ($arrApplicantAllowedTypes as $arrInfo) {
                $arrResult[] = $arrInfo['tab_id'];
            }
        } else {
            $arrResult = $arrApplicantAllowedTypes;
        }

        return $arrResult;
    }

    /**
     * Load specific applicant(s) data
     * @param $companyId
     * @param int|array $memberId
     * @param bool $booCheckFieldAccessRights
     * @param bool $booReplaceComboData
     * @param bool $booParseAgents
     * @param bool $booFormatForXfdf
     * @param array|null $arrFieldIdsLoadOnly
     * @param bool $booLoadParentsWithoutFields
     * @param array $arrInternalContactIds
     * @param bool $booLoadAllMembersData
     * @param null $userId
     * @return array
     * @throws Exception
     */
    public function getApplicantData(
        $companyId,
        $memberId,
        $booCheckFieldAccessRights = true,
        $booReplaceComboData = false,
        $booParseAgents = true,
        $booFormatForXfdf = false,
        $arrFieldIdsLoadOnly = null,
        $booLoadParentsWithoutFields = false,
        $arrInternalContactIds = null,
        $booLoadAllMembersData = false,
        $userId = null
    ) {
        if (empty($companyId) || empty($memberId)) {
            return array();
        }

        $arrMemberIds = is_array($memberId) ? $memberId : array($memberId);
        $arrMemberIds = Settings::arrayUnique($arrMemberIds);

        $arrAllowedFieldIds = array();
        if ($booCheckFieldAccessRights) {
            // Load data only for fields we have access to
            $arrAllowedFields = $this->getApplicantFields()->getUserAllowedFields($userId);

            $count = count($arrAllowedFields);
            if ($count) {
                for ($i = 0; $i < $count; $i++) {
                    $arrAllowedFieldIds[] = $arrAllowedFields[$i]['applicant_field_id'];
                }
            } else {
                $arrAllowedFieldIds = array(0);
            }

            $arrAllowedFieldIds = Settings::arrayUnique($arrAllowedFieldIds);
        }

        $select = (new Select())
            ->from(array('d' => 'applicant_form_data'))
            ->join(array('m' => 'members'), 'd.applicant_id = m.member_id', array('fName', 'lName', 'original_user_type' => 'userType'), Select::JOIN_LEFT)
            ->join(array('f' => 'applicant_form_fields'), 'd.applicant_field_id = f.applicant_field_id', array('field_id' => 'applicant_field_id', 'type', 'field_type_text_id' => 'type', 'applicant_field_unique_id', 'encrypted'), Select::JOIN_LEFT)
            ->where([
                    'd.applicant_id' => $arrMemberIds,
                    (new Where())->notIn('f.type', array('office', 'office_multi'))
                ]
            );

        if (!empty($arrAllowedFieldIds) && count($arrAllowedFieldIds)) {
            $select->where(['d.applicant_field_id' => $arrAllowedFieldIds]);
        }

        $booRunQuery = true;
        if (!is_null($arrFieldIdsLoadOnly)) {
            if (!count($arrFieldIdsLoadOnly)) {
                $booRunQuery = false;
            } else {
                $select->where(['d.applicant_field_id' => $arrFieldIdsLoadOnly]);
            }
        }

        $arrData = $booRunQuery ? $this->_db2->fetchAll($select) : array();

        if (!is_null($arrFieldIdsLoadOnly) && $booLoadAllMembersData) {
            $select = (new Select())
                ->from('members')
                ->columns(array('fName', 'lName', 'original_user_type' => 'userType', 'applicant_id' => 'member_id'))
                ->where(['member_id' => $arrMemberIds]);

            $arrOtherMembersData = $this->_db2->fetchAll($select);

            if (empty($arrData)) {
                $arrData = $arrOtherMembersData;
            } else {
                $arrLoadedClients = [];
                foreach ($arrData as $data) {
                    $arrLoadedClients[$data['applicant_id']] = 1;
                }

                foreach ($arrOtherMembersData as $otherMemberData) {
                    if (!isset($arrLoadedClients[$otherMemberData['applicant_id']])) {
                        $arrData[] = $otherMemberData;
                    }
                }
            }
        }

        // Load applicant internal id in separate requests
        $select = (new Select())
            ->from(array('f' => 'applicant_form_fields'))
            ->where(
                [
                    'f.type'       => array('applicant_internal_id'),
                    'f.company_id' => (int)$companyId
                ]
            );

        if (!empty($arrFieldIdsLoadOnly)) {
            $select->where(['f.applicant_field_id' => $arrFieldIdsLoadOnly]);
        }

        $arrAllApplicantInternalIdFields = $this->_db2->fetchAll($select);

        if (count($arrAllApplicantInternalIdFields)) {
            $arrAllMembersInfo = $this->getMembersSimpleInfo($arrMemberIds);
            $arrParents = $this->getParentsForAssignedApplicants($arrMemberIds);

            foreach ($arrAllMembersInfo as $arrThisMembersInfo) {
                if (in_array($arrThisMembersInfo['userType'], Members::getMemberType('internal_contact'))) {
                    $applicantParentId = $arrParents[$arrThisMembersInfo['member_id']]['parent_member_id'] ?? '';
                } else {
                    $applicantParentId = $arrThisMembersInfo['member_id'];
                }

                foreach ($arrAllApplicantInternalIdFields as $arrApplicantInternalIdFieldInfo) {
                    $arrData[] = array(
                        'fName'              => $arrThisMembersInfo['fName'],
                        'lName'              => $arrThisMembersInfo['lName'],
                        'original_user_type' => $arrThisMembersInfo['userType'],

                        'field_id'                  => $arrApplicantInternalIdFieldInfo['applicant_field_id'],
                        'type'                      => $arrApplicantInternalIdFieldInfo['type'],
                        'field_type_text_id'        => $arrApplicantInternalIdFieldInfo['type'],
                        'applicant_field_unique_id' => $arrApplicantInternalIdFieldInfo['applicant_field_unique_id'],
                        'encrypted'                 => $arrApplicantInternalIdFieldInfo['encrypted'],

                        'applicant_id'       => $arrThisMembersInfo['member_id'],
                        'applicant_field_id' => $arrApplicantInternalIdFieldInfo['applicant_field_id'],
                        'value'              => $applicantParentId,
                        'row'                => 0,
                        'row_id'             => ''
                    );
                }
            }
        }

        // Don't load offices if they were not passed in columns
        $select = (new Select())
            ->from(array('f' => 'applicant_form_fields'))
            ->where(
                [
                    'f.type'       => array('office', 'office_multi'),
                    'f.company_id' => (int)$companyId
                ]
            );

        $arrAllOfficeFields = $this->_db2->fetchAll($select);

        $booLoadMembersDivisions = false;
        if (is_null($arrFieldIdsLoadOnly)) {
            $booLoadMembersDivisions = true;
        } else {
            foreach ($arrAllOfficeFields as $arrOfficeFieldInfo) {
                if (in_array($arrOfficeFieldInfo['applicant_field_id'], $arrFieldIdsLoadOnly)) {
                    $booLoadMembersDivisions = true;
                    break;
                }
            }
        }

        if ($booLoadMembersDivisions) {
            // Load Offices in separate requests because applicant_form_data doesn't have saved values for these fields
            $arrApplicantsDivisions = $this->getMembersDivisions($arrMemberIds);

            $arrGroupedApplicantsDivisions = array();
            foreach ($arrApplicantsDivisions as $arrApplicantDivisions) {
                $arrGroupedApplicantsDivisions[$arrApplicantDivisions['member_id']][] = $arrApplicantDivisions['division_id'];
            }

            if (count($arrGroupedApplicantsDivisions)) {
                $arrAllMembersInfo = $this->getMembersSimpleInfo($arrMemberIds);
                foreach ($arrAllMembersInfo as $arrThisMembersInfo) {
                    foreach ($arrAllOfficeFields as $arrOfficeFieldInfo) {
                        if (!is_null($arrFieldIdsLoadOnly) && !in_array($arrOfficeFieldInfo['applicant_field_id'], $arrFieldIdsLoadOnly)) {
                            continue;
                        }

                        $memberType = in_array($arrThisMembersInfo['userType'], Members::getMemberType('internal_contact')) ? $arrThisMembersInfo['parentUserType'] : $arrThisMembersInfo['userType'];
                        if ($memberType == $arrOfficeFieldInfo['member_type_id']) {
                            if (isset($arrGroupedApplicantsDivisions[$arrThisMembersInfo['member_id']])) {
                                $arrData[] = array(
                                    'fName'              => $arrThisMembersInfo['fName'],
                                    'lName'              => $arrThisMembersInfo['lName'],
                                    'original_user_type' => $arrThisMembersInfo['userType'],

                                    'field_id'                  => $arrOfficeFieldInfo['applicant_field_id'],
                                    'type'                      => $arrOfficeFieldInfo['type'],
                                    'field_type_text_id'        => $arrOfficeFieldInfo['type'],
                                    'applicant_field_unique_id' => $arrOfficeFieldInfo['applicant_field_unique_id'],
                                    'encrypted'                 => $arrOfficeFieldInfo['encrypted'],

                                    'applicant_id'       => $arrThisMembersInfo['member_id'],
                                    'applicant_field_id' => $arrOfficeFieldInfo['applicant_field_id'],
                                    'value'              => implode(',', $arrGroupedApplicantsDivisions[$arrThisMembersInfo['member_id']]),
                                    'row'                => 0,
                                    'row_id'             => ''
                                );
                            }

                            break;
                        }
                    }
                }
            }
        }

        // Load parents' info in separate request - to speed up data loading
        $count = count($arrData);
        $arrParents = array();
        if ($count) {
            $arrInternalContactIds = is_null($arrInternalContactIds) ? $arrMemberIds : $arrInternalContactIds;

            if (is_array($arrInternalContactIds) && count($arrInternalContactIds)) {
                $select2 = (new Select())
                    ->from(array('mr' => 'members_relations'))
                    ->columns(['child_member_id'])
                    ->quantifier(Select::QUANTIFIER_DISTINCT)
                    ->join(array('m2' => 'members'), 'mr.parent_member_id = m2.member_id', array('parent_user_id' => 'member_id', 'parent_user_type' => 'userType', 'parent_first_name' => 'fName', 'parent_last_name' => 'lName'), Select::JOIN_LEFT)
                    ->where(['mr.child_member_id' => $arrInternalContactIds]);

                $arrParents = $this->_db2->fetchAssoc($select2);
            }
        }

        // Decrypt saved data for encrypted fields
        $arrLoadedIds = array();
        for ($i = 0; $i < $count; $i++) {
            if (isset($arrData[$i]['encrypted']) && $arrData[$i]['encrypted'] == 'Y' && !empty($arrData[$i]['value'])) {
                $arrData[$i]['value'] = $this->_encryption->decode($arrData[$i]['value']);
            }

            $booParentExists = isset($arrParents[$arrData[$i]['applicant_id']]);

            $arrData[$i]['parent_user_id'] = $booParentExists ? $arrParents[$arrData[$i]['applicant_id']]['parent_user_id'] : '';
            $arrData[$i]['parent_user_type'] = $booParentExists ? $arrParents[$arrData[$i]['applicant_id']]['parent_user_type'] : '';
            $arrData[$i]['parent_first_name'] = $booParentExists ? $arrParents[$arrData[$i]['applicant_id']]['parent_first_name'] : '';
            $arrData[$i]['parent_last_name'] = $booParentExists ? $arrParents[$arrData[$i]['applicant_id']]['parent_last_name'] : '';

            $arrLoadedIds[] = $arrData[$i]['applicant_id'];
        }

        // Replace combobox/other types with readable info
        if ($booReplaceComboData && $count) {
            $arrData = $this->getFields()->completeFieldsData($companyId, $arrData, true, true, true, true, $booParseAgents, $booFormatForXfdf);
        }

        // In specific cases we want load all members, even if there is no data for them
        if ($booLoadParentsWithoutFields) {
            $arrLoadAdditionalIds = Settings::arrayDiff($arrMemberIds, $arrLoadedIds);
            $arrLoadAdditionalIds = Settings::arrayUnique($arrLoadAdditionalIds);

            if (!empty($arrLoadAdditionalIds)) {
                $select = (new Select())
                    ->from(array('m' => 'members'))
                    ->columns(array('applicant_id' => 'member_id', 'fName', 'lName', 'original_user_type' => 'userType'))
                    ->where(['m.member_id' => $arrLoadAdditionalIds]);

                $arrAdditionalData = $this->_db2->fetchAll($select);

                $count = count($arrAdditionalData);
                for ($i = 0; $i < $count; $i++) {
                    $arrAdditionalDataRow = $arrAdditionalData[$i];
                    $booParentExists = isset($arrParents[$arrAdditionalData[$i]['applicant_id']]);

                    $arrAdditionalDataRow['parent_user_id'] = $booParentExists ? $arrParents[$arrAdditionalData[$i]['applicant_id']]['parent_user_id'] : '';
                    $arrAdditionalDataRow['parent_user_type'] = $booParentExists ? $arrParents[$arrAdditionalData[$i]['applicant_id']]['parent_user_type'] : '';
                    $arrAdditionalDataRow['parent_first_name'] = $booParentExists ? $arrParents[$arrAdditionalData[$i]['applicant_id']]['parent_first_name'] : '';
                    $arrAdditionalDataRow['parent_last_name'] = $booParentExists ? $arrParents[$arrAdditionalData[$i]['applicant_id']]['parent_last_name'] : '';

                    $arrData[] = $arrAdditionalDataRow;
                }
            }
        }

        return $arrData;
    }


    private function _assignContactToApplicant($applicantId, $contactId, $groupId, $row)
    {
        $booIsAlreadyAssigned = false;
        $arrAssignedContacts = $this->getAssignedContacts($applicantId);
        foreach ($arrAssignedContacts as $arrAssignedContactInfo) {
            if ($arrAssignedContactInfo['child_member_id'] == $contactId && $arrAssignedContactInfo['applicant_group_id'] == $groupId) {
                $booIsAlreadyAssigned = true;
                break;
            }
        }

        if (!$booIsAlreadyAssigned) {
            $arrMemberInsertInfo = array(
                'parent_member_id'   => $applicantId,
                'child_member_id'    => $contactId,
                'applicant_group_id' => $groupId,
                'row'                => $row
            );

            $this->_db2->insert('members_relations', $arrMemberInsertInfo);
        }
    }


    /**
     * Assign contact to the applicant
     *
     * @Note we need group id to know where we need show/load contact's info
     *        also, `row` is used to identify the row number (if group is repeatable)
     *
     * @param $arrData
     * @return bool true on success
     */
    public function assignContactToApplicant($arrData)
    {
        try {
            $applicantId = $arrData['applicant_id'];
            $contactId = $arrData['contact_id'];
            $groupId = $arrData['group_id'];
            $row = $arrData['row'];

            $this->_assignContactToApplicant($applicantId, $contactId, $groupId, $row);

            // If the same contact is used in another group (because groups are in the same block) -
            // automatically assign contact to this group too
            $arrGroupInfo = $this->getApplicantFields()->getGroupInfoById($groupId);
            $arrBlockGroupIds = $this->getApplicantFields()->getBlockGroups($arrGroupInfo['applicant_block_id']);
            if (count($arrBlockGroupIds) > 1) {
                foreach ($arrBlockGroupIds as $blockGroupId) {
                    $this->_assignContactToApplicant($applicantId, $contactId, $blockGroupId, $row);
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
     * Get maximum row for the applicant + contact + group
     *
     * @param $applicantId
     * @param $groupId
     * @return int max row
     */
    public function getRowForApplicant($applicantId, $groupId)
    {
        $select = (new Select())
            ->from(array('r' => 'members_relations'))
            ->columns(['row' => new Expression('MAX(`row`)')])
            ->where(
                [
                    'r.parent_member_id'   => (int)$applicantId,
                    'r.applicant_group_id' => (int)$groupId
                ]
            );

        return $this->_db2->fetchOne($select);
    }

    /**
     * Delete all previously assigned contacts except of provided
     *
     * @param $applicantId
     * @param $arrContactIds
     * @return bool true on success
     */
    public function updateAssignedContacts($applicantId, $arrContactIds)
    {
        try {
            if (is_array($arrContactIds) && count($arrContactIds)) {
                $arrAssignedCases = $this->getAssignedCases($applicantId);
                if (count($arrAssignedCases)) {
                    $arrContactIds = array_merge($arrContactIds, $arrAssignedCases);
                }

                // Rows that we should delete from members_relations table
                $this->_db2->delete(
                    'members_relations',
                    [
                        'parent_member_id' => (int)$applicantId,
                        (new Where())->notIn('child_member_id', $arrContactIds)
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
     * Assign case to the applicant
     *
     * @param array $arrData
     * @return bool true on success
     */
    public function assignCaseToApplicant($arrData)
    {
        try {
            $arrAssignedCases = $this->getAssignedCases($arrData['applicant_id'], false);
            if (!is_array($arrAssignedCases) || !count($arrAssignedCases) || !in_array($arrData['case_id'], $arrAssignedCases)) {
                $arrMemberInsertInfo = array(
                    'parent_member_id'   => $arrData['applicant_id'],
                    'child_member_id'    => $arrData['case_id'],
                    'applicant_group_id' => null,
                    'row'                => null
                );

                $this->_db2->insert('members_relations', $arrMemberInsertInfo);
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Calculate the 'case order for the linked employer' and save it
     *
     * @param int $employerId
     * @param int $caseId
     * @return void
     */
    public function calculateAndUpdateCaseNumberForEmployer($employerId, $caseId)
    {
        $parentEmployerCasesCount = $this->getClientCaseMaxNumber($employerId, true);
        $parentEmployerCasesCount = empty($parentEmployerCasesCount) ? 1 : $parentEmployerCasesCount + 1;

        $this->updateCaseNumberForEmployer($caseId, $parentEmployerCasesCount);
    }

    /**
     * Update 'case order for the linked employer'
     *
     * @param int|array $caseId
     * @param int|null $parentEmployerCasesCount
     * @return void
     */
    public function updateCaseNumberForEmployer($caseId, $parentEmployerCasesCount)
    {
        $this->_db2->update(
            'clients',
            ['case_number_of_parent_employer' => $parentEmployerCasesCount],
            ['member_id' => $caseId]
        );
    }

    /**
     * Unassign cases from the applicant
     *
     * @param int $applicantId
     * @param array $arrCasesIds
     * @return bool
     */
    public function unassignCasesFromApplicant($applicantId, $arrCasesIds)
    {
        try {
            $this->_db2->delete(
                'members_relations',
                [
                    'parent_member_id' => (int)$applicantId,
                    'child_member_id'  => array_map('intval', $arrCasesIds)
                ]
            );

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }


    /**
     * Get assigned contact id by parent applicant id and group id
     *
     * @param $applicantId
     * @param $groupId
     * @return int|array contact id (member id)
     */
    public function getAssignedContact($applicantId, $groupId)
    {
        $booLoadAll = is_array($applicantId) || is_array($groupId);
        $arrSelect  = $booLoadAll ? array('*') : array('child_member_id');

        $select = (new Select())
            ->from(array('r' => 'members_relations'))
            ->columns($arrSelect)
            ->join(array('m' => 'members'), 'r.child_member_id = m.member_id', [], Select::JOIN_LEFT)
            ->where(
                [
                    (new Where())
                        ->equalTo('m.userType', (int)$this->getMemberTypeIdByName('internal_contact'))
                        ->equalTo('r.row', 0)
                ]
            )
            ->where(
                [
                    'r.parent_member_id' => $applicantId,
                    'r.applicant_group_id' => $groupId
                ]
            );

        return $booLoadAll ? $this->_db2->fetchAll($select) : $this->_db2->fetchOne($select);
    }

    /**
     * Get all assigned contacts
     *
     * @param $applicantId
     * @param bool $booIdsOnly
     * @return array
     */
    public function getAssignedContacts($applicantId, $booIdsOnly = false)
    {
        $arrContacts = array();
        try {
            if ((is_numeric($applicantId) && !empty($applicantId)) || (is_array($applicantId) && count($applicantId))) {
                $select = (new Select())
                    ->from(array('r' => 'members_relations'))
                    ->join(array('m' => 'members'), 'r.child_member_id = m.member_id', [], Select::JOIN_LEFT)
                    ->join(array('g' => 'applicant_form_groups'), 'g.applicant_group_id = r.applicant_group_id', [], Select::JOIN_LEFT)
                    ->join(array('b' => 'applicant_form_blocks'), 'b.applicant_block_id = g.applicant_block_id', 'repeatable', Select::JOIN_LEFT)
                    ->where(
                        [
                            'm.userType' => (int)$this->getMemberTypeIdByName('internal_contact'),
                            'r.parent_member_id' => $applicantId
                        ]
                    )
                    ->order(array('r.applicant_group_id', 'r.row'));

                $arrSavedContacts = $this->_db2->fetchAll($select);

                if ($booIdsOnly) {
                    foreach ($arrSavedContacts as $arrSavedContactInfo) {
                        $arrContacts[] = $arrSavedContactInfo['child_member_id'];
                    }
                    $arrContacts = Settings::arrayUnique($arrContacts);
                } else {
                    $arrContacts = $arrSavedContacts;
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrContacts;
    }

    /**
     * Get assigned cases ids by parent applicant id
     *
     * @param int|array $applicantId
     * @return array cases ids (members ids)
     */
    public function getAssignedCases($applicantId, $booSortByName = true)
    {
        $select = (new Select())
            ->from(array('r' => 'members_relations'))
            ->columns(['child_member_id'])
            ->join(array('m' => 'members'), 'r.child_member_id = m.member_id', [], Select::JOIN_LEFT)
            ->where(
                [
                    'r.parent_member_id' => is_array($applicantId) ? $applicantId : (int)$applicantId,
                    'm.userType'         => (int)$this->getMemberTypeIdByName('case')
                ]
            );

        if ($booSortByName) {
            $select->order(array('m.lName', 'm.fName'));
        }

        return $this->_db2->fetchCol($select);
    }

    /**
     * Get case ids from the provided list
     *
     * @param array $arrMemberIds
     * @return array
     */
    public function filterCasesFromTheList($arrMemberIds)
    {
        $arrCaseIds = array();

        try {
            if (!empty($arrMemberIds)) {
                $select = (new Select())
                    ->from('members')
                    ->columns(['member_id'])
                    ->where(
                        [
                            'member_id' => $arrMemberIds,
                            'userType'  => Members::getMemberType('case')
                        ]
                    );

                $arrCaseIds = $this->_db2->fetchCol($select);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrCaseIds;
    }

    /**
     * Get case ids from the provided list
     * if specific ID is not case - get parents and load assigned cases for these parents
     *
     * @param array $arrMemberIds
     * @param bool $booLoadAllCases true to load all cases or only 1 for each IA/Employer
     * @return array
     */
    public function getCasesFromTheList($arrMemberIds, $booLoadAllCases)
    {
        $arrCaseIds = array();

        try {
            if (!empty($arrMemberIds)) {
                $arrCaseIds = $this->filterCasesFromTheList($arrMemberIds);

                // For IA/Employers load cases
                $arrNotCasesIds = array_diff($arrMemberIds, $arrCaseIds);
                if (!empty($arrNotCasesIds)) {
                    $arrParentIds = array_unique(array_merge($this->getParentsForAssignedApplicant($arrNotCasesIds), $arrNotCasesIds));
                    if (!empty($arrParentIds)) {
                        $arrAssignedCases = $this->getAssignedApplicants($arrParentIds, Members::getMemberType('case'), true);

                        if ($booLoadAllCases) {
                            foreach ($arrAssignedCases as $arrAssignedCaseInfo) {
                                $arrCaseIds[] = $arrAssignedCaseInfo['child_member_id'];
                            }
                        } else {
                            // Use/load only one case id per client,
                            // So grouped data will have the correct count
                            $arrMemberIds = array();
                            foreach ($arrAssignedCases as $arrAssignedCaseInfo) {
                                $arrMemberIds[$arrAssignedCaseInfo['parent_member_id']] = $arrAssignedCaseInfo['child_member_id'];
                            }
                            $arrCaseIds = array_merge($arrCaseIds, array_unique(array_values($arrMemberIds)));
                        }

                        $arrCaseIds = array_unique($arrCaseIds);
                    }
                }
            }
        } catch (Exception $e) {
            $arrCaseIds = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrCaseIds;
    }


    /**
     * Load maximum cases count assigned to the specific client (IA OR Employer)
     *
     * @param int $parentClientId
     * @param bool $booEmployer
     * @return int
     */
    public function getClientCaseMaxNumber($parentClientId, $booEmployer)
    {
        $number = 0;
        try {
            $key = $booEmployer ? 'case_number_of_parent_employer' : 'case_number_of_parent_client';

            $select = (new Select())
                ->from(array('r' => 'members_relations'))
                ->columns(['child_member_id'])
                ->join(array('m' => 'members'), 'r.child_member_id = m.member_id', [], Select::JOIN_LEFT)
                ->join(array('c' => 'clients'), 'r.child_member_id = c.member_id', $key, Select::JOIN_LEFT)
                ->where(
                    [
                        'r.parent_member_id' => (int)$parentClientId,
                        'm.userType'         => (int)$this->getMemberTypeIdByName('case')
                    ]
                );

            $arrAssignedCases = $this->_db2->fetchAll($select);
            foreach ($arrAssignedCases as $arrAssignedCaseInfo) {
                $number = max($arrAssignedCaseInfo[$key], $number);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $number;
    }

    /**
     * Get the number (order) of the case for a specific client
     *
     * @param int $parentClientId
     * @param int $caseId
     * @return int
     */
    public function getCaseNumberForClient($parentClientId, $caseId)
    {
        $number = 0;
        try {
            $select = (new Select())
                ->from(array('r' => 'members_relations'))
                ->columns(['child_member_id'])
                ->join(array('m' => 'members'), 'r.child_member_id = m.member_id', [], Select::JOIN_LEFT)
                ->where(
                    [
                        'r.parent_member_id' => (int)$parentClientId,
                        'm.userType'         => (int)$this->getMemberTypeIdByName('case')
                    ]
                )
                ->order('child_member_id ASC');

            $arrAssignedCases = $this->_db2->fetchAll($select);

            $booFound = false;
            if (!empty($caseId)) {
                foreach ($arrAssignedCases as $key => $arrAssignedCaseInfo) {
                    if ($arrAssignedCaseInfo['child_member_id'] == $caseId) {
                        $number   = $key + 1;
                        $booFound = true;

                        break;
                    }
                }
            }

            if (!$booFound) {
                $number = count($arrAssignedCases) + 1;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $number;
    }

    /**
     * Load maximum cases count in a company
     * @param int $companyId
     * @param int $caseTypeId
     * @return int
     */
    public function getCompanyCaseMaxNumber($companyId, $caseTypeId = 0)
    {
        $number = 0;
        try {
            $maxRow = $caseTypeId ? new Expression('MAX(case_number_with_same_case_type_in_company)') : new Expression('MAX(case_number_in_company)');
            $select = (new Select())
                ->from(array('c' => 'clients'))
                ->columns(['max_row' => $maxRow])
                ->join(array('m' => 'members'), 'c.member_id = m.member_id', [], Select::JOIN_LEFT)
                ->where(['m.company_id' => (int)$companyId]);

            if ($caseTypeId) {
                $select->where->equalTo('c.client_type_id', (int)$caseTypeId);
            }

            $number = $this->_db2->fetchOne($select);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $number;
    }

    /**
     * Get assigned applicant ids by parent applicant id
     * @param $applicantId
     * @param $childMemberTypeId
     * @param $booAllCols
     * @return array applicant ids (member ids)
     */
    public function getAssignedApplicants($applicantId, $childMemberTypeId = 0, $booAllCols = false)
    {
        $arrAssignedApplicants = array();

        if (!empty($applicantId)) {
            $select = (new Select())
                ->from(array('r' => 'members_relations'))
                ->columns([$booAllCols ? Select::SQL_STAR : 'child_member_id'])
                ->where(['r.parent_member_id' => $applicantId])
                ->order('row');

            // Load specific children only
            if (!empty($childMemberTypeId)) {
                $select->join(array('m' => 'members'), 'r.child_member_id = m.member_id', [], Select::JOIN_LEFT);
                $select->where(['m.userType' => $childMemberTypeId]);
            }

            if ($booAllCols) {
                $arrAssignedApplicants = $this->_db2->fetchAll($select);
            } else {
                $arrAssignedApplicants = $this->_db2->fetchCol($select);
                $arrAssignedApplicants = array_map('intval', $arrAssignedApplicants);
            }
        }

        return $arrAssignedApplicants;
    }

    /**
     * Get the last assigned case for specific client
     *
     * @param int $applicantId
     * @param bool $booOnlyActive true to load the last active case
     * @return int case id
     */
    public function getLastAssignedCase($applicantId, $booOnlyActive)
    {
        $caseId = 0;

        try {
            if (!empty($applicantId)) {
                $arrMemberInfo = $this->getMemberInfo($applicantId);

                $select = (new Select())
                    ->from(array('r' => 'members_relations'))
                    ->columns(['child_member_id'])
                    ->join(array('m' => 'members'), 'r.child_member_id = m.member_id', [], Select::JOIN_LEFT)
                    ->where(
                        [
                            'r.parent_member_id' => (int)$applicantId,
                            'm.userType'         => (int)$this->getMemberTypeIdByName('case')
                        ]
                    )
                    ->order('r.child_member_id DESC');

                $arrAssignedCaseIds = $this->_db2->fetchCol($select);
                if (count($arrAssignedCaseIds)) {
                    if ($booOnlyActive) {
                        $arrAssignedCaseIds = $this->getActiveClientsList(
                            $arrAssignedCaseIds,
                            true,
                            $arrMemberInfo['company_id']
                        );
                    }

                    if (is_array($arrAssignedCaseIds) && count($arrAssignedCaseIds)) {
                        $caseId = max($arrAssignedCaseIds);
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $caseId;
    }

    /**
     * Get assigned applicant ids by parent applicant id (and all sub applicants)
     *
     * @param int|array $applicantId
     * @param int $level - indicates the current level (we need this to stop script if needed)
     * @param int $childMemberTypeId
     * @return array of applicant ids
     */
    public function getAllAssignedApplicants($applicantId, $level = 1, $childMemberTypeId = 0)
    {
        $arrAssignedApplicantsIds = $this->getAssignedApplicants($applicantId, $childMemberTypeId);
        if (count($arrAssignedApplicantsIds) && $level < 10) {
            $arrAssignedSubApplicants = $this->getAllAssignedApplicants($arrAssignedApplicantsIds, $level + 1, $childMemberTypeId);
            $arrAssignedApplicantsIds = array_merge($arrAssignedApplicantsIds, $arrAssignedSubApplicants);
        }

        return array_unique($arrAssignedApplicantsIds);
    }

    /**
     * Get assigned applicant ids by child applicant id
     *
     * @param int|array $applicantId
     * @param int $parentMemberTypeId
     * @return array applicant ids (member ids)
     */
    public function getParentsForAssignedApplicant($applicantId, $parentMemberTypeId = 0)
    {
        $arrIds = [];
        if (is_numeric($applicantId) || (is_array($applicantId) && count($applicantId))) {
            $select = (new Select())
                ->from(array('r' => 'members_relations'))
                ->columns(['parent_member_id'])
                ->join(array('m' => 'members'), 'r.parent_member_id = m.member_id', [], Select::JOIN_LEFT)
                ->where(['r.child_member_id' => $applicantId])
                ->order('row');

            if (!empty($parentMemberTypeId)) {
                $select->where->equalTo('m.userType', (int)$parentMemberTypeId);
            }

            $arrIds = $this->_db2->fetchCol($select);
            $arrIds = Settings::arrayUnique(array_map('intval', $arrIds));
        }

        return $arrIds;
    }


    /**
     * Create applicant's record in members table + save data
     *
     * @param $companyId
     * @param $divisionGroupId
     * @param $createdByMemberId
     * @param $memberTypeId
     * @param $applicantTypeId
     * @param $arrApplicantData
     * @param $arrApplicantOffices
     * @param null $createdOn
     * @param null $parentApplicantId
     * @return bool|int false on error, otherwise applicant id
     */
    public function createApplicant($companyId, $divisionGroupId, $createdByMemberId, $memberTypeId, $applicantTypeId, $arrApplicantData, $arrApplicantOffices, $createdOn = null, $parentApplicantId = null)
    {
        $applicantId = false;
        try {
            if (is_numeric($companyId) && is_numeric($memberTypeId) && is_array($arrApplicantData)) {
                $username = $password = $booLoginEnabled = null;
                foreach ($arrApplicantData as $key => $arrData) {
                    switch ($arrData['field_unique_id']) {
                        case 'username':
                            $username = $arrData['value'];
                            break;

                        case 'password':
                            $password                        = $this->_encryption->hashPassword($arrData['value']);
                            $arrApplicantData[$key]['value'] = '*******';
                            break;

                        case 'disable_login':
                            $booLoginEnabled = $this->getApplicantFields()->getDefaultFieldOptionValue($arrData['value']) == 'Enabled';
                            break;

                        default:
                            break;
                    }
                }

                // Create record in general Members table
                $arrMemberInsertInfo = array(
                    'company_id' => $companyId,
                    'division_group_id' => empty($divisionGroupId) ? $this->_company->getCompanyDivisions()->getCompanyMainDivisionGroupId($companyId) : $divisionGroupId,
                    'userType' => $memberTypeId,
                    'lName' => '',
                    'username' => $username,
                    'password' => $password,
                    'regTime' => is_null($createdOn) ? time() : $createdOn,
                    'status' => 1,
                    'login_enabled' => $booLoginEnabled ? 'Y' : 'N'
                );

                if (empty($arrMemberInsertInfo['division_group_id'])) {
                    unset($arrMemberInsertInfo['division_group_id']);
                }

                // Update/use login, password and login enabled fields only when we've received them
                if (is_null($username)) {
                    unset($arrMemberInsertInfo['username']);
                }
                if (is_null($password)) {
                    unset($arrMemberInsertInfo['password']);
                }
                if (is_null($booLoginEnabled)) {
                    unset($arrMemberInsertInfo['login_enabled']);
                }

                $applicantId = $this->_db2->insert('members', $arrMemberInsertInfo);

                // Create a record in Clients table too
                $arrClientInfo = array(
                    'member_id'          => $applicantId,
                    'added_by_member_id' => $createdByMemberId,
                );

                if (is_numeric($applicantTypeId) && !empty($applicantTypeId)) {
                    $arrClientInfo['applicant_type_id'] = $applicantTypeId;
                }

                $this->_db2->insert('clients', $arrClientInfo);

                // Create new role for this client
                switch ($this->getMemberTypeNameById($memberTypeId)) {
                    case 'employer':
                        $roleType = 'employer_client';
                        break;

                    case 'individual':
                        $roleType = 'individual_client';
                        break;

                    default:
                        $roleType = '';
                        break;
                }

                if (!empty($roleType)) {
                    $this->updateMemberRoles($applicantId, array($this->getRoleIdByRoleType($roleType, $companyId)));
                }

                // Save related offices
                $arrResult = $this->updateApplicantOffices($applicantId, $arrApplicantOffices, true, true, false, 'access_to', $parentApplicantId, $divisionGroupId);
                // Save applicant's data
                if ($arrResult === false) {
                    $applicantId = false;
                } elseif (count($arrApplicantData)) {
                    if (is_array($arrResult) && count($arrResult)) {
                        foreach ($arrApplicantData as $key => $fieldData) {
                            if ($fieldData['field_type'] == 'office_change_date_time') {
                                $arrApplicantData[$key]['value'] = $arrResult['value'];
                            }
                        }
                    }

                    $arrCreateResult = $this->updateApplicantData($applicantId, $arrApplicantData);
                    if (!$arrCreateResult['success']) {
                        $applicantId = false;
                    }
                }
            }
        } catch (Exception $e) {
            $applicantId = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $applicantId;
    }

    /**
     * Update Applicant's credentials (username/password)
     *
     * @param int $applicantId
     * @param int $companyId
     * @param int $usernameFieldId
     * @param string $username
     * @param string $password
     * @return bool true on success
     */
    public function updateApplicantCredentials($applicantId, $companyId, $usernameFieldId, $username, $password)
    {
        $booSuccess = false;

        try {
            // Update member info
            $arrUpdate = array(
                'username'             => $username === '' ? null : $username,
                'password'             => $password === '' ? null : $this->_encryption->hashPassword($password),
                'password_change_date' => time()
            );

            $this->_db2->update(
                'members',
                $arrUpdate,
                [
                    'member_id'  => $applicantId,
                    'company_id' => $companyId
                ]
            );

            // Update username field value
            $arrSavedFieldData = $this->getApplicantFields()->getFieldData($usernameFieldId, $applicantId);
            $arrApplicantData = array();
            if (!is_array($arrSavedFieldData) || !count($arrSavedFieldData)) {
                $arrApplicantData[] = array(
                    'field_id' => $usernameFieldId,
                    'value' => $username,
                    'row' => 0,
                    'row_id' => ''
                );
            } else {
                foreach ($arrSavedFieldData as $arrSavedFieldDataRow) {
                    $arrApplicantData[] = array(
                        'field_id' => $usernameFieldId,
                        'value' => $username,
                        'row' => $arrSavedFieldDataRow['row'],
                        'row_id' => $arrSavedFieldDataRow['row_id']
                    );
                }
            }

            // Save applicant's data
            if (count($arrApplicantData)) {
                $arrUpdateResult = $this->updateApplicantData($applicantId, $arrApplicantData);
                $booSuccess = $arrUpdateResult['success'];
            } else {
                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Check if applicant already exists in DB and get its id
     * Identify applicant by: company id, first/last name, member type, applicant type
     *
     * @param int $companyId
     * @param array $arrClientInfo
     * @return int applicant id, if such applicant already exists
     */
    public function getApplicantByInfo($companyId, $arrClientInfo)
    {
        $applicantId = 0;
        try {
            $select = (new Select())
                ->from(array('m' => 'members'))
                ->columns(['member_id'])
                ->join(array('c' => 'clients'), 'c.member_id = m.member_id', [], Select::JOIN_LEFT)
                ->where(
                    [
                        'm.company_id' => (int)$companyId,
                        'm.lName'      => $arrClientInfo['lName'],
                        'm.userType'   => (int)$arrClientInfo['memberTypeId']
                    ]
                );

            if (empty($arrClientInfo['fName'])) {
                $select->where
                    ->nest()
                    ->isNull('m.fName')
                    ->or
                    ->equalTo('m.fName', '')
                    ->unnest();
            } else {
                $select->where->equalTo('m.fName', $arrClientInfo['fName']);
            }

            if (empty($arrClientInfo['applicantTypeId'])) {
                $select->where->isNull('c.applicant_type_id');
            } else {
                $select->where->equalTo('c.applicant_type_id', (int)$arrClientInfo['applicantTypeId']);
            }

            $applicantId = $this->_db2->fetchOne($select);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $applicantId;
    }

    /**
     * Update applicant's data
     *
     * @param $companyId
     * @param $updatedByMemberId
     * @param $applicantId
     * @param $arrApplicantData
     * @param $arrApplicantOffices
     * @param bool $booUpdateOffices
     * @return array (bool|int false on error, otherwise applicant id, array $arrChangeOfficeFieldToUpdate)
     */
    public function updateApplicant($companyId, $updatedByMemberId, $applicantId, $arrApplicantData, $arrApplicantOffices, $booUpdateOffices = true)
    {
        $booSuccess = false;
        $arrOldData = array();
        $arrChangeOfficeFieldToUpdate = array();

        try {
            if (is_numeric($companyId) && is_array($arrApplicantData)) {
                // Search and update 'can member login' field in DB
                $booLoginEnabled = null;
                $arrFieldIds = array();

                foreach ($arrApplicantData as $arrData) {
                    $arrFieldIds[] = $arrData['field_id'];

                    if ($arrData['field_unique_id'] == 'disable_login') {
                        $booLoginEnabled = $this->getApplicantFields()->getDefaultFieldOptionValue($arrData['value']) == 'Enabled';
                    }
                }

                if (!is_null($booLoginEnabled)) {
                    // Update a record in Members table
                    $arrMemberInfo = array(
                        'login_enabled' => $booLoginEnabled ? 'Y' : 'N'
                    );
                    $this->_db2->update('members', $arrMemberInfo, ['member_id' => (int)$applicantId]);
                }


                // Update a record in Clients table
                $arrClientInfo = array(
                    'modified_on' => date('c'),
                    'modified_by' => $updatedByMemberId,
                );
                $this->_db2->update('clients', $arrClientInfo, ['member_id' => (int)$applicantId]);


                // Update role for this client (if it wasn't assigned before - it will be assigned now)
                $arrMemberRoleIds = $this->getMemberRoles($applicantId);
                if (!count($arrMemberRoleIds)) {
                    $arrClientInfo = $this->getMemberInfo($applicantId);
                    $memberTypeId = $this->getMemberTypeNameById($arrClientInfo['userType']);
                    if (in_array($memberTypeId, array('employer', 'individual'))) {
                        $roleType = $this->getMemberTypeNameById($memberTypeId) == 'employer' ? 'employer_client' : 'individual_client';
                        $this->updateMemberRoles($applicantId, array($this->getRoleIdByRoleType($roleType, $companyId)));
                    }
                }

                if (count($arrFieldIds)) {
                    // Load already saved data
                    $arrOldData = $this->getApplicantFields()->getApplicantFieldsData($applicantId, $arrFieldIds);
                    foreach ($arrOldData as $key => $arrValues) {
                        $arrOldData[$key]['field_id'] = $arrValues['applicant_field_id'];
                    }
                }

                // Save related offices
                if ($booUpdateOffices) {
                    $arrChangeOfficeFieldToUpdate = $this->updateApplicantOffices($applicantId, $arrApplicantOffices);
                }

                // Save applicant's data
                if (count($arrApplicantData)) {
                    if (is_array($arrChangeOfficeFieldToUpdate) && count($arrChangeOfficeFieldToUpdate)) {
                        foreach ($arrApplicantData as $key => $fieldData) {
                            if (isset($fieldData['field_type']) && $fieldData['field_type'] == 'office_change_date_time') {
                                $arrApplicantData[$key]['value'] = $arrChangeOfficeFieldToUpdate['value'];
                            }
                        }
                    }

                    $arrUpdateResult = $this->updateApplicantData($applicantId, $arrApplicantData);
                    $booSuccess = $arrUpdateResult['success'];
                    if (!$booSuccess) {
                        $arrOldData = array();
                    }
                } else {
                    $booSuccess = true;
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array('success' => $booSuccess, 'changeOfficeFieldToUpdate' => $arrChangeOfficeFieldToUpdate, 'arrOldData' => $arrOldData);
    }

    /**
     * Update Applicant's type
     * e.g. contacts can be 'Sales Agent', 'Translator'
     *      IA/Employers don't have types (so will be null)
     *
     * @param int $applicantId
     * @param int $applicantTypeId
     * @return bool true on success
     */
    public function updateApplicantType($applicantId, $applicantTypeId)
    {
        try {
            $this->_db2->update(
                'clients',
                ['applicant_type_id' => $applicantTypeId],
                ['member_id' => (int)$applicantId]
            );
            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Update Applicant's data
     *
     * @param int $applicantId
     * @param array $arrApplicantData
     * @return array (bool true on success, array of data before update)
     */
    public function updateApplicantData($applicantId, $arrApplicantData)
    {
        $query = '';
        $booSuccess = false;
        $arrOldData = array();

        try {
            // Collect field ids
            $arrFieldIds = array();
            foreach ($arrApplicantData as $arrData) {
                $arrFieldIds[] = $arrData['field_id'];
            }

            if (count($arrFieldIds)) {
                // Load already saved data
                $arrOldData = $this->getApplicantFields()->getApplicantFieldsData($applicantId, $arrFieldIds);
                foreach ($arrOldData as $key => $arrValues) {
                    $arrOldData[$key]['field_id'] = $arrValues['applicant_field_id'];
                }

                // Clear previously saved data (for received fields)
                $this->_db2->delete(
                    'applicant_form_data',
                    [
                        'applicant_id' => (int)$applicantId,
                        'applicant_field_id' => $arrFieldIds
                    ]
                );

                // Save all applicant's fields
                $arrFieldsInfo = $this->getApplicantFields()->getFieldsInfo($arrFieldIds);
                foreach ($arrApplicantData as $arrData) {
                    if (is_array($arrData) && array_key_exists('value', $arrData) && $arrData['value'] !== '' && !is_array($arrData['value'])) {
                        // Encrypt data, if needed
                        if ((is_string($arrData['field_id']) || is_integer($arrData['field_id'])) && array_key_exists($arrData['field_id'], $arrFieldsInfo)) {
                            if ($arrFieldsInfo[$arrData['field_id']]['encrypted'] == 'Y') {
                                $arrData['value'] = $this->_encryption->encode($arrData['value']);
                            }

                            $this->_db2->insert(
                                'applicant_form_data',
                                [
                                    'applicant_id'       => (int)$applicantId,
                                    'applicant_field_id' => (int)$arrData['field_id'],
                                    'value'              => $arrData['value'],
                                    'row'                => isset($arrData['row']) ? (int)$arrData['row'] : 0,
                                    'row_id'             => empty($arrData['row_id']) ? null : $arrData['row_id']
                                ]
                            );
                        }
                    }
                }

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile(
                $e->getMessage(),
                $e->getTraceAsString() . PHP_EOL . 'DATA:' . print_r($arrApplicantData, true) . PHP_EOL . 'QUERY:' . $query
            );
        }

        return array('success' => $booSuccess, 'arrOldData' => $arrOldData);
    }

    /**
     * Save provided applicant's offices
     *
     * @param $applicantId
     * @param $arrApplicantOffices
     * @param bool $booDeleteBeforeInsert
     * @param bool $booCheckIfOfficeChanged
     * @param bool $booSaveToLog
     * @param string $type
     * @param $parentApplicantId
     * @param null $divisionGroupId
     * @return array $arrChangeOfficeFieldToUpdate
     */
    public function updateApplicantOffices($applicantId, $arrApplicantOffices, $booDeleteBeforeInsert = true, $booCheckIfOfficeChanged = true, $booSaveToLog = false, $type = 'access_to', $parentApplicantId = null, $divisionGroupId = null)
    {
        $arrChangeOfficeFieldToUpdate = array();

        try {
            $divisionGroupId         = is_null($divisionGroupId) ? $this->_auth->getCurrentUserDivisionGroupId() : $divisionGroupId;
            $arrUserCompanyDivisions = $this->_company->getCompanyDivisions()->getDivisionsByGroupId($divisionGroupId);

            $arrOldValues = array();
            if ($booCheckIfOfficeChanged) {
                $arrOldValues = $this->getApplicantOffices(array($applicantId), $divisionGroupId);
            }

            if ($booDeleteBeforeInsert) {
                $divisionIdWhere = array();
                $divisionIdWhere['member_id'] = (int)$applicantId;
                $divisionIdWhere['type'] = $type;
                // Delete offices for the current user group only
                if (!empty($arrUserCompanyDivisions)) {
                    $divisionIdWhere['division_id'] = $arrUserCompanyDivisions;
                }
                $this->_db2->delete('members_divisions', $divisionIdWhere);
            }

            foreach ($arrApplicantOffices as $officeId) {
                // Insert offices for the current user group only
                if (!empty($arrUserCompanyDivisions) && !in_array($officeId, $arrUserCompanyDivisions)) {
                    continue;
                }

                $this->_db2->insert(
                    'members_divisions',
                    [
                        'member_id'   => (int)$applicantId,
                        'division_id' => (int)$officeId,
                        'type'        => $type
                    ],
                    null,
                    false
                );
            }

            $arrNewValues = $this->getApplicantOffices(array($applicantId), $divisionGroupId);

            if (($booCheckIfOfficeChanged && $arrOldValues == $arrNewValues) || $type != 'access_to') {
                return $arrChangeOfficeFieldToUpdate;
            }

            $arrMemberInfo = $this->getMemberInfo($applicantId);

            // Collect data that must be updated
            $arrToUpdateGrouped = array(
                'office' => array(),
                'office_change_date' => array(),
            );
            switch ($arrMemberInfo['userType']) {
                case $this->getMemberTypeIdByName('case'):
                    // Load fields list for this case
                    $arrCaseFields = $this->getFields()->getCompanyFields($arrMemberInfo['company_id']);

                    // Search for required fields
                    foreach ($arrCaseFields as $arrCaseFieldInfo) {
                        if (in_array($arrCaseFieldInfo['type'], array('office', 'office_multi'))) {
                            $arrToUpdateGrouped['office'][] = array(
                                'booCase' => true,
                                'fieldId' => $arrCaseFieldInfo['field_id'],
                                'applicantId' => $applicantId,
                            );
                        }

                        if ('office_change_date_time' == $arrCaseFieldInfo['type']) {
                            $arrToUpdateGrouped['office_change_date'][] = array(
                                'fieldId' => $arrCaseFieldInfo['field_id'],
                                'applicantId' => $applicantId,
                            );
                        }

                        if (count($arrToUpdateGrouped['office']) && count($arrToUpdateGrouped['office_change_date'])) {
                            break;
                        }
                    }

                    // Load parents -> search for required fields
                    if (count($arrToUpdateGrouped['office']) && count($arrToUpdateGrouped['office_change_date'])) {
                        // Load parents for this case
                        $arrParentIds = $this->getParentsForAssignedApplicants(array($applicantId), false, false);

                        foreach ($arrParentIds as $arrParentLinkInfo) {
                            $arrClientInfo = $this->getClientInfoOnly($arrParentLinkInfo['parent_member_id']);
                            $arrParentInfo = $this->getMemberInfo($arrParentLinkInfo['parent_member_id']);

                            // Load fields list for the first parent
                            $arrCompanyFields = $this->getApplicantFields()->getCompanyFields(
                                $arrParentInfo['company_id'],
                                $arrParentInfo['userType'],
                                $arrClientInfo['applicant_type_id']
                            );

                            // Search for required fields
                            foreach ($arrCompanyFields as $arrCompanyFieldInfo) {
                                if (in_array($arrCompanyFieldInfo['type'], array('office', 'office_multi'))) {
                                    $arrToUpdateGrouped['office'][] = array(
                                        'booCase' => false,
                                        'fieldId' => $arrCompanyFieldInfo['applicant_field_id'],
                                        'applicantId' => $applicantId,
                                    );
                                }

                                if ('office_change_date_time' == $arrCompanyFieldInfo['type']) {
                                    $arrToUpdateGrouped['office_change_date'][] = array(
                                        'fieldId' => $arrCompanyFieldInfo['applicant_field_id'],
                                        'applicantId' => $arrParentLinkInfo['parent_member_id'],
                                    );
                                }

                                if (count($arrToUpdateGrouped['office']) && count($arrToUpdateGrouped['office_change_date'])) {
                                    break;
                                }
                            }
                        }
                    }
                    break;

                case $this->getMemberTypeIdByName('internal_contact'):
                    // Load parents for this internal contact
                    if (!$parentApplicantId) {
                        $arrParents = $this->getParentsForAssignedApplicants(array($applicantId));
                    } else {
                        $arrParents = array(
                            0 => array(
                                'parent_member_id' => $parentApplicantId
                            )
                        );
                    }
                    foreach ($arrParents as $arrParentLinkInfo) {
                        $arrClientInfo = $this->getClientInfoOnly($arrParentLinkInfo['parent_member_id']);
                        $arrParentInfo = $this->getMemberInfo($arrParentLinkInfo['parent_member_id']);
                        $arrAssignedCaseIds = $this->getAssignedCases($arrParentLinkInfo['parent_member_id']);

                        // Load fields list for the first parent
                        $arrGroupedCompanyFields = $this->getApplicantFields()->getGroupedCompanyFields(
                            $arrParentInfo['company_id'],
                            $arrParentInfo['userType'],
                            $arrClientInfo['applicant_type_id']
                        );

                        // Search for fields for this applicant type
                        foreach ($arrGroupedCompanyFields as $arrBlockInfo) {
                            if (!isset($arrBlockInfo['fields']) || $arrBlockInfo['group_contact_block'] != 'Y') {
                                continue;
                            }

                            foreach ($arrBlockInfo['fields'] as $arrCompanyFieldInfo) {
                                if (in_array($arrCompanyFieldInfo['field_type'], array('office', 'office_multi'))) {
                                    foreach ($arrAssignedCaseIds as $caseId) {
                                        $arrToUpdateGrouped['office'][] = array(
                                            'booCase' => false,
                                            'fieldId' => $arrCompanyFieldInfo['field_id'],
                                            'applicantId' => $caseId
                                        );
                                    }
                                }

                                if ('office_change_date_time' == $arrCompanyFieldInfo['field_type']) {
                                    $arrToUpdateGrouped['office_change_date'][] = array(
                                        'fieldId' => $arrCompanyFieldInfo['field_id'],
                                        'applicantId' => $applicantId,
                                    );
                                }

                                if (count($arrToUpdateGrouped['office']) && count($arrToUpdateGrouped['office_change_date'])) {
                                    break;
                                }
                            }
                        }
                    }
                    break;

                default:
                    // This is not a Case, but can be: IA/Employer/Contact

                    // Load company fields for this applicant type
                    $arrClientInfo = $this->getClientInfoOnly($applicantId);
                    $arrGroupedCompanyFields = $this->getApplicantFields()->getGroupedCompanyFields(
                        $arrMemberInfo['company_id'],
                        $arrMemberInfo['userType'],
                        $arrClientInfo['applicant_type_id']
                    );

                    $arrAssignedCaseIds = $this->getAssignedCases($applicantId);

                    // Search for fields for this applicant type
                    foreach ($arrGroupedCompanyFields as $arrBlockInfo) {
                        if (!isset($arrBlockInfo['fields']) || $arrBlockInfo['group_contact_block'] == 'Y') {
                            continue;
                        }

                        foreach ($arrBlockInfo['fields'] as $arrCompanyFieldInfo) {
                            if (in_array($arrCompanyFieldInfo['field_type'], array('office', 'office_multi'))) {
                                foreach ($arrAssignedCaseIds as $caseId) {
                                    $arrToUpdateGrouped['office'][] = array(
                                        'booCase' => false,
                                        'fieldId' => $arrCompanyFieldInfo['field_id'],
                                        'applicantId' => $caseId
                                    );
                                }
                            }

                            if ('office_change_date_time' == $arrCompanyFieldInfo['field_type']) {
                                $arrToUpdateGrouped['office_change_date'][] = array(
                                    'fieldId' => $arrCompanyFieldInfo['field_id'],
                                    'applicantId' => $applicantId,
                                );
                            }

                            if (count($arrToUpdateGrouped['office']) && count($arrToUpdateGrouped['office_change_date'])) {
                                break;
                            }
                        }
                    }
                    break;
            }

            // If Office field was found - save a note if needed
            if ($booSaveToLog && count($arrToUpdateGrouped['office'])) {
                $arrInfoToLog = array();
                foreach ($arrToUpdateGrouped['office'] as $arrGroupedInfo) {
                    $arrInfoToLog[$arrGroupedInfo['applicantId']]['booIsApplicant'] = !$arrGroupedInfo['booCase'];

                    $arrInfoToLog[$arrGroupedInfo['applicantId']]['arrOldData'] = array(
                        array(
                            'field_id' => $arrGroupedInfo['fieldId'],
                            'row' => 0,
                            'value' => implode(',', $arrOldValues)
                        )
                    );

                    $arrInfoToLog[$arrGroupedInfo['applicantId']]['arrNewData'] = array(
                        array(
                            'field_id' => $arrGroupedInfo['fieldId'],
                            'row' => 0,
                            'value' => implode(',', $arrNewValues)
                        )
                    );
                }

                $this->_systemTriggers->triggerFieldBulkChanges($arrMemberInfo['company_id'], $arrInfoToLog, true);
            }

            // If "Office change date" field was found - update date/time
            if (count($arrToUpdateGrouped['office_change_date'])) {
                foreach ($arrToUpdateGrouped['office_change_date'] as $arrGroupedInfo) {
                    $arrNewData = array(
                        array(
                            'field_id' => $arrGroupedInfo['fieldId'],
                            'value' => date("Y-m-d H:i:s")
                        )
                    );
                    $this->updateApplicantData($arrGroupedInfo['applicantId'], $arrNewData);
                    $arrChangeOfficeFieldToUpdate = array(
                        'value' => $arrNewData[0]['value'],
                        'field_id' => $arrGroupedInfo['fieldId']
                    );
                }
            }
        } catch (Exception $e) {
            $arrChangeOfficeFieldToUpdate = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrChangeOfficeFieldToUpdate;
    }


    /**
     * Update applicant's info in relation to the inner contact records
     *
     * @param $companyId
     * @param $applicantId
     * @param int $memberTypeId - member type of the applicant
     * @param int $applicantType
     * @return bool true on success
     */
    public function updateApplicantFromMainContact($companyId, $applicantId, $memberTypeId, $applicantType)
    {
        try {
            // Find the main contact for this applicant
            $arrCompanyGroups = $this->getApplicantFields()->getCompanyGroups($companyId, $memberTypeId, $applicantType);
            $contactGroupId = 0;
            foreach ($arrCompanyGroups as $arrCompanyGroupInfo) {
                if ($arrCompanyGroupInfo['contact_block'] == 'Y' && $arrCompanyGroupInfo['repeatable'] == 'N') {
                    $contactGroupId = $arrCompanyGroupInfo['applicant_group_id'];
                    break;
                }
            }

            $arrUpdate = array(
                'fName' => null,
                'lName' => ''
            );
            if (!empty($contactGroupId)) {
                $mainContactId = $this->getAssignedContact($applicantId, $contactGroupId);
                $arrData = $this->getApplicantData($companyId, $mainContactId, false);
                if (is_array($arrData) && count($arrData)) {
                    // Get main contact fields (first name, last name, company name).
                    $contactTypeId = $this->getMemberTypeIdByName('internal_contact');
                    $arrContactFields = $this->getApplicantFields()->getCompanyFields($companyId, $contactTypeId);

                    $firstNameFieldId = $lastNameFieldId = $companyNameFieldId = $emailFieldId = 0;
                    foreach ($arrContactFields as $arrContactFieldInfo) {
                        switch ($arrContactFieldInfo['applicant_field_unique_id']) {
                            case 'given_names':
                            case 'first_name':
                                $firstNameFieldId = $arrContactFieldInfo['applicant_field_id'];
                                break;

                            case 'family_name':
                            case 'last_name':
                                $lastNameFieldId = $arrContactFieldInfo['applicant_field_id'];
                                break;

                            case 'entity_name':
                                $companyNameFieldId = $arrContactFieldInfo['applicant_field_id'];
                                break;

                            case 'email':
                                $emailFieldId = $arrContactFieldInfo['applicant_field_id'];
                                break;

                            default:
                                break;
                        }
                    }

                    foreach ($arrData as $arrContactData) {
                        // We need only the first row
                        if ($arrContactData['row']) {
                            continue;
                        }

                        switch ($arrContactData['applicant_field_id']) {
                            case $firstNameFieldId:
                                $arrUpdate['fName'] = $arrContactData['value'];
                                break;

                            case $lastNameFieldId:
                                $arrUpdate['lName'] = $arrContactData['value'];
                                break;

                            case $companyNameFieldId:
                                $arrUpdate['entity_name'] = $arrContactData['value'];
                                break;

                            case $emailFieldId:
                                $arrUpdate['emailAddress'] = $arrContactData['value'];
                                break;

                            default:
                                break;
                        }
                    }
                }
            }

            // Some contacts can have company name, instead of regular first/last name fields
            if (empty($arrUpdate['lName']) && array_key_exists('entity_name', $arrUpdate)) {
                $arrUpdate['lName'] = $arrUpdate['entity_name'];
            }
            unset($arrUpdate['entity_name']);

            if (count($arrUpdate)) {
                $this->_db2->update('members', $arrUpdate, ['member_id' => $applicantId]);
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Update inner contact record from applicant's info
     *
     * @param $applicantId
     * @return bool true on success
     */
    public function updateMainContactFromApplicant($applicantId)
    {
        try {
            $arrApplicantInfo = $this->getClientInfo($applicantId);
            if (is_array($arrApplicantInfo) && count($arrApplicantInfo)) {
                // Find the main contact for this applicant
                $arrCompanyGroups = $this->getApplicantFields()->getCompanyGroups($arrApplicantInfo['company_id'], $arrApplicantInfo['userType'], $arrApplicantInfo['applicant_type_id']);
                $arrCompanyFields = $this->getApplicantFields()->getCompanyFields($arrApplicantInfo['company_id'], $arrApplicantInfo['userType'], $arrApplicantInfo['applicant_type_id']);
                $contactGroupId = 0;
                foreach ($arrCompanyGroups as $arrCompanyGroupInfo) {
                    if ($arrCompanyGroupInfo['contact_block'] == 'Y' && $arrCompanyGroupInfo['repeatable'] == 'N') {
                        $contactGroupId = $arrCompanyGroupInfo['applicant_group_id'];
                        break;
                    }
                }

                $mainContactId = 0;
                if (!empty($contactGroupId)) {
                    $mainContactId = $this->getAssignedContact($applicantId, $contactGroupId);
                }

                if (!empty($mainContactId)) {
                    // These are the fields we want update in the 'data' table
                    $arrFieldsMapping = array(
                        'given_names' => 'fName',
                        'first_name'  => 'fName',
                        'family_name' => 'lName',
                        'last_name'   => 'lName',
                        'entity_name' => 'lName',
                        'email'       => 'emailAddress',
                    );

                    foreach ($arrCompanyFields as $arrApplicantFieldInfo) {
                        if ($arrApplicantFieldInfo['repeatable'] == 'N' && array_key_exists($arrApplicantFieldInfo['applicant_field_unique_id'], $arrFieldsMapping)) {
                            $this->_db2->update(
                                'applicant_form_data',
                                [
                                    'value' => $arrApplicantInfo[$arrFieldsMapping[$arrApplicantFieldInfo['applicant_field_unique_id']]]
                                ],
                                [
                                    'applicant_id'       => (int)$mainContactId,
                                    'applicant_field_id' => (int)$arrApplicantFieldInfo['applicant_field_id'],
                                    'row'                => 0
                                ]
                            );
                        }
                    }
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
     * Load assigned offices list for specific applicants (clients)
     * @param array $arrMemberIds
     * @param int $divisionGroupId
     * @return array
     */
    public function getApplicantOffices($arrMemberIds, $divisionGroupId = 0)
    {
        $arrOfficeIds = array();

        if (is_array($arrMemberIds) && count($arrMemberIds)) {
            $select = (new Select())
                ->from(array('d' => 'members_divisions'))
                ->columns(['division_id'])
                ->where(
                    [
                        'd.member_id' => $arrMemberIds,
                        'd.type'      => 'access_to'
                    ]
                );

            if (!empty($divisionGroupId)) {
                $arrGroupDivisions = $this->_company->getCompanyDivisions()->getDivisionsByGroupId($divisionGroupId);
                if (!empty($arrGroupDivisions)) {
                    $select->where(['d.division_id' => $arrGroupDivisions]);
                }
            }

            $arrOfficeIds = $this->_db2->fetchCol($select);
        }

        return array_unique($arrOfficeIds);
    }


    /**
     * Update applicant's offices from all assigned sub applicants (contacts/cases)
     *
     * @param $applicantId
     * @param $arrApplicantOfficeFieldIds
     * @return bool|array
     */
    public function updateApplicantOfficesFromChildren($applicantId, $arrApplicantOfficeFieldIds)
    {
        try {
            $result = array();

            // Get offices from child contacts
            $arrAssignedChildren = $this->getAllAssignedApplicants($applicantId, 1, $this->getMemberTypeIdByName('internal_contact'));
            if (count($arrAssignedChildren)) {
                $arrMemberIds = array_merge($arrAssignedChildren, array($applicantId));
                $arrAllOffices = $this->getApplicantOffices($arrMemberIds);

                $this->updateApplicantOffices($applicantId, $arrAllOffices);

                $arrApplicantOfficeFieldIds = array_unique($arrApplicantOfficeFieldIds);
                if (count($arrApplicantOfficeFieldIds)) {
                    $arrWhere = [];

                    $arrWhere['applicant_id']       = (int)$applicantId;
                    $arrWhere['applicant_field_id'] = $arrApplicantOfficeFieldIds;

                    $strOffices = count($arrAllOffices) ? implode(',', $arrAllOffices) : '';
                    $this->_db2->update('applicant_form_data', ['value' => $strOffices], $arrWhere);
                }

                $result = $arrAllOffices;
            }
        } catch (Exception $e) {
            $result = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $result;
    }

    /**
     * Update case's offices list from parent record
     *
     * @param $arrCaseIds
     * @return bool true on success
     */
    public function updateCaseOfficesFromParent($arrCaseIds)
    {
        try {
            if (is_array($arrCaseIds) && count($arrCaseIds)) {
                $arrParentMemberIds = $this->getParentsForAssignedApplicant($arrCaseIds);

                if (is_array($arrParentMemberIds) && count($arrParentMemberIds)) {
                    $arrAllOffices = $this->getApplicantOffices($arrParentMemberIds);

                    if (is_array($arrAllOffices) && count($arrAllOffices)) {
                        foreach ($arrCaseIds as $caseId) {
                            $this->updateApplicantOffices($caseId, $arrAllOffices);
                        }
                    }
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
     * Update case(s) primary email address from parent record
     *
     * @param $applicantId
     * @return bool true on success
     */
    public function updateCaseEmailFromParent($applicantId)
    {
        try {
            // Get this applicant email address
            $arrMemberInfo = $this->getMemberInfo($applicantId);
            if (is_array($arrMemberInfo) && array_key_exists('emailAddress', $arrMemberInfo) && !empty($arrMemberInfo['emailAddress'])) {
                // Get Cases list for this applicant
                $arrCaseIdsToUpdate = array();
                $arrCaseIds = $this->getAssignedApplicants($applicantId, $this->getMemberTypeIdByName('case'));
                if (is_array($arrCaseIds) && count($arrCaseIds)) {
                    // Update cases for IA/Employer only
                    switch ($arrMemberInfo['userType']) {
                        case $this->getMemberTypeIdByName('individual'):
                            // Update all child cases for this IA
                            $arrCaseIdsToUpdate = $arrCaseIds;
                            break;

                        case $this->getMemberTypeIdByName('employer'):
                            // Update all child cases for this Employer, which are not assigned to IA
                            $arrParents = $this->getParentsForAssignedApplicants($arrCaseIds);
                            foreach ($arrCaseIds as $caseId) {
                                if (array_key_exists($caseId, $arrParents) && $arrParents[$caseId]['parent_member_id'] == $applicantId) {
                                    $arrCaseIdsToUpdate[] = $caseId;
                                }
                            }
                            break;

                        default:
                            break;
                    }
                }

                if (count($arrCaseIdsToUpdate)) {
                    $this->_db2->update(
                        'members',
                        ['emailAddress' => $arrMemberInfo['emailAddress']],
                        ['member_id' => $arrCaseIdsToUpdate]
                    );
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
     * Load cases list with their applicants
     * If case isn't assigned to applicant - he will be not returned
     *
     * @param $arrApplicantIds
     * @return array
     */
    public function getApplicantsCases($arrApplicantIds)
    {
        $arrCasesList = array();
        if (is_array($arrApplicantIds) && count($arrApplicantIds)) {
            $select = (new Select())
                ->from(array('r' => 'members_relations'))
                ->join(array('m' => 'members'), 'm.member_id = r.child_member_id', Select::SQL_STAR, Select::JOIN_LEFT)
                ->join(array('c' => 'clients'), 'c.member_id = r.child_member_id', 'fileNumber', Select::JOIN_LEFT)
                ->join(array('m2' => 'members'), 'm2.member_id = r.parent_member_id', array('applicant_name' => 'lName'), Select::JOIN_LEFT)
                ->where(
                    [
                        'r.parent_member_id' => $arrApplicantIds,
                        'm.userType'         => (int)$this->getMemberTypeIdByName('case')
                    ]
                )
                ->order(array('m.lName', 'm.fName'));

            $arrCasesWithApplicants = $this->_db2->fetchAll($select);

            foreach ($arrCasesWithApplicants as $arrCaseInfo) {
                $arrCaseInfo = $this->generateClientName($arrCaseInfo);

                $arrCasesList[$arrCaseInfo['parent_member_id']][] = array(
                    'case_id'   => $arrCaseInfo['member_id'],
                    'case_name' => $arrCaseInfo['full_name_with_file_num'],
                );
            }
        }

        return $arrCasesList;
    }

    /**
     * Load applicants and cases list in relation to incoming params
     *
     * @param $searchId
     * @param array $arrQueryWords
     * @param $searchFor
     * @param true $booAllClients to load all clients, false to return only active
     * @param true $booReturnIdsOnly to return ids only, without additional data loading
     * @param int $searchQueryLimit limit records found for the quick search, if empty - no limt
     * @return array
     */
    public function getApplicantsAndCases($searchId, $arrQueryWords, $searchFor, $booAllClients = false, $booReturnIdsOnly = false, $searchQueryLimit = 0)
    {
        try {
            $arrResult = array();

            if ($searchId === 'all') {
                $arrMemberIds = $this->getMembersWhichICanAccess();
            } else {
                $arrMemberIds = $this->getMembersWhichICanAccess(static::getMemberType('individual_employer_internal_contact'));
            }

            $arrCases                  = array();
            $arrFoundMembersIds        = array();
            $booFilterByFoundCasesOnly = false;
            if (!empty($arrQueryWords)) {
                if (!empty($arrMemberIds)) {
                    $companyId        = $this->_auth->getCurrentUserCompanyId();
                    $arrCompanyFields = $this->getApplicantFields()->getCompanyAllFieldsInAssignedGroups($companyId);

                    $arrSkippedFields  = [];
                    $arrComboFieldsIds = array();
                    foreach ($arrCompanyFields as $arrCompanyFieldInfo) {
                        if ($arrCompanyFieldInfo['encrypted'] == 'Y' || in_array($arrCompanyFieldInfo['type'], ['checkbox', 'password', 'client_referrals', 'client_profile_id'])) {
                            $arrSkippedFields[] = (int)$arrCompanyFieldInfo['applicant_field_id'];
                        }

                        if ($arrCompanyFieldInfo['type'] == 'combo' || $arrCompanyFieldInfo['type'] == 'multiple_combo') {
                            $arrComboFieldsIds[] = (int)$arrCompanyFieldInfo['applicant_field_id'];
                        }
                    }

                    $arrAllowedFieldIds = array();
                    $arrAllowedFields   = $this->getApplicantFields()->getUserAllowedFields();
                    foreach ($arrAllowedFields as $arrAllowedFieldInfo) {
                        if (!in_array($arrAllowedFieldInfo['applicant_field_id'], $arrAllowedFieldIds) && !in_array($arrAllowedFieldInfo['applicant_field_id'], $arrSkippedFields)) {
                            $arrAllowedFieldIds[] = $arrAllowedFieldInfo['applicant_field_id'];
                        }
                    }

                    // Search for Applicants
                    if (count($arrAllowedFieldIds)) {
                        $arrGroupedFieldsIds  = array();
                        $arrFieldTypesToGroup = array(
                            'country',
                            'assigned_to',
                            'agents',
                            'office',
                            'office_multi',
                        );


                        // Search if "custom" fields are used by the company
                        foreach ($arrCompanyFields as $arrCompanyFieldInfo) {
                            if (!in_array($arrCompanyFieldInfo['applicant_field_id'], $arrAllowedFieldIds)) {
                                continue;
                            }

                            foreach ($arrFieldTypesToGroup as $textFieldId) {
                                if ($arrCompanyFieldInfo['type'] == $textFieldId) {
                                    $arrGroupedFieldsIds[$textFieldId][] = $arrCompanyFieldInfo['applicant_field_id'];
                                }
                            }
                        }

                        // Load the list of possible options for the "Assigned To" field
                        $arrOptionsForCustomFields = array();
                        if (isset($arrGroupedFieldsIds['assigned_to']) && !empty($arrGroupedFieldsIds['assigned_to'])) {
                            /** @var Users $oUsers */
                            $oUsers = $this->_serviceContainer->get(Users::class);

                            $arrOptionsForCustomFields['assigned_to'] = $oUsers->getAssignList('search', null, true);
                        }

                        // Load the list of possible options for the "Agents" field
                        if (isset($arrGroupedFieldsIds['agents']) && !empty($arrGroupedFieldsIds['agents'])) {
                            $arrAgents = $this->getAgents();

                            $arrOptionsForCustomFields['agents'] = array();
                            if (is_array($arrAgents) && count($arrAgents) > 0) {
                                foreach ($arrAgents as $agentInfo) {
                                    $arrOptionsForCustomFields['agents'][] = array(
                                        'option_id'   => $agentInfo['agent_id'],
                                        'option_name' => $this->generateAgentName($agentInfo, false)
                                    );
                                }
                            }
                        }

                        // Load the list of possible options for the "Office" field
                        if ((isset($arrGroupedFieldsIds['office']) && !empty($arrGroupedFieldsIds['office'])) || (isset($arrGroupedFieldsIds['office_multi']) && !empty($arrGroupedFieldsIds['office_multi']))) {
                            $arrOffices = $this->getDivisions();
                            if (is_array($arrOffices) && count($arrOffices) > 0) {
                                foreach ($arrOffices as $officeInfo) {
                                    $arrOptionsForCustomFields['office'][] = array(
                                        'option_id'   => $officeInfo['division_id'],
                                        'option_name' => $officeInfo['name']
                                    );
                                }
                            }
                        }

                        $arrGroupedFoundMembersIdsByWords = array();
                        foreach ($arrQueryWords as $word) {
                            $wherePredicate = (new Where())->nest();

                            // Search by comboboxes/radios
                            $select = (new Select())
                                ->from(array('d' => 'applicant_form_default'))
                                ->columns(array('applicant_field_id', 'applicant_form_default_id'))
                                ->where(['d.applicant_field_id' => $arrAllowedFieldIds])
                                ->where(
                                    [
                                        (new Where())->like('d.value', '%' . $word . '%')
                                    ]
                                );

                            $arrComboValues = $this->_db2->fetchAll($select);

                            if (is_array($arrComboValues) && count($arrComboValues)) {
                                foreach ($arrComboValues as $arrComboInfo) {
                                    $wherePredicate
                                        ->or
                                        ->nest()
                                        ->equalTo('d.applicant_field_id', $arrComboInfo['applicant_field_id'])
                                        ->and
                                        ->equalTo('d.value', $arrComboInfo['applicant_form_default_id'])
                                        ->unnest();
                                }
                            }

                            // Search by Office
                            if ((isset($arrGroupedFieldsIds['office']) && !empty($arrGroupedFieldsIds['office'])) || (isset($arrGroupedFieldsIds['office_multi']) && !empty($arrGroupedFieldsIds['office_multi']))) {
                                $arrFoundDivisions = array();
                                foreach ($arrOptionsForCustomFields['office'] as $arrOfficeInfo) {
                                    if (mb_stripos($arrOfficeInfo['option_name'], $word) !== false) {
                                        $arrFoundDivisions[] = $arrOfficeInfo['option_id'];
                                    }
                                }

                                if (!empty($arrFoundDivisions)) {
                                    $arrFoundMembers = $this->getMembersAssignedToDivisions($arrFoundDivisions, 'access_to', 'case');
                                    if (!empty($arrFoundMembers)) {
                                        $arrFoundMembers = Settings::arrayUnique($arrFoundMembers);
                                        $wherePredicate
                                            ->or
                                            ->in('d.applicant_id', $arrFoundMembers);
                                    }
                                }
                            }

                            // Search by countries
                            if (isset($arrGroupedFieldsIds['country']) && !empty($arrGroupedFieldsIds['country'])) {
                                $select = (new Select())
                                    ->from(array('c' => 'country_master'))
                                    ->columns(array('countries_id'))
                                    ->where(
                                        [
                                            'c.countries_name' => '%' . $word . '%',
                                            'c.type' => 'general'
                                        ]
                                    );

                                $arrFoundCountriesIds = $this->_db2->fetchCol($select);

                                if (!empty($arrFoundCountriesIds)) {
                                    foreach ($arrGroupedFieldsIds['country'] as $countryFieldId) {
                                        if (!is_array($arrFoundCountriesIds)) {
                                            $arrFoundCountriesIds = [$arrFoundCountriesIds];
                                        }
                                        $wherePredicate
                                            ->or
                                            ->nest()
                                            ->equalTo('d.applicant_field_id', (int)$countryFieldId)
                                            ->and
                                            ->in('d.value', $arrFoundCountriesIds)
                                            ->unnest();
                                    }
                                }
                            }

                            // Search by "Assigned To" staff
                            if (isset($arrGroupedFieldsIds['assigned_to']) && !empty($arrGroupedFieldsIds['assigned_to'])) {
                                $arrFoundAssignedTo = array();
                                foreach ($arrOptionsForCustomFields['assigned_to'] as $arrAssignedToInfo) {
                                    if (mb_stripos($arrAssignedToInfo['assign_to_name'], $word) !== false) {
                                        $arrFoundAssignedTo[] = $arrAssignedToInfo['assign_to_id'];
                                    }
                                }

                                if (!empty($arrFoundAssignedTo)) {
                                    foreach ($arrGroupedFieldsIds['assigned_to'] as $assignedToFieldId) {
                                        if (!is_array($arrFoundAssignedTo)) {
                                            $arrFoundAssignedTo = [$arrFoundAssignedTo];
                                        }
                                        $wherePredicate
                                            ->or
                                            ->nest()
                                            ->equalTo('d.applicant_field_id', (int)$assignedToFieldId)
                                            ->and
                                            ->in('d.value', $arrFoundAssignedTo)
                                            ->unnest();
                                    }
                                }
                            }

                            // Search by "Agents"
                            if (isset($arrGroupedFieldsIds['agents']) && !empty($arrGroupedFieldsIds['agents'])) {
                                $arrFoundAgents = array();
                                foreach ($arrOptionsForCustomFields['agents'] as $arrAssignedToInfo) {
                                    if (mb_stripos($arrAssignedToInfo['option_name'], $word) !== false) {
                                        $arrFoundAgents[] = $arrAssignedToInfo['option_id'];
                                    }
                                }

                                if (!empty($arrFoundAgents)) {
                                    foreach ($arrGroupedFieldsIds['agents'] as $agentFieldId) {
                                        if (!is_array($arrFoundAgents)) {
                                            $arrFoundAgents = [$arrFoundAgents];
                                        }
                                        $wherePredicate
                                            ->or
                                            ->nest()
                                            ->equalTo('d.applicant_field_id', (int)$agentFieldId)
                                            ->and
                                            ->in('d.value', $arrFoundAgents)
                                            ->unnest();
                                    }
                                }
                            }


                            // Search by the text, don't search by fields that we already checked
                            $searchByFields = array_diff($arrAllowedFieldIds, $arrComboFieldsIds);
                            foreach ($arrGroupedFieldsIds as $arrFieldsToSkip) {
                                $searchByFields = array_diff($searchByFields, $arrFieldsToSkip);
                            }

                            $wherePredicate
                                ->or
                                ->nest()
                                ->in('d.applicant_field_id', $searchByFields)
                                ->and
                                ->like('d.value', '%' . $word . '%')
                                ->unnest()
                                ->unnest();

                            $select = (new Select())
                                ->from(array('d' => 'applicant_form_data'))
                                ->columns(array('applicant_id'))
                                ->where([$wherePredicate]);

                            $arrFoundApplicantsIds = $this->_db2->fetchCol($select);
                            $arrFoundApplicantsIds = array_map('intval', $arrFoundApplicantsIds);
                            $arrFoundApplicantsIds = array_intersect(Settings::arrayUnique($arrFoundApplicantsIds), $arrMemberIds);

                            if (!empty($arrFoundApplicantsIds)) {
                                $arrFoundApplicantsParentsIds = $this->getParentsForAssignedApplicant($arrFoundApplicantsIds);
                                $arrFoundApplicantsIds        = array_map('intval', array_merge($arrFoundApplicantsIds, $arrFoundApplicantsParentsIds));
                                $arrFoundApplicantsIds        = Settings::arrayUnique($arrFoundApplicantsIds);
                            }

                            $arrGroupedFoundMembersIdsByWords[$word] = $arrFoundApplicantsIds;
                        }


                        // Search for Cases
                        if ($searchFor == 'applicants') {
                            $arrCasesGroupedByWords = $this->getSearch()->runSearch($arrQueryWords, $booAllClients);

                            $level                = 0;
                            $arrClientsInAllWords = array();
                            foreach ($arrQueryWords as $word) {
                                $arrGroupedCasesClientsIdsByWords[$word] = array();
                                if (isset($arrCasesGroupedByWords[$word])) {
                                    $arrGroupedCasesClientsIdsByWords[$word] = Settings::arrayUnique($this->getParentsForAssignedApplicant($arrCasesGroupedByWords[$word]));
                                }

                                $arrUnitedClientsInWords = array_merge($arrGroupedCasesClientsIdsByWords[$word], $arrGroupedFoundMembersIdsByWords[$word]);

                                if (empty($level)) {
                                    $arrClientsInAllWords = $arrUnitedClientsInWords;
                                } else {
                                    $arrClientsInAllWords = array_intersect($arrClientsInAllWords, $arrUnitedClientsInWords);
                                }

                                $level++;
                            }


                            // For clients, we just want to find records that have all the words
                            $level = 0;
                            foreach ($arrGroupedFoundMembersIdsByWords as $arrClientIds) {
                                if (empty($level)) {
                                    $arrFoundMembersIds = array_intersect($arrClientsInAllWords, $arrClientIds);
                                } else {
                                    $arrFoundMembersIds = array_intersect($arrFoundMembersIds, $arrClientIds);
                                }

                                $level++;
                            }
                            $arrFoundMembersIds = Settings::arrayUnique($arrFoundMembersIds);

                            foreach ($arrCasesGroupedByWords as $arrGroupedCasesIdsByWords) {
                                foreach ($arrGroupedCasesIdsByWords as $caseId) {
                                    if (!empty(array_intersect($this->getParentsForAssignedApplicant($caseId), $arrClientsInAllWords))) {
                                        $arrCases[] = $caseId;
                                    }
                                }
                            }
                            $arrCases = Settings::arrayUnique($arrCases);
                        } else {
                            // For clients, we just want to find records that have all the words
                            $level = 0;
                            foreach ($arrGroupedFoundMembersIdsByWords as $arrClientIds) {
                                if (empty($level)) {
                                    $arrFoundMembersIds = $arrClientIds;
                                } else {
                                    $arrFoundMembersIds = array_intersect($arrFoundMembersIds, $arrClientIds);
                                }

                                $level++;
                            }
                        }

                        // And load assigned cases list for these found clients
                        if (is_array($arrFoundMembersIds) && count($arrFoundMembersIds)) {
                            if (!empty($searchQueryLimit)) {
                                $arrFoundMembersIds = array_splice($arrFoundMembersIds, 0, $searchQueryLimit);
                            }

                            $arrParentIds   = $this->getParentsForAssignedApplicant($arrFoundMembersIds);
                            $arrClientCases = $this->getAssignedApplicants(array_merge($arrFoundMembersIds, $arrParentIds), $this->getMemberTypeIdByName('case'));
                            $arrResult      = array_merge($arrCases, $arrClientCases);
                            $arrCases       = Settings::arrayUnique($arrResult);
                        }
                    }
                }
            } else {
                switch ($searchId) {
                    case 'last4all':
                    case 'last4me':
                        $arrCases = $this->getLastViewedClients(50, $searchId);
                        break;

                    case 'all':
                        if (is_array($arrMemberIds) && count($arrMemberIds)) {
                            $select = (new Select())
                                ->from(array('m' => 'members'))
                                ->columns(array('member_id', 'userType'))
                                ->where(['m.member_id' => $arrMemberIds]);

                            $arrAllApplicants = $this->_db2->fetchAll($select);

                            $caseTypeId = $this->getMemberTypeIdByName('case');
                            foreach ($arrAllApplicants as $arrAllApplicantInfo) {
                                if ($arrAllApplicantInfo['userType'] == $caseTypeId) {
                                    $arrCases[] = $arrAllApplicantInfo['member_id'];
                                } else {
                                    $arrFoundMembersIds[] = $arrAllApplicantInfo['member_id'];
                                }
                            }
                        }
                        break;

                    default:
                        // This is a saved search - run it
                        $arrSearchInfo = $this->getSearch()->getSearchInfo($searchId);
                        if (is_array($arrSearchInfo) && count($arrSearchInfo)) {
                            $arrParams = Json::decode($arrSearchInfo['query'], Json::TYPE_ARRAY);
                            $arrParams['columns'] = array();
                            $arrParams['arrSortInfo'] = array(
                                'start' => 0,
                                'limit' => 1000,
                                'sort' => '',
                                'dir' => '',
                            );

                            // Replace unique string field ids with their integer values from DB
                            $companyId          = $this->_auth->getCurrentUserCompanyId();
                            $arrCaseFields      = $this->getFields()->getCompanyFields($companyId);
                            $arrApplicantFields = array();
                            $booReturnedCaseIds = false;
                            for ($i = 1; $i <= $arrParams['max_rows_count']; $i++) {
                                if (!isset($arrParams['field_client_type_' . $i]) || !isset($arrParams['field_' . $i])) {
                                    continue;
                                }

                                switch ($arrParams['field_client_type_' . $i]) {
                                    case 'case':
                                        foreach ($arrCaseFields as $arrCaseFieldInfo) {
                                            if ($arrCaseFieldInfo['company_field_id'] == $arrParams['field_' . $i]) {
                                                $arrParams['field_' . $i] = $arrCaseFieldInfo['field_id'];
                                                break;
                                            }
                                        }
                                        $booReturnedCaseIds = true;
                                        break;

                                    default:
                                        if (!isset($arrApplicantFields[$arrParams['field_client_type_' . $i]])) {
                                            $arrApplicantFields[$arrParams['field_client_type_' . $i]] = $this->getApplicantFields()->getCompanyFields($companyId, $this->getMemberTypeIdByName($arrParams['field_client_type_' . $i]));
                                        }

                                        if (is_array($arrApplicantFields[$arrParams['field_client_type_' . $i]])) {
                                            foreach ($arrApplicantFields[$arrParams['field_client_type_' . $i]] as $arrApplicantFieldInfo) {
                                                if ($arrApplicantFieldInfo['applicant_field_unique_id'] == $arrParams['field_' . $i]) {
                                                    $arrParams['field_' . $i] = $arrApplicantFieldInfo['applicant_field_id'];
                                                    break;
                                                }
                                            }
                                        }
                                        $booReturnedCaseIds = false;
                                        break;
                                }
                            }

                            list($strError, , , $arrAllMemberIds) = $this->getSearch()->runAdvancedSearch($arrParams, $searchFor);

                            if (empty($strError)) {
                                $arrAllMemberIds = array_splice($arrAllMemberIds, 0, 1000);
                                foreach ($arrAllMemberIds as $clientId) {
                                    if ($booReturnedCaseIds) {
                                        $arrCases[] = $clientId;
                                    } else {
                                        $arrFoundMembersIds[] = $clientId;
                                    }
                                }

                                $booFilterByFoundCasesOnly = count($arrCases) > 0;
                            }
                        }
                        break;
                }
            }

            if ($booReturnIdsOnly) {
                return array_merge($arrFoundMembersIds, $arrCases);
            }

            // Load employers/cases details
            if (!empty($arrMemberIds) && (!empty($arrFoundMembersIds) || !empty($arrCases))) {
                $arrGroupedResult = array();
                $arrUserIds       = array();

                $arrAllowedTypes = $searchFor == 'applicants' ? array($this->getMemberTypeIdByName('individual'), $this->getMemberTypeIdByName('employer')) : array($this->getMemberTypeIdByName('contact'));
                if (!empty($arrCases)) {
                    $select = (new Select())
                        ->from(array('r' => 'members_relations'))
                        ->join(array('m' => 'members'), 'm.member_id = r.child_member_id', Select::SQL_STAR, Select::JOIN_LEFT)
                        ->join(array('c' => 'clients'), 'm.member_id = c.member_id', array('fileNumber', 'client_type_id'), Select::JOIN_LEFT)
                        ->join(array('m2' => 'members'), 'm2.member_id = r.parent_member_id', array('applicant_first_name' => 'fName', 'applicant_last_name' => 'lName'), Select::JOIN_LEFT)
                        ->join(array('mt' => 'members_types'), 'm2.userType = mt.member_type_id', array('applicant_type' => 'member_type_name'), Select::JOIN_LEFT)
                        ->where(
                            [
                                'r.parent_member_id' => $arrMemberIds,
                                'r.child_member_id'  => $arrCases,
                                'm2.userType'        => $arrAllowedTypes
                            ]
                        )
                        ->order(array('m2.lName', 'm2.fName', 'm.lName', 'm.fName'));

                    $arrCasesWithApplicants = $this->_db2->fetchAll($select);

                    $arrEmployers = array();
                    $arrIndividuals = array();
                    foreach ($arrCasesWithApplicants as $arrCaseApplicant) {
                        $arrApplicantInfo = array(
                            'fName' => $arrCaseApplicant['applicant_first_name'],
                            'lName' => $arrCaseApplicant['applicant_last_name'],
                        );
                        $arrApplicantInfo = $this->generateClientName($arrApplicantInfo);

                        $arrCaseApplicant = $this->generateClientName($arrCaseApplicant);
                        switch ($arrCaseApplicant['applicant_type']) {
                            case 'contact':
                            case 'employer':
                                $arrEmployers[] = array(
                                    'user_id' => $arrCaseApplicant['parent_member_id'],
                                    'user_name' => $arrApplicantInfo['full_name'],
                                    'user_type' => $arrCaseApplicant['applicant_type'],
                                    'case_type_id' => $arrCaseApplicant['client_type_id'],
                                    'applicant_id' => $arrCaseApplicant['child_member_id'],
                                    'applicant_name' => $arrCaseApplicant['full_name_with_file_num'],
                                    'applicant_type' => 'case'
                                );
                                break;

                            case 'individual':
                                $arrIndividuals[] = array(
                                    'user_id' => $arrCaseApplicant['parent_member_id'],
                                    'user_name' => $arrApplicantInfo['full_name'],
                                    'user_type' => $arrCaseApplicant['applicant_type'],
                                    'case_type_id' => $arrCaseApplicant['client_type_id'],
                                    'applicant_id' => $arrCaseApplicant['child_member_id'],
                                    'applicant_name' => $arrCaseApplicant['full_name_with_file_num'],
                                    'applicant_type' => 'case'
                                );
                                break;

                            default:
                                break;
                        }
                    }

                    foreach ($arrEmployers as $arrEmployerAssignedCaseInfo) {
                        $booAssignedToIndividual = false;
                        $arrAssignedIndividualAssignedCaseInfo = array();
                        foreach ($arrIndividuals as $arrIndividualAssignedCaseInfo) {
                            if ($arrIndividualAssignedCaseInfo['applicant_id'] == $arrEmployerAssignedCaseInfo['applicant_id']) {
                                $booAssignedToIndividual = true;
                                $arrAssignedIndividualAssignedCaseInfo = $arrIndividualAssignedCaseInfo;
                                break;
                            }
                        }

                        if ($booAssignedToIndividual) {
                            unset($arrEmployerAssignedCaseInfo['applicant_id'], $arrEmployerAssignedCaseInfo['applicant_name'], $arrEmployerAssignedCaseInfo['applicant_type']);
                            $arrGroupedResult[$arrEmployerAssignedCaseInfo['user_id']] = $arrEmployerAssignedCaseInfo;

                            $arrUserIds[$arrEmployerAssignedCaseInfo['user_id']] = 1;

                            $arrAssignedIndividualAssignedCaseInfo['user_parent_id'] = $arrEmployerAssignedCaseInfo['user_id'];
                            $arrAssignedIndividualAssignedCaseInfo['user_parent_name'] = $arrEmployerAssignedCaseInfo['user_name'];
                            $arrGroupedResult[$arrAssignedIndividualAssignedCaseInfo['applicant_id']] = $arrAssignedIndividualAssignedCaseInfo;

                            $arrUserIds[$arrAssignedIndividualAssignedCaseInfo['user_id']] = 1;
                        } else {
                            $arrCaseInfo = array(
                                'user_id' => $arrEmployerAssignedCaseInfo['applicant_id'],
                                'user_name' => $arrEmployerAssignedCaseInfo['applicant_name'],
                                'user_type' => $arrEmployerAssignedCaseInfo['applicant_type'],
                                'case_type_id' => $arrEmployerAssignedCaseInfo['case_type_id'],
                                'user_parent_id' => $arrEmployerAssignedCaseInfo['user_id'],
                                'applicant_id' => $arrEmployerAssignedCaseInfo['user_id'],
                                'applicant_name' => $arrEmployerAssignedCaseInfo['user_name'],
                                'applicant_type' => 'employer'
                            );
                            unset($arrEmployerAssignedCaseInfo['applicant_id'], $arrEmployerAssignedCaseInfo['applicant_name'], $arrEmployerAssignedCaseInfo['applicant_type']);
                            $arrGroupedResult[$arrEmployerAssignedCaseInfo['user_id']] = $arrEmployerAssignedCaseInfo;

                            $arrUserIds[$arrEmployerAssignedCaseInfo['user_id']] = 1;

                            $arrGroupedResult[$arrCaseInfo['user_id']] = $arrCaseInfo;
                        }
                    }

                    foreach ($arrIndividuals as $arrIndividualAssignedCaseInfo) {
                        if (!isset($arrGroupedResult[$arrIndividualAssignedCaseInfo['applicant_id']])) {
                            $arrGroupedResult[$arrIndividualAssignedCaseInfo['applicant_id']] = $arrIndividualAssignedCaseInfo;
                        }
                    }
                }

                if (!empty($arrFoundMembersIds)) {
                    // Load parents for internal contacts
                    $select = (new Select())
                        ->from(array('m' => 'members'))
                        ->columns(['member_id'])
                        ->where(
                            [
                                'm.userType' => $this->getMemberTypeIdByName('internal_contact'),
                                'm.member_id' => $arrFoundMembersIds
                            ]
                        );

                    $arrInternalContactIds = $this->_db2->fetchCol($select);

                    if (count($arrInternalContactIds)) {
                        $select = (new Select())
                            ->from(array('r' => 'members_relations'))
                            ->columns(['parent_member_id'])
                            ->where(['r.child_member_id' => $arrInternalContactIds]);

                        $arrParentApplicantIds = $this->_db2->fetchCol($select);
                        if (count($arrParentApplicantIds)) {
                            $arrFoundMembersIds = array_merge($arrFoundMembersIds, $arrParentApplicantIds);
                            $arrFoundMembersIds = Settings::arrayUnique($arrFoundMembersIds);
                        }
                    }

                    $select = (new Select())
                        ->from(array('m' => 'members'))
                        ->columns(array('user_id' => 'member_id', 'fName', 'lName'))
                        ->join(array('t' => 'members_types'), 't.member_type_id = m.userType', array('user_type' => 'member_type_name'), Select::JOIN_LEFT)
                        ->where(
                            [
                                'm.userType'  => $arrAllowedTypes,
                                'm.member_id' => $arrFoundMembersIds,
                                'm.status'    => '1'
                            ]
                        )
                        ->order(array('m.lName', 'm.fName'));

                    $arrApplicants = $this->_db2->fetchAll($select);

                    foreach ($arrApplicants as $arrApplicantInfo) {
                        if (!isset($arrGroupedResult[$arrApplicantInfo['user_id']]) && !isset($arrUserIds[$arrApplicantInfo['user_id']])) {
                            $arrApplicantInfo = $this->generateClientName($arrApplicantInfo);

                            $arrApplicantInfo['user_name'] = $arrApplicantInfo['full_name'];
                            unset($arrApplicantInfo['fName'], $arrApplicantInfo['lName']);
                            $arrGroupedResult[$arrApplicantInfo['user_id']] = $arrApplicantInfo;
                        }
                    }
                }

                if ($booFilterByFoundCasesOnly) {
                    $arrKeysToLeave = array();
                    foreach ($arrGroupedResult as $arrFoundMemberInfo) {
                        if (isset($arrFoundMemberInfo['applicant_type'])) {
                            switch ($arrFoundMemberInfo['applicant_type']) {
                                case 'case':
                                    if (in_array($arrFoundMemberInfo['applicant_id'], $arrCases)) {
                                        $arrKeysToLeave[] = $arrFoundMemberInfo['applicant_id'];
                                        if (isset($arrFoundMemberInfo['user_parent_id'])) {
                                            $arrKeysToLeave[] = $arrFoundMemberInfo['user_parent_id'];
                                        }
                                    }
                                    break;

                                case 'employer':
                                    if (in_array($arrFoundMemberInfo['user_id'], $arrCases)) {
                                        $arrKeysToLeave[] = $arrFoundMemberInfo['user_id'];
                                        if (isset($arrFoundMemberInfo['user_parent_id'])) {
                                            $arrKeysToLeave[] = $arrFoundMemberInfo['user_parent_id'];
                                        }
                                    }
                                    break;

                                default:
                                    break;
                            }
                        }
                    }

                    foreach ($arrGroupedResult as $key => $arrFoundMemberInfo) {
                        if (!in_array($key, $arrKeysToLeave)) {
                            unset($arrGroupedResult[$key]);
                        }
                    }
                }

                // If Applicant is showed with case - don't show him/her separately
                // Andron's comment: we need these 3 separate loops - this works quickly with huge records count
                $arrKeysToUnset = array();
                $arrOnlyClientsIds = array();
                $arrUserIds = array();
                foreach ($arrGroupedResult as $arrFoundMemberInfo) {
                    $arrUserIds[] = $arrFoundMemberInfo['user_id'];
                }

                $arrCountUserIdsValues = array_count_values($arrUserIds);
                foreach ($arrGroupedResult as $key => $arrFoundMemberInfo) {
                    if (!isset($arrFoundMemberInfo['user_parent_id']) && isset($arrCountUserIdsValues[$arrFoundMemberInfo['user_id']]) && $arrCountUserIdsValues[$arrFoundMemberInfo['user_id']] > 1) {
                        $arrOnlyClientsIds[$arrFoundMemberInfo['user_id']] = $key;
                    }
                }

                foreach ($arrGroupedResult as $arrFoundMemberInfo) {
                    if (isset($arrOnlyClientsIds[$arrFoundMemberInfo['user_id']]) && (isset($arrFoundMemberInfo['user_parent_id']) || isset($arrFoundMemberInfo['applicant_id']))) {
                        $arrKeysToUnset[] = $arrOnlyClientsIds[$arrFoundMemberInfo['user_id']];
                    }
                }

                foreach ($arrKeysToUnset as $key) {
                    if (array_key_exists($key, $arrGroupedResult)) {
                        unset($arrGroupedResult[$key]);
                    }
                }

                $arrResult               = array_values($arrGroupedResult);
                $arrMembersToInsert      = array();

                $arrUserIds = Settings::arrayColumn($arrResult, 'user_id');

                foreach ($arrResult as $key => $item) {
                    if ($item['user_type'] == 'individual' && isset($item['user_parent_id'])) {
                        $employerLinkCaseId = $this->getCaseLinkedEmployerCaseId($item['applicant_id']);

                        if (!empty($employerLinkCaseId)) {
                            $arrResult[$key]['linked_to_case'] = true;

                            // Don't delete if related case isn't displayed
                            if (in_array($employerLinkCaseId, $arrUserIds)) {
                                $arrMembersToInsert[$employerLinkCaseId][] = $arrResult[$key];
                                unset($arrResult[$key]);
                            }
                        }
                    }
                }

                $arrRes = array_values($arrResult);
                $arrResult = array();
                foreach ($arrRes as $item) {
                    $arrResult[] = $item;
                    if (array_key_exists($item['user_id'], $arrMembersToInsert)) {
                        foreach ($arrMembersToInsert[$item['user_id']] as $applicant) {
                            $arrResult[] = $applicant;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $arrResult = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrResult;
    }

    /**
     * Load list of assigned cases to specific applicant
     *
     * @param int $companyId - company id (cases we need search in)
     * @param int $applicantId - parent client id
     * @param bool $booOnlyActiveCases - true to load active cases only, if false - load all cases
     * @param int|null $caseIdLinkedTo
     * @param int|null $start
     * @param int|null $limit
     * @return array
     */
    public function getApplicantAssignedCases($companyId, $applicantId, $booOnlyActiveCases, $caseIdLinkedTo, $start, $limit)
    {
        if (!is_numeric($start) || $start <= 0 || $start > 100000) {
            $start = 0;
        }

        if (!is_numeric($limit) || $limit <= 0 || $limit > 100000) {
            $limit = 25;
        }

        $select = (new Select())
            ->from(array('r' => 'members_relations'))
            ->join(array('m' => 'members'), 'r.child_member_id = m.member_id', Select::SQL_STAR, Select::JOIN_LEFT)
            ->join(array('c' => 'clients'), 'c.member_id = r.child_member_id', array('client_type_id', 'fileNumber'), Select::JOIN_LEFT)
            ->join(array('t' => 'client_types'), 't.client_type_id = c.client_type_id', 'client_type_name', Select::JOIN_LEFT)
            ->where(
                [
                    'r.parent_member_id' => (int)$applicantId,
                    'm.userType'         => (int)$this->getMemberTypeIdByName('case')
                ]
            )
            ->order(array('m.lName', 'm.fName'))
            ->limit($limit)
            ->offset($start);

        // Additional check if the current logged-in user is client
        if ($this->_auth->isCurrentUserClient()) {
            $select->where(['r.child_member_id' => $this->getMembersWhichICanAccess()]);
        }

        if ($booOnlyActiveCases) {
            // Load active cases only
            $select2 = (new Select())
                ->from(array('r' => 'members_relations'))
                ->columns(['child_member_id'])
                ->join(array('m' => 'members'), 'r.child_member_id = m.member_id', Select::SQL_STAR, Select::JOIN_LEFT)
                ->where(
                    [
                        'r.parent_member_id' => (int)$applicantId,
                        'm.userType'         => (int)$this->getMemberTypeIdByName('case')
                    ]
                );

            $arrAssignedCasesIds = $this->_db2->fetchCol($select2);

            $arrActiveCasesIds = [];
            if (!empty($arrAssignedCasesIds)) {
                $arrActiveCasesIds = $this->getActiveClientsList($arrAssignedCasesIds, true, $companyId);
            }

            $arrActiveCasesIds = is_array($arrActiveCasesIds) && count($arrActiveCasesIds) ? $arrActiveCasesIds : array(0);
            $select->where(['r.child_member_id' => $arrActiveCasesIds]);
        }

        if (!empty($caseIdLinkedTo)) {
            // Load cases which are linked to specific case only
            $select->where(['c.employer_sponsorship_case_id' => (int)$caseIdLinkedTo]);
        }

        return array(
            $this->_db2->fetchAll($select),
            $this->_db2->fetchResultsCount($select)
        );
    }

    public function getParentsForAssignedApplicants($arrChildMemberIds, $booEmployersOnly = false, $booGroup = true)
    {
        $arrResult = array();

        if (is_array($arrChildMemberIds) && count($arrChildMemberIds)) {
            $arrParentsTypes = $booEmployersOnly ?
                array($this->getMemberTypeIdByName('employer')) :
                array($this->getMemberTypeIdByName('employer'), $this->getMemberTypeIdByName('individual'), $this->getMemberTypeIdByName('contact'));

            $select = (new Select())
                ->from(array('r' => 'members_relations'))
                ->columns(array('child_member_id', 'parent_member_id'))
                ->join(array('m' => 'members'), 'r.parent_member_id = m.member_id', array('fName', 'lName'), Select::JOIN_LEFT)
                ->join(array('t' => 'members_types'), 't.member_type_id = m.userType', 'member_type_name', Select::JOIN_LEFT)
                ->where(
                    [
                        'r.child_member_id' => $arrChildMemberIds,
                        'm.userType' => $arrParentsTypes
                    ]
                );

            $arrSavedRelations = $this->_db2->fetchAll($select);

            if ($booGroup) {
                foreach ($arrSavedRelations as $arrSavedRelationInfo) {
                    if (!array_key_exists($arrSavedRelationInfo['child_member_id'], $arrResult) || $arrSavedRelationInfo['member_type_name'] == 'individual') {
                        $arrResult[$arrSavedRelationInfo['child_member_id']] = $arrSavedRelationInfo;
                    }
                }
            } else {
                $arrResult = $arrSavedRelations;
            }
        }

        return $arrResult;
    }

    public function getParentsForAssignedCases($arrCaseIds)
    {
        $arrCasesParents = array();
        $arrParentIds = array();

        if (is_array($arrCaseIds) && count($arrCaseIds)) {
            $arrParentsTypes = array($this->getMemberTypeIdByName('employer'), $this->getMemberTypeIdByName('individual'));

            $select = (new Select())
                ->from(array('r' => 'members_relations'))
                ->columns(array('child_member_id', 'parent_member_id'))
                ->join(array('m' => 'members'), 'r.parent_member_id = m.member_id', [], Select::JOIN_LEFT)
                ->where(
                    [
                        'r.child_member_id' => $arrCaseIds,
                        'm.userType'        => $arrParentsTypes
                    ]
                );

            $arrSavedRelations = $this->_db2->fetchAll($select);
            foreach ($arrSavedRelations as $arrSavedRelationInfo) {
                $arrCasesParents[$arrSavedRelationInfo['child_member_id']][] = $arrSavedRelationInfo['parent_member_id'];
                $arrParentIds[] = $arrSavedRelationInfo['parent_member_id'];
            }
        }

        return array($arrCasesParents, $arrParentIds);
    }

    /**
     * Load cases readable list with their parents
     *
     * @param array $arrAssignedCases - array with cases info
     * @param int $parentMemberId - load cases only for specific parent (if passed)
     * @param array $arrExceptMemberIds - skip this client(s0/case(s) (if passed)
     * @param string $query - filter by name
     * @param bool $booSort - sort the result array by client name
     * @return array
     */
    public function getCasesListWithParents($arrAssignedCases, $parentMemberId = 0, $arrExceptMemberIds = [], $query = '', $booSort = true)
    {
        // Load cases list
        $arrAssignedCasesGrouped = array();
        foreach ($arrAssignedCases as $arrCaseInfo) {
            $arrAssignedCasesGrouped[$arrCaseInfo['member_id']] = $arrCaseInfo;
        }

        // Load list of parents for these cases
        $arrGroupedTaskClients = array();
        if (count($arrAssignedCasesGrouped)) {
            $arrCasesParents = $this->getParentsForAssignedApplicants(array_keys($arrAssignedCasesGrouped));
            foreach ($arrCasesParents as $arrCaseParentInfo) {
                // Skip case if parent isn't what we are looking for
                if (!empty($parentMemberId) && $parentMemberId != $arrCaseParentInfo['parent_member_id']) {
                    continue;
                }

                // Skip case/client if it was passed
                if (!empty($arrExceptMemberIds) && (in_array($arrCaseParentInfo['parent_member_id'], $arrExceptMemberIds) || in_array($arrCaseParentInfo['child_member_id'], $arrExceptMemberIds))) {
                    continue;
                }

                $fullName = $arrAssignedCasesGrouped[$arrCaseParentInfo['child_member_id']]['full_name'] ?? '';
                $fullName = strlen($fullName) ? '(' . $fullName . ')' : $fullName;

                $fullNameWithFileNum = $arrAssignedCasesGrouped[$arrCaseParentInfo['child_member_id']]['full_name_with_file_num'] ?? '';
                $fullNameWithFileNum = empty($fullNameWithFileNum) ? 'Case 1' : $fullNameWithFileNum;

                $parentName            = $this->generateApplicantName($arrCaseParentInfo);
                $fullClientAndCaseName = trim($parentName . ' ' . (strlen($fullNameWithFileNum) ? '(' . $fullNameWithFileNum . ')' : $fullNameWithFileNum));
                if (strlen($query) && mb_stripos($fullClientAndCaseName, $query) === false) {
                    continue;
                }

                $arrGroupedTaskClients[$arrCaseParentInfo['child_member_id']] = array(
                    'clientId'        => $arrCaseParentInfo['child_member_id'],
                    'caseName'        => $fullNameWithFileNum,
                    'clientName'      => trim($parentName . ' ' . $fullName),
                    'clientFullName'  => $fullClientAndCaseName,
                    'emailAddresses'  => $arrAssignedCasesGrouped[$arrCaseParentInfo['child_member_id']]['emailAddress']
                );
            }
        }

        if (count($arrGroupedTaskClients) && $booSort) {
            // Sort array by name
            $arrNames = array();
            foreach ($arrGroupedTaskClients as $key => $row) {
                $arrNames[$key] = strtolower($row['clientName'] ?? '');
            }
            array_multisort($arrNames, SORT_ASC, $arrGroupedTaskClients);
        }

        return $arrGroupedTaskClients;
    }

    /**
     * Load applicant fields data
     *
     * @param int $applicantId
     * @param int $memberTypeId
     * @param int $groupId
     * @param bool $booReadableFieldIds
     * @param int $applicantParentId
     * @return array
     */
    public function getApplicantFieldsData($applicantId, $memberTypeId, $groupId = 0, $booReadableFieldIds = false, $applicantParentId = 0)
    {
        $arrFields = array();
        $arrRowIds = array();
        try {
            $memberTypeName = $this->getMemberTypeNameById($memberTypeId);

            $select = (new Select())
                ->from(array('d' => 'applicant_form_data'))
                ->join(array('f' => 'applicant_form_fields'), 'd.applicant_field_id = f.applicant_field_id', array('encrypted', 'applicant_field_unique_id', 'applicant_field_id', 'type', 'multiple_values', 'can_edit_in_gui'), Select::JOIN_LEFT)
                ->join(array('m' => 'members'), 'd.applicant_id = m.member_id', 'company_id', Select::JOIN_LEFT)
                ->join(array('o' => 'applicant_form_order'), 'd.applicant_field_id = o.applicant_field_id', 'applicant_group_id', Select::JOIN_LEFT)
                ->join(array('g' => 'applicant_form_groups'), 'g.applicant_group_id = o.applicant_group_id', [], Select::JOIN_LEFT)
                ->join(array('b' => 'applicant_form_blocks'), 'b.applicant_block_id = g.applicant_block_id', [], Select::JOIN_LEFT)
                ->where(
                    [
                        'd.applicant_id' => (int)$applicantId,
                        'b.member_type_id' => (int)$memberTypeId
                    ]
                );

            if (!empty($groupId)) {
                $select->where->equalTo('o.applicant_group_id', (int)$groupId);
            } else {
                $select->where->equalTo('b.contact_block', 'N');
            }
            $arrAllFields = $this->_db2->fetchAll($select);

            if (count($arrAllFields) && isset($arrAllFields[0]['company_id'])) {
                $select = (new Select())
                    ->from(array('f' => 'applicant_form_fields'))
                    ->columns(array('encrypted', 'applicant_field_unique_id', 'applicant_field_id', 'type', 'multiple_values', 'can_edit_in_gui'))
                    ->join(array('o' => 'applicant_form_order'), 'f.applicant_field_id = o.applicant_field_id', 'applicant_group_id', Select::JOIN_LEFT)
                    ->join(array('g' => 'applicant_form_groups'), 'g.applicant_group_id = o.applicant_group_id', [], Select::JOIN_LEFT)
                    ->join(array('b' => 'applicant_form_blocks'), 'b.applicant_block_id = g.applicant_block_id', [], Select::JOIN_LEFT)
                    ->where(
                        [
                            'f.company_id'     => (int)$arrAllFields[0]['company_id'],
                            'f.type'           => ['applicant_internal_id'],
                            'b.member_type_id' => (int)$memberTypeId
                        ]
                    );

                if (!empty($groupId)) {
                    $select->where->equalTo('o.applicant_group_id', (int)$groupId);
                } else {
                    $select->where->equalTo('b.contact_block', 'N');
                }

                $arrApplicantInternalIdFields = $this->_db2->fetchAll($select);

                foreach ($arrApplicantInternalIdFields as $arrApplicantInternalIdFieldInfo) {
                    $arrApplicantInternalIdFieldInfo['row'] = 0;
                    $arrApplicantInternalIdFieldInfo['value'] = $applicantParentId;
                    $arrAllFields[] = $arrApplicantInternalIdFieldInfo;
                }
            }

            // In some cases office can be saved in members_divisions table
            // but not in the applicant_form_data
            // So we need to load this saved info
            if (!empty($groupId) && count($arrAllFields)) {
                $arrApplicantDivisions = $this->getMemberDivisions($applicantId);
                if (count($arrApplicantDivisions)) {
                    $select = (new Select())
                        ->from(array('f' => 'applicant_form_fields'))
                        ->columns(array('encrypted', 'applicant_field_unique_id', 'applicant_field_id', 'type'))
                        ->join(array('o' => 'applicant_form_order'), 'f.applicant_field_id = o.applicant_field_id', 'applicant_group_id', Select::JOIN_LEFT)
                        ->join(array('g' => 'applicant_form_groups'), 'g.applicant_group_id = o.applicant_group_id', [], Select::JOIN_LEFT)
                        ->join(array('b' => 'applicant_form_blocks'), 'b.applicant_block_id = g.applicant_block_id', [], Select::JOIN_LEFT)
                        ->where(
                            [
                                'f.company_id'     => (int)$arrAllFields[0]['company_id'],
                                'f.type'           => ['office', 'office_multi'],
                                'b.member_type_id' => (int)$memberTypeId
                            ]
                        );

                    $arrOfficeFields = $this->_db2->fetchAll($select);

                    foreach ($arrOfficeFields as $arrOfficeFieldInfo) {
                        $booOfficeFound = false;
                        foreach ($arrAllFields as $arrFieldInfo) {
                            if ($arrFieldInfo['applicant_field_id'] == $arrOfficeFieldInfo['applicant_field_id']) {
                                $booOfficeFound = true;
                                break;
                            }
                        }

                        if (!$booOfficeFound) {
                            $arrOfficeFieldInfo['row'] = 0;
                            $arrOfficeFieldInfo['value'] = implode(',', $arrApplicantDivisions);
                            $arrAllFields[] = $arrOfficeFieldInfo;
                        }
                    }
                }
            }

            foreach ($arrAllFields as $arrFieldInfo) {
                // Fill empty rows
                if ($booReadableFieldIds) {
                    $fieldId = $arrFieldInfo['applicant_field_unique_id'];
                } else {
                    $fieldId = 'field_' . $memberTypeName . '_' . $arrFieldInfo['applicant_group_id'] . '_' . $arrFieldInfo['applicant_field_id'];
                }
                for ($i = 0; $i < $arrFieldInfo['row']; $i++) {
                    if (!array_key_exists($fieldId, $arrFields) || !is_array($arrFields[$fieldId]) || !array_key_exists($i, $arrFields[$fieldId])) {
                        $arrFields[$fieldId][$i] = '';
                    }
                }

                $value = $arrFieldInfo['value'];

                if ($arrFieldInfo['type'] == 'reference' && $arrFieldInfo['value'] != '') {
                    $booMultipleValues = isset($arrFieldInfo['multiple_values']) && $arrFieldInfo['multiple_values'] == 'Y';
                    $value = $this->getFields()->prepareReferenceField($arrFieldInfo['value'], $booMultipleValues);
                }

                if ($arrFieldInfo['type'] == 'multiple_text_fields' && $arrFieldInfo['value'] != '') {
                    if (!(is_array(json_decode($arrFieldInfo['value'], true)) && json_last_error() == JSON_ERROR_NONE)) {
                        $arrValues = array($arrFieldInfo['value']);
                        $value = Json::encode($arrValues);
                    }
                }

                $arrFields[$fieldId][$arrFieldInfo['row']] = $arrFieldInfo['encrypted'] == 'Y' ? $this->_encryption->decode($value) : $value;
            }

            // Load saved row ids - will be used to identify each row
            $select = (new Select())
                ->from(array('o' => 'applicant_form_order'))
                ->columns(['applicant_group_id'])
                ->join(array('d' => 'applicant_form_data'), 'd.applicant_field_id = o.applicant_field_id', 'row_id', Select::JOIN_LEFT)
                ->where(['d.applicant_id' => (int)$applicantId])
                ->order('o.field_order ASC');

            if (!empty($groupId)) {
                $select->where->equalTo('o.applicant_group_id', (int)$groupId);
            }

            $arrSavedRowIds = $this->_db2->fetchAll($select);

            foreach ($arrSavedRowIds as $arrSavedRowIdInfo) {
                $key = 'group_' . $arrSavedRowIdInfo['applicant_group_id'];
                if (!array_key_exists($key, $arrRowIds) || !in_array($arrSavedRowIdInfo['row_id'], $arrRowIds[$key])) {
                    $arrRowIds[$key][] = $arrSavedRowIdInfo['row_id'];
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($arrFields, $arrRowIds);
    }

    /**
     * Load all saved data for Applicant
     *
     * @param int $applicantId
     * @param int $memberTypeId
     * @param bool $booReadableFieldIds
     * @return array
     */
    public function getAllApplicantFieldsData($applicantId, $memberTypeId, $booReadableFieldIds = false)
    {
        $applicantParentId = $applicantId;
        // Load main applicant fields and groups
        $arrResult = $this->getApplicantFieldsData($applicantId, $memberTypeId, 0, $booReadableFieldIds, $applicantParentId);
        if (count($arrResult)) {
            $applicantsCount = 0;
            $previousChildId = 0;

            $arrAssignedContacts = $this->getAssignedContacts($applicantId);
            foreach ($arrAssignedContacts as $arrAssignedContact) {
                list($arrFields,) = $this->getApplicantFieldsData($arrAssignedContact['child_member_id'], $memberTypeId, $arrAssignedContact['applicant_group_id'], $booReadableFieldIds, $applicantParentId);

                if ($arrAssignedContact['repeatable'] == 'N') {
                    foreach ($arrFields as $fieldId => $arrFieldData) {
                        $arrResult[0][$fieldId][0] = $arrFieldData[0];
                    }
                } else {
                    foreach ($arrFields as $fieldId => $arrFieldData) {
                        for ($i = 1; $i < $applicantsCount; $i++) {
                            if (!isset($arrResult[0][$fieldId]) || !is_array($arrResult[0][$fieldId]) || !array_key_exists($i, $arrResult[0][$fieldId])) {
                                $arrResult[0][$fieldId][$i] = '';
                            }
                        }
                        $arrResult[0][$fieldId][$applicantsCount] = $arrFieldData[0];
                    }
                }

                $arrResult[1]['group_' . $arrAssignedContact['applicant_group_id']][$applicantsCount] = $arrAssignedContact['child_member_id'];

                if ($arrAssignedContact['child_member_id'] != $previousChildId) {
                    $applicantsCount++;
                }
                $previousChildId = $arrAssignedContact['child_member_id'];
            }
        }

        // Also reset keys for fields/groups - because of js objects :(
        $arrResult[0] = array_map('array_values', $arrResult[0]);
        $arrResult[1] = array_map('array_values', $arrResult[1]);

        return $arrResult;
    }

    /**
     * Load Applicants list with specific member type / applicant type
     *
     * @param string $memberType
     * @param int $applicantType
     * @return array
     */
    public function getApplicants($memberType, $applicantType = 0)
    {
        $memberTypeId = $this->getMemberTypeIdByName($memberType);
        $arrMemberIds = $this->getMembersWhichICanAccess($memberTypeId);

        $arrApplicants = array();
        if (is_array($arrMemberIds) && count($arrMemberIds)) {
            $select = (new Select())
                ->from(array('m' => 'members'))
                ->columns(['user_id' => 'member_id', 'lName', 'fName'])
                ->join(array('t' => 'members_types'), 't.member_type_id = m.userType', array('user_type' => 'member_type_name'), Select::JOIN_LEFT)
                ->where(
                    [
                        'm.userType'  => [Members::getMemberType($memberType)],
                        'm.member_id' => $arrMemberIds,
                        'm.status'    => 1
                    ]
                )
                ->order(array('m.lName', 'm.fName'));

            if (!empty($applicantType)) {
                $select->join(array('c' => 'clients'), 'c.member_id = m.member_id', [], Select::JOIN_LEFT);
                $select->where->equalTo('c.applicant_type_id', (int)$applicantType);
            }

            $arrApplicants = $this->_db2->fetchAll($select);

            foreach ($arrApplicants as $key => $arrApplicantInfo) {
                $arrApplicants[$key]['user_name'] = $this->generateApplicantName($arrApplicantInfo);
                unset($arrApplicants[$key]['lName'], $arrApplicants[$key]['fName']);
            }
        }

        return $arrApplicants;
    }

    /**
     * Check if Applicant used in saved data
     * e.g. can be used to determine if Applicant's profile can be deleted
     *
     * @param int $applicantId
     * @return bool true if is used somewhere
     */
    public function isApplicantUsedInData($applicantId)
    {
        $booUsed = false;

        try {
            $arrFieldIdsToCheck = array(
                $this->getFieldTypes()->getFieldTypeIdByTextId('contact_sales_agent'),
                $this->getFieldTypes()->getFieldTypeIdByTextId('staff_responsible_rma'),
                $this->getFieldTypes()->getFieldTypeIdByTextId('active_users'),
                $this->getFieldTypes()->getFieldTypeIdByTextId('related_case_selection'),
            );

            $select = (new Select())
                ->from(array('d' => 'client_form_data'))
                ->columns(array('records_count' => new Expression('COUNT(*)')))
                ->join(array('f' => 'client_form_fields'), 'f.field_id = d.field_id', [], Select::JOIN_RIGHT)
                ->where(
                    [
                        'f.company_id' => $this->_auth->getCurrentUserCompanyId(),
                        'f.type'       => $arrFieldIdsToCheck,
                        'd.value'      => $applicantId
                    ]
                );

            $assignedCount = $this->_db2->fetchOne($select);
            $booUsed       = $assignedCount > 0;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $booUsed;
    }

    /**
     * Load agents (contacts) list for the current user
     *
     * @param bool $booIdsOnly
     * @param bool $booDefaultTypeOnly
     * @param int $companyId
     * @return array - list of all agents(contacts) for current user's company
     */
    public function getAgents($booIdsOnly = false, $booDefaultTypeOnly = false, $companyId = null)
    {
        $arrAgents = array();

        try {
            $applicantTypeId = 0;
            $memberTypeId    = $this->getMemberTypeIdByName('contact');
            $companyId       = is_null($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId;
            if ($booDefaultTypeOnly) {
                // Get 'Sales Agent' id only - and load contacts with such type
                $applicantTypeId = $this->getApplicantTypes()->getTypeIdByName($companyId, $memberTypeId, 'Sales Agent');
            }

            // Load contacts
            $arrContacts = $this->getApplicants('contact', $applicantTypeId);

            if (is_array($arrContacts) && count($arrContacts)) {
                $arrContactIds = array();
                foreach ($arrContacts as $arrContactInfo) {
                    $arrContactIds[] = $arrContactInfo['user_id'];
                }

                if ($booIdsOnly) {
                    // Use contact ids
                    $arrAgents = $arrContactIds;
                } else {
                    // Load additional info from main sub contact

                    // Search for main contacts
                    $arrGroupIds = array();
                    $arrGroups   = $this->getApplicantFields()->getCompanyGroups($companyId, $memberTypeId, $applicantTypeId);
                    foreach ($arrGroups as $arrGroupInfo) {
                        if ($arrGroupInfo['contact_block'] == 'Y' && $arrGroupInfo['repeatable'] == 'N') {
                            if (!in_array($arrGroupInfo['applicant_group_id'], $arrGroupIds)) {
                                $arrGroupIds[] = $arrGroupInfo['applicant_group_id'];
                            }
                        }
                    }

                    $arrSubContacts        = $this->getAssignedContact($arrContactIds, $arrGroupIds);
                    $arrSubContactIds      = [];
                    $arrSubContactsGrouped = [];
                    foreach ($arrSubContacts as $arrSubContactInfo) {
                        if (!in_array($arrSubContactInfo['child_member_id'], $arrSubContactIds)) {
                            $arrSubContactIds[] = $arrSubContactInfo['child_member_id'];
                        }

                        if (isset($arrSubContactsGrouped[$arrSubContactInfo['parent_member_id']])) {
                            $arrInternalContacts   = $arrSubContactsGrouped[$arrSubContactInfo['parent_member_id']];
                            $arrInternalContacts[] = $arrSubContactInfo['child_member_id'];
                            $arrInternalContacts   = Settings::arrayUnique($arrInternalContacts);
                        } else {
                            $arrInternalContacts = [$arrSubContactInfo['child_member_id']];
                        }

                        $arrSubContactsGrouped[$arrSubContactInfo['parent_member_id']] = $arrInternalContacts;
                    }

                    // Get sub contacts data
                    $arrSubContactsData = $this->getApplicantData($companyId, $arrSubContactIds, false, true, false);

                    foreach ($arrSubContactsGrouped as $contactId => $arrInternalContacts) {
                        $arrSubContactData = array(
                            'agent_id' => $contactId,
                            'title'    => '',
                            'fName'    => '',
                            'lName'    => '',
                            'email1'   => '',
                            'email2'   => '',
                            'email3'   => ''
                        );

                        foreach ($arrInternalContacts as $internalContactId) {
                            foreach ($arrSubContactsData as $arrSubContactsDataRow) {
                                if ($arrSubContactsDataRow['applicant_id'] == $internalContactId) {
                                    $arrSubContactData[$arrSubContactsDataRow['applicant_field_unique_id']] = $arrSubContactsDataRow['value'];
                                }
                            }

                            // Support for old data
                            if (array_key_exists('email', $arrSubContactData)) {
                                $arrSubContactData['email1'] = $arrSubContactData['email'];
                            }

                            if (array_key_exists('email_1', $arrSubContactData)) {
                                $arrSubContactData['email2'] = $arrSubContactData['email_1'];
                            }

                            if (array_key_exists('email_2', $arrSubContactData)) {
                                $arrSubContactData['email3'] = $arrSubContactData['email_2'];
                            }

                            if (array_key_exists('first_name', $arrSubContactData)) {
                                $arrSubContactData['fName'] = $arrSubContactData['first_name'];
                            }

                            if (array_key_exists('given_names', $arrSubContactData)) {
                                $arrSubContactData['fName'] = $arrSubContactData['given_names'];
                            }

                            if (array_key_exists('last_name', $arrSubContactData)) {
                                $arrSubContactData['lName'] = $arrSubContactData['last_name'];
                            }

                            if (array_key_exists('family_name', $arrSubContactData)) {
                                $arrSubContactData['lName'] = $arrSubContactData['family_name'];
                            }
                        }

                        $arrAgents[$contactId] = $arrSubContactData;
                    }


                    usort(
                        $arrAgents,
                        function ($a, $b) {
                            $c = strcmp(strtolower($a['fName'] ?? ''), strtolower($b['fName'] ?? ''));
                            if ($c != 0) {
                                return $c;
                            }
                            return strcmp(strtolower($a['lName'] ?? ''), strtolower($b['lName'] ?? ''));
                        }
                    );
                }
            }
        } catch (Exception $e) {
            $arrAgents = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrAgents;
    }

    /**
     * Generate agent name
     *
     * @param array $agent - info about the agent
     * @param bool $booWithTitle - used to know if title should be used
     *
     * @return string generated agent name
     */
    public function generateAgentName($agent, $booWithTitle = true)
    {
        if (!empty($agent['title']) && $booWithTitle) {
            return trim($agent['title'] . ' ' . $agent['fName'] . ' ' . $agent['lName']);
        } else {
            return trim($agent['fName'] . ' ' . $agent['lName']);
        }
    }

    /**
     * Load formatted agents list for current user
     * (as agent id => agent name)
     *
     * @param bool $booDefaultTypeOnly
     * @param int $companyId
     * @return array - list of all agents for current user's company
     */
    public function getAgentsListFormatted($booDefaultTypeOnly = false, $companyId = null)
    {
        $arrFormatted = array();
        $arrAgents = $this->getAgents(false, $booDefaultTypeOnly, $companyId);

        foreach ($arrAgents as $arrAgentInfo) {
            $arrFormatted[$arrAgentInfo['agent_id']] = $this->generateAgentName($arrAgentInfo, false);
        }

        return $arrFormatted;
    }


    /**
     * Load Clients tab related settings - will be used in js
     * @param int $currentMemberId
     * @param int $currentMemberCompanyId
     * @param int $divisionGroupId
     * @param bool $booOptionsOnly
     * @param bool $booLoadEmployerData
     * @param bool $booForAutomaticReminderConditions
     * @return array of settings
     */
    public function getSettings($currentMemberId, $currentMemberCompanyId, $divisionGroupId, $booOptionsOnly = false, $booLoadEmployerData = false, $booForAutomaticReminderConditions = false)
    {
        try {
            /** @var Users $oUsers */
            $oUsers = $this->_serviceContainer->get(Users::class);

            $arrAgents = $this->getAgents();
            $arrAgentOptions = array();
            if (is_array($arrAgents) && count($arrAgents) > 0) {
                foreach ($arrAgents as $agentInfo) {
                    $arrAgentOptions[] = array(
                        'option_id' => $agentInfo['agent_id'],
                        'option_name' => $this->generateAgentName($agentInfo, false)
                    );
                }
            }

            $arrOffices = $this->getDivisions();
            $arrOfficeOptions = array();
            if (is_array($arrOffices) && count($arrOffices) > 0) {
                foreach ($arrOffices as $officeInfo) {
                    $arrOfficeOptions[] = array(
                        'option_id' => $officeInfo['division_id'],
                        'option_name' => $officeInfo['name']
                    );
                }
            }

            $arrAssignedTo = $oUsers->getAssignList('search');

            $arrAssignedToOptions = array();
            if (is_array($arrAssignedTo) && count($arrAssignedTo)) {
                foreach ($arrAssignedTo as $arrAssignedToInfo) {
                    $arrAssignedToOptions[] = array(
                        'option_id' => $arrAssignedToInfo['assign_to_id'],
                        'option_name' => $arrAssignedToInfo['assign_to_name'],
                        'status' => $arrAssignedToInfo['status'],
                    );
                }
            }

            $arrStaffResponsibleRMA = $oUsers->getAssignedToUsers();
            $arrActiveUsers         = $oUsers->getAssignedToUsers(false);

            $arrCasesGroups = array();
            $arrAccountingFieldsGrouped = array(
                'group_id'            => 0,
                'group_title'         => '',
                'group_collapsed'     => 'N',
                'group_show_title'    => 'Y',
                'group_repeatable'    => 'N',
                'group_contact_block' => 'Y',
                'group_cols_count'    => 3,
                'group_access'        => 'R',
                'fields'              => $this->getFields()->getAccountingFields()
            );

            $arrAccountingFields = array(array('type_id' => 0, 'type_name' => '', 'fields' => $arrAccountingFieldsGrouped));

            $arrCaseTemplates = $this->getCaseTemplates()->getTemplates($currentMemberCompanyId);
            $arrVisibleCaseTemplates = $this->getCaseTemplates()->getTemplates($currentMemberCompanyId, false, null, true);

            $defaultSearch = strtolower($this->getSearch()->getMemberDefaultSearch($currentMemberId, 'clients', false) ?? '');
            $defaultSearchName = $this->getSearch()->getMemberDefaultSearchName($defaultSearch);

            $arrContactOptions = $this->getContactOptions($currentMemberCompanyId);

            $arrVisaOffices = $this->getFields()->getCompanyFieldOptions($currentMemberCompanyId, $divisionGroupId, 'visa_office');

            $arrCompanyCategories        = $this->getFields()->getCompanyFieldOptions($currentMemberCompanyId, $divisionGroupId, 'categories');
            $arrCompanyCategoriesGrouped = $this->getCaseCategories()->getCategoriesGroupedByCaseTypes($currentMemberCompanyId);

            foreach ($arrCaseTemplates as $key => $arrCaseTemplateInfo) {
                $arrCasesGroups[$arrCaseTemplateInfo['case_template_id']] = $this->getFields()->getGroupedCompanyFields($arrCaseTemplateInfo['case_template_id']);

                $booCanBeLinkedToEmployer = false;
                if (isset($arrCompanyCategoriesGrouped[$arrCaseTemplateInfo['case_template_id']])) {
                    foreach ($arrCompanyCategoriesGrouped[$arrCaseTemplateInfo['case_template_id']] as $arrCategoryInfo) {
                        if ($arrCategoryInfo['link_to_employer'] == 'Y') {
                            $booCanBeLinkedToEmployer = true;
                            break;
                        }
                    }
                }
                $arrCaseTemplates[$key]['case_template_can_be_linked_to_employer'] = $booCanBeLinkedToEmployer;
            }

            foreach ($arrVisibleCaseTemplates as $key => $arrVisibleCaseTemplateInfo) {
                $booCanBeLinkedToEmployer = false;
                if (isset($arrCompanyCategoriesGrouped[$arrVisibleCaseTemplateInfo['case_template_id']])) {
                    foreach ($arrCompanyCategoriesGrouped[$arrVisibleCaseTemplateInfo['case_template_id']] as $arrCategoryInfo) {
                        if ($arrCategoryInfo['link_to_employer'] == 'Y') {
                            $booCanBeLinkedToEmployer = true;
                            break;
                        }
                    }
                }
                $arrVisibleCaseTemplates[$key]['case_template_can_be_linked_to_employer'] = $booCanBeLinkedToEmployer;
            }

            $arrCompanyCaseStatuses = $this->getCaseStatuses()->getCompanyCaseStatusesGrouped($currentMemberCompanyId, $arrCaseTemplates);

            $arrAllGeneralCountries = $this->_country->getCountriesList();
            $arrGeneralCountries = array();
            foreach ($arrAllGeneralCountries as $arrCountryInfo) {
                $arrGeneralCountries[] = array(
                    'option_id' => $arrCountryInfo['countries_name'],
                    'option_name' => $arrCountryInfo['countries_name']
                );
            }


            $arrAdditionalOptions = array();
            $arrSectionsToLoad = array(
                'employer_contacts',
                // 'employer_engagements',
                // 'employer_legal_entities',
                // 'employer_locations',
                // 'employer_third_party_representatives'
            );
            $arrEmployerAndContactIds = array();
            $arrAllEmployerIds = $this->getMembersWhichICanAccess($this->getMemberTypeIdByName('employer'));
            if (is_array($arrAllEmployerIds) && count($arrAllEmployerIds)) {
                $arrSubContactIds = array();
                $arrSubContacts = $this->getAssignedContacts($arrAllEmployerIds);
                foreach ($arrSubContacts as $arrSubContactInfo) {
                    $arrSubContactIds[] = $arrSubContactInfo['child_member_id'];
                }
                $arrEmployerAndContactIds = array_unique(array_merge($arrSubContactIds, $arrAllEmployerIds));
            }

            foreach ($arrSectionsToLoad as $section) {
                $arrEmployerFields = !empty($arrEmployerAndContactIds) ? $this->getApplicantFields()->getEmployerRepeatableFieldsGrouped($section, $arrEmployerAndContactIds) : array();
                $arrAdditionalOptions[$section] = $arrEmployerFields;
            }

            $arrCaseAccessRights = array();
            if ($this->_acl->isAllowed('clients-profile-new')) {
                $arrCaseAccessRights[] = 'add';
            }

            if ($this->_acl->isAllowed('clients-profile-edit')) {
                $arrCaseAccessRights[] = 'edit';
            }

            if ($this->_acl->isAllowed('clients-profile-delete')) {
                $arrCaseAccessRights[] = 'delete';
            }

            $arrAuthorizedAgents = array();
            $arrCompanyDivisionsGroups = $this->_company->getCompanyDivisions()->getDivisionsGroups($currentMemberCompanyId, false);
            foreach ($arrCompanyDivisionsGroups as $arrCompanyDivisionsGroupInfo) {
                if (!empty($arrCompanyDivisionsGroupInfo['division_group_company'])) {
                    $arrAuthorizedAgents[] = array(
                        'option_id' => $arrCompanyDivisionsGroupInfo['division_group_id'],
                        'option_name' => $arrCompanyDivisionsGroupInfo['division_group_company']
                    );
                }
            }

            $arrApplicantsSettings = array(
                'booRememberDefaultFieldsSetting' => intval($this->_company->isRememberDefaultFieldsSettingEnabledForCompany($currentMemberCompanyId)),
                'client_warning'                  => $this->_config['site_version']['clients']['warning_message'],
                'case_status_field_multiselect'   => (bool)$this->_config['site_version']['case_status_field_multiselect'],
                'can_edit_case_fields'            => (bool)$this->_acl->isAllowed('manage-groups-view'),
                'can_edit_individuals_fields'     => (bool)$this->_acl->isAllowed('manage-individuals-fields'),
                'can_edit_employer_fields'        => (bool)$this->_acl->isAllowed('manage-employers-fields'),
                'can_edit_contact_fields'         => (bool)$this->_acl->isAllowed('manage-contacts-fields'),

                'options' => array(
                    'general' => array(
                        'country'               => $arrGeneralCountries,
                        'agents'                => $arrAgentOptions,
                        'categories'            => $arrCompanyCategories,
                        'categories_grouped'    => $arrCompanyCategoriesGrouped,
                        'case_statuses'         => $arrCompanyCaseStatuses,
                        'visa_office'           => $arrVisaOffices,
                        'office'                => $arrOfficeOptions,
                        'assigned_to'           => $arrAssignedToOptions,
                        'staff_responsible_rma' => $arrStaffResponsibleRMA,
                        'active_users'          => $arrActiveUsers,
                        'employee'              => array(),
                        'list_of_occupations'   => $this->getApplicantFields()->getListOfOccupations(),
                        'contact_sales_agent'   => $arrContactOptions,
                        'employer_contacts'     => $arrAdditionalOptions['employer_contacts'],
                        'authorized_agents'     => $arrAuthorizedAgents,
                    ),
                    'case'    => $this->getFields()->getGroupedFieldsOptions($arrCasesGroups),
                ),
            );

            if ($this->_acl->isAllowed('clients-accounting-view')) {
                $arrApplicantsSettings['accounting'] = $this->getAccounting()->getAccountingSettings($currentMemberCompanyId);
            }

            if (!$booOptionsOnly) {
                $arrVisaSubclassInfo = $this->getFields()->getCompanyFieldInfoByUniqueFieldId('visa_subclass', $currentMemberCompanyId);

                $governmentFundOptionId = 0;

                if ($this->_config['site_version']['validation']['check_investment_type']) {
                    $arrInvestmentTypeId      = $this->getFields()->getCompanyFieldIdByUniqueFieldId('cbiu_investment_type', $currentMemberCompanyId);
                    $arrInvestmentTypeOptions = $this->getFields()->getFieldsOptions(array($arrInvestmentTypeId));

                    foreach ($arrInvestmentTypeOptions as $arrInvestmentTypeOptionInfo) {
                        if ($arrInvestmentTypeOptionInfo['value'] == 'Government Fund') {
                            $governmentFundOptionId = $arrInvestmentTypeOptionInfo['form_default_id'];
                            break;
                        }
                    }
                }

                $clientsCount = $this->_company->getCompanyClientsCount($currentMemberCompanyId);
                $companyInfo = $this->_company->getCompanyDetailsInfo($currentMemberCompanyId);

                $arrPrices = $this->_company->getCompanyPrices($currentMemberCompanyId, false, false, true);

                $nextBillingDate = new DateTime($companyInfo['next_billing_date']);
                $currentDate = new DateTime(date('Y-m-d'));
                $daysSubtraction = $nextBillingDate->diff($currentDate)->days;

                $amountUpgradePlan = 0;
                if (isset($arrPrices['packageLiteFeeMonthly'], $arrPrices['packageStarterFeeMonthly'])) {
                    if ($companyInfo['payment_term'] == 1) {
                        $amountUpgradePlan = $daysSubtraction / 30 * ($arrPrices['packageLiteFeeMonthly'] - $arrPrices['packageStarterFeeMonthly']);
                    } else {
                        $amountUpgradePlan = $daysSubtraction / 365 * ($arrPrices['packageLiteFeeAnnual'] - $arrPrices['packageStarterFeeAnnual']);
                    }
                }

                $amountUpgradePlan = round($amountUpgradePlan, 2);
                $amountUpgradePlan = $this->getAccounting()::formatPrice($amountUpgradePlan);

                $booViewLeftPanel = true;
                if ($this->_auth->isCurrentUserClient()) {
                    $arrAssignedCases = $this->getAssignedCases($currentMemberId);
                    if (count($arrAssignedCases) < 2) {
                        $booViewLeftPanel = false;
                    }
                }

                $maxDependantsCount = 5 + // One spouse and 4 parents
                    $this->_config['site_version']['dependants']['fields']['relationship']['children_count'] +
                    $this->_config['site_version']['dependants']['fields']['relationship']['options']['siblings']['count'] +
                    $this->_config['site_version']['dependants']['fields']['relationship']['options']['other']['count'];

                /** @var MembersVevo $membersVevo */
                $membersVevo = $this->_serviceContainer->get(MembersVevo::class);
                /** @var MembersQueues $membersQueues */
                $membersQueues = $this->_serviceContainer->get(MembersQueues::class);

                $arrApplicantsSettings = array(
                    'case_templates'         => $arrCaseTemplates,
                    'visible_case_templates' => $arrVisibleCaseTemplates,
                    'case_group_templates'   => $arrCasesGroups,

                    'fields' => array(
                        'dependants' => $this->getFields()->getDependantFields(),
                    ),

                    'booRememberDefaultFieldsSetting' => $arrApplicantsSettings['booRememberDefaultFieldsSetting'],
                    'client_warning'                  => $arrApplicantsSettings['client_warning'],
                    'case_status_field_multiselect'   => $arrApplicantsSettings['case_status_field_multiselect'],
                    'can_edit_case_fields'            => $arrApplicantsSettings['can_edit_case_fields'],
                    'can_edit_individuals_fields'     => $arrApplicantsSettings['can_edit_individuals_fields'],
                    'can_edit_employer_fields'        => $arrApplicantsSettings['can_edit_employer_fields'],
                    'can_edit_contact_fields'         => $arrApplicantsSettings['can_edit_contact_fields'],

                    'options' => $arrApplicantsSettings['options'],

                    'accounting' => $arrApplicantsSettings['accounting'] ?? null,

                    'conditional_fields' => array(
                        'case' => $this->getConditionalFields()->getGroupedConditionalFields($this->getCaseTemplates()->getTemplates($currentMemberCompanyId, true)),
                    ),

                    'access' => array(
                        'employers_module_enabled'            => $this->_company->isEmployersModuleEnabledToCompany($currentMemberCompanyId),
                        'change_case_type'                    => $this->_company->isChangeCaseTypeAllowedToCompany($currentMemberCompanyId),
                        'view_active_cases'                   => true,
                        'generate_file_number'                => $this->getCaseNumber()->isAutomaticTurnedOn($currentMemberCompanyId),
                        'generate_file_number_field_readonly' => $this->getCaseNumber()->isFileNumberReadOnly($currentMemberCompanyId),
                        'submit_to_government'                => $this->_company->getCompanyDivisions()->canCurrentMemberSubmitClientToGovernment(),

                        'search' => array(
                            'view_left_panel'             => $booViewLeftPanel,
                            'show_conflict_of_interest'   => true,
                            'view_queue_panel'            => $this->_acl->isAllowed('clients-queue-run') && $this->_auth->getIdentity()->queue_show_in_left_panel == 'Y',
                            'view_saved_searches'         => !$this->_auth->isCurrentUserClient(),
                            'view_advanced_search'        => $this->_acl->isAllowed('clients-advanced-search-run'),
                            'view_advanced_search_export' => $this->_acl->isAllowed('clients-advanced-search-export'),
                            'view_advanced_search_print'  => $this->_acl->isAllowed('clients-advanced-search-print'),
                            'allow_multiple_tabs'         => $this->_company->isMultipleAdvancedSearchTabsAllowedToCompany($currentMemberCompanyId),
                        ),

                        'analytics' => array(
                            'view_saved'          => !$this->_auth->isCurrentUserClient() && $this->_acl->isAllowed('applicants-analytics-view'),
                            'add'                 => $this->_acl->isAllowed('applicants-analytics-add'),
                            'edit'                => $this->_acl->isAllowed('applicants-analytics-edit'),
                            'delete'              => $this->_acl->isAllowed('applicants-analytics-delete'),
                            'export'              => $this->_acl->isAllowed('applicants-analytics-export'),
                            'print'               => $this->_acl->isAllowed('applicants-analytics-print'),
                            'allow_multiple_tabs' => false,
                        ),

                        'queue' => array(
                            'view'   => $this->_acl->isAllowed('clients-queue-run'),
                            'export' => $this->_acl->isAllowed('clients-queue-export'),
                            'print'  => $this->_acl->isAllowed('clients-queue-print'),
                            'change' => array(
                                'pull_from_queue' => $membersQueues->hasMemberAccessToPullingCases() && $this->_acl->isAllowed('clients-queue-push-to-queue'),
                                'push_to_queue'   => $this->_acl->isAllowed('clients-queue-push-to-queue'),
                                'file_status'     => $this->_acl->isAllowed('clients-queue-change-file-status'),
                                'assigned_staff'  => $this->_acl->isAllowed('clients-queue-change-assigned-staff'),
                                'visa_subclass'   => isset($arrVisaSubclassInfo['label']) && $this->_acl->isAllowed('clients-queue-change-visa-subclass')
                            )
                        ),

                        'vevo' => $membersVevo->hasMemberAccessToVevo(),
                        'case' => $arrCaseAccessRights,

                        'generate_con'        => $this->_acl->isAllowed('generate-con'),
                        'generate_pdf_letter' => $this->_config['site_version']['custom_templates_settings']['comfort_letter']['enabled'] && $this->_acl->isAllowed('generate-pdf-letter'),

                        'accounting' => $this->getAccounting()->getAccountingAccessRights(),
                    ),

                    'last_saved_cases'                        => $this->getLastViewedClientsForTabs(),
                    'active_saved_search'                     => $defaultSearch,
                    'active_saved_search_name'                => $defaultSearchName,
                    'search_for'                              => $this->getApplicantFields()->getAdvancedSearchTypesList(),
                    'filters'                                 => $this->getApplicantFields()->getSearchFiltersList($booForAutomaticReminderConditions),
                    'default_case_template_case_reference_as' => $this->_config['site_version']['version'] == 'australia' ? 'Employer Sponsorship' : 'LMIA',
                    'case_type_label_singular'                => $this->_company->getCurrentCompanyDefaultLabel('case_type'),
                    'categories_label_singular'               => $this->_company->getCurrentCompanyDefaultLabel('categories'),
                    'office_label'                            => $this->_company->getCurrentCompanyDefaultLabel('office'),
                    'office_default_selected'                 => empty($this->_config['site_version']['show_my_offices_link']) ? 'all' : 'favourite',
                    'ta_label'                                => $this->_company->getCurrentCompanyDefaultLabel('trust_account'),
                    'file_status_label'                       => $this->getFields()->getCaseStatusFieldLabel($currentMemberCompanyId),
                    'rma_label'                               => $this->_company->getCurrentCompanyDefaultLabel('rma'),
                    'first_name_label'                        => $this->_company->getCurrentCompanyDefaultLabel('first_name'),
                    'last_name_label'                         => $this->_company->getCurrentCompanyDefaultLabel('last_name'),
                    'visa_subclass_label'                     => $arrVisaSubclassInfo['label'] ?? 'Visa Subclass',
                    'show_other_dependents'                   => $this->_config['site_version']['version'] == 'australia',
                    'queue_settings'                          => $membersQueues->loadAllSettings(),
                    'export_range'                            => self::$exportClientsLimit,
                    'clients_count'                           => $clientsCount,
                    'free_clients_count'                      => $companyInfo['free_clients'],
                    'next_billing_date'                       => $this->_settings->formatDate($companyInfo['next_billing_date']),
                    'subscription_name'                       => $this->_company->getPackages()->getSubscriptionNameById($companyInfo['subscription']),
                    'amount_upgrade'                          => $amountUpgradePlan,
                    'vevo_members_list'                       => $membersVevo->getMembersFromVevoMappingList(),
                    'government_fund_option_id'               => $governmentFundOptionId,
                    'max_dependants_count'                    => $maxDependantsCount
                );

                if (!$this->_acl->isAllowed('clients-accounting-view')) {
                    unset($arrApplicantsSettings['accounting']);
                }

                $arrMemberTypes = $this->getMemberTypes(true);
                foreach ($arrMemberTypes as $arrMemberTypeInfo) {
                    $applicantId = $arrMemberTypeInfo['member_type_name'];

                    $arrAccess = array();
                    if ($applicantId == 'contact') {
                        if ($this->_acl->isAllowed('contacts-profile-new')) {
                            $arrAccess['add'] = 'add';
                        }

                        if ($this->_acl->isAllowed('contacts-profile-edit')) {
                            $arrAccess['edit'] = 'edit';
                        }

                        if ($this->_acl->isAllowed('contacts-profile-delete')) {
                            $arrAccess['delete'] = 'delete';
                        }

                        $arrAccess['queue'] = array(
                            'view' => $this->_acl->isAllowed('clients-queue-run'),

                            'change' => array(
                                'push_to_queue' => $this->_acl->isAllowed('clients-queue-push-to-queue'),
                                'file_status' => false,
                                'assigned_staff' => false,
                                'visa_subclass' => false
                            )
                        );

                        $arrAccess['search'] = array(
                            'view_left_panel'             => $this->_acl->isAllowed('contacts-view'),
                            'view_queue_panel'            => $this->_acl->isAllowed('clients-queue-run') && $this->_auth->getIdentity()->queue_show_in_left_panel == 'Y',
                            'show_conflict_of_interest'   => false,
                            'active_saved_search'         => 'all',
                            'active_saved_search_name'    => 'All contacts',
                            'view_saved_searches'         => !$this->_auth->isCurrentUserClient(),
                            'view_advanced_search'        => $this->_acl->isAllowed('contacts-advanced-search-run'),
                            'view_advanced_search_export' => $this->_acl->isAllowed('contacts-advanced-search-export'),
                            'view_advanced_search_print'  => $this->_acl->isAllowed('contacts-advanced-search-print'),
                            'allow_multiple_tabs'         => $this->_company->isMultipleAdvancedSearchTabsAllowedToCompany($currentMemberCompanyId),
                        );

                        $arrAccess['analytics'] = array(
                            'view_saved' => $this->_acl->isAllowed('contacts-analytics-view'),
                            'add' => $this->_acl->isAllowed('contacts-analytics-add'),
                            'edit' => $this->_acl->isAllowed('contacts-analytics-edit'),
                            'delete' => $this->_acl->isAllowed('contacts-analytics-delete'),
                            'export' => $this->_acl->isAllowed('contacts-analytics-export'),
                            'print' => $this->_acl->isAllowed('contacts-analytics-print'),
                            'allow_multiple_tabs' => false,
                        );
                    } else {
                        if ($this->_acl->isAllowed('clients-profile-new')) {
                            $arrAccess[] = 'add';
                        }

                        if ($this->_acl->isAllowed('clients-profile-edit')) {
                            $arrAccess[] = 'edit';
                        }

                        if ($this->_acl->isAllowed('clients-profile-delete')) {
                            $arrAccess[] = 'delete';
                        }

                        // Special check for client login possibility
                        switch ($applicantId) {
                            case 'individual':
                                $booCanLogin = $this->_acl->isAllowed('clients-individual-client-login');
                                break;

                            case 'employer':
                                $booCanLogin = $this->_acl->isAllowed('clients-employer-client-login');
                                break;

                            default:
                                $booCanLogin = false;
                                break;
                        }
                        if ($booCanLogin) {
                            $arrAccess[] = 'can_client_login';
                        }

                        if ($this->_acl->isAllowed('abn-check')) {
                            $arrAccess[] = 'abn_check';
                        }
                    }
                    $arrApplicantsSettings['access'][$applicantId] = $arrAccess;

                    $arrApplicantTypes = $this->getApplicantTypes()->getTypes($currentMemberCompanyId, false, $arrMemberTypeInfo['member_type_id']);
                    if (!is_array($arrApplicantTypes) || !count($arrApplicantTypes)) {
                        $arrApplicantTypes = array(
                            array(
                                'applicant_type_id' => 0,
                                'applicant_type_name' => ''
                            )
                        );
                    }

                    $arrGroupedFields = array();
                    foreach ($arrApplicantTypes as $arrApplicantTypeInfo) {
                        $arrGroupedFields[] = array(
                            'type_id' => $arrApplicantTypeInfo['applicant_type_id'],
                            'type_name' => $arrApplicantTypeInfo['applicant_type_name'],
                            'fields' => $this->getApplicantFields()->getGroupedCompanyFields($currentMemberCompanyId, $arrMemberTypeInfo['member_type_id'], $arrApplicantTypeInfo['applicant_type_id'])
                        );
                    }

                    $arrApplicantsSettings['applicant_types'][$applicantId] = $arrApplicantTypes;
                    $arrApplicantsSettings['groups_and_fields'][$applicantId] = $arrGroupedFields;
                    $arrApplicantsSettings['options'][$applicantId] = $this->getApplicantFields()->getGroupedFieldsOptions($arrGroupedFields);
                }
                $arrApplicantsSettings['groups_and_fields']['accounting'] = $arrAccountingFields;

                if ($this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled()) {
                    $arrAllSavedTags = $this->getClientDependents()->getGroupedTags($currentMemberId);
                    $arrTagFields = array();

                    foreach ($arrAllSavedTags as $tag) {
                        $arrTagFields[] = array(
                            'field_unique_id' => $tag,
                            'field_name' => $tag,
                            'field_type' => 'text',
                            'field_required' => 'N',
                            'field_disabled' => 'N',
                            'field_group' => 'Tag Percentage',
                            'field_column_show' => false
                        );
                    }

                    $arrTagPercentageFieldsGrouped = array(
                        'group_id'            => 0,
                        'group_title'         => '',
                        'group_collapsed'     => 'N',
                        'group_show_title'    => 'Y',
                        'group_repeatable'    => 'N',
                        'group_contact_block' => 'Y',
                        'group_cols_count'    => 3,
                        'group_access'        => 'R',
                        'fields'              => $arrTagFields
                    );

                    $arrTagPercentageFields = array(array('type_id' => 0, 'type_name' => '', 'fields' => $arrTagPercentageFieldsGrouped));

                    $arrApplicantsSettings['groups_and_fields']['tag_percentage'] = $arrTagPercentageFields;
                }
            }

            $arrEmployeeOptions = array();
            if ($booLoadEmployerData && !$this->_auth->isCurrentUserSuperadmin()) {
                // Load data for "employee" fieldsonly if it is used/showed
                $booLoadEmployeeData  = true;
                if (isset($arrApplicantsSettings['case_group_templates']) || isset($arrApplicantsSettings['groups_and_fields'])) {
                    $booLoadEmployeeData  = false;

                    // Search if there is such field type in the case's type
                    if (isset($arrApplicantsSettings['case_group_templates'])) {
                        foreach ($arrApplicantsSettings['case_group_templates'] as $arrCaseGroups) {
                            foreach ($arrCaseGroups as $arrCaseGroupInfo) {
                                foreach ($arrCaseGroupInfo['fields'] as $arrCaseFieldInfo) {
                                    if ($arrCaseFieldInfo['field_type'] === 'employee') {
                                        $booLoadEmployeeData = true;
                                        break 3;
                                    }
                                }
                            }
                        }
                    }

                    // Search if there is such field type in the client's type
                    if (isset($arrApplicantsSettings['groups_and_fields']) && !$booLoadEmployeeData) {
                        foreach ($arrApplicantsSettings['groups_and_fields'] as $type => $arrClientTypesGrouped) {
                            if (in_array($type, array('tag_percentage', 'accounting'))) {
                                continue;
                            }

                            foreach ($arrClientTypesGrouped as $arrClientType) {
                                foreach ($arrClientType['fields'] as $arrClientGroups) {
                                    foreach ($arrClientGroups['fields'] as $arrClientFieldInfo) {
                                        if ($arrClientFieldInfo['field_type'] === 'employee') {
                                            $booLoadEmployeeData = true;
                                            break 4;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                if ($booLoadEmployeeData) {
                    $arrAssignedCasesIds = $this->getClientsList();
                    $arrAssignedCases = $this->getCasesListWithParents($arrAssignedCasesIds);

                    foreach ($arrAssignedCases as $arrCaseInfo) {
                        $arrEmployeeOptions[] = array(
                            'option_id' => $arrCaseInfo['clientId'],
                            'option_name' => $arrCaseInfo['clientFullName']
                        );
                    }
                }
            }
            $arrApplicantsSettings['options']['general']['employee'] = $arrEmployeeOptions;
        } catch (Exception $e) {
            $arrApplicantsSettings = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrApplicantsSettings;
    }

    public function getContactOptions($currentMemberCompanyId)
    {
        $arrContactOptions = array();
        $memberTypeId = $this->getMemberTypeIdByName('contact');
        $applicantTypeId = $this->getApplicantTypes()->getTypeIdByName($currentMemberCompanyId, $memberTypeId, 'Sales Agent');
        $arrAllContacts = $this->getApplicants('contact', $applicantTypeId);
        foreach ($arrAllContacts as $arrContactInfo) {
            $arrContactOptions[] = array(
                'option_id' => $arrContactInfo['user_id'],
                'option_name' => $arrContactInfo['user_name']
            );
        }
        usort(
            $arrContactOptions,
            function ($a, $b) {
                return strcmp(strtolower($a['option_name'] ?? ''), strtolower($b['option_name'] ?? ''));
            }
        );

        return $arrContactOptions;
    }

    /**
     * Check if current member has access to the specific company Client Account
     *
     * @param int $companyTAId
     * @return bool has access
     */
    public function hasCurrentMemberAccessToTA($companyTAId)
    {
        $booHasAccess = false;

        if (!empty($companyTAId)) {
            // Get current user company id
            $companyId = $this->_auth->getCurrentUserCompanyId();

            // Load all TAs for this company
            $arrCompanyTA = $this->getAccounting()->getCompanyTA($companyId, true);

            // Check if TA's id is in the list
            if ($this->_auth->isCurrentUserSuperadmin() || (is_array($arrCompanyTA) && in_array($companyTAId, $arrCompanyTA))) {
                $booHasAccess = true;
            }
        }

        return $booHasAccess;
    }

    /**
     * Check if folder is located in client's folder
     * OR is Shared folder for the client's company
     * And if current user has access to this client
     * @param $memberId - client's id which is checked
     * @param $checkFolder - ftp folder which is checked
     * @param int|null $currentMemberId
     * @return bool true if user has access, otherwise false
     */
    public function checkClientFolderAccess($memberId, $checkFolder, $currentMemberId = null)
    {
        if (empty($memberId) || empty($checkFolder)) {
            return false;
        }

        $memberInfo = $this->getMemberInfo($memberId);
        $companyId = $memberInfo['company_id'];
        $booIsLocal = $this->_company->isCompanyStorageLocationLocal($companyId);
        $memberType = $this->getMemberTypeByMemberId($memberId);
        $isClient = $this->isMemberClient($memberType);

        $checkFolder = str_replace('\\', '/', $checkFolder ?? '');
        $pathToClientDocs = $this->_files->getMemberFolder($companyId, $memberId, $isClient, $booIsLocal) ?? '';
        $pathToClientDocs = str_replace('\\', '/', $pathToClientDocs);
        $parsedPath = substr($checkFolder, 0, strlen($pathToClientDocs));

        $pathToSharedDocs = $this->_files->getCompanySharedDocsPath($companyId, false, $booIsLocal) ?? '';
        $pathToSharedDocs = str_replace('\\', '/', $pathToSharedDocs);
        $parsedSharedPath = substr($checkFolder, 0, strlen($pathToSharedDocs));

        $booMemberHasAccessToMember = isset($currentMemberId)
            ? $this->hasMemberAccessToMember($currentMemberId, $memberId)
            : $this->hasCurrentMemberAccessToMember($memberId);

        return (($parsedPath == $pathToClientDocs || $parsedSharedPath == $pathToSharedDocs) && $booMemberHasAccessToMember);
    }

    /**
     * Generate a new CSV file in the temp directory with clients + cases data
     * Columns:
     *  - client id,
     *  - clients' fields (ordered by field name that are used in the IA/Employer profile)
     *  - cases' fields (ordered by field name that are used at least in one Immigration Program)
     *
     * @param int $companyId - a company that we want to export data for
     * @param int $start - an offset for this batch
     * @param int $limit - clients count that we want to export in the batch
     * @return array of string error (empty on success) and string file path to the generated file
     */
    public function exportCompanyProfilesAdCases($companyId, $start, $limit)
    {
        $strError    = '';
        $strFilePath = '';

        $oFields = $this->getFields();

        // Collect clients' fields
        $arrClientsUsedFields = [];
        $arrMemberTypes       = array_merge(Members::getMemberType('individual'), Members::getMemberType('employer'));
        foreach ($arrMemberTypes as $memberType) {
            $arrGroupedClientFields = $this->getApplicantFields()->getGroupedCompanyFields($companyId, $memberType, 0, false);

            foreach ($arrGroupedClientFields as $arrGroups) {
                foreach ($arrGroups['fields'] as $arrFieldInfo) {
                    $arrClientsUsedFields[$arrFieldInfo['field_id']] = $arrFieldInfo;
                }
            }

            // Sort by field_unique_id, keep keys
            $keys = array_keys($arrClientsUsedFields);
            array_multisort(array_column($arrClientsUsedFields, 'field_unique_id'), SORT_ASC, SORT_NATURAL | SORT_FLAG_CASE, $arrClientsUsedFields, $keys);
            $arrClientsUsedFields = array_combine($keys, $arrClientsUsedFields);
        }

        if (empty($strError) && empty($arrClientsUsedFields)) {
            $strError = $this->_tr->translate('There are no client fields created.');
        }


        // Collect cases' fields
        $arrCaseTemplates = $this->getCaseTemplates()->getTemplates($companyId);
        if (empty($strError) && empty($arrCaseTemplates)) {
            $strError = $this->_tr->translate('There are no client types created.');
        }

        $arrCasesUsedFields   = [];
        $arrCasesStaticFields = [];
        if (empty($strError)) {
            foreach ($arrCaseTemplates as $arrCaseTemplateInfo) {
                $arrCaseTemplateGroups = $oFields->getGroupedCompanyFields($arrCaseTemplateInfo['case_template_id'], false);

                foreach ($arrCaseTemplateGroups as $arrGroups) {
                    foreach ($arrGroups['fields'] as $arrFieldInfo) {
                        $arrCasesUsedFields[$arrFieldInfo['field_id']] = $arrFieldInfo;

                        if ($oFields->isStaticField($arrFieldInfo['field_unique_id'])) {
                            $arrCasesStaticFields[] = $arrFieldInfo['field_id'];
                        }
                    }
                }
            }

            // Sort by field_unique_id, keep keys
            $keys = array_keys($arrCasesUsedFields);
            array_multisort(array_column($arrCasesUsedFields, 'field_unique_id'), SORT_ASC, SORT_NATURAL | SORT_FLAG_CASE, $arrCasesUsedFields, $keys);
            $arrCasesUsedFields = array_combine($keys, $arrCasesUsedFields);
        }

        if (empty($strError) && empty($arrCasesUsedFields)) {
            $strError = $this->_tr->translate('There are no case fields created.');
        }

        $arrResult = [];
        if (empty($strError) && empty($start)) {
            // Generate columns ONLY for the first file
            $arrRow1 = [];
            $arrRow2 = [];

            // Clients Columns
            $arrRow1[] = 'client_id';
            $arrRow2[] = 'Client Id';
            foreach ($arrClientsUsedFields as $arrFieldInfo) {
                $arrRow1[] = $arrFieldInfo['field_unique_id'];
                $arrRow2[] = $arrFieldInfo['field_name'];
            }

            // Cases Columns
            foreach ($arrCasesUsedFields as $arrFieldInfo) {
                $arrRow1[] = $arrFieldInfo['field_unique_id'];
                $arrRow2[] = $arrFieldInfo['field_name'];
            }
            $arrResult[] = $arrRow1;
            $arrResult[] = $arrRow2;
        }

        if (empty($strError)) {
            // Get the list of clients that we'll export in this batch
            $select = (new Select())
                ->from('members')
                ->columns(['member_id'])
                ->where([
                    'company_id' => (int)$companyId,
                    'userType'   => $arrMemberTypes
                ])
                ->offset($start)
                ->limit($limit)
                ->order('member_id');

            $arrClientsIds = $this->_db2->fetchCol($select);

            $arrInternalContactType = Members::getMemberType('internal_contact');
            $arrCaseType            = Members::getMemberType('case');
            foreach (array_chunk($arrClientsIds, 2000) as $arrClientsIdsSet) {
                $select = (new Select())
                    ->from(array('mr' => 'members_relations'))
                    ->join(array('m' => 'members'), 'mr.child_member_id = m.member_id', ['userType'])
                    ->where(['mr.parent_member_id' => $arrClientsIdsSet])
                    ->order('parent_member_id');

                $arrClientsRelations = $this->_db2->fetchAll($select);

                // Group ids to load data later
                $arrCasesIds            = [];
                $arrInternalContactsIds = [];
                foreach ($arrClientsRelations as $arrClientsRelationInfo) {
                    if (in_array($arrClientsRelationInfo['userType'], $arrInternalContactType)) {
                        $arrInternalContactsIds[] = $arrClientsRelationInfo['child_member_id'];
                    } elseif (in_array($arrClientsRelationInfo['userType'], $arrCaseType)) {
                        $arrCasesIds[] = $arrClientsRelationInfo['child_member_id'];
                    }
                }

                // Clients' data
                $arrGroupedInternalClientsData = [];
                if (!empty($arrInternalContactsIds)) {
                    $select = (new Select())
                        ->from(array('d' => 'applicant_form_data'))
                        ->where(['d.applicant_field_id' => array_keys($arrClientsUsedFields)])
                        ->where(['d.applicant_id' => $arrInternalContactsIds]);

                    $arrApplicantsData = $this->_db2->fetchAll($select);

                    foreach ($arrApplicantsData as $arrClientDataRow) {
                        if (empty($arrClientDataRow['value'])) {
                            $value = '';
                        } else {
                            list($value,) = $oFields->getFieldReadableValue($companyId, 0, $arrClientsUsedFields[$arrClientDataRow['applicant_field_id']], $arrClientDataRow['value'], true);
                        }

                        $arrGroupedInternalClientsData[$arrClientDataRow['applicant_id']][$arrClientDataRow['applicant_field_id']] = $value;
                    }
                }

                // Cases' data
                $arrGroupedCasesData = [];
                if (!empty($arrCasesIds) && !empty($arrCasesUsedFields)) {
                    $select = (new Select())
                        ->from(array('d' => 'client_form_data'))
                        ->where(['d.field_id' => array_keys($arrCasesUsedFields)])
                        ->where(['d.member_id' => $arrCasesIds]);

                    $arrCasesData = $this->_db2->fetchAll($select);

                    foreach ($arrCasesData as $arrCaseDataRow) {
                        if (empty($arrCaseDataRow['value'])) {
                            $value = '';
                        } else {
                            list($value,) = $oFields->getFieldReadableValue($companyId, 0, $arrCasesUsedFields[$arrCaseDataRow['field_id']], $arrCaseDataRow['value']);
                        }

                        $arrGroupedCasesData[$arrCaseDataRow['member_id']][$arrCaseDataRow['field_id']] = $value;
                    }

                    if (!empty($arrCasesStaticFields)) {
                        $select = (new Select())
                            ->from(array('m' => 'members'))
                            ->columns(['member_id', 'username', 'emailAddress', 'fName', 'lName', 'regTime'])
                            ->join(array('c' => 'clients'), 'm.member_id = c.member_id', ['fileNumber', 'agent_id'])
                            ->where(['m.member_id' => $arrCasesIds]);

                        $arrCasesStatic = $this->_db2->fetchAll($select);

                        foreach ($arrCasesUsedFields as $arrCaseFieldInfo) {
                            if (in_array($arrCaseFieldInfo['field_id'], $arrCasesStaticFields)) {
                                $column = $oFields->getStaticColumnName($arrCaseFieldInfo['field_unique_id']);
                                foreach ($arrCasesStatic as $arrCasesStaticInfo) {
                                    $value = '';
                                    if (isset($arrCasesStaticInfo[$column]) && !empty($arrCasesStaticInfo[$column])) {
                                        list($value,) = $oFields->getFieldReadableValue($companyId, 0, $arrCaseFieldInfo, $arrCasesStaticInfo[$column]);
                                    }

                                    $arrGroupedCasesData[$arrCasesStaticInfo['member_id']][$arrCaseFieldInfo['field_id']] = $value;
                                }
                            }
                        }
                    }
                }

                // Group data by the parent client
                $arrGroupedRows = [];
                foreach ($arrClientsRelations as $arrClientsRelationInfo) {
                    $clientId = $arrClientsRelationInfo['parent_member_id'];
                    if (isset($arrGroupedInternalClientsData[$arrClientsRelationInfo['child_member_id']])) {
                        foreach ($arrGroupedInternalClientsData[$arrClientsRelationInfo['child_member_id']] as $fieldId => $fieldValue) {
                            $arrGroupedRows[$clientId]['client'][$fieldId] = $fieldValue;
                        }
                    }

                    if (isset($arrGroupedCasesData[$arrClientsRelationInfo['child_member_id']])) {
                        foreach ($arrGroupedCasesData[$arrClientsRelationInfo['child_member_id']] as $fieldId => $fieldValue) {
                            $arrGroupedRows[$clientId]['case'][$arrClientsRelationInfo['child_member_id']][$fieldId] = $fieldValue;
                        }
                    }
                }

                // Generate a row of the client's row
                if (!empty($arrGroupedRows)) {
                    foreach ($arrGroupedRows as $clientId => $arrFields) {
                        $arrClientRow = [$clientId];
                        foreach ($arrClientsUsedFields as $arrClientFieldInfo) {
                            $arrClientRow[] = $arrFields['client'][$arrClientFieldInfo['field_id']] ?? '';
                        }

                        if (!isset($arrFields['case'])) {
                            $arrFields['case'] = array(array());
                        }

                        foreach ($arrFields['case'] as $arrCaseData) {
                            $arrRow = $arrClientRow;
                            foreach ($arrCasesUsedFields as $arrCaseFieldInfo) {
                                $arrRow[] = $arrCaseData[$arrCaseFieldInfo['field_id']] ?? '';
                            }
                            $arrResult[] = $arrRow;
                        }
                    }
                }
            }

            // Create a csv file
            $strFilePath = tempnam($this->_config['directory']['tmp'], 'csv');

            $fp = fopen($strFilePath, 'w');
            foreach ($arrResult as $fields) {
                fputcsv($fp, $fields);
            }
            fclose($fp);

            if (!file_exists($strFilePath)) {
                $strFilePath = '';
                $strError    = $this->_tr->translate('CSV file was not created.');
            }
        }

        return [$strError, $strFilePath];
    }

    /**
     * Provides list of fields available for system templates.
     * @param EventInterface $e
     * @return array
     */
    public function getSystemTemplateFields(EventInterface $e)
    {
        return $this->getAccounting()->getSystemTemplateFields($e);
    }



    /**
     * Get case readable info (with correct parent client name)
     *
     * @param int $caseId
     * @return array
     */
    public function getClientAndCaseReadableInfo($caseId)
    {
        $arrCaseInfo     = $this->getClientInfo($caseId);
        $arrCasesParents = $this->getParentsForAssignedApplicants([$caseId]);
        if (array_key_exists($caseId, $arrCasesParents)) {
            $arrCaseInfo['fName'] = $arrCasesParents[$caseId]['fName'];
            $arrCaseInfo['lName'] = $arrCasesParents[$caseId]['lName'];
        }

        return empty($arrCaseInfo) ? [] : $this->generateClientName($arrCaseInfo);
    }
}
