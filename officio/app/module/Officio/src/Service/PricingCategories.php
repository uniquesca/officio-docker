<?php

namespace Officio\Service;

use Exception;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;
use Officio\Common\Service\Settings;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class PricingCategories extends BaseService
{

    /**
     * Get pricing category id by key
     *
     * @param int $id
     * @return array
     */
    public function getPricingCategory($id)
    {
        $select = (new Select())
            ->from('pricing_categories')
            ->where(['pricing_category_id' => (int)$id]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Get pricing category id by key
     *
     * @param string $key
     * @return array
     */
    public function getPricingCategoryByKey($key)
    {
        $select = (new Select())
            ->from('pricing_categories')
            ->where(['key_string' => $key]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Get replacing general pricing category
     *
     * @return array
     */
    public function getReplacingGeneralPricingCategory()
    {
        $select = (new Select())
            ->from('pricing_categories')
            ->where(['replacing_general' => 'Y'])
            ->order('pricing_category_id DESC');

        return $this->_db2->fetchRow($select);
    }

    /**
     * Get pricing category id by name
     *
     * @param string $name
     * @return int
     */
    public function getPricingCategoryIdByName($name)
    {
        $select = (new Select())
            ->from('pricing_categories')
            ->columns(['pricing_category_id'])
            ->where(['name' => $name]);

        return $this->_db2->fetchOne($select);
    }

    /**
     * Load pricing category details
     *
     * @param int $pricingCategoryId
     * @param string $subscriptionId
     * @return array
     */
    public function getPricingCategoryDetails($pricingCategoryId, $subscriptionId)
    {
        $subscriptionId = empty($subscriptionId) ? 'lite' : $subscriptionId;

        // For specific subscriptions load settings from their "twins"
        switch ($subscriptionId) {
            case 'pro13':
                $subscriptionId = 'pro';
                break;

            case 'ultimate_plus':
                $subscriptionId = 'ultimate';
                break;

            default:
                break;
        }

        $select = (new Select())
            ->from('pricing_category_details')
            ->where([
                'pricing_category_id' => (int)$pricingCategoryId,
                'subscription_id'     => $subscriptionId
            ]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Save pricing category and its details
     *
     * @param array $arrData
     * @return int pricing category id, empty on error
     */
    public function savePricingCategory($arrData)
    {
        $pricingCategoryId = 0;

        try {
            $arrPricingCategory = array(
                'name'                      => $arrData['name'],
                'expiry_date'               => !Settings::isDateEmpty($arrData['expiry_date']) ? $arrData['expiry_date'] : null,
                'key_string'                => $arrData['key_string'],
                'key_message'               => $arrData['key_message'],
                'default_subscription_term' => $arrData['default_subscription_term'],
                'replacing_general'         => $arrData['replacing_general'] === 'on' ? 'Y' : 'N'
            );

            if (empty($arrData['pricing_category_id'])) {
                $pricingCategoryId = $this->_db2->insert('pricing_categories', $arrPricingCategory);
            } else {
                $pricingCategoryId = $arrData['pricing_category_id'];
                $this->_db2->update(
                    'pricing_categories',
                    $arrPricingCategory,
                    ['pricing_category_id' => (int)$pricingCategoryId]
                );
            }

            if (!empty($pricingCategoryId)) {
                $this->savePricingCategoryDetails($pricingCategoryId, $arrData['pricing_category_details']);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $pricingCategoryId;
    }

    /**
     * Save pricing category details
     *
     * @param int $pricingCategoryId
     * @param array $arrPricingCategoryDetails
     */
    public function savePricingCategoryDetails($pricingCategoryId, $arrPricingCategoryDetails)
    {
        try {
            $this->_db2->delete('pricing_category_details', ['pricing_category_id' => (int)$pricingCategoryId]);

            foreach ($arrPricingCategoryDetails as $subscriptionId => $pricingCategoryDetail) {
                $pricingCategoryDetail['pricing_category_id'] = $pricingCategoryId;
                $pricingCategoryDetail['subscription_id']     = $subscriptionId;
                $this->_db2->insert('pricing_category_details', $pricingCategoryDetail);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * Load pricing categories list
     *
     * @param $sort
     * @param $dir
     * @param $start
     * @param $limit
     * @return array
     */
    public function getPricingCategoriesList($sort, $dir, $start, $limit)
    {
        if (!in_array($dir, array('ASC', 'DESC'))) {
            $dir = 'DESC';
        }

        // All possible pricing categories fields (can be used for sorting)
        $arrColumns = array(
            'name', 'expiry_date', 'key_string', 'key_message', 'default_subscription_term', 'replacing_general'
        );

        if (in_array($sort, $arrColumns)) {
            $sort = 'p.' . $sort;
        } else {
            $sort = 'p.pricing_category_id';
        }

        if (!is_numeric($start) || $start <= 0) {
            $start = 0;
        }

        if (!is_numeric($limit) || $limit <= 0) {
            $limit = 25;
        }

        $select = (new Select())
            ->from(array('p' => 'pricing_categories'))
            ->limit($limit)
            ->offset($start)
            ->order(array($sort . ' ' . $dir));

        $arrPricingCategories = $this->_db2->fetchAll($select);
        $totalRecords         = $this->_db2->fetchResultsCount($select);

        return array(
            'rows'       => $arrPricingCategories,
            'totalCount' => $totalRecords
        );
    }

    /**
     * Delete pricing categories by their ids
     *
     * @param $pricingCategoryIds
     * @return bool
     */
    public function deletePricingCategories($pricingCategoryIds)
    {
        $booSuccess = false;
        try {
            $this->_db2->delete('pricing_categories', ['pricing_category_id' => $pricingCategoryIds]);

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }
}
