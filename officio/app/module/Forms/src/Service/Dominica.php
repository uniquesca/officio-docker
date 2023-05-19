<?php

namespace Forms\Service;

use Exception;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;

/**
 * Class Dominica
 * This class is responsible for DM CBIU related functionality
 */
class Dominica extends BaseService
{
    /**
     * Generate formatted CON number
     *
     * @param $year
     * @param $number
     * @return string
     */
    public function formatConNumber($year, $number)
    {
        return sprintf('%s of %s', $number, $year);
    }

    /**
     * Creates CON number records for the given case
     *
     * @param int $companyId
     * @param int $memberId
     * @param bool $bindMainApplicant
     * @param array $dependantIds
     * @return array Array of inserted numbers
     */
    public function bindConNumbers($companyId, $memberId, $bindMainApplicant = true, $dependantIds = array())
    {
        $arrResult = array();

        $this->_db2->getDriver()->getConnection()->beginTransaction();
        try {
            $year = date('Y');

            $select = (new Select())
                ->from('dm_con_numbers')
                ->columns(['max_number' => new Expression('MAX(`number`)')])
                ->where([
                    'company_id' => (int)$companyId,
                    'year'       => (int)$year
                ]);

            $row = $this->_db2->fetchRow($select);

            if (!$row || empty($row['max_number'])) {
                $lastNumber = 1;
            } else {
                $lastNumber = (int)$row['max_number'] + 1;
            }

            if ($bindMainApplicant) {
                $mainApplicantInserted = $this->_db2->insert(
                    'dm_con_numbers',
                    [
                        'company_id'   => (int)$companyId,
                        'member_id'    => (int)$memberId,
                        'dependent_id' => null,
                        'year'         => (int)$year,
                        'number'       => $lastNumber,
                    ]
                );

                if ($mainApplicantInserted) {
                    $arrResult['main_applicant'] = $this->formatConNumber($year, $lastNumber);
                    $lastNumber++;
                }
            }

            foreach ($dependantIds as $dependantId) {
                $depInserted = $this->_db2->insert(
                    'dm_con_numbers',
                    [
                        'company_id'   => (int)$companyId,
                        'member_id'    => (int)$memberId,
                        'dependent_id' => (int)$dependantId,
                        'year'         => (int)$year,
                        'number'       => $lastNumber,
                    ]
                );

                if ($depInserted) {
                    $arrResult[(int)$dependantId] = $this->formatConNumber($year, $lastNumber);
                    $lastNumber++;
                }
            }

            $this->_db2->getDriver()->getConnection()->commit();
        } catch (Exception $e) {
            $this->_db2->getDriver()->getConnection()->rollback();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            $arrResult = array();
        }

        return $arrResult;
    }

    /**
     * Load the list of used/generated numbers for a specific company and member
     *
     * @param int $companyId
     * @param int $memberId
     * @return array
     */
    public function getCaseConNumbers($companyId, $memberId)
    {
        $select = (new Select())
            ->from('dm_con_numbers')
            ->where([
                'company_id' => $companyId,
                'member_id'  => $memberId
            ]);

        $rows = $this->_db2->fetchAll($select);

        $results = array();
        foreach ($rows as $row) {
            $dependentId = empty($row['dependent_id']) ? 'main_applicant' : $row['dependent_id'];
            $results[$dependentId] = $this->formatConNumber($row['year'], $row['number']);
        }

        return $results;
    }

    /**
     * Checks CON numbers generated for the case and adds new ones if necessary
     *
     * @param $companyId
     * @param $memberId
     * @param array $dependentIds
     * @param bool $booAsDraft
     * @return array Updated list of all the CON numbers
     */
    public function commitConNumbers($companyId, $memberId, $dependentIds, $booAsDraft)
    {
        $conNumbers = $this->getCaseConNumbers($companyId, $memberId);

        if (!$booAsDraft) {
            $missingMainApplicant = !isset($conNumbers['main_applicant']);
            // Dependent IDs added in the family structure recently, but have no con numbers generated yet
            $missingDependentIds = array_diff_key(array_flip($dependentIds), $conNumbers);

            $newConNumbers = $this->bindConNumbers($companyId, $memberId, $missingMainApplicant, array_keys($missingDependentIds));

            // We don't just merge arrays because it will mess the keys
            foreach ($newConNumbers as $dependentId => $conNumber) {
                $conNumbers[$dependentId] = $conNumber;
            }
        }

        return $conNumbers;
    }

}