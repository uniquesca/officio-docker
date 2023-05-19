<?php

namespace Officio\Service\Company;

use Exception;
use Laminas\Db\Sql\Select;
use Laminas\Http\Client;
use Officio\Common\Json;
use Laminas\Uri\UriFactory;
use Officio\Common\Service\BaseService;
use Officio\Comms\Service\Mailer;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\Roles;
use Officio\Common\SubServiceInterface;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class CompanyMarketplace extends BaseService implements SubServiceInterface
{

    /** @var Company */
    private $_parent;

    /** @var Roles */
    protected $_roles;

    /** @var Encryption */
    protected $_encryption;

    /** @var Mailer */
    protected $_mailer;

    public function initAdditionalServices(array $services)
    {
        $this->_roles      = $services[Roles::class];
        $this->_encryption = $services[Encryption::class];
        $this->_mailer     = $services[Mailer::class];
    }

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent() {
        return $this->_parent;
    }

    /**
     * Send request with params to MP web site
     *
     * @param $arrProfiles
     * @return string empty on success, otherwise contains error message
     */
    private function sendRequestToMP($arrProfiles)
    {
        $strError = '';

        try {
            $url = $this->_config['marketplace']['toggle_status_url'];
            if (empty($strError) && !UriFactory::factory($url)->isValid()) {
                $strError = $this->_tr->translate('A correct url to MP web site must be set in the config file.');
            }

            if (empty($strError)) {
                $client = new Client();
                $client->setUri($url);
                $client->setOptions(
                    array(
                        'maxredirects' => 0,
                        'timeout'      => 30
                    )
                );

                // Custom header, will be checked during auth
                $client->setHeaders(['X-Officio'=> '1.0']);

                // Encrypt data
                $arrParams = array();
                $arrParams['profiles'] = $arrProfiles;
                $arrParams['expire_on'] = gmdate('c', strtotime('+ 1 minute'));

                $arrHashedParams = array(
                    'hash' => $this->_encryption->customEncrypt(
                        Json::encode($arrParams),
                        $this->_config['marketplace']['key'],
                        $this->_config['marketplace']['private_pem'],
                        $this->_config['marketplace']['public_pem']
                    )
                );
                $client->setParameterPost($arrHashedParams);

                // Preforming a POST request
                $client->setMethod('POST');
                $response = $client->send();
                $body = $response->getBody();
                try {
                    $arrResult = Json::decode($body, Json::TYPE_ARRAY);
                    if (!isset($arrResult['success'])) {
                        $strError = $this->_tr->translate('Incorrect response from MP web site.');
                    } elseif (!$arrResult['success']) {
                        $strError = $arrResult['message'];
                    }
                } catch (Exception $e) {
                    $strError = $this->_tr->translate('Incorrect response from MP web site.');
                    $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString() . PHP_EOL . 'Params: ' . print_r($arrParams, true) . PHP_EOL . 'Body: ' . print_r($body, true));
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

    /**
     * Check if "Marketplace Module" is enabled to specific company
     *
     * @param $companyId
     * @return bool true if enabled
     */
    public function isMarketplaceModuleEnabledToCompany($companyId)
    {
        $booHasAccessToMarketplace = false;
        if (empty($companyId)) {
            $booHasAccessToMarketplace = true;
        } else {
            $arrCompanyInfo = $this->_parent->getCompanyDetailsInfo($companyId);
            if (isset($arrCompanyInfo['marketplace_module_enabled'])) {
                $booHasAccessToMarketplace = $arrCompanyInfo['marketplace_module_enabled'] == 'Y';
            }
        }

        return $booHasAccessToMarketplace;
    }

    /**
     * Toggle access to Marketplace for specific company
     *
     * @param int $companyId
     * @param bool $booActivate
     * @param bool $booSuperAdmin
     * @return bool
     */
    public function toggleMarketplace($companyId, $booActivate, $booSuperAdmin = false)
    {
        return $this->_roles->toggleModuleAccess($companyId, $booActivate, $booSuperAdmin, 'marketplace', 'manage')
            && $this->_roles->toggleModuleAccess($companyId, $booActivate, $booSuperAdmin, 'marketplace', 'view');
    }


    /**
     * Add/update MP profile for specific company
     *
     * @param int $companyId
     * @param int $mpProfileId
     * @param string $mpProfileName
     * @param string $mpProfileStatus
     * @param $mpProfileKey
     * @return bool true on success
     */
    public function updateMarketplaceProfileStatus($companyId, $mpProfileId, $mpProfileKey, $mpProfileName, $mpProfileStatus)
    {
        try {
            $query = "INSERT IGNORE INTO `company_marketplace_profiles` (`company_id`, `marketplace_profile_id`, `marketplace_profile_key`, 
                        `marketplace_profile_name`, `marketplace_profile_status`,`marketplace_profile_created_on`) 
                        VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE marketplace_profile_name = VALUES(marketplace_profile_name), marketplace_profile_status = VALUES(marketplace_profile_status)";
            $this->_db2->query($query, [$companyId, $mpProfileId, $mpProfileKey, $mpProfileName, $mpProfileStatus, date('c')]);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }


    /**
     * Load list of MP profile list assigned to the company
     *
     * @param int $companyId
     * @param array $arrLoadFields
     * @param int $start
     * @param int $limit
     * @param bool $booActiveOnly
     * @return array
     */
    public function getMarketplaceProfilesList($companyId, $arrLoadFields = array(), $start = 0, $limit = 0, $booActiveOnly = false)
    {
        try {
            $select = (new Select())
                ->from(array('mp' => 'company_marketplace_profiles'))
                ->where(['mp.company_id' => (int)$companyId])
                ->order('mp.marketplace_profile_created_on');

            if (!empty($limit)) {
                $start = $start < 0 || $start > 1000000 ? 0 : $start;
                $limit = $limit < 0 || $limit > 1000000 ? 0 : $limit;
                $select->limit($limit);
                $select->offset($start);
            }

            if ($booActiveOnly) {
                $select->where(['marketplace_profile_status' => 'active']);
            }

            $arrRows    = $this->_db2->fetchAll($select);
            $totalCount = $this->_db2->fetchResultsCount($select);

            // Return only specific fields
            if (is_array($arrLoadFields) && count($arrLoadFields)) {
                foreach ($arrRows as $mainKey => $arrRowInfo) {
                    $arrKeys = array_keys($arrRowInfo);
                    foreach ($arrKeys as $key) {
                        if (!in_array($key, $arrLoadFields)) {
                            unset($arrRows[$mainKey][$key]);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $arrRows    = array();
            $totalCount = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'rows'       => $arrRows,
            'totalCount' => $totalCount
        );
    }

    /**
     * Load saved MP profile information
     *
     * @param int $companyId
     * @param int $mpProfileId
     * @return array
     */
    public function getMarketplaceProfileInfo($companyId, $mpProfileId)
    {
        try {
            $select = (new Select())
                ->from(array('mp' => 'company_marketplace_profiles'))
                ->where([
                    'mp.company_id'             => (int)$companyId,
                    'mp.marketplace_profile_id' => (int)$mpProfileId
                ]);

            $arrResult = $this->_db2->fetchRow($select);
        } catch (Exception $e) {
            $arrResult = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrResult;
    }


    /**
     * Send request to MP web site to toggle MP profile status
     *
     * @param int $companyId
     * @param int $mpProfileId
     * @return string error - empty on success
     */
    public function toggleMarketplaceProfileStatus($companyId, $mpProfileId)
    {
        $strError = '';
        try {
            $mpProfileKey = $mpProfileNewStatus = $mpProfileName = '';
            if (empty($strError)) {
                $arrProfileInfo = $this->getMarketplaceProfileInfo($companyId, $mpProfileId);
                if (!is_array($arrProfileInfo) || !isset($arrProfileInfo['company_id'])) {
                    $strError = $this->_tr->translate('Incorrect incoming info (company id and profile id).');
                } else {
                    $mpProfileKey  = $arrProfileInfo['marketplace_profile_key'];
                    $mpProfileName = $arrProfileInfo['marketplace_profile_name'];
                    switch ($arrProfileInfo['marketplace_profile_status']) {
                        case 'active':
                            $mpProfileNewStatus = 'inactive';
                            break;

                        case 'inactive':
                        case 'suspended':
                            $mpProfileNewStatus = 'active';
                            break;

                        default:
                            $strError = $this->_tr->translate('Unsupported profile status.');
                            break;
                    }
                }
            }

            if (empty($strError)) {
                $arrParams   = array();
                $arrParams[] = array(
                    'company_id'                 => $companyId,
                    'marketplace_profile_id'     => $mpProfileId,
                    'marketplace_profile_key'    => $mpProfileKey,
                    'marketplace_profile_status' => $mpProfileNewStatus,
                );

                $strError = $this->sendRequestToMP($arrParams);
            }

            if (empty($strError) && !$this->updateMarketplaceProfileStatus($companyId, $mpProfileId, $mpProfileKey, $mpProfileName, $mpProfileNewStatus)) {
                $strError = $this->_tr->translate('Internal error. Please try again later.');
            }

        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

    /**
     * Load MP url with hashed params
     *
     * @param int $companyId
     * @param int $mpProfileId
     * @param $mpProfileKey
     * @param string $actionType
     * @return string
     */
    public function getMarketplaceUrl($companyId, $mpProfileId, $mpProfileKey, $actionType)
    {
        $strUrl = '';
        switch ($actionType) {
            case 'create_profile':
                $strUrl = $this->_config['marketplace']['create_profile_url'];
                break;

            case 'edit_profile':
                $strUrl = $this->_config['marketplace']['edit_profile_url'];
                break;

            default:
                break;
        }

        if (!empty($strUrl) && UriFactory::factory($strUrl)->isValid()) {
            $arrParams = array(
                'expire_on'               => gmdate('c', strtotime('+ 1 minute')),
                'company_id'              => $companyId,
                'marketplace_profile_id'  => $mpProfileId,
                'marketplace_profile_key' => $mpProfileKey,
                'action'                  => $actionType,
            );

            $hash = $this->_encryption->customEncrypt(
                Json::encode($arrParams),
                $this->_config['marketplace']['key'],
                $this->_config['marketplace']['private_pem'],
                $this->_config['marketplace']['public_pem']
            );

            $strUrl .= '?hash=' . urlencode($hash);
        }

        return $strUrl;
    }

    /**
     * Restore previously saved statuses (in Officio and on MP side)
     *
     * @param int $companyId
     * @return bool true on success
     */
    private function restoreProfilesStatuses($companyId)
    {
        try {
            $arrSavedProfiles = $this->getMarketplaceProfilesList($companyId);
            $arrSavedProfiles = $arrSavedProfiles['rows'];

            // Restore previously saved statuses
            $arrParams = array();
            foreach ($arrSavedProfiles as $arrSavedProfileInfo) {
                $mpProfileNewStatus = $arrSavedProfileInfo['marketplace_profile_old_status'];
                if (!empty($mpProfileNewStatus)) {
                    $this->_db2->update(
                        'company_marketplace_profiles',
                        [
                            'marketplace_profile_status'     => $mpProfileNewStatus,
                            'marketplace_profile_old_status' => null
                        ],
                        [
                            'company_id'             => (int)$companyId,
                            'marketplace_profile_id' => (int)$arrSavedProfileInfo['marketplace_profile_id']
                        ]
                    );

                    $arrParams[] = array(
                        'company_id'                 => $companyId,
                        'marketplace_profile_id'     => $arrSavedProfileInfo['marketplace_profile_id'],
                        'marketplace_profile_key'    => $arrSavedProfileInfo['marketplace_profile_key'],
                        'marketplace_profile_status' => $mpProfileNewStatus,
                    );
                }
            }

            $booSuccess = true;
            if (count($arrParams)) {
                // Send request to MP to restore old status
                $strError = $this->sendRequestToMP($arrParams);
                if (!empty($strError)) {
                    // On error - send email
                    $subject = 'Error on MP profiles restoring';
                    $message = '<pre>Error generated during MP profiles restoring.' . PHP_EOL . PHP_EOL;
                    $message .= '<h3>REQUEST:</h3>' . print_r($arrParams, true) . PHP_EOL . PHP_EOL;
                    $message .= '<h3>RESPONSE:</h3>' . $strError;
                    $message .= '</pre';
                    $this->_mailer->sendEmailToSupport($subject, $message);
                    $booSuccess = false;
                }
            }
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Backup profiles statuses and mark them as suspended (in Officio and on MP side)
     *
     * @param int $companyId
     * @return bool true on success
     */
    private function markProfilesAsSuspended($companyId)
    {
        try {
            $arrSavedProfiles = $this->getMarketplaceProfilesList($companyId);
            $arrSavedProfiles = $arrSavedProfiles['rows'];

            // Backup old status, mark as suspended
            $arrParams = array();
            foreach ($arrSavedProfiles as $arrSavedProfileInfo) {
                $mpProfileNewStatus = 'suspended';
                if ($arrSavedProfileInfo['marketplace_profile_status'] == $mpProfileNewStatus) {
                    continue;
                }

                $this->_db2->update(
                    'company_marketplace_profiles',
                    [
                        'marketplace_profile_status'     => $mpProfileNewStatus,
                        'marketplace_profile_old_status' => $arrSavedProfileInfo['marketplace_profile_status']
                    ],
                    [
                        'company_id'             => (int)$companyId,
                        'marketplace_profile_id' => (int)$arrSavedProfileInfo['marketplace_profile_id']
                    ]
                );

                $arrParams[] = array(
                    'company_id'                 => $companyId,
                    'marketplace_profile_id'     => $arrSavedProfileInfo['marketplace_profile_id'],
                    'marketplace_profile_key'    => $arrSavedProfileInfo['marketplace_profile_key'],
                    'marketplace_profile_status' => $mpProfileNewStatus,
                );
            }

            $booSuccess = true;
            if (count($arrParams)) {
                // Send request to MP to mark as suspended
                $strError = $this->sendRequestToMP($arrParams);
                if (!empty($strError)) {
                    // On error - send email
                    $subject = 'Error on MP profiles suspending';
                    $message = '<pre>Error generated during MP profiles suspending.' . PHP_EOL . PHP_EOL;
                    $message .= '<h3>REQUEST:</h3>' . print_r($arrParams, true) . PHP_EOL . PHP_EOL;
                    $message .= '<h3>RESPONSE:</h3>' . $strError;
                    $message .= '</pre';
                    $this->_mailer->sendEmailToSupport($subject, $message);
                    $booSuccess = false;
                }
            }
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Toggle saved MP profiles statuses in relation to the company status and MP module status
     *
     * @param int $companyId
     * @param string $companyOldStatus 'active', 'inactive', 'suspended'
     * @param string $companyNewStatus 'active', 'inactive', 'suspended'
     * @param string $mpModuleOldStatus 'Y','N'
     * @param string $mpModuleNewStatus 'Y','N'
     * @return bool
     */
    public function toggleAccessToMarketplaceProfiles($companyId, $companyOldStatus, $companyNewStatus, $mpModuleOldStatus, $mpModuleNewStatus)
    {
        try {
            $booSuccess = true;

            $booCompanyOldStatusActive  = strtolower($companyOldStatus ?? '') == 'active';
            $booCompanyNewStatusActive  = strtolower($companyNewStatus ?? '') == 'active';
            $booMPModuleOldStatusActive = $mpModuleOldStatus == 'Y';
            $booMPModuleNewStatusActive = $mpModuleNewStatus == 'Y';

            if (
                ($booCompanyOldStatusActive === $booCompanyNewStatusActive && $booMPModuleOldStatusActive === $booMPModuleNewStatusActive) ||
                (!$booCompanyOldStatusActive && !$booCompanyNewStatusActive && $booMPModuleOldStatusActive && !$booMPModuleNewStatusActive) ||
                (!$booCompanyOldStatusActive && !$booCompanyNewStatusActive && !$booMPModuleOldStatusActive && $booMPModuleNewStatusActive) ||
                ($booCompanyOldStatusActive && !$booCompanyNewStatusActive && !$booMPModuleOldStatusActive && !$booMPModuleNewStatusActive) ||
                (!$booCompanyOldStatusActive && $booCompanyNewStatusActive && !$booMPModuleOldStatusActive && !$booMPModuleNewStatusActive) ||
                ($booCompanyOldStatusActive && !$booCompanyNewStatusActive && !$booMPModuleOldStatusActive && $booMPModuleNewStatusActive) ||
                (!$booCompanyOldStatusActive && $booCompanyNewStatusActive && $booMPModuleOldStatusActive && !$booMPModuleNewStatusActive)
            ) {
                // Do nothing
            } elseif (
                ($booCompanyOldStatusActive && $booCompanyNewStatusActive && $booMPModuleOldStatusActive && !$booMPModuleNewStatusActive) ||
                ($booCompanyOldStatusActive && !$booCompanyNewStatusActive && $booMPModuleOldStatusActive && $booMPModuleNewStatusActive) ||
                ($booCompanyOldStatusActive && !$booCompanyNewStatusActive && $booMPModuleOldStatusActive && !$booMPModuleNewStatusActive)
            ) {
                // Mark profiles as suspended and save previously set statuses
                $booSuccess = $this->markProfilesAsSuspended($companyId);
            } elseif (
                ($booCompanyOldStatusActive && $booCompanyNewStatusActive && !$booMPModuleOldStatusActive && $booMPModuleNewStatusActive) ||
                (!$booCompanyOldStatusActive && $booCompanyNewStatusActive && $booMPModuleOldStatusActive && $booMPModuleNewStatusActive) ||
                (!$booCompanyOldStatusActive && $booCompanyNewStatusActive && !$booMPModuleOldStatusActive && $booMPModuleNewStatusActive)
            ) {
                // Restore statuses before company was disabled or before MP module was turned off
                $booSuccess = $this->restoreProfilesStatuses($companyId);
            }
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }
}
