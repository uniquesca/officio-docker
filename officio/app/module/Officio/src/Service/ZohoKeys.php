<?php

namespace Officio\Service;

use Exception;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ZohoKeys extends BaseService
{

    public $intShowKeysPerPage = 25;

    // Cache id, where we'll save list of active keys
    public static $_cacheId = 'zoho_keys_cache';

    /**
     * Check if Zoho is enabled
     *
     * @return bool true if enabled, otherwise false
     */
    public function isZohoEnabled()
    {
        return isset($this->_config['zoho']['enabled']) && $this->_config['zoho']['enabled'];
    }

    /**
     * Load zoho keys list
     *
     * @param $sort
     * @param $dir
     * @param $start
     * @param $limit
     * @return array
     */
    public function getZohoKeysList($sort, $dir, $start, $limit)
    {
        try {
            if (!in_array($dir, array('ASC', 'DESC'))) {
                $dir = 'DESC';
            }

            switch ($sort) {
                case 'zoho_key_status':
                    $sort = 'k.zoho_key_status';
                    break;

                case 'zoho_key':
                default:
                    $sort = 'k.zoho_key';
                    break;
            }

            if (!is_numeric($start) || $start <= 0) {
                $start = 0;
            }

            if (!is_numeric($limit) || $limit <= 0) {
                $limit = $this->intShowKeysPerPage;
            }

            $select = (new Select())
                ->from(array('k' => 'zoho_keys'))
                ->limit($limit)
                ->offset($start)
                ->order(array($sort . ' ' . $dir));

            $arrZohoKeys  = $this->_db2->fetchAll($select);
            $totalRecords = $this->_db2->fetchResultsCount($select);
        } catch (Exception $e) {
            $arrZohoKeys  = array();
            $totalRecords = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'rows'       => $arrZohoKeys,
            'totalCount' => $totalRecords
        );
    }

    /**
     * Create zoho key
     *
     * @param $zohoKey
     * @param $zohoKeyEnabled
     */
    public function addZohoKey($zohoKey, $zohoKeyEnabled)
    {
        $arrInsert = array(
            'zoho_key'        => $zohoKey,
            'zoho_key_status' => $zohoKeyEnabled,
        );

        $this->_db2->insert('zoho_keys', $arrInsert);
        $this->_cache->removeItem(self::$_cacheId);
    }

    /**
     * Update zoho key details
     *
     * @param $zohoKey
     * @param $arrToUpdate
     * @return void
     */
    public function updateZohoKey($zohoKey, $arrToUpdate)
    {
        $this->_db2->update('zoho_keys', $arrToUpdate, ['zoho_key' => $zohoKey]);
        $this->_cache->removeItem(self::$_cacheId);
    }

    /**
     * Delete key(s)
     *
     * @param array $arrZohoKeys
     * @return bool true on success
     */
    public function deleteZohoKeys($arrZohoKeys)
    {
        $booSuccess = false;
        if (is_array($arrZohoKeys) && count($arrZohoKeys)) {
            $this->_db2->delete('zoho_keys', ['zoho_key' => $arrZohoKeys]);
            $this->_cache->removeItem(self::$_cacheId);
            $booSuccess = true;
        }

        return $booSuccess;
    }

    /**
     * Check if zoho key exists
     *
     * @param string $zohoKey
     * @return bool true if exists
     */
    public function exists($zohoKey)
    {
        $select = (new Select())
            ->from('zoho_keys')
            ->columns(['zoho_key'])
            ->where(['zoho_key' => $zohoKey]);

        $key = $this->_db2->fetchOne($select);

        return !empty($key);
    }

    /**
     * Load active Zoho key from DB
     * (if there are several - one will be selected randomly)
     * @return string
     */
    public function getActiveApiKey()
    {
        $apiKey = '';

        try {
            if (!($arrKeys = $this->_cache->getItem(self::$_cacheId))) {
                // Not in cache
                $select = (new Select())
                    ->from(array('k' => 'zoho_keys'))
                    ->columns(['zoho_key'])
                    ->where(['zoho_key_status' => 'enabled']);

                $arrKeys = $this->_db2->fetchCol($select);
                $this->_cache->setItem(self::$_cacheId, $arrKeys);
            }

            if (is_array($arrKeys) && count($arrKeys)) {
                $apiKey = $arrKeys[mt_rand(0, count($arrKeys) - 1)];
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $apiKey;
    }
}
