<?php

namespace Superadmin;

use Laminas\Router\Http\Segment;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Officio\Common\Service\Factory\BaseServiceFactory;
use Officio\Controller\Plugin\UrlChecker;
use Superadmin\Controller\Factory\ManageCompanyCaseStatusesControllerFactory;
use Superadmin\Controller\Factory\ManageVacControllerFactory;
use Superadmin\Controller\ManageCompanyCaseStatusesController;
use Superadmin\Controller\ManageVacController;
use Superadmin\Service\SuperadminSearch;
use Superadmin\View\Helper\FormSearchLimit;
use Superadmin\View\Helper\FormSortArrows;
use Superadmin\Controller\AutomatedBillingLogController;
use Superadmin\Controller\AutomaticReminderActionsController;
use Superadmin\Controller\AutomaticReminderConditionsController;
use Superadmin\Controller\AutomaticRemindersController;
use Superadmin\Controller\AutomaticReminderTriggersController;
use Superadmin\Controller\ChangeMyPasswordController;
use Superadmin\Controller\ClientDocumentsController;
use Superadmin\Controller\CompanyWebsiteController;
use Superadmin\Controller\ConditionalFieldsController;
use Superadmin\Controller\DefaultSearchesController;
use Superadmin\Controller\ErrorController;
use Superadmin\Controller\Factory\AccountsControllerFactory;
use Superadmin\Controller\Factory\AuthControllerFactory;
use Superadmin\Controller\AccessLogsController;
use Superadmin\Controller\AccountsController;
use Superadmin\Controller\AdvancedSearchController;
use Superadmin\Controller\AuthController;
use Superadmin\Controller\Factory\AccessLogsControllerFactory;
use Superadmin\Controller\Factory\AdvancedSearchControllerFactory;
use Superadmin\Controller\Factory\AutomatedBillingLogControllerFactory;
use Superadmin\Controller\Factory\AutomaticReminderActionsControllerFactory;
use Superadmin\Controller\Factory\AutomaticReminderConditionsControllerFactory;
use Superadmin\Controller\Factory\AutomaticRemindersControllerFactory;
use Superadmin\Controller\Factory\AutomaticReminderTriggersControllerFactory;
use Superadmin\Controller\Factory\ChangeMyPasswordControllerFactory;
use Superadmin\Controller\Factory\ClientDocumentsControllerFactory;
use Superadmin\Controller\Factory\CompanyWebsiteControllerFactory;
use Superadmin\Controller\Factory\ConditionalFieldsControllerFactory;
use Superadmin\Controller\Factory\DefaultSearchesControllerFactory;
use Superadmin\Controller\Factory\ErrorControllerFactory;
use Superadmin\Controller\Factory\FormsControllerFactory;
use Superadmin\Controller\Factory\FormsDefaultControllerFactory;
use Superadmin\Controller\Factory\FormsMapsControllerFactory;
use Superadmin\Controller\Factory\ImportBcpnpControllerFactory;
use Superadmin\Controller\Factory\ImportClientNotesControllerFactory;
use Superadmin\Controller\Factory\ImportClientsControllerFactory;
use Superadmin\Controller\Factory\IndexControllerFactory;
use Superadmin\Controller\Factory\LastLoggedInControllerFactory;
use Superadmin\Controller\Factory\LetterheadsControllerFactory;
use Superadmin\Controller\Factory\ManageAdminUsersControllerFactory;
use Superadmin\Controller\Factory\ManageApplicantFieldsGroupsControllerFactory;
use Superadmin\Controller\Factory\ManageBadDebtsLogControllerFactory;
use Superadmin\Controller\Factory\ManageBusinessHoursControllerFactory;
use Superadmin\Controller\Factory\ManageCmiControllerFactory;
use Superadmin\Controller\Factory\ManageCompanyAsAdminControllerFactory;
use Superadmin\Controller\Factory\ManageCompanyControllerFactory;
use Superadmin\Controller\Factory\ManageCompanyProspectsControllerFactory;
use Superadmin\Controller\Factory\ManageDefaultAnalyticsControllerFactory;
use Superadmin\Controller\Factory\ManageDefaultMailServersControllerFactory;
use Superadmin\Controller\Factory\ManageDivisionsGroupsControllerFactory;
use Superadmin\Controller\Factory\ManageFaqControllerFactory;
use Superadmin\Controller\Factory\ManageFieldsGroupsControllerFactory;
use Superadmin\Controller\Factory\ManageHstControllerFactory;
use Superadmin\Controller\Factory\ManageInvoicesControllerFactory;
use Superadmin\Controller\Factory\ManageMembersControllerFactory;
use Superadmin\Controller\Factory\ManageMembersPuaControllerFactory;
use Superadmin\Controller\Factory\ManageOfficesControllerFactory;
use Superadmin\Controller\Factory\ManageOwnCompanyControllerFactory;
use Superadmin\Controller\Factory\ManagePricingControllerFactory;
use Superadmin\Controller\Factory\ManageProspectsControllerFactory;
use Superadmin\Controller\Factory\ManagePtErrorCodesControllerFactory;
use Superadmin\Controller\Factory\ManageRssFeedControllerFactory;
use Superadmin\Controller\Factory\ManageTemplatesControllerFactory;
use Superadmin\Controller\Factory\ManageTrialPricingControllerFactory;
use Superadmin\Controller\Factory\MarketplaceControllerFactory;
use Superadmin\Controller\Factory\NewsControllerFactory;
use Superadmin\Controller\Factory\ProspectsMatchingControllerFactory;
use Superadmin\Controller\Factory\ResourcesControllerFactory;
use Superadmin\Controller\Factory\RolesControllerFactory;
use Superadmin\Controller\Factory\SharedTemplatesControllerFactory;
use Superadmin\Controller\Factory\SmtpControllerFactory;
use Superadmin\Controller\Factory\StatisticsControllerFactory;
use Superadmin\Controller\Factory\SystemVariablesControllerFactory;
use Superadmin\Controller\Factory\TicketsControllerFactory;
use Superadmin\Controller\Factory\TrustAccountSettingsControllerFactory;
use Superadmin\Controller\Factory\UrlCheckerControllerFactory;
use Superadmin\Controller\Factory\ZohoControllerFactory;
use Superadmin\Controller\FormsController;
use Superadmin\Controller\FormsDefaultController;
use Superadmin\Controller\FormsMapsController;
use Superadmin\Controller\ImportBcpnpController;
use Superadmin\Controller\ImportClientNotesController;
use Superadmin\Controller\ImportClientsController;
use Superadmin\Controller\IndexController;
use Superadmin\Controller\LastLoggedInController;
use Superadmin\Controller\LetterheadsController;
use Superadmin\Controller\ManageAdminUsersController;
use Superadmin\Controller\ManageApplicantFieldsGroupsController;
use Superadmin\Controller\ManageBadDebtsLogController;
use Superadmin\Controller\ManageBusinessHoursController;
use Superadmin\Controller\ManageCmiController;
use Superadmin\Controller\ManageCompanyAsAdminController;
use Superadmin\Controller\ManageCompanyController;
use Superadmin\Controller\ManageCompanyProspectsController;
use Superadmin\Controller\ManageDefaultAnalyticsController;
use Superadmin\Controller\ManageDefaultMailServersController;
use Superadmin\Controller\ManageDivisionsGroupsController;
use Superadmin\Controller\ManageFaqController;
use Superadmin\Controller\ManageFieldsGroupsController;
use Superadmin\Controller\ManageHstController;
use Superadmin\Controller\ManageInvoicesController;
use Superadmin\Controller\ManageMembersController;
use Superadmin\Controller\ManageMembersPuaController;
use Superadmin\Controller\ManageOfficesController;
use Superadmin\Controller\ManageOwnCompanyController;
use Superadmin\Controller\ManagePricingController;
use Superadmin\Controller\ManageProspectsController;
use Superadmin\Controller\ManagePtErrorCodesController;
use Superadmin\Controller\ManageRssFeedController;
use Superadmin\Controller\ManageTemplatesController;
use Superadmin\Controller\ManageTrialPricingController;
use Superadmin\Controller\MarketplaceController;
use Superadmin\Controller\NewsController;
use Superadmin\Controller\ProspectsMatchingController;
use Superadmin\Controller\ResourcesController;
use Superadmin\Controller\RolesController;
use Superadmin\Controller\SharedTemplatesController;
use Superadmin\Controller\SmtpController;
use Superadmin\Controller\StatisticsController;
use Superadmin\Controller\SystemVariablesController;
use Superadmin\Controller\TicketsController;
use Superadmin\Controller\TrustAccountSettingsController;
use Superadmin\Controller\UrlCheckerController;
use Superadmin\Controller\ZohoController;

