<?php

namespace TrustAccount\Controller;

use Clients\Service\Clients;
use Exception;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\Expression as PredicateExpression;
use Laminas\Db\Sql\Select;
use Laminas\View\Model\JsonModel;
use Officio\BaseController;

/**
 * TrustAccount HistoryController - this controller is for History showing on the Trust Account page
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class HistoryController extends BaseController
{

    /** @var Clients */
    protected $_clients;

    public function initAdditionalServices(array $services)
    {
        $this->_clients = $services[Clients::class];
    }

    /**
     * The default action - show the home page
     */
    public function indexAction()
    {
        set_time_limit(2 * 60); // 2 minutes, no more
        ini_set('memory_limit', '512M');

        $booSuccess = false;
        $arrResult  = array();
        try {
            $ta_id = $this->params()->fromPost('ta_id');

            // Check if current user has access to this Client Account
            if ($this->_clients->hasCurrentMemberAccessToTA($ta_id)) {
                //get info from u_log

                $select = (new Select())
                    ->from(['l' => 'u_log'])
                    ->columns([
                        'trust_account_id',
                        'action_id',
                        'date_of_event',
                        'count_deposits' => new Expression('COUNT(l.date_of_event)')
                    ])
                    ->join(
                        ['ta' => 'u_trust_account'],
                        'ta.trust_account_id = l.trust_account_id',
                        ['ta_date_from_bank' => 'date_from_bank', 'ta_description' => 'description'],
                        Select::JOIN_LEFT
                    )
                    ->join(
                        ['d' => 'u_assigned_deposits'],
                        'ta.trust_account_id = d.trust_account_id',
                        ['deposit_id', 'd_special_transaction' => 'special_transaction', 'd_special_transaction_id' => 'special_transaction_id', 'd_member_id' => 'member_id'],
                        Select::JOIN_LEFT
                    )
                    ->join(
                        ['w' => 'u_assigned_withdrawals'],
                        'ta.trust_account_id = w.trust_account_id',
                        ['withdrawal_id', 'w_invoice_payment_id' => 'invoice_payment_id', 'w_special_transaction' => 'special_transaction', 'w_special_transaction_id' => 'special_transaction_id', 'w_returned_payment_member_id' => 'returned_payment_member_id'],
                        Select::JOIN_LEFT
                    )
                    ->join(
                        ['m' => 'members'],
                        'm.member_id = l.author_id',
                        ['author_fName' => 'fName', 'author_lName' => 'lName'],
                        Select::JOIN_LEFT
                    )
                    ->where(['ta.company_ta_id' => $ta_id])
                    ->group('l.date_of_event');

                $result = $this->_db2->fetchAll($select);

                foreach ($result as $row) {
                    $assignedTo = '';
                    if (!empty($row['deposit_id'])) {
                        if (!empty($row['d_special_transaction_id'])) {
                            // Assigned as special transaction (predefined)
                            $assignedTo = ' to ' . $this->_clients->getAccounting()->getTrustAccount()->getSpecialTypeNameById($row['d_special_transaction_id'], false);
                        } elseif (!empty($row['d_special_transaction'])) {
                            // Assigned as special transaction (not predefined, other)
                            $assignedTo = ' to ' . $row['d_special_transaction'];
                        } else {
                            // Assigned deposit to the client
                            $assignedTo = ' to ' . $this->_clients->generateClientAndCaseName($row['d_member_id']);
                            if ($row['count_deposits'] > 1) {
                                $assignedTo .= ' etc';
                            }
                        }
                    } elseif (!empty($row['withdrawal_id'])) {
                        // Assigned withdrawal
                        if (!empty($row['w_returned_payment_member_id'])) {
                            // Assigned as returned payment
                            $assignedTo = ' to ' . $this->_clients->generateClientAndCaseName($row['w_returned_payment_member_id']);
                        } elseif (!empty($row['w_invoice_payment_id'])) {
                            // Assigned to the invoice payment
                            $arrInvoicePaymentInfo = $this->_clients->getAccounting()->getInvoicePaymentInfo($row['w_invoice_payment_id']);
                            $assignedTo            = ' to ' . $this->_clients->generateClientAndCaseName($arrInvoicePaymentInfo['member_id']);
                        } elseif (!empty($row['w_special_transaction_id'])) {
                            // Assigned as special transaction (predefined)
                            $assignedTo = ' to ' . $this->_clients->getAccounting()->getTrustAccount()->getSpecialTypeNameById($row['w_special_transaction_id']);
                        } elseif (!empty($row['w_special_transaction'])) {
                            // Assigned as special transaction (not predefined, other)
                            $assignedTo = ' to ' . $row['w_special_transaction'];
                        }
                    }

                    $actions = array(
                        1 => 'Assigned ' . $this->_settings->formatDate($row['ta_date_from_bank']) . ' - ' . $row['ta_description'] . $assignedTo,
                        3 => 'Send receipt of payment',
                        4 => 'Updated transaction #' . $row['trust_account_id'] . ' record',
                        5 => 'Unassigned transaction #' . $row['trust_account_id']
                    );

                    $author = $this->_members::generateMemberName(array('fName' => $row['author_fName'], 'lName' => $row['author_lName']));

                    //rows
                    $arrResult[] = array(
                        'history_id'    => $row['trust_account_id'],
                        'action_id'     => $row['action_id'],
                        'action'        => $actions[$row['action_id']],
                        'date_of_event' => $this->_settings->formatDate($row['date_of_event'], false),
                        'time_of_event' => strtotime($row['date_of_event']),
                        'user'          => $author['full_name']
                    );
                }

                $select = (new Select())
                    ->from(['it' => 'u_import_transactions'])
                    ->columns([Select::SQL_STAR])
                    ->join(['m' => 'members'], new PredicateExpression('m.member_id = it.author_id AND company_ta_id =' . $ta_id));

                $result = $this->_db2->fetchAll($select);

                foreach ($result as $row) {
                    $author = $this->_members::generateMemberName($row);

                    $action = sprintf(
                        $this->_tr->translate('Imported bank record of %s to %s with %d records'),
                        $this->_settings->formatDate($row['dt_start']),
                        $this->_settings->formatDate($row['dt_end']),
                        $row['records']
                    );

                    $arrResult[] = array(
                        'history_id'    => $row['import_transaction_id'],
                        'action_id'     => 2,
                        'action'        => $action,
                        'user'          => $author['full_name'],
                        'date_of_event' => $this->_settings->formatDate($row['import_datetime'], false),
                        'time_of_event' => strtotime($row['import_datetime']),
                        'dt_start'      => $this->_settings->formatDate($row['dt_start'], false),
                        'dt_end'        => $this->_settings->formatDate($row['dt_end'], false)
                    );
                }

                //get reconciliation records
                $reconciliations = $this->_clients->getAccounting()->getTrustAccount()->getCompanyTAReconciliationRecords($ta_id);
                foreach ($reconciliations as $reconcile) {
                    $author = $this->_members::generateMemberName($reconcile);
                    if ($this->layout()->getVariable('site_version') == 'australia') {
                        $type = $reconcile['reconciliation_type'] == 'iccrc' ? '' : 'general';
                    } else {
                        $type = $reconcile['reconciliation_type'] == 'iccrc' ? 'CICC' : 'general';
                    }

                    $name = sprintf(
                        $this->_tr->translate('Created %s reconciliation report for %s - <a href="%s" target="_blank">click to view report</a>'),
                        $type,
                        $this->_settings->formatDate($reconcile['recon_date']),
                        $this->layout()->getVariable('baseUrl') . '/trust-account/index/get-pdf?file=Reconciliation_report.pdf&id=' . $reconcile['reconciliation_id']
                    );

                    $arrResult[] = array(
                        'history_id'    => $reconcile['reconciliation_id'],
                        'action_id'     => 6,
                        'action'        => $name,
                        'date_of_event' => $this->_settings->formatDate($reconcile['create_date'], false),
                        'time_of_event' => strtotime($reconcile['create_date']),
                        'user'          => $author['full_name']
                    );
                }

                // Sort all info based on time
                foreach ($arrResult as $key => $row) {
                    $time[$key] = $row['time_of_event'];
                }
                if (!empty($arrResult) && !empty($time)) {
                    array_multisort($time, SORT_DESC, $arrResult);
                }

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $booSuccess = false;
            $arrResult  = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'    => $booSuccess,
            'rows'       => $arrResult,
            'totalCount' => count($arrResult)
        );

        return new JsonModel($arrResult);
    }
}
