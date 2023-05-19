<?php

namespace Officio\Service\Company;

use Clients\Model\TrackerModel;
use Clients\Service\Clients;
use Clients\Service\Members;
use Exception;
use Files\Model\FileInfo;
use Files\Service\Files;
use Officio\Common\Json;
use Notes\Service\Notes;
use Officio\Common\Service\BaseService;
use Officio\Common\Service\Country;
use Officio\Service\Company;
use Officio\Common\Service\Settings;
use Officio\Common\ServiceContainerHolder;
use Officio\Common\SubServiceInterface;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Prospects\Service\CompanyProspects;
use Tasks\Service\Tasks;
use Uniques\Php\StdLib\FileTools;

/**
 * Companies export + related functionality
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class CompanyExport extends BaseService implements SubServiceInterface
{

    use ServiceContainerHolder;

    /** @var Company */
    private $_parent;

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent()
    {
        return $this->_parent;
    }

    public function init()
    {
        set_time_limit(60 * 60); // 1 hour
        ini_set('memory_limit', '512M');
    }

    /**
     * Main method used to export specific data for specific company
     *
     * @param int $companyId
     * @param string $exportWhat
     * @param $exportStart
     * @param $exportRange
     * @param null $exportTaId
     * @param null|array|string $exportFilter
     * @param null $firstParam
     * @param null $secondParam
     * @return array|string Array where first argument is file name, and second is Spreadsheet object
     *                      or error message in case of failure
     */
    public function export($companyId, $exportWhat, $exportStart, $exportRange, $exportTaId = null, $exportFilter = null, $firstParam = null, $secondParam = null)
    {
        switch ($exportWhat) {
            case 'cases':
                $fileName    = 'Export Result ' . date('d-m-Y_H-i-s') . '.xlsx';
                $spreadsheet = $this->exportCases($companyId, $exportStart, $exportRange);
                break;

            case 'prospects':
                $fileName    = 'Export Result ' . date('d-m-Y_H-i-s') . '.xlsx';
                $spreadsheet = $this->exportProspects($companyId, $exportStart, $exportRange);
                break;

            case 'cases_notes':
                $fileName    = 'Notes ' . date('Y-m-d H:i:s') . '.xlsx';
                $spreadsheet = $this->exportNotes($companyId);
                break;

            case 'prospects_notes':
                $fileName    = 'Prospects Notes ' . date('Y-m-d H:i:s') . '.xlsx';
                $spreadsheet = $this->exportProspectsNotes($companyId);
                break;

            case 'tasks':
                $fileName    = 'Tasks ' . date('Y-m-d H:i:s') . '.xlsx';
                $spreadsheet = $this->exportTasks($companyId);
                break;

            case 'time_log':
                $fileName     = 'Timelog ' . date('Y-m-d H:i:s') . '.xlsx';
                $exportFilter = empty($exportFilter) ? [] : $exportFilter;
                $spreadsheet  = $this->exportTimeLog($companyId, $exportFilter);
                break;

            case 'client_balances':
                $title       = 'Case Balances';
                $fileName    = $title . '.xlsx';
                $spreadsheet = $this->exportClientBalances($companyId, $fileName, $title);
                break;

            case 'client_transactions':
                $title       = 'Case Transactions Report';
                $fileName    = $title . '.xlsx';
                $spreadsheet = $this->exportClientTransactions($companyId, $fileName, $title);
                break;

            case 'trust_account':
                $taLabel     = $this->_parent->getCurrentCompanyDefaultLabel('trust_account');
                $fileName    = sprintf($taLabel . 's Export %s.xlsx', date('Y-m-d H-i-s'));
                $spreadsheet = $this->exportTrustAccount(
                    $companyId,
                    (!empty($exportTaId) ? $exportTaId : null),
                    (!empty($exportFilter) ? $exportFilter : null),
                    (!empty($firstParam) ? $firstParam : null),
                    (!empty($secondParam) ? $secondParam : null)
                );
                break;

            default:
                $fileName    = '';
                $spreadsheet = false;
                break;
        }

        if (!$spreadsheet instanceof Spreadsheet) {
            return (is_string($spreadsheet)) ? $spreadsheet : $this->_tr->translate('Internal error.');
        } else {
            return [$fileName, $spreadsheet];
        }
    }

    /**
     * Export cases data for specific company
     *
     * @param int $companyId
     * @param int $exportStart
     * @param int $exportRange
     * @return Spreadsheet|string|bool
     */
    public function exportCases($companyId, $exportStart, $exportRange)
    {
        try {
            /** @var Clients $oClients */
            $oClients = $this->_serviceContainer->get(Clients::class);

            $userId = $this->_parent->getCompanyAdminId($companyId);

            $arrApplicantSettings = $oClients->getSettings($userId, $companyId, $this->_parent->getCompanyDivisions()->getCompanyMainDivisionGroupId($companyId));

            $arrClientsColumns          = $arrCasesColumns = array();
            $arrClientsColumnsWithNames = $arrCasesColumnsWithNames = array();

            $arrClientsColumnsWithNames[] = $arrCasesColumnsWithNames[] = array(
                'id'   => 'applicant_name',
                'name' => 'Applicant Name'
            );

            foreach ($arrApplicantSettings['case_group_templates'] as $arrGroups) {
                foreach ($arrGroups as $arrGroupedFields) {
                    foreach ($arrGroupedFields['fields'] as $arrFieldInfo) {
                        $id = 'case_' . $arrFieldInfo['field_unique_id'];
                        if (!in_array($id, $arrCasesColumns)) {
                            $arrCasesColumns[]          = $id;
                            $arrCasesColumnsWithNames[] = array(
                                'id'   => $id,
                                'name' => $arrFieldInfo['field_name']
                            );
                        }
                    }
                }
            }

            $arrStoreParams['arrColumns'] = Json::encode($arrCasesColumns);
            $arrStoreParams['start']      = $exportStart;
            $arrStoreParams['limit']      = $exportRange;
            $arrResult                    = $oClients->getSearch()->loadClientsForQueueTab($arrStoreParams, false, true, $companyId, $userId);
            $strMessage                   = $arrResult['message'];
            $arrCasesData                 = $arrResult['items'];

            if ($strMessage) {
                exit($this->_tr->translate($strMessage));
            } elseif (!($arrResult['count'])) {
                exit($this->_tr->translate('There are no cases to export.'));
            }

            $arrMemberIds = array_map(
                function ($element) {
                    return $element['case_id'];
                },
                $arrCasesData
            );

            $arrApplicantTypes = array('individual', 'employer');

            foreach ($arrApplicantTypes as $applicantType) {
                foreach ($arrApplicantSettings['groups_and_fields'][$applicantType] as $arrBlockInfo) {
                    foreach ($arrBlockInfo['fields'] as $arrFieldsGroups) {
                        foreach ($arrFieldsGroups['fields'] as $arrFieldInfo) {
                            $id                           = $applicantType . '_' . $arrFieldInfo['field_unique_id'];
                            $arrClientsColumns[]          = $id;
                            $arrClientsColumnsWithNames[] = array(
                                'id'   => $id,
                                'name' => $arrFieldInfo['field_name'],
                            );
                        }
                    }
                }
            }

            $arrCases = $oClients->getClientsList(false, $arrMemberIds, null, false, false, true, $userId);

            list($strMessage, $arrClientsData, ,) = $oClients->getSearch()->loadDetailedClientsInfo($arrCases, $arrClientsColumns, true, 0, 0, null, null, true, $companyId, $userId);

            if ($strMessage) {
                return $this->_tr->translate($strMessage);
            }

            $arrData[] = array(
                'title'   => 'Clients',
                'values'  => $arrClientsData,
                'columns' => $arrClientsColumnsWithNames
            );

            $arrParents     = $oClients->getParentsForAssignedApplicants($arrMemberIds);
            $arrParentNames = array();

            foreach ($arrMemberIds as $caseId) {
                if (array_key_exists($caseId, $arrParents)) {
                    $arrParentInfo           = $oClients->generateClientName($arrParents[$caseId]);
                    $arrParentNames[$caseId] = $arrParentInfo['full_name'];
                }
            }

            foreach ($arrCasesData as $key => $caseData) {
                $arrCasesData[$key]['applicant_name'] = $arrParentNames[$caseData['case_id']];
            }

            $arrData[] = array(
                'title'   => 'Cases',
                'values'  => $arrCasesData,
                'columns' => $arrCasesColumnsWithNames
            );

            $arrDependentFields           = $oClients->getFields()->getDependantFields();
            $arrDependentColumnsWithNames = array();

            $arrDependentColumnsWithNames[] = array(
                'id'    => 'applicant_name',
                'name'  => 'Applicant Name',
                'width' => 250
            );

            $arrDependentColumnsWithNames[] = array(
                'id'    => 'case_reference_number',
                'name'  => 'Case File #',
                'width' => 150
            );

            foreach ($arrDependentFields as $arrFieldInfo) {
                $arrDependentColumnsWithNames[] = array(
                    'id'    => $arrFieldInfo['field_id'],
                    'name'  => $arrFieldInfo['field_name'],
                    'width' => 150
                );
            }

            $arrDependents = $oClients->getFields()->getDependents($arrMemberIds, false, false);

            if (!empty($arrDependents)) {
                $dateFormatFull = $this->_settings->variable_get('dateFormatFull');

                foreach ($arrDependents as $key => $dependent) {
                    foreach ($dependent as $fieldId => $fieldValue) {
                        foreach ($arrDependentFields as $dependentField) {
                            if ($fieldId == $dependentField['field_id'] && $dependentField['field_type'] == 'date') {
                                $arrDependents[$key][$fieldId] = $this->_settings->reformatDate($fieldValue, 'Y-m-d', $dateFormatFull);
                            }
                        }
                    }

                    $arrClientInfo                                = $oClients->getClientInfo($dependent['member_id']);
                    $arrDependents[$key]['case_reference_number'] = $arrClientInfo['fileNumber'];
                    $arrDependents[$key]['applicant_name']        = $arrParentNames[$dependent['member_id']];
                    $arrDependents[$key]['relationship']          = ucfirst($arrDependents[$key]['relationship'] ?? '');
                    $arrDependents[$key]['migrating']             = ucfirst($arrDependents[$key]['migrating'] ?? '');
                }

                $arrData[] = array(
                    'title'   => 'Dependants',
                    'values'  => $arrDependents,
                    'columns' => $arrDependentColumnsWithNames
                );
            }

            return $oClients->exportToExcel($arrData);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            return $this->_tr->translate('Internal error.');
        }
    }

    /**
     * Export prospects data for specific company
     *
     * @param int $companyId
     * @param $exportStart
     * @param $exportRange
     * @return bool|Spreadsheet
     */
    public function exportProspects($companyId, $exportStart, $exportRange)
    {
        try {
            // collect headers
            /** @var CompanyProspects $oCompanyProspects */
            $oCompanyProspects = $this->_serviceContainer->get(CompanyProspects::class);

            $defaultQNRId       = $oCompanyProspects->getCompanyQnr()->getDefaultQuestionnaireId();
            $arrQnrFields       = $oCompanyProspects->getCompanyQnr()->getQuestionnaireFields($defaultQNRId, true);
            $languageSectionId  = $oCompanyProspects->getCompanyQnr()->getQuestionnaireSectionLanguageId();
            $jobSectionId       = $oCompanyProspects->getCompanyQnr()->getQuestionnaireSectionJobId();
            $jobSpouseSectionId = $oCompanyProspects->getCompanyQnr()->getQuestionnaireSpouseSectionJobId();

            $q_data_labels  = $arrProspectColumns = array();
            $firstNameLabel = '';
            $lastNameLabel  = '';
            foreach ($arrQnrFields as $q) {
                // skip occupation fields
                if (in_array($q['q_section_id'], array($jobSectionId, $jobSpouseSectionId))) {
                    continue;
                }

                // skip lang labels
                if ($q['q_section_id'] == $languageSectionId && strstr($q['q_field_unique_id'] ?? '', '_label') !== false) {
                    continue;
                }

                // skip "email confirmation" field
                if ($q['q_field_unique_id'] == 'qf_email_confirmation') {
                    continue;
                }

                if (str_starts_with($q['q_field_unique_id'] ?? '', 'qf_language')) {
                    // generate lang fields labels (e.g. 'French speak')
                    $exploded = explode('_', $q['q_field_unique_id'] ?? '');

                    $lang = strstr($q['q_field_unique_id'] ?? '', 'eng') !== false ? 'English' : 'French';
                    $what = end($exploded); // read|listen|...

                    $label = $lang . ' ' . $what;
                } elseif ($q['q_field_unique_id'] == 'qf_area_of_interest_other1' && empty($q['q_field_label'])) {
                    $label = 'Area of Interest (Other)';
                } else {
                    $label = strip_tags(str_replace(':', '', empty($q['q_field_label']) ? $q['original_q_field_label'] : $q['q_field_label']));

                    if ($q['q_field_unique_id'] == 'qf_first_name') {
                        $firstNameLabel = $label;
                    }

                    if ($q['q_field_unique_id'] == 'qf_last_name') {
                        $lastNameLabel = $label;
                    }
                }

                if (strstr($q['q_field_unique_id'] ?? '', 'spouse') !== false && stristr($label, 'spouse') === false) {
                    $label .= ' Spouse';
                }

                $q_data_labels[$q['q_field_unique_id']] = array(
                    'label' => $label,
                    'type'  => $q['q_field_type']
                );

                $arrProspectColumns[] = array(
                    'id'    => $q['q_field_unique_id'],
                    'name'  => $label,
                    'width' => 55,
                );
            }

            // Additional Created On field
            $q_data_labels['qf_create_date'] = array(
                'label' => $this->_tr->translate('Created On'),
                'type'  => 'date'
            );

            $arrProspectColumns[] = array(
                'id'    => 'qf_create_date',
                'name'  => $this->_tr->translate('Created On'),
                'width' => 55,
            );

            // collect data
            $arrResult = $oCompanyProspects->getProspectsList('prospects', $exportStart, $exportRange, 'all-prospects', '', null, 'cp.create_date', 'DESC', $companyId, $this->_parent->getCompanyDivisions()->getCompanyMainDivisionGroupId($companyId), null, null, false, null, true);

            $arrJobColumns = array(
                array(
                    'id'    => 'fName',
                    'name'  => $firstNameLabel,
                    'width' => 55,
                ),

                array(
                    'id'    => 'lName',
                    'name'  => $lastNameLabel,
                    'width' => 55,
                ),

                array(
                    'id'    => 'email',
                    'name'  => 'Email',
                    'width' => 55,
                ),

                array(
                    'id'    => 'type',
                    'name'  => 'Type',
                    'width' => 55,
                )
            );

            $arrQFields = $oCompanyProspects->getCompanyQnr()->getQuestionnaireFields($defaultQNRId, false, false);
            foreach ($arrQFields as $arrFieldInfo) {
                if ($arrFieldInfo['q_section_id'] == $jobSectionId && $arrFieldInfo['q_field_unique_id'] != 'qf_job_resume') {
                    $arrJobColumns[] = array(
                        'id'    => $arrFieldInfo['q_field_unique_id'],
                        'name'  => strip_tags(str_replace(':', '', empty($arrFieldInfo['q_field_label']) ? $arrFieldInfo['original_q_field_label'] : $arrFieldInfo['q_field_label'])),
                        'width' => 55,
                    );
                }
            }

            $arrProspectsData = array();
            $arrJobsData      = array();
            foreach ($arrResult['rows'] as $key => $r) {
                $basic_prospect_data = $oCompanyProspects->getProspectInfo($r['prospect_id']);
                $prospect_data       = $oCompanyProspects->getProspectDetailedData($r['prospect_id']);

                $prospect_data = array_merge($prospect_data, $basic_prospect_data);

                $prospect_data['qf_create_date'] = array_key_exists('create_date', $prospect_data) ? $prospect_data['create_date'] : '';
                $prospect_data['qf_age']         = array_key_exists('date_of_birth', $prospect_data) ? $prospect_data['date_of_birth'] : '';
                $prospect_data['qf_spouse_age']  = array_key_exists('spouse_date_of_birth', $prospect_data) ? $prospect_data['spouse_date_of_birth'] : '';
                $prospect_data['qf_first_name']  = array_key_exists('fName', $prospect_data) ? $prospect_data['fName'] : '';
                $prospect_data['qf_last_name']   = array_key_exists('lName', $prospect_data) ? $prospect_data['lName'] : '';
                $prospect_data['qf_email']       = array_key_exists('email', $prospect_data) ? $prospect_data['email'] : '';

                foreach ($q_data_labels as $q_key => $q) {
                    $value = '';
                    if (isset($prospect_data[$q_key])) {
                        switch ($q['type']) {
                            case 'age':
                                if (!empty($prospect_data[$q_key])) {
                                    $value = (int)date('Y') - (int)$prospect_data[$q_key];
                                }
                                break;

                            case 'date':
                                $value = Settings::isDateEmpty($prospect_data[$q_key]) ? '' : $prospect_data[$q_key];
                                break;

                            case 'combo':
                            case 'radio':
                                $value = $prospect_data[$q_key] === '0' ? '' : $prospect_data[$q_key];
                                break;

                            default:
                                $value = $prospect_data[$q_key];
                                break;
                        }
                    }

                    $arrProspectsData[$key][$q_key] = $value;
                }

                // Jobs info accumulation
                $arrJobs = $oCompanyProspects->getProspectAssignedJobs($r['prospect_id'], true);
                if (isset($prospect_data['qf_marital_status']) && in_array($prospect_data['qf_marital_status'], array('Married', 'De-Facto/Common Law', 'Engaged'))) {
                    $arrSpouseJobs = $oCompanyProspects->getProspectAssignedJobs($r['prospect_id'], true, 'spouse');
                    $arrJobs       = array_merge($arrJobs, $arrSpouseJobs);
                }

                if (count($arrJobs)) {
                    foreach ($arrJobs as $arrJob) {
                        $arrJobsData[] = array(
                            'fName'                        => $r['fName'],
                            'lName'                        => $r['lName'],
                            'email'                        => $r['email'],
                            'type'                         => $arrJob['prospect_type'] == 'main' ? 'Applicant' : 'Spouse',
                            'qf_job_title'                 => $arrJob['qf_job_title'] != 'Type to search for job title...' ? $arrJob['qf_job_title'] : '',
                            'qf_job_employer'              => $arrJob['qf_job_employer'],
                            'qf_job_text_title'            => $arrJob['qf_job_text_title'],
                            'qf_job_country_of_employment' => $arrJob['qf_job_country_of_employment'],
                            'qf_job_start_date'            => $arrJob['qf_job_start_date'],
                            'qf_job_end_date'              => $arrJob['qf_job_end_date']
                        );
                    }
                }
            }

            return $oCompanyProspects->exportToExcel($arrProspectColumns, $arrProspectsData, null, $arrJobColumns, $arrJobsData);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Export time log data for a specific company
     *
     * @param int $companyId
     * @param array $exportFilter
     * @return Spreadsheet|string
     */
    public function exportTimeLog($companyId, $exportFilter = [])
    {
        try {
            /** @var Clients $oClients */
            $oClients                          = $this->_serviceContainer->get(Clients::class);
            $exportFilter['client_company_id'] = $companyId;

            $sort = '';
            if (isset($exportFilter['sort'])) {
                $sort = $exportFilter['sort'];
                unset($exportFilter['sort']);
            }

            $dir = '';
            if (isset($exportFilter['dir'])) {
                $dir = $exportFilter['dir'];
                unset($exportFilter['dir']);
            }

            // Load time tracker records for all clients of the company (and filter if needed)
            $trackerModel  = new TrackerModel($this->_db2, $this->_settings, $oClients);
            $arrAllRecords = $trackerModel->getList($exportFilter, $sort, $dir, 0, 0, false);

            $arrGroupedRecords = $arrAllRecords['items'];
            if (count($arrGroupedRecords)) {
                $objPHPExcel = new Spreadsheet();

                $objPHPExcel->setActiveSheetIndex(0);
                $sheet = $objPHPExcel->getActiveSheet();

                // Set columns width
                $sheet->getColumnDimension('A')->setWidth(40);
                $sheet->getColumnDimension('B')->setWidth(40);
                $sheet->getColumnDimension('C')->setWidth(40);
                $sheet->getColumnDimension('D')->setWidth(15);
                $sheet->getColumnDimension('E')->setWidth(40);
                $sheet->getColumnDimension('F')->setWidth(12);
                $sheet->getColumnDimension('G')->setWidth(12);
                $sheet->getColumnDimension('H')->setWidth(12);
                $sheet->getColumnDimension('I')->setWidth(12);

                $row = 1;

                // Show current date/time
                $sheet->setCellValueByColumnAndRow(1, $row, 'Date: ' . date('Y-m-d H:i:s'));
                $row += 2;

                // Table Headers
                $arrColumns = array('User Name', 'User Email', 'Client Name', 'Case File Number', 'Date', 'Note', 'Hours', 'Rate/Hour', 'Total', 'Billed');
                $count      = count($arrColumns);
                for ($i = 1; $i < $count + 1; $i++) {
                    $sheet->setCellValueByColumnAndRow($i, $row, $arrColumns[$i - 1]);
                }
                $strRow = $sheet->getCellByColumnAndRow(1, $row)->getCoordinate() . ':' . $sheet->getCellByColumnAndRow(count($arrColumns), $row)->getCoordinate();
                $sheet->getStyle($strRow)->getFont()->setBold(true);
                $row++;

                // Content
                foreach ($arrGroupedRecords as $arrTimeRecordInfo) {
                    $col = 1;
                    $sheet->setCellValueByColumnAndRow($col, $row, $arrTimeRecordInfo['track_posted_by_member_name']);
                    $sheet->setCellValueByColumnAndRow(++$col, $row, $arrTimeRecordInfo['track_posted_by_member_email']);
                    $sheet->setCellValueByColumnAndRow(++$col, $row, $arrTimeRecordInfo['track_client_name']);
                    $sheet->setCellValueByColumnAndRow(++$col, $row, $arrTimeRecordInfo['track_case_file_number']);
                    $sheet->setCellValueByColumnAndRow(++$col, $row, $arrTimeRecordInfo['track_posted_on_date']);
                    $sheet->setCellValueByColumnAndRow(++$col, $row, $arrTimeRecordInfo['track_comment']);
                    $sheet->setCellValueByColumnAndRow(++$col, $row, round($arrTimeRecordInfo['track_time_billed'] / 60, 4));
                    $sheet->setCellValueByColumnAndRow(++$col, $row, $arrTimeRecordInfo['track_rate']);
                    $sheet->setCellValueByColumnAndRow(++$col, $row, $arrTimeRecordInfo['track_total']);
                    $sheet->setCellValueByColumnAndRow(++$col, $row, $arrTimeRecordInfo['track_billed'] == 'Y' ? 'Yes' : 'No');

                    // Use text format for all cells
                    $strRow = $sheet->getCellByColumnAndRow(1, $row)->getCoordinate() . ':' . $sheet->getCellByColumnAndRow(count($arrColumns), $row)->getCoordinate();
                    $sheet->getStyle($strRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

                    // We want wrap text in the comments column
                    $sheet->getStyle('E' . $row)->getAlignment()->setWrapText(true);

                    $row++;
                }

                return $objPHPExcel;
            } else {
                return $this->_tr->translate('There are no records to show.');
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            return $this->_tr->translate('Internal error.');
        }
    }

    /**
     * Export client balances for specific company
     *
     * @param int $companyId
     * @param string $fileName
     * @param string $title
     * @return Spreadsheet|string
     */
    public function exportClientBalances($companyId, $fileName, $title)
    {
        try {
            /** @var Clients $clients */
            $clients = $this->_serviceContainer->get(Clients::class);
            return $clients->getAccounting()->generateClientBalancesReport('excel', $fileName, $title, false, false, $companyId);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            return $this->_tr->translate('Internal error.');
        }
    }

    /**
     * Export client transactions for specific company
     *
     * @param int $companyId
     * @param string $fileName
     * @param string $title
     * @return Spreadsheet|string
     */
    public function exportClientTransactions($companyId, $fileName, $title)
    {
        try {
            /** @var Clients $clients */
            $clients = $this->_serviceContainer->get(Clients::class);
            return $clients->getAccounting()->generateClientTransactionsReport('excel', $fileName, $title, 'transaction-all', '', false, false, $companyId);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            return $this->_tr->translate('Internal error.');
        }
    }

    /**
     * Export trust account data for specific company
     *
     * @param int $companyId
     * @param int $exportTaId
     * @param string $exportFilter
     * @param string $firstParam
     * @param string $secondParam
     * @return Spreadsheet|string
     */
    public function exportTrustAccount($companyId, $exportTaId = null, $exportFilter = null, $firstParam = null, $secondParam = null)
    {
        try {
            /** @var Clients $clients */
            $clients = $this->_serviceContainer->get(Clients::class);

            if (empty($exportTaId)) {
                $arrCompanyTA = $clients->getAccounting()->getCompanyTA($companyId);
            } else {
                $arrCompanyTA = array($clients->getAccounting()->getCompanyTAbyId($exportTaId));
            }

            $arrCompanyTAGrouped = array();
            $arrGroupedRecords   = array();
            $paymentMadeBy       = $clients->getAccounting()->getTrustAccount()->getPaymentMadeByOptions();
            foreach ($arrCompanyTA as $arrCompanyTAInfo) {
                $arrCompanyTAGrouped[$arrCompanyTAInfo['company_ta_id']] = $arrCompanyTAInfo;

                $arrParams = array(
                    'sort' => 'date_from_bank',
                    'dir'  => 'ASC'
                );

                if (!empty($exportFilter)) {
                    $arrParams['filter'] = $exportFilter;

                    switch ($exportFilter) {
                        case 'client_name':
                            $arrParams['client_name'] = $firstParam;
                            break;

                        case 'client_code':
                            $arrParams['client_code'] = $firstParam;
                            break;

                        case 'period':
                            $timestamp               = strtotime($firstParam);
                            $firstParam              = date('d/m/Y', $timestamp);
                            $arrParams['start_date'] = $firstParam;

                            $timestamp             = strtotime($secondParam);
                            $secondParam           = date('d/m/Y', $timestamp);
                            $arrParams['end_date'] = $secondParam;
                            break;

                        case 'unassigned':
                            $timestamp             = strtotime($firstParam);
                            $firstParam            = date('d/m/Y', $timestamp);
                            $arrParams['end_date'] = $firstParam;
                            break;
                    }
                }

                $arrTaRecords = $clients->getAccounting()->getTrustAccount()->getTransactionsGrid(
                    $arrCompanyTAInfo['company_ta_id'],
                    $arrParams,
                    true,
                    '',
                    true
                );

                $arrGroupedRecords[$arrCompanyTAInfo['company_ta_id']] = $arrTaRecords['rows'];
            }

            $taLabel = $this->_parent->getCurrentCompanyDefaultLabel('trust_account');
            if (count($arrGroupedRecords)) {
                $objPHPExcel = new Spreadsheet();

                $currentSheetNumber = 0;
                foreach ($arrGroupedRecords as $taId => $arrTaRecords) {
                    // Each T/A will have an own sheet!
                    // This sounds amazing!!!
                    if ($currentSheetNumber) {
                        $sheet = $objPHPExcel->createSheet($currentSheetNumber);
                        $objPHPExcel->setActiveSheetIndex($currentSheetNumber++);
                    } else {
                        $objPHPExcel->setActiveSheetIndex($currentSheetNumber++);
                        $sheet = $objPHPExcel->getActiveSheet();
                    }

                    // Rename sheet
                    $worksheetName = Files::checkPhpExcelFileName($arrCompanyTAGrouped[$taId]['name']);
                    $sheet->setTitle($worksheetName);


                    // Set columns width
                    $sheet->getColumnDimension('A')->setWidth(15);
                    $sheet->getColumnDimension('B')->setWidth(40);
                    $sheet->getColumnDimension('C')->setWidth(15);
                    $sheet->getColumnDimension('D')->setWidth(15);
                    $sheet->getColumnDimension('E')->setWidth(15);
                    $sheet->getColumnDimension('F')->setWidth(25);
                    $sheet->getColumnDimension('G')->setWidth(70);
                    $sheet->getColumnDimension('H')->setWidth(20);


                    $row = 1;

                    // Table Headers
                    $arrColumns = array('Date', 'Description', 'Payment method', 'Deposit', 'Withdrawal', 'Allocation Amount', 'Assigned to', 'Receipt Number');

                    $count = count($arrColumns);
                    for ($i = 1; $i < $count + 1; $i++) {
                        $sheet->setCellValueByColumnAndRow($i, $row, $arrColumns[$i - 1]);
                    }
                    $strRow = $sheet->getCellByColumnAndRow(1, $row)->getCoordinate() . ':' . $sheet->getCellByColumnAndRow(count($arrColumns), $row)->getCoordinate();
                    $sheet->getStyle($strRow)->getFont()->setBold(true);
                    $row++;

                    $currency = $arrCompanyTAGrouped[$taId]['currency'];
                    foreach ($arrTaRecords as $arrTaRecordInfo) {
                        // For starting balance we need to show Zero
                        $booShowZero = $arrTaRecordInfo['purpose'] == $clients->getAccounting()->startBalanceTransactionId;

                        $col = 1;
                        $sheet->setCellValueByColumnAndRow($col, $row, $arrTaRecordInfo['date_from_bank']);
                        $sheet->setCellValueByColumnAndRow(++$col, $row, $this->filterTags($arrTaRecordInfo['description']));
                        $sheet->setCellValueByColumnAndRow(++$col, $row, $this->filterTags(array_key_exists($arrTaRecordInfo['payment_method'], $paymentMadeBy['arrMapper']) ? $paymentMadeBy['arrMapper'][$arrTaRecordInfo['payment_method']] : $arrTaRecordInfo['payment_method']));
                        $sheet->setCellValueByColumnAndRow(++$col, $row, ((float)$arrTaRecordInfo['deposit'] == 0) && !$booShowZero ? '' : $arrTaRecordInfo['deposit']);
                        $sheet->setCellValueByColumnAndRow(++$col, $row, (float)$arrTaRecordInfo['withdrawal'] == 0 ? '' : $arrTaRecordInfo['withdrawal']);
                        $sheet->setCellValueByColumnAndRow(++$col, $row, str_replace(',', ' | ', $arrTaRecordInfo['allocation_amount']));
                        $sheet->setCellValueByColumnAndRow(++$col, $row, $this->filterTags($arrTaRecordInfo['client_name']));
                        $sheet->getCellByColumnAndRow(++$col, $row)->setValueExplicit($arrTaRecordInfo['receipt_number'], DataType::TYPE_STRING);

                        // Use text format for all cells
                        $strRow = $sheet->getCellByColumnAndRow(1, $row)->getCoordinate() . ':' . $sheet->getCellByColumnAndRow(count($arrColumns), $row)->getCoordinate();
                        $sheet->getStyle($strRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

                        // We want wrap text in the description column
                        $sheet->getStyle('B' . $row)->getAlignment()->setWrapText(true);

                        $sheet->getStyle("F" . $row)->getAlignment()->setWrapText(true);
                        $sheet->getStyle("G" . $row)->getAlignment()->setWrapText(true);
                        $sheet->getStyle("H" . $row)->getAlignment()->setWrapText(true);
                        // For amount columns we need to use correct format
                        switch ($currency) {
                            case 'usd':
                            case 'cad':
                                $currencyFormat = '"$"#,##0.00_-';
                                break;

                            case 'euro':
                                $currencyFormat = '"€"#,##0.00_-';
                                break;

                            default:
                                $currencyFormat = '#,##0.00_-';
                                break;
                        }
                        $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
                        $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode($currencyFormat);

                        $row++;
                    }

                    if (!count($arrTaRecords)) {
                        $sheet->setCellValueByColumnAndRow(0, $row, $this->_tr->translate('There are no records in this ' . $taLabel . '.'));
                    }
                }

                // Set first sheet as active
                $objPHPExcel->setActiveSheetIndex(0);

                return $objPHPExcel;
            } else {
                return $this->_tr->translate('There are no ' . $taLabel . 's for this company.');
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            return $this->_tr->translate('Internal error.');
        }
    }

    /**
     * Export notes for specific company
     *
     * @param int $companyId
     * @return Spreadsheet|string
     */
    public function exportNotes($companyId)
    {
        try {
            $objPHPExcel = new Spreadsheet();

            $objPHPExcel->setActiveSheetIndex(0);
            $sheet = $objPHPExcel->getActiveSheet();

            // Set columns width
            $sheet->getColumnDimension('A')->setWidth(30);
            $sheet->getColumnDimension('B')->setWidth(30);
            $sheet->getColumnDimension('C')->setWidth(40);
            $sheet->getColumnDimension('D')->setWidth(20);
            $sheet->getColumnDimension('E')->setWidth(15);
            $sheet->getColumnDimension('F')->setWidth(15);


            $row = 1;
            $sheet->setCellValueByColumnAndRow(1, $row, 'Date:');
            $sheet->setCellValueByColumnAndRow(2, $row, date('Y-m-d H:i:s'));
            $row++;


            // Table Headers
            $arrColumns = array('Case', 'Author', 'Note', 'Creation date', 'Visible to case', 'RTL');
            $count      = count($arrColumns);
            for ($i = 1; $i < $count + 1; $i++) {
                $sheet->setCellValueByColumnAndRow($i, $row, $arrColumns[$i - 1]);
            }
            $strRow = $sheet->getCellByColumnAndRow(1, $row)->getCoordinate() . ':' . $sheet->getCellByColumnAndRow(count($arrColumns), $row)->getCoordinate();
            $sheet->getStyle($strRow)->getFont()->setBold(true);
            $row++;


            // Content
            $arrCompanyMembersIds = $this->_parent->getCompanyMembersIds($companyId);

            $arrNotes   = array();
            $arrParents = array();
            /** @var Clients $oClients */
            $oClients = $this->_serviceContainer->get(Clients::class);
            if (count($arrCompanyMembersIds)) {
                /** @var Notes $oNotes */
                $oNotes   = $this->_serviceContainer->get(Notes::class);
                $arrNotes = $oNotes->getMembersNotes($arrCompanyMembersIds);

                $arrParents = $oClients->getParentsForAssignedApplicants($arrCompanyMembersIds);
            }

            $caseTypeId = $oClients->getMemberTypeIdByName('case');
            foreach ($arrNotes as $arrNoteInfo) {
                // Don't export cases' records without parents
                if ($arrNoteInfo['userType'] == $caseTypeId && !array_key_exists($arrNoteInfo['member_id'], $arrParents)) {
                    continue;
                }

                $arrCaseInfo = array(
                    'lName'      => $arrNoteInfo['clientLastName'],
                    'fName'      => $arrNoteInfo['clientFirstName'],
                    'fileNumber' => $arrNoteInfo['fileNumber'],
                );
                $arrCaseInfo = $oClients->generateClientName($arrCaseInfo);

                if (array_key_exists($arrNoteInfo['member_id'], $arrParents)) {
                    $arrParentInfo = $oClients->generateClientName($arrParents[$arrNoteInfo['member_id']]);

                    $arrCaseInfo['full_name_with_file_num'] = $arrParentInfo['full_name'] . ' | ' . $arrCaseInfo['full_name_with_file_num'];
                }

                $sheet->setCellValueByColumnAndRow(1, $row, $arrCaseInfo['full_name_with_file_num']);
                $sheet->setCellValueByColumnAndRow(2, $row, $arrNoteInfo['lName'] . ' ' . $arrNoteInfo['fName']);
                $sheet->setCellValueByColumnAndRow(3, $row, $this->filterTags($arrNoteInfo['note']));
                $sheet->setCellValueByColumnAndRow(4, $row, $arrNoteInfo['create_date']);
                $sheet->setCellValueByColumnAndRow(5, $row, $arrNoteInfo['visible_to_clients'] == 'Y' ? 'Yes' : 'No');
                $sheet->setCellValueByColumnAndRow(6, $row, $arrNoteInfo['rtl'] == 'Y' ? 'Yes' : 'No');

                // Use text format for all cells
                $strRow = $sheet->getCellByColumnAndRow(1, $row)->getCoordinate() . ':' . $sheet->getCellByColumnAndRow(count($arrColumns), $row)->getCoordinate();
                $sheet->getStyle($strRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

                $row++;
            }

            return $objPHPExcel;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            return $this->_tr->translate('Internal error.');
        }
    }

    /**
     * Export Prospects notes for a specific company
     *
     * @param int $companyId
     * @return Spreadsheet|string
     */
    public function exportProspectsNotes($companyId)
    {
        try {
            $objPHPExcel = new Spreadsheet();

            $objPHPExcel->setActiveSheetIndex(0);
            $sheet = $objPHPExcel->getActiveSheet();

            // Set columns width
            $sheet->getColumnDimension('A')->setWidth(30);
            $sheet->getColumnDimension('B')->setWidth(30);
            $sheet->getColumnDimension('C')->setWidth(40);
            $sheet->getColumnDimension('D')->setWidth(25);


            $row = 1;
            $sheet->setCellValueByColumnAndRow(1, $row, 'Date:');
            $sheet->setCellValueByColumnAndRow(2, $row, date('Y-m-d H:i:s'));
            $row++;


            // Table Headers
            $arrColumns = array('Prospect', 'Author', 'Note', 'Creation date');
            $count      = count($arrColumns);
            for ($i = 1; $i < $count + 1; $i++) {
                $sheet->setCellValueByColumnAndRow($i, $row, $arrColumns[$i - 1]);
            }
            $strRow = $sheet->getCellByColumnAndRow(1, $row)->getCoordinate() . ':' . $sheet->getCellByColumnAndRow(count($arrColumns), $row)->getCoordinate();
            $sheet->getStyle($strRow)->getFont()->setBold(true);
            $row++;


            // Content
            /** @var CompanyProspects $oProspects * */
            $oProspects = $this->_serviceContainer->get(CompanyProspects::class);
            $arrNotes   = $oProspects->getAllCompanyProspectsNotes($companyId);

            foreach ($arrNotes as $arrNoteInfo) {
                $sheet->setCellValueByColumnAndRow(1, $row, $arrNoteInfo['prospect']);
                $sheet->setCellValueByColumnAndRow(2, $row, $arrNoteInfo['author']);
                $sheet->setCellValueByColumnAndRow(3, $row, $this->filterTags($arrNoteInfo['note']));
                $sheet->setCellValueByColumnAndRow(4, $row, $arrNoteInfo['date']);

                // Use text format for all cells
                $strRow = $sheet->getCellByColumnAndRow(1, $row)->getCoordinate() . ':' . $sheet->getCellByColumnAndRow(count($arrColumns), $row)->getCoordinate();
                $sheet->getStyle($strRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

                $row++;
            }

            return $objPHPExcel;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            return $this->_tr->translate('Internal error.');
        }
    }


    /**
     * Export tasks for specific company
     *
     * @param int $companyId
     * @return Spreadsheet|string
     */
    public function exportTasks($companyId)
    {
        try {
            $objPHPExcel = new Spreadsheet();

            $objPHPExcel->setActiveSheetIndex(0);
            $sheet = $objPHPExcel->getActiveSheet();

            // Set columns width
            $sheet->getColumnDimension('A')->setWidth(30);
            $sheet->getColumnDimension('B')->setWidth(30);
            $sheet->getColumnDimension('C')->setWidth(40);
            $sheet->getColumnDimension('D')->setWidth(40);
            $sheet->getColumnDimension('E')->setWidth(20);
            $sheet->getColumnDimension('F')->setWidth(15);
            $sheet->getColumnDimension('G')->setWidth(15);
            $sheet->getColumnDimension('H')->setWidth(20);


            $row = 1;
            $sheet->setCellValueByColumnAndRow(1, $row, 'Date:');
            $sheet->setCellValueByColumnAndRow(2, $row, date('Y-m-d H:i:s'));
            $row++;


            // Table Headers
            $arrColumns = array('Author', 'Task', 'Assigned to', 'Assigned CC', 'Creation date', 'Due on date', 'Notify case', 'Complete', 'Case/Prospect');

            $count = count($arrColumns);
            for ($i = 1; $i < $count + 1; $i++) {
                $sheet->setCellValueByColumnAndRow($i, $row, $arrColumns[$i - 1]);
            }
            $strRow = $sheet->getCellByColumnAndRow(1, $row)->getCoordinate() . ':' . $sheet->getCellByColumnAndRow(count($arrColumns), $row)->getCoordinate();
            $sheet->getStyle($strRow)->getFont()->setBold(true);
            $row++;


            // Content
            /** @var Members $this */
            $oMembers = $this->_serviceContainer->get(Members::class);
            /** @var Tasks $tasks */
            $tasks = $this->_serviceContainer->get(Tasks::class);

            $arrTasks = $tasks->getCompanyTasks($companyId);
            if (count($arrTasks)) {
                foreach ($arrTasks as $arrTaskInfo) {
                    $strAssignedTo = $oMembers->getCommaSeparatedMemberNames(array_filter(explode(';', $arrTaskInfo['to_ids'] ?? '')));

                    $strAssignedCC = '';
                    if (count(array_filter(explode(';', $arrTaskInfo['cc_ids'] ?? '')))) {
                        $strAssignedCC = $oMembers->getCommaSeparatedMemberNames(array_filter(explode(';', $arrTaskInfo['cc_ids'] ?? '')));
                    }

                    $sheet->setCellValueByColumnAndRow(1, $row, $arrTaskInfo['author_name']);
                    $sheet->setCellValueByColumnAndRow(2, $row, $arrTaskInfo['task']);
                    $sheet->setCellValueByColumnAndRow(3, $row, $strAssignedTo);
                    $sheet->setCellValueByColumnAndRow(4, $row, $strAssignedCC);
                    $sheet->setCellValueByColumnAndRow(5, $row, $arrTaskInfo['create_date']);
                    $sheet->setCellValueByColumnAndRow(6, $row, $arrTaskInfo['due_on']);
                    $sheet->setCellValueByColumnAndRow(7, $row, $arrTaskInfo['notify_client'] == 'Y' ? 'Yes' : 'No');
                    $sheet->setCellValueByColumnAndRow(8, $row, $arrTaskInfo['completed'] == 'Y' ? 'Yes' : 'No');
                    $sheet->setCellValueByColumnAndRow(9, $row, trim($arrTaskInfo['assigned_to_name'] ?? ''));

                    // Use text format for all cells
                    $strRow = $sheet->getCellByColumnAndRow(1, $row)->getCoordinate() . ':' . $sheet->getCellByColumnAndRow(count($arrColumns) - 1, $row)->getCoordinate();
                    $sheet->getStyle($strRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

                    $row++;
                }
            }

            return $objPHPExcel;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            return $this->_tr->translate('Internal error.');
        }
    }

    /**
     * Generate zip file for each company that will include such info:
     *  - generate html with main company info and users list
     *  - get each company's invoice (download to server if in cloud)
     *
     * @param array $arrCompaniesInfo
     * @return array
     */
    public function exportCompaniesMainInfo($arrCompaniesInfo)
    {
        $strError        = '';
        $tempZipFilePath = '';

        try {
            $logFileName = 'companies-export-' . date('Y_m_d H_i_s') . '.log';
            $this->_log->debugToFile('Start', 0, 2, $logFileName);
            $this->_log->debugToFile('To process: ' . count($arrCompaniesInfo), 1, 2, $logFileName);

            /** @var Files $oFiles */
            $oFiles = $this->_serviceContainer->get(Files::class);

            $root = getcwd() . '/' . $this->_config['directory']['tmp'] . '/export';

            // Make sure that a temp folder exists
            if (!$oFiles->createFTPDirectory($root)) {
                $strError = sprintf(
                    $this->_tr->translate('Export folder %s was not created. Insufficient access rights?'),
                    $root
                );
            }

            if (empty($strError)) {
                // Make sure that path is correct for both linux/windows
                $root = realpath($root);

                $oCompany = $this->_parent;

                /** @var Members $oMembers */
                $oMembers = $this->_serviceContainer->get(Members::class);

                /** @var Country $oCountry */
                $oCountry = $this->_serviceContainer->get(Country::class);

                $oCloud           = $oFiles->getCloud();
                $oCompanyInvoices = $oCompany->getCompanyInvoice();
                $oSubscriptions   = $oCompany->getCompanySubscriptions();
                $oPackages        = $oCompany->getPackages();

                // Note: if we want to don't try to refresh company's info (if already processed) - set to true
                $booSkipIfAlreadyCreated = false;

                $arrAllCompaniesHtml     = array();
                $arrFoldersAndFilesToZip = array();
                foreach ($arrCompaniesInfo as $companyId => $arrCompanyInfo) {
                    $this->_log->debugToFile('Processing Company ' . $companyId, 1, 2, $logFileName);

                    // Place company-related files to this folder
                    $companyPath         = $root . '/' . $companyId . ' - ' . str_replace('.', '_', FileTools::cleanupFileName($arrCompanyInfo['companyName']));
                    $companyInfoHtmlPath = $companyPath . '/' . 'company_info.html';

                    if (!$oFiles->createFTPDirectory($companyPath)) {
                        $strError = sprintf(
                            $this->_tr->translate('Export company folder was not created: %s. Insufficient access rights?'),
                            $companyPath
                        );
                        break;
                    }

                    if ($booSkipIfAlreadyCreated && file_exists($companyPath)) {
                        $arrFoldersAndFilesToZip[] = realpath($companyPath);

                        $this->_log->debugToFile('Skipped Company ' . $companyId, 1, 2, $logFileName);

                        if (file_exists($companyInfoHtmlPath)) {
                            $html = file_get_contents($companyInfoHtmlPath);
                            if (preg_match("/<body[^>]*>(.*?)<\/body>/is", $html, $matches)) {
                                $arrAllCompaniesHtml[] = $matches[1];
                            }
                        }

                        continue;
                    }

                    $arrFoldersAndFilesToZip[] = realpath($companyPath);

                    // Prepare company info
                    $htmlCompanyInfo = '<h1>Company Info</h1>';
                    $htmlCompanyInfo .= '<table>';
                    $htmlCompanyInfo .= '<tr><td>Name:</td><td>' . $arrCompanyInfo['companyName'] . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>City:</td><td>' . $arrCompanyInfo['city'] . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>State:</td><td>' . $arrCompanyInfo['state'] . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>Country:</td><td>' . $oCountry->getCountryName($arrCompanyInfo['country']) . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>Email:</td><td>' . $arrCompanyInfo['companyEmail'] . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>Phone1:</td><td>' . $arrCompanyInfo['phone1'] . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>Phone2:</td><td>' . $arrCompanyInfo['phone2'] . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>Contact:</td><td>' . $arrCompanyInfo['contact'] . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>Fax:</td><td>' . $arrCompanyInfo['fax'] . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>Zip:</td><td>' . $arrCompanyInfo['zip'] . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>Address:</td><td>' . nl2br($arrCompanyInfo['address'] ?? '') . '</td></tr>';

                    $htmlCompanyInfo .= '<tr><td>Subscription:</td><td>' . $oPackages->getSubscriptionNameById($arrCompanyInfo['subscription']) . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>Trial:</td><td>' . $arrCompanyInfo['trial'] . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>Account created on:</td><td>' . (Settings::isDateEmpty($arrCompanyInfo['account_created_on']) ? '-' : $this->_settings->formatDate($arrCompanyInfo['account_created_on'])) . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>Company setup on:</td><td>' . (Settings::isDateEmpty($arrCompanyInfo['regTime']) ? '-' : $this->_settings->formatDate($arrCompanyInfo['regTime'])) . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>Next billing date:</td><td>' . (Settings::isDateEmpty($arrCompanyInfo['next_billing_date']) ? '-' : $this->_settings->formatDate($arrCompanyInfo['next_billing_date'])) . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>Billing frequency:</td><td>' . $oSubscriptions->getPaymentTermNameById($arrCompanyInfo['payment_term']) . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>Free users included:</td><td>' . $arrCompanyInfo['free_users'] . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>Free clients included:</td><td>' . $arrCompanyInfo['free_clients'] . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>Free storage included (in GB):</td><td>' . $arrCompanyInfo['free_storage'] . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td># of Active Users:</td><td>' . $oCompany->calculateActiveUsers($companyId) . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>Storage Used (GB):</td><td>' . (max(number_format($arrCompanyInfo['storage_today'] / 1024 / 1024, 2), 0.01)) . '</td></tr>';

                    $htmlCompanyInfo .= '<tr><td>Subscription Recurring Fee:</td><td>' . $arrCompanyInfo['subscription_fee'] . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>Paymentech Profile ID:</td><td>' . $arrCompanyInfo['paymentech_profile_id'] . '</td></tr>';
                    $htmlCompanyInfo .= '<tr><td>Internal Note:</td><td>' . $arrCompanyInfo['internal_note'] . '</td></tr>';
                    $htmlCompanyInfo .= '</table>';


                    $arrCompanyOffices = $oCompany->getDivisions($companyId, 0);

                    // The list of users/admins
                    $arrCompanyUsers = $oCompany->getCompanyMembersWithRoles($companyId, 'admin_and_staff');

                    $arrCompanyUsersGrouped = array();
                    foreach ($arrCompanyUsers as $arrCompanyUserInfo) {
                        if (!isset($arrCompanyUsersGrouped[$arrCompanyUserInfo['member_id']])) {
                            if ($arrCompanyUserInfo['userType'] == 2) {
                                // This is admin, has access to all offices
                                $arrOffices = $arrCompanyOffices;
                            } else {
                                $arrOffices = $oMembers->getMemberDivisionsInfo($arrCompanyUserInfo['member_id']);
                            }

                            $arrOfficesNames = array();
                            foreach ($arrOffices as $arrOfficeInfo) {
                                $arrOfficesNames[] = $arrOfficeInfo['name'];
                            }
                            sort($arrOfficesNames);

                            switch ($arrCompanyUserInfo['status']) {
                                case 1:
                                    $status = 'Active';
                                    break;

                                case 2:
                                    $status = 'Suspended';
                                    break;

                                default:
                                    $status = 'Inactive';
                                    break;
                            }

                            $arrCompanyUsersGrouped[$arrCompanyUserInfo['member_id']] = array(
                                'fName'        => $arrCompanyUserInfo['fName'],
                                'lName'        => $arrCompanyUserInfo['lName'],
                                'emailAddress' => $arrCompanyUserInfo['emailAddress'],
                                'username'     => $arrCompanyUserInfo['username'],
                                'status'       => $status,
                                'arrRoles'     => [$arrCompanyUserInfo['role_name']],
                                'arrOffices'   => $arrOfficesNames,
                            );
                        } else {
                            $arrUserRoles   = $arrCompanyUsersGrouped[$arrCompanyUserInfo['member_id']]['arrRoles'];
                            $arrUserRoles[] = $arrCompanyUserInfo['role_name'];
                            sort($arrUserRoles);

                            $arrCompanyUsersGrouped[$arrCompanyUserInfo['member_id']]['arrRoles'] = $arrUserRoles;
                        }
                    }

                    // Add users list to this file
                    $htmlCompanyInfo .= '<h1>Users</h1>';
                    $htmlCompanyInfo .= '<table>';
                    $htmlCompanyInfo .= '<tr>' .
                        '<th>Last Name</th>' .
                        '<th>First Name</th>' .
                        '<th>Email</th>' .
                        '<th>Username</th>' .
                        '<th>Status</th>' .
                        '<th>Roles</th>' .
                        '<th>Offices</th>' .
                        '</tr>';

                    foreach ($arrCompanyUsersGrouped as $arrCompanyUserInfo) {
                        $htmlCompanyInfo .= '<tr>' .
                            '<td>' . $arrCompanyUserInfo['lName'] . '</td>' .
                            '<td>' . $arrCompanyUserInfo['fName'] . '</td>' .
                            '<td>' . $arrCompanyUserInfo['emailAddress'] . '</td>' .
                            '<td>' . $arrCompanyUserInfo['username'] . '</td>' .
                            '<td>' . $arrCompanyUserInfo['status'] . '</td>' .
                            '<td>' . implode(', ', $arrCompanyUserInfo['arrRoles']) . '</td>' .
                            '<td>' . implode(', ', $arrCompanyUserInfo['arrOffices']) . '</td>' .
                            '</tr>';
                    }

                    $htmlCompanyInfo .= '</table>';

                    $arrAllCompaniesHtml[] = $htmlCompanyInfo;


                    $htmlCompanyInfo = '<!DOCTYPE html>
                    <html lang="en">
                      <head>
                        <meta charset="utf-8">
                        <title>Export Company info</title>
                        <style>
                            th {
                                text-align: left;
                            }                    
                            td {
                                padding: 5px;
                            }
                        </style>
                      </head>
                      <body>' . $htmlCompanyInfo . '</body>
                    </html>';

                    if (file_exists($companyInfoHtmlPath)) {
                        unlink($companyInfoHtmlPath);
                    }
                    file_put_contents($companyInfoHtmlPath, $htmlCompanyInfo);

                    // Now download/copy invoices
                    $booLocal    = $oCompany->isCompanyStorageLocationLocal($companyId);
                    $arrInvoices = $oCompanyInvoices->getCompanyInvoices($companyId);
                    foreach ($arrInvoices as $arrInvoiceInfo) {
                        $invoicePathForZip = $companyPath . '/' . 'Invoice #' . $arrInvoiceInfo['invoice_number'] . '.pdf';

                        if (!file_exists($invoicePathForZip)) {
                            $filePath      = $oCompany->getPathToInvoices($arrInvoiceInfo['company_id'], $booLocal) . '/' . 'Invoice #' . $arrInvoiceInfo['invoice_number'] . '.pdf';
                            $booFileExists = $booLocal ? is_file($filePath) : $oCloud->checkObjectExists($filePath);
                            if (!$booFileExists) {
                                // Try to create the invoice
                                $result = $oCompany->showInvoicePdf($companyId, $arrInvoiceInfo['company_invoice_id']);
                                if (!($result instanceof FileInfo)) {
                                    $strError = sprintf(
                                        $this->_tr->translate('Company Invoice was not created: %s. Insufficient access rights?'),
                                        $filePath
                                    );
                                    break 2;
                                }
                            }

                            if ($booLocal) {
                                copy($filePath, $invoicePathForZip);
                            } else {
                                $tmpInvoicePath = $oCloud->downloadFileContent($filePath);
                                if (empty($tmpInvoicePath)) {
                                    $strError = sprintf(
                                        $this->_tr->translate('Company Invoice was not downloaded: %s.'),
                                        $filePath
                                    );
                                    break 2;
                                }

                                // Try 2 times if failed
                                if (!rename($tmpInvoicePath, $invoicePathForZip)) {
                                    sleep(1);
                                    if (!rename($tmpInvoicePath, $invoicePathForZip)) {
                                        $strError = sprintf(
                                            $this->_tr->translate('Downloaded Invoice was not copied from %s to %s'),
                                            $tmpInvoicePath,
                                            $invoicePathForZip
                                        );
                                        break 2;
                                    }
                                }
                            }

                            if (!is_file($invoicePathForZip)) {
                                $strError = sprintf(
                                    $this->_tr->translate('Company Invoice was not copied: %s. Insufficient access rights?'),
                                    $filePath
                                );
                                break 2;
                            }
                        }
                    }

                    $this->_log->debugToFile('Processed Company ' . $companyId, 1, 2, $logFileName);
                }

                if (empty($strError)) {
                    $this->_log->debugToFile('Zip creation started', 1, 2, $logFileName);

                    if (count($arrCompaniesInfo) > 1) {
                        $allCompaniesHtmlFile = $root . '/' . 'all_companies ' . date('Y-m-d H-i-s') . '.html';
                        if (file_exists($allCompaniesHtmlFile)) {
                            unlink($allCompaniesHtmlFile);
                        }

                        file_put_contents($allCompaniesHtmlFile, implode('<hr>', $arrAllCompaniesHtml));

                        $arrFoldersAndFilesToZip[] = realpath($allCompaniesHtmlFile);
                    }

                    // Now zip all folders and files in the temp folder
                    $tempZipFilePath = tempnam($this->_config['directory']['tmp'], 'zip');

                    if (!$oFiles->zipDirectory($root, $arrFoldersAndFilesToZip, $tempZipFilePath)) {
                        $tempZipFilePath = '';
                        $strError        = $this->_tr->translate('Internal error. Please try again later');
                    }

                    $this->_log->debugToFile('Zip creation done', 1, 2, $logFileName);
                }
            }

            $this->_log->debugToFile(empty($strError) ? 'DONE' : $strError, 1, 2, $logFileName);
        } catch (Exception $e) {
            $tempZipFilePath = '';
            $strError        = $this->_tr->translate('Internal error. Please try again later');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return [$strError, $tempZipFilePath];
    }

    /**
     * Filter specific chars, which can break the Excel file generation
     *
     * @param $string
     * @return string
     */
    private function filterTags($string)
    {
        return preg_replace('/<(.*?)>/i', '', $string);
    }
}
