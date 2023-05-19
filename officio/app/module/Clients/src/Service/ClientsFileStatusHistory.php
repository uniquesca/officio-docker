<?php

namespace Clients\Service;

use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Officio\Common\Service\BaseService;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class ClientsFileStatusHistory extends BaseService
{
    /**
     * Load list of client referral records for the member
     *
     * @param int $memberId
     * @return array
     */
    public function getClientFileStatusHistory($memberId)
    {
        $select = (new Select())
            ->from(array('h' => 'client_statuses_history'))
            ->columns(['history_client_status_name', 'history_checked_user_name', 'history_checked_date', 'history_unchecked_user_name', 'history_unchecked_date'])
            ->join(array('m' => 'members'), 'm.member_id = h.history_checked_user_id', array('history_checked_current_user_name' => new Expression('CONCAT(m.lName, " ", m.fName)')), Select::JOIN_LEFT)
            ->join(array('m2' => 'members'), 'm2.member_id = h.history_unchecked_user_id', array('history_unchecked_current_user_name' => new Expression('CONCAT(m2.lName, " ", m2.fName)')), Select::JOIN_LEFT)
            ->join(array('cs' => 'client_statuses'), 'cs.client_status_id = h.client_status_id', array('history_current_client_status_name' => 'client_status_name'), Select::JOIN_LEFT)
            ->where(['h.member_id' => (int)$memberId])
            ->order(['h.history_checked_date DESC', 'h.history_id DESC']);

        return $this->_db2->fetchAll($select);
    }

    /**
     * Create file status history record
     *
     * @param int $memberId
     * @param array|null $arrStatuses
     * @param int|null $userId
     * @param string $userName
     * @return void
     */
    public function saveClientFileStatusHistory($memberId, $arrStatuses, $userId, $userName)
    {
        $select = (new Select())
            ->columns(['history_id', 'client_status_id'])
            ->from(['h' => 'client_statuses_history'])
            ->where(
                [
                    (new Where())->isNull('h.history_unchecked_date'),
                    'h.member_id' => (int)$memberId,
                ]
            );

        $arrActiveHistoryRecords = $this->_db2->fetchAll($select);

        $arrHistoryRecordsToMarkAsUnchecked = [];
        $arrActiveHistoryRecordsStatuses    = [];
        if (!empty($arrActiveHistoryRecords)) {
            // Check which statuses were unchecked
            $arrNewStatusesIds = array_keys($arrStatuses);
            foreach ($arrActiveHistoryRecords as $arrActiveHistoryRecordInfo) {
                $arrActiveHistoryRecordsStatuses[] = $arrActiveHistoryRecordInfo['client_status_id'];

                if (!in_array($arrActiveHistoryRecordInfo['client_status_id'], $arrNewStatusesIds)) {
                    $arrHistoryRecordsToMarkAsUnchecked[] = $arrActiveHistoryRecordInfo['history_id'];
                }
            }
        }

        $now = date('Y-m-d H:i:s');
        foreach ($arrStatuses as $statusId => $statusName) {
            if (!in_array($statusId, $arrActiveHistoryRecordsStatuses)) {
                $this->_db2->insert(
                    'client_statuses_history',
                    [
                        'member_id'                  => $memberId,
                        'client_status_id'           => $statusId,
                        'history_client_status_name' => $statusName,
                        'history_checked_user_id'    => $userId,
                        'history_checked_user_name'  => $userName,
                        'history_checked_date'       => $now
                    ]
                );
            }
        }

        if (!empty($arrHistoryRecordsToMarkAsUnchecked)) {
            $this->_db2->update(
                'client_statuses_history',
                [
                    'history_unchecked_user_id'   => $userId,
                    'history_unchecked_user_name' => $userName,
                    'history_unchecked_date'      => $now
                ],
                ['history_id' => $arrHistoryRecordsToMarkAsUnchecked]
            );
        }
    }
}
