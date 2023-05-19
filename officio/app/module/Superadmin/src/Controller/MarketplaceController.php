<?php

namespace Superadmin\Controller;

use Exception;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;

/**
 * Marketplace Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class MarketplaceController extends BaseController
{

    /** @var Company */
    protected $_company;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
    }

    /**
     * The default action - show roles list
     */
    public function indexAction()
    {
        $view = new ViewModel();

        $title = $this->_tr->translate('Marketplace Profiles (ImmigrationSquare.com)');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        try {
            $companyId          = $this->_auth->getCurrentUserCompanyId();
            $companyDetailsInfo = $this->_company->getCompanyAndDetailsInfo($companyId);
            $booCreateProfile   = $companyDetailsInfo['Status'] == $this->_company->getCompanyIntStatusByString('active');
        } catch (Exception $e) {
            $companyId        = 0;
            $booCreateProfile = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrMarketplaceAccessRights = array(
            'create_new_profile' => $booCreateProfile
        );
        $view->setVariable('arrMarketplaceAccessRights', $arrMarketplaceAccessRights);
        $view->setVariable('company_id', $companyId);

        return $view;
    }

    public function getMarketplaceProfilesListAction()
    {
        $view = new JsonModel();
        $arrResult = array(
            'rows'       => array(),
            'totalCount' => 0
        );

        try {
            $start = (int)$this->findParam('start');
            $limit = (int)$this->findParam('limit');

            if ($this->_auth->isCurrentUserSuperadmin()) {
                $companyId = Json::decode($this->findParam('company_id'), Json::TYPE_ARRAY);
            } else {
                $companyId = $this->_auth->getCurrentUserCompanyId();
            }

            $oMarketplace = $this->_company->getCompanyMarketplace();
            if ($oMarketplace->isMarketplaceModuleEnabledToCompany($companyId)) {
                $arrFilteredResults = $oMarketplace->getMarketplaceProfilesList($companyId, array(), $start, $limit);
                $arrRows            = $arrFilteredResults['rows'];

                $arrResult = array(
                    'rows'                        => array(),
                    'totalCount'                  => $arrFilteredResults['totalCount'],
                    'marketplace_new_profile_url' => $oMarketplace->getMarketplaceUrl($companyId, 0, '', 'create_profile'),
                );
                foreach ($arrRows as $arrRowInfo) {
                    $arrResult['rows'][] = array(
                        'marketplace_profile_id'     => $arrRowInfo['marketplace_profile_id'],
                        'marketplace_profile_name'   => $arrRowInfo['marketplace_profile_name'],
                        'marketplace_profile_status' => $arrRowInfo['marketplace_profile_status'],
                        'marketplace_profile_url'    => $oMarketplace->getMarketplaceUrl($companyId, $arrRowInfo['marketplace_profile_id'], $arrRowInfo['marketplace_profile_key'], 'edit_profile'),
                    );
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables($arrResult);
    }

    public function toggleMarketplaceProfileStatusAction()
    {
        $view = new JsonModel();
        $strError = '';
        try {
            $oMarketplace = $this->_company->getCompanyMarketplace();
            if ($this->_auth->isCurrentUserSuperadmin()) {
                $companyId = Json::decode($this->findParam('company_id'), Json::TYPE_ARRAY);
            } else {
                $companyId = $this->_auth->getCurrentUserCompanyId();
            }

            if (empty($strError) && !$oMarketplace->isMarketplaceModuleEnabledToCompany($companyId)) {
                $strError = $this->_tr->translate('Marketplace module is turned off for the company. Please enable it and try again.');
            }

            $mpProfileId = Json::decode($this->findParam('marketplace_profile_id'), Json::TYPE_ARRAY);
            if (empty($strError)) {
                $arrProfileInfo = $oMarketplace->getMarketplaceProfileInfo($companyId, $mpProfileId);
                if (!is_array($arrProfileInfo) || !isset($arrProfileInfo['company_id'])) {
                    $strError = $this->_tr->translate('Incorrectly selected marketplace profile.');
                }
            }

            if (empty($strError)) {
                $strError = $oMarketplace->toggleMarketplaceProfileStatus($companyId, $mpProfileId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );
        return $view->setVariables($arrResult);
    }
}