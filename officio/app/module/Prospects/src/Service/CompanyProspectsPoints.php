<?php

namespace Prospects\Service;

use Officio\Common\Service\BaseService;
use Officio\Service\Company;
use Officio\Common\SubServiceInterface;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class CompanyProspectsPoints extends BaseService implements SubServiceInterface
{

    /** @var Company */
    protected $_company;

    /** @var CompanyQnr */
    protected $_companyQnr;

    /** @var CompanyQnr */
    private $_parent;

    public function setParent($parent) {
        $this->_parent = $parent;
    }

    public function getParent() {
        return $this->_parent;
    }

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
    }

    /**
     *
     * @param $strMethod - one from 'qnr' or 'prospect_occupations' or 'prospect_business' or 'prospect_profile' or 'prospect_assessment'
     * @param array $arrReceivedFields
     * @param array $arrQFields
     * @param array $arrQFieldsOptions
     * @param int $prospectId
     * @param bool $booReturnLanguageAssessments
     * @return array
     */
    public function calculatePoints($strMethod, $arrReceivedFields, $arrQFields, $arrQFieldsOptions, $prospectId, $booReturnLanguageAssessments = false)
    {
        // In this array we save maximum points need to be reached
        // to be assigned for specific category
        $arrPointsToPass = array(
            'skilled_worker' => 67
        );

        $booShowEnglishAlert  = false;
        $booShowFrenchAlert   = false;
        $booHasFrench7OrHigh  = null;
        $booHasEnglish5OrHigh = null;

        // Format options list in format:
        // option_id => option_unique_id
        $arrOptions = array();
        foreach ($arrQFieldsOptions as $arrFieldOptions) {
            foreach ($arrFieldOptions as $optionInfo) {
                if (is_array($optionInfo) && array_key_exists('q_field_option_id', $optionInfo) && array_key_exists('q_field_option_unique_id', $optionInfo)) {
                    $arrOptions[$optionInfo['q_field_option_id']] = $optionInfo['q_field_option_unique_id'];
                }
            }
        }

        // For occupations and business tabs load prospect's profile info
        $ageFieldId = $this->getParent()->getQuestionnaireFieldIdByUniqueId('qf_age');

        $arrProspectData = $this->getParent()->getParent()->getProspectData($prospectId);

        if (in_array($strMethod, array('prospect_occupations', 'prospect_business', 'prospect_assessment'))) {
            $arrProspectDataPrepared = array();
            foreach ($arrProspectData as $fieldId => $fieldVal) {
                $arrProspectDataPrepared[$fieldId] = array($fieldVal);
            }

            $arrReceivedFields = $arrReceivedFields + $arrProspectDataPrepared;

            // Load additional info from prospect's profile
            $arrProspectInfo = $this->getParent()->getParent()->getProspectInfo($prospectId, null);

            $age = '';
            if (!empty($arrProspectInfo['date_of_birth'])) {
                $birthday_timestamp = strtotime($arrProspectInfo['date_of_birth']);
                $age                = date('md', $birthday_timestamp) > date('md') ? date('Y') - date('Y', $birthday_timestamp) - 1 : date('Y') - date('Y', $birthday_timestamp);
            }

            $arrAdditionalInfo = array(
                $ageFieldId => array($age)
            );

            $arrReceivedFields = $arrReceivedFields + $arrAdditionalInfo;
        } else {
            if (array_key_exists($ageFieldId, $arrReceivedFields)) {
                $age = '';
                if (!empty($arrReceivedFields[$ageFieldId])) {
                    $birthday_timestamp = strtotime($arrReceivedFields[$ageFieldId][0]);
                    $age                = date('md', $birthday_timestamp) > date('md') ? date('Y') - date('Y', $birthday_timestamp) - 1 : date('Y') - date('Y', $birthday_timestamp);
                }
                $arrReceivedFields[$ageFieldId][0] = $age;
            }
        }

        $maritalStatusFieldId = '';
        $arrSpouseFieldIds    = array();
        foreach ($arrQFields as $qField) {
            if ($qField['q_field_unique_id'] == 'qf_marital_status') {
                $maritalStatusFieldId = $qField['q_field_id'];
            }
            if (strpos($qField['q_field_unique_id'] ?? '', 'spouse') !== false) {
                $arrSpouseFieldIds[] = $qField['q_field_id'];
            }
        }

        $booHasProspectSpouse = false;
        if (array_key_exists($maritalStatusFieldId, $arrReceivedFields)) {
            $booHasProspectSpouse = $this->getParent()->getParent()->hasProspectSpouse($arrReceivedFields[$maritalStatusFieldId][0]);
        } else {
            if ($arrProspectData) {
                $booHasProspectSpouse = $this->getParent()->getParent()->hasProspectSpouse((int)$arrProspectData[$maritalStatusFieldId]);
            }
        }

        if (!$booHasProspectSpouse) {
            foreach ($arrReceivedFields as $checkFieldId => $arrFieldValues) {
                if (in_array($checkFieldId, $arrSpouseFieldIds)) {
                    unset($arrReceivedFields[$checkFieldId]);
                }
            }
        }

        krsort($arrReceivedFields);


        // For prospect's profile and business tabs
        // load job data for prospect and spouse
        $arrJobs       = array();
        $arrSpouseJobs = array();
        if (in_array($strMethod, array('prospect_profile', 'prospect_business', 'prospect_assessment'))) {
            // Load data from DB
            if (is_numeric($prospectId)) {
                $arrJobs       = $this->getParent()->getParent()->getProspectAssignedJobs($prospectId);
                $arrSpouseJobs = $this->getParent()->getParent()->getProspectAssignedJobs($prospectId, false, 'spouse');
            }
        } else {
            // Use provided job data
            $arrJobData       = array();
            $arrSpouseJobData = array();
            foreach ($arrReceivedFields as $checkFieldId => $arrFieldValues) {
                foreach ($arrFieldValues as $checkFieldVal) {
                    if (!isset($arrQFields[$checkFieldId]['q_field_unique_id'])) {
                        continue;
                    }

                    $uniqueFieldId = $arrQFields[$checkFieldId]['q_field_unique_id'];
                    if (preg_match('/^qf_job_/', $uniqueFieldId)) {
                        if (preg_match('/^qf_job_spouse_/', $uniqueFieldId)) {
                            $arrSpouseJobData[$arrQFields[$checkFieldId]['q_field_unique_id']][] = $checkFieldVal;
                        } else {
                            $arrJobData[$arrQFields[$checkFieldId]['q_field_unique_id']][] = $checkFieldVal;
                        }
                    }
                }
            }

            foreach ($arrJobData as $strFieldId => $arrValues) {
                foreach ($arrValues as $valueId => $value) {
                    $arrJobs[$valueId][$strFieldId] = $value;
                }
            }

            foreach ($arrSpouseJobData as $strFieldId => $arrValues) {
                foreach ($arrValues as $valueId => $value) {
                    $strFieldId                           = str_replace('qf_job_spouse_', 'qf_job_', $strFieldId);
                    $arrSpouseJobs[$valueId][$strFieldId] = $value;
                }
            }
        }

        $arrPreparedJobData = array();
        if (count($arrJobs) > 0) {
            foreach ($arrJobs as $arrJobInfo) {
                $arrNocInfo = $this->getParent()->getParent()->getNocDetails($arrJobInfo['qf_job_title']);

                // Ignore job if it is not in NOC database
                if (!empty($arrNocInfo)) {
                    $experience = '';
                    if (array_key_exists($arrJobInfo['qf_job_duration'], $arrOptions)) {
                        $experience = $arrOptions[$arrJobInfo['qf_job_duration']];
                    }

                    $arrPreparedJobData[] = array(
                        'title'           => $arrJobInfo['qf_job_title'],
                        'experience'      => $experience,
                        'location_canada' => $arrJobInfo['qf_job_location'] == 175,
                        'noc_admissible'  => strtolower($arrNocInfo['noc_admissible'] ?? ''),
                        'noc_skill_level' => strtoupper($arrNocInfo['noc_skill_level'] ?? '')
                    );
                }
            }
        }

        $arrPreparedSpouseJobData = array();
        if (count($arrSpouseJobs) > 0) {
            foreach ($arrSpouseJobs as $arrJobInfo) {
                if (array_key_exists('qf_job_title', $arrJobInfo)) {
                    $arrNocInfo = $this->getParent()->getParent()->getNocDetails($arrJobInfo['qf_job_title']);

                    // Ignore job if it is not in NOC database
                    // or if noc_admissible is NO
                    if (!empty($arrNocInfo)) {
                        $experience = '';
                        if (array_key_exists($arrJobInfo['qf_job_duration'], $arrOptions)) {
                            $experience = $arrOptions[$arrJobInfo['qf_job_duration']];
                        }

                        $arrPreparedSpouseJobData[] = array(
                            'title'           => $arrJobInfo['qf_job_title'],
                            'experience'      => $experience,
                            'location_canada' => $arrJobInfo['qf_job_location'] == 343,
                            'noc_admissible'  => strtolower($arrNocInfo['noc_admissible'] ?? ''),
                            'noc_skill_level' => strtoupper($arrNocInfo['noc_skill_level'] ?? '')
                        );
                    }
                }
            }
        }

        $arrLangSelected = array();
        $booEducationStudiedInCanadaMoreThan1Year = false;
        $booSpouseEducationStudiedInCanadaMoreThan1Year = false;
        foreach ($arrReceivedFields as $checkFieldId => $arrFieldValues) {
            foreach ($arrFieldValues as $checkFieldVal) {
                if (!isset($arrQFields[$checkFieldId]['q_field_unique_id'])) {
                    continue;
                }

                switch ($arrQFields[$checkFieldId]['q_field_unique_id']) {
                    case 'qf_language_english_done':
                    case 'qf_language_french_done':
                    case 'qf_language_spouse_english_done':
                    case 'qf_language_spouse_french_done':
                        $arrLangSelected[$arrQFields[$checkFieldId]['q_field_unique_id']] = array_key_exists($checkFieldVal, $arrOptions) ? $arrOptions[$checkFieldVal] : '';
                        break;

                    case 'qf_education_studied_in_canada_period':
                        if (isset($arrOptions[$checkFieldVal]) && in_array($arrOptions[$checkFieldVal], array('2_years', '3_years'))) {
                            $booEducationStudiedInCanadaMoreThan1Year = true;
                        }
                        break;

                    case 'qf_education_spouse_studied_in_canada_period':
                        if (isset($arrOptions[$checkFieldVal]) && in_array($arrOptions[$checkFieldVal], array('2_years', '3_years'))) {
                            $booSpouseEducationStudiedInCanadaMoreThan1Year = true;
                        }
                        break;
                }
            }
        }

        $booSpouseFirstLangLevel4  = null;
        $booSpouseSecondLangLevel4 = null;

        $arrSkilledWorkerPoints = array();
        foreach ($arrReceivedFields as $checkFieldId => $arrFieldValues) {
            // Check each field value for correctness
            foreach ($arrFieldValues as $checkFieldVal) {
                if (!isset($arrQFields[$checkFieldId]['q_field_unique_id'])) {
                    continue;
                }

                switch ($arrQFields[$checkFieldId]['q_field_unique_id']) {

                    /******* Adaptability: Relatives in Canada *******/
                    case 'qf_family_relationship':
                        $pointsAdaptability = 0;
                        if (array_key_exists($checkFieldVal, $arrOptions) && $arrOptions[$checkFieldVal] != 'none') {
                            $pointsAdaptability = 5;
                        }
                        $arrSkilledWorkerPoints['adaptability']['details']['relatives_in_canada'] = $pointsAdaptability;
                        break;

                    /******* Employment Job Offer + Adaptability: Arranged Employment in Canada *******/
                    case 'qf_work_offer_of_employment':
                        $points             = 0;
                        $pointsAdaptability = 0;
                        if (array_key_exists($checkFieldVal, $arrOptions) && $arrOptions[$checkFieldVal] == 'yes') {
                            $points             = 10;
                            $pointsAdaptability = 5;
                        }
                        $arrSkilledWorkerPoints['employment_job_offer']                          = $points;
                        $arrSkilledWorkerPoints['adaptability']['details']['arrange_employment'] = $pointsAdaptability;
                        break;

                    /******* Adaptability: Prospect studied in Canada *******/
                    case 'qf_study_previously_studied':
                        $points = 0;
                        if ($booEducationStudiedInCanadaMoreThan1Year && array_key_exists($checkFieldVal, $arrOptions) && $arrOptions[$checkFieldVal] == 'yes') {
                            $points = 5;
                        }
                        $arrSkilledWorkerPoints['adaptability']['details']['studied_in_canada'] = $points;
                        break;

                    /******* Adaptability: Spouse studied in Canada *******/
                    case 'qf_education_spouse_previously_studied':
                        $points = 0;
                        if ($booSpouseEducationStudiedInCanadaMoreThan1Year && array_key_exists($checkFieldVal, $arrOptions) && $arrOptions[$checkFieldVal] == 'yes') {
                            $points = 5;
                        }
                        $arrSkilledWorkerPoints['adaptability']['details']['spouse_studied_in_canada'] = $points;
                        break;

                    /******* Age *******/
                    case 'qf_age':
                        $selAge = (int)$checkFieldVal;

                        $points = 0;
                        switch ($selAge) {
                            case 36:
                                $points = 11;
                                break;

                            case 37:
                                $points = 10;
                                break;

                            case 38:
                                $points = 9;
                                break;

                            case 39:
                                $points = 8;
                                break;

                            case 40:
                                $points = 7;
                                break;

                            case 41:
                                $points = 6;
                                break;

                            case 42:
                                $points = 5;
                                break;

                            case 43:
                                $points = 4;
                                break;

                            case 44:
                                $points = 3;
                                break;

                            case 45:
                                $points = 2;
                                break;

                            case 46:
                                $points = 1;
                                break;

                            default:
                                if ($selAge >= 18 && $selAge <= 35) {
                                    $points = 12;
                                }
                                break;
                        }
                        $arrSkilledWorkerPoints['age'] = $points;
                        break;


                    /******* Main Applicant English Language *******/
                    case 'qf_language_english_ielts_score_speak':
                    case 'qf_language_english_ielts_score_read':
                    case 'qf_language_english_ielts_score_write':
                    case 'qf_language_english_ielts_score_listen':
                    case 'qf_language_english_celpip_score_speak':
                    case 'qf_language_english_celpip_score_read':
                    case 'qf_language_english_celpip_score_write':
                    case 'qf_language_english_celpip_score_listen':
                    case 'qf_language_english_general_score_speak':
                    case 'qf_language_english_general_score_read':
                    case 'qf_language_english_general_score_write':
                    case 'qf_language_english_general_score_listen':
                        $clb = 0;
                        switch ($arrLangSelected['qf_language_english_done']) {
                            case 'ielts':
                                switch ($arrQFields[$checkFieldId]['q_field_unique_id']) {
                                    case 'qf_language_english_ielts_score_read':
                                        $checkFieldVal = (float)$checkFieldVal;
                                        if ($checkFieldVal >= 8) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 7) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 6.5) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 6) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 5) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 4) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 3.5) {
                                            $clb = 4;
                                        }

                                        if ($booReturnLanguageAssessments && !$booShowEnglishAlert && $clb < 7) {
                                            $booShowEnglishAlert = true;
                                        }
                                        break;

                                    case 'qf_language_english_ielts_score_write':
                                        $checkFieldVal = (float)$checkFieldVal;
                                        if ($checkFieldVal >= 7.5) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 7) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 6.5) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 6) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 5.5) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 5) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 4) {
                                            $clb = 4;
                                        }

                                        if ($booReturnLanguageAssessments && !$booShowEnglishAlert && $clb < 7) {
                                            $booShowEnglishAlert = true;
                                        }
                                        break;

                                    case 'qf_language_english_ielts_score_listen':
                                        $checkFieldVal = (float)$checkFieldVal;
                                        if ($checkFieldVal >= 8.5) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 8) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 7.5) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 6) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 5.5) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 5) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 4.5) {
                                            $clb = 4;
                                        }

                                        if ($booReturnLanguageAssessments && !$booShowEnglishAlert && $clb < 7) {
                                            $booShowEnglishAlert = true;
                                        }
                                        break;

                                    case 'qf_language_english_ielts_score_speak':
                                        $checkFieldVal = (float)$checkFieldVal;
                                        if ($checkFieldVal >= 7.5) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 7) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 6.5) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 6) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 5.5) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 5) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 4) {
                                            $clb = 4;
                                        }

                                        if ($booReturnLanguageAssessments && !$booShowEnglishAlert && $clb < 7) {
                                            $booShowEnglishAlert = true;
                                        }
                                        break;

                                    default:
                                        break 3;
                                        break;
                                }

                                break;

                            case 'celpip':
                                if (!in_array($arrQFields[$checkFieldId]['q_field_unique_id'], array('qf_language_english_celpip_score_speak', 'qf_language_english_celpip_score_read', 'qf_language_english_celpip_score_write', 'qf_language_english_celpip_score_listen'))) {
                                    break 3;
                                }

                                if (array_key_exists($checkFieldVal, $arrOptions)) {
                                    if (preg_match('/^level_(\d+)$/', $arrOptions[$checkFieldVal], $regs)) {
                                        $clb = $regs[1];
                                        if ($booReturnLanguageAssessments && !$booShowEnglishAlert && $clb < 7) {
                                            $booShowEnglishAlert = true;
                                        }
                                    }
                                }
                                break;

                            case 'no':
                                if (!in_array($arrQFields[$checkFieldId]['q_field_unique_id'], array('qf_language_english_general_score_read', 'qf_language_english_general_score_write', 'qf_language_english_general_score_listen', 'qf_language_english_general_score_speak'))) {
                                    break 3;
                                }

                                if (array_key_exists($checkFieldVal, $arrOptions)) {
                                    switch ($arrOptions[$checkFieldVal]) {
                                        case 'native_proficiency':
                                            $clb = 12;
                                            break;

                                        case 'upper_intermediate':
                                            $clb = 8;
                                            break;

                                        case 'intermediate':
                                            $clb = 7;
                                            break;

                                        case 'lower_intermediate':
                                            $clb = 6;
                                            break;

                                        case 'basic':
                                            $clb = 4;
                                            break;

                                        default:
                                            break;
                                    }

                                    if ($booReturnLanguageAssessments && !$booShowEnglishAlert && $clb < 7) {
                                        $booShowEnglishAlert = true;
                                    }
                                }
                                break;

                            default:
                                break;
                        }

                        switch ($arrQFields[$checkFieldId]['q_field_unique_id']) {
                            case 'qf_language_english_ielts_score_speak':
                            case 'qf_language_english_celpip_score_speak':
                            case 'qf_language_english_general_score_speak':
                                $key = 'speaking';
                                break;

                            case 'qf_language_english_ielts_score_read':
                            case 'qf_language_english_celpip_score_read':
                            case 'qf_language_english_general_score_read':
                                $key = 'reading';
                                break;

                            case 'qf_language_english_ielts_score_write':
                            case 'qf_language_english_celpip_score_write':
                            case 'qf_language_english_general_score_write':
                                $key = 'writing';
                                break;

                            case 'qf_language_english_ielts_score_listen':
                            case 'qf_language_english_celpip_score_listen':
                            case 'qf_language_english_general_score_listen':
                            default:
                                $key = 'listening';
                                break;
                        }

                        if ($clb >= 9) {
                            $firstLangPoints = 6;
                        } elseif($clb == 8) {
                            $firstLangPoints = 5;
                        } elseif($clb == 7) {
                            $firstLangPoints = 4;
                        } else {
                            $firstLangPoints = 0;
                        }
                        $arrSkilledWorkerPoints['first_language_english']['details'][$key] = $firstLangPoints;

                        if ((is_null($booHasEnglish5OrHigh) || $booHasEnglish5OrHigh) && $clb >= 5) {
                            $booHasEnglish5OrHigh = true;
                        } else {
                            $booHasEnglish5OrHigh = false;
                        }

                        // At least CLB5 in all of the four abilities
                        if ($clb >= 5) {
                            $secondLangPoints = 4;
                        } else {
                            $secondLangPoints = 0;
                        }

                        $key = 'general';
                        if (isset($arrSkilledWorkerPoints['second_language_english']['details'][$key])) {
                            $secondLangPoints = empty($arrSkilledWorkerPoints['second_language_english']['details'][$key]) || empty($secondLangPoints) ? 0 : $secondLangPoints;
                        }
                        $arrSkilledWorkerPoints['second_language_english']['details'][$key] = $secondLangPoints;
                    break;

                    /******* Main Applicant French Language *******/
                    case 'qf_language_french_tef_score_speak':
                    case 'qf_language_french_tef_score_read':
                    case 'qf_language_french_tef_score_write':
                    case 'qf_language_french_tef_score_listen':
                    case 'qf_language_french_general_score_speak':
                    case 'qf_language_french_general_score_read':
                    case 'qf_language_french_general_score_write':
                    case 'qf_language_french_general_score_listen':
                        $clb = 0;
                        switch ($arrLangSelected['qf_language_french_done']) {
                            case 'yes':
                                switch ($arrQFields[$checkFieldId]['q_field_unique_id']) {
                                    case 'qf_language_french_tef_score_read':
                                        $checkFieldVal = (int)$checkFieldVal;
                                        if ($checkFieldVal >= 263) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 248) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 233) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 207) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 181) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 151) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 121) {
                                            $clb = 4;
                                        }

                                        if ($booReturnLanguageAssessments && !$booShowFrenchAlert && $clb < 7) {
                                            $booShowFrenchAlert = true;
                                        }
                                        break;

                                    case 'qf_language_french_tef_score_write':
                                        $checkFieldVal = (int)$checkFieldVal;
                                        if ($checkFieldVal >= 393) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 371) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 349) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 310) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 271) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 226) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 181) {
                                            $clb = 4;
                                        }

                                        if ($booReturnLanguageAssessments && !$booShowFrenchAlert && $clb < 7) {
                                            $booShowFrenchAlert = true;
                                        }
                                        break;

                                    case 'qf_language_french_tef_score_listen':
                                        $checkFieldVal = (int)$checkFieldVal;
                                        if ($checkFieldVal >= 316) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 298) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 280) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 249) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 217) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 181) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 145) {
                                            $clb = 4;
                                        }

                                        if ($booReturnLanguageAssessments && !$booShowFrenchAlert && $clb < 7) {
                                            $booShowFrenchAlert = true;
                                        }
                                        break;

                                    case 'qf_language_french_tef_score_speak':
                                        $checkFieldVal = (int)$checkFieldVal;
                                        if ($checkFieldVal >= 393) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 371) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 349) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 310) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 271) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 226) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 181) {
                                            $clb = 4;
                                        }

                                        if ($booReturnLanguageAssessments && !$booShowFrenchAlert && $clb < 7) {
                                            $booShowFrenchAlert = true;
                                        }
                                        break;

                                    default:
                                        break 3;
                                        break;
                                }

                                break;

                            case 'no':
                            case 'not_sure':
                                if (!in_array($arrQFields[$checkFieldId]['q_field_unique_id'], array('qf_language_french_general_score_read', 'qf_language_french_general_score_write', 'qf_language_french_general_score_listen', 'qf_language_french_general_score_speak'))) {
                                    break 3;
                                }

                                if (array_key_exists($checkFieldVal, $arrOptions)) {
                                    switch ($arrOptions[$checkFieldVal]) {
                                        case 'native_proficiency':
                                            $clb = 12;
                                            break;

                                        case 'upper_intermediate':
                                            $clb = 8;
                                            break;

                                        case 'intermediate':
                                            $clb = 7;
                                            break;

                                        case 'lower_intermediate':
                                            $clb = 6;
                                            break;

                                        case 'basic':
                                            $clb = 4;
                                            break;

                                        default:
                                            break;
                                    }

                                    if ($booReturnLanguageAssessments && !$booShowFrenchAlert && $clb < 7) {
                                        $booShowFrenchAlert = true;
                                    }
                                }
                                break;

                            default:
                                break;
                        }

                        switch ($arrQFields[$checkFieldId]['q_field_unique_id']) {
                            case 'qf_language_french_tef_score_speak':
                            case 'qf_language_french_general_score_speak':
                                $key = 'speaking';
                                break;

                            case 'qf_language_french_tef_score_read':
                            case 'qf_language_french_general_score_read':
                                $key = 'reading';
                                break;

                            case 'qf_language_french_tef_score_write':
                            case 'qf_language_french_general_score_write':
                                $key = 'writing';
                                break;

                            case 'qf_language_french_tef_score_listen':
                            case 'qf_language_french_general_score_listen':
                            default:
                                $key = 'listening';
                                break;
                        }

                        if ($clb >= 9) {
                            $firstLangPoints = 6;
                        } elseif($clb == 8) {
                            $firstLangPoints = 5;
                        } elseif($clb == 7) {
                            $firstLangPoints = 4;
                        } else {
                            $firstLangPoints = 0;
                        }
                        $arrSkilledWorkerPoints['first_language_french']['details'][$key] = $firstLangPoints;

                        // At least CLB5 in all of the four abilities
                        if ($clb >= 5) {
                            $secondLangPoints = 4;
                        } else {
                            $secondLangPoints = 0;
                        }

                        if ((is_null($booHasFrench7OrHigh) || $booHasFrench7OrHigh) && $clb >= 7) {
                            $booHasFrench7OrHigh = true;
                        } else {
                            $booHasFrench7OrHigh = false;
                        }

                        $key = 'general';
                        if (isset($arrSkilledWorkerPoints['second_language_french']['details'][$key])) {
                            $secondLangPoints = empty($arrSkilledWorkerPoints['second_language_french']['details'][$key]) || empty($secondLangPoints) ? 0 : $secondLangPoints;
                        }
                        $arrSkilledWorkerPoints['second_language_french']['details'][$key] = $secondLangPoints;
                        break;

                    /******* Spouse First Language *******/
                    case 'qf_language_spouse_english_ielts_score_speak':
                    case 'qf_language_spouse_english_ielts_score_read':
                    case 'qf_language_spouse_english_ielts_score_write':
                    case 'qf_language_spouse_english_ielts_score_listen':
                    case 'qf_language_spouse_english_celpip_score_speak':
                    case 'qf_language_spouse_english_celpip_score_read':
                    case 'qf_language_spouse_english_celpip_score_write':
                    case 'qf_language_spouse_english_celpip_score_listen':
                    case 'qf_language_spouse_english_general_score_speak':
                    case 'qf_language_spouse_english_general_score_read':
                    case 'qf_language_spouse_english_general_score_write':
                    case 'qf_language_spouse_english_general_score_listen':
                        $clb = 0;
                        switch ($arrLangSelected['qf_language_spouse_english_done']) {
                            case 'ielts':
                                switch ($arrQFields[$checkFieldId]['q_field_unique_id']) {
                                    case 'qf_language_spouse_english_ielts_score_read':
                                        $checkFieldVal = (float)$checkFieldVal;
                                        if ($checkFieldVal >= 8) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 7) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 6.5) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 6) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 5) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 4) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 3.5) {
                                            $clb = 4;
                                        }
                                        break;

                                    case 'qf_language_spouse_english_ielts_score_write':
                                        $checkFieldVal = (float)$checkFieldVal;
                                        if ($checkFieldVal >= 7.5) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 7) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 6.5) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 6) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 5.5) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 5) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 4) {
                                            $clb = 4;
                                        }
                                        break;

                                    case 'qf_language_spouse_english_ielts_score_listen':
                                        $checkFieldVal = (float)$checkFieldVal;
                                        if ($checkFieldVal >= 8.5) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 8) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 7.5) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 6) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 5.5) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 5) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 4.5) {
                                            $clb = 4;
                                        }
                                        break;

                                    case 'qf_language_spouse_english_ielts_score_speak':
                                        $checkFieldVal = (float)$checkFieldVal;
                                        if ($checkFieldVal >= 7.5) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 7) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 6.5) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 6) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 5.5) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 5) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 4) {
                                            $clb = 4;
                                        }
                                        break;

                                    default:
                                        break 3;
                                        break;
                                }

                                break;

                            case 'celpip':
                                if (!in_array($arrQFields[$checkFieldId]['q_field_unique_id'], array('qf_language_spouse_english_celpip_score_speak', 'qf_language_spouse_english_celpip_score_read', 'qf_language_spouse_english_celpip_score_write', 'qf_language_spouse_english_celpip_score_listen'))) {
                                    break 3;
                                }

                                if (array_key_exists($checkFieldVal, $arrOptions)) {
                                    if (preg_match('/^level_(\d+)$/', $arrOptions[$checkFieldVal], $regs)) {
                                        $clb = $regs[1];
                                    }
                                }
                                break;

                            case 'no':
                                if (!in_array($arrQFields[$checkFieldId]['q_field_unique_id'], array('qf_language_spouse_english_general_score_read', 'qf_language_spouse_english_general_score_write', 'qf_language_spouse_english_general_score_listen', 'qf_language_spouse_english_general_score_speak'))) {
                                    break 3;
                                }

                                if (array_key_exists($checkFieldVal, $arrOptions)) {
                                    switch ($arrOptions[$checkFieldVal]) {
                                        case 'native_proficiency':
                                            $clb = 12;
                                            break;

                                        case 'upper_intermediate':
                                            $clb = 8;
                                            break;

                                        case 'intermediate':
                                            $clb = 7;
                                            break;

                                        case 'lower_intermediate':
                                            $clb = 6;
                                            break;

                                        case 'basic':
                                            $clb = 3;
                                            break;

                                        default:
                                            break;
                                    }
                                }
                                break;

                            default:
                                break;
                        }

                        if(is_null($booSpouseFirstLangLevel4) || $booSpouseFirstLangLevel4) {
                            $booSpouseFirstLangLevel4 = $clb >= 4;
                        }
                        break;

                    /******* Spouse Second Language *******/
                    case 'qf_language_spouse_french_tef_score_speak':
                    case 'qf_language_spouse_french_tef_score_read':
                    case 'qf_language_spouse_french_tef_score_write':
                    case 'qf_language_spouse_french_tef_score_listen':
                    case 'qf_language_spouse_french_general_score_speak':
                    case 'qf_language_spouse_french_general_score_read':
                    case 'qf_language_spouse_french_general_score_write':
                    case 'qf_language_spouse_french_general_score_listen':
                        $clb = 0;
                        switch ($arrLangSelected['qf_language_spouse_french_done']) {
                            case 'yes':
                                switch ($arrQFields[$checkFieldId]['q_field_unique_id']) {
                                    case 'qf_language_spouse_french_tef_score_read':
                                        $checkFieldVal = (int)$checkFieldVal;
                                        if ($checkFieldVal >= 263) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 248) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 233) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 207) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 181) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 151) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 121) {
                                            $clb = 4;
                                        }
                                        break;

                                    case 'qf_language_spouse_french_tef_score_write':
                                        $checkFieldVal = (int)$checkFieldVal;
                                        if ($checkFieldVal >= 393) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 371) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 349) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 310) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 271) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 226) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 181) {
                                            $clb = 4;
                                        }
                                        break;

                                    case 'qf_language_spouse_french_tef_score_listen':
                                        $checkFieldVal = (int)$checkFieldVal;
                                        if ($checkFieldVal >= 316) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 298) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 280) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 249) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 217) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 181) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 145) {
                                            $clb = 4;
                                        }
                                        break;

                                    case 'qf_language_spouse_french_tef_score_speak':
                                        $checkFieldVal = (int)$checkFieldVal;
                                        if ($checkFieldVal >= 393) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 371) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 349) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 310) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 271) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 226) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 181) {
                                            $clb = 4;
                                        }
                                        break;

                                    default:
                                        break 3;
                                        break;
                                }
                                break;

                            case 'no':
                            case 'not_sure':
                                if (!in_array($arrQFields[$checkFieldId]['q_field_unique_id'], array('qf_language_spouse_french_general_score_read', 'qf_language_spouse_french_general_score_write', 'qf_language_spouse_french_general_score_listen', 'qf_language_spouse_french_general_score_speak'))) {
                                    break 3;
                                }

                                if (array_key_exists($checkFieldVal, $arrOptions)) {
                                    switch ($arrOptions[$checkFieldVal]) {
                                        case 'native_proficiency':
                                            $clb = 12;
                                            break;

                                        case 'upper_intermediate':
                                            $clb = 8;
                                            break;

                                        case 'intermediate':
                                            $clb = 7;
                                            break;

                                        case 'lower_intermediate':
                                            $clb = 6;
                                            break;

                                        case 'basic':
                                            $clb = 3;
                                            break;

                                        default:
                                            break;
                                    }
                                }
                                break;

                            default:
                                break;
                        }

                        if(is_null($booSpouseSecondLangLevel4) || $booSpouseSecondLangLevel4) {
                            $booSpouseSecondLangLevel4 = $clb >= 4;
                        }
                        break;


                    /******* Education *******/
                    case 'qf_education_level':
                        $points = 0;

                        if (array_key_exists($checkFieldVal, $arrOptions)) {
                            $arrEducationPoints = array(
                                array('ph_d',          25),
                                array('master',        23),
                                array('2_or_more',     22),
                                array('bachelor_4',    21),
                                array('bachelor_3',    21),
                                array('bachelor_2',    19),
                                array('bachelor_1',    15),
                                array('diploma_3',     21),
                                array('diploma_2',     19),
                                array('diploma_1',     15),
                                array('diploma_high',  5),
                                array('diploma_below', 0),
                            );

                            foreach ($arrEducationPoints as $arrPointDetails) {
                                if ($arrPointDetails[0] == $arrOptions[$checkFieldVal]) {
                                    $points = $arrPointDetails[1];
                                    break;
                                }
                            }
                        }

                        $arrSkilledWorkerPoints['education_level'] = $points;
                        break;

                    default:
                        break;
                }

            }
        } // foreach

        // Identify which language will be first/second
        $totalEnglishLangPoints = 0;
        if (isset($arrSkilledWorkerPoints['first_language_english']['details'])) {
            foreach ($arrSkilledWorkerPoints['first_language_english']['details'] as $langPoints) {
                if (empty($langPoints)) {
                    $totalEnglishLangPoints = 0;
                    break;
                } else {
                    $totalEnglishLangPoints += $langPoints;
                }
            }
        }

        $totalFrenchLangPoints = 0;
        if (isset($arrSkilledWorkerPoints['first_language_french']['details'])) {
            foreach ($arrSkilledWorkerPoints['first_language_french']['details'] as $langPoints) {
                if (empty($langPoints)) {
                    $totalFrenchLangPoints = 0;
                    break;
                } else {
                    $totalFrenchLangPoints += $langPoints;
                }
            }
        }

        if ($totalEnglishLangPoints >= $totalFrenchLangPoints) {
            $first = 'english';
            $arrSkilledWorkerPoints['first_language']['details'] = $arrSkilledWorkerPoints['first_language_english']['details'] ?? array();
            $arrSkilledWorkerPoints['second_language']['details'] = $arrSkilledWorkerPoints['second_language_french']['details'] ?? array();
        } else {
            $first = 'french';
            $arrSkilledWorkerPoints['first_language']['details'] = $arrSkilledWorkerPoints['first_language_french']['details'] ?? array();
            $arrSkilledWorkerPoints['second_language']['details'] = $arrSkilledWorkerPoints['second_language_english']['details'] ?? array();
        }

        //For habdling situation when points are the same
        if ($totalEnglishLangPoints == $totalFrenchLangPoints) {
            $first = '';
        }

        unset(
            $arrSkilledWorkerPoints['first_language_english'],
            $arrSkilledWorkerPoints['second_language_english'],
            $arrSkilledWorkerPoints['first_language_french'],
            $arrSkilledWorkerPoints['second_language_french']
        );

        // Check spouse's lang level
        $points = 0;
        if ($booSpouseFirstLangLevel4 || $booSpouseSecondLangLevel4) {
            $points = 5;
        }
        $arrSkilledWorkerPoints['adaptability']['details']['spouse_language_level'] = $points;

        // Adaptability: Prospect worked in Canada at least 1 year with skill level 0, A or B
        $arrToCheck = array(
            'more_than_1_year_and_less_2',
            'more_than_2_year_and_less_3',
            'more_than_3_year_and_less_4',
            'more_than_4_year_and_less_5',
            'more_than_5_year_and_less_6',
            'more_than_6_years'
        );

        $arrToCheckSkillLevel = array(
            '0',
            'A',
            'B'
        );

        $points = 0;
        foreach ($arrPreparedJobData as $arrJobData) {
            if ($arrJobData['location_canada'] && in_array($arrJobData['experience'], $arrToCheck) && in_array($arrJobData['noc_skill_level'], $arrToCheckSkillLevel)) {
                $points = 10;
                break;
            }
        }
        $arrSkilledWorkerPoints['adaptability']['details']['worked_in_canada'] = $points;

        // Adaptability: Spouse worked in Canada at least 1 year with skill level 0, A or B
        $points = 0;
        foreach ($arrPreparedSpouseJobData as $arrJobData) {
            if ($arrJobData['location_canada'] && in_array($arrJobData['experience'], $arrToCheck) && in_array($arrJobData['noc_skill_level'], $arrToCheckSkillLevel)) {
                $points = 5;
                break;
            }
        }
        $arrSkilledWorkerPoints['adaptability']['details']['spouse_worked_in_canada'] = $points;

        // Calculate job experience
        $maxExperienceYears = 0;
        foreach ($arrPreparedJobData as $arrJobData) {
            if (in_array($arrJobData['experience'], $arrToCheck) && in_array($arrJobData['noc_skill_level'], $arrToCheckSkillLevel)) {
                switch ($arrJobData['experience']) {
                    case 'more_than_1_year_and_less_2':
                        $maxExperienceYears += 1;
                        break;

                    case 'more_than_2_year_and_less_3':
                        $maxExperienceYears += 2;
                        break;

                    case 'more_than_3_year_and_less_4':
                        $maxExperienceYears += 3;
                        break;

                    case 'more_than_4_year_and_less_5':
                        $maxExperienceYears += 4;
                        break;

                    case 'more_than_5_year_and_less_6':
                        $maxExperienceYears += 5;
                        break;

                    case 'more_than_6_years':
                        $maxExperienceYears += 6;
                        break;

                    default:
                        break;
                }
            }
        }

        switch ($maxExperienceYears) {
            case 1:
                $jobExperiencePoints = 9;
                break;

            case 2:
            case 3:
                $jobExperiencePoints = 11;
                break;

            case 4:
            case 5:
                $jobExperiencePoints = 13;
                break;

            default:
                if ($maxExperienceYears >= 6) {
                    $jobExperiencePoints = 15;
                } else {
                    $jobExperiencePoints = 0;
                }
                break;
        }

        // Check if prospect is qualified under 'skilled worker' category
        $booQualified = false;
        $total        = $this->_calculateTotalPoints($arrSkilledWorkerPoints);
        if (!empty($jobExperiencePoints) && ($total + $jobExperiencePoints) >= $arrPointsToPass['skilled_worker']) {
            $booQualified = true;
        }


        $arrSkilledWorkerPoints['experience'] = $jobExperiencePoints;

        ksort($arrSkilledWorkerPoints);

        // Calculate Express Entry points
        $arrExpressEntryPoints          = array();
        $education                      = '';
        $booHasProspectJobOffer         = false;
        $booHasProspectPostSecondaries  = false;
        $booHasProspectTradeCertificate = false;

        if ($booHasFrench7OrHigh) {
            if ($booHasEnglish5OrHigh) {
                $points = 50;
            } else {
                $points = 25;
            }
            $arrExpressEntryPoints['additional_french_language'] = $points;
        }

        foreach ($arrReceivedFields as $checkFieldId => $arrFieldValues) {
            // Check each field value for correctness
            foreach ($arrFieldValues as $checkFieldVal) {
                if (!isset($arrQFields[$checkFieldId]['q_field_unique_id'])) {
                    continue;
                }

                switch ($arrQFields[$checkFieldId]['q_field_unique_id']) {

                    case 'qf_certificate_of_qualification':
                        if (array_key_exists($checkFieldVal, $arrOptions) && $arrOptions[$checkFieldVal] == 'yes') {
                            $booHasProspectTradeCertificate = true;
                        }
                        break;

                    /******* Post-secondary education in Canada *******/
                    case 'qf_study_previously_studied':
                        if (array_key_exists($checkFieldVal, $arrOptions) && $arrOptions[$checkFieldVal] == 'yes') {
                            $booHasProspectPostSecondaries = true;
                        }
                        break;

                    case 'qf_education_studied_in_canada_period':
                        $points = 0;
                        if (isset($arrOptions[$checkFieldVal])) {
                            if (in_array($arrOptions[$checkFieldVal], array('1_year', '2_years'))) {
                                $points = 15;
                            } elseif ($arrOptions[$checkFieldVal] == '3_years') {
                                $points = 30;
                            }
                        }

                        $arrExpressEntryPoints['post_secondary_education'] = $points;
                        break;

                    /******* Siblings in Canada *******/
                    case 'qf_family_relationship':
                        if (isset($arrOptions[$checkFieldVal]) && $arrOptions[$checkFieldVal] == 'sister_or_brother') {
                            $arrExpressEntryPoints['siblings_in_canada'] = 15;
                        }

                        break;

                    /******* Arranged employment *******/
                    case 'qf_work_offer_of_employment':
                        if (array_key_exists($checkFieldVal, $arrOptions) && $arrOptions[$checkFieldVal] == 'yes') {
                            $booHasProspectJobOffer = true;
                        }
                        break;

                    case 'qf_work_noc':
                        $points = 0;
                        if (isset($arrOptions[$checkFieldVal])) {
                            if ($arrOptions[$checkFieldVal] == 'noc_00') {
                                $points = 200;
                            } elseif ($arrOptions[$checkFieldVal] == 'noc_0_a_b') {
                                $points = 50;
                            }
                        }

                        $arrExpressEntryPoints['arranged_employment'] = $points;
                        break;

                    /******* Provincial nomination certificate *******/
                    case 'qf_nomination_certificate':
                        if (array_key_exists($checkFieldVal, $arrOptions) && $arrOptions[$checkFieldVal] == 'yes') {
                            $arrExpressEntryPoints['nomination_certificate'] = 600;
                        }
                        break;

                    /******* Age *******/
                    case 'qf_age':
                        $selAge = (int)$checkFieldVal;

                        $points = 0;
                        if ($booHasProspectSpouse) {
                            switch ($selAge) {
                                case 18:
                                    $points = 90;
                                    break;

                                case 19:
                                    $points = 95;
                                    break;

                                case 30:
                                    $points = 95;
                                    break;

                                case 31:
                                    $points = 90;
                                    break;

                                case 32:
                                    $points = 85;
                                    break;

                                case 33:
                                    $points = 80;
                                    break;

                                case 34:
                                    $points = 75;
                                    break;

                                case 35:
                                    $points = 70;
                                    break;

                                case 36:
                                    $points = 65;
                                    break;

                                case 37:
                                    $points = 60;
                                    break;

                                case 38:
                                    $points = 55;
                                    break;

                                case 39:
                                    $points = 50;
                                    break;

                                case 40:
                                    $points = 45;
                                    break;

                                case 41:
                                    $points = 35;
                                    break;

                                case 42:
                                    $points = 25;
                                    break;

                                case 43:
                                    $points = 15;
                                    break;

                                case 44:
                                    $points = 5;
                                    break;

                                default:
                                    if ($selAge >= 20 && $selAge <= 29) {
                                        $points = 100;
                                    }
                                    break;
                            }
                        } else {
                            switch ($selAge) {
                                case 18:
                                    $points = 99;
                                    break;

                                case 19:
                                    $points = 105;
                                    break;

                                case 30:
                                    $points = 105;
                                    break;

                                case 31:
                                    $points = 99;
                                    break;

                                case 32:
                                    $points = 94;
                                    break;

                                case 33:
                                    $points = 88;
                                    break;

                                case 34:
                                    $points = 83;
                                    break;

                                case 35:
                                    $points = 77;
                                    break;

                                case 36:
                                    $points = 72;
                                    break;

                                case 37:
                                    $points = 66;
                                    break;

                                case 38:
                                    $points = 61;
                                    break;

                                case 39:
                                    $points = 55;
                                    break;

                                case 40:
                                    $points = 50;
                                    break;

                                case 41:
                                    $points = 39;
                                    break;

                                case 42:
                                    $points = 28;
                                    break;

                                case 43:
                                    $points = 17;
                                    break;

                                case 44:
                                    $points = 6;
                                    break;

                                default:
                                    if ($selAge >= 20 && $selAge <= 29) {
                                        $points = 110;
                                    }
                                    break;
                            }
                        }

                        $arrExpressEntryPoints['age'] = $points;
                        break;


                    /******* Main Applicant English Language *******/
                    case 'qf_language_english_ielts_score_speak':
                    case 'qf_language_english_ielts_score_read':
                    case 'qf_language_english_ielts_score_write':
                    case 'qf_language_english_ielts_score_listen':
                    case 'qf_language_english_celpip_score_speak':
                    case 'qf_language_english_celpip_score_read':
                    case 'qf_language_english_celpip_score_write':
                    case 'qf_language_english_celpip_score_listen':
                    case 'qf_language_english_general_score_speak':
                    case 'qf_language_english_general_score_read':
                    case 'qf_language_english_general_score_write':
                    case 'qf_language_english_general_score_listen':
                        $clb = 0;
                        switch ($arrLangSelected['qf_language_english_done']) {
                            case 'ielts':
                                switch ($arrQFields[$checkFieldId]['q_field_unique_id']) {
                                    case 'qf_language_english_ielts_score_read':
                                        $checkFieldVal = (float)$checkFieldVal;
                                        if ($checkFieldVal >= 8) {
                                            $clb = 10;
                                        } elseif ($checkFieldVal >= 7) {
                                            $clb = 9;
                                        } elseif ($checkFieldVal >= 6.5) {
                                            $clb = 8;
                                        } elseif ($checkFieldVal >= 6) {
                                            $clb = 7;
                                        } elseif ($checkFieldVal >= 5) {
                                            $clb = 6;
                                        } elseif ($checkFieldVal >= 4) {
                                            $clb = 5;
                                        } elseif ($checkFieldVal >= 3.5) {
                                            $clb = 4;
                                        }
                                        break;

                                    case 'qf_language_english_ielts_score_write':
                                        $checkFieldVal = (float)$checkFieldVal;
                                        if ($checkFieldVal >= 7.5) {
                                            $clb = 10;
                                        } elseif ($checkFieldVal >= 7) {
                                            $clb = 9;
                                        } elseif ($checkFieldVal >= 6.5) {
                                            $clb = 8;
                                        } elseif ($checkFieldVal >= 6) {
                                            $clb = 7;
                                        } elseif ($checkFieldVal >= 5.5) {
                                            $clb = 6;
                                        } elseif ($checkFieldVal >= 5) {
                                            $clb = 5;
                                        } elseif ($checkFieldVal >= 4) {
                                            $clb = 4;
                                        }
                                        break;

                                    case 'qf_language_english_ielts_score_listen':
                                        $checkFieldVal = (float)$checkFieldVal;
                                        if ($checkFieldVal >= 8.5) {
                                            $clb = 10;
                                        } elseif ($checkFieldVal >= 8) {
                                            $clb = 9;
                                        } elseif ($checkFieldVal >= 7.5) {
                                            $clb = 8;
                                        } elseif ($checkFieldVal >= 6) {
                                            $clb = 7;
                                        } elseif ($checkFieldVal >= 5.5) {
                                            $clb = 6;
                                        } elseif ($checkFieldVal >= 5) {
                                            $clb = 5;
                                        } elseif ($checkFieldVal >= 4.5) {
                                            $clb = 4;
                                        }
                                        break;

                                    case 'qf_language_english_ielts_score_speak':
                                        $checkFieldVal = (float)$checkFieldVal;
                                        if ($checkFieldVal >= 7.5) {
                                            $clb = 10;
                                        } elseif ($checkFieldVal >= 7) {
                                            $clb = 9;
                                        } elseif ($checkFieldVal >= 6.5) {
                                            $clb = 8;
                                        } elseif ($checkFieldVal >= 6) {
                                            $clb = 7;
                                        } elseif ($checkFieldVal >= 5.5) {
                                            $clb = 6;
                                        } elseif ($checkFieldVal >= 5) {
                                            $clb = 5;
                                        } elseif ($checkFieldVal >= 4) {
                                            $clb = 4;
                                        }
                                        break;

                                    default:
                                        break 3;
                                        break;
                                }

                                break;

                            case 'celpip':
                                if (!in_array($arrQFields[$checkFieldId]['q_field_unique_id'], array('qf_language_english_celpip_score_speak', 'qf_language_english_celpip_score_read', 'qf_language_english_celpip_score_write', 'qf_language_english_celpip_score_listen'))) {
                                    break 3;
                                }

                                if (array_key_exists($checkFieldVal, $arrOptions)) {
                                    if (preg_match('/^level_(\d+)$/', $arrOptions[$checkFieldVal], $regs)) {
                                        $clb = $regs[1];
                                    }
                                }
                                break;

                            case 'no':
                                if (!in_array($arrQFields[$checkFieldId]['q_field_unique_id'], array('qf_language_english_general_score_read', 'qf_language_english_general_score_write', 'qf_language_english_general_score_listen', 'qf_language_english_general_score_speak'))) {
                                    break 3;
                                }

                                if (array_key_exists($checkFieldVal, $arrOptions)) {
                                    switch ($arrOptions[$checkFieldVal]) {
                                        case 'native_proficiency':
                                            $clb = 12;
                                            break;

                                        case 'upper_intermediate':
                                            $clb = 8;
                                            break;

                                        case 'intermediate':
                                            $clb = 7;
                                            break;

                                        case 'lower_intermediate':
                                            $clb = 6;
                                            break;

                                        case 'basic':
                                            $clb = 4;
                                            break;

                                        default:
                                            break;
                                    }
                                }
                                break;

                            default:
                                break;
                        }

                        switch ($arrQFields[$checkFieldId]['q_field_unique_id']) {
                            case 'qf_language_english_ielts_score_speak':
                            case 'qf_language_english_celpip_score_speak':
                            case 'qf_language_english_general_score_speak':
                                $key = 'speaking';
                                break;

                            case 'qf_language_english_ielts_score_read':
                            case 'qf_language_english_celpip_score_read':
                            case 'qf_language_english_general_score_read':
                                $key = 'reading';
                                break;

                            case 'qf_language_english_ielts_score_write':
                            case 'qf_language_english_celpip_score_write':
                            case 'qf_language_english_general_score_write':
                                $key = 'writing';
                                break;

                            case 'qf_language_english_ielts_score_listen':
                            case 'qf_language_english_celpip_score_listen':
                            case 'qf_language_english_general_score_listen':
                            default:
                                $key = 'listening';
                                break;
                        }

                        $secondLangPoints = 0;
                        if ($booHasProspectSpouse) {
                            if ($clb >= 10) {
                                $firstLangPoints  = 32;
                                $secondLangPoints = 6;
                            } elseif ($clb == 9) {
                                $firstLangPoints  = 29;
                                $secondLangPoints = 6;
                            } elseif ($clb == 8) {
                                $firstLangPoints  = 22;
                                $secondLangPoints = 3;
                            } elseif ($clb == 7) {
                                $firstLangPoints  = 16;
                                $secondLangPoints = 3;
                            } elseif ($clb == 6) {
                                $firstLangPoints  = 8;
                                $secondLangPoints = 1;
                            } elseif ($clb == 5) {
                                $firstLangPoints  = 6;
                                $secondLangPoints = 1;
                            } elseif ($clb == 4) {
                                $firstLangPoints = 6;
                            } else {
                                $firstLangPoints = 0;
                            }
                        } else {
                            if ($clb >= 10) {
                                $firstLangPoints  = 34;
                                $secondLangPoints = 6;
                            } elseif ($clb == 9) {
                                $firstLangPoints  = 31;
                                $secondLangPoints = 6;
                            } elseif ($clb == 8) {
                                $firstLangPoints  = 23;
                                $secondLangPoints = 3;
                            } elseif ($clb == 7) {
                                $firstLangPoints  = 17;
                                $secondLangPoints = 3;
                            } elseif ($clb == 6) {
                                $firstLangPoints  = 9;
                                $secondLangPoints = 1;
                            } elseif ($clb == 5) {
                                $firstLangPoints  = 6;
                                $secondLangPoints = 1;
                            } elseif ($clb == 4) {
                                $firstLangPoints = 6;
                            } else {
                                $firstLangPoints = 0;
                            }
                        }

                        if ($clb >= 10) {
                            $level = 10;
                        } elseif ($clb >= 5) {
                            $level = $clb;
                        } else {
                            $level = 0;
                        }

                        $arrExpressEntryPoints['first_language_english_main']['details'][$key]  = $firstLangPoints;
                        $arrExpressEntryPoints['first_language_english_main']['levels'][$key]   = $level;
                        $arrExpressEntryPoints['second_language_english_main']['details'][$key] = $secondLangPoints;
                    break;

                    /******* Main Applicant French Language *******/
                    case 'qf_language_french_tef_score_speak':
                    case 'qf_language_french_tef_score_read':
                    case 'qf_language_french_tef_score_write':
                    case 'qf_language_french_tef_score_listen':
                    case 'qf_language_french_general_score_speak':
                    case 'qf_language_french_general_score_read':
                    case 'qf_language_french_general_score_write':
                    case 'qf_language_french_general_score_listen':
                        $clb = 0;
                        switch ($arrLangSelected['qf_language_french_done']) {
                            case 'yes':
                                switch ($arrQFields[$checkFieldId]['q_field_unique_id']) {
                                    case 'qf_language_french_tef_score_read':
                                        $checkFieldVal = (int)$checkFieldVal;
                                        if ($checkFieldVal >= 263) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 248) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 233) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 207) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 181) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 151) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 121) {
                                            $clb = 4;
                                        }
                                        break;

                                    case 'qf_language_french_tef_score_write':
                                        $checkFieldVal = (int)$checkFieldVal;
                                        if ($checkFieldVal >= 393) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 371) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 349) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 310) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 271) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 226) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 181) {
                                            $clb = 4;
                                        }
                                        break;

                                    case 'qf_language_french_tef_score_listen':
                                        $checkFieldVal = (int)$checkFieldVal;
                                        if ($checkFieldVal >= 316) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 298) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 280) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 249) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 217) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 181) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 145) {
                                            $clb = 4;
                                        }
                                        break;

                                    case 'qf_language_french_tef_score_speak':
                                        $checkFieldVal = (int)$checkFieldVal;
                                        if ($checkFieldVal >= 393) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 371) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 349) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 310) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 271) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 226) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 181) {
                                            $clb = 4;
                                        }
                                        break;

                                    default:
                                        break 3;
                                        break;
                                }

                                break;

                            case 'no':
                            case 'not_sure':
                                if (!in_array($arrQFields[$checkFieldId]['q_field_unique_id'], array('qf_language_french_general_score_read', 'qf_language_french_general_score_write', 'qf_language_french_general_score_listen', 'qf_language_french_general_score_speak'))) {
                                    break 3;
                                }

                                if (array_key_exists($checkFieldVal, $arrOptions)) {
                                    switch ($arrOptions[$checkFieldVal]) {
                                        case 'native_proficiency':
                                            $clb = 12;
                                            break;

                                        case 'upper_intermediate':
                                            $clb = 8;
                                            break;

                                        case 'intermediate':
                                            $clb = 7;
                                            break;

                                        case 'lower_intermediate':
                                            $clb = 6;
                                            break;

                                        case 'basic':
                                            $clb = 4;
                                            break;

                                        default:
                                            break;
                                    }
                                }
                                break;

                            default:
                                break;
                        }

                        switch ($arrQFields[$checkFieldId]['q_field_unique_id']) {
                            case 'qf_language_french_tef_score_speak':
                            case 'qf_language_french_general_score_speak':
                                $key = 'speaking';
                                break;

                            case 'qf_language_french_tef_score_read':
                            case 'qf_language_french_general_score_read':
                                $key = 'reading';
                                break;

                            case 'qf_language_french_tef_score_write':
                            case 'qf_language_french_general_score_write':
                                $key = 'writing';
                                break;

                            case 'qf_language_french_tef_score_listen':
                            case 'qf_language_french_general_score_listen':
                            default:
                                $key = 'listening';
                                break;
                        }

                        $secondLangPoints = 0;
                        if ($booHasProspectSpouse) {
                            if ($clb >= 10) {
                                $firstLangPoints  = 32;
                                $secondLangPoints = 6;
                            } elseif ($clb == 9) {
                                $firstLangPoints  = 29;
                                $secondLangPoints = 6;
                            } elseif ($clb == 8) {
                                $firstLangPoints  = 22;
                                $secondLangPoints = 3;
                            } elseif ($clb == 7) {
                                $firstLangPoints  = 16;
                                $secondLangPoints = 3;
                            } elseif ($clb == 6) {
                                $firstLangPoints  = 8;
                                $secondLangPoints = 1;
                            } elseif ($clb == 5) {
                                $firstLangPoints  = 6;
                                $secondLangPoints = 1;
                            } elseif ($clb == 4) {
                                $firstLangPoints = 6;
                            } else {
                                $firstLangPoints = 0;
                            }
                        } else {
                            if ($clb >= 10) {
                                $firstLangPoints  = 34;
                                $secondLangPoints = 6;
                            } elseif ($clb == 9) {
                                $firstLangPoints  = 31;
                                $secondLangPoints = 6;
                            } elseif ($clb == 8) {
                                $firstLangPoints  = 23;
                                $secondLangPoints = 3;
                            } elseif ($clb == 7) {
                                $firstLangPoints  = 17;
                                $secondLangPoints = 3;
                            } elseif ($clb == 6) {
                                $firstLangPoints  = 9;
                                $secondLangPoints = 1;
                            } elseif ($clb == 5) {
                                $firstLangPoints  = 6;
                                $secondLangPoints = 1;
                            } elseif ($clb == 4) {
                                $firstLangPoints = 6;
                            } else {
                                $firstLangPoints = 0;
                            }
                        }

                        if ($clb >= 10) {
                            $level = 10;
                        } elseif ($clb >= 5) {
                            $level = $clb;
                        } else {
                            $level = 0;
                        }

                        $arrExpressEntryPoints['first_language_french_main']['details'][$key]  = $firstLangPoints;
                        $arrExpressEntryPoints['first_language_french_main']['levels'][$key]   = $level;
                        $arrExpressEntryPoints['second_language_french_main']['details'][$key] = $secondLangPoints;
                        break;

                    /******* Spouse English *******/
                    case 'qf_language_spouse_english_ielts_score_speak':
                    case 'qf_language_spouse_english_ielts_score_read':
                    case 'qf_language_spouse_english_ielts_score_write':
                    case 'qf_language_spouse_english_ielts_score_listen':
                    case 'qf_language_spouse_english_celpip_score_speak':
                    case 'qf_language_spouse_english_celpip_score_read':
                    case 'qf_language_spouse_english_celpip_score_write':
                    case 'qf_language_spouse_english_celpip_score_listen':
                    case 'qf_language_spouse_english_general_score_speak':
                    case 'qf_language_spouse_english_general_score_read':
                    case 'qf_language_spouse_english_general_score_write':
                    case 'qf_language_spouse_english_general_score_listen':
                        $clb = 0;
                        switch ($arrLangSelected['qf_language_spouse_english_done']) {
                            case 'ielts':
                                switch ($arrQFields[$checkFieldId]['q_field_unique_id']) {
                                    case 'qf_language_spouse_english_ielts_score_read':
                                        $checkFieldVal = (float)$checkFieldVal;
                                        if ($checkFieldVal >= 8) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 7) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 6.5) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 6) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 5) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 4) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 3.5) {
                                            $clb = 4;
                                        }
                                        break;

                                    case 'qf_language_spouse_english_ielts_score_write':
                                        $checkFieldVal = (float)$checkFieldVal;
                                        if ($checkFieldVal >= 7.5) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 7) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 6.5) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 6) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 5.5) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 5) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 4) {
                                            $clb = 4;
                                        }
                                        break;

                                    case 'qf_language_spouse_english_ielts_score_listen':
                                        $checkFieldVal = (float)$checkFieldVal;
                                        if ($checkFieldVal >= 8.5) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 8) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 7.5) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 6) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 5.5) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 5) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 4.5) {
                                            $clb = 4;
                                        }
                                        break;

                                    case 'qf_language_spouse_english_ielts_score_speak':
                                        $checkFieldVal = (float)$checkFieldVal;
                                        if ($checkFieldVal >= 7.5) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 7) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 6.5) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 6) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 5.5) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 5) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 4) {
                                            $clb = 4;
                                        }
                                        break;

                                    default:
                                        break 3;
                                        break;
                                }

                                break;

                            case 'celpip':
                                if (!in_array($arrQFields[$checkFieldId]['q_field_unique_id'], array('qf_language_spouse_english_celpip_score_speak', 'qf_language_spouse_english_celpip_score_read', 'qf_language_spouse_english_celpip_score_write', 'qf_language_spouse_english_celpip_score_listen'))) {
                                    break 3;
                                }

                                if (array_key_exists($checkFieldVal, $arrOptions)) {
                                    if (preg_match('/^level_(\d+)$/', $arrOptions[$checkFieldVal], $regs)) {
                                        $clb = $regs[1];
                                    }
                                }
                                break;

                            case 'no':
                                if (!in_array($arrQFields[$checkFieldId]['q_field_unique_id'], array('qf_language_spouse_english_general_score_read', 'qf_language_spouse_english_general_score_write', 'qf_language_spouse_english_general_score_listen', 'qf_language_spouse_english_general_score_speak'))) {
                                    break 3;
                                }

                                if (array_key_exists($checkFieldVal, $arrOptions)) {
                                    switch ($arrOptions[$checkFieldVal]) {
                                        case 'native_proficiency':
                                            $clb = 12;
                                            break;

                                        case 'upper_intermediate':
                                            $clb = 8;
                                            break;

                                        case 'intermediate':
                                            $clb = 7;
                                            break;

                                        case 'lower_intermediate':
                                            $clb = 6;
                                            break;

                                        case 'basic':
                                            $clb = 4;
                                            break;

                                        default:
                                            break;
                                    }
                                }
                                break;

                            default:
                                break;
                        }

                        switch ($arrQFields[$checkFieldId]['q_field_unique_id']) {
                            case 'qf_language_spouse_english_ielts_score_speak':
                            case 'qf_language_spouse_english_celpip_score_speak':
                            case 'qf_language_spouse_english_general_score_speak':
                                $key = 'speaking';
                                break;

                            case 'qf_language_spouse_english_ielts_score_read':
                            case 'qf_language_spouse_english_celpip_score_read':
                            case 'qf_language_spouse_english_general_score_read':
                                $key = 'reading';
                                break;

                            case 'qf_language_spouse_english_ielts_score_write':
                            case 'qf_language_spouse_english_celpip_score_write':
                            case 'qf_language_spouse_english_general_score_write':
                                $key = 'writing';
                                break;

                            case 'qf_language_spouse_english_ielts_score_listen':
                            case 'qf_language_spouse_english_celpip_score_listen':
                            case 'qf_language_spouse_english_general_score_listen':
                            default:
                                $key = 'listening';
                                break;
                        }

                        if ($clb >= 9) {
                            $firstLangPoints = 5;
                        } elseif($clb >= 7) {
                            $firstLangPoints = 3;
                        } elseif($clb >= 5) {
                            $firstLangPoints = 1;
                        } else {
                            $firstLangPoints = 0;
                        }

                        $arrExpressEntryPoints['first_language_english_spouse']['details'][$key] = $firstLangPoints;
                        break;

                    /******* Spouse French *******/
                    case 'qf_language_spouse_french_tef_score_speak':
                    case 'qf_language_spouse_french_tef_score_read':
                    case 'qf_language_spouse_french_tef_score_write':
                    case 'qf_language_spouse_french_tef_score_listen':
                    case 'qf_language_spouse_french_general_score_speak':
                    case 'qf_language_spouse_french_general_score_read':
                    case 'qf_language_spouse_french_general_score_write':
                    case 'qf_language_spouse_french_general_score_listen':
                        $clb = 0;
                        switch ($arrLangSelected['qf_language_spouse_french_done']) {
                            case 'yes':
                                switch ($arrQFields[$checkFieldId]['q_field_unique_id']) {
                                    case 'qf_language_spouse_french_tef_score_read':
                                        $checkFieldVal = (int)$checkFieldVal;
                                        if ($checkFieldVal >= 263) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 248) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 233) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 207) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 181) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 151) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 121) {
                                            $clb = 4;
                                        }
                                        break;

                                    case 'qf_language_spouse_french_tef_score_write':
                                        $checkFieldVal = (int)$checkFieldVal;
                                        if ($checkFieldVal >= 393) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 371) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 349) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 310) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 271) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 226) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 181) {
                                            $clb = 4;
                                        }
                                        break;

                                    case 'qf_language_spouse_french_tef_score_listen':
                                        $checkFieldVal = (int)$checkFieldVal;
                                        if ($checkFieldVal >= 316) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 298) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 280) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 249) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 217) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 181) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 145) {
                                            $clb = 4;
                                        }
                                        break;

                                    case 'qf_language_spouse_french_tef_score_speak':
                                        $checkFieldVal = (int)$checkFieldVal;
                                        if ($checkFieldVal >= 393) {
                                            $clb = 10;
                                        } elseif($checkFieldVal >= 371) {
                                            $clb = 9;
                                        } elseif($checkFieldVal >= 349) {
                                            $clb = 8;
                                        } elseif($checkFieldVal >= 310) {
                                            $clb = 7;
                                        } elseif($checkFieldVal >= 271) {
                                            $clb = 6;
                                        } elseif($checkFieldVal >= 226) {
                                            $clb = 5;
                                        } elseif($checkFieldVal >= 181) {
                                            $clb = 4;
                                        }
                                        break;

                                    default:
                                        break 3;
                                        break;
                                }
                                break;

                            case 'no':
                            case 'not_sure':
                                if (!in_array($arrQFields[$checkFieldId]['q_field_unique_id'], array('qf_language_spouse_french_general_score_read', 'qf_language_spouse_french_general_score_write', 'qf_language_spouse_french_general_score_listen', 'qf_language_spouse_french_general_score_speak'))) {
                                    break 3;
                                }

                                if (array_key_exists($checkFieldVal, $arrOptions)) {
                                    switch ($arrOptions[$checkFieldVal]) {
                                        case 'native_proficiency':
                                            $clb = 12;
                                            break;

                                        case 'upper_intermediate':
                                            $clb = 8;
                                            break;

                                        case 'intermediate':
                                            $clb = 7;
                                            break;

                                        case 'lower_intermediate':
                                            $clb = 6;
                                            break;

                                        case 'basic':
                                            $clb = 4;
                                            break;

                                        default:
                                            break;
                                    }
                                }
                                break;

                            default:
                                break;
                        }

                        switch ($arrQFields[$checkFieldId]['q_field_unique_id']) {
                            case 'qf_language_spouse_french_tef_score_speak':
                            case 'qf_language_spouse_french_general_score_speak':
                                $key = 'speaking';
                                break;

                            case 'qf_language_spouse_french_tef_score_read':
                            case 'qf_language_spouse_french_general_score_read':
                                $key = 'reading';
                                break;

                            case 'qf_language_spouse_french_tef_score_write':
                            case 'qf_language_spouse_french_general_score_write':
                                $key = 'writing';
                                break;

                            case 'qf_language_spouse_french_tef_score_listen':
                            case 'qf_language_spouse_french_general_score_listen':
                            default:
                                $key = 'listening';
                                break;
                        }

                        if ($clb >= 9) {
                            $firstLangPoints = 5;
                        } elseif($clb >= 7) {
                            $firstLangPoints = 3;
                        } elseif($clb >= 5) {
                            $firstLangPoints = 1;
                        } else {
                            $firstLangPoints = 0;
                        }

                        $arrExpressEntryPoints['first_language_french_spouse']['details'][$key] = $firstLangPoints;
                        break;

                    /******* Education *******/
                    case 'qf_education_level':
                        $points = 0;

                        if (array_key_exists($checkFieldVal, $arrOptions)) {
                            if ($booHasProspectSpouse) {
                                $arrEducationPoints = array(
                                    array('ph_d',          140, '2_or_more'),
                                    array('master',        126, '2_or_more'),
                                    array('2_or_more',     119, '2_or_more'),
                                    array('bachelor_4',    112, 'post_secondary'),
                                    array('bachelor_3',    112, 'post_secondary'),
                                    array('bachelor_2',    91,  'post_secondary'),
                                    array('bachelor_1',    84,  'post_secondary'),
                                    array('diploma_3',     112, 'post_secondary'),
                                    array('diploma_2',     91,  'post_secondary'),
                                    array('diploma_1',     84,  'post_secondary'),
                                    array('diploma_high',  28,  'no_post_secondary'),
                                    array('diploma_below', 0,   'no_post_secondary'),
                                );
                            } else {
                                $arrEducationPoints = array(
                                    array('ph_d',          150, '2_or_more'),
                                    array('master',        135, '2_or_more'),
                                    array('2_or_more',     128, '2_or_more'),
                                    array('bachelor_4',    120, 'post_secondary'),
                                    array('bachelor_3',    120, 'post_secondary'),
                                    array('bachelor_2',    98,  'post_secondary'),
                                    array('bachelor_1',    90,  'post_secondary'),
                                    array('diploma_3',     120, 'post_secondary'),
                                    array('diploma_2',     98,  'post_secondary'),
                                    array('diploma_1',     90,  'post_secondary'),
                                    array('diploma_high',  30,  'no_post_secondary'),
                                    array('diploma_below', 0,   'no_post_secondary'),
                                );
                            }

                            foreach ($arrEducationPoints as $arrPointDetails) {
                                if ($arrPointDetails[0] == $arrOptions[$checkFieldVal]) {
                                    $points = $arrPointDetails[1];
                                    $education = $arrPointDetails[2];
                                    break;
                                }
                            }
                        }

                        $arrExpressEntryPoints['education_level_main'] = $points;
                        break;

                    case 'qf_education_spouse_level':
                        $points = 0;

                        if (array_key_exists($checkFieldVal, $arrOptions)) {
                            $arrEducationSpousePoints = array(
                                array('ph_d',          10),
                                array('master',        10),
                                array('2_or_more',     9),
                                array('bachelor_4',    8),
                                array('bachelor_3',    8),
                                array('bachelor_2',    7),
                                array('bachelor_1',    6),
                                array('diploma_3',     8),
                                array('diploma_2',     7),
                                array('diploma_1',     6),
                                array('diploma_high',  2),
                                array('diploma_below', 0),
                            );

                            foreach ($arrEducationSpousePoints as $arrPointDetails) {
                                if ($arrPointDetails[0] == $arrOptions[$checkFieldVal]) {
                                    $points = $arrPointDetails[1];
                                    break;
                                }
                            }
                        }
                        $arrExpressEntryPoints['education_level_spouse'] = $points;
                        break;

                    default:
                        break;
                }
            }
        } // foreach

        // Identify which language will be first/second
        $totalEnglishLangPoints = 0;
        if (isset($arrExpressEntryPoints['first_language_english_main']['details']) && is_array($arrExpressEntryPoints['first_language_english_main']['details'])) {
            foreach ($arrExpressEntryPoints['first_language_english_main']['details'] as $langPoints) {
                if (empty($langPoints)) {
                    $totalEnglishLangPoints = 0;
                    break;
                } else {
                    $totalEnglishLangPoints += $langPoints;
                }
            }
        }

        $totalFrenchLangPoints = 0;
        if (isset($arrExpressEntryPoints['first_language_french_main']['details']) && is_array($arrExpressEntryPoints['first_language_french_main']['details'])) {
            foreach ($arrExpressEntryPoints['first_language_french_main']['details'] as $langPoints) {
                if (empty($langPoints)) {
                    $totalFrenchLangPoints = 0;
                    break;
                } else {
                    $totalFrenchLangPoints += $langPoints;
                }
            }
        }

        $totalEnglishLangPointsSpouse = 0;
        if (array_key_exists('first_language_english_spouse', $arrExpressEntryPoints)) {
            foreach ($arrExpressEntryPoints['first_language_english_spouse']['details'] as $langPoints) {
                if (empty($langPoints)) {
                    $totalEnglishLangPointsSpouse = 0;
                    break;
                } else {
                    $totalEnglishLangPointsSpouse += $langPoints;
                }
            }
        }

        $totalFrenchLangPointsSpouse = 0;
        if (array_key_exists('first_language_french_spouse', $arrExpressEntryPoints)) {
            foreach ($arrExpressEntryPoints['first_language_french_spouse']['details'] as $langPoints) {
                if (empty($langPoints)) {
                    $totalFrenchLangPointsSpouse = 0;
                    break;
                } else {
                    $totalFrenchLangPointsSpouse += $langPoints;
                }
            }
            if ($totalEnglishLangPointsSpouse >= $totalFrenchLangPointsSpouse) {
                $arrExpressEntryPoints['first_language_spouse']['details'] = $arrExpressEntryPoints['first_language_english_spouse']['details'];
            } else {
                $arrExpressEntryPoints['first_language_spouse']['details'] = $arrExpressEntryPoints['first_language_french_spouse']['details'];
            }
        }

        if ($totalEnglishLangPoints >= $totalFrenchLangPoints) {
            $arrExpressEntryPoints['first_language_main'] = $arrExpressEntryPoints['first_language_english_main'] ?? 0;
            $arrExpressEntryPoints['second_language_main'] = $arrExpressEntryPoints['second_language_french_main'] ?? 0;
        } else {
            $arrExpressEntryPoints['first_language_main'] = $arrExpressEntryPoints['first_language_french_main'] ?? 0;
            $arrExpressEntryPoints['second_language_main'] = $arrExpressEntryPoints['second_language_english_main'] ?? 0;
        }

        unset(
            $arrExpressEntryPoints['first_language_english_main'],
            $arrExpressEntryPoints['second_language_english_main'],
            $arrExpressEntryPoints['first_language_french_main'],
            $arrExpressEntryPoints['second_language_french_main'],
            $arrExpressEntryPoints['first_language_english_spouse'],
            $arrExpressEntryPoints['first_language_french_spouse']
        );

        $yearsNumberCanadianWork    = 0;
        $yearsNumberNonCanadianWork = 0;

        foreach ($arrPreparedJobData as $arrJobData) {
            if ($arrJobData['noc_admissible'] == 'no') {
                continue;
            }

            if ($arrJobData['location_canada']) {
                switch ($arrJobData['experience']) {
                    case 'less_3':
                    case 'more_than_3_and_less_6':
                    case 'more_than_6_and_less_9':
                    case 'more_than_9_and_less_12':
                        break;

                    case 'more_than_1_year_and_less_2':
                        $yearsNumberCanadianWork += 1;
                        break;

                    case 'more_than_2_year_and_less_3':
                        $yearsNumberCanadianWork += 2;
                        break;

                    case 'more_than_3_year_and_less_4':
                        $yearsNumberCanadianWork += 3;
                        break;

                    case 'more_than_4_year_and_less_5':
                        $yearsNumberCanadianWork += 4;
                        break;

                    default:
                        $yearsNumberCanadianWork += 5;
                        break;
                }
            } else {
                switch ($arrJobData['experience']) {
                    case 'less_3':
                    case 'more_than_3_and_less_6':
                    case 'more_than_6_and_less_9':
                    case 'more_than_9_and_less_12':
                        break;

                    case 'more_than_1_year_and_less_2':
                        $yearsNumberNonCanadianWork += 1;
                        break;

                    case 'more_than_2_year_and_less_3':
                        $yearsNumberNonCanadianWork += 2;
                        break;

                    case 'more_than_3_year_and_less_4':
                        $yearsNumberNonCanadianWork += 3;
                        break;

                    case 'more_than_4_year_and_less_5':
                        $yearsNumberNonCanadianWork += 4;
                        break;

                    default:
                        $yearsNumberNonCanadianWork += 5;
                        break;
                }
            }


        }

        if (!$booHasProspectSpouse) {
            switch ($yearsNumberCanadianWork) {
                case 0:
                    $experiencePoints = 0;
                    break;

                case 1:
                    $experiencePoints = 40;
                    break;

                case 2:
                    $experiencePoints = 53;
                    break;

                case 3:
                    $experiencePoints = 64;
                    break;

                case 4:
                    $experiencePoints = 72;
                    break;

                default:
                    $experiencePoints = 80;
                    break;
            }
        } else {
            switch ($yearsNumberCanadianWork) {
                case 0:
                    $experiencePoints = 0;
                    break;

                case 1:
                    $experiencePoints = 35;
                    break;

                case 2:
                    $experiencePoints = 46;
                    break;

                case 3:
                    $experiencePoints = 56;
                    break;

                case 4:
                    $experiencePoints = 63;
                    break;

                default:
                    $experiencePoints = 70;
                    break;
            }
        }

        $arrExpressEntryPoints['canadian_work_experience'] = $experiencePoints;

        $yearsNumberCanadianWorkSpouse = 0;

        if ($booHasProspectSpouse) {
            foreach ($arrPreparedSpouseJobData as $arrJobData) {

                if ($arrJobData['noc_admissible'] == 'no') {
                    continue;
                }

                if (!$arrJobData['location_canada']) {
                    continue;
                }

                switch ($arrJobData['experience']) {
                    case 'less_3':
                    case 'more_than_3_and_less_6':
                    case 'more_than_6_and_less_9':
                    case 'more_than_9_and_less_12':
                        break;

                    case 'more_than_1_year_and_less_2':
                        $yearsNumberCanadianWorkSpouse += 1;
                        break;

                    case 'more_than_2_year_and_less_3':
                        $yearsNumberCanadianWorkSpouse += 2;
                        break;

                    case 'more_than_3_year_and_less_4':
                        $yearsNumberCanadianWorkSpouse += 3;
                        break;

                    case 'more_than_4_year_and_less_5':
                        $yearsNumberCanadianWorkSpouse += 4;
                        break;

                    default:
                        $yearsNumberCanadianWorkSpouse += 5;
                        break;
                }
            }

            switch ($yearsNumberCanadianWorkSpouse) {
                case 0:
                    $experiencePoints = 0;
                    break;

                case 1:
                    $experiencePoints = 5;
                    break;

                case 2:
                    $experiencePoints = 7;
                    break;

                case 3:
                    $experiencePoints = 8;
                    break;

                case 4:
                    $experiencePoints = 9;
                    break;

                default:
                    $experiencePoints = 10;
                    break;
            }
            $arrExpressEntryPoints['canadian_work_experience_spouse'] = $experiencePoints;
        }

        $booLanguageLowLevel    = true; // CLB 5 or higher on all language abilities, with at least one CLB 5 or 6
        $booLanguageMediumLevel = true; // CLB 7 or higher on all language abilities, with at least one of these CLB 7 or 8
        $booLanguageHighLevel   = true; // CLB 9 or higher for all language abilities
        if (isset($arrExpressEntryPoints['first_language_main']['levels']) && is_array($arrExpressEntryPoints['first_language_main']['levels'])) {
            foreach ($arrExpressEntryPoints['first_language_main']['levels'] as $langLevel) {
                if ($langLevel < 5) {
                    $booLanguageLowLevel    = false;
                    $booLanguageMediumLevel = false;
                    $booLanguageHighLevel   = false;
                    break;
                } elseif ($langLevel < 7) {
                    $booLanguageMediumLevel = false;
                    $booLanguageHighLevel   = false;
                } elseif ($langLevel < 9) {
                    $booLanguageHighLevel = false;
                }
            }
        }

        if ($booLanguageHighLevel) {
            $booLanguageLowLevel    = false;
            $booLanguageMediumLevel = false;
        } elseif ($booLanguageMediumLevel) {
            $booLanguageLowLevel = false;
        }

        $educationLanguageAbilityPoints    = 0;
        $educationCanadianExperiencePoints = 0;
        switch ($education) {
            case 'post_secondary':
                if ($booLanguageMediumLevel) {
                    $educationLanguageAbilityPoints = 13;
                } elseif ($booLanguageHighLevel) {
                    $educationLanguageAbilityPoints = 25;
                }
                if ($yearsNumberCanadianWork == 1) {
                    $educationCanadianExperiencePoints = 13;
                } elseif ($yearsNumberCanadianWork > 1) {
                    $educationCanadianExperiencePoints = 25;
                }
                break;

            case '2_or_more':
                if ($booLanguageMediumLevel) {
                    $educationLanguageAbilityPoints = 25;
                } elseif ($booLanguageHighLevel) {
                    $educationLanguageAbilityPoints = 50;
                }
                if ($yearsNumberCanadianWork == 1) {
                    $educationCanadianExperiencePoints = 25;
                } elseif ($yearsNumberCanadianWork > 1) {
                    $educationCanadianExperiencePoints = 50;
                }
                break;
        }
        $arrExpressEntryPoints['education_language_ability']    = $educationLanguageAbilityPoints;
        $arrExpressEntryPoints['education_canadian_experience'] = $educationCanadianExperiencePoints;

        $languageAbilityNonCanadianExperiencePoints = 0;
        $canadianNonCanadianExperiencePoints        = 0;

        switch ($yearsNumberNonCanadianWork) {
            case 0:
                break;

            case 1:
            case 2:
                if ($booLanguageMediumLevel) {
                    $languageAbilityNonCanadianExperiencePoints = 13;
                } elseif ($booLanguageHighLevel) {
                    $languageAbilityNonCanadianExperiencePoints = 25;
                }
                if ($yearsNumberCanadianWork == 1) {
                    $canadianNonCanadianExperiencePoints = 13;
                } elseif ($yearsNumberCanadianWork > 1) {
                    $canadianNonCanadianExperiencePoints = 25;
                }
                break;

            default:
                if ($booLanguageMediumLevel) {
                    $languageAbilityNonCanadianExperiencePoints = 25;
                } elseif ($booLanguageHighLevel) {
                    $languageAbilityNonCanadianExperiencePoints = 50;
                }
                if ($yearsNumberCanadianWork == 1) {
                    $canadianNonCanadianExperiencePoints = 25;
                } elseif ($yearsNumberCanadianWork > 1) {
                    $canadianNonCanadianExperiencePoints = 50;
                }
                break;
        }

        $arrExpressEntryPoints['language_ability_non_canadian_experience'] = $languageAbilityNonCanadianExperiencePoints;
        $arrExpressEntryPoints['canadian_non_canadian_experience']         = $canadianNonCanadianExperiencePoints;

        if ($booHasProspectTradeCertificate) {
            if ($booLanguageLowLevel) {
                $arrExpressEntryPoints['trade_certificate_language'] = 25;
            } elseif ($booLanguageMediumLevel || $booLanguageHighLevel) {
                $arrExpressEntryPoints['trade_certificate_language'] = 50;
            }
        }

        if (!$booHasProspectJobOffer) {
            unset($arrExpressEntryPoints['arranged_employment']);
        }

        if (!$booHasProspectPostSecondaries) {
            unset($arrExpressEntryPoints['post_secondary_education']);
        }

        ksort($arrExpressEntryPoints);

        // Must be so because $arrSkilledWorkerPoints is returned by pointer
        $skilledWorkerTotal = $this->_calculateTotalPoints($arrSkilledWorkerPoints, true);
        if (empty($jobExperiencePoints)) {
            $skilledWorkerTotal = 0;
        }

        $arrData = array(
            'skilled_worker' => array(
                'global' => array(
                    'total'     => $skilledWorkerTotal,
                    'pass_mark' => $arrPointsToPass['skilled_worker'],
                    'qualified' => $booQualified
                ),
                'data'   => $arrSkilledWorkerPoints
            ),
            'express_entry'  => array(
                'global' => array(
                    'total' => $this->_calculateTotalExpressEntryPoints($arrExpressEntryPoints, true)
                ),
                'data'   => $arrExpressEntryPoints,
            )
        );

        if ($booReturnLanguageAssessments) {
            $arrData['first_language'] = $first;
            $arrData['boo_show_alert'] = ($booShowEnglishAlert && $first == 'english') || ($booShowFrenchAlert && $first == 'french');
        }

        return $arrData;
    }


    /**
     * Calculate total points for each section with subsections
     * Also check maximum for each section
     *
     * @param $arrSkilledWorkerPoints
     * @param bool $booSaveTotals
     * @return int total calculated points count
     */
    private function _calculateTotalPoints(&$arrSkilledWorkerPoints, $booSaveTotals = false)
    {
        $globalTotal = 0;
        foreach ($arrSkilledWorkerPoints as $id => &$val) {
            if (is_array($val) && array_key_exists('details', $val)) {
                $total = 0;

                $booLanguageSectionHasZero = false;
                $booLanguageSection        = in_array($id, array('first_language', 'second_language'));
                if (isset($val['details']) && is_array($val['details'])) {
                    foreach ($val['details'] as $point) {
                        if (empty($point)) {
                            $booLanguageSectionHasZero = true;
                        }
                        $total += $point;
                    }
                }

                // Adaptability (max 10 points)
                if ($id == 'adaptability' && $total > 10) {
                    $total = 10;
                }


                if ($booLanguageSection && $booLanguageSectionHasZero) {
                    $total = 0;
                }

                // At the end of process we need save total for groups
                if ($booSaveTotals) {
                    $val['total'] = $total;
                }

                $globalTotal += $total;
            } else {
                $globalTotal += $val;
            }
        }

        return $globalTotal;
    }

    /**
     * Calculate total points for each section with subsections for Express Entry
     * Also check maximum for each section
     *
     * @param array $arrExpressEntryPoints
     * @param bool $booSaveTotals
     * @return int total calculated points count
     */
    private function _calculateTotalExpressEntryPoints(&$arrExpressEntryPoints, $booSaveTotals = false)
    {
        $globalTotal               = 0;
        $skillTransferabilityTotal = 0;
        foreach ($arrExpressEntryPoints as $id => &$val) {
            if (is_array($val) && array_key_exists('details', $val)) {
                $total = 0;

                $booLanguageSectionHasZero = false;
                $booLanguageSection        = in_array($id, array('first_language_main', 'second_language_main', 'first_language_spouse'));

                foreach ($val['details'] as $point) {
                    if (empty($point)) {
                        $booLanguageSectionHasZero = true;
                    }
                    $total += $point;
                }

                if ($booLanguageSection && $booLanguageSectionHasZero) {
                    $total = 0;
                }

                // At the end of process we need save total for groups
                if ($booSaveTotals) {
                    $val['total'] = $total;
                }

                $globalTotal += $total;
            } else {
                switch ($id) {
                    case 'education_language_ability':
                    case 'education_canadian_experience':
                    case 'language_ability_non_canadian_experience':
                    case 'canadian_non_canadian_experience':
                    case 'trade_certificate_language':
                        $skillTransferabilityTotal += $val;
                        break;
                    default:
                        $globalTotal += $val;
                        break;
                }
            }
        }

        // Make sure that sum of "Additional points" isn't more than 600
        $additionalPointsSum = 0;

        $arrAdditionalPointsFields = array(
            'post_secondary_education',
            'arranged_employment',
            'nomination_certificate',
            'siblings_in_canada',
            'additional_french_language',
        );
        foreach ($arrAdditionalPointsFields as $arrAdditionalPointsField) {
            $additionalPointsSum += $arrExpressEntryPoints[$arrAdditionalPointsField] ?? 0;
        }

        if ($additionalPointsSum > 600) {
            $globalTotal -= $additionalPointsSum - 600;
        }

        // Maximum from these 2 groups can be 50 points
        if (array_key_exists('education_language_ability', $arrExpressEntryPoints) && array_key_exists('education_canadian_experience', $arrExpressEntryPoints)) {
            $sum = $arrExpressEntryPoints['education_language_ability'] + $arrExpressEntryPoints['education_canadian_experience'];
            if ($sum > 50) {
                $skillTransferabilityTotal -= $sum - 50;
            }
        }

        // Maximum from these 2 groups can be 50 points
        if (array_key_exists('language_ability_non_canadian_experience', $arrExpressEntryPoints) && array_key_exists('canadian_non_canadian_experience', $arrExpressEntryPoints)) {
            $sum = $arrExpressEntryPoints['language_ability_non_canadian_experience'] + $arrExpressEntryPoints['canadian_non_canadian_experience'];
            if ($sum > 50) {
                $skillTransferabilityTotal -= $sum - 50;
            }
        }

        if ($skillTransferabilityTotal > 100) {
            $skillTransferabilityTotal = 100;
        }
        $globalTotal += $skillTransferabilityTotal;

        return $globalTotal;
    }
}