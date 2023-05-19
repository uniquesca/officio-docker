<?php

namespace Api\Controller;

use Clients\Service\Clients;
use Exception;
use Files\Service\Files;
use Laminas\Http\Client;
use Laminas\Http\Request;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\AuthHelper;
use Officio\Service\AutomaticReminders;
use Officio\Service\Company;
use Officio\Service\SystemTriggers;
use Templates\Service\Templates;

/**
 * API GV Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class GvController extends BaseController
{

    /** @var Clients */
    protected $_clients;

    /** @var Company */
    protected $_company;

    /** @var AutomaticReminders */
    protected $_automaticReminders;

    /** @var AuthHelper */
    protected $_authHelper;

    /** @var Files */
    protected $_files;

    /** @var Templates */
    protected $_templates;

    /** @var SystemTriggers */
    protected $_triggers;

    public function initAdditionalServices(array $services)
    {
        $this->_company            = $services[Company::class];
        $this->_clients            = $services[Clients::class];
        $this->_automaticReminders = $services[AutomaticReminders::class];
        $this->_authHelper         = $services[AuthHelper::class];
        $this->_files              = $services[Files::class];
        $this->_templates          = $services[Templates::class];
        $this->_triggers           = $services[SystemTriggers::class];
    }

    public function indexAction()
    {
        // Do nothing...
        $view = new ViewModel(
            ['content' => null]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');
        return $view;
    }

    private function _checkAuthAndLogin()
    {
        $strError = '';
        try {
            // Check custom header
            /** @var \Laminas\Http\PhpEnvironment\Request $request */
            $request = $this->getRequest();
            if ($request->getHeader('X-Officio')->getFieldValue() != '1.0') {
                $strError = $this->_tr->translate('Incorrect incoming info.');
            }

            // Check username, password and login user if this is allowed
            if (empty($strError)) {
                $username = substr(trim($this->findParam('auth_username', '')), 0, 50);
                $password = substr(trim($this->findParam('auth_password', '')), 0, $this->_settings->passwordMaxLength);

                if (empty($username)) {
                    $strError = $this->_tr->translate('Please provide a username');
                } elseif (empty($password)) {
                    $strError = $this->_tr->translate('Please provide a password');
                }

                if (empty($strError)) {
                    $arrLoginResult = $this->_authHelper->login($username, $password, false);
                    if (!$arrLoginResult['success']) {
                        $strError = $arrLoginResult['message'];
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error [Auth].');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

    protected function _calcFileHashes($files)
    {
        $fileHashes = array();
        foreach ($files as $formName => $file) {
            if (is_array($file['error'])) {
                foreach ($file['error'] as $key => $error) {
                    if ($error == UPLOAD_ERR_OK) {
                        $fileContents = file_get_contents($file['tmp_name'][$key] ?? '');
                        $actualFormName = $formName . '[]';
                        if (!isset($fileHashes[$actualFormName])) {
                            $fileHashes[$actualFormName] = array();
                        }
                        $fileHashes[$actualFormName][$file['name'][$key]] = md5($fileContents);
                    }
                }
            } else {
                if ($file['error'] == UPLOAD_ERR_OK) {
                    $fileContents = file_get_contents($file['tmp_name'] ?? '');
                    if (!isset($fileHashes[$formName])) {
                        $fileHashes[$formName] = array();
                    }
                    $fileHashes[$formName][$file['name']] = md5($fileContents);
                }
            }
        }

        return $fileHashes;
    }

    protected function _verifyDataAndFilesIntegrity(&$data, $files)
    {
        $strError = '';

        if (empty($data['__data_hash']) && !empty($data)) {
            $strError = 'Data hash is missing. Cannot verify data integrity.';
        } elseif (empty($data['__files_hashes']) && !empty($files)) {
            $strError = 'Files hashes are missing. Cannot verify files integrity.';
        } else {
            $dataHash = (!empty($data['__data_hash'])) ? $data['__data_hash'] : false;
            $filesHashes = (!empty($data['__files_hashes'])) ? json_decode($data['__files_hashes'], true) : false;

            unset($data['__data_hash']);
            unset($data['__files_hashes']);

            if ($dataHash) {
                ksort($data);
                $receivedDataHash = md5(serialize($data));
                if ($dataHash != $receivedDataHash) {
                    $strError = 'Data hash is wrong. Data has probably being corrupted.';
                    $strError .= serialize($data);
                }
            }

            if (!$strError && $filesHashes) {
                try {
                    $receivedFileHashes = $this->_calcFileHashes($files);
                    $hashesFilesDiff = array_diff_key($filesHashes, $receivedFileHashes);
                    if (!empty($hashesFilesDiff)) {
                        $strError = 'Some files are missing.';
                    } else {
                        foreach ($filesHashes as $formName => $formNameHashes) {
                            $hashesFilesDiff = array_diff_key($filesHashes[$formName], $receivedFileHashes[$formName]);
                            if (!empty($hashesFilesDiff)) {
                                $strError = 'Some files are missing.';
                                break;
                            } else {
                                $hashesDiff = array_diff($filesHashes[$formName], $receivedFileHashes[$formName]);
                                if ($hashesDiff) {
                                    $strError = 'Some files were corrupted during the transmission.';
                                    break;
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    $strError = 'Files hashes could not be decoded.';
                }
            }
        }

        return $strError;
    }

    /**
     * Update IA, Employer or Case from "_other_updates"
     *
     * @param array $arrData
     * @param $key
     * @param int $mainProfileId
     * @param int $mainCaseId
     * @return string
     */
    public function updateOtherClient($arrData, $key, $mainProfileId, $mainCaseId)
    {
        $strError = '';
        try {
            $applicantId = $caseId = 0;
            $booCase = false;

            if (preg_match('/^individual_profile_([\d]+)$/i', $key, $regs)) {
                $applicantId = $regs[1];
            } else {
                if (preg_match('/^employer_profile_([\d]+)$/i', $key, $regs)) {
                    $applicantId = $regs[1];
                } else {
                    if (preg_match('/^case_([\d]+)$/i', $key, $regs)) {
                        $booCase = true;
                        $caseId = $regs[1];
                    } else {
                        $strError = $this->_tr->translate('Incorrect client name: ' . $key);
                    }
                }
            }

            if (empty($strError)) {
                $arrParams = array();

                foreach ($arrData as $fieldName => $fieldValue) {
                    if (is_string($fieldValue) && is_array(json_decode($fieldValue, true)) && json_last_error() == JSON_ERROR_NONE) {
                        $fieldValue = Json::decode($fieldValue, Json::TYPE_ARRAY);
                    }
                    if (!is_array($fieldValue)) {
                        $fieldValues = array($fieldValue);
                    } else {
                        $fieldValues = $fieldValue;
                    }

                    $arrResultValues = array();
                    foreach ($fieldValues as $value) {
                        if (preg_match('/<%main_case_id%>/i', $value, $regs)) {
                            if (empty($mainCaseId)) {
                                return $this->_tr->translate('Undefined main case id.');
                            }
                            $value = str_replace('<%main_case_id%>', (string)$mainCaseId, $value);
                        } elseif (preg_match('/<%main_profile_id%>/i', $value, $regs)) {
                            if (empty($mainProfileId)) {
                                return $this->_tr->translate('Undefined main profile id.');
                            }
                            $value = str_replace('<%main_profile_id%>', (string)$mainProfileId, $value);
                        }
                        $arrResultValues[] = $value;
                    }
                    if (count($arrResultValues) > 1) {
                        $arrParams[$fieldName] = Json::encode($arrResultValues);
                    } elseif (isset($arrResultValues[0])) {
                        $arrParams[$fieldName] = $arrResultValues[0];
                    } else {
                        $arrParams[$fieldName] = $fieldValue;
                    }
                }

                if ($booCase) {
                    $arrParents = $this->_clients->getParentsForAssignedApplicant($caseId);
                    if (empty($arrParents)) {
                        return $this->_tr->translate('Incorrect case id: ' . $key);
                    }
                    $arrParams['Officio_case_id']        = $arrParams['Officio_case_id'] ?? $caseId;
                    $arrParams['Officio_applicant_id']   = $arrParams['Officio_applicant_id'] ?? $arrParents[0];
                    $arrParams['Officio_update_profile'] = $arrParams['Officio_update_profile'] ?? 0;
                    $arrParams['Officio_update_case']    = $arrParams['Officio_update_case'] ?? 1;
                } else {
                    $arrParams['Officio_applicant_id']   = $arrParams['Officio_applicant_id'] ?? $applicantId;
                    $arrParams['Officio_update_profile'] = $arrParams['Officio_update_profile'] ?? 1;
                    $arrParams['Officio_update_case']    = $arrParams['Officio_update_case'] ?? 0;
                }
                $arrResult = $this->_clients->createUpdateApplicantAndCase($arrParams, array(), false, $this->_templates);
                $strError  = $arrResult['message'];
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }


    public function updateClientAction()
    {
        $applicantId = 0;
        $employerId = 0;
        $arrCasesIds = array();
        $arrCases = array();
        $caseReferenceNumber = '';
        $arrOtherUpdates = array();
        $strOtherUpdateError = '';
        $view = new JsonModel();

        try {
            $strError = $this->_checkAuthAndLogin();

            if (empty($strError)) {
                $arrParams = $this->params()->fromPost();
                $arrReceivedFiles = $_FILES;

                $strError = $this->_verifyDataAndFilesIntegrity($arrParams, $arrReceivedFiles);

                if (empty($strError)) {
                    if (!empty($arrParams['Officio_applicant_id']) && $arrParams['Officio_update_case'] == 2) {
                        $arrParams['Officio_update_case'] = 0;
                        $arrUpdateResult                  = $this->_clients->createUpdateApplicantAndCase($arrParams, $arrReceivedFiles, false, $this->_templates);

                        $strError = $arrUpdateResult['message'];
                        $applicantId = $arrUpdateResult['applicantId'];

                        if (empty($strError) && !empty($applicantId)) {
                            $arrAssignedCasesIds = $this->_clients->getAssignedCases($applicantId);
                            $arrCasesIds = array_unique($arrAssignedCasesIds);

                            $arrParams['Officio_update_profile'] = 0;
                            $arrParams['Officio_update_case'] = 1;

                            foreach ($arrAssignedCasesIds as $assignedCaseId) {
                                $arrParams['Officio_case_id'] = $assignedCaseId;

                                $arrUpdateResult = $this->_clients->createUpdateApplicantAndCase($arrParams, $arrReceivedFiles, false, $this->_templates);

                                if (!empty($arrUpdateResult['message'])) {
                                    $arrCases[$assignedCaseId] = $arrUpdateResult['message'];
                                    $strError = $this->_tr->translate('Error on all applicant\'s cases update.');
                                } else {
                                    $arrCases[$assignedCaseId] = true;
                                }
                            }
                        }
                    } else {
                        if ($arrParams['Officio_update_case'] == 3) {
                            $arrAssignedCasesIds = Json::decode($arrParams['Officio_case_id'], Json::TYPE_ARRAY);

                            $arrParams['Officio_update_profile'] = 0;
                            $arrParams['Officio_update_case'] = 1;

                            foreach ($arrAssignedCasesIds as $assignedCaseId) {
                                $arrParams['Officio_case_id'] = $assignedCaseId;

                                $arrUpdateResult = $this->_clients->createUpdateApplicantAndCase($arrParams, $arrReceivedFiles, false, $this->_templates);

                                if (!empty($arrUpdateResult['message'])) {
                                    $arrCases[$assignedCaseId] = $arrUpdateResult['message'];
                                    $strError = $this->_tr->translate('Error on cases update.');
                                } else {
                                    $arrCases[$assignedCaseId] = true;
                                }
                            }
                        } else {
                            $arrUpdateResult = $this->_clients->createUpdateApplicantAndCase($arrParams, $arrReceivedFiles, false, $this->_templates);

                            $strError = $arrUpdateResult['message'];
                            $applicantId = $arrUpdateResult['applicantId'];
                            $employerId = $arrUpdateResult['employerId'];
                            $arrCasesIds[] = $arrUpdateResult['caseId'];
                            $caseReferenceNumber = $arrUpdateResult['caseReferenceNumber'];

                            if (isset($arrParams['_other_updates'])) {
                                $arrOtherUpdates = Json::decode($arrParams['_other_updates'], Json::TYPE_ARRAY);

                                foreach ($arrOtherUpdates as $key => $arrOtherUpdate) {
                                    $strOtherUpdateError = $this->updateOtherClient($arrOtherUpdate, $key, $applicantId, $arrUpdateResult['caseId']);

                                    if (!empty($strOtherUpdateError)) {
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return Json result
        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
            'applicantId' => $applicantId,
            'employerId' => $employerId,
            'caseIds' => $arrCasesIds,
            'cases' => $arrCases,
            'caseReferenceNumber' => $caseReferenceNumber,
            '_other_updates' => $arrOtherUpdates,
            'otherUpdateMessage' => $strOtherUpdateError
        );

        return $view->setVariables($arrResult);
    }


    public function getCaseInfoAction()
    {
        $caseStatusName = '';
        $fileNumber = '';
        $resultData = array();

        try {
            $strError = $this->_checkAuthAndLogin();

            if (empty($strError)) {
                $arrRequestData = $this->findParams();
                $strError = $this->_verifyDataAndFilesIntegrity($arrRequestData, $_FILES);
            }

            // Get incoming params, check them
            $caseId = $this->findParam('caseId');
            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($caseId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                // Extract case status for this case
                $companyId = $this->_auth->getCurrentUserCompanyId();
                $fieldId   = $this->_clients->getFields()->getCompanyFieldIdByUniqueFieldId('file_status', $companyId);
                if (!empty($fieldId)) {
                    $caseStatusSavedId = $this->_clients->getFields()->getFieldDataValue($fieldId, $caseId);
                    if (!empty($caseStatusSavedId)) {
                        // Use the first only, can be changed later if needed
                        $arrExploded       = explode(',', $caseStatusSavedId);
                        $caseStatusSavedId = $arrExploded[0];

                        $arrFieldOptions = $this->_clients->getCaseStatuses()->getCompanyCaseStatuses($companyId);
                        foreach ($arrFieldOptions as $arrFieldOptionInfo) {
                            if ($arrFieldOptionInfo['client_status_id'] == $caseStatusSavedId) {
                                $caseStatusName = $arrFieldOptionInfo['client_status_name'];
                                break;
                            }
                        }
                    }
                }

                $info = $this->_clients->getClientShortInfo($caseId);
                $fileNumber = $info['fileNumber'];

                if ($this->getRequest()->isPost()) {
                    $data = $this->findParams();
                    if (!empty($data['requestData'])) {
                        foreach ($data['requestData'] as $fieldName) {
                            if (!empty($info[$fieldName])) {
                                $resultData[$fieldName] = $info[$fieldName];
                            } else {
                                $fieldId = $this->_clients->getFields()->getCompanyFieldIdByUniqueFieldId($fieldName, $companyId);
                                $resultData[$fieldName] = (!empty($fieldId))
                                    ? $this->_clients->getFields()->getFieldDataValue($fieldId, $caseId)
                                    : false;
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return Json result
        $arrResult = [
            'success'    => empty($strError),
            'message'    => $strError,
            'caseStatus' => $caseStatusName,
            'fileNumber' => $fileNumber,
            'data'       => $resultData
        ];

        return new JsonModel($arrResult);
    }

    public function withdrawCaseAction()
    {
        $view = new JsonModel();

        try {
            $strError = $this->_checkAuthAndLogin();

            // Get incoming params, check them
            $caseId = (int)$this->findParam('caseId');

            // Note: make sure that option label will be not changed by company admin
            $optionLabel = 'Withdrawn';

            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($caseId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $companyId = $this->_company->getMemberCompanyId($caseId);

                // Search for the field and option we need set to
                $arrFileStatusInfo = $this->_clients->getFields()->getCompanyFieldInfoByUniqueFieldId('file_status', $companyId);
                $fileStatusFieldId = $arrFileStatusInfo['field_id'] ?? 0;

                $fileStatusNewValue = 0;
                if (!empty($fileStatusFieldId)) {
                    $arrFileStatusOptions = $this->_clients->getCaseStatuses()->getCompanyCaseStatuses($companyId);

                    foreach ($arrFileStatusOptions as $arrFileStatusOptionInfo) {
                        if ($arrFileStatusOptionInfo['client_status_name'] == $optionLabel) {
                            $fileStatusNewValue = $arrFileStatusOptionInfo['client_status_id'];
                            break;
                        }
                    }
                } else {
                    $strError = $this->_tr->translate('Field not found.');
                }

                // Change status to the new value
                if (empty($strError) && !empty($fileStatusNewValue)) {
                    $fileStatusOldValue = $this->_clients->getFields()->getFieldDataValue($fileStatusFieldId, $caseId);
                    $arrClientAndCaseInfo = array(
                        'member_id' => $caseId,
                        'case' => array(
                            'members' => array(),
                            'clients' => array(),
                            'client_form_data' => array(
                                $fileStatusFieldId => $fileStatusNewValue
                            )
                        )
                    );

                    $arrCaseFields = $this->_clients->getFields()->getCompanyFields($companyId);
                    $this->_clients->updateClient($arrClientAndCaseInfo, $companyId, $arrCaseFields);

                    // Trigger: Case Status changed
                    if ($fileStatusOldValue != $fileStatusNewValue) {
                        $this->_triggers->triggerFileStatusChanged(
                            $caseId,
                            $this->_clients->getCaseStatuses()->getCaseStatusesNames($fileStatusOldValue),
                            $this->_clients->getCaseStatuses()->getCaseStatusesNames($fileStatusNewValue)
                        );

                        // Prepare info to save in log
                        $arrInfoToLog[$caseId] = array(
                            'booIsApplicant' => false,

                            'arrOldData' => array(
                                array(
                                    'field_id' => $fileStatusFieldId,
                                    'row' => 0,
                                    'value' => $fileStatusOldValue
                                )
                            ),

                            'arrNewData' => array(
                                array(
                                    'field_id' => $fileStatusFieldId,
                                    'row' => 0,
                                    'value' => $fileStatusNewValue
                                )
                            ),
                        );

                        // Log the changes
                        $this->_triggers->triggerFieldBulkChanges($companyId, $arrInfoToLog);
                    }
                } else {
                    $strError = sprintf($this->_tr->translate('%s option not found.'), $optionLabel);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return Json result
        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
        );

        return $view->setVariables($arrResult);
    }

    public function getCaseIntegrityInformationAction()
    {
        $view = new JsonModel();
        $data = array();

        try {
            $strError = $this->_checkAuthAndLogin();


            // Get incoming params, check them
            $caseId = (int)$this->findParam('caseId');
            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($caseId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $memberInfo = $this->_clients->getMemberInfo($caseId);
            $companyId = $memberInfo['company_id'];
            $booLocal = $this->_company->isCompanyStorageLocationLocal($companyId);

            if (empty($strError)) {
                $jsonFilePath = $this->_files->getClientJsonFilePath($caseId, 'main_clients', null);
                if (file_exists($jsonFilePath)) {
                    $json = file_get_contents($jsonFilePath);
                    $values = Json::decode($json, Json::TYPE_ARRAY);
                    ksort($values);
                    $data['json'] = Json::encode($values);
                }

                $data['files'] = array();
                $path = $this->_files->getClientSubmissionsFolder($companyId, $caseId, $booLocal);
                $dir = scandir($path);
                foreach ($dir as $fileName) {
                    if (in_array($fileName, array('.', '..'))) {
                        continue;
                    }
                    $file = file_get_contents($path . '/' . $fileName);
                    $data['files'][] = md5($file);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $data = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return Json result
        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
            'data' => $data,
        );

        return $view->setVariables($arrResult);
    }

    public function assignFormAction()
    {
        $arrFilesSubmitted = array();
        $arrDataSubmitted = array();
        $strOtherUpdateError = '';
        $arrOtherUpdates = array();
        $view = new JsonModel();

        try {
            $strError = $this->_checkAuthAndLogin();

            $arrReceivedFiles = $arrParams = array();

            if (empty($strError)) {
                $arrParams = $this->findParams();
                $arrReceivedFiles = $_FILES;

                $strError = $this->_verifyDataAndFilesIntegrity($arrParams, $arrReceivedFiles);
            }

            if (empty($strError) && (empty($arrParams['Officio_form_name']))) {
                $strError = $this->_tr->translate('Please provide form name.');
            }

            if (empty($strError)) {
                $arrUpdateResult = $this->_clients->createUpdateApplicantAndCase($arrParams, $arrReceivedFiles, true, $this->_templates);

                $strError = $arrUpdateResult['message'];
                $arrFilesSubmitted = $arrUpdateResult['filesSubmitted'];
                $arrDataSubmitted = $arrUpdateResult['dataSubmitted'];

                if (isset($arrParams['_other_updates'])) {
                    $arrOtherUpdates = Json::decode($arrParams['_other_updates'], Json::TYPE_ARRAY);

                    foreach ($arrOtherUpdates as $key => $arrOtherUpdate) {
                        $strOtherUpdateError = $this->updateOtherClient($arrOtherUpdate, $key, $arrUpdateResult['applicantId'], $arrUpdateResult['caseId']);

                        if (!empty($strOtherUpdateError)) {
                            break;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return Json result
        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
            'filesSubmitted' => $arrFilesSubmitted,
            'dataSubmitted' => $arrDataSubmitted,
            '_other_updates' => $arrOtherUpdates,
            'otherUpdateMessage' => $strOtherUpdateError
        );
        return $view->setVariables($arrResult);
    }

    private function _getHttpClient()
    {
        $url = $this->layout()->getVariable('baseUrl') . '/api/gv/update-client';

        $client = new Client();
        $client->setUri($url);
        $client->setOptions(
            array(
                'maxredirects' => 0,
                'timeout' => 30
            )
        );

        // Custom header, will be checked during auth
        $client->setHeaders(['X-Officio' => '1.0']);

        // TODO: set correct credentials
        $arrParams = array(
            'auth_username' => 'username',
            'auth_password' => 'pass',
        );

        return array($client, $arrParams);
    }

    public function testClientUpdateAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        try {
            /** @var Client $client */
            list($client, $arrParams) = $this->_getHttpClient();

            $arrOtherUpdates = array(
                'case_131436' => array(
                    'Officio_reference' => 'individual_profile_<%main_profile_id%>'
                )
            );

            // Additional specific params
            // TODO: get/prepare correct info
            $arrClientInfo = array(
                // This is a required field
                'Officio_applicant_id' => 0, // 0 if create a new IA, otherwise correct IA id

                // These fields can be missed,
                // must be provided if field is required
                'syncA_family_name' => 'Family Name',
                'syncA_given_name' => 'Given Names',
                'syncA_address_line_1' => 'Address 1',
                'syncA_DOB' => '1980-01-30',
                'syncA_email' => 'test@test.com',

                'Officio_office' => 'Vancouver',
                'Officio_disable_login' => 'Disabled',

                // These are required fields
                'Officio_update_case' => 1, // 0 - to create/update IA only, 1 - create/update IA and Case
                'Officio_case_id' => 0, // 0 if create a new case, otherwise correct case id
                'Officio_case_type' => 'Independent Application', // Required only if create new case, otherwise can be omitted

                // These fields can be missed,
                // must be provided if field is required
                'syncA_visa_subclass' => '151 - Special eligibility Former resident',
                'syncA_current_visa' => 'Current visa',
                'Officio_multi_combo' => Json::encode(array('1111', '2222')),
                'Officio_Multiple_Text_Fields' => Json::encode(array('aaaaa', 'bbbb')),

                'Officio_Client_file_status' => 'Active',
                'Officio_registered_migrant_agent' => 'Abhishek Vora',
                'Officio_sales_and_marketing' => 'All staff',
                'Officio_processing' => 'All staff',
                'Officio_accounting' => 'All staff',
                '_other_updates' => Json::encode($arrOtherUpdates),

                // These fields will be used in XFDF files only
                'BCPNP_ExtUsr_MailAddr' => 'BCPNP Mail Address'
            );

            $arrParams = array_merge($arrParams, $arrClientInfo);

            $client->setParameterPost($arrParams);

            $fileContent = '{}';
            $client->setFileUpload('test.json', 'OfficioJson', $fileContent, 'text/plain');

            // @Note: must be passed as array, even if one file will be sent
            $client->setFileUpload('test.json', 'OfficioCaseFiles[]', 'File 1', 'text/plain');
            $client->setFileUpload('test.txt', 'OfficioCaseFiles[]', 'File 22', 'text/plain');

            // Preforming a POST request
            $client->setMethod(Request::METHOD_POST);
            $response = $client->send();

            $strResult = 'URL: ' . $client->getUri() . PHP_EOL;
            $strResult .= print_r($response, true);
        } catch (Exception $e) {
            $strResult = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('content', '<pre>' . $strResult . '</pre>');

        return $view;
    }


    public function testCaseInfoAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        try {
            /** @var Client $client */
            list($client, $arrParams) = $this->_getHttpClient();

            // Additional specific params
            $arrParams['caseId'] = 110043;

            $client->setParameterPost($arrParams);

            // Preforming a POST request
            $client->setMethod('POST');
            $response = $client->send();

            $strResult = 'URL: ' . $client->getUri() . PHP_EOL;
            $strResult .= print_r($response, true);
        } catch (Exception $e) {
            $strResult = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('content', '<pre>' . $strResult . '</pre>');
        $view->setTerminal(true);

        return $view;
    }

    public function testAssignFormAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        try {
            /** @var Client $client */
            list($client, $arrParams) = $this->_getHttpClient();

            $arrClientInfo = array(
                // These are required fields
                'Officio_update_profile' => 0,
                'Officio_update_case' => 1,

                'Officio_applicant_id' => 120749,
                'Officio_case_id' => 131108,

                'Officio_form_name' => 'Standard Business Sponsorship Information',
                'Officio_form_version_date' => '2015-01-17 15:58:26',
                'Officio_Client_file_status' => 'Active',
                'BCPNP_Reg_PrevPNPFileNum' => 'utjjujjujuj',
                'BCPNP_Reg_ResAddrDiff' => 'No',
                'BCPNP_Reg_ResAddrLine' => 'RALLY'

            );

            $arrParams = array_merge($arrParams, $arrClientInfo);

            $client->setParameterPost($arrParams);
            // @Note: must be passed as array, even if one file will be sent
            $client->setFileUpload('test.json', 'OfficioCaseFiles[]', 'File 1', 'text/plain');
            $client->setFileUpload('test.txt', 'OfficioCaseFiles[]', 'File 22', 'text/plain');

            // Preforming a POST request
            $client->setMethod('POST');
            $response = $client->send();

            $strResult = 'URL: ' . $client->getUri() . PHP_EOL;
            $strResult .= print_r($response, true);
        } catch (Exception $e) {
            $strResult = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('content', '<pre>' . $strResult . '</pre>');

        return $view;
    }

}
