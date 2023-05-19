<?php

namespace Superadmin\Controller;

use Clients\Service\Clients;
use Exception;
use Files\Service\Files;
use Forms\Service\Pdf;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Common\Service\Settings;
use Officio\Service\SystemTriggers;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

/**
 * Clients importing controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class ImportBcpnpController extends BaseController
{

    /** @var Files */
    protected $_files;

    /** @var StripTags */
    private $_filter;

    /** @var Clients */
    protected $_clients;

    /** @var Company */
    protected $_company;

    /** @var Pdf */
    protected $_pdf;

    /** @var SystemTriggers */
    protected $_triggers;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_clients = $services[Clients::class];
        $this->_files = $services[Files::class];
        $this->_filter  = new StripTags();
        $this->_pdf = $services[Pdf::class];
        $this->_triggers = $services[SystemTriggers::class];
    }

    protected function getPreparedFieldsFromDb($companyId)
    {
        $arrGroupedFields = array();

        $arrClientTypesToLoad = array('employer', 'individual');

        foreach ($arrClientTypesToLoad as $clientTypeToLoad) {
            // Load client's fields list (based on type)
            $clientTypeId           = $this->_clients->getMemberTypeIdByName($clientTypeToLoad);
            $arrCompanyClientFields = $this->_clients->getApplicantFields()->getCompanyFields($companyId, $clientTypeId);
            $prefix = $clientTypeToLoad == 'employer' ? 'EM' : 'IA';
            foreach ($arrCompanyClientFields as $field) {
                // Skip fields from repeatable groups
                if ($field['repeatable'] != 'Y') {
                    $arrGroupedFields[$clientTypeToLoad . '_field_' . $field['applicant_field_id']] = $prefix . ': ' . $field['applicant_field_unique_id'];
                }
            }
        }

        $arrCaseTemplates = $this->_clients->getCaseTemplates()->getTemplates($companyId, false, null, true);

        foreach ($arrCaseTemplates as $arrCaseTemplateInfo) {
            // Load case's fields list (based on case template)

            $arrCaseFieldsGrouped = $this->_clients->getFields()->getGroupedCompanyFields($arrCaseTemplateInfo['case_template_id']);
            foreach ($arrCaseFieldsGrouped as $arrCaseFieldsGroup) {
                foreach ($arrCaseFieldsGroup['fields'] as $arrFieldInfo) {
                    $arrGroupedFields['case_field_' . $arrFieldInfo['field_id']] = 'CASE: ' . $arrFieldInfo['field_unique_id'];
                }
            }
        }

        return $arrGroupedFields;
    }

    /**
     * @param string $importFilePath
     * @return array[]
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    protected function getFieldsFromXLS($importFilePath)
    {
        $tempFileLocation = $this->_auth->isCurrentUserCompanyStorageLocal()
            ? $importFilePath
            : $this->_files->getCloud()->downloadFileContent($importFilePath);
        if (empty($tempFileLocation)) {
            return array();
        }

        // Get inputFileType (most of the time Excel5)
        $inputFileType = IOFactory::identify($tempFileLocation);

        // Initialize object reader by file type
        /** @var Xls|Xlsx $objReader */
        $objReader = IOFactory::createReader($inputFileType);

        // Read only data (without formatting) for memory and time performance
        $objReader->setReadDataOnly(true);

        if (!$objReader->canRead($tempFileLocation)) {
            return array();
        }

        // Load file into PHPExcel object
        $objPHPExcel = $objReader->load($tempFileLocation);
        $objPHPExcel->setActiveSheetIndex(0);
        $objSheet = $objPHPExcel->getActiveSheet();

        // Convert data to old format
        $arrSheetData = $objSheet->toArray(null, false, false);
        $arrSheetDataConverted = array();
        foreach ($arrSheetData as $key => $arrData) {
            $arrSheetDataConverted[$key + 1] = array_combine(range(1, count($arrData)), array_values($arrData));
        }

        // Prepare data as it is returned in old format (Spreadsheet_Excel_Reader)
        $arrSheets = array(
            array(
                'numCols' => Coordinate::columnIndexFromString($objSheet->getHighestColumn()),
                'numRows' => $objSheet->getHighestRow(),
                'cells' => $arrSheetDataConverted
            )
        );

        // First sheet is used for client's info
        $sheet = $arrSheets[0];

        // Load headers
        $arrRowHeaders = array();
        $colIndex = 1;
        while ($colIndex <= $sheet['numCols']) {
            $arrRow = $sheet['cells'][1];

            if (isset($arrRow[$colIndex])) {
                $arrRowHeaders[$colIndex] = trim($arrRow[$colIndex] ?? '');
            }

            $colIndex++;
        }

        // Load each client's info
        $arrClientsInfo = array();
        $clientsCount = $sheet['numRows'];
        $rowStart = 0;
        for ($rowIndex = $rowStart; $rowIndex < $clientsCount; $rowIndex++) {
            if (!empty($rowIndex)) {
                $colIndex = 1;
                while ($colIndex <= $sheet['numCols']) {
                    $arrRow = $sheet['cells'][$rowIndex + 1];

                    if (isset($arrRowHeaders[$colIndex]) && !empty($arrRowHeaders[$colIndex])) {
                        $columnId = $arrRowHeaders[$colIndex];

                        $arrClientsInfo[$rowIndex][$columnId] = isset($arrRow[$colIndex]) ? trim($arrRow[$colIndex]) : '';
                    }

                    $colIndex++;
                }
            }

            if (!empty($rowEnd) && $rowIndex >= $rowEnd)
                break;
        }

        return array(
            $arrSheets,
            $arrClientsInfo
        );
    }

    /**
     * This is a stub
     * Automatically redirects to the step 1
     */
    public function indexAction()
    {
        return $this->redirect()->toUrl('/superadmin/import-bcpnp/step1');
    }

    /**
     * Show list of uploaded Excel files,
     * Allow to upload a new file
     */
    public function step1Action()
    {
        $view = new ViewModel();

        $this->layout()->setVariable('useJQuery', true);
        $this->layout()->setVariable('title', $this->_tr->translate('BC PNP Import: Step #1'));

        $fileName = $this->_filter->filter($this->findParam('file_name', ''));
        $view->setVariable('fileName', $fileName);
        $view->setVariable('select_file_message', $this->params('select_file_message', ''));
        $view->setVariable('select_file_error', $this->params('select_file_error', ''));

        $companyId = $this->_auth->getCurrentUserCompanyId();

        if($this->getRequest()->isPost() && !empty($_FILES)) {
            $fileSaveResult = $this->_files->saveClientsXLS($companyId, $this->_auth->getCurrentUserId(), 'xls', true);

            $view->setVariable('upload_error', $fileSaveResult['error']);
            $view->setVariable('upload_message', $fileSaveResult['result']);
        }
        $view->setVariable('storedFiles', $this->_files->getClientsXLSFilesList($companyId, true));

        return $view;
    }

    /**
     * Show list of company fields ready for mapping
     * Check if incoming params are correct
     */
    public function step2Action()
    {
        $view = new ViewModel();

        $strError = '';
        $fields   = array();

        try {
            $this->layout()->setVariable('useJQuery', true);
            $this->layout()->setVariable('title', $this->_tr->translate('BC PNP Import: Step #2'));

            $companyId = $this->_auth->getCurrentUserCompanyId();

            $fileName = $this->_filter->filter($this->findParam('file_name'));
            $view->setVariable('fileName', $fileName);

            // get fields
            $storedFiles = $this->_files->getClientsXLSFilesList($companyId, true);

            $filePath = $this->_files->getBcpnpXLSPath($companyId) . '/' . $fileName;

            $booFileExists = $this->_auth->isCurrentUserCompanyStorageLocal() ? is_file($filePath) : $this->_files->getCloud()->checkObjectExists($filePath);

            if (!isset($storedFiles[$fileName]) || !$booFileExists) {
                $strError = $this->_tr->translate('Incorrectly selected import file.');
            }

            if (empty($strError)) {
                list(, $data) = $this->getFieldsFromXLS($filePath);

                $arrXlsFields = array_key_exists(1, $data) ? array_keys($data[1]) : array();

                $identifierFieldUniqueId = $this->_config['settings']['bcpnp_import_identificator_field_name'];
                if (empty($identifierFieldUniqueId)) {
                    $strError = $this->_tr->translate('Identifier field was not set in the config file.');
                }

                $identifierFieldLabel = '';
                if (empty($strError)) {
                    $identifierFieldInfo = $this->_clients->getFields()->getCompanyFieldInfoByUniqueFieldId($identifierFieldUniqueId, $companyId);
                    if (!isset($identifierFieldInfo['label'])) {
                        $strError = $this->_tr->translate('Identifier field was not found for the company');
                    } else {
                        $identifierFieldLabel = $identifierFieldInfo['label'];
                    }
                }

                if (empty($strError)) {
                    if (!in_array($identifierFieldUniqueId, $arrXlsFields)) {
                        $strError = $identifierFieldLabel . $this->_tr->translate(' field does not exist in the file');
                    } elseif (($key = array_search($identifierFieldUniqueId, $arrXlsFields)) !== false) {
                        unset($arrXlsFields[$key]);
                    }

                    if (empty($strError)) {
                        $view->setVariable('xlsFields', $arrXlsFields);

                        $view->setVariable('number_of_client_rows', count($data));

                        $dbFields = $this->getPreparedFieldsFromDb($companyId);
                        $view->setVariable('dbFields', $dbFields);

                        foreach ($arrXlsFields as $xlsColumnNumber => $xlsColNum) {
                            $shortest = -1;
                            foreach ($dbFields as $id => $dbField) {
                                $field['id']       = $id;
                                $field['selected'] = '';
                                $field['class']    = ''; // contains error class. ($xlsField !== $dbField)
                                if ($xlsColNum == $dbField || strtolower($xlsColNum) == strtolower($dbField)) {
                                    $field['xlsField']        = $xlsColNum;
                                    $field['dbField']         = $dbField;
                                    $field['selected']        = 'selected="selected"';
                                    $fields[$xlsColumnNumber] = $field;
                                    break;
                                } else {
                                    $lev = levenshtein($xlsColNum, $dbField);
                                    if ($lev <= $shortest || $shortest < 0) {
                                        $shortest                 = $lev;
                                        $field['xlsField']        = $xlsColNum;
                                        $field['dbField']         = $dbField;
                                        $field['selected']        = 'selected="selected"';
                                        $field['class']           = 'field-naming-error';
                                        $fields[$xlsColumnNumber] = $field;
                                    }
                                }
                            }
                        }

                        $view->setVariable('fields', $fields);
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            return $this->forward()->dispatch(
                ImportBcpnpController::class,
                array(
                    'action' => 'step1',
                    'select_file_error' => 1,
                    'select_file_message' => $strError
                )
            );
        }

        return $view;
    }

    /**
     * Check if incoming params are correct,
     * Run BC PNP clients importing (update)
     */
    public function step3Action()
    {
        $view = new ViewModel();

        set_time_limit(60 * 60); // 1 hour
        ini_set('memory_limit', '512M');

        try {
            $this->layout()->setVariable('useJQuery', true);
            $this->layout()->setVariable('title', $this->_tr->translate('BC PNP Import: Step #3'));
            $view->setVariable('req', Settings::filterParamsArray($this->findParams(), $this->_filter));

            $page = $this->findParam('page', -1);
            $page = is_numeric($page) ? $page : 0;

            $cacheId = 'import_bcpnp_clients_' . $this->_auth->getCurrentUserId();
            if ($page > 0) {
                if (!($arrSavedCache = $this->_cache->getItem($cacheId))) {
                    throw new Exception('Cache was not loaded');
                } else {
                    $fileName                 = $arrSavedCache['file_name'];
                    $fieldsToMap              = $arrSavedCache['fields_to_map'];
                    $arrPost                  = $arrSavedCache['post_params'];
                    $booTriggerAutomaticTasks = $arrSavedCache['trigger_autotasks'];
                    $arrClientsToProcess      = $arrSavedCache['clients_to_process'];
                }
            } else {
                $fileName                 = $this->_filter->filter($this->findParam('file_name'));
                $booTriggerAutomaticTasks = $this->findParam('trigger_autotasks');
                $fieldsToMap              = array_keys(Settings::filterParamsArray($this->findParam('fields_to_map', array()), $this->_filter));
                $arrPost                  = array();
                $arrClientsToProcess      = array();
            }
            $view->setVariable('fileName', $fileName);

            $companyId = $this->_auth->getCurrentUserCompanyId();

            $processAtOnce = 10;

            // Get company admin id
            $companyAdminId = $this->_company->getCompanyAdminId($companyId);

            if (empty($companyAdminId)) {
                exit($this->_tr->translate('Incorrect Company Admin Id'));
            }

            $arrCaseTemplates = $this->_clients->getCaseTemplates()->getTemplates($companyId, false, null, true);
            $arrCaseTemplateFields = array();

            $arrCompanyCaseFields = $this->_clients->getFields()->getCompanyFields($companyId);

            foreach ($arrCaseTemplates as $arrCaseTemplateInfo) {
                // Load case's fields list (based on case template)
                $arrCaseFieldsGrouped = $this->_clients->getFields()->getGroupedCompanyFields($arrCaseTemplateInfo['case_template_id']);
                foreach ($arrCaseFieldsGrouped as $arrCaseFieldsGroup) {
                    foreach ($arrCaseFieldsGroup['fields'] as $arrFieldInfo) {
                        $arrFieldInfo['company_field_id'] = $arrFieldInfo['field_unique_id'];
                        $arrFieldInfo['required'] = $arrFieldInfo['field_required'];
                        $arrFieldInfo['type'] = $arrFieldInfo['field_type'];
                        $arrCaseTemplateFields[$arrCaseTemplateInfo['case_template_id']][] = $arrFieldInfo;
                    }
                }
            }

            $filePath = $this->_files->getBcpnpXLSPath($companyId) . '/' . $fileName;
            list($arrSheets,) = $this->getFieldsFromXLS($filePath);

            if (!is_array($arrSheets) || empty($arrSheets)) {
                exit($this->_tr->translate('Not correct information'));
            }

            $identifierFieldUniqueId = $this->_config['settings']['bcpnp_import_identificator_field_name'];
            $identifierFieldInfo     = $this->_clients->getFields()->getCompanyFieldInfoByUniqueFieldId($identifierFieldUniqueId, $companyId);
            if (!isset($identifierFieldInfo['label'])) {
                exit($this->_tr->translate('Identifier field was not found for the company'));
            } else {
                $identifierFieldLabel = $identifierFieldInfo['label'];
            }

            // First sheet is used for client's info
            $sheet = $arrSheets[0];

            // Load headers
            $colIndex      = 1;
            $arrRowHeaders = array();
            while ($colIndex <= $sheet['numCols']) {
                $arrRow = $sheet['cells'][1];

                if (isset($arrRow[$colIndex])) {
                    $arrRowHeaders[$colIndex] = trim($arrRow[$colIndex] ?? '');
                }

                $colIndex++;
            }

            // Load each client's info
            $arrClientsInfo = array();
            for ($rowIndex = 2; $rowIndex <= count($sheet['cells']); $rowIndex++) {
                $colIndex = 1;
                while ($colIndex <= $sheet['numCols']) {
                    $arrRow = $sheet['cells'][$rowIndex];

                    if (isset($arrRowHeaders[$colIndex]) && !empty($arrRowHeaders[$colIndex])) {
                        $columnId = $arrRowHeaders[$colIndex];

                        $arrClientsInfo[$rowIndex][$columnId] = isset($arrRow[$colIndex]) ? trim($arrRow[$colIndex]) : '';
                    }

                    $colIndex++;
                }
            }

            // Prepare and run import for each client record
            $currentClientNum = 1;

            // Calculate start and finish
            $user_start = (int)(($page > 0) ? $arrPost['index_start'] - 2 : $this->findParam('index_start') - 2);
            $user_finish = (int)(($page > 0) ? $arrPost['index_finish'] - 2 : $this->findParam('index_finish') - 2);

            if ($user_start > $user_finish) {
                exit('Start row must be less than or equal to finish row');
            } else {
                $arrClientsInfo = array_merge(array_slice($arrClientsInfo, $user_start, $user_finish - $user_start + 1), array());
            }

            if (empty($arrClientsToProcess)) {
                $identifierFieldId = $this->_clients->getFields()->getCompanyFieldIdByUniqueFieldId($identifierFieldUniqueId, $companyId);

                $contactTypeId = $this->_clients->getMemberTypeIdByName('internal_contact');

                $arrCompanyEmployerFields = $this->_clients->getApplicantFields()->getCompanyFields($companyId, $this->_clients->getMemberTypeIdByName('employer'));
                $arrCompanyIndividualFields = $this->_clients->getApplicantFields()->getCompanyFields($companyId, $this->_clients->getMemberTypeIdByName('individual'));

                // Array of fields with valid names from db
                $arrDbFields = array();

                foreach ($fieldsToMap as $xlsFieldNumber) {
                    $fieldNumber = 'excel_field_' . ((int)$xlsFieldNumber);
                    $arrDbFields[$xlsFieldNumber] = $page < 1 ? $this->findParam($fieldNumber) : $arrPost[$fieldNumber];
                }

                $arrAllClientsGrouped = array(
                    1 => array(),
                    2 => array(),
                    3 => array()
                );

                foreach ($arrClientsInfo as $thisClient) {
                    $arrErrors = array();
                    $booClientNotFound = true;
                    $caseId = 0;
                    $identifierFieldValue = '';

                    if (isset($thisClient[$identifierFieldUniqueId]) && !empty($thisClient[$identifierFieldUniqueId])) {
                        if ($identifierFieldUniqueId === 'file_number') {
                            $caseId = $arrNewClientInfo['member_id'] = $this->_clients->getClientIdByFileNumber($thisClient[$identifierFieldUniqueId], $companyId);
                            if ($caseId) {
                                $identifierFieldValue = $thisClient[$identifierFieldUniqueId];
                                $booClientNotFound    = false;
                            }
                        } else {
                            $arrClientIds = $this->_clients->getClientIdBySavedField($companyId, $identifierFieldId, $thisClient[$identifierFieldUniqueId]);
                            if (count($arrClientIds)) {
                                $caseId               = $arrNewClientInfo['member_id'] = $arrClientIds[0];
                                $identifierFieldValue = $thisClient[$identifierFieldUniqueId];
                                $booClientNotFound    = false;
                            }
                        }

                        if ($booClientNotFound) {
                            $arrErrors[] = '<div style="color:red;">' . $this->_tr->translate('Case with ' . $identifierFieldLabel . ' #' . $thisClient[$identifierFieldUniqueId] . ' was not found') . '</div>';
                        }
                    } else {
                        $arrErrors[] = '<div style="color:red;">' . $this->_tr->translate($identifierFieldLabel . ' is undefined') . '</div>';
                    }

                    $arrClientInternalContacts = array();
                    $arrClientData = array();
                    $arrCaseData = array();

                    $fileStatusNewValue = '';
                    $fileStatusFieldId = '';
                    $fileStatusOldValue = '';


                    if (!$booClientNotFound) {
                        $arrCaseInfo = $this->_clients->getClientInfo($caseId);
                        $caseTypeId = $arrCaseInfo['client_type_id'];
                        $arrParents = $this->_clients->getParentsForAssignedApplicants(array($caseId), false, false);
                        $booHasIndividualParent = $booHasEmployerParent = false;
                        $individualParentId = $employerParentId = 0;

                        foreach ($arrParents as $parentInfo) {
                            if ($parentInfo['member_type_name'] == 'individual') {
                                $individualParentId = $parentInfo['parent_member_id'];
                                $booHasIndividualParent = true;
                            } elseif ($parentInfo['member_type_name'] == 'employer') {
                                $employerParentId = $parentInfo['parent_member_id'];
                                $booHasEmployerParent = true;
                            }
                        }

                        $xlsFields = array_keys($thisClient);

                        foreach ($arrDbFields as $xlsColNum => $strFieldId) {
                            if (preg_match('/^(EM|IA|CASE): (.*)$/', $strFieldId, $regs)) {
                                $realFieldId = $regs[2];

                                switch ($regs[1]) {
                                    case 'EM':
                                    case 'IA':
                                        $arrClientFields = $regs[1] == 'EM' ? $arrCompanyEmployerFields : $arrCompanyIndividualFields;
                                        $thisClientType = $regs[1] == 'EM' ? 'employer' : 'individual';
                                        $parentId = $regs[1] == 'EM' ? $employerParentId : $individualParentId;

                                        if ($thisClientType == 'employer' && !$booHasEmployerParent) {
                                            $arrErrors[] = $this->_tr->translate('Employer field ' . $realFieldId . ' was selected in mapping. Case is not assigned to employer');
                                            break;
                                        } elseif ($thisClientType == 'individual' && !$booHasIndividualParent) {
                                            $arrErrors[] = $this->_tr->translate('Individual field ' . $realFieldId . ' was selected in mapping. Case is not assigned to individual');
                                            break;
                                        }

                                        foreach ($arrClientFields as $arrParentClientFieldInfo) {
                                            if ($arrParentClientFieldInfo['applicant_field_unique_id'] == $realFieldId) {
                                                $arrCheckResult = $this->_clients->getFields()->getFieldValue($arrClientFields, $realFieldId, $thisClient[$xlsFields[$xlsColNum]], $currentClientNum, $companyId, false);
                                                $fieldLabel = $arrParentClientFieldInfo['label'];

                                                if ($arrCheckResult['error']) {
                                                    $arrErrors[] = $this->_tr->translate('Field ' . $fieldLabel . ' - ' . $arrCheckResult['error-msg']);
                                                    break 2;
                                                }

                                                $fieldVal = $arrCheckResult['result'] ?? $arrCheckResult;

                                                list($fieldReadableValue,) = $this->_clients->getFields()->getFieldReadableValue(
                                                    $companyId,
                                                    $this->_auth->getCurrentUserDivisionGroupId(),
                                                    array('type' => $arrParentClientFieldInfo['type']),
                                                    $fieldVal,
                                                    true,
                                                    true,
                                                    true,
                                                    true,
                                                    true,
                                                    true
                                                );

                                                $fieldName = $arrParentClientFieldInfo['applicant_field_unique_id'];
                                                if ($this->_clients->getFields()->isStaticField($arrParentClientFieldInfo['applicant_field_unique_id'])) {
                                                    $fieldName = $this->_clients->getFields()->getStaticColumnName($arrParentClientFieldInfo['applicant_field_unique_id']);
                                                }

                                                // Group fields by parent client type
                                                // i.e. internal contact info and main client info
                                                if ($arrParentClientFieldInfo['member_type_id'] == $contactTypeId || $arrParentClientFieldInfo['contact_block'] == 'Y') {
                                                    $assignedInternalContactId = $this->_clients->getAssignedContact($parentId, $arrParentClientFieldInfo['group_id']);
                                                    if (!empty($assignedInternalContactId)) {
                                                        if (!array_key_exists($assignedInternalContactId, $arrClientInternalContacts)) {
                                                            $arrClientInternalContacts[$assignedInternalContactId] = array();
                                                        }

                                                        if (!array_key_exists('parentId', $arrClientInternalContacts[$assignedInternalContactId])) {
                                                            $arrClientInternalContacts[$assignedInternalContactId]['parentId'] = $parentId;
                                                        }

                                                        if (!array_key_exists('data', $arrClientInternalContacts[$assignedInternalContactId])) {
                                                            $arrClientInternalContacts[$assignedInternalContactId]['data'] = array();
                                                        }

                                                        $arrClientInternalContacts[$assignedInternalContactId]['data'][] = array(
                                                            'field_id' => $arrParentClientFieldInfo['applicant_field_id'],
                                                            'field_unique_id' => $arrParentClientFieldInfo['applicant_field_unique_id'],
                                                            'value' => $fieldVal,
                                                            'row' => 0,
                                                            'row_id' => 0
                                                        );

                                                        if (!array_key_exists('arrXfdfData', $arrClientInternalContacts[$assignedInternalContactId])) {
                                                            $arrClientInternalContacts[$assignedInternalContactId]['arrXfdfData'] = array();
                                                        }

                                                        $arrClientInternalContacts[$assignedInternalContactId]['arrXfdfData'][$fieldName] = $fieldReadableValue;
                                                    }

                                                } else {
                                                    if (!array_key_exists($parentId, $arrClientData)) {
                                                        $arrClientData[$parentId] = array();
                                                    }

                                                    if (in_array($arrParentClientFieldInfo['type'], array('office_multi', 'office'))) {
                                                        if (!array_key_exists('arrOffices', $arrClientData[$parentId])) {
                                                            $arrClientData[$parentId]['arrOffices'] = array();
                                                        }
                                                        $arrClientData[$parentId]['arrOffices'][] = $fieldVal;
                                                    } else {
                                                        if (!array_key_exists('applicantData', $arrClientData[$parentId])) {
                                                            $arrClientData[$parentId]['applicantData'] = array();
                                                        }

                                                        $arrClientData[$parentId]['applicantData'][] = array(
                                                            'field_id' => $arrParentClientFieldInfo['applicant_field_id'],
                                                            'field_unique_id' => $arrParentClientFieldInfo['applicant_field_unique_id'],
                                                            'value' => $fieldVal,
                                                            'row' => 0,
                                                            'row_id' => 0
                                                        );

                                                        if (!array_key_exists('arrXfdfData', $arrClientData[$parentId])) {
                                                            $arrClientData[$parentId]['arrXfdfData'] = array();
                                                        }

                                                        $arrClientData[$parentId]['arrXfdfData'][$fieldName] = $fieldReadableValue;
                                                    }
                                                }
                                                break;
                                            }
                                        }
                                        break;

                                    case 'CASE':
                                    default:
                                        $arrCaseFields = $arrCaseTemplateFields[$caseTypeId];
                                        $fieldWasFound = false;
                                        $fieldId = 0;
                                        $fieldLabel = '';

                                        foreach ($arrCaseFields as $fieldInfo) {
                                            if ($fieldInfo['company_field_id'] == $realFieldId) {
                                                $fieldWasFound = true;
                                                $fieldId = $fieldInfo['field_id'];
                                                $fieldLabel = $fieldInfo['field_name'];
                                                break;
                                            }
                                        }

                                        // Field is incorrect/not found
                                        if (!$fieldWasFound) {
                                            $arrErrors[] = $this->_tr->translate('Field ' . $realFieldId . ' was not found for this case');
                                            break;
                                        }

                                        $arrCheckResult = $this->_clients->getFields()->getFieldValue($arrCaseFields, $realFieldId, $thisClient[$xlsFields[$xlsColNum]], $currentClientNum, $companyId);

                                        if ($arrCheckResult['error']) {
                                            $arrErrors[] = $this->_tr->translate('Field ' . $fieldLabel . ' - ' . $arrCheckResult['error-msg']);
                                            break;
                                        }

                                        $value = $arrCheckResult['result'] ?? $arrCheckResult;

                                        if ($realFieldId == 'file_status' && !empty($value)) {
                                            $fileStatusOldValue = $this->_clients->getFields()->getFieldDataValue($fieldId, $caseId);
                                            $fileStatusNewValue = is_array($value) ? $value[0] : $value;
                                            $fileStatusFieldId = $fieldId;
                                        }

                                        $arrCaseData[$fieldId] = $value;
                                        break;
                                }
                            }
                        }
                    }

                    $arrParents = $arrContacts = array();

                    foreach ($arrClientData as $key => $parentData) {
                        $arrParents[] = array(
                            'applicantId'   => $key,
                            'applicantData' => $parentData['applicantData'] ?? array(),
                            'arrOffices'    => $parentData['arrOffices'] ?? array(),
                            'arrXfdfData'   => $parentData['arrXfdfData'] ?? array()
                        );
                    }

                    foreach ($arrClientInternalContacts as $key => $internalContactData) {
                        $arrContacts[] = array(
                            'contactId'   => $key,
                            'parentId'    => $internalContactData['parentId'],
                            'data'        => $internalContactData['data'] ?? array(),
                            'arrXfdfData' => $internalContactData['arrXfdfData'] ?? array()
                        );
                    }

                    if (!empty($arrErrors)) {
                        $order = 2;
                        if (empty($arrParents) && empty($arrContacts) && empty($arrCaseData)) {
                            $order = 1;
                        }
                    } else {
                        $order = 3;
                    }

                    $arrUpdateClientInfo = array(
                        'identifierFieldValue' => $identifierFieldValue,
                        'arrParents'           => $arrParents,
                        'arrContacts'          => $arrContacts,

                        'arrErrors'            => $arrErrors,
                        'order'                => $order,

                        // Case info
                        'member_id'            => $caseId,
                        'case'                 => array(
                            'members'          => array(),
                            'clients'          => array(),
                            'client_form_data' => $arrCaseData,
                            'fileStatusInfo'   => array(
                                'oldValue' => $fileStatusOldValue,
                                'newValue' => $fileStatusNewValue,
                                'fieldId'  => $fileStatusFieldId,
                            )
                        )
                    );

                    $arrAllClientsGrouped[$order][] = $arrUpdateClientInfo;
                    $currentClientNum++;
                }

                $arrClientsToProcess = array_merge($arrAllClientsGrouped[1], $arrAllClientsGrouped[2], $arrAllClientsGrouped[3]);
            }

            if (!is_array($arrClientsToProcess)) {
                throw new Exception('Clients to process has invalid type');
            }

            $rowEnd = 0;
            $clientsCount = count($arrClientsToProcess);

            $log = '';

            if ($clientsCount) {
                $rowStart = $page * $processAtOnce;
                $rowEnd   = $rowStart + $processAtOnce;

                // run import!
                for ($i = $rowStart; $i < min($rowEnd, count($arrClientsToProcess)); $i++) {
                    $arrAllFieldsChangesData = array();
                    $arrClientInfo = $arrClientsToProcess[$i];
                    $identifierFieldValue = $arrClientInfo['identifierFieldValue'];

                    if ($arrClientInfo['order'] == 1) {
                        if (!empty($arrClientInfo['member_id'])) {
                            $log .= '<div style="color:red;">' . $this->_tr->translate('Case with ' . $identifierFieldLabel . ' #' . $identifierFieldValue . ' was not updated:') . '</div>';
                            foreach ($arrClientInfo['arrErrors'] as $error) {
                                $log .= '<div style="color:#ff0000; padding-left: 20px;">' . $error . '</div>';
                            }
                        } else {
                            foreach ($arrClientInfo['arrErrors'] as $error) {
                                $log .= '<div style="color:red;">' . $error . '</div>';
                            }
                        }
                        continue;
                    }

                    foreach ($arrClientInfo['arrParents'] as $arrParentInfo) {
                        $arrUpdateApplicant = $this->_clients->updateApplicant(
                            $companyId,
                            $companyAdminId,
                            $arrParentInfo['applicantId'],
                            $arrParentInfo['applicantData'],
                            array(),
                            false
                        );

                        $arrAllFieldsChangesData[$arrParentInfo['applicantId']]['booIsApplicant'] = true;
                        $arrAllFieldsChangesData[$arrParentInfo['applicantId']]['arrOldData']     = $arrUpdateApplicant['arrOldData'];
                        $arrAllFieldsChangesData[$arrParentInfo['applicantId']]['arrNewData']     = $arrParentInfo['applicantData'];

                        if (!empty($arrParentInfo['arrOffices'])) {
                            $divisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();

                            $arrSavedOffices  = $this->_clients->getApplicantOffices(array($arrParentInfo['applicantId']), $divisionGroupId);
                            $arrDivisionsInfo = $this->_company->getCompanyDivisions()->getDivisionsByIds($arrSavedOffices);

                            // Make sure that permanent offices will be not deleted
                            $thisClientOfficesToAssign = $arrParentInfo['arrOffices'];
                            foreach ($arrDivisionsInfo as $arrDivisionInfo) {
                                if ($arrDivisionInfo['access_permanent'] == 'Y' && !in_array($arrDivisionInfo['division_id'], $thisClientOfficesToAssign)) {
                                    $thisClientOfficesToAssign[] = $arrDivisionInfo['division_id'];
                                }
                            }

                            list($booSuccess,) = $this->_clients->updateClientsOffices($companyId, array($arrParentInfo['applicantId']), $thisClientOfficesToAssign, null, null, $booTriggerAutomaticTasks);

                            if (!$booSuccess) {
                                $log .= '<div style="color:red;">' . $this->_tr->translate('Internal error: Update office failed for parent of case with ' . $identifierFieldLabel . ' #' . $identifierFieldValue) . '</div>';
                                break;
                            }
                        }

                        if (!$arrUpdateApplicant['success']) {
                            $log .= '<div style="color:red;">' . $this->_tr->translate('Internal error: Update failed for parent of case with ' . $identifierFieldLabel . ' #' . $identifierFieldValue) . '</div>';
                        } else {
                            if (!empty($arrParentInfo['arrXfdfData'])) {
                                $arrCasesToUpdate = array_unique($this->_clients->getAssignedApplicants($arrParentInfo['applicantId'], $this->_clients->getMemberTypeIdByName('case')));
                                $memberTypeId = $this->_members->getMemberTypeByMemberId($arrParentInfo['applicantId']);

                                foreach ($arrCasesToUpdate as $caseId) {
                                    if (!$this->_pdf->updateXfdfOnProfileUpdate($companyId, $caseId, $arrParentInfo['arrXfdfData'], $memberTypeId)) {
                                        $log .= '<div style="color:red;">' . $this->_tr->translate('Internal error: Update xfdf failed for parent of case with ' . $identifierFieldLabel . ' #' . $identifierFieldValue) . '</div>';
                                    }
                                }
                            }

                            $this->_clients->updateCaseEmailFromParent($arrParentInfo['applicantId']);

                            if ($booTriggerAutomaticTasks) {
                                $this->_triggers->triggerProfileUpdate($companyId, $arrParentInfo['applicantId']);
                            }
                        }
                    }

                    foreach ($arrClientInfo['arrContacts'] as $arrContactInfo) {
                        $arrUpdateContact = $this->_clients->updateApplicant(
                            $companyId,
                            $companyAdminId,
                            $arrContactInfo['contactId'],
                            $arrContactInfo['data'],
                            array(),
                            false
                        );

                        $arrAllFieldsChangesData[$arrContactInfo['contactId']]['booIsApplicant'] = true;
                        $arrAllFieldsChangesData[$arrContactInfo['contactId']]['arrOldData']     = $arrUpdateContact['arrOldData'];
                        $arrAllFieldsChangesData[$arrContactInfo['contactId']]['arrNewData']     = $arrContactInfo['data'];

                        if (!$arrUpdateContact['success']) {
                            $log .= '<div style="color:red;">' . $this->_tr->translate('Internal error: Update failed for parent\'s internal contact of case with ' . $identifierFieldLabel . ' #' . $identifierFieldValue) . '</div>';
                        } else {
                            // Update applicant's name from the 'main contact'
                            $parentInfo = $this->_clients->getClientInfo($arrContactInfo['parentId']);

                            if (!$this->_clients->updateApplicantFromMainContact($companyId, $arrContactInfo['parentId'], $parentInfo['userType'], $parentInfo['applicant_type_id'])) {
                                $log .= '<div style="color:red;">' . $this->_tr->translate('Internal error: Update failed for parent\'s internal contact of case with ' . $identifierFieldLabel . ' #' . $identifierFieldValue) . '</div>';
                            }

                            $this->_clients->updateCaseEmailFromParent($arrContactInfo['parentId']);

                            if (!empty($arrContactInfo['arrXfdfData'])) {
                                $arrCasesToUpdate = array_unique($this->_clients->getAssignedApplicants($arrContactInfo['parentId'], $this->_clients->getMemberTypeIdByName('case')));
                                $memberTypeId = $this->_members->getMemberTypeByMemberId($arrContactInfo['parentId']);

                                foreach ($arrCasesToUpdate as $caseId) {
                                    if (!$this->_pdf->updateXfdfOnProfileUpdate($companyId, $caseId, $arrContactInfo['arrXfdfData'], $memberTypeId)) {
                                        $log .= '<div style="color:red;">' . $this->_tr->translate('Internal error: Update xfdf failed for parent of case with ' . $identifierFieldLabel . ' #' . $identifierFieldValue) . '</div>';
                                    }
                                }
                            }

                            if ($booTriggerAutomaticTasks) {
                                $this->_triggers->triggerProfileUpdate($companyId, $arrContactInfo['parentId']);
                            }
                        }
                    }

                    $arrCaseData = $arrClientInfo['case']['client_form_data'];
                    $arrFieldIds = array_keys($arrCaseData);
                    $arrOldData  = $this->_clients->getFields()->getClientFieldsValues($arrClientInfo['member_id'], $arrFieldIds);

                    if ($this->_clients->updateClient($arrClientInfo, $companyId, $arrCompanyCaseFields)) {
                        if (!empty($arrClientInfo['arrErrors'])) {
                            $log .= '<div style="color:orange;">' . $this->_tr->translate('Case with ' . $identifierFieldLabel . ' #' . $identifierFieldValue . ' was partially updated:') . '</div>';
                            foreach ($arrClientInfo['arrErrors'] as $error) {
                                $log .= '<div style="color:orange; padding-left: 20px;">' . $error . '</div>';
                            }
                        } else {
                            $log .= '<div style="color:green;">' . $this->_tr->translate('Case with ' . $identifierFieldLabel . ' #' . $identifierFieldValue . ' was updated') . '</div>';
                        }
                        if ($booTriggerAutomaticTasks) {
                            $this->_triggers->triggerProfileUpdate($companyId, $arrClientInfo['member_id']);
                        }
                    } else {
                        $log .= '<div style="color:red;">' . $this->_tr->translate('Internal error: Update failed for case with ' . $identifierFieldLabel . ' #' . $identifierFieldValue) . '</div>';
                    }

                    $arrNewData  = $this->_clients->getFields()->getClientFieldsValues($arrClientInfo['member_id'], $arrFieldIds);

                    $arrAllFieldsChangesData[$arrClientInfo['member_id']]['booIsApplicant'] = false;
                    $arrAllFieldsChangesData[$arrClientInfo['member_id']]['arrOldData']     = $arrOldData;
                    $arrAllFieldsChangesData[$arrClientInfo['member_id']]['arrNewData']     = $arrNewData;

                    if ($booTriggerAutomaticTasks) {
                        $fileStatusOldValue = $arrClientInfo['case']['fileStatusInfo']['oldValue'];
                        $fileStatusNewValue = $arrClientInfo['case']['fileStatusInfo']['newValue'];

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
                                $arrClientInfo['member_id'],
                                $this->_clients->getCaseStatuses()->getCaseStatusesNames($fileStatusOldValue),
                                $this->_clients->getCaseStatuses()->getCaseStatusesNames($fileStatusNewValue),
                                $this->_auth->getCurrentUserId(),
                                $this->_auth->hasIdentity() ? $this->_auth->getIdentity()->full_name : ''
                            );
                        }
                    }

                    $this->_triggers->triggerFieldBulkChanges($companyId, $arrAllFieldsChangesData, $booTriggerAutomaticTasks);
                    $view->setVariable('percent', round($i / $clientsCount * 100));
                }
            }

            $view->setVariable('percent', 100);
            $view->setVariable('log', $log);

            if ($clientsCount > $processAtOnce && $page > 0) {
                $booContinueImporting = $rowEnd < $clientsCount;

                if (!$booContinueImporting) {
                    $this->_cache->removeItem($cacheId);
                }

                $arrResult = array(
                    'log'         => $log,
                    'page'        => $page + 1,
                    'booContinue' => $booContinueImporting,
                    'percent'     => $booContinueImporting ? round($rowEnd / $clientsCount * 100) : 100
                );
                $view->setTerminal(true);
                $view->setTemplate('layout/plain');
                return $view->setVariable('content', Json::encode($arrResult));
            }


            if ($rowEnd < $clientsCount) {
                if ($page < 1) {
                    $arrSavedCache = [
                        'file_name'          => $view->getVariable('fileName'),
                        'fields_to_map'      => $fieldsToMap,
                        'post_params'        => Settings::filterParamsArray($this->findParams(), $this->_filter),
                        'clients_to_process' => $arrClientsToProcess,
                    ];

                    $this->_cache->setItem($cacheId, $arrSavedCache);

                    $view->setVariable('commandNextPage', $page + 1);
                } else {
                    $view->setVariable('commandNextPage', null);
                }

                $view->setVariable('percent', round($rowEnd / $clientsCount * 100));
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            $view->setVariable('log', '<div style="color:red;">' . $this->_tr->translate('Internal error') . '</div>');
            $view->setVariable('percent', 0);
        }

        return $view;
    }

    public function deleteXlsFileAction()
    {
        $view = new JsonModel();
        $strError = '';
        try {
            $xls_id = (int) $this->findParam('xls_id');

            if(!$this->_files->deleteClientsXLSFile($xls_id, true)) {
                $strError = $this->_tr->translate('File was not deleted.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'status'        => empty($strError) ? 'success' : 'error',
            'error_message' => $strError
        );
        return $view->setVariables($arrResult);
    }
}
