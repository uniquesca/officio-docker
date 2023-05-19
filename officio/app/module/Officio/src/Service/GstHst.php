<?php

namespace Officio\Service;

use Exception;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Officio\Common\Service\Country;
use Officio\Common\Service\BaseService;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class GstHst extends BaseService
{

    /** @var Country */
    protected $_country;

    public function initAdditionalServices(array $services)
    {
        $this->_country = $services[Country::class];
    }

    /**
     * Get cache id - place, where cached provinces list will be saved
     * @param $booOfficio
     * @return string
     */
    private function _getCacheId($booOfficio)
    {
        return $booOfficio ? 'hst_officio' : 'hst_companies';
    }


    /**
     * Format GST/HST label in specific format
     * E.g.:
     * -full format:    Alberta GST (5%)
     * -minimal format: HST 5%
     *
     * @Note: float GST/HST will be showed
     *
     * @param array $arrProvinceInfo - information about subscription
     * @param bool $booFullFormat - true to use extended format
     *
     * @return string formatted label
     */
    public function formatGSTLabel($arrProvinceInfo, $booFullFormat = false)
    {
        if ($arrProvinceInfo['is_system'] == 'Y') {
            $strLabel = $arrProvinceInfo['province'];
        } else {
            // Remove trailing zeros
            $rate = preg_replace('/0+$/i', '', $arrProvinceInfo['rate'] ?? '');


            // If there are some numbers after decimal point - show them too
            $intFloatDigits = strcspn(strrev($rate), '.');

            $strFormat = $booFullFormat ? '%1$s %2$s (%3$01.' . $intFloatDigits . 'f%%)' : '%3$01.' . $intFloatDigits . 'f%% %2$s';
            $strLabel  = sprintf($strFormat, $arrProvinceInfo['province'], $arrProvinceInfo['tax_label'], $rate);
        }

        return $strLabel;
    }


    /**
     * Get GST rate by province for specific country
     * @NOTE: Load gst data from Officio GST table
     *
     * @param string $country
     * @param string $provinceLabel
     * @return array of tax rate and tax type
     *         double tax rate - 0 if country is not Canada/Australia, otherwise check for province
     */
    public function getGstByCountryAndProvince($country, $provinceLabel)
    {
        $gstVal      = 0;
        $gstType     = 'included';
        $gstTaxLabel = 'GST';
        if ($this->_country->isDefaultCountry($country)) {
            $arrGstRecords = $this->getProvincesList(true);

            foreach ($arrGstRecords as $arrGstRecordInfo) {
                if ($this->_config['site_version']['version'] == 'australia') {
                    if ($arrGstRecordInfo['is_system'] == 'N' && $arrGstRecordInfo['tax_type'] == 'excluded') {
                        $gstVal      = $arrGstRecordInfo['rate'];
                        $gstType     = $arrGstRecordInfo['tax_type'];
                        $gstTaxLabel = $arrGstRecordInfo['tax_label'];
                        break;
                    }
                } else {
                    if ($arrGstRecordInfo['province'] == $provinceLabel) {
                        $gstVal      = $arrGstRecordInfo['rate'];
                        $gstType     = $arrGstRecordInfo['tax_type'];
                        $gstTaxLabel = $arrGstRecordInfo['tax_label'];
                        break;
                    }
                }
            }
        }

        return array('gst_rate' => $gstVal, 'gst_type' => $gstType, 'gst_tax_label' => $gstTaxLabel);
    }

    public function calculateGstAndSubtotal($gstType, $gstRate, $subtotal)
    {
        switch ($gstType) {
            case 'excluded':
                $gst = $subtotal * $gstRate / 100;
                break;

            case 'included':
                // x + x * gst / 100 = amount
                // so x = amount - amount / (1 + gst/100)
                $gst      = $subtotal - $subtotal / (1 + $gstRate / 100);
                $subtotal -= $gst;
                break;

            case 'exempt':
            default:
                $gst = 0;
                break;
        }

        return array('gst' => round((double)$gst, 2), 'subtotal' => round((double)$subtotal, 2));
    }


    /**
     * Update provinces list
     *
     * @param array $arrUpdate - array with province info to update
     * @param bool $booOfficio - true if Officio GST/HST table must be updated
     *
     * @return bool true if all changes were applied correctly
     */
    public function saveProvinces($arrUpdate, $booOfficio = false)
    {
        $booResult = false;

        try {
            foreach ($arrUpdate as $province_id => $arrUpdateData) {
                // Update companies and prospects state if it was changed
                if (!$booOfficio) {
                    $arrProvinceInfo   = $this->getProvinceById($province_id);
                    $labelBeforeUpdate = is_array($arrProvinceInfo) && array_key_exists('province', $arrProvinceInfo) ? $arrProvinceInfo['province'] : '';
                    $labelAfterUpdate  = is_array($arrUpdateData) && array_key_exists('province', $arrUpdateData) ? $arrUpdateData['province'] : '';

                    if (!empty($labelBeforeUpdate) && !empty($labelAfterUpdate) && $labelBeforeUpdate != $labelAfterUpdate) {
                        $arrWhere = array();
                        $arrWhere['state'] = $labelBeforeUpdate;
                        $arrWhere[] = (new Where())
                            ->nest()
                            ->equalTo('country', $this->_country->getDefaultCountryId())
                            ->or
                            ->equalTo('country', $this->_country->getDefaultCountryIsoCode())
                            ->unnest();

                        $this->_db2->update('company', ['state' => $arrUpdateData['province']], $arrWhere);
                        $this->_db2->update('prospects', ['state' => $arrUpdateData['province']], $arrWhere);
                    }
                }

                // Update related GST/HST table
                $this->_db2->update(
                    $booOfficio ? 'hst_officio' : 'hst_companies',
                    $arrUpdateData,
                    ['province_id' => (int)$province_id]
                );

                // Reset the cache
                $cacheId = $this->_getCacheId($booOfficio);
                $this->_cache->removeItem($cacheId);

                $booResult = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booResult;
    }

    /**
     * Load information about specific province by its id
     *
     * @param int $provinceId
     * @return array with province's info
     */
    public function getProvinceById($provinceId)
    {
        $arrResult = array();
        if (!empty($provinceId)) {
            $arrProvinces = $this->getProvincesList();
            if (!empty($arrProvinces)) {
                foreach ($arrProvinces as $arrProvinceInfo) {
                    if ($arrProvinceInfo['province_id'] == $provinceId) {
                        $arrResult = $arrProvinceInfo;
                        break;
                    }
                }
            }
        }

        return $arrResult;
    }


    /**
     * Load taxes list in special format
     * E.g. Alberta GST (5%)
     *
     * @return array of formatted taxes
     */
    public function getTaxesList()
    {
        $arrResult = array();

        $arrProvinces = $this->getProvincesList();
        if (!empty($arrProvinces)) {
            foreach ($arrProvinces as $arrProvinceInfo) {
                $label       = $this->formatGSTLabel($arrProvinceInfo, true);
                $arrResult[] = array($arrProvinceInfo['province_id'], $label);
            }
        }

        return $arrResult;
    }


    /**
     * Load provinces list
     * @param bool $booOfficio - true if Officio GST/HST table must be used
     * @param bool $booDoNotLoadSystemRecords - true if don't load system records
     * @return array with provinces list
     */
    public function getProvincesList($booOfficio = false, $booDoNotLoadSystemRecords = false)
    {
        $arrResult = array();

        $cacheId = $this->_getCacheId($booOfficio);
        if (!($arrProvinces = $this->_cache->getItem($cacheId))) {
            $select = (new Select())
                ->from($booOfficio ? 'hst_officio' : 'hst_companies')
                ->order(array('province_order', 'province'));

            $arrProvinces = $this->_db2->fetchAll($select);
            $this->_cache->setItem($cacheId, $arrProvinces);
        }

        if ($booDoNotLoadSystemRecords) {
            foreach ($arrProvinces as $key => $arrProvinceInfo) {
                if ($arrProvinceInfo['is_system'] != 'N') {
                    unset($arrProvinces[$key]);
                }
            }
        }

        foreach ($arrProvinces as $arrProvinceInfo) {
            $arrResult[$arrProvinceInfo['province_id']] = $arrProvinceInfo;
        }

        return $arrResult;
    }


    /**
     * Load taxes rates for provinces
     *
     * @return array with taxes rates
     */
    public function getProvincesTaxes()
    {
        $arrProvinces = $this->getProvincesList();
        $arrTaxes     = array();

        if (!empty($arrProvinces)) {
            foreach ($arrProvinces as $arrProvinceInfo) {
                $arrTaxes[$arrProvinceInfo['province_id']] = $arrProvinceInfo['rate'];
            }
        }

        return $arrTaxes;
    }
}
