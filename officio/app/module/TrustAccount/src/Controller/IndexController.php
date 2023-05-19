<?php

namespace TrustAccount\Controller;

use Clients\Service\Clients;
use Exception;
use Files\BufferedStream;
use Files\Service\Files;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;
use Laminas\Db\Sql\Select;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Common\Service\Settings;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * TrustAccount IndexController - main controller for Client Account page
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class IndexController extends BaseController
{
    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_clients;

    /** @var Files */
    protected $_files;

    public function initAdditionalServices(array $services)
    {
        $this->_clients = $services[Clients::class];
        $this->_company = $services[Company::class];
        $this->_files   = $services[Files::class];
    }

    /**
     * Prepare to show T/A list
     */
    public function indexAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setVariable('booCanAddTA', $this->_clients->getAccounting()->canCurrentMemberAddEditTA());
        $view->setVariable('ta_label', $this->_company->getCurrentCompanyDefaultLabel('trust_account'));

        return $view;
    }

    /**
     * Load T/A list for current user
     */
    public function showAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);

        $strError = '';
        try {
            ini_set('memory_limit', '-1');

            $companyTaId = (int)$this->params()->fromPost('company_ta_id');

            // Check if user has access to Company T/A id
            if (!$this->_clients->hasCurrentMemberAccessToTA($companyTaId)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($strError)) {
                $view->setVariable('ta_id', $companyTaId);
                $view->setVariable('arrTAInfo', $this->_clients->getAccounting()->getCompanyTAbyId($companyTaId));
                $view->setVariable('booShowHelp', $this->_acl->isAllowed('help-view'));
                $view->setVariable('booCanEditTA', $this->_acl->isAllowed('trust-account-edit-view'));
                $view->setVariable('booCanImport', $this->_acl->isAllowed('trust-account-import-view'));
                $view->setVariable('booCanSeeHistory', $this->_acl->isAllowed('trust-account-history-view'));
                $view->setVariable('ta_label', $this->_company->getCurrentCompanyDefaultLabel('trust_account'));
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $view->setVariables(['content' => $strError]);
            $view->setTemplate('layout/plain');
        }

        return $view;
    }

    public function getCasesListAction()
    {
        $query            = '';
        $strError         = '';
        $arrFilteredCases = array();

        try {
            $query = trim($this->params()->fromPost('query', ''));

            $arrQueryWords = $this->_clients->getSearch()->getSearchStringExploded($query);
            if (empty($strError) && empty($arrQueryWords)) {
                $strError = $this->_tr->translate('Please type the name.');
            }

            if (empty($strError)) {
                list($arrApplicants,) = $this->_clients->getSearch()->runQuickSearchByStaticFields(
                    $arrQueryWords,
                    10,
                    false,
                    false,
                    true,
                    false
                );

                foreach ($arrApplicants as $arrApplicantInfo) {
                    if ($arrApplicantInfo['applicant_type'] == 'case') {
                        $caseFileNumber = empty($arrApplicantInfo['applicant_name']) ? '' : ' (' . $arrApplicantInfo['applicant_name'] . ')';

                        $arrFilteredCases[] = [
                            'clientId'       => $arrApplicantInfo['applicant_id'],
                            'clientFullName' => $arrApplicantInfo['user_name'] . $caseFileNumber,
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
            'msg'        => $strError,
            'query'      => $query,
            'rows'       => $arrFilteredCases,
            'totalCount' => count($arrFilteredCases)
        );

        return new JsonModel($arrResult);
    }

    /**
     * Show transactions grid
     */
    public function getTransactionsGridAction()
    {
        try {
            $arrResult = $this->_clients->getAccounting()->getTrustAccount()->getTransactionsGrid(
                (int)$this->params()->fromPost('ta_id'),
                $this->params()->fromPost(),
                false
            );
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            $arrResult = array(
                'rows'                  => array(),
                'totalCount'            => 0,
                'balance'               => 0,
                'last_transaction_date' => ''
            );
        }

        return new JsonModel($arrResult);
    }

    /**
     * Print transactions grid
     */
    public function printAction()
    {
        $view        = new ViewModel();
        $companyTAId = (int)$this->params()->fromQuery('ta_id');
        $arrParams   = $this->params()->fromQuery();
        $view->setVariable('ta_label', $this->_company->getCurrentCompanyDefaultLabel('trust_account'));
        $view->setVariable('arrResult', $this->_clients->getAccounting()->getTrustAccount()->getTransactionsGrid($companyTAId, $arrParams, true));
        $view->setTerminal(true);

        return $view;
    }

    //########    RECONCILIATION REPORT    #################
    public function getReconcileAction()
    {
        $view = new JsonModel();

        $arrMonths      = array();
        $last_reconcile = '';
        $strError       = '';
        try {
            $filter        = new StripTags();
            $company_ta_id = (int)$this->findParam('company_ta_id');
            $reconcileType = $filter->filter($this->findParam('reconcile_type'));

            // Check if user has access to Company T/A id
            if (empty($company_ta_id) || !$this->_clients->hasCurrentMemberAccessToTA($company_ta_id)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $ta_date_info = array();
            if (empty($strError)) {
                $select = (new Select())
                    ->from(array('cta' => 'company_ta'))
                    ->columns(['create_date', 'last_reconcile', 'last_reconcile_iccrc'])
                    ->join(['ta' => 'u_trust_account'],  new PredicateExpression('ta.company_ta_id = cta.company_ta_id AND cta.company_ta_id =' . $company_ta_id), ['later_transaction' => new Expression('MIN(ta.date_from_bank)')])
                    ->group('cta.company_ta_id');

                $ta_date_info = $this->_db2->fetchRow($select);
                if (!$ta_date_info) {
                    $strError = $this->_tr->translate('Can\'t load ' . $this->_company->getCurrentCompanyDefaultLabel('trust_account') . ' data');
                }
            }

            if (empty($strError)) {
                list($year, $month, $day) = explode('-', $ta_date_info['create_date'] ?? '');
                $create_date = strtotime($month . '/' . $day . '/' . $year);

                $lastReconcileDate = $reconcileType == 'iccrc' ? $ta_date_info['last_reconcile_iccrc'] : $ta_date_info['last_reconcile'];

                $last_reconcile = 0;
                if (!empty($lastReconcileDate) && $lastReconcileDate != '0000-00-00') {
                    list($year, $month, $day) = explode('-', $lastReconcileDate ?? '');
                    $last_reconcile = strtotime($month . '/' . $day . '/' . $year);
                }

                $later_transaction = 0;
                if (!empty($ta_date_info['later_transaction'])) {
                    list($year, $month, $day) = explode('-', $ta_date_info['later_transaction']);
                    $later_transaction = strtotime($month . '/' . $day . '/' . $year);
                }

                // Load from now till date of T/A creation or last reconciliation date
                if (empty($last_reconcile)) {
                    // Reconciliation report wasn't created yet
                    $startDate = empty($later_transaction) ? $create_date : $later_transaction;
                } else {
                    $startDate = $last_reconcile;
                }

                $max_date         = time();
                $totalMonthToShow = 0;
                if (!empty($startDate)) {
                    while ($startDate <= $max_date) {
                        $startDate = strtotime("+1 MONTH", $startDate);
                        $totalMonthToShow++;
                    }
                }

                for ($i = 0; $i < $totalMonthToShow; $i++) {
                    $time = strtotime('-' . ($i + 1) . ' MONTHS', strtotime('first day of this month'));

                    if ($last_reconcile >= $time) {
                        break;
                    }

                    $arrMonths[] = array(
                        'id'    => date('Y-m-t', $time), // Use last day of the month - will be used later, during pdf form generation
                        'label' => date('F Y', $time)
                    );
                }

                // Sorting in DESC mode
                krsort($arrMonths);
                $arrMonths = array_values($arrMonths);

                $last_reconcile = (empty($last_reconcile) ? false : date('F Y', $last_reconcile));
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'        => empty($strError),
            'msg'            => $strError,
            'month'          => $arrMonths,
            'last_reconcile' => $last_reconcile
        );
        return $view->setVariables($arrResult);
    }

    /**
     * Check for reconcile report requirements
     */
    public function checkReconcileAction()
    {
        $view = new JsonModel();

        $strError              = '';
        $end_date              = '';
        $unassignedWithdrawals = 0;
        $unassignedDeposits    = 0;
        $balance               = 0;

        try {
            $filter      = new StripTags();
            $companyTaId = (int)$this->findParam('company_ta_id');

            // Get end date
            $date          = $filter->filter(Json::decode(stripslashes($this->findParam('end_date', '')), Json::TYPE_ARRAY));
            $end_date      = $this->_settings->formatDate($date, false);
            $reconcileType = $filter->filter($this->findParam('reconcile_type'));

            // Check if user has access to Company T/A id
            if (empty($strError) && !$this->_clients->hasCurrentMemberAccessToTA($companyTaId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && !in_array($reconcileType, array('general', 'iccrc'))) {
                $strError = $this->_tr->translate('Incorrect incoming params.');
            }

            // If no imported record after the end of the month of reconciliation report, then no reconciliation allowed
            if (empty($strError)) {
                $recordsCount = $this->_clients->getAccounting()->getTrustAccount()->getTransactionsCount($companyTaId, $date);

                if (empty($recordsCount)) {
                    $strError = sprintf(
                        'Please import bank transactions for %s and at least one transaction after the end of reconciliation period to ensure all bank transactions for the period are imported.',
                        $this->_settings->formatDate($date)
                    );
                }
            }

            if (empty($strError)) {
                // Get unassigned withdrawals count before the date
                $unassignedWithdrawals = $this->_clients->getAccounting()->getTrustAccount()->getUnassignedWithdrawalsCount($companyTaId, $date);

                // Get unassigned deposits count before the date
                $unassignedDeposits = $this->_clients->getAccounting()->getTrustAccount()->getUnassignedDepositsCount($companyTaId, $date);

                // Load offset balance - will be used to check if we need to show additional dialog
                if ($reconcileType == 'iccrc') {
                    $balance = $this->_clients->getAccounting()->getTrustAccount()->getCheckReconcileBalance($companyTaId, $date);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'               => empty($strError),
            'msg'                   => $strError,
            'end_date'              => $end_date,
            'unassignedWithdrawals' => $unassignedWithdrawals,
            'unassignedDeposits'    => $unassignedDeposits,
            'balance'               => (float)$balance
        );

        return $view->setVariables($arrResult);
    }

    /**
     * Generate reconcile report in pdf format
     */
    public function createReconcileAction()
    {
        $strError        = '';
        $lastReconcileId = 0;
        $booDraft        = false;

        try {
            $filter = new StripTags();

            $companyTaId   = (int)$this->params()->fromPost('company_ta_id');
            $endDate       = $filter->filter(Json::decode($this->params()->fromPost('end_date'), Json::TYPE_ARRAY)); //UNIX DATE: YYYY-MM-DD
            $reconcileType = $filter->filter($this->params()->fromPost('reconcile_type'));

            // Check if user has access to Company T/A id
            if (!$this->_clients->hasCurrentMemberAccessToTA($companyTaId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && !in_array($reconcileType, array('general', 'iccrc'))) {
                $strError = $this->_tr->translate('Incorrect incoming params.');
            }

            $booDraft     = (bool)$this->params()->fromPost('is_draft', false);
            $balanceDate  = $filter->filter($this->params()->fromPost('balance_date'));
            $balanceNotes = $filter->filter($this->params()->fromPost('balance_notes'));
            if (empty($strError) && !empty($balanceDate) && !Settings::isValidDateFormat($balanceDate, 'Y-m-d')) {
                $strError = $this->_tr->translate('Incorrect date.');
            }

            if (empty($strError)) {
                list($lastReconcileId, $tmpPdfFile) = $this->_clients->getAccounting()->getTrustAccount()->createReconcileReport($companyTaId, $endDate, $reconcileType, $booDraft, $balanceDate, $balanceNotes);
                if (empty($lastReconcileId) && !$booDraft) {
                    $strError = $this->_tr->translate('Internal error.');
                }

                if (empty($strError) && $booDraft) {
                    $pdfFileName = 'reconciliation_report.pdf';
                    if ($this->_config['site_version']['version'] != 'australia') {
                        $pdfFileName = $reconcileType . '_' . $pdfFileName;
                    }

                    return $this->downloadFile($tmpPdfFile, $pdfFileName, '', false, true, true);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if ($booDraft) {
            $view = new ViewModel(
                ['content' => $strError]
            );
            $view->setTerminal(true);
            $view->setTemplate('layout/plain');
        } else {
            $arrResult = array(
                'success' => empty($strError),
                'msg'     => $strError,
                'id'      => $lastReconcileId
            );

            $view = new JsonModel($arrResult);
        }

        return $view;
    }

    /**
     * Show generated pdf file
     */
    public function getPdfAction()
    {
        $view = new ViewModel(
            [
                'content' => null
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        $strError = '';
        $id       = $this->findParam('id');

        // Check for incoming data correctness
        if (!is_numeric($id)) {
            $strError = 'Incorrect id';
        }

        // Check if current user can open this reconciliation report
        if (empty($strError)) {
            $checkCompanyTAId = $this->_clients->getAccounting()->getTrustAccount()->getReconciliationRecordInfo($id);

            if (!$this->_clients->hasCurrentMemberAccessToTA($checkCompanyTAId)) {
                $strError = 'Insufficient access rights';
            }
        }


        if (empty($strError)) {
            // If user has access - return correct pdf file
            $filePath = $this->_files->getReconciliationReportsPath() . '/' . $id;
            $fileName = 'ReconciliationReport.pdf';

            if ($this->_auth->isCurrentUserCompanyStorageLocal()) {
                return $this->downloadFile($filePath, $fileName, '', true, false);
            } else {
                $url = $this->_files->getCloud()->getFile($filePath, $fileName, true, false);
                if ($url) {
                    return $this->redirect()->toUrl($url);
                } else {
                    return $this->fileNotFound();
                }
            }
        }

        return $view;
    }

    public function exportAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        try {
            $companyId = $this->_auth->getCurrentUserCompanyId();


            // Related to export of T/A
            $exportTaId = $this->params()->fromQuery('exportTaId');
            $exportTaId = empty($exportTaId) ? 0 : $exportTaId;

            if (!empty($exportTaId) && !$this->_clients->hasCurrentMemberAccessToTA($exportTaId)) {
                return $view->setVariables(
                    [
                        'content' => 'Insufficient access rights.'
                    ]
                );
            }

            $exportFilter = $this->params()->fromQuery('exportFilter');
            $exportFilter = empty($exportFilter) ? 0 : $exportFilter;
            $firstParam   = $this->params()->fromQuery('firstParam');
            $firstParam   = empty($firstParam) ? 0 : $firstParam;
            $secondParam  = $this->params()->fromQuery('secondParam');
            $secondParam  = empty($secondParam) ? 0 : $secondParam;

            $result = $this->_company->getCompanyExport()->export($companyId, 'trust_account', null, null, $exportTaId, $exportFilter, $firstParam, $secondParam);
            if (is_array($result)) {
                list($fileName, $spreadsheet) = $result;

                $writer = new Xlsx($spreadsheet);

                $disposition    = "attachment; filename=\"$fileName\"";
                $pointer        = fopen('php://output', 'wb');
                $bufferedStream = new BufferedStream('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, $disposition);
                $bufferedStream->setStream($pointer);

                $writer->save('php://output');
                fclose($pointer);

                return $view->setVariable('content', null);
            } else {
                $strMessage = $result;
            }
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error. Please try again later');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(
            [
                'content' => $strMessage
            ]
        );
    }
}
