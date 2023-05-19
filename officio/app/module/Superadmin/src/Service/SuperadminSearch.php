<?php
namespace Superadmin\Service;

use Exception;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class SuperadminSearch extends BaseService
{

    public function initAdditionalServices(array $services)
    {
    }

    public function getSavedSearches()
    {
        $arrSavedSearches = array();
        try {
            $select = (new Select())
                ->from('superadmin_searches');

            $arrSavedSearches = $this->_db2->fetchAll($select);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrSavedSearches;
    }

    public function saveSearch($searchId, $searchName, $searchQuery)
    {
        $booSuccess = false;
        try {
            $arrToUpdate = array(
                'search_title' => $searchName,
                'search_query' => $searchQuery
            );

            if (empty($searchId)) {
                $this->_db2->insert('superadmin_searches', $arrToUpdate);
            } else {
                $this->_db2->update('superadmin_searches', $arrToUpdate, ['search_id' => (int)$searchId]);
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    public function renameSearch($searchId, $searchName)
    {
        $booSuccess = false;
        try {
            $arrToUpdate = array(
                'search_title' => $searchName
            );

            $this->_db2->update('superadmin_searches', $arrToUpdate, ['search_id' => (int)$searchId]);
            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    public function deleteSearch($searchId)
    {
        $booSuccess = false;
        try {
            $booSuccess = $this->_db2->delete('superadmin_searches', ['search_id' => (int)$searchId]) > 0;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

}
