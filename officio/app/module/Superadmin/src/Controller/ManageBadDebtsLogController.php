<?php

namespace Superadmin\Controller;

use Exception;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;
use Laminas\Filter\StripTags;
use Prospects\Service\Prospects;

/**
 * Manage Bad Debts Log Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ManageBadDebtsLogController extends BaseController
{
    /** @var Company */
    protected $_company;

    /** @var Prospects */
    protected $_prospects;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_prospects = $services[Prospects::class];
    }

    public function getCompaniesAction() {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            $view = new ViewModel(
                [
                    'content' => $this->_tr->translate('Insufficient access rights.')
                ]
            );
            $view->setTerminal(true);
            $view->setTemplate('layout/plain');

            return $view;
        }
        $view = new JsonModel();

        $arrInvoices = array();
        $companiesCount = 0;
        try {
            $filter = new StripTags();
            $strCompanyQuery = trim($filter->filter($this->findParam('query', '')));
            $start = (int)$this->findParam('start');
            $limit = (int)$this->findParam('limit');


            list($arrFailedInvoices, $companiesCount) = $this->_company->getCompanyInvoice()->getFailedInvoices($strCompanyQuery, $start, $limit);

            foreach ($arrFailedInvoices as $arrGroupedInvoices) {
                foreach ($arrGroupedInvoices as $arrFailedInvoiceInfo) {
                    $arrInvoiceInfo = array(
                        'company_id'     => $arrFailedInvoiceInfo['company_id'],
                        'admin_name'     => $arrFailedInvoiceInfo['admin_name'],
                        'company_name'   => $arrFailedInvoiceInfo['companyName'],
                        'company_abn'    => $arrFailedInvoiceInfo['company_abn'],
                        'company_status' => $arrFailedInvoiceInfo['company_status'],

                        'invoice_id'                 => $arrFailedInvoiceInfo['company_invoice_id'],
                        'invoice_subject'            => $arrFailedInvoiceInfo['subject'],
                        'invoice_total'              => $arrFailedInvoiceInfo['total'],
                        'invoice_date'               => $arrFailedInvoiceInfo['invoice_date'],
                        'invoice_extended_date_till' => $arrFailedInvoiceInfo['show_expiration_dialog_after'],
                        'invoice_error_code'         => $arrFailedInvoiceInfo['log_error_code'],
                        'invoice_error_message'      => $arrFailedInvoiceInfo['log_error_message'],
                    );

                    $arrInvoices[] = $arrInvoiceInfo;
                }
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return Json result
        $arrResult = array(
            'success'    => $booSuccess,
            'rows'       => $arrInvoices,
            'totalCount' => $companiesCount
        );

        return $view->setVariables($arrResult);
    }

    public function deleteInvoicesAction() {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            $view = new ViewModel(
                [
                    'content' => $this->_tr->translate('Insufficient access rights.')
                ]
            );
            $view->setTerminal(true);
            $view->setTemplate('layout/plain');

            return $view;
        }
        $view = new JsonModel();

        $strMessage = '';
        $booSuccess = false;

        $arrInvoices = Json::decode($this->findParam('invoices'), Json::TYPE_ARRAY);
        if(!is_array($arrInvoices) || !count($arrInvoices)) {
            $strMessage = $this->_tr->translate('Incorrect incoming params');
        }

        if(empty($strMessage)) {
            foreach ($arrInvoices as $invoiceId) {
                if(empty($invoiceId) || !is_numeric($invoiceId)) {
                    $strMessage = $this->_tr->translate('Incorrect incoming params');
                    break;
                }
            }
        }

        if(empty($strMessage)) {
            $booSuccess = $this->_company->getCompanyInvoice()->deleteInvoices($arrInvoices);

            $strSuccess = sprintf(
                $this->_tr->translate('%d %s deleted successfully'),
                count($arrInvoices),
                count($arrInvoices) == 1 ? $this->_tr->translate('invoice was') : $this->_tr->translate('invoices were')
            );
            $strMessage = $booSuccess ? $strSuccess : $this->_tr->translate('Internal error');
        }

        $arrResult = array(
            'success' => $booSuccess,
            'message' => $strMessage
        );

        return $view->setVariables($arrResult);
    }


    public function chargeInvoiceAction() {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            $view = new ViewModel(
                [
                    'content' => $this->_tr->translate('Insufficient access rights.')
                ]
            );
            $view->setTerminal(true);
            $view->setTemplate('layout/plain');

            return $view;
        }
        $view = new JsonModel();

        $strMessage = '';
        $booSuccess = false;
        $arrInvoices = array();

        $booLoadFailedInvoices = $this->findParam('load_failed_invoices', false);

        $invoiceId = Json::decode($this->findParam('invoice'), Json::TYPE_ARRAY);
        if(empty($invoiceId) || !is_numeric($invoiceId)) {
            $strMessage = $this->_tr->translate('Incorrect incoming params');
        }

        if(empty($strMessage)) {
            $oInvoices = $this->_company->getCompanyInvoice();
            $arrChargeResult = $oInvoices->chargeSavedInvoice($invoiceId);
            $booSuccess = !$arrChargeResult['error'];
            $strMessage = $arrChargeResult['message'];

            if($booSuccess && empty($strMessage)) {
                $strMessage = $this->_tr->translate('Invoice was charged successfully');

                if($booLoadFailedInvoices) {
                    $arrInvoiceDetails = $oInvoices->getInvoiceDetails($invoiceId, false, false);
                    if(is_array($arrInvoiceDetails) && count($arrInvoiceDetails)) {
                        $arrInvoices = $oInvoices->getCompanyFailedInvoices($arrInvoiceDetails['company_id']);
                    }
                }
            }
        }

        $arrResult = array(
            'success'    => $booSuccess,
            'message'    => $strMessage,
            'rows'       => $arrInvoices,
            'totalCount' => count($arrInvoices)
        );

        return $view->setVariables($arrResult);
    }

}