<?php

namespace Api\Controller;

use Clients\Service\Analytics;
use Clients\Service\Clients;
use Clients\Service\Clients\Fields;
use Exception;
use Help\Service\Help;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\Validator\EmailAddress;
use Laminas\View\Helper\Partial;
use Laminas\View\HelperPluginManager;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Common\Service\AccessLogs;
use Officio\Comms\Service\Mailer;
use Officio\Service\AuthHelper;
use Officio\Service\AutomatedBillingLog;
use Officio\Service\AutomaticReminders;
use Officio\Service\Company;
use Officio\Service\CompanyCreator;
use Officio\Common\Service\Encryption;
use Officio\Service\PricingCategories;
use Officio\Service\Roles;
use Officio\Service\Users;
use Prospects\Service\Prospects;

/**
 * API Index Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class IndexController extends BaseController
{

    /** @var Company */
    protected $_company;

    /** @var CompanyCreator */
    protected $_companyCreator;

    /** @var AccessLogs */
    protected $_accessLogs;

    /** @var Users */
    protected $_users;

    /** @var Clients */
    protected $_clients;

    /** @var Analytics */
    protected $_analytics;

    /** @var AutomatedBillingLog */
    protected $_automatedBillingLog;

    /** @var AutomaticReminders */
    protected $_automaticReminders;

    /** @var PricingCategories */
    protected $_pricingCategories;

    /** @var AuthHelper */
    protected $_authHelper;

    /** @var Help */
    protected $_help;

    /** @var Prospects */
    protected $_prospects;

    /** @var Roles */
    protected $_roles;

    /** @var Encryption */
    protected $_encryption;

    /** @var Mailer */
    protected $_mailer;

    public function initAdditionalServices(array $services)
    {
        $this->_accessLogs          = $services[AccessLogs::class];
        $this->_users               = $services[Users::class];
        $this->_clients             = $services[Clients::class];
        $this->_company             = $services[Company::class];
        $this->_companyCreator      = $services[CompanyCreator::class];
        $this->_analytics           = $services[Analytics::class];
        $this->_automaticReminders  = $services[AutomaticReminders::class];
        $this->_automatedBillingLog = $services[AutomatedBillingLog::class];
        $this->_pricingCategories   = $services[PricingCategories::class];
        $this->_authHelper          = $services[AuthHelper::class];
        $this->_help                = $services[Help::class];
        $this->_prospects           = $services[Prospects::class];
        $this->_roles               = $services[Roles::class];
        $this->_encryption          = $services[Encryption::class];
        $this->_mailer              = $services[Mailer::class];
    }

    public function indexAction()
    {
        // Do nothing...
        $view = new ViewModel(
            ['content' => null]
        );
        $view->setTerminal(true);
        return $view;
    }

    /**
     * Check username if it is unique
     *
     * echo true if username not exists in DB, otherwise false
     */
    public function checkUsernameAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        $filter = new StripTags();

        // Check username (email) if it is unique
        $username = '';
        $arrParams = $this->findParams();
        foreach ($arrParams as $key => $val) {
            if (preg_match('/^username(\d*)$/', $key)) {
                $username = $filter->filter($val);
                break;
            }
        }

        // If prospect key was passed - find a prospect and skip it during username check
        $prospectId  = 0;
        $prospectKey = $this->params()->fromQuery('pkey');
        if (!empty($prospectKey)) {
            list($strError, $arrProspectInfo) = $this->_prospects->checkIsProspectKeyStillValid($prospectKey);
            if (empty($strError)) {
                $prospectId = $arrProspectInfo['prospect_id'];
            }
        }

        $booExits = empty($username) ? true : $this->_members->isUsernameAlreadyUsed($username, 0, $prospectId);

        $strResult = !$booExits ? 'true' : 'false';

        $view->setVariable('content', $strResult);

        return $view;
    }


    /**
     * Run recurring billing for all companies which:
     * 1. Must be processed today (next billing date is in the past (less than today))
     * 2. Are on monthly basis
     * 3. Have PT profile id
     * 4. Are active
     *
     * Also try charge failed invoices for active companies
     */
    public function runRecurringPaymentsAction()
    {
        // Collect all previous failed invoices and try charge them
        $arrChargeResult = $this->_company->getCompanyInvoice()->chargePreviousFailedInvoices();
        $sessionId = $arrChargeResult['session_id'];
        if (empty($arrChargeResult['failed_invoices']) && empty($arrChargeResult['success_invoices'])) {
            $strFailedInvoicesResult = 'No failed invoices were processed today.';
        } else {
            $strFailedInvoicesResult = sprintf(
                '%d failed %s %s charged successfully and %d %s failed. Please see logs in superadmin panel.',
                $arrChargeResult['success_invoices'],
                $arrChargeResult['success_invoices'] == 1 ? 'invoice' : 'invoices',
                $arrChargeResult['success_invoices'] == 1 ? 'was' : 'were',
                $arrChargeResult['failed_invoices'],
                $arrChargeResult['failed_invoices'] == 1 ? 'was' : 'were'
            );
        }

        // Collect all companies which we need process
        $select = (new Select())
            ->from(array('c' => 'company'))
            ->columns(['companyName', 'Status', 'company_abn'])
            ->join(array('d' => 'company_details'), 'c.company_id = d.company_id', Select::SQL_STAR, Select::JOIN_LEFT)
            ->where(
                [
                    (new Where())->lessThanOrEqualTo('d.next_billing_date', date('Y-m-d')),
                    (new Where())->isNotNull('d.paymentech_profile_id'),
                    'd.payment_term' => $this->_company->getCompanySubscriptions()->getPaymentTermIdByName('monthly'),
                    'c.Status' => 1
                ]
            )
            ->order('c.companyName');

        $arrCompanies = $this->_db2->fetchAll($select);

        // Run recurring process
        if (count($arrCompanies) > 0) {
            $arrResult = $this->_company->getCompanyInvoice()->createInvoice($arrCompanies, false);

            // Check result, save to DB
            $intCount = count($arrResult['arrCompaniesResult']);
            if ($intCount > 0) {
                $this->_automatedBillingLog->saveSession($arrResult['arrCompaniesResult'], $sessionId);
            }

            $strCompaniesResult = sprintf(
                '%d %s processed today. Please see logs in superadmin panel.',
                $intCount,
                $intCount == 1 ? 'company was' : 'companies were'
            );
        } else { // All is okay, no any companies have subscriptions today
            $strCompaniesResult = 'No companies were processed today.';
            $arrResult = array();
        }

        $arrCharges = array(
            'siteTitle' => $this->layout()->getVariable('siteTitle'),

            'arrFailedCharges' => array_merge(
                isset($arrResult['arrCharges']) && is_array($arrResult['arrCharges']) ? $arrResult['arrCharges'] : array(),
                isset($arrChargeResult['invoices_array']) && is_array($arrChargeResult['invoices_array']) ? $arrChargeResult['invoices_array'] : array()
            )
        );

        // sort by company name
        usort(
            $arrCharges['arrFailedCharges'],
            function ($a, $b) {
                return $a['company_name'] > $b['company_name'];
            }
        );

        $intSuccessCharged = $intFailedCount = 0;
        foreach ($arrCharges['arrFailedCharges'] as $key => $a) {
            if ($a['success']) {
                $intSuccessCharged++;
            } else {
                $intFailedCount++;
            }

            $arrCharges['arrFailedCharges'][$key]['amount'] = $this->_clients->getAccounting()::formatPrice($a['amount'], $this->_settings->getCurrentCurrency());
        }

        // mail successful/unsuccessful companies
        /** @var HelperPluginManager $viewHelper */
        $viewHelper = $this->_serviceManager->get('ViewHelperManager');
        /** @var Partial $partialHelper */
        $partialHelper = $viewHelper->get('partial');

        $view = new ViewModel($arrCharges);
        $view->setTemplate('api/index/email-charging-companies-failed.phtml');
        $strMessage = $partialHelper($view);

        $strSubject = sprintf(
            $this->_config['site_version']['name'] . ': Company charging log (%d successfully charged, %d failed)',
            $intSuccessCharged,
            $intFailedCount
        );

        $this->_mailer->processAndSendMail(
            $this->_config['settings']['send_fatal_errors_to'],
            $strSubject,
            $strMessage,
            null,
            null,
            null,
            [],
            true,
            $this->_mailer->getOfficioSmtpTransport('business_use')
        );

        // Show result in browser
        $strHtmlMessage = sprintf(
            "<h2>*** Recurring Payments <i>%s</i></h2><ul><li>%s</li><li>%s</li></ul>",
            date('Y-m-d H:i:s'),
            $strCompaniesResult,
            $strFailedInvoicesResult
        );

        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);
        $view->setVariable('content', "$strHtmlMessage");

        return $view;
    }


    /**
     * This action is called from the marketing website
     * @return ViewModel
     */
    public function addProspectAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        $arrResult = $this->_prospects->addProspect($this->findParams());
        if (empty($arrResult['error'])) {
            $strResult = '0|' . $arrResult['key'];
        } else {
            $strResult = '1|' . $arrResult['error'];
        }


        $view->setVariable('content', $strResult);

        return $view;
    }

    public function sendSupportRequestAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        $filter = new StripTags();

        $requestInfo = array(
            'email' => $filter->filter($this->findParam('email')),
            'company' => $filter->filter($this->findParam('company')),
            'name' => $filter->filter($this->findParam('name')),
            'phone' => $filter->filter($this->findParam('phone')),
            'request' => nl2br($filter->filter($this->findParam('description', '')))
        );

        // Email
        $validator = new EmailAddress();
        if (empty($requestInfo['email']) || !$validator->isValid($requestInfo['email']) || empty($requestInfo['request'])) {
            $view->setVariable('content', null);

            return $view;
        }

        $requestSent = $this->_help->sendRequest($requestInfo);
        $view->setVariable('content', $requestSent === true ? 'Your request was successfully sent!' : $requestSent);
        return $view;
    }

    public function addCompanyAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);
        if (!$this->getRequest()->isXmlHttpRequest()) {
            $view->setVariable('content', null);
            return $view;
        }

        try {
            $arrPostInfo = Json::decode($this->findParam('submitInfo'), Json::TYPE_ARRAY);

            $errMsg = $this->_companyCreator->createCompany($arrPostInfo);
        } catch (Exception $e) {
            $errMsg = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('content', $errMsg);

        return $view;
    }


    /**
     * TODO: refactor with view
     * @deprecated
     */
    public function getClientsListAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        $arrClients = $this->_clients->getClientsWithEmails();

        $retHTML = '<table class="clientChoose">';
        $retHTML .= '<tr><td><label for="clientsList">Save To: </label></td><td><select id="clientsList" name="clientsList">';
        $retHTML .= '<option value="" name="">Please select client</option>';
        foreach ($arrClients as $client) {
            $retHTML .= '<option value="' . $client['member_id'] . '" name="' . implode(',', $client['emails']) . '">' . $client['full_name_with_num'] . '</option>';
        }
        $retHTML .= '</select></td></tr>';

        if ($this->findParam('newMsg') != 'true') {
            $retHTML .= '<tr><td><input type="checkbox" name="saveAttachments" id="saveAttachments"/></td><td><label for="saveAttachments">Save attachment(s) only</label></td></tr>';
            $retHTML .= '<tr><td><input type="checkbox" name="removeFromInbox" checked="checked" id="removeFromInbox"/></td><td><label for="removeFromInbox">Remove from Inbox after Saving to client</label></td></tr>';
        } else {
            $retHTML .= '<tr><td><input type="checkbox" disabled="disabled" checked="checked" name="saveEmailTypeCurrent" id="saveEmailTypeCurrent" value="1"/></td><td><label for="saveEmailTypeCurrent">Save this email</label></td></tr>';
            $retHTML .= '<tr><td><input type="checkbox" name="saveEmailTypeOriginal" checked="checked" id="saveEmailTypeOriginal" value="2"/></td><td><label for="saveEmailTypeOriginal">Save the original email</label></td></tr>';
            $retHTML .= '<tr><td>&nbsp;</td><td>&nbsp;</td></tr>';
            $retHTML .= '<tr><td><input type="checkbox" name="removeFromInbox" checked="checked" id="removeFromInbox"/></td><td><label for="removeFromInbox">Remove original email from Inbox after Saving to client</label></td></tr>';
        }
        $retHTML .= '</table>';
        if (count($arrClients) == 0) {
            $retHTML = 'N;';
        }

        $view->setVariable('content', $retHTML);

        return $view;
    }

    /**
     * Check for incoming params (if they are filled)
     *
     * @param array $arrKeys
     * @param array $arrToCheck
     *
     * @return bool true if all array elements for these keys are filled
     */
    private function _areSuchKeysFilled($arrKeys, $arrToCheck)
    {
        $booAllIsCorrect = true;
        foreach ($arrKeys as $key) {
            if (!array_key_exists($key, $arrToCheck) || empty($arrToCheck[$key])) {
                $booAllIsCorrect = false;
                break;
            }
        }
        return $booAllIsCorrect;
    }


    /**
     * Save SMTP settings from webmail
     * (When SMTP settings of specific user will be updated on webmail's side)
     * @deprecated
     */
    public function saveSmtpSettingsAction()
    {
        $view = new ViewModel(
            ['content' => null]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        $memberId = $this->_auth->getCurrentUserId();
        $filter   = new StripTags();

        // User must be logged in to use this functionality
        if (!empty($memberId)) {
            $smtpPort      = $filter->filter($this->findParam('MailOutPort'));
            $arrUpdateInfo = array(
                'member_id'     => $memberId,
                'smtp_host'     => $this->findParam('MailOutHost'),
                'smtp_port'     => $smtpPort,
                'smtp_use_ssl'  => $smtpPort == '465' ? 'ssl' : ($smtpPort == '587' ? 'tls' : ''),
                'smtp_username' => $filter->filter($this->findParam('MailOutLogin')),
                'smtp_password' => $filter->filter($this->findParam('MailOutPassword')),
                'smtp_use_own' => $this->findParam('smtp_use_own') == 1 ? 'Y' : 'N'
            );

            $arrKeysToCheck = array('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password');
            if ($this->_areSuchKeysFilled($arrKeysToCheck, $arrUpdateInfo)) {
                $this->_members->updateMemberSMTPSettings($arrUpdateInfo);
            } else {
                // Something incorrect, save to log
                $this->_log->debugErrorToFile('Error on smtp settings saving:', print_r($arrUpdateInfo, true), 'mail');
            }
        }
        return $view;
    }

    public function getPricesAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        try {
            $filter = new StripTags();
            $key = $filter->filter($this->findParam('key', ''));
            $booExpired = true;
            $expiryDate = '';
            $keyMessage = '';

            $generalCategoryName = $pricingCategoryName = 'General';

            if ($key) {
                $pricingCategory = $this->_pricingCategories->getPricingCategoryByKey($key);

                if (is_array($pricingCategory) && !empty($pricingCategory) && time() <= strtotime($pricingCategory['expiry_date'])) {
                    $pricingCategoryName = $pricingCategory['name'];
                    $booExpired = false;
                    $expiryDate = $this->_settings->formatDate($pricingCategory['expiry_date'], false, 'M jS, Y');
                    $keyMessage = $pricingCategory['key_message'];
                }
            } else {
                $replacingGeneralPricingCategory = $this->_pricingCategories->getReplacingGeneralPricingCategory();
                if (!empty($replacingGeneralPricingCategory)) {
                    $generalCategoryName = $pricingCategoryName = $replacingGeneralPricingCategory['name'];
                }
            }

            $pricingCategoryId = $this->_pricingCategories->getPricingCategoryIdByName($pricingCategoryName);
            $arrPrices = $this->_company->getCompanyPrices(0, false, $pricingCategoryId);

            $generalPricingCategoryId = $this->_pricingCategories->getPricingCategoryIdByName($generalCategoryName);
            $generalArrPrices = $this->_company->getCompanyPrices(0, false, $generalPricingCategoryId);

            $arrPackages = array();
            $arrSubscriptions = $this->_company->getPackages()->getSubscriptionsList();
            foreach ($arrSubscriptions as $arrSubscriptionInfo) {
                $arrPackages[] = array(
                    'package_id' => $arrSubscriptionInfo['subscription_id'],
                    'package_name' => $arrSubscriptionInfo['subscription_name']
                );
            }

            $pricingCategory = $this->_pricingCategories->getPricingCategory($pricingCategoryId);

            $arrResult = array(
                'training' => (int)$arrPrices['feeTraining'],
                'packages' => $arrPackages,
                'key' => $key,
                'key_message' => $keyMessage,
                'expired_key' => $booExpired,
                'expired_date' => $expiryDate,
                'default_subscription_term' => isset($pricingCategory['default_subscription_term']) && !empty($pricingCategory['default_subscription_term']) ? $pricingCategory['default_subscription_term'] : 'annual'
            );


            foreach ($arrSubscriptions as $arrSubscriptionInfo) {
                $subscriptionId = $arrSubscriptionInfo['subscription_id'] ?? '';
                $name = ucfirst($subscriptionId);

                $arrResult['user_licenses_included'][$subscriptionId] = $arrPrices['package' . $name . 'FreeUsers'];
                $arrResult['clients_licenses_included'][$subscriptionId] = $arrPrices['package' . $name . 'FreeClients'];
                $arrResult['storage'][$subscriptionId] = $arrPrices['package' . $name . 'FreeStorage'];

                $arrResult['user_licenses_included_original'][$subscriptionId] = $generalArrPrices['package' . $name . 'FreeUsers'];
                $arrResult['clients_licenses_included_original'][$subscriptionId] = $generalArrPrices['package' . $name . 'FreeClients'];
                $arrResult['storage_original'][$subscriptionId] = $generalArrPrices['package' . $name . 'FreeStorage'];

                $arrResult['month_price'][$subscriptionId] = (int)$arrPrices['package' . $name . 'FeeMonthly'];
                $arrResult['annually_price'][$subscriptionId] = (int)$arrPrices['package' . $name . 'FeeAnnual'];
                $arrResult['bi_price'][$subscriptionId] = (int)$arrPrices['package' . $name . 'FeeBiAnnual'];

                $arrResult['month_price_original'][$subscriptionId] = (int)$generalArrPrices['package' . $name . 'FeeMonthly'];
                $arrResult['annually_price_original'][$subscriptionId] = (int)$generalArrPrices['package' . $name . 'FeeAnnual'];
                $arrResult['bi_price_original'][$subscriptionId] = (int)$generalArrPrices['package' . $name . 'FeeBiAnnual'];

                $arrResult['user_license_month_price'][$subscriptionId] = $this->_company->getUserPrice(1, $subscriptionId, $pricingCategoryId);
                $arrResult['user_license_annually_price'][$subscriptionId] = $this->_company->getUserPrice(2, $subscriptionId, $pricingCategoryId);
                $arrResult['user_license_bi_price'][$subscriptionId] = $this->_company->getUserPrice(3, $subscriptionId, $pricingCategoryId);

                $arrResult['user_license_month_price_original'][$subscriptionId] = $this->_company->getUserPrice(1, $subscriptionId, $generalPricingCategoryId);
                $arrResult['user_license_annually_price_original'][$subscriptionId] = $this->_company->getUserPrice(2, $subscriptionId, $generalPricingCategoryId);
                $arrResult['user_license_bi_price_original'][$subscriptionId] = $this->_company->getUserPrice(3, $subscriptionId, $generalPricingCategoryId);
            }
        } catch (Exception $e) {
            $arrResult = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('content', 'jsonpCallback(' . Json::encode($arrResult) . ')');

        $headers = $this->getResponse()->getHeaders();
        $headers->addHeaders(['Content-Type' => 'application/javascript']);
        $this->getResponse()->setHeaders($headers);

        return $view;
    }

    public function registerAgentAction()
    {
        $view = new ViewModel();

        $hash = '';
        $strError = '';
        $userOffice = '';
        $booCreated = false;
        $booShowForm = true;
        $arrTimeZones = array();

        // Default values
        $arrMemberInfo = array(
            'fName' => '',
            'lName' => '',
            'emailAddress' => '',
            'timeZone' => '',
            'username' => ''
        );

        try {
            $arrTimeZones = $this->_settings->getWebmailTimeZones();

            $hash = $this->findParam('hash', '');
            if (empty($hash)) {
                return $this->redirect()->toUrl($this->layout()->getVariable('baseUrl') . '/default/');
            }

            $divisionGroupId = 0;
            if (empty($strError)) {
                $divisionGroupId = $this->_encryption->decode($hash);
            }

            $companyId = 0;
            $oCompanyDivisions = $this->_company->getCompanyDivisions();
            if (empty($strError)) {
                $arrDivisionGroupInfo = $oCompanyDivisions->getDivisionsGroupInfo($divisionGroupId);

                if (!isset($arrDivisionGroupInfo['company_id'])) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                } else {
                    $userOffice = $arrDivisionGroupInfo['division_group_company'];
                    $companyId = $arrDivisionGroupInfo['company_id'];

                    if ($arrDivisionGroupInfo['division_group_status'] != 'active') {
                        $strError = $this->_tr->translate('The Office is inactive.');
                    }
                }
            }

            $arrMemberDivisions = array();
            if (empty($strError)) {
                // Check if there are divisions in the group
                $arrMemberDivisions = $oCompanyDivisions->getDivisionsByGroupId($divisionGroupId);
                if (empty($arrMemberDivisions)) {
                    $strError = $this->_tr->translate('There are no offices defined.');
                } else {
                    // Check if users were already registered for this group
                    $arrMembers = $this->_members->getMembersAssignedToDivisions($arrMemberDivisions, '');
                    if (!empty($arrMembers)) {
                        $strError = $this->_tr->translate('An admin user has already been defined for this company.');
                    }
                }
            }

            // If an error was generated at this step - means that form cannot be used nor showed
            if (!empty($strError)) {
                $booShowForm = false;
            }

            // Check user's info and if everything is correct - create a new user
            if (empty($strError) && $this->getRequest()->isPost()) {
                $filter = new StripTags();

                // Get admin role, assign it automatically
                $arrMemberRoles = array();

                $adminRoleId = $this->_roles->getCompanyRoleIdByNameAndType($companyId, Roles::$agentAdminRoleName, 'admin');
                if (!empty($adminRoleId)) {
                    $arrMemberRoles[] = $adminRoleId;
                }

                if (empty($arrMemberRoles)) {
                    $strError = $this->_tr->translate('There are no active roles.');
                }

                $arrMemberInfo = array(
                    'userType'          => $this->_members->getUserTypeByRolesIds($arrMemberRoles),
                    'company_id'        => $companyId,
                    'division_group_id' => $divisionGroupId,

                    'fName'        => trim($filter->filter($this->findParam('userFirstName', ''))),
                    'lName'        => trim($filter->filter($this->findParam('userLastName', ''))),
                    'emailAddress' => trim($filter->filter($this->findParam('userEmail', ''))),
                    'timeZone'     => $filter->filter($this->findParam('userTimeZone', '')),
                    'username'     => trim($filter->filter($this->findParam('userUsername', ''))),
                    'password'     => trim($filter->filter($this->findParam('userPassword', ''))),
                );

                if (empty($strError) && empty($arrMemberInfo['username'])) {
                    $strError = $this->_tr->translate('Please enter a Username');
                }

                if (empty($strError) && !Fields::validUserName($arrMemberInfo['username'])) {
                    $strError = 'Incorrect characters in username';
                }

                $memberId = 0;
                if (empty($strError) && $this->_members->isUsernameAlreadyUsed($arrMemberInfo['username'], $memberId)) {
                    $strError = $this->_tr->translate('Duplicate username, please choose another');
                }

                if (empty($strError) && empty($memberId) && empty($arrMemberInfo['password'])) {
                    $strError = $this->_tr->translate('Please enter a Password');
                }

                $arrErrors = array();
                if (empty($strError) && !$this->_authHelper->isPasswordValid($arrMemberInfo['password'], $arrErrors, $arrMemberInfo['username'])) {
                    $strError = implode('<br>', $arrErrors);
                }

                if (empty($strError)) {
                    if (empty($arrMemberInfo['emailAddress'])) {
                        $strError = $this->_tr->translate('Please enter a Email Address');
                    } else {
                        $validator = new EmailAddress();
                        if (!$validator->isValid($arrMemberInfo['emailAddress'])) {
                            // email is invalid; print the reasons
                            foreach ($validator->getMessages() as $message) {
                                $strError .= $message . '<br>';
                            }
                        }
                    }
                }

                if (empty($strError) && empty($arrMemberInfo['fName'])) {
                    $strError = $this->_tr->translate('Please enter a First Name');
                }

                if (empty($strError) && empty($arrMemberInfo['lName'])) {
                    $strError = $this->_tr->translate('Please enter a Last Name');
                }

                if (empty($strError) && (!is_numeric($arrMemberInfo['timeZone']) || !in_array($arrMemberInfo['timeZone'], array_keys($arrTimeZones)))) {
                    $strError = $this->_tr->translate('Please select a Time Zone');
                }

                if (empty($strError)) {
                    $arrUserInfo = array(
                        'timeZone' => $arrMemberInfo['timeZone'],
                    );
                    unset($arrMemberInfo['timeZone']);

                    $userCreationResult = $this->_users->createUser($arrMemberInfo, $arrUserInfo['timeZone'], $arrUserInfo);

                    $arrMemberInfo['timeZone'] = $arrUserInfo['timeZone'];

                    if (!$userCreationResult['error']) {
                        $memberId = $userCreationResult['member_id'];

                        // Create divisions
                        $this->_clients->updateApplicantOffices($memberId, $arrMemberDivisions);

                        // Create/assign roles
                        $this->_members->updateMemberRoles($memberId, $arrMemberRoles, false);

                        // Log this action
                        $arrLog = array(
                            'log_section' => 'user',
                            'log_action' => 'add',
                            'log_description' => '{1} profile was registered',
                            'log_company_id' => $companyId,
                            'log_created_by' => $memberId,
                            'log_action_applied_to' => $memberId,
                        );
                        $this->_accessLogs->saveLog($arrLog);
                    }

                    $booCreated = true;
                    $booShowForm = false;
                }
            }
        } catch (Exception $e) {
            $booShowForm = false;
            $strError    = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $view->setVariables(
            [
                'booCreated'    => $booCreated,
                'booShowForm'   => $booShowForm,
                'strError'      => $strError,
                'hash'          => $hash,
                'passwordRegex' => $this->_settings->getPasswordRegex(true),
                'userOffice'    => $userOffice,
                'arrTimeZones'  => $arrTimeZones,
                'arrMemberInfo' => $arrMemberInfo
            ]
        );

        return $view;
    }
}
