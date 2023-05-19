<?php

namespace Api\Controller;

use Exception;
use Laminas\Filter\StripTags;
use Laminas\Http\Client;
use Laminas\Http\PhpEnvironment\Request;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\AuthHelper;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Common\Service\Encryption;
use Officio\Common\Service\Settings;
use Prospects\Service\CompanyProspects;
use Laminas\Validator\EmailAddress;

/**
 * API marketplace Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class MarketplaceController extends BaseController
{

    /** @var Company */
    protected $_company;

    /** @var AuthHelper */
    protected $_authHelper;

    /** @var Country */
    protected $_country;

    /** @var CompanyProspects */
    protected $_companyProspects;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_company          = $services[Company::class];
        $this->_authHelper       = $services[AuthHelper::class];
        $this->_country          = $services[Country::class];
        $this->_companyProspects = $services[CompanyProspects::class];
        $this->_encryption       = $services[Encryption::class];
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

    private function _getIncomingInfo($booLoginRequired = true)
    {
        try {
            $strError = '';

            // Check custom header
            /** @var Request $request */
            $request = $this->getRequest();
            if ($request->getHeader('X-Officio')->getFieldValue() != '1.0') {
                $strError = $this->_tr->translate('Incorrect incoming info.');
            }

            $hash = $this->findParam('hash', '');
            if (empty($strError)) {
                if (empty($hash)) {
                    $strError = $this->_tr->translate('Incorrect incoming info.');
                }
            }

            $arrDecodedParams = !empty($strError) ? array() : Json::decode(
                $this->_encryption->customDecrypt(
                    $hash,
                    $this->_config['marketplace']['key'],
                    $this->_config['marketplace']['private_pem']
                ),
                Json::TYPE_ARRAY
            );

            if (empty($strError) && !isset($arrDecodedParams['expire_on'])) {
                $strError = $this->_tr->translate('Incorrect incoming info.');
            }

            if (empty($strError) && $arrDecodedParams['expire_on'] <= gmdate('c')) {
                $strError = $this->_tr->translate('Expired link.');
            }

            // Login as superadmin if needed
            if (empty($strError) && $booLoginRequired) {
                $arrSuperadminInfo = $this->_members->getActiveSuperadminForAPI();
                $username          = $arrSuperadminInfo['username'] ?? '';
                $password          = $arrSuperadminInfo['password'] ?? '';

                $username = substr(trim($username), 0, 50);
                $password = trim($password);

                $arrLoginResult = $this->_authHelper->login($username, $password, false, true, true);
                if (!$arrLoginResult['success']) {
                    $strError = $arrLoginResult['message'];
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error [Auth].');
            $arrDecodedParams = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($strError, $arrDecodedParams);
    }


    private function _getHttpClient($action)
    {
        $url = $this->layout()->getVariable('baseUrl') . '/api/marketplace/' . $action;

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

        return $client;
    }


    public function updateProfileStatusAction()
    {
        $view = new JsonModel();

        try {
            list($strError, $arrDecodedParams) = $this->_getIncomingInfo(false);

            $companyId       = $arrDecodedParams['company_id'] ?? 0;
            $mpProfileId     = $arrDecodedParams['marketplace_profile_id'] ?? 0;
            $mpProfileKey    = $arrDecodedParams['marketplace_profile_key'] ?? '';
            $mpProfileName   = $arrDecodedParams['marketplace_profile_name'] ?? '';
            $mpProfileStatus = $arrDecodedParams['marketplace_profile_status'] ?? '';

            if (empty($strError)) {
                $arrCompanyDetails = $this->_company->getCompanyAndDetailsInfo($companyId, array(), false);
                if (!is_array($arrCompanyDetails) || !array_key_exists('company_id', $arrCompanyDetails)) {
                    $strError = $this->_tr->translate('Incorrect company id.');
                }

                if (empty($strError) && $arrCompanyDetails['Status'] != $this->_company->getCompanyIntStatusByString('active')) {
                    $strError = $this->_tr->translate('Company is not active.');
                }

                if (empty($strError) && $arrCompanyDetails['marketplace_module_enabled'] != 'Y') {
                    $strError = $this->_tr->translate('There is no access to MP module.');
                }
            }

            if (empty($strError) && !is_numeric($mpProfileId)) {
                $strError = $this->_tr->translate('Incorrect MP profile id.');
            }

            if (empty($strError) && empty($mpProfileName)) {
                $strError = $this->_tr->translate('MP profile name is a required field.');
            }

            // Status can be the same as it is saved in the DB OR can be 'active', 'inactive'
            $oMarketplace = $this->_company->getCompanyMarketplace();
            if (empty($strError)) {
                $booCorrectStatus = true;
                if (!in_array($mpProfileStatus, array('active', 'inactive', 'suspended'))) {
                    $booCorrectStatus = false;
                } elseif ($mpProfileStatus == 'suspended') {
                    // Note: suspended status can be set only by Officio
                    $arrProfileSavedInfo = $oMarketplace->getMarketplaceProfileInfo($companyId, $mpProfileId);
                    if (!isset($arrProfileSavedInfo['marketplace_profile_status']) || $arrProfileSavedInfo['marketplace_profile_status'] != $mpProfileStatus) {
                        $booCorrectStatus = false;
                    }
                }

                if (!$booCorrectStatus) {
                    $strError = $this->_tr->translate('Incorrect status.');
                }
            }

            if (empty($strError) && !$oMarketplace->updateMarketplaceProfileStatus($companyId, $mpProfileId, $mpProfileKey, $mpProfileName, $mpProfileStatus)) {
                $strError = $this->_tr->translate('Internal error.');
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

    public function updateProspectStatusAction()
    {
        $view = new JsonModel();

        try {
            list($strError, $arrDecodedParams) = $this->_getIncomingInfo(false);

            $filter = new StripTags();

            $prospectId               = $arrDecodedParams['prospect_id'] ?? 0;
            $prospectFirstName        = $filter->filter($arrDecodedParams['prospect_first_name'] ?? '');
            $prospectLastName         = $filter->filter($arrDecodedParams['prospect_last_name'] ?? '');
            $prospectEmail            = $filter->filter($arrDecodedParams['prospect_email'] ?? '');
            $prospectStatus           = $arrDecodedParams['prospect_status'] ?? '';
            $mpProspectExpirationDate = $arrDecodedParams['mp_prospect_expiration_date'] ?? '';

            if (empty($strError) && !is_numeric($prospectId)) {
                $strError = $this->_tr->translate('Incorrect prospect id.');
            }

            if (empty($strError)) {
                $arrProspectInfo = $this->_companyProspects->getProspectInfo($prospectId, 'marketplace', false);
                if (!is_array($arrProspectInfo) || !array_key_exists('company_id', $arrProspectInfo)) {
                    $strError = $this->_tr->translate('Incorrect prospect id.');
                }
            }

            if (empty($strError)) {
                $validator = new EmailAddress();
                if (empty($strMessage) && !empty($prospectEmail) && !$validator->isValid($prospectEmail)) {
                    $strError = $this->_tr->translate('Incorrect email address.');
                }
            }

            if (empty($strError) && !in_array($prospectStatus, array('active', 'inactive', 'suspended'))) {
                $strError = $this->_tr->translate('Incorrect status.');
            }

            if (empty($strError)) {
                $strError = $this->_companyProspects->updateProspect(
                    array(
                        'prospect' => array(
                            'fName' => $prospectFirstName,
                            'lName' => $prospectLastName,
                            'email' => $prospectEmail,
                            'status' => $prospectStatus,
                            'mp_prospect_expiration_date' => empty($mpProspectExpirationDate) ? null : $mpProspectExpirationDate
                        )
                    ),
                    $prospectId
                );
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

    public function createProspectAction()
    {
        $view = new JsonModel();

        $prospectId = 0;

        try {
            list($strError, $arrDecodedParams) = $this->_getIncomingInfo();

            if (empty($strError)) {
                $qId                 = $this->_companyProspects->getCompanyQnr()->getDefaultQuestionnaireId();
                $arrAllFields        = $this->_companyProspects->getCompanyQnr()->getQuestionnaireFields($qId, true);
                $arrAllFieldsOptions = $this->_companyProspects->getCompanyQnr()->getQuestionnaireFieldsOptions($qId, false);

                $prospectId               = $arrDecodedParams['prospect_id'] ?? 0;
                $mpProspectId             = $arrDecodedParams['mp_prospect_id'] ?? 0;
                $mpProspectExpirationDate = $arrDecodedParams['mp_prospect_expiration_date'] ?? '';

                if (empty($prospectId) && !empty($mpProspectId)) {
                    // Check if this id was already used.
                    // If so - use already created prospect id
                    $prospectId = (int)$this->_companyProspects->getProspectIdByMPProspectId($mpProspectId);
                }

                $dateFormatFull = $this->_settings->variable_get('dateFormatFull');

                // Convert data in the same format as we pass from the Prospects tab
                $arrProspectDataConverted = array();
                foreach ($arrDecodedParams as $paramName => $paramVal) {
                    if (preg_match('/^qf_(.*)/', $paramName, $mainRegs) || preg_match('/^qf\d+_(.*)/', $paramName, $mainRegs)) {
                        foreach ($arrAllFields as $arrFieldInfo) {
                            if ($arrFieldInfo['q_field_unique_id'] != 'qf_' . $mainRegs[1]) {
                                continue;
                            }

                            switch ($arrFieldInfo['q_field_type']) {
                                case 'country':
                                    $newVal = $this->_country->getCountryIdByName($paramVal);
                                    break;

                                case 'date':
                                    $newVal = $this->_settings->reformatDate($paramVal, Settings::DATE_UNIX, $dateFormatFull);
                                    break;

                                case 'combo':
                                case 'radio':
                                    $newVal = $paramVal;
                                    foreach ($arrAllFieldsOptions as $optionFieldId => $arrFieldOptions) {
                                        if ($optionFieldId == $arrFieldInfo['q_field_id']) {
                                            foreach ($arrFieldOptions as $arrOptionInfo) {
                                                if ($arrOptionInfo['q_field_option_unique_id'] == $paramVal) {
                                                    $newVal = $arrOptionInfo['q_field_option_id'];
                                                    break;
                                                }
                                            }
                                            break;
                                        }
                                    }
                                    break;

                                case 'checkbox':
                                    $arrVal = explode(',', $paramVal);
                                    $arrResultVal = array();
                                    foreach ($arrAllFieldsOptions as $optionFieldId => $arrFieldOptions) {
                                        if ($optionFieldId == $arrFieldInfo['q_field_id']) {
                                            foreach ($arrVal as $thisVal) {
                                                foreach ($arrFieldOptions as $arrOptionInfo) {
                                                    if ($arrOptionInfo['q_field_option_unique_id'] == $thisVal) {
                                                        $arrResultVal[] = $arrOptionInfo['q_field_option_id'];
                                                        break;
                                                    }
                                                }
                                            }
                                            break;
                                        }
                                    }
                                    $newVal = $arrResultVal;
                                    break;

                                default:
                                    $newVal = $paramVal;
                                    break;
                            }

                            if ($arrFieldInfo['q_field_unique_id'] == 'qf_job_spouse_has_experience') {
                                $arrProspectDataConverted['spouse_has_experience'] = $arrDecodedParams['qf_job_spouse_has_experience'];
                            }

                            // Repeatable fields
                            if ($arrFieldInfo['q_field_unique_id'] == 'qf_job_employment_type') {
                                $fieldPrefix = '_employer_field_';
                            } elseif ($arrFieldInfo['q_field_unique_id'] == 'qf_job_spouse_employment_type') {
                                $fieldPrefix = '_spouse_employer_field_';
                            } else {
                                $fieldPrefix = '_field_';
                            }
                            if (preg_match('/^qf(\d+)_(.*)/', $paramName, $regs)) {
                                $arrProspectDataConverted['p_' . $prospectId . $fieldPrefix . $arrFieldInfo['q_field_id'] . '_' . $regs[1]] = $newVal;
                            } else {
                                $arrProspectDataConverted['p_' . $prospectId . $fieldPrefix . $arrFieldInfo['q_field_id']] = $newVal;
                            }
                        }
                    }
                }

                foreach ($_FILES as $paramName => $paramVal) {
                    if (preg_match('/^qf_(.*)/', $paramName, $mainRegs) || preg_match('/^qf\d+_(.*)/', $paramName, $mainRegs)) {
                        foreach ($arrAllFields as $arrFieldInfo) {
                            if ($arrFieldInfo['q_field_unique_id'] != 'qf_' . $mainRegs[1]) {
                                continue;
                            }

                            // Repeatable fields
                            if (preg_match('/^qf(\d+)_(.*)/', $paramName, $regs)) {
                                $_FILES['p_' . $prospectId . '_field_' . $arrFieldInfo['q_field_id'] . '_' . $regs[1]] = $paramVal;
                            } else {
                                $_FILES['p_' . $prospectId . '_field_' . $arrFieldInfo['q_field_id']] = $paramVal;
                            }
                            unset($_FILES[$paramName]);
                        }
                    }
                }

                $arrCheckResult = $this->_companyProspects->getCompanyQnr()->checkIncomingQNRData($arrProspectDataConverted, $qId, $prospectId);
                $arrAllErrors = $arrCheckResult['arrErrors'];
                $arrProspectData = $arrCheckResult['arrInsertData'];

                // So save data in DB!
                if (!count($arrAllErrors) && count($arrProspectData)) {
                    $arrProspectData['prospect']['mp_prospect_expiration_date'] = empty($mpProspectExpirationDate) ? null : $mpProspectExpirationDate;

                    if (empty($prospectId)) {
                        // Reset company id
                        $arrProspectData['prospect']['company_id'] = 0;

                        // Use passed MP prospect id
                        $arrProspectData['prospect']['mp_prospect_id'] = empty($mpProspectId) ? null : (int)$mpProspectId;

                        $arrCreationResult = $this->_companyProspects->createProspect($arrProspectData, true);
                        $prospectId = $arrCreationResult['prospectId'];
                        $strError = $arrCreationResult['strError'];
                        if (!empty($strError)) {
                            $arrAllErrors[] = $strError;
                        }
                    } else {
                        unset($arrProspectData['prospect']['company_id']);
                        $strError = $this->_companyProspects->updateProspect($arrProspectData, $prospectId);
                        if (!empty($strError)) {
                            $arrAllErrors[] = $strError;
                        } else {
                            $arrUpdate = $arrProspectData['job'] ?? array();
                            $this->_companyProspects->saveProspectJob($arrUpdate, $prospectId, 'main', null, (bool)$arrDecodedParams['boo_delete_resume']);

                            $arrUpdate = $arrProspectData['job_spouse'] ?? array();
                            $this->_companyProspects->saveProspectJob($arrUpdate, $prospectId, 'spouse', null, (bool)$arrDecodedParams['boo_delete_spouse_resume']);
                        }
                    }

                    if (empty($strError)) {
                        // Also update recalculated prospect's assessment
                        $this->_companyProspects->saveProspectPoints($arrProspectData['prospect']['assessment'], $prospectId);
                    }
                }

                if (count($arrAllErrors)) {
                    $strError = implode(PHP_EOL, $arrAllErrors);
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
            'prospectId' => $prospectId,
        );
        return $view->setVariables($arrResult);
    }


    public function inviteProspectAction()
    {
        $view = new JsonModel();

        try {
            list($strError, $arrDecodedParams) = $this->_getIncomingInfo();

            // Check the prospect
            $prospectId = $arrDecodedParams['prospect_id'] ?? 0;
            if (empty($strError) && !$this->_companyProspects->allowAccessToProspect($prospectId)) {
                $strError = $this->_tr->translate('Insufficient access rights to prospect.');
            }

            // Check companies
            $arrCompaniesIds = $arrDecodedParams['company_id'] ?? array();
            if (empty($strError) && (!is_array($arrCompaniesIds) || !count($arrCompaniesIds))) {
                $strError = $this->_tr->translate('Please pass at least one company to invite.');
            }

            if (empty($strError)) {
                foreach ($arrCompaniesIds as $companyId) {
                    if (!$this->_members->hasCurrentMemberAccessToCompany($companyId)) {
                        $strError = $this->_tr->translate('Insufficient access rights to company.');
                    }

                    if (empty($strError)) {
                        $arrCompanyDetails = $this->_company->getCompanyDetailsInfo($companyId);
                        if (!is_array($arrCompanyDetails) || !array_key_exists('company_id', $arrCompanyDetails)) {
                            $strError = $this->_tr->translate('Incorrect company id.');
                        }
                    }

                    if (!empty($strError)) {
                        break;
                    }
                }
            }

            // Add new records to DB and clear previously saved invites, if needed
            $booClearPreviousInvites = isset($arrDecodedParams['clear_previous_invites']) ? (bool)$arrDecodedParams['clear_previous_invites'] : false;
            if (empty($strError) && !$this->_companyProspects->inviteProspect($prospectId, $arrCompaniesIds, $booClearPreviousInvites)) {
                $strError = $this->_tr->translate('Internal error.');
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

    public function getProspectsConversionCountAction()
    {
        $view = new JsonModel();
        $arrOfficioIdConversionCount = array();
        $arrOfficioIdActivitiesCount = array();
        try {
            list($strError, $arrDecodedParams) = $this->_getIncomingInfo();

            $officioProspectIds = $arrDecodedParams['prospect_ids'] ?? array();
            if (empty($strError) && (empty($officioProspectIds) || !is_array($officioProspectIds))) {
                $strError = 'Incorrect incoming data';
            }

            if (empty($strError)) {
                $arrOfficioIdConversionCount = $this->_companyProspects->getProspectConvertedCount($officioProspectIds);
                $arrOfficioIdActivitiesCount = $this->_companyProspects->getProspectActivitiesCount($officioProspectIds);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return Json result
        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
            'arrOfficioIdConversionCount' => $arrOfficioIdConversionCount,
            'arrOfficioIdActivitiesCount' => $arrOfficioIdActivitiesCount
        );
        return $view->setVariables($arrResult);
    }

    public function getLanguageFieldsAssessmentsAction()
    {
        $first = '';
        $booShowAlert = false;
        $view = new JsonModel();

        try {
            $qId = $this->_companyProspects->getCompanyQnr()->getDefaultQuestionnaireId();
            $arrQFields = $this->_companyProspects->getCompanyQnr()->getQuestionnaireFields($qId, true);
            $arrQFieldsOptions = $this->_companyProspects->getCompanyQnr()->getQuestionnaireFieldsOptions($qId);
            $prospectId = 0;

            list($strError, $arrDecodedParams) = $this->_getIncomingInfo();

            $arrFields         = json_decode($arrDecodedParams['data'], true);
            $keys              = array_keys($arrFields);
            $arrForAssessments = array();
            foreach ($arrQFields as $oField) {
                if (in_array($oField['q_field_unique_id'], $keys)) {
                    $fieldOptions = $this->_companyProspects->getCompanyQnr()->getQuestionnaireFieldOptions($qId, $oField['q_field_id']);
                    $optionValue = '';

                    if (strpos($oField['q_field_unique_id'], '_ielts_') || strpos($oField['q_field_unique_id'], '_tef_')) {
                        $optionValue = $arrFields[$oField['q_field_unique_id']];
                    } else {
                        foreach ($fieldOptions as $fieldOption) {
                            if ($fieldOption['q_field_option_unique_id'] == $arrFields[$oField['q_field_unique_id']]) {
                                $optionValue = $fieldOption['q_field_option_id'];
                                break;
                            }
                        }
                    }

                    $arrForAssessments[$oField['q_field_id']] = array($optionValue);
                }
            }

            $arrAssessment = $this->_companyProspects->getCompanyQnr()->getCompanyProspectsPoints()->calculatePoints(
                'qnr',
                $arrForAssessments,
                $arrQFields,
                $arrQFieldsOptions,
                $prospectId,
                true
            );

            $first        = $arrAssessment['first_language'] ?? '';
            $booShowAlert = $arrAssessment['boo_show_alert'] ?? '';
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return Json result
        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
            'first_language' => $first,
            'boo_show_alert' => $booShowAlert
        );
        return $view->setVariables($arrResult);
    }

    // *************************************************
    // TEST methods to show API usage and test its work
    // *************************************************
    public function testProspectCreationAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        try {
            $client = $this->_getHttpClient('create-prospect');

            // **** Profile tab **** //
            $arrParams = array();

            // Pass Officio Prospect id, empty if new must be created
            $arrParams['prospect_id'] = 0;
            $arrParams['mp_prospect_id'] = 123; // Will be used only during prospect creation
            $arrParams['mp_prospect_expiration_date'] = date('Y-m-d', strtotime('tomorrow')); // or empty

            $arrParams['qf_first_name'] = 'First name';
            $arrParams['qf_last_name'] = 'Last name ' . date('Y-m-d H:i:s');
            $arrParams['qf_email'] = 'email@email.com'; // Correct email format
            $arrParams['qf_phone'] = '123-456';
            $arrParams['qf_country_of_citizenship'] = 'Bosnia and Herzegowina'; // As country name/label in DB
            $arrParams['qf_age'] = '2000-01-02'; // YYYY-mm-dd format
            // $arrParams['qf_area_of_interest']                                  = 'skilled_independent_visa' . ',' . 'parent_visa'; // As in company_questionnaires_fields_options table, q_field_option_unique_id
            $arrParams['qf_area_of_interest'] = 'immigrate' . ',' . 'study'; // As in company_questionnaires_fields_options table, q_field_option_unique_id
            $arrParams['qf_marital_status'] = 'married'; // As in company_questionnaires_fields_options table, q_field_option_unique_id
            $arrParams['qf_total_number_of_children'] = '5'; // number
            $arrParams['qf_number_of_children_australian_residents'] = '2';
            $arrParams['qf_education_highest_qualification'] = 'bachelor_degree';
            $arrParams['qf_programs_completed_professional_year'] = 'yes';
            $arrParams['qf_programs_name'] = 'program name';
            $arrParams['qf_language_english_done'] = 'yes';
            $arrParams['qf_language_english_ielts_score_speak'] = '9';
            $arrParams['qf_language_french_done'] = 'no';
            $arrParams['qf_language_french_general_score_speak'] = 'lower_intermediate';

            // Spouse fields
            $arrParams['qf_spouse_first_name'] = 'Spouse first name';
            $arrParams['qf_spouse_last_name'] = 'Spouse last name';
            $arrParams['qf_spouse_date_of_birth'] = '1918-01-25';
            $arrParams['qf_spouse_country_of_passport'] = 'Cuba';
            $arrParams['qf_spouse_country_of_current_residence'] = 'Germany';
            $arrParams['qf_education_spouse_highest_qualification'] = 'high_school';
            $arrParams['qf_education_spouse_completion_date'] = '1998-11-02';
            $arrParams['qf_language_spouse_english_done'] = 'yes';
            $arrParams['qf_language_spouse_english_ielts_score_write'] = '8.5';
            // **** Profile tab **** //


            // **** Occupation tab **** //
            $arrParams['qf_job_employer'] = 'Employer name';
            $arrParams['qf_job_title'] = 'Super job';
            $arrParams['qf_job_text_title'] = 'Job text title';
            $arrParams['qf_job_country_of_employment'] = 'Canada';
            $arrParams['qf_job_start_date'] = date('Y-m-d');
            $arrParams['qf_job_end_date'] = date('Y-m-d', strtotime('tomorrow'));
            // @NOTE: New row with qf\d
            $arrParams['qf1_job_employer'] = 'Employer 2 name';
            $arrParams['qf1_job_title'] = 'Super 2 job';
            $arrParams['qf1_job_text_title'] = 'Job text title 2';
            $arrParams['qf1_job_start_date'] = date('Y-m-d', strtotime('yesterday'));
            $arrParams['qf1_job_end_date'] = date('Y-m-d', strtotime('yesterday + 2 month'));
            $client->setFileUpload('resume.txt', 'qf_job_resume', 'resume file content');

            // Spouse fields
            $arrParams['qf_job_spouse_employer'] = 'Spouse Employer name';
            $arrParams['qf1_job_spouse_employer'] = 'Spouse Employer name 2';
            $client->setFileUpload('spouse_resume.txt', 'qf_job_spouse_resume', 'spouse resume file content');
            // **** Occupation tab **** //

            // **** Business tab ****//
            $arrParams['qf_have_experience_in_managing'] = 'yes';
            $arrParams['qf_prepared_to_invest'] = 'no';
            // **** Business tab ****//

            // In specific case we need to force to delete already uploaded resumes
            $arrParams['boo_delete_resume'] = '0'; // Pass to delete MAIN APPLICANT's resume if it was previously created
            $arrParams['boo_delete_spouse_resume'] = '0'; // Pass to delete SPOUSE's resume if it was previously created

            // This is required to be sure that the same link cannot be used again
            $arrParams['expire_on'] = gmdate('c', strtotime('+ 1 minute'));

            $arrParams = array(
                'hash' => $this->_encryption->customEncrypt(
                    Json::encode($arrParams),
                    $this->_config['marketplace']['key'],
                    $this->_config['marketplace']['private_pem'],
                    $this->_config['marketplace']['public_pem']
                )
            );
            $client->setParameterPost($arrParams);


            // Preforming a POST request
            $client->setMethod('POST');
            $response = $client->send();

            $strResult = '<b>URL:</b> ' . $client->getUri() . PHP_EOL;
            $strResult .= '<b>Status:</b> ' . $response->getStatusCode() . PHP_EOL;
            $strResult .= '<b>Headers:</b> ' . print_r($response->getHeaders(), true) . PHP_EOL;
            $strResult .= '<b>Body:</b> ' . print_r($response->getBody(), true);
        } catch (Exception $e) {
            $strResult = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('content', '<pre>' . $strResult . '</pre>');

        return $view;
    }

    public function testInviteProspectAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        try {
            $client = $this->_getHttpClient('invite-prospect');

            $arrParams = array(
                // Correct Officio prospect id
                'prospect_id' => 56791,

                // Correct Officio companies ids, minimum one must be set
                'company_id' => array(4, 50),

                // @NOTE: if this prospect was already invited - it is possible to clear all previous invites
                'clear_previous_invites' => true,

                // This is required to be sure that the same link cannot be used again
                'expire_on' => gmdate('c', strtotime('+ 1 minute'))
            );

            $arrParams = array(
                'hash' => $this->_encryption->customEncrypt(
                    Json::encode($arrParams),
                    $this->_config['marketplace']['key'],
                    $this->_config['marketplace']['private_pem'],
                    $this->_config['marketplace']['public_pem']
                )
            );
            $client->setParameterPost($arrParams);

            // Preforming a POST request
            $client->setMethod('POST');
            $response = $client->send();

            $strResult = '<b>URL:</b> ' . $client->getUri() . PHP_EOL;
            $strResult .= '<b>Params:</b> ' . print_r($arrParams, true) . PHP_EOL;
            $strResult .= '<b>Status:</b> ' . $response->getStatusCode() . PHP_EOL;
            $strResult .= '<b>Headers:</b> ' . print_r($response->getHeaders(), true) . PHP_EOL;
            $strResult .= '<b>Body:</b> ' . print_r($response->getBody(), true);
        } catch (Exception $e) {
            $strResult = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('content', '<pre>' . $strResult . '</pre>');

        return $view;
    }

    public function testProfileStatusUpdateAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        try {
            $client = $this->_getHttpClient('update-profile-status');

            // Main params
            $arrParams = array(
                // Correct Officio company id
                'company_id' => 1,

                // Unique int profile id (for each company)
                'marketplace_profile_id' => 123,

                // String key, max length 255 chars
                'marketplace_profile_key' => 'some string key',

                // String name, max length 255 chars
                'marketplace_profile_name' => 'Some profile name',

                // Possible options: 'active', 'inactive'
                'marketplace_profile_status' => 'active',

                // This is required to be sure that the same link cannot be used again
                'expire_on' => gmdate('c', strtotime('+ 1 minute'))
            );

            // Note if there is no pair [company id AND profile id] - a new one will be created, otherwise record will be updated

            $arrParams = array(
                'hash' => $this->_encryption->customEncrypt(
                    Json::encode($arrParams),
                    $this->_config['marketplace']['key'],
                    $this->_config['marketplace']['private_pem'],
                    $this->_config['marketplace']['public_pem']
                )
            );
            $client->setParameterPost($arrParams);

            // Preforming a POST request
            $client->setMethod('POST');
            $response = $client->send();

            $strResult = '<b>URL:</b> ' . $client->getUri() . PHP_EOL;
            $strResult .= '<b>Status:</b> ' . $response->getStatusCode() . PHP_EOL;
            $strResult .= '<b>Headers:</b> ' . print_r($response->getHeaders(), true) . PHP_EOL;
            $strResult .= '<b>Body:</b> ' . print_r($response->getBody(), true);
        } catch (Exception $e) {
            $strResult = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('content', '<pre>' . $strResult . '</pre>');

        return $view;
    }

    public function testProspectStatusUpdateAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        try {
            $client = $this->_getHttpClient('update-prospect-status');

            // Main params
            $arrParams = array(
                // Correct Officio prospect id
                'prospect_id' => 2,
                'prospect_first_name' => 'First Name',
                'prospect_last_name' => 'Last Name',
                'prospect_email' => 'email@email.com',


                // Possible options: 'active', 'inactive', 'suspended'
                'prospect_status' => 'inactive',
                'mp_prospect_expiration_date' => date('Y-m-d', strtotime('tomorrow')),

                // This is required to be sure that the same link cannot be used again
                'expire_on' => gmdate('c', strtotime('+ 1 minute'))
            );

            // Note if there is no pair [company id AND profile id] - a new one will be created, otherwise record will be updated

            $arrParams = array(
                'hash' => $this->_encryption->customEncrypt(
                    Json::encode($arrParams),
                    $this->_config['marketplace']['key'],
                    $this->_config['marketplace']['private_pem'],
                    $this->_config['marketplace']['public_pem']
                )
            );
            $client->setParameterPost($arrParams);

            // Preforming a POST request
            $client->setMethod('POST');
            $response = $client->send();

            $strResult = '<b>URL:</b> ' . $client->getUri() . PHP_EOL;
            $strResult .= '<b>Status:</b> ' . $response->getStatusCode() . PHP_EOL;
            $strResult .= '<b>Headers:</b> ' . print_r($response->getHeaders(), true) . PHP_EOL;
            $strResult .= '<b>Body:</b> ' . print_r($response->getBody(), true);
        } catch (Exception $e) {
            $strResult = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('content', '<pre>' . $strResult . '</pre>');

        return $view;
    }

    public function testProspectsConversionCountAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        try {
            $client = $this->_getHttpClient('get-prospects-conversion-count');

            // Main params
            $arrParams = array(
                // Correct Officio prospect ids
                'prospect_ids' => array(269051, 123, 5),

                // This is required to be sure that the same link cannot be used again
                'expire_on' => gmdate('c', strtotime('+ 1 minute'))
            );

            $arrParams = array(
                'hash' => $this->_encryption->customEncrypt(
                    Json::encode($arrParams),
                    $this->_config['marketplace']['key'],
                    $this->_config['marketplace']['private_pem'],
                    $this->_config['marketplace']['public_pem']
                )
            );
            $client->setParameterPost($arrParams);

            // Preforming a POST request
            $client->setMethod('POST');
            $response = $client->send();

            $strResult = '<b>URL:</b> ' . $client->getUri() . PHP_EOL;
            $strResult .= '<b>Status:</b> ' . $response->getStatusCode() . PHP_EOL;
            $strResult .= '<b>Headers:</b> ' . print_r($response->getHeaders(), true) . PHP_EOL;
            $strResult .= '<b>Body:</b> ' . print_r($response->getBody(), true);
        } catch (Exception $e) {
            $strResult = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('content', '<pre>' . $strResult . '</pre>');

        return $view;
    }

    public function testGetLanguageFieldsAssessmentsAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        try {
            $client = $this->_getHttpClient('get-language-fields-assessments');

            $arrReceivedFields = array(
                'qf_language_english_done' => 'no',
                'qf_language_english_general_score_speak' => 'native_proficiency',
                'qf_language_english_general_score_read' => 'native_proficiency',
                'qf_language_english_general_score_write' => 'native_proficiency',
                'qf_language_english_general_score_listen' => 'native_proficiency',

                'qf_language_french_done' => 'no',
                'qf_language_french_general_score_speak' => 'basic',
                'qf_language_french_general_score_read' => 'basic',
                'qf_language_french_general_score_write' => 'basic',
                'qf_language_french_general_score_listen' => 'basic'
            );

            // Main params
            $arrParams = array(
                'data' => Json::encode($arrReceivedFields),

                // This is required to be sure that the same link cannot be used again
                'expire_on' => gmdate('c', strtotime('+ 1 minute'))
            );

            $arrParams = array(
                'hash' => $this->_encryption->customEncrypt(
                    Json::encode($arrParams),
                    $this->_config['marketplace']['key'],
                    $this->_config['marketplace']['private_pem'],
                    $this->_config['marketplace']['public_pem']
                )
            );
            $client->setParameterPost($arrParams);

            // Preforming a POST request
            $client->setMethod('POST');
            $response = $client->send();

            $strResult = '<b>URL:</b> ' . $client->getUri() . PHP_EOL;
            $strResult .= '<b>Status:</b> ' . $response->getStatusCode() . PHP_EOL;
            $strResult .= '<b>Headers:</b> ' . print_r($response->getHeaders(), true) . PHP_EOL;
            $strResult .= '<b>Body:</b> ' . print_r($response->getBody(), true);
        } catch (Exception $e) {
            $strResult = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('content', '<pre>' . $strResult . '</pre>');

        return $view;
    }


}
