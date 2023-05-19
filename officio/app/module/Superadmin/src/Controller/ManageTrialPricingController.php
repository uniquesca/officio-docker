<?php

namespace Superadmin\Controller;

use Exception;
use Laminas\Filter\StripTags;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;

/**
 * Manage Trial pricing Controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class ManageTrialPricingController extends BaseController
{

    /** @var Company */
    protected $_company;

    public function initAdditionalServices(array $services) {
        $this->_company = $services[Company::class];
    }

    public function indexAction() {
        $view = new ViewModel();

        $title = $this->_tr->translate('Trial users pricing');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $companyId = 0;

        $arrPriceSettings = array(
            'before_expiration'            => $this->_company->getCompanyPrices($companyId, false),
            'after_expiration'             => $this->_company->getCompanyPrices($companyId, true),
            'cutting_of_service_days'      => $this->_settings->variable_get('cutting_of_service_days', 30),
            'last_charge_failed_show_days' => $this->_settings->variable_get('last_charge_failed_show_days', 5)
        );
        $view->setVariable('arrPriceSettings', $arrPriceSettings);

        return $view;
    }

    public function saveAction() {
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
            // Get data and save in DB
            $filter = new StripTags();
            $arrParams = $this->findParams();

            $arrKeys = array(
                'before_exp_discount_label'       => 'trial_before_exp_discount_label',
                'before_exp_free_users'           => 'trial_before_exp_free_users',
                'before_exp_fee_annual'           => 'trial_before_exp_fee_annual',
                'before_exp_fee_annual_discount'  => 'trial_before_exp_fee_annual_discount',
                'before_exp_fee_monthly'          => 'trial_before_exp_fee_monthly',
                'before_exp_fee_monthly_discount' => 'trial_before_exp_fee_monthly_discount',
                'before_exp_license_annual'       => 'trial_before_exp_license_annual',
                'before_exp_license_monthly'      => 'trial_before_exp_license_monthly',

                'after_exp_discount_label'       => 'trial_after_exp_discount_label',
                'after_exp_free_users'           => 'trial_after_exp_free_users',
                'after_exp_fee_annual'           => 'trial_after_exp_fee_annual',
                'after_exp_fee_annual_discount'  => 'trial_after_exp_fee_annual_discount',
                'after_exp_fee_monthly'          => 'trial_after_exp_fee_monthly',
                'after_exp_fee_monthly_discount' => 'trial_after_exp_fee_monthly_discount',
                'after_exp_license_annual'       => 'trial_after_exp_license_annual',
                'after_exp_license_monthly'      => 'trial_after_exp_license_monthly',

                'cutting_of_service_days'        => 'cutting_of_service_days',
                'last_charge_failed_show_days'   => 'last_charge_failed_show_days',
                'price_training'                 => 'price_training'
            );

            $index     = 0;
            $count     = count($arrKeys);
            $oPurifier = $this->_settings->getHTMLPurifier(false);
            foreach ($arrKeys as $jsKey => $dbKey) {
                // Refresh updated data only once
                $booRefresh = ($index == $count - 1);

                if (in_array($jsKey, array('before_exp_discount_label', 'after_exp_discount_label'))) {
                    $value = $oPurifier->purify($arrParams[$jsKey]);
                } else {
                    $value = $filter->filter($arrParams[$jsKey]);
                }

                $this->_settings->getSystemVariables()->setVariable($dbKey, $value, $booRefresh);
                $index++;
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return Json result
        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('Information was saved successfully.') : $strError
        );
        return $view->setVariables($arrResult);
    }
}