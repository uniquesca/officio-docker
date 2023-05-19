<?php

namespace Officio\Controller;

use Clients\Service\Clients;
use Exception;
use Officio\Common\Json;
use Laminas\ModuleManager\ModuleManager;
use Laminas\View\Helper\HeadLink;
use Laminas\View\Helper\HeadScript;
use Laminas\View\HelperPluginManager;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Mailer\Service\Mailer;
use Officio\Api2\Model\AccessToken;
use Officio\BaseController;
use Officio\Email\Models\MailAccount;
use Officio\Email\RabbitMqHelper;
use Officio\Service\AuthHelper;
use Officio\Service\Company;
use Officio\Service\GstHst;
use Officio\Service\Letterheads;
use Officio\Service\Navigation;
use Officio\Common\Service\Settings;
use Officio\Service\Sms;
use Officio\Service\SummaryNotifications;
use Officio\Service\SystemTriggers;
use Officio\Service\Users;
use Prospects\Service\CompanyProspects;
use Tasks\Service\Tasks;
use Officio\View\Helper\Minifier;


/**
 * IndexController - The default controller class
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class IndexController extends BaseController
{

    /** @var Company */
    protected $_company;

    /** @var RabbitMqHelper */
    private $_rabbitMqHelper;

    /** @var Users */
    protected $_users;

    /** @var Clients */
    protected $_clients;

    /** @var SummaryNotifications */
    protected $_summaryNotifications;

    /** @var AuthHelper */
    protected $_authHelper;

    /** @var GstHst */
    protected $_gstHst;

    /** @var Navigation */
    protected $_navigation;

    /** @var Letterheads */
    protected $_letterheads;

    /** @var CompanyProspects */
    protected $_companyProspects;

    /** @var Mailer */
    protected $_mailer;

    /** @var Sms */
    protected $_sms;

    /** @var SystemTriggers */
    protected $_triggers;

    /** @var Tasks */
    protected $_tasks;

    /** @var ModuleManager */
    protected $_moduleManager;

    /** @var HelperPluginManager */
    protected $_viewPluginManager;

    public function initAdditionalServices(array $services)
    {
        $this->_company              = $services[Company::class];
        $this->_clients              = $services[Clients::class];
        $this->_summaryNotifications = $services[SummaryNotifications::class];
        $this->_users                = $services[Users::class];
        $this->_authHelper           = $services[AuthHelper::class];
        $this->_gstHst               = $services[GstHst::class];
        $this->_navigation           = $services[Navigation::class];
        $this->_letterheads          = $services[Letterheads::class];
        $this->_companyProspects     = $services[CompanyProspects::class];
        $this->_mailer               = $services[Mailer::class];
        $this->_triggers             = $services[SystemTriggers::class];
        $this->_sms                  = $services[Sms::class];
        $this->_moduleManager        = $services[ModuleManager::class];
        $this->_viewPluginManager    = $services[HelperPluginManager::class];
        $this->_rabbitMqHelper       = $services[RabbitMqHelper::class];
        $this->_tasks                = $services[Tasks::class];
    }

    public function indexAction()
    {
        $view = new ViewModel();

        set_time_limit(5 * 60); // 5 minutes max!
        ini_set('memory_limit', '-1');

        /** @var HeadScript $headScript */
        $headScript = $this->_serviceManager->get('ViewHelperManager')->get('headScript');
        /** @var HeadLink $headLink */
        $headLink = $this->_serviceManager->get('ViewHelperManager')->get('headLink');

        $arrInlineScript      = array();
        $allowedPages         = array();
        $allowedClientSubTabs = array();
        $allowedMyDocsSubTabs = array();

        $arrExpirationStatuses = array('account_expired', 'trial_expired');
        $booExpired            = false;

        $booIsClient                  = $this->_auth->isCurrentUserClient();
        $currentMemberCompanyId       = $this->_auth->getCurrentUserCompanyId();
        $currentMemberDivisionGroupId = $this->_auth->getCurrentUserDivisionGroupId();
        $currentMemberId              = $this->_auth->getCurrentUserId();
        $arrMemberInfo                = $this->_members->getMemberInfo($currentMemberId);
        $arrCurrentUserInfo           = $this->_users->getUserInfo();

        if (!is_array($arrMemberInfo) || !count($arrMemberInfo) || !isset($arrMemberInfo['company_id'])) {
            $booExpired = true;
        } else {
            $oCompanySubscriptions = $this->_company->getCompanySubscriptions();
            $strSubscriptionNotice = $oCompanySubscriptions->checkCompanyStatus($arrMemberInfo);
            $oCompanySubscriptions->createSubscriptionCookie($strSubscriptionNotice);
            if (in_array($strSubscriptionNotice, $arrExpirationStatuses)) {
                $booExpired = true;
            }
        }

        if ($this->_auth->isCurrentUserAdmin()) {
            $daysAmount = intval($this->_config['security']['password_aging']['admin_lifetime']);
        } else {
            $daysAmount = intval($this->_config['security']['password_aging']['client_lifetime']);
        }
        $arrInlineScript[] = "var passwordValidDays = " . Json::encode($daysAmount) . ";";

        if (!$booExpired) {
            $arrAdmins  = $this->_company->getCompanyMembersIds($currentMemberCompanyId, 'admin');
            $arrUsers   = $this->_members->getMembersWhichICanAccess($this->_members::getMemberType('user'));
            $arrMembers = $this->_members->getMembersInfo(array_unique(array_merge($arrUsers, $arrAdmins)), true);

            $arrProfileSettings = array(
                'can_edit_profile' => $this->_acl->isAllowed('user-profile-view'),
                'can_change_name'  => !$booIsClient,
                'can_change_email' => $this->_members->canUpdateMemberEmailAddress($currentMemberId),
                'admin_links'      => $this->_navigation->getMainAdminNavigation()
            );
            $arrInlineScript[]  = "var userProfileSettings = " . Json::encode($arrProfileSettings) . ";";

            //###### HOME PAGE #######################
            if ($this->_acl->isAllowed('index-view')) {
                $booShowHomeTab      = false;
                $arrHomepageSettings = array();
                if (!$booIsClient && $this->_acl->isAllowed('clients-tasks-view')) {
                    $booShowHomeTab = true;
                }

                $arrQuickLinks        = null;
                $arrMouseOverSettings = null;
                if (!$booIsClient) {
                    $allowedPages[] = 'homepage-quick-menu';

                    $arrSavedSettings = isset($arrCurrentUserInfo['quick_menu_settings']) ? Json::decode($arrCurrentUserInfo['quick_menu_settings'], Json::TYPE_ARRAY) : [];
                    if (isset($arrSavedSettings['quick_links'])) {
                        // Use what the user selected
                        $arrQuickLinks = array_values($arrSavedSettings['quick_links']);
                    }

                    if (isset($arrSavedSettings['mouse_over_settings'])) {
                        // Use what the user checked
                        $arrMouseOverSettings = array_values($arrSavedSettings['mouse_over_settings']);
                    }
                }

                $arrHomepageSettings['settings'] = [
                    'quick_links'         => is_null($arrQuickLinks) ? ['applicants-tab', 'prospects-tab', 'tasks-tab', 'email-tab', 'calendar-tab', 'trustac-tab', 'lms-tab'] : $arrQuickLinks,
                    'mouse_over_settings' => is_null($arrMouseOverSettings) ? ['recently-viewed-menu'] : $arrMouseOverSettings
                ];

                // The same check as in the homepage controller
                if ($this->_acl->isAllowed('user-notes-view')) {
                    $booShowHomeTab = true;
                    $allowedPages[] = 'homepage-user-notes';
                }

                if ($this->_acl->isAllowed('links-view')) {
                    $booShowHomeTab = true;
                    $allowedPages[] = 'homepage-user-links';
                }

                // The toggle can be shown under the Tasks section
                $arrHomepageSettings['announcements'] = array(
                    'label'                        => $this->_tr->translate($this->_config['site_version']['homepage']['announcements']['label']),
                    'help'                         => $this->_tr->translate($this->_config['site_version']['homepage']['announcements']['help']),
                    'show_toggle'                  => !empty($this->_config['site_version']['homepage']['announcements']['show_toggle']) && $this->_acl->isAllowed('user-profile-view') && !$booIsClient,
                    'toggle_label'                 => $this->_tr->translate($this->_config['site_version']['homepage']['announcements']['toggle_label']),
                    'toggle_help'                  => $this->_tr->translate($this->_config['site_version']['homepage']['announcements']['toggle_help']),
                    'special_announcement_enabled' => !empty($this->_config['site_version']['homepage']['announcements']['special_announcement_enabled'])
                );

                if ($this->_acl->isAllowed('news-view')) {
                    $booShowHomeTab = true;
                    $allowedPages[] = 'homepage-announcements';
                }

                if ($this->_acl->isAllowed('rss-view')) {
                    $booShowHomeTab = true;
                    $allowedPages[] = 'homepage-rss';

                    $arrHomepageSettings['news'] = array(
                        'label' => $this->_tr->translate($this->_config['site_version']['homepage']['news']['label']),
                        'help'  => $this->_tr->translate($this->_config['site_version']['homepage']['news']['help'])
                    );
                }

                if ($booShowHomeTab) {
                    $allowedPages[] = 'homepage';
                }

                $arrInlineScript[] = "var arrHomepageSettings = " . Json::encode($arrHomepageSettings) . ";";
            }

            // Extra js + css files
            $headScript->appendFile($this->layout()->getVariable('baseUrl') . '/assets/plugins/dropzone/dist/min/dropzone.min.js');
            $headLink->appendStylesheet($this->layout()->getVariable('baseUrl') . '/assets/plugins/dropzone/dist/min/dropzone.min.css');

            $headLink->appendStylesheet($this->layout()->getVariable('baseUrl') . '/assets/notif/notif.css');
            $headScript->appendFile($this->layout()->getVariable('baseUrl') . '/assets/notif/notif.js');

            //###### TASKS TAB ####################
            if ($this->_acl->isAllowed('tasks-view') || $this->_acl->isAllowed('clients-tasks-view') || $this->_acl->isAllowed('clients-notes-view') || $this->_acl->isAllowed('prospects-tasks-view') || $this->_acl->isAllowed('prospects-notes-view')) {
                $headLink->appendStylesheet($this->layout()->getVariable('cssUrl') . '/tasks.css');

                if ($this->_acl->isAllowed('tasks-view')) {
                    $allowedPages[] = 'tasks';
                }

                // Add default option to the beginning
                $booLooseTaskRules = $this->_company->isLooseTaskRulesEnabledToCompany($arrMemberInfo['company_id']);

                // Load users list (current user can access to)
                $arrAllMemberIds = $this->_company->getCompanyMembersIds(
                    $currentMemberCompanyId,
                    'admin_and_user',
                    true
                );

                $arrTasksSettings = array(
                    'all_users'        => $this->_members->getMembersInfo($arrAllMemberIds, true),
                    'users'            => $arrMembers,
                    'clients'          => array(), // Will be loaded on new task dialog show
                    'loose_task_rules' => $booLooseTaskRules,
                );

                $arrInlineScript[] = "var arrTasksSettings = " . Json::encode($arrTasksSettings) . ";";
            }

            //###### CALENDAR PAGE ####################
            if ($this->_acl->isAllowed('calendar-view')) {
                $allowedPages[] = 'calendar';
            }

            //###### EMAIL PAGE ####################
            $extJsAccounts = array();

            if ($this->_acl->isAllowed('mail-view')) {
                if ($this->_config['mail']['enabled']) {
                    $accounts = MailAccount::getAccounts($currentMemberId);

                    #prepeare array to special view
                    foreach ($accounts as $acc) {
                        $extJsAccounts [] = array(
                            'account_id'       => $acc['id'],
                            'account_name'     => $acc['email'],
                            'signature'        => $acc['signature'],
                            'is_default'       => $acc['is_default'],
                            'auto_check'       => $acc['auto_check'],
                            'auto_check_every' => $acc['auto_check_every'],
                            'per_page'         => $acc['per_page'],
                            'inc_enabled'      => $acc['inc_enabled'],
                            'inc_type'         => $acc['inc_type'],
                        );
                    }
                }

                $allowedPages[] = 'email';
            }

            $headLink->appendStylesheet($this->layout()->getVariable('cssUrl') . '/mail.css');
            $headLink->appendStylesheet($this->layout()->getVariable('jsUrl') . '/fine-uploader/fine-uploader-gallery.css');

            $arrMailSettings = array(
                'hide_send_button' => (bool)$this->_config['mail']['hide_send_button'],
                'show_email_tab'   => $this->_acl->isAllowed('mail-module') && $this->_config['mail']['enabled'],
                'accounts'         => $extJsAccounts
            );

            $arrInlineScript[] = "var mail_settings = " . Json::encode($arrMailSettings) . ";";

            //###### PROSPECTS PAGE OR MARKETPLACE PAGE ####################
            $booHasAccessToProspects = $this->_acl->isAllowed('prospects-view');

            $oMarketplace = $this->_company->getCompanyMarketplace();

            $booHasAccessToMarketPlace = $this->_acl->isAllowed('marketplace-view') && $oMarketplace->isMarketplaceModuleEnabledToCompany($currentMemberCompanyId);
            if ($booHasAccessToProspects || $booHasAccessToMarketPlace) {
                $headLink->appendStylesheet($this->layout()->getVariable('cssUrl') . '/prospects.css');

                $arrAccess = array();

                if ($booHasAccessToProspects) {
                    $arrAccess['prospects'] = array(
                        'save_info'         => $this->_acl->isAllowed('prospects-edit'),
                        'assess'            => $this->_acl->isAllowed('prospects-edit') && $this->_config['site_version']['version'] != 'australia',
                        'add_new_prospect'  => $this->_acl->isAllowed('prospects-add'),
                        'mass_email'        => true,
                        'toolbar_email'     => false,
                        'convert_to_client' => $this->_acl->isAllowed('prospects-convert-to-client'),
                        'delete_prospect'   => $this->_acl->isAllowed('prospects-delete'),
                        'email'             => true,
                        'print'             => true,

                        'left_panel' => array(
                            'view_left_panel' => true,
                            'queue_panel'     => true,
                            'search_panel'    => true,
                            'today_prospects' => true,
                        ),

                        'tabs' => array(
                            'advanced_search' => array(
                                'view'       => $this->_acl->isAllowed('prospects-advanced-search-run'),
                                'mass_email' => true,
                                'print_all'  => $this->_acl->isAllowed('prospects-advanced-search-print'),
                                'export_all' => $this->_acl->isAllowed('prospects-advanced-search-export'),
                            ),

                            'tasks' => array(
                                'view'   => $this->_acl->isAllowed('prospects-tasks-view'),
                                'add'    => $this->_acl->isAllowed('prospects-tasks-add'),
                                'delete' => $this->_acl->isAllowed('prospects-tasks-delete'),
                            ),

                            'notes' => array(
                                'view'   => $this->_acl->isAllowed('prospects-notes-view'),
                                'add'    => $this->_acl->isAllowed('prospects-notes-add'),
                                'edit'   => $this->_acl->isAllowed('prospects-notes-edit'),
                                'delete' => $this->_acl->isAllowed('prospects-notes-delete'),
                            ),

                            'documents' => array(
                                'view' => $this->_acl->isAllowed('prospects-documents')
                            )
                        )
                    );
                }

                if ($booHasAccessToMarketPlace) {
                    $arrMPProfiles = $oMarketplace->getMarketplaceProfilesList(
                        $currentMemberCompanyId,
                        array(),
                        0,
                        0,
                        true
                    );

                    $booShowWarning = empty($arrMPProfiles['totalCount']);

                    if ($this->_config['site_version']['version'] == 'canada') {
                        $booShowWarning = $booShowWarning && !in_array($currentMemberCompanyId, array(1, 41));
                    }

                    $arrAccess['marketplace'] = array(
                        'show_warning'       => $booShowWarning,
                        'save_info'          => false,
                        'assess'             => false,
                        'add_new_prospect'   => false,
                        'mass_email'         => false,
                        'toolbar_email'      => true,
                        'manage_marketplace' => $this->_acl->isAllowed('manage-marketplace'),
                        'convert_to_client'  => $this->_acl->isAllowed('marketplace-convert-to-client'),
                        'delete_prospect'    => false,
                        'email'              => true,
                        'print'              => true,

                        'left_panel' => array(
                            'view_left_panel' => true,
                            'queue_panel'     => false,
                            'search_panel'    => true,
                            'today_prospects' => true,
                        ),

                        'tabs' => array(
                            'advanced_search' => array(
                                'view'       => $this->_acl->isAllowed('marketplace-advanced-search-run'),
                                'mass_email' => false,
                                'print_all'  => $this->_acl->isAllowed('marketplace-advanced-search-print'),
                                'export_all' => $this->_acl->isAllowed('marketplace-advanced-search-export'),
                            ),

                            'tasks' => array(
                                'view'   => false,
                                'add'    => false,
                                'delete' => false
                            ),

                            'notes' => array(
                                'view'   => $this->_acl->isAllowed('marketplace-notes-view'),
                                'add'    => $this->_acl->isAllowed('marketplace-notes-add'),
                                'edit'   => $this->_acl->isAllowed('marketplace-notes-edit'),
                                'delete' => $this->_acl->isAllowed('marketplace-notes-delete'),
                            ),

                            'documents' => array(
                                'view' => $this->_acl->isAllowed('marketplace-documents'),
                            )
                        )
                    );

                    $booDefaultInvitedTab = $this->_companyProspects->getCompanyInvitedProspectsCount() > 0;

                    $arrMarketplaceSettings = array(
                        'price_prospect_convert' => sprintf(
                            '%01.2f',
                            $this->_settings->variableGet(
                                'price_marketplace_prospect_convert'
                            )
                        ),

                        'default_tab' => $booDefaultInvitedTab ? 'invited' : 'all-prospects',
                    );

                    $arrInlineScript[] = "var arrMarketplaceSettings = " . Json::encode(
                            $arrMarketplaceSettings
                        ) . ";";
                }

                $arrProspectSettings = array(
                    'qnrJobSectionId'           => $this->_companyProspects->getCompanyQnr()->getQuestionnaireSectionJobId(),
                    'qnrSpouseJobSectionId'     => $this->_companyProspects->getCompanyQnr()->getQuestionnaireSpouseSectionJobId(),
                    'jobSearchFieldId'          => $this->_companyProspects->getCompanyQnr()->getFieldIdByUniqueId('qf_job_title'),
                    'jobNocSearchFieldId'       => $this->_companyProspects->getCompanyQnr()->getFieldIdByUniqueId('qf_job_noc'),
                    'jobSpouseSearchFieldId'    => $this->_companyProspects->getCompanyQnr()->getFieldIdByUniqueId('qf_job_spouse_title'),
                    'jobSpouseNocSearchFieldId' => $this->_companyProspects->getCompanyQnr()->getFieldIdByUniqueId('qf_job_spouse_noc'),
                    'arrAdvancedSearchFields'   => $this->_companyProspects->getCompanyQnr()->getAdvancedSearchFieldsPrepared(),
                    'arrAccess'                 => $arrAccess,
                    'exportRange'               => CompanyProspects::$exportProspectsLimit
                );

                $arrInlineScript[] = "var arrProspectSettings = " . Json::encode(
                        $arrProspectSettings
                    ) . ";";

                $arrInlineScript[] = "var qnrJobSectionId = " . Json::encode(
                        $this->_companyProspects->getCompanyQnr()->getQuestionnaireSectionJobId()
                    ) . ";";

                $arrInlineScript[] = "var qnrSpouseJobSectionId = " . Json::encode(
                        $this->_companyProspects->getCompanyQnr()->getQuestionnaireSpouseSectionJobId()
                    ) . ";";

                if ($booHasAccessToProspects) {
                    $allowedPages[] = 'prospects';
                }

                if ($booHasAccessToMarketPlace) {
                    $allowedPages[] = 'marketplace';
                }
            }

            $headLink->appendStylesheet(
                $this->layout()->getVariable('topBaseUrl') . '/assets/plugins/@fortawesome/fontawesome-free/css/all.min.css'
            );
            $headLink->appendStylesheet($this->layout()->getVariable('cssUrl') . '/kickstart-buttons.css');

            ###### Clients TAB ####################
            if ($this->_acl->isAllowed('clients-view')) {
                $headLink->appendStylesheet($this->layout()->getVariable('cssUrl') . '/applicants.css');

                $arrInlineScript[] = "var arrApplicantsSettings = " . Json::encode(
                        $this->_clients->getSettings(
                            $currentMemberId,
                            $currentMemberCompanyId,
                            $currentMemberDivisionGroupId
                        )
                    ) . ";";

                $caseReferenceNumberSeparator = '';

                if ($this->_clients->getCaseNumber()->isAutomaticTurnedOn($currentMemberCompanyId)) {
                    $caseNumberSettings = $this->_clients->getCaseNumber()->getCompanyCaseNumberSettings($currentMemberCompanyId);

                    $caseReferenceNumberSeparator = $caseNumberSettings['cn-separator'];
                }

                $arrInlineScript[] = "var booAutomaticTurnedOn = " . Json::encode(
                        $this->_clients->getCaseNumber()->isAutomaticTurnedOn($currentMemberCompanyId)
                    ) . ";";
                $arrInlineScript[] = "var caseReferenceNumberSeparator = " . Json::encode(
                        $caseReferenceNumberSeparator
                    ) . ";";

                $allowedPages[] = 'applicants';

                if ($this->_acl->isAllowed('contacts-view')) {
                    $allowedPages[] = 'contacts';
                }


                // Tasks Sub Tab
                if ($this->_acl->isAllowed('clients-tasks-view')) {
                    $allowedClientSubTabs[] = 'tasks';
                }

                //Notes Tabs
                if ($this->_acl->isAllowed('clients-notes-view') || $this->_acl->isAllowed('prospects-notes-view')) {
                    $arrNotesAccess = array();
                    if ($this->_acl->isAllowed('clients-notes-add')) {
                        $arrNotesAccess[] = 'booHiddenNotesAdd';
                    }

                    if ($this->_acl->isAllowed('clients-notes-edit')) {
                        $arrNotesAccess[] = 'booHiddenNotesEdit';
                    }

                    if ($this->_acl->isAllowed('clients-notes-delete')) {
                        $arrNotesAccess[] = 'booHiddenNotesDelete';
                    }

                    if ($this->_acl->isAllowed('clients-tasks-view')) {
                        $arrNotesAccess[] = 'booHiddenTaskAdd';
                    }

                    if ($this->_acl->isAllowed('clients-tasks-delete')) {
                        $arrNotesAccess[] = 'booHiddenClientsTasksDelete';
                    }

                    if ($this->_acl->isAllowed('tasks-delete')) {
                        $arrNotesAccess[] = 'booHiddenTasksDelete';
                    }

                    if ($this->_acl->isAllowed('tasks-view-users')) {
                        $arrNotesAccess[] = 'booTasksViewUsers';
                    }

                    if ($this->_acl->isAllowed('prospects-tasks-delete')) {
                        $arrNotesAccess[] = 'booHiddenProspectsTasksDelete';
                    }

                    $systemLogsCheckboxLabel = $this->_tr->translate('Show System Logs');
                    if (!empty($this->_config['site_version']['fe_api_username'])) {
                        $arrMemberInfo = $this->_clients->getMemberSimpleInfoByUsername($this->_config['site_version']['fe_api_username']);
                        if (!empty($arrMemberInfo['full_name'])) {
                            $systemLogsCheckboxLabel = sprintf(
                                $this->_tr->translate('Show %s entries'),
                                $arrMemberInfo['full_name']
                            );
                        }
                    }

                    $arrNotesToolbarOptions = [
                        'access'                  => $arrNotesAccess,
                        'systemLogsCheckboxLabel' => $systemLogsCheckboxLabel
                    ];

                    $arrInlineScript[] = "var arrNotesToolbarOptions = " . Json::encode($arrNotesToolbarOptions) . ";";

                    $allowedClientSubTabs[] = 'notes';
                    if ($this->_company->isDecisionRationaleTabAllowedToCompany($currentMemberCompanyId)) {
                        $allowedClientSubTabs[] = 'decision-rationale';
                        $arrCompanyDetailsInfo  = $this->_company->getCompanyDetailsInfo($currentMemberCompanyId);
                        if (isset($arrCompanyDetailsInfo['decision_rationale_tab_name']) && !empty($arrCompanyDetailsInfo['decision_rationale_tab_name'])) {
                            $decisionRationaleTabName = $arrCompanyDetailsInfo['decision_rationale_tab_name'];
                        } else {
                            $decisionRationaleTabName = $this->_tr->translate('Draft Notes');
                        }
                        $arrInlineScript[] = "var decisionRationaleTabName = " . Json::encode($decisionRationaleTabName) . ";";
                    }
                }

                // Accounting Sub Tab
                if ($this->_acl->isAllowed('clients-accounting-view')) {
                    $allowedClientSubTabs[] = 'accounting';
                }

                // Documents Sub Tab
                if ($this->_acl->isAllowed('client-documents-view')) {
                    $allowedClientSubTabs[] = 'documents';

                    // Access rights for specific functionality
                    $arrDocumentsAccess = array();
                    if ($this->_acl->isAllowed('client-documents-delete')) {
                        $arrDocumentsAccess[] = 'delete';
                    }

                    $arrInlineScript[] = "var arrDocumentsAccess = " . Json::encode($arrDocumentsAccess) . ";";
                }

                if ($this->_acl->isAllowed('client-documents-checklist-view')) {
                    $allowedClientSubTabs[] = 'documents_checklist';

                    // Access rights for specific functionality
                    $arrChecklistAccess = array();

                    if ($this->_acl->isAllowed('client-documents-checklist-upload')) {
                        $arrChecklistAccess[] = 'upload';
                    }
                    if ($this->_acl->isAllowed('client-documents-checklist-delete')) {
                        $arrChecklistAccess[] = 'delete';
                    }
                    if ($this->_acl->isAllowed('client-documents-checklist-download')) {
                        $arrChecklistAccess[] = 'download';
                    }
                    if ($this->_acl->isAllowed('client-documents-checklist-change-tags')) {
                        $arrChecklistAccess[] = 'tags';
                    }
                    if ($this->_acl->isAllowed('client-documents-checklist-reassign')) {
                        $arrChecklistAccess[] = 'reassign';
                    }
                    $arrInlineScript[] = "var arrDocumentsChecklistAccess = " . Json::encode($arrChecklistAccess) . ";";
                }


                if ($this->_acl->isAllowed('prospects-view') || $this->_acl->isAllowed('client-documents-view')) {
                    $arrDocumentsAccess = array();
                    if ($this->_acl->isAllowed('new-letter-on-letterhead')) {
                        $arrDocumentsAccess[] = 'new_letterhead';
                    }

                    $arrLetterheads = $this->_letterheads->getLetterheadsList(
                        $currentMemberCompanyId,
                        false
                    );

                    $arrDocumentsSettings = array(
                        'letterheads' => $arrLetterheads,
                        'access'      => $arrDocumentsAccess,
                    );

                    $arrInlineScript[] = "var arrDocumentsSettings = " . Json::encode(
                            $arrDocumentsSettings
                        ) . ";";
                }

                // Time Log sub tab
                if ($this->_acl->isAllowed('clients-time-tracker')) {
                    $arrTimeTrackerAccess = array();

                    $headLink->appendStylesheet($this->layout()->getVariable('cssUrl') . '/time_tracker.css');
                    if ($this->_acl->isAllowed('clients-time-tracker-popup-dialog')) {
                        $arrTimeTrackerAccess[] = 'show-popup';
                    }

                    if ($this->_acl->isAllowed('clients-time-tracker-show')) {
                        $allowedClientSubTabs[] = 'time_tracker';
                    }


                    if ($this->_acl->isAllowed('clients-time-tracker-add')) {
                        $arrTimeTrackerAccess[] = 'add';
                    }
                    if ($this->_acl->isAllowed('clients-time-tracker-edit')) {
                        $arrTimeTrackerAccess[] = 'edit';
                    }
                    if ($this->_acl->isAllowed('clients-time-tracker-delete')) {
                        $arrTimeTrackerAccess[] = 'delete';
                    }

                    $arrTimeTrackerSettings = array(
                        'rate'           => (float)$arrCurrentUserInfo['time_tracker_rate'],
                        'round_up'       => (int)$arrCurrentUserInfo['time_tracker_round_up'],
                        'tracker_enable' => $arrCurrentUserInfo['time_tracker_enable'],
                        'disable_popup'  => $arrCurrentUserInfo['time_tracker_disable_popup'],
                        'users'          => $arrMembers,
                        'access'         => $arrTimeTrackerAccess,
                        'arrProvinces'   => $this->_gstHst->getTaxesList()
                    );

                    $arrInlineScript[] = "var arrTimeTrackerSettings = " . Json::encode($arrTimeTrackerSettings) . ";";
                }

                // Forms Sub Tab
                if ($this->_acl->isAllowed('forms-view')) {
                    // Get toolbar options
                    $arrDefaultFormToolbarButtons = array();

                    // Only user can delete a form
                    if (!$booIsClient) {
                        $arrDefaultFormToolbarButtons[] = 'booHiddenFormsDelete';
                    }

                    if ($this->_acl->isAllowed('forms-complete')) {
                        $arrDefaultFormToolbarButtons[] = 'booHiddenFormsComplete';
                    }

                    if ($this->_acl->isAllowed('forms-assign')) {
                        $arrDefaultFormToolbarButtons[] = 'booHiddenFormsAssign';
                        $arrDefaultFormToolbarButtons[] = 'booHiddenFormsNew';
                    }

                    if ($this->_acl->isAllowed('forms-finalize') && $this->_acl->isAllowed('client-documents-view')) {
                        $arrDefaultFormToolbarButtons[] = 'booHiddenFormsFinalize';
                    }

                    if ($this->_acl->isAllowed('forms-lock-unlock')) {
                        $arrDefaultFormToolbarButtons[] = 'booHiddenFormsLockUnlock';
                    }

                    if ($this->_config['site_version']['version'] == 'australia') {
                        $arrDefaultFormToolbarButtons[] = 'booShowFormsQuestionnaire';
                    }
                    $arrInlineScript[] = "var arrFormShowToolbarOptions = " . Json::encode(
                            $arrDefaultFormToolbarButtons
                        ) . ";";

                    $allowedClientSubTabs[] = 'forms';
                }
            }

            if ($booHasAccessToProspects || $booHasAccessToMarketPlace || $this->_acl->isAllowed('clients-view')) {
                $advancedSearchRowsMaxCount = $this->_company->getCompanyDetailsInfo($currentMemberCompanyId);

                $arrInlineScript[] = "var advancedSearchRowsMaxCount = " . Json::encode(
                        (int)$advancedSearchRowsMaxCount['advanced_search_rows_max_count']
                    ) . ";";
            }

            //####### Client A/C ####################
            if ($this->_acl->isAllowed('trust-account-view')) {
                // Load Client Accounts for current user's company
                $arrCompanyTA              = $this->_clients->getAccounting()->getCompanyTA($currentMemberCompanyId);
                $arrCompanyTAIdsWithAccess = $this->_clients->getAccounting()->getCompanyTAIdsWithAccess();

                $arrTATabs = array();
                if (is_array($arrCompanyTA) && !empty($arrCompanyTA)) {
                    foreach ($arrCompanyTA as $taInfo) {
                        if (in_array($taInfo['company_ta_id'], $arrCompanyTAIdsWithAccess)) {
                            $arrTATabs[] = array(
                                'company_ta_id'        => $taInfo['company_ta_id'],
                                'tabId'                => 'ta_tab_' . $taInfo['company_ta_id'],
                                'title'                => $taInfo['name'],
                                'currency'             => $taInfo['currency'],
                                'currency_label'       => $taInfo['currencyLabel'],
                                'view_ta_months'       => $taInfo['view_transactions_months'],
                                'last_reconcile'       => $taInfo['last_reconcile'] === '0000-00-00' ? '' : $taInfo['last_reconcile'],
                                'last_reconcile_iccrc' => $taInfo['last_reconcile_iccrc'] === '0000-00-00' ? '' : $taInfo['last_reconcile_iccrc'],
                            );
                        }
                    }
                }
                $arrInlineScript[] = "var displayRecordsOnTrustAccountPage = 24;";
                $arrInlineScript[] = "var arrTATabs = " . Json::encode($arrTATabs) . ";";
                $arrInlineScript[] = "var paymentMadeByOptions = " . Json::encode(
                        $this->_clients->getAccounting()->getTrustAccount()->getPaymentMadeByOptions()
                    ) . ";";

                $allowedPages[] = 'trustac';
            }

            //####### MY DOCUMENTS #########################
            if ($this->_acl->isAllowed('my-documents-view') || $this->_acl->isAllowed('templates-manage')) {
                if ($this->_acl->isAllowed('my-documents-view')) {
                    $allowedMyDocsSubTabs[] = 'documents';
                }

                if ($this->_acl->isAllowed('templates-manage')) {
                    $allowedMyDocsSubTabs[] = 'templates';
                }

                $allowedPages[] = 'mydocs';
            }

            if ($this->_acl->isAllowed('manage-company-prospects')) {
                $headLink->appendStylesheet(
                    $this->layout()->getVariable('topBaseUrl') . '/superadmin/styles/company_prospects.css'
                );
                $allowedPages[] = 'prospects-templates';
            }

            if ($this->_acl->isAllowed('templates-view')) {
                $allowedPages[] = 'templates-view';
            }

            //####### ADMIN ################################
            if ($this->_auth->isCurrentUserAdmin()) {
                $allowedPages[] = 'admin';
            }


            //####### Clients time log summary #######################
            if ($this->_acl->isAllowed('clients-time-log-summary')) {
                $arrTimeTrackerSettings = array(
                    'users' => $arrMembers
                );

                $arrInlineScript[] = "var arrTimeLogSummarySettings = " . Json::encode(
                        $arrTimeTrackerSettings
                    ) . ";";

                $allowedPages[] = 'time-log-summary';
            }

            //####### HELP #######################
            if ($this->_acl->isAllowed('help-view')) {
                $allowedPages[] = 'help';
            }


            //####### LMS #######################
            $booShowAcademyTab = $this->_users->isLmsEnabled(true) && !$booIsClient;
            if ($booShowAcademyTab) {
                $allowedPages[] = 'lms';
            }

            $arrLMSSettings = array(
                'url'       => $this->_config['lms']['url'],
                'test_mode' => (bool)$this->_config['lms']['test_mode']
            );

            $arrInlineScript[] = "var lmsSettings = " . Json::encode($arrLMSSettings) . ";";
        }

        //save list of available tabs
        $arrInlineScript[] = "var is_client = " . Json::encode($booIsClient) . ";";
        $arrInlineScript[] = "var is_authorized_agent_enabled = " . Json::encode($this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled()) . ";";
        $arrInlineScript[] = "var post_max_size = " . Json::encode($this->_settings->returnBytes(ini_get('post_max_size'))) . ";";
        $arrInlineScript[] = "var allowedPages = " . Json::encode($allowedPages) . ";";
        $arrInlineScript[] = "var allowedClientSubTabs = " . Json::encode($allowedClientSubTabs) . ";";
        $arrInlineScript[] = "var passwordMinLength = " . $this->_settings->passwordMinLength . ";";
        $arrInlineScript[] = "var passwordMaxLength = " . $this->_settings->passwordMaxLength . ";";
        $arrInlineScript[] = "var booPreviewFilesInNewBrowser = " . intval($this->_config['site_version']['preview_files_in_new_browser']) . ";";

        if (in_array('mydocs', $allowedPages)) {
            $arrInlineScript[] = "var allowedMyDocsSubTabs = " . Json::encode($allowedMyDocsSubTabs) . ";";
        }

        if (!empty($arrInlineScript)) {
            $headScript->appendScript(implode("\n", $arrInlineScript));
        }

        $this->layout()->setVariable('booShowHelpTab', in_array('help', $allowedPages));
        $this->layout()->setVariable('booShowAdminTab', !$booExpired && in_array('admin', $allowedPages));

        // Use company's logo instead of the Officio logo for the logged in client
        $companyLogoLink = '';
        if ($booIsClient) {
            $arrCompanyInfo  = $this->_company->getCompanyInfo($currentMemberCompanyId);
            $companyLogoLink = $this->_company->getCompanyLogoLink($arrCompanyInfo);
        }
        $this->layout()->setVariable('companyLogoLink', $companyLogoLink);

        return $view;
    }

    public function generateLmsUrlAction()
    {
        $url      = '';
        $strError = '';

        try {
            $redirectUrl = Json::decode($this->params()->fromPost('redirectUrl', ''));

            // Check if the redirect url starts with our LMS url
            $booValidUrl = !empty($redirectUrl) && !empty($this->_config['lms']['url']) && (substr($redirectUrl, 0, strlen($this->_config['lms']['url'] ?? '')) === $this->_config['lms']['url']);
            if (!$booValidUrl) {
                $redirectUrl = '';
            }

            $url = $this->_users->getLmsLoginUrl($this->_auth->getCurrentUserId(), $redirectUrl);
            if (empty($url)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResponse = array(
            'url'     => $url,
            'success' => empty($strError) && !empty($url),
            'message' => $strError
        );

        return new JsonModel($arrResponse);
    }

    /**
     * Examples:
     * https://secure.officio.ca/default/index/cron?p=0 -- each hour
     * https://secure.officio.ca/default/index/cron?p=1 -- each day
     * https://secure.officio.ca/default/index/cron?p=2 -- each week
     * https://secure.officio.ca/default/index/cron?p=3 -- every 5 minutes (except of 00 - because will be processed by the first cron)
     * https://secure.officio.ca/default/index/cron?p=4 -- each month
     * https://secure.officio.ca/default/index/cron?p=8 -- calculate companies storage usage
     */
    public function cronAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        set_time_limit(0);
        session_write_close();
        ini_set('memory_limit', '-1');

        try {
            $output = '';
            $period = (int)$this->findParam('p', -1);

            switch ($period) {
                case 0 : // 1 hour
                    // Wipe all access tokens which are not connected to a session anymore
                    if ($this->_moduleManager->getModule('Officio\\Api2')) {
                        AccessToken::cleanupTokens();
                    }

                    // Process automatic tasks which trigger is "Cron"
                    $this->_triggers->triggerCronReminders();

                    $this->_tasks->triggerTaskIsDue();

                    if ($this->_config['sms']['enabled']) {
                        $this->_triggers->triggerTaskSms();
                        $this->_sms->send();
                    }

                    $this->_log->saveToCronLog("---cron 1 hour");
                    break;

                case 1 : // 1 day
                    // Check for profile dates (PS records based on client date fields)
                    $this->_triggers->triggerProfileDateFieldChanged();

                    // Check for PS records based on specific dates
                    $this->_triggers->triggerPaymentScheduleDateIsDue();

                    // Process scheduled automatic reminders and run due actions
                    $this->_triggers->triggerProcessScheduledReminderActions();

                    // Delete expired hashes
                    $this->_authHelper->deleteExpiredHashes();

                    // Expire all abandoned file number reservations
                    $this->_clients->getCaseNumber()->expireAbandonedFileNumberReservations(time() - 3600);

                    // Remove all default options if they were marked as deleted before and are not used
                    $this->_clients->getFields()->clearAllDeletedNotUsedOptions();

                    // Send daily email notification to all users
                    $output .= $this->_summaryNotifications->sendNotificationsToUsers();
                    $output .= '<br>';

                    $this->_log->saveToCronLog("---cron 1 day");
                    break;

                case 2 : // 1 week
                    $this->_log->saveToCronLog("---cron 1 week");
                    break;

                case 3 : // every 5 minutes
                    $this->_members->updateLastAccessTimeByCron();
                    $this->_log->saveToCronLog("---cron 5 min");
                    break;

                case 4 : // each month
                    if ($this->_config['site_version']['version'] == 'canada') {
                        $arrCompanies = $this->_company->getAllCompanies();

                        foreach ($arrCompanies as $company) {
                            $companyId          = $company[0];
                            $arrCompanySettings = $this->_clients->getCaseNumber()->getCompanyCaseNumberSettings($companyId);

                            if (isset($arrCompanySettings['cn-start-number-from']) && $arrCompanySettings['cn-start-number-from'] == 'on' && isset($arrCompanySettings['cn-reset-every'])) {
                                if ($arrCompanySettings['cn-reset-every'] == 'month' || ($arrCompanySettings['cn-reset-every'] == 'year' && date(
                                            'z'
                                        ) === '0')) {
                                    if (isset($arrCompanySettings['cn-start-number-from-text'])) {
                                        $counterLength = strlen($arrCompanySettings['cn-start-number-from-text'] ?? '');

                                        $arrCompanySettings['cn-start-number-from-text'] = str_pad(
                                            '1',
                                            $counterLength,
                                            '0',
                                            STR_PAD_LEFT
                                        );
                                    } else {
                                        $arrCompanySettings['cn-start-number-from-text'] = '0001';
                                    }
                                    if ($this->_clients->getCaseNumber()->saveCaseNumberSettings($companyId, $arrCompanySettings)) {
                                        $this->_log->saveToCronLog(
                                            "case file number counter reset to " . $arrCompanySettings['cn-start-number-from-text'] . ' for company_id = ' . $companyId
                                        );
                                    }
                                }
                            }
                        }
                    }


                    $this->_log->saveToCronLog("---cron 1 month");
                    break;

                case 5 : // launch one time on  twenty-four hours to send to RabbitMQ sequence of account ids to update
                    $this->sendMessageAction();
                    break;

                case 6 : // launch every period of time to update in few flows mail accounts
                    if ($this->_rabbitMqHelper) {
                        $this->receiveMessage(RabbitMqHelper::CRON_QUEUE);
                    }
                    break;

                case 7: // launch from user queue ->will be done in future
                    if ($this->_rabbitMqHelper) {
                        $this->receiveMessage(RabbitMqHelper::USER_QUEUE);
                    }
                    break;

                case 8: // calculate companies storage usage
                    $this->_company->calculateCompaniesStorageUsage();
                    $this->_log->saveToCronLog("---cron calculate companies storage usage");
                    break;
            }
        } catch (Exception $e) {
            $output = $this->_tr->translate('Internal Error. Please contact to web site support.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariable('content', $output);
    }

    public function termsAction()
    {
        $view = new ViewModel(
            [
                'content' => Settings::urlGetContents($this->layout()->getVariable('officioBaseUrl') . '/terms.html')
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }

    public function privacyAction()
    {
        $view = new ViewModel(
            [
                'content' => Settings::urlGetContents($this->layout()->getVariable('officioBaseUrl') . '/privacy.html')
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }


    public function sendMessageAction()
    {
        try {
            $arrActiveCompanyIds = $this->_company->getCompaniesWithBillingDateCheck(true);
            $arrActiveUsersIds   = $this->_users->getCompanyActiveUsers($arrActiveCompanyIds);

            $arrActiveMailAccountIds = $this->_mailer->getActiveMailAccountsForRabbit($arrActiveUsersIds);

            if (is_array($arrActiveMailAccountIds) && count($arrActiveMailAccountIds)) {
                if ($this->_rabbitMqHelper) {
                    MailAccount::repairDbForRabbit();
                    $cronId = $this->_mailer->getMailerLog()->insertIntoEmlCronTable(count($arrActiveMailAccountIds), time());
                    if (!empty($cronId)) {
                        foreach ($arrActiveMailAccountIds as $accountId) {
                            // Prepare data for RabbitMQ in special format
                            $dataForUpdate = $this->_rabbitMqHelper->push(
                                $accountId,
                                $cronId,
                                RabbitMqHelper::CRON_QUEUE
                            );

                            $mailAccountManager = new MailAccount($accountId);
                            $mailAccountManager->updateAccountDetails($dataForUpdate);
                        }
                    }
                    $this->_rabbitMqHelper->close();
                } else {
                    $this->_mailer->getMailerLog()->insertIntoEmlCronTable(count($arrActiveMailAccountIds), time());
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view = new ViewModel();
        $view->setTerminal(true);
        return $view;
    }

    public function receiveMessage($type)
    {
        if ($this->_rabbitMqHelper) {
            try {
                $callback = function ($msg) {
                    list($accountId, $cronId) = explode('|', $msg->body);
                    if (!empty($accountId)) {
                        $mailAccountManager = new MailAccount((int)$accountId);
                        $arrAccountInfo     = $mailAccountManager->getAccountDetails();

                        echo 'checking email for id=' . $accountId . "\n";
                        echo 'memberId=' . $arrAccountInfo['member_id'] . "\n";
                        if (!$mailAccountManager->isCheckInProgress()) {
                            $updateId = $this->_mailer->getMailerLog()->insertIntoEmlCronAccountsTable($cronId, $accountId, time());
                            $this->_mailer->checkAccount($accountId);
                            $this->_mailer->getMailerLog()->updateEmlCronAccountsTable($updateId);
                        }

                        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);

                        $dataForUpdate = array(
                            'last_rabbit_pull' => time()
                        );

                        $mailAccountManager->updateAccountDetails($dataForUpdate);
                    }
                };
                echo ' [*] Waiting for messages. To exit press CTRL+C';
                $this->_rabbitMqHelper->pull($type, $callback);
                $this->_rabbitMqHelper->close();
            } catch (Exception $e) {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }
        }

        $view = new ViewModel();
        $view->setTerminal(true);
        return $view;
    }

    public function minAction()
    {
        $view = new ViewModel(
            [
                'content' => null
            ]
        );
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        $group = $this->params()->fromQuery('g');
        /** @var Minifier $minifier */
        $minifier = $this->_viewPluginManager->get('minifier');
        $content  = $minifier->minify($group, true);
        if ($content === false) {
            $this->getResponse()->setStatusCode(500);
        } else {
            $view->setVariable('content', $content);
        }

        return $view;
    }
}
