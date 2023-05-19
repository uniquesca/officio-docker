<?php

namespace Superadmin\Controller;

use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;

/**
 * Index controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class IndexController extends BaseController
{

    /** @var Company */
    protected $_company;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
    }

    /**
     * The default action - show the home page
     */
    public function indexAction ()
    {
        $view = new ViewModel();

        if ($this->_auth->isCurrentUserSuperadmin()) {
            return $this->redirect()->toUrl('/superadmin/index/home');
        }

        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append('Home');

        return $view;
    }

    /**
     * The default action - show the home page
     */
    public function homeAction ()
    {
        $view = new ViewModel();

        ini_set('memory_limit', '-1');

        // Load users list (current user can access to)
        $arrMembersCanAccess  = $this->_company->getCompanyMembersIds(0, 'superadmin', true);
        $arrMembers           = $this->_members->getMembersInfo($arrMembersCanAccess, true, 'superadmin');
        $arrCompanies         = $this->_company->getAllCompanies();
        $arrAllCompaniesUsers = $this->_members->getAllCompaniesMembersIds('admin_and_user');

        $currentMemberId = $this->_auth->getCurrentUserId();

        $clients_list = array(
            array('clientId' => 0, 'clientName' => 'General Task', 'clientFullName' => 'General Task')
        );

        $arrTasksSettings = array(
            'users'   => $arrMembers,
            'dates'   => array(),
            'clients' => $clients_list,
        );
        $view->setVariable('arrCompanies', $arrCompanies);
        $view->setVariable('arrAllCompaniesUsers', $arrAllCompaniesUsers);
        $view->setVariable('arrTasksSettings', $arrTasksSettings);
        $view->setVariable('currentMemberId', $currentMemberId);

        $arrAccessRights = array(
            'admin_tab'         => $this->_acl->isAllowed('admin-tab-view'),
            'add'               => $this->_acl->isAllowed('manage-company-add'),
            'edit'              => $this->_acl->isAllowed('manage-company-edit'),
            'delete'            => $this->_acl->isAllowed('manage-company-delete'),
            'email'             => $this->_acl->isAllowed('manage-company-email'),
            'mass-email'        => $this->_acl->isAllowed('mass-email'),
            'login'             => $this->_acl->isAllowed('manage-company-as-admin'),
            'change_status'     => $this->_acl->isAllowed('manage-company-change-status'),
            'view_companies'    => $this->_acl->isAllowed('manage-company-view-companies'),
            'advanced_search'   => $this->_acl->isAllowed('manage-advanced-search'),
            'tasks-view'        => $this->_acl->isAllowed('tasks-view'),
            'calendar-view'     => $this->_acl->isAllowed('calendar-view'),
            'my-documents-view' => $this->_acl->isAllowed('my-documents-view'),
            'templates-manage'  => $this->_acl->isAllowed('templates-manage'),
            'manage-templates'  => $this->_acl->isAllowed('manage-templates'),
            'tasks-delete'      => $this->_acl->isAllowed('tasks-delete'),
            'tasks-view-users'  => $this->_acl->isAllowed('tasks-view-users'),
            'companies_search'  => $this->_acl->isAllowed('run-companies-search'),
        );

        $arrTicketsAccessRights = array(
            'tickets'       => $this->_acl->isAllowed('manage-company-tickets'),
            'add'           => $this->_acl->isAllowed('manage-company-tickets-add'),
            'change_status' => $this->_acl->isAllowed('manage-company-tickets-status')
        );

        $view->setVariable('arrTicketsAccessRights', $arrTicketsAccessRights);
        $view->setVariable('arrAccessRights', $arrAccessRights);
        $view->setVariable('booHasAccessToMail', $this->_acl->isAllowed('mail-view'));
        $view->setVariable('booMailEnabled', $this->_serviceManager->get('config')['mail']['enabled']);
        $view->setVariable('currentMemberTimeZone', $this->_auth->getCurrentUserCompanyTimezone());
        $view->setVariable('isAuthorizedAgentsManagementEnabled', $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled());
        $view->setVariable('passwordMinLength', $this->_settings->passwordMinLength);
        $view->setVariable('passwordMaxLength', $this->_settings->passwordMaxLength);

        $arrProfileSettings = array();
        if ($this->_acl->isAllowed('user-profile-view')) {
            $arrProfileSettings = array(
                'can_change_name'  => !$this->_auth->isCurrentUserClient(),
                'can_change_email' => $this->_members->canUpdateMemberEmailAddress($currentMemberId)
            );
        }
        $view->setVariable('arrProfileSettings', $arrProfileSettings);

        $view->setVariable('arrSearchFields', array_values($this->_getSearchFieldsList()));
        $view->setVariable('arrSearchFilters', $this->_getSearchFiltersList());
        $view->setVariable('config', $this->_serviceManager->get('config'));

        return $view;
    }

    /**
     * Load fields list for advanced search
     *
     * @return array
     */
    private function _getSearchFieldsList() {
        $arrSearchFields = array(
            array('company_status',        'Status',              'company_status'),
            array('company_purged',        'Company Data Purged', 'yes_no'),
            array('company_name',          'Company Name',        'text'),
            array('company_abn',           'Company ABN',         'text'),
            array('company_address',       'Address',             'text'),
            array('company_city',          'City',                'text'),
            array('company_country',       'Country',             'text'),
            array('company_state',         'State',               'text'),
            array('company_zip',           'Zip',                 'text'),
            array('company_phone1',        'Phone 1',             'text'),
            array('company_phone2',        'Phone 2',             'text'),
            array('company_email',         'Email',               'text'),
            array('company_fax',           'Fax',                 'text'),
            array('company_note',          'Note',                'text'),
            array('company_freetrial_key', 'Free Trial Key',      'text'),
            array('company_last_logged_in','Last Logged In',      'date'),


            array('company_subscription',          'Subscription Plan',           'text'),
            array('company_account_trial',         'Account Is Trial',            'yes_no'),
            array('company_account_created_on',    'Account Creation Date',       'short_date'),
            array('company_setup_on',              'Setup Date',                  'date'),
            array('company_next_billing_date',     'Next Billing Date',           'short_date'),
            array('company_billing_frequency',     'Billing Frequency',           'billing_frequency'),
            array('company_free_users_included',   'Free Users Included',         'number'),
            array('company_free_clients_included', 'Free Clients Included',       'number'),
            array('company_free_storage_included', 'Free Storage in GB Included', 'number'),
            array('company_subscription_fee',      'Subscription Recurring Fee',  'float'),
            array('company_active_users',          'Active Users',                'number'),
            array('company_clients_count',         'Clients Count',               'number'),
            array('company_pt_id',                 'Paymentech ID',               'text'),
            array('company_internal_note',         'Internal Note',               'text'),


            array('company_trust_account_uploaded', 'Last ' . $this->_company->getCurrentCompanyDefaultLabel('trust_account') . ' Uploaded Date', 'short_date'),
            array('company_accounting_updated',     'Last Accounting Updated Date',      'date'),
            array('company_notes_written',          'Last Notes Written Date',           'date'),
            array('company_task_written',           'Last Task Written Date',            'date'),
            array('company_check_email_pressed',    'Last Check Email Was Pressed Date', 'date'),
            array('company_adv_search',             'Last Advanced Search Date',         'date'),
            array('company_mass_email',             'Last Mass Email Date',              'date'),
            array('company_document_uploaded',      'Last Document Uploaded Date',       'date'),
        );

        if (empty($this->_config['site_version']['check_abn_enabled'])) {
            foreach ($arrSearchFields as $key => $arrSearchFieldInfo) {
                if ($arrSearchFieldInfo[0] == 'company_abn') {
                    unset($arrSearchFields[$key]);
                    break;
                }
            }
        }

        return $arrSearchFields;
    }

    private function _getSearchFiltersList() {
        return array(
            'yes_no' => array(
                array('yes', 'Yes'),
                array('no',  'No')
            ),

            'company_status' => array(
                array($this->_company->getCompanyIntStatusByString('inactive'), 'Inactive'),
                array($this->_company->getCompanyIntStatusByString('active'), 'Active'),
                array($this->_company->getCompanyIntStatusByString('suspended'), 'Suspended')
            ),

            'billing_frequency' => array(
                array(0, 'Not set'),
                array(1, 'Monthly'),
                array(2, 'Annually'),
                array(3, 'Biannually'),
            ),

            'number' => array(
                array('equal',         '='),
                array('not_equal',     '<>'),
                array('less',          '<'),
                array('less_or_equal', '<='),
                array('more',          '>'),
                array('more_or_equal', '>='),
            ),

            'text' => array(
                array('contains',         'contains'),
                array('does_not_contain', "does not contain"),
                array('is',               'is'),
                array('is_not',           "is not"),
                array('starts_with',      'starts with'),
                array('ends_with',        'ends with'),
                array('is_empty',         'is empty'),
                array('is_not_empty',     'is not empty'),
            ),

            'date' => array(
                array('is',                                'is'),
                array('is_not',                            "is not"),
                array('is_before',                         'is before'),
                array('is_after',                          'is after'),
                array('is_empty',                          'is empty'),
                array('is_not_empty',                      'is not empty'),
                array('is_between_2_dates',                'is between 2 dates'),
                array('is_between_today_and_date',         'is between today and date'),
                array('is_between_date_and_today',         'is between a date and today'),
                array('is_since_start_of_the_year_to_now', 'is since the start of the year to now'),
                array('is_from_today_to_the_end_of_year',  'is from today to the end of the year'),
                array('is_in_this_month',                  'is in this month'),
                array('is_in_this_year',                   'is in this year'),
                array('is_in_next_days',                   'is in next X days'),
                array('is_in_next_months',                 'is in next X months'),
                array('is_in_next_years',                  'is in next X years'),
            )
        );
    }

}