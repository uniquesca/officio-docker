<?php

namespace Signup\Controller;

use Exception;
use Laminas\Filter\StripTags;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Service\CompanyCreator;
use Officio\Common\Service\Country;
use Officio\Common\Service\Encryption;
use Officio\Service\GstHst;
use Officio\Service\PricingCategories;
use Officio\Service\Roles;
use Prospects\Service\Prospects;
use Laminas\Validator\EmailAddress;

/**
 * SignUp Index Controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class IndexController extends BaseController
{
    /** @var Company */
    protected $_company;

    /** @var PricingCategories */
    protected $_pricingCategories;

    /** @var Country */
    protected $_country;

    /** @var CompanyCreator */
    protected $_companyCreator;

    /** @var Prospects */
    protected $_prospects;

    /** @var Roles */
    protected $_roles;

    /** @var Encryption */
    protected $_encryption;

    /** @var GstHst */
    protected $_gstHst;

    public function initAdditionalServices(array $services)
    {
        $this->_company           = $services[Company::class];
        $this->_companyCreator    = $services[CompanyCreator::class];
        $this->_pricingCategories = $services[PricingCategories::class];
        $this->_country           = $services[Country::class];
        $this->_prospects         = $services[Prospects::class];
        $this->_roles             = $services[Roles::class];
        $this->_encryption        = $services[Encryption::class];
        $this->_gstHst            = $services[GstHst::class];
    }

    public function indexAction()
    {
        return $this->redirect()->toUrl('signup/index/payment');
    }

    public function step3Action()
    {
        $view = new ViewModel();
        $this->layout()->setTemplate('layout/bootstrap');

        $strError = '';

        try {
            $arrStep3Info = array(
                'companyName'  => '',
                'companyPhone' => '',

                'firstName'    => '',
                'lastName'     => '',
                'emailAddress' => '',
                'username'     => '',
                'accept_terms' => 0,
            );

            $prospectKey = $this->params()->fromQuery('pkey');

            $savedPlanPrice = 0;
            if (empty($prospectKey)) {
                $filter       = new StripTags();
                $specialOffer = $filter->filter($this->params()->fromPost('special_offer', 0));
                $paymentTerm  = $filter->filter($this->params()->fromPost('payment_term'));

                if ($specialOffer) {
                    $selectedSubscriptionId = 'ultimate';
                    $promotionalKey         = '';
                    $support                = 0;
                    $extraUsers             = 0;
                } else {
                    $selectedSubscriptionId = $filter->filter($this->params()->fromPost('price_package', ''));
                    $promotionalKey         = $filter->filter($this->params()->fromPost('key', ''));
                    $support                = $this->params()->fromPost('support');
                    $extraUsers             = $this->params()->fromPost('extra_users_count');
                }
            } else {
                // Get prospect info
                list($strError, $arrProspectInfo) = $this->_prospects->checkIsProspectKeyStillValid($prospectKey);

                $promotionalKey         = '';
                $selectedSubscriptionId = '';
                $paymentTerm            = '';
                $support                = 0;
                $extraUsers             = 0;
                $specialOffer           = 0;
                if (empty($strError)) {
                    $selectedSubscriptionId = $arrProspectInfo['package_type'];
                    $paymentTerm            = $arrProspectInfo['payment_term'];
                    $support                = $arrProspectInfo['support'] === 'Y';
                    $extraUsers             = $arrProspectInfo['extra_users'];

                    $arrInvoiceData = $this->_prospects->prepareDataForFirstInvoice($arrProspectInfo['prospect_id']);
                    $savedPlanPrice = $arrInvoiceData['total'];

                    if (!empty($arrProspectInfo['pricing_category_id'])) {
                        $pricingCategory = $this->_pricingCategories->getPricingCategory($arrProspectInfo['pricing_category_id']);
                        $promotionalKey  = $pricingCategory['key_string'];
                    }


                    $arrStep3Info = array(
                        'companyName'  => $arrProspectInfo['company'],
                        'companyPhone' => $arrProspectInfo['phone_w'],

                        'firstName'    => $arrProspectInfo['admin_first_name'],
                        'lastName'     => $arrProspectInfo['admin_last_name'],
                        'emailAddress' => $arrProspectInfo['admin_email'],
                        'username'     => $arrProspectInfo['admin_username'],
                        'accept_terms' => 1,
                    );
                }
            }

            if (empty($strError) && empty($selectedSubscriptionId)) {
                $strError = $this->_tr->translate('Incorrect incoming info');
            }

            if (empty($strError)) {
                $arrSubscriptions = $this->_company->getPackages()->getSubscriptionsList(true);
                if (!in_array($selectedSubscriptionId, $arrSubscriptions)) {
                    $selectedSubscriptionId = 'lite';
                }


                $pricingCategoryName = 'General';
                if ($promotionalKey) {
                    $pricingCategory = $this->_pricingCategories->getPricingCategoryByKey($promotionalKey);

                    if (is_array($pricingCategory) && !empty($pricingCategory)) {
                        if (time() <= strtotime($pricingCategory['expiry_date'] . ' 23:59:59')) {
                            $pricingCategoryName = $pricingCategory['name'];
                        }
                    }
                }

                $pricingCategoryId = $this->_pricingCategories->getPricingCategoryIdByName($pricingCategoryName);

                // Get incoming info and show it on the page
                $arrPrices = $this->_company->getCompanyPrices($this->_company->getDefaultCompanyId(), false, $pricingCategoryId);

                $name         = ucfirst($selectedSubscriptionId);
                $arrStep2Info = array(
                    'special_offer'     => $specialOffer,
                    'key'               => $promotionalKey,
                    'price_package'     => $selectedSubscriptionId,
                    'payment_term'      => $paymentTerm,
                    'support'           => (int)$support,
                    'extra_users_count' => (int)$extraUsers,
                    'price_training'    => $arrPrices['feeTraining'],
                    'price_month'       => $arrPrices['package' . $name . 'FeeMonthly'],
                    'price_annually'    => $arrPrices['package' . $name . 'FeeAnnual'],
                    'price_bi'          => $arrPrices['package' . $name . 'FeeBiAnnual'],
                    'user_included'     => $arrPrices['package' . $name . 'FreeUsers'],
                    'free_storage'      => $arrPrices['package' . $name . 'FreeStorage']
                );

                foreach ($arrSubscriptions as $subscriptionId) {
                    $arrStep2Info['price_' . $subscriptionId . '_user_license_monthly']    = $this->_company->getUserPrice(1, $subscriptionId, $pricingCategoryId);
                    $arrStep2Info['price_' . $subscriptionId . '_user_license_annually']   = $this->_company->getUserPrice(2, $subscriptionId, $pricingCategoryId);
                    $arrStep2Info['price_' . $subscriptionId . '_user_license_biannually'] = $this->_company->getUserPrice(3, $subscriptionId, $pricingCategoryId);
                }

                $view->setVariable('arrSubscriptions', $arrSubscriptions);
                $view->setVariable('arrStep2Info', $arrStep2Info);
                $view->setVariable('arrStep3Info', $arrStep3Info);
                $view->setVariable('prospectKey', $prospectKey);

                if ($specialOffer) {
                    switch ($arrStep2Info['payment_term']) {
                        case 1:
                            $subscriptionFee = $arrPrices['feeMonthly'];
                            break;

                        case 2:
                            $subscriptionFee = $arrPrices['feeAnnual'];
                            break;

                        default:
                            $subscriptionFee = '-';
                            break;
                    }
                } else {
                    switch ($arrStep2Info['payment_term']) {
                        case 1:
                            // monthly
                            $subscriptionFee = $arrStep2Info['price_month'];
                            break;

                        case 2:
                            // annualy
                            $subscriptionFee = $arrStep2Info['price_annually'];
                            break;

                        case 3:
                            // biannualy
                            $subscriptionFee = $arrStep2Info['price_bi'];
                            break;

                        default:
                            $subscriptionFee = '-';
                            break;
                    }
                }

                $view->setVariable('selectedPlanName', $this->_company->getPackages()->getSubscriptionNameById($selectedSubscriptionId));
                $view->setVariable('selectedPlanTerm', $this->_company->getCompanySubscriptions()->getPaymentTermNameById($paymentTerm));
                $view->setVariable('selectedPlanPrice', empty($prospectKey) || empty($savedPlanPrice) ? $subscriptionFee : $savedPlanPrice);
                $view->setVariable('booShowPackageSection', 1);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('strError', $strError);

        return $view;
    }

    public function createProspectAction()
    {
        $strError    = '';
        $prospectKey = '';

        try {
            $prospectKey = $this->params()->fromPost('pkey');

            $prospectId = 0;
            if (!empty($prospectKey)) {
                list($strError, $arrProspectInfo) = $this->_prospects->checkIsProspectKeyStillValid($prospectKey);
                if (empty($strError)) {
                    $prospectId = $arrProspectInfo['prospect_id'];
                }
            }

            if (empty($strError)) {
                $params = $this->params();
                $filter = new StripTags();

                $arrProspectData = array(
                    'company' => trim($filter->filter($params->fromPost('companyName')) ?? ''),
                    'phone_w' => trim($filter->filter($params->fromPost('companyPhone')) ?? ''),

                    'admin_first_name' => trim($filter->filter($params->fromPost('firstName')) ?? ''),
                    'admin_last_name'  => trim($filter->filter($params->fromPost('lastName')) ?? ''),
                    'admin_email'      => trim($filter->filter($params->fromPost('emailAddress')) ?? ''),
                    'admin_username'   => trim($filter->filter($params->fromPost('username')) ?? ''),
                    'admin_password'   => trim($filter->filter($params->fromPost('password')) ?? ''),
                );

                $acceptTerms = $params->fromPost('accept_terms', 'not-found');
                if (($acceptTerms === 'not-found')) {
                    $strError = $this->_tr->translate('Please agree to Terms of Use.');
                }

                if (empty($strError) && (empty($arrProspectData['company']) || empty($arrProspectData['phone_w']))) {
                    $strError = $this->_tr->translate('Incorrect parameters.');
                }

                if (empty($strError)) {
                    $arrAdminInfo = array(
                        'company_id'   => 0,
                        'prospect_id'  => $prospectId,
                        'fName'        => $arrProspectData['admin_first_name'],
                        'lName'        => $arrProspectData['admin_last_name'],
                        'emailAddress' => $arrProspectData['admin_email'],
                        'username'     => $arrProspectData['admin_username'],
                        'password'     => $arrProspectData['admin_password'],
                    );

                    $strError = $this->_company->checkMemberInfo($arrAdminInfo);

                    if (empty($strError) && strlen($arrProspectData['admin_password'])) {
                        $arrProspectData['admin_password'] = $this->_encryption->hashPassword($arrProspectData['admin_password']);
                    }
                }

                $booCreateProspect = empty($prospectId);
                if (empty($strError) && $booCreateProspect) {
                    $pricingCategoryName = 'General';

                    $promotionalKey = $filter->filter($params->fromPost('key'));
                    if ($promotionalKey) {
                        $pricingCategory = $this->_pricingCategories->getPricingCategoryByKey($promotionalKey);

                        if (is_array($pricingCategory) && !empty($pricingCategory) && time() <= strtotime($pricingCategory['expiry_date'])) {
                            $pricingCategoryName = $pricingCategory['name'];
                        }
                    }

                    // Before expiry section
                    $pricingCategoryId = $this->_pricingCategories->getPricingCategoryIdByName($pricingCategoryName);
                    $arrPriceSettings  = $this->_company->getCompanyPrices($this->_company->getDefaultCompanyId(), false, $pricingCategoryId);

                    $packageType = $filter->filter($params->fromPost('price_package'));
                    $paymentTerm = $filter->filter($params->fromPost('payment_term'));
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
                        $support = ($support == 1 || $paymentTerm == 3) ? 'Y' : 'N';
                    }

                    $subscriptionFee = 0;
                    switch ($paymentTerm) {
                        case 1:
                            $subscriptionFee = $arrPriceSettings[$strPrefix . 'FeeMonthly'];
                            break;
                        case 2:
                            $subscriptionFee = $arrPriceSettings[$strPrefix . 'FeeAnnual'];
                            break;
                        case 3:
                            $subscriptionFee = $arrPriceSettings[$strPrefix . 'FeeBiAnnual'];
                            break;
                    }

                    $arrNewProspectData = array(
                        'source'              => 'Sign-up Page',
                        'key'                 => $this->_prospects->generateProspectKey(),
                        'package_type'        => $packageType,
                        'payment_term'        => $paymentTerm,
                        'support'             => $support,
                        'sign_in_date'        => date('Y-m-d'),
                        'subscription_fee'    => (float)$subscriptionFee,
                        'support_fee'         => $this->_company->getPackages()->getSupportFee($paymentTerm, $support, $arrPriceSettings['feeTraining']),
                        'free_users'          => $arrPriceSettings[$strPrefix . 'FreeUsers'],
                        'free_clients'        => $arrPriceSettings[$strPrefix . 'FreeClients'],
                        'extra_users'         => $filter->filter($params->fromPost('extra_users_count')),
                        'free_storage'        => $arrPriceSettings[$strPrefix . 'FreeStorage'],
                        'pricing_category_id' => $pricingCategoryId
                    );

                    $arrProspectData = array_merge($arrProspectData, $arrNewProspectData);


                    // Check incoming info
                    $packageTypeName = $this->_company->getPackages()->getSubscriptionNameById($packageType);
                    if (empty($packageTypeName)) {
                        $strError = $this->_tr->translate('Incorrectly selected package.');
                    }

                    $paymentTermName = $this->_company->getCompanySubscriptions()->getPaymentTermNameById($paymentTerm);
                    if (empty($strError) && $paymentTermName == 'Unknown') {
                        $strError = $this->_tr->translate('Incorrectly selected payment term.');
                    }

                    if (empty($strError) && ($arrProspectData['subscription_fee'] <= 0 || $arrProspectData['subscription_fee'] >= 10000)) {
                        $strError = $this->_tr->translate('Incorrect subscription fee.');
                    }

                    if (empty($strError) && (!is_numeric($arrProspectData['extra_users']) || $arrProspectData['extra_users'] < 0 || $arrProspectData['extra_users'] > 10)) {
                        $strError = $this->_tr->translate('Incorrectly selected extra users count.');
                    }
                }

                if (empty($strError)) {
                    $prospectId = $this->_prospects->createUpdateProspect($prospectId, $arrProspectData);

                    // Check if prospect was created successfully
                    if (empty($prospectId)) {
                        $strError = $this->_tr->translate('Error during prospect creation');
                    } else {
                        if ($booCreateProspect) {
                            // Send a confirmation email to our support
                            $this->_prospects->sendSystemTemplateEmail(
                                'New Company Signup - Started',
                                $prospectId
                            );
                        }

                        $arrProspectInfo = $this->_prospects->getProspectInfo($prospectId);
                        $prospectKey     = $arrProspectInfo['key'];
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
            'message' => $strError,
            'pkey'    => $prospectKey,
        );

        $view = new JsonModel();
        return $view->setVariables($arrResult);
    }

    public function step4Action()
    {
        $view = new ViewModel();
        $this->layout()->setTemplate('layout/bootstrap');

        $googleRecaptchaKey = '';
        try {
            $prospectKey = $this->params()->fromQuery('pkey');
            list($strError, $arrProspectInfo) = $this->_prospects->checkIsProspectKeyStillValid($prospectKey);

            if (empty($strError)) {
                $arrStep4Info = $arrProspectInfo;

                // Prefill company info from the Admin details
                $arrStep4Info['email']     = empty($arrProspectInfo['email']) ? $arrProspectInfo['admin_email'] : $arrProspectInfo['email'];
                $arrStep4Info['name']      = empty($arrProspectInfo['name']) ? $arrProspectInfo['admin_first_name'] : $arrProspectInfo['name'];
                $arrStep4Info['last_name'] = empty($arrProspectInfo['last_name']) ? $arrProspectInfo['admin_last_name'] : $arrProspectInfo['last_name'];

                $arrStep4Info['ccType']     = '';
                $arrStep4Info['ccNumber']   = '';
                $arrStep4Info['ccName']     = '';
                $arrStep4Info['ccExpMonth'] = '';
                $arrStep4Info['ccExpYear']  = '';
                $arrStep4Info['ccCVN']      = '';

                if (!empty($arrStep4Info['country'])) {
                    $arrStep4Info['country'] = $this->_country->getCountryIdByCode($arrStep4Info['country']);
                }

                $view->setVariable('selectedPlanName', $this->_company->getPackages()->getSubscriptionNameById($arrProspectInfo['package_type']));
                $view->setVariable('selectedPlanTerm', $this->_company->getCompanySubscriptions()->getPaymentTermNameById($arrProspectInfo['payment_term']));

                $arrInvoiceData = $this->_prospects->prepareDataForFirstInvoice($arrProspectInfo['prospect_id']);
                $view->setVariable('selectedPlanPrice', $arrInvoiceData['total']);
                $view->setVariable('booShowPackageSection', 1);

                $view->setVariable('prospectKey', $prospectKey);
                $view->setVariable('arrStep4Info', $arrStep4Info);

                $view->setVariable('defaultCountryId', $this->_country->getDefaultCountryId());
                $view->setVariable('arrProvinces', $this->_country->getStatesList(0, true, true));
                $view->setVariable('arrCountries', $this->_country->getCountries(true));
                $view->setVariable('booShowABN', !empty($this->_config['site_version']['check_abn_enabled']));
                $view->setVariable('booProspectCharged', $this->_prospects->isProspectCharged($arrProspectInfo['prospect_id']));

                $googleRecaptchaKey = $this->_config['site_version']['google_recaptcha']['site_key'];
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('strError', $strError);
        $view->setVariable('googleRecaptchaKey', $googleRecaptchaKey);

        return $view;
    }


    public function chargeAndCreateCompanyAction()
    {
        $strError         = '';
        $booChargedBefore = false;

        try {
            $filter = new StripTags();
            $params = $this->params();

            // Make sure that prospect's key is valid
            $prospectKey = $params->fromPost('pkey');
            $prospectId  = 0;
            if (!empty($prospectKey)) {
                list($strError, $arrProspectInfo) = $this->_prospects->checkIsProspectKeyStillValid($prospectKey);
                if (empty($strError)) {
                    $prospectId = $arrProspectInfo['prospect_id'];
                }
            }

            if (empty($strError)) {
                // Check recaptcha if it is turned on
                $googleRecaptchaKey = $this->_config['site_version']['google_recaptcha']['site_key'];
                if (!empty($googleRecaptchaKey)) {
                    $strError = $this->_prospects->validateRecaptcha($params->fromPost('g-recaptcha-response'));
                }
            }


            // Check and prepare the incoming info
            $country     = (int)$filter->filter($params->fromPost('country'));
            $countryCode = $this->_country->getCountryCodeById($country);

            // Get state in relation to selected country
            $state = '';
            if ($this->_country->isDefaultCountry($country)) {
                $stateId = $filter->filter($params->fromPost('stateId'));
                if (!empty($stateId) && is_numeric($stateId)) {
                    $state = $this->_country->getStateLabelById($stateId);
                }
            } else {
                $state = trim($filter->filter($params->fromPost('state')) ?? '');
            }

            $arrProspectData = array(
                'salutation'  => $filter->filter($params->fromPost('salutation')),
                'name'        => trim($filter->filter($params->fromPost('firstName')) ?? ''),
                'last_name'   => trim($filter->filter($params->fromPost('lastName')) ?? ''),
                'company_abn' => trim($filter->filter($params->fromPost('company_abn')) ?? ''),
                'email'       => trim($filter->filter($params->fromPost('companyEmail')) ?? ''),
                'address'     => trim($filter->filter($params->fromPost('address')) ?? ''),
                'city'        => trim($filter->filter($params->fromPost('city')) ?? ''),
                'state'       => $state,
                'country'     => $countryCode,
                'zip'         => trim($filter->filter($params->fromPost('zip')) ?? ''),
            );

            if (empty($strError) && (empty($arrProspectData['salutation']) || empty($arrProspectData['name']) || empty($arrProspectData['last_name']))) {
                $strError = $this->_tr->translate('Incorrect parameters.');
            }

            if (empty($strError) && empty($arrProspectData['email'])) {
                $strError = $this->_tr->translate('Please enter email address');
            }

            if (empty($strError)) {
                $validator = new EmailAddress();
                if (!$validator->isValid($arrProspectData['email'])) {
                    // email is invalid; print the reasons
                    foreach ($validator->getMessages() as $message) {
                        $strError .= "$message\n";
                    }
                }
            }


            if (empty($strError)) {
                // Update prospect's info - add additional info
                $prospectId = $this->_prospects->createUpdateProspect($prospectId, $arrProspectData);

                if (empty($prospectId)) {
                    $strError = $this->_tr->translate('Error during prospect update');
                } else {
                    // Check if we already charged this prospect. If yes - don't try to do that again
                    $booChargedBefore = $this->_prospects->isProspectCharged($prospectId);

                    if (!$booChargedBefore) {
                        $strError = $this->_prospects->chargeProspect($prospectId, $params, true);
                    }
                }
            }

            if (empty($strError)) {
                // Create a company if everything was done correctly
                $arrSavedProspectInfo = $this->_prospects->getProspectInfo($prospectId);

                // Set access to the Admin user to all user/admin roles
                $arrDefaultRolesIds = array();
                $arrDefaultRoles    = $this->_roles->getDefaultRoles(true);
                if (is_array($arrDefaultRoles) && !empty($arrDefaultRoles)) {
                    foreach ($arrDefaultRoles as $defaultRoleInfo) {
                        $arrDefaultRolesIds[] = $defaultRoleInfo['role_id'];
                    }
                }

                $arrNewCompanyInfo = array(
                    'prospectId' => $prospectId,

                    'companyName'     => $arrSavedProspectInfo['company'],
                    'company_abn'     => $arrSavedProspectInfo['company_abn'],
                    'address'         => $arrSavedProspectInfo['address'],
                    'city'            => $arrSavedProspectInfo['city'],
                    'state'           => $arrSavedProspectInfo['state'],
                    'country'         => $this->_country->getCountryIdByCode($arrSavedProspectInfo['country']),
                    'zip'             => $arrSavedProspectInfo['zip'],
                    'phone1'          => $arrSavedProspectInfo['phone_w'],
                    'phone2'          => empty($arrSavedProspectInfo['phone_m']) ? '' : $arrSavedProspectInfo['phone_m'],
                    'fax'             => '',
                    'companyEmail'    => $arrSavedProspectInfo['email'],
                    'companyTimeZone' => $this->_country->getDefaultTimeZone(),

                    // Create 1 admin user
                    'arrUsers'        => array(
                        array(
                            'arrRoles'        => $arrDefaultRolesIds,
                            'arrUserOffices'  => array(),
                            'fName'           => $arrSavedProspectInfo['admin_first_name'],
                            'lName'           => $arrSavedProspectInfo['admin_last_name'],
                            'emailAddress'    => $arrSavedProspectInfo['admin_email'],
                            'username'        => $arrSavedProspectInfo['admin_username'],
                            'password'        => $arrSavedProspectInfo['admin_password'],
                            'hashed_password' => true, // Don't try to check or hash this password - it was already checked
                            'prospect_id'     => $prospectId
                        )
                    ),

                    'arrPackages'  => array(), // Will be loaded from the prospect's info
                    'arrOffices'   => array(), // Automatically create 1 Office (hardcoded) + assign Admin to it
                    'arrTa'        => array(), // Don't create T/A
                    'arrCMI'       => array(), // Not used here
                    'arrFreeTrial' => array(), // Not used here
                );

                $strError = $this->_companyCreator->createCompany($arrNewCompanyInfo);

                if (!empty($strError)) {
                    // Send a confirmation email that company creation failed to our support
                    $this->_prospects->sendSystemTemplateEmail(
                        'New Company Signup - Failed (Payment Successful)',
                        $prospectId,
                        false,
                        sprintf(
                            $this->_tr->translate('<h3>Reason: %s</h3>'),
                            $strError
                        )
                    );

                    $strError = $this->_tr->translate("Your payment was successful but the Admin login account creation didn't go as planned. Reason: " . $strError);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return Json result
        $arrResult = array(
            'success'         => empty($strError),
            'message'         => $strError,
            'company_charged' => $booChargedBefore
        );

        $view = new JsonModel();
        return $view->setVariables($arrResult);
    }

    /**
     * Revert to single payment step. step3/step4 actions not used anymore.
     */
    public function paymentAction()
    {
        $view = new ViewModel();
        $this->layout()->setTemplate('layout/bootstrap');

        $strError = '';

        try {
            $arrPaymentStepInfo = array(
                'name'         => '',
                'last_name'    => '',
                'company'      => '',
                'company_abn'  => '',
                'email'        => '',
                'phone_w'      => '',
                'address'      => '',
                'city'         => '',
                'state'        => '',
                'country'      => '',
                'zip'          => '',
                'ccType'       => '',
                'ccNumber'     => '',
                'ccName'       => '',
                'ccExpMonth'   => '',
                'ccExpYear'    => '',
                'ccCVN'        => '',
                'accept_terms' => '',
            );

            $savedPlanPrice = 0;

            $filter          = new StripTags();
            $booSpecialOffer = (bool)$this->params()->fromPost('special_offer', 0);
            $paymentTerm     = $filter->filter($this->params()->fromPost('payment_term'));
            if ($booSpecialOffer) {
                $selectedSubscriptionId = 'ultimate';
                $promotionalKey         = '';
                $support                = 0;
                $extraUsers             = 0;
            } else {
                $selectedSubscriptionId = $filter->filter($this->params()->fromPost('price_package', ''));
                $promotionalKey         = $filter->filter($this->params()->fromPost('key', ''));
                $support                = $this->params()->fromPost('support');
                $extraUsers             = $this->params()->fromPost('extra_users_count');
            }


            if (empty($strError) && empty($selectedSubscriptionId)) {
                $strError = $this->_tr->translate('Incorrect incoming info');
            }

            if (empty($strError)) {
                $arrSubscriptions = $this->_company->getPackages()->getSubscriptionsList(true);
                if (!in_array($selectedSubscriptionId, $arrSubscriptions)) {
                    $selectedSubscriptionId = 'lite';
                }

                $pricingCategoryName = 'General';
                if ($promotionalKey) {
                    $pricingCategory = $this->_pricingCategories->getPricingCategoryByKey($promotionalKey);

                    if (is_array($pricingCategory) && !empty($pricingCategory)) {
                        if (time() <= strtotime($pricingCategory['expiry_date'] . ' 23:59:59')) {
                            $pricingCategoryName = $pricingCategory['name'];
                        }
                    }
                }

                $pricingCategoryId = $this->_pricingCategories->getPricingCategoryIdByName($pricingCategoryName);

                // Get incoming info and show it on the page
                $arrPrices = $this->_company->getCompanyPrices(0, false, $pricingCategoryId);

                $name         = ucfirst($selectedSubscriptionId);
                $arrStep2Info = array(
                    'special_offer'     => (int)$booSpecialOffer,
                    'key'               => $promotionalKey,
                    'price_package'     => $selectedSubscriptionId,
                    'payment_term'      => $paymentTerm,
                    'support'           => (int)$support,
                    'extra_users_count' => (int)$extraUsers,
                    'price_training'    => $arrPrices['feeTraining'],
                    'price_month'       => $booSpecialOffer ? $arrPrices['feeMonthly'] : $arrPrices['package' . $name . 'FeeMonthly'],
                    'price_annually'    => $booSpecialOffer ? $arrPrices['feeAnnual'] : $arrPrices['package' . $name . 'FeeAnnual'],
                    'price_bi'          => $booSpecialOffer ? $arrPrices['feeAnnual'] * 2 : $arrPrices['package' . $name . 'FeeBiAnnual'],
                    'user_included'     => $booSpecialOffer ? $arrPrices['freeUsers'] : $arrPrices['package' . $name . 'FreeUsers'],
                    'free_storage'      => $arrPrices['package' . $name . 'FreeStorage']
                );

                $view->setVariable('arrStep2Info', $arrStep2Info);
                $view->setVariable('arrPaymentStepInfo', $arrPaymentStepInfo);

                switch ($arrStep2Info['payment_term']) {
                    case 1:
                        // monthly
                        $subscriptionFee = $arrStep2Info['price_month'];
                        break;

                    case 2:
                        // annualy
                        $subscriptionFee = $arrStep2Info['price_annually'];
                        break;

                    case 3:
                        // biannualy
                        $subscriptionFee = $arrStep2Info['price_bi'];
                        break;

                    default:
                        $subscriptionFee = '-';
                        break;
                }

                $selectedPlanName       = $this->_company->getPackages()->getSubscriptionNameById($selectedSubscriptionId) ?? '';
                $selectedPlanNameParsed = strpos($selectedPlanName, "Officio") === 0 ? substr_replace($selectedPlanName, " ", 7, 0) : $selectedPlanName;
                $view->setVariable('selectedPlanName', $selectedPlanNameParsed);
                $view->setVariable('selectedPlanTerm', $this->_company->getCompanySubscriptions()->getPaymentTermNameById($paymentTerm));
                $view->setVariable('selectedPlanPrice', empty($prospectKey) || empty($savedPlanPrice) ? $subscriptionFee : $savedPlanPrice);
                $view->setVariable('booShowPackageSection', 0);
                $view->setVariable('defaultCountryId', $this->_country->getDefaultCountryId());
                $view->setVariable('booShowABN', !empty($this->_config['site_version']['check_abn_enabled']));
                $view->setVariable('arrStates', $this->_country->getStatesList(0, true, true));
                $view->setVariable('arrCountries', $this->_country->getCountries(true));
                $view->setVariable('AuCountryId', $this->_country->getCountryIdByCode('AUS'));
                $view->setVariable('CaCountryId', $this->_country->getCountryIdByCode('CAN'));

                // Get canadian province taxes
                $arrStatesTax   = $this->_gstHst->getProvincesList(true);
                $arrStatesTaxCA = [];
                foreach ($arrStatesTax as $stateId => $info) {
                    if ($stateId >= 1 && $stateId <= 13) {
                        $arrStatesTaxCA[$stateId] = $info;
                    }
                }
                $view->setVariable('arrStatesTaxCA', $arrStatesTaxCA);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('strError', $strError);
        $view->setVariable('googleRecaptchaKey', $this->_config['site_version']['google_recaptcha']['site_key']);
        $view->setVariable('googleTagManagerContainerId', $this->_config['site_version']['google_tag_manager']['container_id']);

        return $view;
    }

    /**
     * Creates propect, charge and create company all in one.
     */
    public function paymentSubmitAction()
    {
        $strError    = '';
        $transaction = null;

        try {
            $params = $this->params();
            $filter = new StripTags();

            // Check recaptcha if it is turned on
            if (!empty($this->_config['site_version']['google_recaptcha']['site_key'])) {
                $strError = $this->_prospects->validateRecaptcha($params->fromPost('g-recaptcha-response'));
            }

            // Check incoming info
            $acceptTerms = $params->fromPost('accept_terms', 'not-found');
            if (empty($strError) && ($acceptTerms === 'not-found')) {
                $strError = $this->_tr->translate('Please agree to Terms of Use.');
            }

            $paymentTerm     = '';
            $paymentTermName = '';
            if (empty($strError)) {
                $paymentTerm     = $filter->filter($params->fromPost('payment_term'));
                $paymentTermName = $this->_company->getCompanySubscriptions()->getPaymentTermNameById($paymentTerm);
                if ($paymentTermName == 'Unknown') {
                    $strError = $this->_tr->translate('Incorrectly selected payment term.');
                }
            }

            $booSpecialOffer = (bool)$params->fromPost('special_offer', 0);

            $packageType         = '';
            $support             = 'N';
            $extraUsers          = 0;
            $pricingCategoryName = 'General';
            if (empty($strError)) {
                if ($booSpecialOffer) {
                    $packageType = 'ultimate';
                } else {
                    $packageType = $filter->filter($params->fromPost('price_package'));
                    $extraUsers  = $filter->filter($params->fromPost('extra_users_count'));

                    $promotionalKey = $filter->filter($params->fromPost('key'));
                    if ($promotionalKey) {
                        $pricingCategory = $this->_pricingCategories->getPricingCategoryByKey($promotionalKey);

                        if (is_array($pricingCategory) && !empty($pricingCategory) && time() <= strtotime($pricingCategory['expiry_date'])) {
                            $pricingCategoryName = $pricingCategory['name'];
                        }
                    }

                    $support = (int)$filter->filter($params->fromPost('support'));
                    if ($this->_config['site_version']['version'] == 'australia') {
                        $support = 'Y';
                    } else {
                        $support = ($support == 1 || $paymentTerm == 3) ? 'Y' : 'N';
                    }
                }
            }

            $packageTypeName = '';
            if (empty($strError)) {
                $packageTypeName = $this->_company->getPackages()->getSubscriptionNameById($packageType);
                if (empty($packageTypeName)) {
                    $strError = $this->_tr->translate('Incorrectly selected package.');
                }
            }

            $arrProspectData = [];
            if (empty($strError)) {
                $arrProspectData = array(
                    'name'        => trim($filter->filter($params->fromPost('name')) ?? ''),
                    'last_name'   => trim($filter->filter($params->fromPost('last_name')) ?? ''),
                    'company'     => trim($filter->filter($params->fromPost('company')) ?? ''),
                    'company_abn' => trim($filter->filter($params->fromPost('company_abn')) ?? ''),
                    'email'       => trim($filter->filter($params->fromPost('email')) ?? ''),
                    'phone_w'     => trim($filter->filter($params->fromPost('phone_w')) ?? ''),
                    'address'     => trim($filter->filter($params->fromPost('address')) ?? ''),
                    'city'        => trim($filter->filter($params->fromPost('city')) ?? ''),
                    'country'     => trim($filter->filter($params->fromPost('country')) ?? ''),
                    'state'       => trim($filter->filter($params->fromPost('state')) ?? ''),
                    'zip'         => trim($filter->filter($params->fromPost('zip')) ?? ''),
                );

                // Get state in relation to selected country
                if ($this->_country->isDefaultCountry($arrProspectData['country'])) {
                    $stateId = $filter->filter($params->fromPost('stateId'));
                    if (!empty($stateId) && is_numeric($stateId)) {
                        $arrProspectData['state'] = $this->_country->getStateLabelById($stateId);
                    }
                }
            }

            if (empty($strError) && empty($arrProspectData['email'])) {
                $strError = $this->_tr->translate('Please enter email address');
            }

            if (empty($strError)) {
                $validator = new EmailAddress();
                if (!$validator->isValid($arrProspectData['email'])) {
                    // email is invalid; print the reasons
                    foreach ($validator->getMessages() as $message) {
                        $strError .= "$message\n";
                    }
                }
            }

            $prospectKey     = '';
            $subscriptionFee = 0;
            $monthlyPrice    = 0;
            $yearlyPrice     = 0;
            $biYearlyPrice   = 0;
            if (empty($strError)) {
                // Before expiry section
                $pricingCategoryId = $this->_pricingCategories->getPricingCategoryIdByName($pricingCategoryName);
                $arrPriceSettings  = $this->_company->getCompanyPrices($this->_company->getDefaultCompanyId(), false, $pricingCategoryId);

                $name = ucfirst($packageType);
                if ($booSpecialOffer) {
                    $monthlyPrice  = $arrPriceSettings['feeMonthly'];
                    $yearlyPrice   = $arrPriceSettings['feeAnnual'];
                    $biYearlyPrice = $yearlyPrice * 2;
                } else {
                    $monthlyPrice  = $arrPriceSettings['package' . $name . 'FeeMonthly'];
                    $yearlyPrice   = $arrPriceSettings['package' . $name . 'FeeAnnual'];
                    $biYearlyPrice = $arrPriceSettings['package' . $name . 'FeeBiAnnual'];
                }

                switch ($paymentTerm) {
                    case 1:
                        $subscriptionFee = $monthlyPrice;
                        break;
                    case 2:
                        $subscriptionFee = $yearlyPrice;
                        break;
                    case 3:
                        $subscriptionFee = $biYearlyPrice;
                        break;
                }

                $prospectKey = $this->_prospects->generateProspectKey();

                $arrNewProspectData = array(
                    'source'              => $booSpecialOffer ? 'Special Offer Page' : 'Sign-up Page',
                    'key'                 => $prospectKey,
                    'package_type'        => $packageType,
                    'payment_term'        => $paymentTerm,
                    'support'             => $support,
                    'sign_in_date'        => date('Y-m-d'),
                    'subscription_fee'    => (float)$subscriptionFee,
                    'support_fee'         => $this->_company->getPackages()->getSupportFee($paymentTerm, $support, $arrPriceSettings['feeTraining']),
                    'free_users'          => $booSpecialOffer ? $arrPriceSettings['freeUsers'] : $arrPriceSettings['package' . $name . 'FreeUsers'],
                    'free_clients'        => $arrPriceSettings['package' . $name . 'FreeClients'],
                    'extra_users'         => $extraUsers,
                    'free_storage'        => $arrPriceSettings['package' . $name . 'FreeStorage'],
                    'pricing_category_id' => $pricingCategoryId
                );

                $arrProspectData = array_merge($arrProspectData, $arrNewProspectData);
            }

            if (empty($strError) && ($arrProspectData['subscription_fee'] <= 0 || $arrProspectData['subscription_fee'] >= 10000)) {
                $strError = $this->_tr->translate('Incorrect subscription fee.');
            }

            if (empty($strError) && (!is_numeric($arrProspectData['extra_users']) || $arrProspectData['extra_users'] < 0 || $arrProspectData['extra_users'] > 10)) {
                $strError = $this->_tr->translate('Incorrectly selected extra users count.');
            }

            if (empty($strError)) {
                $prospectId = $this->_prospects->createUpdateProspect(0, $arrProspectData);

                // Check if prospect was created successfully
                if (empty($prospectId)) {
                    $strError = $this->_tr->translate('Error during prospect creation');
                } else {
                    $strError = $this->_prospects->chargeProspect($prospectId, $params, true);
                }
            }

            if (empty($strError)) {
                $transaction = [
                    'transaction_id'    => $prospectKey,
                    'amount'            => $subscriptionFee,
                    'currency'          => $this->_config['site_version']['version'] == 'australia' ? 'AUD' : 'CAD',
                    'payment_term'      => $paymentTerm,
                    'payment_term_name' => $paymentTermName,
                    'item_id'           => $packageType,
                    'item_name'         => $packageTypeName,
                    'month_price'       => $monthlyPrice,
                    'annually_price'    => $yearlyPrice,
                    'bi_price'          => $biYearlyPrice,
                ];
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return Json result
        $arrResult = array(
            'success'     => empty($strError),
            'message'     => $strError,
            'pkey'        => empty($prospectKey) ? '' : $prospectKey,
            'transaction' => $transaction
        );

        return new JsonModel($arrResult);
    }
}
