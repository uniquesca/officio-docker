<?php

namespace Officio\Service\Company;

use Exception;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;
use Officio\Service\Company;
use Officio\Common\SubServiceInterface;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class CompanyTADivisions extends BaseService implements SubServiceInterface
{

    /** @var Company */
    private $_parent;

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent()
    {
        return $this->_parent;
    }

    /**
     * Get list of assigned divisions for specific company T/A
     * @param int $companyTaId
     * @return array
     */
    public function getCompanyTaDivisions($companyTaId)
    {
        $select = (new Select())
            ->from('company_ta_divisions')
            ->columns(['division_id'])
            ->where(['company_ta_id' => (int)$companyTaId]);

        return $this->_db2->fetchCol($select);
    }

    /**
     * Get list of company T/A ids by division ids
     * @param array $arrDivisionIds
     * @return array
     */
    public function getCompanyTAIdsByDivisions($arrDivisionIds)
    {
        $arrCompanyTAIds = array();
        if (is_array($arrDivisionIds) && count($arrDivisionIds)) {
            $select = (new Select())
                ->from('company_ta_divisions')
                ->columns(['company_ta_id'])
                ->where(['division_id' => $arrDivisionIds]);

            $arrCompanyTAIds = $this->_db2->fetchCol($select);
        }

        return $arrCompanyTAIds;
    }

    /**
     * Update list of assigned divisions for specific specific company T/A
     * @param int $companyTaId
     * @param array $arrDivisionIds
     * @return bool true on success
     */
    public function updateCompanyTaDivisions($companyTaId, $arrDivisionIds)
    {
        try {
            // Clear all
            $this->_db2->delete('company_ta_divisions', ['company_ta_id' => (int)$companyTaId]);

            // Insert all records
            foreach ($arrDivisionIds as $divisionId) {
                $this->_db2->insert(
                    'company_ta_divisions',
                    [
                        'company_ta_id' => $companyTaId,
                        'division_id'   => $divisionId,
                    ]
                );
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }
}
