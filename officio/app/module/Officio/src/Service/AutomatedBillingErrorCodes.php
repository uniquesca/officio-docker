<?php

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */

namespace Officio\Service;

use Exception;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Officio\Common\Service\BaseService;

class AutomatedBillingErrorCodes extends BaseService
{
    public $intShowErrorsPerPage = 25;

    /**
     * Load error codes list
     *
     * @return array
     */
    public function loadErrorCodesOnly()
    {
        $select = (new Select())
            ->from('automated_billing_blacklist')
            ->columns(['pt_error_code']);

        return $this->_db2->fetchCol($select);
    }

    /**
     * Load paged list of error codes (all info)
     *
     * @param int $start
     * @param int $limit
     * @return array
     */
    public function getErrorCodesList($start = 0, $limit = 25)
    {
        if (!is_numeric($start) || $start <= 0) {
            $start = 0;
        }

        if (!is_numeric($limit) || $limit <= 0) {
            $limit = $this->intShowErrorsPerPage;
        }

        $select = (new Select())
            ->from(['l' => 'automated_billing_blacklist'])
            ->order('l.pt_error_code DESC')
            ->limit($limit)
            ->offset($start);

        $arrSavedCodes = $this->_db2->fetchAll($select);
        $totalRecords  = $this->_db2->fetchResultsCount($select);

        return array(
            'rows'       => $arrSavedCodes,
            'totalCount' => $totalRecords
        );
    }

    /**
     * Check if specific error code exists in DB
     *
     * @param $code
     * @param int $ignoreErrorId
     * @return string
     */
    public function checkCodeExists($code, $ignoreErrorId = 0)
    {
        $arrWhere                  = [];
        $arrWhere['pt_error_code'] = $code;
        if (!empty($ignoreErrorId)) {
            $arrWhere[] = (new Where())->notEqualTo('pt_error_id', $ignoreErrorId);
        }

        $select = (new Select())
            ->from(['l' => 'automated_billing_blacklist'])
            ->columns(['count' => new Expression('COUNT(*)')])
            ->where($arrWhere);

        return $this->_db2->fetchOne($select);
    }

    /**
     * Create/update error code info
     *
     * @param array $arrUpdateInfo
     * @return bool true on success
     */
    public function update($arrUpdateInfo)
    {
        try {
            if (!empty($arrUpdateInfo['pt_error_id'])) {
                $arrWhere = ['pt_error_id' => $arrUpdateInfo['pt_error_id']];
                unset($arrUpdateInfo['pt_error_id']);

                $this->_db2->update('automated_billing_blacklist', $arrUpdateInfo, $arrWhere);
            } else {
                unset($arrUpdateInfo['pt_error_id']);
                $this->_db2->insert('automated_billing_blacklist', $arrUpdateInfo);
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Delete error code info by id
     *
     * @param int $ptErrorId
     * @return bool true on success
     */
    public function delete($ptErrorId)
    {
        try {
            $this->_db2->delete('automated_billing_blacklist', ['pt_error_id' => $ptErrorId]);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }
}
