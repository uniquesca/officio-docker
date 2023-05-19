<?php

namespace Forms\Controller;

use Clients\Service\Clients;
use Exception;
use Files\Model\FileInfo;
use Files\Service\Files;
use Forms\Service\Forms;
use Forms\Service\Pdf;
use Forms\Service\XfdfDbSync;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\AuthHelper;
use Officio\Service\Company;
use Uniques\Php\StdLib\StringTools;

/**
 * Forms Sync Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class SyncController extends BaseController
{

    /** @var Files */
    private $_files;

    /** @var Clients */
    protected $_clients;

    /** @var Company */
    protected $_company;

    /** @var Pdf */
    protected $_pdf;

    /** @var XfdfDbSync */
    protected $_xfdfDbSync;

    /** @var AuthHelper */
    protected $_authHelper;

    /** @var Forms */
    protected $_forms;

    public function initAdditionalServices(array $services)
    {
        $this->_authHelper = $services[AuthHelper::class];
        $this->_company    = $services[Company::class];
        $this->_clients    = $services[Clients::class];
        $this->_files      = $services[Files::class];
        $this->_pdf        = $services[Pdf::class];
        $this->_xfdfDbSync = $services[XfdfDbSync::class];
        $this->_forms      = $services[Forms::class];
    }

    /**
     * Receive xfdf data, save it to the file and
     * save sync fields in db
     */
    public function indexAction()
    {
        $view = new ViewModel(
            ['content' => null]
        );
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        $pdfId = 0;
        try {
            // Get incoming xfdf
            $XFDFData = file_get_contents('php://input');

            // Fix: sometimes enter is passed incorrectly - replace it to the correct one
            $XFDFData = str_replace('&#xD;', "\n", $XFDFData);
            $XFDFData = StringTools::stripInvisibleCharacters($XFDFData, false);

            $errorCode = null;
            $result = false;
            $incomingXfdf = $this->_pdf->getIncomingXfdf($XFDFData);
            if ($incomingXfdf) {
                $result = $this->xfdfPreprocessor($incomingXfdf);
                if (!is_array($result)) {
                    $errorCode = $result;
                    $result = false;
                } else {
                    list($pdfId, $currentMemberId, $updateMemberId, $updateMemberCompanyId, $assignedFormInfo) = $result;
                    $additionalData = $this->xfdfProcessor()->process($updateMemberId, $updateMemberCompanyId, $assignedFormInfo);
                    if (!is_array($additionalData)) {
                        $errorCode = $additionalData;
                    } else {
                        list($mainParentId, $arrParentsData, $fieldsMap) = $additionalData;
                        $booAnnotationsEnabled = $this->_company->areAnnotationsEnabledForCompany($updateMemberCompanyId);
                        $result = $this->_pdf->pdfToXfdf($pdfId, $incomingXfdf, $assignedFormInfo, $updateMemberCompanyId, $updateMemberId, $currentMemberId, $mainParentId, $arrParentsData, $fieldsMap, $booAnnotationsEnabled);
                        if ($result->code == Pdf::XFDF_SAVED_CORRECTLY) {
                            $result = $this->_xfdfDbSync->syncXfdfResultToDb($result);
                        }
                    }
                }
            } else {
                $errorCode = Pdf::XFDF_INCORRECT_INCOMING_XFDF;
            }

            $resultCode = !is_null($errorCode) ? $errorCode : $result->code;
            $strMessage = $this->_pdf->getCodeResultById($resultCode);
            $xfdfLoadedCode = ($resultCode == Pdf::XFDF_CLIENT_LOCKED) ? 2 : 1;

            // Check result
            if ($result && $result->code == Pdf::XFDF_SAVED_CORRECTLY) {
                $fileName = $result->xfdfFileName;
                $booError = 0;
            } else {
                // Error happened
                $fileName = 'result.xfdf';
                $booError = 1;
            }

            // Check if we need return filled pdf form
            $booUseMerge = (int)$this->findParam('merge', 0);
            if ($booUseMerge) {
                // Check if we know info about assigned pdf form and xfdf
                $view->setVariables(
                    array(
                        'msgConfirmation' => $strMessage,
                        'pdfId' => $pdfId,
                        'booError' => $booError
                    ),
                    true
                );
                $view->setTerminal(true);
            } else {
                $xfdf = $this->_pdf->getEmptyXfdf();
                $oXml = simplexml_load_string($xfdf);

                // Generate return values
                $this->_pdf->updateFieldInXfdf('server_result', $strMessage, $oXml);
                $this->_pdf->updateFieldInXfdf('server_result_code', $resultCode, $oXml);
                $this->_pdf->updateFieldInXfdf('server_xfdf_loaded', $xfdfLoadedCode, $oXml);

                if ($result && !empty($result->updatedOn)) {
                    $this->_pdf->updateFieldInXfdf('server_time_stamp', $result->updatedOn, $oXml);
                }

                $outputXfdf = $oXml->asXML();

                // Return result xfdf
                return $this->file($outputXfdf, $fileName, 'application/vnd.adobe.xfdf', false, false);
            }
        } catch (Exception $e) {
            // Save to log exception info
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            // Return empty xfdf
            $xfdf = $this->_pdf->getEmptyXfdf();
            $oXml = simplexml_load_string($xfdf);
            return $this->file($oXml->asXML(), 'result.xfdf', 'application/vnd.adobe.xfdf', false, false);
        }

        return $view;
    }

    public function saveXdpAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        try {
            $strError = '';

            $XFDFData = file_get_contents('php://input');
            if (empty($XFDFData)) {
                $strError = 'Incorrect Data';
            }

            $oXml = '';
            if (empty($strError)) {
                $oXml = simplexml_load_string($XFDFData);
                if (!$oXml) {
                    $strError = 'Data is in incorrect format';
                }
            }

            if (empty($strError)) {
                // Load assigned form id
                $formId = 0;
                $el = $oXml->xpath('//OfficioPDFFormId');
                if ($el) {
                    $formId = (string)$el[0];
                }


                if (is_numeric($formId) && !empty($formId)) {
                    // Get assigned form info by id
                    $assignedFormInfo = $this->_forms->getFormAssigned()->getAssignedFormInfo($formId);

                    // Return xdp for specific member id
                    $member_id = $assignedFormInfo['client_member_id'];
                    $family_member_id = $assignedFormInfo['family_member_id'];
                    if ($this->_clients->isAlowedClient($member_id) && $this->_company->getCompanyDivisions()->canCurrentMemberEditClient($member_id)) {
                        // Remove unnecessary nodes
                        $arrRemoveNodes = array(
                            'config',
                            'template',
                            'localeSet',
                            'PDFSecurity',
                            'form',
                            array('datasets', 'LOVFile'),
                            'xdc',
                            'xmpmeta'
                        );

                        foreach ($arrRemoveNodes as $strNodeXpath) {
                            if (is_array($strNodeXpath)) {
                                unset($oXml->$strNodeXpath[0]->$strNodeXpath[1]);
                            } else {
                                unset($oXml->$strNodeXpath);
                            }
                        }

                        // Save to the file
                        if ($this->_clients->isLockedClient($member_id)) {
                            $intSavingCode = 5;
                            $strErrorMessage = 'The forms are locked by the ' . $this->_company->getCurrentCompanyDefaultLabel('office') .
                                '. If you need to make any changes, please contact them for assistance.';
                        } else {
                            // Save xdp file
                            $realPath = $this->_files->getClientXdpFilePath($member_id, $family_member_id, $formId);
                            $oXml->asXML($realPath);

                            $intSavingCode = 0;
                            $strErrorMessage = 'Your form was successfully saved to Officio.';
                        }


                        // Save result message
                        // Update internal fields
                        $arrUpdateFields = array(
                            'OfficioErrorCode' => $intSavingCode,
                            'OfficioErrorMessage' => $strErrorMessage
                        );
                        $oXml = $this->_pdf->updateFieldInXDP($oXml, $arrUpdateFields);

                        // Update path to pdf form
                        $oXml = $this->_pdf->updatePDFUrlInXDPFile($oXml, $this->layout()->getVariable('baseUrl'), $formId);


                        // Output updated file in browser
                        return $this->file($oXml->saveXML(), 'result.xdp', 'application/vnd.adobe.xdp+xml', false, false);
                    } else {
                        $strError = 'Insufficient access rights';
                    }
                } else {
                    $strError = 'Incorrect pdf form id';
                }
            }
        } catch (Exception $e) {
            $strError = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('content', empty($strError) ? 'Information was saved successfully!' : $strError);

        return $view;
    }

    public function saveDataFromHtmlAction()
    {
        $booSuccess = false;
        $strMessage = 'Not correct fields values.';
        $arrData    = array();

        $errorCode = null;
        try {
            $formId    = (int)$this->findParam('assignedId');
            $bcpnpForm = (bool)$this->findParam('bcpnp_form', 0);
            if (!$bcpnpForm) {
                $arrFormFields = Json::decode($this->findParam('arrFormFields'), Json::TYPE_ARRAY);
            } else {
                $arrFormFields = $this->findParam('arrFormFields');
            }

            $data = $this->_pdf->convertFormDataToXfdf($arrFormFields, $formId, $bcpnpForm);
            if ($data) {
                $incomingXfdf = $this->_pdf->getIncomingXfdf($data);
                if ($incomingXfdf) {
                    $result = $this->xfdfPreprocessor($incomingXfdf);
                    if (!is_array($result)) {
                        $errorCode = $result;
                    } else {
                        list($pdfId, $currentMemberId, $updateMemberId, $updateMemberCompanyId, $assignedFormInfo) = $result;
                        $additionalData = $this->xfdfProcessor()->process($updateMemberId, $updateMemberCompanyId, $assignedFormInfo);
                        if (!is_array($additionalData)) {
                            $errorCode = $additionalData;
                        } else {
                            list($mainParentId, $arrParentsData, $fieldsMap) = $additionalData;
                            $booAnnotationsEnabled = $this->_company->areAnnotationsEnabledForCompany($updateMemberCompanyId);

                            $result = $this->_pdf->pdfToXfdf($pdfId, $incomingXfdf, $assignedFormInfo, $updateMemberCompanyId, $updateMemberId, $currentMemberId, $mainParentId, $arrParentsData, $fieldsMap, $booAnnotationsEnabled);
                            if ($result->code == Pdf::XFDF_SAVED_CORRECTLY) {
                                $result = $this->_xfdfDbSync->syncXfdfResultToDb($result);
                            }
                        }
                    }

                    $resultCode = !is_null($errorCode) ? $errorCode : $result->code;
                    $strMessage = $this->_pdf->getCodeResultById($resultCode);

                    if (is_null($errorCode) && $result && $result->code == Pdf::XFDF_SAVED_CORRECTLY) {
                        $booSuccess = true;
                    }

                    $xfdf = $this->_pdf->getEmptyXfdf();
                    $oXml = simplexml_load_string($xfdf);

                    // Generate return values
                    $this->_pdf->updateFieldInXfdf('server_result', $strMessage, $oXml);
                    $this->_pdf->updateFieldInXfdf('server_result_code', $resultCode, $oXml);
                    $this->_pdf->updateFieldInXfdf('server_xfdf_loaded', 1, $oXml);

                    if ($result && !empty($result->updatedOn)) {
                        $this->_pdf->updateFieldInXfdf('server_time_stamp', $result->updatedOn, $oXml);
                    }

                    $strParsedResult = preg_replace('%<value>(\r|\n|\r\n)</value>%', '<value></value>', $oXml->asXML());
                    $xmlParser       = xml_parser_create();
                    xml_parse_into_struct($xmlParser, $strParsedResult, $arrValues);
                    xml_parser_free($xmlParser);

                    for ($i = 0; $i < count($arrValues); $i++) {
                        if ($arrValues[$i]['tag'] == 'FIELD' && $arrValues[$i]['type'] == 'open') {
                            if (isset($arrValues[$i + 1]['value'])) {
                                $arrData[] = array(
                                    'field_id'  => $arrValues[$i]['attributes']['NAME'],
                                    'field_val' => $arrValues[$i + 1]['value']
                                );
                            } else {
                                $arrData[] = array(
                                    'field_id'  => $arrValues[$i]['attributes']['NAME'],
                                    'field_val' => ''
                                );
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strMessage = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => $booSuccess,
            'message' => $strMessage,
            'data'    => $arrData
        );

        return new JsonModel($arrResult);
    }

    public function getDataForHtmlAction()
    {
        $strMessage = '';
        $arrData    = array();

        try {
            $bcpnpForm      = (bool)$this->params()->fromQuery('bcpnp_form', false);
            $assignedFormId = (int)$this->params()->fromQuery('assignedId', 0);
            if (empty($assignedFormId)) {
                $assignedFormId = (int)$this->params()->fromPost('assignedId', 0);
            }


            // Get assigned form info by id
            $assignedFormInfo = $this->_forms->getFormAssigned()->getAssignedFormInfo($assignedFormId);
            if (!$assignedFormInfo) {
                $strMessage = $this->_tr->translate('There is no form with this assigned id.');
            }

            $memberId = 0;
            if (empty($strMessage)) {
                $memberId = $assignedFormInfo['client_member_id'];
                if (!$this->_clients->isAlowedClient($memberId)) {
                    $strMessage = $this->_tr->translate('Insufficient access rights.');
                }
            }

            if (empty($strMessage)) {
                $titleOnly = (bool)$this->params()->fromQuery('title', false);
                if ($titleOnly) {
                    // File number needs to be included into tab header, so this is a request just for the tab/browser title
                    $arrClientInfo = $this->_clients->getClientInfo($memberId);

                    $strResult = $arrClientInfo['fileNumber'] . ' :: ' . $this->layout()->getVariable('siteTitle');

                    $viewModel = new ViewModel(['content' => $strResult]);
                    $viewModel->setTerminal(true);
                    $viewModel->setTemplate('layout/plain');
                    return $viewModel;
                }

                $arrMemberInfo = $this->_members->getMemberInfo($memberId);
                // Check if annotations are enabled for the company
                $booAnnotationsEnabled = $this->_company->areAnnotationsEnabledForCompany($arrMemberInfo['company_id']);
                // Check if we need load data from json or xfdf file
                $jsonFilePath = $this->_files->getClientJsonFilePath($memberId, $assignedFormInfo['family_member_id'], $assignedFormId);
                if (file_exists($jsonFilePath)) {
                    $formInfo = $this->_forms->getFormVersion()->getFormVersionInfo($assignedFormInfo['form_version_id']);
                    $arrData  = $this->_pdf->loadDataFromJson($memberId, $assignedFormInfo['family_member_id'], $arrMemberInfo['regTime'], $assignedFormId, $formInfo['file_name'], $bcpnpForm);
                } else {
                    $arrData = $this->_pdf->loadDataFromXfdf(
                        $assignedFormId,
                        $memberId,
                        $assignedFormInfo['family_member_id'],
                        $booAnnotationsEnabled,
                        $bcpnpForm
                    );
                }
            }
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'result'  => empty($strMessage) ? 'success' : 'error',
            'success' => empty($strMessage),
            'message' => $strMessage,
            'data'    => $arrData
        );

        return new JsonModel($arrResult);
    }

    public function printAction()
    {
        $strError = 'Not correct fields values.';
        $filename = '';

        try {
            $formId        = (int)$this->params()->fromPost('assignedId');
            $arrFormFields = Json::decode($this->params()->fromPost('arrParams'), Json::TYPE_ARRAY);

            $data = $this->_pdf->convertFormDataToXfdf($arrFormFields, $formId);
            if ($data) {
                $incomingXfdf = $this->_pdf->getIncomingXfdf($data);
                if ($incomingXfdf) {
                    $result = $this->xfdfPreprocessor($incomingXfdf, true);
                    if (!is_array($result)) {
                        $strError = $this->_pdf->getCodeResultById($result);
                    } else {
                        list(, , $updateMemberId, , $assignedFormInfo) = $result;
                        $fileInfo = $this->xfdfProcessor()->printXFDF($incomingXfdf, $updateMemberId, $assignedFormInfo);
                        if (!$fileInfo instanceof FileInfo) {
                            $strError = $this->_pdf->getCodeResultById($fileInfo);
                        } else {
                            $filename = $this->_files::extractFileName($fileInfo->path);
                        }
                    }
                } else {
                    $strError = $this->_pdf->getCodeResultById(Pdf::XFDF_INCORRECT_INCOMING_XFDF);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = [
            'error'    => $strError,
            'filename' => $filename
        ];

        return new JsonModel($arrResult);
    }


    public function saveXodAction()
    {
        $view = new JsonModel();

        $booError = false;
        $updatedOn = '';
        $xfdfLoadedCode = 0;

        try {
            $filter = new StripTags();

            // Get incoming xfdf
            $XFDFData = $this->findParam('xfdf', '');
            $pdfId = $filter->filter($this->findParam('pdfId'));

            $result = false;
            $errorCode = null;

            // Fix: sometimes enter is passed incorrectly - replace it to the correct one
            $XFDFData = str_replace('&#xD;', "\n", $XFDFData);
            $incomingXfdf = $this->_pdf->getIncomingXfdf($XFDFData);
            if ($incomingXfdf) {
                $result = $this->xfdfPreprocessor($incomingXfdf, false, $pdfId);
                if (!is_array($result)) {
                    $errorCode = $result;
                } else {
                    list($pdfId, $currentMemberId, $updateMemberId, $updateMemberCompanyId, $assignedFormInfo) = $result;
                    $additionalData = $this->xfdfProcessor()->process($updateMemberId, $updateMemberCompanyId, $assignedFormInfo);
                    if (!is_array($additionalData)) {
                        $errorCode = $additionalData;
                    } else {
                        list($mainParentId, $arrParentsData, $fieldsMap) = $additionalData;
                        $booAnnotationsEnabled = $this->_company->areAnnotationsEnabledForCompany($updateMemberCompanyId);
                        $result = $this->_pdf->pdfToXfdf($pdfId, $incomingXfdf, $assignedFormInfo, $updateMemberCompanyId, $updateMemberId, $currentMemberId, $mainParentId, $arrParentsData, $fieldsMap, $booAnnotationsEnabled);
                        if ($result->code == Pdf::XFDF_SAVED_CORRECTLY) {
                            $result = $this->_xfdfDbSync->syncXfdfResultToDb($result);
                        }
                    }
                }
            } else {
                $errorCode = Pdf::XFDF_INCORRECT_INCOMING_XFDF;
            }

            $resultCode = !is_null($errorCode) ? $errorCode : $result->code;
            $strMessage = $this->_pdf->getCodeResultById($resultCode);
            $xfdfLoadedCode = ($resultCode == Pdf::XFDF_CLIENT_LOCKED) ? 2 : 1;

            // Check result
            $updatedOn = false;
            if (!in_array($resultCode, array(Pdf::XFDF_SAVED_CORRECTLY, Pdf::XFDF_CLIENT_LOCKED))) {
                $booError = true;
            } else {
                if ($result && !empty($result->updatedOn)) {
                    $updatedOn = $result->updatedOn;
                }
            }
        } catch (Exception $e) {
            // Save to log exception info
            $booError = true;
            $strMessage = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if ($booError) {
            $this->getResponse()->setStatusCode(400);
        }

        // return json result
        $arrResult = [
            'message' => $strMessage,
            'arrFieldsToUpdate' => [
                'server_xfdf_loaded' => $xfdfLoadedCode
            ]
        ];
        if ($updatedOn) {
            $arrResult['arrFieldsToUpdate']['server_time_stamp'] = $updatedOn;
        }

        return $view->setVariables($arrResult);
    }
}