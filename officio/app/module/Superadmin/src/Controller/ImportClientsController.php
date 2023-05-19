<?php

namespace Superadmin\Controller;

use Clients\Service\Clients;
use Exception;
use Files\Service\Files;
use Laminas\Cache\Psr\SimpleCache\SimpleCacheDecorator;
use Laminas\Cache\Service\StorageAdapterFactoryInterface;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Common\Service\Settings;
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
class ImportClientsController extends BaseController {

    /** @var StripTags */
    private $_filter;

    /** @var Clients */
    protected $_clients;

    /** @var Company */
    protected $_company;

    /** @var Files */
    protected $_files;

    /** @var StorageAdapterFactoryInterface */
    protected $_cacheFactory;

    public function initAdditionalServices(array $services)
    {
        $this->_company      = $services[Company::class];
        $this->_clients      = $services[Clients::class];
        $this->_files        = $services[Files::class];
        $this->_cacheFactory = $services[StorageAdapterFactoryInterface::class];

        $this->_filter = new StripTags();
    }

    private function _getDependentFieldId($arrDependentFields, $dependentFieldId, $booIdOnly = true) {
        foreach ($arrDependentFields as $fieldInfo) {
            if($fieldInfo['field_id'] == $dependentFieldId) {
                return $booIdOnly ? $fieldInfo['field_id'] : $fieldInfo;
            }
        }

        // Field is incorrect in Excel file
        $msg = sprintf(
            '<div style="color: red;">' .
                $this->_tr->translate('Dependent Field <em>%s</em> was not found for this company') .
            '</div>',
            $dependentFieldId
        );
        $view = new ViewModel(
            [
                'content' => $msg
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }

    private function _getDependentFieldValue($arrDependentFields, $strFieldId, $fieldVal, $clientNum, $booReturnValueOnly = false) {
        $fieldVal       = trim($fieldVal ?? '');
        $strError       = '';
        $strResultValue = '';

        $arrFieldInfo = $this->_getDependentFieldId($arrDependentFields, $strFieldId, false);
        switch ($arrFieldInfo['field_type']) {
            case 'date':
                if ($fieldVal) {
                    // 'm/d/Y' - default excel format
                    $dateFormatFull = $this->_settings->variableGet('dateFormatFull');

                    if (Settings::isValidDateFormat($fieldVal, 'm/d/y')) {
                        $strResultValue = $this->_settings->reformatDate($fieldVal, 'm/d/y', Settings::DATE_UNIX);
                    } elseif (Settings::isValidDateFormat($fieldVal, $dateFormatFull)) {
                        $strResultValue = $this->_settings->reformatDate($fieldVal, $dateFormatFull, Settings::DATE_UNIX);
                    } else {
                        $msg      = sprintf('<div>Incorrect date <em>%s</em> (client row #%d).</div>', $fieldVal, $clientNum + 1);
                        $strError = $msg;
                    }
                }
                break;

            case 'combo':
                if ($arrFieldInfo['field_required'] == 'N' && $fieldVal != '') {
                    $booCorrectOption = false;
                    foreach ($arrFieldInfo['field_options'] as $arrOption) {
                        if ($arrOption['option_id'] == $fieldVal || strtolower($arrOption['option_name'] ?? '') == strtolower($fieldVal ?? '')) {
                            $booCorrectOption = true;
                            $strResultValue   = $arrOption['option_id'];
                            break;
                        }
                    }

                    if (!$booCorrectOption) {
                        $msg      = sprintf(
                            $this->_tr->translate('<div>Option <em>%s</em> is not correct (client row #%d).</div>'),
                            $fieldVal,
                            $clientNum + 1
                        );
                        $strError = $msg;
                    }
                } else {
                    $strResultValue = $fieldVal;
                }
                break;

            // Don't check these fields
            case 'text':
            default:
                $strResultValue = $fieldVal;
                break;
        }


        return $booReturnValueOnly ? $strResultValue : array('error' => !empty($strError), 'error-msg' => $strError, 'result' => $strResultValue);
    }

    protected function getPreparedFieldsFromDb($companyId, $clientType, $caseType) {
        $arrGroupedFields = array();

        $arrCaseTemplateInfo = $this->_clients->getCaseTemplates()->getTemplateInfo($caseType);
        if(is_array($arrCaseTemplateInfo) && count($arrCaseTemplateInfo)) {
            $arrClientTypesToLoad = array($clientType);

            // Can be a situation when we need to load Employer + IA fields
            if($clientType == 'employer' && $arrCaseTemplateInfo['client_type_needs_ia'] == 'Y') {
                $arrClientTypesToLoad[] = 'individual';
            }

            foreach ($arrClientTypesToLoad as $clientTypeToLoad) {
                // Load client's fields list (based on type)
                $clientTypeId           = $this->_clients->getMemberTypeIdByName($clientTypeToLoad);
                $arrCompanyClientFields = $this->_clients->getApplicantFields()->getCompanyFields($companyId, $clientTypeId);
                $prefix = $clientTypeToLoad == 'employer' ? 'EM' : 'IA';
                foreach($arrCompanyClientFields as $field) {
                    // Skip fields from repeatable groups
                    if($field['repeatable'] != 'Y') {
                        $arrGroupedFields[$clientTypeToLoad . '_field_' . $field['applicant_field_id']] = $prefix . ': ' . $field['applicant_field_unique_id'];
                    }
                }
            }


            // Load case's fields list (based on case template)
            $arrCaseFieldsGrouped = $this->_clients->getFields()->getGroupedCompanyFields($caseType);
            foreach ($arrCaseFieldsGrouped as $arrCaseFieldsGroup) {
                foreach ($arrCaseFieldsGroup['fields'] as $arrFieldInfo) {
                    $arrGroupedFields['case_field_' . $arrFieldInfo['field_id']] = 'CASE: ' . $arrFieldInfo['field_unique_id'];
                }
            }

            $maxDependentsCount = 5;
            $arrCaseDependentFields = $this->_clients->getFields()->getDependantFields();
            foreach ($arrCaseDependentFields as $arrCaseDependentFieldInfo) {
                if ($arrCaseDependentFieldInfo['field_id'] != 'relationship') {
                    $arrGroupedFields['case_spouse_field_' . $arrCaseDependentFieldInfo['field_id']] = 'CASE SPOUSE: ' . $arrCaseDependentFieldInfo['field_id'];
                }
            }


            for ($i = 1; $i <= $maxDependentsCount; $i++) {
                foreach ($arrCaseDependentFields as $arrCaseDependentFieldInfo) {
                    $arrGroupedFields['case_dependent_field_' . $i . '_' . $arrCaseDependentFieldInfo['field_id']] = 'CASE DEPENDENT ' . $i . ': ' . $arrCaseDependentFieldInfo['field_id'];
                }
            }
        }


        return $arrGroupedFields;
    }

    protected function getFieldsFromXLS($importFilePath, $companyId) {
        $cacheId = 'import_sheets_' . $companyId;
        if (!($arrSheets = $this->_cache->getItem($cacheId))) {
            $tempFileLocation = $this->_auth->isCurrentUserCompanyStorageLocal() ? $importFilePath : $this->_files->getCloud()->downloadFileContent($importFilePath);
            if (empty($tempFileLocation)) {
                return array();
            }

            // Get inputFileType (most likely Excel5)
            $inputFileType = IOFactory::identify($tempFileLocation);

            // Initialize cache, so the phpExcel will not throw memory overflow
            $storage = $this->_cacheFactory->createFromArrayConfiguration(
                [
                    'adapter' => 'memory',
                    'options' => [
                        'memory_limit' => '8MB'
                    ]
                ]
            );
            $cache   = new SimpleCacheDecorator($storage);
            \PhpOffice\PhpSpreadsheet\Settings::setCache($cache);

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
            $arrSheetData          = $objSheet->toArray(null, false, false);
            $arrSheetDataConverted = array();
            foreach ($arrSheetData as $key => $arrData) {
                $arrSheetDataConverted[$key + 1] = array_combine(range(1, count($arrData)), array_values($arrData));
            }

            // Prepare data as it is returned in old format (Spreadsheet_Excel_Reader)
            $arrSheets = array(
                array(
                    'numCols' => Coordinate::columnIndexFromString($objSheet->getHighestColumn()),
                    'numRows' => $objSheet->getHighestRow(),
                    'cells'   => $arrSheetDataConverted
                )
            );

            $this->_cache->setItem($cacheId, $arrSheets);
        }

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
        for ($rowIndex = $rowStart; $rowIndex < $clientsCount; $rowIndex++)
        {
            if (!empty($rowIndex)) {
                $colIndex = 1;
                while($colIndex <= $sheet['numCols']) {
                    $arrRow = $sheet['cells'][$rowIndex+1];

                    if (isset($arrRowHeaders[$colIndex]) && !empty($arrRowHeaders[$colIndex])) {
                        $columnId = $arrRowHeaders[$colIndex];

                        $arrClientsInfo[$rowIndex][$columnId] = isset($arrRow[$colIndex]) ? trim($arrRow[$colIndex]) : '';
                    }

                    $colIndex++;
                }
            }

            if (!empty($rowEnd) && $rowIndex>=$rowEnd)
                break;
        }

        return $arrClientsInfo;
    }

    private function _clearCache($companyId) {
        $arrClearCache = array(
            'import_sheets_' . $companyId,
            'import_agents_' . $companyId,
            'import_assigned_to_' . $companyId,
            'import_rma_' . $companyId,
        );
        foreach ($arrClearCache as $cacheId) {
            $this->_cache->removeItem($cacheId);
        }
    }

    /**
     * This is a stub
     * Automatically redirects to the step 1
     */
    public function indexAction() {
        return $this->redirect()->toUrl('/superadmin/import-clients/step1');
    }

    /**
     * Show list of uploaded Excel files,
     * Allow to upload a new file
     */
    public function step1Action() {
        $view = new ViewModel();

        $this->layout()->setVariable('useJQuery', true);
        $this->layout()->setVariable('title', $this->_tr->translate('Import Clients: Step #1'));

        $fileName = $this->_filter->filter($this->findParam('file_name', ''));
        $view->setVariable('fileName', $fileName);
        $view->setVariable('select_file_message', $this->params('select_file_message', ''));
        $view->setVariable('select_file_error', $this->params('select_file_error', ''));

        $companyId = $this->_auth->getCurrentUserCompanyId();

        $this->_clearCache($companyId);

        if ($this->getRequest()->isPost() && !empty($_FILES)) {
            $fileSaveResult = $this->_files->saveClientsXLS($companyId, $this->_auth->getCurrentUserId(), 'xls');

            $view->setVariable('upload_error', $fileSaveResult['error']);
            $view->setVariable('upload_message', $fileSaveResult['result']);
        }
        $view->setVariable('storedFiles', $this->_files->getClientsXLSFilesList($companyId));

        return $view;
    }

    /*
     * Load the list of Staff Responsible fields
     * If they are used by the company
     */
    public function getStaffResponsibleFieldsUsedByCompany()
    {
        $arrExtraFieldsIds = array(
            'registered_migrant_agent',
            'accounting',
            'processing',
            'sales_and_marketing'
        );

        $arrProfileFieldsGrouped = $this->_clients->getFields()->getClientProfile('add');
        $arrExtraFieldsIdsFound  = array();
        foreach ($arrProfileFieldsGrouped['groups'] as $group) {
            foreach ($group['fields'] as $field) {
                if (in_array($field['company_field_id'], $arrExtraFieldsIds)) {
                    $arrExtraFieldsIdsFound[] = $field['company_field_id'];
                }
            }
        }

        return $arrExtraFieldsIdsFound;
    }

    /**
     * Show list of fields user needs to select,
     * Check if selected file is correct
     */
    public function step2Action()
    {
        $view = new ViewModel();
        $view->setVariable('select_file_message', $this->_tr->translate('Incorrectly selected import file.'));

        $this->layout()->setVariable('useJQuery', true);
        $this->layout()->setVariable('title', $this->_tr->translate('Import Clients: Step #2'));

        $companyId = $this->_auth->getCurrentUserCompanyId();

        // Some of these variables can come from the next step
        $view->setVariable('global_errors', $this->params('global_errors', []));

        // Params below might be passed via POST or forward() plugin which makes them route params
        $fileName = $this->findParam('file_name', '');
        if (empty($fileName)) {
            $fileName = $this->params($fileName);
        }
        $view->setVariable('fileName', $this->_filter->filter($fileName));

        $clientType = $this->findParam('import_client_type', 'individual');
        if (empty($clientType)) {
            $clientType = $this->params('import_client_type', 'individual');
        }
        $view->setVariable('clientType', $clientType);

        $caseType = $this->findParam('import_case_type', '');
        if (empty($caseType)) {
            $caseType = $this->params('import_case_type', '');
        }
        $view->setVariable('caseType', $caseType);

        $arrCaseTypes = $this->_clients->getCaseTemplates()->getTemplates($companyId, false, null, true);
        $view->setVariable('arrCaseTypes', $arrCaseTypes);

        // get fields
        $view->setVariable('oFields', $this->_clients->getFields());
        $view->setVariable('client_profile', $this->_clients->getFields()->getClientProfile('add'));
        $view->setVariable('caseTypeFieldLabel', $this->_company->getCurrentCompanyDefaultLabel('case_type'));

        $arrExtraFields    = array();
        $arrExtraFieldsIds = $this->getStaffResponsibleFieldsUsedByCompany();
        $view->setVariable('arrMainFieldsIds', $arrExtraFieldsIds);
        $arrCompanyCaseFields = $this->_clients->getFields()->getCompanyFields($companyId);
        foreach ($arrExtraFieldsIds as $extraFieldId) {
            $arrFieldInfo = $this->_clients->getFields()->getFieldId($arrCompanyCaseFields, $extraFieldId);

            if (!$arrFieldInfo['success']) {
                return $this->forward()->dispatch(
                    ImportClientsController::class,
                    array(
                        'action'              => 'step1',
                        'select_file_error'   => 1,
                        'select_file_message' => $arrFieldInfo['result']
                    )
                );
            } else {
                $fieldId = 'field-' . $arrFieldInfo['result'];
                $fieldVal = $this->findParam($fieldId);
                if(!empty($fieldVal)) {
                    $arrExtraFields[$fieldId] = $fieldVal;
                }
            }
        }
        $view->setVariable('arrExtraFields', $arrExtraFields);

        $storedFiles = $this->_files->getClientsXLSFilesList($companyId);
        $filePath = $this->_files->getClientsXLSPath($companyId) . '/' . $fileName;

        $booFileExists = $this->_auth->isCurrentUserCompanyStorageLocal() ? is_file($filePath) : $this->_files->getCloud()->checkObjectExists($filePath);
        if(!isset($storedFiles[$fileName]) || !$booFileExists) {
            return $this->forward()->dispatch(
               ImportClientsController::class,
               array(
                   'action' => 'step1',
                   'select_file_error' => 1,
                   'select_file_message' => $this->_tr->translate('Incorrectly selected import file.')
               )
           );
        }

        return $view;
    }

    /**
     * Show list of company fields ready for mapping
     * Check if incoming params are correct
     */
    public function step3Action()
    {
        $view = new ViewModel();

        $this->layout()->setVariable('useJQuery', true);
        $this->layout()->setVariable('title', $this->_tr->translate('Import Clients: Step #3'));

        $companyId = $this->_auth->getCurrentUserCompanyId();

        // Some of these variables can come from the next step
        $view->setVariable('global_errors', $this->params('global_errors', []));

        // Params below might be passed via POST or forward() plugin which makes them route params
        $fileName = $this->findParam('file_name', '');
        if (empty($fileName)) {
            $fileName = $this->params($fileName);
        }
        $view->setVariable('fileName', $this->_filter->filter($fileName));

        $clientType = $this->findParam('import_client_type', 'individual');
        if (empty($clientType)) {
            $clientType = $this->params('import_client_type', 'individual');
        }
        $view->setVariable('clientType', $clientType);

        $caseType = $this->findParam('import_case_type', '');
        if (empty($caseType)) {
            $caseType = $this->params('import_case_type', '');
        }
        $view->setVariable('caseType', $caseType);

        $arrCaseTypes = $this->_clients->getCaseTemplates()->getTemplates($companyId, false, null, true);
        $view->setVariable('arrCaseTypes', $arrCaseTypes);

        $arrGlobalErrors = array();
        if (!in_array($clientType, array('individual', 'employer'))) {
            $arrGlobalErrors[] = $this->_tr->translate('Please select client type.');
        }

        $arrCaseTypeInfo = $this->_clients->getCaseTemplates()->getTemplateInfo($caseType);
        if (!is_array($arrCaseTypeInfo) || !count($arrCaseTypeInfo)) {
            $caseTypeTerm      = $this->_company->getCurrentCompanyDefaultLabel('case_type');
            $arrGlobalErrors[] = $this->_tr->translate('Please select') . ' ' . $caseTypeTerm;
        }

        $booCorrectExtraFields = true;
        $arrExtraFields        = array();
        $arrExtraFieldsIds     = $this->getStaffResponsibleFieldsUsedByCompany();
        $arrCompanyCaseFields  = $this->_clients->getFields()->getCompanyFields($companyId);
        foreach ($arrExtraFieldsIds as $extraFieldId) {
            $arrFieldInfo = $this->_clients->getFields()->getFieldId($arrCompanyCaseFields, $extraFieldId);
            if (!$arrFieldInfo['success']) {
                $arrGlobalErrors[] = $arrFieldInfo['result'];
                break;
            } else {
                $fieldId                  = 'field-' . $arrFieldInfo['result'];
                $arrExtraFields[$fieldId] = $this->findParam($fieldId);
                if (empty($arrExtraFields[$fieldId])) {
                    $booCorrectExtraFields = false;
                }
            }
        }
        $view->setVariable('arrExtraFields', $arrExtraFields);


        if(!$booCorrectExtraFields) {
            $arrGlobalErrors[] = $this->_tr->translate('You must select all Staff Responsible');
        }


        if (count($arrGlobalErrors)) {
            return $this->forward()->dispatch(
                ImportClientsController::class,
                array(
                    'action' => 'step2',
                    'global_errors' => $arrGlobalErrors,
                    'file_name' => $fileName,
                    'import_client_type' => $clientType,
                    'import_case_type' => $caseType
                )
            );
        }

        $arrCaseTypes = $this->_clients->getCaseTemplates()->getTemplates($companyId, false, null, true);
        $view->setVariable('arrCaseTypes', $arrCaseTypes);

        // get fields
        $storedFiles = $this->_files->getClientsXLSFilesList($companyId);

        $filePath = $this->_files->getClientsXLSPath($companyId) . '/' . $fileName;

        $fields = array();

        $booFileExists = $this->_auth->isCurrentUserCompanyStorageLocal() ? is_file($filePath) : $this->_files->getCloud()->checkObjectExists($filePath);
        if(isset($storedFiles[$fileName]) && $booFileExists) {
            $data = $this->getFieldsFromXLS($filePath, $companyId);
            $view->setVariable('number_of_client_rows', count($data));

            $view->setVariable('xlsFields', array_key_exists(1, $data) ? array_keys($data[1]) : array());
            $view->setVariable('dbFields', $this->getPreparedFieldsFromDb($companyId, $clientType, $caseType));

            foreach ($view->getVariable('xlsFields') as $xlsColumnNumber => $xlsColNum) {
                $shortest = -1;
                foreach ($view->getVariable('dbFields') as $id => $dbField) {
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
                            $shortest = $lev;
                            $field['xlsField'] = $xlsColNum;
                            $field['dbField'] = $dbField;
                            $field['selected'] = 'selected="selected"';
                            $field['class'] = 'field-naming-error';
                            $fields[$xlsColumnNumber] = $field;
                        }
                    }
                }
            }

            $importErrors = $this->params('import_errors');
            $view->setVariable('importErrors', $importErrors);
        } else {
            return $this->forward()->dispatch(
               ImportClientsController::class,
               array(
                   'action' => 'step1',
                   'select_file_error' => 1,
                   'select_file_message' => $this->_tr->translate('Incorrectly selected import file.')
               )
           );
        }

        $view->setVariable('fields', $fields);

        return $view;
    }

