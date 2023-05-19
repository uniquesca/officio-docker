<?php

namespace Clients\Model;


use Clients\Service\Clients;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Officio\Common\DbAdapterWrapper;
use Officio\Common\Service\Settings;
use Laminas\Db\Sql\Expression;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class TrackerModel
{

    /** @var DbAdapterWrapper */
    protected $_db2;

    /** @var Clients */
    protected $_clients;

    /** @var Settings */
    protected $_settings;

    public function __construct(DbAdapterWrapper $db, Settings $settings, Clients $clients)
    {
        $this->_db2      = $db;
        $this->_settings = $settings;
        $this->_clients  = $clients;
    }

    /**
     * Add/edit time tracker record
     *
     * @param array $params
     */
    public function addedit($params)
    {
        if (isset($params['track_id']) && !empty($params['track_id'])) {
            unset($params['track_time_actual']);

            $this->_db2->update('time_tracker', $params, ['track_id' => (int)$params['track_id']]);
        } else {
            $this->_db2->insert('time_tracker', $params);
        }
    }

    /**
     * Load list of time tracker records
     *
     * @param $filters
     * @param $sort_by
     * @param $sort_where
     * @param $start
     * @param $limit
     * @param bool $booLoadTA
     * @return array
     */
    public function getList($filters, $sort_by, $sort_where, $start, $limit, $booLoadTA = true)
    {
        $booSortByClientName = $sort_by === 'track_client_name';

        if (!in_array(
            $sort_by,
            array(
                'track_posted_by_member_name',
                'track_posted_on',
                'track_time_billed_rounded',
                'track_posted_by_member_id',
                'track_case_file_number',
                'track_time_billed',
                'track_rate',
                'track_total',
                'track_comment',
                'track_billed'
            )
        )) {
            $sort_by = 'track_posted_on';
        } // default sorting is 'track_posted_on DESC'

        $sort_where = strtolower($sort_where) == 'asc' ? 'ASC' : 'DESC';

        $select = (new Select())
            ->from(array('t' => 'time_tracker'))
            ->columns(
                array(
                    'track_id',
                    'track_member_id',
                    'track_posted_on',
                    'track_posted_by_member_id',
                    'track_time_billed',
                    'track_round_up',
                    'track_rate',
                    'track_total',
                    'track_comment',
                    'track_billed',
                    'track_posted_on_date'      => 'track_posted_on',
                    'track_time_billed_rounded' => new Expression(
                        'IF (track_round_up>0, CEIL(track_time_billed/track_round_up)*track_round_up, track_time_billed)'
                    ),
                )
            )
            ->join(
                array('m' => 'members'),
                'm.member_id = t.track_posted_by_member_id',
                array('track_posted_by_member_name' => new Expression('CONCAT(m.fName, " ", m.lName)'), 'track_posted_by_member_email' => 'emailAddress'),
                Select::JOIN_LEFT
            )
            ->join(
                array('m2' => 'members'),
                'm2.member_id = t.track_member_id',
                array('client_company_id' => 'company_id'),
                Select::JOIN_LEFT
            )
            ->join(
                array('c' => 'clients'),
                'c.member_id = m2.member_id',
                array('track_case_file_number' => 'fileNumber'),
                Select::JOIN_LEFT
            )
            ->order("$sort_by $sort_where");

        $where = [];
        if (!empty($filters['company_id'])) {
            $where['t.track_company_id'] = (int)$filters['company_id'];
        } elseif (!empty($filters['client_id'])) {
            $where['t.track_member_id'] = is_array($filters['client_id']) ? $filters['client_id'] : (int)$filters['client_id'];
        } elseif (!empty($filters['client_company_id'])) {
            $where['m2.company_id'] = (int)$filters['client_company_id'];
        }

        if (!empty($filters['posted_by_member_id'])) {
            $where['t.track_posted_by_member_id'] = (int)$filters['posted_by_member_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = (new Where())->greaterThanOrEqualTo('t.track_posted_on', date('Y-m-d', strtotime($filters['date_from'])));
        }

        if (!empty($filters['date_to'])) {
            $where[] = (new Where())->lessThanOrEqualTo('t.track_posted_on', date('Y-m-d', strtotime($filters['date_to'])));
        }

        if (!empty($filters['ids_list'])) {
            $where['t.track_id'] = $filters['ids_list'];
        }

        if (isset($filters['billed']) && in_array($filters['billed'], array('Y', 'N'))) {
            $where['t.track_billed'] = $filters['billed'];
        }

        $select->where($where);

        $allItems = $this->_db2->fetchAll($select);
        $total    = count($allItems);

        if (!empty($limit) && $total >= $limit) {
            $select->limit((int)$limit);
            $select->offset((int)$start);

            $items = $this->_db2->fetchAll($select);
        } else {
            $items = $allItems;
        }

        if (!empty($items)) {
            // Load client's info additionally
            $arrCasesIds = [];
            foreach ($items as $arrItemInfo) {
                $arrCasesIds[$arrItemInfo['track_member_id']] = 0;
            }

            $arrCases       = $this->_clients->getClientsList(false, array_keys($arrCasesIds));
            $arrParentsList = $this->_clients->getCasesListWithParents($arrCases, 0, [], '', false);
            $arrNames       = array();
            foreach ($items as $key => $arrTimeRecordInfo) {
                // Format dates in the correct format
                // So will be used in the js too (e.g. Edit Time Tracker dialog)
                $items[$key]['track_posted_on_date'] = $this->_settings->formatDate($arrTimeRecordInfo['track_posted_on_date']);

                $items[$key]['track_client_name'] = isset($arrParentsList[$arrTimeRecordInfo['track_member_id']]) ? $arrParentsList[$arrTimeRecordInfo['track_member_id']]['clientName'] : '';

                $arrNames[$key] = strtolower($items[$key]['track_client_name'] ?? '');
            }

            // Sort data by client name if needed
            if ($booSortByClientName) {
                array_multisort($arrNames, $sort_where === 'ASC' ? SORT_ASC : SORT_DESC, $items);
            }


            if ($booLoadTA && !empty($filters['client_id'])) {
                $arrAccountingInfo = array();
                if (is_array($items) && count($items)) {
                    $arrAccountingInfo = $this->_clients->getAccounting()->getMemberTAList(
                        $items[0]['track_member_id'],
                        $items[0]['client_company_id']
                    );
                }

                foreach ($items as $key => $i) {
                    $items[$key]['ta_ids'] = $arrAccountingInfo;
                }
            }
        }

        $totalHours = 0;
        $totalRate  = 0;
        $allIds     = array();
        foreach ($allItems as $item) {
            $totalHours += round(floatval($item['track_time_billed_rounded']) / 60 * 100) / 100;
            $totalRate  += floatval($item['track_total']);
            $allIds[]   = $item['track_id'];
        }

        return array(
            'items'      => $items,
            'count'      => $total,
            'totalHours' => $totalHours,
            'totalRate'  => $totalRate,
            'allIds'     => $allIds
        );
    }

    /**
     * Mark time tracker record(s) as billed
     *
     * @param $arrTrackIds
     */
    public function markBilled($arrTrackIds)
    {
        if (is_array($arrTrackIds) && count($arrTrackIds)) {
            $this->_db2->update('time_tracker', ['track_billed' => 'Y'], ['track_id' => $arrTrackIds]);
        }
    }

    /**
     * Delete time tracker records
     *
     * @param array $arrTrackIds
     */
    public function deleteItems($arrTrackIds)
    {
        if (is_array($arrTrackIds) && count($arrTrackIds)) {
            $this->_db2->delete('time_tracker', ['track_id' => $arrTrackIds]);
        }
    }

    /**
     * Load list of member ids for specific time tracker records
     *
     * @param array $arrTrackIds
     * @return array
     */
    public function getClientsIdsByItemsIds($arrTrackIds)
    {
        $ids = array();

        if (is_array($arrTrackIds) && count($arrTrackIds)) {
            $select = (new Select())
                ->from('time_tracker')
                ->columns(['track_member_id'])
                ->where(['track_id' => $arrTrackIds]);

            $ids = $this->_db2->fetchCol($select);
        }

        return $ids;
    }
}
