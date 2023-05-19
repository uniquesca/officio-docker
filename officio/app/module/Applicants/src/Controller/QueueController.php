<?php

namespace Applicants\Controller;

use Clients\Service\Clients;
use Clients\Service\Members;
use Clients\Service\MembersQueues;
use Exception;
use Files\BufferedStream;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Common\Service\AccessLogs;
use Officio\Common\Service\Settings;
use Officio\Service\Company;
use Officio\Service\SystemTriggers;
use Officio\Service\Users;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Applicants Search Controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class QueueController extends BaseController
{

    /** @var Company */
    protected $_company;

    /** @var Users */
    protected $_users;

    /** @var Clients */
    protected $_clients;

    /** @var MembersQueues */
    protected $_membersQueues;

    /** @var SystemTriggers */
    protected $_triggers;

    /** @var AccessLogs */
    protected $_accessLogs;

    public function initAdditionalServices(array $services)
    {
        $this->_company       = $services[Company::class];
        $this->_clients       = $services[Clients::class];
        $this->_users         = $services[Users::class];
        $this->_membersQueues = $services[MembersQueues::class];
        $this->_triggers      = $services[SystemTriggers::class];
        $this->_accessLogs    = $services[AccessLogs::class];
    }

    public function indexAction()
    {
        $view = new ViewModel(
            ['content' => null]
        );
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        return $view;
    }

    public function loadQueuesWithCountAction()
    {
        $strError = '';
        $arrList  = array();

        session_write_close();

        try {
            $panelType = $this->params()->fromPost('panelType');
            if (!in_array($panelType, array('contacts', 'applicants', 'prospects'))) {
                $panelType = 'applicants';
            }

            $arrOffices = $this->_members->getDivisions();

            $arrOfficesIds = array();
            foreach ($arrOffices as $arrOfficeInfo) {
                $arrOfficesIds[] = $arrOfficeInfo['division_id'];
            }

            $companyId = $this->_auth->getCurrentUserCompanyId();

            $arrOfficesClientsCount = $this->_company->getCompanyDivisions()->getClientsCountForDivisions($panelType, $companyId, $arrOfficesIds);
            $arrPersonalOfficesIds  = $this->_members->getMemberDivisions($this->_auth->getCurrentUserId(), 'responsible_for');
            $defaultOfficeLabel     = $this->_company->getCompanyDefaultLabel($companyId, 'office');

            // Load saved queue settings for current user
            $arrQueueSettings = $this->_membersQueues->getMemberQueueSettings(
                $this->_auth->getCurrentUserId()
            );

            // Load/check queues selected by user (from GUI in combo)
            // If there are no saved settings - allow access to all offices (queues) that user has access to
            $arrQueueSelected = array();
            if (!empty($arrQueueSettings['queue_member_selected_queues'])) {
                $arrQueueSelected = unserialize($arrQueueSettings['queue_member_selected_queues']);
            }

            $arrGroupedAll         = [];
            $arrGroupedByOffices   = [];
            $arrGroupedByFavorites = [];
            foreach ($arrOfficesClientsCount as $arrOfficesClientsInfo) {
                // Make sure that we'll load records for the offices that we have access to
                if (!in_array($arrOfficesClientsInfo['division_id'], $arrOfficesIds)) {
                    continue;
                }

                $arrGroupedAll[$arrOfficesClientsInfo['member_id']] = 1;

                $arrGroupedByOffices[$arrOfficesClientsInfo['division_id']][$arrOfficesClientsInfo['member_id']] = 1;

                if (in_array($arrOfficesClientsInfo['division_id'], $arrQueueSelected)) {
                    $arrGroupedByFavorites[$arrOfficesClientsInfo['member_id']] = 1;
                }
            }

            if (!empty($this->_config['site_version']['show_my_offices_link'])) {
                $arrList[] = array(
                    'queueId'           => 'favourite',
                    'queueName'         => $this->_tr->translate('My') . ' ' . $defaultOfficeLabel . 's',
                    'queueClientsCount' => count($arrGroupedByFavorites)
                );
            }

            $arrList[] = array(
                'queueId'           => 0,
                'queueName'         => $this->_tr->translate('View All'),
                'queueClientsCount' => count($arrGroupedAll)
            );

            // Show personal offices at the top of the list
            foreach ($arrOffices as $arrOfficeInfo) {
                if (in_array($arrOfficeInfo['division_id'], $arrPersonalOfficesIds)) {
                    $officeCount = isset($arrGroupedByOffices[$arrOfficeInfo['division_id']]) ? count($arrGroupedByOffices[$arrOfficeInfo['division_id']]) : 0;

                    $arrList[] = array(
                        'queueId'           => $arrOfficeInfo['division_id'],
                        'queueName'         => $arrOfficeInfo['name'],
                        'queueClientsCount' => $officeCount
                    );
                }
            }

            foreach ($arrOffices as $arrOfficeInfo) {
                if (!in_array($arrOfficeInfo['division_id'], $arrPersonalOfficesIds)) {
                    $officeCount = isset($arrGroupedByOffices[$arrOfficeInfo['division_id']]) ? count($arrGroupedByOffices[$arrOfficeInfo['division_id']]) : 0;

                    $arrList[] = array(
                        'queueId'           => $arrOfficeInfo['division_id'],
                        'queueName'         => $arrOfficeInfo['name'],
                        'queueClientsCount' => $officeCount
                    );
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError,
            'items'   => $arrList,
            'count'   => count($arrList),
        );

        return new JsonModel($arrResult);
    }

    public function saveSettingsAction()
    {
        $strError = '';

        try {
            if (empty($strError)) {
                // Save settings
                $arrIndividualColumns = Json::decode($this->params()->fromPost('arrIndividualColumnIds'), Json::TYPE_ARRAY);
                if (!is_null($arrIndividualColumns)) {
                    $booSuccess = false;
                    if (is_array($arrIndividualColumns) && count($arrIndividualColumns)) {
                        $booSuccess = $this->_membersQueues->saveMemberQueueSettings(
                            $this->_auth->getCurrentUserId(),
                            'queue_individual_columns',
                            serialize($arrIndividualColumns)
                        );
                    }

                    if (!$booSuccess) {
                        $strError = $this->_tr->translate('Selected individual columns were not saved.');
                    }
                }

                $arrEmployerColumns = Json::decode($this->params()->fromPost('arrEmployerColumnIds'), Json::TYPE_ARRAY);
                if (!is_null($arrEmployerColumns)) {
                    $booSuccess = false;
                    if (is_array($arrEmployerColumns) && count($arrEmployerColumns)) {
                        $booSuccess = $this->_membersQueues->saveMemberQueueSettings(
                            $this->_auth->getCurrentUserId(),
                            'queue_employer_columns',
                            serialize($arrEmployerColumns)
                        );
                    }

                    if (!$booSuccess) {
                        $strError = $this->_tr->translate('Selected employer columns were not saved.');
                    }
                }

                $arrSelectedOffices = Json::decode($this->params()->fromPost('arrOfficesIds'), Json::TYPE_ARRAY);
                if (empty($strError) && !is_null($arrSelectedOffices)) {
                    $arrSelectedOffices = explode(',', $arrSelectedOffices);

                    $booSuccess = false;
                    if (is_array($arrSelectedOffices) && count($arrSelectedOffices)) {
                        $arrOffices = $this->_members->getDivisions(true);
                        foreach ($arrSelectedOffices as $arrSelectedOfficeId) {
                            if (!in_array($arrSelectedOfficeId, $arrOffices)) {
                                $strError = $this->_company->getCurrentCompanyDefaultLabel('office') . $this->_tr->translate(' was selected incorrectly.');
                                break;
                            }
                        }

                        if (empty($strError)) {
                            $booSuccess = $this->_membersQueues->saveMemberQueueSettings(
                                $this->_auth->getCurrentUserId(),
                                'queue_member_selected_queues',
                                serialize($arrSelectedOffices)
                            );
                        }
                    }


                    if (empty($strError) && !$booSuccess) {
                        $strError = sprintf($this->_tr->translate('Settings of the %s were not saved.'), $this->_company->getCurrentCompanyDefaultLabel('office'));
                    }
                }

                $booShowIndividualActiveCases = Json::decode($this->params()->fromPost('booShowIndividualActiveCases'), Json::TYPE_ARRAY);
                if (empty($strError) && !is_null($booShowIndividualActiveCases)) {
                    $booSuccess = $this->_membersQueues->saveMemberQueueSettings(
                        $this->_auth->getCurrentUserId(),
                        'queue_individual_show_active_cases',
                        (int)$booShowIndividualActiveCases
                    );

                    if (!$booSuccess) {
                        $strError = $this->_tr->translate('State of the individual checkbox was not saved.');
                    }
                }

                $booShowEmployerActiveCases = Json::decode($this->params()->fromPost('booShowEmployerActiveCases'), Json::TYPE_ARRAY);
                if (empty($strError) && !is_null($booShowEmployerActiveCases)) {
                    $booSuccess = $this->_membersQueues->saveMemberQueueSettings(
                        $this->_auth->getCurrentUserId(),
                        'queue_employer_show_active_cases',
                        (int)$booShowEmployerActiveCases
                    );

                    if (!$booSuccess) {
                        $strError = $this->_tr->translate('State of the employer checkbox was not saved.');
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
        );

        return new JsonModel($arrResult);
    }

    public function runAction()
    {
        set_time_limit(5 * 60); // 5 minutes max!
        ini_set('memory_limit', '-1');
        session_write_close();

        $arrMembers      = array();
        $totalCount      = 0;
        $arrAllMemberIds = array();
        try {
            $arrResult = $this->_clients->getSearch()->loadClientsForQueueTab($this->params()->fromPost(), false);

            $strError        = $arrResult['message'];
            $arrMembers      = $arrResult['items'];
            $totalCount      = $arrResult['count'];
            $arrAllMemberIds = $arrResult['all_ids'];
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
            'items'   => $arrMembers,
            'count'   => $totalCount,
            'all_ids' => $arrAllMemberIds,
        );
        return new JsonModel($arrResult);
    }

    public function runAndGetMainInfoAction()
    {
        set_time_limit(5 * 60); // 5 minutes max!
        ini_set('memory_limit', '-1');
        session_write_close();

        $arrAllMemberIds = array();
        $searchName      = 'Queue';

        try {
            $arrStoreParams          = $this->params()->fromPost();
            $arrStoreParams['start'] = 0;

            $arrResult       = $this->_clients->getSearch()->loadClientsForQueueTab($arrStoreParams);
            $strError        = $arrResult['message'];
            $arrAllMemberIds = $arrResult['all_ids'];

            $maxRowsReturnCount = 1000;
            if (count($arrResult['all_ids']) > $maxRowsReturnCount) {
                $arrAllMemberIds = array_splice($arrAllMemberIds, 0, $maxRowsReturnCount);

                $searchName .= sprintf(' (show last %d records)', $maxRowsReturnCount);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'    => empty($strError),
            'message'    => $strError,
            'items'      => $arrAllMemberIds,
            'searchName' => $searchName
        );

        return new JsonModel($arrResult);
    }

    public function exportToExcelAction()
    {
        set_time_limit(5 * 60); // 5 minutes, no more
        ini_set('memory_limit', '-1');

        // Close session for writing - so next requests can be done
        session_write_close();

        $strMessage = '';
        $arrData    = array();

        try {
            $filter         = new StripTags();
            $format         = $filter->filter(Json::decode($this->params()->fromPost('format'), Json::TYPE_ARRAY));
            $arrColumns     = Json::decode($this->params()->fromPost('arrColumns'), Json::TYPE_ARRAY);
            $arrStoreParams = Json::decode($this->params()->fromPost('arrStoreParams'), Json::TYPE_ARRAY);
            $exportStart    = (int)Json::decode($this->params()->fromPost('exportStart'), Json::TYPE_ARRAY);
            $exportLimit    = Clients::$exportClientsLimit;

            if (empty($exportStart)) {
                $exportStart = 0;
            }

            if (empty($strMessage) && (!is_array($arrColumns) || !count($arrColumns))) {
                $strMessage = $this->_tr->translate('Incorrectly selected columns.');
            }

            if (empty($strMessage) && !in_array($format, array('xls', 'csv'))) {
                $strMessage = $this->_tr->translate('Incorrectly selected format.');
            }

            if (empty($strMessage)) {
                $arrStoreParams['start'] = $exportStart;
                $arrStoreParams['limit'] = $exportLimit;
                $arrResult               = $this->_clients->getSearch()->loadClientsForQueueTab($arrStoreParams, false);
                $strMessage              = $arrResult['message'];
                $arrData                 = $arrResult['items'];
            }

            if (empty($strMessage)) {
                $arrLog = array(
                    'log_section'     => 'client',
                    'log_action'      => 'export',
                    'log_description' => sprintf('Client Case Data Exported - %d records', count($arrData)),
                    'log_company_id'  => $this->_auth->getCurrentUserCompanyId(),
                    'log_created_by'  => $this->_auth->getCurrentUserId(),
                );
                $this->_accessLogs->saveLog($arrLog);

                $now   = date('Y-m-d H:i:s');
                $title = 'Clients search result';
                switch ($format) {
                    case 'csv':
                        $fileName = $title . ' ' . $now . '.csv';
                        $result   = $this->_clients->getSearch()->exportSearchDataCSV($arrColumns, $arrData);
                        if ($result !== false) {
                            $pointer        = fopen('php://output', 'wb');
                            $bufferedStream = new BufferedStream('text/csv', null, "attachment; filename=\"$fileName\"");
                            $bufferedStream->setStream($pointer);
                            foreach ($result as $row) {
                                fputcsv($pointer, $row);
                            }
                            return $bufferedStream;
                        }
                        break;

                    default:
                        $fileName = $title . ' ' . $now . '.xlsx';
                        $result   = $this->_clients->getSearch()->exportSearchData($arrColumns, $arrData, $title);
                        if ($result) {
                            $pointer        = fopen('php://output', 'wb');
                            $bufferedStream = new BufferedStream('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, "attachment; filename=\"$fileName\"");
                            $bufferedStream->setStream($pointer);

                            $writer = new Xlsx($result);
                            $writer->save('php://output');
                            fclose($pointer);

                            return $bufferedStream;
                        }
                        break;
                }

                $strMessage = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error. Please try again later');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);
        $view->setVariable('content', $strMessage);

        return $view;
    }

    public function printAction()
    {
        $view = new ViewModel(
            ['content' => null]
        );
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);
        // Do nothing - will be run in GUI
        return $view;
    }

    public function pullFromQueueAction()
    {
        $view = new JsonModel();

        $strError = '';

        try {
            $companyId       = $this->_auth->getCurrentUserCompanyId();
            $divisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();

            $pullFromQueueId = (int)Json::decode($this->findParam('pull_from_queue_id'), Json::TYPE_ARRAY);
            $pushToQueueId   = (int)Json::decode($this->findParam('push_to_queue_id'), Json::TYPE_ARRAY);

            $arrCompanyDivisionIds = $this->_company->getDivisions($companyId, $divisionGroupId, true);

            if (empty($strError)) {
                if (!in_array($pullFromQueueId, $arrCompanyDivisionIds)) {
                    $strError = sprintf(
                        $this->_tr->translate('Incorrectly selected "Pull from %s".'),
                        $this->_company->getCurrentCompanyDefaultLabel('office')
                    );
                }
            }

            if (empty($strError)) {
                if (!in_array($pushToQueueId, $arrCompanyDivisionIds)) {
                    $strError = sprintf(
                        $this->_tr->translate('Incorrectly selected "Push to %s".'),
                        $this->_company->getCurrentCompanyDefaultLabel('office')
                    );
                }
            }

            if (empty($strError)) {
                $strError = $this->_membersQueues->pullApplicantFromQueue($companyId, $divisionGroupId, $pullFromQueueId, $pushToQueueId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
        );
        return $view->setVariables($arrResult);
    }

    public function pushToQueueAction()
    {
        set_time_limit(5 * 60); // 5 minutes
        ini_set('memory_limit', '1024M');

        $strError           = '';
        $arrSelectedOffices = array();

        try {
            // Check client(s)
            $arrClientIds = Json::decode($this->params()->fromPost('arrClientIds'), Json::TYPE_ARRAY);
            if (!is_array($arrClientIds) || empty($arrClientIds)) {
                $strError = $this->_tr->translate('Please select client(s).');
            }

            $companyId         = $this->_auth->getCurrentUserCompanyId();
            $oCompanyDivisions = $this->_company->getCompanyDivisions();
            if (empty($strError)) {
                if (!$this->_members->hasCurrentMemberAccessToMember($arrClientIds) || !$oCompanyDivisions->canCurrentMemberEditClient($arrClientIds)) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }
            }

            if (empty($strError)) {
                $divisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();

                $booLoadSavedOffices = $this->params()->fromPost('booLoadSavedOffices');
                if ($booLoadSavedOffices) {
                    // If we just want to load the list of assigned offices to all these clients/cases
                    foreach ($arrClientIds as $clientId) {
                        $arrClientSavedOffices = $this->_clients->getApplicantOffices(array($clientId), $divisionGroupId);
                        $arrSelectedOffices    = array_merge($arrSelectedOffices, $arrClientSavedOffices);
                    }
                    $arrSelectedOffices = array_unique($arrSelectedOffices);
                } else {
                    // Check office(s)
                    $strOfficeId         = Json::decode($this->params()->fromPost('selectedOption', ''), Json::TYPE_ARRAY);
                    $arrOfficeIds        = explode(',', $strOfficeId);
                    $arrCompanyOfficeIds = $this->_company->getDivisions($companyId, $divisionGroupId, true);
                    foreach ($arrOfficeIds as $officeId) {
                        if (!in_array($officeId, $arrCompanyOfficeIds)) {
                            $strError = sprintf(
                                $this->_tr->translate('Incorrectly selected %s.'),
                                $this->_company->getCurrentCompanyDefaultLabel('office')
                            );
                            break;
                        }
                    }

                    if (empty($strError)) {
                        foreach ($arrClientIds as $clientId) {
                            $arrSavedOffices  = $this->_clients->getApplicantOffices(array($clientId), $divisionGroupId);
                            $arrDivisionsInfo = $oCompanyDivisions->getDivisionsByIds($arrSavedOffices);

                            // Make sure that permanent offices will be not deleted
                            $thisClientOfficesToAssign = $arrOfficeIds;
                            foreach ($arrDivisionsInfo as $arrDivisionInfo) {
                                if ($arrDivisionInfo['access_permanent'] == 'Y' && !in_array($arrDivisionInfo['division_id'], $thisClientOfficesToAssign)) {
                                    $thisClientOfficesToAssign[] = $arrDivisionInfo['division_id'];
                                }
                            }

                            list($booSuccess,) = $this->_clients->updateClientsOffices($companyId, array($clientId), $thisClientOfficesToAssign);

                            if (!$booSuccess) {
                                $strError = $this->_tr->translate('Internal error. Please try again later.');
                                break;
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'            => empty($strError),
            'message'            => $strError,
            'arrSelectedOffices' => $arrSelectedOffices
        );
        return new JsonModel($arrResult);
    }


    public function changeFileStatusAction()
    {
        $view = new JsonModel();

        set_time_limit(60 * 5); // 5 minutes
        ini_set('memory_limit', '-1');

        $strError = '';

        try {
            // Check client(s)
            $arrClientIds = Json::decode($this->findParam('arrClientIds'), Json::TYPE_ARRAY);
            if (!is_array($arrClientIds) || empty($arrClientIds)) {
                $strError = $this->_tr->translate('Please select client(s).');
            }

            $companyId         = $this->_auth->getCurrentUserCompanyId();
            $oCompanyDivisions = $this->_company->getCompanyDivisions();
            if (empty($strError)) {
                if (!$this->_members->hasCurrentMemberAccessToMember($arrClientIds) || !$oCompanyDivisions->canCurrentMemberEditClient($arrClientIds)) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }
            }

            // Check file status
            $fileStatusNewValue = Json::decode($this->findParam('selectedOption', ''), Json::TYPE_ARRAY);
            $arrFileStatusInfo  = $this->_clients->getFields()->getCompanyFieldInfoByUniqueFieldId('file_status', $companyId);

            $fieldId = $arrFileStatusInfo['field_id'] ?? 0;
            if (empty($fieldId)) {
                $strError = $this->_tr->translate('Field not found.');
            }

            if (empty($strError)) {
                $arrCompanyFileStatusOptions = $this->_clients->getCaseStatuses()->getCompanyCaseStatuses($companyId);

                $booCorrectOption = false;
                if (!empty($fileStatusNewValue)) {
                    $arrStatuses = explode(',', $fileStatusNewValue);
                    foreach ($arrStatuses as $caseStatusId) {
                        $booCorrectOption = false;
                        foreach ($arrCompanyFileStatusOptions as $arrCompanyFileStatusOption) {
                            if ($arrCompanyFileStatusOption['client_status_id'] == $caseStatusId) {
                                $booCorrectOption = true;
                                break;
                            }
                        }

                        if (!$booCorrectOption) {
                            break;
                        }
                    }
                }

                if (!$booCorrectOption) {
                    $strError = sprintf(
                        $this->_tr->translate('Incorrectly selected %s.'),
                        $this->_clients->getFields()->getCaseStatusFieldLabel($companyId)
                    );
                }
            }

            if (empty($strError)) {
                $arrInfoToLog  = array();
                $arrCaseFields = $this->_clients->getFields()->getCompanyFields($companyId);
                foreach ($arrClientIds as $clientId) {
                    $arrMemberInfo = $this->_members->getMemberInfo($clientId);

                    // Update office field's value in the profile - in relation to the client's type
                    if (in_array($arrMemberInfo['userType'], Members::getMemberType('case'))) {
                        $arrCaseIds = array($clientId);
                    } else {
                        $arrCaseIds = $this->_clients->getAssignedCases($clientId);
                    }

                    foreach ($arrCaseIds as $caseId) {
                        $arrClientAndCaseInfo = array(
                            'member_id' => $caseId,
                            'case'      => array(
                                'members'          => array(),
                                'clients'          => array(),
                                'client_form_data' => array(
                                    $fieldId => $fileStatusNewValue
                                )
                            )
                        );

                        // Remember value before the change
                        $fileStatusOldValue = $this->_clients->getFields()->getFieldDataValue($fieldId, $caseId);

                        $this->_clients->updateClient($arrClientAndCaseInfo, $companyId, $arrCaseFields);

                        // Prepare info to save in log
                        $arrInfoToLog[$caseId] = array(
                            'booIsApplicant' => false,

                            'arrOldData' => array(
                                array(
                                    'field_id' => $fieldId,
                                    'row'      => 0,
                                    'value'    => $fileStatusOldValue
                                )
                            ),

                            'arrNewData' => array(
                                array(
                                    'field_id' => $fieldId,
                                    'row'      => 0,
                                    'value'    => $fileStatusNewValue
                                )
                            ),
                        );


                        // Trigger: Case File Status changed
                        $booValueChanged = false;
                        if (!empty($fileStatusOldValue) && !empty($fileStatusNewValue) && $fileStatusOldValue != $fileStatusNewValue) {
                            $booValueChanged = true;
                        } elseif (!empty($fileStatusOldValue) && empty($fileStatusNewValue)) {
                            $booValueChanged = true;
                        } elseif (empty($fileStatusOldValue) && !empty($fileStatusNewValue)) {
                            $booValueChanged = true;
                        }

                        if ($booValueChanged) {
                            $this->_triggers->triggerFileStatusChanged(
                                $caseId,
                                $this->_clients->getCaseStatuses()->getCaseStatusesNames($fileStatusOldValue),
                                $this->_clients->getCaseStatuses()->getCaseStatusesNames($fileStatusNewValue),
                                $this->_auth->getCurrentUserId(),
                                $this->_auth->hasIdentity() ? $this->_auth->getIdentity()->full_name : ''
                            );
                        }
                    }
                }

                // Log the changes
                $this->_triggers->triggerFieldBulkChanges($companyId, $arrInfoToLog, true);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
        );
        return $view->setVariables($arrResult);
    }

    public function changeAssignedStaffAction()
    {
        $view = new JsonModel();

        set_time_limit(60 * 5); // 5 minutes
        ini_set('memory_limit', '-1');

        $strError = '';

        try {
            // Check client(s)
            $arrClientIds = Json::decode($this->findParam('arrClientIds'), Json::TYPE_ARRAY);
            if (!is_array($arrClientIds) || empty($arrClientIds)) {
                $strError = $this->_tr->translate('Please select client(s).');
            }

            $oCompanyDivisions = $this->_company->getCompanyDivisions();

            $companyId = $this->_auth->getCurrentUserCompanyId();
            if (empty($strError)) {
                if (!$this->_members->hasCurrentMemberAccessToMember($arrClientIds) || !$oCompanyDivisions->canCurrentMemberEditClient($arrClientIds)) {
                    $strError = $this->_tr->translate('Incorrectly selected client.');
                }
            }

            $companyFieldId = Json::decode($this->findParam('companyFieldId'), Json::TYPE_ARRAY);
            $newValue       = Json::decode($this->findParam('selectedOption'), Json::TYPE_ARRAY);

            $arrAssignedStaffFields = array('registered_migrant_agent', 'accounting', 'processing', 'sales_and_marketing');
            if (empty($strError) && !in_array($companyFieldId, $arrAssignedStaffFields)) {
                $strError = $this->_tr->translate('Incorrectly selected field.');
            }

            $fieldId      = 0;
            $arrFieldInfo = array();
            if (empty($strError)) {
                $arrFieldInfo = $this->_clients->getFields()->getCompanyFieldInfoByUniqueFieldId($companyFieldId, $companyId);
                $fieldId      = $arrFieldInfo['field_id'] ?? 0;

                if (empty($fieldId)) {
                    $strError = $this->_tr->translate('Field not found.');
                }
            }

            if (empty($strError)) {
                $arrFieldOptions = array();

                if ($companyFieldId == 'registered_migrant_agent') {
                    $arrFieldOptions = $this->_users->getAssignedToUsers();
                } else {
                    $arrAssignedTo = $this->_users->getAssignList('search');

                    if (is_array($arrAssignedTo) && count($arrAssignedTo)) {
                        foreach ($arrAssignedTo as $arrAssignedToInfo) {
                            $arrFieldOptions[] = array(
                                'option_id' => $arrAssignedToInfo['assign_to_id']
                            );
                        }
                    }
                }

                $booCorrectOption = false;
                foreach ($arrFieldOptions as $arrFieldOption) {
                    if ($arrFieldOption['option_id'] == $newValue) {
                        $booCorrectOption = true;
                        break;
                    }
                }

                if (!$booCorrectOption) {
                    $strError = sprintf(
                        $this->_tr->translate('Incorrectly selected %s.'),
                        $arrFieldInfo['label'] ?? 'field value'
                    );
                }
            }

            if (empty($strError)) {
                $arrInfoToLog  = array();
                $arrCaseFields = $this->_clients->getFields()->getCompanyFields($companyId);
                foreach ($arrClientIds as $clientId) {
                    $arrMemberInfo = $this->_members->getMemberInfo($clientId);

                    // Update office field's value in the profile - in relation to the client's type
                    if (in_array($arrMemberInfo['userType'], Members::getMemberType('case'))) {
                        $arrCaseIds = array($clientId);
                    } else {
                        $arrCaseIds = $this->_clients->getAssignedCases($clientId);
                    }

                    foreach ($arrCaseIds as $caseId) {
                        $arrClientAndCaseInfo = array(
                            'member_id' => $caseId,
                            'case'      => array(
                                'members'          => array(),
                                'clients'          => array(),
                                'client_form_data' => array(
                                    $fieldId => $newValue
                                )
                            )
                        );

                        // Remember value before the change
                        $oldValue = $this->_clients->getFields()->getFieldDataValue($fieldId, $caseId);

                        $this->_clients->updateClient($arrClientAndCaseInfo, $companyId, $arrCaseFields);

                        // Prepare info to save in log
                        $arrInfoToLog[$caseId] = array(
                            'booIsApplicant' => false,

                            'arrOldData' => array(
                                array(
                                    'field_id' => $fieldId,
                                    'row'      => 0,
                                    'value'    => $oldValue
                                )
                            ),

                            'arrNewData' => array(
                                array(
                                    'field_id' => $fieldId,
                                    'row'      => 0,
                                    'value'    => $newValue
                                )
                            ),
                        );
                    }
                }

                // Log the changes
                $this->_triggers->triggerFieldBulkChanges($companyId, $arrInfoToLog, true);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
        );
        return $view->setVariables($arrResult);
    }

    public function changeVisaSubclassAction()
    {
        $view = new JsonModel();

        set_time_limit(60 * 5); // 5 minutes
        ini_set('memory_limit', '-1');

        $strError = '';

        try {
            // Check client(s)
            $arrClientIds = Json::decode($this->findParam('arrClientIds'), Json::TYPE_ARRAY);
            if (!is_array($arrClientIds) || !count($arrClientIds)) {
                $strError = $this->_tr->translate('Please select client(s).');
            }

            $companyId = $this->_auth->getCurrentUserCompanyId();
            if (empty($strError)) {
                if (!$this->_members->hasCurrentMemberAccessToMember($arrClientIds) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($arrClientIds)) {
                    $strError = $this->_tr->translate('Incorrectly selected client.');
                }
            }

            // Check file status
            $visaSubclassNewValue = Json::decode($this->findParam('selectedOption'), Json::TYPE_ARRAY);
            $arrVisaSubclassInfo  = $this->_clients->getFields()->getCompanyFieldInfoByUniqueFieldId('visa_subclass', $companyId);

            $fieldId = $arrVisaSubclassInfo['field_id'] ?? 0;
            if (empty($strError) && empty($fieldId)) {
                $strError = $this->_tr->translate('Field not found.');
            }

            if (empty($strError)) {
                $arrCompanyVisaSubclassOptions = $this->_clients->getCaseCategories()->getCompanyCaseCategories($companyId);

                $booCorrectOption = false;
                foreach ($arrCompanyVisaSubclassOptions as $arrCompanyVisaSubclassOption) {
                    if ($arrCompanyVisaSubclassOption['client_category_id'] == $visaSubclassNewValue) {
                        $booCorrectOption = true;
                        break;
                    }
                }

                if (!$booCorrectOption) {
                    $strError = sprintf(
                        $this->_tr->translate('Incorrectly selected %s.'),
                        $arrVisaSubclassInfo['label'] ?? 'Visa Subclass'
                    );
                }
            }

            if (empty($strError)) {
                $arrInfoToLog  = array();
                $arrCaseFields = $this->_clients->getFields()->getCompanyFields($companyId);
                foreach ($arrClientIds as $clientId) {
                    $arrMemberInfo = $this->_members->getMemberInfo($clientId);

                    // Update office field's value in the profile - in relation to the client's type
                    if (in_array($arrMemberInfo['userType'], Members::getMemberType('case'))) {
                        $arrCaseIds = array($clientId);
                    } else {
                        $arrCaseIds = $this->_clients->getAssignedCases($clientId);
                    }

                    foreach ($arrCaseIds as $caseId) {
                        $arrClientAndCaseInfo = array(
                            'member_id' => $caseId,
                            'case'      => array(
                                'members'          => array(),
                                'clients'          => array(),
                                'client_form_data' => array(
                                    $fieldId => $visaSubclassNewValue
                                )
                            )
                        );

                        // Remember value before the change
                        $visaSubclassOldValue = $this->_clients->getFields()->getFieldDataValue($fieldId, $caseId);

                        $this->_clients->updateClient($arrClientAndCaseInfo, $companyId, $arrCaseFields);

                        // Prepare info to save in log
                        $arrInfoToLog[$caseId] = array(
                            'booIsApplicant' => false,

                            'arrOldData' => array(
                                array(
                                    'field_id' => $fieldId,
                                    'row'      => 0,
                                    'value'    => $visaSubclassOldValue
                                )
                            ),

                            'arrNewData' => array(
                                array(
                                    'field_id' => $fieldId,
                                    'row'      => 0,
                                    'value'    => $visaSubclassNewValue
                                )
                            ),
                        );
                    }
                }

                // Log the changes
                $this->_triggers->triggerFieldBulkChanges($companyId, $arrInfoToLog, true);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
        );
        return $view->setVariables($arrResult);
    }

}