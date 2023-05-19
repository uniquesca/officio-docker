<?php

namespace System\Controller;

#TODO: https://ipp.baystateconsulting.com/ippqboimporter/help/workplaceTransactionProImporterHelp.htm
use Clients\Service\Clients;
use Exception;
use Laminas\Filter\StripTags;
use Laminas\Session\Container;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Import\SpreadsheetExcelReader;
use Officio\Service\Company;
use Officio\Common\Service\Settings;
use Prospects\Service\CompanyProspects;
use Laminas\Validator\EmailAddress;

/**
 * Clients importing controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class ImportController extends BaseController
{

    /** @var Clients */
    protected $_clients;

    /** @var Company */
    protected $_company;

    /** @var CompanyProspects */
    protected $_companyProspects;

    private $_UPLOAD_DIR = '';

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_clients = $services[Clients::class];
        $this->_companyProspects = $services[CompanyProspects::class];

        $this->_UPLOAD_DIR = $this->_config['log']['path'] . DIRECTORY_SEPARATOR;
    }

    /**
     * @param array $arrFields
     * @param int $companyFieldId
     * @return int|void
     */
    private function _getFieldId($arrFields, $companyFieldId)
    {
        foreach ($arrFields as $fieldInfo) {
            if ($fieldInfo['company_field_id'] == $companyFieldId) {
                return (int)$fieldInfo['field_id'];
            }
        }

        // Field is incorrect in excel file
        $msg = sprintf('<div style="color: red;">Field <em>%s</em> was not found for this company</div>', $companyFieldId);
        exit($msg);
    }

    private function _parseDate($strDate, $strExplode = "/")
    {
        $formattedDate = '';
        $strDate = trim($strDate ?? '');
        if (!empty($strDate)) {
            list($month, $day, $year) = explode($strExplode, $strDate);

            if (!checkdate((int)$month, (int)$day, (int)$year)) {
                exit('Incorrect date - ' . $strDate);
            }

            $formattedDate = $year . '-' . $month . '-' . $day;
        }

        return $formattedDate;
    }

    /**
     * @param $arrFields
     * @param $strFieldId
     * @param $fieldVal
     * @param $clientNum
     * @param $booCheckFields
     * @return bool|int|mixed|string
     */
    private function _getFieldValue($arrFields, $strFieldId, $fieldVal, $clientNum, $booCheckFields)
    {
        $booCheckOptions = true;
        $booCheckEmail   = false;
        $booCheckPhone   = false;
        $arrFieldInfo    = [];
        foreach ($arrFields as $fieldInfo) {
            if ($fieldInfo['company_field_id'] == $strFieldId) {
                $arrFieldInfo = $fieldInfo;
                break;
            }
        }
        // Field is incorrect in excel file
        if (!count($arrFieldInfo)){
            $msg = sprintf('<div style="color: red;">Field <em>%s</em> was not found for this company</div>', $strFieldId);
            exit($msg);
        }

        $fieldId  = $arrFieldInfo['field_id'];
        $fieldVal = trim($fieldVal ?? '');
        if ($booCheckFields && $arrFieldInfo['required'] == 'Y' && (empty($fieldVal) && !is_numeric($fieldVal))) {
            $msg = sprintf('<div style="color: red;"> Empty value for <em>%s</em> (client #%d).</div>', $strFieldId, $clientNum);
            echo($msg);
        }

        if (empty($fieldVal)) {
            return $fieldVal;
        }

        $strResultValue = '';

        // Check for data correctness for speific field types
        switch ($arrFieldInfo['type']) {
            // Date
            case $this->_clients->getFieldTypes()->getFieldTypeId('date'):
            case $this->_clients->getFieldTypes()->getFieldTypeId('rdate'):
                // 'm/d/Y' - default excel format

                $dateFormatFull = $this->_settings->variable_get('dateFormatFull');

            if (Settings::isValidDateFormat($fieldVal, 'm/d/y')) {
                $strResultValue = $this->_settings->reformatDate($fieldVal, 'm/d/y', Settings::DATE_UNIX);
            } else {
                if (Settings::isValidDateFormat($fieldVal, $dateFormatFull)) {
                    $strResultValue = $this->_settings->reformatDate($fieldVal, $dateFormatFull, Settings::DATE_UNIX);
                } else {
                    $msg = sprintf('<div style="color: red;">Incorrect date <em>%s</em> for field <em>%s</em> (client #%d).</div>', $fieldVal, $strFieldId, $clientNum);
                    echo($msg);
                }
            }
                break;

            // Email
            case $this->_clients->getFieldTypes()->getFieldTypeId('email'):
                // TODO Fix this as $booCheckEmail is always false
                if ($booCheckFields && $booCheckEmail) {
                    $validator = new EmailAddress();
                    if (!$validator->isValid($fieldVal)) {
                        $msg = sprintf('<div style="color: red;">Client #%d: <em>%s</em> is incorrect.</div>', $clientNum, $fieldVal);
                        echo($msg);
                    }
                }

                $strResultValue = $fieldVal;
                break;

            // Combo
            case $this->_clients->getFieldTypes()->getFieldTypeId('combobox'):

                if ($strFieldId == 'Client_file_status') {
                    $strResultValue = $fieldVal == 'TRUE' ? 'Active' : '';
                } elseif ($booCheckOptions && $booCheckFields) {
                    // TODO Fix this as $booCheckOptions is always true
                    $id = 'import_fields_options';
                    if (!($arrCachedOptions = $this->_cache->getItem($id))) {
                        $arrOptions = $this->_clients->getFields()->getFieldOptions($fieldId);
                        $arrCachedOptions = array(
                            $fieldId => $arrOptions
                        );
                        $this->_cache->setItem($id, $arrCachedOptions);
                    } else {
                        if (is_array($arrCachedOptions) && array_key_exists($fieldId, $arrCachedOptions)) {
                            $arrOptions = $arrCachedOptions[$fieldId];
                        } else {
                            $arrOptions = $this->_clients->getFields()->getFieldOptions($fieldId);
                            $arrCachedOptions[$fieldId] = $arrOptions;
                            $this->_cache->setItem($id, $arrCachedOptions);
                        }
                    }


                    $booCorrectOption = false;
                    foreach ($arrOptions as $arrOption) {
                        if ($arrOption['option_name'] == $fieldVal) {
                            $booCorrectOption = true;
                            break;
                        }
                    }

                    if (!$booCorrectOption) {
                        $msg = sprintf('<div style="color: red;">Option <em>%s</em> is not correct for field <em>%s</em> (client #%d).</div>', $fieldVal, $strFieldId, $clientNum);
                        echo($msg);
                    }
                    $strResultValue = $fieldVal;
                } else {
                    $strResultValue = $fieldVal;
                }
                break;


            // Number
            case $this->_clients->getFieldTypes()->getFieldTypeId('number'):
                if (!$this->_clients->getFields()->validNumber($fieldVal)) {
                    $msg = sprintf('<div style="color: red;">Number <em>%s</em> is not correct for field <em>%s</em> (client #%d).</div>', $fieldVal, $strFieldId, $clientNum);
                    echo($msg);
                }
                $strResultValue = $fieldVal;
                break;


            // Phone
            case $this->_clients->getFieldTypes()->getFieldTypeId('phone'):
                // TODO Fix this as $booCheckPhone is always false
                if ($booCheckFields && $booCheckPhone && !$this->_clients->getFields()->validPhone($fieldVal)) {
                    $msg = sprintf('<div style="color: red;">Client #%d: <em>%s</em> is not correct for field <em>%s</em></div>', $clientNum, $fieldVal, $strFieldId);
                    echo($msg);
                }
                $strResultValue = $fieldVal;
                break;

            // Checkbox/Radio
            case $this->_clients->getFieldTypes()->getFieldTypeId('radio'):
            case $this->_clients->getFieldTypes()->getFieldTypeId('checkbox'):
                // If value is not 'no' , 'n', 'false' and not empty - that's means that it is checked
                if (!in_array(strtolower($fieldVal ?? ''), array('n', 'no', 'false'))) {
                    $strResultValue = 'yes';
                }
                break;

            // 'Country', 'Office', 'Assigned to'

            // Don't check these fields
            case $this->_clients->getFieldTypes()->getFieldTypeId('text'):
            case $this->_clients->getFieldTypes()->getFieldTypeId('memo'):
            case $this->_clients->getFieldTypes()->getFieldTypeId('password'):
            default:
                $strResultValue = $fieldVal;
                break;
        }

        return $strResultValue;
    }

    public function uploadToImportAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);

        if (isset($_FILES['xls']) && !empty($_FILES['xls'])) {
            $file = $_FILES['xls'];
            if ($file['type'] == 'application/vnd.ms-excel') {
                if (move_uploaded_file($file['tmp_name'], $this->_UPLOAD_DIR . $file['name'])) {
                    $sessionContainer                    = new Container('import');
                    $sessionContainer->lastProcessedFile = $this->_UPLOAD_DIR . $file['name'];

                    return $this->redirect()->toUrl($this->layout()->getVariable('baseUrl') . '/system/import/run-step/step/2');
                } else {
                    $view->setTemplate('layout/plain');
                    $view->setVariable('content', "Can't move file. (Try to change name)!");
                }
            } else {
                $view->setTemplate('layout/plain');
                $view->setVariable('content', 'Invalid file format! <br/> <a href="run-step?step=1">Go Back.</a>');
            }
        } else {
            return $this->redirect()->toUrl($this->layout()->getVariable('baseUrl') . '/system/import/run-step/step/1');
        }

        return $view;
    }

    protected function getPreparedFieldsFromDb($companyId)
    {
        $fields = $this->_clients->getFields()->getCompanyFields($companyId);
        $ret = array();
        foreach ($fields as $field) {
            $ret[$field['field_id']] = $field['company_field_id'];
        }
        return $ret;
    }

    public function runStepAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);

        $step = (int)$this->findParam('step', 1);
        switch ($step) {
            case 1:
                break;
            case 2:
                $sessionContainer = new Container('import');
                $view->setVariable('uploadResult', isset($sessionContainer->lastProcessedFile));
                $data = $this->getFieldsFromXLS($view->getVariable('uploadResult') ? $sessionContainer->lastProcessedFile : null);
                $view->setVariable('xlsFields', array_keys($data[1]));
                $view->setVariable('dbFields', $this->getPreparedFieldsFromDb(670));
                $fields = array();
                foreach ($view->getVariable('xlsFields') as $xlsField) {
                    $shortest = -1;
                    foreach ($view->getVariable('dbFields') as $id => $dbField) {
                        $field['id']       = $id;
                        $field['selected'] = '';
                        $field['class']    = ''; // contains error class. ($xlsField !== $dbField)
                        if ($xlsField == $dbField || strtolower($xlsField) == strtolower($dbField)) {
                            $field['xlsField'] = $xlsField;
                            $field['dbField']  = $dbField;
                            $field['selected'] = 'selected="selected"';
                            $fields[$xlsField] = $field;
                            break;
                        } else {
                            $lev = levenshtein($xlsField, $dbField);
                            if ($lev <= $shortest || $shortest < 0) {
                                $shortest          = $lev;
                                $field['xlsField'] = $xlsField;
                                $field['dbField'] = $dbField;
                                $field['selected'] = 'selected="selected"';
                                $field['class'] = 'field-naming-error';
                                $fields[$xlsField] = $field;
                            }
                        }
                    }
                }
                $view->setVariable('fields', $fields);
                break;
            case 3:
                $filter = new StripTags();
                $params = array_merge($this->params()->fromPost(), $this->params()->fromQuery());
                $view->setVariable('req', Settings::filterParamsArray($params, $filter));
                break;
        }

        return $view->setTemplate("system/import/step$step.phtml");
    }

    protected function getFieldsFromXLS($importFilePath = null)
    {
        if (is_null($importFilePath)) {
            return array();
        }
        $id = 'import_sheets';
        if (!($arrSheets = $this->_cache->getItem($id))) {
            $data = new SpreadsheetExcelReader();
            $data->setOutputEncoding('UTF-8');
            $data->read($importFilePath);

            $arrSheets = $data->sheets;

            $this->_cache->setItem($id, $arrSheets);
        }

        // *******************
        // First sheet is used for client's info
        // *******************
        $sheet = $arrSheets[0];

        // Load headers
        $arrRowHeaders = array();
        $colIndex = 1;
        while ($colIndex <= $sheet['numCols']) {
            $arrRow = $sheet['cells'][1];
            $arrRowHeaders[$colIndex] = isset($arrRow[$colIndex]) ? trim($arrRow[$colIndex]) : '';
            $colIndex++;
        }

        // Load each client's info
        $arrClientsInfo = array();
        $clientsCount = $sheet['numRows'];
        $rowStart = 0;
        for ($rowIndex = $rowStart; $rowIndex < $clientsCount; $rowIndex++) {
            if (empty($rowIndex)) {
            } else {
                $colIndex = 1;
                while ($colIndex <= $sheet['numCols']) {
                    $arrRow = $sheet['cells'][$rowIndex + 1];
                    $columnId = $arrRowHeaders[$colIndex];
                    $arrClientsInfo[$rowIndex][$columnId] = isset($arrRow[$colIndex]) ? trim($arrRow[$colIndex]) : '';

                    $colIndex++;
                }
            }

            if (!empty($rowEnd) && $rowIndex >= $rowEnd) {
                break;
            }
        }
        return $arrClientsInfo;
    }

    public function newIndexAction()
    {
        return $this->redirect()->toUrl('system/import/run-step/step/1');
    }

    private function _clearCache()
    {
        $arrClearCache = array(
            'import_sheets',
            'import_notes',
            'import_dependents',
            'import_fields_options'
        );

        foreach ($arrClearCache as $cacheId) {
            $this->_cache->removeItem($cacheId);
        }
    }

    // TODO PHP7 This should use plain view
    public function indexAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        error_reporting(E_ALL);
        set_time_limit(0);
        $importFilePath = $this->_config['log']['path'] . '/premiers.xls'; // @TODO: !Update
        if (!file_exists($importFilePath)) {
            $msg = sprintf('Import File <em>%s</em> does not exists', $importFilePath);
            return $view->setVariables(
                [
                    'content' => $msg
                ],
                true
            );
        }

        echo '<h1>' . date('c') . '</h1>';

        $page = $this->findParam('page', -1);
        if (!is_numeric($page) || $page < 0) {
            return $view->setVariables(
                [
                    'content' => 'No!'
                ],
                true
            );
        }

        //****************
        // Settings
        $booTestMode = true; // @TODO: !Update
        $booHasNotes = false;
        $booHasDependents = false;
        $booHasSponsors = false;
        $companyId = 738; // @TODO: !Update
        $divisionGroupId = 739; // @TODO: !Update
        //****************

        if ($booTestMode) {
            $processAtOnce = 5000;
        } else {
            $processAtOnce = 100;
        }


        $rowStart = $page * $processAtOnce;
        $rowEnd = $rowStart + $processAtOnce - 1;


        // Get company admin id
        $companyAdminId = $this->_company->getCompanyAdminId($companyId);
        if (empty($companyAdminId)) {
            return $view->setVariables(
                [
                    'content' => 'Incorrect Company Admin Id'
                ],
                true
            );
        }

        // Get client role id
        $companyClientRoleId = $this->_company->getCompanyClientRole($companyId);
        if (empty($companyClientRoleId)) {
            return $view->setVariables(
                [
                    'content' => 'Incorrect Client Role Id'
                ],
                true
            );
        }

        // Get fields list
        $arrCompanyFields = $this->_clients->getFields()->getCompanyFields($companyId);
        if (!is_array($arrCompanyFields) || empty($arrCompanyFields)) {
            return $view->setVariables(
                [
                    'content' => 'There are no company fields'
                ],
                true
            );
        }


        // For first import clear the cache
        if (empty($page)) {
            $this->_clearCache();
        }

        // Parse and save in cache
        $id = 'import_sheets';
        if (!($arrSheets = $this->_cache->getItem($id))) {
            $data = new SpreadsheetExcelReader();
            $data->setOutputEncoding('UTF-8');
            $data->read($importFilePath);

            $arrSheets = $data->sheets;

            $this->_cache->setItem($id, $arrSheets);
        }

        if (!is_array($arrSheets) || empty($arrSheets)) {
            return $view->setVariables(
                [
                    'content' => 'Not correct information'
                ],
                true
            );
        }

        // *******************
        // Load client's notes
        // *******************
        $arrClientNotes = array();
        if ($booHasNotes) {
            $id = 'import_notes';
            if (!($arrClientNotes = $this->_cache->getItem($id))) {
                $sheet = $arrSheets[1];

                for ($rowIndex = 1; $rowIndex < $sheet['numRows']; $rowIndex++) {
                    $arrRow = $sheet['cells'][$rowIndex + 1];
                    $arrClientNotes[$arrRow[2]][] = array(
                        'posted_by' => $arrRow[3],
                        'posted_on' => $this->_parseDate($arrRow[4]),
                        'note' => $arrRow[5],
                        'visible' => $arrRow[6]
                    );
                }

                $this->_cache->setItem($id, $arrClientNotes);
            }
        }


        // *******************
        // Load dependants list for clients
        // *******************
        $arrNotImportedDependents = array();
        if ($booHasDependents) {
            $id = 'import_dependents';
            if (!($arrClientDependants = $this->_cache->getItem($id))) {
                $sheet = $arrSheets[1];

                $arrChildren = array();
                $arrSpouse = array();
                for ($rowIndex = 1; $rowIndex < $sheet['numRows']; $rowIndex++) {
                    $arrRow = $sheet['cells'][$rowIndex + 1];

                    // We'll use these fields
                    $fileNumber = $arrRow[2];
                    $fullName = $arrRow[3];
                    $lastName = trim($arrRow[4] ?? '');
                    $firstName = trim($arrRow[5] ?? '');
                    $DOB = array_key_exists(6, $arrRow) ? $arrRow[6] : '';
                    $relation = array_key_exists(7, $arrRow) ? $arrRow[7] : '';

                    $booImportRecord = true;
                    switch ($relation) {
                        case 'child':
                            $line = $arrChildren[$fileNumber] = array_key_exists($fileNumber, $arrChildren) ? $arrChildren[$fileNumber] + 1 : 1;
                            break;

                        case 'spouse':
                            if (array_key_exists($fileNumber, $arrSpouse)) {
                                $arrNotImportedDependents[] = $fullName;
                                $booImportRecord = false;
                            } else {
                                $arrSpouse[$fileNumber][] = $fullName;
                            }
                            $line = 0;
                            break;

                        default:
                            // Empty, skip
                            $booImportRecord = false;
                            break;
                    }

                    if ($booImportRecord) {
                        $arrClientDependants[$fileNumber][] = array(
                            'line' => $line,
                            'lName' => $lastName,
                            'fName' => $firstName,
                            'DOB' => $this->_parseDate($DOB),
                        );
                    }
                }

                $this->_cache->setItem($id, $arrClientDependants);
            }
        }


        // *******************
        // Load sponsors for clients
        // *******************
        if ($booHasSponsors) {
            $id = 'import_sponsors';
            if (!($arrClientSponsors = $this->_cache->getItem($id))) {
                $sheet = $arrSheets[2];

                //$arrChildren = array();
                //$arrSpouse = array();
                for ($rowIndex = 1; $rowIndex < $sheet['numRows']; $rowIndex++) {
                    $arrRow = $sheet['cells'][$rowIndex + 1];

                    // We'll use these fields
                    $fileNumber = $arrRow[2];
                    $sponsorName = $arrRow[3];
                    $sponsorDOB = array_key_exists(4, $arrRow) ? $arrRow[4] : '';
                    $sponsorRelation = array_key_exists(5, $arrRow) ? $arrRow[5] : '';

                    $arrClientSponsors[$fileNumber][] = array(
                        'SponsorName' => $sponsorName,
                        'SponsorDOB' => $sponsorDOB,
                        'SponsorRelation' => $sponsorRelation,
                    );
                }

                $this->_cache->setItem($id, $arrClientSponsors);
            }
        }


        // *******************
        // First sheet is used for client's info
        // *******************
        $sheet = $arrSheets[0];

        // Load headers
        $arrRowHeaders = array();
        $colIndex = 1;
        while ($colIndex <= $sheet['numCols']) {
            $arrRow = $sheet['cells'][1];
            $arrRowHeaders[$colIndex] = isset($arrRow[$colIndex]) ? trim($arrRow[$colIndex]) : '';
            $colIndex++;
        }

        // Load each client's info
        $arrClientsInfo = array();
        $clientsCount = $sheet['numRows'];
        for ($rowIndex = $rowStart; $rowIndex < $clientsCount; $rowIndex++) {
            if (empty($rowIndex)) {
            } else {
                $colIndex = 1;
                while ($colIndex <= $sheet['numCols']) {
                    $arrRow = $sheet['cells'][$rowIndex + 1];
                    $columnId = $arrRowHeaders[$colIndex];
                    $arrClientsInfo[$rowIndex][$columnId] = isset($arrRow[$colIndex]) ? trim($arrRow[$colIndex]) : '';

                    $colIndex++;
                }
            }

            if (!empty($rowEnd) && $rowIndex >= $rowEnd) {
                break;
            }
        }

        if ($booTestMode) {
            $this->_log->debugToFile($arrClientsInfo, 0);
        }

        // Prepare and run import for each client record
        $currentClientNum = $rowStart + 1;
        foreach ($arrClientsInfo as $thisClient) {
            if (!$booHasNotes) {
                $arrThisClientNotes = array();
            } else {
                $arrThisClientNotes = array_key_exists($thisClient['client_key'], $arrClientNotes) ? $arrClientNotes[$thisClient['client_key']] : array();
            }

            $arrThisClientDependents = array();

            $arrClientData = array(
                $this->_getFieldId($arrCompanyFields, 'Client_file_status') => 'Active',
                $this->_getFieldId($arrCompanyFields, 'accounting') => 'user:72436', // @TODO: !Update
                $this->_getFieldId($arrCompanyFields, 'processing') => 'user:72436', // @TODO: !Update
                $this->_getFieldId($arrCompanyFields, 'sales_and_marketing') => 'user:72436', // @TODO: !Update
            );

            // @TODO: !Update
            $arrExcelFields = array(
                'date_client_signed',
                'p_coding',
                'phone_h',
                'payment_option',
                'email_1',
                'country_of_destination',
            );

            foreach ($arrExcelFields as $strFieldId) {
                $fieldId = $this->_getFieldId($arrCompanyFields, $strFieldId);
                $arrClientData[$fieldId] = $this->_getFieldValue(
                    $arrCompanyFields,
                    $strFieldId,
                    $thisClient[$strFieldId],
                    $currentClientNum,
                    $booTestMode
                );
            }


            $arrNewClientInfo = array(
                'members' => array(
                    'company_id' => $companyId,
                    'regTime'    => time(),
                    'userType'   => 3,
                    'status'     => 1,

                    'emailAddress' => $this->_getFieldValue($arrCompanyFields, 'email', $thisClient['primary_email'], $currentClientNum, $booTestMode),
                    'fName'        => $this->_getFieldValue($arrCompanyFields, 'first_name', $thisClient['first_name'], $currentClientNum, $booTestMode),
                    'lName'        => '_', //$this->_getFieldValue($arrCompanyFields, 'last_name', $thisClient['last_name'], $currentClientNum, $booTestMode, $companyId)
                ),

                'members_divisions' => array(
                    'division_id' => 1458 // @TODO: !Update
                ),

                'members_roles' => array(
                    'role_id' => $companyClientRoleId
                ),

                'clients' => array(
                    'added_by_member_id' => $companyAdminId,
                    'fileNumber' => '', // str_replace(' ', '', $this->_getFieldValue($arrCompanyFields, 'file_number', $thisClient['file_number'], $currentClientNum, $booTestMode, $companyId)),
                    'agent_id' => '', // $this->_getFieldValue($arrCompanyFields, 'agent', $thisClient['agent'], $currentClientNum, $booTestMode, $companyId),
                ),

                'u_notes' => $arrThisClientNotes,

                'client_form_dependents' => $arrThisClientDependents,

                'client_form_data' => $arrClientData
            );

            if ($booTestMode) {
                $this->_log->debugToFile("--- test mode:");
                $this->_log->debugToFile($arrNewClientInfo);
            }

            if (!$booTestMode) {
                $arrResult = $this->_clients->createClient($arrNewClientInfo, $companyId, $divisionGroupId);
                $strError = $arrResult['strError'];
                if (empty($strError)) {
                    $fileNum = empty($arrNewClientInfo['clients']['fileNumber']) ? ' UNKNOWN' : $arrNewClientInfo['clients']['fileNumber'];
                    echo 'Created client #' . $currentClientNum . ', file num#' . $fileNum . '<br />';
                } else {
                    echo('Creation failed for: <br/><pre>' . print_r($arrNewClientInfo, true) . '</pre>');
                    return $view->setVariables(
                        [
                            'content' => '<pre>' . print_r($thisClient, true) . '</pre>'
                        ],
                        true
                    );
                }
            }

            $currentClientNum++;
        }

        if (count($arrNotImportedDependents)) {
            echo sprintf(
                '<div style="color: orange;">Such dependents were not imported: %s</div>',
                implode(',<br/>', $arrNotImportedDependents)
            );
        }

        if (($rowStart + $processAtOnce < $clientsCount) && !$booTestMode) {
            $strResult = sprintf('<a href="%s/system/import/index?page=%d">Next Page &gt;&gt;</a>', $this->layout()->getVariable('baseUrl'), $page + 1);
        } else {
            $strResult = 'Done.';
        }

        $pagesCount = $booTestMode ? 1 : round($clientsCount / $processAtOnce);
        if ($pagesCount > 0 && !$booTestMode) {
            $pagesCount++;
        }
        echo sprintf(
            '<div style="padding-top: 5px; margin-top: 10px; border-top: 1px solid #000;">Page %d of %d. <span style="color: green;">%s</span></div>',
            $page + 1,
            $pagesCount,
            $strResult
        );
        return $view;
    }

    // TODO PHP7 This should use plain view
    public function prospectsAction()
    {
        $view = new ViewModel(
            [
                'content' => null
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        error_reporting(E_ALL);
        set_time_limit(0);
        $importFilePath = $this->_config['log']['path'] . '/bee international.xls'; // @TODO: !Update
        if (!file_exists($importFilePath)) {
            $msg = sprintf('Import File <em>%s</em> does not exists', $importFilePath);
            return $view->setVariables(
                [
                    'content' => $msg
                ],
                true
            );
        }

        echo '<h1>' . date('c') . '</h1>';

        $page = (int)$this->findParam('page', 0);
        $currentSheet = (int)$this->findParam('sheet', 0);
        if (!is_numeric($page) || $page < 0) {
            return $view->setVariables(
                [
                    'content' => 'set page at first!'
                ],
                true
            );
        }
        $page = is_numeric($page) ? $page : 0;

        //****************
        // Settings
        $booTestMode = false; // @TODO: !Update

        $companyId = 694; // BEE INTERNATIONAL  @TODO: !Update
        $divisionGroupId = 695; // @TODO: !Update
        //****************

        if ($booTestMode) {
            $processAtOnce = 10000000;
        } else {
            $processAtOnce = 50;
        }

        $rowStart = $page * $processAtOnce;
        $rowEnd = $rowStart + $processAtOnce - 1;

        // Get company admin id
        $companyAdminId = $this->_company->getCompanyAdminId($companyId);
        if (empty($companyAdminId)) {
            return $view->setVariables(
                [
                    'content' => 'Incorrect Company Admin Id'
                ],
                true
            );
        }

        // Get client role id
        $companyClientRoleId = $this->_company->getCompanyClientRole($companyId);
        if (empty($companyClientRoleId)) {
            return $view->setVariables(
                [
                    'content' => 'Incorrect Client Role Id'
                ],
                true
            );
        }

        // Get fields list
        $arrCompanyFields = $this->_clients->getFields()->getCompanyFields($companyId);
        if (!is_array($arrCompanyFields) || empty($arrCompanyFields)) {
            return $view->setVariables(
                [
                    'content' => 'There are no company fields'
                ],
                true
            );
        }

        // For first import clear the cache
        if (empty($page)) {
            $this->_clearCache();
        }

        // Parse and save in cache
        $id = 'import_sheets';
        if (!($arrSheets = $this->_cache->getItem($id))) {
            $data = new SpreadsheetExcelReader();
            $data->setOutputEncoding('UTF-8');
            $data->read($importFilePath);

            $arrSheets = $data->sheets;

            $this->_cache->setItem($id, $arrSheets);
        }

        if (!is_array($arrSheets) || empty($arrSheets)) {
            return $view->setVariables(
                [
                    'content' => 'Not correct information'
                ],
                true
            );
        }

        // *******************
        // First sheet is used for client's info
        // *******************
        $sheet = $arrSheets[$currentSheet];

        // Load headers
        $arrRowHeaders = array();
        $colIndex = 1;
        while ($colIndex <= $sheet['numCols']) {
            $arrRow = $sheet['cells'][1];
            $arrRowHeaders[$colIndex] = isset($arrRow[$colIndex]) ? trim($arrRow[$colIndex]) : '';
            $colIndex++;
        }

        // Load each client's info
        $arrClientsInfo = array();
        $clientsCount = $sheet['numRows'];
        for ($rowIndex = $rowStart; $rowIndex < $clientsCount; $rowIndex++) {
            if (empty($rowIndex)) {
            } else {
                $colIndex = 1;
                while ($colIndex <= $sheet['numCols']) {
                    $arrRow = @$sheet['cells'][$rowIndex + 1];
                    $columnId = $arrRowHeaders[$colIndex];

                    if (!isset($arrClientsInfo[$rowIndex][$columnId])) {
                        $arrClientsInfo[$rowIndex][$columnId] = isset($arrRow[$colIndex]) ? trim($arrRow[$colIndex]) : '';
                    } elseif (is_array($arrClientsInfo[$rowIndex][$columnId])) {
                        $arrClientsInfo[$rowIndex][$columnId][] = isset($arrRow[$colIndex]) ? trim($arrRow[$colIndex]) : '';
                    } else {
                        $arrClientsInfo[$rowIndex][$columnId] = array(
                            $arrClientsInfo[$rowIndex][$columnId],
                            isset($arrRow[$colIndex]) ? trim($arrRow[$colIndex]) : ''
                        );
                    }

                    $colIndex++;
                }
            }

            if (!empty($rowEnd) && $rowIndex >= $rowEnd) {
                break;
            }
        }

        $sheetsAmount = count($arrSheets);

        // Prepare and run import for each client record
        $currentClientNum = $rowStart + 1;

        $q_id = $this->_companyProspects->getCompanyQnr()->getDefaultQuestionnaireId();

        $booTestMode = false;

        $arrDivisions = $this->_company->getDivisions($companyId, $divisionGroupId);

        foreach ($arrClientsInfo as $thisClient) {
            if (empty($thisClient['NAME'])) {
                echo "-------- PASS EMPTY NAME: #$currentClientNum<br/>";
                $currentClientNum++;
                continue;
            }

            $data = array(
                'q_1_field_2' => $thisClient['NAME'], # qf_first_name
                'q_1_field_3' => '_', # qf_last_name
                'q_1_field_7' => $thisClient['email'], # qf_email
                'q_1_field_57' => $thisClient['ph no:'], # qf_phone
                'q_1_field_59' => $thisClient['refered by'], # qf_referred_by
            );

            $res = $this->_companyProspects->getCompanyQnr()->checkIncomingQNRData($data, $q_id, '', 'prospect_profile');
            $insertData = $res['arrInsertData'];

            try {
                if (!empty($thisClient['Date'])) {
                    $insertData['prospect']['create_date'] = $this->_parseDateProspect($thisClient['Date']);
                }
            } catch (Exception $e) {
                echo "<div style=\"color:red; font-weight: bold;\">" . $e->getMessage() . "</div>";
                unset($insertData['prospect']['create_date']);
            }

            $insertData['prospect']['seriousness'] = $thisClient['seriousness level'];
            $insertData['prospect']['company_id']  = $companyId;
            $insertData['prospect']['agent_id']    = $this->_getFieldValue($arrCompanyFields, 'agent', $thisClient['sales agent'], $currentClientNum, $booTestMode); // not required;  exception:

            if (!$booTestMode) {
                try {
                    $arrCreationResult = $this->_companyProspects->createProspect($insertData);

                    if (empty($arrCreationResult['strError']) && !empty($arrCreationResult['prospectId'])) {
                        $officeId = $this->getDivisionIdByName($arrDivisions, $thisClient['OFFICE'] == 'LONDON' || $thisClient['OFFICE'] == 'NORTHAMPTON' ? 'UK' : $thisClient['OFFICE']);
                        if (!empty($officeId)) {
                            $this->_companyProspects->getCompanyProspectOffices()->updateProspectOffices($arrCreationResult['prospectId'], array($officeId));
                        }

                        echo 'Created prospect #' . $currentClientNum . '  db_id=' . $arrCreationResult['prospectId'] . '<br/>';
                        #add notes
                        foreach ($thisClient['notes'] as $note) {
                            if (!empty($note)) {
                                $this->_companyProspects->updateNotes('add', null, $arrCreationResult['prospectId'], $note);
                                echo '&nbsp;&nbsp;&nbsp;^--Add Note<br>';
                            }
                        }

                        $prospectCategories = $this->_companyProspects->getCompanyQnr()->getCategories();

                        $arrOther[] = array(
                            'prospect_category_id' => 'other',
                            'prospect_category_name' => 'Other',
                            'prospect_category_unique_id' => 'other'
                        );
                        $prospectCategories = array_merge($prospectCategories, $arrOther);

                        $dataAssessment = $this->generateCategoriesArray($thisClient['PROGRAM'], $prospectCategories, $arrCreationResult['prospectId']);

                        $arrCheckResult = $this->_companyProspects->getCompanyQnr()->checkIncomingQNRData($dataAssessment, $q_id, $arrCreationResult['prospectId'], 'prospect_business');

                        $strError = $arrCheckResult['strError'];
                        $arrProspectData = $arrCheckResult['arrInsertData'];

                        if (empty($strError)) {
                            $this->_companyProspects->updateProspect($arrProspectData, $arrCreationResult['prospectId']);
                            $categories = $this->generateCategoriesArray($thisClient['PROGRAM'], $prospectCategories, $arrCreationResult['prospectId'], false);
                            $booResult = $this->_companyProspects->saveProspectCategories($categories, $arrCreationResult['prospectId']);

                            if ($booResult) {
                                echo '&nbsp;&nbsp;&nbsp;^--Assessment Updated<br/>';
                            } else {
                                echo('Creation failed for: <br/>' . var_dump($categories));
                            }
                        }
                    } else {
                        echo('Creation failed for: <br/>' . var_dump($insertData));
                    }
                } catch (Exception $e) {
                    echo('Creation failed for: <br/>' . var_dump($insertData));
                    $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
                }
            }

            $currentClientNum++;
        }

        if ($rowStart + $processAtOnce < $clientsCount) {
            $strResult = sprintf('<a href="%s/system/import/prospects?sheet=%d&page=%d">Next Page &gt;&gt;</a>', $this->layout()->getVariable('baseUrl'), $currentSheet, $page + 1);
        } else {
            if ($currentSheet < $sheetsAmount - 1) {
                $strResult = '<br/>Done with this sheet.<br/>';
                $strResult .= sprintf('<a href="%s/system/import/prospects?sheet=%d&page=%d">Next Sheet &gt;&gt;</a>', $this->layout()->getVariable('baseUrl'), $currentSheet + 1, 0);
            } else {
                $strResult = '<h2>Complete!</h2>';
            }
        }

        $pagesCount = round($clientsCount / $processAtOnce);
        echo sprintf(
            '<div style="padding-top: 5px; margin-top: 10px; border-top: 1px solid #000;">Page %d of %d. <span style="color: green;">%s</span></div>',
            $page + 1,
            $pagesCount <= 0 ? 1 : $pagesCount,
            $strResult
        );

        return $view;
    }

    private function _parseDateProspect($strDate, $strExplode = "/")
    {
        $formattedDate = '';
        $strDate = trim($strDate ?? '');
        if (!empty($strDate)) {
            try {
                list($day, $month, $year) = explode($strExplode, $strDate);

                if (!checkdate((int)$month, (int)$day, (int)$year)) {
                    //var_dump(array($day, $month, $year));
                    if (!checkdate((int)$day, (int)$month, (int)$year)) {
                        throw new Exception('Incorrect date - ' . $strDate);
                    } else {
                        $formattedDate = $year . '-' . $day . '-' . $month;
                    }
                } else {
                    $formattedDate = $year . '-' . $month . '-' . $day;
                }
            } catch (Exception $e) {
                throw new Exception('Incorrect date - ' . $strDate, 0, $e);
            }
        }

        return $formattedDate;
    }

    protected function generateCategoriesArray($program, $prospectCategories, $prospectId, $string = true)
    {
        $dataAssessment = array();
        switch (trim($program)) {
            case 'CANADA SKILLED WORKER':
                if ($string) {
                    $dataAssessment[$this->getProspectCategoryStringIdByName($prospectCategories, 'skilled_worker', $prospectId)] = 'on';
                } else {
                    $dataAssessment[] = $this->getProspectCategoryIdByName($prospectCategories, 'skilled_worker');
                }
                break;
            case 'CANADA STUDENT':
                if ($string) {
                    $dataAssessment[$this->getProspectCategoryStringIdByName($prospectCategories, 'student', $prospectId)] = 'on';
                } else {
                    $dataAssessment[] = $this->getProspectCategoryIdByName($prospectCategories, 'student');
                }
                break;
            case 'CANADA WORK PERMIT':
                if ($string) {
                    $dataAssessment[$this->getProspectCategoryStringIdByName($prospectCategories, 'foreign_worker', $prospectId)] = 'on';
                } else {
                    $dataAssessment[] = $this->getProspectCategoryIdByName($prospectCategories, 'foreign_worker');
                }
                break;
            default:
                if ($string) {
                    $otherId = $this->getProspectCategoryStringIdByName($prospectCategories, 'other', $prospectId);
                    $dataAssessment[$otherId] = 'on';
                    $dataAssessment["$otherId-val"] = $program;
                }
                break;
        }
        return $dataAssessment;
    }

    protected function getProspectCategoryStringIdByName($arrCategories, $name, $pid)
    {
        foreach ($arrCategories as $arrCategoryInfo) {
            if ($arrCategoryInfo['prospect_category_unique_id'] == $name) {
                return 'p_' . $pid . '_category_' . $arrCategoryInfo['prospect_category_id'];
            }
        }
        return null;
    }

    protected function getProspectCategoryIdByName($arrCategories, $name)
    {
        foreach ($arrCategories as $arrCategoryInfo) {
            if ($arrCategoryInfo['prospect_category_unique_id'] == $name) {
                return $arrCategoryInfo['prospect_category_id'];
            }
        }
        return null;
    }

    protected function getDivisionIdByName($divisions, $name)
    {
        foreach ($divisions as $office) {
            if ($office['name'] == $name) {
                return $office['division_id'];
            }
        }
        return null;
    }

}
