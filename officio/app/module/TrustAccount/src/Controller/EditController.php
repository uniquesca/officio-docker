<?php

namespace TrustAccount\Controller;

use Clients\Service\Clients;
use Exception;
use Laminas\Db\Sql\Select;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Users;

/**
 * TrustAccount EditController - this controller is used when edit
 * transactions on Client Account page
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class EditController extends BaseController
{
    /** @var Users */
    protected $_users;

    /** @var Clients */
    private $_clients;

    public function initAdditionalServices(array $services)
    {
        $this->_users   = $services[Users::class];
        $this->_clients = $services[Clients::class];
    }

    public function indexAction()
    {
        $view = new ViewModel(
            [
                'content' => null
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }

    public function getAction()
    {
        $view = new JsonModel();

        try {
            $filter = new StripTags();

            $trust_account_id = (int)$filter->filter($this->findParam('trust_account_id'));

            $tInfo = $this->_clients->getAccounting()->getTrustAccount()->getTransactionInfo($trust_account_id);

            // Check if user has access to Company T/A id
            if (!$this->_clients->hasCurrentMemberAccessToTA($tInfo['company_ta_id'])) {
                exit(Json::encode(array('success' => false, 'transaction' => array())));
            }

            $aInfo = $this->getTransactionAssignedInfo($trust_account_id);

            //assigned to
            $to = '';
            if ($aInfo[0]['type'] == 'withdrawal') {
                if (isset($aInfo[0]['invoice_payment_id'])) {
                    $arrInvoicePaymentInfo = $this->_clients->getAccounting()->getInvoicePaymentInfo($aInfo[0]['invoice_payment_id']);

                    // Citizen John (Q.) for Jan 12, 2008 Invoice #1000
                    $to = $this->_clients->getAccounting()->getTrustAccount()->generateClientLink($arrInvoicePaymentInfo['member_id'], true, false) . ' for ' . $this->_settings->formatDate($arrInvoicePaymentInfo['date_of_creation']) . (is_numeric($arrInvoicePaymentInfo['invoice_num']) ? ' Invoice #' : ' ') . $arrInvoicePaymentInfo['invoice_num'];
                } elseif (isset($aInfo[0]['special_transaction']) && !empty($aInfo[0]['special_transaction'])) {
                    $to = 'Special transaction as "' . $aInfo[0]['special_transaction'] . '"';
                } elseif (isset($aInfo[0]['special_transaction_id']) && !empty($aInfo[0]['special_transaction_id'])) {
                    $to = 'Special transaction as "' . $this->_clients->getAccounting()->getTrustAccount()->getSpecialTypeNameById($aInfo[0]['special_transaction_id']) . '"';
                } elseif (isset($aInfo[0]['returned_payment_member_id'])) {
                    $to = $this->_clients->getAccounting()->getTrustAccount()->generateClientLink($aInfo[0]['returned_payment_member_id'], true, false) . ' as Returned payment';
                } elseif (!empty($aInfo[0]['member_id'])) {
                    $to = "<div>" . $this->_clients->getAccounting()->getTrustAccount()->generateClientLink($aInfo[0]['member_id'], true, false) . "</div>";
                }
            } else {
                if (isset($aInfo[0]['member_id'])) {
                    foreach ($aInfo as $a) {
                        $amount = $a['deposit'] ?? $a['withdrawal'];
                        $tainfo = $this->_clients->getAccounting()->getTAInfo($a['company_ta_id']);
                        $to .= "<div>" . $this->_clients->getAccounting()::formatPrice($amount, $tainfo['currency']) . ' - ' . $this->_clients->getAccounting()->getTrustAccount()->generateClientLink(
                                $a['member_id'],
                                true,
                                false
                            ) . "</div>";
                    }
                } elseif (isset($aInfo[0]['special_transaction'])) {
                    $to = 'Special transaction as "' . $aInfo[0]['special_transaction'] . '"';
                } elseif (isset($aInfo[0]['special_transaction_id']) && !empty($aInfo[0]['special_transaction_id'])) {
                    $to = 'Special transaction as "' . $this->_clients->getAccounting()->getTrustAccount()->getSpecialTypeNameById($aInfo[0]['special_transaction_id'], false) . '"';
                }
            }

            $userInfo = $this->_users->getUserInfo($aInfo[0]['author_id']);

            $tInfo['assigned'] = array(
                'type' => $aInfo[0]['type'],
                'to'   => $to,
                'on'   => $this->_settings->formatDate($aInfo[0]['date_of_event']),
                'by'   => $userInfo['username']
            );

            $tInfo['date_from_bank'] = $this->_settings->formatDate($tInfo['date_from_bank']);

            if ($aInfo[0]['type'] == 'withdrawal') {
                $tInfo['assigned'] = array_merge(
                    $tInfo['assigned'],
                    array(
                        'destination_account_id' => $aInfo[0]['destination_account_id'],
                        'destination'            => $this->_clients->getAccounting()->getTrustAccount()->getDestinationType($aInfo[0]['destination_account_id'], $aInfo[0]['destination_account_other'])
                    )
                );
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $tInfo      = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => $booSuccess, 'transaction' => $tInfo));
    }

    private function getTransactionAssignedInfo($trustAccountId)
    {
        $arrTrustAccountInfo = array();

        //for withdrawal
        $select = (new Select())
            ->from('u_assigned_withdrawals')
            ->columns([Select::SQL_STAR, 'type' => 'withdrawal'])
            ->where(['trust_account_id' => $trustAccountId]);

        $w_result = $this->_db2->fetchAll($select);
        if (!empty($w_result)) {
            $arrTrustAccountInfo = $w_result;
        }

        //for deposit
        $select = (new Select())
            ->from('u_assigned_deposits')
            ->columns([Select::SQL_STAR, 'type' => 'deposit'])
            ->where(['trust_account_id' => $trustAccountId]);

        $d_result = $this->_db2->fetchAll($select);
        if (!empty($d_result)) {
            $arrTrustAccountInfo = $d_result;
        }

        return $arrTrustAccountInfo;
    }

    public function editAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            $view = new ViewModel(
                [
                    'content' => null
                ]
            );
            $view->setTerminal(true);
            $view->setTemplate('layout/plain');

            return $view;
        }
        $view = new JsonModel();

        $filter = new StripTags();

        $trust_account_id = (int)$filter->filter($this->findParam('trust_account_id'));

        $tInfo = $this->_clients->getAccounting()->getTrustAccount()->getTransactionInfo($trust_account_id);

        // Check if user has access to Company T/A id
        if (!$this->_clients->hasCurrentMemberAccessToTA($tInfo['company_ta_id'])) {
            return $view->setVariables(array('success' => false));
        }


        $notes                      = $filter->filter(Json::decode($this->findParam('notes', ''), Json::TYPE_ARRAY));
        $notes                      = substr($notes, 0, 1000);
        $destination_account_id     = $filter->filter($this->findParam('destination_account_id'));
        $destination_account_custom = $filter->filter(Json::decode($this->findParam('destination_account_custom', ''), Json::TYPE_ARRAY));
        $destination_account_custom = substr($destination_account_custom, 0, 1000);

        $this->_db2->update(
            'u_trust_account',
            ['notes' => $notes],
            ['trust_account_id' => $trust_account_id]
        );

        $this->_db2->update(
            'u_assigned_withdrawals',
            [
                'destination_account_id'    => $destination_account_id,
                'destination_account_other' => $destination_account_custom,
            ],
            [
                'trust_account_id' => $trust_account_id
            ]
        );

        $this->_log->saveToLog($trust_account_id, $this->_auth->getCurrentUserId(), 'update_transaction');
        return $view->setVariables(array('success' => true));
    }

    /**
     * Delete Client Account records after certain date
     */
    public function deleteAction()
    {
        $strError      = '';
        $reconcileDate = '';

        try {
            $date = Json::decode($this->params()->fromPost('date'), Json::TYPE_ARRAY);
            $ba   = $this->params()->fromPost('ba', 'after');
            $ba   = strtolower($ba) === 'before' ? 'before' : 'after';

            $companyTAId               = (int)$this->params()->fromPost('ta_id');
            $booTimePeriod             = (bool)$this->params()->fromPost('time_period');
            $arrTransactionIdsToDelete = Json::decode($this->params()->fromPost('arr_ids'), Json::TYPE_ARRAY);

            if (empty($strError) && $booTimePeriod && !$this->_settings->isValidDate($date, 'Y-m-d')) {
                $strError = 'Incorrectly selected date';
            }

            // Check if user has access to Company T/A id
            if (empty($strError) && !$this->_clients->hasCurrentMemberAccessToTA($companyTAId)) {
                $strError = 'Insufficient access rights';
            }

            if (empty($strError) && !$booTimePeriod) {
                if (!is_array($arrTransactionIdsToDelete) || !count($arrTransactionIdsToDelete)) {
                    $strError = 'Incorrectly selected transactions';
                }
            }

            if (empty($strError)) {
                if (!$booTimePeriod) {
                    // Check if selected records can be deleted
                    list($errorCode, $reconcileDate, $date, $arrTransactionIdsToDelete) = $this->_clients->getAccounting()->canDeleteTARecords($companyTAId, $arrTransactionIdsToDelete);

                    if ($errorCode === 0 && !count($arrTransactionIdsToDelete)) {
                        $strError = 'There are no transactions to delete (incorrectly selected?).';
                    } elseif ($errorCode === 1) {
                        $strError = 'You have chosen to delete transactions that are already reconciled. ' .
                            'Deleting reconciled transactions is not permitted. ' .
                            'Please select transactions after the reconciliation period.';
                    } elseif ($errorCode === 2) {
                        $strError = 'You have selected transactions that are already assigned to some cases. ' .
                            'Please unassign these transactions before you continue.';
                    }
                } else {
                    // Get all TA records before/after this date
                    list($errorCode, $reconcileDate, $arrTransactionIdsToDelete) = $this->_clients->getAccounting()->canDeleteTARecordsBeforeAfterDate($companyTAId, $date, $ba);

                    if ($errorCode === 0 && !count($arrTransactionIdsToDelete)) {
                        $strError = 'There are no transactions to delete in the given period.';
                    } elseif ($errorCode === 1) {
                        $strError = 'You have chosen to delete transactions that are already reconciled. ' .
                            'Deleting reconciled transactions is not permitted. ' .
                            'Please specify a date after the reconciliation period.';
                    } elseif ($errorCode === 2) {
                        $strError = 'You have transactions in this period that are already assigned to some cases. ' .
                            'Please unassign these transactions before you continue.';
                    }
                }
            }

            if (empty($strError)) {
                $this->_db2->delete(
                    'u_trust_account',
                    [
                        'trust_account_id' => $arrTransactionIdsToDelete,
                        'company_ta_id'    => $companyTAId
                    ]
                );


                // If start balance record was deleted =>
                // create start balance record with deposit = 0 OR with balance from the first transaction
                if (!$this->_clients->getAccounting()->startBalanceRecordExists($companyTAId)) {
                    $arrFirstRecord   = $this->_clients->getAccounting()->getFirstTransactionInfo($companyTAId);
                    $balance          = !empty($arrFirstRecord) ? $arrFirstRecord['balance'] : 0;
                    $startBalanceDate = !empty($arrFirstRecord) ? date('c', strtotime($arrFirstRecord['date_from_bank']) - 60 * 60 * 24) : '';
                    $this->_clients->getAccounting()->createStartBalance($companyTAId, $balance, $startBalanceDate);
                }

                if ($ba == 'before') {
                    $date = false;
                }

                // Don't recalculate balances if we delete records after the specific date
                if (!($booTimePeriod && $ba == 'after')) {
                    $this->_clients->getAccounting()->updateTrustAccountRecordsBalance($companyTAId, $date);
                }
            }
        } catch (Exception $e) {
            $strError = 'Internal error';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'  => empty($strError),
            'error'    => $strError,
            'rec_date' => $reconcileDate
        );
        return new JsonModel($arrResult);
    }
}
