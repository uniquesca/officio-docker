<?php

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */

namespace Officio\Service;

use Laminas\Navigation\Page\Mvc;
use Laminas\Router\RouteStackInterface;
use Laminas\View\HelperPluginManager;
use Officio\Common\Service\BaseService;

class Navigation extends BaseService
{

    /** @var Company */
    protected $_company;

    /** @var HelperPluginManager */
    protected $_viewHelperManager;

    /** @var RouteStackInterface */
    protected $_router;

    public function initAdditionalServices(array $services)
    {
        $this->_company           = $services[Company::class];
        $this->_viewHelperManager = $services[HelperPluginManager::class];
        $this->_router            = $services['router'];
    }

    public function getAdminNavigation()
    {
        $booCompanyAdmin              = $this->_auth->isCurrentUserAdmin();
        $booSuperAdmin                = $this->_auth->isCurrentUserSuperadmin();
        $booSuperAdminLoggedInAsAdmin = $this->_auth->isCurrentUserSuperadminMaskedAsAdmin();

        $arrPages = [
            [
                // Company Admin only
                'label'   => 'Manage Users',
                'module'  => 'officio',
                'visible' => $booCompanyAdmin && !$booSuperAdmin,
                'uri'     => '#',
                'pages'   => [
                    [
                        'label'      => 'Roles',
                        'module'     => 'superadmin',
                        'controller' => 'roles',
                        'route'      => 'superadmin_roles',
                    ],
                    [
                        'label'      => 'Users',
                        'module'     => 'superadmin',
                        'controller' => 'manage-members',
                        'route'      => 'superadmin_manage_members',
                    ],
                    [
                        'label'         => 'Change My Password',
                        'module'        => 'superadmin',
                        'controller'    => 'change-my-password',
                        'route'         => 'superadmin_change_my_password',
                        'class'         => 'changeMyPasswordLink',
                        'rule_check_id' => 'user-profile-view',
                    ],
                    [
                        'label'         => 'PUA Planning',
                        'module'        => 'superadmin',
                        'controller'    => 'manage-members-pua',
                        'route'         => 'superadmin_manage_members_pua',
                        'rule_check_id' => 'pua-planning',
                        'visible'       => (bool)$this->_config['site_version']['pua_enabled']
                    ],
                ]
            ],

            [
                // Company Admin only
                'label'   => 'Manage Settings',
                'module'  => 'officio',
                'visible' => $booCompanyAdmin && !$booSuperAdmin,
                'uri'     => '#',
                'pages'   => [
                    [
                        'label'         => 'Company Settings',
                        'module'        => 'superadmin',
                        'controller'    => 'manage-own-company',
                        'route'         => 'superadmin_manage_own_company',
                        'rule_check_id' => 'manage-company-edit'
                    ],
                    [
                        'label'      => 'Automatic Tasks',
                        'module'     => 'superadmin',
                        'controller' => 'automatic-reminders',
                        'route'      => 'superadmin_automatic_reminders',
                    ],
                    [
                        'label'      => 'Prospects Questionnaires',
                        'module'     => 'superadmin',
                        'controller' => 'manage-company-prospects',
                        'route'      => 'superadmin_manage_company_prospects',
                    ],
                    [
                        'label'      => 'Case File Number Settings',
                        'module'     => 'superadmin',
                        'controller' => 'manage-company',
                        'action'     => 'case-number-settings',
                        'route'      => 'superadmin_manage_company',
                    ],
                    [
                        'label'      => $this->_company->getCurrentCompanyDefaultLabel('office', true),
                        'module'     => 'superadmin',
                        'controller' => 'manage-offices',
                        'route'      => 'superadmin_manage_offices'
                    ],
                    [
                        'label'         => "Client Document Folders",
                        'module'        => 'superadmin',
                        'controller'    => 'client-documents',
                        'action'        => 'index',
                        'route'         => 'superadmin_client_documents',
                        'rule_check_id' => 'client-documents-settings-view'
                    ],
                    [
                        'label'      => $this->_company->getCurrentCompanyDefaultLabel('trust_account'),
                        'module'     => 'superadmin',
                        'controller' => 'trust-account-settings',
                        'action'     => 'index',
                        'route'      => 'superadmin_trust_account_settings',
                    ],
                    [
                        'label'         => 'Marketplace Profiles',
                        'module'        => 'superadmin',
                        'controller'    => 'marketplace',
                        'route'         => 'superadmin_marketplace',
                        'rule_check_id' => 'manage-marketplace',
                        'class'         => 'nav-spacer',
                    ],


                    [
                        'label'      => 'Events Log',
                        'module'     => 'superadmin',
                        'controller' => 'access-logs',
                        'route'      => 'superadmin_access_logs',
                    ],
                    [
                        'label'         => 'VACs/Visa Offices',
                        'module'        => 'superadmin',
                        'controller'    => 'manage-vac',
                        'route'         => 'superadmin_manage_vac',
                        'rule_check_id' => 'manage-company-edit'
                    ],
                    [
                        'label'      => 'Define Authorised Agents',
                        'module'     => 'superadmin',
                        'controller' => 'manage-divisions-groups',
                        'route'      => 'superadmin_manage_divisions_groups',
                        'visible'    => $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled()
                    ],
                    [
                        'label'         => 'Default Forms',
                        'module'        => 'superadmin',
                        'controller'    => 'forms-default',
                        'route'         => 'superadmin_forms_default',
                        'rule_check_id' => 'forms-default'
                    ],
                    [
                        'label'      => 'Web-builder',
                        'module'     => 'superadmin',
                        'controller' => 'company-website',
                        'route'      => 'superadmin_company_website',
                        'class'      => 'nav-spacer',
                    ],

                    [
                        'label'         => 'Workflow',
                        'module'        => 'superadmin',
                        'controller'    => 'manage-company-case-statuses',
                        'action'        => 'index',
                        'route'         => 'superadmin_manage_company_case_statuses',
                        'rule_check_id' => 'manage-company-edit',
                        'class'         => $booSuperAdminLoggedInAsAdmin ? '' : 'nav-spacer',
                    ],
                    [
                        'label'      => $this->_company->getCurrentCompanyDefaultLabel('case_type', true),
                        'module'     => 'superadmin',
                        'controller' => 'manage-fields-groups',
                        'action'     => 'templates',
                        'route'      => 'superadmin_manage_fields_groups',
                        'visible'    => $booSuperAdminLoggedInAsAdmin
                    ],
                    [
                        'label'         => 'Contacts Types',
                        'module'        => 'superadmin',
                        'controller'    => 'manage-applicant-fields-groups',
                        'action'        => 'applicant-types',
                        'route'         => 'superadmin_manage_applicant_fields_groups',
                        'rule_check_id' => 'manage-contacts-fields',
                        'visible'       => $booSuperAdminLoggedInAsAdmin
                    ],
                    [
                        'label'         => 'Individual Client Profile',
                        'module'        => 'superadmin',
                        'controller'    => 'manage-applicant-fields-groups',
                        'action'        => 'index',
                        'params'        => [
                            'member_type' => 'individuals',
                        ],
                        'route'         => 'superadmin_manage_applicant_fields_groups',
                        'rule_check_id' => 'manage-individuals-fields',
                        'visible'       => $booSuperAdminLoggedInAsAdmin
                    ],
                    [
                        'label'         => 'Employer Client Profile',
                        'module'        => 'superadmin',
                        'controller'    => 'manage-applicant-fields-groups',
                        'action'        => 'index',
                        'params'        => [
                            'member_type' => 'employers',
                        ],
                        'route'         => 'superadmin_manage_applicant_fields_groups',
                        'rule_check_id' => 'manage-employers-fields',
                        'visible'       => $booSuperAdminLoggedInAsAdmin
                    ],
                    [
                        'label'         => 'Internal Client Profile',
                        'module'        => 'superadmin',
                        'controller'    => 'manage-applicant-fields-groups',
                        'action'        => 'index',
                        'params'        => [
                            'member_type' => 'internal_contact',
                        ],
                        'route'         => 'superadmin_manage_applicant_fields_groups',
                        'rule_check_id' => 'manage-internals-fields',
                        'visible'       => $booSuperAdminLoggedInAsAdmin,
                        'class'         => 'nav-spacer',
                    ],

                    [
                        'label'      => 'Import Clients',
                        'module'     => 'superadmin',
                        'controller' => 'import-clients',
                        'route'      => 'superadmin_import_clients',
                    ],
                    [
                        'label'      => 'Import Client Notes',
                        'module'     => 'superadmin',
                        'controller' => 'import-client-notes',
                        'route'      => 'superadmin_import_client_notes',
                    ],
                    [
                        'label'      => 'BC PNP Import',
                        'module'     => 'superadmin',
                        'controller' => 'import-bcpnp',
                        'route'      => 'superadmin_import_bcpnp',
                    ],
                ],
            ],

            // SuperAdmin only
            [
                'label'   => 'Super Admin Functions',
                'module'  => 'officio',
                'visible' => $booSuperAdmin,
                'uri'     => '/',
                'pages'   => [
                    [
                        'label'      => 'Events Log',
                        'module'     => 'superadmin',
                        'controller' => 'access-logs',
                        'route'      => 'superadmin_access_logs',
                    ],
                    [
                        'label'      => 'Announcements',
                        'module'     => 'superadmin',
                        'controller' => 'news',
                        'route'      => 'superadmin_news',
                    ],
                    [
                        'label'      => 'Manage Help',
                        'module'     => 'superadmin',
                        'controller' => 'manage-faq',
                        'route'      => 'superadmin_manage_faq',
                    ],
                    [
                        'label'      => 'Manage Super Admin Users',
                        'module'     => 'superadmin',
                        'controller' => 'manage-admin-users',
                        'route'      => 'superadmin_manage_admin_users',
                    ],
                    [
                        'label'         => 'Manage Super Admin Roles',
                        'module'        => 'superadmin',
                        'controller'    => 'roles',
                        'route'         => 'superadmin_roles',
                        'rule_check_id' => 'manage-superadmin-roles',
                        'query'         => ['type' => 'superadmin']
                    ],
                    [
                        'label'      => 'Manage All Company Users',
                        'module'     => 'superadmin',
                        'controller' => 'manage-members',
                        'route'      => 'superadmin_manage_members',
                    ],
                    [
                        'label'      => 'Manage Forms',
                        'module'     => 'superadmin',
                        'controller' => 'forms',
                        'route'      => 'superadmin_forms',
                    ],
                    [
                        'label'      => 'PDF Version Checker',
                        'module'     => 'superadmin',
                        'controller' => 'url-checker',
                        'route'      => 'superadmin_url_checker',
                    ],
                    [
                        'label'         => 'Change My Password',
                        'module'        => 'superadmin',
                        'controller'    => 'change-my-password',
                        'route'         => 'superadmin_change_my_password',
                        'class'         => 'changeMyPasswordLink',
                        'rule_check_id' => 'user-profile-view',
                    ],
                    [
                        'label'      => 'Manage Prospects',
                        'module'     => 'superadmin',
                        'controller' => 'manage-prospects',
                        'route'      => 'superadmin_manage_prospects',
                    ],
                    [
                        'label'      => 'Manage Templates',
                        'module'     => 'superadmin',
                        'controller' => 'manage-templates',
                        'route'      => 'superadmin_manage_templates',
                    ],
                    [
                        'label'      => 'Mail Server Settings',
                        'module'     => 'superadmin',
                        'controller' => 'smtp',
                        'route'      => 'superadmin_smtp',
                    ],
                    [
                        'label'      => 'Last Logged In Info',
                        'module'     => 'superadmin',
                        'controller' => 'last-logged-in',
                        'route'      => 'superadmin_last_logged_in',
                    ],
                    [
                        'label'      => 'Manage GST/HST',
                        'module'     => 'superadmin',
                        'controller' => 'manage-hst',
                        'route'      => 'superadmin_manage_hst',
                    ],
                    [
                        'label'      => 'Manage news black list',
                        'module'     => 'superadmin',
                        'controller' => 'manage-rss-feed',
                        'route'      => 'superadmin_manage_rss_feed',
                    ],
                    [
                        'label'      => 'Manage CMI',
                        'module'     => 'superadmin',
                        'controller' => 'manage-cmi',
                        'route'      => 'superadmin_manage_cmi',
                    ],
                    [
                        'label'      => 'Trial users pricing',
                        'module'     => 'superadmin',
                        'controller' => 'manage-trial-pricing',
                        'route'      => 'superadmin_manage_trial_pricing',
                    ],
                    [
                        'label'      => 'Manage pricing',
                        'module'     => 'superadmin',
                        'controller' => 'manage-pricing',
                        'route'      => 'superadmin_manage_pricing',
                    ],
                    [
                        'label'      => 'Accounts',
                        'module'     => 'superadmin',
                        'controller' => 'accounts',
                        'route'      => 'superadmin_accounts',
                    ],
                    [
                        'label'      => 'Statistics',
                        'module'     => 'superadmin',
                        'controller' => 'statistics',
                        'route'      => 'superadmin_statistics',
                    ],
                    [
                        'label'      => 'Prospects Matching',
                        'module'     => 'superadmin',
                        'controller' => 'prospects-matching',
                        'route'      => 'superadmin_prospects_matching',
                    ],
                    [
                        'label'      => 'System variables',
                        'module'     => 'superadmin',
                        'controller' => 'system-variables',
                        'route'      => 'superadmin_system_variables',
                    ],
                    [
                        'label'      => 'Zoho settings',
                        'module'     => 'superadmin',
                        'controller' => 'zoho',
                        'route'      => 'superadmin_zoho',
                    ],
                    [
                        'label'      => 'Manage default mail servers',
                        'module'     => 'superadmin',
                        'controller' => 'manage-default-mail-servers',
                        'route'      => 'superadmin_manage_default_mail_servers',
                    ],
                ],
            ],

            // SuperAdmin only
            [
                'label'   => 'New Company Defaults',
                'module'  => 'officio',
                'visible' => $booSuperAdmin,
                'uri'     => '/',
                'pages'   => [
                    [
                        'label'         => 'Default Roles',
                        'module'        => 'superadmin',
                        'controller'    => 'roles',
                        'route'         => 'superadmin_roles',
                        'rule_check_id' => 'admin-roles-view',
                    ],
                    [
                        'label'         => 'Default Workflow',
                        'module'        => 'superadmin',
                        'controller'    => 'manage-company-case-statuses',
                        'action'        => 'index',
                        'route'         => 'superadmin_manage_company_case_statuses',
                        'rule_check_id' => 'manage-company-edit'
                    ],
                    [
                        'label'         => 'Default VACs/Visa Offices',
                        'module'        => 'superadmin',
                        'controller'    => 'manage-vac',
                        'route'         => 'superadmin_manage_vac',
                        'rule_check_id' => 'manage-company-edit'
                    ],
                    [
                        'label'      => 'Default ' . $this->_company->getCurrentCompanyDefaultLabel('case_type', true),
                        'module'     => 'superadmin',
                        'controller' => 'manage-fields-groups',
                        'action'     => 'templates',
                        'route'      => 'superadmin_manage_fields_groups',
                    ],
                    [
                        'label'      => 'Default Contacts Types',
                        'module'     => 'superadmin',
                        'controller' => 'manage-applicant-fields-groups',
                        'action'     => 'applicant-types',
                        'route'      => 'superadmin_manage_applicant_fields_groups',
                    ],
                    [
                        'label'      => 'Default Individual Client Profile',
                        'module'     => 'superadmin',
                        'controller' => 'manage-applicant-fields-groups',
                        'action'     => 'index',
                        'params'     => [
                            'member_type' => 'individuals',
                        ],
                        'route'      => 'superadmin_manage_applicant_fields_groups',
                    ],
                    [
                        'label'      => 'Default Employer Client Profile',
                        'module'     => 'superadmin',
                        'controller' => 'manage-applicant-fields-groups',
                        'action'     => 'index',
                        'params'     => [
                            'member_type' => 'employers',
                        ],
                        'route'      => 'superadmin_manage_applicant_fields_groups',
                    ],
                    [
                        'label'      => 'Default Internal Contact Profile',
                        'module'     => 'superadmin',
                        'controller' => 'manage-applicant-fields-groups',
                        'action'     => 'index',
                        'params'     => [
                            'member_type' => 'internal_contact',
                        ],
                        'route'      => 'superadmin_manage_applicant_fields_groups',
                    ],
                    [
                        'label'         => "Default Clients' Document Folders",
                        'module'        => 'superadmin',
                        'controller'    => 'client-documents',
                        'action'        => 'index',
                        'route'         => 'superadmin_client_documents',
                        'rule_check_id' => 'client-documents-settings-view',
                    ],
                    [
                        'label'      => 'Default Automatic Tasks',
                        'module'     => 'superadmin',
                        'controller' => 'automatic-reminders',
                        'route'      => 'superadmin_automatic_reminders',
                    ],
                    [
                        'label'         => 'Default ' . $this->_company->getCurrentCompanyDefaultLabel('trust_account') . ' Transaction Settings',
                        'module'        => 'superadmin',
                        'controller'    => 'trust-account-settings',
                        'action'        => 'settings',
                        'route'         => 'superadmin_trust_account_settings',
                        'rule_check_id' => 'superadmin-trust-account-settings'
                    ],
                    [
                        'label'      => 'Default Shared Templates',
                        'module'     => 'superadmin',
                        'controller' => 'shared-templates',
                        'route'      => 'superadmin_shared_templates',
                    ],
                    [
                        'label'      => 'Default Searches',
                        'module'     => 'superadmin',
                        'controller' => 'default-searches',
                        'route'      => 'superadmin_default_searches',
                    ],
                    [
                        'label'      => 'Default Analytics',
                        'module'     => 'superadmin',
                        'controller' => 'manage-default-analytics',
                        'route'      => 'superadmin_manage_default_analytics',
                    ],
                    [
                        'label'      => 'Default Prospects',
                        'module'     => 'superadmin',
                        'controller' => 'manage-company-prospects',
                        'route'      => 'superadmin_manage_company_prospects',
                    ],
                    [
                        'label'      => 'Default Website Settings',
                        'module'     => 'superadmin',
                        'controller' => 'company-website',
                        'route'      => 'superadmin_company_website',
                    ],
                    [
                        'label'      => 'Default Case File Number Settings',
                        'module'     => 'superadmin',
                        'controller' => 'manage-company',
                        'action'     => 'case-number-settings',
                        'route'      => 'superadmin_manage_company',
                    ],
                ],
            ],
        ];

        foreach ($arrPages as $pageKey => $page) {
            $booAtLeastOneVisible = false;
            foreach ($page['pages'] as $subPageKey => $arrSubPageInfo) {
                if (isset($arrSubPageInfo['rule_check_id']) && !empty($arrSubPageInfo['rule_check_id'])) {
                    $booAllowed = $this->_acl->isAllowed($arrSubPageInfo['rule_check_id']);
                } else {
                    $arrSubPageInfo['action']     = $arrSubPageInfo['action'] ?? 'index';
                    $arrSubPageInfo['controller'] = $arrSubPageInfo['controller'] ?? 'index';
                    $booAllowed                   = $this->_acl->isAllowedResource($arrSubPageInfo['module'], $arrSubPageInfo['controller'], $arrSubPageInfo['action']);
                }

                $booPageVisible = $arrSubPageInfo['visible'] ?? true;

                $arrPages[$pageKey]['pages'][$subPageKey]['visible'] = $booPageVisible && $booAllowed;

                if ($booPageVisible && $booAllowed) {
                    $booAtLeastOneVisible = true;
                }
            }

            // Don't show the title if there are no sub-pages
            if (!$booAtLeastOneVisible) {
                unset($arrPages[$pageKey]);
            }
        }

        // Create container from array
        $container = new \Laminas\Navigation\Navigation($arrPages);
        Mvc::setDefaultRouter($this->_router);

        /** @var \Laminas\View\Helper\Navigation $navigation */
        $navigation = $this->_viewHelperManager->get('navigation');
        $navigation->setContainer($container)
            ->setRenderInvisible(false);

        return $navigation->menu()
            ->setPartial('officio/partials/menu')
            ->render();
    }

    /**
     * Get admin links that will be available in the main menu (under the user's name)
     *
     * @return array
     */
    public function getMainAdminNavigation()
    {
        $arrAdminLinks = array();

        if ($this->_acl->isAllowed('admin-view')) {
            if ($this->_acl->isAllowed('manage-members')) {
                $arrAdminLinks[] = array(
                    'title' => 'Manage Users',
                    'link'  => '/superadmin/manage-members'
                );
            }

            if ($this->_acl->isAllowed('manage-company-edit')) {
                $arrAdminLinks[] = array(
                    'title' => 'Company Settings',
                    'link'  => '/superadmin/manage-own-company/index'
                );

                $arrAdminLinks[] = array(
                    'title' => 'View Subscription Details',
                    'link'  => '/superadmin/manage-own-company/index?tab=company-packages'
                );
            }

            if ($this->_acl->isAllowed('automatic-reminders')) {
                $arrAdminLinks[] = array(
                    'title' => 'Edit Automatic Tasks',
                    'link'  => '/superadmin/automatic-reminders'
                );
            }

            $arrAdminLinks[] = array(
                'title' => 'View All Admin Settings',
                'link'  => '/superadmin/'
            );
        }

        return $arrAdminLinks;
    }
}