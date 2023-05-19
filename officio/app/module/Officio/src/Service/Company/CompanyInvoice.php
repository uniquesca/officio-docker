<?php

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */

namespace Officio\Service\Company;

use Clients\Service\Clients\Accounting;
use Clients\Service\Members;
use Exception;
use Files\Service\Files;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Officio\Common\Service\BaseService;
use Officio\Service\AutomatedBillingErrorCodes;
use Officio\Service\AutomatedBillingLog;
use Officio\Service\Company;
use Officio\Service\GstHst;
use Officio\Service\Payment\PaymentServiceInterface;
use Officio\Common\ServiceContainerHolder;
use Officio\Common\SubServiceInterface;
use Officio\Templates\Model\SystemTemplate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Prospects\Service\Prospects;
use Officio\Templates\SystemTemplates;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class CompanyInvoice extends BaseService implements SubServiceInterface
{

    use ServiceContainerHolder;

    public const TEMPLATE_DEFAULT = 'default';
    public const TEMPLATE_SPECIAL_CC_CHARGE = 'special-cc-charge';

    /** @var int invoices will be showed on one page */
    public $intShowInvoicesPerPage = 50;

    /** @var int invoices will be showed on one page */
    public $intShowCompaniesPerPage = 20;

    /** @var AutomatedBillingLog */
    protected $_automatedBillingLog;

    /** @var Company */
    private $_parent;

    /** @var SystemTemplates */
    protected $_systemTemplates;

    /** @var GstHst */
    protected $_gstHst;

    /** @var PaymentServiceInterface */
    protected $_payment;

    public function initAdditionalServices(array $services)
    {
        $this->_automatedBillingLog = $services[AutomatedBillingLog::class];
        $this->_systemTemplates     = $services[SystemTemplates::class];
        $this->_gstHst              = $services[GstHst::class];
        $this->_payment             = $services['payment'];
    }

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent()
    {
        return $this->_parent;
    }

    /**
     * Get first failed invoice date for specific company
     *
     * @param int $companyId
     * @return string invoice date
     */
    public function getCompanyFirstFailedInvoiceDate($companyId)
    {
        $select = (new Select())
            ->from(array('i' => 'company_invoice'))
            ->columns(['invoice_date'])
            ->where(['i.status' => 'F', 'i.company_id' => (int)$companyId])
            ->order('i.company_invoice_id ASC')
            ->limit(1);

        return $this->_db2->fetchOne($select);
    }


    /**
     * Get failed invoices list for specific company
     *
     * @param int $companyId
     * @return array invoices
     */
    public function getCompanyFailedInvoices($companyId)
    {
        $select = (new Select())
            ->from(array('i' => 'company_invoice'))
            ->where([
                'i.status'     => 'F',
                'i.company_id' => (int)$companyId
            ])
            ->order('i.company_invoice_id ASC');

        return $this->_db2->fetchAll($select);
    }

    /**
     * Reset PT error codes for company invoices
     *
     * @param int $companyId
     */
    public function resetPTErrorCodesForCompany($companyId)
    {
        $this->_db2->update(
            'company_invoice',
            ['last_PT_error_code' => null],
            [
                'status'     => 'F',
                'company_id' => $companyId
            ]
        );
    }


    /**
     * Charge all failed invoices for all companies or only for the specific one
     *
     * @param int|null $specificCompanyId
     * @return array with result
     */
    public function chargePreviousFailedInvoices($specificCompanyId = null)
    {
        $sessionId               = 0;
        $intFailedInvoicesCount  = 0;
        $intSuccessInvoicesCount = 0;
        $arrAllInvoices          = array();

        try {
            // Collect all invoices we need charge
            $arrWhere             = [];
            $arrWhere['c.Status'] = 1;
            $arrWhere['i.status'] = 'F';

            if (!empty($specificCompanyId)) {
                $arrWhere['i.company_id'] = (int)$specificCompanyId;
            } else {
                // Don't try to charge very often - once per 10 days
                $arrWhere[] = (new Where())
                    ->nest()
                    ->isNull('i.invoice_posted_date')
                    ->or
                    ->lessThan('i.invoice_posted_date', new PredicateExpression('DATE_SUB(NOW(), INTERVAL 10 DAY)'))
                    ->unnest();
            }

            /** @var AutomatedBillingErrorCodes $automatedBillingErrorCodes */
            $automatedBillingErrorCodes = $this->_serviceContainer->get(AutomatedBillingErrorCodes::class);
            $arrIgnoreCodes             = $automatedBillingErrorCodes->loadErrorCodesOnly();
            if (count($arrIgnoreCodes)) {
                $arrWhere[] = (new Where())
                    ->nest()
                    ->notIn('i.last_PT_error_code', $arrIgnoreCodes)
                    ->or
                    ->isNull('i.last_PT_error_code')
                    ->or
                    ->equalTo('i.last_PT_error_code', '')
                    ->unnest();
            }
            $select = (new Select())
                ->from(array('i' => 'company_invoice'))
                ->join(array('c' => 'company'), 'c.company_id = i.company_id', array('companyName', 'company_abn'), Select::JOIN_LEFT_OUTER)
                ->join(array('d' => 'company_details'), 'c.company_id = d.company_id', array('next_billing_date', 'show_expiration_dialog_after'), Select::JOIN_LEFT_OUTER)
                ->where($arrWhere)
                ->order('i.company_invoice_id ASC');

            $arrInvoices = $this->_db2->fetchAll($select);

            // Group invoices by companies
            $arrInvoicesGrouped = array();
            foreach ($arrInvoices as $arrInvoiceInfo) {
                $arrInvoicesGrouped[$arrInvoiceInfo['company_id']][] = $arrInvoiceInfo;
            }

            // Now collect all invoices we need to charge for each company
            $arrSessionLogs = array();

            $arrFailedInvoicesOfTheCompany = array();
            foreach ($arrInvoicesGrouped as $companyId => $arrInvoices) {
                foreach ($arrInvoices as $arrInvoiceInfo) {
                    $arrInvoiceInfo['error_message'] = '';
                    $arrInvoiceInfo['company_name']  = $arrInvoiceInfo['companyName'];
                    $arrInvoiceInfo['amount']        = $arrInvoiceInfo['total'];

                    // Charge the invoice!
                    // Don't try to charge a second (and so on) invoice if the previous one failed
                    if (!in_array($companyId, $arrFailedInvoicesOfTheCompany)) {
                        $arrChargeResult = $this->chargeSavedInvoice($arrInvoiceInfo['company_invoice_id']);
                    } else {
                        // Update charging date, so this skipped invoice will be not tried to be charged again tomorrow
                        $arrInvoiceUpdate = array(
                            'invoice_posted_date' => date('c')
                        );
                        $this->updateInvoice($arrInvoiceUpdate, $arrInvoiceInfo['company_invoice_id']);

                        $arrChargeResult = array(
                            'error'   => true,
                            'code'    => '-1',
                            'message' => 'SKIPPED (because the first invoice was already failed)'
                        );
                    }

                    if ($arrChargeResult['error']) {
                        $intFailedInvoicesCount++;
                        $strErrorMessage                 = $arrChargeResult['message'];
                        $strErrorCode                    = $arrChargeResult['code'];
                        $arrInvoiceInfo['success']       = false;
                        $arrInvoiceInfo['error_message'] = $strErrorMessage;

                        $arrFailedInvoicesOfTheCompany[] = $companyId;
                    } else {
                        $intSuccessInvoicesCount++;
                        $strErrorMessage           = '';
                        $strErrorCode              = '';
                        $arrInvoiceInfo['success'] = true;
                    }

                    $arrAllInvoices[] = $arrInvoiceInfo;

                    // Save in log table
                    $arrSessionLogs[] = array(
                        'retry'                    => true,
                        'company_name'             => $arrInvoiceInfo['companyName'],
                        'company_abn'              => $arrInvoiceInfo['company_abn'],
                        'company_show_dialog_date' => $arrInvoiceInfo['show_expiration_dialog_after'],
                        'invoice_id'               => $arrInvoiceInfo['company_invoice_id'],
                        'amount'                   => $arrInvoiceInfo['total'],
                        'old_billing_date'         => $arrInvoiceInfo['next_billing_date'],
                        'new_billing_date'         => $arrInvoiceInfo['next_billing_date'],
                        'status'                   => $arrChargeResult['error'] ? 'F' : 'C',
                        'error_code'               => $strErrorCode,
                        'error_message'            => $strErrorMessage
                    );
                }
            }

            if (count($arrSessionLogs)) {
                $sessionId = $this->_automatedBillingLog->saveSession($arrSessionLogs);
            }


            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'success'          => $booSuccess,
            'session_id'       => $sessionId,
            'failed_invoices'  => $intFailedInvoicesCount,
            'success_invoices' => $intSuccessInvoicesCount,
            'invoices_array'   => $arrAllInvoices,
        );
    }

    /**
     * Load product names grouped from already saved invoices
     *
     * @param bool $booKeysOnly
     * @return array
     */
    public function getProductsList($booKeysOnly = false)
    {
        $arrProducts = array(
            array('productId' => '', 'productName' => 'All'),
        );

        // Load products from DB
        $select = (new Select())
            ->from(array('i' => 'company_invoice'))
            ->columns(['product'])
            ->where([(new Where())->isNotNull('product')])
            ->group('product');

        $arrSavedProducts = $this->_db2->fetchCol($select);

        foreach ($arrSavedProducts as $productName) {
            $arrProducts[] = array('productId' => $productName, 'productName' => $productName);
        }


        // In some cases we need only keys
        if ($booKeysOnly) {
            $arrResult = array();
            foreach ($arrProducts as $arrProduct) {
                $arrResult[] = $arrProduct['productId'];
            }

            $arrProducts = $arrResult;
        }


        return $arrProducts;
    }

    /**
     * Load modes of payment list
     *
     * @param bool $booKeysOnly
     * @return array
     */
    public function getModesOfPaymentList($booKeysOnly = false)
    {
        $arrModes = array(
            array('modeId' => '', 'modeName' => 'All')
        );

        // Load modes from DB
        $select = (new Select())
            ->from(array('i' => 'company_invoice'))
            ->columns(['mode_of_payment'])
            ->where([(new Where())->isNotNull('mode_of_payment')])
            ->group('mode_of_payment');

        $arrSavedModes = $this->_db2->fetchCol($select);

        foreach ($arrSavedModes as $modeName) {
            $arrModes[] = array('modeId' => $modeName, 'modeName' => $modeName);
        }

        // In some cases we need only keys
        if ($booKeysOnly) {
            $arrResult = array();
            foreach ($arrModes as $arrMode) {
                $arrResult[] = $arrMode['modeId'];
            }

            $arrModes = $arrResult;
        }

        return $arrModes;
    }

    /**
     * Load company invoices list
     *
     * @param int $companyId
     * @return array
     */
    public function getCompanyInvoices($companyId)
    {
        $select = (new Select())
            ->from('company_invoice')
            ->where(['company_id' => (int)$companyId, 'deleted' => 'N'])
            ->order('company_invoice_id DESC');

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load prospect-related invoices
     *
     * @param array $arrProspectIds
     * @return array
     */
    public function getProspectsInvoices($arrProspectIds)
    {
        $arrInvoices = array();
        if (is_array($arrProspectIds) && count($arrProspectIds)) {
            $select = (new Select())
                ->from('company_invoice')
                ->where(['prospect_id' => $arrProspectIds]);

            $arrInvoices = $this->_db2->fetchAll($select);
        }

        return $arrInvoices;
    }

    /**
     * Load detailed info about invoice
     *
     * @param int $companyInvoiceId
     * @param bool $booCheckIfDeleted
     * @param bool $booExtendedInfo
     * @return array
     */
    public function getInvoiceDetails($companyInvoiceId, $booCheckIfDeleted = true, $booExtendedInfo = true)
    {
        $arrWhere                       = [];
        $arrWhere['company_invoice_id'] = (int)$companyInvoiceId;

        if ($booCheckIfDeleted) {
            $arrWhere['deleted'] = 'N';
        }

        $select = (new Select())
            ->from('company_invoice')
            ->where($arrWhere);

        $arrInvoiceInfo = $this->_db2->fetchRow($select);

        // Load additional info for invoice
        if (count($arrInvoiceInfo) && $booExtendedInfo) {
            if (!empty($arrInvoiceInfo['company_id'])) {
                $arrCompanyDetails                       = $this->_parent->getCompanyAndDetailsInfo($arrInvoiceInfo['company_id']);
                $arrInvoiceInfo['payment_term']          = $arrCompanyDetails['payment_term'];
                $arrInvoiceInfo['subscription']          = $arrCompanyDetails['subscription'];
                $arrInvoiceInfo['paymentech_profile_id'] = $arrCompanyDetails['paymentech_profile_id'];
                $arrInvoiceInfo['companyName']           = $arrCompanyDetails['companyName'];
                $arrInvoiceInfo['pricing_category_id']   = $arrCompanyDetails['pricing_category_id'];
            } elseif (!empty($arrInvoiceInfo['prospect_id'])) {
                $prospects                               = $this->_serviceContainer->get(Prospects::class);
                $arrProspectDetails                      = $prospects->getProspectInfo($arrInvoiceInfo['prospect_id']);
                $arrInvoiceInfo['payment_term']          = $arrProspectDetails['payment_term'];
                $arrInvoiceInfo['subscription']          = $arrProspectDetails['package_type'];
                $arrInvoiceInfo['paymentech_profile_id'] = $arrProspectDetails['paymentech_profile_id'];
                $arrInvoiceInfo['companyName']           = $arrProspectDetails['company'];
                $arrInvoiceInfo['pricing_category_id']   = $arrProspectDetails['pricing_category_id'];
            }
        }

        return $arrInvoiceInfo;
    }

    /**
     * Update invoice's status (failed or complete)
     *
     * @param int $companyInvoiceId
     * @param bool $booComplete
     * @return int
     */
    public function updateInvoiceStatus($companyInvoiceId, $booComplete = true)
    {
        $arrStatus = array(
            'status' => $booComplete ? 'C' : 'F'
        );

        return $this->updateInvoice($arrStatus, $companyInvoiceId);
    }


    /**
     * Update/save last error code
     *
     * @param int $intInvoiceId - invoice id
     * @param string $strCode - code received form PT
     * @return int count records updated
     */
    public function updateInvoicePTErrorCode($intInvoiceId, $strCode)
    {
        if ($this->_config['payment']['method'] == 'payway') {
            $arrOkCodes = array('00', '08');
        } else {
            $arrOkCodes = array('00');
        }
        $arrUpdate = array(
            'last_PT_error_code' => empty($strCode) || in_array($strCode, $arrOkCodes) ? null : $strCode
        );

        return $this->updateInvoice($arrUpdate, $intInvoiceId);
    }

    /**
     * Mark invoice as unpaid
     *
     * @param int $companyInvoiceId
     * @return int
     */
    public function markInvoiceUnpaid($companyInvoiceId)
    {
        return $this->updateInvoice(array('status' => 'U'), $companyInvoiceId);
    }


    /**
     * Generate readable invoice status
     *
     * @param  $invoiceStatus - C, Q or F
     * @param bool $booUseHtml - true to use html formatted result
     * @return string readable status
     */
    public function getInvoiceReadableStatus($invoiceStatus, $booUseHtml = true)
    {
        switch ($invoiceStatus) {
            case 'C':
                $strStatus = 'Complete';
                $strColor  = 'green';
                break;

            case 'F':
                $strStatus = 'Failed';
                $strColor  = 'red';
                break;

            case 'U':
                $strStatus = 'Unpaid';
                $strColor  = 'red';
                break;

            default:
                $strStatus = 'Queued';
                $strColor  = 'orange';
                break;
        }

        return $booUseHtml ? sprintf('<span %s>%s</span>', "style='color: $strColor;'", $strStatus) : $strStatus;
    }


    /**
     * Mark invoice as deleted
     *
     * @param int $companyInvoiceId - invoice id
     * @param bool $booDeleted true to mark as deleted
     * @return int count records updated
     */
    public function markInvoiceAsDeleted($companyInvoiceId, $booDeleted = true)
    {
        $arrStatus = array(
            'deleted' => $booDeleted ? 'Y' : 'N'
        );

        return $this->updateInvoice($arrStatus, $companyInvoiceId);
    }


    /**
     * Delete invoices
     *
     * @param array $arrInvoicesIds
     * @return bool true on success
     */
    public function deleteInvoices($arrInvoicesIds)
    {
        try {
            if (count($arrInvoicesIds)) {
                $this->_db2->delete('company_invoice', ['company_invoice_id' => $arrInvoicesIds]);
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }


    /**
     * Create new invoice with provided data
     *
     * @param array $arrInvoiceData - invoice details
     * @return int invoice id
     */
    public function insertInvoice($arrInvoiceData)
    {
        return $this->_db2->insert('company_invoice', $arrInvoiceData);
    }


    /**
     * Update invoice with provided data
     *
     * @param array $arrInvoiceData - invoice details
     * @param int $invoiceId - invoice id
     * @return int count records updated
     */
    public function updateInvoice($arrInvoiceData, $invoiceId)
    {
        return $this->_db2->update(
            'company_invoice',
            $arrInvoiceData,
            ['company_invoice_id' => $invoiceId]
        );
    }


    /**
     * Update invoice with provided data (for prospect)
     *
     * @param array $arrInvoiceData - invoice details
     * @param int $prospectId - prospect id
     * @return int count records updated
     */
    public function updateProspectInvoice($arrInvoiceData, $prospectId)
    {
        return $this->_db2->update(
            'company_invoice',
            $arrInvoiceData,
            ['prospect_id' => $prospectId]
        );
    }


    /**
     * Generate unique invoice number (there is a check in DB)
     * E.g.: 201105251653360
     *
     * @return string unique invoice number
     */
    public function generateUniqueInvoiceNumber()
    {
        $i = 0;
        do {
            $invoiceNumber = date('YmdHis') . $i++;
        } while ($this->checkInvoiceExistsByNumber($invoiceNumber));

        return $invoiceNumber;
    }


    /**
     * Check if there is invoice with specific number
     *
     * @param string $strInvoiceNumber
     * @return bool true if invoice exists
     */
    public function checkInvoiceExistsByNumber($strInvoiceNumber)
    {
        $select = (new Select())
            ->from('company_invoice')
            ->columns(['count' => new Expression('COUNT(*)')])
            ->where(['invoice_number' => $strInvoiceNumber]);

        return $this->_db2->fetchOne($select) > 0;
    }


    /**
     * Charge invoice
     *
     * @param int $intInvoiceId
     * @param array $arrInvoiceInfo - invoice info
     * @param bool $booSendRequestToPT - true to send request to PT
     * @return array
     */
    public function chargeSavedInvoice($intInvoiceId, $arrInvoiceInfo = array(), $booSendRequestToPT = true)
    {
        $arrOrderResult = array(
            'error'   => false,
            'code'    => '',
            'message' => ''
        );

        try {
            $strError = '';

            if ($booSendRequestToPT) {
                if (!$this->_config['payment']['enabled']) {
                    // Payment is not enabled
                    $strError = $this->_tr->translate('Communication with PT is turned off. Please turn it on in config file and try again.');
                }
            }

            if (empty($strError) && !count($arrInvoiceInfo)) {
                $arrInvoiceInfo = $this->getInvoiceDetails($intInvoiceId, false);
                if (!count($arrInvoiceInfo)) {
                    $strError = $this->_tr->translate('Invoice was not found');
                }
            }

            if (empty($strError) && $booSendRequestToPT && empty($arrInvoiceInfo['paymentech_profile_id'])) {
                $strError = $this->_tr->translate('Payment profile id is empty.');
            }

            if (empty($strError)) {
                // Update charge date
                $arrInvoiceUpdate = array(
                    'invoice_posted_date' => date('c')
                );

                if ($booSendRequestToPT) {
                    $this->_payment->init();

                    // Send request to PT to charge money
                    $arrPTParams    = array(
                        'orderId'        => $this->_payment->generatePaymentOrderId($arrInvoiceInfo['invoice_number']),
                        'customerRefNum' => $arrInvoiceInfo['paymentech_profile_id'],
                        'amount'         => $arrInvoiceInfo['total'],
                        'comments'       => $this->_settings->br2nl($arrInvoiceInfo['companyName']), // will be truncated to 64 chars...
                        'traceNumber'    => $this->_payment->generatePaymentTraceNumber()
                    );
                    $arrOrderResult = $this->_payment->chargeAmountBasedOnProfile($arrPTParams);

                    if (!$arrOrderResult['error']) {
                        // Mark invoice that it was charged via PT
                        $arrInvoiceUpdate['sent_request_to_PT'] = 'Y';
                    }
                }

                $this->updateInvoice($arrInvoiceUpdate, $intInvoiceId);

                // Update invoice status
                $this->updateInvoiceStatus($intInvoiceId, !$arrOrderResult['error']);
            }

            // Update PT error code
            $this->updateInvoicePTErrorCode($intInvoiceId, $arrOrderResult['code']);
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $arrOrderResult = array(
                'error'   => true,
                'message' => $strError,
                'code'    => ''
            );
        }

        return $arrOrderResult;
    }

    /**
     * Select all companies with last failed invoices
     *
     * @param string $strCompanyQuery
     * @param int $start - number to limit from
     * @param int $limit - number of invoices to load at once
     * @return array invoices
     */
    public function getFailedInvoices($strCompanyQuery = '', $start = 0, $limit = 0)
    {
        if (!is_numeric($start) || $start <= 0) {
            $start = 0;
        }

        if (!is_numeric($limit) || $limit <= 0) {
            $limit = $this->intShowCompaniesPerPage;
        }

        $arrWhere             = [];
        $arrWhere['i.status'] = 'F';

        if (!empty($strCompanyQuery)) {
            $arrWhere[] = (new Where())->like('c.companyName', "%$strCompanyQuery%");
        }
        $select = (new Select())
            ->from(array('i' => 'company_invoice'))
            ->join(array('c' => 'company'), 'c.company_id = i.company_id', array('companyName', 'company_status' => 'status', 'company_abn'), Select::JOIN_LEFT_OUTER)
            ->join(array('d' => 'company_details'), 'c.company_id = d.company_id', array('show_expiration_dialog_after'), Select::JOIN_LEFT_OUTER)
            ->join(array('m' => 'members'), 'c.admin_id = m.member_id', array('admin_name' => new Expression('CONCAT(m.fName, " ", m.lName)')), Select::JOIN_LEFT_OUTER)
            ->join(array('log' => new Expression('(SELECT * FROM automated_billing_log ORDER BY log_id DESC)')), 'log.log_invoice_id = i.company_invoice_id', Select::SQL_STAR, Select::JOIN_LEFT_OUTER)
            ->where($arrWhere)
            ->group('i.company_invoice_id')
            ->order(array('c.companyName ASC', 'i.company_invoice_id DESC'));

        $arrInvoices = $this->_db2->fetchAll($select);

        // Group invoices by companies
        $arrCompanies = array();
        foreach ($arrInvoices as $arrInvoiceInfo) {
            $arrCompanies[$arrInvoiceInfo['company_id']][] = $arrInvoiceInfo;
        }

        // Filter invoices
        $arrLastFailedInvoices = array();
        foreach ($arrCompanies as $companyId => $arrInvoices) {
            foreach ($arrInvoices as $arrInvoiceInfo) {
                $arrLastFailedInvoices[$companyId][] = $arrInvoiceInfo;
            }
        }

        // Limit found invoices
        $countFailedInvoices = count($arrLastFailedInvoices);

        $i = 0;

        $arrFilteredFailedInvoices = array();
        foreach ($arrLastFailedInvoices as $companyId => $arrFailedInvoices) {
            if ($i >= $start && $i < ($start + $limit)) {
                $arrFilteredFailedInvoices[$companyId] = $arrFailedInvoices;
            }
            $i++;
        }
        // $arrLastFailedInvoices

        return array(
            $arrFilteredFailedInvoices,
            $countFailedInvoices
        );
    }

    /**
     * Load invoices list
     *
     * @param array $arrFilterData - invoices will be filtered
     * @param string $sort - field for sorting
     * @param string $dir - sorting direction
     * @param int $start - number to limit from
     * @param int $limit - number of invoices to load at once
     * @param bool $booHtml - true to load with html
     *
     * @return array result with filtered invoices and total records
     */
    public function getInvoicesList($arrFilterData = array(), $sort = '', $dir = 'DESC', $start = 0, $limit = 50, $booHtml = true)
    {
        if (!in_array($dir, array('ASC', 'DESC'))) {
            $dir = 'DESC';
        }

        switch ($sort) {
            case 'invoice_subject':
                $sort = 'i.subject';
                break;

            case 'invoice_posted_date':
                $sort = 'i.invoice_posted_date';
                break;

            case 'invoice_number':
                $sort = 'i.invoice_number';
                break;

            case 'invoice_company':
                $sort = 'c.companyName';
                break;

            case 'invoice_gross':
                $sort = 'i.total';
                break;

            case 'invoice_net':
                $sort = 'i.subtotal';
                break;

            case 'invoice_tax':
                $sort = 'i.tax';
                break;

            case 'invoice_date':
            default:
                $sort = 'i.invoice_date';
                break;
        }

        if (!is_numeric($start) || $start <= 0) {
            $start = 0;
        }

        if (!is_numeric($limit) || $limit <= 0) {
            $limit = $this->intShowInvoicesPerPage;
        }

        $arrResultInvoices = array();
        $totalRecords      = 0;
        $strPeriod         = '';
        try {
            $select = (new Select())
                ->from(array('i' => 'company_invoice'))
                ->join(array('c' => 'company'), 'c.company_id = i.company_id', array('companyName'), Select::JOIN_LEFT_OUTER)
                ->join(array('p' => 'prospects'), 'p.prospect_id = i.prospect_id', array('company'), Select::JOIN_LEFT_OUTER)
                ->where(['i.sent_request_to_PT' => 'Y'])
                ->limit($limit)
                ->offset($start)
                ->order(array($sort . ' ' . $dir));

            // Apply filters if needed
            switch ($arrFilterData['filter_date_by']) {
                case 'today':
                    $strPeriod = ' for today';
                    $select->where(['i.invoice_posted_date' => date('Y-m-d')]);
                    break;

                case 'from_to':
                    if (!empty($arrFilterData['filter_date_from'])) {
                        $strDate = $this->_settings->toUnixDate($arrFilterData['filter_date_from']);
                        if ($strDate != '0000-00-00') {
                            $strPeriod = ' from ' . $this->_settings->formatDate($strDate);
                            $select->where([(new Where())->greaterThanOrEqualTo('i.invoice_posted_date', $strDate)]);
                        }
                    }

                    if (!empty($arrFilterData['filter_date_to'])) {
                        $strDate = $this->_settings->toUnixDate($arrFilterData['filter_date_to']);
                        if ($strDate != '0000-00-00') {
                            $strPeriod .= ' to ' . $this->_settings->formatDate($strDate);
                            $select->where([(new Where())->lessThanOrEqualTo('i.invoice_posted_date', $strDate)]);
                        }
                    }
                    break;

                case 'year':
                    $strPeriod = ' for this year';
                    $select->where(
                        [
                            (new Where())->greaterThanOrEqualTo('i.invoice_posted_date', date('Y-01-01')),
                            (new Where())->lessThanOrEqualTo('i.invoice_posted_date', date('Y-12-31'))
                        ]
                    );
                    break;

                case 'month':
                default:
                    $strPeriod = ' for this month';
                    $select->where(
                        [
                            (new Where())->greaterThanOrEqualTo('i.invoice_posted_date', date('Y-m-01')),
                            (new Where())->lessThanOrEqualTo('i.invoice_posted_date', date('Y-m-t'))
                        ]
                    );
                    break;
            }


            if (!empty($arrFilterData['filter_company'])) {
                $strQuery  = (new Where())->like('c.companyName', '%' . $arrFilterData['filter_company'] . '%');
                $strQuery2 = (new Where())->like('p.company', '%' . $arrFilterData['filter_company'] . '%');
                $select->where([(new Where())->nest()->addPredicates([$strQuery, $strQuery2], Where::OP_OR)->unnest()]);
            }

            if (!empty($arrFilterData['filter_mode_of_payment']) && in_array($arrFilterData['filter_mode_of_payment'], $this->getModesOfPaymentList(true))) {
                $select->where(['i.mode_of_payment' => $arrFilterData['filter_mode_of_payment']]);
            }

            if (!empty($arrFilterData['filter_product']) && in_array($arrFilterData['filter_product'], $this->getProductsList(true))) {
                $select->where(['i.product' => $arrFilterData['filter_product']]);
            }


            // Run query
            $arrInvoices  = $this->_db2->fetchAll($select);
            $totalRecords = $this->_db2->fetchResultsCount($select);

            // Format found invoices
            foreach ($arrInvoices as $arrInvoiceInfo) {
                $subtotal = $arrInvoiceInfo['total'] - $arrInvoiceInfo['tax'];
                $subtotal = max($subtotal, 0);

                $arrResultInvoices[] = array(
                    'invoice_id'              => $arrInvoiceInfo['company_invoice_id'],
                    'invoice_subject'         => $arrInvoiceInfo['subject'],
                    'invoice_number'          => $arrInvoiceInfo['invoice_number'],
                    'invoice_date'            => $arrInvoiceInfo['invoice_date'],
                    'invoice_posted_date'     => $arrInvoiceInfo['invoice_posted_date'],
                    'invoice_company'         => empty($arrInvoiceInfo['companyName']) ? $arrInvoiceInfo['company'] : $arrInvoiceInfo['companyName'],
                    'invoice_company_id'      => $arrInvoiceInfo['company_id'],
                    'invoice_prospect_id'     => $arrInvoiceInfo['prospect_id'],
                    'invoice_product'         => $arrInvoiceInfo['product'],
                    'invoice_mode_of_payment' => $arrInvoiceInfo['mode_of_payment'],
                    'invoice_status'          => $this->getInvoiceReadableStatus($arrInvoiceInfo['status'], $booHtml),

                    // Gross is the total amount on the invoice
                    'invoice_gross'           => $arrInvoiceInfo['total'],

                    // Net is the Gross - GST/HST
                    'invoice_net'             => $subtotal,

                    // Tax is GST/HST
                    'invoice_tax'             => $arrInvoiceInfo['tax'],
                );
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'rows'       => $arrResultInvoices,
            'totalCount' => $totalRecords,
            'period'     => $strPeriod
        );
    }


    /**
     * Generate excel report and output it in browser
     *
     * @param array $arrInvoices - invoices list
     * @param string $title will be showed at the top of Excel file
     * @return Spreadsheet
     */
    public function createInvoicesExcelReport($arrInvoices, $title)
    {
        // Turn off warnings - issue when generate xls file
        error_reporting(E_ERROR);

        set_time_limit(60 * 60); // 1 hour
        ini_set('memory_limit', '512M');

        $worksheetName = Files::checkPhpExcelFileName($title);
        $worksheetName = empty($worksheetName) ? 'Export Result' : $worksheetName;

        $abc     = array('A');
        $current = 'A';
        while ($current != 'ZZZ') {
            $abc[] = ++$current;
        }

        // Creating an object
        $objPHPExcel = new Spreadsheet();

        // Set properties
        $objPHPExcel->getProperties()->setTitle($worksheetName);
        $objPHPExcel->getProperties()->setSubject($worksheetName);

        $objPHPExcel->setActiveSheetIndex(0);
        $sheet = $objPHPExcel->getActiveSheet();

        // column sizes
        $sizes = array(12, 44, 17, 38, 8, 13, 12);
        foreach ($sizes as $key => $size) {
            $sheet->getColumnDimension($abc[$key])->setWidth($size);
        }

        // all cells styles
        $bottom_right_cell = 'J' . (count($arrInvoices) + 5);

        $sheet->getStyle('A1:' . $bottom_right_cell)->getFont()->setName('Arial');
        $sheet->getStyle('A1:' . $bottom_right_cell)->getFont()->setSize(10);
        $sheet->getStyle('A1:' . $bottom_right_cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // header styles
        $sheet->getStyle('A1')->getFont()->setBold(true);

        // headers styles
        $sheet->getStyle('A5:J5')->getFont()->setBold(true);
        $sheet->getStyle('A5:J5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A5:J5')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('A5:J5')->getAlignment()->setWrapText(true); // wrap!
        $sheet->getStyle('A5:J5')->applyFromArray(
            [
                'fillType'   => Fill::FILL_SOLID,
                'rotation'   => 0,
                'startColor' => [
                    'rgb' => 'DBDBDB'
                ],
                'endColor'   => [
                    'argb' => 'DBDBDB'
                ]
            ]
        );

        // money styles
        $sheet->getStyle('G6:I' . (count($arrInvoices) + 5))->getNumberFormat()->setFormatCode('$###0.00');

        // output header
        $sheet->mergeCells("A1:B1"); // colspan
        $sheet->mergeCells("A3:B3");

        $sheet->setCellValue('A1', $worksheetName);
        $sheet->setCellValue('A3', 'Report Date: ' . date('M d, Y'));

        // output headers
        $headers = array('Date', 'Subject', 'Invoice Number', 'Company/Prospect', 'Product', 'Mode of Payment', 'Gross', 'Net', 'Tax', 'Status',);

        foreach ($headers as $key => $h) {
            $sheet->setCellValue($abc[$key] . '5', $h);
        }

        // output
        foreach ($arrInvoices as $key => $arrInvoice) {
            $sheet->setCellValue('A' . ($key + 6), $arrInvoice['invoice_posted_date']);
            $sheet->setCellValue('B' . ($key + 6), $arrInvoice['invoice_subject']);
            $sheet->setCellValueExplicit('C' . ($key + 6), $arrInvoice['invoice_number'], DataType::TYPE_STRING);
            $sheet->setCellValue('D' . ($key + 6), $arrInvoice['invoice_company']);
            $sheet->setCellValue('E' . ($key + 6), $arrInvoice['invoice_product']);
            $sheet->setCellValue('F' . ($key + 6), $arrInvoice['invoice_mode_of_payment']);
            $sheet->setCellValue('G' . ($key + 6), $arrInvoice['invoice_gross']);
            $sheet->setCellValue('H' . ($key + 6), $arrInvoice['invoice_net']);
            $sheet->setCellValue('I' . ($key + 6), $arrInvoice['invoice_tax']);
            $sheet->setCellValue('J' . ($key + 6), $arrInvoice['invoice_status']);
        }

        // Rename sheet
        $sheet->setTitle($worksheetName);

        return $objPHPExcel;
    }

    /**
     * Run recurring invoice for one or several companies
     *
     * @param array $arrCompanies - information for each company from company_details table
     * @param bool $booManualProcess - if false email will be not sent on error to company admin
     * @return array
     */
    public function createInvoice($arrCompanies, $booManualProcess = true)
    {
        $strErrorMessage    = ''; // Will be used for end user
        $arrCompaniesResult = array();
        $arrCharges         = array();

        // Sum errors count in one row
        // If this count is more than X (will be used from config) -
        // So we exit and send email to support
        $errorsCountInRow = 0;
        $maxErrorsAtOnce  = $this->_config['payment']['recurring_errors_in_row'];

        try {
            $template = SystemTemplate::loadOne(['title' => 'Recurring Invoice']);

            $intFailedCompanies = 0;
            foreach ($arrCompanies as $arrCompanyInfo) {
                $companyId = $arrCompanyInfo['company_id'];

                if (!$booManualProcess) {
                    // Mark company as 'suspended'
                    // if the first failed invoice creation date is more than X days
                    $strFirstFailedInvoiceDate = $this->getCompanyFirstFailedInvoiceDate($companyId);
                    if (!empty($strFirstFailedInvoiceDate)) {
                        $cuttingOfServiceDays = $this->_settings->variableGet('cutting_of_service_days', 30);
                        $maxDays              = $cuttingOfServiceDays * 24 * 60 * 60;
                        if ((strtotime($strFirstFailedInvoiceDate) + $maxDays) < time()) {
                            $this->_parent->updateCompanyStatus(
                                isset($arrCompanyInfo['Status']) ? $this->_parent->getCompanyStringStatusById($arrCompanyInfo['Status']) : '',
                                'suspend',
                                $companyId
                            );
                            continue;
                        }
                    }
                }

                $booUpdateCompanyNextBillingDate = false;
                if (!$booManualProcess) {
                    // Calculate Next Billing Date
                    switch ($arrCompanyInfo['payment_term']) {
                        case '1': // Monthly, 1 month
                            $intDays  = 10;
                            $intMonth = 1;
                            break;

                        case '2': // Annually, 12 months
                            $intDays  = 355;
                            $intMonth = 12;
                            break;

                        case '3': // Biannually, 24 months
                            $intDays  = 710;
                            $intMonth = 24;
                            break;

                        default: // Unknown
                            $intDays  = 0;
                            $intMonth = 0;
                            break;
                    }

                    $nextBillingDate = '';
                    if (!empty($intDays)) {
                        $baseTime = 0;

                        // Check what start date we need use
                        // for next billing date calculation
                        if (!empty($arrCompanyInfo['next_billing_date'])) {
                            $savedNextBillingTime = strtotime($arrCompanyInfo['next_billing_date']);

                            $strXDaysAgo = sprintf('-%d days', $intDays);
                            if ($savedNextBillingTime && $savedNextBillingTime >= strtotime($strXDaysAgo)) {
                                $baseTime = $savedNextBillingTime;
                            }
                        }

                        if (empty($baseTime)) {
                            $baseTime = time();
                        }

                        $nextBillingDate = $this->_settings->getXMonthsToTheFuture($baseTime, $intMonth);
                        $nextBillingDate = date('Y-m-d H:i:s', $nextBillingDate);

                        $booUpdateCompanyNextBillingDate = true;
                    }
                } else {
                    // Not changed
                    $nextBillingDate = $arrCompanyInfo['next_billing_date'];
                }

                $invoiceData    = $this->prepareDataForRecurringInvoice($companyId);
                $companyDetails = $this->_parent->getCompanyAndDetailsInfo($companyId);

                if (!empty($nextBillingDate)) {
                    $invoiceData['next_billing_date']    = $nextBillingDate;
                    $companyDetails['next_billing_date'] = $nextBillingDate;
                }

                /** @var Members $members */
                $members   = $this->_serviceContainer->get(Members::class);
                $adminInfo = $members->getMemberInfo($companyDetails['admin_id']);

                $replacements = $this->_systemTemplates->getGlobalTemplateReplacements();
                $replacements += $this->_parent->getTemplateReplacements($companyDetails, $adminInfo);
                $replacements += $this->getTemplateReplacements($invoiceData);

                $processedTemplate = $this->_systemTemplates->processTemplate($template, $replacements);

                // Create invoice in officio DB
                $invoiceData = array(
                    'company_id'                 => $companyId,
                    'invoice_number'             => $this->generateUniqueInvoiceNumber(),
                    'subscription_fee'           => round($invoiceData['subscription_fee'], 2),
                    'support_fee'                => round($invoiceData['support_fee'], 2),
                    'invoice_date'               => date('Y-m-d'),
                    'free_users'                 => $invoiceData['free_users'],
                    'additional_users'           => $invoiceData['additional_users'],
                    'additional_users_fee'       => round($invoiceData['additional_users_fee'], 2),
                    'additional_storage'         => $invoiceData['additional_storage'],
                    'additional_storage_charges' => round($invoiceData['additional_storage_charges'], 2),
                    'subtotal'                   => round($invoiceData['subtotal'], 2),
                    'tax'                        => round($invoiceData['gst'], 2),
                    'total'                      => round($invoiceData['total'], 2),
                    'message'                    => $processedTemplate->template,
                    'subject'                    => $processedTemplate->subject
                );

                if (array_key_exists('prospect_id', $arrCompanyInfo)) {
                    $invoiceData['prospect_id'] = $arrCompanyInfo['prospect_id'];
                }

                $invoiceId = $this->insertInvoice($invoiceData);

                // Charge money
                $booSendRequestToPT = true;

                $invoiceData['paymentech_profile_id'] = $arrCompanyInfo['paymentech_profile_id'];
                $invoiceData['companyName']           = $arrCompanyInfo['companyName'];
                $invoiceData['company_abn']           = $arrCompanyInfo['company_abn'];
                $arrOrderResult                       = $this->chargeSavedInvoice($invoiceId, $invoiceData, $booSendRequestToPT);
                $booSuccess                           = !$arrOrderResult['error'];

                $strErrorMessage = '';
                $strErrorCode    = '';
                if (!$booSuccess) {
                    // Save last error message
                    $strErrorMessage = $arrOrderResult['message'];
                    $strErrorCode    = $arrOrderResult['code'];

                    /*
                    // Inform company admin or Officio support -
                    // related to template's settings
                    $arrParams = array(
                        'company_id'    => $companyId,
                        'error_message' => $strErrorMessage
                    );
                    $parsedTemplate = $this->getParsedCCProcessFailedTemplate($arrParams);
                    if(!$booManualProcess) {
                        $this->send($parsedTemplate);
                    }
                    */

                    if ($booManualProcess) {
                        // Delete invoice
                        $this->deleteInvoices(array($invoiceId));
                    }
                } else {
                    // Update invoice's mode of payment and status
                    $arrInvoiceUpdate = array(
                        'mode_of_payment' => $arrCompanyInfo['paymentech_mode_of_payment']
                    );
                    $this->updateInvoice($arrInvoiceUpdate, $invoiceId);


                    // Create PDF
                    $this->_parent->createInvoicePDF($processedTemplate->template, $invoiceData);

                    // Reset counter
                    $errorsCountInRow = 0;
                }

                if (!$booManualProcess || $booSuccess) {
                    // Update next billing date
                    if ($booUpdateCompanyNextBillingDate) {
                        $this->_parent->updateCompanyDetails(
                            $companyId,
                            array('next_billing_date' => $nextBillingDate)
                        );
                    }
                } else {
                    // Not changed
                    $nextBillingDate = $arrCompanyInfo['next_billing_date'];
                }

                $arrCompanyResult = array(
                    'retry'                    => false,
                    'company_id'               => $companyId,
                    'company_name'             => $arrCompanyInfo['companyName'],
                    'company_show_dialog_date' => $arrCompanyInfo['show_expiration_dialog_after'],
                    'invoice_id'               => $invoiceId,
                    'amount'                   => $invoiceData['total'],
                    'old_billing_date'         => $arrCompanyInfo['next_billing_date'],
                    'new_billing_date'         => $nextBillingDate,
                    'status'                   => $booSuccess ? 'C' : 'F',
                    'error_code'               => $strErrorCode,
                    'error_message'            => $strErrorMessage,
                );

                $arrCompaniesResult[] = $arrCompanyResult;

                // Save
                $arrCompanyResult['success'] = $booSuccess;
                $arrCharges[]                = $arrCompanyResult;

                if (!$booSuccess) {
                    $intFailedCompanies++;
                }

                // Check if X times at row we get errors - send email and exit
                if ($arrOrderResult['error']) {
                    $errorsCountInRow++;
                    /*
                    if ($errorsCountInRow >= $maxErrorsAtOnce) {
                        // Send email to support
                        $support = $this->_settings->getOfficioSupportEmail();
                        $arrEmailInfo = array(
                            'email'   => $support['email'],
                            'subject' => "[ERROR] during processing $maxErrorsAtOnce companies in row",
                            'message' => "An error happened during processing $maxErrorsAtOnce companies in row.<br/>Please check log files."
                        );
                        $this->send($arrEmailInfo);

                        // Don't process other companies
                        break;
                    }
                    */
                }
            } // foreach
        } catch (Exception $e) {
            $strErrorMessage     = 'Server Error';
            $strRealErrorMessage = $e->getMessage();
            $this->_log->debugPaymentErrorToFile($strRealErrorMessage);
        }

        return array(
            'success'            => empty($strErrorMessage),
            'message'            => $strErrorMessage,
            'arrCompaniesResult' => $arrCompaniesResult,
            'arrCharges'         => $arrCharges,
        );
    }

    public function prepareDataForRecurringInvoice($companyId, $prospectId = null)
    {
        // get company data from db
        $arrCompanyData = $this->_parent->getCompanyAndDetailsInfo($companyId);

        // try to get prospect info
        // TODO Should be removed from here
        if (is_null($prospectId)) {
            $select = (new Select())
                ->from('prospects')
                ->where(['company_id' => (int)$companyId]);

            $arrProspectData = $this->_db2->fetchRow($select);
            $prospectId      = $arrProspectData['prospect_id'] ?? 0;
        } else {
            $select          = (new Select())
                ->from('prospects')
                ->where(['prospect_id' => (int)$prospectId]);
            $arrProspectData = $this->_db2->fetchRow($select);
        }

        // Merge data BUT company data is more important than prospect data
        $data = array_merge($arrProspectData, $arrCompanyData);

        //get support fee value
        $supportFee = $data['support_fee'];
        if (empty($supportFee) && isset($data['support'])) { //first invoice
            $supportFee = $this->_parent->getPackages()->getSupportFee($data['payment_term'], $data['support'], $supportFee);
        }

        // Only for Recurring Invoice
        $activeUsers     = $this->_parent->calculateActiveUsers($companyId);
        $additionalUsers = $this->_parent->calculateAdditionalUsers($activeUsers, $data['free_users']);

        // users fee
        $additionalUsersFee = $this->_parent->calculateAdditionalUsersFee($companyId, $additionalUsers, $data['payment_term'], $data['pricing_category_id']);

        // storage
        $additionalStorage = $this->_parent->calculateAdditionalStorage($companyId, $data['free_storage']);

        // storage fee
        $additionalStorageFee = $this->_parent->calculateAdditionalStorageFee($additionalStorage, $data['payment_term']);

        // subtotal
        $subtotal = $data['subscription_fee'] + $additionalUsersFee + $additionalStorageFee;
        $subtotal = round((double)$subtotal, 2);

        // gst/hst
        $gstType = $data['gst_type'];
        if ($data['gst_type'] == 'auto') {
            $gstType = $data['gst_default_type'];
        }

        $arrCalculatedGst = $this->_gstHst->calculateGstAndSubtotal($gstType, $data['gst_used'], $subtotal);
        $gst              = round($arrCalculatedGst['gst'], 2);
        $subtotal         = round($arrCalculatedGst['subtotal'], 2);

        //total
        $total = $subtotal + $gst;
        $total = round($total, 2);

        // First invoice
        if (!empty($prospectId) && (empty($subtotal) || empty($total))) {
            // Load/use extra users count and fee from the prospect's info
            $additionalUsers = (int)$data['extra_users'];

            // Users fee
            $additionalUsersFee = round(
                $this->_parent->calculateAdditionalUsersFeeBySubscription(
                    $data['package_type'],
                    $additionalUsers,
                    $data['payment_term'],
                    $data['pricing_category_id']
                ),
                2
            );

            // Subtotal
            $subtotal = round($data['subscription_fee'] + $additionalUsersFee + $supportFee, 2);

            $arrGstInfo       = $this->_gstHst->getGstByCountryAndProvince($data['country'], $data['state']);
            $arrCalculatedGst = $this->_gstHst->calculateGstAndSubtotal($arrGstInfo['gst_type'], $arrGstInfo['gst_rate'], $subtotal);
            $gst              = round($arrCalculatedGst['gst'], 2);
            $subtotal         = round($arrCalculatedGst['subtotal'], 2);

            // Total
            $total = round($subtotal + $gst, 2);
        }

        return array(
            'payment_term'               => $data['payment_term'],
            'subscription_fee'           => $data['subscription_fee'],
            'subscription'               => $data['subscription'] ?? $data['package_type'],
            'support_fee'                => $supportFee,
            'free_users'                 => $data['free_users'],
            'free_clients'               => $data['free_clients'],
            'free_storage'               => $data['free_storage'],
            'gst'                        => $gst,
            'subtotal'                   => $subtotal,
            'total'                      => $total,
            'additional_users'           => $additionalUsers,
            'additional_users_fee'       => $additionalUsersFee,
            'additional_storage'         => $additionalStorage,
            'additional_storage_charges' => $additionalStorageFee,
            'pricing_category_id'        => $data['pricing_category_id']
        );
    }

    /**
     * Provides replacements for a template
     * @param array $data
     * @return array
     */
    public function getTemplateReplacements($data, $templateType = self::TEMPLATE_DEFAULT)
    {
        if ($templateType == self::TEMPLATE_SPECIAL_CC_CHARGE) {
            return [
                '{special cc charge: net}'    => $data['net'] ?? '',
                '{special cc charge: tax}'    => $data['tax'] ?? '',
                '{special cc charge: amount}' => $data['amount'] ?? '',
                '{special cc charge: notes}'  => $data['notes'] ?? '',
            ];
        } else {
            // Use default values if needed
            $defaultValues = array(
                'invoice_number'             => $this->_parent->getCompanyInvoice()->generateUniqueInvoiceNumber(),
                'amount_paid'                => $data['total'],
                'invoice_date'               => $this->_settings->formatDate(date('Y-m-d')),
                'price_per_user'             => $this->_parent->getUserPrice($data['payment_term'], $data['subscription'], $data['pricing_category_id']),
                'price_per_storage'          => $this->_parent->getStoragePrice($data['payment_term']),
                'additional_users'           => 0,
                'additional_users_fee'       => 0,
                'additional_storage'         => 0,
                'additional_storage_charges' => 0,
                'quantity'                   => 1,
                'discount'                   => 0,
                'support_fee'                => 0,
                'currency'                   => $this->_settings->getCurrentCurrency(),
                'payment_method'             => 'Credit Card Online',
            );
            $data          += $defaultValues;

            return [
                '{invoice: number}'           => $data['invoice_number'] ?? '',
                '{invoice: date}'             => $data['invoice_date'] ?? '',
                '{invoice: subscription fee}' => Accounting::formatPrice($data['subscription_fee'] ?? 0),
                '{invoice: support fee}'      => Accounting::formatPrice($data['support_fee'] ?? 0),

                '{invoice: additional users}'           => $data['additional_users'] ?? '',
                '{invoice: additional users fee}'       => Accounting::formatPrice($data['additional_users_fee'] ?? 0),
                '{invoice: additional storage}'         => $data['additional_storage'] ?? '',
                '{invoice: additional storage charges}' => Accounting::formatPrice($data['additional_storage_charges'] ?? 0),

                '{invoice: free users}'        => $data['free_users'] ?? '',
                '{invoice: free clients}'      => $data['free_clients'] ?? '',
                '{invoice: free storage}'      => $data['free_storage'] ?? '',
                '{invoice: price per user}'    => Accounting::formatPrice($data['price_per_user'] ?? 0),
                '{invoice: price per storage}' => Accounting::formatPrice($data['price_per_storage'] ?? 0),
                '{invoice: quantity}'          => $data['quantity'] ?? '',
                '{invoice: discount}'          => Accounting::formatPrice($data['discount'] ?? 0),
                '{invoice: currency}'          => $data['currency'] ?? '',
                '{invoice: payment method}'    => $data['payment_method'] ?? '',

                '{invoice: gst/hst fee}' => Accounting::formatPrice($data['gst'] ?? 0),
                '{invoice: subtotal}'    => Accounting::formatPrice($data['subtotal'] ?? 0),
                '{invoice: total}'       => Accounting::formatPrice($data['total'] ?? 0),
                '{invoice: amount paid}' => Accounting::formatPrice($data['amount_paid'] ?? 0),

                '{invoice: hide if empty discount}' => empty((int)$data['discount']) ? 'style="display: none;"' : ''
            ];
        }
    }
}
