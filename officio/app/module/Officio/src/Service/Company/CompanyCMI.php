<?php

namespace Officio\Service\Company;

use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Officio\Common\Service\BaseService;
use Officio\Service\Company;
use Officio\Common\SubServiceInterface;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class CompanyCMI extends BaseService implements SubServiceInterface
{

    /** @var Company */
    private $_parent;

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent() {
        return $this->_parent;
    }

    /**
     * Load company id by CMI and regulator ids
     *
     * @param $cmiId
     * @param $regId
     * @return array
     */
    public function getCMIPairCompanyId($cmiId, $regId)
    {
        $select = (new Select())
            ->from('company_cmi')
            ->where([
                'cmi_id'       => $cmiId,
                'regulator_id' => $regId
            ]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Check if the pair of CMI and regulator ids can be used (not used before)
     *
     * @param $cmiId
     * @param $regId
     * @return string empty if pair wasn't used before
     */
    public function checkCMIPairUsed($cmiId, $regId)
    {
        $booSuccess = false;
        $strMessage = '';

        $cmiId = trim($cmiId ?? '');
        $regId = trim($regId ?? '');

        if (!empty($cmiId) && !empty($regId)) {
            $arrCMIInfo = $this->getCMIPairCompanyId($cmiId, $regId);

            if (!empty($arrCMIInfo)) {
                // Pair is correct

                $booSuccess = true;
                if (is_numeric($arrCMIInfo['company_id'])) {
                    // Pair was used
                    $strMessage = 'You have already used your trial account privileges.';
                }
            }
        }

        if (!$booSuccess) {
            $arrEmailSettings = $this->_settings->getOfficioSupportEmail();
            $strMessage       = 'We could not locate you as a registered member.' .
                ' This could be because you typed your IDs incorrectly,' .
                ' or we may not have received your membership confirmation yet.<br/><br/>' .
                'If you need assistance, please email us at: ' . $arrEmailSettings['email'];
        }

        return $strMessage;
    }

    /**
     * Set CMI and regulator ids to specific company
     *
     * @param $cmiId
     * @param $regId
     * @param $companyId
     * @return bool
     */
    public function updateCMICompany($cmiId, $regId, $companyId)
    {
        $count = $this->_db2->update(
            'company_cmi',
            ['company_id' => (int)$companyId],
            [
                'cmi_id'       => $cmiId,
                'regulator_id' => $regId
            ]
        );


        return ($count > 0);
    }

    /**
     * Search/load all CMI records
     *
     * @param string $query
     * @param int $start
     * @param int $limit
     * @return array
     */
    public function searchCMI($query = '', $start = 0, $limit = 0)
    {
        $select = (new Select())
            ->from(array('cmi' => 'company_cmi'))
            ->join(array('c' => 'company'), 'c.company_id = cmi.company_id', 'companyName', Select::JOIN_LEFT_OUTER)
            ->order(array('cmi.company_id DESC', 'cmi.cmi_id DESC'))
            ->limit((int)$limit)
            ->offset((int)$start);

        if (!empty($query)) {
            $where = (new Where())
                ->nest()
                ->like('cmi.cmi_id', "%$query%")
                ->or
                ->like('cmi.regulator_id', "%$query%")
                ->or
                ->like('c.companyName', "%$query%")
                ->unnest();
            $select->where([$where]);
        }

        return $this->_db2->fetchAll($select);
    }

    /**
     * Save in DB all provided CMI records
     * @param $arr
     */
    public function addCMI($arr)
    {
        foreach ($arr as $rec) {
            $companyId = isset($rec['company_id']) ? (double)$rec['company_id'] : 'NULL';
            $query = "INSERT INTO `company_cmi` (`cmi_id`, `regulator_id`, `company_id`) 
                        VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE cmi_id = VALUES(cmi_id)";

            $this->_db2->query($query, [$rec['cmi_id'], $rec['regulator_id'], $companyId]);
        }
    }

    /**
     * Get count of CMI records in DB
     * @return int count
     */
    public function getCMITotalRecords()
    {
        $select = (new Select())
            ->from('company_cmi')
            ->columns(['count' => new Expression('COUNT(*)')]);

        return $this->_db2->fetchOne($select);
    }
}
