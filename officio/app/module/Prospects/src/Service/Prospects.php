<?php

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */

namespace Prospects\Service;

use Clients\Service\Members;
use Exception;
use Laminas\Db\Sql\Select;
use Laminas\EventManager\EventInterface;
use Laminas\Filter\StripTags;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Helper\Layout;
use Laminas\View\HelperPluginManager;
use Laminas\View\Model\ViewModel;
use Laminas\View\Renderer\PhpRenderer;
use Officio\Common\SubServiceOwner;
use Officio\Comms\Service\Mailer;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Service\GstHst;
use Officio\Service\Payment\PaymentServiceInterface;
use Officio\Service\PricingCategories;
use Officio\Templates\Model\SystemTemplate;
use Officio\Templates\SystemTemplates;
use Laminas\Validator\EmailAddress;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Prospects extends SubServiceOwner
{

    public const TEMPLATE_FIRST_INVOICE = 'first-invoice';

    /** @var Company */
    protected $_company;

    /** @var PricingCategories */
    protected $_pricingCategories;

    /** @var Country */
    protected $_country;

    /** @var int count of prospects to show in the grid at once */
    public $intShowProspectsPerPage = 20;

    /** @var SystemTemplates */
    protected $_systemTemplates;

    /** @var GstHst */
    protected $_gstHst;

    /** @var PhpRenderer */
    protected $_renderer;

    /** @var HelperPluginManager */
    protected $_viewHelperManager;

    /** @var PaymentServiceInterface */
    protected $_payment;

    /** @var Mailer */
    protected $_mailer;

    public function initAdditionalServices(array $services)
    {
        $this->_company           = $services[Company::class];
        $this->_pricingCategories = $services[PricingCategories::class];
        $this->_country           = $services[Country::class];
        $this->_systemTemplates   = $services[SystemTemplates::class];
        $this->_gstHst            = $services[GstHst::class];
        $this->_renderer          = $services[PhpRenderer::class];
        $this->_viewHelperManager = $services[HelperPluginManager::class];
        $this->_mailer            = $services[Mailer::class];
        $this->_payment           = $services['payment'];
    }

    public function init()
    {
        $this->_systemTemplates->getEventManager()->attach(SystemTemplates::EVENT_GET_AVAILABLE_FIELDS, [$this, 'getSystemTemplateFields']);
    }

    public function prepareDataForFirstInvoice($prospectId)
    {
        //get data from db
        $data = $this->getProspectInfo($prospectId);

        $total = $subtotal = $gst = $additionalUsers = $additionalUsersFee = $additionalStorage = $additionalStorageFee = 0;

        //get support fee value
        $supportFee = $data['support_fee'];
        if (empty($supportFee) && isset($data['support'])) { //first invoice
            $supportFee = $this->_company->getPackages()->getSupportFee($data['payment_term'], $data['support'], $supportFee);
        }

        // First invoice
        if (empty($subtotal) || empty($total)) {
            // Load/use extra users count and fee from the prospect's info
            $additionalUsers = (int)$data['extra_users'];

            // Users fee
            $additionalUsersFee = round($this->_company->calculateAdditionalUsersFeeBySubscription(
                $data['package_type'],
                $additionalUsers,
                $data['payment_term'],
                $data['pricing_category_id']
            ), 2);

            // Subtotal
            $subtotal = round($data['subscription_fee'] + $additionalUsersFee + $supportFee, 2);

            $arrGstInfo       = $this->_gstHst->getGstByCountryAndProvince($data['country'], $data['state']);
            $arrCalculatedGst = $this->_gstHst->calculateGstAndSubtotal($arrGstInfo['gst_type'], $arrGstInfo['gst_rate'], $subtotal);
            $gst              = round($arrCalculatedGst['gst'], 2);
            $subtotal         = round($arrCalculatedGst['subtotal'], 2);

            // Total
            $total = round($subtotal + $gst, 2);
        }

        return array(
            'payment_term'               => $data['payment_term'],
            'subscription_fee'           => $data['subscription_fee'],
            'subscription'               => $data['subscription'] ?? $data['package_type'],
            'support_fee'                => $supportFee,
            'free_users'                 => $data['free_users'],
            'free_clients'               => $data['free_clients'],
            'free_storage'               => $data['free_storage'],
            'gst'                        => $gst,
            'subtotal'                   => $subtotal,
            'total'                      => $total,
            'additional_users'           => $additionalUsers,
            'additional_users_fee'       => $additionalUsersFee,
            'additional_storage'         => $additionalStorage,
            'additional_storage_charges' => $additionalStorageFee,
            'pricing_category_id'        => $data['pricing_category_id']
        );
    }

    /**
     * Create a first invoice for the prospect/company, charge it and send a confirmation email
     *
     * @param array $arrInvoiceInfo
     * @param bool $booUsePT
     * @param bool $booSendEmail
     * @return array
     */
    public function createFirstInvoice($arrInvoiceInfo, $booUsePT = true, $booSendEmail = true)
    {
        $strErrorMessage = '';
        $invoiceId       = 0;

        if ($booUsePT && empty($arrInvoiceInfo['customerRefNum'])) {
            $strErrorMessage = 'Please create PT profile before invoice creation';
        }

        if (empty($strErrorMessage)) {
            $prospectId = $arrInvoiceInfo['prospect_id'] ?? null;
            $companyId  = $arrInvoiceInfo['company_id'] ?? null;

            $invoiceData = $this->prepareDataForFirstInvoice($prospectId);

            $template     = SystemTemplate::loadOne(['title' => 'First Invoice']);
            $replacements = $this->getTemplateReplacements($prospectId);
            $replacements += $this->_company->getCompanyInvoice()->getTemplateReplacements($invoiceData);
            // TODO Do we need company and admin info processing here?
            $processedTemplate = $this->_systemTemplates->processTemplate($template, $replacements, ['to', 'subject', 'template']);

            // 4. Save First Invoice
            $invoiceData = array(
                'invoice_number'             => $this->_company->getCompanyInvoice()->generateUniqueInvoiceNumber(),
                'subscription_fee'           => round($invoiceData['subscription_fee'], 2),
                'support_fee'                => round($invoiceData['support_fee'], 2),
                'additional_users'           => $invoiceData['additional_users'],
                'free_users'                 => $invoiceData['free_users'],
                'additional_users_fee'       => round($invoiceData['additional_users_fee'], 2),
                'additional_storage'         => $invoiceData['additional_storage'],
                'additional_storage_charges' => round($invoiceData['additional_storage_charges'], 2),
                'invoice_date'               => date('Y-m-d'),
                'subtotal'                   => round($invoiceData['subtotal'], 2),
                'tax'                        => round($invoiceData['gst'], 2),
                'total'                      => round($invoiceData['total'], 2),
                'message'                    => $processedTemplate->template,
                'subject'                    => $processedTemplate->subject
            );

            if (!empty($companyId)) {
                $invoiceData['company_id'] = $companyId;
            } else {
                $invoiceData['prospect_id'] = $prospectId;
            }
            $invoiceId = $this->_company->getCompanyInvoice()->insertInvoice($invoiceData);

            // 5. Create invoice/order in PT
            $invoiceData['paymentech_profile_id'] = $arrInvoiceInfo['customerRefNum'];
            $invoiceData['companyName']           = '';
            $arrOrderResult                       = $this->_company->getCompanyInvoice()->chargeSavedInvoice($invoiceId, $invoiceData, $booUsePT);

            if ($arrOrderResult['error']) {
                // Error happened
                $strErrorMessage = 'Processing error:' .
                    "<div style='padding: 10px 0; font-style:italic;'>" . $arrOrderResult['message'] . '</div>' .
                    'Please review your entries &amp; try again. ' .
                    'If the error shown is not related to your credit card, ' .
                    'please contact our support to resolve the issue promptly.';
            } else {
                // Update invoice's mode of payment, charged via PT, complete
                $arrInvoiceUpdate = array(
                    'mode_of_payment' => $arrInvoiceInfo['mode_of_payment']
                );
                $this->_company->getCompanyInvoice()->updateInvoice($arrInvoiceUpdate, $invoiceId);

                // Send 'first invoice' to user
                if ($booSendEmail) {
                    $this->_systemTemplates->sendTemplate($processedTemplate);
                }
            }
        }

        return array(
            'success'   => empty($strErrorMessage),
            'message'   => $strErrorMessage,
            'invoiceId' => $invoiceId
        );
    }

    /**
     * Create/update prospect info
     *
     * @param int $prospectId
     * @param array $arrProspectData
     * @return int|mixed|string
     */
    public function createUpdateProspect($prospectId, $arrProspectData)
    {
        try {
            if (empty($prospectId)) {
                $prospectId = $this->_db2->insert('prospects', $arrProspectData);
            } else {
                $this->_db2->update('prospects', $arrProspectData, ['prospect_id' => $prospectId]);
            }
        } catch (Exception $e) {
            $prospectId = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $prospectId;
    }

    /**
     * Generate prospect key that will be used later
     *
     * @return string
     */
    public function generateProspectKey()
    {
        $rand = rand();
        return md5(uniqid((string)$rand, true) . 'officio');
    }

    /**
     * Check if provided prospect key is valid and wasn't used yet
     *
     * @param string $prospectKey
     * @return array of string error (empty if valid) and array prospect info
     */
    public function checkIsProspectKeyStillValid($prospectKey)
    {
        $strError = '';

        $arrProspectInfo = $this->getProspectInfoByKey($prospectKey);
        if (!isset($arrProspectInfo['prospect_id']) || $arrProspectInfo['status'] == 'Closed' || $arrProspectInfo['key_status'] == 'Disable') {
            $strError = $this->_tr->translate('Incorrect key.');
        }

        if (empty($strError) && $arrProspectInfo['key_status'] == 'Used Once') {
            /** @var Layout $layout */
            $layout = $this->_viewHelperManager->get('layout');

            $strError = sprintf(
                $this->_tr->translate('The page has expired. Please login here: <a href="%s">%s</a><br><br>This key has already been used.'),
                $layout()->getVariable('baseUrl'),
                $layout()->getVariable('baseUrl')
            );
        }

        return array($strError, $arrProspectInfo);
    }

    /**
     * Create prospect record in DB
     *
     * @param mixed $params
     * @param string $source
     * @return array with creation details (error and generated key)
     */
    public function addProspect($params, $source = '')
    {
        $key      = '';
        $strError = '';
        try {
            $filter = new StripTags();
            if (empty($source)) {
                $source = $filter->filter($params->fromPost('source'));
            }

            $booSignUp       = false;
            $booSpecialOffer = (bool)$params->fromPost('special_offer');
            if ($source == 'Sign-up Page' || $booSpecialOffer) {
                $booSignUp = true;
                $key       = $this->generateProspectKey();
            }

            $promotionalKey = $filter->filter($params->fromPost('key'));

            $pricingCategoryName = 'General';

            if ($promotionalKey) {
                $pricingCategory = $this->_pricingCategories->getPricingCategoryByKey($promotionalKey);

                if (is_array($pricingCategory) && !empty($pricingCategory) && time() <= strtotime($pricingCategory['expiry_date'])) {
                    $pricingCategoryName = $pricingCategory['name'];
                }
            }

            $pricingCategoryId = $this->_pricingCategories->getPricingCategoryIdByName($pricingCategoryName);

            $country     = (int)$filter->filter($params->fromPost('country'));
            $countryCode = $this->_country->getCountryCodeById($country);

            $payment_term = $filter->filter($params->fromPost('payment_term'));

            $subscription_fee = '';

            // Before expiry section
            $arrPriceSettings = $this->_company->getCompanyPrices($this->_company->getDefaultCompanyId(), false, $pricingCategoryId);

            if ($booSpecialOffer) {
                switch ($payment_term) {
                    case 1:
                        $subscription_fee = $arrPriceSettings['feeMonthly'];
                        break;

                    case 2:
                        $subscription_fee = $arrPriceSettings['feeAnnual'];
                        break;
                }

                $packageType = 'ultimate';
                $strPrefix   = 'packageUltimate';
                $support     = 'N';
            } else {
                $packageType = $filter->filter($params->fromPost('price_package'));
                switch ($packageType) {
                    case 'ultimate':
                        $strPrefix = 'packageUltimate';
                        break;

                    case 'pro':
                        $strPrefix = 'packagePro';
                        break;

                    case 'starter':
                        $strPrefix = 'packageStarter';
                        break;

                    default:
                        $strPrefix = 'packageLite';
                        break;
                }

                $support = (int)$filter->filter($params->fromPost('support'));

                if ($this->_config['site_version']['version'] == 'australia') {
                    $support = 'Y';
                } else {
                    $support = ($support == 1 || $payment_term == 3) ? 'Y' : 'N';
                }

                switch ($payment_term) {
                    case 1:
                        $subscription_fee = $arrPriceSettings[$strPrefix . 'FeeMonthly'];
                        break;
                    case 2:
                        $subscription_fee = $arrPriceSettings[$strPrefix . 'FeeAnnual'];
                        break;
                    case 3:
                        $subscription_fee = $arrPriceSettings[$strPrefix . 'FeeBiAnnual'];
                        break;
                }
            }
            $freeUsersCount   = $arrPriceSettings[$strPrefix . 'FreeUsers'];
            $freeClientsCount = $arrPriceSettings[$strPrefix . 'FreeClients'];
            $freeStorageCount = $arrPriceSettings[$strPrefix . 'FreeStorage'];


            // Get state in relation to selected country
            $state = '';
            if ($this->_country->isDefaultCountry($country)) {
                $stateId = $filter->filter($params->fromPost('province'));
                if (!empty($stateId) && is_numeric($stateId)) {
                    $state = $this->_country->getStateLabelById($stateId);
                }
            } else {
                $state = trim($filter->filter($params->fromPost('state')) ?? '');
            }

            $packageTypeName = $this->_company->getPackages()->getSubscriptionNameById($packageType);
            $data            = array(
                'salutation'          => $filter->filter($params->fromPost('salutation')),
                'name'                => trim($filter->filter($params->fromPost('firstName')) ?? ''),
                'last_name'           => trim($filter->filter($params->fromPost('lastName')) ?? ''),
                'company'             => trim($filter->filter($params->fromPost('companyName')) ?? ''),
                'company_abn'         => trim($filter->filter($params->fromPost('company_abn')) ?? ''),
                'phone_w'             => trim($filter->filter($params->fromPost('phone1')) ?? ''),
                'email'               => trim($filter->filter($params->fromPost('companyEmail')) ?? ''),
                'address'             => trim($filter->filter($params->fromPost('address')) ?? ''),
                'city'                => trim($filter->filter($params->fromPost('city')) ?? ''),
                'state'               => $state,
                'country'             => $countryCode,
                'zip'                 => trim($filter->filter($params->fromPost('zip')) ?? ''),
                'source'              => $source,
                'key'                 => $key,
                'package_type'        => $packageType,
                'payment_term'        => $payment_term,
                'support'             => $support,
                'notes'               => '',
                'sign_in_date'        => date('Y-m-d'),
                'subscription_fee'    => (float)$subscription_fee,
                'support_fee'         => $this->_company->getPackages()->getSupportFee($payment_term, $support, $arrPriceSettings['feeTraining']),
                'free_users'          => $freeUsersCount,
                'free_clients'        => $freeClientsCount,
                'extra_users'         => $filter->filter($params->fromPost('extra_users_count')),
                'free_storage'        => $freeStorageCount,
                'pricing_category_id' => $pricingCategoryId
            );

            // Check incoming info
            $payment_term_name = $this->_company->getCompanySubscriptions()->getPaymentTermNameById($payment_term);

            if (empty($strError) && $booSignUp && $payment_term_name == 'Unknown') {
                $strError = $this->_tr->translate('Incorrectly selected payment term.');
            }

            $acceptTerms = $params->fromPost('accept_terms', 'not-found');
            if (empty($strError) && $booSignUp && ($acceptTerms === 'not-found')) {
                $strError = $this->_tr->translate('Please agree to Terms of Use.');
            }

            if (empty($strError) && (empty($data['salutation']) || empty($data['name']) || empty($data['last_name']) || empty($data['phone_w']) || empty($data['email']))) {
                $strError = $this->_tr->translate('Incorrect parameters.');
            }

            if (empty($strError) && $booSignUp && ($data['subscription_fee'] <= 0 || $data['subscription_fee'] >= 10000)) {
                $strError = $this->_tr->translate('Incorrect subscription fee.');
            }

            if (empty($strError) && $booSignUp && (!is_numeric($data['extra_users']) || $data['extra_users'] < 0 || $data['extra_users'] > 10)) {
                $strError = $this->_tr->translate('Incorrectly selected extra users count.');
            }

            if (empty($strError) && $booSignUp && empty($packageTypeName)) {
                $strError = $this->_tr->translate('Incorrectly selected package.');
            }

            // If all is correct - create record in DB
            $prospectId = 0;
            if (empty($strError)) {
                $data['package_type'] = empty($data['package_type']) ? null : $data['package_type'];

                // Get inserted prospect ID
                $prospectId = $this->createUpdateProspect(0, $data);

                // Check if prospect was created successfully
                if (empty($prospectId)) {
                    $strError = $this->_tr->translate('Error during prospect creation');
                }
            }

            if (empty($strError)) {
                $strError = $this->chargeProspect($prospectId, $params, $booSignUp);

                // Delete prospect on error
                if (!empty($strError) && !empty($prospectId)) {
                    $this->_db2->delete('prospects', ['prospect_id' => $prospectId]);
                }
            }

        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array('error' => $strError, 'key' => $key);
    }

    /**
     *** If this is a 'sign up' process: ***
     * 1. Try to charge the prospect
     *    a. on fail - delete the invoice + PT profile (if was created)
     *    b. on success - send 'Signup Completed' email to the prospect
     * 2. Send 'Company signed up' email to support
     *
     *
     *** If this is a 'demo request' process: ***
     * 1. Send 'Reply to Request a Demo' email to the prospect
     * 2. Send 'Demo requested' email to support
     *
     * @param int $prospectId
     * @param Params|mixed $params
     * @param bool $booSignUp
     * @return mixed|string
     */
    public function chargeProspect($prospectId, $params, $booSignUp)
    {
        $strError = '';

        try {
            $arrSavedProspectInfo = $this->getProspectInfo($prospectId);
            if (!isset($arrSavedProspectInfo['prospect_id'])) {
                // Cannot be here, but...
                throw new Exception('Incorrect prospect id');
            }

            $customerRefNum      = '';
            $arrCCInfo           = array();
            $booPTProfileCreated = false;

            // @NOTE: Set to true to send requests to PT
            $booUsePT = $this->_config['payment']['enabled'];

            if ($booSignUp) {
                $filter = new StripTags();

                // Collect CC data
                $arrCCInfo = array(
                    'ccType'     => ucwords(strtolower($filter->filter($params->fromPost('ccType', '')))),
                    'ccName'     => $filter->filter($params->fromPost('ccName')),
                    'ccNumber'   => str_replace(array(' ', '-'), '', $filter->filter($params->fromPost('ccNumber', ''))),
                    'ccCVN'      => $filter->filter($params->fromPost('ccCVN', '')),
                    'ccExpMonth' => $filter->filter($params->fromPost('ccExpMonth', '')),
                    'ccExpYear'  => $filter->filter($params->fromPost('ccExpYear'))
                );

                if (strlen($arrCCInfo['ccExpMonth']) == 1) {
                    $arrCCInfo['ccExpMonth'] = '0' . $arrCCInfo['ccExpMonth'];
                }

                $booTestMode = $arrCCInfo['ccNumber'] == '8888' || $arrCCInfo['ccName'] == '8888';
                if ($booUsePT && !$booTestMode) {

                    $this->_payment->init();

                    // 1. Create new profile in PT
                    $customerRefNum = $this->_payment->generatePaymentProfileId($prospectId);

                    $arrProfileInfo = array(
                        'customerName'            => $arrCCInfo['ccName'],
                        'customerRefNum'          => $customerRefNum,
                        'creditCardNum'           => $arrCCInfo['ccNumber'],
                        'creditCardExpDate'       => $arrCCInfo['ccExpMonth'] . $arrCCInfo['ccExpYear'],
                        'OrderDefaultDescription' => $arrSavedProspectInfo['company']
                    );

                    $arrResult = $this->_payment->createProfile($arrProfileInfo);

                    if ($arrResult['error']) {
                        $arrResult['message'] = 'Processing error:' .
                            "<div style='padding: 10px 0; font-style:italic;'>" . $arrResult['message'] . '</div>' .
                            'Please review your entries &amp; try again. ' .
                            'If the error shown is not related to your credit card, ' .
                            'please contact our support to resolve the issue promptly.';
                    }
                } else {
                    $arrResult = array('error' => false, 'message' => '');
                }

                $booPTProfileCreated = !$arrResult['error'];
                if ($booPTProfileCreated) {
                    // 2. Update PT profile id in Prospects table
                    $ccType          = in_array($arrCCInfo['ccType'], array('Visa', 'Mastercard')) ? $arrCCInfo['ccType'] : '';
                    $arrProspectInfo = array(
                        'paymentech_profile_id'      => $customerRefNum,
                        'paymentech_mode_of_payment' => empty($ccType) ? null : $ccType,
                    );

                    $this->_db2->update(
                        'prospects',
                        $arrProspectInfo,
                        ['prospect_id' => $prospectId]
                    );

                    if (!$booTestMode) {
                        // 3. Create First Invoice
                        $arrFirstInvoiceInfo   = array(
                            'prospect_id'     => $prospectId,
                            'customerRefNum'  => $customerRefNum,
                            'mode_of_payment' => $arrProspectInfo['paymentech_mode_of_payment'],
                        );
                        $arrFirstInvoiceResult = $this->createFirstInvoice($arrFirstInvoiceInfo, $booUsePT, $booUsePT || $booTestMode);

                        $strError = $arrFirstInvoiceResult['message'];
                        if (!empty($strError) && !empty($arrFirstInvoiceResult['invoiceId'])) {
                            // Delete created invoice...
                            $this->_company->getCompanyInvoice()->deleteInvoices(array($arrFirstInvoiceResult['invoiceId']));
                        }
                    }
                } else {
                    // Error happened
                    $strError = $arrResult['message'];
                }
            }


            if (empty($strError)) {
                // Send mails to the prospect
                try {
                    $this->sendSystemTemplateEmail(
                        $booSignUp ? 'Signup Completed' : 'Reply to Request a Demo',
                        $prospectId,
                        true
                    );
                } catch (Exception $e) {
                    $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
                }
            } else {
                // Delete profile in PT
                if ($booUsePT && $booPTProfileCreated && !is_null($this->_payment) && !$booTestMode) {
                    $this->_payment->deleteProfile($customerRefNum);
                }
            }

            if (empty($strError)) {
                // Prepare info and send email
                $arrParsedInfo                      = array_merge($arrSavedProspectInfo, $arrCCInfo);
                $arrParsedInfo['country_name']      = $this->_country->getCountryNameByCountryCode($arrSavedProspectInfo['country']);
                $arrParsedInfo['str_payment_term']  = $this->_company->getCompanySubscriptions()->getPaymentTermNameById($arrSavedProspectInfo['payment_term']);
                $arrParsedInfo['package_type_name'] = $this->_company->getPackages()->getSubscriptionNameById($arrSavedProspectInfo['package_type']);
                $arrParsedInfo['cityLabel']         = $this->_settings->getSiteCityLabel();

                $viewModel = new ViewModel($arrParsedInfo);
                $template  = $booSignUp ? 'prospects/partials/prospect-signup-success.phtml' : 'prospects/partials/prospect-request-a-demo.phtml';
                $viewModel->setTemplate($template);
                $content = $this->_renderer->render($viewModel);

                $emailValidator  = new EmailAddress();
                $arrSupportEmail = $this->_settings->getOfficioSupportEmail();
                $arrSalesEmail   = $this->_settings->getOfficioSalesEmail();

                if ($booSignUp) {
                    $subject = $this->_tr->translate('Company signed up');
                    $from    = null;

                    $to = [];
                    if (!empty($arrSupportEmail['email'])) {
                        $to[] = $arrSupportEmail['email'];
                    }

                    if (!empty($arrSalesEmail['email']) && !in_array($arrSalesEmail['email'], $to)) {
                        $to[] = $arrSalesEmail['email'];
                    }
                    $to = implode(', ', $to);

                    $bcc = null;
                } else {
                    $subject = $this->_tr->translate('Demo requested');
                    $from    = isset($arrParsedInfo['email']) && !empty($arrParsedInfo['email']) && $emailValidator->isValid($arrParsedInfo['email']) ? $arrParsedInfo['email'] : $arrSalesEmail['email'];
                    $to      = $arrSalesEmail['email'];
                    $bcc     = $arrSupportEmail['email'];
                }

                $booResult = $this->_mailer->sendEmailToSupport($subject, $content, $to, $from, $bcc);

                if (!$booResult) {
                    $strError = $this->_tr->translate('Prospect was created, but email was not sent. Please contact the web site admin.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }


    /**
     * Send system template email to the prospect and/or support
     *
     * @param string $systemTemplateName
     * @param int $prospectId
     * @param bool $booUseProspectEmailIfEmpty if true and if template's "to" is empty - will be sent to prospect's email
     * @param string $strAdditionalHtml
     */
    public function sendSystemTemplateEmail($systemTemplateName, $prospectId, $booUseProspectEmailIfEmpty = false, $strAdditionalHtml = '')
    {
        // Send mails to the prospect
        $template          = SystemTemplate::loadOne(['title' => $systemTemplateName]);
        $prospect = $this->getProspectInfo($prospectId);
        $companyDetails = $this->_company->getCompanyAndDetailsInfo($prospect['company_id']);
        $adminInfo = $this->getServiceContainer()->get(Members::class)->getMemberInfo($companyDetails['admin_id']);

        $replacements      = $this->getTemplateReplacements($prospectId);
        $replacements      += $this->_company->getTemplateReplacements($companyDetails, $adminInfo);
        $processedTemplate = $this->_systemTemplates->processTemplate($template, $replacements, ['to', 'subject', 'template']);

        if (empty($processedTemplate->to) && $booUseProspectEmailIfEmpty) {
            $prospectInfo          = $this->getProspectInfo($prospectId);
            $processedTemplate->to = $prospectInfo['email'];
        }

        if (!empty($processedTemplate->to)) {
            $processedTemplate->template .= $strAdditionalHtml;

            $arrEmails = explode(',', $processedTemplate->to ?? '');
            if (count($arrEmails) > 1) {
                $processedTemplate->to = array_map('trim', $arrEmails);
            }

            if (isset($processedTemplate->cc) && empty($processedTemplate->cc)) {
                $processedTemplate->cc = null;
            }

            if (isset($processedTemplate->bcc) && empty($processedTemplate->bcc)) {
                $processedTemplate->bcc = null;
            }

            $this->_systemTemplates->sendTemplate($processedTemplate);
        }
    }



    /**
     * Get readable string for Support field
     *
     * @param $support
     * @return string
     */
    public function getProspectSupportName($support)
    {
        return $support == 'Y' ? 'Yes' : 'No';
    }


    /**
     * Load prospect info by prospect id
     *
     * @param int $prospectId
     * @return array|bool
     */
    public function getProspectInfo($prospectId)
    {
        $prospect = false;
        if (!empty($prospectId)) {
            $select = (new Select())
                ->from('prospects')
                ->where(['prospect_id' => (int)$prospectId]);

            $prospect = $this->_db2->fetchRow($select);
        }

        return $prospect;
    }


    /**
     * Load prospect info by string key
     *
     * @param string $key
     * @return array with prospect info
     */
    public function getProspectInfoByKey($key)
    {
        $prospect = array();
        if (!empty($key)) {
            $select = (new Select())
                ->from('prospects')
                ->where(['key' => $key]);

            $prospect = $this->_db2->fetchRow($select);
        }

        return $prospect;
    }

    /**
     * Update key status for specific prospect
     *
     * @param $prospectId
     * @return bool true on success
     */
    public function setKeyStatusAsUsed($prospectId)
    {
        try {
            $this->_db2->update('prospects', ['key_status' => 'Used Once'], ['prospect_id' => (int)$prospectId]);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Reset prospect's admin info (after company was created)
     *
     * @param int $prospectId
     * @return bool
     */
    public function resetProspectAdminInfo($prospectId)
    {
        try {
            $this->_db2->update(
                'prospects',
                [
                    'admin_first_name' => null,
                    'admin_last_name'  => null,
                    'admin_email'      => null,
                    'admin_username'   => null,
                    'admin_password'   => null,
                ],
                [
                    'prospect_id' => (int)$prospectId
                ]
            );

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }


    /**
     * Update company id for specific prospect id
     *
     * @param int $prospectId
     * @param int $companyId
     * @return bool true on success
     */
    public function setCompanyId($prospectId, $companyId)
    {
        try {
            $this->_db2->update('prospects', ['company_id' => $companyId], ['prospect_id' => (int)$prospectId]);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }


    public function getProspectsList($sort, $dir, $start, $limit)
    {
        if (!in_array($dir, array('ASC', 'DESC'))) {
            $dir = 'DESC';
        }

        // All possible prospect fields (can be used for sorting)
        $arrColumns = array(
            'name', 'last_name', 'company', 'email', 'phone_w', 'phone_m', 'source', 'key', 'key_status', 'address',
            'city', 'state', 'country_display', 'zip', 'package_display', 'support', 'payment_term_display', 'status'
        );
        if (in_array($sort, $arrColumns)) {
            $sort = 'p.' . $sort;
        } else {
            $sort = 'p.prospect_id';
        }


        if (!is_numeric($start) || $start <= 0) {
            $start = 0;
        }

        if (!is_numeric($limit) || $limit <= 0) {
            $limit = $this->intShowProspectsPerPage;
        }

        $select = (new Select())
            ->from(['p' => 'prospects'])
            ->limit($limit)
            ->offset($start)
            ->order([$sort . ' ' . $dir]);

        $arrProspects = $this->_db2->fetchAll($select);
        $totalRecords = $this->_db2->fetchResultsCount($select);

        return array(
            'rows'       => $arrProspects,
            'totalCount' => $totalRecords
        );
    }

    public function getInvoiceAdditionalInfo($invoiceInfo) {
        if ($invoiceInfo['prospect_id']) {
            $arrProspectDetails                   = $this->getProspectInfo($invoiceInfo['prospect_id']);
            $invoiceInfo['payment_term']          = $arrProspectDetails['payment_term'];
            $invoiceInfo['subscription']          = $arrProspectDetails['package_type'];
            $invoiceInfo['paymentech_profile_id'] = $arrProspectDetails['paymentech_profile_id'];
            $invoiceInfo['companyName']           = $arrProspectDetails['company'];
            $invoiceInfo['pricing_category_id']   = $arrProspectDetails['pricing_category_id'];
        }

        return $invoiceInfo;
    }

    /**
     * Provides template replacements based on input data
     *
     * @param int $prospectId
     * @return array
     */
    public function getTemplateReplacements($prospectId)
    {
        $arrProspectInfo = $this->getProspectInfo($prospectId);

        return empty($arrProspectInfo) ? [] : [
            '{prospects: salutation}'             => $arrProspectInfo['salutation'] ?? '',
            '{prospects: name}'                   => $arrProspectInfo['name'] ?? '',
            '{prospects: last name}'              => $arrProspectInfo['last_name'] ?? '',
            '{prospects: company}'                => $arrProspectInfo['company'] ?? '',
            '{prospects: email}'                  => $arrProspectInfo['email'] ?? '',
            '{prospects: phone (w)}'              => $arrProspectInfo['phone_w'] ?? '',
            '{prospects: phone (m)}'              => $arrProspectInfo['phone_m'] ?? '',
            '{prospects: source}'                 => $arrProspectInfo['source'] ?? '',
            '{prospects: reg. key}'               => $arrProspectInfo['key'] ?? '',
            '{prospects: reg. key status}'        => $arrProspectInfo['key_status'] ?? '',
            '{prospects: address}'                => $arrProspectInfo['address'] ?? '',
            '{prospects: city}'                   => $arrProspectInfo['city'] ?? '',
            '{prospects: province/state}'         => $arrProspectInfo['state'] ?? '',
            '{prospects: country}'                => isset($arrProspectInfo['country']) ? $this->_country->getCountryNameByCountryCode($arrProspectInfo['country']) : '',
            '{prospects: postal code/zip}'        => $arrProspectInfo['zip'] ?? '',
            '{prospects: package}'                => !empty($arrProspectInfo['package_type']) ? $this->_company->getPackages()->getSubscriptionNameById($arrProspectInfo['package_type']) : '',
            '{prospects: training &amp; support}' => isset($arrProspectInfo['support']) ? $this->getProspectSupportName($arrProspectInfo['support']) : '',
            '{prospects: payment term}'           => !empty($arrProspectInfo['payment_term']) ? $this->_company->getCompanySubscriptions()->getPaymentTermNameById($arrProspectInfo['payment_term']) : '',
            '{prospects: paymentech profile id}'  => $arrProspectInfo['paymentech_profile_id'] ?? '',
            '{prospects: status}'                 => $arrProspectInfo['status'] ?? '',
            '{prospects: notes}'                  => $arrProspectInfo['notes'] ?? '',
            '{prospects: sign in date}'           => !empty($arrProspectInfo['sign_in_date']) ? $this->_settings->formatDate($arrProspectInfo['sign_in_date']) : '',
            '{prospects: admin_first_name}'       => $arrProspectInfo['admin_first_name'] ?? '',
            '{prospects: admin_last_name}'        => $arrProspectInfo['admin_last_name'] ?? '',
            '{prospects: admin_email}'            => $arrProspectInfo['admin_email'] ?? '',
            '{prospects: admin_username}'         => $arrProspectInfo['admin_username'] ?? '',
        ];
    }

    /**
     * Check if prospect was already charged (there is a Complete invoice assigned to the prospect)
     *
     * @param int $prospectId
     * @return bool
     */
    public function isProspectCharged($prospectId)
    {
        $booChargedBefore = false;

        // Check if we already charged this prospect. If yes - don't try to do that again
        $arrInvoices = $this->_company->getCompanyInvoice()->getProspectsInvoices(array($prospectId));
        foreach ($arrInvoices as $arrInvoiceInfo) {
            if ($arrInvoiceInfo['status'] == 'C') {
                $booChargedBefore = true;
                break;
            }
        }

        return $booChargedBefore;
    }

    /**
     * Validate provided recaptcha
     *
     * @param string $recaptcha
     * @return string
     */
    public function validateRecaptcha($recaptcha)
    {
        $strError = '';

        try {
            $settings = $this->_config['site_version']['google_recaptcha'];

            if (empty($settings['site_key']) || empty($settings['secret_key'])) {
                $strError = $this->_tr->translate('Recaptcha key(s) is not set in the config.');
            }

            if (empty($strError)) {
                $url = 'https://www.google.com/recaptcha/api/siteverify';

                $arrPost = [
                    'secret'   => $settings['secret_key'],
                    'response' => $recaptcha,
                    'remoteip' => $_SERVER['REMOTE_ADDR'] //optional field
                ];

                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($arrPost));

                if (empty($settings['check_ssl'])) {
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                }


                $response = curl_exec($curl);
                if ($z = curl_error($curl)) {
                    $errorNumber = curl_errno($curl);
                    if ($errorNumber == 28) {
                        $strError = $this->_tr->translate('Operation timeout. The specified time-out period was reached according to the conditions.');
                    } else {
                        $strError = $this->_tr->translate('Internal error');
                    }
                    $this->_log->debugErrorToFile('', 'Curl error: ' . $z . ' Url: ' . $url, 'recaptcha');
                } else {
                    $response = json_decode($response, true);
                    if (!isset($response['success']) || $response['success'] !== true) {
                        $details = $this->_tr->translate('URL: ') . $url . PHP_EOL;
                        $details .= $this->_tr->translate('Params: ') . print_r($arrPost, true) . PHP_EOL;
                        $details .= $this->_tr->translate('Response: ') . print_r($response, true) . PHP_EOL;

                        $this->_log->debugErrorToFile('', $details, 'recaptcha');
                    }
                }
                curl_close($curl);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

    /**
     * Provides list of fields available for system templates.
     * @param EventInterface $e
     * @return array
     */
    public function getSystemTemplateFields(EventInterface $e)
    {
        $templateType = $e->getParam('templateType');
        if ($templateType == 'mass_email') {
            return [];
        }

        $arrProspectsFields = array(
            array('name' => 'prospects: salutation', 'label' => 'Salutation'),
            array('name' => 'prospects: name', 'label' => 'Name'),
            array('name' => 'prospects: last name', 'label' => 'Last Name'),
            array('name' => 'prospects: company', 'label' => 'Company'),
            array('name' => 'prospects: email', 'label' => 'Email'),
            array('name' => 'prospects: phone (w)', 'label' => 'Phone (W)'),
            array('name' => 'prospects: phone (m)', 'label' => 'Phone (M)'),
            array('name' => 'prospects: source', 'label' => 'Source'),
            array('name' => 'prospects: reg. key', 'label' => 'Registration Key'),
            array('name' => 'prospects: reg. key status', 'label' => 'Reg. Key Status'),
            array('name' => 'prospects: address', 'label' => 'Address'),
            array('name' => 'prospects: city', 'label' => $this->_settings->getSiteCityLabel()),
            array('name' => 'prospects: province/state', 'label' => 'Province/State'),
            array('name' => 'prospects: country', 'label' => 'Country'),
            array('name' => 'prospects: postal code/zip', 'label' => 'Postal Code/Zip'),
            array('name' => 'prospects: package', 'label' => 'Package'),
            array('name' => 'prospects: training & support', 'label' => 'Training & Support'),
            array('name' => 'prospects: payment term', 'label' => 'Payment Term'),
            array('name' => 'prospects: paymentech profile id', 'label' => 'Paymentech Profile ID'),
            array('name' => 'prospects: status', 'label' => 'Status'),
            array('name' => 'prospects: notes', 'label' => 'Notes'),
            array('name' => 'prospects: sign in date', 'label' => 'Sign in date'),
            array('name' => 'prospects: admin_first_name', 'label' => 'Admin First Name'),
            array('name' => 'prospects: admin_last_name', 'label' => 'Admin Last Name'),
            array('name' => 'prospects: admin_email', 'label' => 'Admin Email'),
            array('name' => 'prospects: admin_username', 'label' => 'Admin Username'),
        );

        foreach ($arrProspectsFields as &$field4) {
            $field4['n']     = 3;
            $field4['group'] = 'Prospect details';
        }
        unset($field4);

        return $arrProspectsFields;
    }

}
