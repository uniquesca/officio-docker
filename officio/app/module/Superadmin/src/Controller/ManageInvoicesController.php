<?php

namespace Superadmin\Controller;

use Exception;
use Files\BufferedStream;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Manage Invoices Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ManageInvoicesController extends BaseController
{
    /** @var Company $_company */
    protected $_company;

    public function initAdditionalServices(array $services) {
        $this->_company = $services[Company::class];
    }

    public function getInvoicesAction() {
        // Get params
        $view  = new JsonModel();
        $sort  = $this->findParam('sort');
        $dir   = $this->findParam('dir');
        $start = $this->findParam('start');
        $limit = $this->findParam('limit');

        $arrFilterData = array(
            'filter_date_by'         => Json::decode($this->findParam('filter_date_by'), Json::TYPE_ARRAY),
            'filter_date_from'       => Json::decode($this->findParam('filter_date_from'), Json::TYPE_ARRAY),
            'filter_company'         => Json::decode($this->findParam('filter_company'), Json::TYPE_ARRAY),
            'filter_date_to'         => Json::decode($this->findParam('filter_date_to'), Json::TYPE_ARRAY),
            'filter_mode_of_payment' => Json::decode($this->findParam('filter_mode_of_payment'), Json::TYPE_ARRAY),
            'filter_product'         => Json::decode($this->findParam('filter_product'), Json::TYPE_ARRAY),
        );

        // Load invoices list from DB
        $arrResult = $this->_company->getCompanyInvoice()->getInvoicesList($arrFilterData, $sort, $dir, $start, $limit);

        // Return invoices list
        return $view->setVariables($arrResult);
    }

    public function exportAction() {
        $view = new ViewModel(
            [
                'content' => null
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        try {
            // Get params
            $sort  = $this->findParam('sort');
            $dir   = $this->findParam('dir');
            $start = 0;
            $limit = 1000000;

            $arrFilterData = array(
                'filter_date_by'         => Json::decode($this->findParam('filter_date_by'), Json::TYPE_ARRAY),
                'filter_date_from'       => Json::decode($this->findParam('filter_date_from'), Json::TYPE_ARRAY),
                'filter_company'         => Json::decode($this->findParam('filter_company'), Json::TYPE_ARRAY),
                'filter_date_to'         => Json::decode($this->findParam('filter_date_to'), Json::TYPE_ARRAY),
                'filter_mode_of_payment' => Json::decode($this->findParam('filter_mode_of_payment'), Json::TYPE_ARRAY),
                'filter_product'         => Json::decode($this->findParam('filter_product'), Json::TYPE_ARRAY),
            );

            // Load invoices list from DB
            $oInvoices = $this->_company->getCompanyInvoice();
            $arrResult = $oInvoices->getInvoicesList($arrFilterData, $sort, $dir, $start, $limit, false);

            // Export to excel
            $title  = 'Invoices';
            $title .= empty($arrResult['period']) ? '' : $arrResult['period'];
            $fileName = "$title.xlsx";

            $spreadsheet = $oInvoices->createInvoicesExcelReport($arrResult['rows'], $title);
            $writer = new Xlsx($spreadsheet);

            $disposition = "attachment; filename=\"$fileName\"";

            $pointer = fopen('php://output', 'wb');
            $bufferedStream = new BufferedStream('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, $disposition);
            $bufferedStream->setStream($pointer);

            $writer->save('php://output');
            fclose($pointer);

            return $view->setVariable('content', null);
        } catch (Exception $e) {
            $view->setVariable('content', 'Internal error');

            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view;
    }
}