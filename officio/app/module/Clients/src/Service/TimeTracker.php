<?php

namespace Clients\Service;

use Clients\Model\TrackerModel;
use Exception;
use Laminas\Filter\StripTags;
use Officio\Common\Service\BaseService;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class TimeTracker extends BaseService
{
    /** @var Members */
    private $_members;

    /** @var Clients */
    private $_clients;

    /** @var TrackerModel */
    private $_tracker;

    public function initAdditionalServices(array $services)
    {
        $this->_members = $services[Members::class];
        $this->_clients = $services[Clients::class];
    }

    public function init()
    {
        $this->_tracker = new TrackerModel($this->_db2, $this->_settings, $this->_clients);
    }

    public function addedit($params)
    {
        $strError = '';

        try {
            $booSuperadmin = $this->_auth->isCurrentUserSuperadmin();
            if (($booSuperadmin && !$params['track_company_id']) || (!$booSuperadmin && !$this->_members->hasCurrentMemberAccessToMember($params['track_member_id']))) {
                $strError = 'Internal error';
            }

            if (empty($strError) && !$params['track_date']) {
                $strError = 'Please fill in all required fields';
            }

            if (empty($strError)) {
                $filter = new StripTags();

                $params = array(
                    'track_id'                  => (int)$params['track_id'],
                    'track_member_id'           => $params['track_type'] == 'client' ? $params['track_member_id'] : null,
                    'track_company_id'          => $params['track_type'] == 'company' ? $params['track_company_id'] : null,
                    'track_posted_on'           => date('Y-m-d', isset($params['track_date']) ? strtotime($params['track_date']) : time()),
                    'track_posted_by_member_id' => $this->_auth->getCurrentUserId(),
                    'track_time_billed'         => (int)$params['track_time_billed'],
                    'track_time_actual'         => (int)$params['track_time_actual'],
                    'track_round_up'            => (int)$params['track_round_up'],
                    'track_rate'                => (double)$params['track_rate'],
                    'track_total'               => (double)$params['track_total'],
                    'track_comment'             => $filter->filter($params['track_comment']),
                    'track_billed'              => $params['track_billed'] ?? 'N',
                );

                $this->_tracker->addedit($params);
            }
        } catch (Exception $e) {
            $strError = 'Internal error.';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'success' => empty($strError),
            'msg'     => $strError
        );
    }

    public function getList($filters, $sortBy, $sortWhere, $start, $limit)
    {
        return $this->_tracker->getList($filters, $sortBy, $sortWhere, $start, $limit);
    }

    public function markBilled($arrTrackIds)
    {
        $arrTrackIds = (array)$arrTrackIds;

        $this->_tracker->markBilled($arrTrackIds);
    }

    public function deleteItems($arrTrackIds)
    {
        if (!is_array($arrTrackIds)) {
            $arrTrackIds = array($arrTrackIds);
        }

        $this->_tracker->deleteItems($arrTrackIds);
    }

    public function getClientsIdsByItemsIds($arrTrackIds)
    {
        if (!is_array($arrTrackIds)) {
            $arrTrackIds = array($arrTrackIds);
        }

        return $this->_tracker->getClientsIdsByItemsIds($arrTrackIds);
    }
}