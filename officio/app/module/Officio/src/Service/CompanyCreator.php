<?php

namespace Officio\Service;

use Clients\Service\Analytics;
use Clients\Service\Clients;
use Clients\Service\Members;
use Exception;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Officio\Common\Service\BaseService;
use Officio\Comms\Service\Mailer;
use Officio\Templates\Model\SystemTemplate;
use Prospects\Service\Prospects;
use Officio\Templates\SystemTemplates;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class CompanyCreator extends BaseService
{
    /** @var Company */
    protected $_company;

    /** @var Prospects */
    protected $_prospects;

    /** @var Roles */
    protected $_roles;

    /** @var Clients */
    protected $_clients;

    /** @var Members */
    protected $_members;

    /** @var Users */
    protected $_users;

    /** @var SystemTemplates */
    protected $_systemTemplates;

    /** @var Analytics */
    protected $_analytics;

    /** @var Mailer */
    protected $_mailer;

    public function initAdditionalServices(array $services)
    {
        $this->_company         = $services[Company::class];
        $this->_prospects       = $services[Prospects::class];
        $this->_roles           = $services[Roles::class];
        $this->_clients         = $services[Clients::class];
        $this->_members         = $services[Members::class];
        $this->_users           = $services[Users::class];
        $this->_systemTemplates = $services[SystemTemplates::class];
        $this->_analytics       = $services[Analytics::class];
        $this->_mailer          = $services[Mailer::class];
    }

    /**
     * Create a new company from the provided info
     *
     * @param array $arrPostInfo
     * @return string error, empty on success
     */
    public function createCompany($arrPostInfo)
    {
        try {
            // Get data from POST request
            $filter = new StripTags();

            $arrCompanyInfo = array(
                'company_template_id' => 0,
                'companyName'         => $filter->filter($arrPostInfo['companyName']),
                'company_abn'         => $filter->filter($arrPostInfo['company_abn']),
                'companyLogo'         => '',
                'address'             => $filter->filter($arrPostInfo['address']),
                'city'                => $filter->filter($arrPostInfo['city']),
                'state'               => $filter->filter($arrPostInfo['state']),
                'country'             => $filter->filter($arrPostInfo['country']),
                'zip'                 => $filter->filter($arrPostInfo['zip']),
                'phone1'              => $filter->filter($arrPostInfo['phone1']),
                'phone2'              => $filter->filter($arrPostInfo['phone2']),
                'companyEmail'        => $filter->filter($arrPostInfo['companyEmail']),
                'fax'                 => $filter->filter($arrPostInfo['fax']),
                'companyTimeZone'     => $filter->filter($arrPostInfo['companyTimeZone'])
            );

            $prospectId   = array_key_exists('prospectId', $arrPostInfo) ? $arrPostInfo['prospectId'] : 0;
            $arrPackages  = $arrPostInfo['arrPackages'];
            $arrOffices   = $arrPostInfo['arrOffices'];
            $arrUsers     = $arrPostInfo['arrUsers'];
            $arrTA        = $arrPostInfo['arrTa'];
            $arrCMI       = $arrPostInfo['arrCMI'];
            $arrFreeTrial = $arrPostInfo['arrFreeTrial'];

            if (!is_array($arrUsers)) {
                throw new Exception(sprintf('Wrong type `%s` of variable $arrUsers', gettype($arrUsers)));
            }

            $strError = $this->_company->checkCompanyInfo($arrCompanyInfo);

            $prospectInfo = array();
            if (empty($strError) && !empty($prospectId)) {
                $prospectInfo = $this->_prospects->getProspectInfo($prospectId);

                if (is_array($prospectInfo) && count($prospectInfo) && empty($prospectInfo['company_id'])) {
                    // Load selected packages from DB
                    $arrPackages = $this->_company->getPackages()->getPackagesBySubscriptionId($prospectInfo['package_type']);
                } else {
                    $strError = $this->_tr->translate('Incorrect prospect id');
                }
            }

            // Check user's info
            if (empty($strError)) {
                // Check if passed users count is correct
                $booCorrectUsersCount = false;
                $usersCount           = count($arrUsers);
                if ($usersCount > 0) {
                    if (!empty($prospectId)) {
                        if ($usersCount <= ($prospectInfo['free_users'] + $prospectInfo['extra_users'])) {
                            $booCorrectUsersCount = true;
                        }
                    } else {
                        $booCorrectUsersCount = true;
                    }
                }

                if ($booCorrectUsersCount) {
                    $resultCheck   = '';
                    $booIsOneAdmin = false;
                    $count         = 1;

                    $arrDefaultRolesIds = array();

                    $arrDefaultRoles = $this->_roles->getDefaultRoles(true, false);

                    if (is_array($arrDefaultRoles) && !empty($arrDefaultRoles)) {
                        foreach ($arrDefaultRoles as $defaultRoleInfo) {
                            $arrDefaultRolesIds[] = $defaultRoleInfo['role_id'];
                        }
                    }

                    foreach ($arrUsers as &$userInfo) {
                        $userInfo['is_admin'] = false;
                        if (!is_array($userInfo['arrRoles']) || empty($userInfo['arrRoles'])) {
                            $resultCheck = $this->_tr->translate('Incorrectly selected role');
                            break;
                        }

                        // Check if there is one user with selected 'admin role'
                        if (is_array($arrDefaultRoles) && !empty($arrDefaultRoles)) {
                            // Check if received roles are correct
                            $rolesIntersect = array_intersect($userInfo['arrRoles'], $arrDefaultRolesIds);
                            if ($userInfo['arrRoles'] != $rolesIntersect) {
                                $resultCheck = $this->_tr->translate('Incorrectly selected role [2]');
                                break;
                            } else {
                                foreach ($arrDefaultRoles as $defaultRoleInfo) {
                                    if (in_array($defaultRoleInfo['role_id'], $userInfo['arrRoles'])) {
                                        if ($defaultRoleInfo['role_type'] == 'admin') {
                                            $userInfo['is_admin'] = true;
                                            $booIsOneAdmin        = true;
                                            break;
                                        }
                                    }
                                }
                            }
                        }

                        if (!empty($prospectId)) {
                            $userInfo['prospect_id'] = $prospectId;
                        }

                        $resultCheck = $this->_company->checkMemberInfo($userInfo);

                        unset($userInfo['prospect_id']);

                        if (!empty($resultCheck)) {
                            break;
                        }

                        $count++;
                    }
                    unset($userInfo);

                    if (!empty($resultCheck)) {
                        $strError = 'User ' . $count . ': ' . $resultCheck;
                    } elseif (!$booIsOneAdmin) {
                        $strError = $this->_tr->translate('Must be defined at least one admin user');
                    }

                    // Each user must have unique username, not used before
                    if (empty($strError)) {
                        $usersCount = count($arrUsers);
                        for ($index = 0; $index < $usersCount; $index++) {
                            $checkUsername = $arrUsers[$index]['username'];
                            $count         = 0;

                            for ($j = 0; $j < $usersCount; $j++) {
                                if ($checkUsername == $arrUsers[$j]['username']) {
                                    $count++;
                                }
                            }

                            if ($count > 1) {
                                // There are several users with the same username
                                $strError = $this->_tr->translate('Each user must have a unique username.');
                                $strError .= $this->_tr->translate(sprintf('<div>Username %s was used %d times.</div>', $checkUsername, $count));
                                break;
                            }
                        }
                    }
                } else {
                    $strError = $this->_tr->translate('Incorrectly selected user');
                }
            }


            // Check TA info, if it exists
            $booCreateTA = false;
            if (empty($strError)) {
                if (is_array($arrTA) && count($arrTA) > 0) {
                    $i                = 1;
                    $arrCurrencies    = $this->_clients->getAccounting()->getSupportedCurrencies();
                    $arrCurrenciesIds = array_keys($arrCurrencies);
                    $taLabel          = $this->_company->getCurrentCompanyDefaultLabel('trust_account');
                    foreach ($arrTA as $taInfo) {
                        if (empty($taInfo['name'])) {
                            $strError = $this->_tr->translate(sprintf($taLabel . ' %d: Please enter the name', $i));
                            break;
                        }

                        if (!in_array($taInfo['currency'], $arrCurrenciesIds)) {
                            $strError = $this->_tr->translate(sprintf($taLabel . ' %d: Incorrectly selected currency', $i));
                            break;
                        }

                        if (!is_numeric($taInfo['balance'])) {
                            $strError = $this->_tr->translate(sprintf($taLabel . ' %d: Please enter correct balance', $i));
                            break;
                        }
                        $i++;
                    }

                    $booCreateTA = empty($strError);
                }
            }

            $booUpdateCMI = false;
            $oCMI         = $this->_company->getCompanyCMI();
            $cmi_id       = $reg_id = '';
            if (empty($strError)) {
                if (is_array($arrCMI) && count($arrCMI) > 0) {
                    $cmi_id = trim($arrCMI['cmi_id'] ?? '');
                    $reg_id = trim($arrCMI['reg_id'] ?? '');

                    $strError     = $oCMI->checkCMIPairUsed($cmi_id, $reg_id);
                    $booUpdateCMI = empty($strError);
                }
            }

            $booUpdateFreeTrial = false;
            $freetrialKey       = '';
            if (empty($strError)) {
                if (is_array($arrFreeTrial) && count($arrFreeTrial) > 0) {
                    $freetrialKey = trim($arrFreeTrial['freetrial_key'] ?? '');

                    $strError           = $this->_company->getCompanyTrial()->checkKeyCorrect($freetrialKey);
                    $booUpdateFreeTrial = empty($strError);
                }
            }

            // Update offices for: CMI and regular Signup pages
            if (empty($strError) && (!empty($prospectId) || $booUpdateCMI || $booUpdateFreeTrial)) {
                $arrOffices[] = array('officeId' => 1, 'officeName' => 'Main');

                // Set access to this office to all users by default
                foreach ($arrUsers as &$userInfo1) {
                    $userInfo1['arrUserOffices'] = array(1);
                }
                unset($userInfo1);
            }

            if (empty($strError)) {
                // Create company and assign packages to it
                $arrResult = $this->_company->createCompany($arrCompanyInfo, $arrPackages);
                if ($arrResult['error']) {
                    // Company was not created

                    // Roll back changes
                    if (is_numeric($arrResult['company_id'])) {
                        $this->_company->deleteCompany(array($arrResult['company_id']));
                    }

                    $strError = $this->_tr->translate('Can not create a company');
                } else {
                    // Company was created
                    $companyId      = $arrResult['company_id'];
                    $arrMappedRoles = $arrResult['arrCompanyDefaultSettings']['arrMappingRoles'];

                    // Create divisions group
                    $oCompanyDivisions = $this->_company->getCompanyDivisions();
                    $divisionGroupId   = $oCompanyDivisions->createUpdateDivisionsGroup(
                        $companyId,
                        0,
                        array(
                            'division_group_company'   => 'Main',
                            'division_group_is_system' => 'Y'
                        )
                    );

                    // Create offices
                    $arrOfficesIds = array();
                    if (is_array($arrOffices)) {
                        $order = 0;
                        foreach ($arrOffices as $officeInfo) {
                            $arrOfficesIds[$officeInfo['officeId']] = $oCompanyDivisions->createUpdateDivision(
                                $companyId,
                                $divisionGroupId,
                                0,
                                $officeInfo['officeName'],
                                $order
                            );
                        }
                    }

                    // Assign all previously created roles to this default divisions group (if role's group id was set)
                    $arrRoleIds = array();
                    $arrRoles   = $this->_company->getCompanyRoles($companyId);
                    foreach ($arrRoles as $arrRoleInfo) {
                        if (!empty($arrRoleInfo['division_group_id'])) {
                            $arrRoleIds[] = $arrRoleInfo['role_id'];
                        }
                    }
                    if (count($arrRoleIds)) {
                        $this->_roles->updateRoleDetails($arrRoleIds, array('division_group_id' => $divisionGroupId));
                    }

                    // Create users
                    $adminId            = 0;
                    $arrCreatedUsersIds = [];
                    foreach ($arrUsers as $arrInsertInfo) {
                        $booAdmin = $arrInsertInfo['is_admin'];
                        unset($arrInsertInfo['is_admin']);

                        $memberRoleIds = $arrInsertInfo['arrRoles'];
                        unset($arrInsertInfo['arrRoles']);

                        $memberOffices = $arrInsertInfo['arrUserOffices'];
                        unset($arrInsertInfo['arrUserOffices']);

                        // Create user/admin
                        $userType                  = $booAdmin ? 'admin' : 'user';
                        $arrUserTypes              = Members::getMemberType($userType);
                        $arrInsertInfo['userType'] = $arrUserTypes[0];

                        $arrInsertInfo['company_id'] = $companyId;

                        $arrUserInfo = array();
                        if ($booAdmin) {
                            $arrUserInfo['user_is_rma'] = 'Y';
                        }

                        $userCreationResult = $this->_users->createUser($arrInsertInfo, $arrCompanyInfo['companyTimeZone'], $arrUserInfo);

                        if ($userCreationResult['error'] || empty($userCreationResult['member_id'])) {
                            $this->_company->deleteCompany(array($companyId));

                            $strError = $this->_tr->translate('Cannot create a user');
                            break;
                        }

                        $memberId = (int)$userCreationResult['member_id'];

                        $arrCreatedUsersIds[] = $memberId;

                        // Assign new created roles to this member
                        $arrMemberRoleIds = array();
                        foreach ($memberRoleIds as $defaultRoleId) {
                            $arrMemberRoleIds[] = $arrMappedRoles[$defaultRoleId];
                        }
                        $this->_company->addMemberRoles($memberId, array('arrRoleIds' => $arrMemberRoleIds));


                        // Make first user admin as company admin
                        if ($booAdmin && empty($adminId) && $memberId) {
                            $adminId = $memberId;
                            $this->_company->updateCompanyAdmin($companyId, $adminId);
                        }


                        // Create offices for the member
                        if (is_array($arrOfficesIds) && !empty($arrOfficesIds)) {
                            $arrMemberOffices = array();

                            if (!$booAdmin) {
                                foreach ($memberOffices as $officeId) {
                                    if (array_key_exists($officeId, $arrOfficesIds)) {
                                        $arrMemberOffices[] = $arrOfficesIds[$officeId];
                                    }
                                }
                            } else {
                                // If this is the admin - automatically assign to ALL offices
                                $arrMemberOffices = array_values($arrOfficesIds);
                            }

                            if (!empty($arrMemberOffices)) {
                                $this->_company->getCompanyDivisions()->addMemberDivision($memberId, $arrMemberOffices);
                            }
                        }

                        // Send email to user
                        if ($memberId) {
                            $companyInfo = $this->_company->getCompanyAndDetailsInfo($companyId);
                            $adminInfo   = empty($adminId) ? [] : $this->_clients->getMemberInfo($adminId);

                            $template          = SystemTemplate::loadOne(['title' => 'New user notification on Company Creation']);
                            $replacements      = $this->_clients->getTemplateReplacements($memberId);
                            $replacements      += $this->_company->getTemplateReplacements($companyInfo, $adminInfo);
                            $processedTemplate = $this->_systemTemplates->processTemplate($template, $replacements, ['to', 'subject', 'template']);
                            $this->_systemTemplates->sendTemplate($processedTemplate);
                        }
                    }

                    if (empty($strError) && empty($arrCreatedUsersIds)) {
                        // Cannot be here
                        $strError = $this->_tr->translate('Incorrectly selected user');
                    }

                    // When all users and admins were created - create default company sections
                    if (empty($strError)) {
                        $this->_company->createDefaultCompanySections(null, $companyId, $adminId, $arrCreatedUsersIds, $arrResult['arrCompanyDefaultSettings'], $arrPackages);
                    }

                    // Add Client Account
                    if (empty($strError) && $booCreateTA) {
                        foreach ($arrTA as $taInfo) {
                            $arrTAInfo = array(
                                'company_id'               => (int)$companyId,
                                'name'                     => addslashes($taInfo['name'] ?? ''),
                                'currency'                 => $taInfo['currency'],
                                'view_transactions_months' => 5,
                                'balance'                  => (float)$taInfo['balance'],
                                'create_date'              => date('Y-m-d'),
                                'last_reconcile'           => '0000-00-00',
                                'last_reconcile_iccrc'     => '0000-00-00',
                                'status'                   => 1
                            );

                            $companyTaId = $this->_db2->insert('company_ta', $arrTAInfo);

                            if (is_array($arrOfficesIds) && !empty($arrOfficesIds)) {
                                $this->_company->getCompanyTADivisions()->updateCompanyTaDivisions($companyTaId, $arrOfficesIds);
                            }
                        }
                    }

                    // Update CMI data if needed
                    if (empty($strError) && $booUpdateCMI) {
                        $oCMI->updateCMICompany($cmi_id, $reg_id, $companyId);

                        // Set up a trial
                        $nextBillingDate = date('Y-m-d', strtotime('+4 months'));
                        $companyDetails  = array(
                            'company_id'                  => $arrResult['company_id'],
                            'default_label_office'        => $this->_company->getDefaultLabel('office'),
                            'default_label_trust_account' => $this->_company->getDefaultLabel('trust_account'),
                            'account_created_on'          => date('Y-m-d'),
                            'next_billing_date'           => $nextBillingDate,
                            'payment_term'                => null,
                            'subscription'                => 'lite',
                            'free_users'                  => 3,
                            'free_storage'                => 2,
                            'trial'                       => 'Y',
                            'pricing_category_id'         => null,
                            'invoice_number_settings'     => Json::encode($this->_company->getCompanyInvoiceNumberSettings($arrResult['company_id'])),
                            'client_profile_id_settings'  => Json::encode($this->_company->getCompanyClientProfileIdSettings($arrResult['company_id']))
                        );

                        $this->_company->updateCompanyDetails($arrResult['company_id'], $companyDetails);
                    }

                    // Update Free trial key
                    if (empty($strError) && $booUpdateFreeTrial) {
                        // Mark this key as used
                        $this->_company->getCompanyTrial()->saveTrialKey($freetrialKey, $companyId);

                        // Set up a trial
                        $arrParsedKey    = $this->_company->getCompanyTrial()->parseTrialKey($freetrialKey);
                        $nextBillingDate = $arrParsedKey['expDate'];
                        $companyDetails  = array(
                            'company_id'                  => $companyId,
                            'default_label_office'        => $this->_company->getDefaultLabel('office'),
                            'default_label_trust_account' => $this->_company->getDefaultLabel('trust_account'),
                            'account_created_on'          => date('Y-m-d'),
                            'next_billing_date'           => $nextBillingDate,
                            'payment_term'                => null,
                            'subscription'                => 'lite',
                            'free_users'                  => 3,
                            'free_storage'                => 2,
                            'trial'                       => 'Y',
                            'pricing_category_id'         => null,
                            'invoice_number_settings'     => Json::encode($this->_company->getCompanyInvoiceNumberSettings($companyId)),
                            'client_profile_id_settings'  => Json::encode($this->_company->getCompanyClientProfileIdSettings($companyId))
                        );

                        $this->_company->updateCompanyDetails($companyId, $companyDetails);
                    }

                    // Update prospect key (if provided)
                    if (empty($strError) && !empty($prospectId)) {
                        // Calculate Next Billing Date
                        switch ($prospectInfo['payment_term']) {
                            case '1': // Monthly
                                $nextBillingDate = date('Y-m-d', strtotime('+1 months'));
                                break;

                            case '2': // Annually
                                $nextBillingDate = date('Y-m-d', strtotime('+1 year'));
                                break;

                            case '3': // Biannually
                                $nextBillingDate = date('Y-m-d', strtotime('+2 years'));
                                break;

                            default: // Unknown
                                $nextBillingDate = null;
                                break;
                        }

                        $strSubscription = $this->_company->getPackages()->getSubscriptionNameByPackageIds($arrPackages);

                        // Disable access to the "client log" by default
                        $booEnabledClientLog = false;

                        // Web Builder will be enabled for the Pro and upper package
                        $booEnabledWebBuilder = false;
                        if (in_array($strSubscription, array('ultimate_plus', 'ultimate', 'pro13', 'pro')) && in_array($prospectInfo['payment_term'], array('2', '3'))) {
                            $booEnabledWebBuilder = true;
                        }

                        $booEnabledTimeTracker = false;
                        if (in_array($strSubscription, array('ultimate_plus', 'ultimate'))) {
                            $booEnabledTimeTracker = true;
                        }

                        // Enable employer module to ALL supported packages - for AU, otherwise enable for ultimate package only
                        $booEnabledEmployersModule     = false;
                        $arrPackagesForEmployersModule = $this->_config['site_version']['version'] == 'canada' ? ['ultimate_plus', 'ultimate'] : ['ultimate_plus', 'ultimate', 'pro', 'pro13', 'lite'];
                        if (in_array($strSubscription, $arrPackagesForEmployersModule)) {
                            $booEnabledEmployersModule = true;
                        }

                        $booEnabledMarketplaceModule = false;
                        if ($this->_config['marketplace']['enable_on_company_creation'] && in_array($strSubscription, array('ultimate_plus', 'ultimate')) && in_array($prospectInfo['payment_term'], array('2', '3'))) {
                            $booEnabledMarketplaceModule = true;
                        }

                        $booEnableCaseManagement = (bool)$this->_config['site_version']['case_management_enable'];
                        // Add company details
                        $companyDetails = array(
                            'company_id'                  => $arrResult['company_id'],
                            'default_label_office'        => $this->_company->getDefaultLabel('office'),
                            'default_label_trust_account' => $this->_company->getDefaultLabel('trust_account'),
                            'support_and_training'        => $prospectInfo['support'],
                            'payment_term'                => $prospectInfo['payment_term'],
                            'paymentech_profile_id'       => $prospectInfo['paymentech_profile_id'],
                            'paymentech_mode_of_payment'  => $prospectInfo['paymentech_mode_of_payment'],
                            'subscription'                => $strSubscription,
                            'next_billing_date'           => $nextBillingDate,
                            'account_created_on'          => date('Y-m-d'),
                            'subscription_fee'            => $prospectInfo['subscription_fee'],
                            'support_fee'                 => $prospectInfo['support_fee'],
                            'free_users'                  => $prospectInfo['free_users'],
                            'free_clients'                => $prospectInfo['free_clients'],
                            'extra_users'                 => $prospectInfo['extra_users'],
                            'free_storage'                => $prospectInfo['free_storage'],
                            'use_annotations'             => 'Y',
                            'remember_default_fields'     => 'Y',
                            'allow_export'                => 'Y',
                            'company_website'             => $booEnabledWebBuilder ? 'Y' : 'N',
                            'time_tracker_enabled'        => $booEnabledTimeTracker ? 'Y' : 'N',
                            'marketplace_module_enabled'  => $booEnabledMarketplaceModule ? 'Y' : 'N',
                            'employers_module_enabled'    => $booEnabledEmployersModule ? 'Y' : 'N',
                            'log_client_changes_enabled'  => $booEnabledClientLog ? 'Y' : 'N',
                            'enable_case_management'      => $booEnableCaseManagement ? 'Y' : 'N',
                            'loose_task_rules'            => 'N',
                            'hide_inactive_users'         => 'N',
                            'pricing_category_id'         => $prospectInfo['pricing_category_id'],
                            'invoice_number_settings'     => Json::encode($this->_company->getCompanyInvoiceNumberSettings($companyId)),
                            'client_profile_id_settings'  => Json::encode($this->_company->getCompanyClientProfileIdSettings($companyId))
                        );

                        $this->_company->updateCompanyDetails($arrResult['company_id'], $companyDetails);

                        $this->_company->toggleAccessToExport($companyId, true);

                        // Enable access to the "Web Builder" for the Pro and upper package
                        $this->_company->updateWebBuilder($companyId, $booEnabledWebBuilder);

                        // Enable access to Time Tracker if 'Ultimate' package was selected
                        $this->_company->updateTimeTracker($companyId, $booEnabledTimeTracker);

                        // Enable access to Marketplace if correct package was selected
                        $this->_company->getCompanyMarketplace()->toggleMarketplace($companyId, $booEnabledMarketplaceModule);


                        // Update prospects
                        $this->_prospects->setCompanyId($prospectId, $arrResult['company_id']);
                        $this->_prospects->setKeyStatusAsUsed($prospectId);
                        $this->_prospects->resetProspectAdminInfo($prospectId);

                        //update company first invoice
                        $arrInvoiceUpdate = array('company_id' => $arrResult['company_id']);
                        $this->_company->getCompanyInvoice()->updateProspectInvoice($arrInvoiceUpdate, $prospectId);
                    }

                    if (empty($strError)) {
                        //Create default Case Number Settings
                        $this->_clients->getCaseNumber()->createDefaultCompanyCaseNumberSettings(0, $companyId);

                        // Copy default analytics to this new company
                        $this->_analytics->createDefaultCompanyAnalytics(0, $companyId);
                    }

                    // Send confirmation email
                    if (empty($strError)) {
                        $subject = $arrCompanyInfo['companyName'] . ' - Activated';
                        $message = 'Company <i>' . $arrCompanyInfo['companyName'] . '</i> was successfully created';
                        $this->_mailer->sendEmailToSupport($subject, $message);
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $strError;
    }
}
