<?php

use Clients\Service\Clients;
use Files\Service\Files;
use Officio\Common\Service\Log;
use Phinx\Migration\AbstractMigration;

class FixBcpnpIncorrectClients extends AbstractMigration
{

    // This is function ported from form code. Although not best way to calculate number of months, it's implementation
    // is closest to the original JS code.
    private function calcMonthsBetweenDates($fromDate, $toDate)
    {
        if (strlen($fromDate) === 7) {
            $fromDate = $fromDate . '-01';
        }
        if (strlen($toDate) === 7) {
            $toDate = $toDate . '-28';
        }

        $fromDate = strtotime($fromDate);
        $toDate   = strtotime($toDate);
        if ($fromDate > $toDate) {
            return 0;
        }

        $months = ((int)date('Y', $toDate) - (int)date('Y', $fromDate)) * 12;
        $months -= (int)date('n', $fromDate);
        $months += (int)date('n', $toDate);

        $fromDay = (int)date('j', $fromDate);
        $toDay   = (int)date('j', $toDate);
        if ($fromDay < $toDay) {
            $months++;
        }

        if ($months < 0) {
            return 0;
        } elseif ($months < 1) {
            return 1;
        }

        return $months;
    }

    /**
     * We cannot use bccomp...
     *
     * @param $float1
     * @param $float2
     * @param string $operator
     * @return bool
     */
    private function compareFloatNumbers($float1, $float2, $operator = '=')
    {
        $epsilon = 0.1;

        $float1 = (float)$float1;
        $float2 = (float)$float2;

        switch ($operator) {
            // equal
            case "=":
            case "eq":
            {
                if (abs($float1 - $float2) < $epsilon) {
                    return true;
                }
                break;
            }
            // less than
            case "<":
            case "lt":
            {
                if (abs($float1 - $float2) < $epsilon) {
                    return false;
                } else {
                    if ($float1 < $float2) {
                        return true;
                    }
                }
                break;
            }
            // less than or equal
            case "<=":
            case "lte":
            {
                if ($this->compareFloatNumbers($float1, $float2, '<') || $this->compareFloatNumbers($float1, $float2)) {
                    return true;
                }
                break;
            }
            // greater than
            case ">":
            case "gt":
            {
                if (abs($float1 - $float2) < $epsilon) {
                    return false;
                } else {
                    if ($float1 > $float2) {
                        return true;
                    }
                }
                break;
            }
            // greater than or equal
            case ">=":
            case "gte":
            {
                if ($this->compareFloatNumbers($float1, $float2, '>') || $this->compareFloatNumbers($float1, $float2)) {
                    return true;
                }
                break;
            }
            case "<>":
            case "!=":
            case "ne":
            {
                if (abs($float1 - $float2) > $epsilon) {
                    return true;
                }
                break;
            }
            default:
            {
                die("Unknown operator '" . $operator . "' in compareFloatNumbers()");
            }
        }

        return false;
    }