return [
    'service_manager' => [
        'factories' => [
            SuperadminSearch::class => BaseServiceFactory::class
        ]
    ],

    'controller_plugins' => [
        'invokables' => [
            'urlChecker' => UrlChecker::class,
        ]
    ],

    'router' => [
        'routes' => [
            'superadmin_login' => [
                'type' => 'literal',
                'options' => [
                    'route' => '/superadmin/login',
                    'defaults' => [
                        'controller' => AuthController::class,
                        'action' => 'login',
                    ],
                ],
            ],

            'superadmin_access_denied_error' => [
                'type' => 'literal',
                'options' => [
                    'route' => '/superadmin/error/access-denied',
                    'defaults' => [
                        'controller' => ErrorController::class,
                        'action' => 'access-denied',
                    ],
                ],
            ],

            /** controllers **/
            'superadmin_access_logs' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/access-logs[/:action[/]]',
                    'defaults' => [
                        'controller' => AccessLogsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_accounts' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/accounts[/:action[/]]',
                    'defaults' => [
                        'controller' => AccountsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_advanced_search' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/advanced-search[/:action[/]]',
                    'defaults' => [
                        'controller' => AdvancedSearchController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_auth' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/auth[/:action[/]]',
                    'defaults' => [
                        'controller' => AuthController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_automated_billing_log' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/automated-billing-log[/:action[/]]',
                    'defaults' => [
                        'controller' => AutomatedBillingLogController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_automatic_reminder_actions' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/automatic-reminder-actions[/:action[/]]',
                    'defaults' => [
                        'controller' => AutomaticReminderActionsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_automatic_reminder_conditions' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/automatic-reminder-conditions[/:action[/]]',
                    'defaults' => [
                        'controller' => AutomaticReminderConditionsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_automatic_reminders' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/automatic-reminders[/:action[/]]',
                    'defaults' => [
                        'controller' => AutomaticRemindersController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_automatic_reminder_triggers' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/automatic-reminder-triggers[/:action[/]]',
                    'defaults' => [
                        'controller' => AutomaticReminderTriggersController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_change_my_password' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/change-my-password[/:action[/]]',
                    'defaults' => [
                        'controller' => ChangeMyPasswordController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_client_documents' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/client-documents[/:action[/]]',
                    'defaults' => [
                        'controller' => ClientDocumentsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_company_website' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/company-website[/:action[/]]',
                    'defaults' => [
                        'controller' => CompanyWebsiteController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_company_website_builder' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/builder/:entrance[/:action[/]]',
                    'defaults' => [
                        'controller' => CompanyWebsiteController::class,
                        'action' => 'builder',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'entrance' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_conditional_fields' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/conditional-fields[/:action[/]]',
                    'defaults' => [
                        'controller' => ConditionalFieldsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_default_searches' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/default-searches[/:action[/]]',
                    'defaults' => [
                        'controller' => DefaultSearchesController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_error' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/error[/:action[/]]',
                    'defaults' => [
                        'controller' => ErrorController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_forms' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/forms[/:action[/]]',
                    'defaults' => [
                        'controller' => FormsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_forms_default' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/forms-default[/:action[/]]',
                    'defaults' => [
                        'controller' => FormsDefaultController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_forms_maps' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/forms-maps[/:action[/]]',
                    'defaults' => [
                        'controller' => FormsMapsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_import_bcpnp' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/import-bcpnp[/:action[/]]',
                    'defaults' => [
                        'controller' => ImportBcpnpController::class,
                        'action' => 'index',
                        'select_file_error' => '',
                        'select_file_message' => '',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_import_client_notes' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/import-client-notes[/:action[/]]',
                    'defaults' => [
                        'controller' => ImportClientNotesController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_import_clients' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/import-clients[/:action[/]]',
                    'defaults' => [
                        'controller' => ImportClientsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_index' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/[index[/:action[/]]]',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_last_logged_in' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/last-logged-in[/:action[/]]',
                    'defaults' => [
                        'controller' => LastLoggedInController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_letterheads' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/letterheads[/:action[/]]',
                    'defaults' => [
                        'controller' => LetterheadsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_admin_users' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-admin-users[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageAdminUsersController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_applicant_fields_groups' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-applicant-fields-groups[/:action[/:member_type[/]]]',
                    'defaults' => [
                        'controller' => ManageApplicantFieldsGroupsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'member_type' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_bad_debts_log' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-bad-debts-log[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageBadDebtsLogController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_business_hours' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-business-hours[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageBusinessHoursController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_cmi' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-cmi[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageCmiController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_company_as_admin' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-company-as-admin[/:company_id[/:member_id]]',
                    'defaults' => [
                        'controller' => ManageCompanyAsAdminController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'company_id' => '[0-9]+',
                        'member_id' => '[0-9]+',
                    ],
                ],
            ],

            'superadmin_manage_company' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-company[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageCompanyController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_company_case_statuses' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-company-case-statuses[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageCompanyCaseStatusesController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_company_prospects' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-company-prospects[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageCompanyProspectsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_default_analytics' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-default-analytics[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageDefaultAnalyticsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_default_mail_servers' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-default-mail-servers[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageDefaultMailServersController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_divisions_groups' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-divisions-groups[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageDivisionsGroupsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_faq' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-faq[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageFaqController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_fields_groups' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-fields-groups[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageFieldsGroupsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_hst' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-hst[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageHstController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_invoices' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-invoices[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageInvoicesController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_members' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-members[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageMembersController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_members_pua' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-members-pua[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageMembersPuaController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_offices' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-offices[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageOfficesController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_own_company' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-own-company[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageOwnCompanyController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_pricing' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-pricing[/:action[/]]',
                    'defaults' => [
                        'controller' => ManagePricingController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_prospects' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-prospects[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageProspectsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_pt_error_codes' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-pt-error-codes[/:action[/]]',
                    'defaults' => [
                        'controller' => ManagePtErrorCodesController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_rss_feed' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-rss-feed[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageRssFeedController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_templates' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-templates[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageTemplatesController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_trial_pricing' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/manage-trial-pricing[/:action[/]]',
                    'defaults' => [
                        'controller' => ManageTrialPricingController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_marketplace' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/marketplace[/:action[/]]',
                    'defaults' => [
                        'controller' => MarketplaceController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_news' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/news[/:action[/]]',
                    'defaults' => [
                        'controller' => NewsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_prospects_matching' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/prospects-matching[/:action[/]]',
                    'defaults' => [
                        'controller' => ProspectsMatchingController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_resources' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/resources[/:action[/]]',
                    'defaults' => [
                        'controller' => ResourcesController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_roles' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/roles[/:action[/:type]]',
                    'defaults' => [
                        'controller' => RolesController::class,
                        'action' => 'index',
                        'type' => 'company',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'type' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_shared_templates' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/shared-templates[/:action[/]]',
                    'defaults' => [
                        'controller' => SharedTemplatesController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_smtp' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/smtp[/:action[/]]',
                    'defaults' => [
                        'controller' => SmtpController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_statistics' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/statistics[/:action[/]]',
                    'defaults' => [
                        'controller' => StatisticsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_system_variables' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/system-variables[/:action[/]]',
                    'defaults' => [
                        'controller' => SystemVariablesController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_tickets' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/tickets[/:action[/]]',
                    'defaults' => [
                        'controller' => TicketsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_trust_account_settings' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/trust-account-settings[/:action[/]]',
                    'defaults' => [
                        'controller' => TrustAccountSettingsController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_manage_vac' => [
                'type'    => Segment::class,
                'options' => [
                    'route'       => '/superadmin/manage-vac[/:action[/]]',
                    'defaults'    => [
                        'controller' => ManageVacController::class,
                        'action'     => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_url_checker' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/url-checker[/:action[/]]',
                    'defaults' => [
                        'controller' => UrlCheckerController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],

            'superadmin_zoho' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/superadmin/zoho[/:action[/]]',
                    'defaults' => [
                        'controller' => ZohoController::class,
                        'action' => 'index',
                    ],
                    'constraints' => [
                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                ],
            ],
        ],
    ],

    'controllers' => [
        'factories' => [
            AccessLogsController::class                  => AccessLogsControllerFactory::class,
            AccountsController::class                    => AccountsControllerFactory::class,
            AdvancedSearchController::class              => AdvancedSearchControllerFactory::class,
            AuthController::class                        => AuthControllerFactory::class,
            AutomatedBillingLogController::class         => AutomatedBillingLogControllerFactory::class,
            AutomaticReminderActionsController::class    => AutomaticReminderActionsControllerFactory::class,
            AutomaticReminderConditionsController::class => AutomaticReminderConditionsControllerFactory::class,
            AutomaticRemindersController::class          => AutomaticRemindersControllerFactory::class,
            AutomaticReminderTriggersController::class   => AutomaticReminderTriggersControllerFactory::class,
            ChangeMyPasswordController::class            => ChangeMyPasswordControllerFactory::class,
            ClientDocumentsController::class             => ClientDocumentsControllerFactory::class,
            CompanyWebsiteController::class              => CompanyWebsiteControllerFactory::class,
            ConditionalFieldsController::class           => ConditionalFieldsControllerFactory::class,
            DefaultSearchesController::class             => DefaultSearchesControllerFactory::class,
            ErrorController::class                       => ErrorControllerFactory::class,
            FormsController::class                       => FormsControllerFactory::class,
            FormsDefaultController::class                => FormsDefaultControllerFactory::class,
            FormsMapsController::class                   => FormsMapsControllerFactory::class,
            ImportBcpnpController::class                 => ImportBcpnpControllerFactory::class,
            ImportClientNotesController::class           => ImportClientNotesControllerFactory::class,
            ImportClientsController::class               => ImportClientsControllerFactory::class,
            IndexController::class                       => IndexControllerFactory::class,
            LastLoggedInController::class                => LastLoggedInControllerFactory::class,
            LetterheadsController::class                 => LetterheadsControllerFactory::class,
            ManageAdminUsersController::class            => ManageAdminUsersControllerFactory::class,
            ManageApplicantFieldsGroupsController::class => ManageApplicantFieldsGroupsControllerFactory::class,
            ManageBadDebtsLogController::class           => ManageBadDebtsLogControllerFactory::class,
            ManageBusinessHoursController::class         => ManageBusinessHoursControllerFactory::class,
            ManageCmiController::class                   => ManageCmiControllerFactory::class,
            ManageVacController::class                   => ManageVacControllerFactory::class,
            ManageCompanyAsAdminController::class        => ManageCompanyAsAdminControllerFactory::class,
            ManageCompanyController::class               => ManageCompanyControllerFactory::class,
            ManageCompanyCaseStatusesController::class   => ManageCompanyCaseStatusesControllerFactory::class,
            ManageCompanyProspectsController::class      => ManageCompanyProspectsControllerFactory::class,
            ManageDefaultAnalyticsController::class      => ManageDefaultAnalyticsControllerFactory::class,
            ManageDefaultMailServersController::class    => ManageDefaultMailServersControllerFactory::class,
            ManageDivisionsGroupsController::class       => ManageDivisionsGroupsControllerFactory::class,
            ManageFaqController::class                   => ManageFaqControllerFactory::class,
            ManageFieldsGroupsController::class          => ManageFieldsGroupsControllerFactory::class,
            ManageHstController::class                   => ManageHstControllerFactory::class,
            ManageInvoicesController::class              => ManageInvoicesControllerFactory::class,
            ManageMembersController::class               => ManageMembersControllerFactory::class,
            ManageMembersPuaController::class            => ManageMembersPuaControllerFactory::class,
            ManageOfficesController::class               => ManageOfficesControllerFactory::class,
            ManageOwnCompanyController::class            => ManageOwnCompanyControllerFactory::class,
            ManagePricingController::class               => ManagePricingControllerFactory::class,
            ManageProspectsController::class             => ManageProspectsControllerFactory::class,
            ManagePtErrorCodesController::class          => ManagePtErrorCodesControllerFactory::class,
            ManageRssFeedController::class               => ManageRssFeedControllerFactory::class,
            ManageTemplatesController::class             => ManageTemplatesControllerFactory::class,
            ManageTrialPricingController::class          => ManageTrialPricingControllerFactory::class,
            MarketplaceController::class                 => MarketplaceControllerFactory::class,
            NewsController::class                        => NewsControllerFactory::class,
            ProspectsMatchingController::class           => ProspectsMatchingControllerFactory::class,
            ResourcesController::class                   => ResourcesControllerFactory::class,
            RolesController::class                       => RolesControllerFactory::class,
            SharedTemplatesController::class             => SharedTemplatesControllerFactory::class,
            SmtpController::class                        => SmtpControllerFactory::class,
            StatisticsController::class                  => StatisticsControllerFactory::class,
            SystemVariablesController::class             => SystemVariablesControllerFactory::class,
            TicketsController::class                     => TicketsControllerFactory::class,
            TrustAccountSettingsController::class        => TrustAccountSettingsControllerFactory::class,
            UrlCheckerController::class                  => UrlCheckerControllerFactory::class,
            ZohoController::class                        => ZohoControllerFactory::class,
        ],
    ],

    // The following registers our custom view
    // helper classes in view plugin manager.
    'view_helpers' => [
        'factories' => [
            FormSearchLimit::class => InvokableFactory::class,
            FormSortArrows::class => InvokableFactory::class
        ],
        'aliases'   => [
            'formSearchLimit'   => FormSearchLimit::class,
            'formSortArrows'   => FormSortArrows::class,
        ]
    ],

    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions' => true,
        'template_map' => [
            'layout/superadmin_home' => __DIR__ . '/../view/layout/home.phtml',
            'layout/superadmin' => __DIR__ . '/../view/layout/superadmin.phtml',
            'website/boleo' => 'public/templates/boleo/index.php',
            'website/DK2' => 'public/templates/DK2/index.php',
            'website/gipo' => 'public/templates/gipo/index.php',
            'website/gourmet' => 'public/templates/gourmet/index.php',
            'website/prospect' => 'public/templates/prospect/index.php',
            'website/editTemplate' => 'public/templates/editTemplate/index.php',
            'website/viewTemplate' => 'public/templates/viewTemplate/index.php',
            'website/boleo/settings' => 'public/templates/boleo/settings.php',
            'website/DK2/settings' => 'public/templates/DK2/settings.php',
            'website/gipo/settings' => 'public/templates/gipo/settings.php',
            'website/gourmet/settings' => 'public/templates/gourmet/settings.php',
            'website/prospect/settings' => 'public/templates/prospect/settings.php',
            'website/editTemplate/settings' => 'public/templates/editTemplate/settings.php',
            'website/viewTemplate/settings' => 'public/templates/viewTemplate/settings.php',
        ],
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
];
