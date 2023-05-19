<?php

namespace Clients\Model;

use Exception;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Officio\Common\DbAdapterWrapper;
use Officio\Common\Service\Log;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class BusinessHoursHoliday
{
    /** @var DbAdapterWrapper */
    protected $_db2;

    /** @var Log */
    protected $_log;

    public function __construct(DbAdapterWrapper $db, Log $log)
    {
        $this->_db2 = $db;
        $this->_log = $log;
    }

    /**
     * Load list of company/user holidays records
     *
     * @param ?int $memberId
     * @param ?int $companyId
     * @param string $sort
     * @param string $dir
     * @return array
     */
    public function getHolidayRecords($memberId = null, $companyId = null, $sort = '', $dir = '')
    {
        $arrRecords = array();

        try {
            $select = (new Select())
                ->from('business_hours_holidays');

            $arrWhere = [];
            if (!empty($memberId) && !empty($companyId)) {
                $arrWhere[] = (new Where())
                    ->nest()
                    ->equalTo('member_id', (int)$memberId)
                    ->or
                    ->equalTo('company_id', (int)$companyId)
                    ->unnest();
            } else {
                if (!empty($memberId)) {
                    $arrWhere['member_id'] = (int)$memberId;
                }

                if (!empty($companyId)) {
                    $arrWhere['company_id'] = (int)$companyId;
                }
            }
            $select->where($arrWhere);


            if (!empty($sort) && !empty($dir)) {
                $select->order("$sort $dir");
            }

            if (!empty($memberId) || !empty($companyId)) {
                $arrRecords = $this->_db2->fetchAll($select);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrRecords;
    }

    /**
     * Load info about the specific holiday record
     *
     * @param int $holidayRecordId
     * @return array
     */
    public function getHolidayRecord($holidayRecordId)
    {
        try {
            $select = (new Select())
                ->from('business_hours_holidays')
                ->where(['holiday_id' => (int)$holidayRecordId]);

            $arrRecordInfo = $this->_db2->fetchRow($select);
        } catch (Exception $e) {
            $arrRecordInfo = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrRecordInfo;
    }

    /**
     * Create/update holiday record
     *
     * @param array $arrHolidayRecordInfo
     * @return bool true on success
     */
    public function saveHolidayRecord($arrHolidayRecordInfo)
    {
        try {
            if (empty($arrHolidayRecordInfo['holiday_id'])) {
                $this->_db2->insert('business_hours_holidays', $arrHolidayRecordInfo);
            } else {
                $this->_db2->update('business_hours_holidays', $arrHolidayRecordInfo, ['holiday_id' => (int)$arrHolidayRecordInfo['holiday_id']]);
            }
            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Delete holiday record(s)
     *
     * @param array $arrHolidayRecordsIds
     * @return bool true on success
     */
    public function deleteHolidayRecords($arrHolidayRecordsIds)
    {
        $booSuccess = false;

        try {
            if (is_array($arrHolidayRecordsIds) && !empty($arrHolidayRecordsIds)) {
                $this->_db2->delete('business_hours_holidays', ['holiday_id' => $arrHolidayRecordsIds]);

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }
}
