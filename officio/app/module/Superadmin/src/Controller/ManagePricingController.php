<?php

namespace Superadmin\Controller;

use Exception;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Service\PricingCategories;
use Officio\Common\Service\Settings;

/**
 * Manage Pricing Controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class ManagePricingController extends BaseController
{

    /** @var PricingCategories */
    protected $_pricingCategories;

    /** @var Company */
    protected $_company;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_pricingCategories = $services[PricingCategories::class];
    }

    public function indexAction()
    {
        $view = new ViewModel();

        $title = $this->_tr->translate('Manage pricing');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        try {
            $arrSubscriptions = $this->_company->getPackages()->getSubscriptionsList();
        } catch (Exception $e) {
            $arrSubscriptions = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $view->setVariable('arrSubscriptions', $arrSubscriptions);

        return $view;
    }

    public function getPricingCategoriesListAction()
    {
        $view = new JsonModel();
        $arrPricingCategories = array();
        $totalCount = 0;

        try {
            // Get params
            $sort  = $this->findParam('sort');
            $dir   = $this->findParam('dir');
            $start = $this->findParam('start');
            $limit = $this->findParam('limit');

            $arrPricingCategoriesList = $this->_pricingCategories->getPricingCategoriesList($sort, $dir, $start, $limit);
            $arrPricingCategories     = $arrPricingCategoriesList['rows'];
            $totalCount               = $arrPricingCategoriesList['totalCount'];


            $arrSubscriptions = $this->_company->getPackages()->getSubscriptionsList(true);

            //update some field values
            foreach ($arrPricingCategories as &$pricingCategory) {
                $arrPrices = $this->_company->getCompanyPrices(0, false, $pricingCategory['pricing_category_id']);

                foreach ($arrSubscriptions as $subscriptionId) {
                    $name = ucfirst($subscriptionId);

                    $pricingCategory[$subscriptionId . '_price_license_user_annual']  = $arrPrices['package' . $name . 'UserLicenseAnnual'];
                    $pricingCategory[$subscriptionId . '_price_license_user_monthly'] = $arrPrices['package' . $name . 'UserLicenseMonthly'];
                    $pricingCategory[$subscriptionId . '_price_package_2_years']      = $arrPrices['package' . $name . 'FeeBiAnnual'];
                    $pricingCategory[$subscriptionId . '_price_package_monthly']      = $arrPrices['package' . $name . 'FeeMonthly'];
                    $pricingCategory[$subscriptionId . '_price_package_yearly']       = $arrPrices['package' . $name . 'FeeAnnual'];
                    $pricingCategory[$subscriptionId . '_users_add_over_limit']       = $arrPrices['package' . $name . 'AddUsersOverLimit'];
                    $pricingCategory[$subscriptionId . '_user_included']              = $arrPrices['package' . $name . 'FreeUsers'];
                    $pricingCategory[$subscriptionId . '_free_storage']               = $arrPrices['package' . $name . 'FreeStorage'];
                    $pricingCategory[$subscriptionId . '_free_clients']               = $arrPrices['package' . $name . 'FreeClients'];
                }

                $pricingCategory['price_storage_1_gb_monthly']          = $arrPrices['feeStorageMonthly'];
                $pricingCategory['price_storage_1_gb_annual']           = $arrPrices['feeStorageAnnual'];

                $pricingCategory['allow_delete']                        = !($pricingCategory['name'] == 'General');
                $pricingCategory['allow_edit_name']                     = !($pricingCategory['name'] == 'General');
            }
            unset($pricingCategory);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return array result
        $arrResult = array(
            'success'       => $booSuccess,
            'totalCount'    => $totalCount,
            'rows'          => $arrPricingCategories
        );

        return $view->setVariables($arrResult);
    }

    public function saveAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            $view = new ViewModel(
                [
                    'content' => $this->_tr->translate('Insufficient access rights.')
                ]
            );
            $view->setTerminal(true);
            $view->setTemplate('layout/plain');

            return $view;
        }

        $view = new JsonModel();

        $strError = '';
        try {
            $arrPricingCategoryData = array(
                'pricing_category_id'       => (int)$this->findParam('pricing_category_id', 0),
                'name'                      => $this->findParam('name'),
                'expiry_date'               => $this->findParam('expiry_date'),
                'key_string'                => $this->findParam('key_string'),
                'key_message'               => $this->findParam('key_message'),
                'default_subscription_term' => $this->findParam('default_subscription_term'),
                'replacing_general'         => $this->findParam('replacing_general'),
                'pricing_category_details'  => array()
            );

            $arrSubscriptions = $this->_company->getPackages()->getSubscriptionsList(true);
            foreach ($arrSubscriptions as $subscriptionId) {
                $arrPricingCategoryDetails = array(
                    'price_storage_1_gb_annual'  => (double)$this->findParam('price_storage_1_gb_annual'),
                    'price_storage_1_gb_monthly' => (double)$this->findParam('price_storage_1_gb_monthly'),
                    'price_license_user_annual'  => (double)$this->findParam($subscriptionId . '_price_license_user_annual'),
                    'price_license_user_monthly' => (double)$this->findParam($subscriptionId . '_price_license_user_monthly'),
                    'price_package_2_years'      => (double)$this->findParam($subscriptionId . '_price_package_2_years'),
                    'price_package_monthly'      => (double)$this->findParam($subscriptionId . '_price_package_monthly'),
                    'price_package_yearly'       => (double)$this->findParam($subscriptionId . '_price_package_yearly'),
                    'users_add_over_limit'       => (int)$this->findParam($subscriptionId . '_users_add_over_limit'),
                    'user_included'              => (int)$this->findParam($subscriptionId . '_user_included'),
                    'free_storage'               => (int)$this->findParam($subscriptionId . '_free_storage'),
                    'free_clients'               => (int)$this->findParam($subscriptionId . '_free_clients'),
                );

                $arrPricingCategoryData['pricing_category_details'][$subscriptionId] = $arrPricingCategoryDetails;
            }

            if (!Settings::isDateEmpty($arrPricingCategoryData['expiry_date'])) {
                $arrPricingCategoryData['expiry_date'] = date('Y-m-d', strtotime($arrPricingCategoryData['expiry_date']));
            }

            if ($arrPricingCategoryData['pricing_category_id'] == 1) {
                $arrPricingCategoryData['name'] = 'General';
            } elseif ($arrPricingCategoryData['name'] == 'General') {
                $strError = $this->_tr->translate('Name "General" is reserved.');
            }

            if (empty($strError) && !in_array($arrPricingCategoryData['default_subscription_term'], array('annual','monthly'))) {
                $strError = $this->_tr->translate('Incorrectly selected subscription term.');
            }

            if (empty($strError) && !$this->_pricingCategories->savePricingCategory($arrPricingCategoryData)) {
                $strError = $this->_tr->translate('Internal error.');
            }

        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('Information was saved successfully.') : $strError
        );

        return $view->setVariables($arrResult);
    }

    public function deletePricingCategoryAction()
    {
        $view = new JsonModel();
        $strError = '';
        try {
            $pricingCategoryIds = Json::decode($this->findParam('pricing_category_ids'), Json::TYPE_ARRAY);

            if (!$this->_pricingCategories->deletePricingCategories($pricingCategoryIds)) {
                $strError = $this->_tr->translate('Internal error.');
            }

        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return $view->setVariables($arrResult);
    }
}