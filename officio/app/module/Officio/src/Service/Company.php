<?php

namespace Officio\Service;

use Clients\Service\Analytics;
use Clients\Service\Clients;
use Clients\Service\Members;
use Clients\Service\MembersVevo;
use Exception;
use Files\Model\FileInfo;
use Files\Service\Files;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\EventManager\EventInterface;
use Laminas\Filter\StripTags;
use Laminas\Mail\AddressList;
use Officio\Common\Json;
use Laminas\ServiceManager\ServiceManager;
use Laminas\View\Helper\Layout;
use Laminas\View\HelperPluginManager;
use Uniques\Php\StdLib\DateTimeTools;
use Uniques\Php\StdLib\FileTools;
use Officio\Common\Service\Country;
use Officio\Common\Service\Encryption;
use Officio\Common\Service\Settings;
use Officio\Comms\Service\Mailer;
use Officio\Email\Models\MailAccount;
use Officio\Service\AutomaticReminders\Actions;
use Officio\Service\Company\CompanyCMI;
use Officio\Service\Company\CompanyDivisions;
use Officio\Service\Company\CompanyExport;
use Officio\Service\Company\CompanyInvoice;
use Officio\Service\Company\CompanyMarketplace;
use Officio\Service\Company\CompanySubscriptions;
use Officio\Service\Company\CompanyTADivisions;
use Officio\Service\Company\CompanyTrial;
use Officio\Service\Company\Packages;
use Officio\Common\ServiceContainerHolder;
use Officio\Common\SubServiceOwner;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use TCPDF;
use Officio\Templates\SystemTemplates;
use Laminas\Validator\EmailAddress;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class Company extends SubServiceOwner
{

    use ServiceContainerHolder;

    /** @var Packages */
    protected $_packages;

    /** @var CompanyDivisions */
    protected $_companyDivisions;

    /** @var CompanyExport */
    protected $_companyExport;

    /** @var CompanyTrial */
    protected $_companyTrial;

    /** @var CompanyInvoice */
    protected $_companyInvoice;

    /** @var GstHst */
    protected $_gstHst;

    /** @var PricingCategories */
    protected $_pricingCategories;

    /** @var CompanyCMI */
    protected $_сompanyCMI;

    /** @var Files */
    protected $_files;

    /** @var CompanySubscriptions */
    protected $_companySubscriptions;

    /** @var CompanyTADivisions */
    protected $_companyTADivisions;

    /** @var CompanyMarketplace */
    protected $_companyMarketplace;

    /** @var Roles */
    protected $_roles;

    /** @var Country */
    protected $_country;

    /** @var SystemTriggers */
    protected $_triggers;

    /** @var HelperPluginManager */
    protected $_viewHelperManger;

    /** @var Encryption */
    protected $_encryption;

    /** @var SystemTemplates */
    protected $_systemTemplates;

    /** @var Mailer */
    protected $_mailer;

    public function initAdditionalServices(array $services)
    {
        $this->_pricingCategories = $services[PricingCategories::class];
        $this->_gstHst            = $services[GstHst::class];
        $this->_files             = $services[Files::class];
        $this->_roles             = $services[Roles::class];
        $this->_country           = $services[Country::class];
        $this->_triggers          = $services[SystemTriggers::class];
        $this->_viewHelperManger  = $services[HelperPluginManager::class];
        $this->_encryption        = $services[Encryption::class];
        $this->_systemTemplates   = $services[SystemTemplates::class];
        $this->_mailer            = $services[Mailer::class];
    }

    public function init()
    {
        $this->_systemTemplates->getEventManager()->attach(SystemTemplates::EVENT_GET_AVAILABLE_FIELDS, [$this, 'getSystemTemplateFields']);
    }

    /**
     * @return Packages
     */
    public function getPackages()
    {
        if (is_null($this->_packages)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_packages = $this->_serviceContainer->build(Packages::class, ['parent' => $this]);
            } else {
                $this->_packages = $this->_serviceContainer->get(Packages::class);
                $this->_packages->setParent($this);
            }
        }

        return $this->_packages;
    }

    /**
     * @return CompanyTADivisions
     */
    public function getCompanyTADivisions()
    {
        if (is_null($this->_companyTADivisions)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_companyTADivisions = $this->_serviceContainer->build(CompanyTADivisions::class, ['parent' => $this]);
            } else {
                $this->_companyTADivisions = $this->_serviceContainer->get(CompanyTADivisions::class);
                $this->_companyTADivisions->setParent($this);
            }
        }

        return $this->_companyTADivisions;
    }

    /**
     * @return CompanyExport
     */
    public function getCompanyExport()
    {
        if (is_null($this->_companyExport)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_companyExport = $this->_serviceContainer->build(CompanyExport::class, ['parent' => $this]);
            } else {
                $this->_companyExport = $this->_serviceContainer->get(CompanyExport::class);
                $this->_companyExport->setParent($this);
            }
        }

        return $this->_companyExport;
    }

    /**
     * @return CompanyTrial
     */
    public function getCompanyTrial()
    {
        if (is_null($this->_companyTrial)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_companyTrial = $this->_serviceContainer->build(CompanyTrial::class, ['parent' => $this]);
            } else {
                $this->_companyTrial = $this->_serviceContainer->get(CompanyTrial::class);
                $this->_companyTrial->setParent($this);
            }
        }

        return $this->_companyTrial;
    }

    /**
     * @return CompanyCMI
     */
    public function getCompanyCMI()
    {
        if (is_null($this->_сompanyCMI)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_сompanyCMI = $this->_serviceContainer->build(CompanyCMI::class, ['parent' => $this]);
            } else {
                $this->_сompanyCMI = $this->_serviceContainer->get(CompanyCMI::class);
                $this->_сompanyCMI->setParent($this);
            }
        }

        return $this->_сompanyCMI;
    }

    /**
     * @return CompanyInvoice
     */
    public function getCompanyInvoice()
    {
        if (is_null($this->_companyInvoice)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_companyInvoice = $this->_serviceContainer->build(CompanyInvoice::class, ['parent' => $this]);
            } else {
                $this->_companyInvoice = $this->_serviceContainer->get(CompanyInvoice::class);
                $this->_companyInvoice->setParent($this);
            }
        }

        return $this->_companyInvoice;
    }

    /**
     * @return CompanyDivisions
     */
    public function getCompanyDivisions()
    {
        if (is_null($this->_companyDivisions)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_companyDivisions = $this->_serviceContainer->build(CompanyDivisions::class, ['parent' => $this]);
            } else {
                $this->_companyDivisions = $this->_serviceContainer->get(CompanyDivisions::class);
                $this->_companyDivisions->setParent($this);
            }
        }

        return $this->_companyDivisions;
    }

    /**
     * @return CompanySubscriptions
     */
    public function getCompanySubscriptions()
    {
        if (is_null($this->_companySubscriptions)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_companySubscriptions = $this->_serviceContainer->build(CompanySubscriptions::class, ['parent' => $this]);
            } else {
                $this->_companySubscriptions = $this->_serviceContainer->get(CompanySubscriptions::class);
                $this->_companySubscriptions->setParent($this);
            }
        }

        return $this->_companySubscriptions;
    }

    /**
     * @return CompanyMarketplace
     */
    public function getCompanyMarketplace()
    {
        if (is_null($this->_companyMarketplace)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_companyMarketplace = $this->_serviceContainer->build(CompanyMarketplace::class, ['parent' => $this]);
            } else {
                $this->_companyMarketplace = $this->_serviceContainer->get(CompanyMarketplace::class);
                $this->_companyMarketplace->setParent($this);
            }
        }

        return $this->_companyMarketplace;
    }

    /**
     * Load the list of offices/divisions for the list of companies
     *
     * @param array $arrCompaniesIds
     * @return array
     */
    public function getCompaniesDivisionsIds($arrCompaniesIds)
    {
        $arrDivisionsGrouped = array();

        if (!empty($arrCompaniesIds)) {
            $select = (new Select())
                ->from(array('d' => 'divisions'))
                ->columns(array('company_id', 'division_id'))
                ->where(['d.company_id' => $arrCompaniesIds]);

            $arrAllCompaniesDivisions = $this->_db2->fetchAll($select);

            foreach ($arrAllCompaniesDivisions as $arrCompanyDivisionsInfo) {
                $arrDivisionsGrouped[$arrCompanyDivisionsInfo['company_id']][] = $arrCompanyDivisionsInfo['division_id'];
            }
        }

        return $arrDivisionsGrouped;
    }

    /**
     * Load default company id
     *
     * @return int
     */
    public function getDefaultCompanyId()
    {
        return 0;
    }


    /**
     * Check if company's data directory is located on local server
     *
     * @param int $companyId
     * @return bool true if company's data directory is located on local server
     */
    public function isCompanyStorageLocationLocal($companyId)
    {
        $booLocal = false;

        if ($companyId == $this->getDefaultCompanyId()) {
            $booLocal = true;
        } elseif (is_numeric($companyId)) {
            $select = (new Select())
                ->from(['c' => 'company'])
                ->columns(['company_id'])
                ->where([
                    'company_id'       => (int)$companyId,
                    'storage_location' => 'local'
                ]);

            $booLocal = $companyId == $this->_db2->fetchOne($select);
        }

        return $booLocal;
    }


    /**
     * Check if "save client's changes" functionality is enabled to specific company
     *
     * @param int $companyId
     * @return bool true if "save client's changes" functionality is enabled
     */
    public function isClientLogEnabledToCompany($companyId)
    {
        $booEnabled = false;

        if (is_numeric($companyId) && $companyId != $this->getDefaultCompanyId()) {
            $select = (new Select())
                ->from(['c' => 'company_details'])
                ->columns(['company_id'])
                ->where([
                    'company_id'                 => (int)$companyId,
                    'log_client_changes_enabled' => 'Y'
                ]);

            $booEnabled = $companyId == $this->_db2->fetchOne($select);
        }

        return $booEnabled;
    }

    /**
     * Check if "Employers Module" is enabled to specific company
     *
     * @param $companyId
     * @return bool true if enabled
     */
    public function isEmployersModuleEnabledToCompany($companyId)
    {
        $booHasAccessToEmployers = false;
        if (empty($companyId)) {
            $booHasAccessToEmployers = true;
        } else {
            $arrCompanyInfo = $this->getCompanyDetailsInfo($companyId);
            if (isset($arrCompanyInfo['employers_module_enabled'])) {
                $booHasAccessToEmployers = $arrCompanyInfo['employers_module_enabled'] == 'Y';
            }
        }

        return $booHasAccessToEmployers;
    }

    /**
     * Check if "Multiple Advanced Search Tabs" feature is allowed to specific company
     *
     * @param $companyId
     * @return bool true if enabled
     */
    public function isMultipleAdvancedSearchTabsAllowedToCompany($companyId)
    {
        $booAllowed = false;
        if (empty($companyId)) {
            $booAllowed = true;
        } else {
            $arrCompanyInfo = $this->getCompanyDetailsInfo($companyId);
            if (isset($arrCompanyInfo['allow_multiple_advanced_search_tabs'])) {
                $booAllowed = $arrCompanyInfo['allow_multiple_advanced_search_tabs'] == 'Y';
            }
        }

        return $booAllowed;
    }

    /**
     * Check if "Change Immigration Program" feature is allowed to specific company
     *
     * @param $companyId
     * @return bool true if enabled
     */
    public function isChangeCaseTypeAllowedToCompany($companyId)
    {
        $booAllowed = false;
        if (empty($companyId)) {
            $booAllowed = true;
        } else {
            $arrCompanyInfo = $this->getCompanyDetailsInfo($companyId);
            if (isset($arrCompanyInfo['allow_change_case_type'])) {
                $booAllowed = $arrCompanyInfo['allow_change_case_type'] == 'Y';
            }
        }

        return $booAllowed;
    }

    /**
     * Check if "Show Decision rationale tab" feature is allowed to specific company
     *
     * @param $companyId
     * @return bool true if enabled
     */
    public function isDecisionRationaleTabAllowedToCompany($companyId)
    {
        $booAllowed = false;

        if (!empty($companyId)) {
            $arrCompanyInfo = $this->getCompanyDetailsInfo($companyId);
            if (isset($arrCompanyInfo['allow_decision_rationale_tab'])) {
                $booAllowed = $arrCompanyInfo['allow_decision_rationale_tab'] == 'Y';
            }
        }

        return $booAllowed;
    }

    /**
     * Check if "Case Management" is enabled to specific company
     *
     * @param $companyId
     * @return bool true if enabled
     */
    public function isCaseManagementEnabledToCompany($companyId)
    {
        $booHasAccessToCaseManagement = false;
        if (empty($companyId)) {
            $booHasAccessToCaseManagement = (bool)$this->_config['site_version']['case_management_enable'];
        } else {
            $arrCompanyInfo = $this->getCompanyDetailsInfo($companyId);
            if (isset($arrCompanyInfo['enable_case_management'])) {
                $booHasAccessToCaseManagement = $arrCompanyInfo['enable_case_management'] == 'Y';
            }
        }

        return $booHasAccessToCaseManagement;
    }

    /**
     * Check if "Loose Task Rules" is enabled to specific company
     *
     * @param $companyId
     * @return bool true if enabled
     */
    public function isLooseTaskRulesEnabledToCompany($companyId)
    {
        $booHasAccessToLooseTaskRules = false;
        if (!empty($companyId)) {
            $arrCompanyInfo = $this->getCompanyDetailsInfo($companyId);
            if (isset($arrCompanyInfo['loose_task_rules'])) {
                $booHasAccessToLooseTaskRules = $arrCompanyInfo['loose_task_rules'] == 'Y';
            }
        }

        return $booHasAccessToLooseTaskRules;
    }

    /**
     * Check if "Hide inactive users" is enabled to specific company
     *
     * @param $companyId
     * @return bool true if enabled
     */
    public function isHideInactiveUsersEnabledToCompany($companyId)
    {
        $booHasAccessToHideInactiveUsers = false;
        if (!empty($companyId)) {
            $arrCompanyInfo = $this->getCompanyDetailsInfo($companyId);
            if (isset($arrCompanyInfo['hide_inactive_users'])) {
                $booHasAccessToHideInactiveUsers = $arrCompanyInfo['hide_inactive_users'] == 'Y';
            }
        }

        return $booHasAccessToHideInactiveUsers;
    }

    /**
     * Get CC type by its number
     *
     * @param string $strCardNumber
     * @return string
     */
    public function getCardTypeByNumber($strCardNumber)
    {
        /**
         * Mastercard: Must have a prefix of 51 to 55, and must be 16 digits in length.
         * Visa: Must have a prefix of 4, and must be either 13 or 16 digits in length.
         * American Express: Must have a prefix of 34 or 37, and must be 15 digits in length.
         * Diners Club: Must have a prefix of 300 to 305, 36, or 38, and must be 14 digits in length.
         * Discover: Must have a prefix of 6011, and must be 16 digits in length.
         */
        $arrPatterns = array(
            "/^(34|37)(\d{13})$/"          => 'American Express',
            "/^(30|36|38)(\d{12})$/"       => 'Dinners Club',
            "/^6011(\d{12})$/"             => 'Discover Card',
            "/^(51|52|53|54|55)(\d{14})$/" => 'Mastercard',
            "/^4(\d{12,15})$/"             => 'Visa',
        );

        $strCardType = '';
        foreach ($arrPatterns as $pattern => $ccType) {
            if (preg_match($pattern, $strCardNumber)) {
                $strCardType = $ccType;
                break;
            }
        }

        return $strCardType;
    }

    /**
     * Update payment info (payment id, CC type) for specific company
     *
     * @param $ccNum
     * @param $ptId
     * @param $companyId
     * @return string
     */
    public function updatePTInfo($ccNum, $ptId, $companyId)
    {
        $ccType = $this->getCardTypeByNumber($ccNum);
        $ccType = in_array($ccType, array('Visa', 'Mastercard')) ? $ccType : '';

        $this->updateCompanyDetails(
            $companyId,
            array(
                'paymentech_profile_id'      => $ptId,
                'paymentech_mode_of_payment' => empty($ccType) ? null : $ccType
            )
        );

        return $ccType;
    }

    /**
     * Check if company account is expired
     *
     * @param int $companyId
     * @return array (bool expired, exp date)
     */
    public function isCompanyAccountExpired($companyId)
    {
        $firstFailedInvoiceDate = $this->getCompanyInvoice()->getCompanyFirstFailedInvoiceDate($companyId);

        $expDate = empty($firstFailedInvoiceDate) ? time() : strtotime($firstFailedInvoiceDate);

        $cuttingOfServiceDays = (int)$this->_settings->getSystemVariables()->getVariable('cutting_of_service_days', 30);
        $maxDays              = $cuttingOfServiceDays * 24 * 60 * 60;
        $booExpired           = (($expDate + $maxDays) < time());

        return array($booExpired, $expDate);
    }


    /**
     * Update company's "show expiration date" setting
     *
     * @param int $companyId
     * @param bool $booClear
     */
    public function updateCompanyShowExpirationDate($companyId, $booClear = false)
    {
        if ($booClear) {
            $newDate = null;
        } else {
            $xDays   = $this->_settings->getSystemVariables()->getVariable('last_charge_failed_show_days', 5);
            $newDate = date('c', strtotime(sprintf('+ %d days', $xDays)));
        }

        $this->updateCompanyDetails(
            $companyId,
            array('show_expiration_dialog_after' => $newDate)
        );
    }

    /**
     * Update company details
     *
     * @param int $companyId
     * @param array $arrCompanyDetails
     */
    public function updateCompanyDetails($companyId, $arrCompanyDetails)
    {
        //Check if record with company id exists in COMPANY_DETAILS table
        $select = (new Select())
            ->from('company_details')
            ->where(['company_id' => (int)$companyId]);

        $arrCompanyInfo = $this->_db2->fetchRow($select);
        if (empty($arrCompanyInfo)) {
            $arrCompanyDetails['company_id'] = $companyId;
            if (!isset($arrCompanyDetails['invoice_number_settings'])) {
                $arrCompanyDetails['invoice_number_settings'] = Json::encode($this->getCompanyInvoiceNumberSettings($companyId));
            }

            if (!isset($arrCompanyDetails['client_profile_id_settings'])) {
                $arrCompanyDetails['client_profile_id_settings'] = Json::encode($this->getCompanyClientProfileIdSettings($companyId));
            }
            $this->_db2->insert('company_details', $arrCompanyDetails);
        } else {
            $this->_db2->update('company_details', $arrCompanyDetails, ['company_id' => $companyId]);
        }
    }

    /**
     * Load company pricing information
     *
     * @param int $companyId
     * @param bool $booExpired true to load expired only
     * @param bool|int $pricingCategoryId
     * @param bool $booShowAll
     * @return array
     */
    public function getCompanyPrices($companyId, $booExpired, $pricingCategoryId = false, $booShowAll = false)
    {
        $arrResult = array();
        $strPrefix = $booExpired ? 'trial_after_exp_' : 'trial_before_exp_';

        if (!$pricingCategoryId) {
            $pricingCategoryId = $this->_pricingCategories->getPricingCategoryIdByName('General');
        }

        $arrSubscriptions = $this->getPackages()->getSubscriptionsList(true, $booShowAll);
        foreach ($arrSubscriptions as $subscriptionId) {
            $name = ucfirst($subscriptionId);

            $priceCategoryDetail = $this->_pricingCategories->getPricingCategoryDetails($pricingCategoryId, $subscriptionId);

            $arrResult['package' . $name . 'UserLicenseMonthly'] = sprintf('%01.2f', $priceCategoryDetail['price_license_user_monthly']);
            $arrResult['package' . $name . 'UserLicenseAnnual']  = sprintf('%01.2f', $priceCategoryDetail['price_license_user_annual']);
            $arrResult['package' . $name . 'FreeStorage']        = sprintf('%d', $priceCategoryDetail['free_storage']);
            $arrResult['package' . $name . 'FreeClients']        = sprintf('%d', $priceCategoryDetail['free_clients']);
            $arrResult['package' . $name . 'FreeUsers']          = sprintf('%d', $priceCategoryDetail['user_included']);
            $arrResult['package' . $name . 'AddUsersOverLimit']  = sprintf('%d', $priceCategoryDetail['users_add_over_limit']);
            $arrResult['package' . $name . 'FeeMonthly']         = sprintf('%01.2f', $priceCategoryDetail['price_package_monthly']);
            $arrResult['package' . $name . 'FeeAnnual']          = sprintf('%01.2f', $priceCategoryDetail['price_package_yearly']);
            $arrResult['package' . $name . 'FeeBiAnnual']        = sprintf('%01.2f', $priceCategoryDetail['price_package_2_years']);
            $arrResult['feeStorageMonthly']                      = sprintf('%01.2f', $priceCategoryDetail['price_storage_1_gb_monthly']);
            $arrResult['feeStorageAnnual']                       = sprintf('%01.2f', $priceCategoryDetail['price_storage_1_gb_annual']);
        }

        $arrResult['activeUsers']        = empty($companyId) ? 0 : $this->calculateActiveUsers($companyId);
        $arrResult['feeTraining']        = sprintf('%01.2f', $this->_settings->getSystemVariables()->getVariable('price_training'));
        $arrResult['freeUsers']          = $this->_settings->getSystemVariables()->getVariable($strPrefix . 'free_users', 1);
        $arrResult['feeAnnual']          = sprintf('%01.2f', $this->_settings->getSystemVariables()->getVariable($strPrefix . 'fee_annual'));
        $arrResult['feeAnnualDiscount']  = sprintf('%01.2f', $this->_settings->getSystemVariables()->getVariable($strPrefix . 'fee_annual_discount'));
        $arrResult['feeMonthly']         = sprintf('%01.2f', $this->_settings->getSystemVariables()->getVariable($strPrefix . 'fee_monthly'));
        $arrResult['feeMonthlyDiscount'] = sprintf('%01.2f', $this->_settings->getSystemVariables()->getVariable($strPrefix . 'fee_monthly_discount'));
        $arrResult['licenseAnnual']      = sprintf('%01.2f', $this->_settings->getSystemVariables()->getVariable($strPrefix . 'license_annual'));
        $arrResult['licenseMonthly']     = sprintf('%01.2f', $this->_settings->getSystemVariables()->getVariable($strPrefix . 'license_monthly'));
        $arrResult['discountLabel']      = $this->_settings->getSystemVariables()->getVariable($strPrefix . 'discount_label');

        return $arrResult;
    }

    /**
     * Load detailed company info
     *
     * @param int $companyId
     * @param array $arrFields - which fields must be loaded from company table
     * @param bool $booLoadOtherInfo - true to load additional info (time zone, gst)
     * @return array
     */
    public function getCompanyAndDetailsInfo($companyId, $arrFields = array(), $booLoadOtherInfo = true)
    {
        try {
            $select = (new Select());

            // We need write in such way to select all fields
            // or only specific fields from company table
            if (count($arrFields)) {
                $select->from(array('c' => 'company'))
                    ->columns($arrFields);
            } else {
                $select->from(array('c' => 'company'));
            }

            $select->join(array('d' => 'company_details'), 'd.company_id = c.company_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER)
                ->where(['c.company_id' => (int)$companyId]);

            $arrInfo = $this->_db2->fetchRow($select);

            // Avoid issue when there is created record in company_details table
            if (!empty($arrInfo) && empty($arrInfo['company_id'])) {
                $arrInfo['company_id'] = $companyId;
            }

            if (!empty($arrInfo) && $booLoadOtherInfo) {
                $arrInfo['companyTimeZone'] = empty($arrInfo['companyTimeZone'])
                    ? date_default_timezone_get()
                    : $arrInfo['companyTimeZone'];

                // Calculate/load gst related data
                $arrGstInfo                  = $this->_gstHst->getGstByCountryAndProvince($arrInfo['country'], $arrInfo['state']);
                $arrInfo['gst_default']      = $arrGstInfo['gst_rate'];
                $arrInfo['gst_default_type'] = $arrGstInfo['gst_type'];
                $arrInfo['gst_tax_label']    = $arrGstInfo['gst_tax_label'];
                $arrInfo['gst_used']         = $arrInfo['gst_type'] == 'auto' ? $arrInfo['gst_default'] : $arrInfo['gst'];
            }
        } catch (Exception $e) {
            $arrInfo = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrInfo;
    }

    /**
     * Load company information (company_details table only)
     *
     * @param int $companyId
     * @return array
     */
    public function getCompanyDetailsInfo($companyId)
    {
        $select = (new Select())
            ->from('company_details')
            ->where(['company_id' => (int)$companyId]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Check if annotation support is enabled for the company
     *
     * @param int $companyId
     * @return bool true if support is enabled
     */
    public function areAnnotationsEnabledForCompany($companyId)
    {
        $arrCompanyDetailsInfo = $this->getCompanyDetailsInfo($companyId);

        return (is_array($arrCompanyDetailsInfo) &&
            array_key_exists('use_annotations', $arrCompanyDetailsInfo) &&
            $arrCompanyDetailsInfo['use_annotations'] == 'Y');
    }

    /**
     * Check if "Express Entry" section can be shown for the company
     *
     * @param int $companyId
     * @return bool true if "Express Entry" section can be shown
     */
    public function isExpressEntryEnabledForCompany($companyId = null)
    {
        if (is_null($companyId)) {
            $companyId = $this->_auth->getCurrentUserCompanyId();
        }

        $arrCompanyDetailsInfo = $this->getCompanyDetailsInfo($companyId);

        return isset($arrCompanyDetailsInfo['subscription']) && !in_array($arrCompanyDetailsInfo['subscription'], array('starter', 'lite'));
    }

    /**
     * Check if "Remember default fields" is checked for the company
     *
     * @param int $companyId
     * @return bool true if "Remember default fields" is checked
     */
    public function isRememberDefaultFieldsSettingEnabledForCompany($companyId)
    {
        $arrCompanyDetailsInfo = $this->getCompanyDetailsInfo($companyId);

        return (is_array($arrCompanyDetailsInfo) &&
            array_key_exists('remember_default_fields', $arrCompanyDetailsInfo) &&
            $arrCompanyDetailsInfo['remember_default_fields'] == 'Y');
    }

    /**
     * Load company information (company table only + timezone + country name)
     *
     * @param int $companyId
     * @return array
     */
    public function getCompanyInfo($companyId)
    {
        $select = (new Select())
            ->from('company')
            ->where(['company_id' => (int)$companyId]);

        $arrCompanyInfo = $this->_db2->fetchRow($select);

        if (!empty($arrCompanyInfo)) {
            $arrCompanyInfo['companyTimeZone'] = empty($arrCompanyInfo['companyTimeZone']) ? date_default_timezone_get() : $arrCompanyInfo['companyTimeZone'];
        }

        $arrCompanyInfo['countryName'] = !empty($arrCompanyInfo['country']) ? $this->_country->getCountryName($arrCompanyInfo['country']) : null;

        return $arrCompanyInfo;
    }

    /**
     * Load company path (where company files/folders are stored)
     *
     * @param int $companyId
     * @param bool|true $booLocal - true, if company storage location is local (OR specific folder is saved locally only)
     * @return string
     */
    public function getCompanyPath($companyId, $booLocal = true)
    {
        $root = $booLocal ? $this->_config['directory']['companyfiles'] : '';

        return $root . '/' . $companyId;
    }

    public function getCompanyLogoPath($companyId, $booLocal)
    {
        return $this->getCompanyLogoFolderPath($companyId, $booLocal) . '/' . 'logo';
    }

    public function getCompanyLogoFolderPath($companyId, $booLocal = null)
    {
        if (is_null($booLocal)) {
            $booLocal = $this->_auth->isCurrentUserCompanyStorageLocal();
        }

        return $this->getCompanyPath($companyId, $booLocal) . '/' . $this->_config['directory']['company_logo'];
    }

    /**
     * Load link to company logo image
     * If remote storage is used - direct link will be generated
     * If no url is generated (e.g. there is no logo uploaded) - empty gif will be passed (supported in > IE7)
     *
     * @param $arrCompanyInfo
     * @return string
     */
    public function getCompanyLogoLink($arrCompanyInfo)
    {
        $companyId = $arrCompanyInfo['company_id'];

        if (!empty($arrCompanyInfo['companyLogo'])) {
            /** @var Layout $layout */
            $layout = $this->_viewHelperManger->get('layout');
            $url    = $layout()->getVariable('baseUrl') . '/auth/get-client-company-logo?id=' . $this->generateHashByCompanyId($companyId);
        }

        // If url wasn't generated - use 'empty' transparent gif 1x1px
        return empty($url) ? 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=' : $url;
    }

    /**
     * Load company logo image data
     *
     * @param $arrCompanyInfo
     * @return string
     */
    public function getCompanyLogoData($arrCompanyInfo)
    {
        $companyId = $arrCompanyInfo['company_id'];

        $imgSrc = '';

        if (!empty($arrCompanyInfo['companyLogo'])) {
            $booLocal        = $this->isCompanyStorageLocationLocal($companyId);
            $companyLogoPath = $this->_files->getCompanyLogoPath($companyId, $booLocal);

            $content = '';
            if ($booLocal) {
                if (is_file($companyLogoPath) && is_readable($companyLogoPath)) {
                    $companyLogoPath = str_replace('\\', '/', $companyLogoPath);
                    $content         = file_get_contents($companyLogoPath);
                }
            } else {
                $content = $this->_files->getCloud()->getFileContent($companyLogoPath);
            }

            if (!empty($content)) {
                // Read image path, convert to base64 encoding
                $imgData = base64_encode($content);
                $imgSrc  = 'data: ' . FileTools::getMimeByFileName($companyLogoPath) . ';base64,' . $imgData;
            }
        }

        return $imgSrc;
    }


    /**
     * Load admin id for specific company
     *
     * @param int $companyId
     * @param int $divisionGroupId
     * @return int admin id, empty on error or when not found
     */
    public function getCompanyAdminId($companyId, $divisionGroupId = null)
    {
        $adminId = 0;

        /** @var Members $members */
        $members = $this->_serviceContainer->get(Members::class);

        if (is_numeric($companyId)) {
            $select = (new Select())
                ->from(array('c' => 'company'))
                ->columns(['company_id'])
                ->join(array('m' => 'members'), 'c.admin_id = m.member_id', 'member_id', Select::JOIN_LEFT_OUTER)
                ->where(['c.company_id' => (int)$companyId]);

            $arrCompanyInfo = $this->_db2->fetchRow($select);

            if (isset($arrCompanyInfo['member_id']) && !empty($arrCompanyInfo['member_id'])) {
                $adminId = $arrCompanyInfo['member_id'];
            } else {
                // Search admin by company roles
                $divisionGroupId = !is_numeric($divisionGroupId) ? $this->getCompanyDivisions()->getCompanyMainDivisionGroupId($companyId) : $divisionGroupId;
                $arrAdminIds     = $members->getMemberByRoleIds(
                    $this->_roles->getCompanyRoles($companyId, $divisionGroupId, true, array('admin'))
                );

                // Get the first one - this is the MAIN admin of this company :)
                if (is_array($arrAdminIds) && count($arrAdminIds)) {
                    $adminId = $arrAdminIds[0];
                }
            }
        }

        return $adminId;
    }

    /**
     * Load company roles list
     *
     * @param int $companyId
     * @param bool $booIdOnly
     * @return array
     */
    public function getCompanyRoles($companyId, $booIdOnly = false)
    {
        $select = (new Select())
            ->from('acl_roles')
            ->columns([$booIdOnly ? 'role_id' : Select::SQL_STAR])
            ->where(['company_id' => (int)$companyId]);

        return $booIdOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
    }

    /**
     * Load employer/individual role id for specific company
     *
     * @param int $companyId
     * @param bool $booIndividualRole
     * @return int
     */
    public function getCompanyClientRole($companyId, $booIndividualRole = true)
    {
        $select = (new Select())
            ->from('acl_roles')
            ->columns(['role_id'])
            ->where([
                'company_id' => (int)$companyId,
                'role_type'  => $booIndividualRole ? 'individual_client' : 'employer_client'
            ]);

        return $this->_db2->fetchOne($select);
    }

    /**
     * Load company id by member id
     *
     * @param int $memberId
     * @return int
     */
    public function getMemberCompanyId($memberId)
    {
        $select = (new Select())
            ->from('members')
            ->columns(['company_id'])
            ->where(['member_id' => (int)$memberId]);

        return $this->_db2->fetchOne($select);
    }


    /**
     * Load clients list for specific company
     *
     * @param int $companyId
     * @param bool $booIdOnly - true to load ids only
     * @return array
     */
    public function getCompanyClients($companyId, $booIdOnly = true)
    {
        $select = (new Select())
            ->from('members')
            ->columns($booIdOnly ? ['member_id'] : ['member_id', 'fName', 'lName'])
            ->where([
                'company_id' => (int)$companyId,
                'userType'   => Members::getMemberType('case')
            ]);

        return $booIdOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
    }

    /**
     * Load list of all companies, except of the default one
     *
     * @param bool $booIdsOnly
     * @return array
     */
    public function getAllCompanies($booIdsOnly = false)
    {
        $arrResult = array();

        $select = (new Select())
            ->from('company')
            ->columns($booIdsOnly ? ['company_id'] : ['company_id', 'companyName'])
            ->where([
                (new Where())->notEqualTo('company_id', $this->getDefaultCompanyId())
            ]);

        if ($booIdsOnly) {
            $arrResult = $this->_db2->fetchCol($select);
        } else {
            $arrCompanies = $this->_db2->fetchAll($select);
            foreach ($arrCompanies as $company) {
                $arrResult[] = array(
                    $company['company_id'],
                    $company['companyName']
                );
            }
        }

        return $arrResult;
    }

    /**
     * Load list of active companies, which their next billing date >= today
     *
     * @param bool $booIdsOnly
     * @return array
     */
    public function getCompaniesWithBillingDateCheck($booIdsOnly)
    {
        $select = (new Select())
            ->from(array('c' => 'company'))
            ->columns($booIdsOnly ? ['company_id'] : ['company_id', 'storage_today', 'storage_location'])
            ->join(array('d' => 'company_details'), 'd.company_id = c.company_id', [], Select::JOIN_LEFT)
            ->where(
                [
                    'c.Status' => 1,
                    (new Where())->greaterThanOrEqualTo('d.next_billing_date', date('Y-m-d')),
                    // 'c.company_id' => [1],
                ]
            )
            ->order('c.company_id ASC');


        return $booIdsOnly ? $this->_db2->fetchCol($select) : $this->_db2->fetchAll($select);
    }

    /**
     * Load clients count for specific company
     *
     * @param int $companyId
     * @return int
     */
    public function getCompanyClientsCount($companyId)
    {
        $clientsCount = 0;
        $memberTypes  = array_merge(Members::getMemberType('individual'), Members::getMemberType('employer'));

        if (!empty($companyId) && !empty($memberTypes)) {
            $select = (new Select())
                ->from('members')
                ->columns(['clients_count' => new Expression('COUNT(*)')])
                ->where([
                    'company_id' => (int)$companyId,
                    'userType'   => $memberTypes
                ]);

            $clientsCount = $this->_db2->fetchOne($select);
        }

        return $clientsCount;
    }

    /**
     * Load cases (with parents) count for a specific company
     *
     * @param int $companyId
     * @return int
     */
    public function getCompanyCasesCount($companyId)
    {
        $casesCount = 0;

        if (!empty($companyId)) {
            $select = (new Select())
                ->from(array('m' => 'members'))
                ->columns(['count' => new Expression('COUNT(*)')])
                ->join(array('mr' => 'members_relations'), 'mr.child_member_id = m.member_id', [])
                ->where([
                    'm.company_id' => (int)$companyId,
                    'm.userType'   => Members::getMemberType('case')
                ]);

            $casesCount = $this->_db2->fetchOne($select);
        }

        return $casesCount;
    }

    /**
     * Load member ids for specific company
     *
     * @param int $companyId
     * @param string $strUsersType
     * @param bool $booActiveOnly
     * @param null $divisionGroupId
     * @return array
     */
    public function getCompanyMembersIds($companyId, $strUsersType = '', $booActiveOnly = false, $divisionGroupId = null)
    {
        $select = (new Select())
            ->from('members')
            ->columns(['member_id'])
            ->where(['company_id' => (int)$companyId]);

        if ($booActiveOnly) {
            $select->where(['status' => 1]);
        }

        if (!is_null($divisionGroupId)) {
            $select->where(['division_group_id' => (int)$divisionGroupId]);
        }

        if (!empty($strUsersType)) {
            $arrTypes = Members::getMemberType($strUsersType);
            if (!empty($arrTypes)) {
                $select->where(['userType' => $arrTypes]);
            }
        }

        $arrMemberIds = $this->_db2->fetchCol($select);

        $uniqueMemberIds = $this->_settings::arrayUnique($arrMemberIds);
        return is_array($uniqueMemberIds) ? $uniqueMemberIds : [];
    }

    /**
     * Load company members with roles info
     *
     * @param int $companyId
     * @param string $strUsersType
     * @return array
     */
    public function getCompanyMembersWithRoles($companyId, $strUsersType = '')
    {
        $select = (new Select())
            ->from(array('m' => 'members'))
            ->join(array('mr' => 'members_roles'), 'mr.member_id = m.member_id', [], Select::JOIN_LEFT_OUTER)
            ->join(array('r' => 'acl_roles'), 'r.role_id = mr.role_id', 'role_name', Select::JOIN_LEFT_OUTER)
            ->where(['m.company_id' => (int)$companyId])
            ->order(['m.lName', 'm.fName']);

        if (!empty($strUsersType)) {
            $arrTypes = Members::getMemberType($strUsersType);
            if (!empty($arrTypes)) {
                $select->where(['m.userType' => $arrTypes]);
            }
        }

        return $this->_db2->fetchAll($select);
    }


    /**
     * Load email addresses list for all users (specified by user type) for specific company
     *
     * @param int $companyId
     * @param string $strUsersType
     * @param array $arrMemberIds
     * @param bool $booExport
     * @return array email addresses
     */
    public function getCompanyMembersEmails($companyId, $strUsersType = '', $arrMemberIds = array(), $booExport = false)
    {
        $select = (new Select())
            ->from(array('m' => 'members'))
            ->columns(array('member_id', 'emailAddress', 'fName', 'lName'))
            ->join(array('a' => 'eml_accounts'), new PredicateExpression('a.member_id = m.member_id AND is_default = "Y"'), 'email')
            ->where([
                'm.company_id' => $companyId,
                'm.status'     => 1
            ]);

        if (!empty($strUsersType)) {
            $arrTypes = Members::getMemberType($strUsersType);
            if (!empty($arrTypes)) {
                $select->where(['m.userType' => $arrTypes]);
            }
        }

        if (count($arrMemberIds)) {
            $select->where(['m.member_id' => $arrMemberIds]);
        }
        $arrFoundMembers = $this->_db2->fetchAll($select);

        $arrAddresses      = array();
        $arrAddedAddresses = array();
        foreach ($arrFoundMembers as $arrMemberInfo) {
            $strEmail = empty($arrMemberInfo['email']) ? $arrMemberInfo['emailAddress'] : $arrMemberInfo['email'];
            if (!in_array($strEmail, $arrAddedAddresses)) {
                $arrAddedAddresses[]                       = $strEmail;
                $arrAddresses[$arrMemberInfo['member_id']] = array(
                    'email' => $strEmail,
                    'name'  => $arrMemberInfo['fName'] . ' ' . $arrMemberInfo['lName']
                );

                if ($booExport) {
                    $arrAddresses[$arrMemberInfo['member_id']]['member_id'] = $arrMemberInfo['member_id'];
                }
            }
        }

        return $arrAddresses;
    }


    /**
     * Check if company has a field 'division' (office) and this field is in assigned group (visible to the user)
     *
     * @param int $companyId
     * @return bool
     */
    public function hasCompanyDivisions($companyId)
    {
        // Check if office field is presented in the case's profile
        $select = (new Select())
            ->from(array('g' => 'client_form_groups'))
            ->columns(['assigned'])
            ->join(array('o' => 'client_form_order'), 'o.group_id = g.group_id', [], Select::JOIN_LEFT_OUTER)
            ->join(array('f' => 'client_form_fields'), 'f.field_id = o.field_id', [], Select::JOIN_LEFT_OUTER)
            ->where(['f.company_field_id' => 'division', 'g.company_id' => (int)$companyId]);

        $arrAssignedGroups = $this->_db2->fetchCol($select);

        $booCaseDivisionFieldEnabled = is_array($arrAssignedGroups) && in_array('A', $arrAssignedGroups);

        // Check if office field is presented in the applicant's (IA/Employer/Contact/InternalContact) profile
        $booApplicantDivisionFieldEnabled = false;
        if (!$booCaseDivisionFieldEnabled) {
            $select = (new Select())
                ->from(array('g' => 'applicant_form_groups'))
                ->columns(['applicant_group_id'])
                ->join(array('o' => 'applicant_form_order'), 'o.applicant_group_id = g.applicant_group_id', [], Select::JOIN_LEFT_OUTER)
                ->join(array('f' => 'applicant_form_fields'), 'f.applicant_field_id = o.applicant_field_id', [], Select::JOIN_LEFT_OUTER)
                ->where(['f.applicant_field_unique_id' => 'office', 'g.company_id' => (int)$companyId]);

            $arrAssignedGroups = $this->_db2->fetchCol($select);

            $booApplicantDivisionFieldEnabled = is_array($arrAssignedGroups) && count($arrAssignedGroups);
        }

        return $booCaseDivisionFieldEnabled || $booApplicantDivisionFieldEnabled;
    }

    /**
     * Load offices/divisions list for specific company
     *
     * @param int $companyId
     * @param int|null $divisionGroupId
     * @param bool $booIdOnly
     * @param bool $booAssoc
     * @return array
     */
    public function getDivisions($companyId, $divisionGroupId, $booIdOnly = false, $booAssoc = false)
    {
        $select = (new Select())
            ->quantifier(Select::QUANTIFIER_DISTINCT)
            ->from(['d' => 'divisions'])
            ->columns($booIdOnly ? ['division_id'] : [Select::SQL_STAR])
            ->where(['d.company_id' => (int)$companyId])
            ->order('d.order');

        if (!empty($divisionGroupId)) {
            $select->where(['d.division_group_id' => (int)$divisionGroupId]);
        }

        if ($booIdOnly) {
            $result = $this->_db2->fetchCol($select);
        } elseif ($booAssoc) {
            $result = $this->_db2->fetchAssoc($select);
        } else {
            $result = $this->_db2->fetchAll($select);
        }

        return $result;
    }

    /**
     * Delete company or several companies at once (with all related info)
     *
     * @param $arrCompanyIds
     * @return bool
     */
    public function deleteCompany($arrCompanyIds)
    {
        if (!is_array($arrCompanyIds) || count($arrCompanyIds) == 0) {
            return false;
        }

        try {
            // Trigger company deletion
            $this->_triggers->triggerCompanyDelete($arrCompanyIds);

            // Collect company members
            $select = (new Select())
                ->from('members')
                ->columns(['member_id'])
                ->where(['company_id' => $arrCompanyIds]);

            $arrMembersIds = $this->_db2->fetchCol($select);

            // Delete all information related to company's members
            if (is_array($arrMembersIds) && count($arrMembersIds) > 0) {
                // Delete all email accounts + mails, etc.
                foreach ($arrMembersIds as $memberId) {
                    $accounts = MailAccount::getAccounts($memberId);
                    if (is_array($accounts) && count($accounts)) {
                        foreach ($accounts as $account) {
                            $account = new MailAccount($account['id']);
                            $account->deleteAccount($this->_files);
                        }
                    }
                }

                $this->_db2->delete('form_default', ['updated_by' => $arrMembersIds]);

                //delete logs
                $this->_db2->delete('u_log', ['author_id' => $arrMembersIds]);

                // Delete assigned forms
                $this->_db2->delete('form_assigned', ['client_member_id' => $arrMembersIds]);

                // delete superadmin time tracker items
                $this->_db2->delete('time_tracker', ['track_company_id' => $arrCompanyIds]);

                /** @var Clients $clients */
                $clients = $this->_serviceContainer->get(Clients::class);
                $clients->deleteClientAllDependents($arrMembersIds);

                $arrDeleteMemberInfoTables = array(
                    'client_form_data',
                    'members_divisions',
                    'members_roles',
                    'members_ta',
                    'templates',
                    'u_invoice',
                    'u_links',
                    'u_notes',
                    'u_payment',
                    'u_payment_schedule',
                    'u_tasks',
                    'members_last_access',
                    'users',
                    'clients',
                    'members'
                );

                foreach ($arrDeleteMemberInfoTables as $table) {
                    $this->_db2->delete($table, ['member_id' => $arrMembersIds]);
                }
            }

            // Collect company T/A
            $select = (new Select())
                ->from('company_ta')
                ->columns(['company_ta_id'])
                ->where(['company_id' => $arrCompanyIds]);

            $arrCompanyTAIds = $this->_db2->fetchCol($select);

            // Delete all information related to company's members
            if (is_array($arrCompanyTAIds) && count($arrCompanyTAIds) > 0) {
                $strTAIds = implode(',', $arrCompanyTAIds);

                // Delete all company related info
                $arrDeleteTAInfoTables = array(
                    'u_trust_account',
                    'u_import_transactions',
                    'u_assigned_deposits',
                    'u_assigned_withdrawals',
                    'u_invoice',
                    'u_trust_account'
                );

                foreach ($arrDeleteTAInfoTables as $table) {
                    $this->_db2->delete($table, ['company_ta_id' => $strTAIds]);
                }
            }

            // Delete field-dependent tables
            $select = (new Select())
                ->from('client_form_fields')
                ->columns(['field_id'])
                ->where(['company_id' => $arrCompanyIds]);

            $fields = $this->_db2->fetchCol($select);
            if (is_array($fields) && !empty($fields)) {
                $this->_db2->delete('client_form_default', ['field_id' => $fields]);
            }

            // Delete roles and related access info
            $select = (new Select())
                ->from('acl_roles')
                ->columns(['role_id'])
                ->where(['company_id' => $arrCompanyIds]);

            $roles = $this->_db2->fetchCol($select);
            if (is_array($roles) && !empty($roles)) {
                $arrDeleteCompanyInfoTables = array(
                    'acl_roles',
                    'client_form_field_access',
                    'client_form_group_access',
                );
                foreach ($arrDeleteCompanyInfoTables as $table) {
                    $this->_db2->delete($table, ['role_id' => $roles]);
                }
            }
            $this->_files->getFolders()->getFolderAccess()->deleteByRoleIds($roles);

            $arrWhere = [
                (new Where())->in(
                    'role_id',
                    (new Select())
                        ->from('acl_roles')
                        ->columns(['role_parent_id'])
                        ->where(
                            [
                                'company_id' => $arrCompanyIds
                            ]
                        )
                )
            ];

            $this->_db2->delete('acl_role_access', $arrWhere);

            // Update CMI pair, so now it can be used again
            $this->_db2->update('company_cmi', ['company_id' => null], ['company_id' => $arrCompanyIds]);

            // Delete all company related info
            $arrDeleteCompanyInfoTables = array(
                'company_prospects_selected_categories',
                'company_questionnaires',
                'form_default',
                'company_trial',
                'company_packages',
                'company_ta',
                'divisions_groups',
                'divisions',
                'u_deposit_types',
                'u_destination_types',
                'u_withdrawal_types',
                'u_folders',
                'u_notes',
                'u_tasks',
                'client_form_fields',
                'searches',
                'company_details',
                'company'
            );

            foreach ($arrDeleteCompanyInfoTables as $table) {
                $this->_db2->delete($table, ['company_id' => $arrCompanyIds]);
            }

            // Delete Real Files - local and remote (S3)
            $path = $this->_config['directory'];
            foreach ($arrCompanyIds as $companyId) {
                $this->_files->deleteFolder($path['companyfiles'] . '/' . $companyId, true);
                $this->_files->deleteFolder('/' . $companyId, false);

                $this->_roles->clearCompanyRolesCache($companyId);
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Load string company status by status id
     *
     * @param int $intStatus
     * @return string
     */
    public function getCompanyStringStatusById($intStatus)
    {
        switch ($intStatus) {
            case 2:
                $strStatus = 'Suspended';
                break;

            case 1:
                $strStatus = 'Active';
                break;

            case 0:
                $strStatus = 'Inactive';
                break;

            default:
                $strStatus = 'Unknown';
                break;
        }

        return $strStatus;
    }

    /**
     * Load company status id by its string id
     *
     * @param string $strStatus
     * @return int
     */
    public function getCompanyIntStatusByString($strStatus)
    {
        switch ($strStatus) {
            case 'suspended':
            case 'suspend':
                $intStatus = 2;
                break;

            case 'active':
            case 'activate':
                $intStatus = 1;
                break;

            case 'inactive':
            case 'deactivate':
                $intStatus = 0;
                break;

            default:
                $intStatus = -1;
                break;
        }

        return $intStatus;
    }

    /**
     * Update company/companies status
     *
     * @param $strOldStatus
     * @param $strNewStatus
     * @param $companyId
     * @param null $mpModuleOldStatus
     * @param null $mpModuleNewStatus
     * @return bool true on success
     */
    public function updateCompanyStatus($strOldStatus, $strNewStatus, $companyId, $mpModuleOldStatus = null, $mpModuleNewStatus = null)
    {
        try {
            $intStatus = $this->getCompanyIntStatusByString($strNewStatus);

            if ($intStatus >= 0 && !empty($companyId)) {
                // Update the related users' status in members table
                $this->updateCompanyUsersStatus($intStatus, array($companyId));

                // Toggle access to MP profiles if company status was changed
                $arrCompanyDetails = $this->getCompanyDetailsInfo($companyId);
                $this->getCompanyMarketplace()->toggleAccessToMarketplaceProfiles(
                    $companyId,
                    $strOldStatus,
                    $strNewStatus,
                    is_null($mpModuleOldStatus) ? $arrCompanyDetails['marketplace_module_enabled'] : $mpModuleOldStatus,
                    is_null($mpModuleNewStatus) ? $arrCompanyDetails['marketplace_module_enabled'] : $mpModuleNewStatus
                );

                // Update companies statuses
                $this->_db2->update('company', ['Status' => $intStatus], ['company_id' => $companyId]);
                $booResult = true;
            } else {
                $booResult = false;
            }
        } catch (Exception $e) {
            $booResult = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booResult;
    }

    /**
     * Update company/companies users status
     *
     * @param string $intStatus
     * @param array $arrCompanyIds
     * @return bool true on success
     */
    public function updateCompanyUsersStatus($intStatus, $arrCompanyIds)
    {
        $booResult = false;

        try {
            if ($intStatus >= 0 && !empty($arrCompanyIds)) {
                $arrCompanyWhere = [];
                $statusForUpdate = null;

                switch ($intStatus) {
                    case 0:
                    case 2:
                        $arrCompanyWhere = ['company_id' => $arrCompanyIds, 'status' => 1];
                        $statusForUpdate = 2;
                        break;

                    case 1:
                        $arrCompanyWhere = ['company_id' => $arrCompanyIds, 'status' => 2];
                        $statusForUpdate = 1;
                        break;

                    default:
                        break;
                }

                if (count($arrCompanyWhere) && !empty($statusForUpdate)) {
                    $this->_db2->update('members', ['status' => $statusForUpdate], $arrCompanyWhere);
                    $booResult = true;
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booResult;
    }


    /**
     * Load detailed info about specific company admin
     *
     * @param int $companyId
     * @return array
     */
    public function loadCompanyAdminInfo($companyId)
    {
        $arrCompanyAdminInfo = array(
            'admin_id'     => 0,
            'fName'        => '',
            'lName'        => '',
            'username'     => '',
            'password'     => '',
            'emailAddress' => ''
        );

        if (!empty($companyId)) {
            $select = (new Select())
                ->from(array('c' => 'company'))
                ->join(array('m' => 'members'), 'c.admin_id = m.member_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER)
                ->where(['c.company_id' => (int)$companyId]);

            $arrCompanyAdminInfo = $this->_db2->fetchRow($select);
        }

        return $arrCompanyAdminInfo;
    }

    /**
     * Company can change/edit time zone only:
     * a) This is a new company
     * b) There are no new saved/entered information for this company,
     *    e.g. clients/members, T/A
     * @param $companyId
     * @return bool
     */
    public function canUpdateTimeZone($companyId)
    {
        if (empty($companyId)) {
            return true;
        }

        $arrMembers = $this->getCompanyMembersIds($companyId);

        /** @var Clients $clients */
        $clients = $this->_serviceContainer->get(Clients::class);
        $arrTA   = $clients->getAccounting()->getCompanyTA($companyId, true);

        // @TODO: Add more checks
        return (count($arrMembers) <= 1 || count($arrTA) == 0);
    }

    /**
     * Create default folders for specific company
     *
     * @param ?int $fromCompanyId
     * @param $toCompanyId
     * @param $companyAdminId
     * @return array
     */
    private function _createDefaultFolders($fromCompanyId, $toCompanyId, $companyAdminId)
    {
        $arrDefaultFolders = $this->_files->getFolders()->getDefaultFolders($fromCompanyId);

        $identity = array();
        if (is_array($arrDefaultFolders)) {
            // Create same folders
            foreach ($arrDefaultFolders as $defaultFolderInfo) {
                $oldId = $defaultFolderInfo['folder_id'];

                $identity[$oldId] = $this->_files->getFolders()->createFolder(
                    $toCompanyId,
                    $companyAdminId,
                    empty($defaultFolderInfo['parent_id']) ? 0 : $identity[$defaultFolderInfo['parent_id']],
                    $defaultFolderInfo['folder_name'],
                    $defaultFolderInfo['type']
                );
            }
        }

        return $identity;
    }

    /**
     * Create default templates for specific company
     *
     * @param ?int $fromCompanyId
     * @param $companyAdminId
     * @param $arrFoldersMapping
     * @return array
     */
    private function _createDefaultTemplates($fromCompanyId, $companyAdminId, $arrFoldersMapping)
    {
        $arrMappingTemplates = array();

        $folderId = $this->_files->getFolders()->getDefaultSharedFolderId($fromCompanyId);

        // Create same files
        $select = (new Select())
            ->from(['t' => 'templates'])
            ->where(['t.folder_id' => (int)$folderId]);

        $arrDefaultTemplates = $this->_db2->fetchAll($select);

        foreach ($arrDefaultTemplates as $defaultTemplateInfo) {
            $templateId = $defaultTemplateInfo['template_id'];

            $defaultTemplateInfo['template_id'] = 0;
            $defaultTemplateInfo['member_id']   = $companyAdminId;
            $defaultTemplateInfo['create_date'] = date('Y-m-d');
            $defaultTemplateInfo['folder_id']   = $arrFoldersMapping[$defaultTemplateInfo['folder_id']];

            $arrMappingTemplates[$templateId] = $this->_db2->insert('templates', $defaultTemplateInfo);
        }

        return $arrMappingTemplates;
    }

    /**
     * Create default 'Special Types' for new company (based on default)
     *
     * @param $fromCompanyId
     * @param $toCompanyId
     */
    private function _createDefaultTypes($fromCompanyId, $toCompanyId)
    {
        $select = (new Select())
            ->from('u_deposit_types')
            ->where(['company_id' => (int)$fromCompanyId]);

        $arrDefaultDepositTypes = $this->_db2->fetchAll($select);

        if (is_array($arrDefaultDepositTypes)) {
            foreach ($arrDefaultDepositTypes as $defaultDepositTypeInfo) {
                unset($defaultDepositTypeInfo['dtl_id']);
                $defaultDepositTypeInfo['company_id'] = $toCompanyId;

                $this->_db2->insert('u_deposit_types', $defaultDepositTypeInfo);
            }
        }

        $select = (new Select())
            ->from('u_destination_types')
            ->where(['company_id' => (int)$fromCompanyId]);

        $arrDefaultDestinationTypes = $this->_db2->fetchAll($select);

        if (is_array($arrDefaultDestinationTypes)) {
            foreach ($arrDefaultDestinationTypes as $defaultDestinationTypeInfo) {
                unset($defaultDestinationTypeInfo['destination_account_id']);
                $defaultDestinationTypeInfo['company_id'] = $toCompanyId;

                $this->_db2->insert('u_destination_types', $defaultDestinationTypeInfo);
            }
        }

        $select = (new Select())
            ->from('u_withdrawal_types')
            ->where(['company_id' => (int)$fromCompanyId]);

        $arrDefaultWithdrawalTypes = $this->_db2->fetchAll($select);
        if (is_array($arrDefaultWithdrawalTypes)) {
            foreach ($arrDefaultWithdrawalTypes as $defaultWithdrawalTypeInfo) {
                unset($defaultWithdrawalTypeInfo['wtl_id']);
                $defaultWithdrawalTypeInfo['company_id'] = $toCompanyId;

                $this->_db2->insert('u_withdrawal_types', $defaultWithdrawalTypeInfo);
            }
        }
    }

    /**
     * Generate text role id for specific company by type
     *
     * @param $companyId
     * @param $roleType
     * @return string
     */
    private function _generateRoleTextId($companyId, $roleType)
    {
        $count  = 0;
        $suffix = '';

        $select = (new Select())
            ->from(array('a' => 'acl_roles'))
            ->columns(['role_parent_id']);

        $arrRoles = $this->_db2->fetchCol($select);

        while (true) {
            if (!empty($count)) {
                $suffix = '_' . $count;
            }

            $newRoleTextId = 'company_' . $companyId . '_' . $roleType . $suffix;

            if (!in_array($newRoleTextId, $arrRoles)) {
                break;
            } else {
                $count++;
            }
        }

        return $newRoleTextId;
    }

    /**
     * Create default roles for specific company
     *
     * @param $fromCompanyId
     * @param $toCompanyId
     * @param string $strRoleName
     * @return array
     */
    public function createDefaultRoles($fromCompanyId, $toCompanyId, $strRoleName = '')
    {
        $arrMappingRoles = array();

        // Get rules in relation to Packages for this company
        $arrPackageRules = $this->getPackages()->getCompanyRules($toCompanyId);

        // Get default Roles
        $select = (new Select())
            ->from('acl_roles')
            ->where([
                'role_status'  => 1,
                'role_visible' => 1,
                'company_id'   => (int)$fromCompanyId
            ]);

        if (!empty($strRoleName)) {
            $select->where(['role_name' => $strRoleName]);
        }

        $arrDefaultRoles = $this->_db2->fetchAll($select);

        if (is_array($arrDefaultRoles)) {
            foreach ($arrDefaultRoles as $defaultRoleInfo) {
                // Create same role
                $originalRoleId     = $defaultRoleInfo['role_parent_id'];
                $originalRealRoleId = $defaultRoleInfo['role_id'];
                unset($defaultRoleInfo['role_id']);

                $defaultRoleInfo['role_child_id'] = 'guest';
                // Generate new role text id
                $defaultRoleInfo['role_parent_id'] = $this->_generateRoleTextId($toCompanyId, $defaultRoleInfo['role_type']);
                $defaultRoleInfo['company_id']     = $toCompanyId;
                $defaultRoleInfo['role_regTime']   = time();

                $newRoleId = $this->_db2->insert('acl_roles', $defaultRoleInfo);

                $arrMappingRoles[$originalRealRoleId] = $newRoleId;

                // Assign access in relation to default roles
                $select = (new Select())
                    ->from('acl_role_access')
                    ->where(['role_id' => $originalRoleId]);

                $arrDefaultRoleAccess = $this->_db2->fetchAll($select);

                $values = array();
                foreach ($arrDefaultRoleAccess as $arrDefaultRoleAccessInfo) {
                    if (in_array($arrDefaultRoleAccessInfo['rule_id'], $arrPackageRules)) {
                        $values['role_id'] = $defaultRoleInfo['role_parent_id'];
                        $values['rule_id'] = (int)$arrDefaultRoleAccessInfo['rule_id'];
                        // Insert all at once
                        if (count($values)) {
                            $this->_db2->insert('acl_role_access', $values);
                        }
                    }
                }
            }
        }

        $this->_roles->clearCompanyRolesCache($toCompanyId);

        return $arrMappingRoles;
    }

    /**
     * Load default role id for specific company by type (admin or user)
     *
     * @param int $companyId
     * @param bool $booAdmin
     * @return int
     */
    private function _getDefaultMemberRole($companyId, $booAdmin = false)
    {
        $userType = $booAdmin ? 'admin' : 'user';
        $select   = (new Select())
            ->from(['r' => 'acl_roles'])
            ->columns(['role_id'])
            ->where([
                'role_type'  => $userType,
                'company_id' => (int)$companyId
            ]);

        return $this->_db2->fetchOne($select);
    }

    /**
     * Check provided company info and return error(s) if any
     *
     * @param array $arrCompanyInfo
     * @return string
     */
    public function checkCompanyInfo($arrCompanyInfo)
    {
        $msgError = '';

        if (empty($msgError) && empty($arrCompanyInfo['companyName'])) {
            $msgError = $this->_tr->translate('Please enter company name');
        }

        if (empty($msgError) && !empty($this->_config['site_version']['check_abn_enabled']) && empty($arrCompanyInfo['company_abn'])) {
            $msgError = $this->_tr->translate('Please enter Company ABN');
        }

        if (empty($msgError) && empty($arrCompanyInfo['address'])) {
            $msgError = $this->_tr->translate('Please enter address');
        }

        if (empty($msgError) && empty($arrCompanyInfo['city'])) {
            $msgError = $this->_tr->translate('Please enter ') . $this->_settings->getSiteCityLabel();
        }

        if (empty($msgError) && empty($arrCompanyInfo['country'])) {
            $msgError = $this->_tr->translate('Please select country');
        }

        if (empty($msgError) && (empty($arrCompanyInfo['state']) && !$this->_country->isDefaultCountry($arrCompanyInfo['country']))) {
            $msgError = $this->_tr->translate('Please enter state');
        }

        if (empty($msgError) && empty($arrCompanyInfo['phone1'])) {
            $msgError = $this->_tr->translate('Please enter company phone #1 number');
        }

        if (array_key_exists('companyEmail', $arrCompanyInfo)) {
            if (empty($msgError) && empty($arrCompanyInfo['companyEmail'])) {
                $msgError = $this->_tr->translate('Please enter company email address');
            }

            if (empty($msgError)) {
                $validator = new EmailAddress();
                if (!$validator->isValid($arrCompanyInfo['companyEmail'])) {
                    // email is invalid; print the reasons
                    foreach ($validator->getMessages() as $message) {
                        $msgError .= "$message\n";
                    }
                }
            }
        }

        if (empty($msgError) && empty($arrCompanyInfo['companyTimeZone'])) {
            $msgError = $this->_tr->translate('Please select Time Zone');
        }

        if (empty($msgError) && array_key_exists('advanced_search_rows_max_count', $arrCompanyInfo) && (!is_numeric($arrCompanyInfo['advanced_search_rows_max_count']) || (int)$arrCompanyInfo['advanced_search_rows_max_count'] < 1 || (int)$arrCompanyInfo['advanced_search_rows_max_count'] > 100)) {
            $msgError = $this->_tr->translate('Please enter a number between 1 and 100 for Max count of rows in Advanced Search.');
        }

        if (empty($msgError) && array_key_exists('storage_location', $arrCompanyInfo) && $arrCompanyInfo['storage_location'] == 's3' && !$this->_config['storage']['is_online']) {
            $msgError = $this->_tr->translate('Please select Local Storage Location. Cloud turned off in the config.');
        }

        return $msgError;
    }

    /**
     * Check provided member info
     *
     * @param array $arrMemberInfo
     * @return string error, empty if all info is correct
     */
    public function checkMemberInfo($arrMemberInfo)
    {
        $msgError = '';

        /** @var Members $members */
        $members = $this->_serviceContainer->get(Members::class);

        if (empty($msgError) && empty($arrMemberInfo['fName'])) {
            $msgError = $this->_tr->translate('Please enter company admin first name');
        }

        if (empty($msgError) && empty($arrMemberInfo['lName'])) {
            $msgError = $this->_tr->translate('Please enter company admin last name');
        }

        if (empty($msgError) && empty($arrMemberInfo['username'])) {
            $msgError = $this->_tr->translate('Please enter company user name');
        }

        $adminId = 0;
        if (empty($msgError)) {
            if (!empty($arrMemberInfo['company_id'])) {
                $arrAdminInfo = $this->loadCompanyAdminInfo($arrMemberInfo['company_id']);
                $adminId      = $arrAdminInfo['admin_id'];
            }

            $prospectId = isset($arrMemberInfo['prospect_id']) && !empty($arrMemberInfo['prospect_id']) ? $arrMemberInfo['prospect_id'] : 0;
            if ($members->isUsernameAlreadyUsed($arrMemberInfo['username'], $adminId, $prospectId)) {
                $msgError = sprintf($this->_tr->translate('Please enter an unique username. %s already in the system as a username.'), $arrMemberInfo['username']);
            }

            if (empty($msgError) && !Clients\Fields::validUserName($arrMemberInfo['username'])) {
                $msgError = $this->_tr->translate('Incorrect characters in username.');
            }
        }

        // Check password:
        // 1. New company
        // 2. Created company and not empty password
        if (empty($msgError) && (empty($arrMemberInfo['company_id']) || (!empty($arrMemberInfo['password'])))) {
            if (empty($arrMemberInfo['password'])) {
                $msgError = $this->_tr->translate('Please enter password');
            } elseif (!isset($arrMemberInfo['hashed_password']) || $arrMemberInfo['hashed_password'] === false) {
                $arrErrors = array();
                /** @var AuthHelper $auth */
                $auth = $this->_serviceContainer->get(AuthHelper::class);
                if (!$auth->isPasswordValid($arrMemberInfo['password'], $arrErrors, $arrMemberInfo['username'], $adminId)) {
                    $msgError = implode('<br/>', $arrErrors);
                }
            }
        }

        if (empty($msgError) && empty($arrMemberInfo['emailAddress'])) {
            $msgError = $this->_tr->translate('Please enter email address');
        }


        if (empty($msgError)) {
            $validator = new EmailAddress();
            if (!$validator->isValid($arrMemberInfo['emailAddress'])) {
                // email is invalid; print the reasons
                foreach ($validator->getMessages() as $message) {
                    $msgError .= "$message\n";
                }
            }
        }

        return $msgError;
    }

    /**
     * Create company from provided info
     *
     * @param array $arrInsertCompany
     * @param array $arrCompanyPackages
     * @return array
     */
    public function createCompany($arrInsertCompany, $arrCompanyPackages = array())
    {
        $companyId = false;

        $arrCompanyDefaultSettings = array();
        try {
            // Default values
            $arrInsertCompany['regTime'] = time();
            $arrInsertCompany['Status']  = $arrInsertCompany['Status'] ?? 1;

            $companyId = $this->_db2->insert('company', $arrInsertCompany);

            // Enable default packages
            if (!is_array($arrCompanyPackages) || empty($arrCompanyPackages)) {
                $this->getPackages()->createDefaultPackages($companyId);
            } else {
                $arrAvailablePackages = $this->getPackages()->getPackages(false, true);
                $arrIntersectPackages = array_intersect($arrAvailablePackages, $arrCompanyPackages);
                $this->getPackages()->updateCompanyPackages($companyId, $arrIntersectPackages, false);
            }

            $arrCompanyDefaultSettings = $this->copyCompanyDefaultSettings($this->getDefaultCompanyId(), $companyId);

            $booError = false;
        } catch (Exception $e) {
            $booError = true;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array('error' => $booError, 'company_id' => $companyId, 'arrCompanyDefaultSettings' => $arrCompanyDefaultSettings);
    }

    /**
     * Create default company's settings
     *
     * @param int $fromCompanyId
     * @param int $toCompanyId
     * @return array
     */
    public function copyCompanyDefaultSettings($fromCompanyId, $toCompanyId)
    {
        // Create default Roles
        $arrMappingRoles = $this->createDefaultRoles($fromCompanyId, $toCompanyId);

        // Create default options list (e.g. options for Categories field)
        /** @var Clients $oClients */
        $oClients = $this->_serviceContainer->get(Clients::class);

        $arrMappingDefaultCaseStatuses    = $oClients->getCaseStatuses()->createDefaultCaseStatuses($fromCompanyId, $toCompanyId);
        $arrMappingDefaultCaseVACs        = $oClients->getCaseVACs()->createDefaultCaseVACs($fromCompanyId, $toCompanyId);
        $arrMappingDefaultCaseStatusLists = $oClients->getCaseStatuses()->createDefaultCaseStatusLists($fromCompanyId, $toCompanyId);
        $arrMappingCaseTemplates          = $oClients->getCaseTemplates()->createCompanyDefaultCaseTemplates($fromCompanyId, $toCompanyId, $arrMappingDefaultCaseStatusLists);
        $arrMappingDefaultCategories      = $oClients->getCaseCategories()->createDefaultCategories($fromCompanyId, $toCompanyId, $arrMappingCaseTemplates, $arrMappingDefaultCaseStatusLists);

        $arrMappingCaseGroupsAndFields   = false;
        $arrMappingClientGroupsAndFields = false;

        $result = $this->_triggers->triggerCopyCompanyDefaultSettings($fromCompanyId, $toCompanyId, $arrMappingRoles, $arrMappingDefaultCategories, $arrMappingDefaultCaseStatuses, $arrMappingDefaultCaseStatusLists, $arrMappingCaseTemplates);
        $result->rewind();
        while ($result->valid()) {
            $item = $result->current();
            if (is_array($item)) {
                if (isset($item[Clients::class])) {
                    $arrMappingCaseGroupsAndFields   = $item[Clients::class]['caseGroupsAndFields'];
                    $arrMappingClientGroupsAndFields = $item[Clients::class]['applicantGroupsAndFields'];
                }
            }
            $result->next();
        }

        // Create default deposit/withdrawal types
        $this->_createDefaultTypes($fromCompanyId, $toCompanyId);

        // Create default mapping between "case status lists" and "case statuses"
        $oClients->getCaseStatuses()->createDefaultListStatusesMapping($arrMappingDefaultCaseStatusLists, $arrMappingDefaultCaseStatuses);

        // Create Company Folder
        // TODO Move to Files service
        $this->_files->mkNewCompanyFolders($toCompanyId);

        return array(
            'arrMappingRoles'                 => $arrMappingRoles,
            'arrMappingDefaultCategories'     => $arrMappingDefaultCategories,
            'arrMappingDefaultCaseStatuses'   => $arrMappingDefaultCaseStatuses,
            'arrMappingDefaultCaseVACs'       => $arrMappingDefaultCaseVACs,
            'arrMappingCaseTemplates'         => $arrMappingCaseTemplates,
            'arrMappingCaseGroupsAndFields'   => $arrMappingCaseGroupsAndFields,
            'arrMappingClientGroupsAndFields' => $arrMappingClientGroupsAndFields,
        );
    }

    /**
     * Create/assign roles to specific member
     *
     * @param int $memberId
     * @param array $arrRoleInfo
     */
    public function addMemberRoles($memberId, $arrRoleInfo)
    {
        if (isset($arrRoleInfo['arrRoleIds']) && is_array($arrRoleInfo['arrRoleIds'])) {
            foreach ($arrRoleInfo['arrRoleIds'] as $roleId) {
                $this->_db2->insert(
                    'members_roles',
                    [
                        'member_id' => $memberId,
                        'role_id'   => $roleId
                    ]
                );
            }
        } elseif (isset($arrRoleInfo['company_id']) && isset($arrRoleInfo['booAdmin'])) {
            $this->_db2->insert(
                'members_roles',
                [
                    'member_id' => $memberId,
                    'role_id'   => $this->_getDefaultMemberRole($arrRoleInfo['company_id'], $arrRoleInfo['booAdmin'])
                ]
            );
        }
    }

    /**
     * Set/update company admin
     *
     * @param int $companyId
     * @param int $adminId
     * @return bool true on success
     */
    public function updateCompanyAdmin($companyId, $adminId)
    {
        try {
            $this->_db2->update(
                'company',
                ['admin_id' => $adminId],
                ['company_id' => $companyId]
            );

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Return company logo size (after it is resized)
     *
     * @return int[] width/height
     */
    public function getCompanyLogoDimensions()
    {
        return array(300, 120);
    }

    /**
     * Create default company sections
     *
     * @param ?int $fromCompanyId
     * @param int $toCompanyId
     * @param int $adminId
     * @param array $arrMembersIds
     * @param array $arrCompanyDefaultSettings
     * @param array $arrCompanyPackages
     */
    public function createDefaultCompanySections($fromCompanyId, $toCompanyId, $adminId, $arrMembersIds, $arrCompanyDefaultSettings, $arrCompanyPackages = array())
    {
        // Create default Folders
        $arrFoldersMapping = $this->_createDefaultFolders($fromCompanyId, $toCompanyId, $adminId);

        // Update owner of all searches for this company
        $arrTablesToUpdate = array('searches', 'u_deposit_types', 'u_destination_types', 'u_withdrawal_types');
        foreach ($arrTablesToUpdate as $table) {
            $this->_db2->update($table, ['author_id' => $adminId], ['company_id' => $toCompanyId]);
        }

        // Create default Templates
        $arrCompanyDefaultSettings['arrMappingTemplates'] = $this->_createDefaultTemplates($fromCompanyId, $adminId, $arrFoldersMapping);

        $this->_triggers->triggerCreateCompanyDefaultSections($fromCompanyId, $toCompanyId, $arrCompanyDefaultSettings, $arrFoldersMapping);

        // Create default Folder Access
        $this->_files->getFolders()->getFolderAccess()->createDefaultFolderAccess($arrCompanyDefaultSettings['arrMappingRoles'], $arrFoldersMapping);

        // Create default folders for the admin + other created company users (if any)
        $booLocal = $this->isCompanyStorageLocationLocal($toCompanyId);
        foreach ($arrMembersIds as $memberId) {
            $this->_files->mkNewMemberFolders($memberId, $toCompanyId, $booLocal, false);
        }

        // In relation to company packages - turn on/off specific functionality
        if (is_array($arrCompanyPackages) && count($arrCompanyPackages)) {
            $arrAvailablePackages = $this->getPackages()->getPackages(false, true);
            $arrIntersectPackages = array_intersect($arrAvailablePackages, $arrCompanyPackages);

            $this->getPackages()->enableDisableProspects($toCompanyId, array(), $arrIntersectPackages);
        }
    }

    /**
     * Create/update company from provided info
     *
     * @param array $arrCompanyInfo
     * @param bool $booCheckAdminInfo
     * @param Analytics $analytics
     * @param Actions $automaticReminderActions
     * @return array
     */
    public function createUpdateCompany($arrCompanyInfo, $booCheckAdminInfo, Analytics $analytics, Actions $automaticReminderActions)
    {
        $arrChangesData = array();

        try {
            /** @var Clients $clients */
            $clients = $this->_serviceContainer->get(Clients::class);

            $msgError      = '';
            $companyId     = $arrCompanyInfo['company_id'];
            $booSuperAdmin = $this->_auth->isCurrentUserSuperadmin();

            if ($booCheckAdminInfo) {
                $msgError = $this->checkMemberInfo($arrCompanyInfo);
            }

            if (empty($msgError)) {
                $msgError = $this->checkCompanyInfo($arrCompanyInfo);
            }

            if (!empty($msgError)) {
                $arrCompanyInfo['error'] = $msgError;

                $arrCompanyInfo['default_label_office_readable'] = $this->getCurrentCompanyDefaultLabel('office');

                if (array_key_exists('use_annotations', $arrCompanyInfo)) {
                    $arrCompanyInfo['use_annotations'] = $arrCompanyInfo['use_annotations'] ? 'Y' : 'N';
                }

                if (array_key_exists('remember_default_fields', $arrCompanyInfo)) {
                    $arrCompanyInfo['remember_default_fields'] = $arrCompanyInfo['remember_default_fields'] ? 'Y' : 'N';
                }

                $arrDefaultOfficeOptions = $this->getDefaultLabelsList('office');
                if (array_key_exists('default_label_office', $arrCompanyInfo)) {
                    if (in_array($arrCompanyInfo['default_label_office'], array_keys($arrDefaultOfficeOptions))) {
                        $defaultOfficeLabel = $arrCompanyInfo['default_label_office'];
                    } else {
                        $defaultOfficeLabel = $this->getDefaultLabel('office');
                    }
                    $arrCompanyInfo['default_label_office'] = $defaultOfficeLabel;
                }

                if (array_key_exists('default_label_trust_account', $arrCompanyInfo)) {
                    if (in_array($arrCompanyInfo['default_label_trust_account'], array_keys($this->getDefaultLabelsList('trust_account')))) {
                        $defaultTALabel = $arrCompanyInfo['default_label_trust_account'];
                    } else {
                        $defaultTALabel = $this->getDefaultLabel('trust_account');
                    }
                    $arrCompanyInfo['default_label_trust_account'] = $defaultTALabel;
                }

                if ($booCheckAdminInfo && array_key_exists('do_not_send_mass_email', $arrCompanyInfo)) {
                    $arrCompanyInfo['send_mass_email'] = $arrCompanyInfo['do_not_send_mass_email'] ? 'N' : 'Y';
                }

                if ($booSuperAdmin && array_key_exists('company_website', $arrCompanyInfo)) {
                    $arrCompanyInfo['company_website'] = $arrCompanyInfo['company_website'] ? 'Y' : 'N';
                }

                $arrCompanyDetails = array();
                if (!empty($companyId)) {
                    $arrCompanyDetails = $this->getCompanyDetailsInfo($companyId);

                    if ($booSuperAdmin) {
                        $arrCompanyInfo['purged']         = $arrCompanyDetails['purged'];
                        $arrCompanyInfo['purged_details'] = $arrCompanyDetails['purged_details'];
                    }
                }

                if ($booSuperAdmin && array_key_exists('allow_export', $arrCompanyInfo)) {
                    $arrCompanyInfo['allow_export'] = $arrCompanyInfo['allow_export'] ? 'Y' : 'N';
                } else {
                    $arrCompanyInfo['allow_export'] = array_key_exists('allow_export', $arrCompanyDetails) ? $arrCompanyDetails['allow_export'] : 'N';
                }

                if ($booSuperAdmin && array_key_exists('allow_import', $arrCompanyInfo)) {
                    $arrCompanyInfo['allow_import'] = $arrCompanyInfo['allow_import'] ? 'Y' : 'N';
                } else {
                    $arrCompanyInfo['allow_import'] = array_key_exists('allow_import', $arrCompanyDetails) ? $arrCompanyDetails['allow_import'] : 'N';
                }

                if ($booSuperAdmin && array_key_exists('allow_import_bcpnp', $arrCompanyInfo)) {
                    $arrCompanyInfo['allow_import_bcpnp'] = $arrCompanyInfo['allow_import_bcpnp'] ? 'Y' : 'N';
                } else {
                    $arrCompanyInfo['allow_import_bcpnp'] = array_key_exists('allow_import_bcpnp', $arrCompanyDetails) ? $arrCompanyDetails['allow_import_bcpnp'] : 'N';
                }

                if ($booSuperAdmin && array_key_exists('allow_multiple_advanced_search_tabs', $arrCompanyInfo)) {
                    $arrCompanyInfo['allow_multiple_advanced_search_tabs'] = $arrCompanyInfo['allow_multiple_advanced_search_tabs'] ? 'Y' : 'N';
                } else {
                    $arrCompanyInfo['allow_multiple_advanced_search_tabs'] = array_key_exists('allow_multiple_advanced_search_tabs', $arrCompanyDetails) ? $arrCompanyDetails['allow_multiple_advanced_search_tabs'] : 'N';
                }

                if ($booSuperAdmin && array_key_exists('allow_change_case_type', $arrCompanyInfo)) {
                    $arrCompanyInfo['allow_change_case_type'] = $arrCompanyInfo['allow_change_case_type'] ? 'Y' : 'N';
                } else {
                    $arrCompanyInfo['allow_change_case_type'] = array_key_exists('allow_change_case_type', $arrCompanyDetails) ? $arrCompanyDetails['allow_change_case_type'] : 'N';
                }

                if ($booSuperAdmin && array_key_exists('allow_decision_rationale_tab', $arrCompanyInfo)) {
                    $arrCompanyInfo['allow_decision_rationale_tab'] = $arrCompanyInfo['allow_decision_rationale_tab'] ? 'Y' : 'N';
                    if ($arrCompanyInfo['allow_decision_rationale_tab'] == 'Y') {
                        $arrCompanyInfo['decision_rationale_tab_name'] = array_key_exists('decision_rationale_tab_name', $arrCompanyInfo) ? $arrCompanyInfo['decision_rationale_tab_name'] : 'Draft Notes';
                    } else {
                        $arrCompanyInfo['decision_rationale_tab_name'] = 'Draft Notes';
                    }
                } else {
                    $arrCompanyInfo['allow_decision_rationale_tab'] = array_key_exists('allow_decision_rationale_tab', $arrCompanyDetails) ? $arrCompanyDetails['allow_decision_rationale_tab'] : 'N';
                    $arrCompanyInfo['decision_rationale_tab_name']  = array_key_exists('decision_rationale_tab_name', $arrCompanyDetails) ? $arrCompanyDetails['decision_rationale_tab_name'] : 'Draft Notes';
                }

                if ($booSuperAdmin && array_key_exists('enable_case_management', $arrCompanyInfo)) {
                    $arrCompanyInfo['enable_case_management'] = $arrCompanyInfo['enable_case_management'] ? 'Y' : 'N';
                } else {
                    $arrCompanyInfo['enable_case_management'] = array_key_exists('enable_case_management', $arrCompanyDetails) ? $arrCompanyDetails['enable_case_management'] : 'N';
                }

                if (array_key_exists('loose_task_rules', $arrCompanyInfo)) {
                    $arrCompanyInfo['loose_task_rules'] = $arrCompanyInfo['loose_task_rules'] ? 'Y' : 'N';
                } else {
                    $arrCompanyInfo['loose_task_rules'] = array_key_exists('loose_task_rules', $arrCompanyDetails) ? $arrCompanyDetails['loose_task_rules'] : 'N';
                }

                if (array_key_exists('hide_inactive_users', $arrCompanyInfo)) {
                    $arrCompanyInfo['hide_inactive_users'] = $arrCompanyInfo['hide_inactive_users'] ? 'Y' : 'N';
                } else {
                    $arrCompanyInfo['hide_inactive_users'] = array_key_exists('hide_inactive_users', $arrCompanyDetails) ? $arrCompanyDetails['hide_inactive_users'] : 'N';
                }

                if ($booSuperAdmin && array_key_exists('time_tracker_enabled', $arrCompanyInfo)) {
                    $arrCompanyInfo['time_tracker_enabled'] = $arrCompanyInfo['time_tracker_enabled'] ? 'Y' : 'N';
                }

                if ($booSuperAdmin && array_key_exists('employers_module_enabled', $arrCompanyInfo)) {
                    $arrCompanyInfo['employers_module_enabled'] = $arrCompanyInfo['employers_module_enabled'] ? 'Y' : 'N';
                }

                if ($booSuperAdmin && array_key_exists('log_client_changes_enabled', $arrCompanyInfo)) {
                    $arrCompanyInfo['log_client_changes_enabled'] = $arrCompanyInfo['log_client_changes_enabled'] ? 'Y' : 'N';
                }

                if (!empty($companyId)) {
                    $select = (new Select())
                        ->from('company')
                        ->columns(['companyLogo'])
                        ->where(['company_id' => $companyId]);

                    $arrCompanyInfo['companyLogo'] = $this->_db2->fetchOne($select);

                    $arrSavedCompanyInfo               = $this->getCompanyInfo($companyId);
                    $arrCompanyInfo['companyTimeZone'] = $arrSavedCompanyInfo['companyTimeZone'];
                }

                unset($arrCompanyInfo['provinces']);

                return array(
                    'arrCompanyInfo' => $arrCompanyInfo,
                    'arrChangesData' => $arrChangesData
                );
            }

            if (!$this->canUpdateTimeZone($companyId)) {
                unset($arrCompanyInfo['companyTimeZone']);
            }

            if (isset($arrCompanyInfo['provinces'])) {
                if ($this->_country->isDefaultCountry($arrCompanyInfo['country'])) {
                    $arrCompanyInfo['state'] = $arrCompanyInfo['provinces'];
                }
                unset($arrCompanyInfo['provinces']);
            }

            $arrAdminInfo = array(
                'fName'        => $arrCompanyInfo['fName'],
                'lName'        => $arrCompanyInfo['lName'],
                'username'     => $arrCompanyInfo['username'],
                'password'     => $arrCompanyInfo['password'],
                'emailAddress' => $arrCompanyInfo['emailAddress'],
            );

            $arrInsertCompany = $arrCompanyInfo;
            foreach ($arrAdminInfo as $key => $val) {
                unset($arrInsertCompany[$key]);
            }

            unset(
                $arrInsertCompany['use_annotations'],
                $arrInsertCompany['remember_default_fields'],
                $arrInsertCompany['do_not_send_mass_email'],
                $arrInsertCompany['company_website'],
                $arrInsertCompany['allow_export'],
                $arrInsertCompany['allow_import'],
                $arrInsertCompany['allow_import_bcpnp'],
                $arrInsertCompany['allow_multiple_advanced_search_tabs'],
                $arrInsertCompany['allow_change_case_type'],
                $arrInsertCompany['allow_decision_rationale_tab'],
                $arrInsertCompany['decision_rationale_tab_name'],
                $arrInsertCompany['marketplace_module_enabled'],
                $arrInsertCompany['time_tracker_enabled'],
                $arrInsertCompany['employers_module_enabled'],
                $arrInsertCompany['log_client_changes_enabled'],
                $arrInsertCompany['default_label_office'],
                $arrInsertCompany['default_label_trust_account'],
                $arrInsertCompany['advanced_search_rows_max_count'],
                $arrInsertCompany['enable_case_management'],
                $arrInsertCompany['loose_task_rules'],
                $arrInsertCompany['hide_inactive_users'],
                $arrInsertCompany['invoice_number_format'],
                $arrInsertCompany['invoice_number_start_from'],
                $arrInsertCompany['invoice_tax_number'],
                $arrInsertCompany['invoice_disclaimer'],
                $arrInsertCompany['client_profile_id_enabled'],
                $arrInsertCompany['client_profile_id_format'],
                $arrInsertCompany['client_profile_id_start_from'],
            );


            $arrOldCompanyInfo    = empty($companyId) ? array() : $this->getCompanyInfo($companyId);
            $arrOldCompanyDetails = empty($companyId) ? array() : $this->getCompanyDetailsInfo($companyId);

            // Only super admin can update company storage location
            if (!$booSuperAdmin) {
                unset($arrInsertCompany['storage_location']);
            }

            if (!empty($companyId)) {
                $booLocal       = $this->isCompanyStorageLocationLocal($companyId);
                $fileSaveResult = $this->_files->saveImage(
                    $this->getCompanyLogoFolderPath($companyId, $booLocal),
                    'companyLogo',
                    'logo',
                    $this->getCompanyLogoDimensions(),
                    $booLocal
                );

                if ($fileSaveResult['error']) {
                    $arrCompanyInfo['error'] = $fileSaveResult['result'];

                    return array(
                        'arrCompanyInfo' => $arrCompanyInfo,
                        'arrChangesData' => $arrChangesData
                    );
                } else {
                    // Check if we need to update file name
                    if (!empty($fileSaveResult['result'])) {
                        $arrCompanyInfo['companyLogo'] = $arrInsertCompany['companyLogo'] = $fileSaveResult['result'];
                    } else {
                        unset($arrInsertCompany['companyLogo']);
                    }
                }
                $arrChangesData = $this->createArrChangesData($arrInsertCompany, 'company', $companyId);

                $this->_db2->update('company', $arrInsertCompany, ['company_id' => $companyId]);

                $select = (new Select())
                    ->from('company')
                    ->columns(['companyLogo'])
                    ->where(['company_id' => $companyId]);

                $arrCompanyInfo['companyLogo'] = $this->_db2->fetchOne($select);


                $arrSavedCompanyInfo               = $this->getCompanyInfo($companyId);
                $arrCompanyInfo['companyTimeZone'] = $arrSavedCompanyInfo['companyTimeZone'];
            } else {
                $arrResult = $this->createCompany($arrInsertCompany);
                if ($arrResult['error']) {
                    $arrCompanyInfo['error'] = $this->_tr->translate('Cannot create a company');

                    return array(
                        'arrCompanyInfo' => $arrCompanyInfo,
                        'arrChangesData' => $arrChangesData
                    );
                }

                // Company successfully created
                $arrCompanyInfo['company_id'] = $companyId = $arrResult['company_id'];

                $arrDivisionGroupInfo = array(
                    'division_group_company'   => 'Main',
                    'division_group_is_system' => 'Y'
                );

                $CompanyDivisions = $this->getCompanyDivisions();
                $divisionGroupId  = $CompanyDivisions->createUpdateDivisionsGroup($companyId, 0, $arrDivisionGroupInfo);
                if (empty($divisionGroupId)) {
                    $arrCompanyInfo['error'] = $this->_tr->translate('Cannot create a division group.');

                    return array(
                        'arrCompanyInfo' => $arrCompanyInfo,
                        'arrChangesData' => $arrChangesData
                    );
                }

                // Assign all previously created roles to this default divisions group (if role's group id was set)
                $arrRoleIds = array();
                $arrRoles   = $this->getCompanyRoles($companyId);
                foreach ($arrRoles as $arrRoleInfo) {
                    if (!empty($arrRoleInfo['division_group_id'])) {
                        $arrRoleIds[] = $arrRoleInfo['role_id'];
                    }
                }

                if (count($arrRoleIds)) {
                    $this->_roles->updateRoleDetails($arrRoleIds, array('division_group_id' => $divisionGroupId));
                }

                // Create admin
                $arrUserTypes                      = Members::getMemberType('admin');
                $arrAdminInfo['userType']          = $arrUserTypes[0];
                $arrAdminInfo['company_id']        = $companyId;
                $arrAdminInfo['division_group_id'] = $divisionGroupId;

                /** @var Users $oUsers */
                $oUsers = $this->_serviceContainer->get(Users::class);

                $adminCreationResult = $oUsers->createUser($arrAdminInfo, $arrCompanyInfo['companyTimeZone']);
                if ($adminCreationResult['error']) {
                    $arrCompanyInfo['error'] = $this->_tr->translate('Cannot create admin of the company');

                    return array(
                        'arrCompanyInfo' => $arrCompanyInfo,
                        'arrChangesData' => $arrChangesData
                    );
                }

                $adminId = $adminCreationResult['member_id'];


                // Automatically assign the first office created for this company
                $arrDivisions = $CompanyDivisions->getDivisionsByGroupId($divisionGroupId);
                if (!$CompanyDivisions->addMemberDivision($adminId, $arrDivisions)) {
                    $arrCompanyInfo['error'] = $this->_tr->translate('Cannot assign divisions to the admin.');

                    return array(
                        'arrCompanyInfo' => $arrCompanyInfo,
                        'arrChangesData' => $arrChangesData
                    );
                }

                // Assign default Roles
                $this->addMemberRoles($adminId, array('company_id' => $companyId, 'booAdmin' => true));


                // Make this member as company admin
                $this->updateCompanyAdmin($companyId, $adminId);

                // Create default sections which are based on admin's id
                $this->createDefaultCompanySections(null, $companyId, $adminId, [$adminId], $arrResult['arrCompanyDefaultSettings'], $automaticReminderActions);

                // Save / update logo
                $booLocal       = $this->isCompanyStorageLocationLocal($companyId);
                $fileSaveResult = $this->_files->saveImage(
                    $this->getCompanyLogoFolderPath($companyId, $booLocal),
                    'companyLogo',
                    'logo',
                    $this->getCompanyLogoDimensions(),
                    $booLocal
                );

                if ($fileSaveResult['error']) {
                    $arrCompanyInfo['error'] = $fileSaveResult['result'];

                    return array(
                        'arrCompanyInfo' => $arrCompanyInfo,
                        'arrChangesData' => $arrChangesData
                    );
                } else {
                    // Check if we need to update file name
                    if (!empty($fileSaveResult['result'])) {
                        $this->_db2->update(
                            'company',
                            ['companyLogo' => $fileSaveResult['result']],
                            ['company_id' => $companyId]
                        );
                        $arrCompanyInfo['companyLogo'] = $fileSaveResult['result'];
                    }
                }
            }

            // Update other settings
            $arrToUpdate = array();
            if (array_key_exists('use_annotations', $arrCompanyInfo)) {
                $arrToUpdate['use_annotations'] = $arrCompanyInfo['use_annotations'] = $arrCompanyInfo['use_annotations'] ? 'Y' : 'N';
            }

            if (array_key_exists('remember_default_fields', $arrCompanyInfo)) {
                $arrToUpdate['remember_default_fields'] = $arrCompanyInfo['remember_default_fields'] = $arrCompanyInfo['remember_default_fields'] ? 'Y' : 'N';
            }

            $arrDefaultOfficeOptions = $this->getDefaultLabelsList('office');
            if (array_key_exists('default_label_office', $arrCompanyInfo)) {
                if (in_array($arrCompanyInfo['default_label_office'], array_keys($arrDefaultOfficeOptions))) {
                    $defaultOfficeLabel = $arrCompanyInfo['default_label_office'];
                } else {
                    $defaultOfficeLabel = $this->getDefaultLabel('office');
                }
                $arrToUpdate['default_label_office'] = $arrCompanyInfo['default_label_office'] = $defaultOfficeLabel;
            }

            if (array_key_exists('default_label_trust_account', $arrCompanyInfo)) {
                if (in_array($arrCompanyInfo['default_label_trust_account'], array_keys($this->getDefaultLabelsList('trust_account')))) {
                    $defaultTALabel = $arrCompanyInfo['default_label_trust_account'];
                } else {
                    $defaultTALabel = $this->getDefaultLabel('trust_account');
                }
                $arrToUpdate['default_label_trust_account'] = $arrCompanyInfo['default_label_trust_account'] = $defaultTALabel;
            }

            if ($booSuperAdmin && array_key_exists('advanced_search_rows_max_count', $arrCompanyInfo)) {
                $arrToUpdate['advanced_search_rows_max_count'] = (int)$arrCompanyInfo['advanced_search_rows_max_count'];
            }

            if ($booCheckAdminInfo && array_key_exists('do_not_send_mass_email', $arrCompanyInfo)) {
                $arrToUpdate['send_mass_email'] = $arrCompanyInfo['send_mass_email'] = $arrCompanyInfo['do_not_send_mass_email'] ? 'N' : 'Y';
            }

            if ($booSuperAdmin && array_key_exists('company_website', $arrCompanyInfo)) {
                $arrToUpdate['company_website'] = $arrCompanyInfo['company_website'] = $arrCompanyInfo['company_website'] ? 'Y' : 'N';
            }

            $arrCompanyDetails = array();
            if (!empty($companyId)) {
                $arrCompanyDetails = $this->getCompanyDetailsInfo($companyId);
            }
            if ($booSuperAdmin && array_key_exists('allow_export', $arrCompanyInfo)) {
                $arrToUpdate['allow_export'] = $arrCompanyInfo['allow_export'] = $arrCompanyInfo['allow_export'] ? 'Y' : 'N';
            } else {
                $arrCompanyInfo['allow_export'] = array_key_exists('allow_export', $arrCompanyDetails) ? $arrCompanyDetails['allow_export'] : 'N';
            }

            if ($booSuperAdmin && array_key_exists('allow_import', $arrCompanyInfo)) {
                $arrToUpdate['allow_import'] = $arrCompanyInfo['allow_import'] = $arrCompanyInfo['allow_import'] ? 'Y' : 'N';
            } else {
                $arrCompanyInfo['allow_import'] = array_key_exists('allow_import', $arrCompanyDetails) ? $arrCompanyDetails['allow_import'] : 'N';
            }

            if ($booSuperAdmin && array_key_exists('allow_import_bcpnp', $arrCompanyInfo)) {
                $arrToUpdate['allow_import_bcpnp'] = $arrCompanyInfo['allow_import_bcpnp'] = $arrCompanyInfo['allow_import_bcpnp'] ? 'Y' : 'N';
            } else {
                $arrCompanyInfo['allow_import_bcpnp'] = array_key_exists('allow_import_bcpnp', $arrCompanyDetails) ? $arrCompanyDetails['allow_import_bcpnp'] : 'N';
            }

            if ($booSuperAdmin && array_key_exists('allow_multiple_advanced_search_tabs', $arrCompanyInfo)) {
                $arrToUpdate['allow_multiple_advanced_search_tabs'] = $arrCompanyInfo['allow_multiple_advanced_search_tabs'] = $arrCompanyInfo['allow_multiple_advanced_search_tabs'] ? 'Y' : 'N';
            } else {
                $arrCompanyInfo['allow_multiple_advanced_search_tabs'] = array_key_exists('allow_multiple_advanced_search_tabs', $arrCompanyDetails) ? $arrCompanyDetails['allow_multiple_advanced_search_tabs'] : 'N';
            }

            if ($booSuperAdmin && array_key_exists('allow_change_case_type', $arrCompanyInfo)) {
                $arrToUpdate['allow_change_case_type'] = $arrCompanyInfo['allow_change_case_type'] = $arrCompanyInfo['allow_change_case_type'] ? 'Y' : 'N';
            } else {
                $arrCompanyInfo['allow_change_case_type'] = array_key_exists('allow_change_case_type', $arrCompanyDetails) ? $arrCompanyDetails['allow_change_case_type'] : 'N';
            }

            if ($booSuperAdmin && array_key_exists('allow_decision_rationale_tab', $arrCompanyInfo)) {
                $arrToUpdate['allow_decision_rationale_tab'] = $arrCompanyInfo['allow_decision_rationale_tab'] = $arrCompanyInfo['allow_decision_rationale_tab'] ? 'Y' : 'N';
                if ($arrToUpdate['allow_decision_rationale_tab'] == 'Y') {
                    $arrToUpdate['decision_rationale_tab_name'] = $arrCompanyInfo['decision_rationale_tab_name'] = array_key_exists('decision_rationale_tab_name', $arrCompanyInfo) ? $arrCompanyInfo['decision_rationale_tab_name'] : 'Draft Notes';
                } else {
                    $arrToUpdate['decision_rationale_tab_name'] = $arrCompanyInfo['decision_rationale_tab_name'] = 'Draft Notes';
                }
            } else {
                $arrCompanyInfo['allow_decision_rationale_tab'] = array_key_exists('allow_decision_rationale_tab', $arrCompanyDetails) ? $arrCompanyDetails['allow_decision_rationale_tab'] : 'N';
                $arrCompanyInfo['decision_rationale_tab_name']  = array_key_exists('decision_rationale_tab_name', $arrCompanyDetails) ? $arrCompanyDetails['decision_rationale_tab_name'] : 'Draft Notes';
            }

            if ($booSuperAdmin && array_key_exists('enable_case_management', $arrCompanyInfo)) {
                $arrToUpdate['enable_case_management'] = $arrCompanyInfo['enable_case_management'] = $arrCompanyInfo['enable_case_management'] ? 'Y' : 'N';
            } else {
                $arrCompanyInfo['enable_case_management'] = array_key_exists('enable_case_management', $arrCompanyDetails) ? $arrCompanyDetails['enable_case_management'] : 'N';
            }

            if (array_key_exists('loose_task_rules', $arrCompanyInfo)) {
                $arrToUpdate['loose_task_rules'] = $arrCompanyInfo['loose_task_rules'] = $arrCompanyInfo['loose_task_rules'] ? 'Y' : 'N';
            } else {
                $arrCompanyInfo['loose_task_rules'] = array_key_exists('loose_task_rules', $arrCompanyDetails) ? $arrCompanyDetails['loose_task_rules'] : 'N';
            }

            if (array_key_exists('hide_inactive_users', $arrCompanyInfo)) {
                $arrToUpdate['hide_inactive_users'] = $arrCompanyInfo['hide_inactive_users'] = $arrCompanyInfo['hide_inactive_users'] ? 'Y' : 'N';
            } else {
                $arrCompanyInfo['hide_inactive_users'] = array_key_exists('hide_inactive_users', $arrCompanyDetails) ? $arrCompanyDetails['hide_inactive_users'] : 'N';
            }

            if ($booSuperAdmin && array_key_exists('time_tracker_enabled', $arrCompanyInfo)) {
                $arrToUpdate['time_tracker_enabled'] = $arrCompanyInfo['time_tracker_enabled'] = $arrCompanyInfo['time_tracker_enabled'] ? 'Y' : 'N';
            }

            if ($booSuperAdmin && array_key_exists('employers_module_enabled', $arrCompanyInfo)) {
                $arrToUpdate['employers_module_enabled'] = $arrCompanyInfo['employers_module_enabled'] = $arrCompanyInfo['employers_module_enabled'] ? 'Y' : 'N';
            }

            if ($booSuperAdmin && array_key_exists('log_client_changes_enabled', $arrCompanyInfo)) {
                $arrToUpdate['log_client_changes_enabled'] = $arrCompanyInfo['log_client_changes_enabled'] = $arrCompanyInfo['log_client_changes_enabled'] ? 'Y' : 'N';
            }

            if ($booSuperAdmin && array_key_exists('marketplace_module_enabled', $arrCompanyInfo)) {
                $arrToUpdate['marketplace_module_enabled'] = $arrCompanyInfo['marketplace_module_enabled'] = $arrCompanyInfo['marketplace_module_enabled'] ? 'Y' : 'N';
            }

            if (array_key_exists('invoice_number_format', $arrCompanyInfo)) {
                $arrToUpdate['invoice_number_settings'] = Json::encode(
                    [
                        'format'     => $arrCompanyInfo['invoice_number_format'],
                        'start_from' => $arrCompanyInfo['invoice_number_start_from'],
                        'tax_number' => $arrCompanyInfo['invoice_tax_number'],
                        'disclaimer' => $arrCompanyInfo['invoice_disclaimer'],
                    ]
                );
            }

            if (array_key_exists('client_profile_id_format', $arrCompanyInfo)) {
                $booEnabledClientProfileIdFormat = isset($arrCompanyInfo['client_profile_id_enabled']);

                $arrSavedClientProfileIdFormat = $this->getCompanyClientProfileIdSettings($companyId);
                if (!$booEnabledClientProfileIdFormat) {
                    $arrCompanyInfo['client_profile_id_start_from'] = $arrSavedClientProfileIdFormat['start_from'];
                    $arrCompanyInfo['client_profile_id_format']     = $arrSavedClientProfileIdFormat['format'];
                }

                $arrToUpdate['client_profile_id_settings'] = Json::encode(
                    [
                        'enabled'    => $booEnabledClientProfileIdFormat ? 1 : 0,
                        'start_from' => $arrCompanyInfo['client_profile_id_start_from'],
                        'format'     => $arrCompanyInfo['client_profile_id_format'],
                    ]
                );

                // Toggle access to the field(s) if checkbox is checked/unchecked
                if ($booEnabledClientProfileIdFormat != $arrSavedClientProfileIdFormat['enabled']) {
                    $arrRoles = $this->_roles->getCompanyRoles($companyId, 0, false, true, ['admin', 'user', 'individual_client', 'employer_client']);

                    $arrFieldDefaultAccess = array();
                    foreach ($arrRoles as $roleId) {
                        $arrFieldDefaultAccess[$roleId] = $booEnabledClientProfileIdFormat ? 'R' : '';
                    }

                    $oApplicantFields   = $clients->getApplicantFields();
                    $arrApplicantFields = $oApplicantFields->getCompanyAllFields($companyId);
                    foreach ($arrApplicantFields as $arrApplicantFieldInfo) {
                        if ($arrApplicantFieldInfo['type'] == 'client_profile_id') {
                            $oApplicantFields->updateDefaultAccessRights($arrApplicantFieldInfo['applicant_field_id'], $arrFieldDefaultAccess);
                        }
                    }
                }
            }

            if (count($arrToUpdate)) {
                if (is_array($arrCompanyDetails) && count($arrCompanyDetails)) {
                    $arrChangesData = array_merge($arrChangesData, $this->createArrChangesData($arrToUpdate, 'company_details', $companyId));
                    // Update settings
                    $this->updateCompanyDetails($companyId, $arrToUpdate);

                    if ($booSuperAdmin) {
                        // update web-builder
                        $this->updateWebBuilder($companyId, $arrToUpdate['company_website'] == 'Y');

                        // Toggle access to the Client's Time Tracker
                        if ($arrOldCompanyDetails['time_tracker_enabled'] != $arrToUpdate['time_tracker_enabled']) {
                            $this->updateTimeTracker($companyId, $arrToUpdate['time_tracker_enabled'] == 'Y');
                        }

                        // Toggle access to the Marketplace
                        if ($arrOldCompanyDetails['marketplace_module_enabled'] != $arrToUpdate['marketplace_module_enabled']) {
                            $this->getCompanyMarketplace()->toggleMarketplace($companyId, $arrToUpdate['marketplace_module_enabled'] == 'Y');
                        }

                        if ($arrCompanyDetails['allow_import'] != $arrCompanyInfo['allow_import']) {
                            $this->toggleAccessToImport($companyId, $arrToUpdate['allow_import'] == 'Y');
                        }

                        if ($arrCompanyDetails['allow_import_bcpnp'] != $arrCompanyInfo['allow_import_bcpnp']) {
                            $this->toggleAccessToImport($companyId, $arrToUpdate['allow_import_bcpnp'] == 'Y', true);
                        }

                        if ($arrCompanyDetails['allow_export'] != $arrCompanyInfo['allow_export']) {
                            $this->toggleAccessToExport($companyId, $arrToUpdate['allow_export'] == 'Y');
                        }
                    }

                    // Update default values for current logged in user
                    if (array_key_exists('default_label_office', $arrCompanyInfo)) {
                        $this->_auth->getIdentity()->default_label_office = $arrCompanyInfo['default_label_office'];

                        // Rename all office fields for clients
                        if (in_array($arrCompanyInfo['default_label_office'], array_keys($arrDefaultOfficeOptions))) {
                            $clients->getApplicantFields()->renameOfficeFields($companyId, $arrDefaultOfficeOptions[$arrCompanyInfo['default_label_office']]);
                        }
                    }

                    if (array_key_exists('default_label_trust_account', $arrCompanyInfo)) {
                        $this->_auth->getIdentity()->default_label_trust_account = $arrCompanyInfo['default_label_trust_account'];
                    }
                } else {
                    // Create new record in company_details table
                    $arrToUpdate['company_id'] = $companyId;
                    $this->updateCompanyDetails($companyId, $arrToUpdate);

                    // Create default Case Number Settings
                    $clients->getCaseNumber()->createDefaultCompanyCaseNumberSettings(0, $companyId);

                    // Copy default analytics to this new company
                    $analytics->createDefaultCompanyAnalytics(0, $companyId);
                }
            }

            if ($booSuperAdmin && array_key_exists('Status', $arrCompanyInfo) && $arrCompanyInfo['Status'] != '') {
                // Changes status
                $booResult = $this->updateCompanyStatus(
                    isset($arrOldCompanyInfo['Status']) ? $this->getCompanyStringStatusById($arrOldCompanyInfo['Status']) : '',
                    strtolower($this->getCompanyStringStatusById($arrCompanyInfo['Status']) ?? ''),
                    $companyId,
                    $arrOldCompanyDetails['marketplace_module_enabled'],
                    $arrToUpdate['marketplace_module_enabled']
                );

                // If status was suspended - we need mark all failed invoices as unpaid
                if ($booResult && $arrCompanyInfo['Status'] == $this->getCompanyIntStatusByString('suspended')) {
                    $oInvoices   = $this->getCompanyInvoice();
                    $arrInvoices = $oInvoices->getCompanyFailedInvoices($companyId);
                    foreach ($arrInvoices as $arrInvoiceInfo) {
                        $oInvoices->markInvoiceUnpaid($arrInvoiceInfo['company_invoice_id']);
                    }
                }
            }

            $arrCompanyInfo['default_label_office_readable'] = $this->getCurrentCompanyDefaultLabel('office');
            $arrCompanyInfo['error']                         = '';
        } catch (Exception $e) {
            $arrCompanyInfo['error'] = 'Internal error.';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'arrCompanyInfo' => $arrCompanyInfo,
            'arrChangesData' => $arrChangesData
        );
    }

    /**
     * Calculate active users count for specific company
     *
     * @param int $companyId
     * @return int
     */
    public function calculateActiveUsers($companyId)
    {
        $select = (new Select())
            ->from('view_active_users')
            ->columns(['active_users_count'])
            ->where(['company_id' => (int)$companyId]);

        return $this->_db2->fetchOne($select);
    }

    /**
     * Calculate clients count for specific company
     *
     * @param int $companyId
     * @return string
     */
    public function calculateClients($companyId)
    {
        $select = (new Select())
            ->from('view_clients_count')
            ->columns(['clients_count'])
            ->where(['company_id' => (int)$companyId]);

        return $this->_db2->fetchOne($select);
    }

    /**
     * Get date when company T/A was uploaded last time
     *
     * @param int $companyId
     * @return string date
     */
    public function getCompanyLastTAUploaded($companyId)
    {
        $select = (new Select())
            ->from('view_last_ta_uploaded')
            ->columns(['last_ta_uploaded_date'])
            ->where(['company_id' => (int)$companyId]);

        return $this->_db2->fetchOne($select);
    }

    /**
     * Get price for additional user
     * @param int $billingFrequency
     * @param string $subscriptionId
     * @param int|bool $pricingCategoryId
     * @return float price
     */
    public function getUserPrice($billingFrequency = 1, $subscriptionId = 'lite', $pricingCategoryId = false)
    {
        if (!$pricingCategoryId) {
            $pricingCategoryId = $this->_pricingCategories->getPricingCategoryIdByName('General');
        }

        $pricingCategoryDetails = $this->_pricingCategories->getPricingCategoryDetails($pricingCategoryId, $subscriptionId);

        $price = '';

        if (is_array($pricingCategoryDetails) && !empty($pricingCategoryDetails)) {
            switch ($billingFrequency) {
                case 2: // Annually
                    $price = $pricingCategoryDetails['price_license_user_annual'];
                    break;

                case 3: // Every two years
                    if (is_numeric($pricingCategoryDetails['price_license_user_annual'])) {
                        $price = $pricingCategoryDetails['price_license_user_annual'] * 2;
                    }
                    break;

                default: // Monthly
                    $price = $pricingCategoryDetails['price_license_user_monthly'];
                    break;
            }
        }

        return (float)sprintf('%01.2f', $price);
    }

    /**
     * Load subscription prices for specific company
     *
     * @param array $arrCompanyInfo
     * @return array (price per month, price per year)
     */
    public function getCompanySubscriptionPrices($arrCompanyInfo)
    {
        // Load subscription fees
        $companySubscriptionFee = $arrCompanyInfo['subscription_fee'];
        switch ($arrCompanyInfo['payment_term']) {
            case '1': // Monthly
                $feeMonthly = $companySubscriptionFee;
                $feeAnnual  = $feeMonthly * 10;
                break;

            case '2': // Annually
                $feeAnnual  = $companySubscriptionFee;
                $feeMonthly = $feeAnnual / 10;
                break;

            case '3': // Biannually
                $feeAnnual  = $companySubscriptionFee / 2;
                $feeMonthly = $feeAnnual / 10;
                break;

            default: // Unknown
                $feeAnnual  = 0;
                $feeMonthly = 0;
                break;
        }

        return array($feeMonthly, $feeAnnual);
    }

    /**
     * Get price for additional 1 Gb of storage
     * @param int $billingFrequency
     * @param bool|int $pricingCategoryId
     * @return float price
     */
    public function getStoragePrice($billingFrequency = 1, $pricingCategoryId = false)
    {
        if (!$pricingCategoryId) {
            $pricingCategoryId = $this->_pricingCategories->getPricingCategoryIdByName('General');
        }

        $arrPriceCategoryDetails = $this->_pricingCategories->getPricingCategoryDetails($pricingCategoryId, 'lite');

        $price = '';

        if (is_array($arrPriceCategoryDetails) && !empty($arrPriceCategoryDetails)) {
            switch ($billingFrequency) {
                case 2: // Annually
                    $price = $arrPriceCategoryDetails['price_storage_1_gb_annual'];
                    break;
                case 3: // Every two years
                    if (is_numeric($arrPriceCategoryDetails['price_storage_1_gb_annual'])) {
                        $price = $arrPriceCategoryDetails['price_storage_1_gb_annual'] * 2;
                    }
                    break;

                default: // Monthly
                    $price = $arrPriceCategoryDetails['price_storage_1_gb_monthly'];
                    break;
            }
        }

        return (float)sprintf('%01.2f', $price);
    }

    /**
     * Calculate additional user licenses count
     *
     * @param int $activeUsers
     * @param int $freeUsers
     * @return int
     */
    public function calculateAdditionalUsers($activeUsers, $freeUsers)
    {
        $additionalUsers = $activeUsers - $freeUsers;

        return max($additionalUsers, 0);
    }

    /**
     * Calculate fee for additional users count for specific company
     *
     * @param int $companyId
     * @param int $additionalUsers
     * @param int $billingFrequency
     * @param bool|int $pricingCategoryId
     * @return float|int
     */
    public function calculateAdditionalUsersFee($companyId, $additionalUsers, $billingFrequency, $pricingCategoryId = false)
    {
        $arrCompanyInfo = $this->getCompanyAndDetailsInfo($companyId, array('company_id'), false);

        return $this->calculateAdditionalUsersFeeBySubscription(
            $arrCompanyInfo['subscription'],
            $additionalUsers,
            $billingFrequency,
            $pricingCategoryId
        );
    }

    /**
     * Calculate fee for additional users count by subscription
     *
     * @param string $subscription
     * @param int $additionalUsers
     * @param int $billingFrequency
     * @param bool|int $pricingCategoryId
     * @return float|int
     */
    public function calculateAdditionalUsersFeeBySubscription($subscription, $additionalUsers, $billingFrequency, $pricingCategoryId = false)
    {
        return $additionalUsers * $this->getUserPrice($billingFrequency, $subscription, $pricingCategoryId);
    }

    /**
     * Calculate additional storage used for specific company
     *
     * @param int $companyId
     * @param float $freeStorage
     * @return float
     */
    public function calculateAdditionalStorage($companyId, $freeStorage)
    {
        $arrCompanyInfo = $this->getCompanyAndDetailsInfo($companyId, array('storage_today'), false);

        $storage               = $arrCompanyInfo['storage_today']; //get company storage in KB
        $companyDefaultStorage = $freeStorage * 1024 * 1024; //get free storage in KB
        $additionalStorage     = $storage - $companyDefaultStorage; //get additional storage
        $additionalStorage     = max($additionalStorage, 0); //only unsigned value
        $additionalStorage     = $additionalStorage / 1024 / 1024; //KB to GB
        $additionalStorage     = ($additionalStorage < 0.01 && $additionalStorage > 0) ? 0.01 : $additionalStorage; //not need to show values like 0.00001

        return round($additionalStorage, 2);
    }

    /**
     * Calculate fee for additional storage usage for specific company
     *
     * @param float $additionalStorage
     * @param int $billingFrequency
     * @return float
     */
    public function calculateAdditionalStorageFee($additionalStorage, $billingFrequency)
    {
        return ceil($additionalStorage) * $this->getStoragePrice($billingFrequency);
    }

    /**
     * Load path to invoices for specific company
     *
     * @param int|string $companyId
     * @param bool $booLocal
     * @return string
     */
    public function getPathToInvoices($companyId, $booLocal)
    {
        $root      = $booLocal ? $this->_config['directory']['companyfiles'] : '';
        $companyId = empty($companyId) || !is_numeric($companyId) ? 0 : $companyId;

        return $root . '/' . $companyId . '/' . '.invoices';
    }

    /**
     * Create pdf invoice based on parsed template
     *
     * @param string $parsedTemplate
     * @param array $invoiceData
     * @return string path to created pdf invoice, empty on error
     */
    public function createInvoicePDF($parsedTemplate, $invoiceData)
    {
        try {
            // Hide notices and warnings
            error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING ^ E_DEPRECATED);

            $booLocal = $this->isCompanyStorageLocationLocal($invoiceData['company_id']);
            $filePath = $this->getPathToInvoices($invoiceData['company_id'], $booLocal) . '/' . 'Invoice #' . $invoiceData['invoice_number'] . '.pdf';

            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $customMarginTop = 10;

            $pdf->setTitle($invoiceData['subject']);
            $pdf->setAuthor($this->_config['site_version']['company_name']);
            $pdf->setFont('helvetica', '', 9);
            $pdf->setMargins(PDF_MARGIN_LEFT, $customMarginTop, PDF_MARGIN_RIGHT);
            $pdf->setAutoPageBreak(true);

            // add a page
            $pdf->AddPage();

            // output the HTML content
            $parsedTemplate = str_replace('> ', '>', $parsedTemplate);
            $parsedTemplate = str_replace("\n\r", '', $parsedTemplate);
            $parsedTemplate = str_replace("\n", '', $parsedTemplate);
            $parsedTemplate = str_replace("\r", '', $parsedTemplate);
            $parsedTemplate = str_replace('border=1', 'border="1"', $parsedTemplate);
            $parsedTemplate = str_replace('cellpadding=5', 'cellpadding="5"', $parsedTemplate);
            $parsedTemplate = str_replace('cellspacing=0', 'cellspacing="0"', $parsedTemplate);
            $parsedTemplate = str_replace('<br>', '<br />', $parsedTemplate);

            // Make sure that html is valid -> otherwise tcpdf can generate fatal errors
            $oPurifier = $this->_settings->getHTMLPurifier(false);
            $pdf->writeHTML($oPurifier->purify($parsedTemplate));

            // reset pointer to the last page
            $pdf->lastPage();

            //Send the document to a given destination: string, local file or browser.
            $tempFileName = tempnam($this->_config['directory']['tmp'], 'inv');
            $pdf->Output($tempFileName, 'F');

            if ($booLocal) {
                $this->_files->createFTPDirectory(dirname($filePath));
                $booSuccess = rename($tempFileName, $filePath);
            } else {
                $booSuccess = $this->_files->getCloud()->uploadFile($tempFileName, $filePath);
                if ($booSuccess) {
                    unlink($tempFileName);
                }
            }

            if (!$booSuccess) {
                $filePath = '';
            }
        } catch (Exception $e) {
            $filePath = '';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $filePath;
    }

    /**
     * Generate and show/output pdf invoice
     *
     * @param int $companyId
     * @param int $invoiceId
     * @return FileInfo|string
     */
    public function showInvoicePdf($companyId, $invoiceId)
    {
        try {
            //get invoice info
            $invoiceInfo = $this->getCompanyInvoice()->getInvoiceDetails($invoiceId, false);

            $invoiceInfo['message'] = '<b>TAX INVOICE</b><br/>' . $invoiceInfo['message'];
            if (!empty($invoiceInfo)) {
                $filePath = $this->createInvoicePDF($invoiceInfo['message'], $invoiceInfo);
                if (!empty($filePath)) {
                    if (!empty($companyId)) {
                        $fileName = sprintf('Invoice #%d.pdf', $invoiceInfo['invoice_number']);
                    } else {
                        $fileName = sprintf('First Invoice #%d (company has not yet created).pdf', $invoiceId);
                    }

                    return new FileInfo($fileName, $filePath, $this->isCompanyStorageLocationLocal($companyId));
                } else {
                    $strError = 'Could not create invoice.';
                }
            } else {
                $strError = 'Internal error';
            }
        } catch (Exception $e) {
            $strError = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return $strError;
    }

    /**
     * Load "last logged in" date/time for specific company
     *
     * @param $companyId
     * @return int
     */
    public function getLastLoggedIn($companyId)
    {
        $select = (new Select())
            ->from('members')
            ->columns(['lastLogin' => new Expression('MAX(lastLogin)')])
            ->where(['company_id' => (int)$companyId]);

        return (int)$this->_db2->fetchOne($select);
    }

    /**
     * Load company fields list
     *
     * @param $companyId
     * @return array
     */
    public function getCompanyLastFields($companyId)
    {
        $select = (new Select())
            ->from(array('c' => 'company'))
            ->columns(array('last_doc_uploaded', 'last_adv_search', 'last_calendar_entry', 'last_task_written', 'last_note_written', 'last_accounting_subtab_updated'))
            ->join(array('u' => 'view_last_mail_used'), 'u.company_id = c.company_id', array('last_manual_check', 'last_mass_mail'), Select::JOIN_LEFT_OUTER)
            ->where(['c.company_id' => (int)$companyId]);

        return $this->_db2->fetchRow($select);
    }

    /**
     * Update specific field in company table
     *
     * @param int $memberId
     * @param string $field
     * @param bool|string|int $value
     */
    public function updateLastField($memberId, $field, $value = false)
    {
        try {
            if ($memberId === false) {
                $memberId = $this->_auth->getCurrentUserId();
            }

            if ($value === false) {
                $value = time();
            }

            $this->_db2->update(
                'company',
                [$field => $value],
                ['company_id' => $this->getMemberCompanyId($memberId)]
            );
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * Toggle access to import for specific company
     *
     * @param int $companyId
     * @param bool $booActivate
     * @param bool $booImportBcpnp
     * @return bool
     */
    public function toggleAccessToImport($companyId, $booActivate, $booImportBcpnp = false)
    {
        try {
            $companyRoles = $this->getCompanyRoles($companyId);

            // Get admin roles
            $role_name        = 'admin';
            $arrSelectedRoles = $arrAllRoles = array();
            if (!empty($companyRoles) && is_array($companyRoles)) {
                foreach ($companyRoles as $role) {
                    $arrAllRoles[] = $role['role_parent_id'];
                    if (preg_match('/^(.*)' . $role_name . '(.*)$/i', $role['role_name'])) {
                        $arrSelectedRoles[] = $role['role_parent_id'];
                    }
                }
            }

            if (count($arrSelectedRoles)) {
                // Load all rule ids we want to enable/disable
                $arrRuleIds = $this->_roles->getAllowImportRules($booImportBcpnp);

                if ($booActivate) {
                    foreach ($arrSelectedRoles as $strRoleId) {
                        foreach ($arrRuleIds as $ruleId) {
                            $this->_db2->insert(
                                'acl_role_access',
                                [
                                    'role_id' => $strRoleId,
                                    'rule_id' => $ruleId
                                ],
                                null,
                                false
                            );
                        }
                    }
                } else {
                    $arrToDelete = array();
                    foreach ($arrAllRoles as $strRoleId) {
                        foreach ($arrRuleIds as $ruleId) {
                            $arrToDelete[] = (new Where())
                                ->nest()
                                ->equalTo('role_id', $strRoleId)
                                ->and
                                ->equalTo('rule_id', $ruleId)
                                ->unnest();
                        }
                    }

                    if (count($arrToDelete)) {
                        $this->_db2->delete(
                            'acl_role_access',
                            [(new Where())->nest()->addPredicates($arrToDelete, Where::OP_OR)->unnest()]
                        );
                    }
                }

                $this->_cache->removeItem('acl_role_access' . $companyId);
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     *  Toggle access to export for specific company
     *
     * @param int $companyId
     * @param bool $booActivate
     * @return bool
     */
    public function toggleAccessToExport($companyId, $booActivate)
    {
        try {
            $companyRoles = $this->getCompanyRoles($companyId);

            // Get admin roles
            $role_name        = 'admin';
            $arrSelectedRoles = $arrAllRoles = array();
            if (!empty($companyRoles) && is_array($companyRoles)) {
                foreach ($companyRoles as $role) {
                    $arrAllRoles[] = $role['role_parent_id'];
                    if (preg_match('/^(.*)' . $role_name . '(.*)$/i', $role['role_name'])) {
                        $arrSelectedRoles[] = $role['role_parent_id'];
                    }
                }
            }

            if (count($arrSelectedRoles)) {
                // Load all rule ids we want to enable/disable
                $arrRuleIds = $this->_roles->getAllowExportRules();

                if ($booActivate) {
                    foreach ($arrSelectedRoles as $strRoleId) {
                        foreach ($arrRuleIds as $ruleId) {
                            $this->_db2->insert(
                                'acl_role_access',
                                [
                                    'role_id' => $strRoleId,
                                    'rule_id' => $ruleId
                                ],
                                null,
                                false
                            );
                        }
                    }
                } else {
                    $arrToDelete = array();
                    foreach ($arrAllRoles as $strRoleId) {
                        foreach ($arrRuleIds as $ruleId) {
                            $arrToDelete[] = (new Where())
                                ->nest()
                                ->equalTo('role_id', $strRoleId)
                                ->and
                                ->equalTo('rule_id', $ruleId)
                                ->unnest();
                        }
                    }

                    if (count($arrToDelete)) {
                        $this->_db2->delete(
                            'acl_role_access',
                            [
                                (new Where())->addPredicates($arrToDelete, Where::OP_OR)
                            ]
                        );
                    }
                }

                $this->_cache->removeItem('acl_role_access' . $companyId);
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Toggle access to web builder for specific company
     *
     * @param int $companyId
     * @param bool $booActivate
     * @return bool
     */
    public function updateWebBuilder($companyId, $booActivate)
    {
        $booSuccess = false;

        try {
            // get admin role
            $adminRole    = '';
            $companyRoles = $this->getCompanyRoles($companyId);
            if (!empty($companyRoles) && is_array($companyRoles)) {
                foreach ($companyRoles as $role) {
                    if ($role['role_type'] == 'admin') {
                        $adminRole = $role['role_parent_id'];
                        break;
                    }
                }
            }

            if (!empty($adminRole)) {
                if ($booActivate) {
                    $this->_db2->insert(
                        'acl_role_access',
                        [
                            'role_id' => $adminRole,
                            'rule_id' => 1400
                        ],
                        null,
                        false
                    );
                } else {
                    $this->_db2->delete(
                        'acl_role_access',
                        [
                            'role_id' => $adminRole,
                            'rule_id' => 1400
                        ]
                    );
                }

                $this->_cache->removeItem('acl_role_access' . $companyId);

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Toggle access to time tracker for specific company
     *
     * @param int $companyId
     * @param bool $booActivate
     * @param bool $booSuperAdmin
     * @return bool
     */
    public function updateTimeTracker($companyId, $booActivate, $booSuperAdmin = false)
    {
        return $this->_roles->toggleModuleAccess($companyId, $booActivate, $booSuperAdmin, 'time-tracker');
    }

    /**
     * Load default labels list which can be used in company settings.
     * Note: When new changes will be applied to the result array - also please check getDefaultLabel method maybe it must be updated too
     * @param $kind
     * @return array
     */
    public function getDefaultLabelsList($kind)
    {
        switch ($kind) {
            case 'trust_account':
                $arrResult = array(
                    'client_account'          => $this->_tr->translate('Client Account'),
                    'trust_account'           => $this->_tr->translate('Trust Account'),
                    'client_or_trust_account' => $this->_tr->translate('Client/Trust Account')
                );
                break;

            case 'office':
                $arrResult = array(
                    'agent_office' => $this->_tr->translate("Agent's Office"),
                    'group'        => $this->_tr->translate('Group'),
                    'market'       => $this->_tr->translate('Market'),
                    'office'       => $this->_tr->translate('Office'),
                    'our_office'   => $this->_tr->translate('Our Office'),
                    'queue'        => $this->_tr->translate('Queue'),
                    'team'         => $this->_tr->translate('Team'),
                );
                break;

            case 'categories':
                $arrResult = array(
                    'category' => $this->_tr->translate('Category'),
                    'subclass' => $this->_tr->translate('Subclass'),
                );
                break;

            case 'case_type':
                $arrResult = array(
                    'case_type'           => $this->_tr->translate('Case Type'),
                    'immigration_program' => $this->_tr->translate('Immigration Program'),
                );
                break;

            case 'case_status':
                $arrResult = array(
                    'case_status' => $this->_tr->translate('Case Status'),
                );
                break;

            case 'rma':
                $arrResult = array(
                    'rma'                       => $this->_tr->translate('RMA responsible for the Case'),
                    'authorized_representative' => $this->_tr->translate('The Authorized Representative'),
                );
                break;

            case 'rma_number':
                $arrResult = array(
                    'rma_number'         => $this->_tr->translate('Registration Number'),
                    'law_society_number' => $this->_tr->translate('RCIC Number or Provincial Law Society Number'),
                );
                break;

            case 'first_name':
                $arrResult = array(
                    'first_name'  => $this->_tr->translate('First Name'),
                    'given_names' => $this->_tr->translate('Given Names'),
                );
                break;

            case 'last_name':
                $arrResult = array(
                    'last_name'   => $this->_tr->translate('Last Name'),
                    'family_name' => $this->_tr->translate('Family Name'),
                );
                break;

            default:
                $arrResult = array();
                break;
        }

        return $arrResult;
    }

    /**
     * Get default label in relation to the website version
     * @param $kind
     * @return string
     */
    public function getDefaultLabel($kind)
    {
        switch ($kind) {
            case 'trust_account':
                $defaultLabel = 'client_account';
                break;

            case 'office':
                if ($this->_config['site_version']['version'] == 'australia') {
                    $defaultLabel = 'agent_office';
                } else {
                    $defaultLabel = 'office';
                }
                break;

            case 'categories':
                $defaultLabel = $this->_config['site_version']['categories_field_default_label'];
                $defaultLabel = in_array($defaultLabel, ['subclass', 'category']) ? $defaultLabel : 'category';
                break;

            case 'case_type':
                $defaultLabel = $this->_config['site_version']['case_type_field_default_label'];
                $defaultLabel = in_array($defaultLabel, ['immigration_program', 'case_type']) ? $defaultLabel : 'case_type';
                break;

            case 'case_status':
                $defaultLabel = 'case_status';
                break;

            case 'rma':
                if ($this->_config['site_version']['version'] == 'australia') {
                    $defaultLabel = 'rma';
                } else {
                    $defaultLabel = 'authorized_representative';
                }
                break;

            case 'rma_number':
                if ($this->_config['site_version']['version'] == 'australia') {
                    $defaultLabel = 'registration_number';
                } else {
                    $defaultLabel = 'law_society_number';
                }
                break;

            case 'first_name':
                if ($this->_config['site_version']['version'] == 'australia') {
                    $defaultLabel = 'given_names';
                } else {
                    $defaultLabel = 'first_name';
                }
                break;

            case 'last_name':
                if ($this->_config['site_version']['version'] == 'australia') {
                    $defaultLabel = 'family_name';
                } else {
                    $defaultLabel = 'last_name';
                }
                break;

            default:
                $defaultLabel = '';
                break;
        }

        return $defaultLabel;
    }

    /**
     * Load company's specific field's label
     *
     * @param int $companyId
     * @param string $kind office OR trust_account
     * @return string
     */
    public function getCompanyDefaultLabel($companyId, $kind)
    {
        $defaultLabel = '';

        $arrCompanyDetails = $this->getCompanyDetailsInfo($companyId);
        if (isset($arrCompanyDetails['default_label_office'])) {
            switch ($kind) {
                case 'office':
                    $defaultLabelId = $arrCompanyDetails['default_label_office'];
                    break;

                case 'trust_account':
                    $defaultLabelId = $arrCompanyDetails['default_label_trust_account'];
                    break;

                default:
                    $defaultLabelId = '';
                    break;
            }
            $defaultLabelId = empty($defaultLabelId) ? $this->getDefaultLabel($kind) : $defaultLabelId;

            $arrOptions = $this->getDefaultLabelsList($kind);
            if (array_key_exists($defaultLabelId, $arrOptions)) {
                $defaultLabel = $arrOptions[$defaultLabelId];
            }
        }

        return $defaultLabel;
    }

    /**
     * Get current company default label
     * @param string $kind
     * @param bool $plural Whether company default label is needed in plural format
     * @return string
     */
    public function getCurrentCompanyDefaultLabel($kind, $plural = false)
    {
        $defaultLabel = '';

        if ($this->_auth->hasIdentity()) {
            $identity = $this->_auth->getIdentity();
            switch ($kind) {
                case 'office':
                    $defaultLabelId = $identity->default_label_office ?? '';
                    break;

                case 'trust_account':
                    $defaultLabelId = $identity->default_label_trust_account ?? '';
                    break;

                default:
                    $defaultLabelId = '';
                    break;
            }

            $defaultLabelId = empty($defaultLabelId) ? $this->getDefaultLabel($kind) : $defaultLabelId;

            $arrOptions = $this->getDefaultLabelsList($kind);
            if (array_key_exists($defaultLabelId, $arrOptions)) {
                $defaultLabel = $arrOptions[$defaultLabelId];
            }
        } else {
            $defaultLabel = $this->getCompanyDefaultLabel($this->getDefaultCompanyId(), $kind);
        }

        if ($plural) {
            $defaultLabel = $this->_settings->pluralizeWord($defaultLabel);
        }

        return $defaultLabel;
    }

    /**
     * Create array with data for creating notes about company changes
     * @param $arrInsertCompany
     * @param $table
     * @param $companyId
     * @param int $memberId
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */

    public function createArrChangesData($arrInsertCompany, $table, $companyId, $memberId = 0)
    {
        $arrChangesData   = array();
        $changesDataIndex = 0;
        $username         = '';

        /** @var Members $members */
        $members = $this->_serviceContainer->get(Members::class);

        if ($memberId != 0) {
            $select = (new Select())
                ->from('members')
                ->columns(['username'])
                ->where(['member_id' => $memberId]);

            $username = $this->_db2->fetchOne($select);
        }

        if ($table == 'members_vevo_mapping') {
            /** @var MembersVevo $membersVevo */
            $membersVevo = $this->_serviceContainer->get(MembersVevo::class);

            $arrOldMembersToVevoMapping = $membersVevo->getMembersToVevoMappingList($memberId, false);
            $strOldMembersVevoValue     = '';
            $strNewMembersVevoValue     = '';

            foreach ($arrOldMembersToVevoMapping as $memberToVevo) {
                $strOldMembersVevoValue .= $memberToVevo['option_name'] . ($memberToVevo !== end($arrOldMembersToVevoMapping) ? ', ' : '');
            }

            foreach ($arrInsertCompany as $toMemberId) {
                $toMemberInfo           = $members->getMemberInfo($toMemberId);
                $strNewMembersVevoValue .= $toMemberInfo['full_name'] . ($toMemberId !== end($arrInsertCompany) ? ', ' : '');
            }

            $arrOldValues     = array('vevo users' => $strOldMembersVevoValue);
            $arrInsertCompany = array('vevo users' => $strNewMembersVevoValue);
        } else {
            if ($memberId != 0) {
                $select = (new Select())
                    ->from($table)
                    ->where(['member_id' => $memberId]);
            } else {
                $select = (new Select())
                    ->from($table)
                    ->where(['company_id' => $companyId]);
            }

            $arrOldValues = $this->_db2->fetchRow($select);
        }

        foreach ($arrInsertCompany as $key => $val) {
            if ($val instanceof Expression) {
                $expr = $val->getExpression();
                if ($expr == 'NULL') {
                    $val = '';
                }
            }
            $old_value = $arrOldValues[$key];
            if ($old_value != $val) {
                $arrChangesData[$changesDataIndex]['log_company_id']        = $companyId;
                $arrChangesData[$changesDataIndex]['log_changed_table']     = $table;
                $arrChangesData[$changesDataIndex]['log_changed_column']    = $key;
                $arrChangesData[$changesDataIndex]['log_column_old_value']  = $old_value;
                $arrChangesData[$changesDataIndex]['log_column_new_value']  = $val;
                $arrChangesData[$changesDataIndex]['log_company_member_id'] = $memberId;
                $arrChangesData[$changesDataIndex]['log_username']          = $username;
                $changesDataIndex++;
            }
        }

        return $arrChangesData;
    }

    /**
     * Load list of companies, filter by incoming params
     *
     * @param string $strQuery
     * @param array $advancedSearchParams
     * @param int $start
     * @param int $limit
     * @param bool $booShowLastLoginColumn
     * @param string $dir
     * @param string $sort
     * @return array
     */
    public function getCompanies($strQuery, $advancedSearchParams, $start, $limit, $booShowLastLoginColumn, $dir, $sort)
    {
        try {
            $arrCompanies       = array();
            $arrAllCompaniesIds = array();

            $dir = strtoupper($dir);
            if ($dir != 'ASC') {
                $dir = 'DESC';
            }

            switch ($sort) {
                case 'company_status':
                    $sort = 'c.Status';
                    break;

                case 'company_name':
                    $sort = 'c.companyName';
                    break;

                case 'company_country':
                    $sort = 'country.countries_name';
                    break;

                case 'company_phone':
                    $sort = 'c.phone1';
                    break;

                case 'company_add_date':
                    $sort = 'c.regTime';
                    break;

                case 'company_trial':
                    $sort = 'cd.trial';
                    break;

                case 'company_next_billing_date':
                    $sort = 'cd.next_billing_date';
                    break;

                case 'company_last_logged_in':
                    $booShowLastLoginColumn = true;

                    $sort = 'lastLoggedIn';
                    break;

                case 'storage_today':
                    $sort = 'c.storage_today';
                    break;

                case 'storage_diff':
                    $sort = 'storage_diff';
                    break;

                case 'company_id':
                default:
                    $sort = 'c.company_id';
                    break;
            }

            $select = (new Select())
                ->from(array('c' => 'company'))
                ->columns(array('storage_diff' => new Expression('IF(c.storage_today < c.storage_yesterday, 0, c.storage_today - c.storage_yesterday)'), Select::SQL_STAR))
                ->join(array('cd' => 'company_details'), 'cd.company_id = c.company_id', array('trial', 'next_billing_date'), Select::JOIN_LEFT_OUTER)
                ->join(array('me' => 'members'), 'c.admin_id = me.member_id', array('admin_fName' => 'fName', 'admin_lName' => 'lName'), Select::JOIN_LEFT_OUTER)
                ->join(array('country' => 'country_master'), 'c.country = country.countries_id', 'countries_name', Select::JOIN_LEFT_OUTER)
                ->where([(new Where())->notEqualTo('c.company_id', $this->getDefaultCompanyId())])
                ->group('c.company_id')
                ->order($sort . ' ' . $dir);

            // Advanced search
            if (is_array($advancedSearchParams) && count($advancedSearchParams)) {
                // By default we don't join extra views/tables
                $booJoinTrialTable         = false;
                $booJoinLastTAUploadedView = false;
                $booJoinLastMailUsedView   = false;
                $booJoinActiveUsersView    = false;
                $booJoinClientsCountView   = false;

                $booSearchNull = true;

                $filter = new StripTags();

                $advancedSearchQuery = (new Where())->nest();
                for ($i = 1; $i <= $advancedSearchParams['max_rows_count']; $i++) {
                    if (!isset($advancedSearchParams['operator_' . $i])) {
                        continue;
                    }

                    $fieldText = $filter->filter(trim($advancedSearchParams['text_' . $i] ?? ''));

                    $fieldDateFrom = current(explode('T', $advancedSearchParams['date_from_' . $i] ?? ''));
                    $fieldDateTo   = current(explode('T', $advancedSearchParams['date_to_' . $i] ?? ''));

                    $fieldName   = $advancedSearchParams['field_' . $i];
                    $fieldFilter = $advancedSearchParams['filter_' . $i] ?? '';
                    $fieldType   = $advancedSearchParams['field_type_' . $i];

                    // Find field
                    switch ($fieldName) {
                        // Main company info
                        case 'company_status':
                            $strDatabaseField = 'c.Status';
                            break;

                        case 'company_purged':
                            $strDatabaseField = 'cd.purged';
                            break;

                        case 'company_name':
                            $strDatabaseField = 'c.companyName';
                            break;

                        case 'company_address':
                            $strDatabaseField = 'c.address';
                            break;

                        case 'company_city':
                            $strDatabaseField = 'c.city';
                            break;

                        case 'company_country':
                            $strDatabaseField = 'country.countries_name';
                            break;

                        case 'company_state':
                            $strDatabaseField = 'c.state';
                            break;

                        case 'company_zip':
                            $strDatabaseField = 'c.zip';
                            break;

                        case 'company_phone1':
                            $strDatabaseField = 'c.phone1';
                            break;

                        case 'company_phone2':
                            $strDatabaseField = 'c.phone2';
                            break;

                        case 'company_email':
                            $strDatabaseField = 'c.companyEmail';
                            break;

                        case 'company_fax':
                            $strDatabaseField = 'c.fax';
                            break;

                        case 'company_note':
                            $strDatabaseField = 'c.note';
                            break;

                        case 'company_freetrial_key':
                            $strDatabaseField  = 't.key';
                            $booJoinTrialTable = true;
                            break;

                        case 'company_last_logged_in':
                            $booShowLastLoginColumn = true;
                            $strDatabaseField       = 'MAX(m.lastLogin)';
                            break;


                        // Additional Company Settings
                        case 'company_account_created_on':
                            $strDatabaseField = 'cd.account_created_on';
                            break;

                        case 'company_setup_on':
                            $strDatabaseField = 'c.regTime';
                            break;

                        case 'company_subscription':
                            $strDatabaseField = 'cd.subscription';
                            break;

                        case 'company_account_trial':
                            $strDatabaseField = 'cd.trial';
                            break;

                        case 'company_next_billing_date':
                            $strDatabaseField = 'cd.next_billing_date';
                            break;

                        case 'company_billing_frequency':
                            $strDatabaseField = 'cd.payment_term';
                            break;

                        case 'company_free_users_included':
                            $strDatabaseField = 'cd.free_users';
                            break;

                        case 'company_free_clients_included':
                            $strDatabaseField = 'cd.free_clients';
                            break;

                        case 'company_free_storage_included':
                            $strDatabaseField = 'cd.free_storage';
                            break;

                        case 'company_active_users':
                            $strDatabaseField       = 'view_active_users.active_users_count';
                            $booJoinActiveUsersView = true;
                            $booSearchNull          = false;
                            break;

                        case 'company_clients_count':
                            $strDatabaseField        = 'view_clients_count.clients_count';
                            $booJoinClientsCountView = true;
                            $booSearchNull           = false;
                            break;

                        case 'company_subscription_fee':
                            $strDatabaseField = 'cd.subscription_fee';
                            break;

                        case 'company_pt_id':
                            $strDatabaseField = 'cd.paymentech_profile_id';
                            break;

                        case 'company_internal_note':
                            $strDatabaseField = 'cd.internal_note';
                            break;

                        case 'company_trust_account_uploaded':
                            $strDatabaseField          = 'last_ta_uploaded.last_ta_uploaded_date';
                            $booJoinLastTAUploadedView = true;
                            break;

                        case 'company_accounting_updated':
                            $strDatabaseField = 'c.last_accounting_subtab_updated';
                            break;

                        case 'company_notes_written':
                            $strDatabaseField = 'c.last_note_written';
                            break;

                        case 'company_task_written':
                            $strDatabaseField = 'c.last_task_written';
                            break;

                        case 'company_check_email_pressed':
                            $strDatabaseField        = 'last_mail_used.last_manual_check';
                            $booJoinLastMailUsedView = true;
                            break;

                        case 'company_adv_search':
                            $strDatabaseField = 'c.last_adv_search';
                            break;

                        case 'company_mass_email':
                            $strDatabaseField        = 'last_mail_used.last_mass_mail';
                            $booJoinLastMailUsedView = true;
                            break;

                        case 'company_document_uploaded':
                            $strDatabaseField = 'c.last_doc_uploaded';
                            break;

                        default:
                            $strDatabaseField = '';
                            break;
                    }

                    if (!empty($strDatabaseField)) {
                        switch ($fieldType) {
                            case 'text':
                                switch ($fieldFilter) {
                                    case 'contains':
                                        $condition = (new Where())->like("$strDatabaseField", "%" . $fieldText . "%");
                                        break;

                                    case 'does_not_contain':
                                        $condition = (new Where())->notLike("$strDatabaseField", "%" . $fieldText . "%");
                                        break;

                                    case 'is':
                                        $condition = (new Where())->equalTo("$strDatabaseField", $fieldText);
                                        break;

                                    case 'is_not':
                                        $condition = (new Where())->notEqualTo("$strDatabaseField", $fieldText);
                                        break;

                                    case 'starts_with':
                                        $condition = (new Where())->like("$strDatabaseField", $fieldText . '%');
                                        break;

                                    case 'ends_with':
                                        $condition = (new Where())->like("$strDatabaseField", '%' . $fieldText);
                                        break;

                                    case 'is_empty':
                                        $condition = (new Where())->equalTo("$strDatabaseField", '');
                                        break;

                                    case 'is_not_empty':
                                        $condition = (new Where())->notEqualTo("$strDatabaseField", '');
                                        break;

                                    default:
                                        break;
                                }
                                break;

                            case 'float':
                            case 'number':
                                if ($fieldType == 'float') {
                                    // Make sure that it is a float
                                    $fieldText = sprintf('%F', $fieldText);
                                } else {
                                    // Make sure it is an int
                                    $fieldText = (string)intval($fieldText);
                                }

                                $subCondition = false;
                                switch ($fieldFilter) {
                                    case 'equal':
                                        $subCondition = (new Where())->equalTo($strDatabaseField, $fieldText);
                                        break;

                                    case 'not_equal':
                                        $subCondition = (new Where())->notEqualTo($strDatabaseField, $fieldText);
                                        break;

                                    case 'less':
                                        $subCondition = (new Where())->lessThan($strDatabaseField, $fieldText);
                                        break;

                                    case 'less_or_equal':
                                        $subCondition = (new Where())->lessThanOrEqualTo($strDatabaseField, $fieldText);
                                        break;

                                    case 'more':
                                        $subCondition = (new Where())->greaterThan($strDatabaseField, $fieldText);
                                        break;

                                    case 'more_or_equal':
                                        $subCondition = (new Where())->greaterThanOrEqualTo($strDatabaseField, $fieldText);
                                        break;

                                    default:
                                        break;
                                }

                                if ($subCondition) {
                                    if (empty($fieldText) && $booSearchNull) {
                                        $condition = (new Where())
                                            ->nest()
                                            ->isNull($strDatabaseField)
                                            ->or
                                            ->addPredicate($subCondition)
                                            ->unnest();
                                    } else {
                                        $condition = $subCondition;
                                    }
                                }
                                break;

                            case 'date': // timestamp
                            case 'short_date': // YYYY-mm-dd
                                $empty     = '000-00-00';
                                $today     = date('Y-m-d');
                                $yearStart = date('Y-01-01');
                                $yearEnd   = date('Y-12-31');

                                $identifierDate = ($fieldType == 'date') ? new Expression("FROM_UNIXTIME($strDatabaseField)") : $strDatabaseField;

                                switch ($fieldFilter) {
                                    case 'is':
                                        $condition = (new Where())->like($identifierDate, $fieldDateFrom . "%");
                                        break;

                                    case 'is_not':
                                        $condition = (new Where())->notLike($identifierDate, $fieldDateFrom . "%");
                                        break;

                                    case 'is_before':
                                        $condition = (new Where())
                                            ->nest()
                                            ->lessThan($identifierDate, $fieldDateFrom)
                                            ->or
                                            ->isNull($strDatabaseField)
                                            ->unnest();
                                        break;

                                    case 'is_after':
                                        $condition = (new Where())->greaterThan($identifierDate, $fieldDateFrom);
                                        break;

                                    case 'is_empty':
                                        $condition = (new Where())
                                            ->nest()
                                            ->equalTo($identifierDate, $empty)
                                            ->or
                                            ->isNull($strDatabaseField)
                                            ->unnest();
                                        break;

                                    case 'is_not_empty':
                                        $condition = (new Where())
                                            ->nest()
                                            ->notEqualTo($identifierDate, $empty)
                                            ->and
                                            ->isNotNull($strDatabaseField)
                                            ->unnest();
                                        break;

                                    case 'is_between_2_dates':
                                        $condition = (new Where())
                                            ->nest()
                                            ->greaterThan($identifierDate, $fieldDateFrom)
                                            ->and
                                            ->lessThan($identifierDate, $fieldDateTo)
                                            ->unnest();
                                        break;

                                    case 'is_between_today_and_date':
                                        $condition = (new Where())
                                            ->nest()
                                            ->greaterThan($identifierDate, $today)
                                            ->and
                                            ->lessThan($identifierDate, $fieldDateFrom)
                                            ->unnest();
                                        break;

                                    case 'is_between_date_and_today':
                                        $condition = (new Where())
                                            ->nest()
                                            ->greaterThan($identifierDate, $fieldDateFrom)
                                            ->and
                                            ->lessThan($identifierDate, $today)
                                            ->unnest();
                                        break;

                                    case 'is_since_start_of_the_year_to_now':
                                        $condition = (new Where())
                                            ->greaterThan($identifierDate, $yearStart)
                                            ->and
                                            ->lessThan($identifierDate, $today);
                                        break;

                                    case 'is_from_today_to_the_end_of_year':
                                        $condition = (new Where())
                                            ->greaterThan($identifierDate, $today)
                                            ->and
                                            ->lessThan($identifierDate, $yearEnd);
                                        break;

                                    case 'is_in_this_month':
                                        $condition = (new Where())
                                            ->like($identifierDate, date('Y-m') . '%');
                                        break;

                                    case 'is_in_this_year':
                                        $condition = (new Where())
                                            ->like($identifierDate, date('Y') . '%');
                                        break;

                                    case 'is_in_next_days':
                                    case 'is_in_next_months':
                                    case 'is_in_next_years':
                                        $exploded_fieldFilter = explode('_', $fieldFilter);
                                        $fieldText            = (int)$fieldText;
                                        $timestamp            = strtotime("+$fieldText " . array_pop($exploded_fieldFilter)); // example: +3 months

                                        $endDate = date('Y-m-d', $timestamp);

                                        $condition = (new Where())
                                            ->nest()
                                            ->greaterThan($identifierDate, $today)
                                            ->and
                                            ->lessThan($identifierDate, $endDate)
                                            ->unnest();
                                        break;

                                    default:
                                        break;
                                }
                                break;

                            case 'yes_no':
                                $condition = (new Where())
                                    ->equalTo($strDatabaseField, strtoupper($fieldFilter) == 'YES' ? 'Y' : 'N');
                                break;

                            case 'company_status':
                            case 'billing_frequency':
                                if (!empty($fieldFilter)) {
                                    $condition = (new Where())
                                        ->equalTo($strDatabaseField, $fieldFilter);
                                } else {
                                    $condition = (new Where())
                                        ->nest()
                                        ->equalTo($strDatabaseField, 0)
                                        ->or
                                        ->isNull($strDatabaseField)
                                        ->unnest();
                                }

                                break;

                            default:
                                // Can't be here...
                                break;
                        }

                        if (!empty($condition)) {
                            if ($fieldName == 'company_last_logged_in') {
                                $select->having($condition);
                            } elseif ($i != 1) {
                                if (strtoupper($advancedSearchParams['operator_' . $i] ?? '') == 'OR') {
                                    $advancedSearchQuery->or->addPredicate($condition);
                                } else {
                                    $advancedSearchQuery->and->addPredicate($condition);
                                }
                            } else {
                                $advancedSearchQuery->addPredicate($condition);
                            }
                        }
                    }
                }

                if ($advancedSearchQuery->count() > 0) {
                    $advancedSearchQuery = $advancedSearchQuery->unnest();
                    $select->where->addPredicate($advancedSearchQuery);
                }

                // Join tables and views only when we need to
                if ($booJoinTrialTable) {
                    $select->join(array('t' => 'company_trial'), 't.company_id = c.company_id', 'key', Select::JOIN_LEFT_OUTER);
                }

                if ($booJoinLastTAUploadedView) {
                    $select->join(
                        array('last_ta_uploaded' => 'view_last_ta_uploaded'),
                        'last_ta_uploaded.company_id = c.company_id',
                        'last_ta_uploaded_date',
                        Select::JOIN_LEFT_OUTER
                    );
                }

                if ($booJoinLastMailUsedView) {
                    $select->join(
                        array('last_mail_used' => 'view_last_mail_used'),
                        'last_mail_used.company_id = c.company_id',
                        array('last_manual_check', 'last_mass_mail'),
                        Select::JOIN_LEFT_OUTER
                    );
                }

                if ($booJoinActiveUsersView) {
                    $select->join(
                        array('view_active_users' => 'view_active_users'),
                        'view_active_users.company_id = c.company_id',
                        'active_users_count',
                        Select::JOIN_LEFT_OUTER
                    );
                }

                if ($booJoinClientsCountView) {
                    $select->join(
                        array('view_clients_count' => 'view_clients_count'),
                        'view_clients_count.company_id = c.company_id',
                        'clients_count',
                        Select::JOIN_LEFT_OUTER
                    );
                }
            }


            if ($booShowLastLoginColumn) {
                $select->join(
                    array('m' => 'members'),
                    'm.company_id = c.company_id',
                    ['lastLoggedIn' => new Expression('MAX(m.lastLogin)')],
                    Select::JOIN_LEFT_OUTER
                );
            }

            if (!empty($strQuery)) {
                // Safe way to use like '%search%'
                $where = (new Where())->nest()
                    ->like('c.companyName', "%$strQuery%")
                    ->or
                    ->like('c.companyEmail', "%$strQuery%")
                    ->or
                    ->like('c.phone1', "%$strQuery%")
                    ->or
                    ->like('c.phone2', "%$strQuery%");

                // Search by users/admins in a separate request to speed up loading
                $select1 = (new Select())
                    ->from(['m' => 'members'])
                    ->columns(['company_id'])
                    ->join(array('u' => 'users'), 'u.member_id = m.member_id', [], Select::JOIN_LEFT_OUTER)
                    ->where([
                        (new Where())->nest()
                            ->like('m.fName', "%$strQuery%")
                            ->or
                            ->like('m.lName', "%$strQuery%")
                            ->or
                            ->like('m.emailAddress', "%$strQuery%")
                            ->or
                            ->like('u.homePhone', "%$strQuery%")
                            ->or
                            ->like('u.workPhone', "%$strQuery%")
                            ->or
                            ->like('u.mobilePhone', "%$strQuery%")
                            ->or
                            ->equalTo('m.member_id', is_numeric($strQuery) ? (int)$strQuery : 0),
                        'm.userType' => Members::getMemberType('admin_and_user')
                    ]);

                $arrFoundMembersCompaniesIds = $this->_db2->fetchCol($select1);

                if (!empty($arrFoundMembersCompaniesIds)) {
                    $where->or->in('c.company_id', Settings::arrayUnique($arrFoundMembersCompaniesIds));
                }

                // Search for member id only if search string is a number
                if (is_numeric($strQuery)) {
                    $where->or->equalTo('c.company_id', (int)$strQuery);
                }

                $where = $where->unnest();
                $select->where([$where]);
            }

            // We need all companies for later using
            $arrAllFilteredCompanies = $this->_db2->fetchAll($select);

            if (count($arrAllFilteredCompanies)) {
                foreach ($arrAllFilteredCompanies as $arrCompanyInfo) {
                    $arrAllCompaniesIds[] = $arrCompanyInfo['company_id'];
                }
                unset($arrCompanyInfo, $arrAllFilteredCompanies);
            }

            // Now search for 'pager' and grid
            $select->limit($limit)->offset($start);
            $arrFoundCompanies = $this->_db2->fetchAll($select);

            if (count($arrFoundCompanies)) {
                $arrCompaniesIds = array();
                foreach ($arrFoundCompanies as $arrCompanyInfo) {
                    $arrCompaniesIds[] = $arrCompanyInfo['company_id'];
                }

                // Collect admin users
                $select = (new Select())
                    ->from(['m' => 'members'])
                    ->columns(['company_id', 'username'])
                    ->where([
                        'company_id' => $arrCompaniesIds,
                        'userType'   => Members::getMemberType('admin')
                    ]);

                $arrAllCompaniesAdmins = $this->_db2->fetchAll($select);

                $arrCompanyAdmins = array();
                foreach ($arrAllCompaniesAdmins as $arrInfo) {
                    $arrCompanyAdmins[$arrInfo['company_id']][] = $arrInfo['username'];
                }

                // Generate result companies list
                foreach ($arrFoundCompanies as $arrCompanyInfo) {
                    $strAdmins = array_key_exists($arrCompanyInfo['company_id'], $arrCompanyAdmins) ?
                        implode(',<br/>', $arrCompanyAdmins[$arrCompanyInfo['company_id']]) : '';

                    $strLoginDate   = empty($arrCompanyInfo['lastLoggedIn']) ? '' : date('Y-m-d H:i:s', $arrCompanyInfo['lastLoggedIn']);
                    $arrCompanies[] = array(
                        'company_id'                => $arrCompanyInfo['company_id'],
                        'company_name'              => $arrCompanyInfo['companyName'],
                        'company_email'             => $arrCompanyInfo['companyEmail'],
                        'company_abn'               => $arrCompanyInfo['company_abn'],
                        'company_admins'            => $strAdmins,
                        'admin_name'                => $arrCompanyInfo['admin_lName'] . ' ' . $arrCompanyInfo['admin_fName'],
                        'company_country'           => $arrCompanyInfo['countries_name'],
                        'company_phone'             => $arrCompanyInfo['phone1'] . '<br/>' . $arrCompanyInfo['phone2'],
                        'company_add_date'          => date('Y-m-d H:i:s', $arrCompanyInfo['regTime']),
                        'company_trial'             => $arrCompanyInfo['trial'],
                        'company_next_billing_date' => $arrCompanyInfo['next_billing_date'],
                        'company_last_logged_in'    => $strLoginDate,
                        'company_status'            => $this->getCompanyStringStatusById($arrCompanyInfo['Status']),
                        'storage_today'             => $arrCompanyInfo['storage_today'] * 1024,
                        'storage_yesterday'         => $arrCompanyInfo['storage_yesterday'],
                        'storage_diff'              => $arrCompanyInfo['storage_diff'],
                    );
                }
            }
        } catch (Exception $e) {
            $arrCompanies       = array();
            $arrAllCompaniesIds = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(count($arrAllCompaniesIds), $arrCompanies, $arrAllCompaniesIds);
    }

    public function getCompanyEmailById($id)
    {
        $companyEmail = '';
        try {
            if (!empty($id) || $id === 0) {
                $select = (new Select())
                    ->from(['c' => 'company'])
                    ->columns(['companyEmail'])
                    ->where(['company_id' => (int)$id]);

                $companyEmail = $this->_db2->fetchOne($select);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $companyEmail;
    }

    /**
     * Provides template replacements based on input data
     *
     * @param array|int $companyDetails
     * @param array $adminInfo
     * @param array $specialData
     * @return array
     */
    public function getTemplateReplacements($companyDetails, $adminInfo = [], $specialData = [])
    {
        $companyDetails  = is_int($companyDetails) ? $this->getCompanyAndDetailsInfo($companyDetails) : $companyDetails;
        $nextBillingDate = $specialData['next_billing_date'] ?? $companyDetails['next_billing_date'];
        $paymentTermId   = $specialData['payment_term'] ?? $companyDetails['payment_term'];

        return [
            '{company ID}'              => $companyDetails['company_id'] ?? '',
            '{company}'                 => $companyDetails['companyName'] ?? '', // can be defined in prospect templates
            '{company name}'            => $companyDetails['companyName'] ?? '',
            '{company ABN}'             => $companyDetails['company_abn'] ?? '',
            '{company_abn}'             => $companyDetails['company_abn'] ?? '', // can be defined in prospect templates
            '{company city}'            => $companyDetails['city'] ?? '',
            '{company province/state}'  => $companyDetails['state'] ?? '',
            '{company country}'         => isset($companyDetails['country']) ? $this->_country->getCountryName($companyDetails['country']) : '',
            '{company address}'         => $companyDetails['address'] ?? '',
            '{company postal code/zip}' => $companyDetails['zip'] ?? '',
            '{company phone 1}'         => $companyDetails['phone1'] ?? '',
            '{company phone 2}'         => $companyDetails['phone2'] ?? '',
            '{company email}'           => $companyDetails['companyEmail'] ?? '',
            '{company fax}'             => $companyDetails['fax'] ?? '',

            '{next billing date}'                => empty($nextBillingDate) ? '-' : $this->_settings->formatDate($nextBillingDate),
            '{next billing date: hide if empty}' => empty($nextBillingDate) ? 'style="display: none;"' : '',
            '{billing frequency}'                => empty($paymentTermId) ? '-' : $this->getCompanySubscriptions()->getPaymentTermNameById($paymentTermId),
            '{company package}'                  => !empty($companyDetails['subscription']) ? $this->getPackages()->getSubscriptionNameById($companyDetails['subscription']) : '',
            '{subscription description}'         => trim($companyDetails['subscription'] ?? ''),
            '{company setup on}'                 => isset($companyDetails['regTime']) ? $this->_settings->formatDate($companyDetails['regTime']) : '',
            '{account created on}'               => isset($companyDetails['account_created_on']) ? $this->_settings->formatDate($companyDetails['account_created_on']) : '-',
            '{maximum users}'                    => $companyDetails['max_users'] ?? '',
            '{internal note}'                    => $companyDetails['internal_note'] ?? '',

            '{admin first name}' => $adminInfo['fName'] ?? '',
            '{admin last name}'  => $adminInfo['lName'] ?? '',
            '{admin username}'   => $adminInfo['username'] ?? '',
            '{admin email}'      => $adminInfo['emailAddress'] ?? '',

            '{error message}' => $specialData['error_message'] ?? '',
        ];
    }

    /**
     * Load company's settings for Client Profile ID
     * If not found - load the default ones
     *
     * @param int $companyId
     * @return array
     */
    public function getCompanyClientProfileIdSettings($companyId)
    {
        $arrClientProfileIdSettings = array();

        $arrClientProfileIdDefaultSettings = array(
            'enabled'    => 0,
            'start_from' => '0001',
            'format'     => '{client_id_sequence}',
        );

        if (!empty($companyId)) {
            $arrCompanyInfo = $this->getCompanyDetailsInfo($companyId);
            if (!empty($arrCompanyInfo['client_profile_id_settings'])) {
                $arrClientProfileIdSettings = Json::decode($arrCompanyInfo['client_profile_id_settings'], Json::TYPE_ARRAY);
            }
        }

        if (empty($arrClientProfileIdSettings)) {
            $arrClientProfileIdSettings = $arrClientProfileIdDefaultSettings;
        } else {
            // Make sure that default values are set
            foreach ($arrClientProfileIdDefaultSettings as $key => $val) {
                if (!isset($arrClientProfileIdSettings[$key])) {
                    $arrClientProfileIdSettings[$key] = $val;
                }
            }
        }

        return $arrClientProfileIdSettings;
    }

    /**
     * Generate Client Profile ID from company's settings and generated Client ID Sequence
     *
     * @param array $arrSavedClientProfileIdSettings
     * @param string|null $clientIdSequence
     * @return array
     */
    public function generateClientProfileIdFromFormat($arrSavedClientProfileIdSettings, $clientIdSequence)
    {
        if (is_null($clientIdSequence)) {
            $clientIdSequence = $arrSavedClientProfileIdSettings['start_from'] ?? '';
            $clientIdSequence = str_pad((string)($clientIdSequence + 1), strlen($clientIdSequence ?? ''), '0', STR_PAD_LEFT);
        }

        return [$clientIdSequence, str_replace('{client_id_sequence}', $clientIdSequence, $arrSavedClientProfileIdSettings['format'])];
    }

    /**
     * Update company's settings (Client ID Sequence) for Client Profile ID generation
     *
     * @param int $companyId
     * @param string $clientIdSequence
     * @return void
     */
    public function updateCompanyClientProfileIdStartFrom($companyId, $clientIdSequence)
    {
        $arrSavedSettings               = $this->getCompanyClientProfileIdSettings($companyId);
        $arrSavedSettings['start_from'] = $clientIdSequence;

        $this->updateCompanyDetails(
            $companyId,
            array(
                'client_profile_id_settings' => Json::encode($arrSavedSettings)
            )
        );
    }

    /**
     * Check if Client Profile ID is enabled in company settings
     *
     * @param int $companyId
     * @return bool true if enabled
     */
    public function isCompanyClientProfileIdEnabled($companyId)
    {
        $arrSavedSettings = $this->getCompanyClientProfileIdSettings($companyId);

        return (bool)$arrSavedSettings['enabled'];
    }

    /**
     * Load company's settings for invoice number generation
     * If not found - load the default ones
     *
     * @param int $companyId
     * @return array
     */
    public function getCompanyInvoiceNumberSettings($companyId)
    {
        $arrInvoiceNumberSettings = array();

        if (!empty($companyId)) {
            $arrCompanyInfo = $this->getCompanyDetailsInfo($companyId);
            if (!empty($arrCompanyInfo['invoice_number_settings'])) {
                $arrInvoiceNumberSettings = Json::decode($arrCompanyInfo['invoice_number_settings'], Json::TYPE_ARRAY);
            }
        }

        if (empty($arrInvoiceNumberSettings)) {
            $arrInvoiceNumberSettings = array(
                'format'     => '{sequence_number}',
                'start_from' => 1
            );
        }

        if (!isset($arrInvoiceNumberSettings['tax_number'])) {
            $arrInvoiceNumberSettings['tax_number'] = '';
        }

        if (!isset($arrInvoiceNumberSettings['disclaimer'])) {
            $arrInvoiceNumberSettings['disclaimer'] = $this->_config['site_version']['invoice_disclaimer_default'];
        }

        return $arrInvoiceNumberSettings;
    }

    /**
     * Generate invoice number from company's settings and provided invoice number
     *
     * @param int $companyId
     * @param int|string $invoiceNumber
     * @return string
     */
    public function generateInvoiceNumberFromFormat($companyId, $invoiceNumber)
    {
        $arrSavedSettings = $this->getCompanyInvoiceNumberSettings($companyId);
        return str_replace('{sequence_number}', $invoiceNumber, $arrSavedSettings['format']);
    }

    /**
     * Update company's settings (invoice number start from) for invoice number generation
     *
     * @param int $companyId
     * @param int|string $invoiceNumber
     */
    public function updateCompanyInvoiceNumberStartFrom($companyId, $invoiceNumber)
    {
        $arrSavedSettings               = $this->getCompanyInvoiceNumberSettings($companyId);
        $arrSavedSettings['start_from'] = intval($invoiceNumber) + 1;

        $this->updateCompanyDetails(
            $companyId,
            array(
                'invoice_number_settings' => Json::encode($arrSavedSettings)
            )
        );
    }

    /**
     * Find company id by the provided hash
     *
     * @param string $hash
     * @return false|int false on fail, company id on success
     */
    public function getCompanyIdByHash($hash)
    {
        $id = false;

        if (!empty($hash)) {
            $allCompaniesIds = $this->getAllCompanies(true);
            foreach ($allCompaniesIds as $companyId) {
                if ($this->generateHashByCompanyId($companyId) === $hash) {
                    $id = $companyId;
                }
            }
        }

        return $id;
    }

    /**
     * Generate string hash (that will be used on the login page) by company id
     *
     * @param int $companyId
     * @return string
     */
    public function generateHashByCompanyId($companyId)
    {
        return md5($companyId . $this->_config['security']['login_hash_key']);
    }

    /**
     * Provides list of fields available for system templates.
     * @param EventInterface $e
     * @return array
     */
    public function getSystemTemplateFields(EventInterface $e)
    {
        // Company info
        $arrCompanyInfoFields = array(
            array('name' => 'company ID', 'label' => 'Company ID'),
            array('name' => 'company name', 'label' => 'Company Name'),
            array('name' => 'company city', 'label' => $this->_settings->getSiteCityLabel()),
            array('name' => 'company province/state', 'label' => 'Province/State'),
            array('name' => 'company country', 'label' => 'Country'),
            array('name' => 'company address', 'label' => 'Address'),
            array('name' => 'company postal code/zip', 'label' => 'Postal Code/Zip'),
            array('name' => 'company phone 1', 'label' => 'Phone #1'),
            array('name' => 'company phone 2', 'label' => 'Phone #2'),
            array('name' => 'company email', 'label' => 'Company Email'),
            array('name' => 'company fax', 'label' => 'Fax'),
            array('name' => 'company package', 'label' => 'Package'),
            array('name' => 'admin first name', 'label' => 'Admin First Name'),
            array('name' => 'admin last name', 'label' => 'Admin Last Name'),
            array('name' => 'admin username', 'label' => 'Admin Username'),
            array('name' => 'admin email', 'label' => 'Admin Email Address')
        );

        foreach ($arrCompanyInfoFields as &$field1) {
            $field1['n']     = 0;
            $field1['group'] = 'Company information';
        }
        unset($field1);

        // Account Details
        $arrAccountDetails = array(
            array('name' => 'subscription description', 'label' => 'Subscription Description'),
            array('name' => 'company setup on', 'label' => 'Company Setup on'),
            array('name' => 'account created on', 'label' => 'Account Created on'),
            array('name' => 'next billing date', 'label' => 'Next Billing Date'),
            array('name' => 'billing frequency', 'label' => 'Billing Frequency'),
            array('name' => 'maximum users', 'label' => 'Maximum No of Users Allowed'),
            array('name' => 'internal note', 'label' => 'Internal Note'),
            array('name' => 'error message', 'label' => 'Error Message')
        );

        foreach ($arrAccountDetails as &$field2) {
            $field2['n']     = 1;
            $field2['group'] = 'Account Details';
        }
        unset($field2);

        return array_merge($arrCompanyInfoFields, $arrAccountDetails);
    }

    /**
     * A helper to output the data and force to flush the output in browser
     *
     * @param string $s
     * @param bool $lb true to add a new line
     * @return void
     */
    private function out($s = '', $lb = true)
    {
        echo $s . ($lb ? PHP_EOL : '');
        flush();
    }

    /**
     * - calculate companies storage usage,
     * - update storage for each company in DB
     * - send email to support
     * - save results to the cron log
     * @return void
     */
    public function calculateCompaniesStorageUsage()
    {
        $timeStart = microtime(true);

        // We try to turn off buffering at all and only respond with the correct data
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_implicit_flush();

        // Required to be sure that gzip will not break our partial output
        // And a user will see all statuses in the status bar
        header("Content-Encoding: none");
        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);

        try {
            if (!FileTools::isCli()) {
                $this->out('<pre>', false);
            }

            $this->out(sprintf('***** Started: %s', date('Y-m-d H:i:s')));
            $this->out('Loading companies list...', false);
            $arrCompanies = $this->getCompaniesWithBillingDateCheck(false);

            $total = count($arrCompanies);
            $this->out(sprintf(' Done. %d companies to process', $total));

            $countProcessedSuccessfully = 0;
            $arrErrorCompanies          = array();
            $arrSkippedCompanies        = array(self::getDefaultCompanyId());

            foreach ($arrCompanies as $i => $arrCompanyInfo) {
                $companyId = $arrCompanyInfo['company_id'];

                $this->out(sprintf('Calculating size for company %d (%d/%d)...', $companyId, ($i + 1), $total), false);

                try {
                    $localDirectorySize = 0;
                    $booCalculateLocal  = $this->_config['site_version']['calculate_local_size'] || $arrCompanyInfo['storage_location'] == 'local';
                    if ($booCalculateLocal) {
                        $companyRSPath      = realpath('data/' . $companyId);
                        $localDirectorySize = intval(FileTools::getFolderSize($companyRSPath) / 1024);
                    }

                    // We save in KB in the database
                    $remoteDirectorySize = intval($this->_files->getCloud()->getFolderSize($companyId) / 1024);
                    $totalSize           = $remoteDirectorySize + $localDirectorySize;

                    if ($booCalculateLocal) {
                        $this->out(sprintf(' (Local: %d, Remote: %d, Total: %d, Previous: %d)  ', $localDirectorySize, $remoteDirectorySize, $totalSize, $arrCompanyInfo['storage_today']), false);
                    } else {
                        $this->out(sprintf(' (Remote: %d, Previous: %d)  ', $remoteDirectorySize, $arrCompanyInfo['storage_today']), false);
                    }

                    if ($totalSize != (int)$arrCompanyInfo['storage_today']) {
                        $this->_db2->update(
                            'company',
                            [
                                'storage_today'     => $totalSize,
                                'storage_yesterday' => (int)$arrCompanyInfo['storage_today'],
                            ],
                            ['company_id' => $companyId]
                        );

                        $this->out(' [UPDATED]');
                    } else {
                        $this->out(' [SKIPPED]');
                        $arrSkippedCompanies[] = $companyId;
                    }

                    $countProcessedSuccessfully++;
                } catch (Exception $e) {
                    $this->out(' [INTERNAL ERROR]');
                    $arrErrorCompanies[] = $companyId;

                    $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'cron_size');
                }
            }

            $this->out('Generating confirmation email...', false);

            $select = (new Select())
                ->from(array('c' => 'company'))
                ->columns(['company_id', 'companyName', 'storage_today', 'storage_diff' => new Expression('IF(c.storage_today < c.storage_yesterday, 0, c.storage_today - c.storage_yesterday)')])
                ->join(array('d' => 'company_details'), 'c.company_id = d.company_id', ['trial'], Select::JOIN_LEFT)
                ->join(array('m' => 'members'), 'c.admin_id = m.member_id', ['fName', 'lName'], Select::JOIN_LEFT)
                ->where(
                    [
                        (new Where())->notIn('c.company_id', $arrSkippedCompanies),
                    ]
                )
                ->order(['storage_diff DESC', 'c.companyName ASC'])
                ->limit(20);

            $result = $this->_db2->fetchAll($select);

            $table = '<table border="1" cellpadding="4">';
            $table .= '<tr>';
            $table .= '<th>Id</th>';
            $table .= '<th>Company Name</th>';
            $table .= '<th>Company Admin</th>';
            $table .= '<th>Storage</th>';
            $table .= '<th>Storage used since previous calculation</th>';
            $table .= '</tr>';

            $noWrap = 'style="white-space: nowrap;"';
            foreach ($result as $c) {
                $booSkipped   = in_array($c['company_id'], $arrErrorCompanies);
                $booHighlight = $booSkipped || $c['trial'] == 'Y';

                $companyName = $c['companyName'];
                if ($booSkipped) {
                    $companyName .= ' [SKIPPED, ERROR]';
                }

                $table .= '<tr style="color:' . ($booHighlight ? 'red' : 'black') . ';">' . PHP_EOL;
                $table .= '<td>' . $c['company_id'] . '</td>' . PHP_EOL;
                $table .= '<td>' . $companyName . '</td>' . PHP_EOL;
                $table .= '<td>' . $c['lName'] . ' ' . $c['fName'] . '</td>' . PHP_EOL;
                $table .= "<td $noWrap>" . ((int)$c['storage_today'] > 0 ? $this->_files->formatFileSize($c['storage_today'] * 1024) : '-') . '</td>' . PHP_EOL;
                $table .= "<td $noWrap>" . ((int)$c['storage_diff'] > 0 ? $this->_files->formatFileSize($c['storage_diff'] * 1024) : '-') . '</td>' . PHP_EOL;
                $table .= '</tr>' . PHP_EOL;
            }

            $table .= '</table>';

            $table .= '<div style="padding-top: 10px; font-style: italic;">
                                 <span style="font-weight: bold">Note:</span>
                                 Company in red color - means that company is in Trial mode or script failed to process.
                               </div>';

            $table .= '<div style="padding-top: 10px; font-style: italic;">
                                 <span style="font-weight: bold">Script worked:</span>
                                 ' . DateTimeTools::convertSecondsToHumanReadable(microtime(true) - $timeStart) . '
                               </div>';

            // Set receiver(s)
            $arrEmailSettings = $this->_settings->getOfficioSupportEmail();

            $oAddressList = new AddressList();
            $oAddressList->add($arrEmailSettings['email'], $arrEmailSettings['label']);

            $arrAdditionalEmails = explode(',', $this->_config['settings']['send_fatal_errors_to'] ?? '');
            foreach ($arrAdditionalEmails as $email) {
                $oAddressList->add(trim($email));
            }

            $subject = $this->_config['site_version']['name'] . ': Storage used since previous calculation';
            if (!$this->_mailer->sendEmailToSupport($subject, $table, $oAddressList, null, null, null, false)) {
                $this->out(' ERROR: Email was not sent.');
            } else {
                $this->out(' Sent.');
            }

            if (count($arrErrorCompanies)) {
                $this->out(sprintf('***** Processed with errors: %d companies (ids: %s).', count($arrErrorCompanies), implode(', ', $arrErrorCompanies)) . PHP_EOL);
            }

            $this->out(sprintf('***** Finished: %s, worked: %s', date('Y-m-d H:i:s'), DateTimeTools::convertSecondsToHumanReadable(microtime(true) - $timeStart)) . PHP_EOL);

            // Save to log file
            $strResult = PHP_EOL . str_repeat('*', 80) . PHP_EOL
                . sprintf('***** Processed successfully: %d companies.', $countProcessedSuccessfully) . PHP_EOL
                . (count($arrErrorCompanies) ? sprintf('***** Processed with errors: %d companies (ids: %s).', count($arrErrorCompanies), implode(', ', $arrErrorCompanies)) . PHP_EOL : '')
                . sprintf('***** Finished: %s, worked: %s', date('Y-m-d H:i:s'), DateTimeTools::convertSecondsToHumanReadable(microtime(true) - $timeStart)) . PHP_EOL
                . str_repeat('*', 80) . PHP_EOL;

            $this->_log->debugToFile($strResult, 1, 2, 'cron_size_info.log');
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'cron_size');
        }
    }
}
