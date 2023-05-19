<?php

namespace Superadmin\Controller;

use Clients\Service\Analytics;
use Clients\Service\Clients;
use Exception;
use Files\BufferedStream;
use Files\Model\FileInfo;
use Files\Service\Files;
use Laminas\Db\Sql\Select;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Helper\HeadScript;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Laminas\View\Renderer\PhpRenderer;
use Mailer\ExportEmailsLoaderDispatcher;
use Mailer\Service\Mailer;
use Officio\BaseController;
use Officio\Common\Service\Encryption;
use Officio\Email\Models\Folder;
use Officio\Email\Models\MailAccount;
use Officio\Email\Models\Message;
use Officio\Service\AutomaticReminders;
use Officio\Service\Company;
use Officio\Service\Company\CompanyInvoice;
use Officio\Common\Service\Country;
use Officio\Service\GstHst;
use Officio\Service\Payment\PaymentServiceInterface;
use Officio\Service\Tickets;
use Officio\Service\Users;
use Officio\Templates\Model\SystemTemplate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Prospects\Service\CompanyProspects;
use Prospects\Service\Prospects;
use Officio\Templates\SystemTemplates;
use Uniques\Php\StdLib\FileTools;

/**
 * Manage Company Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ManageCompanyController extends BaseController
{

    /** @var Company */
    protected $_company;

    /** @var Files */
    protected $_files;

    /** @var Tickets */
    protected $_tickets;

    /** @var StripTags */
    private $_filter;

    /** @var Analytics */
    protected $_analytics;

    /** @var Clients */
    protected $_clients;

    /** @var Users */
    protected $_users;

    /** @var GstHst */
    protected $_gstHst;

    /** @var Country */
    protected $_country;

    /** @var AutomaticReminders */
    protected $_automaticReminders;

    /** @var Prospects */
    protected $_prospects;

    /** @var CompanyProspects */
    protected $_companyProspects;

    /** @var Mailer */
    protected $_mailer;

    /** @var SystemTemplates */
    protected $_systemTemplates;

    /** @var PaymentServiceInterface */
    protected $_payment;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_analytics          = $services[Analytics::class];
        $this->_users              = $services[Users::class];
        $this->_clients            = $services[Clients::class];
        $this->_company            = $services[Company::class];
        $this->_automaticReminders = $services[AutomaticReminders::class];
        $this->_gstHst             = $services[GstHst::class];
        $this->_country            = $services[Country::class];
        $this->_prospects          = $services[Prospects::class];
        $this->_tickets            = $services[Tickets::class];
        $this->_files              = $services[Files::class];
        $this->_companyProspects   = $services[CompanyProspects::class];
        $this->_mailer             = $services[Mailer::class];
        $this->_systemTemplates    = $services[SystemTemplates::class];
        $this->_payment            = $services['payment'];
        $this->_encryption         = $services[Encryption::class];

        $this->_filter = new StripTags();
    }

    /**
     * Search for companies
     *
     * output string in specific format (not simple json)
     */
    public function companySearchAction()
    {
        // Get incoming params
        $strQuery    = $this->_filter->filter(trim($this->params()->fromPost('query', '')));
        $strCallback = $this->params()->fromPost('callback');
        $start       = (int)$this->params()->fromPost('start', 0);
        $limit       = (int)$this->params()->fromPost('limit', 25);

        list($totalCount, $arrCompanies,) = $this->_company->getCompanies(
            $strQuery,
            array(),
            $start,
            $limit,
            false,
            'ASC',
            'company_name'
        );

        $arrResult = array(
            'rows'       => $arrCompanies,
            'totalCount' => $totalCount
        );

        // Return result in specific format (not simple Json!)
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');
        return $view->setVariables(['content' => $strCallback . '(' . Json::encode($arrResult) . ')']);
    }

    public function getCompaniesAction()
    {
        try {
            // Search for companies with these advanced search fields
            $advancedSearchParams = $this->params()->fromPost('advanced_search_params', '');
            if (!empty($advancedSearchParams)) {
                try {
                    $advancedSearchParams = $this->_filter->filter($advancedSearchParams);
                    $advancedSearchParams = Json::decode($advancedSearchParams, Json::TYPE_ARRAY);
                } catch (Exception $e) {
                    $advancedSearchParams = array();
                    $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
                }
            }

            $dir      = $this->_filter->filter($this->params()->fromPost('dir'));
            $sort     = $this->params()->fromPost('sort');
            $start    = (int)$this->params()->fromPost('start', 0);
            $limit    = (int)$this->params()->fromPost('limit', 25);
            $strQuery = $this->_filter->filter(trim($this->params()->fromPost('query', '')));

            $booShowLastLoginColumn = (bool)$this->params()->fromPost('booShowLastLoginColumn');


            list($totalRecords, $arrCompanies, $arrAllCompaniesIds) = $this->_company->getCompanies(
                $strQuery,
                $advancedSearchParams,
                $start,
                $limit,
                $booShowLastLoginColumn,
                $dir,
                $sort
            );
        } catch (Exception $e) {
            $totalRecords       = 0;
            $arrCompanies       = array();
            $arrAllCompaniesIds = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return Json result
        $arrResult = array(
            'rows'       => $arrCompanies,
            'all_ids'    => $arrAllCompaniesIds,
            'totalCount' => $totalRecords
        );

        return new JsonModel($arrResult);
    }


    public function updateStatusAction () {
        $errMessage = '';
        $view       = new JsonModel();

        try {
            $companyId = $this->findParam('company_id');
            $strStatus = strtolower($this->_filter->filter($this->findParam('new_status', '')));

            if(!is_numeric($companyId) || empty($companyId)) {
                $errMessage = $this->_tr->translate('Incorrectly selected company');
            }

            if(empty($errMessage) && !in_array($strStatus, array('active', 'inactive', 'suspended'))) {
                $errMessage = $this->_tr->translate('Incorrectly selected status');
            }

            if(empty($errMessage)) {
                // Save current company status for further use
                $arrCompanyInfo = $this->_company->getCompanyInfo($companyId);
                $intStatus      = $this->_company->getCompanyIntStatusByString($strStatus);
                $arrChangesData = $this->_company->createArrChangesData(array('Status' => $intStatus), 'company', $companyId);
                $this->_tickets->addTicketsWithCompanyChanges($arrChangesData, $companyId);
                // Changes status
                $booResult = $this->_company->updateCompanyStatus(
                    $this->_company->getCompanyStringStatusById($arrCompanyInfo['Status']),
                    $strStatus,
                    $companyId
                );

                // If status was suspended - we need mark all failed invoices as unpaid
                if($booResult && $arrCompanyInfo['Status'] == $this->_company->getCompanyIntStatusByString('suspended')) {
                    $oInvoices   = $this->_company->getCompanyInvoice();
                    $arrInvoices = $oInvoices->getCompanyFailedInvoices($companyId);
                    foreach ($arrInvoices as $arrInvoiceInfo) {
                        $oInvoices->markInvoiceUnpaid($arrInvoiceInfo['company_invoice_id']);
                    }
                }

                if(!$booResult) {
                    $errMessage = $this->_tr->translate('Internal Error.');
                }
            }
        } catch (Exception $e) {
            $errMessage = $this->_tr->translate('Internal Error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        // Return Json result
        return $view->setVariables(array('success' => empty($errMessage), 'msg' => $errMessage));
    }


    public function deleteAction () {
        $errMessage = '';
        $view       = new JsonModel();

        try {
            $companyId = $this->findParam('company_id');

            if(!is_numeric($companyId) || empty($companyId)) {
                $errMessage = $this->_tr->translate('Incorrectly selected company');
            }

            if(empty($errMessage)) {
                $companyMemberIds = $this->_members->getCompanyMemberIds($companyId);

                $booResult = $this->_company->deleteCompany(array($companyId));

                // Delete assigned QNRs
                $qnrIds = $this->_companyProspects->getCompanyQnr()->getQnrIds($companyId, $companyMemberIds, $companyMemberIds);
                if (!empty($qnrIds) && $booResult) {
                    $booResult = $this->_companyProspects->getCompanyQnr()->deleteQnr($qnrIds);
                }

                if(!$booResult) {
                    $errMessage = $this->_tr->translate('Internal Error.');
                }
            }
        } catch (Exception $e) {
            $errMessage = $this->_tr->translate('Internal Error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        // Return Json result
        return $view->setVariables(array('success' => empty($errMessage), 'msg' => $errMessage));
    }


    public function getFailedInvoicesAction () {
        $view        = new JsonModel();
        $errMessage  = '';
        $arrInvoices = array();

        try {
            $companyId = $this->findParam('company_id');

            if(!is_numeric($companyId) || empty($companyId)) {
                $errMessage = $this->_tr->translate('Incorrectly selected company');
            }

            if(empty($errMessage)) {
                $arrInvoices = $this->_company->getCompanyInvoice()->getCompanyFailedInvoices($companyId);
            }
        } catch (Exception $e) {
            $errMessage = $this->_tr->translate('Internal Error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        // Return Json result
        return $view->setVariables(array('success' => empty($errMessage), 'msg' => $errMessage, 'rows' => $arrInvoices, 'totalCount' => count($arrInvoices)));
    }

    public function getCompanyDetailsAction() {
        $arrTemplates = array();
        $arrUsers     = array();
        $strError     = '';

        try {
            $companyId = Json::decode($this->findParam('companyId'), Json::TYPE_ARRAY);
            if(empty($strError) && (!is_numeric($companyId) || empty($companyId))) {
                $strError = $this->_tr->translate('Company was selected incorrectly.');
            }

            if(empty($strError)) {
                // Load company users
                $arrMembers = $this->_company->getCompanyMembersWithRoles($companyId, 'admin_and_staff');
                if(count($arrMembers)) {
                    foreach ($arrMembers as $arrMemberInfo) {
                        $strRole = $arrMemberInfo['role_name'];
                        if(array_key_exists($arrMemberInfo['member_id'], $arrUsers)) {
                            $arrUsers[$arrMemberInfo['member_id']]['user_roles'] .= ', ' . $strRole;
                        } else {
                            $arrUsers[$arrMemberInfo['member_id']] = array(
                                'user_id'    => $arrMemberInfo['member_id'],
                                'user_name'  => $arrMemberInfo['fName'] . ' ' . $arrMemberInfo['lName'],
                                'user_roles' => $strRole
                            );
                        }
                    }
                }
            }

            if(empty($strError)) {
                $templates = SystemTemplate::loadMultipleByConditions(['type' => 'mass_email']);
                $arrTemplates = array_map(function($template) {
                    $arrTemplate =  $template->toExtJs(['template_id' => 'system_template_id']);
                    $arrTemplate['create_date'] = $this->_settings->formatDate($template['create_date']);
                    return $arrTemplate;
                }, $templates);
                usort($arrTemplates, function ($item1, $item2) {
                    return strtotime($item1['create_date']) <=> strtotime($item2['create_date']);
                });
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'templates' => array(
                'rows'       => $arrTemplates,
                'totalCount' => count($arrTemplates)
            ),

            'users' => array(
                'rows'       => array_merge($arrUsers), // need to reset keys
                'totalCount' => count($arrUsers)
            ),

            'error_message' => $strError
        );

        return new JsonModel($arrResult);
    }


    public function massEmailAction() {
        try {
            set_time_limit(60 * 60); // 1 hour
            ini_set('memory_limit', '512M');

            $strError   = '';
            $textStatus = '';

            // Get and check incoming info
            $arrCompanyIds         = Json::decode($this->findParam('arr_ids'), Json::TYPE_ARRAY);
            $processedCount        = Json::decode($this->findParam('processed_count'), Json::TYPE_ARRAY);
            $totalCount            = Json::decode($this->findParam('total_count'), Json::TYPE_ARRAY);
            $templateId            = Json::decode($this->findParam('template_id'), Json::TYPE_ARRAY);
            $sendTo                = Json::decode($this->findParam('send_to'), Json::TYPE_ARRAY);
            $booRespectEmailPolicy = Json::decode($this->findParam('respect_policy', true), Json::TYPE_ARRAY);
            /** @var array $arrUserIds */
            $arrUserIds = Json::decode($this->findParam('arr_user_ids'), Json::TYPE_ARRAY);
            if (!is_array($arrUserIds)) {
                $strError = $this->_tr->translate('Users were selected incorrectly.');
            }

            if(empty($strError) && (!is_array($arrCompanyIds) || !count($arrCompanyIds))) {
                $strError = $this->_tr->translate('Companies were selected incorrectly.');
            }

            if(empty($strError) && (!is_numeric($processedCount) || $processedCount < 0)) {
                $strError = $this->_tr->translate('Incoming data [processed count] is incorrect.');
            }

            if(empty($strError) && (!is_numeric($totalCount) || empty($totalCount))) {
                $strError = $this->_tr->translate('Incoming data [total count] is incorrect.');
            }

            if(empty($strError) && (!is_numeric($templateId) || empty($templateId))) {
                $strError = $this->_tr->translate('Template was selected incorrectly.');
            }

            $strType      = '';
            $arrMemberIds = array();
            if(count($arrUserIds)) {
                if(is_array($arrUserIds)) {
                    $arrMemberIds = $arrUserIds;
                } else {
                    $strError = $this->_tr->translate('Users were selected incorrectly.');
                }
            } else {
                if(empty($strError) && !in_array($sendTo, array('admin', 'all'))) {
                    $strError = $this->_tr->translate('Send to option was selected incorrectly.');
                } else {
                    $strType = $sendTo == 'admin' ? 'admin' : 'admin_and_staff';
                }
            }

            $template = false;
            if (empty($strError)) {
                $template = SystemTemplate::load((int)$templateId);
                if (!$template) {
                    $strError = $this->_tr->translate('Template was selected incorrectly.');
                }
            }

            if (empty($strError)) {
                $companyId = array_shift($arrCompanyIds);

                $arrCompanyInfo = $this->_company->getCompanyAndDetailsInfo($companyId);
                $textStatus     = sprintf('<div style="font-weight: bold;">%s</div>', $arrCompanyInfo['companyName']);

                // Send email always
                // Except if checkbox is checked OR
                // checkbox not checked and company option is not 'Yes'
                $booSend = true;
                if($booRespectEmailPolicy && $arrCompanyInfo['send_mass_email'] != 'Y') {
                    $booSend = false;
                }

                if($booSend) {
                    // Load template from correct place and parse company data
                    $adminInfo    = $this->_clients->getTemplateReplacements((int)$arrCompanyInfo['admin_id']);
                    $replacements = $this->_company->getTemplateReplacements($arrCompanyInfo, $adminInfo);
                    $replacements += $this->_systemTemplates->getGlobalTemplateReplacements();

                    // Load emails addresses we need send to (by user type)
                    $arrMembersEmails = $this->_company->getCompanyMembersEmails($companyId, $strType, $arrMemberIds);
                    if (count($arrMembersEmails)) {
                        foreach ($arrMembersEmails as $arrMemberInfo) {
                            $emailAddress = $arrMemberInfo['email'];
                            $name         = $arrMemberInfo['name'];
                            if (!empty($emailAddress)) {
                                $currentReplacements = $replacements + $this->_members->getTemplateReplacements($arrMemberInfo);
                                $processedTemplate   = $this->_systemTemplates->processTemplate($template, $currentReplacements);

                                $form = array(
                                    'from'    => $processedTemplate->from,
                                    'email'   => $name . " <$emailAddress>",
                                    'cc'      => '',
                                    'bcc'     => '',
                                    'subject' => $processedTemplate->subject,
                                    'message' => $processedTemplate->template
                                );

                                $senderInfo = $this->_members->getMemberInfo();
                                list($res, ) = $this->_mailer->send($form, array(), $senderInfo, false);

                                if ($res !== true) {
                                    $strColor  = 'red';
                                    $strStatus = $this->_tr->translate('Error: ') . $res;
                                } else {
                                    $strColor  = '#006600';
                                    $strStatus = $this->_tr->translate('Ok');
                                }

                                $textStatus .= sprintf('<div style="color: %s">%s (%s) - %s</div>', $strColor, $name, $emailAddress, $strStatus);
                            }
                        }
                    }
                } else {
                    $textStatus .= sprintf('<div style="color: %s">%s</div>', '#9E0F0F', $this->_tr->translate('Skipped (<b>do not send mass email</b> is checked)'));
                }

                $processedCount++;
            }
        } catch (Exception $e) {
            $strError       = $textStatus = $this->_tr->translate('Internal Error');
            $arrCompanyIds  = array();
            $processedCount = $totalCount = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        // Return json result
        $arrResult = array(
            'success'         => empty($strError),
            'msg'             => $strError,
            'arr_ids'         => $arrCompanyIds,
            'processed_count' => $processedCount,
            'total_count'     => $totalCount,
            'text_status'     => $textStatus

        );
        return new JsonModel($arrResult);
    }

    /**
     * The default action - show companies list
     */
    public function indexAction ()
    {
        $view = new ViewModel();

        if (!$this->_auth->isCurrentUserSuperadmin()) {
            return $this->redirect()->toUrl('/superadmin/manage-own-company');
        }

        $title = $this->_tr->translate('Welcome to Super Admin section');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        return $view;
    }

    private function _loadCompanyInfo($companyId = 0)
    {
        $arrCompanyInfo = array(
            'company_id'                     => '',
            'admin_id'                       => '',
            'use_annotations'                => 'N',
            'purged'                         => 'N',
            'purged_details'                 => '',
            'remember_default_fields'        => 'Y',
            'companyEmail'                   => '',
            'company_template_id'            => '1', // Default template
            'companyName'                    => '',
            'companyLogo'                    => '',
            'city'                           => '',
            'state'                          => '',
            'country'                        => $this->_country->getDefaultCountryId(),
            'phone1'                         => '',
            'phone2'                         => '',
            'contact'                        => '',
            'fax'                            => '',
            'zip'                            => '',
            'address'                        => '',
            'note'                           => '',
            'companyCode'                    => '',
            'companyTimeZone'                => '',
            'freetrial_key'                  => '',
            'storage_location'               => '',

            // Use default labels in relation to the website version
            'default_label_office'           => $this->_company->getDefaultLabel('office'),
            'default_label_office_readable'  => $this->_company->getCurrentCompanyDefaultLabel('office'),
            'default_label_trust_account'    => $this->_company->getDefaultLabel('trust_account'),
            'advanced_search_rows_max_count' => 3,
            'enable_case_management'         => ($this->_config['site_version']['case_management_enable'] == 1) ? 'Y' : 'N',
            'loose_task_rules'               => 'N',
            'hide_inactive_users'            => 'N',
            'invoice_number_format'          => '',
            'invoice_number_start_from'      => '',
            'invoice_tax_number'             => '',
            'invoice_disclaimer'             => '',
            'client_profile_id_enabled'      => 0,
            'client_profile_id_format'       => '',
            'client_profile_id_start_from'   => '',
        );

        if (!empty($companyId)) {
            // Load from db
            $select = (new Select())
                ->from(['c' => 'company'])
                ->join(['d' => 'company_details'], 'd.company_id = c.company_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER)
                ->join(['t' => 'company_trial'], 't.company_id = c.company_id', ['freetrial_key' => 'key'], Select::JOIN_LEFT_OUTER)
                ->where(['c.company_id' => $companyId]);

            $arrCompanyInfo = $this->_db2->fetchRow($select);

            // We need overwrite company id
            // if there is no record in company details table
            $arrCompanyInfo['company_id']                    = $companyId;
            $arrCompanyInfo['default_label_office_readable'] = $this->_company->getCurrentCompanyDefaultLabel('office');
        }

        if (empty($arrCompanyInfo['companyTimeZone'])) {
            $arrCompanyInfo['companyTimeZone'] = date_default_timezone_get();
        }

        $arrInvoiceNumberSettings                    = $this->_company->getCompanyInvoiceNumberSettings($companyId);
        $arrCompanyInfo['invoice_number_format']     = $arrInvoiceNumberSettings['format'];
        $arrCompanyInfo['invoice_number_start_from'] = $arrInvoiceNumberSettings['start_from'];
        $arrCompanyInfo['invoice_tax_number']        = $arrInvoiceNumberSettings['tax_number'];
        $arrCompanyInfo['invoice_disclaimer']        = $arrInvoiceNumberSettings['disclaimer'];


        $arrClientProfileIdSettings                     = $this->_company->getCompanyClientProfileIdSettings($companyId);
        $arrCompanyInfo['client_profile_id_enabled']    = $arrClientProfileIdSettings['enabled'];
        $arrCompanyInfo['client_profile_id_format']     = $arrClientProfileIdSettings['format'];
        $arrCompanyInfo['client_profile_id_start_from'] = $arrClientProfileIdSettings['start_from'];

        return $arrCompanyInfo;
    }

    private function _loadTemplatesList($booWithPlease = true)
    {
        return $booWithPlease ? array('' => $this->_tr->translate('-- Please select --')) : array();
    }

    /**
     * @param int $company_id
     * @return array
     */
    private function _CreateUpdateCompany($company_id)
    {
        // Get data from POST request
        $arrCompanyInfo = array(
            'company_id' => $company_id,

            'fName'        => $this->_filter->filter($this->params()->fromPost('fName')),
            'lName'        => $this->_filter->filter($this->params()->fromPost('lName')),
            'username'     => $this->_filter->filter($this->params()->fromPost('username')),
            'password'     => trim($this->_filter->filter($this->params()->fromPost('password', ''))),
            'emailAddress' => $this->_filter->filter($this->params()->fromPost('emailAddress')),
            'Status'       => $this->_filter->filter($this->params()->fromPost('new_status')),

            'company_template_id'            => $this->_filter->filter($this->params()->fromPost('company_template_id')),
            'companyName'                    => $this->_filter->filter($this->params()->fromPost('companyName')),
            'company_abn'                    => empty($this->_config['site_version']['check_abn_enabled']) ? '' : $this->_filter->filter($this->params()->fromPost('company_abn')),
            'companyLogo'                    => '',
            'address'                        => $this->_filter->filter($this->params()->fromPost('address')),
            'city'                           => $this->_filter->filter($this->params()->fromPost('city')),
            'state'                          => $this->_filter->filter($this->params()->fromPost('state')),
            'provinces'                      => $this->_filter->filter($this->params()->fromPost('provinces')),
            'country'                        => $this->_filter->filter($this->params()->fromPost('country')),
            'zip'                            => $this->_filter->filter($this->params()->fromPost('zip')),
            'phone1'                         => $this->_filter->filter($this->params()->fromPost('phone1')),
            'phone2'                         => $this->_filter->filter($this->params()->fromPost('phone2')),
            'companyEmail'                   => $this->_filter->filter($this->params()->fromPost('companyEmail')),
            'fax'                            => $this->_filter->filter($this->params()->fromPost('fax')),
            'companyTimeZone'                => $this->_filter->filter($this->params()->fromPost('companyTimeZone')),
            'note'                           => $this->_filter->filter($this->params()->fromPost('note')),
            'advanced_search_rows_max_count' => $this->_filter->filter($this->params()->fromPost('advanced_search_rows_max_count')),
            'invoice_number_format'          => $this->_filter->filter($this->params()->fromPost('invoice_number_format')),
            'invoice_number_start_from'      => $this->_filter->filter($this->params()->fromPost('invoice_number_start_from')),
            'invoice_tax_number'             => trim($this->_filter->filter($this->params()->fromPost('invoice_tax_number', ''))),
            'invoice_disclaimer'             => $this->_settings->getHTMLPurifier()->purify($this->params()->fromPost('invoice_disclaimer')),
            'client_profile_id_enabled'      => $this->_filter->filter($this->params()->fromPost('client_profile_id_enabled')),
            'client_profile_id_format'       => $this->_filter->filter($this->params()->fromPost('client_profile_id_format')),
            'client_profile_id_start_from'   => $this->_filter->filter($this->params()->fromPost('client_profile_id_start_from')),

            'use_annotations'                     => $this->_filter->filter($this->params()->fromPost('use_annotations')),
            'remember_default_fields'             => $this->_filter->filter($this->params()->fromPost('remember_default_fields')),
            'do_not_send_mass_email'              => $this->_filter->filter($this->params()->fromPost('do_not_send_mass_email')),
            'company_website'                     => $this->_filter->filter($this->params()->fromPost('company_website')),
            'allow_change_case_type'              => $this->_filter->filter($this->params()->fromPost('allow_change_case_type')),
            'allow_export'                        => $this->_filter->filter($this->params()->fromPost('allow_export')),
            'allow_import'                        => $this->_filter->filter($this->params()->fromPost('allow_import')),
            'allow_import_bcpnp'                  => $this->_filter->filter($this->params()->fromPost('allow_import_bcpnp')),
            'allow_multiple_advanced_search_tabs' => $this->_filter->filter($this->params()->fromPost('allow_multiple_advanced_search_tabs')),
            'allow_decision_rationale_tab'        => $this->_filter->filter($this->params()->fromPost('allow_decision_rationale_tab')),
            'decision_rationale_tab_name'         => $this->_filter->filter($this->params()->fromPost('decision_rationale_tab_name')),
            'time_tracker_enabled'                => $this->_filter->filter($this->params()->fromPost('time_tracker_enabled')),
            'marketplace_module_enabled'          => $this->_filter->filter($this->params()->fromPost('marketplace_module_enabled')),
            'employers_module_enabled'            => $this->_filter->filter($this->params()->fromPost('employers_module_enabled')),
            'log_client_changes_enabled'          => $this->_filter->filter($this->params()->fromPost('log_client_changes_enabled')),
            'enable_case_management'              => $this->_filter->filter($this->params()->fromPost('enable_case_management')),
            'loose_task_rules'                    => $this->_filter->filter($this->params()->fromPost('loose_task_rules')),
            'hide_inactive_users'                 => $this->_filter->filter($this->params()->fromPost('hide_inactive_users')),
            'storage_location'                    => $this->_filter->filter($this->params()->fromPost('storage_location')),

            'default_label_office'        => $this->_filter->filter($this->params()->fromPost('default_label_office')),
            'default_label_trust_account' => $this->_filter->filter($this->params()->fromPost('default_label_trust_account')),
        );

        $booIsSuperAdmin = $this->_auth->isCurrentUserSuperadmin();
        if(!$booIsSuperAdmin) {
            unset($arrCompanyInfo['Status']);
            unset($arrCompanyInfo['advanced_search_rows_max_count']);
        }

        if (!$this->_acl->isAllowed('edit-company-extra-details')) {
            unset(
                $arrCompanyInfo['phone2'],
                $arrCompanyInfo['companyEmail'],
                $arrCompanyInfo['fax'],
                $arrCompanyInfo['note'],
                $arrCompanyInfo['do_not_send_mass_email'],
                $arrCompanyInfo['storage_location']
            );
        }

        // Check Company's Info
        $arrCreateUpdateCompany = $this->_company->createUpdateCompany($arrCompanyInfo, $booIsSuperAdmin && empty($company_id), $this->_analytics, $this->_automaticReminders->getActions());

        $arrCompanyInfo = $arrCreateUpdateCompany['arrCompanyInfo'];
        $arrChangesData = $arrCreateUpdateCompany['arrChangesData'];

        // Save notes only for superadmin only
        if($booIsSuperAdmin) {
            $this->_tickets->addTicketsWithCompanyChanges($arrChangesData, $company_id);
        }

        return $arrCompanyInfo;
    }

    /**
     * Add company action
     */
    public function addAction ()
    {
        $view = new ViewModel();

        set_time_limit(60 * 60); // 1 hour
        ini_set('memory_limit', '512M');

        $company_id = 0;
        $msgError   = '';

        if (!$this->_auth->isCurrentUserSuperadmin()) {
            return $this->redirect()->toUrl('/superadmin/manage-own-company');
        }


        if ($this->getRequest()->isPost()) {
            // Save received data
            $arrCompanyInfo = $this->_CreateUpdateCompany($company_id);

            $arrAdminInfo = array(
                'fName'        => $arrCompanyInfo['fName'],
                'lName'        => $arrCompanyInfo['lName'],
                'username'     => $arrCompanyInfo['username'],
                'password'     => $arrCompanyInfo['password'],
                'emailAddress' => $arrCompanyInfo['emailAddress'],
            );

            if (empty($arrCompanyInfo['error'])) {
                // Go to 'Edit this user' page
                return $this->redirect()->toUrl('/superadmin/manage-company/edit?' . http_build_query(['company_id' => $arrCompanyInfo['company_id'], 'status' => 1]));
            }

            $msgError = $arrCompanyInfo['error'];
        } else {
            $arrCompanyInfo = $this->_loadCompanyInfo($company_id);
            $arrAdminInfo   = $this->_company->loadCompanyAdminInfo($company_id);
        }

        $lastLoggedIn                   = $this->_company->getLastLoggedIn($company_id);
        $arrCompanyInfo['lastLoggedIn'] = empty($lastLoggedIn) ? '-' : date('Y-m-d H:i:s', $lastLoggedIn);

        $title = $this->_tr->translate('Add new Company');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $view->setVariable('edit_company_id', $company_id);
        $view->setVariable('arrCompanyInfo', $arrCompanyInfo);
        $view->setVariable('arrAdminCompanyInfo', $arrAdminInfo);

        $view->setVariable('defaultCountryId', $this->_country->getDefaultCountryId());
        $view->setVariable('isDefaultCountry', $this->_country->isDefaultCountry($arrCompanyInfo['country']));
        $view->setVariable('defaultTimezone', $this->_country->getDefaultTimeZone());
        $view->setVariable('defaultTimezone', $this->_country->getDefaultTimeZone());
        $view->setVariable('arrCountry', $this->_country->getCountries(true));
        $view->setVariable('provincesList', $this->_country->getStatesList(0, false, true));

        $view->setVariable('arrOfficeLabels', $this->_company->getDefaultLabelsList('office'));
        $view->setVariable('arrTALabels', $this->_company->getDefaultLabelsList('trust_account'));

        $view->setVariable('arrTemplates', $this->_loadTemplatesList());

        $view->setVariable('edit_error_message', $msgError);

        $view->setVariable('booShowABN', !empty($this->_config['site_version']['check_abn_enabled']));
        $view->setVariable('arrTimeZones', $this->_settings->getTimeZones(true, true));
        $view->setVariable('booCanChangeTimeZone', $this->_company->canUpdateTimeZone($company_id));
        $view->setVariable('caseTypeFieldLabel', $this->_company->getCurrentCompanyDefaultLabel('case_type'));

        $booCurrentMemberSuperAdmin = $this->_auth->isCurrentUserSuperadmin();
        $view->setVariable('booCurrentMemberSuperAdmin', $booCurrentMemberSuperAdmin);
        $view->setVariable('currentMemberId', $this->_auth->getCurrentUserId());

        $view->setVariable('btnHeading', $this->_tr->translate("Create New Company"));

        $view->setVariable('passwordMinLength', $this->_settings->passwordMinLength);
        $view->setVariable('passwordMaxLength', $this->_settings->passwordMaxLength);
        $view->setVariable('arrCompanyLogoDimensions', $this->_company->getCompanyLogoDimensions());

        $view->setVariable('invoice_tax_number_label', $this->_config['site_version']['invoice_tax_number_label']);

        $arrAccessRights = array(
            'allow_export'                 => false,
            'manage_extra_details'         => $this->_acl->isAllowed('edit-company-extra-details'),
            'login_as_company_admin'       => $this->_acl->isAllowed('manage-company-as-admin'),
            'clients_time_tracker_show'    => false,
            'manage_business_hours'        => false,
            'business_hours_access_rights' => array(),
            'manage_company_tickets'       => false,
            'manage_company_users'         => false,
            'loose_task_rules'             => false,
            'hide_inactive_users'          => false
        );
        $view->setVariable('arrAccessRights', $arrAccessRights);

        $arrTicketsAccessRights = array(
            'tickets'       => $this->_acl->isAllowed('manage-company-tickets') ,
            'add'           => $this->_acl->isAllowed('manage-company-tickets-add'),
            'change_status' => $this->_acl->isAllowed('manage-company-tickets-status')
        );
        $view->setVariable('arrTicketsAccessRights', $arrTicketsAccessRights);

        $view->setVariable('data', Json::encode(array()));

        return $view;
    }

    /**
     * Edit company action
     */
    public function editAction()
    {
        $view = new ViewModel();

        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $msgError = $msgConfirmation = '';

        $companyId                  = $this->_filter->filter($this->findParam('company_id'));
        $booCurrentMemberSuperAdmin = $this->_auth->isCurrentUserSuperadmin();
        if (!$booCurrentMemberSuperAdmin) {
            if ($this->_auth->getCurrentUserCompanyId() != $companyId) {
                return $this->redirect()->toUrl('/superadmin/manage-own-company');
            }
        }

        if ($this->getRequest()->isPost()) {
            // Save received data
            $arrCompanyInfo = $this->_CreateUpdateCompany($companyId);

            if (empty($arrCompanyInfo['error'])) {
                // Go to 'Edit this user' page
                $msgConfirmation = $this->_tr->translate("Company's information was updated");
            } else {
                $msgError = $arrCompanyInfo['error'];
            }
        } else {
            if (!is_numeric($companyId)) {
                return $this->redirect()->toUrl('/superadmin/manage-company');
            }

            $status = $this->findParam('status');
            if (is_numeric($status) && $status == '1') {
                $msgConfirmation = $this->_tr->translate("Company was successfully created");
            }

            $arrCompanyInfo = $this->_loadCompanyInfo($companyId);
        }

        $lastLoggedIn                   = $this->_company->getLastLoggedIn($companyId);
        $arrCompanyInfo['lastLoggedIn'] = empty($lastLoggedIn) ? '-' : date('Y-m-d H:i:s', $lastLoggedIn);

        $arrCompanyInfo['loginUrlWithHash'] = $this->_config['urlSettings']['baseUrl'] . "/login?id=" . $this->_company->generateHashByCompanyId($companyId);

        $companyName     = empty($companyId) ? 'Default company' : $arrCompanyInfo['companyName'];
        $arrAccessRights = array(
            'allow_export'              => $arrCompanyInfo['allow_export'] == 'Y',
            'manage_extra_details'      => $this->_acl->isAllowed('edit-company-extra-details'),
            'login_as_company_admin'    => $this->_acl->isAllowed('manage-company-as-admin'),
            'clients_time_tracker_show' => $this->_acl->isAllowed('clients-time-tracker-show'),
            'manage_company_tickets'    => $this->_acl->isAllowed('manage-company-tickets'),
            'manage_company_users'      => $this->_acl->isAllowed('manage-members'),
            'loose_task_rules'          => $arrCompanyInfo['loose_task_rules'] == 'Y',
            'hide_inactive_users'       => $arrCompanyInfo['hide_inactive_users'] == 'Y',

            'manage_business_hours'        => $this->_acl->isAllowed('manage-company-business-hours'),
            'business_hours_access_rights' => array(
                'view-workdays'   => $this->_acl->isAllowed('manage-company-business-hours-workdays-view'),
                'update-workdays' => $this->_acl->isAllowed('manage-company-business-hours-workdays-update'),
                'view-holidays'   => $this->_acl->isAllowed('manage-company-business-hours-holidays-view'),
                'add-holidays'    => $this->_acl->isAllowed('manage-company-business-hours-holidays-add'),
                'edit-holidays'   => $this->_acl->isAllowed('manage-company-business-hours-holidays-edit'),
                'delete-holidays' => $this->_acl->isAllowed('manage-company-business-hours-holidays-delete'),
            ),
        );
        $view->setVariable('ta_label', $this->_company->getCurrentCompanyDefaultLabel('trust_account'));

        $view->setVariable('booCanDeleteNotes', $this->_acl->isAllowed('clients-notes-delete'));
        $view->setVariable('booCanDeleteTasks', $this->_acl->isAllowed('clients-tasks-delete'));
        $view->setVariable('booCanViewMemberDetails', $booCurrentMemberSuperAdmin ? $this->_acl->isAllowed('manage-members-view-details') : false);
        $view->setVariable('booShowABN', !empty($this->_config['site_version']['check_abn_enabled']));

        $view->setVariable('arrAccessRights', $arrAccessRights);
        if ($booCurrentMemberSuperAdmin) {
            $view->setVariable('booCurrentMemberSuperAdmin', true);
            $strTitle = sprintf('Company Settings (%s)', $companyName);
        } else {
            $view->setVariable('booCurrentMemberSuperAdmin', false);
            $strTitle = 'Company Settings';
        }


        $view->setVariable('edit_company_id', $companyId);
        $view->setVariable('arrCompanyInfo', $arrCompanyInfo);

        $arrAdminInfo = $this->_company->loadCompanyAdminInfo($arrCompanyInfo['company_id']);
        $view->setVariable('arrDefaultCompanyAdminInfo', $arrAdminInfo);

        // Get company admins, remove duplicates, sort by name
        $arrNames             = array();
        $arrAdminsCompanyInfo = array();
        $arrAdminsWithRoles   = $this->_company->getCompanyMembersWithRoles($arrCompanyInfo['company_id'], 'admin_and_staff');
        foreach ($arrAdminsWithRoles as $arrAdminWithRoleInfo) {
            $key = $arrAdminWithRoleInfo['member_id'];
            if (!isset($arrAdminsCompanyInfo[$key])) {
                // Don't show regular users unless this was set as a company admin previously
                if (in_array($arrAdminWithRoleInfo['userType'], $this->_members::getMemberType('user')) && $key != $arrAdminInfo['admin_id']) {
                    continue;
                }

                $arrAdminsCompanyInfo[$key] = $arrAdminWithRoleInfo;

                $arrNames[$key] = strtolower($arrAdminWithRoleInfo['fName'] . ' ' . $arrAdminWithRoleInfo['lName']);
            }
        }
        array_multisort($arrNames, SORT_ASC, $arrAdminsCompanyInfo);
        $view->setVariable('arrAdminsCompanyInfo', $arrAdminsCompanyInfo);

        $view->setVariable('isDefaultCountry', $this->_country->isDefaultCountry($arrCompanyInfo['country']));
        $view->setVariable('defaultCountryId', $this->_country->getDefaultCountryId());
        $view->setVariable('defaultTimezone', $this->_country->getDefaultTimeZone());
        $view->setVariable('arrCountry', $this->_country->getCountries(true));
        $view->setVariable('provincesList', $this->_country->getStatesList());

        $view->setVariable('arrOfficeLabels', $this->_company->getDefaultLabelsList('office'));
        $view->setVariable('arrTALabels', $this->_company->getDefaultLabelsList('trust_account'));

        $view->setVariable('arrTemplates', $this->_loadTemplatesList());

        $view->setVariable('edit_error_message', $msgError);
        $view->setVariable('confirmation_message', $msgConfirmation);

        $view->setVariable('btnHeading', $this->_tr->translate('Update Company Info'));
        $view->setVariable('booCanDeleteInvoices', $this->_acl->isAllowedResource('superadmin', 'manage-company', 'delete-invoice'));
        $view->setVariable('booCanChargeInvoices', $this->_acl->isAllowedResource('superadmin', 'manage-company', 'run-charge'));
        $view->setVariable('arrTimeZones', $this->_settings->getTimeZones(true, true));
        $view->setVariable('booCanChangeTimeZone', $this->_company->canUpdateTimeZone($companyId));
        $view->setVariable('caseTypeFieldLabel', $this->_company->getCurrentCompanyDefaultLabel('case_type'));

        //INVOICES
        $oInvoices   = $this->_company->getCompanyInvoice();
        $arrInvoices = $oInvoices->getCompanyInvoices($companyId);

        foreach ($arrInvoices as &$arrInvoiceInfo) {
            $arrInvoiceInfo['status_formatted']    = $oInvoices->getInvoiceReadableStatus($arrInvoiceInfo['status']);
            $arrInvoiceInfo['invoice_date']        = $this->_settings::isDateEmpty($arrInvoiceInfo['invoice_date']) ? '' : $this->_settings->formatDate($arrInvoiceInfo['invoice_date']);
            $arrInvoiceInfo['invoice_posted_date'] = $this->_settings::isDateEmpty($arrInvoiceInfo['invoice_posted_date']) ? '' : $this->_settings->formatDate($arrInvoiceInfo['invoice_posted_date']);
        }

        $view->setVariable('invoices', $arrInvoices);


        //COMPANY PACKAGES        

        //get company details info
        $companyDetailsInfo                  = $this->_company->getCompanyAndDetailsInfo($companyId, array('regTime', 'country', 'state', 'storage_today'));
        $companyDetailsInfo['company_setup'] = $this->_settings->formatDate($companyDetailsInfo['regTime']);

        if ($companyDetailsInfo) {
            if (!empty($companyDetailsInfo['account_created_on'])) {
                $companyDetailsInfo['account_created_on'] = $this->_settings->formatDate($companyDetailsInfo['account_created_on']);
            }
        } else {
            $companyDetailsInfo = array();
        }

        //get packages
        $packages = $this->_company->getPackages()->getPackages();

        //get company packages
        $arrCompanyPackages = $this->_company->getPackages()->getCompanyPackages($companyId);

        $data = array_merge(
            $companyDetailsInfo,
            array('arrPackages' => $packages),
            array('arrCompanyPackages' => $arrCompanyPackages),
            array('packages_list' => $this->_company->getPackages()->getSubscriptionsList(false, true)),
            array('company_name' => $companyName)
        );

        $date_format = "d-m-Y H:i:s";

        $company_last_fields = $this->_company->getCompanyLastFields($companyId);

        //additional user and storage info
        $used                             = number_format($companyDetailsInfo['storage_today'] / 1024 / 1024, 2);
        $data['active_users']             = $this->_company->calculateActiveUsers($companyId);
        $data['additional_users']         = $this->_company->calculateAdditionalUsers($data['active_users'], $data['free_users']);
        $data['additional_user_charges']  = $this->_company->calculateAdditionalUsersFee($companyId, $data['additional_users'], $data['payment_term'], $data['pricing_category_id']);
        $data['storage_used']             = max($used, 0.01);
        $data['additional_storage']       = $this->_company->calculateAdditionalStorage($companyId, $data['free_storage']);
        $data['additional_storage_price'] = $this->_company->getStoragePrice($data['payment_term']);
        $data['extra_storage_charges']    = $this->_company->calculateAdditionalStorageFee($data['additional_storage'], $data['payment_term']);
        $data['subscription_name']        = $this->_company->getPackages()->getSubscriptionNameById($data['subscription']);

        if ($booCurrentMemberSuperAdmin) {
            $data['number_of_clients']              = $this->_company->calculateClients($companyId);
            $data['last_ta_upload']                 = $this->_company->getCompanyLastTAUploaded($companyId) ? date($date_format, strtotime($this->_company->getCompanyLastTAUploaded($companyId))) : "";
            $data['last_accounting_subtab_updated'] = $company_last_fields['last_accounting_subtab_updated'] ? date($date_format, $company_last_fields['last_accounting_subtab_updated']) : "";
            $data['last_notes_written']             = $company_last_fields['last_note_written'] ? date($date_format, $company_last_fields['last_note_written']) : "";
            $data['last_task_written']              = $company_last_fields['last_task_written'] ? date($date_format, $company_last_fields['last_task_written']) : "";
            $data['last_calendar_entry_written']    = $company_last_fields['last_calendar_entry'] ? date($date_format, $company_last_fields['last_calendar_entry']) : "";
            $data['last_check_email_pressed']       = $company_last_fields['last_manual_check'] ? date($date_format, $company_last_fields['last_manual_check']) : "";
            $data['last_advanced_search']           = $company_last_fields['last_adv_search'] ? date($date_format, $company_last_fields['last_adv_search']) : "";
            $data['last_mass_mail']                 = $company_last_fields['last_mass_mail'] ? date($date_format, $company_last_fields['last_mass_mail']) : "";
            $data['last_doc_uploaded']              = $company_last_fields['last_doc_uploaded'] ? date($date_format, $company_last_fields['last_doc_uploaded']) : "";

            $arrPrices = $this->_company->getCompanyPrices($companyId, false, $data['pricing_category_id'], true);

            $arrSubscriptions = $this->_company->getPackages()->getSubscriptionsList(true, true);

            foreach ($arrSubscriptions as $subscriptionId) {
                $name = ucfirst($subscriptionId);

                $data['prices'][$subscriptionId] = array(
                    'month_price'             => $arrPrices['package' . $name . 'FeeMonthly'],
                    'annually_price'          => $arrPrices['package' . $name . 'FeeAnnual'],
                    'bi_price'                => $arrPrices['package' . $name . 'FeeBiAnnual'],
                    'user_license_monthly'    => $this->_company->getUserPrice(1, $subscriptionId, $data['pricing_category_id']),
                    'user_license_annually'   => $this->_company->getUserPrice(2, $subscriptionId, $data['pricing_category_id']),
                    'user_license_biannually' => $this->_company->getUserPrice(3, $subscriptionId, $data['pricing_category_id']),
                );
            }
        }

        $view->setVariable('packagesCount', count($packages));
        $view->setVariable('data', Json::encode($data));
        $view->setVariable('btnInvoices', $this->_tr->translate("Save Changes"));
        $view->setVariable('booCanManageCompanyPackages', $this->_acl->isAllowed('manage-company-packages'));
        $view->setVariable('booCanManageCompanyPackagesExtraDetails', $this->_acl->isAllowed('manage-company-packages-extra-details'));

        $booCanExport = (!empty($companyId) && array_key_exists('allow_export', $arrCompanyInfo) && $arrCompanyInfo['allow_export'] == 'Y') && $this->_acl->isAllowed('allow-export');
        $view->setVariable('booCanExport', $booCanExport);

        $arrTicketsAccessRights = array(
            'tickets'       => $this->_acl->isAllowed('manage-company-tickets'),
            'add'           => $this->_acl->isAllowed('manage-company-tickets-add'),
            'change_status' => $this->_acl->isAllowed('manage-company-tickets-status')
        );
        $view->setVariable('arrTicketsAccessRights', $arrTicketsAccessRights);
        // Load task settings - used in Notes and Activities tab
        $arrTasksSettings = array();
        $arrCompanyUsers  = array();
        if ($booCurrentMemberSuperAdmin) {
            // Load users list (current user can access to)
            $arrMembersCanAccess = $this->_company->getCompanyMembersIds(0, 'superadmin', true);
            $arrMembers          = $this->_members->getMembersInfo($arrMembersCanAccess, true, 'superadmin');

            $clients_list = array(
                array('clientId' => 0, 'clientName' => 'General Task', 'clientFullName' => 'General Task')
            );

            $arrTasksSettings = array(
                'users'   => $arrMembers,
                'dates'   => array(),
                'clients' => $clients_list,
            );
            $arrCompanyUsers  = $this->_members->getMembersInfo($this->_company->getCompanyMembersIds($companyId), false);
        }
        $view->setVariable('arrTasksSettings', $arrTasksSettings);
        $view->setVariable('arrCompanyUsers', $arrCompanyUsers);
        $view->setVariable('currentMemberId', $this->_auth->getCurrentUserId());

        // time tracker settings
        $arrTimeTrackerSettings = array(
            'rate'           => 0,
            'round_up'       => 0,
            'tracker_enable' => 'Y',
            'disable_popup'  => 'N',
            'users'          => $this->_members->getMembersInfo($this->_company->getCompanyMembersIds(0), true, 'superadmin'),
            'access'         => array("show-popup", "add", "edit", "delete"),
            'arrProvinces'   => $this->_gstHst->getTaxesList()
        );
        $arrInlineScript        = "var arrTimeTrackerSettings = " . Json::encode($arrTimeTrackerSettings) . ";";

        /** @var HeadScript $headScript */
        $headScript = $this->_serviceManager->get('ViewHelperManager')->get('headScript');
        $headScript->appendScript($arrInlineScript);


        $exportRangeProspects = 0;
        $prospectsTotalCount  = 0;
        $clientsTotalCount    = 0;
        $exportRangeClients   = 0;
        if ($booCanExport) {
            $exportRangeProspects = CompanyProspects::$exportProspectsLimit;
            $prospectsTotalCount  = $this->_companyProspects->getProspectsCountForCompany($companyId);

            $clientsTotalCount  = $this->_company->getCompanyCasesCount($companyId);
            $exportRangeClients = 1000;
        }

        $arrInlineScript = "var exportRangeProspects = " . $exportRangeProspects . ";";
        $headScript->appendScript($arrInlineScript);

        $arrInlineScript = "var prospectsTotalCount = " . $prospectsTotalCount . ";";
        $headScript->appendScript($arrInlineScript);

        $arrInlineScript = "var clientsTotalCount = " . $clientsTotalCount . ";";
        $headScript->appendScript($arrInlineScript);

        $arrInlineScript = "var exportRangeClients = " . $exportRangeClients . ";";
        $headScript->appendScript($arrInlineScript);

        $view->setVariable('passwordMinLength', $this->_settings->passwordMinLength);
        $view->setVariable('passwordMaxLength', $this->_settings->passwordMaxLength);
        $view->setVariable('arrCompanyLogoDimensions', $this->_company->getCompanyLogoDimensions());

        $view->setVariable('invoice_tax_number_label', $this->_config['site_version']['invoice_tax_number_label']);

        // Prepare content for the "Manage Users + Manage Roles" sections.
        /** @var PhpRenderer $renderer */
        $renderer = $this->_serviceManager->get(PhpRenderer::class);
        $view->setVariable(
            'rendered_members',
            $renderer->render(
                $this->forward()->dispatch(
                    ManageMembersController::class,
                    array(
                        'action'     => 'index',
                        'company_id' => $companyId
                    )
                )
            )
        );

        $view->setVariable(
            'rendered_roles',
            $renderer->render(
                $this->forward()->dispatch(
                    RolesController::class,
                    array(
                        'action'     => 'index',
                        'company_id' => $companyId
                    )
                )
            )
        );

        $this->layout()->setVariable('title', $strTitle);

        //RENDER
        return $view;
    }

    /**
     * Return company logo by its id action
     */
    public function getCompanyLogoAction()
    {
        $view = new ViewModel(
            [
                'content' => null
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        $company_id = $this->findParam('company_id');
        if (is_numeric($company_id) && $this->_members->hasCurrentMemberAccessToCompany($company_id)) {
            $arrCompanyInfo = $this->_loadCompanyInfo($company_id);

            if (! empty($arrCompanyInfo['companyLogo'])) {
                $fileInfo = $this->_files->getCompanyLogo($company_id, $arrCompanyInfo['companyLogo'], $this->_company->isCompanyStorageLocationLocal($company_id));
                if ($fileInfo instanceof FileInfo) {
                    if ($fileInfo->local) {
                        return $this->downloadFile($fileInfo->path, $fileInfo->name, $fileInfo->mime, true, false);
                    } else {
                        $url = $this->_files->getCloud()->getFile($fileInfo->path, $fileInfo->name, true, false);
                        if ($url) {
                            return $this->redirect()->toUrl($url);
                        }
                    }
                }
            }
        }

        return $view;
    }

    public function getInvoiceAction()
    {
        $company_invoice_id = $this->findParam('company_invoice_id');

        $invoice                 = $this->_company->getCompanyInvoice()->getInvoiceDetails($company_invoice_id);
        $invoice['invoice_date'] = $this->_settings->formatDate($invoice['invoice_date']);

        return new JsonModel($invoice);
    }

    public function savePackagesAction()
    {
        $strErrorMessage = '';

        try {
            $companyId         = $this->findParam('company_id');
            $strSubscriptionId = Json::decode($this->findParam('packages'), Json::TYPE_ARRAY);

            if (!$this->_members->hasCurrentMemberAccessToCompany($companyId)) {
                $strErrorMessage = 'Insufficient access rights';
            }

            if (empty($strErrorMessage)) {
                $arrSubscriptions = $this->_company->getPackages()->getSubscriptionsList(true, true);
                if (!in_array($strSubscriptionId, $arrSubscriptions)) {
                    $strErrorMessage = 'Incorrectly selected subscription';
                }
            }

            if (empty($strErrorMessage)) {
                // Get company packages details
                $data = array(
                    'trial'                 => $this->_filter->filter(Json::decode($this->findParam('trial'), Json::TYPE_ARRAY)),
                    'subscription'          => $strSubscriptionId,
                    'next_billing_date'     => $this->_filter->filter(Json::decode($this->findParam('next_billing_date'), Json::TYPE_ARRAY)),
                    'payment_term'          => $this->_filter->filter(Json::decode($this->findParam('billing_frequency'), Json::TYPE_ARRAY)),
                    'free_users'            => $this->_filter->filter(Json::decode($this->findParam('free_users'), Json::TYPE_ARRAY)),
                    'free_clients'          => $this->_filter->filter(Json::decode($this->findParam('free_clients'), Json::TYPE_ARRAY)),
                    'free_storage'          => $this->_filter->filter(Json::decode($this->findParam('free_storage'), Json::TYPE_ARRAY)),
                    'internal_note'         => $this->_filter->filter(Json::decode($this->findParam('internal_note'), Json::TYPE_ARRAY)),
                    'subscription_fee'      => $this->_filter->filter(Json::decode($this->findParam('subscription_fee'), Json::TYPE_ARRAY)),
                    'gst'                   => $this->_filter->filter(Json::decode($this->findParam('gst'), Json::TYPE_ARRAY)),
                    'gst_type'              => $this->_filter->filter(Json::decode($this->findParam('gst_type'), Json::TYPE_ARRAY)),
                    'paymentech_profile_id' => $this->_filter->filter(Json::decode($this->findParam('pt_profile_id'), Json::TYPE_ARRAY))
                );

                if ($data['trial'] != 'Y') {
                    $data['trial'] = 'N';
                }

                if (empty($data['next_billing_date'])) {
                    // We don't want save 0000-00-00, make it as NULL
                    $data['next_billing_date'] = null;
                }

                // Update PT id if needed
                if (empty($data['paymentech_profile_id'])) {
                    $data['paymentech_profile_id'] = null;
                }

                // Check if there is record in Db
                $arrSavedCompanyDetails  = $this->_company->getCompanyDetailsInfo($companyId);

                // Save company details
                if (isset($arrSavedCompanyDetails['company_id']) && !empty($arrSavedCompanyDetails['company_id'])) {
                    $arrChangesData = $this->_company->createArrChangesData($data, 'company_details', $companyId);
                    $this->_tickets->addTicketsWithCompanyChanges($arrChangesData, $companyId);

                    // Run mass update if subscription was changed
                    if ($arrSavedCompanyDetails['subscription'] != $data['subscription']) {
                        $this->_users->massCompanyUsersUpdateInLms($companyId);
                    }
                } else {
                    $data['company_id']                  = $companyId;
                    $data['default_label_office']        = $this->_company->getDefaultLabel('office');
                    $data['default_label_trust_account'] = $this->_company->getDefaultLabel('trust_account');
                    $data['invoice_number_settings']     = Json::encode($this->_company->getCompanyInvoiceNumberSettings($companyId));
                    $data['client_profile_id_settings']  = Json::encode($this->_company->getCompanyClientProfileIdSettings($companyId));
                }
                $this->_company->updateCompanyDetails($companyId, $data);

                // Load required packages list from db
                $arrRequiredPackagesIds = $this->_company->getPackages()->getPackages(true, true);

                $arrPackagesIds = $this->_company->getPackages()->getPackagesBySubscriptionId($strSubscriptionId);

                $arrPackagesIds = array_unique(array_merge($arrPackagesIds, $arrRequiredPackagesIds));
                if (!$this->_company->getPackages()->updateCompanyPackages($companyId, $arrPackagesIds)) {
                    $strErrorMessage = 'Packages were not updated.';
                }
            }
        } catch (Exception $e) {
            $strErrorMessage = 'Internal error.';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strErrorMessage),
            'msg'     => $strErrorMessage
        );

        return new JsonModel($arrResult);
    }

    /**
     * Check if:
     * 1. There is created PT profile id. There are 3 cases:
     *    - Exists in officio DB, but not in PT -> we need create a new one in PT
     *    - Exists in officio DB and in PT      -> we need update in PT
     *    - Does not exist in officio DB        -> we need create a new one in PT and Officio
     * 2. Other incoming info is correct
     *
     * output array in json format: string error message and
     * if we need ask that PT profile is not created yet
     */
    public function checkCcInfoAction()
    {
        $view   = new JsonModel();
        $booAsk = false;

        // Get incoming info
        $companyId         = Json::decode($this->findParam('company_id'), Json::TYPE_ARRAY);
        $customerName      = trim($this->_filter->filter(Json::decode($this->findParam('customer_name', ''), Json::TYPE_ARRAY)));
        $creditCardNum     = $this->_filter->filter(Json::decode($this->findParam('cc_num'), Json::TYPE_ARRAY));
        $creditCardCVN     = $this->_filter->filter(Json::decode($this->findParam('cc_cvn'), Json::TYPE_ARRAY));
        $creditCardExpDate = $this->_filter->filter(Json::decode($this->findParam('cc_exp'), Json::TYPE_ARRAY));

        // Check incoming info
        $strMessage = $this->_clients->getAccounting()->checkCCInfo($companyId, 0, $customerName, $creditCardNum, $creditCardExpDate, $creditCardCVN);

        // Check if for current company was created PT profile id
        if (empty($strMessage)) {
            $arrDetailsCompanyInfo = $this->_company->getCompanyAndDetailsInfo($companyId);
            if (!empty($arrDetailsCompanyInfo['paymentech_profile_id'])) {
                $this->_payment->init();
                $arrResult = $this->_payment->readProfile($arrDetailsCompanyInfo['paymentech_profile_id']);

                if ($arrResult['error']) {
                    // Such PT profile doesn't exist
                    $booAsk = true;
                }
            }
        }

        // Return result in json format
        $arrResult = array(
            'success' => empty($strMessage),
            'message' => $strMessage,
            'booAsk'  => $booAsk
        );
        return $view->setVariables($arrResult);
    }

    /**
     * Update CC info in PT
     */
    public function updatePackagesCcAction()
    {
        $booSuccess     = false;
        $customerRefNum = '';

        // Get incoming info
        $companyId         = Json::decode($this->findParam('company_id'), Json::TYPE_ARRAY);
        $customerName      = trim($this->_filter->filter(Json::decode($this->findParam('customer_name', ''), Json::TYPE_ARRAY)));
        $creditCardNum     = $this->_filter->filter(Json::decode($this->findParam('cc_num'), Json::TYPE_ARRAY));
        $creditCardCVN     = $this->_filter->filter(Json::decode($this->findParam('cc_cvn'), Json::TYPE_ARRAY));
        $creditCardExpDate = $this->_filter->filter(Json::decode($this->findParam('cc_exp', ''), Json::TYPE_ARRAY));
        $booForceCreate    = Json::decode($this->findParam('booForceCreate'), Json::TYPE_ARRAY);

        // Check incoming info
        $strMessage = $this->_clients->getAccounting()->checkCCInfo($companyId, 0, $customerName, $creditCardNum, $creditCardExpDate, $creditCardCVN);

        // All incoming info is okay, send request to PT
        if (empty($strMessage)) {
            $creditCardExpDate = str_replace('/', '', $creditCardExpDate);

            try {
                $this->_payment->init();

                // Check if for current company was created PT profile id
                $arrDetailsCompanyInfo = $this->_company->getCompanyAndDetailsInfo($companyId);
                if (empty($arrDetailsCompanyInfo) || empty($arrDetailsCompanyInfo['paymentech_profile_id']) || $booForceCreate) {
                    // PT profile was not yet created, so let's create it now
                    $customerRefNum = $this->_payment->generatePaymentProfileId($companyId, false);

                    $arrProfileInfo = array(
                        'customerName'            => $customerName,
                        'customerRefNum'          => $customerRefNum,
                        'creditCardNum'           => $creditCardNum,
                        'creditCardExpDate'       => $creditCardExpDate,
                        'OrderDefaultDescription' => $arrDetailsCompanyInfo['companyName']
                    );

                    $arrResult = $this->_payment->createProfile($arrProfileInfo);
                } else {
                    // PT profile was already created, so lets update it
                    $customerRefNum = $arrDetailsCompanyInfo['paymentech_profile_id'];
                    $arrProfileInfo = array(
                        'customerName'            => $customerName,
                        'customerRefNum'          => $customerRefNum,
                        'creditCardNum'           => $creditCardNum,
                        'creditCardExpDate'       => $creditCardExpDate,
                        'OrderDefaultDescription' => $arrDetailsCompanyInfo['companyName']
                    );

                    $arrResult = $this->_payment->updateProfile($arrProfileInfo);
                }

                // Check response from PT
                if ($arrResult['error']) {
                    $strMessage = $arrResult['message'];
                } else {
                    // Update company details
                    $this->_company->updatePTInfo($creditCardNum, $customerRefNum, $companyId);

                    // Send email to support that CC info were updated
                    $template          = SystemTemplate::loadOne(['title' => 'Credit card on file was updated by admin']);
                    $adminInfo         = $this->_members->getMemberInfo($arrDetailsCompanyInfo['admin_id']);
                    $replacements      = $this->_company->getTemplateReplacements($arrDetailsCompanyInfo, $adminInfo);
                    $processedTemplate = $this->_systemTemplates->processTemplate($template, $replacements, ['to', 'subject', 'template']);
                    $this->_systemTemplates->sendTemplate($processedTemplate);

                    // Erase the error codes for all previous failed invoices
                    $this->_company->getCompanyInvoice()->resetPTErrorCodesForCompany($companyId);

                    $booSuccess = true;
                    $strMessage = $this->_tr->translate('Thank you for updating your credit card. Your future invoices will be charged on this card.');

                    // Try to charge previously failed invoices
                    $arrChargingResult = $this->_company->getCompanyInvoice()->chargePreviousFailedInvoices($companyId);
                    if ($arrChargingResult['success'] && count($arrChargingResult['invoices_array'])) {
                        if ($arrChargingResult['failed_invoices']) {
                            $booSuccess = false;
                            $strMessage = $this->_tr->translate('Unfortunately, we could not process your card successfully. Please provide us with a different credit card.');
                        } else {
                            $strMessage = $this->_tr->translate('Thank you for your payment. Your card was processed successfully.');
                        }
                    }
                }
            } catch (Exception $e) {
                $booSuccess = false;
                $strMessage = $e->getMessage();
            }
        }

        // Return result in json format
        $arrResult = array(
            'success'       => $booSuccess,
            'message'       => $strMessage,
            'pt_profile_id' => $customerRefNum
        );

        return new JsonModel($arrResult);
    }

    public function updateDefaultCompanyAdminAction()
    {
        $strError = '';

        try {
            // Get incoming info
            $companyId = Json::decode($this->params()->fromPost('company_id'), Json::TYPE_ARRAY);
            $adminId   = Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY);

            if (empty($strError) && (!is_numeric($companyId) || empty($companyId))) {
                $strError = $this->_tr->translate('Incorrectly selected company.');
            }

            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToCompany($companyId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && (!is_numeric($adminId) || empty($adminId))) {
                $strError = $this->_tr->translate('Incorrectly selected admin.');
            }

            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($adminId)) {
                $strError = $this->_tr->translate('Incorrectly selected admin.');
            }

            if (empty($strError) & !$this->_company->updateCompanyAdmin($companyId, $adminId)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel(array('success' => empty($strError), 'message' => $strError));
    }


    public function showInvoicePdfAction()
    {
        $view = new ViewModel(
            [
                'content' => null
            ]
        );
        $view->setTerminal(true);

        $invoiceId = (int)$this->findParam('invoiceId');
        if ($this->_auth->isCurrentUserSuperadmin()) {
            $companyId = (int)$this->findParam('companyId');
        } else {
            // Company admin can download only invoices for the own company
            $companyId   = $this->_auth->getCurrentUserCompanyId();
            $invoiceInfo = $this->_company->getCompanyInvoice()->getInvoiceDetails($invoiceId, false);
            if (!isset($invoiceInfo['company_id']) || $invoiceInfo['company_id'] != $companyId) {
                $invoiceId = 0;
            }
        }

        if (!empty($invoiceId)) {
            $fileInfo = $this->_company->showInvoicePdf($companyId, $invoiceId);
            if ($fileInfo instanceof FileInfo) {
                if ($fileInfo->local) {
                    return $this->downloadFile($fileInfo->path, $fileInfo->name, $fileInfo->mime, true, false);
                } else {
                    $url = $this->_files->getCloud()->getFile($fileInfo->path, $fileInfo->name, true, false);
                    if ($url) {
                        return $this->redirect()->toUrl($url);
                    }
                }
            }
        }

        $view->setVariables(
            [
                'content' => $this->_tr->translate('File not found.')
            ],
            true
        );
        $view->setTemplate('layout/plain');
        return $view;
    }

    public function deleteInvoiceAction()
    {
        $success = false;

        if ($this->_auth->isCurrentUserSuperadmin()) {
            $company_invoice_id = $this->params()->fromPost('company_invoice_id');
            if (!empty($company_invoice_id)) {
                $success = $this->_company->getCompanyInvoice()->deleteInvoices(array($company_invoice_id));
            }
        }

        return new JsonModel(array('success' => $success));
    }

    public function generateInvoiceTemplateAction()
    {
        $booError        = true;
        $strMessage      = '';
        $templateBody    = '';
        $templateSubject = '';
        $invoiceId       = 0;

        if ($this->_auth->isCurrentUserSuperadmin()) {
            $companyId        = Json::decode($this->findParam('company_id'), Json::TYPE_ARRAY);
            $net              = Json::decode($this->findParam('net'), Json::TYPE_ARRAY);
            $tax              = Json::decode($this->findParam('tax'), Json::TYPE_ARRAY);
            $amount           = Json::decode($this->findParam('amount'), Json::TYPE_ARRAY);
            $invoiceDate      = Json::decode($this->findParam('date_of_invoice'), Json::TYPE_ARRAY);
            $notes            = trim($this->_filter->filter(Json::decode($this->findParam('notes', ''), Json::TYPE_ARRAY)));
            $booSpecialCharge = Json::decode($this->findParam('booSpecialCharge'), Json::TYPE_ARRAY);

            // ******************************
            // * Check incoming info
            // ******************************

            // Check if net is correct
            if (empty($strMessage) && !is_numeric($net)) {
                $strMessage = $this->_tr->translate('Incorrect Net.');
            }


            // Check if tax is correct
            if (empty($strMessage) && !is_numeric($tax)) {
                $strMessage = $this->_tr->translate('Incorrect Tax.');
            }


            // Check if amount is correct
            if (empty($strMessage) && !is_numeric($amount)) {
                $strMessage = $this->_tr->translate('Incorrect Total.');
            }

            if (empty($strMessage) && !$this->_settings->floatCompare($net + $tax, $amount)) {
                $strMessage = $this->_tr->translate('The sum of Net and Tax fields must be equal to Total field.');
            }


            // Check if 'Notes' field is correct
            if (empty($strMessage) && empty($notes)) {
                $strMessage = $this->_tr->translate('Incorrect description.');
            }


            // Check if date of invoice is correct
            if (empty($strMessage) && empty($invoiceDate)) {
                $strMessage = $this->_tr->translate('Incorrect date of invoice.');
            }


            // Check if company is correctly selected
            if (empty($strMessage) && !is_numeric($companyId)) {
                $strMessage = $this->_tr->translate('Incorrectly selected company.');
            }


            // Check if current user can create invoice for this company
            // Of course, superadmin can update
            if (empty($strMessage) && !$this->_auth->isCurrentUserSuperadmin()) {
                if ($this->_auth->getCurrentUserCompanyId() != $companyId) {
                    $strMessage = $this->_tr->translate('Insufficient access rights.');
                }
            }

            if (empty($strMessage)) {
                try {
                    $companyDetails       = $this->_company->getCompanyAndDetailsInfo($companyId);
                    $adminInfo            = $this->_members->getMemberInfo($companyDetails['admin_id']);
                    $recurringInvoiceData = $this->_company->getCompanyInvoice()->prepareDataForRecurringInvoice($companyId);

                    $oInvoices      = $this->_company->getCompanyInvoice();
                    $arrInvoiceData = array(
                        'company_id'                 => $companyId,
                        'invoice_number'             => $oInvoices->generateUniqueInvoiceNumber(),
                        'invoice_date'               => $invoiceDate,
                        'total'                      => round($amount, 2),
                        'message'                    => 'Invoice created temporary. Can be deleted if will be not updated.',
                        'subject'                    => '!!! Temporary created invoice !!!',
                        'free_users'                 => $recurringInvoiceData['free_users'],
                        'free_clients'               => $recurringInvoiceData['free_clients'],
                        'free_storage'               => $recurringInvoiceData['free_storage'],
                        'additional_users'           => $recurringInvoiceData['additional_users'],
                        'additional_users_fee'       => round($recurringInvoiceData['additional_users_fee'], 2),
                        'additional_storage'         => $recurringInvoiceData['additional_storage'],
                        'additional_storage_charges' => round($recurringInvoiceData['additional_storage_charges'], 2),
                        'subscription_fee'           => round($recurringInvoiceData['subscription_fee'], 2),
                        'tax'                        => round($tax, 2),
                        'subtotal'                   => round($net, 2),
                        'deleted'                    => 'Y' // Required, will be updated later
                    );

                    // Save Invoice
                    $invoiceId = $oInvoices->insertInvoice($arrInvoiceData);

                    // Prepare template and all related info
                    $templateId = $booSpecialCharge ? 'Special CC Charge' : 'Notes or Special Invoice';
                    $template   = SystemTemplate::loadOne(['title' => $templateId]);

                    // Get parsed message
                    // Preparing preliminary info
                    $arrInvoiceData        = $this->_company->getCompanyInvoice()->getInvoiceDetails($invoiceId, false);
                    $arrInvoiceData['gst'] = $arrInvoiceData['tax'];

                    $replacements      = $this->_systemTemplates->getGlobalTemplateReplacements();
                    $replacements      += $this->_company->getTemplateReplacements($companyDetails, $adminInfo);
                    $replacements      += $this->_company->getCompanyInvoice()->getTemplateReplacements($arrInvoiceData);
                    $replacements      += $this->_company->getCompanyInvoice()->getTemplateReplacements([
                        'net'    => round($net, 2),
                        'tax'    => round($tax, 2),
                        'amount' => round($amount, 2),
                        'notes'  => $notes,
                    ], CompanyInvoice::TEMPLATE_SPECIAL_CC_CHARGE);

                    $processedTemplate = $this->_systemTemplates->processTemplate($template, $replacements, ['to', 'subject', 'template']);
                    $templateBody      = $processedTemplate->template;
                    $templateSubject   = $processedTemplate->subject;

                    $booError          = false;
                } catch (Exception $e) {
                    $strMessage = $e->getMessage();
                }
            }
        }

        $arrResult = array(
            'error'            => $booError,
            'error_message'    => $strMessage,
            'template_body'    => $templateBody,
            'template_subject' => $templateSubject,
            'invoice_id'       => $invoiceId
        );
        return new JsonModel($arrResult);
    }

    public function runChargeAction()
    {
        $booPTError = false;
        $strError   = '';

        try {
            if (!$this->_auth->isCurrentUserSuperadmin()) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $companyId            = Json::decode($this->params()->fromPost('company_id'), Json::TYPE_ARRAY);
            $invoiceId            = Json::decode($this->params()->fromPost('invoice_id'), Json::TYPE_ARRAY);
            $booInvoiceChargeOnly = Json::decode($this->params()->fromPost('booInvoiceChargeOnly'), Json::TYPE_ARRAY);
            $booSpecialCharge     = Json::decode($this->params()->fromPost('booSpecialCharge'), Json::TYPE_ARRAY);

            // ******************************
            // * Check incoming info
            // ******************************

            // Check if company is correctly selected
            if (empty($strError) && !is_numeric($companyId)) {
                $strError = $this->_tr->translate('Incorrectly selected company.');
            }

            // Check if PT profile is created (for Special Charge only)
            $arrCompanyInfo = array();
            if (empty($strError) && $booSpecialCharge) {
                // Get info about the company
                $arrCompanyInfo = $this->_company->getCompanyAndDetailsInfo($companyId);

                if (empty($arrCompanyInfo['paymentech_profile_id'])) {
                    $strError = $this->_tr->translate("PT profile was not created yet. Please use 'Update Credit Card on File' functionality and try again.");
                }
            }


            // For Special Charge payments must be enabled
            if (empty($strError) && $booSpecialCharge && !$this->_config['payment']['enabled']) {
                $strError = $this->_tr->translate("Communication with PT is turned off. Please turn it on in config file and try again.");
            }

            // Check invoice id
            $oInvoices = $this->_company->getCompanyInvoice();
            if (empty($strError)) {
                if (!is_numeric($invoiceId)) {
                    $strError = $this->_tr->translate('Incorrect invoice id.');
                } else {
                    // Load info about this invoice
                    $arrInvoiceInfo = $oInvoices->getInvoiceDetails($invoiceId, false);

                    // And check if the same company is used
                    if (empty($arrInvoiceInfo) || $arrInvoiceInfo['company_id'] != $companyId) {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                }
            }

            if (empty($strError) && !$booInvoiceChargeOnly) {
                $amount           = Json::decode($this->params()->fromPost('amount'), Json::TYPE_ARRAY);
                $date_of_invoice  = Json::decode($this->params()->fromPost('date_of_invoice'), Json::TYPE_ARRAY);
                $notes            = trim($this->_filter->filter(Json::decode($this->params()->fromPost('notes', ''), Json::TYPE_ARRAY)));
                $template_body    = Json::decode($this->params()->fromPost('template_body'), Json::TYPE_ARRAY);
                $template_subject = $this->_filter->filter(Json::decode($this->params()->fromPost('template_subject'), Json::TYPE_ARRAY));

                // Check if amount is correct
                if (!is_numeric($amount)) {
                    $strError = $this->_tr->translate('Incorrect amount.');
                }

                // Check if 'Notes' field is correct
                if (empty($strError) && empty($notes)) {
                    $strError = $this->_tr->translate('Incorrect description.');
                }


                // Check if date of invoice is correct
                if (empty($strError) && empty($date_of_invoice)) {
                    $strError = $this->_tr->translate('Incorrect date of invoice.');
                }

                // Check if template is correctly entered
                if (empty($strError) && empty($template_body)) {
                    $strError = $this->_tr->translate('Template cannot be empty.');
                }


                // Check if template is correctly entered
                if (empty($strError) && empty($template_subject)) {
                    $strError = $this->_tr->translate('Template subject cannot be empty.');
                }

                if (empty($strError)) {
                    // Update invoice to be sure that amount is the same as in the parsed template
                    $arrUpdate = array(
                        'total'        => $amount,
                        'invoice_date' => $date_of_invoice,
                        'message'      => $template_body,
                        'subject'      => $template_subject,
                        'deleted'      => 'N'
                    );

                    $oInvoices->updateInvoice($arrUpdate, $invoiceId);
                }
            }


            // ******************************
            // * Run charge and create invoice
            // ******************************
            if (empty($strError) && $booSpecialCharge) {
                // Send request to PT to charge money
                $arrOrderResult = $oInvoices->chargeSavedInvoice($invoiceId);

                if ($arrOrderResult['error']) {
                    // Mark invoice as unpaid, so we'll not try to charge it again later
                    $oInvoices->markInvoiceUnpaid($invoiceId);

                    // Error happened during the charge
                    $booPTError = true;
                    $strError   =
                        $this->_tr->translate('Processing error:') .
                        sprintf("<div style='padding: 10px 0; font-style:italic;'>%s</div>", $arrOrderResult['message']) .
                        $this->_tr->translate(
                            'Please review your entries &amp; try again. ' .
                            'If the error shown is not related to your credit card, ' .
                            'please contact our support to resolve the issue promptly.'
                        );
                } else {
                    $arrInvoiceUpdate = array(
                        'mode_of_payment' => $arrCompanyInfo['paymentech_mode_of_payment']
                    );
                    $oInvoices->updateInvoice($arrInvoiceUpdate, $invoiceId);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'error'         => !empty($strError),
            'error_message' => $strError,
            'booPTError'    => $booPTError
        );
        return new JsonModel($arrResult);
    }

    public function exportAction()
    {
        $strError = '';

        try {
            ini_set('memory_limit', '-1');

            $export = $this->_filter->filter($this->params()->fromQuery('export'));
            if ($this->_auth->isCurrentUserSuperadmin()) {
                $companyId = (int)$this->params()->fromQuery('company_id');
            } else {
                $companyId = $this->_auth->getCurrentUserCompanyId();
            }

            $exportStart = $this->params()->fromQuery('exportStart');
            $exportStart = empty($exportStart) ? 0 : $exportStart;
            $exportRange = $this->params()->fromQuery('exportRange');
            $exportRange = empty($exportRange) ? 0 : $exportRange;

            // Related to export of T/A
            $exportTaId   = $this->params()->fromQuery('exportTaId');
            $exportTaId   = empty($exportTaId) ? 0 : $exportTaId;
            $exportFilter = $this->params()->fromQuery('exportFilter');
            $exportFilter = empty($exportFilter) ? 0 : $exportFilter;
            $firstParam   = $this->params()->fromQuery('firstParam');
            $firstParam   = empty($firstParam) ? 0 : $firstParam;
            $secondParam  = $this->params()->fromQuery('secondParam');
            $secondParam  = empty($secondParam) ? 0 : $secondParam;

            if (!$this->_auth->isCurrentUserSuperadmin()) {
                $arrCompanyInfo = $this->_company->getCompanyDetailsInfo($companyId);
                if (!isset($arrCompanyInfo['allow_export']) || $arrCompanyInfo['allow_export'] != 'Y') {
                    $strError = $this->_tr->translate('Export is not allowed.');
                }
            }

            if (empty($strError)) {
                $result = $this->_company->getCompanyExport()->export($companyId, $export, $exportStart, $exportRange, $exportTaId, $exportFilter, $firstParam, $secondParam);

                if (is_array($result)) {
                    list($fileName, $spreadsheet) = $result;

                    $pointer        = fopen('php://output', 'wb');
                    $bufferedStream = new BufferedStream(FileTools::getMimeByFileName($fileName), null, "attachment; filename=\"$fileName\"");
                    $bufferedStream->setStream($pointer);

                    $writer = new Xlsx($spreadsheet);
                    $writer->save('php://output');
                    fclose($pointer);
                } else {
                    $strError = $result;
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view = new ViewModel(['content' => $strError]);
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }

    public function exportCompaniesMainInfoAction()
    {
        set_time_limit(60 * 60); // 1 hour
        ini_set('memory_limit', '-1');
        session_write_close();

        $strError = '';

        try {
            $arrCompaniesIds  = Json::decode($this->params()->fromPost('companies_ids'));
            $arrCompaniesInfo = array();
            foreach ($arrCompaniesIds as $companyId) {
                $arrCompanyInfo = $this->_company->getCompanyAndDetailsInfo($companyId, array(), false);
                if (empty($arrCompanyInfo['company_id'])) {
                    $strError = $this->_tr->translate('Company was selected incorrectly.');
                    break;
                }

                $arrCompaniesInfo[$companyId] = $arrCompanyInfo;
            }

            if (empty($strError) && empty($arrCompaniesInfo)) {
                $strError = $this->_tr->translate('No companies were selected.');
            }

            if (empty($strError)) {
                list($strError, $zipFilePath) = $this->_company->getCompanyExport()->exportCompaniesMainInfo($arrCompaniesInfo);

                if (empty($strError)) {
                    if (empty($zipFilePath) || !is_file($zipFilePath)) {
                        $strError = $this->_tr->translate('Internal error. Zip was not created.');
                    } else {
                        return $this->downloadFile(
                            $zipFilePath,
                            'export ' . date('Y-m-d H-i-s') . '.zip',
                            'application/zip'
                        );
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view = new ViewModel(['content' => $strError]);
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }

    public function removeCompanyLogoAction() {
        $view     = new JsonModel();
        $strError = '';
        try {
            if ($this->_auth->isCurrentUserSuperadmin()) {
                $companyId = (int)$this->findParam('company_id');
            } else {
                $companyId = $this->_auth->getCurrentUserCompanyId();
            }

            if(!empty($companyId)) {
                $booLocal = $this->_company->isCompanyStorageLocationLocal($companyId);
                $logoPath = $this->_company->getCompanyLogoFolderPath($companyId, $booLocal) . DIRECTORY_SEPARATOR . 'logo';

                if ($booLocal) {
                    if (file_exists($logoPath)) {
                        $booFileRemoved = unlink($logoPath);
                    } else {
                        $booFileRemoved = true;
                    }
                } else {
                    $booFileRemoved = $this->_files->getCloud()->deleteObject($logoPath);
                }

                if(!$booFileRemoved) {
                    $strError = $this->_tr->translate('Internal error. Company logo was not removed.');
                } else {
                    $this->_db2->update('company', ['companyLogo' => null], ['company_id' => $companyId]);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError
        );
        return $view->setVariables($arrResult);
    }

    public function caseNumberSettingsAction()
    {
        $view = new ViewModel();

        $title = $this->_tr->translate('Case File Number Settings');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $companyId = $this->_auth->getCurrentUserCompanyId();
        $view->setVariable('arrCaseNumberSettings', $this->_clients->getCaseNumber()->getCompanyCaseNumberSettings($companyId));
        $view->setVariable('arrClientProfileIdSettings', $this->_company->getCompanyClientProfileIdSettings($companyId));

        // Use different labels in relation to the website version + config settings
        $booAustralia  = $this->_config['site_version']['version'] == 'australia';
        $booSubmission = $this->_config['site_version']['clients']['generate_case_number_on'] === 'submission';
        $view->setVariable(
            'arrCaseNumberLabels',
            array(
                'case_number_start_from'      => ($booSubmission ? $this->_tr->translate('and increment by one for each case submitted') : $this->_tr->translate('and increment by one for each new case created')) . ($booAustralia ? '' : '.'),
                'case_number_start_duplicate' => $booSubmission ? $this->_tr->translate('and increment by one for each case submitted skipping duplicate numbers') : $this->_tr->translate('and increment by one for each new case created skipping duplicate numbers'),
                'subclass'                    => $this->_company->getCurrentCompanyDefaultLabel('categories') . ' ' . $this->_tr->translate('Abbreviation'),
                'case_type'                   => $this->_company->getCurrentCompanyDefaultLabel('case_type'),
                'first_name_label'            => $this->_company->getCurrentCompanyDefaultLabel('first_name'),
                'last_name_label'             => $this->_company->getCurrentCompanyDefaultLabel('last_name'),
            )
        );

        return $view;
    }

    public function caseNumberSettingsSaveAction()
    {
        $booError   = true;
        $strMessage = '';
        try {
            $arrParams = $this->params()->fromPost();

            $arrToSave = array();
            $filter    = new StripTags();
            foreach ($arrParams as $key => $val) {
                if (preg_match('/^cn-(.*)$/s', $key)) {
                    $arrToSave[$key] = $filter->filter(trim($val));
                }
            }

            if (empty($strMessage) && (!array_key_exists('cn-generate-number', $arrToSave) || !in_array($arrToSave['cn-generate-number'], array('generate', 'not-generate')))) {
                $strMessage = $this->_tr->translate('Radio button was checked incorrectly.');
            }

            if (empty($strMessage) && $arrToSave['cn-generate-number'] == 'generate') {
                if (array_key_exists('cn-include-fixed-prefix', $arrToSave) && $arrToSave['cn-include-fixed-prefix'] != 'on') {
                    $strMessage = $this->_tr->translate('Checkbox "Include a fixed prefix" was checked incorrectly.');
                }

                if (empty($strMessage) && array_key_exists('cn-include-fixed-prefix', $arrToSave) &&
                    (!array_key_exists('cn-include-fixed-prefix-text', $arrToSave))) {
                    $strMessage = $this->_tr->translate('Please enter "a fixed prefix".');
                }

                if (empty($strMessage) && array_key_exists('cn-name-prefix', $arrToSave) && $arrToSave['cn-name-prefix'] != 'on') {
                    $strMessage = $this->_tr->translate('Checkbox "Include name prefix" was checked incorrectly.');
                }

                if (empty($strMessage) && array_key_exists('cn-name-prefix', $arrToSave) && (!array_key_exists('cn-name-prefix-family-name', $arrToSave) || !is_numeric($arrToSave['cn-name-prefix-family-name']))) {
                    $strMessage = sprintf(
                        $this->_tr->translate('Please enter "first letter(s) of the %s".'),
                        $this->_company->getCurrentCompanyDefaultLabel('last_name')
                    );
                }

                if (empty($strMessage) && array_key_exists('cn-name-prefix', $arrToSave) && (!array_key_exists('cn-name-prefix-given-names', $arrToSave) || !is_numeric($arrToSave['cn-name-prefix-given-names']))) {
                    $strMessage = sprintf(
                        $this->_tr->translate('Please enter "first letter(s) of the client %s".'),
                        $this->_company->getCurrentCompanyDefaultLabel('first_name')
                    );
                }

                if (empty($strMessage) && array_key_exists('cn-increment', $arrToSave) && $arrToSave['cn-increment'] != 'on') {
                    $strMessage = $this->_tr->translate('Checkbox "total count of cases for the client" was checked incorrectly.');
                }

                if (empty($strMessage) && array_key_exists('cn-increment-employer', $arrToSave) && $arrToSave['cn-increment-employer'] != 'on') {
                    $strMessage = $this->_tr->translate('Checkbox "Use Employer Case count if the case linked to an Employer" was checked incorrectly.');
                }

                if (empty($strMessage) && !array_key_exists('cn-increment', $arrToSave)) {
                    unset($arrToSave['cn-increment-employer']);
                }

                if (empty($strMessage) && array_key_exists('cn-subclass', $arrToSave) && $arrToSave['cn-subclass'] != 'on') {
                    $strMessage = $this->_tr->translate(
                        sprintf(
                            'Checkbox "Include %s Digits" was checked incorrectly.',
                            $this->_company->getCurrentCompanyDefaultLabel('categories')
                        )
                    );
                }

                if (empty($strMessage) && array_key_exists('cn-start-from', $arrToSave) && $arrToSave['cn-start-from'] != 'on') {
                    $strMessage = $this->_tr->translate('Checkbox "Start Case File #" was checked incorrectly.');
                }

                if (empty($strMessage) && array_key_exists('cn-start-from', $arrToSave) && (!array_key_exists('cn-start-from-text', $arrToSave) || !is_numeric($arrToSave['cn-start-from-text']))) {
                    $strMessage = $this->_tr->translate('Please enter "Case File #".');
                }

                if (empty($strMessage) && array_key_exists('cn-start-from', $arrToSave) && !array_key_exists('cn-global-or-based-on-case-type', $arrToSave)) {
                    $caseTypeTerm = $this->_company->getCurrentCompanyDefaultLabel('case_type');
                    $strMessage   = $this->_tr->translate('Please select "Global or Based on ' . $caseTypeTerm . '" value.');
                }

                if (empty($strMessage) && array_key_exists('cn-start-number-from', $arrToSave) && $arrToSave['cn-start-number-from'] != 'on') {
                    $strMessage = $this->_tr->translate('Checkbox "Start Case File #" was checked incorrectly.');
                }

                if (empty($strMessage) && array_key_exists('cn-start-number-from', $arrToSave) && (!array_key_exists('cn-start-number-from-text', $arrToSave) || !is_numeric($arrToSave['cn-start-number-from-text']))) {
                    $strMessage = $this->_tr->translate('Please enter "Case File #".');
                }

                if (empty($strMessage) && array_key_exists('cn-start-number-from', $arrToSave) && $this->_config['site_version']['version'] == 'canada' && !array_key_exists('cn-reset-every', $arrToSave)) {
                    $strMessage = $this->_tr->translate('Please select "Reset Every" value.');
                }

                if (empty($strMessage) && (!array_key_exists('cn-separator', $arrToSave) || !in_array($arrToSave['cn-separator'], array('blank', '.', '-', '_', '/')))) {
                    $strMessage = $this->_tr->translate('Please select a separator correctly.');
                }

                if (empty($strMessage) && array_key_exists('cn-read-only', $arrToSave) && $arrToSave['cn-read-only'] != 'on') {
                    $strMessage = $this->_tr->translate('Checkbox "Case File # is Read only Field - User cannot edit field" was checked incorrectly.');
                }

                if (empty($strMessage)) {
                    // Make sure that at least one checkbox is checked,
                    // So the generated number will be not empty
                    $arrCheckboxesToCheck = array(
                        'cn-include-fixed-prefix',
                        'cn-name-prefix',
                        'cn-start-from',
                        'cn-subclass',
                        'cn-increment',
                        'cn-start-number-from',
                        'cn-client-profile-id',
                        'cn-number-of-client-cases',
                    );

                    $booAtLeastOneChecked = false;
                    foreach ($arrCheckboxesToCheck as $checkboxId) {
                        if (array_key_exists($checkboxId, $arrToSave)) {
                            $booAtLeastOneChecked = true;
                            break;
                        }
                    }

                    if (!$booAtLeastOneChecked) {
                        $strMessage = $this->_tr->translate('Please check at least one checkbox, so generated number will be not empty.');
                    }
                }
            } else {
                $arrToSave = array();
            }

            if (empty($strMessage)) {
                if ($this->_clients->getCaseNumber()->saveCaseNumberSettings($this->_auth->getCurrentUserCompanyId(), $arrToSave)) {
                    $strMessage = 'Data saved successfully.';
                    $booError   = false;
                } else {
                    $strMessage = $this->_tr->translate('Internal error.');
                }
            }
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => !$booError,
            'message' => $strMessage
        );
        return new JsonModel($arrResult);
    }

    public function caseNumberSettingsResetCounterAction() {
        $view     = new JsonModel();
        $booError = true;
        try {
            $startFromNumberText = Json::decode($this->findParam('start_from', ''), Json::TYPE_ARRAY);

            $startFromNumberText = (int)$this->_filter->filter(trim($startFromNumberText));

            if (!is_numeric($startFromNumberText)) {
                $strMessage = $this->_tr->translate('Please enter "Case File #".');
            }

            if (empty($strMessage) && $this->_config['site_version']['version'] != 'canada') {
                $strMessage = $this->_tr->translate('This feature is not allowed for your site version.');
            }

            if (empty($strMessage)) {
                $companyId                                       = $this->_auth->getCurrentUserCompanyId();
                $arrCompanySettings                              = $this->_clients->getCaseNumber()->getCompanyCaseNumberSettings($companyId);
                $arrCompanySettings['cn-start-number-from-text'] = $startFromNumberText;

                if ($this->_clients->getCaseNumber()->saveCaseNumberSettings($companyId, $arrCompanySettings)) {
                    $strMessage = 'Data saved successfully.';
                    $booError   = false;
                } else {
                    $strMessage = $this->_tr->translate('Internal error.');
                }
            }
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => !$booError,
            'message' => $strMessage
        );
        return $view->setVariables($arrResult);
    }

    public function getExportEmailUsersAction() {
        $view     = new JsonModel();
        $strError = '';
        $arrUsers = array();

        try {
            if ($this->_auth->isCurrentUserSuperadmin()) {
                $companyId = (int)$this->findParam('companyId');
            } else {
                $companyId = $this->_auth->getCurrentUserCompanyId();
            }

            if (!empty($companyId)) {
                $arrUsers = $this->_company->getCompanyMembersEmails($companyId, 'admin_and_staff', array(), true);
                $arrUsers = array_values($arrUsers);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError,
            'items'   => $arrUsers,
            'count'   => count($arrUsers),
        );

        return $view->setVariables($arrResult);
    }

    public function getExportEmailAccountsAction() {
        $view             = new JsonModel();
        $strError         = '';
        $arrEmailAccounts = array();

        try {
            $memberId = (int)$this->findParam('memberId');
            if (!$this->_auth->isCurrentUserSuperadmin() && !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($strError) && !empty($memberId)) {
                $arrEmailAccounts = array_values(MailAccount::getAccounts($memberId, array('account_id' => 'id', 'email')));
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError,
            'items'   => $arrEmailAccounts,
            'count'   => count($arrEmailAccounts),
        );

        return $view->setVariables($arrResult);
    }

    public function getExportEmailAccountsFoldersAction() {
        $view       = new JsonModel();
        $strError   = '';
        $arrFolders = array();

        try {
            $accountId = (int)$this->findParam('accountId');
            if (!$this->_auth->isCurrentUserSuperadmin()) {
                $mailAccountManager = new MailAccount($accountId);
                $arrAccountInfo     = $mailAccountManager->getAccountDetails();
                if (!isset($arrAccountInfo['member_id']) || !$this->_members->hasCurrentMemberAccessToMember($arrAccountInfo['member_id'])) {
                    $strError = $this->_tr->translate('Insufficient access rights');
                }
            }

            if (empty($strError) && !empty($accountId)) {
                $arrFolders = Folder::getRootFoldersInfo($accountId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError,
            'items'   => $arrFolders,
            'count'   => count($arrFolders),
        );

        return $view->setVariables($arrResult);
    }

    public function exportEmailsAction()
    {
        set_time_limit(60 * 60 * 48); // 48 hours
        ini_set('memory_limit', '1024M');

        session_write_close();

        // We try turn off buffering at all and only respond with correct data
        @ob_end_clean();
        try {
            while (@ob_get_level() > 0) {
                @ob_end_flush();
            }
        } catch (Exception $e) {
        }
        ob_implicit_flush();

        echo "<html><body>";

        $strError = '';

        try {
            if ($this->_auth->isCurrentUserSuperadmin()) {
                $companyId      = (int)$this->findParam('companyId');
                $arrCompanyInfo = $this->_company->getCompanyInfo($companyId);
                if (!isset($arrCompanyInfo['company_id'])) {
                    $strError = $this->_tr->translate('Company was selected incorrectly.');
                }
            } else {
                $companyId = $this->_auth->getCurrentUserCompanyId();
            }

            $accountId          = (int)$this->findParam('accountId');
            $mailAccountManager = new MailAccount($accountId);
            $arrAccountInfo     = $mailAccountManager->getAccountDetails();
            $accountName        = $arrAccountInfo['email'] ?? '';
            $userId             = $arrAccountInfo['member_id'] ?? 0;

            if (empty($strError) && empty($userId)) {
                $strError = $this->_tr->translate('Account was selected incorrectly.');
            }

            if (empty($strError) && empty($accountName)) {
                $strError = $this->_tr->translate('Account was selected incorrectly.');
            }

            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($userId)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            $strFolderIds = $this->_filter->filter($this->findParam('folderIds', ''));
            $arrFolderIds = empty($strFolderIds) ? array() : explode(',', $strFolderIds);
            if (empty($strError) && empty($arrFolderIds)) {
                $strError = 'Please select folders.';
            }

            if (empty($strError)) {
                ExportEmailsLoaderDispatcher::change('Processing...', 0, 0);

                $booLocal = $this->_company->isCompanyStorageLocationLocal($companyId);

                $pathToSharedDocs = $this->_files->getCompanySharedDocsPath($companyId, false, $booLocal);
                $pathToEmails     = rtrim($pathToSharedDocs . '/' . $accountName, '/');
                $pathToEmails     = $this->_files->generateFolderName($pathToEmails, $booLocal);

                // Create "Shared Docs" folder if it does not exist
                if ($booLocal) {
                    $this->_files->createFTPDirectory($pathToEmails);
                } else {
                    $this->_files->createCloudDirectory($pathToEmails);
                }

                //At first tome it will be done for one folder
                $foldersList = Folder::getFoldersList($accountId);

                $totalMailsCount = 0;

                $arrFolderEmails = array();
                foreach ($arrFolderIds as $folderId) {
                    $arrFolderEmails[$folderId] = Message::getEmailsList($accountId, $folderId, 'sent_date', 'ASC', false, false, true);

                    $totalMailsCount += count($arrFolderEmails[$folderId]);
                }

                $currentTimeZone = date_default_timezone_get();

                $currentEmail = 0;
                foreach ($arrFolderIds as $folderId) {
                    $currentEmailFolderPath = $pathToEmails . '/' . $foldersList[$folderId]['label'];
                    if ($booLocal) {
                        $this->_files->createFTPDirectory($currentEmailFolderPath);
                    } else {
                        $this->_files->createCloudDirectory($currentEmailFolderPath);
                    }

                    $companyEmailsPath = $this->_files->getCompanyEmailAttachmentsPath($this->_auth->getCurrentUserCompanyId(), $booLocal);
                    foreach ($arrFolderEmails[$folderId] as $arrEmailInfo) {
                        $arrAttachments = $this->_mailer->getMailAttachments($arrEmailInfo['id'], $booLocal, $companyEmailsPath);

                        $form = array(
                            'from'              => $arrEmailInfo['from'],
                            'email'             => $arrEmailInfo['to'],
                            'cc'                => $arrEmailInfo['cc'],
                            'bcc'               => $arrEmailInfo['bcc'],
                            'subject'           => $arrEmailInfo['subject'],
                            'message'           => $arrEmailInfo['body_html'],
                            'forwarded'         => $arrEmailInfo['forwarded'],
                            'replied'           => $arrEmailInfo['replied'],
                            'attachments_array' => $arrAttachments,
                            'original_mail_id'  => (int)$arrEmailInfo['id'],
                        );

                        $senderInfo = $this->_members->getMemberInfo();
                        list($res, $emailMsg) = $this->_mailer->send($form, $arrAttachments, $senderInfo, false, false, false, true);
                        if ($res === true) {
                            if (isset($emailMsg['header'])) {
                                date_default_timezone_set('UTC');
                                $emailMsg['header'] = preg_replace('/Date: (.*)/', 'Date: ' . date('Y-m-d H:i:s', $arrEmailInfo['sent_date_timestamp']), $emailMsg['header']);
                                date_default_timezone_set($currentTimeZone);
                            }

                            $this->_mailer->saveRawEmailToFolder($emailMsg, $arrEmailInfo['subject'], $currentEmailFolderPath, $booLocal, $arrEmailInfo['sent_date_timestamp']);
                        }
                        $currentEmail++;
                        ExportEmailsLoaderDispatcher::changeStatus($currentEmail, $totalMailsCount);
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            ExportEmailsLoaderDispatcher::change($strError, 100, true);
        }

        echo "</body></html>";
        exit();
    }

    public function exportProfilesAndCasesAction()
    {
        set_time_limit(10 * 60); // 10 minutes, no more
        ini_set('memory_limit', '-1');
        session_write_close();

        $strError          = '';
        $strFilePath       = '';
        $booContinue       = false;
        $progressPercent   = 0;
        $progressMessage   = '';
        $totalClientsCount = 0;

        try {
            // Load it once, use later
            $totalClientsCount = (int)$this->params()->fromPost('totalClientsCount');

            $start = (int)$this->params()->fromPost('start');
            $limit = (int)$this->params()->fromPost('limit');
            $limit = $limit < 0 || $limit > 100000 ? 10000 : $limit;

            // Superadmin can export data for a specific company
            if ($this->_auth->isCurrentUserSuperadmin()) {
                $companyId      = (int)$this->params()->fromPost('companyId');
                $arrCompanyInfo = $this->_company->getCompanyInfo($companyId);
                if (!isset($arrCompanyInfo['company_id'])) {
                    $strError = $this->_tr->translate('Company was selected incorrectly.');
                }
            } else {
                $companyId = $this->_auth->getCurrentUserCompanyId();
            }

            if (empty($strError) && empty($start)) {
                // Load it once, use in next requests
                $totalClientsCount = $this->_company->getCompanyClientsCount($companyId);
            }

            if (empty($strError) && empty($totalClientsCount)) {
                $strError = $this->_tr->translate('There are no clients in the company.');
            }


            if (empty($strError)) {
                list($strError, $strFilePath) = $this->_clients->exportCompanyProfilesAdCases($companyId, $start, $limit);

                if (empty($strError)) {
                    $strFilePath = $this->_encryption->encode($strFilePath . '#' . $companyId);

                    $currentExportedCount = $start + $limit;
                    $currentExportedCount = min($currentExportedCount, $totalClientsCount);

                    $progressPercent = ($currentExportedCount / $totalClientsCount) * 100;
                    $progressPercent = $progressPercent > 99 ? 100 : $progressPercent;

                    $booContinue = $currentExportedCount < $totalClientsCount;

                    if ($booContinue) {
                        $progressMessage = sprintf(
                            $this->_tr->translatePlural('Exported %d client from %d...', 'Exported %d clients from %d...', $currentExportedCount),
                            $currentExportedCount,
                            $totalClientsCount
                        );
                    } else {
                        $progressMessage = sprintf(
                            $this->_tr->translatePlural('Sucessfully exported %d client', 'Sucessfully exported all %d clients', $currentExportedCount),
                            $currentExportedCount,
                            $totalClientsCount
                        );
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'           => empty($strError),
            'msg'               => $strError,
            'booContinue'       => $booContinue,
            'progressPercent'   => $progressPercent,
            'progressMessage'   => $progressMessage,
            'filePath'          => $strFilePath,
            'totalClientsCount' => $totalClientsCount,
        );

        return new JsonModel($arrResult);
    }

    public function generateProfilesAndCasesExportFileAction()
    {
        ini_set('memory_limit', '-1');
        session_write_close();

        $strError          = '';
        $generatedFilePath = '';

        try {
            $arrFilesPaths = Json::decode($this->params()->fromPost('arrFiles'), Json::TYPE_ARRAY);
            if (!is_array($arrFilesPaths) || empty($arrFilesPaths)) {
                $strError = $this->_tr->translate('Incorrect info');
            }

            $companyId         = 0;
            $arrRealFilesPaths = [];
            if (empty($strError)) {
                // Superadmin can export data for a specific company
                if ($this->_auth->isCurrentUserSuperadmin()) {
                    $companyId      = (int)Json::decode($this->params()->fromPost('companyId'), Json::TYPE_ARRAY);
                    $arrCompanyInfo = $this->_company->getCompanyInfo($companyId);
                    if (!isset($arrCompanyInfo['company_id'])) {
                        $strError = $this->_tr->translate('Company was selected incorrectly.');
                    }
                } else {
                    $companyId = $this->_auth->getCurrentUserCompanyId();
                }

                // Check each file path
                foreach ($arrFilesPaths as $strFilePath) {
                    $strFilePath = $this->_encryption->decode($strFilePath);
                    if (preg_match('/(.*)#(\d+)/', $strFilePath, $regs)) {
                        $realPath       = $regs[1];
                        $checkCompanyId = $regs[2];

                        if ($companyId != $checkCompanyId || !file_exists($realPath)) {
                            $strError = $this->_tr->translate('Incorrect file info');
                            break;
                        } else {
                            $arrRealFilesPaths[] = $realPath;
                        }
                    } else {
                        $strError = $this->_tr->translate('Incorrect file info');
                        break;
                    }
                }
            }

            if (empty($strError) && empty($arrRealFilesPaths)) {
                $strError = $this->_tr->translate('No files were provided');
            }

            if (empty($strError)) {
                // Generate a file path to the temp file
                $generatedFilePath = tempnam($this->_config['directory']['tmp'], 'csv');

                // Combine all csv files into the one file
                $mainFileHandle = fopen($generatedFilePath, "w+");
                foreach ($arrRealFilesPaths as $thisFilePath) {
                    $thisFileHandle = fopen($thisFilePath, "r");
                    while (!feof($thisFileHandle)) {
                        fwrite($mainFileHandle, fgets($thisFileHandle));
                    }
                    fclose($thisFileHandle);
                    unset($thisFileHandle);
                    fwrite($mainFileHandle, PHP_EOL); //usually last line doesn't have a newline
                }
                fclose($mainFileHandle);
                unset($mainFileHandle);

                if (!is_file($generatedFilePath)) {
                    $strError = $this->_tr->translate('Internal error during files uniting');
                } else {
                    // Delete these temp files, only the combined will be left
                    foreach ($arrRealFilesPaths as $thisFilePath) {
                        unlink($thisFilePath);
                    }

                    $generatedFilePath = $this->_encryption->encode($generatedFilePath . '#' . $companyId);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'  => empty($strError),
            'msg'      => $strError,
            'filePath' => $generatedFilePath,
        );

        return new JsonModel($arrResult);
    }

    public function downloadExportedProfilesAndCasesAction()
    {
        ini_set('memory_limit', '-1');
        session_write_close();

        try {
            $strFilePath = Json::decode($this->params()->fromPost('filePath'), Json::TYPE_ARRAY);
            if (empty($strFilePath)) {
                $strError = $this->_tr->translate('Incorrect info');
            }

            $companyId = 0;
            if (empty($strError)) {
                // Superadmin can export data for a specific company
                if ($this->_auth->isCurrentUserSuperadmin()) {
                    $companyId      = (int)Json::decode($this->params()->fromPost('companyId'), Json::TYPE_ARRAY);
                    $arrCompanyInfo = $this->_company->getCompanyInfo($companyId);
                    if (!isset($arrCompanyInfo['company_id'])) {
                        $strError = $this->_tr->translate('Company was selected incorrectly.');
                    }
                } else {
                    $companyId = $this->_auth->getCurrentUserCompanyId();
                }
            }

            if (empty($strError)) {
                $strFilePath = $this->_encryption->decode($strFilePath);
                if (preg_match('/(.*)#(\d+)/', $strFilePath, $regs)) {
                    $strFilePath    = $regs[1];
                    $checkCompanyId = $regs[2];

                    if ($companyId != $checkCompanyId || !file_exists($strFilePath)) {
                        $strError = $this->_tr->translate('Incorrect file info');
                    }
                } else {
                    $strError = $this->_tr->translate('Incorrect file info');
                }
            }

            if (empty($strError)) {
                $fileName = 'Export Result ' . date('d-m-Y_H-i-s') . '.csv';
                return $this->downloadFile($strFilePath, $fileName, FileTools::getMimeByFileName($fileName), false, true, true);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // That's an error, if we are here
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);
        $view->setVariable('content', $strError);

        return $view;
    }
}
