<?php

namespace Templates\Service;

use Clients\Service\Clients;
use Exception;
use Laminas\Db\Sql\Select;
use Officio\Common\Json;
use Officio\Common\Service\BaseService;
use Officio\Service\Company;
use Officio\Common\SubServiceInterface;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class TemplatesSettings extends BaseService implements SubServiceInterface
{
    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_clients;

    /** @var Templates */
    protected $_parent;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_clients = $services[Clients::class];
    }

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent() {
        return $this->_parent;
    }

    /**
     * Generate cache id - used when save/load 'templates settings'
     *
     * @param int $companyId
     * @return string cache id
     */
    private static function _getCacheId($companyId)
    {
        return 'company_templates_settings_' . $companyId;
    }

    /**
     * Load 'templates settings' for a specific company
     *
     * @param $companyId
     * @return array
     */
    public function getCompanyTemplatesSettings($companyId)
    {
        // Default settings
        $arrDefaultSettings = array(
            'comfort_letter' => array(
                'current_number' => 0
            )
        );

        try {
            // Use cache to save settings
            if (!($strSavedSettings = $this->_cache->getItem(self::_getCacheId($companyId)))) {
                $select = (new Select())
                    ->from('company_details')
                    ->columns(['templates_settings'])
                    ->where(['company_id' => (int)$companyId]);

                $strSavedSettings = $this->_db2->fetchOne($select);

                $this->_cache->setItem(self::_getCacheId($companyId), $strSavedSettings);
            }

            if (!empty($strSavedSettings)) {
                // Merge with default settings, so not saved data will be used from default
                $arrDefaultSettings = array_replace_recursive($arrDefaultSettings, Json::decode($strSavedSettings, Json::TYPE_ARRAY));
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrDefaultSettings;
    }

    /**
     * Save 'templates settings' for a specific company
     *
     * @param int $companyId
     * @param array $arrSettings
     * @return bool true on success
     */
    public function saveCompanyTemplatesSettings($companyId, $arrSettings)
    {
        $booSuccess = false;
        try {
            if (!is_array($arrSettings) || !count($arrSettings)) {
                $strSettings = null;
            } else {
                $strSettings = Json::encode($arrSettings);
            }

            $this->_company->updateCompanyDetails(
                $companyId,
                array('templates_settings' => $strSettings)
            );

            // Clear cached settings
            $this->_cache->removeItem(self::_getCacheId($companyId));

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Generate letter number for a specific type
     *
     * @param string $type supported: comfort_letter
     * @param int $companyId
     * @param int $caseId
     * @return array of string error, string generated number
     */
    public function generateNewLetterNumber($type, $companyId, $caseId)
    {
        $strError  = '';
        $newNumber = '';

        try {
            $arrCompanySettings = $this->getCompanyTemplatesSettings($companyId);

            switch ($type) {
                case 'comfort_letter':
                    $oConfigSettings = $this->_config['site_version']['custom_templates_settings']['comfort_letter'];

                    if (!empty($oConfigSettings['enabled'])) {
                        if (empty($oConfigSettings['format'])) {
                            $strError = $this->_tr->translate('Comfort letter format not set.');
                        }

                        if (empty($strError) && empty($oConfigSettings['number_format'])) {
                            $strError = $this->_tr->translate('Comfort letter number format not set.');
                        }


                        $investmentType = '';
                        if (empty($strError)) {
                            $investmentTypeFieldId = $this->_clients->getFields()->getCompanyFieldIdByUniqueFieldId('cbiu_investment_type', $companyId);
                            if (!empty($investmentTypeFieldId)) {
                                $investmentTypeFieldValue = $this->_clients->getFields()->getFieldDataValue($investmentTypeFieldId, $caseId);
                                if (!empty($investmentTypeFieldValue)) {
                                    $investmentTypeFieldValueDetails = $this->_clients->getFields()->getDefaultFieldOptionDetails($investmentTypeFieldValue);
                                    switch ($investmentTypeFieldValueDetails['value']) {
                                        case 'Government Fund':
                                            $investmentType = 'ED';
                                            break;

                                        case 'Real Estate':
                                            $investmentType = 'RE';
                                            break;

                                        default:
                                            break;
                                    }
                                }
                            }
                        }


                        if (empty($strError)) {
                            $arrCompanySettings['comfort_letter']['current_number'] += 1;

                            $comfortLetterNumber = str_pad($arrCompanySettings['comfort_letter']['current_number'] ?? '', strlen($oConfigSettings['number_format'] ?? ''), '0', STR_PAD_LEFT);

                            $newNumber = $this->_settings->_sprintf(
                                $oConfigSettings['format'],
                                array(
                                    'investment_type'       => $investmentType,
                                    'comfort_letter_number' => $comfortLetterNumber
                                )
                            );

                            $this->saveCompanyTemplatesSettings($companyId, $arrCompanySettings);
                        }
                    }

                    break;

                default:
                    $strError = $this->_tr->translate('Unsupported letter number type.');
                    break;
            }
        } catch (Exception $e) {
            $strError  = $this->_tr->translate('Internal Error.');
            $newNumber = '';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($strError, $newNumber);
    }
}