    /**
     * Check if incoming params are correct,
     * Run clients importing (creation)
     */
    public function step4Action() {
        $view = new ViewModel();

        set_time_limit(60 * 60); // 1 hour
        ini_set('memory_limit', '512M');

        try {
            $this->layout()->setVariable('useJQuery', true);
            $this->layout()->setVariable('title', $this->_tr->translate('Import Clients: Step #4'));
            $view->setVariable('req', Settings::filterParamsArray($this->findParams(), $this->_filter));

            $page = $this->findParam('page', -1);
            $page = is_numeric($page) ? $page : 0;

            $cacheId = 'import_clients_' . $this->_auth->getCurrentUserId();
            if ($page > 0) {
                if (!($arrSavedCache = $this->_cache->getItem($cacheId))) {
                    throw new Exception('Cache was not loaded');
                } else {
                    $fileName                 = $arrSavedCache['file_name'];
                    $fieldsToMap              = $arrSavedCache['fields_to_map'];
                    $arrPost                  = $arrSavedCache['post_params'];
                }
            } else {
                $fileName    = $this->_filter->filter($this->findParam('file_name'));
                $fieldsToMap = array_keys($this->findParam('fields_to_map', array()));
                $arrPost     = array();
            }

            $view->setVariable('fileName', $fileName);
            $companyId       = $this->_auth->getCurrentUserCompanyId();
            $divisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();

            $processAtOnce = 10;

            // Get company admin id
            $companyAdminId = $this->_company->getCompanyAdminId($companyId);
            if (empty($companyAdminId)) {
                exit($this->_tr->translate('Incorrect Company Admin Id'));
            }

            $applicantTypeId = 0;
            $caseTypeId      = ($page > 0) ? $arrPost['import_case_type'] : $this->_filter->filter($this->findParam('import_case_type'));
            $view->setVariable('caseType', $caseTypeId);
            $clientType      = ($page > 0) ? $arrPost['import_client_type'] : $this->_filter->filter($this->findParam('import_client_type'));
            $view->setVariable('clientType', $clientType);

            // Get fields list
            $arrCompanyCaseFields = $this->_clients->getFields()->getCompanyFields($companyId);
            if(!is_array($arrCompanyCaseFields) || empty($arrCompanyCaseFields))
                exit($this->_tr->translate('There are no company fields (for case)'));

            // For first import clear the cache
            if(empty($page)) {
                $this->_clearCache($companyId);
            }

            $filePath = $this->_files->getClientsXLSPath($companyId) . '/' . $fileName;
            $this->getFieldsFromXLS($filePath, $companyId);

            // Parse and save in cache
            if (!($arrSheets = $this->_cache->getItem('import_sheets_' . $companyId))) {
                throw new Exception('xls file was not read');
            }

            if(!is_array($arrSheets) || empty($arrSheets))
                exit($this->_tr->translate('Not correct information'));

            $arrDependentFields = $this->_clients->getFields()->getDependantFields();

            // First sheet is used for client's info
            $sheet = $arrSheets[0];

            // Load headers
            $colIndex = 1;
            $arrRowHeaders = array();
            while ($colIndex <= $sheet['numCols']) {
                $arrRow = $sheet['cells'][1];

                if (isset($arrRow[$colIndex]))
                    $arrRowHeaders[$colIndex] = trim($arrRow[$colIndex] ?? '');

                $colIndex++;
            }

            // Load each client's info
            $arrClientsInfo = array();
            for ($rowIndex = 2; $rowIndex <= count($sheet['cells']); $rowIndex++) {
                $colIndex = 1;
                while ($colIndex <= $sheet['numCols']) {
                    $arrRow   = $sheet['cells'][$rowIndex];

                    if (isset($arrRowHeaders[$colIndex]) && !empty($arrRowHeaders[$colIndex])) {
                        $columnId = $arrRowHeaders[$colIndex];

                        $arrClientsInfo[$rowIndex][$columnId] = isset($arrRow[$colIndex]) ? trim($arrRow[$colIndex]) : '';
                    }

                    $colIndex++;
                }
            }

            // Prepare and run import for each client record
            $importErrors     = array();
            $currentClientNum = 1;

            $booHasErrors    = false;
            $arrAllClients   = array();
            $arrGlobalErrors = array();

            // Calculate start and finish
            $user_start  = (int)(($page > 0) ? $arrPost['index_start'] - 2 : $this->findParam('index_start') - 2);
            $user_finish = (int)(($page > 0) ? $arrPost['index_finish'] - 2 : $this->findParam('index_finish') - 2);

            if ($user_start > $user_finish) {
                $arrGlobalErrors[] = $this->_tr->translate('Start row must be less than or equal to finish row');
                $arrClientsInfo  = array();
            } else {
                $arrClientsInfo = array_merge(array_slice($arrClientsInfo, $user_start, $user_finish - $user_start + 1), array());
            }


            // For RBC company get the ids of fields:
            // 1. column id identifier (identifies client)
            // 2. field id where log will be saved
            // in the future we can load these fields from the settings
            $identifierFieldId = 0;
            $fieldIdToSaveLog  = 0;
            if ($companyId == 866) {
                $identifierFieldId = 70432; // persNo
                $fieldIdToSaveLog  = 69183; // Comments_Notes_LMO
            }

            $clientTypeId    = $this->_clients->getMemberTypeIdByName($clientType);
            $contactTypeId   = $this->_clients->getMemberTypeIdByName('internal_contact');

            $arrCompanyEmployerFields = $this->_clients->getApplicantFields()->getCompanyFields($companyId, $this->_clients->getMemberTypeIdByName('employer'));
            $arrCompanyIndividualFields = $this->_clients->getApplicantFields()->getCompanyFields($companyId, $this->_clients->getMemberTypeIdByName('individual'));

            if(!is_array($arrCompanyEmployerFields) || empty($arrCompanyEmployerFields))
                exit($this->_tr->translate('There are no company fields (for client)'));

            if(!$clientTypeId) {
                $arrGlobalErrors[] = $this->_tr->translate('You must select <i>Client type</i>');
            }

            if(!$caseTypeId) {
                $caseTypeTerm      = $this->_company->getCurrentCompanyDefaultLabel('case_type');
                $arrGlobalErrors[] = $this->_tr->translate('You must select <i>' . $caseTypeTerm . '</i>');
            }

            // Get RMA + staff responsible fields
            $booCorrectExtraFields        = true;
            $arrExtraFields               = array();
            $arrExtraFieldsGroupedByNames = array();
            $arrExtraFieldsIds            = $this->getStaffResponsibleFieldsUsedByCompany();
            foreach ($arrExtraFieldsIds as $extraFieldId) {
                $arrFieldInfo = $this->_clients->getFields()->getFieldId($arrCompanyCaseFields, $extraFieldId);
                if (!$arrFieldInfo['success']) {
                    $arrGlobalErrors[] = $arrFieldInfo['result'];
                    break;
                } else {
                    $fieldId                                     = 'field-' . $arrFieldInfo['result'];
                    $arrExtraFieldsGroupedByNames[$extraFieldId] = $arrExtraFields[$fieldId] = ($page == 0) ? $this->findParam($fieldId) : $arrPost[$fieldId];
                    if (empty($arrExtraFields[$fieldId])) {
                        $booCorrectExtraFields = false;
                    }
                }
            }
            $view->setVariable('arrExtraFields', $arrExtraFields);

            if (!$booCorrectExtraFields) {
                $arrGlobalErrors[] = $this->_tr->translate('You must select all Staff Responsible');
            }


            // Array of fields with valid names from db
            $arrDbFields = array();
            foreach($fieldsToMap as $xlsFieldNumber) {
                $fieldNumber = 'excel_field_' . ((int)$xlsFieldNumber);
                $arrDbFields[$xlsFieldNumber] = $page < 1 ? $this->findParam($fieldNumber) : $arrPost[$fieldNumber];
            }

            $arrDbFieldsFlipped = array_flip($arrDbFields);


            // Prepare default fields, which must be presented in Excel file
            // If they are not there - we'll use default or selected by user
            $arrDefaultValues = array(
                'CASE: ' . 'Client_file_status' => 'Active',
                'IA: ' . 'disable_login'        => 'Disabled',
                'EM: ' . 'disable_login'        => 'Disabled',
            );

            // Load RMA and Staff Responsible fields data from Excel file,
            // If there is no such data - use from default (selected by user)
            foreach ($arrExtraFieldsGroupedByNames as $extraFieldId => $extraFieldVal) {
                $arrDefaultValues['CASE: ' . $extraFieldId] = $extraFieldVal;
            }

            // Add default fields and data (as they were entered in Excel file)
            foreach ($arrDefaultValues as $arrDefaultFieldId => $arrDefaultFieldValue) {
                $booFound = false;
                foreach ($arrDbFields as $strFieldId) {
                    if($arrDefaultFieldId == $strFieldId) {
                        $booFound = true;
                        break;
                    }
                }

                if(!$booFound) {
                    $newKey = (is_array($arrDbFields) && !empty($arrDbFields)) ? max(array_keys($arrDbFields)) + 1 : 0;
                    $arrDbFields[$newKey] = $arrDefaultFieldId;

                    foreach($arrClientsInfo as $key => $thisClient) {
                        $arrClientsInfo[$key][$arrDefaultFieldId] = $arrDefaultFieldValue;
                    }
                }
            }

            $arrCaseTemplateInfo = $this->_clients->getCaseTemplates()->getTemplateInfo($caseTypeId);
            $booCreateEmployerAndIndividual = false;
            $arrClientTypesToCreate = array($clientType);
            if(is_array($arrCaseTemplateInfo) && count($arrCaseTemplateInfo)) {
                if ($clientType == 'employer' && $arrCaseTemplateInfo['client_type_needs_ia'] == 'Y') {
                    $booCreateEmployerAndIndividual = true;
                    $arrClientTypesToCreate[] = 'individual';
                }
            }


            // Check if required fields were selected
            if($clientType == 'employer') {
                foreach ($arrCompanyEmployerFields as $arrCompanyEmployerFieldInfo) {
                    $fieldPrefix = 'EM: ' . $arrCompanyEmployerFieldInfo['applicant_field_unique_id'];
                    if ($arrCompanyEmployerFieldInfo['required'] == 'Y' && $arrCompanyEmployerFieldInfo['repeatable'] != 'Y' && !in_array($fieldPrefix, $arrDbFields)) {
                        $arrGlobalErrors[] = sprintf(
                            $this->_tr->translate('You must select required DB Field <i>%s</i>'),
                            $fieldPrefix
                        );
                    }
                }
            }

            if($clientType == 'individual' || $booCreateEmployerAndIndividual) {
                foreach ($arrCompanyIndividualFields as $arrCompanyIndividualFieldInfo) {
                    $fieldPrefix = 'IA: ' . $arrCompanyIndividualFieldInfo['applicant_field_unique_id'];
                    if ($arrCompanyIndividualFieldInfo['required'] == 'Y' && $arrCompanyIndividualFieldInfo['repeatable'] != 'Y'  && !in_array($fieldPrefix, $arrDbFields)) {
                        $arrGlobalErrors[] = sprintf(
                            $this->_tr->translate('You must select required DB Field <i>%s</i>'),
                            $fieldPrefix
                        );
                    }
                }
            }


            if(!count($arrGlobalErrors)) {
                foreach ($arrClientsInfo as $thisClient) {
                    $xlsFields = array_keys($thisClient);

                    // Prepare client and case data
                    $arrClientInternalContacts = array();
                    $arrClientData             = array();
                    $arrCaseData               = array();
                    $arrCaseDependents         = array();

                    foreach ($arrDbFields as $xlsColNum => $strFieldId) {
                        if (preg_match('/^(EM|IA|CASE|CASE SPOUSE|CASE DEPENDENT \d+): (.*)$/', $strFieldId, $regs)) {
                            $realFieldId = $regs[2];

                            // Use default value even if there is an empty column
                            if(array_key_exists($realFieldId, $arrExtraFieldsGroupedByNames) && $thisClient[$xlsFields[$xlsColNum]] == '') {
                                $thisClient[$xlsFields[$xlsColNum]] = $arrExtraFieldsGroupedByNames[$realFieldId];
                            }

                            switch ($regs[1]) {
                                case 'EM':
                                case 'IA':
                                    $arrClientFields = $regs[1] == 'EM' ? $arrCompanyEmployerFields : $arrCompanyIndividualFields;
                                    $thisClientType = $regs[1] == 'EM' ? 'employer' : 'individual';
                                    foreach ($arrClientFields as $arrParentClientFieldInfo) {
                                        if ($arrParentClientFieldInfo['applicant_field_unique_id'] == $realFieldId) {
                                            $arrCheckResult = $this->_clients->getFields()->getFieldValue($arrClientFields, $realFieldId, $thisClient[$xlsFields[$xlsColNum]], $currentClientNum, $companyId, false);

                                            if ($arrCheckResult['error']) {
                                                $importErrors[$xlsColNum][] = '<div>' . $arrCheckResult['error-msg'] . '</div>';
                                            }

                                            $fieldVal = $arrCheckResult['result'] ?? $arrCheckResult;

                                            // Group fields by parent client type
                                            // i.e. internal contact info and main client info
                                            if ($arrParentClientFieldInfo['member_type_id'] == $contactTypeId || $arrParentClientFieldInfo['contact_block'] == 'Y') {
                                                if (!array_key_exists($thisClientType, $arrClientInternalContacts)) {
                                                    $arrClientInternalContacts[$thisClientType] = array();
                                                }

                                                if (!array_key_exists($arrParentClientFieldInfo['applicant_block_id'], $arrClientInternalContacts[$thisClientType])) {
                                                    $arrClientInternalContacts[$thisClientType][$arrParentClientFieldInfo['applicant_block_id']] = array(
                                                        'parent_group_id' => array(),
                                                        'data'            => array()
                                                    );
                                                }

                                                if (!in_array($arrParentClientFieldInfo['group_id'], $arrClientInternalContacts[$thisClientType][$arrParentClientFieldInfo['applicant_block_id']]['parent_group_id'])) {
                                                    $arrClientInternalContacts[$thisClientType][$arrParentClientFieldInfo['applicant_block_id']]['parent_group_id'][] = $arrParentClientFieldInfo['group_id'];
                                                }

                                                $arrClientInternalContacts[$thisClientType][$arrParentClientFieldInfo['applicant_block_id']]['data'][] = array(
                                                    'field_id'        => $arrParentClientFieldInfo['applicant_field_id'],
                                                    'field_unique_id' => $arrParentClientFieldInfo['applicant_field_unique_id'],
                                                    'value'           => $fieldVal,
                                                    'row'             => 0,
                                                    'row_id'          => 0
                                                );
                                            } else {
                                                $arrClientData[$thisClientType][] = array(
                                                    'field_id'        => $arrParentClientFieldInfo['applicant_field_id'],
                                                    'field_unique_id' => $arrParentClientFieldInfo['applicant_field_unique_id'],
                                                    'value'           => $fieldVal,
                                                    'row'             => 0,
                                                    'row_id'          => 0
                                                );
                                            }

                                            break;
                                        }
                                    }
                                    break;

                                case 'CASE SPOUSE':
                                    $arrCheckResult = $this->_getDependentFieldValue($arrDependentFields, $realFieldId, $thisClient[$xlsFields[$xlsColNum]], $currentClientNum);
                                    if ($arrCheckResult['error']) {
                                        $importErrors[$xlsColNum][] = $arrCheckResult['error-msg'];
                                    }

                                    $value = $arrCheckResult['result'] ?? $arrCheckResult;
                                    if($value != '') {
                                        $arrCaseDependents[0]['relationship'] = 'spouse';
                                        $arrCaseDependents[0][$realFieldId] = $value;
                                    }

                                    break;

                                case 'CASE DEPENDENT 1':
                                case 'CASE DEPENDENT 2':
                                case 'CASE DEPENDENT 3':
                                case 'CASE DEPENDENT 4':
                                case 'CASE DEPENDENT 5':
                                    if (preg_match('/^CASE DEPENDENT (\d+)$/', $regs[1], $dependentRegs)) {
                                        $dependentNum = $dependentRegs[1];

                                        $arrCheckResult = $this->_getDependentFieldValue($arrDependentFields, $realFieldId, $thisClient[$xlsFields[$xlsColNum]], $currentClientNum);
                                        if ($arrCheckResult['error']) {
                                            $importErrors[$xlsColNum][] = $arrCheckResult['error-msg'];
                                        }

                                        $value = $arrCheckResult['result'] ?? $arrCheckResult;
                                        if($value != '') {
                                            $arrCaseDependents[$dependentNum][$realFieldId] = $value;
                                        }
                                    }
                                    break;

                                case 'CASE':
                                default:
                                    $arrFieldInfo = $this->_clients->getFields()->getFieldId($arrCompanyCaseFields, $realFieldId);
                                    if (!$arrFieldInfo['success']) {
                                        $importErrors[$xlsColNum][] = $arrFieldInfo['result'];
                                    } else {
                                        $fieldId        = $arrFieldInfo['result'];
                                        $arrCheckResult = $this->_clients->getFields()->getFieldValue($arrCompanyCaseFields, $realFieldId, $thisClient[$xlsFields[$xlsColNum]], $currentClientNum, $companyId);

                                        if ($arrCheckResult['error']) {
                                            $importErrors[$xlsColNum][] = '<div>' . $arrCheckResult['error-msg'] . '</div>';
                                        }

                                        $arrCaseData[$fieldId] = $arrCheckResult['result'] ?? $arrCheckResult;
                                    }
                                break;
                            }
                        }
                    }

                    $arrThisClientDependents = array();
                    if(count($arrCaseDependents)) {
                        $row = 0;
                        foreach ($arrCaseDependents as $arrCaseDependentInfo) {
                            if(!is_array($arrCaseDependentInfo) || !array_key_exists('relationship', $arrCaseDependentInfo)) {
                                $arrGlobalErrors[] = sprintf(
                                    $this->_tr->translate('Each dependent must have assigned <i>%s</i> field'),
                                    'Relation'
                                );
                            }

                            $arrCaseDependentInfo['line'] = $row++;
                            $arrThisClientDependents[] = $arrCaseDependentInfo;
                        }
                    }

                    $arrThisClientNotes = array();

                    // Client form fields + data
                    $arrCaseInfo = array(
                        'client_type_id'     => $caseTypeId,
                        'added_by_member_id' => $companyAdminId,
                        'fileNumber'         => str_replace(' ', '', $this->_clients->getFields()->getFieldValue($arrCompanyCaseFields, 'file_number', @$thisClient[@$xlsFields[@$arrDbFieldsFlipped['CASE: ' . 'file_number']]], $currentClientNum, $companyId, true, true)),
                        // 'agent_id'           => $this->_clients->getFields()->getFieldValue($arrCompanyCaseFields, 'agent', @$thisClient[@$xlsFields[@$arrDbFieldsFlipped['agent']]], $currentClientNum, $companyId, true, true),
                    );

                    // Prepare all info
                    $arrParents = array();
                    $booAustralia = $this->_config['site_version']['version'] == 'australia';

                    foreach ($arrClientTypesToCreate as $clientTypeToCreate) {
                        if($clientTypeToCreate == 'employer') {
                            $strClientLastNameKey = 'entity_name';
                            $arrClientFields = $arrCompanyEmployerFields;
                            $fieldPrefix = 'EM: ';
                        } else {
                            $strClientLastNameKey = $booAustralia ? 'family_name' : 'last_name';
                            $arrClientFields = $arrCompanyIndividualFields;
                            $fieldPrefix = 'IA: ';
                        }

                        // Remember the office - will be used during case creation
                        $strOffice = $this->_clients->getFields()->getFieldValue($arrClientFields, 'office', @$thisClient[@$xlsFields[@$arrDbFieldsFlipped[$fieldPrefix . 'office']]], $currentClientNum, $companyId, false, true);

                        $strClientFirstNameKey = $booAustralia ? 'given_names' : 'first_name';
                        $arrParents[$clientTypeToCreate] = array(
                            // Parent client info
                            'arrParentClientInfo' => array(
                                'emailAddress'     => $this->_clients->getFields()->getFieldValue($arrClientFields, 'email', @$thisClient[@$xlsFields[@$arrDbFieldsFlipped[$fieldPrefix . 'email']]], $currentClientNum, $companyId, false, true),
                                'fName'            => $this->_clients->getFields()->getFieldValue($arrClientFields, $strClientFirstNameKey, @$thisClient[@$xlsFields[@$arrDbFieldsFlipped[$fieldPrefix . $strClientFirstNameKey]]], $currentClientNum, $companyId, false, true),
                                'lName'            => $this->_clients->getFields()->getFieldValue($arrClientFields, $strClientLastNameKey, @$thisClient[@$xlsFields[@$arrDbFieldsFlipped[$fieldPrefix . $strClientLastNameKey]]], $currentClientNum, $companyId, false, true),
                                'createdBy'        => $companyAdminId,
                                'memberTypeId'     => $this->_clients->getMemberTypeIdByName($clientTypeToCreate),
                                'applicantTypeId'  => $applicantTypeId,
                                'arrApplicantData' => $arrClientData[$clientTypeToCreate],
                                'arrOffices'       => array($strOffice),
                            ),

                            // Internal contact(s) info
                            'arrInternalContacts' => $arrClientInternalContacts[$clientTypeToCreate],
                        );
                    }


                    $arrNewClientInfo     = array(
                        'arrParents' => $arrParents,
                        'createdBy'  => $companyAdminId,

                        // Case info
                        'case' => array(
                            'members' => array(
                                'company_id' => $companyId,
                                'userType'   => $this->_clients->getMemberTypeIdByName('case'),
                                'regTime'    => time(),
                                'status'     => 1
                            ),

                            'members_divisions' => array(
                                'division_id' => $strOffice,
                            ),

                            'clients'                => $arrCaseInfo,

                            'client_form_data'       => $arrCaseData,

                            'client_form_dependents' => $arrThisClientDependents,

                            'u_notes'                => $arrThisClientNotes,
                        )
                    );

                    // Find the column which can identify already created case
                    $arrNewClientInfo['member_id'] = 0;
                    if (!empty($identifierFieldId) && array_key_exists($identifierFieldId, $arrCaseData)) {
                        $arrClientIds = $this->_clients->getClientIdBySavedField($companyId, $identifierFieldId, $arrCaseData[$identifierFieldId]);
                        if (count($arrClientIds)) {
                            $arrNewClientInfo['member_id'] = $arrClientIds[0];
                        }
                    }


                    // Not sure if we need check if client exists in DB already
                    /*if ($page == 0 && empty($arrNewClientInfo['member_id']) && $this->_clients->checkExistingClient($arrNewClientInfo['arrParentClientInfo']['lName'], $arrNewClientInfo['arrParentClientInfo']['fName'], $companyId, in_array('CASE: ' . 'file_number', $arrDbFields) ? $arrNewClientInfo['case']['clients']['fileNumber'] : '')) {
                        $arrGlobalErrors[] = sprintf(
                            $this->_tr->translate('Client <i>%s</i> already exists'),
                            $arrNewClientInfo['arrParentClientInfo']['members']['lName'] . ' ' . $arrNewClientInfo['arrParentClientInfo']['members']['fName']
                        );
                    }*/

                    if (count($importErrors))
                        $booHasErrors = true;

                    $arrAllClients[] = $arrNewClientInfo;

                    $currentClientNum++;
                }
            }

            $booAtLeastOneClientCreationFailed = false;
            if (!$booHasErrors && count($arrGlobalErrors) == 0) {
                $clientsCount = count($arrAllClients);

                $rowStart = $page * $processAtOnce;
                $rowEnd   = $rowStart + $processAtOnce;

                // run import!
                $log = '';
                for ($i = $rowStart; $i < min($rowEnd, count($arrAllClients)); $i++) {
                    $arrClientInfo = $arrAllClients[$i];
                    $fileNum       = empty($arrClientInfo['case']['clients']['fileNumber']) ? '<i>empty</i>' : '# ' . $arrClientInfo['case']['clients']['fileNumber'];

                    if (empty($arrClientInfo['member_id'])) {
                        // Create new client (with internal contact(s)) + case
                        $arrResult = $this->_clients->createClient($arrClientInfo, $companyId, $divisionGroupId, false, true);
                        $strError  = $arrResult['strError'];
                        $caseId    = $arrResult['caseId'];

                        if (empty($strError)) {
                            // Create new client folders
                            $this->_files->mkNewMemberFolders($caseId, $companyId, $this->_company->isCompanyStorageLocationLocal($companyId));

                            $log .= '<div style="color:green;">' . $this->_tr->translate('Created client with case with file num ') . $fileNum . '</div>';
                        } else {
                            $log .= '<div style="color:red;">' . $this->_tr->translate('Creation failed for row #') . ($i + 1);

                            $booAtLeastOneClientCreationFailed = true;
                        }
                    } elseif ($this->_clients->updateClient($arrClientInfo, $companyId, $arrCompanyCaseFields, $fieldIdToSaveLog)) {
                        $log .= '<div style="color:green;">' . $this->_tr->translate('Updated case with file num ') . $fileNum . '</div>';
                    } else {
                        $log .= '<div style="color:red;">' . $this->_tr->translate('Update failed for row #') . ($i + 1);

                        $booAtLeastOneClientCreationFailed = true;
                    }
                }

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
            }


            if (!empty($importErrors) || count($arrGlobalErrors)) {
                $arrParams = array(
                    'action'             => 'step3',
                    'global_errors'      => array_unique($arrGlobalErrors),
                    'import_errors'      => $importErrors,
                    'file_name'          => $view->getVariable('fileName'),
                    'import_client_type' => $view->getVariable('clientType'),
                    'import_case_type'   => $view->getVariable('caseType')
                );

                foreach ($arrExtraFields as $extraFieldId => $extraFieldVal) {
                    $arrParams[$extraFieldId] = $extraFieldVal;
                }

                return $this->forward()->dispatch(
                    ImportClientsController::class,
                    $arrParams
                );
            }

            $view->setVariable('percent', 100);
            if ($rowEnd < $clientsCount && !$booAtLeastOneClientCreationFailed) {
                if ($page < 1) {
                    $arrSavedCache = [
                        'file_name'     => $view->getVariable('fileName'),
                        'fields_to_map' => $fieldsToMap,
                        'post_params'   => Settings::filterParamsArray($this->findParams(), $this->_filter),
                    ];

                    $this->_cache->setItem($cacheId, $arrSavedCache);
                }

                $view->setVariable('percent', round($rowEnd / $clientsCount * 100));
                $view->setVariable('commandNextPage', (int)$page + 1);
            } else {
                $view->setVariable('commandNextPage', null);
            }


        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            $view->setVariables(
                [
                    'content' => 'Internal error.'
                ],
                true
            );
            $view->setTerminal(true);
            $view->setTemplate('layout/plain');
        }

        return $view;
    }

    public function deleteXlsFileAction()
    {
        $view = new JsonModel();
        $strError = '';
        try {
            $xls_id = (int) $this->findParam('xls_id');

            if(!$this->_files->deleteClientsXLSFile($xls_id)) {
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