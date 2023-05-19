<?php

namespace Forms\Controller;

use Clients\Service\Clients;
use Exception;
use Files\Service\Files;
use Forms\Service\Forms;
use Forms\Service\Pdf;
use Forms\Service\XfdfDbSync;
use Forms\XfdfParseResult;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Officio\BaseController;
use Officio\Service\AuthHelper;
use Officio\Service\Company;

/**
 * Forms Sync Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class AngularFormsController extends BaseController
{
    /** @var Files */
    private $_files;

    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_clients;

    /** @var XfdfDbSync */
    protected $_xfdfDbSync;

    /** @var Pdf */
    protected $_pdf;

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

    protected function renderXfdfError($errorCode, $loaded = 1)
    {
        $result = [];
        $result['server_xfdf_loaded'] = $loaded;
        $result['server_result'] = $this->_pdf->getCodeResultById($errorCode);
        $result['server_result_code'] = $errorCode;
        return new JsonModel(
            [
                'result' => 'error',
                'data' => $result
            ]
        );
    }

    protected function renderXfdfResult(XfdfParseResult $xfdfResult)
    {
        $booSuccess = false;
        $result = [];
        $result['server_xfdf_loaded'] = 1;
        $result['server_result'] = $this->_pdf->getCodeResultById($xfdfResult->code);
        $result['server_result_code'] = $xfdfResult->code;
        if ($result) {
            $result['server_time_stamp'] = $xfdfResult->updatedOn;
            if ($xfdfResult->code === Pdf::XFDF_SAVED_CORRECTLY) {
                $booSuccess = true;
            }
        }
        return new JsonModel(
            [
                'result' => $booSuccess ? 'success' : 'error',
                'data' => $result
            ]
        );
    }

    public function saveAction()
    {
        try {
            $formId = (int)$this->findParam('assignedId');

            $arrFormFields = file_get_contents('php://input');
            $arrFormFields = Json::decode($arrFormFields, Json::TYPE_ARRAY);

            $data = $this->_pdf->convertAngularFormDataToXfdf($arrFormFields, $formId);
            if ($data) {
                $incomingXfdf = $this->_pdf->getIncomingXfdf($data);
                if ($incomingXfdf) {
                    $result = $this->xfdfPreprocessor($incomingXfdf);
                    if (!is_array($result)) {
                        return $this->renderXfdfError($result);
                    }

                    list($pdfId, $currentMemberId, $updateMemberId, $updateMemberCompanyId, $assignedFormInfo) = $result;

                    $additionalData = $this->xfdfProcessor()->process($updateMemberId, $updateMemberCompanyId, $assignedFormInfo);
                    if (!is_array($additionalData)) {
                        return $this->renderXfdfError($additionalData);
                    }
                    list($mainParentId, $arrParentsData, $fieldsMap) = $additionalData;

                    $booAnnotationsEnabled = $this->_company->areAnnotationsEnabledForCompany($updateMemberCompanyId);
                    $result = $this->_pdf->pdfToXfdf($pdfId, $incomingXfdf, $assignedFormInfo, $updateMemberCompanyId, $updateMemberId, $currentMemberId, $mainParentId, $arrParentsData, $fieldsMap, $booAnnotationsEnabled);
                    if ($result->code == Pdf::XFDF_SAVED_CORRECTLY) {
                        $result = $this->_xfdfDbSync->syncXfdfResultToDb($result);
                    }

                    return $this->renderXfdfResult($result);
                } else {
                    return $this->renderXfdfError(Pdf::XFDF_INCORRECT_INCOMING_XFDF);
                }
            } else {
                return $this->renderXfdfError(Pdf::XFDF_INCORRECT_INCOMING_XFDF);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            return $this->renderXfdfError(Pdf::XFDF_INCORRECT_INCOMING_XFDF);
        }
    }

    public function loadAction()
    {
        $view = new JsonModel();

        try {
            // IMPORTANT (OR ELSE INFINITE LOOP) - close current sessions or the next page will wait FOREVER for a write lock.
            session_write_close();

            $strMessage = '';
            $arrData = array();
            $formId = (int)$this->findParam('assignedId');

            // Get assigned form info by id
            $assignedFormInfo = $this->_forms->getFormAssigned()->getAssignedFormInfo($formId);
            if (!$assignedFormInfo) {
                $strMessage = 'There is no form with this assigned id.';
            }

            if (empty($strMessage)) {
                $memberId = $assignedFormInfo['client_member_id'];
                if (!$this->_clients->isAlowedClient($memberId)) {
                    $strMessage = 'Insufficient access rights.';
                }
            }

            if (empty($strMessage)) {
                // Return xfdf for specific member id
                $memberId = $assignedFormInfo['client_member_id'];
                $familyMemberId = $assignedFormInfo['family_member_id'];

                // Check if we need load data from json or xfdf file
                $jsonFilePath = $this->_files->getClientJsonFilePath($memberId, $familyMemberId, $formId);
                if (file_exists($jsonFilePath)) {
                    $savedJson = file_get_contents($jsonFilePath);
                    $arrData = json_decode($savedJson);
                } else {
                    $fileName      = $this->_pdf::getXfdfFileName($familyMemberId, $formId);
                    $pathToXfdfDir = $this->_files->getClientXFDFFTPFolder($memberId);
                    $realPath      = $pathToXfdfDir . '/' . $fileName;

                    $oXml = null;

                    if (file_exists($realPath)) {
                        $pathToAnnotation = $this->_pdf->getAnnotationPath($pathToXfdfDir, $formId);

                        // Check if annotations are enabled for the company
                        $arrMemberInfo = $this->_members->getMemberInfo($memberId);
                        $booAnnotationsEnabled = $this->_company->areAnnotationsEnabledForCompany($arrMemberInfo['company_id']);

                        $xml = $this->_pdf->readXfdfFromFile($realPath, $pathToAnnotation, $booAnnotationsEnabled);
                        if ($xml !== false) {
                            $oXml = $xml;
                        }
                    }

                    if (is_null($oXml)) {
                        $emptyXfdf = $this->_pdf->getEmptyXfdf();
                        $oXml = simplexml_load_string($emptyXfdf);
                    }

                    // Check if this client is locked
                    $booClient = $this->_auth->isCurrentUserClient();
                    $booLocked = $this->_clients->isLockedClient($memberId);
                    if ($booClient && $booLocked) {
                        $xfdfLoadedCode = 2;
                    } else {
                        $xfdfLoadedCode = 1;
                    }

                    $this->_pdf->updateFieldInXfdf('server_form_version', '', $oXml);
                    $this->_pdf->updateFieldInXfdf('server_url', $this->layout()->getVariable('baseUrl') . '/forms/sync#FDF', $oXml);
                    $this->_pdf->updateFieldInXfdf('server_assigned_id', $formId, $oXml);
                    $this->_pdf->updateFieldInXfdf('server_xfdf_loaded', $xfdfLoadedCode, $oXml);
                    $this->_pdf->updateFieldInXfdf('server_locked_form', ($booClient && $booLocked) ? 1 : 0, $oXml);
                    $this->_pdf->updateFieldInXfdf('server_time_stamp', $assignedFormInfo['last_update_date'], $oXml);

                    $strParsedResult = preg_replace('%<value>(\r|\n|\r\n)</value>%', '<value></value>', $oXml->asXML());
                    $xmlParser = xml_parser_create();
                    xml_parse_into_struct($xmlParser, $strParsedResult, $arrValues);
                    xml_parser_free($xmlParser);

                    $booConvertData = false;
                    for ($i = 0; $i < count($arrValues); $i++) {
                        if ($arrValues[$i]['tag'] == 'FIELD' && $arrValues[$i]['type'] == 'open') {
                            $value = $arrValues[$i + 1]['value'] ?? '';
                            if (preg_match("/fieldset_(.*)_field_(.*)_(\d+)/", $arrValues[$i]['attributes']['NAME'], $arrFieldsetNamesMatches)) {
                                $booConvertData = true;
                                $arrData[$arrFieldsetNamesMatches[0]] = '';
                                $arrData[$arrFieldsetNamesMatches[1]][$arrFieldsetNamesMatches[3]][$arrFieldsetNamesMatches[2]] = $value;
                            } elseif (preg_match("/fieldset_(.*)/", $arrValues[$i]['attributes']['NAME'], $arrFieldsetNamesMatches)) {
                                $booConvertData = true;
                                if (!array_key_exists($arrFieldsetNamesMatches[1], $arrData)) {
                                    $arrData[$arrFieldsetNamesMatches[0]] = '';
                                    $arrData[$arrFieldsetNamesMatches[1]] = array();
                                }
                            } else {
                                $arrData[$arrValues[$i]['attributes']['NAME']] = $value;
                            }
                        }
                    }
                    if ($booConvertData) {
                        $arrData = $this->convertNormalDataToNewFormat($arrData);
                    }
                }
            }
        } catch (Exception $e) {
            $strMessage = 'Internal error';
            $arrData = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'result' => empty($strMessage) ? 'success' : 'error',
            'success' => empty($strMessage),
            'message' => $strMessage,
            'data' => $arrData
        );

        return $view->setVariables($arrResult);
    }

    public function flattenArray($data, $flattenedData, $parents)
    {
        foreach ($data as $fieldName => $value) {
            if (is_array($value)) {
                $parentsToPass = $parents;
                $parentsToPass[] = $fieldName;
                $flattenedData = $this->flattenArray($value, $flattenedData, $parentsToPass);
            } else {
                if (!isset($flattenedData[$fieldName])) {
                    $flattenedData[$fieldName] = array();
                }

                $flattenedData[$fieldName][] = array(
                    'value' => $value,
                    'parents' => $parents,
                );
            }
        }

        return $flattenedData;
    }

    public function convertNormalDataToNewFormat($data)
    {
        $flattenedData = array();
        foreach ($data as $fieldName => $value) {
            if (is_array($value)) {
                $flattenedData = $this->flattenArray($value, $flattenedData, array($fieldName));
                unset($data[$fieldName]);
            }
        }

        $dataToMerge = $addresses = array();
        foreach ($flattenedData as $fieldName => $fields) {
            foreach ($fields as $delta => $field) {
                $dataToMerge[$fieldName . '_' . $delta] = $field['value'];
                $addresses[$fieldName . '_' . $delta] = $field['parents'];
            }
        }

        $data = array_merge($data, $dataToMerge);
        $data['__addresses'] = base64_encode(json_encode($addresses));

        return $data;
    }
}