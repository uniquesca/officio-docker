<?php

namespace Clients\Controller;

use Clients\Service\Clients;
use Documents\Service\Documents;
use Exception;
use Files\BufferedStream;
use Files\Model\FileInfo;
use Files\Service\Files;
use Forms\Service\Pdf;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\GstHst;
use Officio\Common\Service\Settings;
use Officio\Service\Users;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Templates\Service\Templates;

/**
 * Clients Accounting Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class AccountingController extends BaseController
{

    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_clients;

    /** @var Users */
    protected $_users;

    /** @var GstHst */
    protected $_gstHst;

    /** @var Documents */
    protected $_documents;

    /** @var Files */
    protected $_files;

    /** @var Templates */
    protected $_templates;

    /** @var Encryption */
    protected $_encryption;

    /** @var Clients\Accounting */
    protected $_accounting;

    /** @var Pdf */
    protected $_pdf;

    public function initAdditionalServices(array $services)
    {
        $this->_company    = $services[Company::class];
        $this->_clients    = $services[Clients::class];
        $this->_users      = $services[Users::class];
        $this->_gstHst     = $services[GstHst::class];
        $this->_documents  = $services[Documents::class];
        $this->_files      = $services[Files::class];
        $this->_templates  = $services[Templates::class];
        $this->_encryption = $services[Encryption::class];
        $this->_pdf        = $services[Pdf::class];
        $this->_accounting = $this->_clients->getAccounting();
    }

    public function indexAction()
    {
        $view = new ViewModel(
            ['content' => null]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');
        return $view;
    }

    public function getLegacyInvoiceTemplatesAction()
    {
        $strError      = '';
        $arrSendAsList = array();
        $arrTemplates  = array();

        try {
            $arrTemplates  = $this->_templates->getTemplatesList(true, '', 'Invoice', 'Email');
            $arrSendAsList = $this->_templates->getSendAsOptions();
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'         => empty($strError),
            'message'         => $strError,
            'send_as_options' => $arrSendAsList,
            'templates'       => $arrTemplates
        );

        return new JsonModel($arrResult);
    }

    /**
     * Add/Delete Client Accounts for specific client by member id
     *
     */
    public function manageTaAction()
    {
        $strError = '';

        try {
            $memberId = (int)Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY);
            if (!is_numeric($memberId)) {
                $strError = $this->_tr->translate('Incorrectly selected Case');
            }

            // Check if current user has access to this member
            if (empty($strError) && (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId))) {
                $strError = $this->_tr->translate('Insufficient access rights for this Case');
            }


            if (empty($strError)) {
                $newPrimaryTaId   = Json::decode($this->params()->fromPost('primary_ta_id'), Json::TYPE_ARRAY);
                $newSecondaryTaId = Json::decode($this->params()->fromPost('secondary_ta_id'), Json::TYPE_ARRAY);
                $taLabel          = $this->_company->getCurrentCompanyDefaultLabel('trust_account');

                // Primary T/A should be provided
                if (!is_numeric($newPrimaryTaId) || empty($newPrimaryTaId)) {
                    $strError = $this->_tr->translate('Incorrectly selected primary ' . $taLabel);
                }

                // Check if current user has access to this T/A
                if (empty($strError) && !$this->_clients->hasCurrentMemberAccessToTA($newPrimaryTaId)) {
                    $strError = $this->_tr->translate('Insufficient access rights for this ' . $taLabel . ' [primary]');
                }

                if (empty($strError) && !is_numeric($newSecondaryTaId)) {
                    $strError = $this->_tr->translate('Incorrectly selected secondary ' . $taLabel);
                }

                // A secondary T/A is optional
                if (empty($strError) && !empty($newSecondaryTaId) && !$this->_clients->hasCurrentMemberAccessToTA($newSecondaryTaId)) {
                    $strError = $this->_tr->translate('Insufficient access rights for this ' . $taLabel . ' [secondary]');
                }

                $oldPrimaryTAId   = 0;
                $oldSecondaryTAId = 0;
                if (empty($strError)) {
                    // Check if T/As can be changed/deleted
                    $oldPrimaryTAId   = $this->_accounting->getClientPrimaryCompanyTaId($memberId);
                    $oldSecondaryTAId = $this->_accounting->getClientSecondaryCompanyTaId($memberId);

                    if ($oldPrimaryTAId == $newPrimaryTaId && $oldSecondaryTAId == $newSecondaryTaId) {
                        $strError = $this->_tr->translate('No changes were provided.');
                    }

                    if (empty($strError) && (!empty($oldPrimaryTAId) && $oldPrimaryTAId != $newPrimaryTaId) && !$this->_accounting->canDeleteOrChangeTA($memberId, $oldPrimaryTAId)) {
                        $strError = $this->_tr->translate('It is not possible to change primary ' . $taLabel . ' because there are assigned deposits or created invoices');
                    }

                    if (empty($strError) && (!empty($oldSecondaryTAId) && $oldSecondaryTAId != $newSecondaryTaId) && !$this->_accounting->canDeleteOrChangeTA($memberId, $oldSecondaryTAId)) {
                        $strError = $this->_tr->translate('It is not possible to change secondary ' . $taLabel . ' because there are assigned deposits or created invoices');
                    }
                }


                if (empty($strError)) {
                    if ($oldPrimaryTAId != $newPrimaryTaId) {
                        $this->_accounting->changeMemberTA($memberId, $oldPrimaryTAId, $newPrimaryTaId, true);
                    }

                    if ($oldSecondaryTAId != $newSecondaryTaId) {
                        $this->_accounting->changeMemberTA($memberId, $oldSecondaryTAId, $newSecondaryTaId, false);
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
        );

        return new JsonModel($arrResult);
    }


    /***** Payment Schedule **********************************************************/
    public function getPaymentScheduleAction()
    {
        $arrPaymentInfo = array();
        $arrCategories  = array();
        try {
            $paymentScheduleId = (int)$this->params()->fromPost('payment_schedule_id');
            if ($this->_accounting->hasAccessToPaymentSchedule($paymentScheduleId)) {
                $arrPaymentInfo = $this->_accounting->getPaymentScheduleInfo($paymentScheduleId);

                if (!empty($arrPaymentInfo['based_on_date'])) {
                    $arrPaymentInfo['based_on_date'] = $this->_settings->formatDate($arrPaymentInfo['based_on_date']);
                }
            }

            $arrCategories = $this->_clients->getFields()->getClientCategories(true);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel(array('payment' => $arrPaymentInfo, 'categories' => $arrCategories));
    }

    public function savePaymentsAction()
    {
        $strError               = '';
        $savedPaymentsList      = array();
        $savedPaymentTemplateId = false;

        try {
            $filter            = new StripTags();
            $arrPayments       = Json::decode($this->params()->fromPost('arrPayments'), Json::TYPE_ARRAY);
            $booSaveTemplate   = (bool)Json::decode($this->params()->fromPost('booSaveTemplate'), Json::TYPE_ARRAY);
            $name              = $filter->filter(Json::decode($this->params()->fromPost('name'), Json::TYPE_ARRAY));
            $memberId          = (int)$this->params()->fromPost('member_id');
            $companyTAId       = (int)$this->params()->fromPost('ta_id');
            $paymentTemplateId = (int)$this->params()->fromPost('saved_payment_template_id');
            $invoiceTemplateId = (int)$this->params()->fromPost('template_id');

            if (empty($arrPayments)) {
                $strError = $this->_tr->translate('Payments can not be empty');
            }

            // Check if current user has access to this member
            if (empty($strError) && (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId))) {
                $strError = $this->_tr->translate('Insufficient access rights for this Case');
            }

            if (empty($strError) && !empty($companyTAId) && !$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this ') . $this->_company->getCurrentCompanyDefaultLabel('trust_account');
            }

            if (empty($strError) && $booSaveTemplate && empty($name)) {
                $strError = $this->_tr->translate('Template name can not be empty');
            }

            $arrCategories = $this->_clients->getFields()->getClientCategories();
            foreach ($arrPayments as $i => $arrPaymentInfo) {
                if (empty($strError) && !is_numeric($arrPaymentInfo['amount'])) {
                    $strError = $this->_tr->translate('Incorrect amount');
                }

                if (empty($strError) && empty($arrPaymentInfo['description'])) {
                    $strError = $this->_tr->translate('Please enter the description');
                }

                if (empty($strError)) {
                    switch ($arrPaymentInfo['type']) {
                        case 'date':
                            $arrPaymentInfo['due_date'] = $this->_settings->formatJsonDate($arrPaymentInfo['due_date']);
                            if ($booSaveTemplate && empty($arrPaymentInfo['due_date'])) {
                                // This is ok, a date can be empty
                            } elseif (!Settings::isValidDateFormat($arrPaymentInfo['due_date'], 'Y-m-d H:i:s') && !Settings::isValidDateFormat($arrPaymentInfo['due_date'], 'Y-m-d')) {
                                $strError = $this->_tr->translate('Please select a date');
                            }
                            break;

                        case 'profile_date':
                            $booFound = false;
                            foreach ($arrCategories as $arrCategoryInfo) {
                                if ($arrCategoryInfo['cType'] === 'profile_date' && $arrCategoryInfo['cFieldId'] == $arrPaymentInfo['due_on_id']) {
                                    $booFound = true;
                                    break;
                                }
                            }

                            if (!$booFound) {
                                $strError = $this->_tr->translate('Please select a correct profile date field');
                            }
                            break;

                        case 'file_status':
                            $booFound = false;
                            foreach ($arrCategories as $arrCategoryInfo) {
                                if ($arrCategoryInfo['cType'] === 'file_status' && $arrCategoryInfo['cOptionId'] == $arrPaymentInfo['due_on_id']) {
                                    $booFound = true;
                                    break;
                                }
                            }

                            if (!$booFound) {
                                $strError = $this->_tr->translate('Please select a correct file status');
                            }
                            break;

                        default:
                            $strError = $this->_tr->translate('Incorrectly selected Due On field');
                            break;
                    }
                }

                if (!empty($strError)) {
                    $strError .= sprintf(
                        $this->_tr->translate(' (row #%d)'),
                        $i + 1
                    );
                    break;
                }
            }

            if (empty($strError) && !empty($paymentTemplateId) && !$this->_accounting->hasAccessToPaymentTemplate($paymentTemplateId)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($strError)) {
                $this->_company->updateLastField(false, 'last_accounting_subtab_updated');

                if ($booSaveTemplate) {
                    //save template
                    $savedPaymentTemplateId = $this->_accounting->savePaymentTemplate($paymentTemplateId, $name, $arrPayments);
                } else {
                    //add payments
                    foreach ($arrPayments as $arrPaymentInfo) {
                        $arrProvinceInfo['rate']      = 0;
                        $arrProvinceInfo['tax_label'] = '';

                        $booCheckGST = true;
                        if (($arrPaymentInfo['tax_id'] == '-1') || empty($arrPaymentInfo['tax_id'])) {
                            $booCheckGST = false;
                        }

                        if ($booCheckGST) {
                            $arrProvinceInfo = $this->_gstHst->getProvinceById($arrPaymentInfo['tax_id']);

                            if (empty($arrProvinceInfo)) {
                                $strError = $this->_tr->translate('Incorrectly selected GST');
                            }
                        }

                        if (empty($strError)) {
                            $this->_accounting->savePayment(
                                'add',
                                $memberId,
                                $companyTAId,
                                false,
                                $arrPaymentInfo['amount'],
                                $filter->filter($arrPaymentInfo['description']),
                                $arrPaymentInfo['type'],
                                $arrPaymentInfo['due_on_id'],
                                $arrPaymentInfo['due_date'] ?? '',
                                $arrProvinceInfo['rate'],
                                $arrPaymentInfo['tax_id'],
                                $filter->filter($arrProvinceInfo['tax_label']),
                                $invoiceTemplateId
                            );
                        }
                    }
                }
            }

            // Get the list of saved payments
            $savedPaymentsList = $this->_accounting->getSavedPaymentsList(true);
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success'                   => empty($strError),
            'message'                   => $strError,
            'saved_payments'            => $savedPaymentsList,
            'saved_payment_template_id' => $savedPaymentTemplateId
        );
        return new JsonModel($arrResult);
    }

    public function removePaymentTemplateAction()
    {
        $strError = '';

        try {
            $savedPaymentTemplateId = (int)$this->params()->fromPost('saved_payment_template_id');
            if (!$this->_accounting->hasAccessToPaymentTemplate($savedPaymentTemplateId)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($strError)) {
                $this->_accounting->removePaymentTemplate($savedPaymentTemplateId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return new JsonModel(array('success' => empty($strError), 'message' => $strError));
    }

    public function savePaymentAction()
    {
        $strError = '';

        try {
            $filter            = new StripTags();
            $mode              = $filter->filter($this->params()->fromPost('mode'));
            $memberId          = $this->params()->fromPost('member_id');
            $companyTAId       = $this->params()->fromPost('ta_id');
            $amount            = $this->params()->fromPost('amount');
            $description       = trim($filter->filter(Json::decode($this->params()->fromPost('description', ''), Json::TYPE_ARRAY)));
            $paymentScheduleId = (int)$this->params()->fromPost('payment_schedule_id');
            $based             = $this->params()->fromPost('based_on');
            $type              = $filter->filter(Json::decode($this->params()->fromPost('type'), Json::TYPE_ARRAY));
            $date              = $filter->filter($this->_settings->formatJsonDate(Json::decode($this->params()->fromPost('based_date'), Json::TYPE_ARRAY)));
            $gstProvinceId     = (int)Json::decode($this->params()->fromPost('gst_province_id'), Json::TYPE_ARRAY);

            if (!is_numeric($memberId)) {
                $strError = $this->_tr->translate('Incorrectly selected Case');
            }

            if (empty($strError) && !in_array($type, array('date', 'profile_date', 'file_status'))) {
                $strError = $this->_tr->translate('Incorrectly selected Due On field');
            }

            if (empty($strError) && $type == 'date') {
                $date = $this->_settings->formatJsonDate($date);
                if (!Settings::isValidDateFormat($date, 'Y-m-d H:i:s')) {
                    $strError = $this->_tr->translate('Please select a date');
                }
            }

            // Check if current user has access to this member
            if (empty($strError) && (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId))) {
                $strError = $this->_tr->translate('Insufficient access rights for this Case');
            }

            if (empty($strError) && !empty($companyTAId) && !$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this ') . $this->_company->getCurrentCompanyDefaultLabel('trust_account');
            }

            if (empty($strError) && !is_numeric($amount)) {
                $strError = $this->_tr->translate('Incorrect amount');
            }

            if (empty($strError) && empty($description)) {
                $strError = $this->_tr->translate('Please enter the description');
            }

            if (empty($strError) && $mode != 'add') {
                $payment = $this->_accounting->getPaymentScheduleInfo($paymentScheduleId);

                // Check if current user can access to this payment record (by assigned client id)
                if (!is_array($payment) || !array_key_exists('member_id', $payment) || !$this->_members->hasCurrentMemberAccessToMember($payment['member_id'])) {
                    $strError = $this->_tr->translate('Insufficient access rights for this Payment Schedule');
                }
            }

            $arrProvinceInfo['rate']      = 0;
            $arrProvinceInfo['tax_label'] = '';

            if (empty($strError) && !is_numeric($gstProvinceId)) {
                $strError = $this->_tr->translate('Incorrectly selected GST');
            } else {
                $booCheckGST = true;
                if (($mode != 'add' && $gstProvinceId == '-1') || empty($gstProvinceId)) {
                    $booCheckGST = false;
                }

                if ($booCheckGST) {
                    $arrProvinceInfo = $this->_gstHst->getProvinceById($gstProvinceId);

                    if (empty($arrProvinceInfo)) {
                        $strError = $this->_tr->translate('Incorrectly selected GST');
                    }
                }
            }

            if (empty($strError)) {
                $gst         = $arrProvinceInfo['rate'];
                $gstTaxLabel = $arrProvinceInfo['tax_label'];

                $this->_accounting->savePayment(
                    $mode,
                    $memberId,
                    $companyTAId,
                    $paymentScheduleId,
                    $amount,
                    $description,
                    $type,
                    $based,
                    $date,
                    $gst,
                    $gstProvinceId,
                    $gstTaxLabel
                );
            }
        } catch (Exception $e) {
            $strError = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel(array('success' => empty($strError), 'message' => $strError));
    }

    public function deletePaymentAction()
    {
        $strMessage = '';

        try {
            // Check incoming info
            $memberId    = (int)$this->params()->fromPost('member_id');
            $companyTaId = (int)$this->params()->fromPost('ta_id');
            if (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_clients->hasCurrentMemberAccessToTA($companyTaId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
                $strMessage = $this->_tr->translate('Insufficient access rights');
            }

            $paymentScheduleId = (int)$this->params()->fromPost('payment_id');
            if (empty($strMessage) && !$this->_accounting->hasAccessToPaymentSchedule($paymentScheduleId)) {
                $strMessage = $this->_tr->translate('Insufficient access rights for this Payment Schedule');
            }

            if (empty($strMessage)) {
                $this->_db2->delete(
                    'u_payment_schedule',
                    [
                        'payment_schedule_id' => $paymentScheduleId,
                        'status'              => 0
                    ]
                );
            }
        } catch (Exception $e) {
            $strMessage = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel(array('success' => empty($strMessage), 'message' => $strMessage));
    }

    public function addWizardAction()
    {
        $view = new JsonModel();

        $strError = '';
        try {
            if (!$this->getRequest()->isXmlHttpRequest()) {
                exit;
            }

            $filter      = new StripTags();
            $amount      = $this->params()->fromPost('amount');
            $description = trim($filter->filter(Json::decode($this->params()->fromPost('description', ''), Json::TYPE_ARRAY)));
            $payments    = $this->params()->fromPost('payments');
            $start       = Json::decode($this->params()->fromPost('start', ''), Json::TYPE_ARRAY);
            list($start,) = explode(" ", $this->_settings->formatJsonDate($start)); // We need only date, without hours and seconds section
            $period        = $this->params()->fromPost('period');
            $memberId      = $this->params()->fromPost('member_id');
            $companyTAId   = $this->params()->fromPost('ta_id');
            $gstProvinceId = (int)Json::decode($this->params()->fromPost('gst_province_id'), Json::TYPE_ARRAY);

            if (!is_numeric($memberId)) {
                $strError = $this->_tr->translate('Incorrectly selected Case');
            }

            // Check if current user has access to this member
            if (empty($strError) && (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId))) {
                $strError = $this->_tr->translate('Insufficient access rights for this Case');
            }

            if (empty($strError) && !empty($companyTAId) && !$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this ') . $this->_company->getCurrentCompanyDefaultLabel('trust_account');
            }

            if (empty($strError) && !is_numeric($amount)) {
                $strError = $this->_tr->translate('Incorrect amount');
            }

            if (empty($strError) && empty($description)) {
                $strError = $this->_tr->translate('Please enter the description');
            }

            $gst         = 0;
            $gstTaxLabel = '';
            if (empty($strError) && !is_numeric($gstProvinceId)) {
                $strError = $this->_tr->translate('Incorrectly selected GST');
            } elseif (!empty($gstProvinceId)) {
                $arrProvinceInfo = $this->_gstHst->getProvinceById($gstProvinceId);

                if (empty($arrProvinceInfo)) {
                    $strError = $this->_tr->translate('Incorrectly selected GST');
                } else {
                    $gst         = $arrProvinceInfo['rate'];
                    $gstTaxLabel = $arrProvinceInfo['tax_label'];
                }
            }

            if (empty($strError)) {
                $this->_accounting->addWizard($amount, $description, $payments, $start, $period, $memberId, $companyTAId, $gst, $gstProvinceId, $gstTaxLabel);
                $this->_company->updateLastField(false, 'last_accounting_subtab_updated');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $view->setVariables(array('success' => empty($strError), 'message' => $strError));
    }

    public function previewRecurringPaymentPlanAction()
    {
        $strError    = '';
        $arrPayments = array();

        try {
            $filter = new StripTags();

            $amount        = $this->params()->fromPost('amount');
            $description   = trim($filter->filter(Json::decode($this->params()->fromPost('description', ''), Json::TYPE_ARRAY)));
            $paymentsCount = $this->params()->fromPost('payments');
            $start         = Json::decode($this->params()->fromPost('start', ''), Json::TYPE_ARRAY);
            list($start,) = explode(" ", $this->_settings->formatJsonDate($start)); // We need only date, without hours and seconds section
            $period        = $this->params()->fromPost('period');
            $gstProvinceId = (int)Json::decode($this->params()->fromPost('gst_province_id'), Json::TYPE_ARRAY);

            if (empty($strError) && !is_numeric($amount)) {
                $strError = $this->_tr->translate('Incorrect amount');
            }

            if (empty($strError) && empty($description)) {
                $strError = $this->_tr->translate('Please enter the description');
            }

            $tax     = 0;
            $taxType = 0;
            if (empty($strError) && !is_numeric($gstProvinceId)) {
                $strError = $this->_tr->translate('Incorrectly selected GST');
            } elseif (!empty($gstProvinceId)) {
                $arrProvinceInfo = $this->_gstHst->getProvinceById($gstProvinceId);

                if (empty($arrProvinceInfo)) {
                    $strError = $this->_tr->translate('Incorrectly selected GST');
                } else {
                    $tax     = $arrProvinceInfo['rate'];
                    $taxType = $arrProvinceInfo['tax_type'];
                }
            }

            $dateFormatFull = $this->_settings->variableGet('dateFormatFull');
            for ($i = 0; $i < $paymentsCount; $i++) {
                $basedDate = $this->_accounting->calculatePaymentRecurringDate($start, $period, $i);
                if (empty($basedDate)) {
                    continue;
                }

                $arrGstInfo = $this->_gstHst->calculateGstAndSubtotal($taxType, $tax, $amount);

                $arrPayments[] = array(
                    'due_date'    => $this->_settings->reformatDate($basedDate, Settings::DATE_UNIX, $dateFormatFull),
                    'description' => $description . ' #' . ($i + 1),
                    'subtotal'    => $arrGstInfo['subtotal'],
                    'gst'         => $arrGstInfo['gst']
                );
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel(array('success' => empty($strError), 'message' => $strError, 'arrPayments' => $arrPayments));
    }


    /***** Fees Due ********************************************************************/
    public function addFeeAction()
    {
        $view = new JsonModel();

        $booSuccess = false;
        $strError   = '';
        $paymentId  = '';

        try {
            if (!$this->getRequest()->isXmlHttpRequest()) {
                exit();
            }

            $filter = new StripTags();

            // Check all incoming params
            $companyTAId = (int)Json::decode($this->findParam('ta_id'), Json::TYPE_ARRAY);
            $taLabel     = $this->_company->getCurrentCompanyDefaultLabel('trust_account');

            if (empty($strError) && empty($companyTAId)) {
                $strError = $this->_tr->translate('Incorrectly selected ' . $taLabel);
            }

            if (empty($strError) && !$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this ' . $taLabel);
            }

            $memberId = (int)Json::decode($this->findParam('member_id'), Json::TYPE_ARRAY);
            if (empty($strError) && (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId))) {
                $strError = $this->_tr->translate('Insufficient access rights for this Case');
            }

            $amount = Json::decode($this->findParam('amount'), Json::TYPE_ARRAY);
            if (empty($strError) && !is_numeric($amount)) {
                $strError = $this->_tr->translate('Incorrect amount');
            }

            $description = trim($filter->filter(Json::decode($this->findParam('description', ''), Json::TYPE_ARRAY)));
            if (empty($strError) && empty($description)) {
                $strError = $this->_tr->translate('Description can not be empty');
            }

            $type = Json::decode($this->findParam('type'), Json::TYPE_ARRAY);
            if (empty($strError) && !in_array($type, array('add-fee-due', 'add-fee-received'))) {
                $strError = $this->_tr->translate('Incorrect action');
            }

            $gst           = 0;
            $gstProvinceId = 0;
            $gstTaxLabel   = '';
            if (empty($strError) && $type == 'add-fee-due') {
                $gstProvinceId = (int)Json::decode($this->findParam('gst_province_id'), Json::TYPE_ARRAY);


                if (!is_numeric($gstProvinceId)) {
                    $strError = $this->_tr->translate('Incorrectly selected GST');
                } elseif (!empty($gstProvinceId)) {
                    $arrProvinceInfo = $this->_gstHst->getProvinceById($gstProvinceId);

                    if (empty($arrProvinceInfo)) {
                        $strError = $this->_tr->translate('Incorrectly selected GST');
                    } else {
                        $gst         = $arrProvinceInfo['rate'];
                        $gstTaxLabel = $arrProvinceInfo['tax_label'];
                    }
                }
            }


            $date            = Json::decode($this->findParam('date', ''), Json::TYPE_ARRAY);
            $date            = str_replace('00:00:00', date('H:i:s'), $date);
            $payment_made_by = trim($filter->filter(Json::decode($this->findParam('payment_made_by', ''), Json::TYPE_ARRAY)));
            $notes           = trim($filter->filter(Json::decode($this->findParam('notes', ''), Json::TYPE_ARRAY)));

            if (empty($strError)) {
                $paymentId = $this->_accounting->addFee(
                    $companyTAId,
                    $memberId,
                    $amount,
                    $description,
                    $type,
                    $date,
                    $payment_made_by,
                    $gst,
                    $gstProvinceId,
                    $gstTaxLabel,
                    $notes,
                    $this->_auth->getCurrentUserId()
                );

                if (!empty($paymentId)) {
                    $this->_company->updateLastField(false, 'last_accounting_subtab_updated');

                    $booSuccess = true;
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $view->setVariables(array("success" => $booSuccess, "message" => $strError, "payment_id" => $paymentId));
    }

    public function updateFeeAction()
    {
        $view = new JsonModel();

        $strError = '';

        try {
            if (!$this->getRequest()->isXmlHttpRequest()) {
                exit();
            }

            $filter = new StripTags();

            // Check all incoming params
            $companyTAId = (int)Json::decode($this->params()->fromPost('ta_id'), Json::TYPE_ARRAY);
            $taLabel     = $this->_company->getCurrentCompanyDefaultLabel('trust_account');

            if (empty($strError) && empty($companyTAId)) {
                $strError = $this->_tr->translate('Incorrectly selected ' . $taLabel);
            }

            if (empty($strError) && !$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this ' . $taLabel);
            }

            $memberId = (int)Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY);
            if (empty($strError) && (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId))) {
                $strError = $this->_tr->translate('Insufficient access rights for this Case');
            }

            $paymentId = (int)Json::decode($this->params()->fromPost('payment_id'), Json::TYPE_ARRAY);
            if (empty($strError)) {
                $arrPaymentInfo = $this->_accounting->getPaymentInfo($paymentId);
                if (empty($arrPaymentInfo)) {
                    $strError = $this->_tr->translate('Insufficient access rights for this Fee');
                } elseif (!empty($arrPaymentInfo['invoice_id'])) {
                    $strError = $this->_tr->translate('This Fee cannot be updated because it was already invoiced');
                }
            }

            $amount = Json::decode($this->params()->fromPost('amount'), Json::TYPE_ARRAY);
            if (empty($strError) && !is_numeric($amount)) {
                $strError = $this->_tr->translate('Incorrect amount');
            }

            $description = trim($filter->filter(Json::decode($this->params()->fromPost('description', ''), Json::TYPE_ARRAY)));
            if (empty($strError) && empty($description)) {
                $strError = $this->_tr->translate('Description can not be empty');
            }


            $gst           = 0;
            $gstTaxLabel   = '';
            $gstProvinceId = (int)Json::decode($this->params()->fromPost('gst_province_id'), Json::TYPE_ARRAY);
            if (empty($strError)) {
                if (!is_numeric($gstProvinceId)) {
                    $strError = $this->_tr->translate('Incorrectly selected GST');
                } elseif (!empty($gstProvinceId)) {
                    $arrProvinceInfo = $this->_gstHst->getProvinceById($gstProvinceId);

                    if (empty($arrProvinceInfo)) {
                        $strError = $this->_tr->translate('Incorrectly selected GST');
                    } else {
                        $gst         = $arrProvinceInfo['rate'];
                        $gstTaxLabel = $arrProvinceInfo['tax_label'];
                    }
                }
            }

            $date = Json::decode($this->params()->fromPost('date'), Json::TYPE_ARRAY);

            if (empty($strError)) {
                $booSuccess = $this->_accounting->updateFee(
                    $paymentId,
                    $companyTAId,
                    $memberId,
                    $amount,
                    $description,
                    $date,
                    $gst,
                    $gstProvinceId,
                    $gstTaxLabel
                );

                if ($booSuccess) {
                    $this->_accounting->updateTrustAccountSubTotal($memberId, $companyTAId);
                    $this->_company->updateLastField(false, 'last_accounting_subtab_updated');
                } else {
                    $strError = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array("success" => empty($strError), "message" => $strError));
    }

    public function deleteFeeAction()
    {
        $view = new JsonModel();

        $strError = '';

        try {
            if (!$this->getRequest()->isXmlHttpRequest()) {
                exit();
            }

            // Check all incoming params
            $companyTAId = (int)Json::decode($this->params()->fromPost('ta_id'), Json::TYPE_ARRAY);
            $taLabel     = $this->_company->getCurrentCompanyDefaultLabel('trust_account');

            if (empty($strError) && empty($companyTAId)) {
                $strError = $this->_tr->translate('Incorrectly selected ' . $taLabel);
            }

            if (empty($strError) && !$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this ' . $taLabel);
            }

            $memberId = (int)Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY);
            if (empty($strError) && (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId))) {
                $strError = $this->_tr->translate('Insufficient access rights for this Case');
            }

            $paymentId = (int)Json::decode($this->params()->fromPost('payment_id'), Json::TYPE_ARRAY);
            if (empty($strError)) {
                $arrPaymentInfo = $this->_accounting->getPaymentInfo($paymentId);
                if (empty($arrPaymentInfo)) {
                    $strError = $this->_tr->translate('Insufficient access rights for this Fee');
                } elseif (!empty($arrPaymentInfo['invoice_id'])) {
                    $strError = $this->_tr->translate('This Fee cannot be deleted because it was already invoiced');
                }
            }

            if (empty($strError)) {
                $booSuccess = $this->_accounting->deletePayment($paymentId);

                if ($booSuccess) {
                    // update member's outstanding balance
                    $this->_accounting->updateOutstandingBalance($memberId, $companyTAId);
                    // update member's subtotals
                    $this->_accounting->updateTrustAccountSubTotal($memberId, $companyTAId);

                    $this->_company->updateLastField(false, 'last_accounting_subtab_updated');
                } else {
                    $strError = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array("success" => empty($strError), "message" => $strError));
    }

    public function getFeeDetailsAction()
    {
        $view = new JsonModel();

        $invoiceNum          = false;
        $arrTemplates        = array();
        $arrSendAsOptions    = array();
        $arrFeesDescriptions = array();
        try {
            if (!$this->getRequest()->isXmlHttpRequest()) {
                exit();
            }
            $booFeeDue   = Json::decode($this->findParam('booFeeDue'), Json::TYPE_ARRAY);
            $companyTAId = (int)$this->findParam('ta_id');
            $type        = Json::decode($this->findParam('type'), Json::TYPE_ARRAY);

            if (!$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                exit(Json::encode(array()));
            }

            if (!in_array($type, array('receipt', 'invoice'))) {
                exit(Json::encode(array()));
            }

            // Get descriptions created by current user
            $arrFeesDescriptions = $this->_accounting->getPaymentDescriptions($this->_auth->getCurrentUserId());
            foreach ($arrFeesDescriptions as $key => $val) {
                $arrFeesDescriptions[$key] = array('optionId' => $key, 'optionName' => $val);
            }

            // Get templates
            if ($booFeeDue) {
                $templateType = 3;
                $templateFor  = 'Request';
            } else {
                $templateType = 2;
                $templateFor  = 'Invoice';
            }
            $arrTemplates = $this->_templates->getTemplatesList(false, $templateType, $templateFor, 'Email');

            // Get invoice number
            if (!$booFeeDue) {
                $invoiceNum = $this->_accounting->getMaxInvoiceNumber($companyTAId);
            }

            // Get send as options
            $arrSendAsOptions = $this->_templates->getSendAsOptions();
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'invoice_number'  => $invoiceNum,
            'templates'       => $arrTemplates,
            'send_as_options' => $arrSendAsOptions,
            'descriptions'    => $arrFeesDescriptions
        );
        return $view->setVariables($arrResult);
    }

    public function getMarkAsPaidDetailsAction()
    {
        $strError       = '';
        $arrInvoices    = array();
        $arrAmountLimit = array();

        try {
            $companyTAId = Json::decode($this->params()->fromPost('ta_id'), Json::TYPE_ARRAY);
            if (empty($strError) && !$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this ' . $this->_company->getCurrentCompanyDefaultLabel('trust_account'));
            }

            $memberId = Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY);
            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this Client.');
            }

            if (empty($strError)) {
                // Unpaid invoices
                $arrInvoices = $this->_accounting->getUnpaidInvoices($memberId);

                if (empty($arrInvoices)) {
                    $strError = $this->_tr->translate('There are no unpaid invoices.<br>Please generate an invoice for the fees due first.');
                } else {
                    // If we want to process only one specific invoice (clicked on the Pay Now link) -
                    // return only this one invoice only
                    $invoiceId = Json::decode($this->params()->fromPost('invoice_id'), Json::TYPE_ARRAY);
                    if (!empty($invoiceId)) {
                        $booValidInvoice = false;
                        foreach ($arrInvoices as $arrInvoiceInfo) {
                            if ($arrInvoiceInfo['invoice_id'] == $invoiceId) {
                                $booValidInvoice = true;
                                $arrInvoices     = array($arrInvoiceInfo);
                                break;
                            }
                        }

                        if (!$booValidInvoice) {
                            $strError = $this->_tr->translate('Selected invoice is not unpaid.');
                        }
                    }
                }
            }

            if (empty($strError)) {
                // Get T/A details
                $arrMemberTA = $this->_accounting->getMemberCompanyTA($memberId, true);
                if (is_array($arrMemberTA) && count($arrMemberTA) > 0) {
                    foreach ($arrMemberTA as $memberTAId) {
                        $arrAmountLimit[] = array(
                            'ta_id'      => $memberTAId,
                            'ta_balance' => $this->_accounting->getTrustAccountSubTotalCleared($memberId, $memberTAId, false)
                        );
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'      => empty($strError),
            'message'      => $strError,
            'invoices'     => $arrInvoices,
            'amount_limit' => $arrAmountLimit,
        );

        return new JsonModel($arrResult);
    }

    public function markAsPaidAction()
    {
        $strError = '';

        try {
            $filter = new StripTags();

            $companyTAId = $this->params()->fromPost('invoice_payment_ta_id');
            if (empty($strError) && !$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this ' . $this->_company->getCurrentCompanyDefaultLabel('trust_account'));
            }

            $companyPaymentTAIdFrom      = $this->params()->fromPost('invoice_payment_transfer_from');
            $companyPaymentTAIdFromOther = trim($this->params()->fromPost('invoice_payment_transfer_from_other', ''));
            if ($companyPaymentTAIdFrom === 'other' || $companyPaymentTAIdFrom === 'special_adjustment') {
                $companyPaymentTAIdFrom = 0;
                if (!strlen($companyPaymentTAIdFromOther)) {
                    $strError = $this->_tr->translate('Please enter the description of the Other ') . $this->_company->getCurrentCompanyDefaultLabel('trust_account');
                }
            } elseif (empty($strError) && !$this->_clients->hasCurrentMemberAccessToTA($companyPaymentTAIdFrom)) {
                $strError = $this->_tr->translate('Insufficient access rights for selected ' . $this->_company->getCurrentCompanyDefaultLabel('trust_account'));
            }


            $memberId = $this->params()->fromPost('invoice_payment_member_id');
            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this Client.');
            }

            $paymentDate = $this->params()->fromPost('invoice_payment_date');
            if (empty($strError)) {
                $dateFormatFull = $this->_settings->variableGet('dateFormatFull');
                if (!Settings::isValidDateFormat($paymentDate, $dateFormatFull)) {
                    $strError = $this->_tr->translate('Incorrectly selected date.');
                } else {
                    $paymentDate = $this->_settings->reformatDate($paymentDate, $dateFormatFull, 'Y-m-d');
                }
            }

            $paymentCheque = $filter->filter(trim($this->params()->fromPost('invoice_payment_cheque_num', '')));

            $arrInvoicePayments          = array();
            $arrInvoiceEquivalentAmounts = array();
            if (empty($strError)) {
                $arrParams = $this->params()->fromPost();
                foreach ($arrParams as $key => $val) {
                    if (preg_match('/^invoice_payment_amount_(\d+)$/', $key, $regs) && !empty($val)) {
                        $arrInvoicePayments[$regs[1]] = $val;
                    } elseif (preg_match('/^invoice_payment_amount_equivalent_(\d+)$/', $key, $regs) && !empty($val)) {
                        $arrInvoiceEquivalentAmounts[$regs[1]] = $val;
                    }
                }

                if (empty($arrInvoicePayments)) {
                    $strError = $this->_tr->translate('Please enter at least one amount for any invoice.');
                } else {
                    // Unpaid invoices
                    $arrInvoices = $this->_accounting->getUnpaidInvoices($memberId);

                    if (empty($arrInvoices)) {
                        $strError = $this->_tr->translate('There are no unpaid invoices.');
                    } else {
                        $companyTAAvailableBalance = 0;
                        if (!empty($companyPaymentTAIdFrom)) {
                            $companyTAAvailableBalance = $this->_accounting->getTrustAccountSubTotalCleared($memberId, $companyPaymentTAIdFrom, false);
                        }

                        foreach ($arrInvoicePayments as $invoiceId => $amount) {
                            $booValidInvoice = false;
                            foreach ($arrInvoices as $arrInvoiceInfo) {
                                if (empty($companyPaymentTAIdFrom)) {
                                    $maxAmountAllowed = $arrInvoiceInfo['invoice_amount_due'];
                                } else {
                                    $maxAmountAllowed = min($companyTAAvailableBalance, $arrInvoiceInfo['invoice_amount_due']);
                                }
                                if ($arrInvoiceInfo['invoice_id'] == $invoiceId && $this->_settings->floatCompare($amount, $maxAmountAllowed, '<=', 2)) {
                                    $booValidInvoice = true;
                                    break;
                                }
                            }

                            if (!$booValidInvoice) {
                                $strError = $this->_tr->translate('Incorrectly selected invoice or entered amount.');
                                break;
                            }
                        }
                    }
                }
            }

            if (empty($strError)) {
                foreach ($arrInvoicePayments as $invoiceId => $paymentAmount) {
                    $transferFromCompanyId = 0;
                    $transferFromAmount    = 0;
                    if (!empty($companyPaymentTAIdFrom)) {
                        $arrInvoiceInfo = $this->_accounting->getInvoiceInfo($invoiceId);
                        if ($arrInvoiceInfo['company_ta_id'] != $companyPaymentTAIdFrom) {
                            if (isset($arrInvoiceEquivalentAmounts[$invoiceId])) {
                                $transferFromCompanyId = $companyPaymentTAIdFrom;
                                $transferFromAmount    = $arrInvoiceEquivalentAmounts[$invoiceId];
                            }

                            $companyPaymentTAIdFrom = $arrInvoiceInfo['company_ta_id'];
                        }
                    }

                    $this->_accounting->createInvoicePayments(array(
                        'invoice_id'                 => $invoiceId,
                        'company_ta_id'              => empty($companyPaymentTAIdFrom) ? null : $companyPaymentTAIdFrom,
                        'company_ta_other'           => empty($companyPaymentTAIdFrom) ? $companyPaymentTAIdFromOther : null,
                        'invoice_payment_amount'     => $paymentAmount,
                        'invoice_payment_date'       => $paymentDate,
                        'invoice_payment_cheque_num' => strlen($paymentCheque) ? $paymentCheque : null,

                        'transfer_from_company_ta_id' => empty($transferFromCompanyId) ? null : $transferFromCompanyId,
                        'transfer_from_amount'        => empty($transferFromAmount) ? null : $transferFromAmount,
                    ));

                    if (!empty($transferFromCompanyId)) {
                        $this->_accounting->updateTrustAccountSubTotal($memberId, $transferFromCompanyId);
                    }
                }

                if (!empty($companyPaymentTAIdFrom)) {
                    $this->_accounting->updateTrustAccountSubTotal($memberId, $companyPaymentTAIdFrom);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }

    public function getFtDetailsAction()
    {
        $view = new JsonModel();

        if (!$this->getRequest()->isXmlHttpRequest()) {
            exit;
        }
        $payment_id   = (int)$this->findParam('payment_id');
        $payment_info = $this->_accounting->getPaymentInfo($payment_id);

        return $view->setVariables($payment_info);
    }


    /**
     * Reverse transaction (remove incorrectly created records)
     * @return JsonModel
     */
    public function reverseTransactionAction()
    {
        $strError     = '';
        $booRefreshTA = $booRefreshPS = false;

        $arrPayments = Json::decode($this->params()->fromPost('payments'), Json::TYPE_ARRAY);
        $memberId    = (int)Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY);

        if (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
            $strError = $this->_tr->translate('Insufficient access rights');
        }

        foreach ($arrPayments as $payment) {
            $paymentId   = $payment['id'];
            $paymentType = $payment['type'];

            if (empty($strError) && !is_numeric($paymentId)) {
                $strError = $this->_tr->translate('Incorrectly selected transaction');
            }

            $arrPaymentInfo = array();
            if (empty($strError)) {
                switch ($paymentType) {
                    case 'payment':
                        $arrPaymentInfo = $this->_accounting->getPaymentInfo($paymentId);
                        $booHasAccess   = !empty($arrPaymentInfo) && $this->_accounting->canCurrentMemberAccessPayment($arrPaymentInfo);

                        if ($booHasAccess && !empty($arrPaymentInfo['invoice_id'])) {
                            $strError = $this->_tr->translate('This fee is assigned to an invoice.<br>You cannot delete the fee unless you delete the invoice or un-assign this fee from the invoice.');
                        }
                        break;

                    case 'receipt':
                    case 'invoice':
                        $arrPaymentInfo = $this->_accounting->getInvoiceInfo($paymentId);
                        $booHasAccess   = !empty($arrPaymentInfo) && $this->_accounting->canCurrentMemberAccessPayment($arrPaymentInfo);
                        break;

                    case 'withdrawal':
                        $arrPaymentInfo = $this->_accounting->getWithdrawalInfo($paymentId);
                        $booHasAccess   = !empty($arrPaymentInfo) && $this->_accounting->canCurrentMemberAccessPayment($arrPaymentInfo);
                        break;

                    case 'ps':
                        $arrPaymentInfo = $this->_accounting->getPaymentScheduleInfo($paymentId);
                        $booHasAccess   = !empty($arrPaymentInfo) && $this->_accounting->hasAccessToPaymentSchedule($paymentId);
                        break;

                    default:
                        $booHasAccess = false;
                        break;
                }

                if (!$booHasAccess) {
                    $strError = $this->_tr->translate('Insufficient access rights for this transaction');
                }
            }

            if (empty($strError)) {
                // If all is okay - reverse the transaction
                $arrReverseResult = $this->_accounting->reverseTransaction($arrPaymentInfo, $paymentType);

                $strError     = $arrReverseResult['message'];
                $booRefreshTA = $arrReverseResult['booRefreshTA'];
                $booRefreshPS = $arrReverseResult['booRefreshPS'];
            }

            if (!empty($strError)) {
                break;
            }
        }

        $arrResult = array(
            'success'      => empty($strError),
            'message'      => $strError,
            'booRefreshTA' => $booRefreshTA,
            'booRefreshPS' => $booRefreshPS
        );

        return new JsonModel($arrResult);
    }

    public function getNewInvoiceDetailsAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            exit;
        }

        $strError  = '';
        $arrResult = [];

        try {
            $companyTAId     = $this->params()->fromPost('ta_id');
            $memberId        = $this->params()->fromPost('member_id');
            $arrPSRecordsIds = Json::decode($this->params()->fromPost('ps_records'), Json::TYPE_ARRAY);
            $arrFeesIds      = Json::decode($this->params()->fromPost('fees'), Json::TYPE_ARRAY);

            if (empty($strError) && !$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this ' . $this->_company->getCurrentCompanyDefaultLabel('trust_account'));
            }

            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this Case');
            }

            if (empty($strError)) {
                $arrResult = $this->_accounting->getNewInvoiceDetails($companyTAId, $memberId, $arrPSRecordsIds, $arrFeesIds);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $arrResult = array(
                'success' => empty($strError),
                'message' => $strError
            );
        }

        return new JsonModel($arrResult);
    }

    public function getNewInvoiceFromTemplateAction()
    {
        $strError = '';

        try {
            $memberId    = Json::decode($this->params()->fromQuery('member_id'));
            $companyTAId = Json::decode($this->params()->fromQuery('ta_id'));
            $templateId  = Json::decode($this->params()->fromQuery('template_id'));
            $invoiceId   = Json::decode($this->params()->fromQuery('invoice_id'));
            $arrFeesIds  = Json::decode($this->params()->fromQuery('fees'), Json::TYPE_ARRAY);

            if (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($strError) && !empty($invoiceId) && !$this->_accounting->hasAccessToInvoice($invoiceId)) {
                $strError = $this->_tr->translate('Insufficient access rights to the invoice');
            }

            if (empty($strError)) {
                $arrInvoiceTemplatesIds = $this->_accounting->getInvoiceTemplates(true);

                if (empty($arrInvoiceTemplatesIds)) {
                    $strError = $this->_tr->translate('There are no invoice templates');
                }

                if (empty($strError) && !in_array($templateId, $arrInvoiceTemplatesIds)) {
                    // Use the first template if it was not provided or is incorrect
                    $templateId = $arrInvoiceTemplatesIds[0];
                }
            }

            if (empty($strError)) {
                list($strError, $strHtmlTemplate) = $this->_accounting->getInvoiceRenderedTemplate($memberId, $companyTAId, $invoiceId, $templateId, $arrFeesIds, false);
                if (empty($strError)) {
                    $strError = $strHtmlTemplate;
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setVariable('content', $strError);
        $view->setTemplate('layout/plain');

        return $view;
    }

    public function getNewInvoiceTemplatesAction()
    {
        $arrRecords = [];
        try {
            $arrRecords = $this->_accounting->getInvoiceTemplates();
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrRecords = array(
            'items' => $arrRecords,
            'count' => count($arrRecords),
        );

        return new JsonModel($arrRecords);
    }

    public function saveInvoiceAction()
    {
        $strError  = '';
        $invoiceId = '';

        try {
            $filter = new StripTags();

            $arrInvoiceInfo              = array();
            $arrInvoiceInfo['member_id'] = Json::decode($this->params()->fromPost('member_id'));
            if (!is_numeric($arrInvoiceInfo['member_id'])) {
                $strError = $this->_tr->translate('Incorrectly selected Case');
            }

            // Check if current user has access to this member
            if (empty($strError) && (!$this->_members->hasCurrentMemberAccessToMember($arrInvoiceInfo['member_id']) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($arrInvoiceInfo['member_id']))) {
                $strError = $this->_tr->translate('Insufficient access rights for this Case');
            }

            $taLabel = $this->_company->getCurrentCompanyDefaultLabel('trust_account');
            if (empty($strError)) {
                $arrInvoiceInfo['transfer_to_ta_id'] = Json::decode($this->params()->fromPost('transfer_to_ta_id'));
                if (!is_numeric($arrInvoiceInfo['transfer_to_ta_id']) || empty($arrInvoiceInfo['transfer_to_ta_id'])) {
                    $strError = $this->_tr->translate('Incorrectly selected ' . $taLabel);
                }

                // Check if current user has access to this T/A
                if (empty($strError) && !$this->_clients->hasCurrentMemberAccessToTA($arrInvoiceInfo['transfer_to_ta_id'])) {
                    $strError = $this->_tr->translate('Insufficient access rights for this ' . $taLabel);
                }
            }

            if (empty($strError)) {
                $templateId             = Json::decode($this->params()->fromPost('template_id'));
                $arrInvoiceTemplatesIds = $this->_accounting->getInvoiceTemplates(true);
                if (!in_array($templateId, $arrInvoiceTemplatesIds)) {
                    $strError = $this->_tr->translate('Incorrectly selected template');
                }
            }

            $arrInvoiceInfo['date'] = $this->_settings->formatJsonDate(Json::decode($this->params()->fromPost('date'), Json::TYPE_ARRAY));
            $timestamp              = strtotime($arrInvoiceInfo['date']);
            $dateFormatFull         = $this->_settings->variableGet('dateFormatFull');
            if (empty($strError) && ($timestamp === false)) {
                $strError = $this->_tr->translate('Incorrect date');
            } else {
                $arrInvoiceInfo['date'] = date('Y-m-d', $timestamp);
                $arrInvoiceInfo['date_formatted'] = $this->_settings->reformatDate($arrInvoiceInfo['date'], Settings::DATE_UNIX, $dateFormatFull);
            }

            // Check other incoming params
            $arrInvoiceInfo['invoice_num']             = trim($filter->filter(Json::decode($this->params()->fromPost('invoice_number', ''))));
            $arrInvoiceInfo['arrPayments']             = Json::decode($this->params()->fromPost('fees'), Json::TYPE_ARRAY);
            $arrInvoiceInfo['invoice_recipient_notes'] = trim($filter->filter(Json::decode($this->params()->fromPost('invoice_recipient_notes', ''))));
            $arrInvoiceInfo['description']             = '';

            // For invoice - load/calculate fees + taxes + total from the payments
            if (empty($strError)) {
                if (empty($arrInvoiceInfo['arrPayments'])) {
                    $strError = $this->_tr->translate('Incorrectly selected payments');
                } else {
                    $fee = 0;
                    $tax = 0;
                    foreach ($arrInvoiceInfo['arrPayments'] as $paymentId) {
                        $arrPaymentInfo = $this->_accounting->getPaymentInfo($paymentId);
                        if (empty($arrPaymentInfo)) {
                            $strError = $this->_tr->translate('Insufficient access rights to the payment');
                            break;
                        }

                        $fee += $arrPaymentInfo['withdrawal'];
                        $tax += $arrPaymentInfo['due_gst'];
                    }

                    $arrInvoiceInfo['amount'] = $fee + $tax;
                    $arrInvoiceInfo['fee']    = $fee;
                    $arrInvoiceInfo['tax']    = $tax;
                }
            }

            if (empty($strError) && empty($arrInvoiceInfo['amount'])) {
                $strError = $this->_tr->translate('Incorrect amount');
            }

            if (empty($strError)) {
                $invoiceId = $this->_accounting->saveInvoice($arrInvoiceInfo);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'    => empty($strError),
            'message'    => $strError,
            'invoice_id' => $invoiceId,
        );

        return new JsonModel($arrResult);
    }

    public function openInvoiceDocumentAction()
    {
        $strError  = '';
        $arrResult = [];

        try {
            $fileId = Json::decode($this->params()->fromPost('file_id'), Json::TYPE_ARRAY);
            if (empty($fileId)) {
                $strError = $this->_tr->translate('Incorrect incoming info');
            }

            if (empty($strError)) {
                $fileId   = $this->_encryption->decode($fileId);
                $memberId = (int)$this->params()->fromPost('member_id');

                $arrResult = $this->_documents->preview($fileId, $memberId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (empty($arrResult)) {
            $arrResult = [
                'success'   => empty($strError),
                'message'   => $strError,
                'type'      => '',
                'filename'  => '',
                'file_path' => '',
                'content'   => '',
            ];
        }

        return new JsonModel($arrResult);
    }

    public function updateDepositAction()
    {
        $view = new JsonModel();

        $strMessage = '';

        try {
            $filter = new StripTags();

            // Check incoming info
            $memberId    = (int)$this->findParam('member_id');
            $companyTAId = (int)$this->findParam('ta_id');
            if (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_clients->hasCurrentMemberAccessToTA($companyTAId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
                $strMessage = $this->_tr->translate('Insufficient access rights');
            }

            $depositId = (int)$this->findParam('deposit_id');
            if (empty($strMessage) && !empty($depositId) && !$this->_accounting->hasAccessToAssignedDeposit($depositId)) {
                $strMessage = $this->_tr->translate('Incorrect incoming data');
            }

            $amount = Json::decode($this->findParam('deposit_amount'), Json::TYPE_ARRAY);
            if (empty($strMessage) && (!is_numeric($amount) || $amount <= 0)) {
                $strMessage = $this->_tr->translate('Incorrect amount');
            }

            $notes   = trim($filter->filter(Json::decode($this->findParam('deposit_notes', ''), Json::TYPE_ARRAY)));
            $details = trim($filter->filter(Json::decode($this->findParam('deposit_details', ''), Json::TYPE_ARRAY)));

            if (empty($strMessage)) {
                $this->_company->updateLastField(false, 'last_accounting_subtab_updated');

                $arrUpdateInfo = array(
                    "deposit"     => (double)$amount,
                    "description" => $details,
                    "notes"       => $notes
                );

                if (empty($depositId)) {
                    // Create new deposit
                    $arrUpdateInfo['author_id']     = $this->_auth->getCurrentUserId();
                    $arrUpdateInfo['company_ta_id'] = $companyTAId;
                    $arrUpdateInfo['member_id']     = $memberId;
                    $arrUpdateInfo['date_of_event'] = date('Y-m-d H:i:s');
                    $this->_db2->insert('u_assigned_deposits', $arrUpdateInfo);
                } else {
                    // Update already created deposit
                    $this->_db2->update(
                        'u_assigned_deposits',
                        $arrUpdateInfo,
                        [
                            'deposit_id' => $depositId,
                            'member_id'  => $memberId
                        ]
                    );
                }

                //update sub total
                $this->_accounting->updateTrustAccountSubTotal($memberId, $companyTAId);
            }
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $view->setVariables(array('success' => empty($strMessage), 'message' => $strMessage));
    }

    public function deleteDepositAction()
    {
        $view = new JsonModel();

        $strMessage = '';

        try {
            // Check incoming info
            $memberId    = (int)$this->findParam('member_id');
            $companyTAId = (int)$this->findParam('ta_id');
            if (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_clients->hasCurrentMemberAccessToTA($companyTAId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
                $strMessage = $this->_tr->translate('Insufficient access rights');
            }

            $depositId = (int)$this->findParam('deposit_id');
            if (empty($strMessage) && !$this->_accounting->hasAccessToAssignedDeposit($depositId)) {
                $strMessage = $this->_tr->translate('Incorrect incoming data');
            }

            if (empty($strMessage)) {
                $this->_db2->delete(
                    'u_assigned_deposits',
                    [
                        'deposit_id'       => $depositId,
                        'trust_account_id' => null
                    ]
                );

                $this->_accounting->updateTrustAccountSubTotal($memberId, $companyTAId);
            }
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $view->setVariables(array('success' => empty($strMessage), 'message' => $strMessage));
    }

    public function getDepositDetailsAction()
    {
        $view = new JsonModel();

        $strMessage     = '';
        $arrDepositInfo = array();

        try {
            if (!$this->getRequest()->isXmlHttpRequest()) {
                exit;
            }

            // Check incoming info
            $memberId    = (int)$this->findParam('member_id');
            $companyTAId = (int)$this->findParam('ta_id');
            if (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $strMessage = $this->_tr->translate('Insufficient access rights');
            }

            $depositId = (int)$this->findParam('deposit_id');
            if (empty($strMessage) && !$this->_accounting->hasAccessToAssignedDeposit($depositId)) {
                $strMessage = $this->_tr->translate('Incorrect incoming data');
            }

            // If information is correct - load info from DB
            if (empty($strMessage)) {
                $arrDepositInfo = $this->_accounting->getDeposit($depositId, $memberId);
            }
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $view->setVariables(array('success' => empty($strMessage), 'message' => $strMessage, "arrDepositInfo" => $arrDepositInfo));
    }

    public function getAssignedWithdrawalDetailsAction()
    {
        $view = new JsonModel();

        $strMessage        = '';
        $arrWithdrawalInfo = array();

        try {
            // Check incoming info
            $memberId    = (int)$this->findParam('member_id');
            $companyTAId = (int)$this->findParam('ta_id');
            if (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $strMessage = $this->_tr->translate('Insufficient access rights');
            }

            $withdrawalId = $this->findParam('withdrawal_id');
            if (empty($strMessage) && !is_numeric($withdrawalId)) {
                $strMessage = $this->_tr->translate('Incorrect incoming data');
            }


            // If information is correct - load info from DB
            if (empty($strMessage)) {
                $arrWithdrawalInfo = $this->_accounting->getWithdrawal($withdrawalId, $memberId);

                if (empty($arrWithdrawalInfo)) {
                    $strMessage = $this->_tr->translate('Insufficient access rights');
                }
            }
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error occurred. Please contact to web site administrator.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => empty($strMessage), 'message' => $strMessage, "arrWithdrawalInfo" => $arrWithdrawalInfo));
    }

    public function updateNotesAction()
    {
        $strError = '';

        try {
            $filter = new StripTags();

            $type     = $this->params()->fromPost('update_type');
            $id       = $this->params()->fromPost('update_id');
            $notes    = trim($filter->filter(Json::decode($this->params()->fromPost('update_notes', ''), Json::TYPE_ARRAY)));
            $memberId = (int)$this->params()->fromPost('member_id');

            // Check if current user has access to this member
            if (empty($strError) && (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId))) {
                $strError = $this->_tr->translate('Insufficient access rights for this Case');
            }

            if (empty($strError) && !in_array($type, ['invoice', 'invoice_recipient_notes', 'deposit', 'withdrawal', 'payment', 'ps'])) {
                $strError = $this->_tr->translate('Incorrect incoming info.');
            }

            if (empty($strError)) {
                switch ($type) {
                    case 'invoice':
                    case 'invoice_recipient_notes':
                        $booHasAccess = $this->_accounting->hasAccessToInvoice($id);
                        break;

                    case 'deposit':
                        $booHasAccess = $this->_accounting->hasAccessToAssignedDeposit($id);
                        break;

                    case 'withdrawal':
                        $booHasAccess = $this->_accounting->hasAccessToAssignedWithdrawal($id);
                        break;

                    case 'payment':
                        $arrPaymentInfo = $this->_accounting->getPaymentInfo($id);
                        $booHasAccess   = !empty($arrPaymentInfo);
                        break;

                    case 'ps':
                        $booHasAccess = $this->_accounting->hasAccessToPaymentSchedule($id);
                        break;

                    default:
                        $booHasAccess = false;
                        break;
                }

                if (!$booHasAccess) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }
            }

            if (empty($strError) && !$this->_accounting->updateNotes($id, $notes, $type)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel(array('success' => empty($strError), 'message' => $strError));
    }

    public function getCategoriesListAction()
    {
        try {
            $arrCategories = $this->_clients->getFields()->getClientCategories(true);
            $booSuccess    = true;
        } catch (Exception $e) {
            $booSuccess    = false;
            $arrCategories = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'rows'       => $arrCategories,
            'success'    => $booSuccess,
            'totalCount' => count($arrCategories)
        );

        return new JsonModel($arrResult);
    }

    /***** Client A/C Summary ********************************************************************/
    public function getClientSummaryListAction()
    {
        $arrResult = array();

        try {
            $memberId    = (int)$this->params()->fromPost('member_id');
            $companyTAId = (int)$this->params()->fromPost('ta_id');
            $start       = (int)$this->params()->fromPost('start');
            $limit       = (int)$this->params()->fromPost('limit');

            if ($this->_members->hasCurrentMemberAccessToMember($memberId) && $this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $arrResult = $this->_accounting->getClientsTrustAccountInfoPaged($memberId, $companyTAId, $start, $limit);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel($arrResult);
    }

    public function printAction()
    {
        try {
            $memberId    = $this->findParam('member_id');
            $destination = $this->findParam('destination');
            $destination = !in_array($destination, array('I', 'F')) ? 'I' : $destination;

            if (!$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                exit($this->_tr->translate('Insufficient access rights.'));
            }
            $arrResult = $this->_accounting->printClientAccounting($memberId, $destination);
        } catch (Exception $e) {
            $arrResult = '';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view = new ViewModel([
            'content' => is_array($arrResult) ? Json::encode($arrResult) : $arrResult
        ]);
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }

    public function createReportAction()
    {
        $view = new ViewModel(
            ['content' => null]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        set_time_limit(60 * 60); // 1 hour
        ini_set('memory_limit', '512M');

        $strError = '';

        // Get and check incoming params
        $report   = Json::decode($this->params()->fromPost('report'), Json::TYPE_ARRAY);
        $from     = $this->_settings->formatJsonDate(Json::decode($this->params()->fromPost('from'), Json::TYPE_ARRAY));
        $to       = $this->_settings->formatJsonDate(Json::decode($this->params()->fromPost('to'), Json::TYPE_ARRAY));
        $type     = Json::decode($this->params()->fromPost('type'), Json::TYPE_ARRAY);
        $currency = Json::decode($this->params()->fromPost('currency'), Json::TYPE_ARRAY);

        // Check selected report
        if (!in_array($report, array('transaction-all', 'transaction-fees-due', 'transaction-fees-received', 'balances-all', 'balances-period'))) {
            $strError = $this->_tr->translate('Incorrectly selected report.');
        }

        // Check date fields
        if (empty($strError) && $report != 'balances-all') {
            $date_format = 'Y-m-d H:i:s';
            if (!Settings::isValidDateFormat($to, $date_format)) {
                $strError = $this->_tr->translate('Incorrectly selected To date.');
            }

            if (!Settings::isValidDateFormat($from, $date_format)) {
                $strError = $this->_tr->translate('Incorrectly selected From date.');
            }

            if (empty($strError) && (strtotime($from) > strtotime($to))) {
                $strError = $this->_tr->translate("The date in 'From' field must be less or equal to date in 'To' field.");
            }
        }

        // Check selected export type
        if (empty($strError) && !in_array($type, array('xls', 'pdf'))) {
            $strError = $this->_tr->translate('Incorrectly selected type.');
        }

        // If all is okay - generate related report
        if (empty($strError)) {
            try {
                switch ($report) {
                    case 'balances-all':
                    case 'balances-period':
                        if ($report == 'balances-all') {
                            $from = $to = false;
                        }

                        $title = empty($from) && empty($to) ? 'All Clients Case Balances Report' : 'Clients Case Balances Report';
                        if (!empty($from) || !empty($to)) {
                            $title .= $type == 'pdf' ? '<br>' : PHP_EOL;
                            $title .= empty($from) ? '' : ' From ' . $this->_settings->formatDate($from);
                            $title .= empty($to) ? '' : ' To ' . $this->_settings->formatDate($to);
                        }

                        $fileName = str_replace(['<br>', PHP_EOL], ' ', $title);

                        if ($type == 'pdf') {
                            $this->_accounting->generateClientBalancesReport('pdf', $fileName, $title, $from, $to);
                        } else {
                            $spreadsheet = $this->_accounting->generateClientBalancesReport('excel', $fileName, $title, $from, $to);

                            $pointer        = fopen('php://output', 'wb');
                            $bufferedStream = new BufferedStream('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, "attachment; filename=\"$fileName.xlsx\"");
                            $bufferedStream->setStream($pointer);

                            $writer = new Xlsx($spreadsheet);
                            $writer->save('php://output');
                            fclose($pointer);

                            return $view->setVariable('content', null);
                        }
                        break;

                    case 'transaction-all':
                    case 'transaction-fees-due':
                    case 'transaction-fees-received':
                        // Generate title
                        $title = empty($from) && empty($to) ? 'All Clients Case Transactions Report' : 'Clients Case Transactions Report';

                        if (!empty($from) || !empty($to)) {
                            $title .= $type == 'pdf' ? '<br>' : PHP_EOL;
                            $title .= empty($from) ? '' : ' From ' . $this->_settings->formatDate($from);
                            $title .= empty($to) ? '' : ' To ' . $this->_settings->formatDate($to);
                        }

                        $fileName = str_replace(['<br>', PHP_EOL], ' ', $title);

                        if ($type == 'pdf') {
                            $this->_accounting->generateClientTransactionsReport('pdf', $fileName, $title, $report, $currency, $from, $to);
                        } else {
                            $spreadsheet = $this->_accounting->generateClientTransactionsReport('excel', $fileName, $title, $report, $currency, $from, $to);

                            if ($spreadsheet) {
                                $pointer        = fopen('php://output', 'wb');
                                $bufferedStream = new BufferedStream('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, "attachment; filename=\"$fileName.xlsx\"");
                                $bufferedStream->setStream($pointer);

                                $writer = new Xlsx($spreadsheet);
                                $writer->save('php://output');
                                fclose($pointer);

                                return $view->setVariable('content', null);
                            } else {
                                $strError = $this->_tr->translate('Internal error.');
                            }
                        }
                        break;

                    default:
                        // Can't be here
                        $strError = $this->_tr->translate('Incorrectly selected report.');
                        break;
                }
            } catch (Exception $e) {
                $strError = $this->_tr->translate('An error occurred. Please contact to web site administrator.');
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }
        }

        $view->setVariables(
            [
                'content' => $strError
            ],
            true
        );
        return $view;
    }

    public function checkInvoicePdfExistsAction()
    {
        $strError       = '';
        $invoicePdfPath = '';
        $fileSize       = '';
        $booExists      = false;

        try {
            $invoiceId = (int)$this->params()->fromPost('invoice_id');
            $memberId  = (int)$this->params()->fromPost('member_id');

            if (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && !$this->_accounting->hasAccessToInvoice($invoiceId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $memberInfo                 = $this->_members->getMemberInfo($memberId);
                $companyId                  = $memberInfo['company_id'];
                $booLocal                   = $this->_company->isCompanyStorageLocationLocal($companyId);
                $invoiceDocumentsFolderPath = $this->_files->getClientInvoiceDocumentsFolder($memberId, $companyId, $booLocal);

                $invoiceDocumentPdfPath = $invoiceDocumentsFolderPath . '/' . $invoiceId . '.pdf';
                if ($booLocal) {
                    if (file_exists($invoiceDocumentPdfPath)) {
                        $booExists      = true;
                        $fileSize       = Settings::formatSize(filesize($invoiceDocumentPdfPath) / 1024);
                        $invoicePdfPath = $this->_encryption->encode($invoiceDocumentPdfPath);
                    }
                } elseif ($this->_files->getCloud()->checkObjectExists($invoiceDocumentPdfPath)) {
                    $booExists      = true;
                    $fileSize       = Settings::formatSize($this->_files->getCloud()->getObjectFilesize($invoiceDocumentPdfPath) / 1024);
                    $invoicePdfPath = $this->_encryption->encode($invoiceDocumentPdfPath);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'error'       => $strError,
            'file_exists' => $booExists,
            'file_id'     => $invoicePdfPath,
            'filesize'    => $fileSize
        );

        return new JsonModel($arrResult);
    }

    public function createInvoicePdfAction()
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $fileName = '';
        $fileSize = '';
        $filePath = '';

        try {
            $memberId                = (int)Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY);
            $invoiceId               = (int)Json::decode($this->params()->fromPost('invoice_id'), Json::TYPE_ARRAY);
            $templateId              = (int)Json::decode($this->params()->fromPost('template_id'), Json::TYPE_ARRAY);
            $booCopyToCorrespondence = (bool)Json::decode($this->params()->fromPost('copy_to_correspondence'), Json::TYPE_ARRAY);

            if (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && !$this->_accounting->hasAccessToInvoice($invoiceId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $arrInvoiceTemplatesIds = $this->_accounting->getInvoiceTemplates(true);
                if (!in_array($templateId, $arrInvoiceTemplatesIds)) {
                    $strError = $this->_tr->translate('Incorrectly selected template');
                }
            }

            if (empty($strError)) {
                $arrInvoiceInfo = $this->_accounting->getInvoiceInfo($invoiceId);

                list($strError, $strHtmlTemplate) = $this->_accounting->getInvoiceRenderedTemplate($memberId, $arrInvoiceInfo['company_ta_id'], $invoiceId, $templateId, [], true);
                if (empty($strError)) {
                    $arrResult = $this->_accounting->createInvoicePdf($arrInvoiceInfo, $strHtmlTemplate, $booCopyToCorrespondence);
                    $strError  = $arrResult['error'];

                    if (empty($strError)) {
                        $fileName = $arrResult['filename'];
                        $fileSize = $arrResult['file_size'];
                        $filePath = $arrResult['file_id'];
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'error'     => $strError,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'file_id'   => $filePath
        );

        return new JsonModel($arrResult);
    }

    public function getInvoicePdfAction()
    {
        try {
            $invoiceId   = (int)$this->params()->fromQuery('invoice_id');
            $memberId    = (int)$this->params()->fromQuery('member_id');
            $booDownload = (bool)$this->params()->fromQuery('download');
            $invoicePath = $this->params()->fromQuery('invoice_path');

            $result = $this->_accounting->getInvoicePdf($memberId, $invoiceId, $invoicePath);
            if ($result instanceof FileInfo) {
                if ($result->local) {
                    return $this->downloadFile($result->path, $result->name, $result->mime, false, $booDownload);
                } else {
                    $url = $this->_files->getCloud()->getFile($result->path, $result->name, false, $booDownload);
                    if ($url) {
                        return $this->redirect()->toUrl($url);
                    } else {
                        $strError = $this->_tr->translate('File not found');
                    }
                }
            } else {
                $strError = $result;
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view = new ViewModel(
            ['content' => $strError]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');
        return $view;
    }

    public function getInfoToAssignDepositAction()
    {
        $strError              = '';
        $transactionAmount     = '';
        $transactionIdToSelect = 0;
        $arrTransactions       = array();
        $arrTemplates          = array();
        $arrSendAsOptions      = array();
        $booCanBeAssigned      = false;

        try {
            // Check incoming info
            $memberId = $this->params()->fromPost('member_id');
            if (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights to the client.');
            }

            $companyTaId = $this->params()->fromPost('ta_id');
            if (empty($strError) && !$this->_clients->hasCurrentMemberAccessToTA($companyTaId)) {
                $strError = $this->_tr->translate('Insufficient access rights to the ') . $this->_company->getCurrentCompanyDefaultLabel('trust_account');
            }

            $depositId = $this->params()->fromPost('deposit_id');
            if (empty($strError) && !$this->_accounting->hasAccessToAssignedDeposit($depositId)) {
                $strError = $this->_tr->translate('Incorrect incoming data');
            }


            if (empty($strError)) {
                $arrDepositInfo        = $this->_accounting->getDeposit($depositId, $memberId);
                $arrTAInfo             = $this->_accounting->getTAInfo($companyTaId);
                $arrNotClearedDeposits = $this->_accounting->getTrustAccountNotClearedDeposits($companyTaId);
                $transactionAmount     = $this->_accounting::formatPrice($arrDepositInfo['amount'], $arrDepositInfo['currency']);

                foreach ($arrNotClearedDeposits as $arrNotClearedDepositInfo) {
                    $booCanBeSelected = $arrNotClearedDepositInfo['deposit'] == $arrDepositInfo['amount'];
                    $booCanBeAssigned = $booCanBeAssigned || $booCanBeSelected;

                    if ($booCanBeSelected && empty($transactionIdToSelect)) {
                        $transactionIdToSelect = $arrNotClearedDepositInfo['trust_account_id'];
                    }

                    $arrTransactions[] = array(
                        'transaction_id'              => $arrNotClearedDepositInfo['trust_account_id'],
                        'transaction_description'     => $this->_settings->formatDate($arrNotClearedDepositInfo['date_from_bank']) . ' ' . $arrNotClearedDepositInfo['description'] . ' ' . $this->_accounting::formatPrice($arrNotClearedDepositInfo['deposit'], $arrTAInfo['currency']),
                        'transaction_can_be_selected' => $booCanBeSelected
                    );
                }

                $arrTemplates     = $this->_templates->getTemplatesList(false, 0, 'Payment', 'Email', false, true);
                $arrSendAsOptions = $this->_templates->getSendAsOptions();
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success'               => empty($strError),
            'message'               => $strError,
            'transactions'          => $arrTransactions,
            'templates'             => $arrTemplates,
            'send_as_options'       => $arrSendAsOptions,
            'can_be_assigned'       => $booCanBeAssigned,
            'transaction_amount'    => $transactionAmount,
            'transaction_id_select' => $transactionIdToSelect,
        );

        return new JsonModel($arrResult);
    }

    public function assignDepositAction()
    {
        $strError = '';
        try {
            $depositId = Json::decode($this->params()->fromPost('deposit_id'), Json::TYPE_ARRAY);
            if (empty($strError) && !$this->_accounting->hasAccessToAssignedDeposit($depositId)) {
                $strError = $this->_tr->translate('Insufficient access rights to deposit');
            }

            $trustAccountId      = Json::decode($this->params()->fromPost('transaction_id'), Json::TYPE_ARRAY);
            $arrTrustAccountInfo = array();
            if (empty($strError)) {
                $arrTrustAccountInfo = $this->_accounting->getTrustAccount()->getTransactionInfo($trustAccountId);
                if ((empty($arrTrustAccountInfo) || !$this->_clients->hasCurrentMemberAccessToTA($arrTrustAccountInfo['company_ta_id']))) {
                    $strError = $this->_tr->translate('Incorrect incoming data');
                }
            }

            $memberId = Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY);
            if (empty($strError) && (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId))) {
                $strError = $this->_tr->translate('Insufficient access rights to the client.');
            }

            if (empty($strError)) {
                // Assign deposit to the client
                $result = $this->_accounting->getTrustAccount()->assignDepositToAlreadyCreated(array(
                    'company_ta_id'    => $arrTrustAccountInfo['company_ta_id'],
                    'trust_account_id' => $trustAccountId,
                    'deposit_id'       => $depositId,
                    'deposit'          => $arrTrustAccountInfo['deposit'],
                    'member_id'        => $memberId
                ));

                if (!empty($result)) {
                    $this->_accounting->getTrustAccount()->updateMaxReceiptNumber();

                    // Update notes if there are no errors
                    $filter = new StripTags();
                    $this->_accounting->getTrustAccount()->updateTransactionInfo(
                        array(
                            'id'              => $trustAccountId,
                            'notes'           => $filter->filter(Json::decode($this->params()->fromPost('notes'), Json::TYPE_ARRAY)),
                            'payment_made_by' => $filter->filter(Json::decode($this->params()->fromPost('payment_made_by'), Json::TYPE_ARRAY))
                        )
                    );
                } else {
                    $strError = $this->_tr->translate('Cannot assign deposit. Please try again later.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('The transaction is assigned successfully.') : $strError,
        );

        return new JsonModel($arrResult);
    }

    public function getCaseAccountingSettingsAction()
    {
        $strError            = '';
        $caseEmail           = '';
        $arrCaseStatusFields = array();
        $arrCaseDateFields   = array();
        $arrMemberTA         = array();
        $arrCompanyTA        = array();
        $primaryTAId         = null;
        $secondaryTAId       = null;
        $primaryCurrency     = '';
        $booCanEditClient    = false;
        $switchTAMode        = [];

        try {
            $currentMemberId = $this->_auth->getCurrentUserId();
            $memberId        = (int)Json::decode($this->params()->fromPost('caseId'), Json::TYPE_ARRAY);

            if (!$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $this->_log->debugErrorToFile('', 'Client has not access to this client or client does not exists (User ID: ' . $currentMemberId . ', Client ID: ' . $memberId . ' )', 'access_denied');
                $strError = $this->_tr->translate('Access denied');
            }

            if (empty($strError)) {
                $arrMemberInfo = $this->_members->getMemberInfo($memberId);
                $companyId     = $arrMemberInfo['company_id'];
                $caseEmail     = $arrMemberInfo['emailAddress'];

                $booCanEditClient = !$this->_auth->isCurrentUserClient() && $this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId);

                // If there are no assigned T/As to this client and client's company has only 1 T/A -> automatically assign it
                $arrAccountingInfo = $this->_accounting->getMemberAccounting($memberId, $companyId);
                if (count($arrAccountingInfo['arrMemberTA']) == 0) {
                    $this->_clients->createClientTAIfCompanyHasOneTA($memberId, $companyId);
                    $arrAccountingInfo = $this->_accounting->getMemberAccounting($memberId, $companyId);
                }

                // Collect all accounting info
                $arrMemberTA     = $arrAccountingInfo['arrMemberTA'] ?? array();
                $arrCompanyTA    = $arrAccountingInfo['arrCompanyTA'] ?? array();
                $primaryTAId     = $arrAccountingInfo['primaryTAId'] ?? null;
                $secondaryTAId   = $arrAccountingInfo['secondaryTAId'] ?? null;
                $switchTAMode    = $arrAccountingInfo['switchTAMode'];
                $primaryCurrency = $arrAccountingInfo['primaryCurrency'];

                $arrCategories = $this->_clients->getFields()->getClientCategories(true);
                foreach ($arrCategories as $arrCategoryInfo) {
                    if (!empty($arrCategoryInfo['cId'])) {
                        switch ($arrCategoryInfo['cType']) {
                            case 'profile_date':
                                $arrCaseDateFields[] = array(
                                    'cId'   => $arrCategoryInfo['cFieldId'],
                                    'cName' => $arrCategoryInfo['cName']
                                );
                                break;

                            case 'file_status':
                                $arrCaseStatusFields[] = array(
                                    'cId'   => $arrCategoryInfo['cOptionId'],
                                    'cName' => $arrCategoryInfo['cName']
                                );
                                break;

                            default:
                                break;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'             => empty($strError),
            'message'             => $strError,
            'caseEmail'           => $caseEmail,
            'arrCaseStatusFields' => $arrCaseStatusFields,
            'arrCaseDateFields'   => $arrCaseDateFields,
            'arrMemberTA'         => $arrMemberTA,
            'arrCompanyTA'        => $arrCompanyTA,
            'primaryTAId'         => $primaryTAId,
            'secondaryTAId'       => $secondaryTAId,
            'primaryCurrency'     => $primaryCurrency,
            'booCanEditClient'    => $booCanEditClient,
            'switchTAMode'        => $switchTAMode,
        );

        return new JsonModel($arrResult);
    }

    public function getClientInvoicesAction()
    {
        $arrResult = array(
            'rows'       => array(),
            'totalCount' => 0
        );

        try {
            $memberId = (int)$this->params()->fromPost('member_id');
            $start    = (int)$this->params()->fromPost('start');
            $limit    = (int)$this->params()->fromPost('limit');

            // Check if current user can access to this client to get the list of invoices
            if ($this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $arrResult = $this->_accounting->getClientInvoices($memberId, $start, $limit);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel($arrResult);
    }

    public function getClientFeesAction()
    {
        $arrRows                  = array();
        $totalCount               = 0;
        $totalDue                 = 0;
        $total                    = 0;
        $totalGst                 = 0;
        $unassignedInvoicesAmount = 0;

        try {
            $companyTAId = (int)$this->params()->fromPost('ta_id');
            $memberId    = (int)$this->params()->fromPost('member_id');
            $start       = (int)$this->params()->fromPost('start');
            $limit       = (int)$this->params()->fromPost('limit');
            $sort        = $this->params()->fromPost('sort');
            $dir         = $this->params()->fromPost('dir');

            $strError = '';

            // Check if current user has access to this member
            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this Case');
            }

            if (empty($strError) && !$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this ' . $this->_company->getCurrentCompanyDefaultLabel('trust_account'));
            }

            if (empty($strError)) {
                $arrFeesResult            = $this->_accounting->getClientAccountingFeesList($memberId, $companyTAId, $sort, $dir);
                $arrLoadedRecords         = $arrFeesResult['rows'];
                $total                    = $arrFeesResult['total'];
                $totalGst                 = $arrFeesResult['total_gst'];
                $totalDue                 = $arrFeesResult['total_due'];
                $unassignedInvoicesAmount = $arrFeesResult['unassigned_invoices_amount'];

                // Apply paging
                $totalCount = count($arrLoadedRecords);

                if ($totalCount) {
                    for ($i = $start; $i < $start + $limit; $i++) {
                        if (count($arrLoadedRecords) > $i) {
                            $arrRows[] = $arrLoadedRecords[$i];
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'rows'                       => $arrRows,
            'totalCount'                 => $totalCount,
            'error'                      => $strError,
            'total_due'                  => $totalDue,
            'total_due_gst'              => 0, // Is not used
            'total'                      => $total,
            'total_gst'                  => $totalGst,
            'unassigned_invoices_amount' => $unassignedInvoicesAmount,
        );

        return new JsonModel($arrResult);
    }

    public function deleteInvoicePaymentAction()
    {
        $strError = '';

        try {
            $memberId    = 0;
            $companyTAId = 0;

            $invoicePaymentId = $this->params()->fromPost('invoice_payment_id');

            // Check if there is such a payment
            $arrInvoicePaymentInfo = $this->_accounting->getInvoicePaymentInfo($invoicePaymentId);
            if (empty($arrInvoicePaymentInfo)) {
                $strError = $this->_tr->translate('Incorrectly selected payment');
            } else {
                $memberId    = $arrInvoicePaymentInfo['member_id'];
                $companyTAId = $arrInvoicePaymentInfo['company_ta_id'];
            }

            // Check if a current user has access to this member
            if (empty($strError) && $this->_auth->isCurrentUserClient()) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId))) {
                $strError = $this->_tr->translate('Insufficient access rights for this Case');
            }

            // Check if a current user has access to this T/A
            if (empty($strError) && !$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this ' . $this->_company->getCurrentCompanyDefaultLabel('trust_account'));
            }

            // Check if this invoice payment was already reconciled
            if (empty($strError)) {
                $booCanUnAssign          = true;
                $arrAssignedTransactions = $this->_accounting->getAssignedTransactionsByInvoiceId($arrInvoicePaymentInfo['invoice_id']);
                foreach ($arrAssignedTransactions as $arrAssignedTransactionInfo) {
                    if (isset($arrAssignedTransactionInfo['trust_account_id']) && !empty($arrAssignedTransactionInfo['trust_account_id']) && $arrAssignedTransactionInfo['invoice_payment_id'] == $invoicePaymentId) {
                        $booCanUnAssign = $this->_accounting->getTrustAccount()->canUnassignTransaction($companyTAId, $arrAssignedTransactionInfo['date_from_bank']);
                        break;
                    }
                }

                if (!$booCanUnAssign) {
                    $strError = $this->_tr->translate('This payment is part of an existing reconciliation report and cannot be deleted.');
                }
            }

            if (empty($strError)) {
                $this->_accounting->deleteInvoicePayment($invoicePaymentId);
                $this->_accounting->updateTrustAccountSubTotal($memberId, $companyTAId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }

    public function getPaymentsToAssignInvoiceAction()
    {
        $strError         = '';
        $arrAssignedFees  = [];
        $arrAvailableFees = [];

        try {
            $companyTAId = (int)$this->params()->fromPost('ta_id');
            $memberId    = (int)$this->params()->fromPost('member_id');
            $invoiceId   = (int)$this->params()->fromPost('invoice_id');

            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this Case');
            }

            if (empty($strError) && !$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this ' . $this->_company->getCurrentCompanyDefaultLabel('trust_account'));
            }

            if (empty($strError) && !$this->_accounting->hasAccessToInvoice($invoiceId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this invoice');
            }

            if (empty($strError)) {
                $arrAvailableFees = $this->_accounting->getFeesAvailableToAssignToInvoice($memberId, $companyTAId, $invoiceId);
                $arrAssignedFees  = $this->_accounting->getFeesAssignedToInvoice($invoiceId, true);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'      => empty($strError),
            'message'      => $strError,
            'assignedFees' => $arrAssignedFees,
            'rows'         => $arrAvailableFees,
            'totalCount'   => count($arrAvailableFees)
        );

        return new JsonModel($arrResult);
    }

    public function assignInvoiceToFeesAction()
    {
        $strError = '';

        try {
            $memberId    = (int)Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY);
            $companyTAId = (int)Json::decode($this->params()->fromPost('ta_id'), Json::TYPE_ARRAY);
            $invoiceId   = (int)Json::decode($this->params()->fromPost('invoice_id'), Json::TYPE_ARRAY);
            $arrFees     = Json::decode($this->params()->fromPost('fees'), Json::TYPE_ARRAY);

            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this Case');
            }

            if (empty($strError) && !$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this ' . $this->_company->getCurrentCompanyDefaultLabel('trust_account'));
            }

            if (empty($strError) && !$this->_accounting->hasAccessToInvoice($invoiceId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this invoice.');
            }

            $arrCorrectFees = [];
            $invoiceNewFee  = 0;
            $invoiceNewTax  = 0;
            if (empty($strError)) {
                $arrAvailableFees = $this->_accounting->getFeesAvailableToAssignToInvoice($memberId, $companyTAId, $invoiceId);

                foreach ($arrAvailableFees as $arrAvailableFeeInfo) {
                    if (in_array($arrAvailableFeeInfo['fee_id'], $arrFees)) {
                        $arrCorrectFees[] = $arrAvailableFeeInfo['real_id'];

                        $invoiceNewFee += floatval($arrAvailableFeeInfo['fee_amount']);
                        $invoiceNewTax += floatval($arrAvailableFeeInfo['fee_gst']);
                    }
                }

                if (count($arrCorrectFees) != count($arrFees)) {
                    $strError = $this->_tr->translate('Incorrectly selected fees.');
                }
            }

            $arrInvoiceInfo = [];
            if (empty($strError)) {
                $outstandingAmount = $this->_accounting->getInvoiceOutstandingAmount($invoiceId);
                $arrInvoiceInfo    = $this->_accounting->getInvoiceInfo($invoiceId);
                if ($outstandingAmount > 0 && $this->_settings->floatCompare(floatval($arrInvoiceInfo['amount']) - $outstandingAmount, $invoiceNewFee + $invoiceNewTax, '>')) {
                    $strError = $this->_tr->translate('It is not possible to decrease invoice amount.');
                }
            }

            if (empty($strError)) {
                $arrAssignedPaymentIds = $this->_accounting->getFeesAssignedToInvoice($invoiceId, true);
                if (!empty($arrAssignedPaymentIds)) {
                    // Unassign from already assigned
                    $this->_accounting->updatePaymentInfo($arrAssignedPaymentIds, array('invoice_id' => null));
                }

                $this->_accounting->updatePaymentInfo($arrCorrectFees, array('invoice_id' => $invoiceId));

                // Update invoice's fee + tax if it is different from the fees' calculated
                if (!$this->_settings->floatCompare(floatval($arrInvoiceInfo['fee']), $invoiceNewFee) || !$this->_settings->floatCompare(floatval($arrInvoiceInfo['tax']), $invoiceNewTax)) {
                    $this->_accounting->updateInvoice(
                        $invoiceId,
                        [
                            'amount' => $invoiceNewFee + $invoiceNewTax,
                            'fee'    => $invoiceNewFee,
                            'tax'    => $invoiceNewTax,
                        ]
                    );
                }

                // update outstanding balance
                $this->_accounting->updateOutstandingBalance($memberId, $companyTAId);

                // update sub totals
                $this->_accounting->updateTrustAccountSubTotal($memberId, $companyTAId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }

    public function getInvoicesToAssignFeeAction()
    {
        $strError             = '';
        $arrAvailableInvoices = array();

        try {
            $memberId    = (int)Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY);
            $companyTAId = (int)Json::decode($this->params()->fromPost('ta_id'), Json::TYPE_ARRAY);
            $arrPayments = Json::decode($this->params()->fromPost('fees'), Json::TYPE_ARRAY);

            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this Case');
            }

            if (empty($strError) && !$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this ' . $this->_company->getCurrentCompanyDefaultLabel('trust_account'));
            }

            if (empty($strError) && empty($arrPayments)) {
                $strError = $this->_tr->translate('Please select at least one Fee');
            }

            $paymentsAmount = 0;
            if (empty($strError)) {
                foreach ($arrPayments as $paymentId) {
                    $arrPaymentInfo = $this->_accounting->getPaymentInfo($paymentId);
                    if (empty($arrPaymentInfo)) {
                        $strError = $this->_tr->translate('Insufficient access rights for this Fee');
                    } elseif (!empty($arrPaymentInfo['invoice_id'])) {
                        $strError = $this->_tr->translate('This Fee cannot be updated because it was already invoiced');
                    }

                    if (!empty($strError)) {
                        break;
                    } else {
                        $paymentsAmount += floatval($arrPaymentInfo['withdrawal']);
                    }
                }
            }

            if (empty($strError) && empty($paymentsAmount)) {
                $strError = $this->_tr->translate('Incorrectly selected Fees.');
            }

            if (empty($strError)) {
                $arrUnassignedInvoices = $this->_accounting->getClientUnassignedInvoices($memberId, $companyTAId);
                foreach ($arrUnassignedInvoices as $arrUnassignedInvoiceInfo) {
                    if ($paymentsAmount == $arrUnassignedInvoiceInfo['amount']) {
                        $arrAvailableInvoices[] = [
                            'invoice_id'     => $arrUnassignedInvoiceInfo['invoice_id'],
                            'invoice_date'   => $arrUnassignedInvoiceInfo['date_of_invoice'],
                            'invoice_num'    => $arrUnassignedInvoiceInfo['invoice_num'],
                            'invoice_amount' => $arrUnassignedInvoiceInfo['amount'],
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'    => empty($strError),
            'message'    => $strError,
            'rows'       => $arrAvailableInvoices,
            'totalCount' => count($arrAvailableInvoices)
        );

        return new JsonModel($arrResult);
    }

    public function assignFeesToInvoiceAction()
    {
        $strError = '';

        try {
            $memberId    = (int)Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY);
            $companyTAId = (int)Json::decode($this->params()->fromPost('ta_id'), Json::TYPE_ARRAY);
            $invoiceId   = (int)Json::decode($this->params()->fromPost('invoice_id'), Json::TYPE_ARRAY);
            $arrPayments = Json::decode($this->params()->fromPost('fees'), Json::TYPE_ARRAY);

            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this Case');
            }

            if (empty($strError) && !$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this ' . $this->_company->getCurrentCompanyDefaultLabel('trust_account'));
            }

            if (empty($strError) && (empty($invoiceId) || !$this->_accounting->hasAccessToInvoice($invoiceId))) {
                $strError = $this->_tr->translate('Insufficient access rights for this invoice.');
            }

            $invoiceAmount = 0;
            if (empty($strError)) {
                $arrUnassignedInvoices = $this->_accounting->getClientUnassignedInvoices($memberId, $companyTAId);
                foreach ($arrUnassignedInvoices as $arrUnassignedInvoiceInfo) {
                    if ($arrUnassignedInvoiceInfo['invoice_id'] == $invoiceId) {
                        $invoiceAmount = floatval($arrUnassignedInvoiceInfo['amount']);
                        break;
                    }
                }
            }

            if (empty($strError) && empty($invoiceAmount)) {
                $strError = $this->_tr->translate('Incorrectly selected invoice.');
            }

            if (empty($strError) && empty($arrPayments)) {
                $strError = $this->_tr->translate('Please select at least one Fee');
            }

            if (empty($strError)) {
                $paymentsAmount = 0;
                foreach ($arrPayments as $paymentId) {
                    $arrPaymentInfo = $this->_accounting->getPaymentInfo($paymentId);
                    if (empty($arrPaymentInfo)) {
                        $strError = $this->_tr->translate('Insufficient access rights for this Fee');
                    } elseif (!empty($arrPaymentInfo['invoice_id'])) {
                        $strError = $this->_tr->translate('This Fee cannot be updated because it was already invoiced');
                    }

                    if (!empty($strError)) {
                        break;
                    } else {
                        $paymentsAmount += floatval($arrPaymentInfo['withdrawal']);
                    }
                }

                if (empty($strError) && !$this->_settings->floatCompare($invoiceAmount, $paymentsAmount, '==', 2)) {
                    $strError = $this->_tr->translate('Fee(s) amount is not equal to the invoice amount');
                }
            }

            if (empty($strError)) {
                $this->_accounting->updatePaymentInfo($arrPayments, array('invoice_id' => $invoiceId));

                // update outstanding balance
                $this->_accounting->updateOutstandingBalance($memberId, $companyTAId);

                // update sub totals
                $this->_accounting->updateTrustAccountSubTotal($memberId, $companyTAId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }


    public function unassignInvoiceFeesAction()
    {
        $strError = '';

        try {
            $memberId    = (int)Json::decode($this->params()->fromPost('caseId'), Json::TYPE_ARRAY);
            $companyTAId = (int)Json::decode($this->params()->fromPost('caseTAId'), Json::TYPE_ARRAY);
            $invoiceId   = (int)Json::decode($this->params()->fromPost('invoiceId'), Json::TYPE_ARRAY);

            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this case');
            }

            if (empty($strError) && !$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $strError = $this->_tr->translate('Insufficient access rights for this ' . $this->_company->getCurrentCompanyDefaultLabel('trust_account'));
            }

            if (empty($strError) && (empty($invoiceId) || !$this->_accounting->hasAccessToInvoice($invoiceId))) {
                $strError = $this->_tr->translate('Insufficient access rights for this invoice.');
            }

            $arrPaymentIds = [];
            if (empty($strError)) {
                $arrPaymentIds = $this->_accounting->getFeesAssignedToInvoice($invoiceId, true);
            }

            if (empty($strError) && empty($arrPaymentIds)) {
                $strError = $this->_tr->translate('There are no fees assigned to this invoice.');
            }

            if (empty($strError)) {
                $this->_accounting->updatePaymentInfo($arrPaymentIds, array('invoice_id' => null));

                // Shrink back the invoice amount to the payment on that invoice when assigned fees are removed
                $arrInvoicePayments = $this->_accounting->getInvoicePayments($invoiceId);

                $invoiceNewFee = 0;
                $invoiceNewTax = 0;
                foreach ($arrInvoicePayments as $arrInvoicePayment) {
                    $invoiceNewFee += $arrInvoicePayment['invoice_payment_amount'];
                }
                $this->_accounting->updateInvoice(
                    $invoiceId,
                    [
                        'amount' => $invoiceNewFee + $invoiceNewTax,
                        'fee'    => $invoiceNewFee,
                        'tax'    => $invoiceNewTax,
                    ]
                );

                // update outstanding balance
                $this->_accounting->updateOutstandingBalance($memberId, $companyTAId);

                // update sub totals
                $this->_accounting->updateTrustAccountSubTotal($memberId, $companyTAId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }
}