    public function up()
    {
        /** @var $log Log */
        $log = Zend_Registry::get('serviceManager')->get('log');

        /** @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        try {
            $log->debugToFile('Start: ' . date('c'), 0);

            $companyId                  = 1;
            $caseType                   = 'Skills Immigration Registration';
            $scoreFieldTextIdToFix      = 'scoreExperienceSI_new';
            $totalScoreFieldTextIdToFix = 'scoreTotalSI_new';

            $startDate = '2020-01-29 00:00';
            $endDate   = '2020-02-14 23:59';


            // Get case type id
            $select = $db->select()
                ->from('client_types', 'client_type_id')
                ->where('client_type_name = ?', $caseType)
                ->where('company_id = ?', $companyId, 'INT');

            $caseTypeId = $db->fetchOne($select);

            if (empty($caseTypeId)) {
                throw new Exception('Case Type was not found');
            }


            // Make sure that "score" field exists
            /** @var Clients $clients */
            $clients        = Zend_Registry::get('serviceManager')->get(Clients::class);
            $scoreFieldInfo = $clients->getFields()->getCompanyFieldInfoByUniqueFieldId($scoreFieldTextIdToFix, $companyId);
            $scoreFieldId   = isset($scoreFieldInfo['field_id']) ? $scoreFieldInfo['field_id'] : 0;
            if (empty($scoreFieldId)) {
                throw new Exception('Score field not found');
            }

            // Make sure that "total score" field exists
            $totalScoreFieldInfo = $clients->getFields()->getCompanyFieldInfoByUniqueFieldId($totalScoreFieldTextIdToFix, $companyId);
            $totalScoreFieldId   = isset($totalScoreFieldInfo['field_id']) ? $totalScoreFieldInfo['field_id'] : 0;
            if (empty($totalScoreFieldId)) {
                throw new Exception('Total score field not found');
            }

            // Find all clients assigned to this case type created in that specified date range
            $select = $db->select()
                ->from(array('c' => 'clients'), array('member_id', 'fileNumber'))
                ->joinInner(array('m' => 'members'), 'c.member_id = m.member_id', '')
                ->where('m.regTime >= ?', strtotime($startDate))
                ->where('m.regTime <= ?', strtotime($endDate))
                ->where('c.client_type_id = ?', $caseTypeId, 'INT');

            $arrCases = $db->fetchPairs($select);

            if (!empty($arrCases)) {
                $arrCasesIds = array_keys($arrCases);

                // Load already saved values (for the score field) for all clients
                $arrScoreValues = $clients->getFields()->getClientsFieldDataValue($scoreFieldId, $arrCasesIds);

                // Load already saved values (for the total score field) for all clients
                $arrTotalScoreValues = $clients->getFields()->getClientsFieldDataValue($totalScoreFieldId, $arrCasesIds);

                // Load json file for each assigned form,
                // Calculate + update
                // Update case's field too
                /** @var Files $oFiles */
                $oFiles = Zend_Registry::get('serviceManager')->get(Files::class);
                foreach ($arrCases as $memberId => $caseReferenceNumber) {
                    $jsonFilePath = $oFiles->getClientJsonFilePath($memberId, 'main_applicant', 0);
                    if (file_exists($jsonFilePath)) {
                        $savedJson      = file_get_contents($jsonFilePath);
                        $arrData        = json_decode($savedJson, true);
                        $workExpRecords = empty($arrData['WorkExpRecords']) ? false : $arrData['WorkExpRecords'];
                        if (!$workExpRecords) {
                            $log->debugToFile('Case #' . $caseReferenceNumber . ': no work experience records, skipping.');
                            continue;
                        }

                        $workExpMonthsTotal  = $workExpMonthsCanadaTotal = 0;
                        $workExpRecordsFixed = false;
                        foreach ($workExpRecords as $deltaId => &$workExpRecord) {
                            list(, $key) = explode('_', $deltaId);
                            $fromFieldName        = 'BCPNP_App_Work_From-' . $key;
                            $toFieldName          = 'BCPNP_App_Work_To-' . $key;
                            $monthsFieldName      = 'BCPNP_App_Work_TotalMonths-' . $key;
                            $inCanadaExpFieldName = 'BCPNP_App_WorkExp_InCan-' . $key;
                            $partTimeExpFieldName = 'BCPNP_App_Work_PartTime-' . $key;

                            if (empty($workExpRecord[$fromFieldName])) {
                                $log->debugToFile('Case #' . $caseReferenceNumber . ': work experience record(s) without "From" date, skipping.');
                                continue;
                            }

                            if (empty($workExpRecord[$toFieldName])) {
                                $log->debugToFile('Case #' . $caseReferenceNumber . ': work experience record(s) without "To" date, skipping.');
                                continue;
                            }

                            $months = $this->calcMonthsBetweenDates($workExpRecord[$fromFieldName], $workExpRecord[$toFieldName]);

                            $partTime = !empty($workExpRecord[$partTimeExpFieldName]) ? $workExpRecord[$partTimeExpFieldName] : false;
                            if ($partTime == 'Yes') {
                                $months = round($months / 2, 1);
                            }

                            $monthsFormValue = !empty($workExpRecord[$monthsFieldName]) ? round($workExpRecord[$monthsFieldName], 1) : 0;
                            if ($this->compareFloatNumbers($monthsFormValue, $months, '!=')) {
                                $workExpRecord[$monthsFieldName] = $months;

                                $workExpRecordsFixed = true;
                                $log->debugToFile(
                                    'Case #' . $caseReferenceNumber . ': ' . $monthsFormValue
                                    . ' months value instead of ' . $months . ', dates are ' . $workExpRecord[$fromFieldName]
                                    . ' - ' . $workExpRecord[$toFieldName] . ($partTime == 'Yes' ? ', part time' : '')
                                );
                            }
                            $workExpMonthsTotal += $months;

                            $inCanadaExperienceValue = !empty($workExpRecord[$inCanadaExpFieldName]) ? $workExpRecord[$inCanadaExpFieldName] : false;
                            if ($inCanadaExperienceValue == 'Yes') {
                                $workExpMonthsCanadaTotal += $months;
                            }
                        }

                        if ($workExpRecordsFixed) {
                            // Save JSON
                            $arrData['WorkExpRecords'] = $workExpRecords;

                            $result = file_put_contents($jsonFilePath, json_encode($arrData));
                            if ($result) {
                                $log->debugToFile('Case #' . $caseReferenceNumber . ': form data updated successfully.');
                            } else {
                                $log->debugToFile('WARNING: Case #' . $caseReferenceNumber . ': could not update form data.');
                            }

                            // Calculate correct score
                            if ($this->compareFloatNumbers($workExpMonthsTotal, 60, '>')) {
                                $points = 15;
                            } elseif ($this->compareFloatNumbers($workExpMonthsTotal, 48, '>=')) {
                                $points = 12;
                            } elseif ($this->compareFloatNumbers($workExpMonthsTotal, 36, '>=')) {
                                $points = 9;
                            } elseif ($this->compareFloatNumbers($workExpMonthsTotal, 24, '>=')) {
                                $points = 6;
                            } elseif ($this->compareFloatNumbers($workExpMonthsTotal, 12, '>=')) {
                                $points = 3;
                            } elseif ($this->compareFloatNumbers($workExpMonthsTotal, 0, '>')) {
                                $points = 1;
                            } else {
                                $points = 0;
                            }

                            if ($this->compareFloatNumbers($workExpMonthsCanadaTotal, 12, '>=')) {
                                $points += 10;
                            }

                            $oldPointsValue   = isset($arrScoreValues[$memberId]['value']) ? $arrScoreValues[$memberId]['value'] : 0;
                            $pointsDifference = $points - $oldPointsValue;

                            // If the score is different - insert/update
                            if ($pointsDifference) {
                                $log->debugToFile('Case #' . $caseReferenceNumber . ': got ' . $oldPointsValue . ' experience points instead of ' . $points);

                                if (isset($arrScoreValues[$memberId]['value'])) {
                                    $db->update(
                                        'client_form_data',
                                        array(
                                            'value' => $points
                                        ),
                                        $db->quoteInto('member_id = ? AND ', $memberId, 'INT') . $db->quoteInto('field_id = ?', $scoreFieldId, 'INT')
                                    );

                                    $log->debugToFile('Case #' . $caseReferenceNumber . ': experience points updated');
                                } else {
                                    $db->insert(
                                        'client_form_data',
                                        array(
                                            'member_id' => $memberId,
                                            'field_id'  => $scoreFieldId,
                                            'value'     => $points,
                                        )
                                    );

                                    $log->debugToFile('Case #' . $caseReferenceNumber . ': experience points inserted');
                                }

                                // Recalculate the total too
                                $oldPointsTotalValue = isset($arrTotalScoreValues[$memberId]['value']) ? $arrTotalScoreValues[$memberId]['value'] : 0;
                                $newPointsTotalValue = $oldPointsTotalValue + $pointsDifference;

                                if ($oldPointsTotalValue != $newPointsTotalValue) {
                                    if (isset($arrTotalScoreValues[$memberId]['value'])) {
                                        $db->update(
                                            'client_form_data',
                                            array(
                                                'value' => $newPointsTotalValue
                                            ),
                                            $db->quoteInto('member_id = ? AND ', $memberId, 'INT') . $db->quoteInto('field_id = ?', $totalScoreFieldId, 'INT')
                                        );

                                        $log->debugToFile('Case #' . $caseReferenceNumber . ': total points updated from ' . $oldPointsTotalValue . ' to ' . $newPointsTotalValue);
                                    } else {
                                        $db->insert(
                                            'client_form_data',
                                            array(
                                                'member_id' => $memberId,
                                                'field_id'  => $totalScoreFieldId,
                                                'value'     => $newPointsTotalValue
                                            )
                                        );

                                        $log->debugToFile('Case #' . $caseReferenceNumber . ': total points set to ' . $newPointsTotalValue);
                                    }
                                } else {
                                    $log->debugToFile('WARNING: Case #' . $caseReferenceNumber . ': total points not changed');
                                }
                            }
                        } else {
                            $log->debugToFile('Case #' . $caseReferenceNumber . ': fix not needed');
                        }
                    } else {
                        $log->debugToFile('WARNING: Case #' . $caseReferenceNumber . ': no json file');
                    }
                    $log->debugToFile(PHP_EOL);

                    // Ping, so phinx connection will be alive
                    $this->fetchRow('SELECT 1');
                }
            } else {
                $log->debugToFile('WARNING: No cases found');
            }

            $log->debugToFile('End: ' . date('c'));
        } catch (\Exception $e) {
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            exit($e->getMessage());
        }
    }

    public function down()
    {
    }

}
