<?php
namespace Clients\Service;

use Exception;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class ClientsVisaSurvey extends BaseService
{
    /**
     * Load list of visa records for the member
     *
     * @param int $memberId
     * @param int $dependentId
     * @return array
     */
    public function getVisaSurveyRecords($memberId, $dependentId)
    {
        $select = (new Select())
            ->from(array('s' => 'client_form_dependents_visa_survey'))
            ->where(
                [
                    's.member_id' => (int)$memberId
                ]
            );

        if (empty($dependentId)) {
            $select->where->isNull('dependent_id');
        } else {
            $select->where->equalTo('dependent_id', (int)$dependentId);
        }

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load list of supported countries
     * 
     * @param bool $booKeysOnly
     * @return array
     */
    public function getCountriesList($booKeysOnly = false)
    {
        $arrCountries = array(
            array(
                'countries_id'   => 'US',
                'countries_name' => 'US',
            ), array(
                'countries_id'   => 'UK',
                'countries_name' => 'UK',
            ), array(
                'countries_id'   => 'Schengen',
                'countries_name' => 'Schengen',
            ), array(
                'countries_id'   => 'Canada',
                'countries_name' => 'Canada',
            ),
        );

        if ($booKeysOnly) {
            $arrResult = array();

            foreach ($arrCountries as $arrCountryInfo) {
                $arrResult[] = $arrCountryInfo['countries_id'];
            }
        } else {
            $arrResult = $arrCountries;
        }

        return $arrResult;
    }


    /**
     * Load info about the visa survey record
     *
     * @param int $visaSurveyRecordId
     * @return array
     */
    public function getVisaSurveyRecordInfo($visaSurveyRecordId)
    {
        $select = (new Select())
            ->from(array('s' => 'client_form_dependents_visa_survey'))
            ->where(
                [
                    's.visa_survey_id' => (int)$visaSurveyRecordId
                ]
            );

        return $this->_db2->fetchRow($select);
    }

    /**
     * Delete visa survey record
     *
     * @param int $visaSurveyId
     * @return bool true on success
     */
    public function deleteVisaSurveyRecord($visaSurveyId)
    {
        try {
            $this->_db2->delete('client_form_dependents_visa_survey', ['visa_survey_id' => (int)$visaSurveyId]);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Create/update visa survey record
     *
     * @param int $caseId
     * @param int $dependentId
     * @param int $visaSurveyId
     * @param int $visaCountryId
     * @param string $visaNumber
     * @param string $visaIssueDate
     * @param string $visaExpiryDate
     * @return bool
     */
    public function saveVisaSurveyRecord($caseId, $dependentId, $visaSurveyId, $visaCountryId, $visaNumber, $visaIssueDate, $visaExpiryDate)
    {
        $booSuccess = false;

        try {
            if (empty($visaSurveyId)) {
                $this->_db2->insert(
                    'client_form_dependents_visa_survey',
                    [
                        'member_id'        => $caseId,
                        'dependent_id'     => empty($dependentId) ? null : $dependentId,
                        'visa_country_id'  => $visaCountryId,
                        'visa_number'      => $visaNumber,
                        'visa_issue_date'  => $visaIssueDate,
                        'visa_expiry_date' => $visaExpiryDate,
                    ]
                );
            } else {
                $this->_db2->update(
                    'client_form_dependents_visa_survey',
                    [
                        'visa_country_id'  => $visaCountryId,
                        'visa_number'      => $visaNumber,
                        'visa_issue_date'  => $visaIssueDate,
                        'visa_expiry_date' => $visaExpiryDate,
                    ],
                    ['visa_survey_id' => (int)$visaSurveyId]
                );
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

}
