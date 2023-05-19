<?php

namespace Clients\Service\Clients;

use Clients\Service\Clients;
use Exception;
use Laminas\Db\Sql\Select;
use Officio\Common\Json;
use Officio\Common\Service\BaseService;
use Officio\Service\Company;
use Officio\Common\SubServiceInterface;
use Laminas\Db\Sql\Expression;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class CaseNumber extends BaseService implements SubServiceInterface
{

    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_parent;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
    }

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent()
    {
        return $this->_parent;
    }

    /**
     * Generate cache id - used when save/load 'case number generation' config settings
     *
     * @param int $companyId
     * @return string cache id
     */
    private static function _getCacheId($companyId)
    {
        return 'company_case_number_settings_' . $companyId;
    }

    /**
     * Check if given file number is valid
     *
     * @param string $fileNum
     * @return bool true if valid
     */
    public static function isValidFileNum($fileNum)
    {
        return empty($fileNum) ? true : preg_match('/^[a-z\dA-Z_\-\/ \.]+$/', $fileNum);
    }

    /**
     * Remove not allowed chars from the file number
     *
     * @param string $fileNum
     * @return string mixed
     */
    public static function filterFileNum($fileNum)
    {
        return preg_replace('/[^a-z\dA-Z_\-\/ \.]/', '', $fileNum);
    }

    /**
     * Load 'case number generation' config for specific company
     *
     * @param int $companyId
     * @return array
     */
    public function getCompanyCaseNumberSettings($companyId)
    {
        // Default settings
        $arrCaseNumberSettings = array(
            'cn-1' => '1',
            'cn-2' => '2',
            'cn-3' => '3',
            'cn-4' => '4',
            'cn-5' => '5',
            'cn-6' => '6',
            'cn-7' => '7',
            'cn-8' => '8',

            'cn-generate-number'           => 'not-generate',
            'cn-include-fixed-prefix'      => 'off',
            'cn-include-fixed-prefix-text' => '',
            'cn-name-prefix'               => 'off',
            'cn-name-prefix-family-name'   => 3,
            'cn-name-prefix-given-names'   => 1,
            'cn-increment'                 => 'off',
            'cn-increment-employer'        => 'off',
            'cn-subclass'                  => 'off',
            'cn-start-from'                => 'off',
            'cn-start-from-text'           => '0001',
            'cn-separator'                 => '/',
            'cn-client-profile-id'         => 'off',
            'cn-number-of-client-cases'    => 'off',
        );

        try {
            // Use cache to save settings
            if (!($strSavedSettings = $this->_cache->getItem(self::_getCacheId($companyId)))) {
                $select = (new Select())
                    ->from('company_details')
                    ->columns(['case_number_settings'])
                    ->where(['company_id' => (int)$companyId]);

                $strSavedSettings = $this->_db2->fetchOne($select);

                $this->_cache->setItem(self::_getCacheId($companyId), $strSavedSettings);
            }

            if (!empty($strSavedSettings)) {
                // Merge with default settings, so not saved data will be used from default
                $arrCaseNumberSettings = array_merge($arrCaseNumberSettings, Json::decode($strSavedSettings, Json::TYPE_ARRAY));
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrCaseNumberSettings;
    }

    /**
     * Save 'case number generation' config
     *
     * @param int $companyId
     * @param array $arrSettings
     * @return bool true on success
     */
    public function saveCaseNumberSettings($companyId, $arrSettings)
    {
        $booSuccess = false;
        try {
            if (!is_array($arrSettings) || !count($arrSettings)) {
                $strSettings = null;
            } else {
                $strSettings = Json::encode($arrSettings);
            }

            $this->_company->updateCompanyDetails(
                $companyId,
                array('case_number_settings' => $strSettings)
            );

            // Clear cached settings
            $this->_cache->removeItem(self::_getCacheId($companyId));

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Check if 'case number' field must be showed as readonly for the company
     *
     * @param int $companyId
     * @return bool true if it must be showed as readonly, otherwise false
     */
    public function isFileNumberReadOnly($companyId)
    {
        $arrCompanySettings = $this->getCompanyCaseNumberSettings($companyId);

        return array_key_exists('cn-generate-number', $arrCompanySettings) && $arrCompanySettings['cn-generate-number'] == 'generate' &&
            array_key_exists('cn-read-only', $arrCompanySettings) && $arrCompanySettings['cn-read-only'] == 'on';
    }

    /**
     * Check if 'generate automatically' option is turned on for the company
     *
     * @param int $companyId
     * @return bool true if 'generate automatically' option is turned on
     */
    public function isAutomaticTurnedOn($companyId)
    {
        $arrCompanySettings = $this->getCompanyCaseNumberSettings($companyId);

        return array_key_exists('cn-generate-number', $arrCompanySettings) && $arrCompanySettings['cn-generate-number'] == 'generate';
    }

    /**
     * Generate Case Number based on settings defined in the company admin section
     * Case number can be based on:
     * 1. Fixed prefix
     * 2. Parent client (IA/Employer) name
     * 3. Unique counter of the cases assigned to the parent client
     * 4. Based on special case's field (Category Abbreviation)
     * 5. Based on case's type (Immigration program)
     * 6. Counter of the cases created in the company
     * 7. Client Profile ID field (from the parent client)
     * 8. Number of client cases
     *
     * @param int $companyId
     * @param int $individualClientId
     * @param int $employerClientId
     * @param int $caseId
     * @param int $caseTypeId
     * @param string $subclassAbbreviation
     * @param bool $booCaseIsNew - true if we need to reset cases count and load from the company
     * @param int $attempt Increases every time after unsuccessfull generation attempt
     *
     * @return array generated case number
     */
    public function generateNewCaseNumber($companyId, $individualClientId, $employerClientId, $caseId, $caseTypeId, $subclassAbbreviation, $booCaseIsNew = false, $attempt = 0)
    {
        try {
            $strError        = '';
            $newCaseNumber   = '';
            $startNumberFrom = '';
            $increment       = '';

            $arrCompanySettings = $this->getCompanyCaseNumberSettings($companyId);
            if (array_key_exists('cn-generate-number', $arrCompanySettings) && $arrCompanySettings['cn-generate-number'] == 'generate') {
                $arrNumberParts = array();

                // Load info about created case
                $arrClientInfo = empty($caseId) ? array() : $this->_parent->getClientInfo($caseId);

                // Fixed prefix
                if (array_key_exists('cn-include-fixed-prefix', $arrCompanySettings) && $arrCompanySettings['cn-include-fixed-prefix'] == 'on') {
                    $fixedPrefix = array_key_exists('cn-include-fixed-prefix-text', $arrCompanySettings) ? $arrCompanySettings['cn-include-fixed-prefix-text'] : '';
                    if (!empty($fixedPrefix)) {
                        $fixedPrefix = str_ireplace('%year%', date('Y'), $fixedPrefix);
                        $fixedPrefix = str_ireplace('%short_year%', date('y'), $fixedPrefix);
                        $fixedPrefix = str_ireplace('%month%', date('m'), $fixedPrefix);
                        $fixedPrefix = str_ireplace('%day%', date('d'), $fixedPrefix);

                        $arrNumberParts[$arrCompanySettings['cn-1']] = trim($fixedPrefix);
                    }
                }

                // First name / Last name
                if (array_key_exists('cn-name-prefix', $arrCompanySettings) && $arrCompanySettings['cn-name-prefix'] == 'on') {
                    // Load saved client name and use it in relation to the type
                    $parentClientId = empty($individualClientId) ? $employerClientId : $individualClientId;
                    $arrParentInfo  = $this->_parent->getMemberInfo($parentClientId);
                    $booEmployer    = $arrParentInfo['userType'] == $this->_parent->getMemberTypeIdByName('employer');
                    $familyName     = $arrParentInfo['lName'] ?? '';
                    $givenNames     = $booEmployer ? '' : $arrParentInfo['fName'];


                    $familyNameLengthToCut = array_key_exists('cn-name-prefix-family-name', $arrCompanySettings) ? (int)$arrCompanySettings['cn-name-prefix-family-name'] : 0;
                    $givenNamesLengthToCut = array_key_exists('cn-name-prefix-given-names', $arrCompanySettings) ? (int)$arrCompanySettings['cn-name-prefix-given-names'] : 0;

                    $name = trim(substr($familyName, 0, $familyNameLengthToCut) . substr($givenNames, 0, $givenNamesLengthToCut));
                    if (strlen($name)) {
                        $arrNumberParts[$arrCompanySettings['cn-2']] = $name;
                    }
                }

                // Start Case File # from and increment by one for each new case created skipping duplicate numbers
                if (array_key_exists('cn-start-from', $arrCompanySettings) && $arrCompanySettings['cn-start-from'] == 'on') {
                    $maxValue           = array_key_exists('cn-start-from-text', $arrCompanySettings) ? (int)$arrCompanySettings['cn-start-from-text'] : 0;
                    $maxValueLength     = array_key_exists('cn-start-from-text', $arrCompanySettings) ? strlen($arrCompanySettings['cn-start-from-text'] ?? '') : 0;
                    $booBasedOnCaseType = array_key_exists('cn-global-or-based-on-case-type', $arrCompanySettings) && $arrCompanySettings['cn-global-or-based-on-case-type'] === 'case-type';
                    $caseTypeId         = $booBasedOnCaseType ? $caseTypeId : 0;

                    if (empty($caseId)) {
                        $totalCasesCount = $this->_parent->getCompanyCaseMaxNumber($companyId, $caseTypeId) + 1;
                    } elseif ($booCaseIsNew) {
                        $totalCasesCount = $this->_parent->getCompanyCaseMaxNumber($companyId, $caseTypeId);
                    } else {
                        $key             = $booBasedOnCaseType ? 'case_number_with_same_case_type_in_company' : 'case_number_in_company';
                        $totalCasesCount = is_array($arrClientInfo) && array_key_exists($key, $arrClientInfo) ? intval($arrClientInfo[$key]) : 0;
                    }

                    $increment = $resultMaxValue = $maxValue + $totalCasesCount + $attempt;

                    $maxReservedIncrement = $this->getMaxFileNumberReservedIncrement($companyId);

                    if (!empty($maxReservedIncrement)) {
                        if ($maxValue + $totalCasesCount < $maxReservedIncrement) {
                            $increment = $resultMaxValue = $maxReservedIncrement + $attempt;
                        }
                    }

                    // Prepend leading zeroes if needed
                    // e.g. format 00001, count is 25 -> 00025
                    $arrNumberParts[$arrCompanySettings['cn-3']] = str_pad($resultMaxValue, $maxValueLength, '0', STR_PAD_LEFT);
                }

                // Start Case File # from and increment by one each time Case File # is generated
                if (array_key_exists('cn-start-number-from', $arrCompanySettings) && $arrCompanySettings['cn-start-number-from'] == 'on') {
                    $maxValue       = array_key_exists('cn-start-number-from-text', $arrCompanySettings) ? (int)$arrCompanySettings['cn-start-number-from-text'] : 0;
                    $maxValueLength = array_key_exists('cn-start-number-from-text', $arrCompanySettings) ? strlen($arrCompanySettings['cn-start-number-from-text'] ?? '') : 0;

                    $attempt  = $attempt - 1;
                    $maxValue = $maxValue + $attempt;

                    $startNumberFrom = $maxValue + 1;
                    $startNumberFrom = str_pad((string)$startNumberFrom, $maxValueLength, '0', STR_PAD_LEFT);

                    // Prepend leading zeroes if needed
                    // e.g. format 00001, count is 25 -> 00025
                    $arrNumberParts[$arrCompanySettings['cn-6']] = str_pad((string)$maxValue, $maxValueLength, '0', STR_PAD_LEFT);
                }

                // Subclass Digits
                if (array_key_exists('cn-subclass', $arrCompanySettings) && $arrCompanySettings['cn-subclass'] == 'on') {
                    if (!empty($subclassAbbreviation)) {
                        $arrNumberParts[$arrCompanySettings['cn-4']] = trim($subclassAbbreviation);
                    }
                }

                // Total count of cases for this parent client
                if (array_key_exists('cn-increment', $arrCompanySettings) && $arrCompanySettings['cn-increment'] == 'on') {
                    // Force to use the Employer's count
                    $booUseEmployerKey = isset($arrCompanySettings['cn-increment-employer']) && $arrCompanySettings['cn-increment-employer'] == 'on';
                    if (empty($caseId)) {
                        if ($booUseEmployerKey && !empty($employerClientId)) {
                            $maxUsedNumber = $this->_parent->getClientCaseMaxNumber($employerClientId, true);
                        } else {
                            $parentClientId = empty($individualClientId) ? $employerClientId : $individualClientId;
                            $maxUsedNumber  = $this->_parent->getClientCaseMaxNumber($parentClientId, false);
                        }

                        $caseClientNumber = empty($maxUsedNumber) ? 1 : $maxUsedNumber + 1;
                    } else {
                        $caseClientNumber = 0;
                        if ($booUseEmployerKey) {
                            $caseClientNumber = !empty($arrClientInfo['case_number_of_parent_employer']) ? intval($arrClientInfo['case_number_of_parent_employer']) : 0;
                        }

                        // If employer count wasn't set - use for the IA
                        if (empty($caseClientNumber)) {
                            $caseClientNumber = !empty($arrClientInfo['case_number_of_parent_client']) ? intval($arrClientInfo['case_number_of_parent_client']) : 0;
                        }

                        $caseClientNumber = empty($caseClientNumber) ? 1 : $caseClientNumber;
                    }

                    $arrNumberParts[$arrCompanySettings['cn-5']] = $caseClientNumber;
                }

                // Client Profile ID
                if (array_key_exists('cn-client-profile-id', $arrCompanySettings) && $arrCompanySettings['cn-client-profile-id'] == 'on' && $this->_company->isCompanyClientProfileIdEnabled($companyId)) {
                    // Load saved Client Profile ID
                    $parentClientId  = empty($employerClientId) ? $individualClientId : $employerClientId;
                    $clientProfileId = $this->_parent->getApplicantFields()->getClientSavedClientProfileId($companyId, $parentClientId);
                    if (strlen($clientProfileId ?? '')) {
                        $arrNumberParts[$arrCompanySettings['cn-7']] = $clientProfileId;
                    }
                }

                // Number of Client Cases
                if (array_key_exists('cn-number-of-client-cases', $arrCompanySettings) && $arrCompanySettings['cn-number-of-client-cases'] == 'on') {
                    $arrNumberParts[$arrCompanySettings['cn-8']] = $this->_parent->getCaseNumberForClient(
                        empty($employerClientId) ? $individualClientId : $employerClientId,
                        $caseId
                    );
                }

                // Sort all parts by order specified in settings
                ksort($arrNumberParts);

                // Unite all parts in one string
                $separator     = (array_key_exists('cn-separator', $arrCompanySettings) && $arrCompanySettings['cn-separator'] != 'blank') ? $arrCompanySettings['cn-separator'] : '';
                $newCaseNumber = implode($separator, $arrNumberParts);

                // Remove chars that are invalid/not allowed
                $newCaseNumber = self::filterFileNum($newCaseNumber);

                if (!self::isValidFileNum($newCaseNumber)) {
                    $strError = $this->_tr->translate('Incorrect characters');
                }

                if (strlen($newCaseNumber) > 32) {
                    $strError = $this->_tr->translate('Maximum length: 32 symb.');
                }

                if (!strlen($newCaseNumber)) {
                    $strError = $this->_tr->translate('Cannot be empty.');
                }

                if (!empty($strError)) {
                    $newCaseNumber = '';
                }
            }
        } catch (Exception $e) {
            $strError        = $this->_tr->translate('Internal Error.');
            $newCaseNumber   = '';
            $startNumberFrom = '';
            $increment       = '';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($strError, $newCaseNumber, $startNumberFrom, $increment);
    }

    /**
     * Check if Visa Subclass option is checked for the company
     *
     * @param int $companyId
     * @return bool
     */
    public function isSubClassSettingEnabled($companyId)
    {
        $arrCompanySettings = $this->getCompanyCaseNumberSettings($companyId);

        return array_key_exists('cn-subclass', $arrCompanySettings) && $arrCompanySettings['cn-subclass'] == 'on';
    }


    /**
     * Generate case number based on the client and case data
     *
     * @param array $arrParams
     * @param int $individualClientId
     * @param int $employerClientId
     * @param int $caseId
     * @param int $caseTemplateId
     * @param int $attempt
     * @return array
     */
    public function generateCaseReference($arrParams, $individualClientId, $employerClientId, $caseId, $caseTemplateId, $attempt = 0)
    {
        $strError              = '';
        $newCaseNumber         = '';
        $startCaseNumberFrom   = '';
        $subclassMarkInvalidId = '';
        $increment             = '';

        try {
            if (!empty($individualClientId) && !$this->_parent->hasCurrentMemberAccessToMember($individualClientId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && !empty($employerClientId) && !$this->_parent->hasCurrentMemberAccessToMember($employerClientId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && !empty($caseId) && !$this->_parent->hasCurrentMemberAccessToMember($caseId)) {
                $strError = $this->_tr->translate('Incorrectly selected case.');
            }

            if (empty($strError)) {
                $arrTemplateInfo = $this->_parent->getCaseTemplates()->getTemplateInfo($caseTemplateId);
                if (!is_array($arrTemplateInfo) || !count($arrTemplateInfo)) {
                    $caseTypeTerm = $this->_company->getCurrentCompanyDefaultLabel('case_type');
                    $strError     = $this->_tr->translate('Incorrectly selected') . ' ' . $caseTypeTerm;
                }
            }

            if (empty($strError)) {
                $companyId = $this->_auth->getCurrentUserCompanyId();

                // Get the currently used subclass (categories) field id
                $subclassFieldId   = 0;
                $subclassFieldName = '';
                $arrGroupedFields  = $this->_parent->getFields()->getGroupedCompanyFields($caseTemplateId);
                foreach ($arrGroupedFields as $arrGroupInfo) {
                    foreach ($arrGroupInfo['fields'] as $arrFieldInfo) {
                        if ($arrFieldInfo['field_type'] == 'categories') {
                            $subclassFieldId   = $arrFieldInfo['field_id'];
                            $subclassFieldName = $arrFieldInfo['field_name'];
                            break 2;
                        }
                    }
                }

                // Get value for subclass (categories) field
                $subclassFieldVal = '';
                if (!empty($subclassFieldId)) {
                    foreach ($arrParams as $paramName => $paramValue) {
                        if (preg_match('/^field_case_(\d+)_(\d+)$/i', $paramName, $regs)) {
                            if ($subclassFieldId == $regs[2]) {
                                $subclassFieldVal = is_array($paramValue) && count($paramValue) ? $paramValue[0] : $paramValue;

                                // Get full field id of visa subclass field if its value is empty for mark invalid
                                if (empty($subclassFieldVal)) {
                                    $subclassMarkInvalidId = $paramName;
                                }
                                break;
                            }
                        }
                    }
                }

                // Identify abbreviation for selected option in the subclass (categories) field
                $subclassAbbreviation = '';
                if (!empty($subclassFieldVal)) {
                    $arrAllOptions = $this->_parent->getCaseCategories()->getCompanyCaseCategories($companyId);
                    foreach ($arrAllOptions as $arrOptionInfo) {
                        if ($arrOptionInfo['client_category_id'] == $subclassFieldVal) {
                            $subclassAbbreviation = $arrOptionInfo['client_category_abbreviation'];
                            break;
                        }
                    }
                }

                $arrCompanySettings = $this->getCompanyCaseNumberSettings($companyId);
                if (array_key_exists('cn-generate-number', $arrCompanySettings) && $arrCompanySettings['cn-generate-number'] == 'generate') {
                    if (array_key_exists('cn-subclass', $arrCompanySettings) && $arrCompanySettings['cn-subclass'] == 'on' && empty($subclassAbbreviation)) {
                        if (!empty($subclassFieldName)) {
                            $strError = sprintf(
                                $this->_tr->translate('Please enter a %s for this application. This information is required to generate a Reference Number.'),
                                $subclassFieldName
                            );
                        }
                    }
                }

                if (empty($strError)) {
                    // Generate case number based on company settings + case/client saved data + number of attempts
                    list($strError, $newCaseNumber, $startCaseNumberFrom, $increment) = $this->generateNewCaseNumber($companyId, $individualClientId, $employerClientId, $caseId, $caseTemplateId, $subclassAbbreviation, false, $attempt);
                    if (empty($strError)) {
                        $subclassMarkInvalidId = '';
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'strError'              => $strError,
            'newCaseNumber'         => $newCaseNumber,
            'startCaseNumberFrom'   => $startCaseNumberFrom,
            'subclassMarkInvalidId' => $subclassMarkInvalidId,
            'increment'             => $increment
        );
    }

    /**
     * Reserves file number so it won't be repeated.
     *
     * @param int $companyId
     * @param string $strFileNumber
     * @param int $increment // "increment skipping duplicate" number
     * @return bool
     */
    public function reserveFileNumber($companyId, $strFileNumber, $increment)
    {
        return $this->_db2->fetchOne('SELECT reserve_file_number(?, ?, ?) as res;', [$companyId, $strFileNumber, $increment]);
    }

    /**
     * Releases file number previously checking if it was assigned to a case.
     *
     * @param $companyId
     * @param string $strFileNumber
     * @return bool
     */
    public function releaseFileNumber($companyId, $strFileNumber)
    {
        try {
            $this->_db2->delete(
                'file_number_reservations',
                [
                    'company_id'  => (int)$companyId,
                    'file_number' => $strFileNumber
                ]
            );

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $booSuccess = false;
        }

        return $booSuccess;
    }

    /**
     * Deletes all abandoned file number reservations. This cleanup is necessary, because file number might not always
     * be properly released in case of some error (case was not created, new generated file number was not saved, etc).
     *
     * @param string|int $expireBefore
     * @return mixed
     */
    public function expireAbandonedFileNumberReservations($expireBefore)
    {
        return $this->_db2->fetchOne('SELECT expire_abandoned_file_number_reservations(?) as res;', [$expireBefore]);
    }

    /**
     * Get max company file number increment ("increment skipping duplicate" number)
     *
     * @param int $companyId
     * @return bool true if already used
     */
    public function getMaxFileNumberReservedIncrement($companyId)
    {
        $increment = '';

        try {
            $select = (new Select())
                ->from('file_number_reservations')
                ->columns(array('increment' => new Expression('MAX(increment)')))
                ->where(['company_id' => (int)$companyId]);

            $increment = $this->_db2->fetchOne($select);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $increment;
    }


    /**
     * Create default company case number settings from the default to a specific company
     *
     * @param int $fromCompanyId
     * @param int $toCompanyId
     * @return void
     */
    public function createDefaultCompanyCaseNumberSettings($fromCompanyId, $toCompanyId)
    {
        $arrSettings = $this->getCompanyCaseNumberSettings($fromCompanyId);
        $this->saveCaseNumberSettings($toCompanyId, $arrSettings);
    }
}
