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
use Notes\Service\Notes;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Common\Service\Settings;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

/**
 * Clients' notes importing controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class ImportClientNotesController extends BaseController
{

    /** @var Files */
    protected $_files;

    /** @var StripTags */
    private $_filter;

    /** @var Notes */
    protected $_notes;

    /** @var Clients */
    protected $_clients;

    /** @var Company */
    protected $_company;

    /** @var StorageAdapterFactoryInterface */
    protected $_cacheFactory;

    public function initAdditionalServices(array $services)
    {
        $this->_company      = $services[Company::class];
        $this->_clients      = $services[Clients::class];
        $this->_files        = $services[Files::class];
        $this->_notes        = $services[Notes::class];
        $this->_cacheFactory = $services[StorageAdapterFactoryInterface::class];

        $this->_filter = new StripTags();
    }

    /**
     * This is a stub
     * Automatically redirects to the step 1
     */
    public function indexAction()
    {
        return $this->redirect()->toUrl('/superadmin/import-client-notes/step1');
    }

    /**
     * Show list of uploaded Excel files,
     * Allow to run import
     */
    public function step1Action() {
        $view = new ViewModel();

        $title = $this->_tr->translate('Import Client Notes: Step #1');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        try {
            $fileId = (int)$this->findParam('file_id', 0);
            $view->setVariable('fileId', $fileId);

            $companyId = $this->_auth->getCurrentUserCompanyId();

            $this->_clearCache($companyId);

            $arrSavedFiles = $this->_files->getClientsXLSFilesList($companyId);
            $arrFiles = array();
            foreach ($arrSavedFiles as $fileId => $fileName) {
                $arrFiles[] = array(
                    'file_id'   => $fileId,
                    'file_name' => $fileName,
                );
            }

            $arrResult = array(
                'rows'       => $arrFiles,
                'totalCount' => count($arrFiles)
            );
            $view->setVariable('arrFiles', $arrResult);
            $strError = $this->params('errorMessage', '');
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $strError = empty($strError) ? '' : $strError;
        $view->setVariable('errorMessage', $strError);

        return $view;
    }

    /**
     * Show list of fields user needs to select,
     * Check if selected file is correct
     */
    public function step2Action() {
        $view = new ViewModel();

        $strError = '';
        try {
            $title = $this->_tr->translate('Import Client Notes: Step #2');
            $this->layout()->setVariable('title', $title);
            $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

            $fileId = (int)$this->findParam('file_id');
            $view->setVariable('fileId', $fileId);

            $companyId     = $this->_auth->getCurrentUserCompanyId();
            $arrSavedFiles = $this->_files->getClientsXLSFilesList($companyId);

            if(!isset($arrSavedFiles[$fileId])) {
                $strError = $this->_tr->translate('File was selected incorrectly.');
            }

            if(empty($strError)) {
                $filePath      = $this->_files->getClientsXLSPath($companyId) . '/' . $fileId;
                $booFileExists = $this->_auth->isCurrentUserCompanyStorageLocal() ? is_file($filePath) : $this->_files->getCloud()->checkObjectExists($filePath);

                if(!$booFileExists) {
                    $strError = $this->_tr->translate('File does not exists.');
                }
            }

            // Use default or already selected values or from post
            if(!empty($view->getVariable('arrDefaultSettings'))) {
                $arrDefaultSettings = $view->getVariable('arrDefaultSettings');
            } else {
                $dateFormatFull = $this->_settings->variable_get("dateFormatFull");

                $arrParams = Settings::filterParamsArray($this->findParams(), $this->_filter);
                $arrDefaultSettings = array(
                    'author'             => $arrParams['note_author'] ?? $this->_company->getCompanyAdminId($companyId),
                    'create_date'        => $arrParams['note_create_date'] ?? date($dateFormatFull),
                    'visible_to_clients' => $arrParams['note_visible_to_clients'] ?? 'N',
                    'rtl'                => $arrParams['note_rtl'] ?? 'N'
                );
            }

            $view->setVariable('arrDefaultSettings', $arrDefaultSettings);
            $view->setVariable('errorMessage', $this->params('errorMessage', ''));

            // Load users list (current user can access to)
            $arrMembersCanAccess = $this->_company->getCompanyMembersIds(
                $companyId,
                'admin_and_user',
                true
            );
            $arrCompanyUsers = $this->_members->getMembersInfo($arrMembersCanAccess, false);
            $view->setVariable('arrCompanyUsers', $arrCompanyUsers);

        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if(!empty($strError)) {
            return $this->forward()->dispatch(
                ImportClientNotesController::class,
                array(
                    'action' => 'step1',
                    'errorMessage' => $strError
                )
            );
        }

        return $view;
    }

    protected function getFieldsFromXLS($importFilePath, $companyId)
    {
        $cacheId = 'import_notes_sheets_' . $companyId;
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
                    $columnId = @$arrRowHeaders[$colIndex];

                    if ($columnId)
                        $arrClientsInfo[$rowIndex][$columnId] = isset($arrRow[$colIndex]) ? trim($arrRow[$colIndex]) : '';

                    $colIndex++;
                }
            }

            if (!empty($rowEnd) && $rowIndex>=$rowEnd)
                break;
        }

        return $arrClientsInfo;
    }

    protected function getPreparedFieldsFromDb() {
        return array(
            'author'             => 'Author',
            'case_number'        => 'Case file number',
            'create_date'        => 'Date of creation',
            'rtl'                => 'Direction (right to left)',
            'note'               => 'Message',
            'visible_to_clients' => 'Visible to clients',
        );
    }

    public function step3Action() {
        $view = new ViewModel();

        try {
            $title = $this->_tr->translate('Import Client Notes: Step #3');
            $this->layout()->setVariable('title', $title);
            $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

            $fileId = (int)$this->findParam('file_id');
            $view->setVariable('fileId', $fileId);
            $view->setVariable('errorMessage', $this->params('errorMessage', ''));

            $companyId     = $this->_auth->getCurrentUserCompanyId();
            $arrSavedFiles = $this->_files->getClientsXLSFilesList($companyId);

            $filePath = '';
            if(!isset($arrSavedFiles[$fileId])) {
                $arrErrors[] = $this->_tr->translate('File was selected incorrectly.');
            } else {
                $filePath = $this->_files->getClientsXLSPath($companyId) . '/' . $fileId;

                $booFileExists = $this->_auth->isCurrentUserCompanyStorageLocal() ? is_file($filePath) : $this->_files->getCloud()->checkObjectExists($filePath);

                if(!$booFileExists) {
                    $arrErrors[] = $this->_tr->translate('File does not exists.');
                }
            }

            $arrDefaultSettings = Settings::filterParamsArray(array(
                'author'             => $this->findParam('note_author'),
                'create_date'        => $this->findParam('note_create_date'),
                'visible_to_clients' => $this->findParam('note_visible_to_clients'),
                'rtl'                => $this->findParam('note_rtl')
            ), $this->_filter);
            $view->setVariable('arrDefaultSettings', $arrDefaultSettings);


            $arrErrors = array();

            $arrMembersCanAccess = $this->_company->getCompanyMembersIds(
                $companyId,
                'admin_and_user',
                true
            );
            if(!in_array($arrDefaultSettings['author'], $arrMembersCanAccess)) {
                $arrErrors[] = $this->_tr->translate('Author was selected incorrectly.');
            }

            $booDateCorrect = false;
            if(!empty($arrDefaultSettings['create_date'])) {
                $dateFormatFull = $this->_settings->variable_get("dateFormatFull");
                $booDateCorrect = Settings::isValidDateFormat($arrDefaultSettings['create_date'], $dateFormatFull);
            }

            if(!$booDateCorrect) {
                $arrErrors[] = $this->_tr->translate('"Date of note creation" was selected incorrectly.');
            }

            if(!in_array($arrDefaultSettings['visible_to_clients'], array('Y', 'N'))) {
                $arrErrors[] = $this->_tr->translate('"Visible to clients" option was selected incorrectly.');
            }

            if(!in_array($arrDefaultSettings['rtl'], array('Y', 'N'))) {
                $arrErrors[] = $this->_tr->translate('"Direction" was selected incorrectly.');
            }

            $strError = implode('<br/>', $arrErrors);

            $fields = array();
            if(empty($strError)) {
                $data = $this->getFieldsFromXLS($filePath, $companyId);
                $view->setVariable('number_of_client_rows', count($data));

                $view->setVariable('xlsFields', array_key_exists(1, $data) ? array_keys($data[1]) : array());
                $view->setVariable('dbFields', $this->getPreparedFieldsFromDb());

                foreach ($view->getVariable('xlsFields') as $xlsColumnNumber => $xlsColNum) {
                    $shortest = -1;
                    foreach ($view->getVariable('dbFields') as $id => $dbField) {
                        $field['id']       = $id;
                        $field['selected'] = '';
                        $field['class']    = ''; // contains error class. ($xlsField !== $dbField)
                        if ($xlsColNum == $dbField) {
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
            }
            $view->setVariable('fields', $fields);
            $view->setVariable('importErrors', $this->params('importErrors'));

        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        if (!empty($strError)) {
            return $this->forward()->dispatch(
                ImportClientNotesController::class,
                array(
                    'action' => 'step2',
                    'errorMessage' => $strError
                )
            );
        }

        return $view;
    }

    /**
     * Check if incoming params are correct,
     * Run notes importing (creation)
     */
    public function step4Action() {
        $view = new ViewModel();

        set_time_limit(60 * 60); // 1 hour
        ini_set('memory_limit', '512M');

        try {
            $title = $this->_tr->translate('Import Client Notes: Step #4');
            $this->layout()->setVariable('title', $title);
            $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

            $page = $this->findParam('page', -1);
            $page = is_numeric($page) ? $page : 0;

            $additionalOutput = '';

            $cacheId = 'import_client_notes_' . $this->_auth->getCurrentUserId();
            if ($page > 0) {
                if (!($arrSavedCache = $this->_cache->getItem($cacheId))) {
                    throw new Exception('Cache was not loaded');
                } else {
                    $fileId      = $arrSavedCache['file_id'];
                    $fieldsToMap = $arrSavedCache['fields_to_map'];
                    $arrPost     = $arrSavedCache['post_params'];
                }
            } else {
                $fileId      = (int)$this->findParam('file_id');
                $fieldsToMap = array_keys($this->findParam('fields_to_map', array()));
                $arrPost     = array();
            }

            $view->setVariable('fileId', $fileId);

            $companyId = $this->_auth->getCurrentUserCompanyId();

            $processAtOnce = 10;

            // For first import clear the cache
            if(empty($page)) {
                $this->_clearCache($companyId);
            }

            if (empty($fileId)) {
                return $this->forward()->dispatch(
                    ImportClientNotesController::class,
                    array(
                        'action' => 'step1',
                        'errorMessage' => $this->_tr->translate('Incorrectly selected file')
                    )
                );
            }

            $filePath = $this->_files->getClientsXLSPath($companyId) . '/' . $fileId;
            $this->getFieldsFromXLS($filePath, $companyId);
            
            // Parse and save in cache
            if (!($arrSheets = $this->_cache->getItem('import_notes_sheets_' . $companyId))) {
                throw new Exception('XLS file was not read');
            }

            if(!is_array($arrSheets) || empty($arrSheets)) {
                exit($this->_tr->translate('Not correct information'));
            }

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

            // Array of fields with valid names from db
            $arrDbFields = array();
            foreach($fieldsToMap as $xlsFieldNumber) {
                $fieldNumber = 'excel_field_' . ((int)$xlsFieldNumber);
                $arrDbFields[$xlsFieldNumber] = $page < 1 ? $this->findParam($fieldNumber) : $arrPost[$fieldNumber];
            }

            // Load each note's info
            $arrAllNotesToImport = array();
            for ($rowIndex = 2; $rowIndex <= count($sheet['cells']); $rowIndex++) {
                $colIndex = 1;
                while ($colIndex <= $sheet['numCols']) {
                    $arrRow   = $sheet['cells'][$rowIndex];
                    $columnId = @$arrRowHeaders[$colIndex];

                    if ($columnId && isset($arrDbFields[$colIndex - 1])) {
                        $arrAllNotesToImport[$rowIndex][$arrDbFields[$colIndex - 1]] = isset($arrRow[$colIndex]) ? trim($arrRow[$colIndex]) : '';
                    }

                    $colIndex++;
                }
            }

            // Prepare and run import for each client record
            $arrGlobalErrors = array();
            $arrRowErrors    = array();

            // Calculate start and finish
            $record_start  = (int)(($page > 0) ? $arrPost['index_start'] - 2 : $this->findParam('index_start') - 2);
            $record_finish = (int)(($page > 0) ? $arrPost['index_finish'] - 2 : $this->findParam('index_finish') - 2);

            if ($record_start > $record_finish) {
                $arrGlobalErrors[] = $this->_tr->translate('Start row must be less than or equal to finish row');
                $arrAllNotesToImport  = array();
            } else {
                $arrAllNotesToImport = array_merge(array_slice($arrAllNotesToImport, $record_start, $record_finish - $record_start + 1), array());
            }

            // Get default fields
            $arrDefaultSettings = array();
            $arrDefaultFieldIds = array(
                'author',
                'create_date',
                'visible_to_clients',
                'rtl'
            );
            foreach ($arrDefaultFieldIds as $extraFieldId) {
                $arrDefaultSettings[$extraFieldId] = ($page == 0) ? $this->findParam('note_' . $extraFieldId) : $arrPost['note_' . $extraFieldId];
            }
            $view->setVariable('arrDefaultSettings', $arrDefaultSettings);


            // Add default fields and data (as they were entered in excel file)
            // 'm/d/Y' - default excel format
            foreach ($arrDefaultSettings as $strDefaultFieldId => $strDefaultFieldValue) {
                $booFound = false;
                foreach ($arrDbFields as $strFieldId) {
                    if($strDefaultFieldId == $strFieldId) {
                        $booFound = true;
                        break;
                    }
                }

                if(!$booFound) {
                    $newKey = (is_array($arrDbFields) && !empty($arrDbFields)) ? max(array_keys($arrDbFields)) + 1 : 1;
                    $arrDbFields[$newKey] = $strDefaultFieldId;

                    if($strDefaultFieldId == 'create_date') {
                        $dateFormatFull = $this->_settings->variable_get('dateFormatFull');

                        if (Settings::isValidDateFormat($strDefaultFieldValue, $dateFormatFull)) {
                            $strDefaultFieldValue = $this->_settings->reformatDate($strDefaultFieldValue, $dateFormatFull, 'm/d/y');
                        }
                    }

                    foreach($arrAllNotesToImport as $key => $thisNote) {
                        $arrAllNotesToImport[$key][$strDefaultFieldId] = $strDefaultFieldValue;
                    }
                }
            }

            $arrCompanyCases = $this->_clients->getActiveClientsList(array(), false, $companyId, true);
            foreach ($arrAllNotesToImport as $arrNoteInfo) {
                if(!isset($arrNoteInfo['case_number'])) {
                    $arrGlobalErrors[] = 'Case number is a required field.';
                }

                if(!isset($arrNoteInfo['note'])) {
                    $arrGlobalErrors[] = 'Message is a required field.';
                }

                if(!isset($arrNoteInfo['author'])) {
                    $arrGlobalErrors[] = 'Author is a required field.';
                }

                if(!isset($arrNoteInfo['rtl'])) {
                    $arrGlobalErrors[] = 'Direction is a required field.';
                }

                if(!isset($arrNoteInfo['visible_to_clients'])) {
                    $arrGlobalErrors[] = '"Visible to clients" is a required field.';
                }
            }

            if(!count($arrGlobalErrors)) {
                $arrDbFieldsFlipped = array_flip($arrDbFields);

                $arrMembersCanAccess = $this->_company->getCompanyMembersIds(
                    $companyId,
                    'admin_and_user',
                    true
                );

                foreach ($arrAllNotesToImport as $row => $arrNoteInfo) {
                    if (isset($arrNoteInfo['case_number'])) {
                        $memberId = 0;
                        foreach ($arrCompanyCases as $arrCompanyCaseInfo) {
                            if ($arrCompanyCaseInfo['fileNumber'] == $arrNoteInfo['case_number']) {
                                $memberId = $arrCompanyCaseInfo['member_id'];
                                break;
                            }
                        }

                        if (!$memberId) {
                            $arrRowErrors[$arrDbFieldsFlipped['case_number']][] = sprintf(
                                '<div>Case not found by <em>%s</em> (client row #%d).</div>',
                                $arrNoteInfo['case_number'],
                                $row + 1
                            );
                        } else {
                            $arrAllNotesToImport[$row]['member_id'] = $memberId;
                            unset($arrAllNotesToImport[$row]['case_number']);
                        }
                    }

                    if (isset($arrNoteInfo['note']) && $arrNoteInfo['note'] == '') {
                        $arrRowErrors[$arrDbFieldsFlipped['note']][] = sprintf(
                            '<div>Message cannot be empty (client row #%d).</div>',
                            $row + 1
                        );
                    }

                    if (isset($arrNoteInfo['author']) && !in_array($arrNoteInfo['author'], $arrMembersCanAccess)) {
                        $arrRowErrors[$arrDbFieldsFlipped['author']][] = sprintf(
                            '<div>Author was not found (client row #%d).</div>',
                            $row + 1
                        );
                    }

                    if (isset($arrNoteInfo['create_date'])) {
                        $dateFormatFull = $this->_settings->variable_get('dateFormatFull');

                        if (Settings::isValidDateFormat($arrNoteInfo['create_date'], 'm/d/y')) {
                            $arrAllNotesToImport[$row]['create_date'] = $this->_settings->reformatDate($arrNoteInfo['create_date'], 'm/d/y', Settings::DATE_UNIX);
                        } else if (Settings::isValidDateFormat($arrNoteInfo['create_date'], $dateFormatFull)) {
                            $arrAllNotesToImport[$row]['create_date'] = $this->_settings->reformatDate($arrNoteInfo['create_date'], $dateFormatFull, Settings::DATE_UNIX);
                        } else {
                            $arrRowErrors[$arrDbFieldsFlipped['create_date']][] = sprintf(
                                '<div>Incorrect date <em>%s</em> (client row #%d).</div>',
                                $arrNoteInfo['create_date'],
                                $row + 1
                            );
                        }
                    }

                    if (isset($arrNoteInfo['visible_to_clients']) && !in_array($arrNoteInfo['visible_to_clients'], array('Y', 'N'))) {
                        $arrRowErrors[$arrDbFieldsFlipped['visible_to_clients']][] = sprintf(
                            '<div>Incorrect option <em>%s</em> (client row #%d).</div>',
                            $arrNoteInfo['visible_to_clients'],
                            $row + 1
                        );
                    }

                    if (isset($arrNoteInfo['rtl']) && !in_array($arrNoteInfo['rtl'], array('Y', 'N'))) {
                        $arrRowErrors[$arrDbFieldsFlipped['rtl']][] = sprintf(
                            '<div>Incorrect option <em>%s</em> (client row #%d).</div>',
                            $arrNoteInfo['rtl'],
                            $row + 1
                        );
                    }
                }
            }

            $notesCount = 0;
            $booAtLeastOneNoteCreationFailed = false;
            if (!count($arrRowErrors) && !count($arrGlobalErrors)) {
                $notesCount = count($arrAllNotesToImport);

                $rowStart = $page * $processAtOnce;
                $rowEnd   = $rowStart + $processAtOnce;


                // run import!
                for ($i = $rowStart; $i < min($rowEnd, $notesCount); $i++) {
                    try {
                        $booSuccess = $this->_notes->updateNote(
                            0,
                            (int)$arrAllNotesToImport[$i]['member_id'],
                            $arrAllNotesToImport[$i]['note'],
                            false,
                            $arrAllNotesToImport[$i]['visible_to_clients'] == 'Y',
                            0,
                            '',
                            $arrAllNotesToImport[$i]['rtl'] == 'Y',
                            $arrAllNotesToImport[$i]['create_date']
                        );
                    } catch (Exception $e) {
                        $booSuccess = false;
                        $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
                    }

                    if ($booSuccess) {
                        $additionalOutput .= '<div style="color:green;">' . $this->_tr->translate('Created note for row # ') . ($i + 1) . '</div>';
                    } else {
                        $additionalOutput                .= '<div style="color:red;">' . $this->_tr->translate('Creation failed for row #') . ($i + 1) . '</div>';
                        $booAtLeastOneNoteCreationFailed = true;
                    }
                }

                $view->setVariable('additionalOutput', $additionalOutput);
                if ($notesCount > $processAtOnce && $page > 0) {
                    $booContinueImporting = $rowEnd < $notesCount;

                    if (!$booContinueImporting) {
                        $this->_cache->removeItem($cacheId);
                    }

                    $arrResult = array(
                        'additionalOutput' => $additionalOutput,
                        'page'             => $page + 1,
                        'booContinue'      => $booContinueImporting,
                        'percent'          => $booContinueImporting ? round($rowEnd / $notesCount * 100) : 100
                    );
                    $view->setTerminal(true);
                    $view->setTemplate('layout/plain');
                    return $view->setVariable('content', Json::encode($arrResult));
                }
            }

            if (!empty($arrRowErrors) || count($arrGlobalErrors)) {
                $arrParams = array(
                    'action'       => 'step3',
                    'file_id'      => $view->getVariable('fileId'),
                    'errorMessage' => implode('<br/>', array_unique($arrGlobalErrors)),
                    'importErrors' => $arrRowErrors
                );

                foreach ($arrDefaultSettings as $extraFieldId => $extraFieldVal) {
                    $arrParams[$extraFieldId] = $extraFieldVal;
                }

                return $this->forward()->dispatch(
                    ImportClientNotesController::class,
                    $arrParams
                );
            }

            $view->setVariable('percent', 100);
            if ($rowEnd < $notesCount && !$booAtLeastOneNoteCreationFailed) {
                if ($page < 1) {
                    $arrSavedCache = [
                        'file_id'       => $view->getVariable('fileId'),
                        'fields_to_map' => $fieldsToMap,
                        'post_params'   => Settings::filterParamsArray($this->findParams(), $this->_filter),
                    ];

                    $this->_cache->setItem($cacheId, $arrSavedCache);
                }

                $view->setVariable('percent', round($rowEnd / $notesCount * 100));
                $view->setVariable('commandNextPage', (int)$page + 1);
            } else {
                $view->setVariable('commandNextPage', null);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            $view->setTerminal(true);
            $view->setTemplate('layout/plain');

            $view->setVariables(
                [
                    'contnet' => 'Internal error.'
                ],
                true
            );
        }

        return $view;
    }

    private function _clearCache($companyId) {
        $arrClearCache = array(
            'import_notes_sheets_' . $companyId,
        );
        foreach ($arrClearCache as $cacheId) {
            $this->_cache->removeItem($cacheId);
        }
    }


    public function getFilesAction() {
        $view = new JsonModel();
        try {
            $arrFiles = array();

            $companyId     = $this->_auth->getCurrentUserCompanyId();
            $arrSavedFiles = $this->_files->getClientsXLSFilesList($companyId);

            foreach ($arrSavedFiles as $fileId => $fileName) {
                $arrFiles[] = array(
                    'file_id'   => $fileId,
                    'file_name' => $fileName,
                );
            }

        } catch (Exception $e) {
            $arrFiles = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return Json result
        $arrResult = array(
            'rows'       => $arrFiles,
            'totalCount' => count($arrFiles)
        );
        return $view->setVariables($arrResult);
    }

}