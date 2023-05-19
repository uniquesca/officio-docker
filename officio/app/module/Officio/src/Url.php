<?php

namespace Officio;

use Exception;
use Laminas\Db\Sql\Select;
use Officio\Common\DbAdapterWrapper;
use Officio\Common\Service\Log;

/**
 * Class Url
 * @package Officio
 */
class Url
{

    /** @var Log */
    private $_log;

    /** @var DbAdapterWrapper */
    protected $_db2;

    public function __construct(DbAdapterWrapper $db, Log $log)
    {
        $this->_db2 = $db;
        $this->_log = $log;
    }

    public function getList()
    {
        try {
            $select = (new Select())
                ->from('snapshots')
                ->order('id');

            $arrUrls = $this->_db2->fetchAll($select);

            foreach ($arrUrls as &$arrUrlInfo) {
                $arrUrlInfo['status'] = empty($arrUrlInfo['hash']) ? 'not_checked' : 'ok';
            }
        } catch (Exception $e) {
            $arrUrls = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrUrls;
    }


    public function saveUrl($urlId, $urlAddress, $urlDescription, $urlAssignedFormId)
    {
        try {
            $strError   = '';
            $arrUrlInfo = array(
                'id'               => $urlId,
                'url'              => $urlAddress,
                'url_description'  => strlen($urlDescription ?? '') ? $urlDescription : null,
                'assigned_form_id' => empty($urlAssignedFormId) ? null : $urlAssignedFormId,
            );

            if (empty($urlId)) {
                // Add new url
                $this->_db2->insert('snapshots', $arrUrlInfo);
            } else {
                // Update existing
                $arrUrlInfo['hash'] = null;

                $this->_db2->update('snapshots', $arrUrlInfo, ['id' => $urlId]);
            }
        } catch (Exception $e) {
            $strError = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }


    public function deleteUrls($arrIds)
    {
        try {
            $strError = '';
            $this->_db2->delete('snapshots', ['id' => $arrIds]);
        } catch (Exception $e) {
            $strError = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return $strError;
    }


    public function updateHash($urlId, $urlHash)
    {
        try {
            $arrUpdate = array(
                'hash'    => $urlHash,
                'updated' => date('Y-m-d H:i:s')
            );

            $this->_db2->update('snapshots', $arrUpdate, ['id' => $urlId]);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

}
