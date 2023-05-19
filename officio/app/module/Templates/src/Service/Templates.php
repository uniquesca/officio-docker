<?php

namespace Templates\Service;

use Clients\Service\Clients;
use Clients\Service\Members;
use Documents\PhpDocxImage;
use Documents\PhpDocxTable;
use Documents\Service\Documents;
use Exception;
use Files\Service\Files;
use Forms\Service\Pdf;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\EventManager\EventInterface;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\ServiceManager\ServiceManager;
use Officio\Common\Service\Encryption;
use Officio\Email\Models\MailAccount;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Common\Service\Settings;
use Officio\Service\SystemTriggers;
use Officio\Common\SubServiceOwner;
use Phpdocx\Create\CreateDocxFromTemplate;
use Prospects\Service\CompanyProspects;
use Laminas\Validator\EmailAddress;
use Uniques\Php\StdLib\FileTools;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Templates extends SubServiceOwner
{

    /** @var Clients */
    protected $_clients;

    /** @var Country */
    protected $_country;

    /** @var Company */
    protected $_company;

    /** @var Documents */
    protected $_documents;

    /** @var Files */
    protected $_files;

    /** @var CompanyProspects */
    protected $_companyProspects;

    /** @var TemplatesSettings */
    protected $_templatesSettings;

    /** @var SystemTriggers */
    protected $_systemTriggers;

    /** @var Pdf */
    protected $_pdf;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_company          = $services[Company::class];
        $this->_country          = $services[Country::class];
        $this->_files            = $services[Files::class];
        $this->_clients          = $services[Clients::class];
        $this->_documents        = $services[Documents::class];
        $this->_companyProspects = $services[CompanyProspects::class];
        $this->_systemTriggers   = $services[SystemTriggers::class];
        $this->_pdf              = $services[Pdf::class];
        $this->_encryption       = $services[Encryption::class];
    }

    public function init()
    {
        $this->_systemTriggers->getEventManager()->attach(SystemTriggers::EVENT_MEMBER_DELETED, [$this, 'onDeleteMember']);
    }

    /**
     * @return TemplatesSettings
     */
    public function getTemplatesSettings()
    {
        if (is_null($this->_templatesSettings)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_templatesSettings = $this->_serviceContainer->build(TemplatesSettings::class, ['parent' => $this]);
            } else {
                $this->_templatesSettings = $this->_serviceContainer->get(TemplatesSettings::class);
                $this->_templatesSettings->setParent($this);
            }
        }

        return $this->_templatesSettings;
    }

    public function createLetterFromLetterTemplate($templateId, $clientId, $folderId = null, $fileName = null, $arrPayments = array(), $booTemp = false, $caseParentId = 0)
    {
        $booSuccess     = false;
        $copyToFullPath = '';

        try {
            $fileTmpPath = $this->createLetter($templateId, $clientId, $arrPayments, $caseParentId);
            if ($fileTmpPath) {
                if ($booTemp) {
                    $booSuccess     = true;
                    $copyToFullPath = $fileTmpPath;
                } else {
                    $copyToFullPath = rtrim($folderId ?? '', '/') . '/' . $fileName;

                    if ($this->_auth->isCurrentUserCompanyStorageLocal()) {
                        $booSuccess = $this->_files->moveLocalFile($fileTmpPath, $copyToFullPath);
                    } else {
                        $booSuccess = $this->_files->getCloud()->uploadFile($fileTmpPath, $copyToFullPath);
                    }

                    if (!$booSuccess) {
                        throw new Exception(
                            sprintf(
                                $this->_tr->translate('Letter template file was not copied from %s to %s'),
                                $fileTmpPath,
                                $copyToFullPath
                            )
                        );
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess ? $copyToFullPath : false;
    }


    public function onDeleteMember(EventInterface $event)
    {
        $id = $event->getParam('id');
        if (!is_array($id)) {
            $id = array($id);
        }

        $select = (new Select())
            ->from('templates')
            ->columns(['template_id'])
            ->where(['member_id' => $id]);

        $arrTemplateIds = $this->_db2->fetchCol($select);

        $this->delete($arrTemplateIds);
    }

    public function hasAccessToTemplate($templateId)
    {
        return $this->getAccessRightsToTemplate($templateId) !== false;
    }

    public function getFields($filter = 'all', $caseTemplateId = 0)
    {
        $fields = array();
        $n      = 0;
        if ($filter == 'all' || $filter == 'case') {
            //get dynamic fields

            // Apply case template, if needed
            $where = (new Where())->notEqualTo('f.field_id', '')->notEqualTo('f.type', 7);
            if (!empty($caseTemplateId)) {
                $where->equalTo('fg.client_type_id', (int)$caseTemplateId);
            }

            $arrDbFields = $this->_clients->getFields()->getFields(
                array(
                    'select' => [
                        'f' => ['name' => 'company_field_id' , 'label'],
                        'fg' => ['group' => 'title', 'group_id']
                    ],
                    'where'  => [$where],
                    'order'  => ['fg.order ASC', 'o.field_order ASC']
                ),
                false
            );

            //sort fields by groups
            $booAuthAgent                         = false;
            $booAuthorizedAgentsManagementEnabled = $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled();
            foreach ($arrDbFields as $key => $field) {
                if ($key != 0 && $arrDbFields[$key - 1]['group_id'] != $field['group_id']) {
                    ++$n;
                    if ($booAuthAgent) {
                        ++$n;
                        $booAuthAgent = false;
                    }
                }

                if ($booAuthorizedAgentsManagementEnabled && $field['field_type_text_id'] == 'authorized_agents') {
                    $field['n'] = $n + 1;
                    $fields[] = $field;

                    $authAgentFieldName = $field['name'];
                    $fields[] = array('n' => $n + 1, 'group' => 'Authorized Agent', 'name' => $authAgentFieldName . '_salutation', 'label' => 'Salutation', 'authorized_agent_field_id' => $authAgentFieldName);
                    $fields[] = array('n' => $n + 1, 'group' => 'Authorized Agent', 'name' => $authAgentFieldName . '_first_name', 'label' => 'First Name', 'authorized_agent_field_id' => $authAgentFieldName);
                    $fields[] = array('n' => $n + 1, 'group' => 'Authorized Agent', 'name' => $authAgentFieldName . '_last_name', 'label' => 'Last Name', 'authorized_agent_field_id' => $authAgentFieldName);
                    $fields[] = array('n' => $n + 1, 'group' => 'Authorized Agent', 'name' => $authAgentFieldName . '_position', 'label' => 'Position', 'authorized_agent_field_id' => $authAgentFieldName);
                    $fields[] = array('n' => $n + 1, 'group' => 'Authorized Agent', 'name' => $authAgentFieldName . '_company', 'label' => 'Company', 'authorized_agent_field_id' => $authAgentFieldName);
                    $fields[] = array('n' => $n + 1, 'group' => 'Authorized Agent', 'name' => $authAgentFieldName . '_address1', 'label' => 'Address 1', 'authorized_agent_field_id' => $authAgentFieldName);
                    $fields[] = array('n' => $n + 1, 'group' => 'Authorized Agent', 'name' => $authAgentFieldName . '_address2', 'label' => 'Address 2', 'authorized_agent_field_id' => $authAgentFieldName);
                    $fields[] = array('n' => $n + 1, 'group' => 'Authorized Agent', 'name' => $authAgentFieldName . '_city', 'label' => 'City', 'authorized_agent_field_id' => $authAgentFieldName);
                    $fields[] = array('n' => $n + 1, 'group' => 'Authorized Agent', 'name' => $authAgentFieldName . '_state', 'label' => 'State', 'authorized_agent_field_id' => $authAgentFieldName);
                    $fields[] = array('n' => $n + 1, 'group' => 'Authorized Agent', 'name' => $authAgentFieldName . '_country', 'label' => 'Country', 'authorized_agent_field_id' => $authAgentFieldName);
                    $fields[] = array('n' => $n + 1, 'group' => 'Authorized Agent', 'name' => $authAgentFieldName . '_postal_code', 'label' => 'Postal Code', 'authorized_agent_field_id' => $authAgentFieldName);
                    $fields[] = array('n' => $n + 1, 'group' => 'Authorized Agent', 'name' => $authAgentFieldName . '_phone_main', 'label' => 'Phone (Main)', 'authorized_agent_field_id' => $authAgentFieldName);
                    $fields[] = array('n' => $n + 1, 'group' => 'Authorized Agent', 'name' => $authAgentFieldName . '_phone_secondary', 'label' => 'Phone (Secondary)', 'authorized_agent_field_id' => $authAgentFieldName);
                    $fields[] = array('n' => $n + 1, 'group' => 'Authorized Agent', 'name' => $authAgentFieldName . '_email_primary', 'label' => 'Email (Primary)', 'authorized_agent_field_id' => $authAgentFieldName);
                    $fields[] = array('n' => $n + 1, 'group' => 'Authorized Agent', 'name' => $authAgentFieldName . '_email_other', 'label' => 'Email (Other)', 'authorized_agent_field_id' => $authAgentFieldName);
                    $fields[] = array('n' => $n + 1, 'group' => 'Authorized Agent', 'name' => $authAgentFieldName . '_fax', 'label' => 'Fax', 'authorized_agent_field_id' => $authAgentFieldName);
                    $fields[] = array('n' => $n + 1, 'group' => 'Authorized Agent', 'name' => $authAgentFieldName . '_notes', 'label' => 'Notes', 'authorized_agent_field_id' => $authAgentFieldName);
                    $booAuthAgent = true;
                } else {
                    $field['n'] = $n;
                    $fields[] = $field;
                }
            }
            ++$n;

            if (!empty($this->_clients->getFields()->getAccessToDependants())) {
                $fields[] = array('n' => $n, 'group' => 'Dependants', 'name' => 'dependants_list', 'label' => 'List of dependants');
                $fields[] = array('n' => $n, 'group' => 'Dependants', 'name' => 'dependants_count_is_1_message', 'label' => 'A text if dependants count is 1 (defined in the config)');
                $fields[] = array('n' => $n, 'group' => 'Dependants', 'name' => 'dependants_count_is_more_than_1_message', 'label' => 'A text if dependants count is more than 1 (defined in the config)');
                ++$n;
            }
        }

        if ($filter == 'all' || $filter == 'other') {
            //date & Time fields
            $fields[] = array('n' => $n, 'group' => 'Date &amp; Time', 'name' => 'time', 'label' => 'Current Time (' . date('H:i') . ')');
            $fields[] = array('n' => $n, 'group' => 'Date &amp; Time', 'name' => 'today_date', 'label' => 'Today Date (' . $this->_settings->formatDate(date('Y-m-d')) . ')');
            $fields[] = array('n' => $n, 'group' => 'Date &amp; Time', 'name' => 'today_datetime', 'label' => 'Today Date and Time (' . $this->_settings->formatDateTime(date('Y-m-d H:i')) . ')');
            ++$n;

            //user info
            $fields[] = array('n' => $n, 'group' => 'Current Staff Info', 'name' => 'current_user_fName', 'label' => 'Current User First Name');
            $fields[] = array('n' => $n, 'group' => 'Current Staff Info', 'name' => 'current_user_lName', 'label' => 'Current User Last Name');
            $fields[] = array('n' => $n, 'group' => 'Current Staff Info', 'name' => 'current_user_username', 'label' => 'Current User Username');
            $fields[] = array('n' => $n, 'group' => 'Current Staff Info', 'name' => 'current_user_email', 'label' => 'Current User Email Address');
            $fields[] = array('n' => $n, 'group' => 'Current Staff Info', 'name' => 'current_user_email_signature', 'label' => 'Current User Email signature');
            ++$n;

            //company fields
            $fields[] = array('n' => $n, 'group' => 'Company Info', 'name' => 'company_name', 'label' => 'Company Name');
            if (!empty($this->_config['site_version']['check_abn_enabled'])) {
                $fields[] = array('n' => $n, 'group' => 'Company Info', 'name' => 'company_abn', 'label' => 'Company ABN');
            }
            $fields[] = array('n' => $n, 'group' => 'Company Info', 'name' => 'company_address', 'label' => 'Company Address');
            $fields[] = array('n' => $n, 'group' => 'Company Info', 'name' => 'company_city', 'label' => 'Company ' . $this->_settings->getSiteCityLabel());
            $fields[] = array('n' => $n, 'group' => 'Company Info', 'name' => 'company_state', 'label' => 'Company Province/State');
            $fields[] = array('n' => $n, 'group' => 'Company Info', 'name' => 'company_country', 'label' => 'Company Country');
            $fields[] = array('n' => $n, 'group' => 'Company Info', 'name' => 'company_zip', 'label' => 'Company Postal Code/Zip');
            $fields[] = array('n' => $n, 'group' => 'Company Info', 'name' => 'company_phone_1', 'label' => 'Company Phone #1');
            $fields[] = array('n' => $n, 'group' => 'Company Info', 'name' => 'company_phone_2', 'label' => 'Company Phone #2');
            $fields[] = array('n' => $n, 'group' => 'Company Info', 'name' => 'company_email', 'label' => 'Company Email');
            $fields[] = array('n' => $n, 'group' => 'Company Info', 'name' => 'company_fax', 'label' => 'Company Fax');
            $fields[] = array('n' => $n, 'group' => 'Company Info', 'name' => 'company_logo', 'label' => 'Company Logo');
            ++$n;

            //Accounting
            $currencyLabel       = Clients\Accounting::getCurrencyLabel($this->_settings->getSiteDefaultCurrency(false));
            $currencyLabelNoSign = strtolower(Clients\Accounting::getCurrencyLabel($this->_settings->getSiteDefaultCurrency(), false));
            $taLabel             = $this->_company->getCurrentCompanyDefaultLabel('trust_account');
            $fields[]            = array('n' => $n, 'group' => 'Accounting', 'name' => 'outstanding_balance_' . $currencyLabelNoSign, 'label' => 'Outstanding Balance ' . $currencyLabel);
            $fields[]            = array('n' => $n, 'group' => 'Accounting', 'name' => 'outstanding_balance_non_' . $currencyLabelNoSign, 'label' => 'Outstanding Balance Non ' . $currencyLabel);
            $fields[]            = array('n' => $n, 'group' => 'Accounting', 'name' => 'trust_ac_balance_' . $currencyLabelNoSign, 'label' => $taLabel . ' Summary ' . $currencyLabel . ' Balance');
            $fields[]            = array('n' => $n, 'group' => 'Accounting', 'name' => 'trust_ac_balance_non_' . $currencyLabelNoSign, 'label' => $taLabel . ' Summary Non ' . $currencyLabel . ' Balance');
            $fields[]            = array('n' => $n, 'group' => 'Accounting', 'name' => 'financial_transaction_date', 'label' => 'Fees Due Date');
            $fields[]            = array('n' => $n, 'group' => 'Accounting', 'name' => 'financial_transaction_description', 'label' => 'Fees Due Description');
            $fields[]            = array('n' => $n, 'group' => 'Accounting', 'name' => 'financial_transaction_amount', 'label' => 'Fees Due Amount');
            ++$n;

            //Invoice
            $fields[] = array('n' => $n, 'group' => 'Invoices', 'name' => 'invoice_number', 'label' => 'Invoice Number');
            $fields[] = array('n' => $n, 'group' => 'Invoices', 'name' => 'invoice_date', 'label' => 'Date of Invoice');
            $fields[] = array('n' => $n, 'group' => 'Invoices', 'name' => 'invoice_transfer', 'label' => 'Invoice Transfer From');
            $fields[] = array('n' => $n, 'group' => 'Invoices', 'name' => 'invoice_amount_' . $currencyLabelNoSign, 'label' => 'Invoice Amount of Transfer ' . $currencyLabel);
            $fields[] = array('n' => $n, 'group' => 'Invoices', 'name' => 'invoice_amount_non_' . $currencyLabelNoSign, 'label' => 'Invoice Amount of Transfer Non ' . $currencyLabel);

            $fields[] = array('n' => $n, 'group' => 'Invoices', 'name' => 'invoice_net_' . $currencyLabelNoSign, 'label' => 'Invoice Net ' . $currencyLabel);
            $fields[] = array('n' => $n, 'group' => 'Invoices', 'name' => 'invoice_net_non_' . $currencyLabelNoSign, 'label' => 'Invoice Net Non ' . $currencyLabel);

            $fields[] = array('n' => $n, 'group' => 'Invoices', 'name' => 'invoice_tax_' . $currencyLabelNoSign, 'label' => 'Invoice Tax ' . $currencyLabel);
            $fields[] = array('n' => $n, 'group' => 'Invoices', 'name' => 'invoice_tax_non_' . $currencyLabelNoSign, 'label' => 'Invoice Tax Non ' . $currencyLabel);

            $fields[] = array('n' => $n, 'group' => 'Invoices', 'name' => 'invoice_total_' . $currencyLabelNoSign, 'label' => 'Invoice Total ' . $currencyLabel);
            $fields[] = array('n' => $n, 'group' => 'Invoices', 'name' => 'invoice_total_non_' . $currencyLabelNoSign, 'label' => 'Invoice Total Non ' . $currencyLabel);

            $fields[] = array('n' => $n, 'group' => 'Invoices', 'name' => 'invoice_cheque_number', 'label' => 'Invoice Cheque or Transaction #');
            ++$n;

            //Tables
            $fields[] = array('n' => $n, 'group' => 'Tables', 'name' => 'payment_schedule_table', 'label' => 'Payment Schedule');
            $fields[] = array('n' => $n, 'group' => 'Tables', 'name' => 'non_' . $currencyLabelNoSign . '_curr_fin_transaction_table', 'label' => 'CurrFinTransactionTable (Non ' . $currencyLabel . ')');
            $fields[] = array('n' => $n, 'group' => 'Tables', 'name' => $currencyLabelNoSign . '_curr_fin_transaction_table', 'label' => 'CurrFinTransactionTable (' . $currencyLabel . ')');
            $fields[] = array('n' => $n, 'group' => 'Tables', 'name' => 'non_'.$currencyLabelNoSign.'_curr_fin_transaction_table_selected_records_invoice', 'label' => 'CurrFinTransactionTableSelectedRecordsInvoice (Non ' . $currencyLabel . ')');
            $fields[] = array('n' => $n, 'group' => 'Tables', 'name' => $currencyLabelNoSign . '_curr_fin_transaction_table_selected_records_invoice', 'label' => 'CurrFinTransactionTableSelectedRecordsInvoice (' . $currencyLabel . ')');
            $fields[] = array('n' => $n, 'group' => 'Tables', 'name' => 'non_'.$currencyLabelNoSign.'_curr_fin_transaction_table_selected_records_receipt', 'label' => 'CurrFinTransactionTableSelectedRecordsReceipt (Non ' . $currencyLabel . ')');
            $fields[] = array('n' => $n, 'group' => 'Tables', 'name' => $currencyLabelNoSign . '_curr_fin_transaction_table_selected_records_receipt', 'label' => 'CurrFinTransactionTableSelectedRecordsReceipt (' . $currencyLabel . ')');

            $fields[] = array('n' => $n, 'group' => 'Tables', 'name' => 'non_' . $currencyLabelNoSign . '_curr_trustac_summary_table', 'label' => 'CurrTrustAcctSummaryTable (Non ' . $currencyLabel . ')');
            $fields[] = array('n' => $n, 'group' => 'Tables', 'name' => $currencyLabelNoSign . '_curr_trustac_summary_table', 'label' => 'CurrTrustAccountSummaryTable (' . $currencyLabel . ')');
            ++$n;

            //Assigned Deposit
            $fields[] = array('n' => $n, 'group' => 'Assigned Deposit', 'name' => 'assign_deposit_date_from_bank', 'label' => 'Assigned Deposit Date From Bank');
            $fields[] = array('n' => $n, 'group' => 'Assigned Deposit', 'name' => 'assign_deposit_description', 'label' => 'Assigned Deposit Description');
            $fields[] = array('n' => $n, 'group' => 'Assigned Deposit', 'name' => 'assign_deposit_amount', 'label' => 'Assigned Deposit Amount');
            ++$n;

            //Company Prospects
            $fields[] = array('n' => $n, 'group' => 'Company Prospects', 'name' => 'prospect_first_name', 'label' => 'Prospect First name');
            $fields[] = array('n' => $n, 'group' => 'Company Prospects', 'name' => 'prospect_last_name', 'label' => 'Prospect Last name');
            $fields[] = array('n' => $n, 'group' => 'Company Prospects', 'name' => 'prospect_email', 'label' => 'Prospect Email');

            if (!empty($this->_config['site_version']['custom_templates_settings']['comfort_letter']['enabled'])) {
                ++$n;
                $fields[] = array('n' => $n, 'group' => 'Others', 'name' => 'comfort_letter_number', 'label' => 'Comfort Letter Number');
            }
        }

        return $fields;
    }

    public function getTemplate($templateId, $booStripSlashes = true)
    {
        $select = (new Select())
            ->quantifier(Select::QUANTIFIER_DISTINCT)
            ->from('templates')
            ->where(['template_id' => (int)$templateId]);

        $template = $this->_db2->fetchRow($select);

        if (isset($template['message']) && $booStripSlashes) {
            $template['message'] = stripslashes($template['message']);
        }

        return $template;
    }

    public function getTemplateAttachments($templateId)
    {
        $select = (new Select())
            ->from('template_attachments')
            ->columns(['letter_template_id'])
            ->where(['email_template_id' => (int)$templateId]);

        return $this->_db2->fetchAll($select);
    }

    public function getTemplateFileAttachments($templateId)
    {
        $select = (new Select())
            ->from('template_file_attachments')
            ->where(['template_id' => (int)$templateId]);

        return $this->_db2->fetchAll($select);
    }

    public function parseTemplateAttachments($templateId, $clientId, $booFromCron = false, $booEncodeClientIdInPath = false)
    {
        $arrResult = array();

        try {
            $arrAttachments = $this->getTemplateAttachments($templateId);
            $arrFileAttachments = $this->getTemplateFileAttachments($templateId);
            $emailTemplate  = $this->getTemplate($templateId);
            foreach ($arrAttachments as $attachment) {

                if (!$booFromCron && !$this->hasAccessToTemplate($attachment['letter_template_id'])) {
                    continue;
                }

                $letterTemplate = $this->getTemplate($attachment['letter_template_id']);

                $fileId = $this->createLetterFromLetterTemplate($attachment['letter_template_id'], $clientId, null, null, array(), true);
                $fileId = $fileId ? $this->_encryption->encode($fileId) : false;

                if (!$fileId) {
                    continue;
                }

                if ($emailTemplate['attachments_pdf']) {
                    $letterDocxPath      = $this->_encryption->decode($fileId);
                    $arrConvertingResult = $this->_documents->convertToPdf('', $letterDocxPath, $letterTemplate['name'], true);

                    $strError = $arrConvertingResult['error'];

                    if (!empty($strError)) {
                        continue;
                    }

                    // encode with $clientId
                    $fileId                  = $this->_encryption->encode($booEncodeClientIdInPath ? $arrConvertingResult['file_id'] . '#' . $clientId : $arrConvertingResult['file_id']);
                    $fileSize                = $arrConvertingResult['file_size'];
                    $originalFileName        = $letterTemplate['name'] . '.pdf';
                    $booLibreOfficeSupported = false;

                } else {
                    $filePath                = $this->_encryption->decode($fileId);
                    $fileSize                = Settings::formatSize(filesize($filePath) / 1024);
                    $originalFileName        = $letterTemplate['name'] . '.docx';
                    $booLibreOfficeSupported = true;

                    if ($booEncodeClientIdInPath) {
                        $fileId = $this->_encryption->encode($filePath . '#' . $clientId);
                    }
                }

                $arrResult[] = array(
                    'id'                         => $fileId,
                    'size'                       => $fileSize,
                    'libreoffice_supported'      => $booLibreOfficeSupported,
                    'letter_template_attachment' => true,
                    'template_id'                => $attachment['letter_template_id'],
                    'link'                       => '#',
                    'original_file_name'         => $originalFileName
                );
            }

            $companyId     = $this->_auth->getCurrentUserCompanyId();
            $booLocal      = $this->_company->isCompanyStorageLocationLocal($companyId);
            $templatesPath = $this->_files->getCompanyTemplateAttachmentsPath($companyId, $booLocal);
            foreach ($arrFileAttachments as $fileAttachment) {
                $filePath         = $templatesPath . '/' . $templateId . '/' . $fileAttachment['id'];
                $fileId           = $this->_encryption->encode($filePath);
                $fileSize         = Settings::formatSize($fileAttachment['size'] / 1024);
                $originalFileName = $fileAttachment['name'];

                $arrResult[] = array(
                    'id'                         => $fileId,
                    'size'                       => $fileSize,
                    'libreoffice_supported'      => false,
                    'letter_template_attachment' => true,
                    'template_file_attachment'   => true,
                    'template_id'                => $templateId,
                    'link'                       => '#',
                    'original_file_name'         => $originalFileName
                );

            }


            } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrResult;
    }

    /**
     * Max count of repeatable groups that will be parsed in templates
     * @return int
     */
    public static function getRepeatableGroupsMaxCount()
    {
        return 5;
    }

    /**
     * Prefix that is used in field names for fields in repeatable groups
     * @return string
     */
    public static function getRepeatableGroupsPrefix()
    {
        return 'repeatable_record_%d_';
    }

    private function _parseTemplateForMember($memberId, $companyId, $booLetterTemplate)
    {
        $arrVariablesToReplace = array();

        $arrMemberInfo = $this->_clients->getMemberInfo($memberId);
        $arrClientInfo = $this->_clients->getClientsInfo(array($memberId), true, $arrMemberInfo['userType']);
        $arrClientInfo = !empty($arrClientInfo) ? $arrClientInfo[0] : [];

        switch (array($arrMemberInfo['userType'])) {
            case Members::getMemberType('employer'):
            case Members::getMemberType('individual'):
            case Members::getMemberType('contact'):
                $arrCompanyFields = $this->_clients->getApplicantFields()->getCompanyFields($companyId, $arrMemberInfo['userType'], $arrClientInfo['applicant_type_id']);

                list($arrSavedData,) = $this->_clients->getAllApplicantFieldsData($memberId, $arrMemberInfo['userType']);

                $arrClientSavedInfo = array();

                // Prepare list of general fields values
                foreach ($arrCompanyFields as $arrCompanyFieldInfo) {
                    $key      = 'field_' . $this->_clients->getMemberTypeNameById($arrMemberInfo['userType']) . '_' . $arrCompanyFieldInfo['group_id'] . '_' . $arrCompanyFieldInfo['applicant_field_id'];
                    $fieldVal = array_key_exists($key, $arrSavedData) ? $arrSavedData[$key][0] : '';

                    $arrClientSavedInfo[] = array(
                        'company_field_id'   => $arrCompanyFieldInfo['applicant_field_unique_id'],
                        'field_id'           => $arrCompanyFieldInfo['applicant_field_id'],
                        'type'               => $this->_clients->getFieldTypes()->getFieldTypeId($arrCompanyFieldInfo['type']),
                        'field_type_text_id' => $arrCompanyFieldInfo['type'],
                        'value'              => $fieldVal
                    );
                }


                // Generate list of fields in repeatable groups
                $maxRepeatableGroupsCount = self::getRepeatableGroupsMaxCount();
                $repeatableGroupsPrefix   = self::getRepeatableGroupsPrefix();
                foreach ($arrCompanyFields as $arrCompanyFieldInfo) {
                    if ($arrCompanyFieldInfo['repeatable'] != 'Y') {
                        continue;
                    }

                    for ($i = 0; $i < $maxRepeatableGroupsCount; $i++) {
                        $key      = 'field_' . $this->_clients->getMemberTypeNameById($arrMemberInfo['userType']) . '_' . $arrCompanyFieldInfo['group_id'] . '_' . $arrCompanyFieldInfo['applicant_field_id'];
                        $fieldVal = $arrSavedData[$key][$i] ?? '';

                        $arrClientSavedInfo[] = array(
                            'company_field_id'   => sprintf($repeatableGroupsPrefix, $i + 1) . $arrCompanyFieldInfo['applicant_field_unique_id'],
                            'field_id'           => $arrCompanyFieldInfo['applicant_field_id'],
                            'type'               => $this->_clients->getFieldTypes()->getFieldTypeId($arrCompanyFieldInfo['type']),
                            'field_type_text_id' => $arrCompanyFieldInfo['type'],
                            'value'              => $fieldVal
                        );
                    }
                }

                // Replace saved value with its 'readable' value
                $arrClientSavedInfo = $this->_clients->getFields()->completeFieldsData($companyId, $arrClientSavedInfo, true, true, true, true, true, false, true);
                break;

            case Members::getMemberType('case'):
            default:
                $arrVariablesToReplace['case_internal_id'] = $memberId;

                // Generate a list of dependants
                $arrDependents = !empty($this->_clients->getFields()->getAccessToDependants()) ? $this->_clients->getDependentsByMemberId($memberId) : array();
                $arrVariablesToReplace['dependants_list'] = $this->_clients->getFields()->getReadableDependantsRows($arrDependents);
                $arrVariablesToReplace['dependants_count_is_1_message'] = count($arrDependents) == 1 ? $this->_config['site_version']['dependants']['template_count_is_1_message'] : '';
                $arrVariablesToReplace['dependants_count_is_more_than_1_message'] = count($arrDependents) > 1 ? $this->_config['site_version']['dependants']['template_count_is_more_than_1_message'] : '';

                $arrParams          = array(
                    'select'               => [
                        'f' => ['field_id', 'label', 'type', 'company_field_id'],
                        'd' => ['value']
                    ],
                    'where'                => [(new Where())->equalTo('fg.client_type_id', $arrClientInfo['client_type_id'])],
                    'clientFormDataOption' => sprintf('d.member_id = %d', $memberId),
                    'booWithCountryNames'  => true,
                    'booFormatStaff'       => true,
                    'booFormatDates'       => true
                );
                $arrClientSavedInfo = $this->_clients->getFields()->getFields($arrParams);
                break;
        }

        $filter = new StripTags();
        foreach ($arrClientSavedInfo as $arrClientSavedFieldInfo) {
            // Replace values for static fields
            $fieldId = $arrClientSavedFieldInfo['company_field_id'];
            if ($this->_clients->getFields()->isStaticField($fieldId) && array_key_exists($this->_clients->getFields()->getStaticColumnName($fieldId), $arrClientInfo)) {
                $column = $this->_clients->getFields()->getStaticColumnName($fieldId);

                if ($column == 'client_type_id') {
                    if (!empty($arrClientInfo['client_type_id'])) {
                        $arrCaseTypeInfo = $this->_clients->getCaseTemplates()->getTemplateInfo($arrClientInfo['client_type_id']);

                        $arrClientSavedFieldInfo['value'] = $arrCaseTypeInfo['client_type_name'];
                    }
                } else {
                    $arrClientSavedFieldInfo['value'] = $arrClientInfo[$column];
                }
            }

            if ($booLetterTemplate && isset($arrClientSavedFieldInfo['type']) &&
                (
                    (is_numeric($arrClientSavedFieldInfo['type']) && $arrClientSavedFieldInfo['type'] == $this->_clients->getFieldTypes()->getFieldTypeId('html_editor')) ||
                    (!is_numeric($arrClientSavedFieldInfo['type']) && $arrClientSavedFieldInfo['type'] == 'html_editor')
                )
            ) {
                $arrClientSavedFieldInfo['value'] = $filter->filter($arrClientSavedFieldInfo['value']);
            }

            $arrVariablesToReplace[$fieldId] = $arrClientSavedFieldInfo['value'];
        }

        return array(
            'userType'     => $arrMemberInfo['userType'],
            'arrVariables' => $arrVariablesToReplace
        );
    }

    /**
     * For the HTML template: process the template and return a list of processed template's sections (message, subject, etc.)
     * For the letter template - process the docx file located at $letterTemplatePath and save it
     *
     * @param int $templateId
     * @param int $caseId
     * @param string $email
     * @param int $prospectId
     * @param int $companyId
     * @param string $letterTemplatePath
     * @param array $arrExtraFields
     * @param int $caseParentId
     * @return array
     */
    public function getMessage($templateId, $caseId, $email = '', $prospectId = false, $companyId = false, $letterTemplatePath = false, $arrExtraFields = array(), $caseParentId = 0)
    {
        $arrResult = array(
            'success' => false,
            'email'   => '',
            'from'    => '',
            'subject' => '',
            'message' => '',
            'cc'      => '',
            'bcc'     => ''
        );

        try {
            ini_set('pcre.recursion_limit', '512');

            // Get template info
            $template                 = $this->getTemplate($templateId, false);
            $template['emailAddress'] = '';

            $arrResult['from'] = $template['from'] ? $template['from'] : '';

            $emailValidator = new EmailAddress();
            $arrCaseInfo    = array();
            if ($caseId) {
                $arrCaseInfo = $this->_clients->getMemberInfo($caseId);
                if (isset($arrCaseInfo['emailAddress'])) {
                    $template['emailAddress'] = $arrCaseInfo['emailAddress'];
                }
            } else {
                $email = $emailValidator->isValid($email) ? trim($email) : $template['emailAddress'];
            }

            if ($prospectId) {
                $prospectsInfo = $this->_companyProspects->getProspectInfo($prospectId, null);
                if ($prospectsInfo) {
                    $email = $prospectsInfo['email'];
                }
            }
            $arrResult['email'] = $emailValidator->isValid($email) ? trim($email) : $template['emailAddress'];

            if (!$companyId) {
                if (!empty($caseId) && isset($arrCaseInfo['company_id'])) {
                    $companyId = $arrCaseInfo['company_id'];
                } else {
                    $companyId = $this->_auth->getCurrentUserCompanyId();
                }
            }

            // Check if we need to generate the comfort letter number
            // Note that if it will be generated - count will be automatically increased
            // No matter if it will be used or not
            if (!empty($companyId) && !empty($caseId)) {
                // Company and case ids were provided
                if (!empty($this->_config['site_version']['custom_templates_settings']['comfort_letter']['enabled']) && !empty($this->_config['site_version']['custom_templates_settings']['comfort_letter']['templates'])) {
                    // Comfort letter number generation is enabled + templates were set in the config
                    if (in_array($template['name'], $this->_config['site_version']['custom_templates_settings']['comfort_letter']['templates'])) {
                        // We try to parse the "allowed template"
                        list(, $arrExtraFields['comfort_letter_number']) = $this->getTemplatesSettings()->generateNewLetterNumber('comfort_letter', $companyId, $caseId);
                    }
                }
            }

            if ($letterTemplatePath) {
                // This is the letter template,
                // Load the list of variables from the docx (from header, document and footer)
                // And replace variables with the correct values
                $booLocal       = $this->_company->isCompanyStorageLocationLocal($companyId);
                $letterFilePath = $this->_files->getCompanyLetterTemplatesPath($companyId, $booLocal) . '/' . $templateId;

                if ($booLocal) {
                    if (!file_exists($letterFilePath) || !copy($letterFilePath, $letterTemplatePath)) {
                        throw new Exception('Letter file does not exist or was not copied from ' . $letterFilePath . ' to ' . $letterTemplatePath);
                    }
                } else {
                    if (!$this->_files->getCloud()->checkObjectExists($letterFilePath)) {
                        throw new Exception('Letter file does not exist: ' . $letterFilePath);
                    } else {
                        $letterTemplatePath2 = $this->_files->getCloud()->downloadFileContent($letterFilePath);
                        if (empty($letterTemplatePath2) || !copy($letterTemplatePath2, $letterTemplatePath)) {
                            throw new Exception('Letter file was not downloaded or copied from ' . $letterFilePath . ' to ' . $letterTemplatePath);
                        }
                    }
                }

                $docx = new CreateDocxFromTemplate($letterTemplatePath);
                // regular expression to process <% %> as placeholders
                $docx::$regExprVariableSymbols = '&lt;.*%.*%.*&gt;';
                // set escape characters to get template variables
                $docx->setTemplateSymbol('&lt;%', '%&gt;');
                // process the template
                $docx->processTemplate();

                // Load the list of all variables
                $arrAllTemplateVariables = $docx->getTemplateVariables();

                if (!empty($arrAllTemplateVariables)) {
                    // do not set escape characters to use replace methods
                    $docx->setTemplateSymbol('<%', '%>');

                    // Change the format from <%var%> to #var# format - requirement from the PHPDocx
                    $arrAllTemplateVariablesGrouped = array();
                    foreach ($arrAllTemplateVariables as $target => $arrTargetVariables) {
                        $arrVariablesToReplace = array();
                        foreach ($arrTargetVariables as $variable) {
                            $arrVariablesToReplace[$variable] = '#' . $variable . '#';

                            $arrAllTemplateVariablesGrouped[$variable] = $variable;
                        }

                        $options = array(
                            'parseLineBreaks' => true,
                            'target'          => $target
                        );
                        $docx->replaceVariableByText($arrVariablesToReplace, $options);
                    }

                    $arrVariablesWithValues = $this->_parseTemplateMessage(
                        $companyId,
                        $caseId,
                        $caseParentId,
                        $prospectId,
                        $arrAllTemplateVariablesGrouped,
                        true,
                        $arrExtraFields
                    );

                    $docx->setTemplateSymbol('#');

                    // Replace variables in all targets (header, document and footer) - where they were found
                    $arrReplaceWhere = array_keys($arrAllTemplateVariables);
                    foreach ($arrReplaceWhere as $target) {
                        $options = array(
                            'parseLineBreaks' => true,
                            'target'          => $target
                        );

                        $docx = $this->_documents->getPhpDocx()->processVariablesInDocx($docx, $target, $arrVariablesWithValues, $options);
                    }
                }

                $docx->createDocx($letterTemplatePath);

                $booSuccess = is_file($letterTemplatePath);
                if (!$booSuccess) {
                    throw new Exception('Letter file was not created by PHPDocx:' . $letterTemplatePath);
                }
            } else {
                // This is the html template, load variables list from the text and replace with their values
                $arrProcess = array(
                    'message' => $template['message'] ? $template['message'] : '',
                    'subject' => $template['subject'] ? htmlspecialchars($template['subject']) : '',
                    'cc'      => $template['cc'] ? htmlspecialchars($template['cc']) : '',
                    'bcc'     => $template['bcc'] ? htmlspecialchars($template['bcc']) : '',
                );

                foreach ($arrProcess as $what => $strContent) {
                    // Get the list of variables in the html
                    $arrHtmlVariables = preg_match_all('/&lt;%([\w]+)%&gt;/', $strContent, $regs) ? $regs[1] : array();

                    $arrVariablesWithValues = $this->_parseTemplateMessage(
                        $companyId,
                        $caseId,
                        $caseParentId,
                        $prospectId,
                        $arrHtmlVariables,
                        false,
                        $arrExtraFields
                    );

                    foreach ($arrVariablesWithValues as $variable => $value) {
                        // Only for the email signature don't try to replace line breaks to the <br>
                        if ($variable !== 'current_user_email_signature') {
                            $value = nl2br($value);
                        }

                        $strContent = str_replace('&lt;%' . $variable . '%&gt;', $value, $strContent);
                    }
                    $arrResult[$what] = stripslashes($strContent);

                    if ($what !== 'message') {
                        // We need this for subject, cc and bcc, but not for the message
                        $arrResult[$what] = htmlspecialchars_decode($arrResult[$what]);
                    }
                }

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $booSuccess = false;
        }

        $arrResult['success'] = $booSuccess;

        return $arrResult;
    }

    private function _parseTemplateMessage($companyId, $caseId, $caseParentId, $prospectId, $arrTemplateUsedVariables, $booLetterTemplate, $arrExtraFields = array())
    {
        // Don't try to load the info if there are no variables
        if (empty($arrTemplateUsedVariables)) {
            return array();
        }

        $arrAllVariablesToReplace = array();

        // Replace case's fields
        if (!empty($caseId)) {
            $arrRes                   = $this->_parseTemplateForMember($caseId, $companyId, $booLetterTemplate);
            $arrAllVariablesToReplace = array_merge($arrRes['arrVariables'], $arrAllVariablesToReplace);

            // Also replace parent client's fields data
            if (in_array($arrRes['userType'], Members::getMemberType('case'))) {
                $arrParents = $this->_clients->getParentsForAssignedApplicants(array($caseId), false, false);
                if (!empty($caseParentId)) {
                    foreach ($arrParents as $arrParentInfo) {
                        // If case's parent id was passed - make sure that this parent's info will be parsed first
                        if ($arrParentInfo['parent_member_id'] == $caseParentId) {
                            $arrRes                   = $this->_parseTemplateForMember($arrParentInfo['parent_member_id'], $companyId, $booLetterTemplate);
                            $arrAllVariablesToReplace = array_merge($arrRes['arrVariables'], $arrAllVariablesToReplace);
                        }
                    }

                    foreach ($arrParents as $arrParentInfo) {
                        // And parse all others
                        if ($arrParentInfo['parent_member_id'] != $caseParentId) {
                            $arrRes                   = $this->_parseTemplateForMember($arrParentInfo['parent_member_id'], $companyId, $booLetterTemplate);
                            $arrAllVariablesToReplace = array_merge($arrRes['arrVariables'], $arrAllVariablesToReplace);
                        }
                    }
                } else {
                    foreach ($arrParents as $arrParentInfo) {
                        $arrRes                   = $this->_parseTemplateForMember($arrParentInfo['parent_member_id'], $companyId, $booLetterTemplate);
                        $arrAllVariablesToReplace = array_merge($arrRes['arrVariables'], $arrAllVariablesToReplace);
                    }
                }

                $caseEmployerLinkId = $this->_clients->getCaseLinkedEmployerCaseId($caseId);
                if (!empty($caseEmployerLinkId)) {
                    $arrRes = $this->_parseTemplateForMember($caseEmployerLinkId, $companyId, $booLetterTemplate);

                    $arrAllVariablesToReplace = array_merge($arrRes['arrVariables'], $arrAllVariablesToReplace);
                    $arrParents               = $this->_clients->getParentsForAssignedApplicants(array($caseEmployerLinkId), false, false);

                    foreach ($arrParents as $arrParentInfo) {
                        $arrRes                   = $this->_parseTemplateForMember($arrParentInfo['parent_member_id'], $companyId, $booLetterTemplate);
                        $arrAllVariablesToReplace = array_merge($arrRes['arrVariables'], $arrAllVariablesToReplace);
                    }
                }

                $oCompanyDivisions = $this->_company->getCompanyDivisions();
                if ($oCompanyDivisions->isAuthorizedAgentsManagementEnabled()) {
                    // Replace Auth Agent fields
                    $arrMemberInfo = $this->_clients->getMemberInfo($caseId);
                    $arrClientInfo = $this->_clients->getClientsInfo(array($caseId), true, $arrMemberInfo['userType']);
                    $arrClientInfo = $arrClientInfo[0];

                    if (isset($arrClientInfo['division_group_id']) && !empty($arrClientInfo['division_group_id']) && isset($arrClientInfo['client_type_id'])) {
                        $arrDivisionGroupInfo = $oCompanyDivisions->getDivisionsGroupInfo($arrClientInfo['division_group_id']);

                        // Replace salutation to the readable value
                        if (isset($arrDivisionGroupInfo['division_group_salutation']) && !empty($arrDivisionGroupInfo['division_group_salutation'])) {
                            $arrSalutationIds = $oCompanyDivisions->getSalutations();
                            foreach ($arrSalutationIds as $arrSalutationInfo) {
                                if ($arrSalutationInfo['option_id'] === $arrDivisionGroupInfo['division_group_salutation']) {
                                    $arrDivisionGroupInfo['division_group_salutation'] = $arrSalutationInfo['option_name'];
                                    break;
                                }
                            }
                        }

                        $caseTemplateId       = $arrClientInfo['client_type_id'];
                        $arrTemplateFields    = $this->getFields('case', $caseTemplateId);
                        $arrAuthAgentFields   = array();
                        foreach ($arrTemplateFields as $key => $templateField) {
                            if (isset($templateField['authorized_agent_field_id'])) {
                                $arrAuthAgentFields[$key] = $templateField['name'];
                            }
                        }

                        if ($this->_areFieldsInTemplate($arrAuthAgentFields, $arrTemplateUsedVariables)) {
                            foreach ($arrAuthAgentFields as $key => $agentField) {
                                $fieldIdToReplace     = $arrTemplateFields[$key]['authorized_agent_field_id'];
                                $divisionGroupFieldId = str_replace($fieldIdToReplace, 'division_group', $agentField);

                                $value = $arrDivisionGroupInfo[$divisionGroupFieldId] ?? '';

                                $booConvertLineBreaks = !$booLetterTemplate && $divisionGroupFieldId != 'division_group_notes';

                                // Replace line breaks, so if we'll use the nl2br again - no extra breaks will be generated
                                $arrAllVariablesToReplace[$agentField] = $booConvertLineBreaks ? str_replace(array("\r\n", "\r", "\n"), "<br />", $value) : $value;
                            }
                        }
                    }
                }
            }
        }

        // Use another date format for date fields in the template
        $dateFormatFull     = $this->_settings->variable_get('dateFormatFull');
        $dateFormatExtended = $this->_settings->variable_get('dateFormatFullExtended', $dateFormatFull);

        // Replace Date & Time fields
        $arrGeneralFields = array(
            'time',
            'today_date',
            'today_date_day',
            'today_datetime'
        );
        if ($this->_areFieldsInTemplate($arrGeneralFields, $arrTemplateUsedVariables)) {
            $arrAllVariablesToReplace['time']           = date('H:i');
            $arrAllVariablesToReplace['today_date']     = $this->_settings->formatDate(date('Y-m-d'), true, $dateFormatExtended);
            $arrAllVariablesToReplace['today_date_day'] = date('l');
            $arrAllVariablesToReplace['today_datetime'] = $this->_settings->formatDate(date('Y-m-d'), true, $dateFormatExtended) . date(' H:i');
        }

        if (isset($arrExtraFields['decoded_password'])) {
            $arrAllVariablesToReplace['password'] = $arrExtraFields['decoded_password'];
        }

        // Replace current user info
        $arrUserFields = array(
            'current_user_fName',
            'current_user_lName',
            'current_user_email',
            'current_user_username',
            'current_user_email_signature',
        );
        if ($this->_areFieldsInTemplate($arrUserFields, $arrTemplateUsedVariables)) {
            $currentMemberId      = $this->_auth->getCurrentUserId();
            $arrCurrentMemberInfo = $this->_clients->getMemberInfo($currentMemberId, true);
            if ($arrCurrentMemberInfo) {
                $arrAllVariablesToReplace['current_user_fName']    = $arrCurrentMemberInfo['fName'];
                $arrAllVariablesToReplace['current_user_lName']    = $arrCurrentMemberInfo['lName'];
                $arrAllVariablesToReplace['current_user_email']    = $arrCurrentMemberInfo['emailAddress'];
                $arrAllVariablesToReplace['current_user_username'] = $arrCurrentMemberInfo['username'];

                $arrDefAccount                                            = MailAccount::getDefaultAccount($currentMemberId);
                $arrAllVariablesToReplace['current_user_email_signature'] = $arrDefAccount['signature'] ?? '';
            }
        }

        // Replace company fields
        $arrCompanyFields = array(
            'company_country',
            'company_name',
            'company_abn',
            'company_address',
            'company_city',
            'company_state',
            'company_zip',
            'company_phone_1',
            'company_phone_2',
            'company_email',
            'company_fax',
            'company_logo',
        );
        if ($this->_areFieldsInTemplate($arrCompanyFields, $arrTemplateUsedVariables)) {
            $arrCompanyInfo = $this->_company->getCompanyInfo($companyId);
            if ($arrCompanyInfo) {
                $arrAllVariablesToReplace['company_country'] = $this->_country->getCountryName($arrCompanyInfo['country']);
                $arrAllVariablesToReplace['company_name']    = $arrCompanyInfo['companyName'];
                $arrAllVariablesToReplace['company_abn']     = $arrCompanyInfo['company_abn'];
                $arrAllVariablesToReplace['company_address'] = $arrCompanyInfo['address'];
                $arrAllVariablesToReplace['company_city']    = $arrCompanyInfo['city'];
                $arrAllVariablesToReplace['company_state']   = $arrCompanyInfo['state'];
                $arrAllVariablesToReplace['company_zip']     = $arrCompanyInfo['zip'];
                $arrAllVariablesToReplace['company_phone_1'] = $arrCompanyInfo['phone1'];
                $arrAllVariablesToReplace['company_phone_2'] = $arrCompanyInfo['phone2'];
                $arrAllVariablesToReplace['company_email']   = $arrCompanyInfo['companyEmail'];
                $arrAllVariablesToReplace['company_fax']     = $arrCompanyInfo['fax'];

                // Now we support company logo
                $arrAllVariablesToReplace['company_logo'] = '-';
                if (!empty($arrCompanyInfo['companyLogo'])) {
                    $booLocal = $this->_company->isCompanyStorageLocationLocal($companyId);
                    $path     = $this->_files->getCompanyLogoPath($companyId, $booLocal);

                    if (!$booLocal) {
                        if ($this->_files->getCloud()->checkObjectExists($path)) {
                            $path = $this->_files->getCloud()->downloadFileContent($path);
                        } else {
                            $path = '';
                        }
                    }

                    if (!empty($path) && file_exists($path)) {
                        if ($booLetterTemplate) {
                            $arrAllVariablesToReplace['company_logo'] = new PhpDocxImage(['properties' => ['src' => $path, 'scaling' => 100]]);
                        } else {
                            $arrAllVariablesToReplace['company_logo'] = sprintf('<img src="%s" alt="Company Logo" />', $this->_company->getCompanyLogoLink($arrCompanyInfo));
                        }
                    }
                }
            }
        }

        // Replace accounting fields
        $defaultCurrency     = $this->_settings->getSiteDefaultCurrency();
        $currencyLabelNoSign = strtolower(Clients\Accounting::getCurrencyLabel($defaultCurrency, false));

        $arrTAFields = array(
            'outstanding_balance_' . $currencyLabelNoSign,
            'outstanding_balance_non_' . $currencyLabelNoSign,
            'trust_ac_balance_' . $currencyLabelNoSign,
            'trust_ac_balance_non_' . $currencyLabelNoSign,
        );
        if ($this->_areFieldsInTemplate($arrTAFields, $arrTemplateUsedVariables)) {
            $arrMemberTAInfo = empty($caseId) ? array() : $this->_clients->getAccounting()->getMemberTA($caseId);
            if ($arrMemberTAInfo) {
                $mainCurrencyOutstandingBalance = $secondaryCurrencyOutstandingBalance = $mainCurrencyTABalance = $secondaryCurrencyTABalance = 0;
                $mainCurrencySign               = $secondaryCurrencySign = '';

                foreach ($arrMemberTAInfo as $ta) {
                    if ($ta['currency'] == $defaultCurrency) {
                        $mainCurrencySign               = $this->_clients->getAccounting()::getCurrencySign($ta['currency']);
                        $mainCurrencyOutstandingBalance = $this->_clients->getAccounting()->calculateOutstandingBalance($caseId, $ta['company_ta_id']);
                        $mainCurrencyTABalance          = $this->_clients->getAccounting()->getTrustAccountSubTotal($caseId, $ta['company_ta_id'], false, false, $companyId);
                    } else {
                        $secondaryCurrencySign               = $this->_clients->getAccounting()::getCurrencySign($ta['currency']);
                        $secondaryCurrencyOutstandingBalance = $this->_clients->getAccounting()->calculateOutstandingBalance($caseId, $ta['company_ta_id']);
                        $secondaryCurrencyTABalance          = $this->_clients->getAccounting()->getTrustAccountSubTotal($caseId, $ta['company_ta_id'], false, false, $companyId);
                    }
                }

                $arrAllVariablesToReplace['outstanding_balance_' . $currencyLabelNoSign]     = $mainCurrencySign . $mainCurrencyOutstandingBalance;
                $arrAllVariablesToReplace['outstanding_balance_non_' . $currencyLabelNoSign] = $secondaryCurrencySign . $secondaryCurrencyOutstandingBalance;
                $arrAllVariablesToReplace['trust_ac_balance_' . $currencyLabelNoSign]        = $mainCurrencySign . $mainCurrencyTABalance;
                $arrAllVariablesToReplace['trust_ac_balance_non_' . $currencyLabelNoSign]    = $secondaryCurrencySign . $secondaryCurrencyTABalance;
            }
        }

        // Replace accounting: Fees Due fields
        $arrFTFields = array(
            'financial_transaction_date',
            'financial_transaction_description',
            'financial_transaction_amount'
        );
        if ($this->_areFieldsInTemplate($arrFTFields, $arrTemplateUsedVariables)) {
            $arrLastFTRecord = $this->_clients->getAccounting()->getLastMemberPayment($caseId);
            if ($arrLastFTRecord) {
                $arrAllVariablesToReplace['financial_transaction_date']        = $this->_settings->formatDate($arrLastFTRecord['date_of_event'], true, $dateFormatExtended);
                $arrAllVariablesToReplace['financial_transaction_description'] = $arrLastFTRecord['description'];
                $arrAllVariablesToReplace['financial_transaction_amount']      = empty($arrLastFTRecord['deposit']) ? $arrLastFTRecord['withdrawal'] : $arrLastFTRecord['deposit'];
            }
        }

        // Replace invoice fields
        $arrInvoiceFields = array(
            'invoice_number',
            'invoice_date',
            'invoice_transfer',
            'invoice_cheque_number',
            'invoice_amount_' . $currencyLabelNoSign,
            'invoice_amount_non_' . $currencyLabelNoSign,
            'invoice_net_' . $currencyLabelNoSign,
            'invoice_net_non_' . $currencyLabelNoSign,
            'invoice_tax_' . $currencyLabelNoSign,
            'invoice_tax_non_' . $currencyLabelNoSign,
            'invoice_total_' . $currencyLabelNoSign,
            'invoice_total_non_' . $currencyLabelNoSign,
        );

        if ($this->_areFieldsInTemplate($arrInvoiceFields, $arrTemplateUsedVariables)) {
            $invoiceId      = $arrExtraFields['invoice_id'] ?? null;
            $arrLastInvoice = $this->_clients->getAccounting()->getLastMemberInvoice($caseId, $invoiceId);
            if ($arrLastInvoice) {
                $amount = empty($arrLastInvoice['transfer_from_amount']) ? $arrLastInvoice['amount'] : $arrLastInvoice['transfer_from_amount'];
                $net    = $arrLastInvoice['fee'] ?? '-';
                $tax    = $arrLastInvoice['tax'] ?? '-';
                $total  = $arrLastInvoice['total'] ?? '-';

                $arrAllVariablesToReplace['invoice_number']                             = $arrLastInvoice['invoice_num'];
                $arrAllVariablesToReplace['invoice_date']                               = $this->_settings->formatDate($arrLastInvoice['date_of_invoice'], true, $dateFormatExtended);
                $arrAllVariablesToReplace['invoice_transfer']                           = $arrLastInvoice['name'] . ' - ' . Clients\Accounting::getCurrencyLabel($arrLastInvoice['currency']);
                $arrAllVariablesToReplace['invoice_cheque_number']                      = $arrLastInvoice['cheque_num'] ?? '-';
                $arrAllVariablesToReplace['invoice_amount_' . $currencyLabelNoSign]     = $arrLastInvoice['currency'] == $defaultCurrency ? $amount : '-';
                $arrAllVariablesToReplace['invoice_amount_non_' . $currencyLabelNoSign] = $arrLastInvoice['currency'] == $defaultCurrency ? '-' : $amount;
                $arrAllVariablesToReplace['invoice_net_' . $currencyLabelNoSign]        = $arrLastInvoice['currency'] == $defaultCurrency ? $net : '-';
                $arrAllVariablesToReplace['invoice_net_non_' . $currencyLabelNoSign]    = $arrLastInvoice['currency'] == $defaultCurrency ? '-' : $net;
                $arrAllVariablesToReplace['invoice_tax_' . $currencyLabelNoSign]        = $arrLastInvoice['currency'] == $defaultCurrency ? $tax : '-';
                $arrAllVariablesToReplace['invoice_tax_non_' . $currencyLabelNoSign]    = $arrLastInvoice['currency'] == $defaultCurrency ? '-' : $tax;
                $arrAllVariablesToReplace['invoice_total_' . $currencyLabelNoSign]      = $arrLastInvoice['currency'] == $defaultCurrency ? $total : '-';
                $arrAllVariablesToReplace['invoice_total_non_' . $currencyLabelNoSign]  = $arrLastInvoice['currency'] == $defaultCurrency ? '-' : $total;
            }
        }

        // Table params used in the letter template (PHPDocx) during tables creation
        $paramsTable = array(
            'border'         => 'single',
            'tableAlign'     => 'center',
            'borderWidth'    => 4,
            'borderColor'    => 'cccccc',
            'tableWidth'     => array('type' => 'pct', 'value' => 100),
            'textProperties' => array('font' => 'Arial', 'fontSize' => 11)
        );

        // Replace tables: payment schedule
        if ($this->_areFieldsInTemplate(array('payment_schedule_table'), $arrTemplateUsedVariables)) {
            $total              = 0;
            $totalDue           = 0;
            $currency           = '';
            $currencyLabel      = '';
            $arrPaymentSchedule = array();

            if (!empty($caseId)) {
                $companyTaId = $this->_clients->getAccounting()->getClientPrimaryCompanyTaId($caseId);
                if (!empty($companyTaId)) {
                    $currency      = $this->_clients->getAccounting()->getCompanyTACurrency($companyTaId);
                    $currencyLabel = ' (' . $this->_clients->getAccounting()::getCurrencyLabel($currency) . ')';

                    $arrFeesResult      = $this->_clients->getAccounting()->getClientAccountingFeesList($caseId, $companyTaId);
                    $arrPaymentSchedule = $arrFeesResult['rows'];
                    $total              = $arrFeesResult['total'] + $arrFeesResult['total_gst'];
                    $totalDue           = $arrFeesResult['total_due'];
                }
            }

            $list                            = '';
            $paymentScheduleTableValuesArray = array();
            if (count($arrPaymentSchedule)) {
                if (!$booLetterTemplate) {
                    $list = '<tr>' .
                        '<th style="border:#ccc solid 1px; padding:4px; font:bold 9px Arial;"><u>Fees &amp; Disbursements' . $currencyLabel . '</u></th>' .
                        '<th style="border:#ccc solid 1px; padding:2px; font:bold 9px Arial;" width="60"><u>Due on</u></th>' .
                        '<th style="border:#ccc solid 1px; padding:2px; font:bold 9px Arial;" width="60"><u>Amount</u></th>' .
                        '</tr>';
                } else {
                    $paymentScheduleTableValuesArray[] = array(
                        array(
                            'value' => 'Fees & Disbursements' . $currencyLabel
                        ),
                        array(
                            'value' => 'Due on'
                        ),
                        array(
                            'value' => 'Amount'
                        )
                    );
                }

                foreach ($arrPaymentSchedule as $ps) {
                    $description = $ps['fee_description'];
                    $amount      = $this->_clients->getAccounting()::formatPrice($ps['fee_amount'], $currency, false);
                    if (!empty($ps['fee_gst'])) {
                        $description .= '<br>' . $ps['fee_description_gst'];
                        $amount      .= '<br>' . $this->_clients->getAccounting()::formatPrice($ps['fee_gst'], $currency, false);
                    }

                    if (!$booLetterTemplate) {
                        $list .= '<tr>' .
                            '<td align="left" style="border:#ccc solid 1px; padding:2px; font:9px Arial;">' . $description . '</td>' .
                            '<td align="center" style="border:#ccc solid 1px; padding:2px; font:9px Arial;">' . $ps['fee_due_date'] . '</td>' .
                            '<td align="right" style="border:#ccc solid 1px; padding:2px; font:9px Arial;">' . $amount . '</td>' .
                            '</tr>';
                    } else {
                        $paymentScheduleTableValuesArray[] = array(
                            array(
                                'value' => $description
                            ),
                            array(
                                'value' => $ps['fee_due_date']
                            ),
                            array(
                                'value' => $amount
                            )
                        );
                    }
                }

                if (!$booLetterTemplate) {
                    $list .= '<tr>' .
                        '<td align="left" colspan="2" style="border:#ccc solid 1px; padding:2px; font: bold 9px Arial;">Total Due:</td>' .
                        '<td align="right" style="border:#ccc solid 1px; padding:2px; font: bold 9px Arial;">' . $this->_clients->getAccounting()::formatPrice($totalDue, $currency, false) . '</td>' .
                        '</tr>';

                    $list .= '<tr>' .
                        '<td align="left" colspan="2" style="border:#ccc solid 1px; padding:2px; font: bold 9px Arial;">Total:</td>' .
                        '<td align="right" style="border:#ccc solid 1px; padding:2px; font: bold 9px Arial;">' . $this->_clients->getAccounting()::formatPrice($total, $currency, false) . '</td>' .
                        '</tr>';
                } else {
                    $paymentScheduleTableValuesArray[] = array(
                        array(
                            'colspan' => 2,
                            'value'   => 'Total Due:',
                        ),
                        array(
                            'value' => $this->_clients->getAccounting()::formatPrice($totalDue, $currency, false),
                        )
                    );

                    $paymentScheduleTableValuesArray[] = array(
                        array(
                            'colspan' => 2,
                            'value'   => 'Total:',
                        ),
                        array(
                            'value' => $this->_clients->getAccounting()::formatPrice($total, $currency, false),
                        )
                    );
                }
            }

            if (!$booLetterTemplate) {
                $list = empty($list) ? '-' : '<table style="border:#ccc solid 1px; font-size:12px; border-collapse:collapse; padding:2px;" cellspacing="0" cellpadding="0" width="500">' . $list . '</table>';
            } else {
                $list = empty($paymentScheduleTableValuesArray) ? '-' : new PhpDocxTable(['values' => $paymentScheduleTableValuesArray, 'properties' => $paramsTable]);
            }
            $arrAllVariablesToReplace['payment_schedule_table'] = $list;
        }

        // Load and replace Accounting info only when needed
        $arrPayments = $arrExtraFields['payments'] ?? array();

        $booReplaceFinTable = $this->_areFieldsInTemplate(
            array(
                $currencyLabelNoSign . '_curr_fin_transaction_table',
                'non_' . $currencyLabelNoSign . '_curr_fin_transaction_table'
            ),
            $arrTemplateUsedVariables
        );

        $booReplaceFinTableSelectedRecordsInvoice = !empty($arrPayments) &&
            $this->_areFieldsInTemplate(
                array(
                    $currencyLabelNoSign . '_curr_fin_transaction_table_selected_records',
                    $currencyLabelNoSign . '_curr_fin_transaction_table_selected_records_invoice',
                    'non_' . $currencyLabelNoSign . '_curr_fin_transaction_table_selected_records_invoice'
                ),
                $arrTemplateUsedVariables
            );

        $booReplaceFinTableSelectedRecordsReceipt = !empty($arrPayments) &&
            $this->_areFieldsInTemplate(
                array(
                    $currencyLabelNoSign . '_curr_fin_transaction_table_selected_records_receipt',
                    'non_' . $currencyLabelNoSign . '_curr_fin_transaction_table_selected_records_receipt',
                ),
                $arrTemplateUsedVariables
            );


        $booReplaceTATable = $this->_areFieldsInTemplate(
            array(
                $currencyLabelNoSign . '_curr_trustac_summary_table',
                'non_' . $currencyLabelNoSign . '_curr_trustac_summary_table'
            ),
            $arrTemplateUsedVariables
        );

        $arrCompanyTA = false;
        if ($booReplaceFinTable || $booReplaceTATable || $booReplaceFinTableSelectedRecordsInvoice || $booReplaceFinTableSelectedRecordsReceipt) {
            $arrCompanyTA = $this->_clients->getAccounting()->getCompanyTA($companyId);
        }

        // Replace table: Fees Due
        if (!empty($caseId) && $arrCompanyTA && $booReplaceFinTable) {
            $canList = $nonCanList = [];
            foreach ($arrCompanyTA as $ta) {
                $list                    = '';
                $arrFinancialTableValues = array();

                $ft_arr = $this->_clients->getAccounting()->getClientsTransactionsInfo($caseId, $ta['company_ta_id'], false, false, $booLetterTemplate);
                if (!empty($ft_arr)) {
                    if (!$booLetterTemplate) {
                        $list = '<tr><th style="border:#ccc solid 1px; padding:2px; font:bold 9px Arial;" width="60"><u>Date</u></th>' .
                            '<th style="border:#ccc solid 1px; padding:2px; font:bold 9px Arial;"><u>Description</u></th>' .
                            '<th style="border:#ccc solid 1px; padding:2px; font:bold 9px Arial;" width="60"><u>Amount&nbsp;Received</u></th>' .
                            '<th style="border:#ccc solid 1px; padding:2px; font:bold 9px Arial;" width="60"><u>Amount&nbsp;Due</u></th></tr>';
                    } else {
                        $arrFinancialTableValues[0] = array(
                            array(
                                'value' => 'Date'
                            ),
                            array(
                                'value' => 'Description'
                            ),
                            array(
                                'value' => 'Amount Received'
                            ),
                            array(
                                'value' => 'Amount Due'
                            )
                        );
                    }

                    foreach ($ft_arr as $ft) {
                        $equal = '';
                        if (!empty($ft['transfer_from_amount']) && !$booLetterTemplate) {
                            $equal = ' (' . Clients\Accounting::getCurrencyLabel($ta['currency']) . '&nbsp;' . $ft['transfer_from_amount'] . ')';
                        }

                        $feeReceived = $ft['fees_received'];
                        $feeDue      = $ft['fees_due'];

                        // Show GST/HST if used
                        if (!empty($ft['received_gst'])) {
                            if (!$booLetterTemplate) {
                                $feeReceived .= sprintf("<br/><span style='color:#666666;'>%s</span>", $ft['received_gst']);
                            } else {
                                $feeReceived = $feeReceived . ' ' . $ft['received_gst'];
                            }
                        }

                        if (!empty($ft['due_gst'])) {
                            if (!$booLetterTemplate) {
                                $feeDue .= sprintf("<br/><span style='color:#666666;'>%s</span>", $ft['due_gst']);
                            } else {
                                $feeDue = $feeDue . '\n' . $ft['due_gst'];
                            }
                        }

                        if (!$booLetterTemplate) {
                            $record = '<tr><td style="border:#ccc solid 1px; padding:2px; font:9px Arial;">' . $ft['date'] . '</td>' .
                                '<td style="border:#ccc solid 1px; padding:2px; font:9px Arial;">' . $ft['description'] . $equal . '</td>' .
                                '<td align="right" style="border:#ccc solid 1px; padding:2px; font:9px Arial;">' . $feeReceived . '</td>' .
                                '<td align="right" style="border:#ccc solid 1px; padding:2px; font:9px Arial;">' . $feeDue . '</td></tr>';

                            $list .= $record;
                        } else {
                            $arrFinancialTableValues[] = array(
                                $ft['date'],
                                $ft['description'] . $equal,
                                $feeReceived,
                                $feeDue
                            );
                        }
                    }

                    $total = $this->_clients->getAccounting()->calculateOutstandingBalance($caseId, $ta['company_ta_id'], true);

                    if (!$booLetterTemplate) {
                        $list .= '<tr><td>&nbsp;</td><td style="border:#ccc solid 1px; padding:2px; font:9px Arial;"><b>Balance Due:</td><td>&nbsp;</td>' .
                            '<td align="right" style="border:#ccc solid 1px; padding:2px; font:9px Arial;"><b>' . Clients\Accounting::getCurrencyLabel($ta['currency']) . '&nbsp;' . $total . '</b></td></tr>';
                    } else {
                        $arrFinancialTableValues[] = array(
                            array(
                                'colspan' => 3,
                                'value'   => 'Balance Due:',
                            ),
                            array(
                                'value' => Clients\Accounting::getCurrencyLabel($ta['currency']) . ' ' . $total,
                            )
                        );
                    }
                }

                if ($ta['currency'] == $defaultCurrency) {
                    $canList[] = !$booLetterTemplate ? $list : (empty($arrFinancialTableValues) ? '-' : new PhpDocxTable(['values' => $arrFinancialTableValues, 'properties' => $paramsTable]));
                } else {
                    $nonCanList[] = !$booLetterTemplate ? $list : (empty($arrFinancialTableValues) ? '-' : new PhpDocxTable(['values' => $arrFinancialTableValues, 'properties' => $paramsTable]));
                }
            }

            if (!$booLetterTemplate) {
                $canList    = empty($canList)
                    ? '-'
                    : '<table style="border:#ccc solid 1px; font-size:12px; border-collapse:collapse; padding:2px;" width="500" cellspacing="0" cellpadding="0">' . implode('', $canList) . '</table>';
                $nonCanList = empty($nonCanList)
                    ? '-'
                    : '<table style="border:#ccc solid 1px; font-size:12px; border-collapse:collapse; padding:2px;" width="500" cellspacing="0" cellpadding="0">' . implode('', $nonCanList) . '</table>';
            } else {
                $canList    = empty($canList) ? '-' : $canList;
                $nonCanList = empty($nonCanList) ? '-' : $nonCanList;
            }

            $arrAllVariablesToReplace[$currencyLabelNoSign . '_curr_fin_transaction_table']          = $canList;
            $arrAllVariablesToReplace['non_' . $currencyLabelNoSign . '_curr_fin_transaction_table'] = $nonCanList;
        }

        if (!empty($caseId) && $arrCompanyTA && $booReplaceFinTableSelectedRecordsInvoice) {
            $arrAllVariablesToReplace = array_merge(
                $this->replaceFinancialTableSelectedRecords($arrCompanyTA, $caseId, $booLetterTemplate, true, $arrPayments, $defaultCurrency, $currencyLabelNoSign, $paramsTable),
                $arrAllVariablesToReplace
            );
        }

        if (!empty($caseId) && $arrCompanyTA && $booReplaceFinTableSelectedRecordsReceipt) {
            $arrAllVariablesToReplace = array_merge(
                $this->replaceFinancialTableSelectedRecords($arrCompanyTA, $caseId, $booLetterTemplate, false, $arrPayments, $defaultCurrency, $currencyLabelNoSign, $paramsTable),
                $arrAllVariablesToReplace
            );
        }

        // Replace table: Client A/C Summary
        if (!empty($caseId) && $arrCompanyTA && $booReplaceTATable) {
            $canList = $nonCanList = [];
            foreach ($arrCompanyTA as $ta) {
                $list                        = '';
                $arrClientSummaryTableValues = array();
                $ft_arr                      = $this->_clients->getAccounting()->getClientsTrustAccountInfo($caseId, $ta['company_ta_id']);
                if (!empty($ft_arr)) {
                    if (!$booLetterTemplate) {
                        $list = '<tr><th style="border:#ccc solid 1px; padding:2px; font:bold 9px Arial;"><u>Date</u></th>' .
                            '<th style="border:#ccc solid 1px; padding:2px; font:bold 9px Arial;"><u>Description</u></th>' .
                            '<th style="border:#ccc solid 1px; padding:2px; font:bold 9px Arial;"><u>Deposit</u></th>' .
                            '<th style="border:#ccc solid 1px; padding:2px; font:bold 9px Arial;"><u>Withdrawal</u></th></tr>';
                    } else {
                        $arrClientSummaryTableValues[0] = array(
                            array(
                                'value' => 'Date'
                            ),
                            array(
                                'value' => 'Description'
                            ),
                            array(
                                'value' => 'Deposit'
                            ),
                            array(
                                'value' => 'Withdrawal'
                            )
                        );
                    }
                    foreach ($ft_arr as $ft) {
                        if (!$booLetterTemplate) {
                            $list .= '<tr><td align="center" style="border:#ccc solid 1px; padding:2px; font:9px Arial;" width="60">' . $ft['date'] . '</td>' .
                                '<td style="border:#ccc solid 1px; padding:2px; font:9px Arial;">' . $ft['description'] . '</td>' .
                                '<td align="right" style="border:#ccc solid 1px; padding:2px; font:9px Arial;" width="60">' . $this->_clients->getAccounting()::formatPrice($ft['deposit'], '', false) . '</td>' .
                                '<td align="right" style="border:#ccc solid 1px; padding:2px; font:9px Arial;" width="60">' . $this->_clients->getAccounting()::formatPrice($ft['withdrawal'], '', false) . '</td></tr>';
                        } else {
                            $arrClientSummaryTableValues[] = array(
                                $ft['date'],
                                $ft['description'],
                                $this->_clients->getAccounting()::formatPrice($ft['deposit'], '', false),
                                $this->_clients->getAccounting()::formatPrice($ft['withdrawal'], '', false)
                            );
                        }
                    }

                    $total = $this->_clients->getAccounting()::formatPrice($this->_clients->getAccounting()->getTrustAccountSubTotal($caseId, $ta['company_ta_id'], false, false, $companyId));

                    if ($booLetterTemplate) {
                        $arrClientSummaryTableValues[] = array(
                            array(
                                'colspan' => 3,
                                'value'   => 'Balance in ' . $this->_company->getCurrentCompanyDefaultLabel('trust_account') . ':',
                            ),
                            array(
                                'colspan' => 2,
                                'value'   => Clients\Accounting::getCurrencyLabel($ta['currency']) . ' ' . $total,
                            )
                        );
                    }

                    if ($ta['currency'] == $defaultCurrency) {
                        $canList[] = !$booLetterTemplate ? $list : (empty($arrClientSummaryTableValues) ? '-' : new PhpDocxTable(['values' => $arrClientSummaryTableValues, 'properties' => $paramsTable]));
                    } else {
                        $nonCanList[] = !$booLetterTemplate ? $list : (empty($arrClientSummaryTableValues) ? '-' : new PhpDocxTable(['values' => $arrClientSummaryTableValues, 'properties' => $paramsTable]));
                    }
                }
            }

            if (!$booLetterTemplate) {
                $canList    = empty($canList) ? '-' : '<table style="border:#ccc solid 1px; font-size:12px; border-collapse:collapse; padding:2px;" width="500" cellspacing="0" cellpadding="0">' . implode('', $canList) . '</table>';
                $nonCanList = empty($nonCanList) ? '-' : '<table style="border:#ccc solid 1px; font-size:12px; border-collapse:collapse; padding:2px;" width="500" cellspacing="0" cellpadding="0">' . implode('', $nonCanList) . '</table>';
            } else {
                $canList    = empty($canList) ? '-' : $canList;
                $nonCanList = empty($nonCanList) ? '-' : $nonCanList;
            }

            $arrAllVariablesToReplace[$currencyLabelNoSign . '_curr_trustac_summary_table']          = $canList;
            $arrAllVariablesToReplace['non_' . $currencyLabelNoSign . '_curr_trustac_summary_table'] = $nonCanList;
        }

        // Replace assigned deposit fields
        if ($this->_areFieldsInTemplate(array('assign_deposit_date_from_bank', 'assign_deposit_description', 'assign_deposit_amount'), $arrTemplateUsedVariables)) {
            $subSelect = (new Select())
                ->from('u_assigned_deposits')
                ->columns([new Expression('MAX(date_of_event)')])
                ->where([
                    'member_id' => $caseId
                ]);

            $select = (new Select())
                ->from(array('ad' => 'u_assigned_deposits'))
                ->columns(['deposit'])
                ->join(['ta' => 'u_trust_account'], 'ta.trust_account_id = ad.trust_account_id', ['company_ta_id', 'date_from_bank', 'description'], Select::JOIN_LEFT)
                ->where([
                    'ad.member_id'     => $caseId,
                    'ad.date_of_event' => $subSelect
                ]);

            $deposits = $this->_db2->fetchAll($select);
            if ($deposits) {
                $amount = 0;
                foreach ($deposits as $deposit) {
                    $amount += $deposit['deposit'];
                }

                $arrAllVariablesToReplace['assign_deposit_date_from_bank'] = $this->_settings->formatDate($deposits[0]['date_from_bank'], true, $dateFormatExtended);
                $arrAllVariablesToReplace['assign_deposit_description']    = $deposits[0]['description'];
                $arrAllVariablesToReplace['assign_deposit_amount']         = $this->_clients->getAccounting()::formatPrice($amount, $this->_clients->getAccounting()->getCurrency($deposits[0]['company_ta_id']));
            } else {
                $arrAllVariablesToReplace['assign_deposit_date_from_bank'] = '-';
                $arrAllVariablesToReplace['assign_deposit_description']    = '-';
                $arrAllVariablesToReplace['assign_deposit_amount']         = '-';
            }
        }

        // Replace prospects
        $arrProspectFields = array(
            'prospect_first_name',
            'prospect_last_name',
            'prospect_email',
        );
        if ($prospectId && $this->_areFieldsInTemplate($arrProspectFields, $arrTemplateUsedVariables)) {
            $prospectsInfo = $this->_companyProspects->getProspectInfo($prospectId, null);
            if ($prospectsInfo) {
                $arrAllVariablesToReplace['prospect_first_name'] = $prospectsInfo['fName'];
                $arrAllVariablesToReplace['prospect_last_name']  = $prospectsInfo['lName'];
                $arrAllVariablesToReplace['prospect_email']      = $prospectsInfo['email'];
            }
        }


        // "Other" section
        $arrAllVariablesToReplace['comfort_letter_number'] = $arrExtraFields['comfort_letter_number'] ?? '';


        // Return only needed/used variables
        $arrResult = array();
        foreach ($arrTemplateUsedVariables as $variableToCheck) {
            $arrResult[$variableToCheck] = $arrAllVariablesToReplace[$variableToCheck] ?? "< no access to - $variableToCheck >";
        }

        return $arrResult;
    }


    private function replaceFinancialTableSelectedRecords($company_ta, $caseId, $booLetterTemplate, $booReplaceFinTableSelectedRecordsInvoice, $arrPayments, $defaultCurrency, $currencyLabelNoSign, $paramsTable)
    {
        $arrVariablesToReplace = array();

        $canList = $nonCanList = [];
        foreach ($company_ta as $ta) {
            $list                    = '';
            $arrFinancialTableValues = array();

            $ft_arr = $this->_clients->getAccounting()->getClientsTransactionsInfo($caseId, $ta['company_ta_id'], false, false, $booLetterTemplate);
            if (!empty($ft_arr)) {
                if (!$booLetterTemplate) {
                    $list = '<tr><th style="border:#ccc solid 1px; padding:2px; font:bold 9px Arial;" width="60"><u>Date</u></th>' .
                        '<th style="border:#ccc solid 1px; padding:2px; font:bold 9px Arial;"><u>Description</u></th>' .
                        ($booReplaceFinTableSelectedRecordsInvoice ? '<th style="border:#ccc solid 1px; padding:2px; font:bold 9px Arial;" width="60"><u>Amount&nbsp;Due</u></th></tr>' : '<th style="border:#ccc solid 1px; padding:2px; font:bold 9px Arial;" width="60"><u>Amount&nbsp;Received</u></th>');
                } else {
                    $arrFinancialTableValues[0] = array(
                        array(
                            'value' => 'Date'
                        ),
                        array(
                            'value' => 'Description'
                        ),
                        array(
                            'value' => $booReplaceFinTableSelectedRecordsInvoice ? 'Amount Due' : 'Amount Received'
                        )
                    );
                }

                $totalSelectedRecords = 0;

                foreach ($ft_arr as $ft) {
                    $booCurrentPaymentSelected = false;

                    $arrIdParts = explode('-', $ft['id'] ?? '');
                    if ($ft['type'] == 'payment' && in_array($arrIdParts[1], $arrPayments)) {
                        $booCurrentPaymentSelected = true;
                    }

                    $equal = '';
                    if (!empty($ft['transfer_from_amount']) && !$booLetterTemplate) {
                        $equal = ' (' . Clients\Accounting::getCurrencyLabel($ta['currency']) . '&nbsp;' . $ft['transfer_from_amount'] . ')';
                    }

                    $feeReceived = $ft['fees_received'];
                    $feeDue      = $ft['fees_due'];

                    // Show GST/HST if used
                    if (!empty($ft['received_gst'])) {
                        if (!$booLetterTemplate) {
                            $feeReceived .= sprintf("<br/><span style='color:#666666;'>%s</span>", $ft['received_gst']);
                        } else {
                            $feeReceived = $feeReceived . ' ' . $ft['received_gst'];
                        }
                    }

                    if (!empty($ft['due_gst'])) {
                        if (!$booLetterTemplate) {
                            $feeDue .= sprintf("<br/><span style='color:#666666;'>%s</span>", $ft['due_gst']);
                        } else {
                            $feeDue = $feeDue . '\n' . $ft['due_gst'];
                        }
                    }

                    if (!$booLetterTemplate && $booCurrentPaymentSelected) {
                        $record = '<tr><td style="border:#ccc solid 1px; padding:2px; font:9px Arial;">' . $ft['date'] . '</td>' .
                            '<td style="border:#ccc solid 1px; padding:2px; font:9px Arial;">' . $ft['description'] . $equal . '</td>' .
                            ($booReplaceFinTableSelectedRecordsInvoice ? '<td align="right" style="border:#ccc solid 1px; padding:2px; font:9px Arial;">' . $feeDue . '</td></tr>' : '<td align="right" style="border:#ccc solid 1px; padding:2px; font:9px Arial;">' . $feeReceived . '</td>');
                        $list .= $record;
                    } elseif ($booCurrentPaymentSelected) {
                        $record = array(
                            $ft['date'],
                            $ft['description'] . $equal
                        );
                        if ($booReplaceFinTableSelectedRecordsInvoice) {
                            $record[2] = $feeDue;
                            $totalSelectedRecords += (float)$ft['fees_due'] + (float)$ft['due_gst'];
                        } else {
                            $record[2] = $feeReceived;
                            $totalSelectedRecords += (float)$ft['fees_received'];
                        }
                        $arrFinancialTableValues[] = $record;
                    }

                }

                $totalSelectedRecords = $this->_clients->getAccounting()::formatPrice($totalSelectedRecords);

                if (!$booLetterTemplate) {
                    $list .= '<tr><td>&nbsp;</td><td style="border:#ccc solid 1px; padding:2px; font:9px Arial;"><b>' . ($booReplaceFinTableSelectedRecordsInvoice ? 'Balance Due:' : 'Balance Received:') . '</td><td>&nbsp;</td>' .
                        '<td align="right" style="border:#ccc solid 1px; padding:2px; font:9px Arial;"><b>' . Clients\Accounting::getCurrencyLabel($ta['currency']) . '&nbsp;' . $totalSelectedRecords . '</b></td></tr>';
                } else {
                    $balanceLabel = $booReplaceFinTableSelectedRecordsInvoice ? 'Balance Due:' : 'Balance Received:';
                    $arrFinancialTableValues[] = array(
                        array(
                            'colspan' => 2,
                            'value' => $balanceLabel,
                        ),
                        array(
                            'value' => Clients\Accounting::getCurrencyLabel($ta['currency']) . ' ' . $totalSelectedRecords,
                        )
                    );
                }
            }

            if ($ta['currency'] == $defaultCurrency) {
                $canList[] = !$booLetterTemplate ? $list : (empty($arrFinancialTableValues) ? '-' : new PhpDocxTable(['values' => $arrFinancialTableValues, 'properties' => $paramsTable]));
            } else {
                $nonCanList[] = !$booLetterTemplate ? $list : (empty($arrFinancialTableValues) ? '-' : new PhpDocxTable(['values' => $arrFinancialTableValues, 'properties' => $paramsTable]));
            }
        }

        if (!$booLetterTemplate) {
            $canList    = empty($canList) ? '-' : '<table style="border:#ccc solid 1px; font-size:12px; border-collapse:collapse; padding:2px;" width="500" cellspacing="0" cellpadding="0">' . implode(',', $canList) . '</table>';
            $nonCanList = empty($nonCanList) ? '-' : '<table style="border:#ccc solid 1px; font-size:12px; border-collapse:collapse; padding:2px;" width="500" cellspacing="0" cellpadding="0">' . implode(',', $nonCanList) . '</table>';
        } else {
            $canList    = empty($canList) ? '-' : $canList;
            $nonCanList = empty($nonCanList) ? '-' : $nonCanList;
        }

        if ($booReplaceFinTableSelectedRecordsInvoice) {
            $arrVariablesToReplace[$currencyLabelNoSign . '_curr_fin_transaction_table_selected_records']                  = $canList;
            $arrVariablesToReplace[$currencyLabelNoSign . '_curr_fin_transaction_table_selected_records_invoice']          = $canList;
            $arrVariablesToReplace['non_' . $currencyLabelNoSign . '_curr_fin_transaction_table_selected_records_invoice'] = $nonCanList;
        } else {
            $arrVariablesToReplace[$currencyLabelNoSign . '_curr_fin_transaction_table_selected_records_receipt']          = $canList;
            $arrVariablesToReplace['non_' . $currencyLabelNoSign . '_curr_fin_transaction_table_selected_records_receipt'] = $nonCanList;
        }

        return $arrVariablesToReplace;
    }


    /**
     * Check if fields ids are used in the template
     *
     * @param array $arrVariablesToCheck
     * @param array $arrTemplateAllVariables
     * @return bool true if at least one field is in the template
     */
    private function _areFieldsInTemplate($arrVariablesToCheck, $arrTemplateAllVariables)
    {
        return !empty(array_intersect($arrVariablesToCheck, $arrTemplateAllVariables));
    }

    public function getTemplatesList($booWithoutOther = false, $msgType = 0, $templatesFor = false, $templatesType = false, $booOnlyShared = false, $booAssignedDeposit = false)
    {
        $arrTemplates = array();

        try {
            if (!$booWithoutOther) {
                switch ($msgType) {
                    case 1:
                        $doNotSendName = $this->_tr->translate("Don't Send Case Notification");
                        break;

                    case 2:
                        $doNotSendName = $this->_tr->translate("Don't Send Invoice");
                        break;

                    case 3:
                        $doNotSendName = $this->_tr->translate("Don't Send Request for Payment");
                        break;

                    case 4:
                        $doNotSendName = $this->_tr->translate('Do not generate invoice automatically');
                        break;

                    case 5:
                        $doNotSendName = $this->_tr->translate('No Template (blank document)');
                        break;

                    default:
                        $doNotSendName = $this->_tr->translate("Don't Send Receipt");
                        break;
                }

                if (!$booAssignedDeposit) {
                    $arrTemplates[] = array('templateId' => 0, 'templateName' => $doNotSendName);
                }
            }

            // Load templates list from the 'Shared Templates' folder
            $company_id         = $this->_auth->getCurrentUserCompanyId();
            $arrSharedFolderIds = $this->_files->getFolders()->getCompanyFolders($company_id, 0, array('ST', 'STR'), true, true);
            if (is_array($arrSharedFolderIds) && count($arrSharedFolderIds)) {
                $where = [];
                $where['folder_id'] = $arrSharedFolderIds;

                if ($templatesFor) {
                    $where['templates_for'] = $templatesFor;
                }

                if ($templatesType) {
                    $where['templates_type'] = $templatesType;
                }

                $select = (new Select())
                    ->from('templates')
                    ->columns(['templateId' => 'template_id', 'templateName' => 'name'])
                    ->where($where)
                    ->order('name ASC');

                $arrTemplates = array_merge($arrTemplates, $this->_db2->fetchAll($select));
            }

            if (!$booOnlyShared) {
                // Load templates list from the 'My Templates' and other private folders
                $arrUserTemplateFolderIds = $this->_files->getFolders()->getCompanyFolders($company_id, 0, array('T'), true, true);
                if (is_array($arrUserTemplateFolderIds) && count($arrUserTemplateFolderIds)) {
                    $memberId = $this->_auth->getCurrentUserId();
                    $where = [];
                    $where['folder_id'] = $arrUserTemplateFolderIds;
                    $where['member_id'] = $memberId;

                    if ($templatesFor) {
                        $where['templates_for'] = $templatesFor;
                    }

                    if ($templatesType) {
                        $where['templates_type'] = $templatesType;
                    }

                    $select = (new Select())
                        ->from('templates')
                        ->columns(['templateId' => 'template_id', 'templateName' => 'name'])
                        ->where($where)
                        ->order('name ASC');

                    $arrTemplates = array_merge($arrTemplates, $this->_db2->fetchAll($select));

                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrTemplates;
    }

    public function getTemplatesForName($templates_for)
    {
        switch ($templates_for) {
            case 'General':
                $strName = 'General';
                break;

            case 'Invoice':
                $strName = 'Invoice';
                break;

            case 'Payment':
                $strName = 'Receipt of payment';
                break;

            case 'Request':
                $strName = 'Request for Payment';
                break;

            case 'Password':
                $strName = 'User Id & Password update';
                break;

            case 'Prospect':
                $strName = 'Company Prospect';
                break;

            case 'Welcome':
                $strName = 'Welcome Message';
                break;

            default:
                $strName = '';
                break;
        }

        return $strName;
    }

    public function getTemplates($folderId, $memberId)
    {
        $select = (new Select())
            ->from(['t' => 'templates'])
            ->columns(['template_id', 'name', 'member_id', 'updated_by_id', 'order', 'create_date', 'update_date', 'templates_type', 'templates_for', 'length' => new Expression('LENGTH(message)'), 'default'])
            ->join(array('f' => 'u_folders'), 'f.folder_id = t.folder_id', [], Select::JOIN_LEFT_OUTER)
            ->join(array('m' => 'members'), 'm.member_id = t.member_id', ['fName', 'lName'], Select::JOIN_LEFT_OUTER)
            ->join(array('u' => 'members'), 'u.member_id = t.updated_by_id', ['update_fName' => 'fName', 'update_lName' => 'lName'], Select::JOIN_LEFT_OUTER)
            ->where(
                [
                    't.folder_id' => $folderId,
                    (new Where())->nest()->equalTo('f.type', 'T')->and->equalTo('t.member_id', $memberId)->or->in('f.type', ['ST', 'STR'])->unnest()
                ]
            )
            ->order(['t.order ASC', 'create_date DESC']);

        return $this->_db2->fetchAll($select);
    }

    private function _getTemplatesContent($arrFolders, $parentId = 0, $parentAccess = 'RW')
    {
        $arr             = array();
        $i               = 0;
        $currentMemberId = $this->_auth->getCurrentUserId();
        $companyId       = $this->_auth->getCurrentUserCompanyId();
        $folders         = $this->_files->getFolders()->getTemplateFolders($companyId, $parentId, $currentMemberId);
        $booCompanyAdmin = $this->_auth->isCurrentUserAdmin();
        $booSuperAdmin   = $this->_auth->isCurrentUserSuperadmin();
        $dateFormatFull  = $this->_settings->variable_get('dateFormatFull');

        foreach ($folders as $folder) {
            //get access
            $access = $parentAccess;
            foreach ($arrFolders as $folderId => $arrFolderAccess) {
                if ($folderId == $folder['folder_id']) {
                    $access = $arrFolderAccess;
                }
            }

            //add folder
            $allowRW           = ($booSuperAdmin || ($access == 'RW'));
            $allowEdit         = $allowRW || ($booCompanyAdmin || $booSuperAdmin || $folder['type'] == 'T' || $folder['type'] == 'STR');
            $allowDeleteFolder = $allowEdit && ($folder['type'] != 'STR' || (!empty($folder['parent_id'])));

            // Root "My Templates" folder cannot be deleted/renamed.
            if ($allowDeleteFolder && $folder['type'] == 'T' && empty($folder['parent_id'])) {
                $allowDeleteFolder = false;
            }

            $arrAccessRights = array(
                'allowAdd'                => $allowRW || $folder['type'] != 'STR' || $folder['type'] != 'T',
                'allowDelete'             => $folder['author_id'] == $this->_auth->getCurrentUserId(),
                'allowDefault'            => $folder['type'] != 'STR',
                'allowAddFolder'          => $allowEdit,
                'allowRenameDeleteFolder' => $allowDeleteFolder
            );

            // Get sub folders + templates
            $arrChildren = $this->_getTemplatesContent($arrFolders, $folder['folder_id'], $access);

            $arr[$i] = array(
                'el_id'           => $folder['folder_id'],
                'filename'        => $folder['folder_name'],
                'uiProvider'      => 'col',
                'iconCls'         => empty($arrAccessRights) ? 'far fa-folder' : 'fas fa-folder',
                'allowDrag'       => false,
                'allowDrop'       => true,
                'allowChildren'   => true,
                'expanded'        => true,
                'leaf'            => false,
                'folder'          => true,
                'isSharedFolder'  => $folder['type'] == 'STR',
                'type'            => $folder['type'],
                'children'        => $arrChildren,
                'arrAccessRights' => $arrAccessRights
            );

            //get templates
            $templates = $this->getTemplates($folder['folder_id'], $currentMemberId);
            foreach ($templates as $template) {
                $template = $this->_clients->generateUpdateMemberName($template);
                $templateAccess = $this->getAccessRightsToTemplate($template['template_id']);

                $arrAccessRights = array(
                    'allowAdd'                => $allowRW || $folder['type'] != 'STR' || $folder['type'] != 'T',
                    'allowDelete'             => $templateAccess === 'edit',
                    'allowDefault'            => $template['default'] != 'Y' && $templateAccess,
                    'allowAddFolder'          => $allowEdit,
                    'allowRenameDeleteFolder' => $allowDeleteFolder
                );

                // Show updated by/on in the tooltip if creation/update dates are different
                $createdOnDate  = $this->_settings->formatDate($template['create_date']);
                $updatedOnDate  = $this->_settings->formatDate($template['update_date']);

                // Don't show "00:00:00" in the tooltip
                $createdOnDateTime = $this->_settings->formatDate($template['create_date'], true, $dateFormatFull . ' ' . 'H:i:s');
                $createdOnDateTime = strpos($createdOnDateTime, '00:00:00') !== false ? $createdOnDate : $createdOnDateTime;

                $tooltip = $this->_tr->translate('Created By: ') . $template['full_name'] . $this->_tr->translate(' on ') . $createdOnDateTime;

                if ($template['update_date'] != $template['create_date']) {
                    // Don't show "00:00:00" in the tooltip
                    $updatedOnDateTime = $this->_settings->formatDate($template['update_date'], true, $dateFormatFull . ' ' . 'H:i:s');
                    $updatedOnDateTime = strpos($updatedOnDateTime, '00:00:00') !== false ? $updatedOnDate : $updatedOnDateTime;

                    $tooltip = $this->_tr->translate('Updated By: ') . $template['update_full_name'] . $this->_tr->translate(' on ') . $updatedOnDateTime . '<br>' . $tooltip;
                }

                $templatesArr = array(
                    'el_id'            => $template['template_id'],
                    'folder'           => false,
                    'filename'         => $template['name'],
                    'author'           => $template['full_name'],
                    'author_update'    => $template['update_full_name'],
                    'order'            => $template['order'],
                    'templates_type'   => $template['templates_type'],
                    'templates_for'    => $this->getTemplatesForName($template['templates_for']),
                    'create_date'      => $createdOnDate,
                    'update_date'      => $updatedOnDate,
                    'uiProvider'       => 'col',
                    'allowDrag'        => true,
                    'leaf'             => true,
                    'is_default'       => $template['default'] == 'Y',
                    'cls'              => 'template-icon',
                    'arrAccessRights'  => $arrAccessRights,
                    'template_tooltip' => $tooltip
                );

                $arr[$i]['children'][] = $templatesArr;
            }

            ++$i;
        }

        return $arr;
    }

    public function getTemplatesContent()
    {
        try {
            $currentMemberId = $this->_auth->getCurrentUserId();
            $companyId       = $this->_auth->getCurrentUserCompanyId();
            $roles           = $this->_clients->getMemberRoles($currentMemberId);

            // Get default folders
            $defaultFolders = $this->_files->getFolders()->getTemplateFolders($companyId, 0, $currentMemberId);

            // Get folders access
            $arrFoldersAccess = $this->_files->getFolders()->getFolderAccess()->getFoldersAccessByRoles($roles);

            $arrFolders = array();
            foreach ($defaultFolders as $folder) {
                $folderId = $folder['folder_id'];

                //get access
                $access       = 'R';
                $booIsInRoles = false;
                foreach ($roles as $role) {
                    $booIsInRole = false;

                    foreach ($arrFoldersAccess as $fa) {
                        if ($fa['folder_id'] == $folderId && $fa['role_id'] == $role) {
                            $booIsInRole = $booIsInRoles = true;
                            $access      = ($access == 'R' && $fa['access'] == 'RW') ? 'RW' : $access;
                        }
                    }

                    if (!$booIsInRole && !$booIsInRoles) {
                        $access = false;
                    }
                }

                $arrFolders[$folderId] = $access;
            }

            $arrContent = $this->_getTemplatesContent($arrFolders);
        } catch (Exception $e) {
            $arrContent = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrContent;
    }

    public function recursiveIsSharedFolder($folderId)
    {
        $arrFolderInfo = $this->_files->getFolders()->getFolderInfo($folderId);

        if ($arrFolderInfo['type'] == 'STR') {
            return true;
        }

        if ($arrFolderInfo['parent_id']) {
            return $this->recursiveIsSharedFolder($arrFolderInfo['parent_id']);
        }

        return false;
    }

    public function isSharedTemplate($templateId)
    {
        $booIsShared = false;

        $arrTemplateInfo = $this->getTemplate($templateId);
        if (isset($arrTemplateInfo['folder_id']) && $this->recursiveIsSharedFolder($arrTemplateInfo['folder_id'])) {
            $booIsShared = true;
        }

        return $booIsShared;
    }

    private function getTemplatesByFolderId($folderId)
    {
        $select = (new Select())
            ->from('templates')
            ->columns(['template_id'])
            ->where(['folder_id' => (int)$folderId])
            ->order('create_date DESC');

        return $this->_db2->fetchCol($select);
    }

    private function getFolderOrdersSum($folderId)
    {
        $select = (new Select())
            ->from('templates')
            ->columns(['sum' => new Expression('SUM(`order`)')])
            ->where(['folder_id' => (int)$folderId]);

        return $this->_db2->fetchOne($select);
    }

    private function getFolderOrdersMax($folderId)
    {
        $order = 0;
        if (!empty($folderId)) {
            $select = (new Select())
                ->from('templates')
                ->columns(['max' => new Expression('MAX(`order`)')])
                ->where(['folder_id' => (int)$folderId]);

            $order = $this->_db2->fetchOne($select);
        }

        return $order;
    }

    private function getFolderOrdersCount($folderId)
    {
        $select = (new Select())
            ->from('templates')
            ->columns(['count' => new Expression('COUNT(`order`)')])
            ->where(['folder_id' => (int)$folderId]);

        return $this->_db2->fetchOne($select);
    }

    public function dragAndDrop($templateId, $folderId, $order)
    {
        $memberId = $this->_auth->getCurrentUserId();

        $arrTemplateInfo = $this->getTemplate($templateId);
        $oldFolderId     = isset($arrTemplateInfo['folder_id']) ? $arrTemplateInfo['folder_id'] : 0;

        // if in old folder all orders are 0, re-order all templates
        if ($this->getFolderOrdersSum($oldFolderId) == 0) {
            $arrTemplateIds = $this->getTemplatesByFolderId($oldFolderId);

            if (count($arrTemplateIds)) {
                $counter = 0;
                foreach ($arrTemplateIds as $t_id) {
                    $this->_db2->update('templates', ['order' => $counter], ['template_id' => $t_id]);
                    $counter++;
                }
            }
        }

        // if in new folder all orders are 0, re-order all templates
        if ($folderId != $oldFolderId && $this->getFolderOrdersSum($folderId) == 0) {
            $arrTemplateIds = $this->getTemplatesByFolderId($folderId);

            if (count($arrTemplateIds)) {
                $counter = 0;
                foreach ($arrTemplateIds as $t_id) {
                    $this->_db2->update('templates', ['order' => $counter], ['template_id' => $t_id]);
                    $counter++;
                }
            }
        }

        $arrTemplateInfo = $this->getTemplate($templateId);
        $oldOrder        = isset($arrTemplateInfo['order']) ? $arrTemplateInfo['order'] : 0;

        if ($oldFolderId != $folderId) {
            // moving into other folder
            // in old folder dec all templates order, where order>file_order
            $this->_db2->update(
                'templates',
                ['order' => new Expression('(`order`-1)')],
                [
                    (new Where())->greaterThan('order', $oldOrder),
                    'folder_id' => $oldFolderId
                ]
            );

            // in new folder inc all templates order, where order>=file_order
            $this->_db2->update(
                'templates',
                ['order' => new Expression('(`order`+1)')],
                [
                    (new Where())->greaterThanOrEqualTo('order', $order),
                    'folder_id' => $folderId
                ]
            );

        } else // moving into the same folder
        {
            // inc/dec all templates order, where order>=new_order AND order<old_order
            $todo    = ($oldOrder > $order) ? '+1' : '-1';
            $arrWhere = ($oldOrder > $order) ? [
                (new Where())->greaterThanOrEqualTo('order', min($order, $oldOrder)),
                (new Where())->lessThan('order', max($order, $oldOrder)),
                'folder_id' => $folderId
            ] : [
                (new Where())->greaterThan('order', min($order, $oldOrder)),
                (new Where())->lessThanOrEqualTo('order', max($order, $oldOrder)),
                'folder_id' => $folderId
            ];

            $this->_db2->update('templates', ['order' => new Expression("(`order` $todo)")], $arrWhere);
        }

        $arrToUpdate = array(
            'folder_id' => (int)$folderId,
            'order'     => (int)$order
        );

        if ($this->isSharedTemplate($templateId)) {
            // no need to check if this template belongs to this member
            $result = $this->_db2->update('templates', $arrToUpdate, ['template_id' => (int)$templateId]);
        } else {
            $result = $this->_db2->update('templates', $arrToUpdate, ['template_id' => (int)$templateId, 'member_id' => $memberId]);
        }

        // if we move to non-shared folder, update member_id
        if (!$this->recursiveIsSharedFolder($folderId)) {
            $this->_db2->update(
                'templates',
                ['member_id' => $memberId],
                ['template_id' => $templateId]
            );
        }

        return $result;
    }

    public function delete($templates)
    {
        $booSuccess = false;
        try {
            if (!is_array($templates)) {
                $templates = array($templates);
            }

            foreach ($templates as $templateId) {
                if (empty($templateId) || !is_numeric($templateId) || !$this->getAccessRightsToTemplate($templateId)) {
                    return false;
                }
            }

            // dec all templates order, where order>current_template_order
            if (count($templates) == 1) {
                $arrTemplateInfo = $this->getTemplate($templates[0]);
                if (isset($arrTemplateInfo['folder_id']) && !empty($arrTemplateInfo['folder_id'])) {
                    $max_order = $this->getFolderOrdersMax($arrTemplateInfo['folder_id']);
                    $tpl_count = $this->getFolderOrdersCount($arrTemplateInfo['folder_id']);

                    if ($tpl_count > 1 && $max_order != 0) {
                        $this->_db2->update(
                            'templates',
                            ['order' => new Expression("(`order`-1)")],
                            [(new Where())->greaterThan('order', $arrTemplateInfo['order'])]
                        );
                    }
                }
            }

            if (count($templates)) {
                $companyId = $this->_auth->getCurrentUserCompanyId();
                $booLocal  = $this->_company->isCompanyStorageLocationLocal($companyId);
                foreach ($templates as $templateId) {
                    $filePath = $this->_files->getCompanyLetterTemplatesPath($companyId, $booLocal) . '/' . $templateId;
                    $this->_files->deleteFile($filePath, $booLocal);
                }

                $templatesAttachmentsPath = $this->_files->getCompanyTemplateAttachmentsPath($companyId, $booLocal);
                foreach ($templates as $templateId) {
                    $folderPath = $templatesAttachmentsPath . '/' . $templateId;
                    $this->_files->deleteFolder($folderPath, $booLocal);
                }

                $this->_db2->delete('templates', ['template_id' => $templates]);

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Delete template by specific template id
     *
     * @param int $templateId
     */
    public function deleteTemplateById($templateId)
    {
        if (!empty($templateId) && is_numeric($templateId)) {
            $this->_db2->delete('templates', ['template_id' => (int)$templateId]);
        }
    }
    

    public function duplicate($arrTemplateIds)
    {
        $strError = '';

        try {
            if (!is_array($arrTemplateIds)) {
                $arrTemplateIds = array($arrTemplateIds);
            }

            if (count($arrTemplateIds) > 0) {
                $memberId = $this->_auth->getCurrentUserId();

                $select = (new Select())
                    ->from(['t' => 'templates'])
                    ->join(array('f' => 'u_folders'), 'f.folder_id = t.folder_id', [], Select::JOIN_LEFT_OUTER)
                    ->where([
                        't.template_id' => $arrTemplateIds,
                        (new Where())
                            ->nest()
                            ->equalTo('t.member_id', $memberId)
                            ->or
                            ->equalTo('f.type', 'STR')
                    ]);

                $arrTemplates = $this->_db2->fetchAll($select);

                foreach ($arrTemplates as $arrTemplateInfo) {
                    if (!empty($strError)) {
                        break;
                    }
                    $templateId = $arrTemplateInfo['template_id'];
                    unset($arrTemplateInfo['template_id'], $arrTemplateInfo['default']);

                    $maxOrder = $this->getFolderOrdersMax($arrTemplateInfo['folder_id']);
                    $tplCount = $this->getFolderOrdersCount($arrTemplateInfo['folder_id']);

                    $arrTemplateInfo['create_date'] = date('Y-m-d');
                    $arrTemplateInfo['member_id']   = $memberId;
                    $arrTemplateInfo['name']        = 'Copy of ' . $arrTemplateInfo['name'];
                    $arrTemplateInfo['order']       = ($tplCount > 1 && $maxOrder != 0) ? (int)$maxOrder + 1 : 0;

                    $newTemplateId = $this->_db2->insert('templates', $arrTemplateInfo);

                    if ($arrTemplateInfo['templates_type'] == 'Email') {
                        $select = (new Select())
                            ->from('template_attachments')
                            ->columns(['letter_template_id'])
                            ->where(['email_template_id' => (int)$templateId]);

                        $arrTemplateAttachments = $this->_db2->fetchCol($select);

                        foreach ($arrTemplateAttachments as $templateAttachmentId) {
                            $this->_db2->insert(
                                'template_attachments',
                                [
                                    'email_template_id'  => (int)$newTemplateId,
                                    'letter_template_id' => (int)$templateAttachmentId
                                ]
                            );
                        }
                    } else {
                        $strError = $this->_files->duplicateCompanyLetterTemplate(
                            $this->_auth->getCurrentUserCompanyId(),
                            $templateId,
                            $newTemplateId,
                            $this->_company->isCompanyStorageLocationLocal($this->_auth->getCurrentUserCompanyId())
                        );
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

    /**
     * Create/update template's info
     *
     * @param array $form
     * @return bool|int false on error, otherwise template id
     */
    public function saveTemplate($form)
    {
        try {
            $filter    = new StripTags();
            $oPurifier = $this->_settings->getHTMLPurifier(false);
            $maxOrder  = $this->getFolderOrdersMax($form['folder_id']);
            $tplCount  = $this->getFolderOrdersCount($form['folder_id']);

            $arrTemplateData = array(
                'folder_id'       => (int)$form['folder_id'],
                'templates_type'  => $form['templates_type'],
                'templates_for'   => $form['templates_for'],
                'order'           => ($tplCount > 1 && $maxOrder != 0) ? (int)$maxOrder + 1 : 0,
                'name'            => trim($filter->filter($form['templates_name']) ?? ''),
                'subject'         => isset($form['templates_subject']) ? trim($form['templates_subject']) : '',
                'from'            => $form['templates_from'] ?? '',
                'cc'              => $form['templates_cc'] ?? '',
                'bcc'             => $form['templates_bcc'] ?? '',
                'message'         => $oPurifier->purify(trim($form['templates_message'] ?? '')),
                'attachments_pdf' => isset($form['templates_type']) && $form['templates_type'] == 'Email' && isset($form['templates_send_as_pdf']) ? $form['templates_send_as_pdf'] : 0,
                'update_date'     => date('c'),
                'updated_by_id'   => $this->_auth->getCurrentUserId()
            );

            if ($form['act'] == 'add') {
                $arrTemplateData['member_id']   = $this->_auth->getCurrentUserId();
                $arrTemplateData['create_date'] = date('c');

                $templateId = $this->_db2->insert('templates', $arrTemplateData);
            } else {
                $templateId = $form['template_id'];

                $this->_db2->update('templates', $arrTemplateData, ['template_id' => $templateId]);
            }

            if ($form['templates_type'] == 'Email') {
                // Unassign previously assigned PDF attachments
                // and assign only that were just selected
                $this->_db2->delete('template_attachments', ['email_template_id' => (int)$templateId]);

                if (isset($form['templates_attachments']) && is_array($form['templates_attachments']) && !empty($form['templates_attachments'])) {
                    foreach ($form['templates_attachments'] as $templateAttachmentId) {
                        $this->_db2->insert(
                            'template_attachments',
                            [
                                'email_template_id'  => (int)$templateId,
                                'letter_template_id' => (int)$templateAttachmentId
                            ]
                        );
                    }
                }

                $targetPath    = $this->_config['directory']['tmp'] . '/uploads/';
                $memberId      = $this->_auth->getCurrentUserId();
                $companyId     = $this->_auth->getCurrentUserCompanyId();
                $booLocal      = $this->_company->isCompanyStorageLocationLocal($companyId);
                $templatesPath = $this->_files->getCompanyTemplateAttachmentsPath($companyId, $booLocal);


                $arrSavedFileAttachments = $this->getTemplateFileAttachments($templateId);

                // Delete previously uploaded files that were assigned to this template
                // If they were not provided again (e.g. deleted on the page)
                $form['templates_file_attachments'] = $form['templates_file_attachments'] ?? array();
                foreach ($arrSavedFileAttachments as $savedFileAttachment) {
                    $booFound = false;
                    foreach ($form['templates_file_attachments'] as $templateFileAttachment) {
                        if ($savedFileAttachment['id'] == $templateFileAttachment['attach_id']) {
                            $booFound = true;
                            break;
                        }
                    }

                    if (!$booFound) {
                        $this->_db2->delete('template_file_attachments', ['id' => (int)$savedFileAttachment['id']]);

                        $filePath  = $templatesPath . '/' . $templateId . '/' . $savedFileAttachment['id'];
                        $this->_files->deleteFile($filePath, $booLocal);
                    }
                }

                // If just uploaded file was not linked to this template yet:
                // 1. move that file to the correct place and
                // 2. link it to the template
                foreach ($form['templates_file_attachments'] as $templateFileAttachment) {
                    if ($templateFileAttachment['type'] !== 'uploaded') {
                        continue;
                    }

                    $attachmentId = $this->_db2->insert(
                        'template_file_attachments',
                        [
                            'template_id' => (int)$templateId,
                            'member_id'   => $memberId,
                            'name'        => $filter->filter($templateFileAttachment['name']),
                            'size'        => empty($templateFileAttachment['size']) ? null : (int)$templateFileAttachment['size']
                        ]
                    );

                    $templateFileAttachment['tmp_name'] = $this->_encryption->decode($templateFileAttachment['tmp_name']);
                    // File path is in such format: path/to/file#check_id
                    if (preg_match('/(.*)#(\d+)/', $templateFileAttachment['tmp_name'], $regs)) {
                        $templateFileAttachment['tmp_name'] = $regs[1];
                    }

                    $tmpPath = str_replace('//', '/', $targetPath) . $templateFileAttachment['tmp_name'];

                    if (file_exists($tmpPath)) {
                        // Get correct path to the file in the cloud
                        $pathToFile  = $templatesPath . '/' . $templateId . '/' . $attachmentId;

                        // Upload file and
                        // make sure file doesn't exist there, if yes - unique name will be generated
                        if ($booLocal) {
                            $filePath = $this->_files->fixFilePath($pathToFile);
                            $booSuccess = $this->_files->moveLocalFile($tmpPath, $filePath);
                        } else {
                            $booSuccess = $this->_files->getCloud()->uploadFile($tmpPath, $this->_files->getCloud()->fixFilePath($pathToFile));
                        }

                        if (!$booSuccess) {
                            // Should not happen, just log
                            $this->_log->debugErrorToFile("Template's attachment was not copied", 'From: ' . $tmpPath . ' To: ' . $pathToFile);
                        }

                        unlink($tmpPath);
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            $templateId = false;
        }

        return $templateId;
    }

    public function renameTemplate($templateId, $templateName)
    {
        $this->_db2->update('templates', ['name' => $templateName], ['template_id' => $templateId]);
    }

    //Save/Show As PDF
    public function createEmlAsPdf($memberId, $templateId, $option, $fileName = 'Payment')
    {
        try {
            $booSuccess  = false;
            $message     = $this->getMessage($templateId, $memberId);
            $author_info = $this->_clients->getMemberInfo();
            $client_info = $this->_clients->getClientInfo($memberId);

            $html = '<h2>Case: ' . $client_info['full_name_with_file_num'] . '</h2>' . $message['message'];

            $fileName .= ' - ' . date('Y-m-d H-i') . '.pdf';

            $destination = $option == 'F' ? tempnam($this->_config['directory']['tmp'], 'pdf') : $fileName;
            $this->_pdf->htmlToPdf($html, $destination, $option, array('header_title' => $message['subject'], 'SetAuthor' => $author_info['full_name']));

            if ($option == 'F') {
                if (file_exists($destination)) {
                    // Get correct path to the file in the cloud
                    $companyId  = $this->_auth->getCurrentUserCompanyId();
                    $isLocal    = $this->_company->isCompanyStorageLocationLocal($companyId);
                    $pathToFile = $this->_files->getClientCorrespondenceFTPFolder($companyId, $memberId, $isLocal) . '/' . $fileName;

                    // Upload file and
                    // make sure file doesn't exist there, if yes - unique name will be generated
                    if ($this->_auth->isCurrentUserCompanyStorageLocal()) {
                        $filePath = $this->_files->fixFilePath($pathToFile);
                        $this->_files->createFTPDirectory(dirname($filePath));

                        rename($destination, $filePath);
                    } else {
                        $booSuccess = $this->_files->getCloud()->uploadFile($destination, $this->_files->getCloud()->fixFilePath($pathToFile));
                    }
                }
            } else {
                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    public function getDefaultTemplateId()
    {
        //select template for this user or from shared templates
        $select = (new Select())
            ->from(['t' => 'templates'])
            ->columns(['template_id'])
            ->join(['f' => 'u_folders'], 'f.folder_id = t.folder_id', [], Select::JOIN_LEFT)
            ->join(['m' => 'members'], 'm.member_id = t.member_id', [], Select::JOIN_LEFT)
            ->where(
                [
                    (new Where())
                        ->equalTo('t.default', 'Y')
                        ->nest()
                        ->equalTo('m.member_id', $this->_auth->getCurrentUserId())
                        ->or
                        ->nest()
                        ->in('f.type', ['ST', 'STR'])
                        ->equalTo('m.company_id', $this->_auth->getCurrentUserCompanyId())
                        ->unnest()
                        ->unnest()
                ]
            )
            ->limit(1);

        return (int)$this->_db2->fetchOne($select);
    }

    /**
     * Set template as a default
     *
     * @param array|int $oldTemplateId
     * @param int $newTemplateId
     * @return bool
     */
    public function setTemplateAsDefault($oldTemplateId, $newTemplateId)
    {
        $booSuccess = false;
        if ((is_array($oldTemplateId) && count($oldTemplateId)) || is_numeric($oldTemplateId)) {
            // Unset old template(s) as default
            $this->_db2->update('templates', ['default' => 'N'], ['template_id' => $oldTemplateId]);
        }

        if (!empty($newTemplateId) && is_numeric($newTemplateId)) {
            // Set new template as default
            $this->_db2->update('templates', ['default' => 'Y'], ['template_id' => $newTemplateId]);

            $booSuccess = $this->getDefaultTemplateId() > 0;
        }

        return $booSuccess;
    }

    /**
     * Check access rights to a specific template
     *
     * @param int $templateId
     * @param int|null $memberId
     * @return bool|string false if no access, 'read' if read-only and 'edit' if full access
     */
    public function getAccessRightsToTemplate($templateId, $memberId = null)
    {
        if (empty($memberId)) {
            $memberId              = $this->_auth->getCurrentUserId();
            $companyId             = $this->_auth->getCurrentUserCompanyId();
            $booMemberIsSuperAdmin = $this->_auth->isCurrentUserSuperadmin();
        } else {
            $arrMemberInfo         = $this->_clients->getMemberInfo($memberId);
            $companyId             = $arrMemberInfo['company_id'];
            $booMemberIsSuperAdmin = in_array($arrMemberInfo['userType'], Members::getMemberType('superadmin'));
        }

        $access = false;

        if (!empty($templateId)) {
            $select = (new Select())
                ->from(['t' => 'templates'])
                ->columns(['template_id', 'member_id'])
                ->join(array('m' => 'members'), 't.member_id = m.member_id', [], Select::JOIN_LEFT_OUTER)
                ->join(array('f' => 'u_folders'), 'f.folder_id = t.folder_id', 'type', Select::JOIN_LEFT_OUTER)
                ->where([
                    'm.company_id'  => (int)$companyId,
                    't.template_id' => (int)$templateId
                ]);

            $arrTemplateInfo = $this->_db2->fetchRow($select);

            // This template not for current member company or not found
            if (!empty($arrTemplateInfo)) {
                if ($arrTemplateInfo['member_id'] == $memberId) {
                    // This is current user's template
                    $access = 'edit';
                } elseif ($arrTemplateInfo['type'] != 'ST' && $arrTemplateInfo['type'] != 'STR') {
                    // This template not for the current member and is not shared
                    $access = false;
                } elseif ($this->_acl->isMemberAllowed($memberId, 'templates-manage-shared') || $booMemberIsSuperAdmin) {
                    // Access is allowed in the role or this is superadmin
                    $access = 'edit';
                } else {
                    // Not an owner nor admin, but this is a Shared Template
                    $access = 'read';
                }
            }
        }

        return $access;
    }

    public function addFolder($options)
    {
        $options['parent_id']   = empty($options['parent_id']) ? 0 : $options['parent_id'];
        $options['company_id']  = empty($options['company_id']) ? $this->_auth->getCurrentUserCompanyId() : $options['company_id'];
        $options['author_id']   = empty($options['author_id']) ? $this->_auth->getCurrentUserId() : $options['author_id'];
        $options['folder_name'] = empty($options['folder_name']) ? 'New Folder' : $options['folder_name'];

        if (empty($options['type'])) {
            $arrParentFolderInfo = $this->_files->getFolders()->getFolderInfo($options['parent_id']);
            $options['type']     = $arrParentFolderInfo['type'];
        }

        $newFolderId = $this->_files->getFolders()->createFolder(
            $options['company_id'],
            $options['author_id'],
            $options['parent_id'],
            $options['folder_name'],
            $options['type']
        );

        return !empty($newFolderId);
    }

    public function renameFolder($folderId, $folderName)
    {
        return $this->_files->getFolders()->updateFolder($folderId, $folderName);
    }

    public function deleteFolder($folderId)
    {
        $arrFolderIds = array();
        $this->_files->getFolders()->getSubFolderIds($folderId, $arrFolderIds);

        // Delete templates from these folders/subfolders
        if (is_array($arrFolderIds) && count($arrFolderIds)) {
            $select = (new Select())
                ->from('templates')
                ->columns(['template_id'])
                ->where(['folder_id' => $arrFolderIds]);

            $arrTemplateIds = $this->_db2->fetchCol($select);

            $this->delete($arrTemplateIds);
        }

        // Delete this folder
        return $this->_files->getFolders()->deleteFolders($arrFolderIds);
    }

    public function getSendAsOptions()
    {
        $options   = array();
        $options[] = array('optionId' => 1, 'optionName' => 'Send as Email');
        //$options[] = array('optionId' => 2, 'optionName' => 'Show as PDF');

        $companyPackages = $this->_company->getPackages()->getCompanyPackages($this->_auth->getCurrentUserCompanyId());

        if ($this->_acl->isAllowed('client-documents-view') && in_array(3, $companyPackages)) {
            //$options[] = array('optionId' => 3, 'optionName' => 'Save as Letter');
            $options[] = array('optionId' => 4, 'optionName' => 'Save to Documents');
            //$options[] = array('optionId' => 5, 'optionName' => 'Save as PDF');
        }

        $options[] = array('optionId' => 6, 'optionName' => 'Download as Doc');

        return $options;
    }

    /**
     * Create docx (letter template) for a specific template
     *
     * @param int $templateId
     * @param int $clientId
     * @param array $arrPayments
     * @param int $caseParentId
     * @return false on fail, otherwise string path to the generated file
     */
    public function createLetter($templateId, $clientId, $arrPayments, $caseParentId)
    {
        $fileExtension = '.docx';
        $tempFileName  = FileTools::cleanupFileName('Letter docx - ' . date('Y-m-d H-i-s') . substr((string)microtime(), 1, 8) . $fileExtension);
        $fileTmpPath   = $this->_files->generateFileName($this->_config['directory']['tmp'] . '/' . $tempFileName, true);

        if (empty($templateId)) {
            $booSuccess = $this->_documents->getPhpDocx()->createDocx(substr($fileTmpPath, 0, strlen($fileTmpPath) - strlen($fileExtension)));
            if (!$booSuccess) {
                $this->_log->debugErrorToFile('Empty Letter file was not created', $fileTmpPath);
            }
        } else {
            $arrExtraFields = array(
                'payments' => $arrPayments
            );

            $arrResult  = $this->getMessage($templateId, $clientId, '', false, false, $fileTmpPath, $arrExtraFields, $caseParentId);
            $booSuccess = $arrResult['success'];
        }

        return $booSuccess ? $fileTmpPath : false;
    }

    /**
     * Load list of filtering groups
     * Also, load several case templates (for case) or applicant types (for contacts)
     *
     * @return array
     */
    public function getTemplateFilterGroups()
    {
        try {
            $arrFilter = array();
            $companyId = $this->_auth->getCurrentUserCompanyId();

            // Load case templates list
            $arrCaseTemplates = $this->_clients->getCaseTemplates()->getTemplates($companyId, false, null, true);
            foreach ($arrCaseTemplates as $arrCaseTemplateInfo) {
                $arrFilter[] = array(
                    'filter_id'         => 'case_' . $arrCaseTemplateInfo['case_template_id'],
                    'filter_group_id'   => 'case',
                    'filter_group_name' => $this->_tr->translate('Case Details'),
                    'filter_type_id'    => $arrCaseTemplateInfo['case_template_id'],
                    'filter_type_name'  => $arrCaseTemplateInfo['case_template_name']
                );
            }

            // Load IA/Employer/Contact templates
            $arrClientFilters   = array();
            $arrClientFilters[] = array(
                'filterId'   => 'individual',
                'filterName' => $this->_tr->translate('Individual Profile')
            );

            if ($this->_auth->isCurrentUserSuperadmin() || $this->_auth->isCurrentMemberCompanyEmployerModuleEnabled()) {
                $arrClientFilters[] = array(
                    'filterId'   => 'employer',
                    'filterName' => $this->_tr->translate('Employer Profile')
                );
            }

            if ($this->_auth->isCurrentUserSuperadmin() || $this->_acl->isAllowed('contacts-view')) {
                $arrClientFilters[] = array(
                    'filterId'   => 'contact',
                    'filterName' => $this->_tr->translate('Contact Profile')
                );
            }

            foreach ($arrClientFilters as $arrClientFilterInfo) {
                $memberTypeId      = $this->_clients->getMemberTypeIdByName($arrClientFilterInfo['filterId']);
                $arrApplicantTypes = $this->_clients->getApplicantTypes()->getTypes($companyId, false, $memberTypeId);
                $arrApplicantTypes = empty($arrApplicantTypes) ? [['applicant_type_id' => 0]] : $arrApplicantTypes;

                foreach ($arrApplicantTypes as $arrApplicantTypeInfo) {
                    $arrFilter[] = array(
                        'filter_id'         => $arrClientFilterInfo['filterId'] . '_' . (int)$arrApplicantTypeInfo['applicant_type_id'],
                        'filter_group_id'   => $arrClientFilterInfo['filterId'],
                        'filter_group_name' => empty($arrApplicantTypeInfo['applicant_type_id']) ? '' : $arrClientFilterInfo['filterName'],
                        'filter_type_id'    => $arrApplicantTypeInfo['applicant_type_id'],
                        'filter_type_name'  => empty($arrApplicantTypeInfo['applicant_type_id']) ? $arrClientFilterInfo['filterName'] : $arrApplicantTypeInfo['applicant_type_name']
                    );
                }
            }

            $arrFilter[] = array(
                'filter_id'         => 'other_0',
                'filter_group_id'   => 'other',
                'filter_group_name' => '',
                'filter_type_name'  => $this->_tr->translate('Other Fields (company, users, date, etc)'),
                'filter_type_id'    => 0
            );

            $booSuccess = true;
        } catch (Exception $e) {
            $arrFilter  = array();
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'success' => $booSuccess,
            'rows'    => $arrFilter
        );
    }

    /**
     * Load list of fields for specific client type, and it's applicant type
     *
     * @param string $arrFilterInfo will be decoded
     * @return array
     */
    public function getTemplateFilterFields($arrFilterInfo)
    {
        try {
            $arrFields = array();
            if ($arrFilterInfo === 'individual_0') {
                $arrFilterInfo = ['filter_group_id' => 'individual', 'filter_type_id' => 0];
            } else {
                $arrFilterInfo = empty($arrFilterInfo) ? [] : Json::decode($arrFilterInfo, Json::TYPE_ARRAY);
            }

            $filterBy        = $arrFilterInfo['filter_group_id'] ?? '';
            $applicantTypeId = $arrFilterInfo['filter_type_id'] ?? 0;
            $companyId       = $this->_auth->getCurrentUserCompanyId();

            switch ($filterBy) {
                case 'individual':
                case 'employer':
                case 'contact':
                    // Check if user can load fields list
                    switch ($filterBy) {
                        case 'individual':
                            $booHasAccess = true;
                            break;

                        case 'employer':
                            $booHasAccess = $this->_auth->isCurrentUserSuperadmin() || $this->_auth->isCurrentMemberCompanyEmployerModuleEnabled();
                            break;

                        case 'contact':
                            $booHasAccess = $this->_auth->isCurrentUserSuperadmin() || $this->_acl->isAllowed('contacts-view');
                            break;

                        default:
                            $booHasAccess = false;
                            break;
                    }

                    // Check if passed "applicant type id" is correct
                    $memberTypeId = 0;
                    if ($booHasAccess) {
                        $memberTypeId      = $this->_clients->getMemberTypeIdByName($filterBy);
                        $arrApplicantTypes = $this->_clients->getApplicantTypes()->getTypes($companyId, true, $memberTypeId);
                        if (empty($arrApplicantTypes) && empty($applicantTypeId)) {
                            $applicantTypeId = 0;
                        } elseif (!in_array($applicantTypeId, $arrApplicantTypes)) {
                            $booHasAccess = false;
                        }
                    }


                    if ($booHasAccess) {
                        $n = 0;
                        // Load list of fields for this "member type" and for specific "applicant type"
                        $arrAllFields = $this->_clients->getApplicantFields()->getCompanyFields(
                            $companyId,
                            $memberTypeId,
                            $applicantTypeId
                        );

                        $maxRepeatableGroupsCount = self::getRepeatableGroupsMaxCount();
                        $repeatableGroupsPrefix   = self::getRepeatableGroupsPrefix();
                        foreach ($arrAllFields as $key => $arrFieldInfo) {
                            if (!empty($arrFieldInfo['group_id'])) {
                                if ($key != 0 && (isset($arrAllFields[$key - 1]['group_id']) && isset($arrAllFields[$key]['group_id'])) && $arrAllFields[$key - 1]['group_id'] != $arrAllFields[$key]['group_id']) {
                                    ++$n;
                                }

                                $fieldId    = $arrFieldInfo['applicant_field_unique_id'];
                                $fieldLabel = $arrFieldInfo['label'];
                                if ($arrFieldInfo['repeatable'] == 'Y') {
                                    for ($i = 0; $i < $maxRepeatableGroupsCount; $i++) {
                                        $arrFields[] = array(
                                            'n'     => $n,
                                            'group' => $arrFieldInfo['group_title'],
                                            'name'  => sprintf($repeatableGroupsPrefix, $i + 1) . $fieldId,
                                            'label' => $this->_tr->translate('Record ') . ($i + 1) . ' - ' . $fieldLabel
                                        );
                                    }
                                } else {
                                    $arrFields[] = array(
                                        'n'     => $n,
                                        'group' => $arrFieldInfo['group_title'],
                                        'name'  => $fieldId,
                                        'label' => $fieldLabel
                                    );
                                }
                            }
                        }
                    }

                    break;

                case 'other':
                    $arrFields = $this->getFields($filterBy);
                    break;

                case 'case':
                    $arrFields = $this->getFields($filterBy, $applicantTypeId);
                    break;

                default:
                    break;
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $arrFields  = array();
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'success'    => $booSuccess,
            'rows'       => $arrFields,
            'totalCount' => count($arrFields)
        );
    }

    /**
     * Generate "Comfort Letter" pdf file
     *
     * @param int $companyId
     * @param int $caseId
     * @param int $caseParentId
     * @param int $templateId
     * @param bool $booLocal
     * @return string empty on success, otherwise error message
     */
    public function generateComfortLetterPdf($companyId, $caseId, $caseParentId, $templateId, $booLocal)
    {
        try {
            $tmpFilePath = $this->createLetterFromLetterTemplate($templateId, $caseId, null, null, array(), true, $caseParentId);
            if ($tmpFilePath) {
                $letterTemplate       = $this->getTemplate($templateId);
                $correspondenceFolder = $this->_files->getClientCorrespondenceFTPFolder($companyId, $caseId, $booLocal);

                // Create directory if it is not created or deleted
                if ($booLocal) {
                    $this->_files->createFTPDirectory($correspondenceFolder);
                } else {
                    $this->_files->createCloudDirectory($correspondenceFolder);
                }

                $arrConvertingResult = $this->_documents->convertToPdf($correspondenceFolder, $tmpFilePath, $letterTemplate['name'] . '.docx', true);

                $strError = $arrConvertingResult['error'];
            } else {
                $strError = $this->_tr->translate('Comfort Letter template was not parsed.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

}
