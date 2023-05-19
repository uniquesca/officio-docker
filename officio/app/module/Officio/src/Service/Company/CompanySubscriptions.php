<?php

namespace Officio\Service\Company;

use Clients\Service\Members;
use DateTime;
use Exception;
use Officio\Common\Service\BaseService;
use Officio\Service\Company;
use Officio\Service\GstHst;
use Officio\Common\Service\Settings;
use Officio\Service\Payment\PaymentServiceInterface;
use Officio\Service\Users;
use Officio\Common\ServiceContainerHolder;
use Officio\Common\SubServiceInterface;
use Officio\Templates\Model\SystemTemplate;
use Officio\Templates\SystemTemplates;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class CompanySubscriptions extends BaseService implements SubServiceInterface
{

    use ServiceContainerHolder;

    /** @var Company */
    private $_parent;

    /** @var GstHst */
    protected $_gstHst;

    /** @var SystemTemplates */
    protected $_systemTemplates;

    /** @var PaymentServiceInterface */
    protected $_payment;

    public function initAdditionalServices(array $services)
    {
        $this->_gstHst          = $services[GstHst::class];
        $this->_systemTemplates = $services[SystemTemplates::class];
        $this->_payment         = $services['payment'];
    }

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent()
    {
        return $this->_parent;
    }

    /**
     * This method is used for cookies creation/delete
     * (Cookie is used to sho expiration dialogs)
     *
     * @param string $strValue - empty to delete cookie
     * @return bool true on success
     */
    public function createSubscriptionCookie($strValue = '')
    {
        $timeInTheFuture = time() + 60 * 60 * 24 * 365;
        $timeInThePast   = time() - 60 * 60;
        $expirationTime  = empty($strValue) ? $timeInThePast : $timeInTheFuture;

        $booCreateCookie = true;
        if (empty($strValue) && !isset($_COOKIE['subscription_notice'])) {
            // Don't try to create the cookie if it wasn't created before.
            // Otherwise, browser will reject the cookie because it is already expired.
            $booCreateCookie = false;
        }

        return $booCreateCookie ? Settings::setCookie('subscription_notice', $strValue, $expirationTime, '/') : true;
    }


    /**
     * Check if dialog must be showed for a specific company
     *
     * @param string $strDialogType
     * @return bool true if show dialog, otherwise false
     */
    private function _checkShowDialog($strDialogType)
    {
        switch ($strDialogType) {
            case 'charge_interrupted':
                // Show for all companies
                $booShow = true;
                break;

            case 'renew':
                // Show for all companies
                $booShow = true;
                break;

            case 'trial':
            default:
                // Show for all companies
                $booShow = true;
                break;
        }

        return $booShow;
    }


    /**
     * There are such cases:
     * 1. Company Trial account
     * for every user (except Agents, and Clients) of a company that trial=true
     * if the trial_period_remaining <= (trial_period / 4):
     *    a. expired, but charging wasn't done
     *    b. will expire soon
     *
     * 2. Company NOT Trial account
     *    a. expired, but charging wasn't done
     *    b. will expire soon (if the next billing date <= month)
     *
     * 3. Company payment term is monthly and charging failed
     *
     * @param array $arrMemberInfo - information about current member
     * @param bool $booCheckIfAdmin
     * @return string - empty if company account is correct
     */
    public function checkCompanyStatus($arrMemberInfo, $booCheckIfAdmin = true)
    {
        $strSubscriptionStatus = '';
        $booCorrectMemberInfo  = false;

        if ($booCheckIfAdmin) {
            // If there is user's info - continue
            if (is_array($arrMemberInfo) && array_key_exists('userType', $arrMemberInfo) &&
                in_array($arrMemberInfo['userType'], Members::getMemberType('admin_and_user'))) {
                $booCorrectMemberInfo = true;
            }
        } else {
            $booCorrectMemberInfo = true;
        }


        if ($booCorrectMemberInfo) {
            $arrCompanyInfo = $this->_parent->getCompanyAndDetailsInfo($arrMemberInfo['company_id']);

            if ($arrCompanyInfo['Status'] == $this->_parent->getCompanyIntStatusByString('suspended')) {
                $strSubscriptionStatus = 'account_suspended';
            } else {
                $now             = time();
                $month           = 60 * 60 * 24 * 30;
                $nextBillingDate = strtotime($arrCompanyInfo['next_billing_date']);
                $periodRemaining = $nextBillingDate - $now;

                /*
                    a. company term is monthly
                    b. there are failed invoices
                    c. show_expiration_dialog_after <= now
                 */
                $firstFailedInvoiceDate = $this->_parent->getCompanyInvoice()->getCompanyFirstFailedInvoiceDate($arrMemberInfo['company_id']);
                if (!empty($firstFailedInvoiceDate) && $arrCompanyInfo['payment_term'] == $this->getPaymentTermIdByName('monthly')) {
                    // Can we show now?
                    if ((empty($arrCompanyInfo['show_expiration_dialog_after']) || strtotime($arrCompanyInfo['show_expiration_dialog_after']) <= $now) && $this->_checkShowDialog('charge_interrupted')) {
                        // #3. Company account is expired and charging failed
                        $strSubscriptionStatus = 'charge_interrupted';
                    }
                } else {
                    // Check 2 other cases

                    // Check all fields if we need show the tab
                    if ($arrCompanyInfo['trial'] == 'Y' && $this->_checkShowDialog('trial')) {
                        $trialPeriod = strtotime($arrCompanyInfo['next_billing_date']) - $arrCompanyInfo['regTime'];

                        if ($periodRemaining <= 0) {
                            $strSubscriptionStatus = 'trial_expired';
                        } elseif ($periodRemaining <= $trialPeriod / 4) {
                            // #1. Company Trial account
                            $strSubscriptionStatus = 'trial_expire';
                        }
                    }

                    if (empty($strSubscriptionStatus) && $arrCompanyInfo['trial'] == 'N' && ($periodRemaining <= $month) && $this->_checkShowDialog('renew')) {
                        $booShowAccountWillExpire = false;
                        switch ($arrCompanyInfo['payment_term']) {
                            case '1': // Monthly
                                // ((company is on monthly plan) & (their next billing date is less than 30 days) &
                                // (there is no PT id for them))
                                if (empty($arrCompanyInfo['paymentech_profile_id'])) {
                                    $booShowAccountWillExpire = true;
                                }
                                break;

                            case '2': // Annually
                            case '3': // Biannually
                                // ((company is on Annual or Biannual plan) &
                                // (their next billing date is less than 30 days))
                                $booShowAccountWillExpire = true;
                                break;

                            default: // Unknown
                                break;
                        }

                        if ($booShowAccountWillExpire) {
                            // #2. Company NOT Trial account

                            $booExpired = true;
                            $expDate    = $arrCompanyInfo['next_billing_date'];
                            if (!empty($expDate) && strtotime($expDate) > time()) {
                                $booExpired = false;
                            }

                            $strSubscriptionStatus = $booExpired ? 'account_expired' : 'account_expire';
                        }
                    }
                }
            }
        }

        // Check if we need to show 'change password' dialog
        /** @var Members $oMembers */
        $oMembers = $this->_serviceContainer->get(Members::class);
        if (empty($strSubscriptionStatus) && $this->_config['security']['password_aging']['enabled']) {
            $memberInfo = $oMembers->getMemberInfo($this->_auth->getCurrentUserId());

            if ($this->_auth->isCurrentUserAdmin()) {
                $daysAmount = (int)$oMembers->_config['security']['password_aging']['admin_lifetime'];
            } else {
                $daysAmount = (int)$oMembers->_config['security']['password_aging']['client_lifetime'];
            }
            $daysAmount = empty($daysAmount) ? 45 : $daysAmount;

            // last password change date from db + 90 or 45 days less than current day -> user should change password
            if (empty($memberInfo['password_change_date'])) {
                $strSubscriptionStatus = 'password_should_be_changed_first_time';
            } elseif (strtotime('+' . $daysAmount . ' days', strtotime($memberInfo['password_change_date'])) <= time()) {
                $strSubscriptionStatus = 'password_should_be_changed';
            }
        }

        return $strSubscriptionStatus;
    }


    /**
     * Check if agent/client can log in to officio
     *
     * Don't allow clients/agents to login if:
     *   company_status = suspended OR
     *   company_status = inactive OR
     *   today > Next billing date - 30 days OR
     *   today > First_failed_invoice + cutting_of_service_day
     *
     * @param array $arrMemberInfo
     * @return int error code, 0 if client can log in
     */
    public function canClientAndAgentLogin($arrMemberInfo)
    {
        $intErrorNum = 0;

        // Agents and Clients are blocked and are not able to get into the program.
        // If trial period has expired for the company
        $arrClientAgentTypes = Members::getMemberType('client_agent');
        if (is_array($arrClientAgentTypes) && in_array($arrMemberInfo['userType'], $arrClientAgentTypes)) {
            $intErrorNum = $this->getCompanySubscriptionStatusCode($arrMemberInfo['company_id']);
        }

        return $intErrorNum;
    }


    /**
     * Check company status, subscription and load error code if company is inactive/suspended/expired
     *
     * @param int $companyId
     * @return int
     */
    public function getCompanySubscriptionStatusCode($companyId)
    {
        $intErrorNum = 0;

        // Load company info
        $arrCompanyInfo = $this->_parent->getCompanyAndDetailsInfo($companyId);

        // Allow 30 days after next billing date is expired
        $booExpired = false;
        if (!empty($arrCompanyInfo['next_billing_date'])) {
            $maxDays    = $this->_settings->getSystemVariables()->getVariable('cutting_of_service_days', 30) * 24 * 60 * 60;
            $booExpired = (strtotime($arrCompanyInfo['next_billing_date']) + $maxDays) < time();
        }

        if ($arrCompanyInfo['Status'] == $this->_parent->getCompanyIntStatusByString('suspended')) {
            $intErrorNum = 100;
        } elseif ($arrCompanyInfo['Status'] == $this->_parent->getCompanyIntStatusByString('inactive')) {
            $intErrorNum = 101;
        } elseif ($booExpired) {
            $intErrorNum = 102;
        } else {
            list($booExpired,) = $this->_parent->isCompanyAccountExpired($companyId);
            if ($booExpired) {
                $intErrorNum = 103;
            }
        }

        return $intErrorNum;
    }


    /**
     * Update company's PT profile
     * If it is not created yet - it will be created, otherwise - updated
     *
     * @param array $arrCCInfo - CC information will be sent
     * @param array $arrDetailsCompanyInfo - Company information will be sent
     * @param bool $booSendRequestToPT true to send request to PT (for testing)
     *
     * @return array
     */
    public function updateCCInfo($arrCCInfo, $arrDetailsCompanyInfo, $booSendRequestToPT = true)
    {
        $strError = '';

        // Prepare CC info
        if (strlen($arrCCInfo['cc_exp_month'] ?? '') == 1) {
            $arrCCInfo['cc_exp_month'] = '0' . $arrCCInfo['cc_exp_month'];
        }

        $arrProfileInfo = array(
            'customerName'            => $arrCCInfo['cc_name'],
            'customerRefNum'          => $arrDetailsCompanyInfo['paymentech_profile_id'],
            'creditCardNum'           => $arrCCInfo['cc_num'],
            'creditCardExpDate'       => $arrCCInfo['cc_exp_month'] . $arrCCInfo['cc_exp_year'],
            'OrderDefaultDescription' => $arrDetailsCompanyInfo['companyName']
        );


        try {
            if (!$this->_config['payment']['enabled']) {
                // Payment is not enabled
                $strError = $this->_tr->translate('Communication with Payment Service is turned off. Please turn it on in config file and try again.');
            }

            if (empty($strError)) {
                $this->_payment->init();

                // Check Payment Service profile id for the company, create or update it
                if (!empty($arrProfileInfo['customerRefNum'])) {
                    if ($booSendRequestToPT) {
                        $arrResult = $this->_payment->readProfile($arrProfileInfo['customerRefNum']);
                    } else {
                        $arrResult = array(
                            'error'   => false,
                            'message' => ''
                        );
                    }

                    if ($arrResult['error']) {
                        // Such Payment Service profile doesn't exist
                        $strError = $this->_tr->translate("Payment Service profile doesn't exists.");
                    } else {
                        // PT profile was already created, so lets update it
                        if ($booSendRequestToPT) {
                            $arrResult = $this->_payment->updateProfile($arrProfileInfo);
                        } else {
                            $arrResult = array(
                                'error'   => false,
                                'message' => ''
                            );
                        }

                        // Check response from Payment Service
                        if ($arrResult['error']) {
                            $strError = $arrResult['message'];
                        }
                    }
                } else {
                    // Payment Service profile was not yet created, so let's create it now
                    $arrProfileInfo['customerRefNum'] = $this->_payment->generatePaymentProfileId($arrDetailsCompanyInfo['company_id'], false);
                    if ($booSendRequestToPT) {
                        $arrResult = $this->_payment->createProfile($arrProfileInfo);
                    } else {
                        $arrResult = array(
                            'error'   => false,
                            'message' => ''
                        );
                    }

                    // Check response from Payment Service
                    if ($arrResult['error']) {
                        $strError = $arrResult['message'];
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            $strError,
            $arrProfileInfo
        );
    }

    /**
     * Get payment term by its id
     *
     * @param int $paymentTermId
     * @return string
     */
    public function getPaymentTermNameById($paymentTermId)
    {
        $paymentTermId = (int)$paymentTermId;

        switch ($paymentTermId) {
            case 1:
                $strPaymentTerm = 'Monthly';
                break;

            case 2:
                $strPaymentTerm = 'Annual';
                break;

            case 3:
                $strPaymentTerm = 'Bi-annual';
                break;

            default:
                $strPaymentTerm = 'Unknown';
                break;
        }

        return $strPaymentTerm;
    }

    /**
     * Get payment term int id by its name
     *
     * @param $strPaymentTerm
     * @return int
     */
    public function getPaymentTermIdByName($strPaymentTerm)
    {
        $strPaymentTerm = strtolower($strPaymentTerm ?? '');

        switch ($strPaymentTerm) {
            case 'monthly':
                $paymentTermId = 1;
                break;

            case 'annual':
                $paymentTermId = 2;
                break;

            case 'bi-annual':
                $paymentTermId = 3;
                break;

            default:
                $paymentTermId = 0;
                break;
        }

        return $paymentTermId;
    }

    /**
     * Update company subscription details
     * 1. Check PT profile id, create if needed
     * 2. Prepare and generate invoice, save in DB
     * 3. Send request to PT to charge money
     * 4. Update company and invoice info
     * 5. Generate invoice pdf and send email
     *
     * @param array $arrParams
     * @param array $arrDetailsCompanyInfo
     * @param bool $booRenew
     * @param bool $booSendRequestToPT
     * @return array of these values
     *          string error details (empty on success)
     *          string email was sent to
     */
    public function updateCompanySubscriptions($arrParams, $arrDetailsCompanyInfo, $booRenew, $booSendRequestToPT)
    {
        $strEmailSentTo = '';
        $invoiceId      = 0;
        $oInvoices      = null;

        try {
            $companyId = $arrDetailsCompanyInfo['company_id'];
            $oInvoices = $this->_parent->getCompanyInvoice();

            // Update CC info
            list($strError, $arrProfileInfo) = $this->updateCCInfo($arrParams, $arrDetailsCompanyInfo, $booSendRequestToPT);
            $arrParams['subscription']        = $arrDetailsCompanyInfo['subscription'];
            $arrParams['pricing_category_id'] = $arrDetailsCompanyInfo['pricing_category_id'];

            // If PT profile is ready - charge money
            if (empty($strError)) {
                // Update PT info in database for this company
                $ccType = $this->_parent->updatePTInfo($arrProfileInfo['creditCardNum'], $arrProfileInfo['customerRefNum'], $companyId);


                $expDate    = empty($arrDetailsCompanyInfo['next_billing_date']) ? time() : strtotime($arrDetailsCompanyInfo['next_billing_date']);
                $booExpired = $expDate <= time();

                // Load prices for this company (in relation to expiration status)
                $arrPrices = $this->_parent->getCompanyPrices($companyId, $booExpired, $arrParams['pricing_category_id']);


                $arrParams['payment_term'] = $this->getPaymentTermIdByName($arrParams['subscription_plan']);

                // Free users
                $arrParams['free_users'] = $booRenew ? $arrDetailsCompanyInfo['free_users'] : $arrPrices['freeUsers'];
                if ($arrParams['total_users'] < $arrParams['free_users']) {
                    $arrParams['total_users'] = $arrParams['free_users'];
                }

                // Calculate fees
                // Get user price in relation to the page and billing frequency
                if ($booRenew) {
                    $arrParams['price_per_user'] = round($this->_parent->getUserPrice($arrParams['payment_term'], $arrDetailsCompanyInfo['subscription'], $arrDetailsCompanyInfo['pricing_category_id']), 2);
                    $arrParams['discount']       = 0;

                    list($feeMonthly, $feeAnnual) = $this->_parent->getCompanySubscriptionPrices($arrDetailsCompanyInfo);
                    if ($arrParams['subscription_plan'] == 'monthly') {
                        $arrParams['subscription_fee'] = round($feeMonthly, 2);
                    } else {
                        $arrParams['subscription_fee'] = round($feeAnnual, 2);
                    }
                } elseif ($arrParams['subscription_plan'] == 'monthly') {
                    $arrParams['price_per_user']   = round($arrPrices['licenseMonthly'], 2);
                    $arrParams['discount']         = round($arrPrices['feeMonthlyDiscount'], 2);
                    $arrParams['subscription_fee'] = round($arrPrices['feeMonthly'] + $arrPrices['feeMonthlyDiscount'], 2);
                } else {
                    $arrParams['price_per_user']   = round($arrPrices['licenseAnnual'], 2);
                    $arrParams['discount']         = round($arrPrices['feeAnnualDiscount'], 2);
                    $arrParams['subscription_fee'] = round($arrPrices['feeAnnual'] + $arrPrices['feeAnnualDiscount'], 2);
                }

                // Additional users
                $arrParams['additional_users']     = $this->_parent->calculateAdditionalUsers($arrParams['total_users'], $arrParams['free_users']);
                $arrParams['additional_users_fee'] = round($arrParams['additional_users'] * $arrParams['price_per_user'], 2);
                $arrParams['free_storage']         = $arrDetailsCompanyInfo['free_storage'];

                // Additional storage price
                $arrParams['price_per_storage']          = round($this->_parent->getStoragePrice($arrParams['payment_term']), 2);
                $arrParams['additional_storage_charges'] = round($this->_parent->calculateAdditionalStorageFee($arrParams['additional_storage'], $arrParams['payment_term']), 2);

                // This param is not used here
                $arrParams['support_fee'] = 0;

                $arrParams['subtotal'] = round(
                    $arrParams['subscription_fee'] -
                    $arrParams['discount'] +
                    $arrParams['support_fee'] +
                    $arrParams['additional_users_fee'] +
                    $arrParams['additional_storage_charges'],
                    2
                );


                $gstType = $arrDetailsCompanyInfo['gst_type'];
                if ($arrDetailsCompanyInfo['gst_type'] == 'auto') {
                    $gstType = $arrDetailsCompanyInfo['gst_default_type'];
                }

                $arrGstInfo            = $this->_gstHst->calculateGstAndSubtotal($gstType, $arrDetailsCompanyInfo['gst_used'], $arrParams['subtotal']);
                $arrParams['gst']      = round($arrGstInfo['gst'], 2);
                $arrParams['subtotal'] = round($arrGstInfo['subtotal'], 2);
                $arrParams['total']    = round($arrParams['subtotal'] + $arrParams['gst'], 2);


                // Calculate and update Next Billing Date
                $base_time = strtotime($arrDetailsCompanyInfo['next_billing_date']);
                $base_time = $base_time < time() ? time() : $base_time;

                if ($arrParams['subscription_plan'] == 'monthly') {
                    // Monthly, 1 month
                    $nextBillingDate = $this->_settings->getXMonthsToTheFuture($base_time);
                } else {
                    // Annually, 12 months
                    $nextBillingDate = $this->_settings->getXMonthsToTheFuture($base_time, 12);
                }
                $arrParams['next_billing_date'] = date('Y-m-d', $nextBillingDate);

                // Use the updated/new info during template processing
                $arrDetailsCompanyInfo['payment_term']      = $arrParams['payment_term'];
                $arrDetailsCompanyInfo['next_billing_date'] = $arrParams['next_billing_date'];
                $arrDetailsCompanyInfo['subscription_fee']  = $arrParams['subscription_fee'];

                $strTemplateName = $booRenew ? 'Renew Invoice' : 'Subscription Invoice';

                /** @var Members $members */
                $members   = $this->_serviceContainer->get(Members::class);
                $adminInfo = $members->getMemberInfo($arrDetailsCompanyInfo['admin_id']);

                $template     = SystemTemplate::loadOne(['title' => $strTemplateName]);
                $replacements = $this->_systemTemplates->getGlobalTemplateReplacements();
                $replacements += $this->_parent->getTemplateReplacements($arrDetailsCompanyInfo, $adminInfo);
                $replacements += $this->_parent->getCompanyInvoice()->getTemplateReplacements($arrParams);

                $processedTemplate = $this->_systemTemplates->processTemplate($template, $replacements);

                // Create invoice in officio DB
                $invoiceData = array(
                    'company_id'                 => $companyId,
                    'invoice_number'             => $this->_parent->getCompanyInvoice()->generateUniqueInvoiceNumber(),
                    'subscription_fee'           => $arrParams['subscription_fee'],
                    'discount'                   => $arrParams['discount'],
                    'support_fee'                => empty($arrParams['support_fee']) ? null : $arrParams['support_fee'],
                    'invoice_date'               => date('Y-m-d'),
                    'free_users'                 => $arrParams['free_users'],
                    'free_storage'               => $arrParams['free_storage'],
                    'additional_users'           => $arrParams['additional_users'],
                    'additional_users_fee'       => $arrParams['additional_users_fee'],
                    'additional_storage'         => empty($arrParams['additional_storage']) ? null : $arrParams['additional_storage'],
                    'additional_storage_charges' => empty($arrParams['additional_storage_charges']) ? null : $arrParams['additional_storage_charges'],
                    'subtotal'                   => $arrParams['subtotal'],
                    'tax'                        => $arrParams['gst'],
                    'total'                      => $arrParams['total'],
                    'message'                    => $processedTemplate->template,
                    'subject'                    => $processedTemplate->subject,
                );

                $invoiceId = $oInvoices->insertInvoice($invoiceData);

                // Send request to PT to charge money
                $invoiceData['paymentech_profile_id'] = $arrProfileInfo['customerRefNum'];
                $invoiceData['companyName']           = $arrDetailsCompanyInfo['companyName'];
                $arrOrderResult                       = $oInvoices->chargeSavedInvoice($invoiceId, $invoiceData, $booSendRequestToPT);

                // Check response from PT
                if ($arrOrderResult['error']) {
                    $strError = $arrOrderResult['message'];

                    // Delete failed invoices
                    $oInvoices->deleteInvoices(array($invoiceId));
                } else {
                    // Update company settings
                    $arrCompanyUpdate = array(
                        'trial'                        => 'N',
                        'payment_term'                 => $arrParams['payment_term'],
                        'next_billing_date'            => $arrParams['next_billing_date'],
                        'subscription_fee'             => $arrParams['subscription_fee'],
                        'show_expiration_dialog_after' => null,
                    );
                    $this->_parent->updateCompanyDetails($companyId, $arrCompanyUpdate);

                    // Update invoice's mode of payment
                    if (!empty($ccType)) {
                        $oInvoices->updateInvoice(array('mode_of_payment' => $ccType), $invoiceId);
                    }

                    // Send email to company admin and officio support
                    $this->_systemTemplates->sendTemplate($processedTemplate);
                    $strEmailSentTo = $processedTemplate->to;

                    // Create PDF
                    $this->_parent->createInvoicePDF($processedTemplate->template, $invoiceData);

                    // Delete subscription cookie if exist
                    $this->createSubscriptionCookie();
                }
            }
        } catch (Exception $e) {
            // Delete previously created invoice
            if (!empty($invoiceId) && !empty($oInvoices)) {
                // Set status to failed
                $oInvoices->updateInvoiceStatus($invoiceId, false);
            }

            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            $strError,
            $strEmailSentTo
        );
    }

    public function upgradeSubscriptionPlan($companyId)
    {
        $strError         = '';
        $freeClientsCount = 0;
        $subscriptionName = '';
        $strEmailSentTo   = '';
        $arrParams        = [];

        $oInvoices = $this->_parent->getCompanyInvoice();

        // @NOTE: Turn this off to test functionality without requests sending to PT
        $booSendRequestToPT = true;
        $invoiceId          = 0;

        try {
            $arrDetailsCompanyInfo = $this->_parent->getCompanyAndDetailsInfo($companyId);

            $ccType    = $arrDetailsCompanyInfo['paymentech_mode_of_payment'];
            $arrPrices = $this->_parent->getCompanyPrices($companyId, false, false, true);

            $arrParams['payment_term']   = $arrDetailsCompanyInfo['payment_term'];
            $arrParams['price_per_user'] = round($this->_parent->getUserPrice($arrParams['payment_term']), 2);
            $arrParams['subscription']   = 'lite';
            $arrParams['free_users']     = $arrPrices['packageLiteFreeUsers'];
            $arrParams['free_storage']   = $arrPrices['packageLiteFreeStorage'];

            $freeClientsCount = $arrParams['free_clients'] = $arrPrices['packageLiteFreeClients'];
            $subscriptionName = $this->_parent->getPackages()->getSubscriptionNameById($arrParams['subscription']);

            $nextBillingDate = new DateTime($arrDetailsCompanyInfo['next_billing_date']);
            $currentDate     = new DateTime(date("Y-m-d"));
            $daysSubtraction = $nextBillingDate->diff($currentDate)->days;

            if ($arrParams['payment_term'] == '1') {
                $arrParams['subscription_fee'] = round($arrPrices['packageLiteFeeMonthly'], 2);
                $amountUpgradePlan             = $daysSubtraction / 30 * ($arrPrices['packageLiteFeeMonthly'] - $arrPrices['packageStarterFeeMonthly']);
            } else {
                $arrParams['subscription_fee'] = round($arrPrices['packageLiteFeeAnnual'], 2);
                $amountUpgradePlan             = $daysSubtraction / 365 * ($arrPrices['packageLiteFeeAnnual'] - $arrPrices['packageStarterFeeAnnual']);
            }

            $arrParams['subtotal'] = round($amountUpgradePlan, 2);

            $gst = $arrDetailsCompanyInfo['gst_used'];

            if (!empty($gst)) {
                $gst = $arrParams['subtotal'] * $gst / 100;
            }

            $gst                = round($gst, 2);
            $arrParams['gst']   = $gst;
            $arrParams['total'] = round($arrParams['subtotal'] + $gst, 2);

            /** @var Members $members */
            $members   = $this->_serviceContainer->get(Members::class);
            $adminInfo = $members->getMemberInfo($arrDetailsCompanyInfo['admin_id']);

            // Send mails to the prospect
            $template = SystemTemplate::loadOne(['title' => 'Upgrade to OfficioSolo']);

            $replacements = $this->_systemTemplates->getGlobalTemplateReplacements();
            $replacements += $this->_parent->getTemplateReplacements($arrDetailsCompanyInfo, $adminInfo);
            $replacements += $this->_parent->getCompanyInvoice()->getTemplateReplacements($arrParams);

            $processedTemplate = $this->_systemTemplates->processTemplate($template, $replacements);

            // Create invoice in officio DB
            $invoiceData = array(
                'company_id'                 => $companyId,
                'invoice_number'             => $this->_parent->getCompanyInvoice()->generateUniqueInvoiceNumber(),
                'subscription_fee'           => $arrParams['subscription_fee'],
                'discount'                   => 0,
                'support_fee'                => null,
                'invoice_date'               => date('Y-m-d'),
                'free_users'                 => $arrParams['free_users'],
                'free_clients'               => $arrParams['free_clients'],
                'free_storage'               => $arrParams['free_storage'],
                'additional_users'           => null,
                'additional_users_fee'       => null,
                'additional_storage'         => null,
                'additional_storage_charges' => null,
                'subtotal'                   => $arrParams['subtotal'],
                'tax'                        => $arrParams['gst'],
                'total'                      => $arrParams['total'],
                'message'                    => $processedTemplate->template,
                'subject'                    => $processedTemplate->subject,
            );

            $invoiceId = $oInvoices->insertInvoice($invoiceData);

            // Send request to PT to charge money
            $invoiceData['paymentech_profile_id'] = $arrDetailsCompanyInfo['paymentech_profile_id'];
            $invoiceData['companyName']           = $arrDetailsCompanyInfo['companyName'];

            $arrOrderResult = $oInvoices->chargeSavedInvoice($invoiceId, $invoiceData, $booSendRequestToPT);

            // Check response from PT
            if ($arrOrderResult['error']) {
                $strError = $arrOrderResult['message'];

                // Delete failed invoices
                $oInvoices->deleteInvoices(array($invoiceId));
            } else {
                // Update company settings
                $arrCompanyUpdate = array(
                    'subscription'     => $arrParams['subscription'],
                    'subscription_fee' => $arrParams['subscription_fee'],
                    'free_users'       => $arrParams['free_users'],
                    'free_clients'     => $arrParams['free_clients'],
                    'free_storage'     => $arrParams['free_storage']
                );
                $this->_parent->updateCompanyDetails($companyId, $arrCompanyUpdate);

                // Update invoice's mode of payment
                if (!empty($ccType)) {
                    $oInvoices->updateInvoice(array('mode_of_payment' => $ccType), $invoiceId);
                }

                // Send email to company admin and officio support
                $this->_systemTemplates->sendTemplate($processedTemplate);
                $strEmailSentTo = $processedTemplate->to;

                // Create PDF
                $this->_parent->createInvoicePDF($processedTemplate->template, $invoiceData);

                /** @var Users $oUsers */
                $oUsers = $this->_serviceContainer->get(Users::class);
                $oUsers->massCompanyUsersUpdateInLms($companyId);
            }
        } catch (Exception $e) {
            // Delete previously created invoice
            if (!empty($invoiceId)) {
                // Set status to failed
                $oInvoices->updateInvoiceStatus($invoiceId, false);
            }

            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            $strError,
            $strEmailSentTo,
            $freeClientsCount,
            $subscriptionName
        );
    }

}
