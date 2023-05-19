<?php

namespace Officio\Controller;

use Clients\Service\Clients;
use Exception;
use Officio\Common\Json;
use Laminas\View\Model\ViewModel;
use Laminas\View\Model\JsonModel;
use Officio\BaseController;
use Laminas\Filter\StripTags;
use Files\Service\Files;
use Officio\Service\Company;
use Officio\Common\Service\Settings;

/**
 * TrialController - controller related to company account and its status
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class TrialController extends BaseController
{

    /** @var string currency saved in settings */
    private $_currency;

    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_clients;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_clients = $services[Clients::class];
        $this->_currency = strtolower($this->_settings->getCurrentCurrency());
    }
    
    public function indexAction() {
        $view = new ViewModel();
        $view->setTerminal(true);

        $companyId = $this->_auth->getCurrentUserCompanyId();

        $arrCompanyInfo = $this->_company->getCompanyAndDetailsInfo($companyId);

        // Calculate GST based on company's info
        $view->setVariable('companyGst', $arrCompanyInfo['gst_used']);
        $view->setVariable('companyGstTaxLabel', $arrCompanyInfo['gst_tax_label']);
        $view->setVariable('companyGstType', $arrCompanyInfo['gst_type']);
        $view->setVariable('companyGstDefaultType', $arrCompanyInfo['gst_default_type']);

        $booExpired = true;
        $expDate = $arrCompanyInfo['next_billing_date'];
        if(!empty($expDate)) {
            if(strtotime($expDate) > time()) {
                $booExpired = false;
            }
            $strExpirationDate = date($this->_settings->variable_get('dateFormatFull'), strtotime($expDate));
        } else {
            $strExpirationDate = 'Unknown';
        }

        $view->setVariable('expirationDate', $strExpirationDate);
        $view->setVariable('booExpired', $booExpired);

        // Load prices for this company (in relation to expiration status)
        $arrPrices = $this->_company->getCompanyPrices($companyId, $booExpired, $arrCompanyInfo['pricing_category_id']);
        $view->setVariable('freeUsers', $arrPrices['freeUsers']);
        $view->setVariable('activeUsers', $arrPrices['activeUsers']);

        $view->setVariable('feeAnnual', $arrPrices['feeAnnual']);
        $view->setVariable('feeMonthly', $arrPrices['feeMonthly']);

        $view->setVariable('feeAnnualPerMonthFormatted', $this->_clients->getAccounting()::formatPrice($arrPrices['feeAnnual'] / 12, $this->_currency));
        $view->setVariable('feeAnnualFormatted', $this->_clients->getAccounting()::formatPrice($arrPrices['feeAnnual'], $this->_currency));
        $view->setVariable('feeMonthlyFormatted', $this->_clients->getAccounting()::formatPrice($arrPrices['feeMonthly'], $this->_currency));

        $view->setVariable('licenseAnnual', $arrPrices['licenseAnnual']);
        $view->setVariable('licenseMonthly', $arrPrices['licenseMonthly']);
        $view->setVariable('discountLabel', $arrPrices['discountLabel']);

        $view->setVariable('currentCurrency', $this->_currency);

        return $view;
    }


    public function renewAction() {
        $view = new ViewModel();
        $view->setTerminal(true);

        $companyId = $this->_auth->getCurrentUserCompanyId();

        $arrCompanyInfo = $this->_company->getCompanyAndDetailsInfo($companyId);

        // Calculate GST based on company's info
        $view->setVariable('companyGst', $arrCompanyInfo['gst_used']);
        $view->setVariable('companyGstTaxLabel', $arrCompanyInfo['gst_tax_label']);
        $view->setVariable('companyGstType', $arrCompanyInfo['gst_type']);
        $view->setVariable('companyGstDefaultType', $arrCompanyInfo['gst_default_type']);

        // Check if account is expired or not
        $booExpired = true;
        $expDate = $arrCompanyInfo['next_billing_date'];
        if(!empty($expDate)) {
            if(strtotime($expDate) > time()) {
                $booExpired = false;
            }
            $strExpirationDate = date($this->_settings->variable_get('dateFormatFull'), strtotime($expDate));
        } else {
            $strExpirationDate = 'Unknown';
        }

        $view->setVariable('expirationDate', $strExpirationDate);
        $view->setVariable('booExpired', $booExpired);
        
        // Load company current selected plan
        $companyPlan = $arrCompanyInfo['payment_term'] == '1' ? 'monthly' : 'annual';
        $view->setVariable('companyPlan', $companyPlan);

        // Load prices for this company (in relation to expiration status)
        $arrPrices = $this->_company->getCompanyPrices($companyId, $booExpired, $arrCompanyInfo['pricing_category_id']);
        $view->setVariable('freeUsers', $arrCompanyInfo['free_users']);
        $view->setVariable('activeUsers', $arrPrices['activeUsers']);

        
        // Load storage settings
        $storageUsed = Files::formatSizeInGb($arrCompanyInfo['storage_today'] * 1024);
        $freeStorage = empty($arrCompanyInfo['free_storage']) ? 2 : $arrCompanyInfo['free_storage'];
        
        $view->setVariable('activeStorage', $storageUsed);
        $view->setVariable('freeStorage', $freeStorage);
        $view->setVariable('feeStorageMonthly', $arrPrices['feeStorageMonthly']);
        $view->setVariable('feeStorageAnnual', $arrPrices['feeStorageAnnual']);


        // Load subscription fees
        list($feeMonthly, $feeAnnual) = $this->_company->getCompanySubscriptionPrices($arrCompanyInfo);
        $view->setVariable('feeAnnual', $feeAnnual);
        $view->setVariable('feeMonthly', $feeMonthly);
        
        // Load user license settings
        $view->setVariable('licenseAnnual', $this->_company->getUserPrice(2, $arrCompanyInfo['subscription']));
        $view->setVariable('licenseMonthly', $this->_company->getUserPrice(1, $arrCompanyInfo['subscription']));
        
        $view->setVariable('currentCurrency', $this->_currency);

        return $view;
    }

    public function saveAction()
    {
        $strError          = '';
        $strSuccessMessage = '';

        try {
            $filter = new StripTags();

            // Get and check incoming params
            $arrParams = array(
                'submission_type' => $filter->filter(Json::decode($this->params()->fromPost('submission_type'), Json::TYPE_ARRAY)),
                'cc_type'         => $filter->filter(Json::decode($this->params()->fromPost('cc_type'), Json::TYPE_ARRAY)),
                'cc_name'         => $filter->filter(trim(Json::decode($this->params()->fromPost('cc_name', ''), Json::TYPE_ARRAY))),
                'cc_num'          => str_replace(array(' ', '-'), '', $filter->filter(trim(Json::decode($this->params()->fromPost('cc_num', ''), Json::TYPE_ARRAY)))),
                'cc_cvn'          => $filter->filter(trim(Json::decode($this->params()->fromPost('cc_cvn', ''), Json::TYPE_ARRAY))),
                'cc_exp_month'    => $filter->filter(trim(Json::decode($this->params()->fromPost('cc_exp_month', ''), Json::TYPE_ARRAY))),
                'cc_exp_year'     => $filter->filter(trim(Json::decode($this->params()->fromPost('cc_exp_year', ''), Json::TYPE_ARRAY))),
            );

            if (empty($strError) && !in_array($arrParams['submission_type'], array('charge_interrupted', 'trial', 'renew'))) {
                $strError = $this->_tr->translate('Incorrectly selected submission type.');
            }

            if (empty($strError) && !in_array($arrParams['cc_type'], array('VISA', 'MasterCard'))) {
                $strError = $this->_tr->translate('Incorrectly selected Credit Card Type.');
            }

            if (empty($strError) && empty($arrParams['cc_name'])) {
                $strError = $this->_tr->translate('Please enter Name on the Card.');
            }

            if (empty($strError) && !is_numeric($arrParams['cc_num'])) {
                $strError = $this->_tr->translate('Please enter correct Credit Card No.');
            }

            if (empty($strError) && !is_numeric($arrParams['cc_cvn']) && $this->layout()->getVariable('site_version') == 'australia') {
                $strError = $this->_tr->translate('Please enter correct CVN.');
            }

            if (empty($strError) && (!is_numeric($arrParams['cc_exp_month']) || ($arrParams['cc_exp_month'] < 1 || $arrParams['cc_exp_month'] > 12))) {
                $strError = $this->_tr->translate('Please select Expiry Date Month.');
            }

            if (empty($strError) && !is_numeric($arrParams['cc_exp_year'])) {
                $strError = $this->_tr->translate('Please select Expiry Date Year.');
            }

            if (empty($strError)) {
                $oSubscriptions = $this->_company->getCompanySubscriptions();

                $companyId             = $this->_auth->getCurrentUserCompanyId();
                $arrDetailsCompanyInfo = $this->_company->getCompanyAndDetailsInfo($companyId);

                // @NOTE: Turn this off to test functionality without requests sending to PT
                $booSendRequestToPT = true;

                if (in_array($arrParams['submission_type'], array('trial', 'renew'))) {
                    $booRenew = false;

                    // Get additional params
                    $arrParams['subscription_plan'] = $filter->filter(Json::decode($this->params()->fromPost('subscription_plan'), Json::TYPE_ARRAY));
                    $arrParams['total_users']       = (int)Json::decode($this->params()->fromPost('user_licenses'), Json::TYPE_ARRAY);

                    if (!in_array($arrParams['subscription_plan'], array('monthly', 'annual'))) {
                        $strError = $this->_tr->translate('Incorrectly selected subscription.');
                    }

                    if (empty($strError) && (!is_numeric($arrParams['total_users']) || $arrParams['total_users'] < 1 || $arrParams['total_users'] > 1000)) {
                        $strError = $this->_tr->translate('Please enter correct number of user licenses.');
                    }


                    if (empty($strError)) {
                        // More additional params
                        if ($arrParams['submission_type'] == 'renew') {
                            $booRenew = true;

                            $arrParams['additional_storage'] = Json::decode($this->params()->fromPost('additional_storage'), Json::TYPE_ARRAY);

                            $storageUsed = Files::formatSizeInGb($arrDetailsCompanyInfo['storage_today'] * 1024);
                            $freeStorage = empty($arrDetailsCompanyInfo['free_storage']) ? 2 : $arrDetailsCompanyInfo['free_storage'];
                            $minStorage  = max($storageUsed, $freeStorage);

                            if (!is_numeric($arrParams['additional_storage']) || $arrParams['additional_storage'] < $minStorage) {
                                $arrParams['additional_storage'] = $minStorage;
                            }

                            if ($arrParams['additional_storage'] > $minStorage + 10) {
                                $strError = $this->_tr->translate('Please select correct Storage needed value.');
                            } else {
                                // Use difference only
                                $arrParams['additional_storage'] = $arrParams['additional_storage'] - $freeStorage;
                                $arrParams['additional_storage'] = max($arrParams['additional_storage'], 0);
                            }
                        } else {
                            $arrParams['additional_storage'] = 0;
                        }
                    }

                    // Update/process
                    if (empty($strError)) {
                        $arrMemberInfo = $this->_members->getMemberInfo();

                        $strSubscriptionNotice = $oSubscriptions->checkCompanyStatus($arrMemberInfo, false);

                        list($strError, $strEmailSentTo) = $oSubscriptions->updateCompanySubscriptions($arrParams, $arrDetailsCompanyInfo, $booRenew, $booSendRequestToPT);
                        $strSuccessMessage = sprintf($this->_tr->translate('Thank you for renewing your subscription. A copy of the invoice was emailed to: %s'), $strEmailSentTo);

                        $arrExpirationStatuses = array('account_expired', 'trial_expired');
                        if (in_array($strSubscriptionNotice, $arrExpirationStatuses)) {
                            $strSuccessMessage .= $this->_tr->translate(' Please logout, and log back in to start Officio.');
                        }
                    }
                } else {
                    // Charge interrupted:
                    // 1. update cc info
                    // 2. mark show_expiration_dialog_after date to tomorrow

                    // Update CC info
                    list($strError, $arrProfileInfo) = $oSubscriptions->updateCCInfo($arrParams, $arrDetailsCompanyInfo, $booSendRequestToPT);

                    if (empty($strError)) {
                        // Update PT info in database for this company
                        $this->_company->updatePTInfo($arrProfileInfo['creditCardNum'], $arrProfileInfo['customerRefNum'], $companyId);

                        // Mark show_expiration_dialog_after date to tomorrow
                        $this->_company->updateCompanyShowExpirationDate($companyId);

                        // Erase the error codes for all previous failed invoices
                        $this->_company->getCompanyInvoice()->resetPTErrorCodesForCompany($companyId);

                        // Generate success message
                        $strSuccessMessage = $this->_tr->translate('Thank you for renewing your subscription.');
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return Json result
        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $strSuccessMessage : $strError
        );

        return new JsonModel($arrResult);
    }


    //////////////////////////////////////////////////////////////////////
    // These actions are related to 'Unsuccessful PT transactions'
    //////////////////////////////////////////////////////////////////////

    public function chargeInterruptedAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);

        // Show different message if:
        // 2 invoices are failed AND next billing date is in less than 15 days.
        $booShowExpiration   = false;
        $failedInvoicesCount = 0;
        $strExpirationDate   = '';

        $companyId      = $this->_auth->getCurrentUserCompanyId();
        $arrCompanyInfo = $this->_company->getCompanyDetailsInfo($companyId);
        if (isset($arrCompanyInfo['next_billing_date']) && !Settings::isDateEmpty($arrCompanyInfo['next_billing_date'])) {
            $nextBillingDate = strtotime($arrCompanyInfo['next_billing_date']);
            if ($nextBillingDate < time() + 60 * 60 * 24 * 15) {
                $booShowExpiration = true;
                $strExpirationDate = date($this->_settings->variable_get('dateFormatFull'), $nextBillingDate);
            }

            if ($booShowExpiration) {
                $arrFailedInvoices   = $this->_company->getCompanyInvoice()->getCompanyFailedInvoices($companyId);
                $failedInvoicesCount = count($arrFailedInvoices);
                $booShowExpiration   = $failedInvoicesCount >= 2;
            }
        }
        $view->setVariable('showExpiration', $booShowExpiration);
        $view->setVariable('failedInvoicesCount', $failedInvoicesCount);
        $view->setVariable('suspendedOnDate', $strExpirationDate);

        return $view;
    }


    public function suspendAction() {
        $view = new JsonModel();

        try {
            $strError = '';

            $companyId = $this->_auth->getCurrentUserCompanyId();

            // Mark data to "Don't show message for X days"
            $this->_company->updateCompanyShowExpirationDate($companyId);

            // Delete cookie
            $this->_company->getCompanySubscriptions()->createSubscriptionCookie();
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        // Return Json result
        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('Thank you. We will try charging in next several days.') : $strError
        );

        return $view->setVariables($arrResult);
    }

}
