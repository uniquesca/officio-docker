<?php

namespace Forms\Service;

use Clients\Service\Clients;
use Clients\Service\Clients\Accounting;
use DateTime;
use Documents\Service\Documents;
use DOMDocument;
use Exception;
use Files\Model\FileInfo;
use Files\Service\Files;
use Forms\DominicaTCPDF;
use Forms\Letterhead;
use Forms\XfdfParseResult;
use Laminas\Cache\Exception\ExceptionInterface;
use Laminas\Db\Sql\Select;
use Laminas\View\Model\ViewModel;
use Laminas\View\Renderer\PhpRenderer;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Notes\Service\Notes;
use Officio\Common\Service\BaseService;
use Uniques\Php\StdLib\FileTools;
use Officio\Comms\Service\Mailer;
use Officio\Service\Bcpnp;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Common\Service\Settings;
use Officio\Common\ServiceContainerHolder;
use Officio\PdfTron\Service\PdfTronPython;
use Officio\Templates\Model\SystemTemplate;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use SimpleXMLElement;
use TCPDF;
use Officio\Templates\SystemTemplates;
use Uniques\Php\StdLib\StringTools;

/**
 * PDF class is used when playing with pdf and xfdf files
 * (e.g. Generating xfdf files, flatten version of pdf files,
 * retrieve and parse xfdf files, pdf reports generation, etc.)
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class Pdf extends BaseService
{

    use ServiceContainerHolder;

    // Possible results of pdf form submitting
    public const XFDF_SAVED_CORRECTLY = 0;
    public const XFDF_FILE_NOT_SAVED = 1;
    public const XFDF_DIRECTORY_NOT_CREATED = 2;
    public const XFDF_INCORRECT_INCOMING_XFDF = 3;
    public const XFDF_INSUFFICIENT_ACCESS_TO_PDF = 4;
    public const XFDF_CLIENT_LOCKED = 5;
    public const XFDF_MARKED_AS_COMPLETE = 6;
    public const XFDF_INCORRECT_PDF_VERSION_INFO = 7;
    public const XFDF_MARKED_AS_FINALIZED = 8;
    public const XFDF_INCORRECT_LOGIN_INFO = 9;
    public const XFDF_INCORRECT_TIME_STAMP = 10;
    public const PDF_FORM_DOES_NOT_EXIST = 11;
    public const PDF_CREATED_CORRECTLY = 12;
    public const PDF_NOT_CREATED = 13;

    public const XFDF_FIELD_PREFIX = 'syncA_';

    /** @var Files */
    protected $_files;

    /** @var PdfTronPython */
    protected $_pdfTron;

    /** @var Forms */
    protected $_forms;

    /** @var SystemTemplates */
    protected $_systemTemplates;

    /** @var PhpRenderer */
    protected $_renderer;

    /** @var Encryption */
    protected $_encryption;

    /** @var Mailer */
    protected $_mailer;

    public function initAdditionalServices(array $services)
    {
        $this->_encryption      = $services[Encryption::class];
        $this->_files           = $services[Files::class];
        $this->_pdfTron         = $services[PdfTronPython::class] ?? null;
        $this->_forms           = $services[Forms::class];
        $this->_systemTemplates = $services[SystemTemplates::class];
        $this->_renderer        = $services[PhpRenderer::class];
        $this->_mailer          = $services[Mailer::class];
    }

    /**
     * Get string result by its code
     *
     * @param int $codeId - const
     * @return string error code
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getCodeResultById($codeId)
    {
        /** @var Company $company */
        $company  = $this->_serviceContainer->get(Company::class);
        $arrCodes = array(
            self::XFDF_SAVED_CORRECTLY            => $this->_tr->translate('Your form was successfully saved to Officio.'),
            self::XFDF_FILE_NOT_SAVED             => $this->_tr->translate('Submitted information was not saved - close the form, then try to open it again from Officio. If the issue persists, please contact the website support.'),
            self::XFDF_MARKED_AS_FINALIZED        => $this->_tr->translate('Form is marked as finalized and can not be updated.'),
            self::XFDF_INCORRECT_INCOMING_XFDF    => $this->_tr->translate('Invalid data file format - close the form, then try to open it again from Officio. If the issue persists, please contact the website support.'),
            self::XFDF_INCORRECT_LOGIN_INFO       => $this->_tr->translate('Incorrect login info. Please try again.'),
            self::XFDF_CLIENT_LOCKED              => sprintf(
                $this->_tr->translate('The forms are locked by the %s. If you need to make any changes, please contact them for assistance.'),
                $company->getCurrentCompanyDefaultLabel('office')
            ),
            self::XFDF_INSUFFICIENT_ACCESS_TO_PDF => $this->_tr->translate('Insufficient access rights for this form or case.'),
            self::XFDF_INCORRECT_TIME_STAMP       => $this->_tr->translate(
                'Your form cannot be saved. Since the last time you download this form, this form has been modified by another user and it has newer information not available in your copy. Please open or download a new copy of this form and make your modifications on that form.'
            ),
            self::PDF_FORM_DOES_NOT_EXIST         => $this->_tr->translate('PDF form does not exist.'),
            self::PDF_NOT_CREATED                 => $this->_tr->translate('Your form was not created.')
        );

        return array_key_exists($codeId, $arrCodes) ? $arrCodes[$codeId] : 'Error code: ' . $codeId;
    }

    /**
     * Run command to convert file to pdf
     * @NOTE works on linux only
     *
     * @param string $pathToFile
     * @param string $resultPdfPath
     * @return string path to converted file, empty on error
     */
    public function _runConvertCommand($pathToFile, $resultPdfPath)
    {
        try {
            if (stristr(PHP_OS, 'WIN')) {
                $pathToScript = getcwd() . '/scripts/convert_to_pdf.bat';
            } else {
                $pathToScript = getcwd() . '/scripts/convert_to_pdf.sh';
            }

            $strCommand = $this->_settings->_sprintf(
                '"%path_to_file%" "%result_pdf_path%"',
                array(
                    'path_to_file'    => getcwd() . '/' . str_replace('\\', '/', $pathToFile),
                    'result_pdf_path' => getcwd() . '/' . str_replace('\\', '/', $resultPdfPath)
                )
            );

            exec($pathToScript . ' ' . $strCommand, $output, $exit);
            if ($exit !== 0) {
                throw new Exception(
                    'Error when run convert command: ' . $pathToScript . ' ' . $strCommand . PHP_EOL .
                    'Output: ' . print_r($output, true) . PHP_EOL .
                    'Exit: ' . print_r($exit, true)
                );
            }

            $fileExtension     = FileTools::getFileExtension($pathToFile);
            $convertedFilePath = substr($pathToFile, 0, strlen($pathToFile) - strlen($fileExtension)) . 'pdf';

            // Check if file was created
            if (!file_exists($convertedFilePath)) {
                throw new Exception(
                    'File was not converted.' . PHP_EOL .
                    'Path:' . $convertedFilePath . PHP_EOL .
                    'Command: ' . $pathToScript . ' ' . $strCommand . PHP_EOL .
                    'Output: ' . print_r($output, true) . PHP_EOL .
                    'Exit: ' . print_r($exit, true)
                );
            }
        } catch (Exception $e) {
            $convertedFilePath = false;
            $this->_log->debugErrorToFile('Error when run script:', $e->getMessage());
        }

        return $convertedFilePath;
    }

    /**
     * Create flatten version of pdf form
     *
     * @param $pdfFileName - path to pdf form
     * @param $xfdfFileName - path to xfdf file
     * @param $mergeFileName - path where result pdf file will be saved
     * @param bool $booFlatten - true to make pdf flatten
     * @return bool true on success
     */
    public function createFlattenPdf($pdfFileName, $xfdfFileName, $mergeFileName, $booFlatten = true)
    {
        $booSuccess = false;
        if (file_exists($pdfFileName)) {
            // Use tmp directory because path to result pdf file can contain 'bad' symbols
            $tmpFlattenFile = $this->_config['directory']['pdf_temp'] . '/' . uniqid(rand() . time(), true) . '.pdf';

            if ($this->_config['pdf']['use_pdftk']) {
                // ***********************************
                // Use Pdftk to merge/flatten the file
                // ***********************************
                if (file_exists($xfdfFileName)) {
                    $strCommand = 'pdftk "' . getcwd() . '/' . $pdfFileName . '" fill_form "' . getcwd() . '/' . $xfdfFileName . '" output "' . getcwd() . '/' . $tmpFlattenFile . '"';
                } else {
                    $strCommand = 'pdftk "' . getcwd() . '/' . $pdfFileName . '" output "' . getcwd() . '/' . $tmpFlattenFile . '"';
                }

                if ($booFlatten) {
                    $strCommand .= ' flatten';
                }

                exec($strCommand);
            } else {
                // ***********************************
                // Use PDFTron SDK Locally
                // ***********************************
                if (is_null($this->_pdfTron)) {
                    throw new Exception('Officio\PdfTron module is not installed.');
                }
                $this->_pdfTron->flattenPdf(
                    getcwd() . '/' . str_replace('\\', '/', $pdfFileName),
                    getcwd() . '/' . str_replace('\\', '/', $xfdfFileName),
                    getcwd() . '/' . str_replace('\\', '/', $tmpFlattenFile),
                    $booFlatten
                );
            }


            // Check if all is correct
            if (file_exists($tmpFlattenFile)) {
                rename($tmpFlattenFile, $mergeFileName);
                $booSuccess = true;
            }
        }

        return $booSuccess;
    }


    /**
     * Load form fields from pdf file
     *
     * @param $pdfFileName
     * @return array|bool|null
     */
    public function _getPdfFields($pdfFileName)
    {
        $result = false;

        if (file_exists($pdfFileName)) {
            if ($this->_config['pdf']['use_pdftk']) {
                // ***********************************
                // Use Pdftk to merge/flatten the file
                // ***********************************
                $strCommand = 'pdftk "' . getcwd() . '/' . $pdfFileName . '" dump_data_fields ';
                exec($strCommand, $result);
                $result = $this->_getSyncFields($result, true);
            } else {
                // ***********************************
                // Use PDFTron SDK Locally
                // ***********************************
                if (is_null($this->_pdfTron)) {
                    throw new Exception('Officio\PdfTron module is not installed.');
                }
                $listField = $this->_pdfTron->getFields(getcwd() . '/' . $pdfFileName);

                if (is_array($listField)) {
                    $arrIncorrectNamedFields = array();
                    if (count($listField) > 0) {
                        // Gather all fields and fields with incorrect names
                        foreach ($listField as $fieldName) {
                            if (!empty($fieldName) && !$this->checkFieldNameCorrect($fieldName)) {
                                $arrIncorrectNamedFields[] = $fieldName;
                            }
                        }
                    }
                    $arrSyncFields = $this->_getSyncFields($listField, false);

                    $result = array('arrSyncFields' => $arrSyncFields, 'arrBadFields' => $arrIncorrectNamedFields);
                }
            }
        }

        return $result;
    }

    /**
     * Extract fields names from incoming array of fields
     * (Only with correct prefix)
     *
     * @param $arrFieldsNames - array of fields, which must be filtered
     * @param $booUsePdftk - true to extract field name, delete unnecessary info
     * @param string $strSyncFieldPrefix - prefix used to identify the field
     * @return array
     */
    public function _getSyncFields($arrFieldsNames, $booUsePdftk, $strSyncFieldPrefix = '')
    {
        $arrResult = array();

        $strSyncFieldPrefix = empty($strSyncFieldPrefix) ? self::XFDF_FIELD_PREFIX : $strSyncFieldPrefix;
        if (is_array($arrFieldsNames) && count($arrFieldsNames) > 0) {
            foreach ($arrFieldsNames as $fieldName) {
                if ($booUsePdftk) {
                    // In such format pdftk returns: FieldName: syncA_family_name_c2
                    if (preg_match('/^FieldName:(.*)/', $fieldName, $regs)) {
                        $checkFieldName = $regs[1];
                    } else {
                        $checkFieldName = '';
                    }
                } else {
                    $checkFieldName = $fieldName;
                }

                if (preg_match("/^$strSyncFieldPrefix/", trim($checkFieldName))) {
                    $arrResult[] = trim($checkFieldName);
                }
            }
        }

        return $arrResult;
    }


    /**
     * Manage Sync Fields in DB:
     * Extract fields from PDF file and save new fields in DB
     *
     * @param $pathToPdf - path to pdf file
     * @return array result
     * @throws ExceptionInterface
     */
    public function manageSynFields($pathToPdf)
    {
        $arrBadFields = array();
        $strError     = '';


        if (file_exists($pathToPdf)) {
            $result = $this->_getPdfFields($pathToPdf);

            if (is_array($result)) {
                if (is_array($result['arrSyncFields'])) {
                    $arrPdfFieldsNames = $result['arrSyncFields'];

                    // Gather all sync fields
                    $arrFieldNames = array();
                    foreach ($arrPdfFieldsNames as $fieldName) {
                        if (!empty($fieldName)) {
                            $arrFieldNames[] = trim($fieldName);
                        }
                    }

                    if (!empty($arrFieldNames)) {
                        // Get list of previously saved fields
                        $arrAlreadyInDBSynFieldsNames = $this->_forms->getFormSynField()->getAlreadySavedFields($arrFieldNames);

                        // Check which fields are already in db
                        $arrFieldNamesToInsert = array_diff($arrFieldNames, $arrAlreadyInDBSynFieldsNames);

                        // Insert only new fields
                        if (!empty($arrFieldNamesToInsert)) {
                            foreach ($arrFieldNamesToInsert as $fieldName) {
                                $this->_db2->insert('form_syn_field', ['field_name' => $fieldName]);
                            }

                            // Clean saved records in cache
                            $this->_cache->removeItem('pdf_form_synfield');
                        }
                    }
                }

                $arrBadFields = $result['arrBadFields'];
            } else {
                // Error not correct pdf - there are no fields or error was returned
                $strError = $this->_tr->translate('Error during PDF form parsing.');
            }
        } else {
            $strError = $this->_tr->translate('PDF form does not exists.');
        }

        return array('strError' => $strError, 'arrBadFields' => $arrBadFields);
    }

    /**
     * Convert angular form data to xfdf
     *
     * @param array $arrFormFields
     * @param int $formId
     * @return string on success, otherwise false
     */
    public function convertAngularFormDataToXfdf($arrFormFields, $formId)
    {
        $booSuccess = false;

        try {
            if (!empty($arrFormFields) && is_array($arrFormFields)) {
                $data = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
                    '<xfdf xmlns="http://ns.adobe.com/xfdf/" xml:space="preserve">' . "\n" .
                    '<fields></fields>' . "\n" .
                    '</xfdf>';

                $doc = new DOMDocument();
                $doc->loadXML($data);

                $xfdfEl = $doc->getElementsByTagName('xfdf');
                $xfdfEl = $xfdfEl->item(0);

                $fieldsEl = $doc->getElementsByTagName('fields');
                $fieldsEl = $fieldsEl->item(0);

                foreach ($arrFormFields as $key => $value) {
                    if (is_array($value)) {
                        continue;
                    }

                    $fieldName  = $key;
                    $fieldValue = $value;

                    // There is an issue with enter character, we need fix this
                    // Check details here: http://stackoverflow.com/questions/20525741/xml-create-element-new-line
                    $fieldValue = str_replace(array("\r\n", "\r"), "\n", $fieldValue);
                    $fieldValue = StringTools::stripInvisibleCharacters($fieldValue, false);

                    $fieldEl = $doc->createElement('field');
                    $fieldEl->setAttribute('name', $fieldName);

                    $valueEl = $doc->createElement('value');
                    $valueEl->appendChild($doc->createTextNode($fieldValue));
                    $fieldEl->appendChild($valueEl);
                    $fieldsEl->appendChild($fieldEl);
                }

                $pdfUrl = 'forms/index/open-assigned-pdf/pdfid/' . $formId;

                $fEl = $doc->createElement('f');
                $fEl->setAttribute('href', $pdfUrl);
                $xfdfEl->appendChild($fEl);

                $booSuccess = $doc->saveXML();
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Convert form data to xfdf
     *
     * @param array $arrFormFields
     * @param int $formId
     * @param bool $bcpnpForm
     * @return string on success, otherwise false
     */
    public function convertFormDataToXfdf($arrFormFields, $formId, $bcpnpForm = false)
    {
        $booSuccess = false;

        try {
            if (!empty($arrFormFields) && is_array($arrFormFields)) {
                $data = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
                    '<xfdf xmlns="http://ns.adobe.com/xfdf/" xml:space="preserve">' . "\n" .
                    '<fields></fields>' . "\n" .
                    '</xfdf>';

                $doc = new DOMDocument();
                $doc->loadXML($data);

                $xfdfEl = $doc->getElementsByTagName('xfdf');
                $xfdfEl = $xfdfEl->item(0);

                $fieldsEl = $doc->getElementsByTagName('fields');
                $fieldsEl = $fieldsEl->item(0);

                foreach ($arrFormFields as $key => $value) {
                    if ($bcpnpForm) {
                        $fieldName  = $key;
                        $fieldValue = $value;

                        if (!empty($fieldValue) && strtotime($fieldValue)) {
                            $d = DateTime::createFromFormat('Y-m-d', $fieldValue);
                            if ($d && $d->format('Y-m-d') === $fieldValue) {
                                $fieldValue = $this->_settings->reformatDate($fieldValue, 'Y-m-d', 'd-m-Y');
                            }
                        }
                    } else {
                        $fieldName  = $value['name'];
                        $fieldValue = $value['value'];
                    }

                    // There is an issue with enter character, we need fix this
                    // Check details here: http://stackoverflow.com/questions/20525741/xml-create-element-new-line
                    $fieldValue = str_replace(array("\r\n", "\r"), "\n", $fieldValue);
                    $fieldValue = StringTools::stripInvisibleCharacters($fieldValue, false);

                    $fieldEl = $doc->createElement('field');
                    $fieldEl->setAttribute('name', $fieldName);

                    $valueEl = $doc->createElement('value');
                    $valueEl->appendChild($doc->createTextNode($fieldValue));
                    $fieldEl->appendChild($valueEl);

                    $fieldsEl->appendChild($fieldEl);
                }

                $pdfUrl = 'forms/index/open-assigned-pdf/pdfid/' . $formId;

                $fEl = $doc->createElement('f');
                $fEl->setAttribute('href', $pdfUrl);
                $xfdfEl->appendChild($fEl);

                $booSuccess = $doc->saveXML();
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Retrieve incoming xfdf data
     *
     * @param string $XFDFData
     * @return bool|SimpleXMLElement on success, otherwise false
     */
    public function getIncomingXfdf($XFDFData)
    {
        // From all places (pdf/html forms) data must be encoded, utf symbols must be NOT encoded
        // $XFDFData = html_entity_decode($XFDFData, ENT_NOQUOTES, 'UTF-8' );
        // $XFDFData = str_replace('&', '&amp;', $XFDFData);
        $XMLData = @simplexml_load_string($XFDFData);
        if (!$XMLData) {
            if (!empty($XFDFData)) {
                $strError = 'Incoming XFDF: ' . print_r($XFDFData, true);

                $this->_log->debugErrorToFile(__FUNCTION__ . ', line ' . __LINE__ . ' Cannot parse xml:', $strError, 'pdf');
            }

            return false;
        }

        return $XMLData;
    }


    /**
     * Check if pdf field name is correct (has only allowed chars)
     *
     * @param string $checkFieldName - name of the field which will be checked
     * @return bool check result
     */
    public function checkFieldNameCorrect($checkFieldName)
    {
        return preg_match('/^[a-zA-Z0-9_()\-[\]\s]*$/i', $checkFieldName);
    }


    /**
     * Try update field value
     *
     * @param $updateFieldInfo
     * @param $oXml
     *
     * @return string list of field names with incorrect symbols
     * (subfields are not supported too)
     */
    public function parseXfdfFieldVal($updateFieldInfo, &$oXml)
    {
        $arrUpdateFields  = array();
        $updateFieldValue = '';

        $subField = $updateFieldInfo;

        $booCheckAgain        = true;
        $booContainsBadSymbol = false;
        while ($booCheckAgain) {
            $updateFieldName   = (string)$subField['name'];
            $arrUpdateFields[] = $updateFieldName;

            // Check if there are some bad symbols in field name
            if (!$this->checkFieldNameCorrect($updateFieldName)) {
                $booContainsBadSymbol = true;
            }

            // Maybe there are other internal fields?
            if (isset($subField->field)) {
                $booCheckAgain = true;
                $subField      = $subField->field;
            } else {
                $booCheckAgain    = false;
                $updateFieldValue = $subField->value;
            }
        }

        // Update the field
        $this->updateFieldInXfdf($arrUpdateFields, $updateFieldValue, $oXml);


        if ($booContainsBadSymbol || count($arrUpdateFields) > 1) {
            $strBadField = implode('.', $arrUpdateFields);
        } else {
            $strBadField = '';
        }

        return $strBadField;
    }

    /**
     * Get path to the annotations xfdf file
     *
     * @param string $pathToXfdf
     * @param int $pdfId
     * @return string
     */
    public function getAnnotationPath($pathToXfdf, $pdfId)
    {
        return $pathToXfdf . '/annotation/' . $pdfId;
    }

    /**
     * Extract login and password from the xfdf (XML)
     *
     * @param SimpleXMLElement $XMLData
     * @param bool|int $pdfId
     * @return array
     */
    public function parsePdfForCredentials($XMLData, $pdfId = false)
    {
        if ($XMLData === false || !is_object($XMLData)) {
            if (!empty($XMLData)) {
                $this->_log->debugErrorToFile(__FUNCTION__ . ', line ' . __LINE__ . ' Not object:', $XMLData, 'pdf');
            }

            return array('code' => self::XFDF_INCORRECT_INCOMING_XFDF);
        }

        $login = $pass = $formTimeStamp = '';

        $oFieldLogin = $XMLData->xpath("//*[@name='server_login']");
        if (!empty($oFieldLogin)) {
            $login = (string)$oFieldLogin[0]->value;
        }

        $oFieldPassword = $XMLData->xpath("//*[@name='server_pass']");
        if (!empty($oFieldPassword)) {
            $pass = trim((string)$oFieldPassword[0]->value);
        }

        if (!$pdfId) {
            $oFieldAssignedPDFId = $XMLData->xpath("//*[@name='server_assigned_id']");
            if (!empty($oFieldAssignedPDFId)) {
                $pdfId = (string)$oFieldAssignedPDFId[0]->value;
            }
        }

        $oFieldTimeStamp = $XMLData->xpath("//*[@name='server_time_stamp']");
        if (!empty($oFieldTimeStamp)) {
            $formTimeStamp = (string)$oFieldTimeStamp[0]->value;
        }

        // Clear login and password from the data
        $this->updateFieldInXfdf('server_login', '', $XMLData);
        $this->updateFieldInXfdf('server_pass', '', $XMLData);

        return [$login, $pass, $pdfId, $formTimeStamp];
    }

    /**
     * Get the pdf id from the url string
     * A sample url: forms/index/open-assigned-pdf/pdfid/8010
     *
     * @param SimpleXMLElement $XMLData
     * @return bool|int
     */
    public function parsePdfIdFromUrl($XMLData)
    {
        $pdfid = false;
        if (isset($XMLData->f['href']) && preg_match('%^(.*)/pdfid/([\d]+)%', (string)$XMLData->f['href'], $regs)) {
            $pdfid = (int)$regs[2];
        }

        return $pdfid;
    }


    /**
     * Get received xfdf data and save/updated xfdf files + mapped data in the DB
     *
     * Steps:
     * 1. Receive xfdf data, check it
     * 2. Save sync fields in db
     * 3. Save xfdf file
     *
     * @param string|int $pdfid
     * @param SimpleXMLElement $XMLData
     * @param $assignedFormInfo
     * @param $updateCompanyId
     * @param $updateMemberId
     * @param $currentMemberId
     * @param $mainParentId
     * @param $arrParentsData
     * @param $fieldsMap
     * @param bool $booAnnotationsEnabled
     * @param bool $booPrintPDF
     * @return XfdfParseResult
     * @throws Exception
     */
    public function pdfToXfdf($pdfid, $XMLData, $assignedFormInfo, $updateCompanyId, $updateMemberId, $currentMemberId, $mainParentId, $arrParentsData, $fieldsMap, $booAnnotationsEnabled = false, $booPrintPDF = false)
    {
        $result = new XfdfParseResult();

        $xfdfBeforeChanges = '';

        // Unset specific fields
        if (isset($XMLData->f)) {
            unset($XMLData->f);
        }
        if (isset($XMLData->ids)) {
            unset($XMLData->ids);
        }

        // Create directory if it does not exist
        if ($booPrintPDF) {
            $tmpXFDFPath = $this->_files->createFTPDirectory($this->_config['directory']['pdf_temp']);

            $xfdf_file_name = $tmpXFDFPath . '/' . uniqid(rand() . time(), true) . '.xfdf';
            if (file_exists($xfdf_file_name)) {
                unlink($xfdf_file_name);
            }

            $XMLData = $this->clearEmptyFields($XMLData);
            $this->removeInternalFields($XMLData);
            $XML_FILE_DATA = $XMLData->asXML();

            $thisXfdfCreationResult = $this->saveXfdf($xfdf_file_name, $XML_FILE_DATA);
            if ($thisXfdfCreationResult != self::XFDF_SAVED_CORRECTLY) {
                $this->_log->debugErrorToFile(__FUNCTION__ . ', line ' . __LINE__ . ' File:' . $xfdf_file_name, $XML_FILE_DATA, 'pdf');
                $result->code = $thisXfdfCreationResult;
                return $result;
            }

            $arrFormInfo['formId']      = $assignedFormInfo['form_id'];
            $arrFormInfo['memberId']    = $updateMemberId;
            $arrFormInfo['useRevision'] = $assignedFormInfo['use_revision'];
            $arrFormInfo['filePath']    = $assignedFormInfo['file_path'];

            $arrPdfFormPath = $this->_getPDFFormPath($arrFormInfo);
            $pdfFormPath    = $arrPdfFormPath['pdfFormPath'];

            if (!file_exists($pdfFormPath)) {
                $result->code = self::PDF_FORM_DOES_NOT_EXIST;
                return $result;
            }

            $flattenPdfPath = $tmpXFDFPath . '/' . 'flatten_form_' . uniqid(rand() . time(), true) . '.pdf';
            $booResult      = $this->createFlattenPdf($pdfFormPath, $xfdf_file_name, $flattenPdfPath);
            if ($booResult && file_exists($flattenPdfPath)) {
                $result->code = self::PDF_CREATED_CORRECTLY;
            } else {
                $result->code = self::PDF_NOT_CREATED;
            }
            return $result;
        }

        $xfdfDirectory = $this->_files->getClientXFDFFTPFolder($updateMemberId);
        if (!$this->_files->createFTPDirectory($xfdfDirectory)) {
            $this->_log->debugErrorToFile('Directory not created', $xfdfDirectory, 'files');
            $result->code = self::XFDF_DIRECTORY_NOT_CREATED;
            return $result;
        }

        // Save this xfdf file (but merge fields before)
        $xfdf_file_name = $xfdfDirectory . '/' . static::getXfdfFileName($assignedFormInfo['family_member_id'], $pdfid);


        // ***********************
        // Work with annotations
        // ***********************
        if ($booAnnotationsEnabled) {
            // Get received annotations
            $annots = $XMLData->annots;
            $annots = $annots->asXML();

            // Path to annotation files
            $annotationPath = $this->getAnnotationPath($xfdfDirectory, $pdfid);

            // Create directory if it doesn't exist
            if (!$this->_files->createFTPDirectory(dirname($annotationPath))) {
                $result->code = self::XFDF_DIRECTORY_NOT_CREATED;
                return $result;
            }

            if ($annots == '<annots/>') {
                // Delete the file
                if (file_exists($annotationPath)) {
                    unlink($annotationPath);
                }
            } else {
                // Create or update the file
                $annotationCreationResult = $this->saveXfdf($annotationPath, $annots);
                if ($annotationCreationResult != self::XFDF_SAVED_CORRECTLY) {
                    $result->code = $annotationCreationResult;
                    return $result;
                }
            }
        }

        // Remove annotations from xfdf file
        unset($XMLData->annots);

        // ***********************
        // Save xfdf file
        // ***********************
        if (file_exists($xfdf_file_name)) {
            // Merge previously saved info
            $xmlOld = $this->readXfdfFromFile($xfdf_file_name);

            if ($xmlOld === false || !is_object($xmlOld)) {
                $this->_log->debugErrorToFile(__FUNCTION__ . ', line ' . __LINE__ . ' Not object:', $xmlOld, 'pdf');
                $result->code = self::XFDF_FILE_NOT_SAVED;
                return $result;
            }

            $xfdfBeforeChanges = $xmlOld->asXML();


            // Delete previously saved notes
            unset($xmlOld->annots);


            $arrBadFields = array();
            // Update each field, one by one
            foreach ($XMLData->fields->field as $updateFieldInfo) {
                $strBadField = $this->parseXfdfFieldVal($updateFieldInfo, $xmlOld);
                if (!empty($strBadField)) {
                    $arrBadFields[] = $strBadField;
                }
            }

            $filteredXml = $this->clearEmptyFields($xmlOld);
            $this->removeInternalFields($filteredXml);
            $XML_FILE_DATA = $filteredXml->asXML();

            if (!empty($arrBadFields)) {
                $this->_log->saveBadPdfField(
                    array(
                        'user_id'      => $currentMemberId,
                        'client_id'    => $updateMemberId,
                        'company_id'   => $updateCompanyId,
                        'assignedForm' => $pdfid
                    ),
                    $assignedFormInfo['file_name'] . ' (' . date('Y-m-d', strtotime($assignedFormInfo['version_date'])) . ')',
                    implode('<br/>', $arrBadFields),
                    $XML_FILE_DATA
                );
            }
        } else {
            // There is no created xfdf file, so simply get incoming xfdf and save in file
            $XMLData = $this->clearEmptyFields($XMLData);
            $this->removeInternalFields($XMLData);
            $XML_FILE_DATA = $XMLData->asXML();
        }

        $thisXfdfCreationResult = $this->saveXfdf($xfdf_file_name, $XML_FILE_DATA);
        if ($thisXfdfCreationResult != self::XFDF_SAVED_CORRECTLY) {
            $this->_log->debugErrorToFile(__FUNCTION__ . ', line ' . __LINE__ . ' File:' . $xfdf_file_name, $XML_FILE_DATA, 'pdf');
            $result->code = $thisXfdfCreationResult;
            return $result;
        }

        $xfdfAfterChanges = $XML_FILE_DATA;


        // ***********************
        // Save sync info in DB and
        // all related xfdf files
        // ***********************

        // Update sync fields in all xfdf files in relation to the map created
        // for these sync fields
        $arrUpdateFields        = array();
        $arrProfileFieldsUpdate = array();
        if (is_array($fieldsMap)) {
            // Collect fields names and their new values which we need to update
            foreach ($fieldsMap as $mapFieldInfo) {
                $updateValue = $XMLData->xpath("//*[@name='" . $mapFieldInfo['from_field_name'] . "']");
                if (isset($updateValue[0]->value)) {
                    $val = $updateValue[0]->value;
                    $val = $val == 'Off' ? '' : $val;

                    $arrUpdateFields[$mapFieldInfo['to_family_member_id']][] = array(
                        'fieldName'  => $mapFieldInfo['to_field_name'],
                        'fieldValue' => $val
                    );

                    // Save this info for later profile update
                    if (!empty($mapFieldInfo['form_map_type'])) {
                        $arrProfileFieldsUpdate[$mapFieldInfo['to_profile_family_member_id']][$mapFieldInfo['to_profile_field_id']][$mapFieldInfo['parent_member_type']][$mapFieldInfo['form_map_type']] = $val;
                    } else {
                        $arrProfileFieldsUpdate[$mapFieldInfo['to_profile_family_member_id']][$mapFieldInfo['to_profile_field_id']][$mapFieldInfo['parent_member_type']] = $val;
                    }
                }
            }
        }

        // If there are fields which we need to update -
        // update these fields in xfdf files
        $resCode = $this->updateXfdfFiles($updateMemberId, $arrUpdateFields);
        if ($resCode != self::XFDF_SAVED_CORRECTLY) {
            $result->code = $resCode;
            return $result;
        }

        $result                        = new XfdfParseResult();
        $result->code                  = self::XFDF_SAVED_CORRECTLY;
        $result->pdfId                 = $pdfid;
        $result->xfdfFileName          = $updateMemberId . '.xfdf';
        $result->updateMemberId        = $updateMemberId;
        $result->updateCompanyId       = $updateCompanyId;
        $result->fieldsToUpdate        = $arrUpdateFields;
        $result->profileFieldsToUpdate = $arrProfileFieldsUpdate;
        $result->parentsData           = $arrParentsData;
        $result->mainParentId          = $mainParentId;
        $result->before                = $xfdfBeforeChanges;
        $result->after                 = $xfdfAfterChanges;

        return $result;
    }

    /**
     * Update xfdf files for specific case
     *
     * @param int $updateMemberId
     * @param array $arrUpdateFields
     * @return int code id
     * @throws Exception
     */
    public function updateXfdfFiles($updateMemberId, $arrUpdateFields)
    {
        $xfdfDirectory = $this->_files->getClientXFDFFTPFolder($updateMemberId);

        if (is_array($arrUpdateFields) && count($arrUpdateFields)) {
            foreach ($arrUpdateFields as $xfdfMemberName => $arrUpdateInfo) {
                // Create or update a file
                $updateXfdfFileName = $xfdfDirectory . '/' . $xfdfMemberName . '.xfdf';
                if (!file_exists($updateXfdfFileName)) {
                    // Create new and write sync fields for it
                    $xmlString = $this->getEmptyXfdf();

                    $xml = simplexml_load_string($xmlString);
                } else {
                    // Update sync fields in it
                    $xml = $this->readXfdfFromFile($updateXfdfFileName);
                }

                if ($xml === false || !is_object($xml)) {
                    // Not in xml format, skip
                    $this->_log->debugErrorToFile(__FUNCTION__ . ', line ' . __LINE__ . ' Not object:', $xml, 'pdf');
                    break;
                } elseif (is_array($arrUpdateInfo) && count($arrUpdateInfo)) {
                    // Update or create fields and their values in this xfdf file
                    foreach ($arrUpdateInfo as $updateFieldInfo) {
                        $this->updateFieldInXfdf($updateFieldInfo['fieldName'], $updateFieldInfo['fieldValue'], $xml);
                    }

                    // Update file
                    $updateResult = $this->saveXfdf($updateXfdfFileName, $xml->asXML());

                    if ($updateResult != self::XFDF_SAVED_CORRECTLY) {
                        return $updateResult;
                    }
                }
            } // eo foreach for all files and fields
        }

        return self::XFDF_SAVED_CORRECTLY;
    }

    /**
     * Replace incorrect symbols
     *
     * @param string $text
     * @return string corrected text
     */
    public function replaceXmlSpecialChars($text)
    {
        return str_replace('&#039;', '&apos;', htmlspecialchars($text, ENT_QUOTES));
    }

    /**
     * Clear empty fields
     *
     * @param SimpleXMLElement $inputSimpleXml
     * @return SimpleXMLElement
     */
    public function clearEmptyFields($inputSimpleXml)
    {
        $dom_sxe = dom_import_simplexml($inputSimpleXml);

        if (!empty($dom_sxe)) {
            $allFields = $dom_sxe->getElementsByTagName("field");

            $domElementsToRemove = array();
            foreach ($allFields as $checkField) {
                if (!isset($checkField->nodeValue) || $this->_isPdfFieldValueEmpty($checkField->nodeValue)) {
                    $domElementsToRemove[] = $checkField;
                }
            }

            foreach ($domElementsToRemove as $domElement) {
                $domElement->parentNode->removeChild($domElement);
            }

            $simpleXmlResult = simplexml_import_dom($dom_sxe);
        } else {
            $simpleXmlResult = $inputSimpleXml;
        }

        return $simpleXmlResult;
    }

    /**
     * Check if value passed from pdf is empty (don't save to xfdf)
     *
     * @param string $strFieldValue
     * @return bool true if empty
     */
    private function _isPdfFieldValueEmpty($strFieldValue)
    {
        return in_array($strFieldValue, array('', "\n", "\r", "\r\n", "\n\r"));
    }


    /**
     * Update xfdf files when update client's profile
     *
     * @param int $companyId
     * @param int $memberId
     * @param array $arrClientInfo
     * @param int $parentMemberTypeId
     * @return bool true on success
     * @throws Exception
     */
    public function updateXfdfOnProfileUpdate($companyId, $memberId, $arrClientInfo, $parentMemberTypeId)
    {
        // exit if incorrect client's info
        if (!is_array($arrClientInfo) || empty($arrClientInfo)) {
            return false;
        }

        // Load sync fields list
        $arrSynFields = $this->_forms->getFormSynField()->fetchFormFields(true);
        if (!is_array($arrSynFields)) {
            $arrSynFields = array();
        }


        // Collect all fields which we need update in xfdf files
        $arrUpdateFields = array();
        $arrXFDFToCreate = array();
        foreach ($arrClientInfo as $key => $val) {
            switch ($key) {
                case 'dependents':
                    // Get dependents info
                    if (is_array($val)) {
                        $arrRelationship = array();
                        foreach ($val as $relationship => $arrDependentData) {
                            if (!in_array($relationship, array('spouse', 'child', 'parent', 'sibling', 'other', 'other1', 'other2'))) {
                                continue;
                            }

                            foreach ($arrDependentData as $arrDependentInfo) {
                                if (array_key_exists($relationship, $arrRelationship)) {
                                    $arrRelationship[$relationship] += 1;
                                } else {
                                    $arrRelationship[$relationship] = 1;
                                }

                                if (is_array($arrDependentInfo)) {
                                    // Get applicant id - will be used in xfdf file name
                                    $line      = $relationship == 'spouse' && $arrRelationship[$relationship] == 1 ? '' : $arrRelationship[$relationship];
                                    $applicant = $relationship . $line;

                                    if (!preg_match('/^other\d*$/', $applicant)) {
                                        $arrXFDFToCreate[] = $applicant;
                                    }

                                    foreach ($arrDependentInfo as $depProfileFieldId => $depProfileVal) {
                                        $arrToUpdate = $this->_forms->getFormMap()->getMappedXfdfFields($applicant, $depProfileFieldId);

                                        if (is_array($arrToUpdate)) {
                                            foreach ($arrToUpdate as $updateInfo) {
                                                $saveVal = $depProfileVal;

                                                if (array_key_exists($updateInfo['from_syn_field_id'], $arrSynFields)) {
                                                    $fieldId = $arrSynFields[$updateInfo['from_syn_field_id']];
                                                } else {
                                                    $fieldId = $updateInfo['from_syn_field_id'];
                                                }

                                                if (!empty($updateInfo['form_map_type'])) {
                                                    switch ($updateInfo['form_map_type']) {
                                                        // For date fields we check if it's not "zero date" like 0000-00-00 and then retrieve corresponding part out of it
                                                        case 'date_year':
                                                            $saveVal = (($depProfileVal != '0000-00-00') && ($timestamp = strtotime($depProfileVal))) ? date('Y', $timestamp) : '';
                                                            break;
                                                        case 'date_month':
                                                            $saveVal = (($depProfileVal != '0000-00-00') && ($timestamp = strtotime($depProfileVal))) ? date('m', $timestamp) : '';
                                                            break;
                                                        case 'date_day':
                                                            $saveVal = (($depProfileVal != '0000-00-00') && ($timestamp = strtotime($depProfileVal))) ? date('d', $timestamp) : '';
                                                            break;

                                                        // Do nothing because this is simple text field
                                                        default:
                                                            break;
                                                    }
                                                }

                                                $arrUpdateFields[$updateInfo['from_family_member_id']][$fieldId] = $saveVal;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        // We need remove all previously created xfdf files
                    }
                    break;

                default:
                    $arrXFDFToCreate[] = 'main_applicant';

                    // Get from DB all related pdf fields (related to client) which we need to update
                    $arrToUpdate = $this->_forms->getFormMap()->getMappedXfdfFields('main_applicant', $key);

                    if (is_array($arrToUpdate)) {
                        foreach ($arrToUpdate as $updateInfo) {
                            if (($updateInfo['parent_member_type_name'] != 'case') && ($parentMemberTypeId != $updateInfo['parent_member_type'])) {
                                continue;
                            }
                            if (array_key_exists($updateInfo['from_syn_field_id'], $arrSynFields)) {
                                $fieldId = $arrSynFields[$updateInfo['from_syn_field_id']];
                            } else {
                                $fieldId = $updateInfo['from_syn_field_id'];
                            }

                            $saveVal2 = $val;

                            if (!empty($updateInfo['form_map_type'])) {
                                // For date fields we check if it's not "zero date" like 0000-00-00 and then retrieve corresponding part out of it
                                switch ($updateInfo['form_map_type']) {
                                    case 'date_year':
                                        $saveVal2 = (($val != '0000-00-00') && ($timestamp = strtotime($val))) ? date('Y', $timestamp) : '';
                                        break;
                                    case 'date_month':
                                        $saveVal2 = (($val != '0000-00-00') && ($timestamp = strtotime($val))) ? date('m', $timestamp) : '';
                                        break;
                                    case 'date_day':
                                        $saveVal2 = (($val != '0000-00-00') && ($timestamp = strtotime($val))) ? date('d', $timestamp) : '';
                                        break;

                                    // Do nothing because this is simple text field
                                    default:
                                        break;
                                }
                            }

                            $arrUpdateFields[$updateInfo['from_family_member_id']][$fieldId] = $saveVal2;
                        }
                    }
                    break;
            }
        }

        // Create the default xfdfs if they were not created yet (except of 'other' records because we don't have the 'client form id' here)
        $arrXFDFToCreate = Settings::arrayUnique($arrXFDFToCreate);
        foreach ($arrXFDFToCreate as $familyMemberId) {
            $arrNewFormData = array(
                'client_form_id'     => 0,
                'family_member_type' => $familyMemberId,
            );

            $this->_forms->copyDefaultXfdfToClient($companyId, $memberId, $arrNewFormData);
        }

        // Update related xfdf files
        if (!empty($arrUpdateFields)) {
            // Update json files
            foreach ($arrUpdateFields as $familyMemberId => $arrXfdfUpdateInfo) {
                $jsonFilePath = $this->_files->getClientJsonFilePath($memberId, $familyMemberId, null);
                if (file_exists($jsonFilePath)) {
                    $savedJson = file_get_contents($jsonFilePath);
                    $arrData   = (array)json_decode($savedJson);

                    if (is_array($arrXfdfUpdateInfo)) {
                        foreach ($arrXfdfUpdateInfo as $updateFieldId => $updateFieldVal) {
                            $arrData[$updateFieldId] = $updateFieldVal;
                        }
                    }

                    file_put_contents($jsonFilePath, json_encode($arrData));
                }
            }

            // Prepare path to xfdf file
            $xfdfDirectory = $this->_files->getClientXFDFFTPFolder($memberId);
            if (!$this->_files->createFTPDirectory($xfdfDirectory)) {
                $this->_log->debugErrorToFile('Directory not created', $xfdfDirectory, 'files');
                return self::XFDF_DIRECTORY_NOT_CREATED;
            }

            $pathToDefaultXfdf = $this->_forms->getDefaultXfdfPath($companyId);

            foreach ($arrUpdateFields as $familyMemberId => $arrXfdfUpdateInfo) {
                // Load the file
                $xfdf_file_name = $xfdfDirectory . '/' . $familyMemberId . '.xfdf';

                if (!file_exists($xfdf_file_name)) {
                    if (!empty($pathToDefaultXfdf) && file_exists($pathToDefaultXfdf)) {
                        // Load 'default' xfdf
                        $oXml = $this->readXfdfFromFile($pathToDefaultXfdf);
                    } else {
                        // Generate empty xfdf
                        $xmlString = $this->getEmptyXfdf();

                        $oXml = simplexml_load_string($xmlString);
                    }
                } else {
                    // Load saved xfdf
                    $oXml = $this->readXfdfFromFile($xfdf_file_name);
                }

                if ($oXml === false || !is_object($oXml)) {
                    $this->_log->debugErrorToFile(__FUNCTION__ . ', line ' . __LINE__ . ' Not object:', $oXml, 'pdf');
                    continue;
                }

                // Create/update these fields
                if (is_array($arrXfdfUpdateInfo)) {
                    foreach ($arrXfdfUpdateInfo as $updateFieldId => $updateFieldVal) {
                        $this->updateFieldInXfdf($updateFieldId, $updateFieldVal, $oXml);
                    }
                }

                // Remove fields with empty values
                $clearedXml = $this->clearEmptyFields($oXml);

                // Save changes
                $this->saveXfdf($xfdf_file_name, $clearedXml->asXML());
            }
        }

        return true;
    }


    /**
     * Create xfdf file and save it
     *
     * @param string $xfdfFileName - path where file will be saved
     * @param string $xfdfData
     * @return int result of creating
     */
    public function saveXfdf($xfdfFileName, $xfdfData)
    {
        // Create a directory if it doesn't exist
        if (!$this->_files->createFTPDirectory(dirname($xfdfFileName))) {
            return self::XFDF_FILE_NOT_SAVED;
        }

        if (!$handle = fopen($xfdfFileName, 'w')) {
            $this->_log->debugErrorToFile('Failed to open the XFDF file for writing', $xfdfFileName, 'pdf');
            return self::XFDF_FILE_NOT_SAVED;
        }

        if (fwrite($handle, $xfdfData, strlen($xfdfData)) === false) {
            $this->_log->debugErrorToFile('Failed to write XFDF data to the file', $xfdfFileName, 'pdf');
            return self::XFDF_FILE_NOT_SAVED;
        }

        fclose($handle);

        return self::XFDF_SAVED_CORRECTLY;
    }


    /**
     * Detect encoding from the provided string
     *
     * @param string $string
     * @return string|null
     */
    private function detectEncoding($string)
    {
        static $list = array('utf-8', 'windows-1251', 'ISO-8859-1');

        foreach ($list as $item) {
            $sample = iconv($item, $item, $string);
            if (md5($sample) == md5($string)) {
                return $item;
            }
        }

        return null;
    }


    /**
     * Read xfdf from saved file
     *
     * @param string $xfdfFileName - path to xfdf file
     * @param string $pathToAnnotation
     * @param bool $booAnnotationsEnabled
     * @return SimpleXMLElement|bool - false on error
     * @throws Exception
     */
    public function readXfdfFromFile($xfdfFileName, $pathToAnnotation = '', $booAnnotationsEnabled = false)
    {
        error_reporting(E_ERROR | E_PARSE);

        try {
            if (file_exists($xfdfFileName)) {
                // Convert to avoid errors
                $strContent = file_get_contents($xfdfFileName);
                $strContent = preg_replace('/&(?!amp;|quot;|nbsp;|gt;|lt;|laquo;|raquo;|copy;|reg;|bul;|rsquo;)/', '&amp;', $strContent);
                $strContent = StringTools::stripInvisibleCharacters($strContent, false);

                $encoding = $this->detectEncoding($strContent);
                if ($encoding != 'utf-8') {
                    $strContent = iconv("ISO-8859-1", "UTF-8", $strContent);
                }

                $oXML = simplexml_load_string($strContent);
                if ($oXML === false) {
                    // Something wrong during parsing xfdf (xml)
                    throw new Exception('Error during reading xfdf file');
                }

                // Load annotation if needed
                if ($booAnnotationsEnabled && !empty($pathToAnnotation) && file_exists($pathToAnnotation)) {
                    $strAnnotations = file_get_contents($pathToAnnotation);
                    $XML_FILE_DATA  = $oXML->asXML();

                    $search_data   = '<xfdf xmlns="http://ns.adobe.com/xfdf/" xml:space="preserve">';
                    $XML_FILE_DATA = str_replace($search_data, $search_data . $strAnnotations, $XML_FILE_DATA);

                    $oXML = simplexml_load_string($XML_FILE_DATA);
                }

                return $oXML;
            }
        } catch (Exception $e) {
            // Error happened - send email to support
            $subject = $e->getMessage();
            $message = '<h2>Path to xfdf:</h2>';
            $message .= $xfdfFileName;
            $this->_log->debugErrorToFile($subject, $message, 'pdf');
            $this->_mailer->sendEmailToSupport($subject, $message);
        }

        return false;
    }

    /**
     * Generate empty xfdf
     *
     * @return string xml
     */
    public function getEmptyXfdf()
    {
        return '<?xml version="1.0" encoding="UTF-8"?><xfdf xmlns="http://ns.adobe.com/xfdf/" xml:space="preserve"><fields/></xfdf>';
    }

    /**
     * Generate empty xdp file content
     *
     * @return string xml
     */
    public function getEmptyXdp()
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<xdp:xdp xmlns:xdp="http://ns.adobe.com/xdp/">' .
            '<xfa:datasets xmlns:xfa="http://www.xfa.org/schema/xfa-data/1.0/"><xfa:data><form1><Page1/></form1></xfa:data></xfa:datasets>' .
            '<pdf href="form.pdf" xmlns="http://ns.adobe.com/xdp/pdf/" />' .
            '<xfdf xmlns="http://ns.adobe.com/xfdf/" xml:space="preserve"><annots/></xfdf>' .
            '</xdp:xdp>';
    }

    /**
     * Update fields in xdp file
     *
     * @param SimpleXMLElement $oXml
     * @param $arrUpdateFields
     * @return SimpleXMLElement
     */
    public function updateFieldInXDP($oXml, $arrUpdateFields)
    {
        if (is_array($arrUpdateFields) && count($arrUpdateFields)) {
            $page1 = $oXml->xpath('//Page1');
            foreach ($arrUpdateFields as $nodeKey => $nodeVal) {
                $el = $oXml->xpath('//' . $nodeKey);

                if ($el) {
                    $dom = dom_import_simplexml($el[0]);

                    $dom->nodeValue = $nodeVal;
                } elseif ($page1) {
                    $page1[0]->addChild($nodeKey, $nodeVal);
                }
            }
        }

        return $oXml;
    }


    /**
     * Update path to pdf form in xdp file
     *
     * @param SimpleXMLElement $oXml
     * @param string $baseUrl
     * @param int $formId
     * @return SimpleXMLElement
     */
    public function updatePDFUrlInXDPFile($oXml, $baseUrl, $formId)
    {
        $pdfUrl = $baseUrl . '/forms/index/open-assigned-pdf?pdfid=' . $formId;

        $pdf = $oXml->pdf;
        if ($pdf) {
            $pdf->attributes()->href = $pdfUrl;
        } else {
            $pdf = $oXml->addChild('pdf');
            $pdf->addAttribute('href', $pdfUrl);
        }

        return $oXml;
    }


    /**
     * Update specific field with value in xfdf file
     * Field will be created if it is not yet
     *
     * @param string|array $fName - field name or array
     * @param string $fValue - field value
     * @param SimpleXMLElement $oXml - object of class SimpleXMLElement $oXml which must be updated
     * @return bool true on success, otherwise false
     */
    public function updateFieldInXfdf($fName, $fValue, &$oXml)
    {
        try {
            if (!is_array($fName)) {
                // Support for XX.YY.ZZ fields
                if (strpos($fName ?? '', '.')) {
                    $fName = explode('.', $fName);
                } else {
                    $fName = array($fName);
                }
            }

            if (!is_object($oXml)) {
                $this->_log->debugErrorToFile(__FUNCTION__ . ', line ' . __LINE__ . ' Not object:', $oXml, 'pdf');

                return false;
            }

            // Create first field if it doesn't exist yet
            /** @var SimpleXMLElement $subField */
            $subField = $oXml->xpath("//*[@name='$fName[0]']");
            if (empty($subField)) {
                // Create it!
                $subField = $oXml->fields->addChild('field');
                $subField->addAttribute('name', $fName[0]);
            }

            // Create all subfields if needed
            if (count($fName) > 1) {
                for ($i = 1, $iMax = count($fName); $i < $iMax; $i++) {
                    /** @var SimpleXMLElement $parentField */
                    $parentField = is_array($subField) ? $subField[0] : $subField;
                    $subField    = $parentField->xpath("//*[@name='$fName[$i]']");
                    if (empty($subField)) {
                        // Create it!
                        $parentField = is_array($parentField) ? $parentField[0] : $parentField;
                        $subField    = $parentField->addChild('field');
                        $subField->addAttribute('name', $fName[$i]);
                    }
                }
            }

            // Update field value
            if (isset($subField[0]->value)) {
                // Update value field property
                $subField[0]->value = $fValue;
            } else {
                // Add value field property

                // Why it is not updated automatically on save????
                $subField[0]->addChild('value', $this->replaceXmlSpecialChars($fValue));
            }

            if (preg_match("/(fieldset_(.*)_field_(.*)_)(\d+)/", $fName[0], $arrFieldsetNamesMatches)) {
                $fieldsetFields = $oXml->xpath("//*[@name[contains(., '" . $arrFieldsetNamesMatches[1] . "')]]");

                foreach ($fieldsetFields as &$fieldsetField) {
                    if (preg_match("/fieldset_.*_field_.*_(\d+)/", (string)$fieldsetField['name'], $arrFieldsetNumberMatches)) {
                        if ((int)$arrFieldsetNumberMatches[1] > (int)$arrFieldsetNamesMatches[4]) {
                            $fieldsetField->value = '';
                        }
                    }
                }
                unset($fieldsetField);
            } elseif (preg_match("/fieldset_(.*)/", $fName[0], $arrFieldsetNamesMatches) && $fValue == '0') {
                $fieldsetFields = $oXml->xpath("//*[@name[contains(., '" . $arrFieldsetNamesMatches[1] . "')]]");

                foreach ($fieldsetFields as &$fieldsetField1) {
                    if (preg_match("/fieldset_.*_field_.*_(\d+)/", (string)$fieldsetField1['name'])) {
                        $fieldsetField1->value = '';
                    }
                }
                unset($fieldsetField1);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'pdf');

            return false;
        }

        return true;
    }


    /**
     * Remove field from xfdf (XML) by its name
     *
     * @param string $removeFieldName - field name
     * @param SimpleXMLElement $XMLData - where field is located
     */
    public function removeFieldByName($removeFieldName, $XMLData)
    {
        $result = $XMLData->xpath("//*[@name='$removeFieldName']");
        if (!empty($result)) {
            list($theNodeToBeDeleted) = $result;
        }
        if (!empty($theNodeToBeDeleted)) {
            $oNode = dom_import_simplexml($theNodeToBeDeleted);
            $oNode->parentNode->removeChild($oNode);
        }
    }

    /**
     * Remove internal fields from xml object
     *
     * @param SimpleXMLElement $XMLData
     */
    public function removeInternalFields(&$XMLData)
    {
        if ($XMLData instanceof SimpleXMLElement) {
            // Remove internal fields
            $arrClearFields = array(
                'server_assigned_id',
                'server_form_version',
                'server_locked_form',
                'server_result',
                'server_result_code',
                'server_submit_button',
                'server_xfdf_loaded',
                'server_confirmation',
                'server_time_stamp',

                'srv_tmp_status',
                'server_generate',
                'server_url'
            );
            foreach ($arrClearFields as $removeFieldName) {
                $this->removeFieldByName($removeFieldName, $XMLData);
            }
        }
    }

    /**
     * Generate pdf file from html
     *
     * @param string $html
     * @param string $destinationName The name of the file when saved. Note that special characters are removed and blanks characters are replaced with the underscore character.
     * @param string $destinationParam Destination where to send the document. It can take one of the following values:
     * I: send the file inline to the browser (default). The plug-in is used if available. The name given by name is used when one selects the "Save as" option on the link generating the PDF.
     * D: send to the browser and force a file download with the name given by name.
     * F: save to a local file with the name given by name.
     * S: return the document as a string. name is ignored.
     * @param array $arrPdfSettings
     * @param bool $booUseMpdf
     * @throws MpdfException
     */
    public function htmlToPdf($html, $destinationName = 'print.pdf', $destinationParam = 'I', $arrPdfSettings = array(), $booUseMpdf = false)
    {
        ini_set("pcre.backtrack_limit", "5000000");

        $default_settings = array(
            'setPrintHeader' => true,
            'header_title'   => 'PDF Report',
            'setHeaderFont'  => array('helvetica', 'B', 18),
            'SetAuthor'      => $this->_config['site_version']['company_name']
        );

        $arrPdfSettings = array_merge($default_settings, $arrPdfSettings);

        // set document information

        //page orientation (P=portrait, L=landscape)
        $PDF_PAGE_ORIENTATION = $arrPdfSettings['PDF_PAGE_ORIENTATION'] ?? 'P';

        // document unit of measure pt=point, mm=millimeter, cm=centimeter, in=inch
        $PDF_UNIT = $arrPdfSettings['PDF_UNIT'] ?? 'mm';

        //page format default A4
        $PDF_PAGE_FORMAT = $arrPdfSettings['PDF_PAGE_FORMAT'] ?? 'A4';

        $PDF_HEADER_LOGO_URL   = $arrPdfSettings['PDF_HEADER_LOGO_URL'] ?? '';
        $PDF_HEADER_LOGO_WIDTH = $arrPdfSettings['PDF_HEADER_LOGO_WIDTH'] ?? 0;

        $pdf_header_title = $pdf_header_string = '';
        if (isset($arrPdfSettings['header_title'])) {
            $pdf_header_title = $arrPdfSettings['header_title'];
        }
        // other parameters: boolean $unicode = true, String $encoding = 'UTF-8', boolean $diskcache = false
        if ($booUseMpdf) {
            $pdf = new Mpdf(
                [
                    'default_font-size'   => 5,
                    'defaultPageNumStyle' => 1,
                    'mode'                => 'utf-8',
                    'format'              => $PDF_PAGE_FORMAT,
                    'orientation'         => $PDF_PAGE_ORIENTATION,
                    'tempDir'             => $this->_config['directory']['tmp'],
                ]
            );

            // default header
            if (isset($arrPdfSettings['SetCreator'])) {
                $pdf->SetCreator($arrPdfSettings['SetCreator']);
            }
            if (isset($arrPdfSettings['SetAuthor'])) {
                $pdf->SetAuthor($arrPdfSettings['SetAuthor']);
            }
            if (isset($arrPdfSettings['SetTitle'])) {
                $pdf->SetTitle($arrPdfSettings['SetTitle']);
            }
            if (isset($arrPdfSettings['SetSubject'])) {
                $pdf->SetSubject($arrPdfSettings['SetSubject']);
            }
            if (isset($arrPdfSettings['SetKeywords'])) {
                $pdf->SetKeywords($arrPdfSettings['SetKeywords']);
            }

            if (isset($arrPdfSettings['header_string'])) {
                $pdf_header_string = str_replace(PHP_EOL, '<br>', $arrPdfSettings['header_string']);
            }
            // set default header data
            if (isset($arrPdfSettings['PDF_HEADER_LOGO_URL'])) {
                $htmlHeader = '<img src="' . $PDF_HEADER_LOGO_URL . '" width="' . $PDF_HEADER_LOGO_WIDTH . '"><b>' . $pdf_header_title . '</b><br>' . $pdf_header_string;
            } else {
                $htmlHeader = '<b>' . $pdf_header_title . '</b><br>' . $pdf_header_string;
            }

            $header = array(
                'L'    => array(
                    'content'     => $htmlHeader,
                    'font-size'   => 10,
                    'font-style'  => '',
                    'font-family' => 'serif',
                    'color'       => '#000000'
                ),
                'line' => 1
            );

            if (isset($arrPdfSettings['header_title']) || isset($arrPdfSettings['header_string']) || isset($arrPdfSettings['PDF_HEADER_LOGO_URL'])) {
                $pdf->SetHeader($header, 'O');
            }

            $pdf->defaultfooterline = 1;
            $pdf->SetFooter('{PAGENO}/{nbpg}');

            if (isset($arrPdfSettings['SetFont'])) {
                $pdf->SetFont($arrPdfSettings['SetFont']['name'], $arrPdfSettings['SetFont']['style'], $arrPdfSettings['SetFont']['size']);
            } else {
                $pdf->SetFont('helvetica', '', 7);
            }

            $pdf->SetMargins(15, 15, 27);

            //set auto page breaks
            $pdf->SetAutoPageBreak(true, 25);

            // add a page
            $pdf->AddPage();

            // Apply watermark if needed
            if (isset($arrPdfSettings['watermark'])) {
                // WaterMark Size
                $ImageW = 220;
                $ImageH = 200;
                // WaterMark Positioning - locate in the center of the page
                $pdf->SetWatermarkImage($arrPdfSettings['watermark'], 0.25, array($ImageW, $ImageH), 'P');
                $pdf->showWatermarkImage = true;
                // Reset Alpha Settings
                $pdf->SetAlpha(1);
            }
            // output the HTML content
            $pdf->WriteHTML('table {width: 100%;} table {font-size:10px}', HTMLParserMode::HEADER_CSS);
            $pdf->WriteHTML($html);
            //Send the document to a given destination: string, local file or browser.
        } else {
            $pdf = new TCPDF($PDF_PAGE_ORIENTATION, $PDF_UNIT, $PDF_PAGE_FORMAT, true, 'UTF-8', false);

            // default header
            if (isset($arrPdfSettings['setPrintHeader'])) {
                $pdf->setPrintHeader($arrPdfSettings['setPrintHeader']);
            }

            if (isset($arrPdfSettings['SetCreator'])) {
                $pdf->setCreator($arrPdfSettings['SetCreator']);
            }
            if (isset($arrPdfSettings['SetAuthor'])) {
                $pdf->setAuthor($arrPdfSettings['SetAuthor']);
            }
            if (isset($arrPdfSettings['SetTitle'])) {
                $pdf->setTitle($arrPdfSettings['SetTitle']);
            }
            if (isset($arrPdfSettings['SetSubject'])) {
                $pdf->setSubject($arrPdfSettings['SetSubject']);
            }
            if (isset($arrPdfSettings['SetKeywords'])) {
                $pdf->setKeywords($arrPdfSettings['SetKeywords']);
            }
            if (isset($arrPdfSettings['header_string'])) {
                $pdf_header_string = $arrPdfSettings['header_string'];
            }
            // set default header data

            if (isset($arrPdfSettings['header_title']) || isset($arrPdfSettings['header_string']) || isset($arrPdfSettings['PDF_HEADER_LOGO_URL'])) {
                $pdf->setHeaderData($PDF_HEADER_LOGO_URL, $PDF_HEADER_LOGO_WIDTH, $pdf_header_title, $pdf_header_string);
                if (isset($arrPdfSettings['setHeaderFont'])) {
                    $pdf->setHeaderFont($arrPdfSettings['setHeaderFont']);
                }

                $pdf->setFooterFont(array('helvetica', '', 8));

                if (isset($arrPdfSettings['SetHeaderMargin'])) {
                    $pdf->setHeaderMargin($arrPdfSettings['SetHeaderMargin']);
                } else {
                    $pdf->setHeaderMargin(5);
                }

                $pdf->setFooterMargin(5);
            }

            if (isset($arrPdfSettings['SetFont'])) {
                $pdf->setFont($arrPdfSettings['SetFont']['name'], $arrPdfSettings['SetFont']['style'], $arrPdfSettings['SetFont']['size']);
            } else {
                $pdf->setFont('helvetica', '', 7);
            }

            $pdf->setMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);

            //set auto page breaks
            $pdf->setAutoPageBreak(true, PDF_MARGIN_BOTTOM);

            // add a page
            $pdf->AddPage();

            // output the HTML content
            $pdf->writeHTML($html, true, 0, true, 0);

            // Apply watermark if needed
            if (isset($arrPdfSettings['watermark'])) {
                // WaterMark Size
                $ImageW = 220;
                $ImageH = 200;

                $pagesCount = $pdf->getNumPages();
                for ($i = 1; $i <= $pagesCount; $i++) {
                    $pdf->setPage($i);
                    $myPageWidth  = $pdf->getPageWidth();
                    $myPageHeight = $pdf->getPageHeight();

                    // WaterMark Positioning - locate in the center of the page
                    $myX = ($myPageWidth / 2) - $ImageW / 2;
                    $myY = ($myPageHeight / 2) - $ImageH / 2;

                    $pdf->setAlpha(0.25);
                    $pdf->Image($arrPdfSettings['watermark'], $myX, $myY, $ImageW, $ImageH, '', '', '', true, 60);
                }

                // Reset Alpha Settings
                $pdf->setAlpha();
            }

            // reset pointer to the last page
            $pdf->lastPage();
            //Send the document to a given destination: string, local file or browser.
        }
        $pdf->Output($destinationName, $destinationParam);
    }

    /**
     * Generate "Certificate of Naturalisation" (CON) pdf file
     *
     * @param int $companyId
     * @param int $caseId
     * @param bool $isLocal
     * @param array $arrParams
     * @param bool $booAsDraft
     * @param bool $targetFolderAccess
     * @param $arrConNumbers
     * @return FileInfo|array FileInfo on success, otherwise array (string error message, path to the file)
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function generatePdfCon($companyId, $caseId, $isLocal, $arrParams, $booAsDraft, $targetFolderAccess, $arrConNumbers)
    {
        $strError    = '';
        $pdfFilePath = '';

        try {
            $originalTemplate = SystemTemplate::loadOne(['title' => 'Certificate of Naturalisation (for PDF generation)']);
            if (empty($originalTemplate->system_template_id)) {
                $strError = $this->_tr->translate('There is no system template.');
            }

            if (empty($strError)) {
                $strOriginalTemplate = $this->_systemTemplates::fixEncoding($originalTemplate->template);

                $pdf = new DominicaTCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

                $pdf->setMargins(20, 20, 20);

                $pdf->setHeaderFont(array('helvetica', '', 11));
                $pdf->setHeaderMargin(12);

                $pdf->setFont('helvetica', '', 11);

                //set auto page breaks
                $pdf->setAutoPageBreak(true, PDF_MARGIN_BOTTOM);

                $pdf->setFontSubsetting(false);

                $photoX         = 20;
                $photoY         = 155;
                $pagesCount     = $arrParams['pages_count'];
                $arrHtmlContent = array();
                for ($i = 1; $i <= $pagesCount; $i++) {
                    $conNumber = false;
                    if (isset($arrParams['dependent_id_' . $i])) {
                        $conNumber = isset($arrConNumbers[$arrParams['dependent_id_' . $i]]) ? '#' . $arrConNumbers[$arrParams['dependent_id_' . $i]] : false;
                    }

                    if ($conNumber) {
                        $pdf->setHtmlHeader('<table border="0" cellpadding="0" width="100%"><tr><td align="right">' . $conNumber . '&nbsp;&nbsp;&nbsp;</td></tr></table>');
                    } else {
                        $pdf->setHtmlHeader('');
                    }

                    // add a page
                    $pdf->AddPage();

                    $arrApplicantData = array(
                        'main_applicant_name'   => $arrParams['main_applicant_name_' . $i] ?? '',
                        'main_applicant_name_2' => $arrParams['main_applicant_name_2_' . $i] ?? '',
                        'main_applicant_name_3' => $arrParams['main_applicant_name_3_' . $i] ?? '',
                        'self_name'             => $arrParams['self_name_' . $i] ?? '',
                        'address'               => $arrParams['address_' . $i] ?? '',
                        'occupation'            => $arrParams['occupation_' . $i] ?? '',
                        'place_of_birth'        => $arrParams['place_of_birth_' . $i] ?? '',
                        'sex'                   => $arrParams['sex_' . $i] ?? '',
                        'date_of_birth'         => $arrParams['date_of_birth_' . $i] ?? '',
                        'marital_status'        => $arrParams['marital_status_' . $i] ?? '',
                        'name_of_spouse'        => $arrParams['name_of_spouse_' . $i] ?? '',
                        'page_number'           => $i
                    );

                    if (!empty($arrApplicantData['self_name']) && !in_array($arrApplicantData['self_name'], array('himself', 'herself'))) {
                        $arrApplicantData['self_name'] = '<b><i><u>' . $arrApplicantData['self_name'] . '</u></i></b>';
                    }

                    $replacements      = $this->getTemplateReplacements($arrApplicantData);
                    $strParsedTemplate = $this->_systemTemplates->processText($strOriginalTemplate, $replacements);

                    // output the HTML content
                    $pdf->writeHTML($strParsedTemplate, true, false, true);

                    // place the photo
                    if (isset($arrParams['photo_path_' . $i]) && !empty($arrParams['photo_path_' . $i])) {
                        $pathToTemp = $this->_encryption->decode($arrParams['photo_path_' . $i]);
                        if (file_exists($pathToTemp)) {
                            $pdf->Image($pathToTemp, $photoX, $photoY, 0, 0, '', '', '', false, 150);

                            $strParsedTemplate = Settings::strReplaceFirst('<td width="32%"></td>', '<td width="32%" rowspan="7"><img src="file://' . str_replace('\\', '/', $pathToTemp) . '"></td>', $strParsedTemplate);
                            $strParsedTemplate = str_replace('<td width="32%"></td>', '', $strParsedTemplate);
                        } else {
                            throw new Exception('Incorrect path to the photo:' . $pathToTemp);
                        }
                    }

                    // Prepare content for the docx version
                    $strParsedTemplate = str_replace('<tr><td colspan="3"></td></tr>', '', $strParsedTemplate);
                    $strParsedTemplate = str_replace('<p></p>', '<p style="font-size: 1px"></p>', $strParsedTemplate);
                    $strParsedTemplate = str_replace('вЂ¦', '...', $strParsedTemplate);

                    $pageHeader = $conNumber ? sprintf(
                        '<div style="%s text-align: right; font-weight: bold">%s</div>',
                        empty($i) ? '' : 'page-break-before: always;',
                        $conNumber
                    ) : '';

                    $arrHtmlContent[] = $pageHeader . $strParsedTemplate;
                }

                // Apply watermark if needed
                if ($booAsDraft) {
                    // WaterMark Size
                    $ImageW = 220;
                    $ImageH = 200;

                    $pagesCount = $pdf->getNumPages();
                    for ($i = 1; $i <= $pagesCount; $i++) {
                        $pdf->setPage($i);
                        $myPageWidth  = $pdf->getPageWidth();
                        $myPageHeight = $pdf->getPageHeight();

                        // WaterMark Positioning - locate in the center of the page
                        $myX = ($myPageWidth / 2) - $ImageW / 2;
                        $myY = ($myPageHeight / 2) - $ImageH / 2;

                        $pdf->setAlpha(0.25);
                        $pdf->Image('public/images/draft.png', $myX, $myY, $ImageW, $ImageH, '', '', '', true, 60);
                    }

                    // Reset Alpha Settings
                    $pdf->setAlpha();
                }


                // reset pointer to the last page
                $pdf->lastPage();

                $tempFileName = tempnam($this->_config['directory']['tmp'], 'gcon');

                $pdf->Output($tempFileName, 'F');

                if (!$booAsDraft) {
                    // Send the document to a given destination: string, local file or browser.
                    $fileName = $this->_tr->translate('Certificate of Naturalisation');
                    if (isset($arrParams['main_applicant_name_1'])) {
                        $fileName .= $this->_tr->translate(' for ') . $arrParams['main_applicant_name_1'];
                    }

                    $pdfFileName  = $this->_files->convertToFilename($fileName . '.pdf');
                    $docxFileName = $this->_files->convertToFilename($fileName . '.docx');

                    $folderPath = $this->_files->getClientCorrespondenceFTPFolder($companyId, $caseId, $isLocal);

                    if ($targetFolderAccess) {
                        $booLocal    = $this->_auth->isCurrentUserCompanyStorageLocal();
                        $pdfFilePath = $this->_files->generateFileName($folderPath . '/' . $pdfFileName, $booLocal);

                        if ($booLocal) {
                            $this->_files->createFTPDirectory(dirname($pdfFilePath));
                            // Tmp file will later be cleaned up by Cron
                            $booSuccess = copy($tempFileName, $pdfFilePath);
                        } else {
                            $booSuccess = $this->_files->getCloud()->uploadFile($tempFileName, $pdfFilePath);
                        }

                        // Generate Docx
                        if ($booSuccess) {
                            /** @var Documents $oDocuments */
                            $oDocuments = $this->_serviceContainer->get(Documents::class);

                            // Decrease default margin
                            $oDocuments->getPhpDocx()->modifyPageLayout(
                                'letter',
                                array(
                                    'marginTop'    => 100,
                                    'marginBottom' => 100,
                                    'marginRight'  => 1400,
                                    'marginLeft'   => 1400
                                )
                            );

                            $docxTempFileName = tempnam($this->_config['directory']['tmp'], 'gdocx');
                            if ($oDocuments->getPhpDocx()->createDocxFromHtml(implode('', $arrHtmlContent), $docxTempFileName)) {
                                $docxFilePath = $this->_files->generateFileName($folderPath . '/' . $docxFileName, $booLocal);

                                if ($booLocal) {
                                    $booSuccess = $this->_files->moveLocalFile($docxTempFileName . '.docx', $docxFilePath);
                                } else {
                                    $booSuccess = $this->_files->getCloud()->uploadFile($docxTempFileName . '.docx', $docxFilePath);
                                }
                            }
                        }

                        if ($booSuccess) {
                            return new FileInfo($pdfFileName, $tempFileName, true);
                        } else {
                            $strError = $this->_tr->translate('PDF file was not copied to the Correspondence folder.');
                        }
                    } else {
                        // Just output the file, don't save it to the folder if we don't have access to it
                        return new FileInfo($pdfFileName, $tempFileName, true);
                    }

                    if (empty($strError)) {
                        /** @var Notes $notes */
                        $notes = $this->_serviceContainer->get(Notes::class);
                        $notes->updateNote(0, (int)$caseId, 'CON Issued.', true);
                        exit();
                    }
                } else {
                    $pdfFilePath = $tempFileName;
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($strError, $pdfFilePath);
    }

    /**
     * Generate PDF report and output it in browser
     *
     * @param string $fileName
     * @param $arrReportInfo - array with report info which will be parsed
     * @param $title - title will be showed at the top in header
     * @param $companyName - company name will be showed at the top in header
     * @param $taLabel - TrustAccount label
     *
     * @return bool true on success
     * @throws MpdfException
     */
    public function createClientsBalancesReport($fileName, $arrReportInfo, $title, $companyName, $taLabel)
    {
        $client_width = 120;
        $cell_width   = 65;

        // Generate report's body
        $table = '<table border="1" cellspacing="0" cellpadding="2">
                  <tr>
                    <th rowspan="2" width="' . $client_width . '" align="center" bgcolor="#ccc">Case</th>
                    <th colspan="4" width="' . $cell_width * 4 . '" align="center" bgcolor="#ccc">' . $taLabel . ' Summary</th>
                    <th colspan="2" width="' . $cell_width * 2 . '" align="center" bgcolor="#ccc">Outstanding Balance</th>
                  </tr>
                  <tr>
                    <th width="' . $cell_width . '" align="center" bgcolor="#ccc">Deposits not verified (Secondary ' . $taLabel . ')</th>
                    <th width="' . $cell_width . '" align="center" bgcolor="#ccc">Available Total (Secondary ' . $taLabel . ')</th>
                    <th width="' . $cell_width . '" align="center" bgcolor="#ccc">Deposits not verified (Primary ' . $taLabel . ')</th>
                    <th width="' . $cell_width . '" align="center" bgcolor="#ccc">Available Total (Primary ' . $taLabel . ')</th>
                    <th width="' . $cell_width . '" align="center" bgcolor="#ccc">&nbsp;</th>
                    <th width="' . $cell_width . '" align="center" bgcolor="#ccc">&nbsp;</th>
                  </tr>';

        foreach ($arrReportInfo as $arrRow) {
            $table .= '<tr><td width="' . $client_width . '">' . $arrRow['client'] . '</td>'
                . '<td width="' . $cell_width . '" align="right">' . $arrRow['secondary_currency_not_verified'] . '</td>'
                . '<td width="' . $cell_width . '" align="right">' . $arrRow['secondary_currency_available'] . '</td>'
                . '<td width="' . $cell_width . '" align="right">' . $arrRow['primary_currency_not_verified'] . '</td>'
                . '<td width="' . $cell_width . '" align="right">' . $arrRow['primary_currency_available'] . '</td>'
                . '<td width="' . $cell_width . '" align="right">' . $arrRow['secondary_currency_outst_balance'] . '</td>'
                . '<td width="' . $cell_width . '" align="right">' . $arrRow['primary_currency_outst_balance'] . '</td></tr>';
        }

        $table .= '</table>';

        $this->htmlToPdf(
            $table,
            $fileName . '.pdf',
            'I',
            array(
                'header_title'  => $title,
                'setHeaderFont' => array('helvetica', '', 8),
                'header_string' => $companyName . PHP_EOL . 'Report Date ' . $this->_settings->formatDate(date('Y-m-d'))
            ),
            true
        );


        return true;
    }

    /**
     * Create Clients Transactions Report (in pdf format)
     *
     * @param string $fileName
     * @param array $arrReportInfo
     * @param string $report
     * @param string $title
     * @param string $company
     * @param string $date
     * @return bool
     * @throws MpdfException
     */
    public function createClientsTransactionsReport($fileName, $arrReportInfo, $report, $title, $company, $date)
    {
        $booShowFeeReceived = in_array($report, array('transaction-all', 'transaction-fees-received'));
        $booShowFeeDue      = in_array($report, array('transaction-all', 'transaction-fees-due'));

        $table = '';
        foreach ($arrReportInfo as $arrClients) {
            $table .= '<h3>' . $arrClients['client'] . '</h3>';
            $table .= '<table border="1" cellpadding="2" cellspacing="0">';

            foreach ($arrClients['ta'] as $arrTA) {
                $table .= '<tr><td colspan="5" width="' . ($report == 'transaction-all' ? 510 : 465) . '" bgcolor="#B9B3EF">' . $arrTA['name'] . ' (' . Clients\Accounting::getCurrencyLabel($arrTA['currency']) . ')</td></tr>';
                $table .= '<tr>' .
                    '<td width="50" align="center" style="font-size:17px;" bgcolor="#ccc">Date</td>' .
                    '<td width="230" align="center" style="font-size:17px;" bgcolor="#ccc">Description</td>' .
                    ($booShowFeeReceived ? '<td width="45" align="center" style="font-size:17px;" bgcolor="#ccc">Fee Received</td>' : '') .
                    ($booShowFeeDue ? '<td width="45" align="center" style="font-size:17px;" bgcolor="#ccc">Fee Due</td>' : '') .
                    '<td width="140" align="center" style="font-size:17px;" bgcolor="#ccc">Comments</td>' .
                    '</tr>';

                $received_total = $due_total = 0;
                foreach ($arrTA['transactions'] as $transaction) {
                    $receivedGst = empty($transaction['received_gst']) ? '' : '<br />' . $transaction['received_gst'];
                    $dueGst      = empty($transaction['due_gst']) ? '' : '<br />' . $transaction['due_gst'];
                    $description = $transaction['description'] . (empty($transaction['destination']) ? '' : "<br />Destination: " . $transaction['destination']);

                    $table .= '<tr>' .
                        '<td align="center" width="50">' . $transaction['date'] . '</td>' .
                        '<td width="230">' . $description . '</td>' .
                        ($booShowFeeReceived ? '<td width="45" align="right">' . $transaction['fees_received'] . $receivedGst . '</td>' : '') .
                        ($booShowFeeDue ? '<td width="45" align="right">' . $transaction['fees_due'] . $dueGst . '</td>' : '') .
                        '<td width="140">' . $transaction['status'] . '</td>' .
                        '</tr>';

                    $received_total += (float)$transaction['fees_received'] + (float)$transaction['received_gst'];
                    $due_total      += (float)$transaction['fees_due'] + (float)$transaction['due_gst'];
                }

                // totals
                $table .= '<tr>' .
                    '<td align="left" width="280" colspan="2"><b>Total</b></td>' .
                    ($booShowFeeReceived ? '<td width="45" align="right"><b>' . Accounting::formatPrice($received_total) . '</b></td>' : '') .
                    ($booShowFeeDue ? '<td width="45" align="right"><b>' . Clients\Accounting::formatPrice($due_total) . '</b></td>' : '') .
                    '<td width="140">&nbsp;</td>' .
                    '</tr>';
            }

            $table .= '</table>';
            $table .= '<div> </div>';
        }

        $this->htmlToPdf(
            $table,
            $fileName . '.pdf',
            'I',
            array(
                'header_title'  => $title,
                'setHeaderFont' => array('helvetica', '', 8),
                'header_string' => $company . PHP_EOL . $date
            ),
            true
        );

        return true;
    }

    public function filterFormIds($arrFormsIds)
    {
        if (is_array($arrFormsIds) && count($arrFormsIds)) {
            $arrCorrectFormIds = array();
            foreach ($arrFormsIds as $formId) {
                if (is_numeric($formId) && $formId > 0) {
                    $arrCorrectFormIds[] = $formId;
                }
            }
            return $arrCorrectFormIds;
        }

        return false;
    }


    public function createPDF($companyId, $memberId, $userId, $arrFormsFormatted, $arrFamilyMembers)
    {
        $strError    = '';
        $arrPdfFiles = array();

        // Select forms
        $select = (new Select())
            ->from(['a' => 'form_assigned'])
            ->columns(['form_assigned_id', 'updated_by', 'family_member_id', 'use_revision', 'finalized_date', 'last_update_date'])
            ->join(array('v' => 'form_version'), 'a.form_version_id = v.form_version_id', array('form_version_id', 'file_path', 'file_name'), Select::JOIN_LEFT_OUTER)
            ->where(['a.form_assigned_id' => array_keys($arrFormsFormatted)]);

        $arrAssignedFormsInfo = $this->_db2->fetchAll($select);

        if (!count($arrAssignedFormsInfo)) {
            $strError = $this->_tr->translate('Forms not found');
        }

        if (empty($strError)) {
            $arrAssignedFormInfo   = array();
            $arrNewCompleteFormIds = array();
            foreach ($arrAssignedFormsInfo as $assignedFormInfo) {
                $arrNewCompleteFormIds[] = $assignedFormInfo['form_assigned_id'];

                $arrAssignedFormInfo[$assignedFormInfo['form_assigned_id']] = $assignedFormInfo;
            }

            // Insert new records in db
            foreach ($arrNewCompleteFormIds as $formId) {
                $fName = $lName = '';
                foreach ($arrFamilyMembers as $familyMemberInfo) {
                    if (preg_match('/^other\d*$/', $arrAssignedFormInfo[$formId]['family_member_id'])) {
                        $lName = 'Other';

                        break;
                    } elseif ($familyMemberInfo['id'] == $arrAssignedFormInfo[$formId]['family_member_id']) {
                        $fName = $familyMemberInfo['fName'];
                        $lName = $familyMemberInfo['lName'];

                        break;
                    }
                }

                // Merge PDF with Xfdf data
                $arrFileInfo = array(
                    'formId'         => $formId,
                    'formVersionId'  => $arrAssignedFormInfo[$formId]['form_version_id'],
                    'authorId'       => $userId,
                    'memberId'       => $memberId,
                    'familyMemberId' => $arrAssignedFormInfo[$formId]['family_member_id'],
                    'fName'          => $fName,
                    'lName'          => $lName,
                    'companyId'      => $companyId,
                    'fileName'       => $arrAssignedFormInfo[$formId]['file_name'],
                    'useRevision'    => $arrAssignedFormInfo[$formId]['use_revision'],
                    'filePath'       => $arrAssignedFormInfo[$formId]['file_path'],
                    'lastUpdateDate' => $arrAssignedFormInfo[$formId]['last_update_date']
                );

                $booFlatten       = !empty($arrAssignedFormInfo[$formId]['finalized_date']) || !isset($arrFormsFormatted[$formId]) || $arrFormsFormatted[$formId] == 'read-only';
                $pathToXfdfDir    = $this->_files->getClientXFDFFTPFolder($memberId, $companyId);
                $pathToAnnotation = $this->getAnnotationPath($pathToXfdfDir, $formId);
                if (!file_exists($pathToAnnotation)) {
                    $pathToAnnotation = false;
                }

                $flattenPdfPath = $this->createPrintVersion($arrFileInfo, $booFlatten, $pathToAnnotation);
                if ($flattenPdfPath === false) {
                    $arrPdfFiles = [];
                    $strError    = $this->_tr->translate('Print version was not created: ') . $arrFileInfo['fileName'] . '.pdf';
                    break;
                }

                $arrPdfFiles[] = array(
                    'file'         => $flattenPdfPath,
                    'filename'     => $arrFileInfo['fileName'] . '.pdf',
                    'use_revision' => $arrFileInfo['useRevision'],
                    'pdf_id'       => $formId
                );
            }
        }

        return array(
            'error' => $strError,
            'files' => $arrPdfFiles
        );
    }


    /**
     * Generate html template from prospect's profile
     *
     * @param array $arrProspectInfo - prospect's info
     * @param array $arrCategories - default options for categories
     * @return string - generated html content
     */
    public function exportProspectDataToHtml($arrProspectInfo, $arrCategories)
    {
        try {
            $booAustralia = $this->_config['site_version']['version'] == 'australia';
            $template     = $booAustralia ?
                'forms/partials/pdf-prospect-export-to-client-au.phtml' :
                'forms/partials/pdf-prospect-export-to-client.phtml';

            $viewModel = new ViewModel(
                [
                    'arrProspectInfo' => $arrProspectInfo,
                    'arrCategories'   => $arrCategories,
                ]
            );
            $viewModel->setTemplate($template);
            $strHtml = $this->_renderer->render($viewModel);
        } catch (Exception $e) {
            $strHtml = '';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strHtml;
    }


    /**
     * Generate pdf file with prospect's info
     *
     * @param string $strHeader - must be showed at the top of the file
     * @param string $pathToPDF - path to the file
     * @param string $strHtmlContent - content of the file
     * @param bool $booOutputToFile - true to output pdf in file, false to output to browser
     *
     * @return bool true on success
     */
    public function generatePDFFromHtml($strHeader, $pathToPDF, $strHtmlContent, $booOutputToFile = true)
    {
        $booSuccess = false;
        try {
            error_reporting(E_ERROR);

            $arrPdfOptions = array(
                'header_title'  => null,
                'header_string' => $strHeader,
                'setHeaderFont' => array('helvetica', '', 8)
            );

            if (!$booOutputToFile) {
                // Output in browser
                $this->htmlToPdf(
                    $strHtmlContent,
                    'export.pdf',
                    'I',
                    $arrPdfOptions,
                    true
                );
                $booSuccess = true;
            } else {
                // Output to file
                $pathToTmp = getcwd() . '/' . $this->_config['directory']['tmp'] . '/' . md5((string)time());

                $this->htmlToPdf(
                    $strHtmlContent,
                    $pathToTmp,
                    'F',
                    $arrPdfOptions,
                    true
                );

                if (file_exists($pathToTmp)) {
                    if ($this->_auth->isCurrentUserCompanyStorageLocal()) {
                        $booSuccess = rename($pathToTmp, $pathToPDF);
                    } else {
                        $booSuccess = $this->_files->getCloud()->uploadFile($pathToTmp, $pathToPDF);
                    }
                    unlink($pathToTmp);
                }
            }
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }


    /**
     * Check received xfdf and merge it with default xfdf file
     * @param $XMLData
     * @param $currentMemberCompanyId
     * @return int
     * @throws Exception
     */
    public function syncDefaultXfdf($XMLData, $currentMemberCompanyId)
    {
        if ($XMLData === false || !is_object($XMLData)) {
            if (!empty($XMLData)) {
                $this->_log->debugErrorToFile(__FUNCTION__ . ', line ' . __LINE__ . ' Not object:', $XMLData, 'pdf');
            }

            return self::XFDF_INCORRECT_INCOMING_XFDF;
        }

        // Unset specific fields
        if (isset($XMLData->f)) {
            unset($XMLData->f);
        }

        if (isset($XMLData->ids)) {
            unset($XMLData->ids);
        }

        if (isset($XMLData->annots)) {
            unset($XMLData->annots);
        }

        // ***********************
        // Save xfdf file
        // ***********************
        $xfdf_file_name = $this->_forms->getDefaultXfdfPath($currentMemberCompanyId);
        if (file_exists($xfdf_file_name)) {
            // Merge previously saved info
            $xmlOld = $this->readXfdfFromFile($xfdf_file_name);

            if ($xmlOld === false || !is_object($xmlOld)) {
                $this->_log->debugErrorToFile(__FUNCTION__ . ', line ' . __LINE__ . ' Not object:', $xmlOld, 'pdf');

                return self::XFDF_FILE_NOT_SAVED;
            }

            // Delete previously saved notes
            if ($xmlOld->annots) {
                unset($xmlOld->annots);
            }


            // Update each field, one by one
            foreach ($XMLData->fields->field as $updateFieldInfo) {
                $this->parseXfdfFieldVal($updateFieldInfo, $xmlOld);
            }

            $XMLData = $xmlOld;
        }

        $XMLData = $this->clearEmptyFields($XMLData);
        $this->removeInternalFields($XMLData);
        $XML_FILE_DATA = $XMLData->asXML();

        $thisXfdfCreationResult = $this->saveXfdf($xfdf_file_name, $XML_FILE_DATA);
        if ($thisXfdfCreationResult != self::XFDF_SAVED_CORRECTLY) {
            $this->_log->debugErrorToFile(__FUNCTION__ . ', line ' . __LINE__ . ' File:' . $xfdf_file_name, $XML_FILE_DATA, 'pdf');
        }

        return $thisXfdfCreationResult;
    }

    /**
     * Load xfdf file name in relation to family member type
     *
     * @param string $familyMemberId
     * @param int $formId
     * @return string file name
     */
    public static function getXfdfFileName($familyMemberId, $formId)
    {
        if (preg_match('/^other\d*$/', $familyMemberId)) {
            $fileName = 'other' . '_' . $formId . '.xfdf';
        } else {
            $fileName = $familyMemberId . '.xfdf';
        }

        return $fileName;
    }

    public function letterOnLetterheadToPdf($html, $letterheadsPath, $destinationName = 'print.pdf', $destinationParam = 'I', $arrPdfSettings = array())
    {
        $default_settings = array(
            'setPrintHeader' => true,
            'header_title'   => 'PDF Report',
            'setHeaderFont'  => array('helvetica', 'B', 18),
            'SetAuthor'      => $this->_config['site_version']['company_name']
        );

        $arrPdfSettings = array_merge($default_settings, $arrPdfSettings);

        // set document information
        //page orientation (P=portrait, L=landscape)
        $PDF_PAGE_ORIENTATION = $arrPdfSettings['PDF_PAGE_ORIENTATION'] ?? 'P';

        // document unit of measure pt=point, mm=millimeter, cm=centimeter, in=inch
        $PDF_UNIT = $arrPdfSettings['PDF_UNIT'] ?? 'mm';

        //page format default A4
        $PDF_PAGE_FORMAT = $arrPdfSettings['PDF_PAGE_FORMAT'] ?? 'A4';
        // other parameters: boolean $unicode = true, String $encoding = 'UTF-8', boolean $diskcache = false
        $pdf = new Letterhead($PDF_PAGE_ORIENTATION, $PDF_UNIT, $PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetLetterheadsPath($letterheadsPath);
        $pdf->SetLetterheadSettings($arrPdfSettings['letterhead_settings']);
        $pdf->SetPageNumberSettings($arrPdfSettings['page_number_settings']);

        // default header
        if (isset($arrPdfSettings['setPrintHeader'])) {
            $pdf->setPrintHeader($arrPdfSettings['setPrintHeader']);
        }

        if (isset($arrPdfSettings['SetCreator'])) {
            $pdf->setCreator($arrPdfSettings['SetCreator']);
        }
        if (isset($arrPdfSettings['SetAuthor'])) {
            $pdf->setAuthor($arrPdfSettings['SetAuthor']);
        }
        if (isset($arrPdfSettings['SetTitle'])) {
            $pdf->setTitle($arrPdfSettings['SetTitle']);
        }
        if (isset($arrPdfSettings['SetSubject'])) {
            $pdf->setSubject($arrPdfSettings['SetSubject']);
        }
        if (isset($arrPdfSettings['SetKeywords'])) {
            $pdf->setKeywords($arrPdfSettings['SetKeywords']);
        }

        $PDF_HEADER_LOGO_URL   = $arrPdfSettings['PDF_HEADER_LOGO_URL'] ?? '';
        $PDF_HEADER_LOGO_WIDTH = $arrPdfSettings['PDF_HEADER_LOGO_WIDTH'] ?? 0;

        $pdf_header_title = $pdf_header_string = '';
        if (isset($arrPdfSettings['header_title'])) {
            $pdf_header_title = $arrPdfSettings['header_title'];
        }
        if (isset($arrPdfSettings['header_string'])) {
            $pdf_header_string = $arrPdfSettings['header_string'];
        }
        // set default header data

        if (isset($arrPdfSettings['header_title']) || isset($arrPdfSettings['header_string']) || isset($arrPdfSettings['PDF_HEADER_LOGO_URL'])) {
            $pdf->setHeaderData($PDF_HEADER_LOGO_URL, $PDF_HEADER_LOGO_WIDTH, $pdf_header_title, $pdf_header_string);
            if (isset($arrPdfSettings['setHeaderFont'])) {
                $pdf->setHeaderFont($arrPdfSettings['setHeaderFont']);
            }

            if (isset($arrPdfSettings['SetHeaderMargin'])) {
                $pdf->setHeaderMargin($arrPdfSettings['SetHeaderMargin']);
            } else {
                $pdf->setHeaderMargin(5);
            }
        }

        if (isset($arrPdfSettings['SetFont'])) {
            $pdf->setFont($arrPdfSettings['SetFont']['name'], $arrPdfSettings['SetFont']['style'], $arrPdfSettings['SetFont']['size']);
        } else {
            $pdf->setFont('helvetica', '', 7);
        }

        if (array_key_exists('first_margin_left', $arrPdfSettings['letterhead_settings'])) {
            $pdf->setLeftMargin($arrPdfSettings['letterhead_settings']['first_margin_left']);
        }
        if (array_key_exists('first_margin_right', $arrPdfSettings['letterhead_settings'])) {
            $pdf->setRightMargin($arrPdfSettings['letterhead_settings']['first_margin_right']);
        }
        //set auto page breaks
        $pdf->setAutoPageBreak(true, PDF_MARGIN_BOTTOM);
        // add a page
        $pdf->AddPage();

        $pattern = "/font-size:[ ]*[\d]*px;/";
        $html    = preg_replace($pattern, '', $html);
        $pdf->writeHTML($html, true, 0, true, 0);

        // Apply watermark if needed
        if (isset($arrPdfSettings['watermark'])) {
            // WaterMark Size
            $ImageW = 220;
            $ImageH = 200;

            $pagesCount = $pdf->getNumPages();
            for ($i = 1; $i <= $pagesCount; $i++) {
                $pdf->setPage($i);
                $myPageWidth  = $pdf->getPageWidth();
                $myPageHeight = $pdf->getPageHeight();

                // WaterMark Positioning - locate in the center of the page
                $myX = ($myPageWidth / 2) - $ImageW / 2;
                $myY = ($myPageHeight / 2) - $ImageH / 2;

                $pdf->setAlpha(0.25);
                $pdf->Image($arrPdfSettings['watermark'], $myX, $myY, $ImageW, $ImageH, '', '', '', true, 150);
            }

            // Reset Alpha Settings
            $pdf->setAlpha();
        }

        // reset pointer to the last page
        $pdf->lastPage();
        //Send the document to a given destination: string, local file or browser.
        $pdf->Output($destinationName, $destinationParam);
    }

    /**
     * Provides an array of changes to be applied on the fly where key is an old field name and value is a new field name.
     * @param $caseCreationDate
     * @param $formName
     * @return array
     */
    public function jsonChangeOnFlyNeeded($caseCreationDate, $formName)
    {
        $changes = Bcpnp::jsonChangesOnTheFly();

        // Refine changes list to get those applicable to $formName of $formVersion only
        $filteredChanges = array_filter(
            $changes,
            function ($n) use ($formName, $caseCreationDate) {
                return (($n['formName'] == $formName) && ($n['changeIsNotNeededSince'] > $caseCreationDate));
            }
        );

        $changesToApply = array();
        foreach ($filteredChanges as $change) {
            $changesToApply = array_merge($changesToApply, $change['changes']);
        }

        return $changesToApply;
    }

    /**
     * Applies all the needed changes to the JSON file
     * @param $jsonData
     * @param $caseCreationDate
     * @param $formName
     * @return array
     */
    public function jsonDataChangeOnTheFly($jsonData, $caseCreationDate, $formName)
    {
        $changesToApply = $this->jsonChangeOnFlyNeeded($caseCreationDate, $formName);
        if (empty($changesToApply)) {
            return array(false, $jsonData);
        }

        $changesApplied = false;
        foreach ($jsonData as $fieldName => $value) {
            if (is_array($value) && !isset($value['originalName'])) {
                // We are in a fieldset here
                foreach ($value as $sectionKey => $sectionValues) {
                    foreach ($sectionValues as $sectionFieldName => $sectionFieldValue) {
                        $fieldNamePieces    = explode('-', $sectionFieldName);
                        $fieldNameRectified = $fieldNamePieces[0];
                        $fieldNameKey       = $fieldNamePieces[1];

                        $changeTo = $changesToApply[$fieldNameRectified] ?? null;
                        if (!is_null($changeTo)) {
                            $jsonData[$fieldName][$sectionKey]["$changeTo-$fieldNameKey"] = $jsonData[$fieldName][$sectionKey][$sectionFieldName];
                            unset($jsonData[$fieldName][$sectionKey][$sectionFieldName]);
                            $changesApplied = true;
                        }
                    }
                }
            } else {
                $changeTo = $changesToApply[$fieldName] ?? null;
                if (!is_null($changeTo)) {
                    $jsonData[$changeTo] = $jsonData[$fieldName];
                    unset($jsonData[$fieldName]);
                    $changesApplied = true;
                }
            }
        }

        return array($changesApplied, $jsonData);
    }

    /**
     * Loads data from JSON file
     * @param $memberId
     * @param $familyMemberId
     * @param $caseCreationDate
     * @param $formId
     * @param $fileName
     * @param $isBcpnpForm
     * @return array|false|mixed
     */
    public function loadDataFromJson($memberId, $familyMemberId, $caseCreationDate, $formId, $fileName, $isBcpnpForm)
    {
        try {
            $arrCaseData = [];
            // Check if we need load data from json or xfdf file
            $jsonFilePath = $this->_files->getClientJsonFilePath($memberId, $familyMemberId, $formId);
            if (file_exists($jsonFilePath)) {
                $savedJson   = file_get_contents($jsonFilePath);
                $arrCaseData = json_decode($savedJson, $isBcpnpForm);

                if ($isBcpnpForm) {
                    // Some form fields are changed from time to time and in order to not introduce new form version
                    // for small changes we migrate data on the fly
                    list($changesApplied, $arrCaseData) = $this->jsonDataChangeOnTheFly($arrCaseData, $caseCreationDate, $fileName);
                    if ($changesApplied) {
                        file_put_contents($jsonFilePath, json_encode($arrCaseData));
                    }

                    foreach ($arrCaseData as $key => $fieldValue) {
                        if (!empty($fieldValue) && is_string($fieldValue) && strtotime($fieldValue)) {
                            $d = DateTime::createFromFormat('d-m-Y', $fieldValue);
                            if ($d && $d->format('d-m-Y') === $fieldValue) {
                                $fieldValue = $this->_settings->reformatDate($fieldValue, 'd-m-Y', 'Y-m-d');

                                $arrCaseData[$key] = $fieldValue;
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $arrCaseData = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrCaseData;
    }

    /**
     * Load saved info from xfdf file
     * @param $formId
     * @param $memberId
     * @param $familyMemberId
     * @param $booAnnotationsEnabled
     * @param $bcpnpForm
     * @return array
     */
    public function loadDataFromXfdf($formId, $memberId, $familyMemberId, $booAnnotationsEnabled, $bcpnpForm = false)
    {
        try {
            // Check if we need load data from json or xfdf file
            $fileName      = static::getXfdfFileName($familyMemberId, $formId);
            $pathToXfdfDir = $this->_files->getClientXFDFFTPFolder($memberId);
            $realPath      = $pathToXfdfDir . '/' . $fileName;

            $oXml = null;
            if (file_exists($realPath)) {
                $pathToAnnotation = $this->getAnnotationPath($pathToXfdfDir, $formId);

                $xml = $this->readXfdfFromFile($realPath, $pathToAnnotation, $booAnnotationsEnabled);
                if ($xml !== false) {
                    $oXml = $xml;
                }
            }

            if (is_null($oXml)) {
                $emptyXfdf = $this->getEmptyXfdf();
                $oXml      = simplexml_load_string($emptyXfdf);
            }

            $strParsedResult = preg_replace('%<value>(\r|\n|\r\n)</value>%', '<value></value>', $oXml->asXML());
            $xmlParser       = xml_parser_create();
            xml_parse_into_struct($xmlParser, $strParsedResult, $arrValues);
            xml_parser_free($xmlParser);

            $arrCaseData = array();
            for ($i = 0; $i < count($arrValues); $i++) {
                if ($arrValues[$i]['tag'] == 'FIELD' && $arrValues[$i]['type'] == 'open') {
                    if (isset($arrValues[$i + 1]['value'])) {
                        $fieldValue = $arrValues[$i + 1]['value'];

                        if ($bcpnpForm) {
                            if (!empty($fieldValue) && strtotime($fieldValue)) {
                                $d = DateTime::createFromFormat('d-m-Y', $fieldValue);
                                if ($d && $d->format('d-m-Y') === $fieldValue) {
                                    $fieldValue = $this->_settings->reformatDate($fieldValue, 'd-m-Y', 'Y-m-d');
                                }
                            }
                        }

                        $arrCaseData[] = array(
                            'field_id'  => $arrValues[$i]['attributes']['NAME'],
                            'field_val' => $fieldValue
                        );
                    } else {
                        $arrCaseData[] = array(
                            'field_id'  => $arrValues[$i]['attributes']['NAME'],
                            'field_val' => ''
                        );
                    }
                }
            }
        } catch (Exception $e) {
            $arrCaseData = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrCaseData;
    }

    /**
     * Generate pdf file name in relation to the assigned dependent's info
     *
     * @param array $arrAssignedFormInfo
     * @param array $arrFormVersionInfo
     * @param array $arrFamilyMembers
     * @return string generated name (without extension)
     */
    public function generateFileNameFromAssignedFormInfo($arrAssignedFormInfo, $arrFormVersionInfo, $arrFamilyMembers)
    {
        $mainApplicantName = '';
        foreach ($arrFamilyMembers as $familyMember) {
            if ($familyMember['id'] == 'main_applicant') {
                $mainApplicantName = $familyMember['lName'] . ', ' . $familyMember['fName'];
                break;
            }
        }

        $familyMemberType = '';
        foreach ($arrFamilyMembers as $familyMember) {
            if ($familyMember['id'] == $arrAssignedFormInfo['family_member_id']) {
                switch ($arrAssignedFormInfo['family_member_id']) {
                    case 'main_applicant':
                        $familyMemberType = $familyMember['value'];
                        break;

                    case 'other':
                        $familyMemberType = empty($arrAssignedFormInfo['assign_alias']) ? $familyMember['value'] : $familyMember['value'] . ' - ' . $arrAssignedFormInfo['assign_alias'];
                        break;

                    default:
                        $familyMemberType = empty($familyMember['fName']) ? $familyMember['value'] : $familyMember['value'] . ' - ' . $familyMember['fName'];
                        break;
                }
                break;
            }
        }
        $familyMemberType = empty($familyMemberType) ? '' : '(' . $familyMemberType . ') ';

        return FileTools::cleanupFileName(trim($mainApplicantName . ' ' . $familyMemberType . $arrFormVersionInfo['file_name']));
    }

    /**
     * Load file path to assigned pdf form.
     * Latest version wil be used if 'versioning' is used.
     *
     * @param $arrFormInfo
     * @return array
     *   $booMergeXfdf - boolean true if xfdf must be used for margin with pdf
     *   $pdfFormPath  - string path to pdf form
     *
     */
    public function _getPDFFormPath($arrFormInfo)
    {
        $formId         = $arrFormInfo['formId'];
        $memberId       = $arrFormInfo['memberId'];
        $strUseRevision = $arrFormInfo['useRevision'];
        $strFilePath    = $arrFormInfo['filePath'];

        $booMergeXfdf = true;
        if ($strUseRevision == 'Y') {
            // Load the latest revision info
            $arrLatestRevisionInfo = $this->_forms->getFormRevision()->getAssignedFormLatestRevision($formId);
            if (is_array($arrLatestRevisionInfo) && count($arrLatestRevisionInfo)) {
                // Load path to already saved pdf form
                $pdfFormPath = $this->_files->getClientBarcodedPDFFilePath($memberId, $arrLatestRevisionInfo['form_revision_id']);
            }
            $booMergeXfdf = false;
        }

        if (empty($pdfFormPath)) {
            $pdfFormPath = $this->_config['directory']['pdfpath_physical'] . '/' . $strFilePath;
        }

        return array(
            'booMergeXfdf' => $booMergeXfdf,
            'pdfFormPath'  => $pdfFormPath
        );
    }


    /**
     * Create flatten version of pdf form with xfdf data
     *
     * @param array $arrFileInfo
     * @return bool result
     */
    public function createFinalizedVersion($arrFileInfo)
    {
        $companyId          = $arrFileInfo['companyId'];
        $memberId           = $arrFileInfo['memberId'];
        $familyMemberId     = $arrFileInfo['familyMemberId'];
        $booFinalizeReplace = $arrFileInfo['booFinalizeReplace'];
        $booLocal           = $this->_auth->isCurrentUserCompanyStorageLocal();

        // LastName, FirstName FormName.pdf
        $fileName = '';
        if (!empty($arrFileInfo['lName'])) {
            $fileName .= $arrFileInfo['lName'];
        }

        if (!empty($arrFileInfo['fName'])) {
            if (!empty($fileName)) {
                $fileName .= ', ';
            }

            $fileName .= $arrFileInfo['fName'];
        }
        if (!empty($fileName)) {
            $fileName .= ' ';
        }

        $fileName .= $arrFileInfo['fileName'];
        $fileName = str_replace('.pdf', '', $fileName);
        $fileName = FileTools::cleanupFileName($fileName);

        try {
            $arrPdfFormPath = $this->_getPDFFormPath($arrFileInfo);
            $pdfFormPath    = $arrPdfFormPath['pdfFormPath'];
            $booMergeXfdf   = $arrPdfFormPath['booMergeXfdf'];

            // File doesn't exists?
            // http://goo.gl/w8Jr1
            if (!file_exists($pdfFormPath)) {
                return false;
            }

            // Path, where xfdf file is located, can be empty (not exists)
            $xfdfPath = '';
            if ($booMergeXfdf) {
                $clientDir = $this->_files->createFTPDirectory($this->_files->getClientXFDFFTPFolder($memberId, $companyId));
                $xfdfPath  = $clientDir . '/' . static::getXfdfFileName($familyMemberId, $arrFileInfo['formId']);
            }


            // Path, where flatten pdf file will be created
            $pathToSubmissions = $this->_files->getClientSubmissionsFolder($companyId, $memberId, $booLocal);

            // Check if there is already created file - create other version
            $flattenPdfPath = $pathToSubmissions . '/' . $fileName . '.pdf';

            if ($booLocal) {
                $this->_files->createFTPDirectory(dirname($flattenPdfPath));
            }

            if ($booFinalizeReplace) {
                $this->_files->deleteFile($flattenPdfPath, $booLocal);
            } else {
                $flattenPdfPath = $this->_files->generateFileName($flattenPdfPath, $booLocal);
            }


            // Run flattening!
            if ($booMergeXfdf) {
                $tempPdfResult = tempnam($this->_config['directory']['pdf_temp'], 'aws');

                $booResult = $this->createFlattenPdf($pdfFormPath, $xfdfPath, $tempPdfResult);
                if ($booResult) {
                    if ($booLocal) {
                        $booResult = rename($tempPdfResult, $flattenPdfPath);
                    } else {
                        $booResult = $this->_files->getCloud()->uploadFile($tempPdfResult, $flattenPdfPath);
                    }
                    unlink($tempPdfResult);
                }
            } elseif ($booLocal) {
                $booResult = rename($pdfFormPath, $flattenPdfPath);
            } else {
                $booResult = $this->_files->getCloud()->uploadFile($pdfFormPath, $flattenPdfPath);
            }
        } catch (Exception $e) {
            $booResult = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booResult;
    }

    /**
     * Create flatten version of pdf form with xfdf data
     *
     * @param array $arrFileInfo
     * @param bool $booFlatten
     * @param bool $annotations
     * @return bool result
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function createPrintVersion($arrFileInfo, $booFlatten = true, $annotations = false)
    {
        try {
            // Path, where flatten pdf file will be created
            $tmpPdfPath     = $this->_files->createFTPDirectory($this->_config['directory']['pdf_temp']);
            $flattenPdfPath = $tmpPdfPath . '/' . 'flatten_form_' . uniqid(rand() . time(), true) . '.pdf';

            // Make sure that there is no such file created yet
            if (file_exists($flattenPdfPath)) {
                unlink($flattenPdfPath);
            }

            // Check which version of the form is used - angular OR pdf
            $booIsAngularForm = $this->_forms->getFormVersion()->isFormVersionAngular($arrFileInfo['formVersionId']);
            if (!$booIsAngularForm) {
                $companyId      = $arrFileInfo['companyId'];
                $memberId       = $arrFileInfo['memberId'];
                $familyMemberId = $arrFileInfo['familyMemberId'];
                $arrPdfFormPath = $this->_getPDFFormPath($arrFileInfo);
                $pdfFormPath    = $arrPdfFormPath['pdfFormPath'];
                $booMergeXfdf   = $arrPdfFormPath['booMergeXfdf'];

                // Path, where pdf form is located, must be created
                if (!file_exists($pdfFormPath)) {
                    return false;
                }

                // Path, where xfdf file is located, can be empty (not exists)
                if ($booMergeXfdf) {
                    $pathToXfdfDir = $this->_files->getClientXFDFFTPFolder($memberId, $companyId);
                    $clientDir     = $this->_files->createFTPDirectory($pathToXfdfDir);
                    $fileName      = static::getXfdfFileName($familyMemberId, $arrFileInfo['formId']);
                    $xfdfPath      = $clientDir . '/' . $fileName;

                    if (file_exists($xfdfPath)) {
                        // Join annotations, if they exists
                        if ($annotations) {
                            $oXml = $this->readXfdfFromFile($xfdfPath, $annotations, true);
                            if ($oXml !== false) {
                                $tmpXFDFPath = $tmpPdfPath . '/' . 'flatten_form_' . uniqid(rand() . time(), true) . '.xfdf';

                                // Make sure that there is no such file created yet
                                if (file_exists($tmpXFDFPath)) {
                                    unlink($tmpXFDFPath);
                                }

                                if ($this->saveXfdf($tmpXFDFPath, $oXml->asXML()) == Pdf::XFDF_SAVED_CORRECTLY) {
                                    $xfdfPath = $tmpXFDFPath;
                                }
                            }
                        }

                        // If we generate the fillable form - we need to include our special fields too
                        if (!$booFlatten) {
                            $oXml = $this->readXfdfFromFile($xfdfPath);
                            if ($oXml === false) {
                                // Not in xml format, return empty doc
                                $emptyXfdf = $this->getEmptyXfdf();

                                $oXml = simplexml_load_string($emptyXfdf);
                            }


                            // Check if this client is locked
                            /** @var Clients $oClients */
                            $oClients  = $this->_serviceContainer->get(Clients::class);
                            $booClient = $this->_auth->isCurrentUserClient();
                            $booLocked = $oClients->isLockedClient($memberId);
                            if ($booClient && $booLocked) {
                                $xfdfLoadedCode = 2;
                            } else {
                                $xfdfLoadedCode = 1;
                            }

                            // $timeStamp = strtotime($assignedFormInfo['version_date']);
                            // $formVersion = 'Form Version Date: ';
                            // $formVersion .= ($timeStamp === false) ? 'Unknown' : date('Y-m-d', $timeStamp);
                            $this->updateFieldInXfdf('server_form_version', '', $oXml);
                            $this->updateFieldInXfdf('server_url', $this->_config['urlSettings']['baseUrl'] . '/forms/sync#FDF', $oXml);
                            $this->updateFieldInXfdf('server_assigned_id', $arrFileInfo['formId'], $oXml);
                            $this->updateFieldInXfdf('server_xfdf_loaded', $xfdfLoadedCode, $oXml);
                            $this->updateFieldInXfdf('server_locked_form', ($booClient && $booLocked) ? 1 : 0, $oXml);
                            $this->updateFieldInXfdf('server_time_stamp', $arrFileInfo['lastUpdateDate'], $oXml);

                            // Make sure that there is no such file created yet
                            $tmpXFDFPath = $tmpPdfPath . '/' . 'flatten_form_full_' . uniqid(rand() . time(), true) . '.xfdf';
                            if (file_exists($tmpXFDFPath)) {
                                unlink($tmpXFDFPath);
                            }

                            $strParsedResult = preg_replace('%<value>(\r|\n|\r\n)</value>%', '<value></value>', $oXml->asXML());
                            if ($this->saveXfdf($tmpXFDFPath, $strParsedResult) == Pdf::XFDF_SAVED_CORRECTLY) {
                                $xfdfPath = $tmpXFDFPath;
                            }
                        }
                    }

                    // Run flattening!
                    $booResult = $this->createFlattenPdf($pdfFormPath, $xfdfPath, $flattenPdfPath, $booFlatten);
                } else {
                    $booResult = copy($pdfFormPath, $flattenPdfPath);
                }
            } else {
                if (stristr(PHP_OS, 'WIN')) {
                    $pathToLibrary = 'library/PhantomJS/phantomjs.exe';
                } else {
                    $pathToLibrary = 'library/PhantomJS/phantomjs';
                }

                $strCommand = $this->_settings->_sprintf(
                    '%path_to_library% --ignore-ssl-errors=true --debug=true %path_to_script% %session_name% %session_id% %top_url% %form_url% %flatten_pdf_path% 2>&1',
                    array(
                        'path_to_library'  => $pathToLibrary,
                        'path_to_script'   => 'library/PhantomJS/convert_to_pdf.js',
                        'session_name'     => session_name(),
                        'session_id'       => session_id(),
                        'top_url'          => $this->_config['urlSettings']['topUrl'],
                        'form_url'         => escapeshellarg($this->_config['urlSettings']['baseUrl'] . '/pdf/' . $arrFileInfo['formVersionId'] . '/?assignedId=' . $arrFileInfo['formId'] . '&print'),
                        'flatten_pdf_path' => $flattenPdfPath
                    )
                );

                try {
                    exec($strCommand, $arrResult);

                    if (!file_exists($flattenPdfPath)) {
                        $this->_log->debugErrorToFile(
                            'Error during angular form flattening.' . PHP_EOL . 'Command: ' . $strCommand,
                            print_r($arrResult, true),
                            'phantomjs'
                        );
                        $booResult = false;
                    } else {
                        $booResult = true;
                    }
                } catch (Exception $e) {
                    $booResult = false;
                    $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
                }
            }

            if ($booResult && file_exists($flattenPdfPath)) {
                return $flattenPdfPath;
            } else {
                return false;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            return false;
        }
    }

    /**
     * Get template replacements
     * @param array $data
     * @return array
     */
    public function getTemplateReplacements($data)
    {
        return [
            '{today_day}'             => date('j') . 'th',
            '{today_month}'           => date('F'),
            '{today_year}'            => date('Y'),
            '{main_applicant_name}'   => $data['main_applicant_name'] ?? '',
            '{main_applicant_name_2}' => $data['main_applicant_name_2'] ?? '',
            '{main_applicant_name_3}' => $data['main_applicant_name_3'] ?? '',
            '{self_name}'             => $data['self_name'] ?? '',
            '{address}'               => $data['address'] ?? '',
            '{occupation}'            => $data['occupation'] ?? '',
            '{place_of_birth}'        => $data['place_of_birth'] ?? '',
            '{date_of_birth}'         => $data['date_of_birth'] ?? '',
            '{marital_status}'        => $data['marital_status'] ?? '',
            '{sex}'                   => $data['sex'] ?? '',
            '{name_of_spouse}'        => $data['name_of_spouse'] ?? '',
            '{photo}'                 => $data['photo'] ?? '',
            '{photo_path}'            => $data['photo_path'] ?? '',
            '{con_number}'            => $data['con_number'] ?? '',
            '{dependent_id}'          => $data['dependent_id'] ?? '',
            '{page_number}'           => $data['page_number'] ?? '',
        ];
    }

}
