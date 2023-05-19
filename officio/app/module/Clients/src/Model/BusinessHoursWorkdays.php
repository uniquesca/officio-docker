<?php

namespace Clients\Model;

use Exception;
use Laminas\Db\Sql\Select;
use Officio\Common\DbAdapterWrapper;
use Officio\Common\Service\Log;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class BusinessHoursWorkdays
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
     * Load "workdays" settings for user/company
     *
     * @param int $memberId
     * @param int $companyId
     * @param bool $booConvertValues
     * @return array
     */
    public function getWorkdays($memberId = null, $companyId = null, $booConvertValues = true)
    {
        $arrRecords = array();

        try {
            $select = (new Select())
                ->from('business_hours_workdays');

            $arrWhere = [];
            if (!empty($memberId)) {
                $arrWhere['member_id'] = (int)$memberId;
            }

            if (!empty($companyId)) {
                $arrWhere['company_id'] = (int)$companyId;
            }
            $select->where($arrWhere);

            if (!empty($memberId) || !empty($companyId)) {
                $arrRecords = $this->_db2->fetchRow($select);

                if (!empty($arrRecords) && $booConvertValues) {
                    // Remove unnecessary seconds
                    for ($i = 0; $i < 7; $i++) {
                        $day = strtolower(jddayofweek($i, 1));

                        if (isset($arrRecords[$day . '_time_from']) && !empty($arrRecords[$day . '_time_from'])) {
                            $exploded                        = explode(':', $arrRecords[$day . '_time_from']  ?? '');
                            $arrRecords[$day . '_time_from'] = $exploded[0] . ':' . $exploded[1];
                        }

                        if (isset($arrRecords[$day . '_time_to']) && !empty($arrRecords[$day . '_time_to'])) {
                            $exploded                      = explode(':', $arrRecords[$day . '_time_to']  ?? '');
                            $arrRecords[$day . '_time_to'] = $exploded[0] . ':' . $exploded[1];
                        }

                        if (isset($arrRecords[$day . '_time_enabled'])) {
                            $arrRecords[$day . '_time_enabled'] = $arrRecords[$day . '_time_enabled'] == 'Y';
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrRecords;
    }

    /**
     * Create/update "workdays" for specific user
     *
     * @param int $memberId
     * @param array $arrDataToSave
     */
    public function updateUserWorkdays($memberId, $arrDataToSave)
    {
        $arrSavedInfo = $this->getWorkdays($memberId);

        if (empty($arrSavedInfo)) {
            if (!empty($arrDataToSave)) {
                $arrDataToSave['member_id'] = $memberId;

                $this->_db2->insert('business_hours_workdays', $arrDataToSave);
            }
        } elseif (empty($arrDataToSave)) {
            $this->_db2->delete('business_hours_workdays', ['member_id' => (int)$memberId]);
        } else {
            $this->_db2->update('business_hours_workdays', $arrDataToSave, ['member_id' => (int)$memberId]);
        }
    }

    /**
     * Create/update "workdays" for specific company
     *
     * @param int $companyId
     * @param array $arrDataToSave
     */
    public function updateCompanyWorkdays($companyId, $arrDataToSave)
    {
        $arrSavedInfo = $this->getWorkdays(null, $companyId);

        if (empty($arrSavedInfo)) {
            if (!empty($arrDataToSave)) {
                $arrDataToSave['company_id'] = $companyId;

                $this->_db2->insert('business_hours_workdays', $arrDataToSave);
            }
        } elseif (empty($arrDataToSave)) {
            $this->_db2->delete('business_hours_workdays', ['company_id' => (int)$companyId]);
        } else {
            $this->_db2->update('business_hours_workdays', $arrDataToSave, ['company_id' => (int)$companyId]);
        }
    }
}
