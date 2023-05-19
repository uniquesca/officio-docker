<?php

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */

namespace Clients\Service\Clients;

use Clients\Service\Clients;
use Exception;
use Files\Service\Files;
use Forms\Service\Pdf;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Join;
use Laminas\Db\Sql\Predicate\IsNotNull;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Filter\StripTags;
use Officio\Common\Service\BaseService;
use Officio\Service\Company;
use Officio\Common\Service\Country;
use Officio\Common\Service\Settings;
use Officio\Common\SubServiceInterface;

class TrustAccount extends BaseService implements SubServiceInterface
{

    /** @var Clients */
    protected $_clients;

    /** @var Company */
    protected $_company;

    /** @var Country */
    protected $_country;

    /** @var Accounting */
    protected $_parent;

    /** @var Files */
    protected $_files;

    /** @var Pdf */
    protected $_pdf;

    public function initAdditionalServices(array $services)
    {
        $this->_clients = $services[Clients::class];
        $this->_company = $services[Company::class];
        $this->_country = $services[Country::class];
        $this->_files   = $services[Files::class];
        $this->_pdf     = $services[Pdf::class];
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
     * @param string $type
     * @param bool $booWithoutOther
     * @return array
     */
    public function getTypeOptions($type, $booWithoutOther = false)
    {
        $arrTransactions = array();
        try {
            $companyId = $this->_auth->getCurrentUserCompanyId();

            switch ($type) {
                case 'deposit':
                    $select = (new Select())
                        ->from('u_deposit_types')
                        ->columns(array('transactionId' => 'dtl_id', 'transactionName' => 'name', 'transactionOrder' => 'order', 'transactionLocked' => 'locked'))
                        ->where(['company_id' => $companyId])
                        ->order('order');

                    $arrSavedTransactions = $this->_db2->fetchAll($select);
                    break;

                case 'withdrawal':
                    $select = (new Select())
                        ->from('u_withdrawal_types')
                        ->columns(array('transactionId' => 'wtl_id', 'transactionName' => 'name', 'transactionOrder' => 'order', 'transactionLocked' => 'locked'))
                        ->where(['company_id' => $companyId])
                        ->order('order');

                    $arrSavedTransactions = $this->_db2->fetchAll($select);
                    break;

                case 'destination':
                    $select = (new Select())
                        ->from('u_destination_types')
                        ->columns(array('transactionId' => 'destination_account_id', 'transactionName' => 'name', 'transactionOrder' => 'order'))
                        ->where(['company_id' => $companyId])
                        ->order('order');

                    $arrSavedTransactions = $this->_db2->fetchAll($select);
                    break;

                default:
                    $arrSavedTransactions = array();
            }

            if (is_array($arrSavedTransactions)) {
                $arrTransactions = array_merge($arrTransactions, $arrSavedTransactions);
            }

            if (!$booWithoutOther) {
                if ($type == 'destination') {
                    $arrTransactions[] = array(
                        'transactionId'   => -1,
                        'transactionName' => '---'
                    );
                }

                $arrTransactions [] = array(
                    'transactionId'   => 0,
                    'transactionName' => 'Other'
                );
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrTransactions;
    }

    public function deleteTypeOption($type, $optionId)
    {
        $booSuccess = false;
        try {
            $companyId = $this->_auth->getCurrentUserCompanyId();

            switch ($type) {
                case 'deposit':
                    $booSuccess = $this->_db2->delete(
                        'u_deposit_types',
                        [
                            (new Where())->notEqualTo('locked', 'Y'),
                            'dtl_id'     => $optionId,
                            'company_id' => $companyId
                        ]
                    );
                    break;

                case 'withdrawal':
                    $booSuccess = $this->_db2->delete(
                        'u_withdrawal_types',
                        [
                            (new Where())->notEqualTo('locked', 'Y'),
                            'wtl_id'     => $optionId,
                            'company_id' => $companyId
                        ]
                    );
                    break;

                case 'destination':
                    $booSuccess = $this->_db2->delete(
                        'u_destination_types',
                        [
                            'destination_account_id' => $optionId,
                            'company_id'             => $companyId
                        ]
                    );
                    break;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    public function saveTypeOption($type, $data)
    {
        try {
            $memberId  = $this->_auth->getCurrentUserId();
            $companyId = $this->_auth->getCurrentUserCompanyId();

            if (!is_array($data) || !count($data)) {
                return false;
            }

            $filter = new StripTags();
            switch ($type) {
                case 'deposit':
                    foreach ($data as $record) {
                        if (!isset($record['transactionName']) || !isset($record['transactionOrder'])) {
                            continue;
                        }

                        if (empty($record['transactionId'])) {
                            $this->_db2->insert(
                                'u_deposit_types',
                                [
                                    'company_id' => $companyId,
                                    'author_id'  => $memberId,
                                    'name'       => $filter->filter($record['transactionName']),
                                    'order'      => (int)$record['transactionOrder']
                                ]
                            );
                        } else {
                            $this->_db2->update(
                                'u_deposit_types',
                                [
                                    'name'  => $filter->filter($record['transactionName']),
                                    'order' => (int)$record['transactionOrder']
                                ],
                                [
                                    'dtl_id'     => $record['transactionId'],
                                    'company_id' => $companyId
                                ]
                            );
                        }
                    }

                    $booSuccess = true;
                    break;

                case 'withdrawal':
                    foreach ($data as $record) {
                        if (!isset($record['transactionName']) || !isset($record['transactionOrder'])) {
                            continue;
                        }

                        if (empty($record['transactionId'])) {
                            $this->_db2->insert(
                                'u_withdrawal_types',
                                [
                                    'company_id' => $companyId,
                                    'author_id'  => $memberId,
                                    'name'       => $filter->filter($record['transactionName']),
                                    'order'      => (int)$record['transactionOrder']
                                ]
                            );
                        } else {
                            $this->_db2->update(
                                'u_withdrawal_types',
                                [
                                    'name'  => $filter->filter($record['transactionName']),
                                    'order' => (int)$record['transactionOrder']
                                ],
                                [
                                    'wtl_id'     => $record['transactionId'],
                                    'company_id' => $companyId
                                ]
                            );
                        }
                    }
                    $booSuccess = true;
                    break;

                case 'destination':
                    foreach ($data as $record) {
                        if (!isset($record['transactionName']) || !isset($record['transactionOrder'])) {
                            continue;
                        }

                        if (empty($record['transactionId'])) {
                            $this->_db2->insert(
                                'u_destination_types',
                                [
                                    'company_id' => $companyId,
                                    'author_id'  => $memberId,
                                    'name'       => $filter->filter($record['transactionName']),
                                    'order'      => (int)$record['transactionOrder']
                                ]
                            );
                        } else {
                            $this->_db2->update(
                                'u_destination_types',
                                [
                                    'name'  => $filter->filter($record['transactionName']),
                                    'order' => (int)$record['transactionOrder']
                                ],
                                [
                                    'destination_account_id' => $record['transactionId'],
                                    'company_id'             => $companyId
                                ]
                            );
                        }
                    }

                    $booSuccess = true;
                    break;

                default:
                    $booSuccess = false;
                    break;
            }
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    public function getInvoicesList($companyTaId)
    {
        $arrResult = array();

        $select = (new Select())
            ->from(array('ip' => 'u_invoice_payments'))
            ->join(array('i' => 'u_invoice'), 'ip.invoice_id = i.invoice_id', array('invoice_num', 'member_id'), Join::JOIN_LEFT)
            ->join(array('w' => 'u_assigned_withdrawals'), 'w.invoice_payment_id = ip.invoice_payment_id', ['destination_account_id', 'destination_account_other'], Join::JOIN_LEFT)
            ->where(
                [
                    (new Where())
                        ->isNull('w.invoice_payment_id')
                        ->nest()
                        ->equalTo('ip.company_ta_id', (int)$companyTaId)
                        ->or
                        ->equalTo('ip.transfer_from_company_ta_id', (int)$companyTaId)
                        ->unnest()
                ]
            )
            ->group('ip.invoice_payment_id');

        $arrInvoices = $this->_db2->fetchAll($select);

        // Generate names for cases and their parents
        $arrCaseIds = array();
        foreach ($arrInvoices as $arrInvoiceInfo) {
            if (!isset($arrCaseIds[$arrInvoiceInfo['member_id']])) {
                $arrCaseIds[$arrInvoiceInfo['member_id']] = $arrInvoiceInfo['member_id'];
            }
        }
        $arrCasesList            = $this->_clients->getClientsInfo($arrCaseIds);
        $arrCasesListWithParents = $this->_clients->getCasesListWithParents($arrCasesList);

        foreach ($arrInvoices as $arrInvoiceInfo) {
            if ($arrInvoiceInfo['company_ta_id'] == $companyTaId) {
                $amount = $arrInvoiceInfo['invoice_payment_amount'];
            } else {
                $amount = empty($arrInvoiceInfo['transfer_from_amount']) ? $arrInvoiceInfo['invoice_payment_amount'] : $arrInvoiceInfo['transfer_from_amount'];
            }

            $name = '';
            foreach ($arrCasesListWithParents as $arrCaseInfo) {
                if ($arrCaseInfo['clientId'] == $arrInvoiceInfo['member_id']) {
                    $name = $arrCaseInfo['clientFullName'];
                    break;
                }
            }

            $arrResult[] = array(
                'invoicePaymentId'          => (int)$arrInvoiceInfo['invoice_payment_id'],
                'invoiceNum'                => $arrInvoiceInfo['invoice_num'],
                'clientId'                  => (int)$arrInvoiceInfo['member_id'],
                'clientName'                => $name,
                'destination_account_id'    => $arrInvoiceInfo['destination_account_id'],
                'destination_account_other' => $arrInvoiceInfo['destination_account_other'],
                'amount'                    => (float)$amount
            );
        }

        return $arrResult;
    }

    public function assignWithdrawal($transaction)
    {
        $author_id = $this->_auth->getCurrentUserId();

        $arrToInsert = array(
            'author_id'              => $author_id,
            'company_ta_id'          => (int)$transaction['company_ta_id'],
            'trust_account_id'       => (int)$transaction['trust_account_id'],
            'withdrawal'             => (double)$transaction['withdrawal'],
            'destination_account_id' => (int)$transaction['destination_account_id'],
            'date_of_event'          => date('c')
        );

        if ($transaction['destination_account_id'] == 0) {
            $arrToInsert['destination_account_other'] = $transaction['destination_account_other'];
        }

        if (isset($transaction['invoice_id'])) {
            $arrToInsert['invoice_id'] = (int)$transaction['invoice_id'];
        }

        if (isset($transaction['invoice_payment_id'])) {
            $arrToInsert['invoice_payment_id'] = (int)$transaction['invoice_payment_id'];
        }

        if (isset($transaction['member_id'])) {
            $arrToInsert['member_id'] = (int)$transaction['member_id'];
        }

        if (isset($transaction['special_transaction_id']) && $transaction['special_transaction_id'] != 0) {
            $arrToInsert['special_transaction_id'] = (int)$transaction['special_transaction_id'];
        }

        if (isset($transaction['special_transaction']) && $transaction['special_transaction_id'] == 0) {
            $arrToInsert['special_transaction'] = $transaction['special_transaction'];
        }

        if (isset($transaction['returned_payment_member_id'])) {
            $arrToInsert['returned_payment_member_id'] = (int)$transaction['returned_payment_member_id'];
        }

        // Create record in DB
        $generatedId = $this->_db2->insert('u_assigned_withdrawals', $arrToInsert);
        if ($generatedId) {
            $this->_log->saveToLog($transaction['trust_account_id'], $author_id, 'assign');
            if (isset($transaction['member_id'])) {
                //update sub total
                $this->_parent->updateTrustAccountSubTotal($transaction['member_id'], $transaction['company_ta_id']);

                //update OB
                $this->_parent->updateOutstandingBalance($transaction['member_id'], $transaction['company_ta_id']);
            } elseif (isset($transaction['returned_payment_member_id'])) {
                //update sub total
                $this->_parent->updateTrustAccountSubTotal($transaction['returned_payment_member_id'], $transaction['company_ta_id']);
            }
        }

        return $generatedId;
    }

    /**
     * Assign deposit (T/A record) to the client
     *
     * @param array $arrTransactionInfo
     * @return int generated id
     */
    public function assignDeposit($arrTransactionInfo)
    {
        $memberId = $this->_auth->getCurrentUserId();

        $arrToInsert = array(
            'author_id'        => $memberId,
            'company_ta_id'    => (int)$arrTransactionInfo['company_ta_id'],
            'trust_account_id' => (int)$arrTransactionInfo['trust_account_id'],
            'deposit'          => (double)$arrTransactionInfo['deposit'],
            'date_of_event'    => date('c'),
            'receipt_number'   => (int)$arrTransactionInfo['receipt_number']
        );


        if (isset($arrTransactionInfo['member_id'])) {
            $arrToInsert['member_id'] = (int)$arrTransactionInfo['member_id'];
        }

        if (isset($arrTransactionInfo['template_id']) && !empty($arrTransactionInfo['template_id'])) {
            $arrToInsert['template_id'] = (int)$arrTransactionInfo['template_id'];
        }

        if (isset($arrTransactionInfo['special_transaction_id']) && $arrTransactionInfo['special_transaction_id'] != 0) {
            $arrToInsert['special_transaction_id'] = (int)$arrTransactionInfo['special_transaction_id'];
        }

        if (isset($arrTransactionInfo['special_transaction']) && $arrTransactionInfo['special_transaction_id'] == 0) {
            $arrToInsert['special_transaction'] = $arrTransactionInfo['special_transaction'];
        }

        // Create record in DB
        $generatedId = $this->_db2->insert('u_assigned_deposits', $arrToInsert);
        if ($generatedId) {
            $this->_log->saveToLog($arrTransactionInfo['trust_account_id'], $memberId, 'assign');

            //update sub total
            if (isset($arrTransactionInfo['member_id'])) {
                $this->_parent->updateTrustAccountSubTotal($arrTransactionInfo['member_id'], $arrTransactionInfo['company_ta_id']);
            }
        }

        return $generatedId;
    }

    /**
     * Assign deposit from T/A to already created deposit for specific client and T/A
     *
     * @param $arrDepositInfo
     * @return int updated records count
     */
    public function assignDepositToAlreadyCreated($arrDepositInfo)
    {
        // We want update this info for already created deposit
        $arrUpdateInfo = [
            'deposit'          => (double)$arrDepositInfo['deposit'],
            'trust_account_id' => (int)$arrDepositInfo['trust_account_id']
        ];

        $arrWhere = [
            'deposit_id'    => $arrDepositInfo['deposit_id'],
            'member_id'     => $arrDepositInfo['member_id'],
            'company_ta_id' => $arrDepositInfo['company_ta_id']
        ];

        // Additional info is used to be more secure that incoming params are correct
        $result = $this->_db2->update('u_assigned_deposits', $arrUpdateInfo, $arrWhere);

        if ($result) {
            $memberId = $this->_auth->getCurrentUserId();
            $this->_log->saveToLog($arrDepositInfo['trust_account_id'], $memberId, 'assign');

            //update sub total
            $this->_parent->updateTrustAccountSubTotal($arrDepositInfo['member_id'], $arrDepositInfo['company_ta_id']);
        }

        return $result;
    }

    public function updateTransactionInfo($arrTransactionInfo)
    {
        $arrUpdateInfo = array(
            'notes' => $arrTransactionInfo['notes']
        );

        if (isset($arrTransactionInfo['payment_made_by'])) {
            $arrUpdateInfo['payment_made_by'] = $arrTransactionInfo['payment_made_by'];
        }

        return $this->_db2->update('u_trust_account', $arrUpdateInfo, ['trust_account_id' => $arrTransactionInfo['id']]);
    }


    /**
     * Check if transaction can be unassigned
     *
     * @param int $company_ta_id - company T/A id
     * @param string $date_from_bank - date from bank of transaction
     *
     * @return bool true if transaction can be unassigned, otherwise false
     */
    public function canUnassignTransaction($company_ta_id, $date_from_bank)
    {
        $booCanUnassign = true;

        $lastReconcileDate = $this->_parent->getLastReconcileDate($company_ta_id, true);
        if (!empty($lastReconcileDate) && $lastReconcileDate != '0000-00-00') {
            // We need to be sure that it is last day of the month
            list($year, $month,) = explode('-', $lastReconcileDate);
            $timeLastReconcile = strtotime('+1 month', mktime(0, 0, 0, (int)$month, 0, (int)$year));

            // Check if transaction date is less than last reconcile date
            $timeTransactionDate = strtotime($date_from_bank);
            if ($timeLastReconcile >= $timeTransactionDate) {
                $booCanUnassign = false;
            }
        }

        return $booCanUnassign;
    }


    /**
     * Un-assign transaction
     *
     * @param int $tid - transaction id
     * @return bool true if transaction was successfully unassigned, otherwise false
     */
    public function unassignTransaction($tid)
    {
        $booResult = false;

        try {
            if (is_numeric($tid) && !empty($tid)) {
                $this->_db2->getDriver()->getConnection()->beginTransaction();

                $members = $this->_parent->findMemberIdsByTransactionId($tid);

                $this->_db2->delete('u_assigned_deposits', ['trust_account_id' => $tid]);
                $this->_db2->delete('u_assigned_withdrawals', ['trust_account_id' => $tid]);

                $this->_db2->update(
                    'u_trust_account',
                    [
                        'notes'           => '',
                        'payment_made_by' => ''
                    ],
                    ['trust_account_id' => $tid]
                );

                $this->_log->saveToLog($tid, $this->_auth->getCurrentUserId(), 'unassign');

                $company_ta_id = $this->_parent->getCompanyTAIdByTrustAccountId($tid);

                // Update sub total
                if (count($members)) {
                    foreach ($members as $member_id) {
                        // In some cases member id can be null? Strange...
                        if (!empty($member_id) && is_numeric($member_id)) {
                            // update member's subtotals
                            $this->_parent->updateTrustAccountSubTotal($member_id, $company_ta_id);
                            //update member's outstanding balance
                            $this->_parent->updateOutstandingBalance($member_id, $company_ta_id);
                        }
                    }
                }

                $this->_db2->getDriver()->getConnection()->commit();
                $booResult = true;
            }
        } catch (Exception $e) {
            $this->_db2->getDriver()->getConnection()->rollback();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booResult;
    }

    public function getSpecialDepositTypeValue($name)
    {
        $select = (new Select())
            ->from(array('d' => 'u_deposit_types'))
            ->columns(array('amount'))
            ->where(['d.unique_name' => $name]);

        return $this->_db2->fetchOne($select);
    }

    public function getSpecialWithdrawalTypeValue($name)
    {
        $select = (new Select())
            ->from(array('w' => 'u_withdrawal_types'))
            ->columns(array('amount'))
            ->where(['w.unique_name' => $name]);

        return $this->_db2->fetchOne($select);
    }

    public function getSpecialTypeValue($type)
    {
        switch ($type) {
            case 'fees':
                $amount = $this->getSpecialWithdrawalTypeValue('fees');
                break;

            case 'bank_error':
                $amount = $this->getSpecialDepositTypeValue('bank_error');
                break;

            case 'interest':
                $amount = $this->getSpecialDepositTypeValue('interest');
                break;

            default:
                $amount = 0;
                break;
        }

        return $amount;
    }

    public function getSpecialTypeNameById($specialTypeId, $booIsWithdrawal = true)
    {
        if ($booIsWithdrawal) {
            $select = (new Select())
                ->from(array('w' => 'u_withdrawal_types'))
                ->columns(['name'])
                ->where(
                    [
                        'wtl_id' => (int)$specialTypeId
                    ]
                );
        } else {
            $select = (new Select())
                ->from(array('d' => 'u_deposit_types'))
                ->columns(['name'])
                ->where(
                    [
                        'dtl_id' => (int)$specialTypeId
                    ]
                );
        }

        return $this->_db2->fetchOne($select);
    }

    public function getReturnPayments($companyTAId, $startDate = false, $endDate = false)
    {
        $select = (new Select())
            ->from(array('aw' => 'u_assigned_withdrawals'))
            ->join(array('ta' => 'u_trust_account'), 'ta.trust_account_id = aw.trust_account_id', array('date_from_bank', 'description'), Select::JOIN_LEFT)
            ->where(
                [
                    (new Where())
                        ->isNotNull('aw.returned_payment_member_id')
                        ->equalTo('ta.company_ta_id', (int)$companyTAId)
                ]
            );

        if ($startDate) {
            $select->where->greaterThan('date_from_bank', $startDate);
        }

        if ($endDate) {
            $select->where->lessThanOrEqualTo('date_from_bank', $endDate);
        }

        return $this->_db2->fetchAll($select);
    }

    public function getSpecialTransactions($companyTAId, $startDate = false, $endDate = false)
    {
        $whereDate = new Where();
        if ($startDate) {
            $whereDate->greaterThan('date_from_bank', $startDate);
        }
        if ($endDate) {
            $whereDate->lessThanOrEqualTo('date_from_bank', $endDate);
        }

        //get special transactions from assigned withdrawals
        $select1 = (new Select())
            ->quantifier(new Expression("DISTINCT 'withdrawal' as type, "))
            ->from(['aw' => 'u_assigned_withdrawals'])
            ->columns(['special_transaction', 'special_transaction_id', 'amount' => 'withdrawal'])
            ->join(['ta' => 'u_trust_account'], 'ta.trust_account_id = aw.trust_account_id', 'date_from_bank', Select::JOIN_LEFT)
            ->where(
                [
                    new IsNotNull('aw.special_transaction'),
                    'ta.company_ta_id' => $companyTAId
                ]
            );
        $select2 = (new Select())
            ->quantifier(new Expression("DISTINCT 'deposit' as type, "))
            ->from(['ad' => 'u_assigned_deposits'])
            ->columns(['special_transaction', 'special_transaction_id', 'amount' => 'deposit'])
            ->join(['ta' => 'u_trust_account'], 'ta.trust_account_id = ad.trust_account_id', 'date_from_bank', Select::JOIN_LEFT)
            ->where(
                [
                    new IsNotNull('ad.special_transaction'),
                    'ta.company_ta_id' => $companyTAId
                ]
            );

        $select1->where([$whereDate]);
        $select2->where([$whereDate]);
        $select1->combine($select2);
        return $this->_db2->fetchAll($select1);
    }

    public function getDestinationType($destinationAccountId, $other = '')
    {
        if ($destinationAccountId != 0) {
            $select = (new Select())
                ->from(array('d' => 'u_destination_types'))
                ->columns(['name'])
                ->where(['destination_account_id' => (int)$destinationAccountId]);

            $name = $this->_db2->fetchOne($select);
        } else {
            $name = $other;
        }

        return empty($name) ? 'Unknown destination' : $name;
    }

    public function getTransactionInfo($trustAccountId)
    {
        $select = (new Select())
            ->from(array('ta' => 'u_trust_account'))
            ->where(['ta.trust_account_id' => $trustAccountId]);

        return is_array($trustAccountId) ? $this->_db2->fetchAll($select) : $this->_db2->fetchRow($select);
    }

    public function isCorrectTrustAccountId($trustAccountId)
    {
        if (!preg_match('/^[0-9]+$/', $trustAccountId)) {
            return false;
        }

        // Check in DB
        $select = (new Select())
            ->from('u_trust_account')
            ->columns(array('count' => new Expression('COUNT(*)')))
            ->where(['trust_account_id' => $trustAccountId]);

        return $this->_db2->fetchOne($select) > 0;
    }


    public function getTransactionsByCompanyTaId($companyTAId)
    {
        $select = (new Select())
            ->from(array('ta' => 'u_trust_account'))
            ->where(['ta.company_ta_id' => $companyTAId])
            ->order(array('ta.date_from_bank DESC', 'ta.trust_account_id DESC'));

        return $this->_db2->fetchAll($select);
    }

    /**
     * Generate link which will be used in the grid (switch to user's profile)
     *
     * @param int $caseId
     * @param bool $booPrint
     * @param bool $booFormatPrint
     * @param bool $booShortClientName
     * @return string
     */
    public function generateClientLink($caseId, $booPrint, $booFormatPrint = true, $booShortClientName = false)
    {
        list($caseAndClientName, $clientId) = $this->getParent()->getParent()->getCaseAndClientName($caseId, $booShortClientName);

        if ($booPrint) {
            $strReturn = $booFormatPrint ? '<span class="bluetxt">' . $caseAndClientName . '</span>' : $caseAndClientName;
        } else {
            $strReturn = sprintf(
                '<a href="#" onclick="setUrlHash(\'#applicants/%d/cases/%d/accounting\'); setActivePage(); return false;" title="%s">%s</a>',
                $clientId,
                $caseId,
                str_replace(['"', "'"], '`', $caseAndClientName),
                $caseAndClientName
            );
        }

        return $strReturn;
    }

    /**
     *
     * Generate link or text in relation to assigned or not transaction
     *
     *
     * Not assigned:
     * a) Show Assign link
     *
     * Deposit Assigned to:
     * a) Client: client link
     * b) Multiple Clients: Assigned + a list of clients (wrapped)
     * c) Special Transaction: name/type of the Special transaction
     *
     *
     * Withdrawal Assigned to:
     * a) Invoice:  Smith, John (file#1121) - Jan 10 2008 invoice
     * b) Special Transaction: Assigned as "type of special transaction"
     * c) Returned Payment: Client name - Returned Payment
     *
     *
     * @param array $arrTrustInfo
     * @param bool $booPrint
     * @param bool $booCanAssign
     * @param bool $booExportExcel
     * @param bool $booShortClientName
     * @return array result
     */
    private function generateAssignedColumnValues($arrTrustInfo, $booPrint, $booCanAssign, $booExportExcel, $booShortClientName = false)
    {
        $strReturn           = '';
        $strAllocationAmount = '';
        $strReceiptNumber    = '';

        if (count($arrTrustInfo) == 1) {
            // One assigned or not assigned record
            $trustInfo = $arrTrustInfo[0];

            if (empty($trustInfo['deposit_id']) && empty($trustInfo['withdrawal_id'])) {
                // This is not assigned transaction
                if (!$booPrint && $booCanAssign) {
                    if ($trustInfo['purpose'] == $this->_parent->startBalanceTransactionId) {
                        if ($trustInfo['deposit'] > 0) {
                            $strReturn = "<a href='#' onclick='assignDeposit($trustInfo[trust_account_id], $trustInfo[deposit], $trustInfo[company_ta_id]); return false;'>Assign</a>";
                        }
                    } elseif ($trustInfo['deposit'] > 0) {
                        $strReturn = "<a href='#' onclick='assignDeposit($trustInfo[trust_account_id], $trustInfo[deposit], $trustInfo[company_ta_id]); return false;'>Assign</a>";
                    } else {
                        $strReturn = "<a href='#' onclick='assignWithdrawal($trustInfo[trust_account_id], $trustInfo[withdrawal], $trustInfo[company_ta_id]); return false;'>Assign</a>";
                    }
                } else {
                    $strReturn = $this->_tr->translate('Not assigned');
                }
            } elseif (!empty($trustInfo['deposit_id'])) {
                // Assigned Deposit
                if (!empty($trustInfo['d_member_id'])) {
                    // Assigned to one client
                    $strReturn = $this->generateClientLink($trustInfo['d_member_id'], $booPrint, false, $booShortClientName);
                } elseif (!empty($trustInfo['d_special_transaction'])) {
                    $strReturn = "Special Transaction: Assigned as '" . $trustInfo['d_special_transaction'] . "'";
                } elseif (!empty($trustInfo['d_special_transaction_id'])) {
                    $st_name   = $this->getSpecialTypeNameById($trustInfo['d_special_transaction_id'], false);
                    $strReturn = "Special Transaction: Assigned as '" . $st_name . "'";
                }

                $arrDeposit = $this->_parent->getDeposit($trustInfo['deposit_id'], $trustInfo['d_member_id']);

                // Show 'Allocated amount' if this specific T/A record is assigned to several clients
                $assignedMemebersIds = $this->_parent->getDepositsByTARecordId($trustInfo['trust_account_id']);
                if (count($assignedMemebersIds) > 1) {
                    $strAllocationAmount = $arrDeposit['amount'];
                }

                if (!empty($arrDeposit['receipt_number'])) {
                    $strReceiptNumber = $arrDeposit['receipt_number'] > 10000000 ? $arrDeposit['receipt_number'] : str_pad($arrDeposit['receipt_number'], 8, '0', STR_PAD_LEFT);
                    if (!$booExportExcel && !empty($arrDeposit['template_id']) && !empty($arrDeposit['member_id'])) {
                        $strReceiptNumber = "<a href='#' onclick='downloadAssignedDepositReceipt(" . $arrDeposit['member_id'] . ',' . $arrDeposit['template_id'] . "); return false;'>" . $strReceiptNumber . '</a>';
                    }
                }
            } else {
                // Assigned Withdrawal
                if (!empty($trustInfo['w_member_id'])) {
                    $strReturn = $this->generateClientLink($trustInfo['w_member_id'], $booPrint, false, $booShortClientName);
                }

                if (!empty($trustInfo['w_invoice_payment_id'])) {
                    // Assigned to the invoice payment
                    $arrInvoicePaymentInfo = $this->_parent->getInvoicePaymentInfo($trustInfo['w_invoice_payment_id']);

                    if (empty($arrInvoicePaymentInfo)) {
                        $strReturn = 'Invoice';
                    } else {
                        // Smith, John (file#1121) - Jan 10 2008 invoice
                        $strReturn = sprintf(
                            '%s - %s %s',
                            $this->generateClientLink($arrInvoicePaymentInfo['member_id'], $booPrint, false, $booShortClientName),
                            date($this->_settings->variableGet('dateFormatFull'), strtotime($arrInvoicePaymentInfo['date_of_invoice'])),
                            $arrInvoicePaymentInfo['invoice_num'] == 'Statement' ? 'statement' : 'invoice'
                        );
                    }
                } elseif (!empty($trustInfo['w_special_transaction'])) {
                    $strReturn = "Special Transaction: Assigned as '" . $trustInfo['w_special_transaction'] . "'";
                } elseif (!empty($trustInfo['w_special_transaction_id'])) {
                    $st_name   = $this->getSpecialTypeNameById($trustInfo['w_special_transaction_id']);
                    $strReturn = "Special Transaction: Assigned as '" . $st_name . "'";
                } elseif (!empty($trustInfo['w_returned_payment_member_id'])) {
                    // Assigned to Returned Payment
                    $strReturn = $this->generateClientLink($trustInfo['w_returned_payment_member_id'], $booPrint, false, $booShortClientName) . ' - Returned Payment';
                }
            }
        } else {
            // Several assigned records for one transaction
            if (!empty($arrTrustInfo[0]['deposit_id'])) {
                // 'Assign Deposit' -> 'Multiple Clients'
                $arrReceiptNumber    = [];
                $arrAllocationAmount = [];
                $arrReturn           = [];
                foreach ($arrTrustInfo as $num => $trustInfo) {
                    $arrDeposit = $this->_parent->getDeposit($trustInfo['deposit_id'], $trustInfo['d_member_id']);

                    $arrAllocationAmount[] = $arrDeposit['amount'];
                    if (!$booExportExcel) {
                        $arrReturn[] = '<div' . ($num == 0 ? " style='padding-bottom:4px;'" : " class='trustac-devider'") . '>' . $this->generateClientLink($trustInfo['d_member_id'], $booPrint, false, $booShortClientName) . '</div>';
                    } else {
                        $arrReturn[] = $this->generateClientLink($trustInfo['d_member_id'], $booPrint, false, $booShortClientName);
                    }

                    if (!empty($arrDeposit['receipt_number'])) {
                        $thisReceiptNumber = $arrDeposit['receipt_number'] > 10000000 ? $arrDeposit['receipt_number'] : str_pad($arrDeposit['receipt_number'] ?? '', 8, '0', STR_PAD_LEFT);
                        if (!$booExportExcel) {
                            if (!empty($arrDeposit['template_id']) && !empty($arrDeposit['member_id'])) {
                                $thisReceiptNumber = "<a href='#' onclick='downloadAssignedDepositReceipt(" . $arrDeposit['member_id'] . ',' . $arrDeposit['template_id'] . "); return false;'>" . $thisReceiptNumber . '</a>';
                            }
                            $arrReceiptNumber[] = '<div' . ($num == 0 ? " style='padding-bottom: 4px;'" : " class='trustac-devider'") . '>' . $thisReceiptNumber . '</div>';
                        } else {
                            $arrReceiptNumber[] = $thisReceiptNumber;
                        }
                    }
                }

                // WTF is here? arrays/strings...
                if ($booExportExcel) {
                    $strReceiptNumber    = implode(PHP_EOL, $arrReceiptNumber);
                    $strAllocationAmount = implode(PHP_EOL, $arrAllocationAmount);
                    $strReturn           = implode(PHP_EOL, $arrReturn);
                } else {
                    $strReceiptNumber    = implode('', $arrReceiptNumber);
                    $strAllocationAmount = implode(',', $arrAllocationAmount);
                    $strReturn           = "<div style='width:100%;'>" . implode('', $arrReturn) . '</div>';
                }
            } else {
                if (!empty($arrTrustInfo[0]['withdrawal_id'])) {
                    // 'Assign Withdrawal' -> 'Multiple Invoices'
                    foreach ($arrTrustInfo as $num => $trustInfo) {
                        $strReturn .= $booPrint ? '' : "<div" . ($num == 0 ? " style='padding-bottom:4px;'" : " class='trustac-devider'") . '>';
                        if ($booPrint && !empty($strReturn)) {
                            $strReturn .= '<br>';
                        }

                        if (!empty($trustInfo['w_special_transaction'])) {
                            $strReturn .= "Special Transaction: Assigned as '" . $trustInfo['w_special_transaction'] . "'";
                        } elseif (!empty($trustInfo['w_special_transaction_id'])) {
                            $st_name   = $this->getSpecialTypeNameById($trustInfo['w_special_transaction_id']);
                            $strReturn .= "Special Transaction: Assigned as '" . $st_name . "'";
                        } elseif (!empty($trustInfo['w_invoice_payment_id'])) {
                            $arrInvoicePaymentInfo = $this->_parent->getInvoicePaymentInfo($trustInfo['w_invoice_payment_id']);

                            $invoiceLabel = is_numeric($arrInvoicePaymentInfo['invoice_num']) ? 'Invoice #' . $arrInvoicePaymentInfo['invoice_num'] : $arrInvoicePaymentInfo['invoice_num'];
                            $strReturn    .= $this->generateClientLink($arrInvoicePaymentInfo['member_id'], $booPrint, false, $booShortClientName) . ' - ' . $invoiceLabel;
                        } elseif (!empty($trustInfo['w_member_id'])) {
                            $strReturn .= $this->generateClientLink($trustInfo['w_member_id'], $booPrint, false, $booShortClientName) . '</div>';
                        }

                        $strReturn .= $booPrint ? '' : '</div>';
                    }
                    $strReturn = $booPrint ? $strReturn : "<div style='width:100%;'>" . $strReturn . "</div>";
                }
            }
        }

        return array(
            'assign_to'         => $strReturn,
            'allocation_amount' => $strAllocationAmount,
            'receipt_number'    => $strReceiptNumber
        );
    }

    /**
     * Load transactions grid
     *
     * @param int $taId
     * @param array $arrParams
     * @param bool $booPrint
     * @param string $dateFormat
     * @param bool $booExportExcel
     * @param bool $booShortClientName
     * @return array
     */
    public function getTransactionsGrid($taId, $arrParams, $booPrint, $dateFormat = '', $booExportExcel = false, $booShortClientName = false)
    {
        $arrResult             = array();
        $totalCount            = 0;
        $trustAccountBalance   = 0;
        $totalTransactionsInTA = 0;
        $startDate             = '';

        try {
            // Check if current user has access to this Client Account
            if (!$this->getParent()->getParent()->hasCurrentMemberAccessToTA($taId)) {
                // Incorrect id or has no access to it
                return array('rows' => array(), 'totalCount' => 0, 'balance' => 0, 'last_transaction_date' => '');
            }

            $wherePredicate = new Where();
            $wherePredicate->equalTo('ta.company_ta_id', $taId);

            $dir = array_key_exists('dir', $arrParams) ? strtoupper($arrParams['dir'] ?? '') : '';
            if ($dir != 'ASC') {
                $dir = 'DESC';
            }

            $sort = array_key_exists('sort', $arrParams) ? $arrParams['sort'] : '';
            switch ($sort) {
                case 'full_description':
                case 'description':
                    $rez_sort = 'ta.description ';
                    break;
                case 'date_from_bank':
                    $rez_sort = "ta.date_from_bank $dir, ta.trust_account_id $dir ";
                    $dir      = '';
                    break;
                case 'deposit':
                    $rez_sort = 'ta.deposit ';
                    break;
                case 'withdrawal':
                    $rez_sort = 'ta.withdrawal ';
                    break;
                case 'client_name':
                    $rez_sort = 'd.member_id ';
                    break;
                case 'balance':
                    $rez_sort = 'ta.balance ';
                    break;
                case 'destination_account':
                    $rez_sort = 'w.destination_account_id ';
                    break;

                default:
                    $rez_sort = 'ta.date_from_bank DESC, ta.trust_account_id DESC ';
                    $dir      = '';
            }

            $sort = $rez_sort;

            $filter = array_key_exists('filter', $arrParams) ? $arrParams['filter'] : '';
            switch ($filter) {
                case 'client_name':
                case 'client_code':
                    if ($filter === 'client_code') {
                        // Get Client's ID by File Num
                        $arrClientsIds = $this->getParent()->getParent()->getClientIdByFileNumber(
                            $arrParams['client_code'],
                            $this->_auth->getCurrentUserCompanyId(),
                            false,
                            false
                        );
                    } else {
                        $arrClientsIds = empty($arrParams['client_name']) ? [0] : [$arrParams['client_name']];
                    }

                    $arrClientsIds = empty($arrClientsIds) ? [0] : $arrClientsIds;

                    $select = (new Select())
                        ->from(array('i' => 'u_invoice'))
                        ->columns([])
                        ->join(array('ip' => 'u_invoice_payments'), 'i.invoice_id = ip.invoice_id', 'invoice_payment_id')
                        ->where([
                            'i.member_id'     => $arrClientsIds,
                            'i.company_ta_id' => (int)$taId
                        ]);

                    $arrMemberInvoicesPayments = $this->_db2->fetchCol($select);
                    $arrMemberInvoicesPayments = empty($arrMemberInvoicesPayments) ? array(0) : $arrMemberInvoicesPayments;

                    $wherePredicate
                        ->nest()
                        ->in('d.member_id', $arrClientsIds)
                        ->or
                        ->in('w.returned_payment_member_id', $arrClientsIds)
                        ->or
                        ->in('w.invoice_payment_id', $arrMemberInvoicesPayments)
                        ->unnest();
                    break;

                case 'process':
                    $wherePredicate
                        ->isNull('deposit_id')
                        ->isNull('withdrawal_id');
                    break;

                case 'today':
                    $wherePredicate->equalTo("ta.date_from_bank", date('Y-m-d'));
                    break;

                case 'period':
                    $start = $arrParams['start_date'];
                    $start = str_replace('_', '/', $start);
                    $start = str_replace('"', '', $start);
                    $start = $arrParams['start_date'] == '' ? $this->getParent()->getParent()->getCalendarStartDate() : $start;

                    $end = $arrParams['end_date'];
                    $end = str_replace('"', '', $end);
                    $end = str_replace('_', '/', $end);

                    $end = $arrParams['end_date'] == '' ? $this->_settings->formatDate(date('Y-m-d'), false) : $end;
                    $wherePredicate->between("date_from_bank", $this->_settings->toUnixDate($start, $dateFormat), $this->_settings->toUnixDate($end, $dateFormat));
                    break;

                case 'unassigned':
                    $end = $arrParams['end_date'];
                    $end = str_replace('"', '', $end);
                    $end = str_replace('_', '/', $end);

                    $end = $arrParams['end_date'] == '' ? $this->_settings->formatDate(date('Y-m-d'), false) : $end;
                    $wherePredicate
                        ->isNull('deposit_id')
                        ->isNull('withdrawal_id')
                        ->lessThanOrEqualTo("date_from_bank", $this->_settings->toUnixDate($end));
                    break;

                case '30days':
                    $wherePredicate->greaterThanOrEqualTo("ta.date_from_bank", date('Y-m-d', strtotime('today -1 month')));
                    break;

                case 'all':
                default:
                    break;
            }

            //start and(or) limit filter
            $start = array_key_exists('start', $arrParams) ? (int)$arrParams['start'] : '';
            $limit = array_key_exists('limit', $arrParams) ? (int)$arrParams['limit'] : '';

            // Calculate transactions count
            $select = (new Select())
                ->from(['ta' => 'u_trust_account'])
                ->columns(['trust_account_id'])
                ->join(['w' => 'u_assigned_withdrawals'], 'ta.trust_account_id = w.trust_account_id', [], Select::JOIN_LEFT)
                ->join(['d' => 'u_assigned_deposits'], 'ta.trust_account_id = d.trust_account_id', [], Select::JOIN_LEFT)
                ->where([$wherePredicate])
                ->group('ta.trust_account_id');

            $totalCount = count($this->_db2->fetchCol($select));

            // Earlier it was a bug during rendering of Client T/A grid - "we did limit not on trust_account_id but on deposits or withdrawal count"
            // that's why in grid we had not divided value rows but less due to the same deposits had more than one assigned case.
            // This bug influenced on situation when deposit for example had 2 assigned cases.
            $selectTA = (new Select())
                ->quantifier(Select::QUANTIFIER_DISTINCT)
                ->from(['ta' => 'u_trust_account'])
                ->columns(['id' => 'trust_account_id'])
                ->join(['w' => 'u_assigned_withdrawals'], 'ta.trust_account_id = w.trust_account_id', [], Select::JOIN_LEFT)
                ->join(['d' => 'u_assigned_deposits'], 'ta.trust_account_id = d.trust_account_id', [], Select::JOIN_LEFT)
                ->where([$wherePredicate])
                ->order($sort . ' ' . $dir)
                ->offset($start == '' ? 0 : $start);

            if ($limit !== '') {
                $selectTA->limit($limit);
            }

            $trustAccsIds = $this->_db2->fetchCol($selectTA);

            $result = array();
            if (!empty($trustAccsIds)) {
                $wherePredicate->in('ta.trust_account_id', $trustAccsIds);

                // Get transactions (filtered and/or limited)
                $select = (new Select())
                    ->from(['ta' => 'u_trust_account'])
                    ->columns([Select::SQL_STAR])
                    ->join(
                        ['w' => 'u_assigned_withdrawals'],
                        'ta.trust_account_id = w.trust_account_id',
                        [
                            'withdrawal_id',
                            'w_invoice_payment_id'         => 'invoice_payment_id',
                            'w_member_id'                  => 'member_id',
                            'w_special_transaction'        => 'special_transaction',
                            'w_special_transaction_id'     => 'special_transaction_id',
                            'w_returned_payment_member_id' => 'returned_payment_member_id',
                            'w_destination_account_id'     => 'destination_account_id',
                            'w_destination_account_other'  => 'destination_account_other'
                        ],
                        Select::JOIN_LEFT
                    )
                    ->join(
                        ['d' => 'u_assigned_deposits'],
                        'ta.trust_account_id = d.trust_account_id',
                        [
                            'deposit_id',
                            'd_member_id'              => 'member_id',
                            'd_special_transaction'    => 'special_transaction',
                            'd_special_transaction_id' => 'special_transaction_id',
                        ],
                        Select::JOIN_LEFT
                    )
                    ->where([$wherePredicate])
                    ->order($sort . ' ' . $dir);

                $result = $this->_db2->fetchAll($select);
            }

            //calculate TA Balance (end of period)
            $trustAccountBalance = $this->_parent->calculateTABalance($taId);

            $arrTrustAccountInfo = array();
            foreach ($result as $tInfo) {
                $arrTrustAccountInfo[$tInfo['trust_account_id']][] = $tInfo;
            }

            $booCanEdit     = $this->_acl->isAllowed('trust-account-edit-view');
            $booCanAssign   = $this->_acl->isAllowed('trust-account-assign-view');
            $strCanUnAssign = $booCanAssign ? 'true' : 'false';

            // Generate Result
            foreach ($arrTrustAccountInfo as $arrInfo) {
                $transactionInfo = $arrInfo[0];

                // Check if record is assigned
                $booIsAssigned = !empty($transactionInfo['deposit_id']) || !empty($transactionInfo['withdrawal_id']);

                $customDestinationName = '';

                if ($booIsAssigned) {
                    $customDestinationName = $this->getDestinationType($transactionInfo['trust_account_id'], $transactionInfo['w_destination_account_other']);


                    if ($booPrint || !$booCanEdit) {
                        $date_from_bank = $this->_settings->formatDate($transactionInfo['date_from_bank']);
                    } else {
                        $jsFunc = "showEditTransaction($transactionInfo[trust_account_id], $strCanUnAssign, $transactionInfo[company_ta_id]); return false;";

                        $date_from_bank = '<a href="#" onclick="' . $jsFunc . '" title="Edit transaction #' . $transactionInfo['trust_account_id'] . '">' . $this->_settings->formatDate($transactionInfo['date_from_bank']) . '</a>';
                    }
                } else {
                    $date_from_bank = $this->_settings->formatDate($transactionInfo['date_from_bank']);
                }

                $notes       = trim($transactionInfo['notes'] ?? '');
                $description = empty($notes) ? $transactionInfo['description'] : $transactionInfo['description'] . ' - ' . $notes;
                if ($booCanEdit) {
                    $description = sprintf(
                        '<a href="#" onclick="editNote(%d, %d); return false;" title="%s">%s</a>',
                        $transactionInfo['trust_account_id'],
                        $taId,
                        str_replace(['"', "'"], '`', $description),
                        $description
                    );
                }

                $arrValues      = $this->generateAssignedColumnValues($arrInfo, $booPrint, $booCanAssign, $booExportExcel, $booShortClientName);
                $arrValuesPrint = $this->generateAssignedColumnValues($arrInfo, true, $booCanAssign, $booExportExcel, $booShortClientName);
                $paymentMadeBy  = $this->getPaymentMadeByOptions();

                $arrResult[] = array(
                    'id'                  => $transactionInfo['trust_account_id'],
                    'date_from_bank'      => $date_from_bank,
                    'description'         => $description,
                    'deposit'             => $transactionInfo['deposit'],
                    'withdrawal'          => $transactionInfo['withdrawal'],
                    'balance'             => $transactionInfo['balance'],
                    'purpose'             => $transactionInfo['purpose'],
                    'notes'               => $notes,
                    'assigned'            => $booIsAssigned,
                    'client_name'         => $arrValues['assign_to'],
                    'client_name_text'    => $arrValuesPrint['assign_to'],
                    'destination_account' => $customDestinationName,
                    'allocation_amount'   => $arrValues['allocation_amount'],
                    'receipt_number'      => $arrValues['receipt_number'],
                    'payment_method'      => array_key_exists($transactionInfo['payment_made_by'], $paymentMadeBy['arrMapper']) ? $paymentMadeBy['arrMapper'][$transactionInfo['payment_made_by']] : $transactionInfo['payment_made_by']
                );
            }

            //get total transactions in t\a without filters
            $select = (new Select())
                ->from('u_trust_account')
                ->columns(['count' => new Expression('COUNT(*)')])
                ->where(['company_ta_id' => (int)$taId]);

            $totalTransactionsInTA = $this->_db2->fetchOne($select);

            $startBalanceInfo  = $this->_parent->getStartBalanceInfo($taId);
            $startBalanceDate  = isset($startBalanceInfo['date_from_bank']) ? date('Y-m-d', strtotime($startBalanceInfo['date_from_bank'] . ' + 1 days')) : '';
            $lastReconcileDate = $this->_parent->getLastReconcileDate($taId, true);

            // Allow to create new transactions if there is only one start balance record and no reconciliation was done
            if (Settings::isDateEmpty($lastReconcileDate) && $totalTransactionsInTA == 1 && isset($startBalanceInfo['date_from_bank'])) {
                $startDate = null;

                // Show "please import records" image
                $totalTransactionsInTA = 0;
            } else {
                $startDate = Settings::isDateEmpty($lastReconcileDate) ? $startBalanceDate : date('Y-m-d', strtotime($lastReconcileDate . ' + 1 days'));
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return array(
            'rows'                  => $arrResult,
            'totalCount'            => $totalCount,
            'balance'               => $trustAccountBalance,
            'totalTransactionsInTA' => $totalTransactionsInTA,
            'lastTransactionDate'   => $startDate
        );
    }

    public function getTransactionsCount($companyTaId, $date, $booAfter = true)
    {
        $select = (new Select())
            ->from('u_trust_account')
            ->columns(['count' => new Expression('COUNT(trust_account_id)')])
            ->where(['company_ta_id' => (int)$companyTaId]);

        if ($booAfter) {
            $select->where->greaterThan('date_from_bank', $date);
        } else {
            $select->where->lessThanOrEqualTo('date_from_bank', $date);
        }

        return $this->_db2->fetchOne($select);
    }

    public function getUnassignedWithdrawalsCount($companyTaId, $date)
    {
        $select = (new Select())
            ->from(array('ta' => 'u_trust_account'))
            ->columns(['count' => new Expression('COUNT(ta.trust_account_id)')])
            ->join(array('w' => 'u_assigned_withdrawals'), 'ta.trust_account_id = w.trust_account_id', [], Select::JOIN_LEFT)
            ->where(
                [
                    (new Where())
                        ->equalTo('ta.company_ta_id', (int)$companyTaId)
                        ->lessThanOrEqualTo('ta.date_from_bank', $date)
                        ->isNull('w.withdrawal_id')
                        ->greaterThan('ta.withdrawal', 0)
                ]
            );

        return $this->_db2->fetchOne($select);
    }

    public function getUnassignedDepositsCount($companyTaId, $date)
    {
        $select = (new Select())
            ->from(array('ta' => 'u_trust_account'))
            ->columns(['count' => new Expression('COUNT(ta.trust_account_id)')])
            ->join(array('d' => 'u_assigned_deposits'), 'ta.trust_account_id = d.trust_account_id', [], Select::JOIN_LEFT)
            ->where(
                [
                    (new Where())
                        ->equalTo('ta.company_ta_id', (int)$companyTaId)
                        ->lessThanOrEqualTo('ta.date_from_bank', $date)
                        ->isNull('d.deposit_id')
                        ->greaterThan('ta.deposit', 0)
                ]
            );

        return $this->_db2->fetchOne($select);
    }

    private function generateGeneralReconciliationReportContent($companyTaId, $endDate, $reconcileType)
    {
        $startDate = $this->_parent->getLastReconcileDate($companyTaId, false, $reconcileType);
        $currency  = $this->_parent->getCurrency($companyTaId);

        $clients   = $this->_parent->getTAMembers($companyTaId);
        $rr_period = $this->_settings->formatDate($endDate);
        $ta_info   = $this->_parent->getTAInfo($companyTaId);


        $balance_cel_1_width = 40;
        $balance_cel_2_width = 300;
        $balance_cel_3_width = 50;

        $balance_per_bank_statement = $this->_parent->calculateTABalance($companyTaId, $startDate, $endDate);

        $bank_error = $this->getSpecialTypeValue('bank_error');

        //get unassigned invoices
        $arrUnassignedInvoicePayments         = $this->_parent->getUnassignedInvoicePayments($companyTaId, $startDate, $endDate);
        $arrUnassignedInvoicePaymentsPrepared = array();
        $unassignedInvoicePaymentsSum         = 0;

        foreach ($arrUnassignedInvoicePayments as $invoicePayment) {
            $unassignedInvoicePaymentsSum -= $invoicePayment['invoice_payment_amount'];
            list($caseAndClientName,) = $this->getParent()->getParent()->getCaseAndClientName($invoicePayment['member_id']);

            $arrUnassignedInvoicePaymentsPrepared[] = array(
                'invoice_number'   => $invoicePayment['invoice_num'],
                'date_of_creation' => $this->_settings->formatDate($invoicePayment['invoice_payment_date']),
                'amount'           => $this->_parent::formatPrice($invoicePayment['invoice_payment_amount'] * -1, $currency),
                'client'           => $caseAndClientName,
                'cheque_num'       => $invoicePayment['invoice_payment_cheque_num']
            );
        }

        $special_transactions = $this->getSpecialTransactions($companyTaId, $startDate, $endDate);

        $special_transactions_sum = 0;
        foreach ($special_transactions as &$transaction) {
            //update name
            if (!empty($transaction['special_transaction_id'])) {
                $transaction['name'] = $this->getSpecialTypeNameById($transaction['special_transaction_id'], $transaction['type'] == 'withdrawal');
            } else {
                $transaction['name'] = $transaction['special_transaction'];
            }

            //update amount
            if ($transaction['type'] == 'withdrawal') {
                $transaction['amount'] *= -1;
            }

            $special_transactions_sum += $transaction['amount'];
        }
        unset($transaction);

        $ta_summary_arr = $this->_parent->getNotClearedDeposits($companyTaId, false, $startDate, $endDate);
        $rowspan        = (count($special_transactions) > 0 ? count($special_transactions) + 1 : 0) + (count($ta_summary_arr) > 0 ? count($ta_summary_arr) + 1 : 0) + 3;

        $html = '<span align="center">' . $this->_company->getCurrentCompanyDefaultLabel('trust_account') . ' - ' . $ta_info['name'] .
            '<br />For Period Ending on ' . $rr_period . '</span>

                 <br /><br />
                 <table border="0" cellspacing="0" cellpadding="0">
                 <tr>
                     <td width="' . ($balance_cel_1_width + $balance_cel_2_width) . '" colspan="2">Balance per bank statement, ' . $rr_period . '</td>
                     <td width="' . $balance_cel_3_width . '" align="right">' . $this->_parent::formatPrice($balance_per_bank_statement, $currency) . '</td>
                 </tr>
                 <tr>
                     <td width="' . $balance_cel_1_width . '" rowspan="' . $rowspan . '">Add:</td>';

        $deposit_in_transit = $this->_parent->calculateNotClearedDepositsTotal($companyTaId, false, $startDate, $endDate);

        if (count($ta_summary_arr) == 0) {
            $html .= '<td width="' . $balance_cel_2_width . '">Deposits in Transit*</td>
                         <td width="' . $balance_cel_3_width . '" align="right">' . $this->_parent::formatPrice($deposit_in_transit, $currency) . '</td>
                      </tr>';
        } else {
            $html .= '<td width="' . ($balance_cel_2_width + $balance_cel_3_width) . '" colspan="2">Deposits in Transit*</td>
                 </tr>';

            foreach ($ta_summary_arr as $t) {
                $html .= '<tr>
                        <td width="' . ($balance_cel_2_width - 50) . '">&nbsp;&nbsp;&nbsp;&nbsp;' . $t['description'] . ' - ' . $this->_settings->formatDate($t['date_of_event']) . '</td>
                        <td width="' . $balance_cel_3_width . '" align="right">' . $this->_parent::formatPrice($t['deposit'], $currency) . '</td>
                    </tr>';
            }

            $html .= '<tr>
                     <td width="' . $balance_cel_2_width . '">&nbsp;</td>
                     <td width="' . $balance_cel_3_width . '" align="right">' . $this->_parent::formatPrice($deposit_in_transit, $currency) . '</td>
                     </tr>';
        }

        $html .= '<tr>
                     <td width="' . $balance_cel_2_width . '">Bank Error</td>
                     <td width="' . $balance_cel_3_width . '" align="right">' . $this->_parent::formatPrice($bank_error, $currency) . '</td>
                 </tr>';

        if (count($special_transactions) == 0) {
            $html .= '<tr>
                         <td width="' . $balance_cel_2_width . '">Special Transactions</td>
                         <td width="' . $balance_cel_3_width . '" align="right">' . $this->_parent::formatPrice($special_transactions_sum, $currency) . '</td>
                      </tr>';
        } else {
            $html .= '<tr>
                     <td width="' . ($balance_cel_2_width + $balance_cel_3_width) . '" colspan="2">Special Transactions</td>
                 </tr>';

            foreach ($special_transactions as $special_transaction) {
                $html .= '<tr>
                        <td width="' . ($balance_cel_2_width - 50) . '">&nbsp;&nbsp;&nbsp;&nbsp;' . $special_transaction['name'] . ' - ' . $special_transaction['type'] . ' - ' . $this->_settings->formatDate(
                        $special_transaction['date_from_bank']
                    ) . '</td>
                        <td width="' . $balance_cel_3_width . '" align="right">' . $this->_parent::formatPrice($special_transaction['amount'], $currency) . '</td>
                    </tr>';
            }

            $html .= '<tr>
                     <td width="' . $balance_cel_2_width . '">&nbsp;</td>
                     <td width="' . $balance_cel_3_width . '" align="right">' . $this->_parent::formatPrice($special_transactions_sum, $currency) . '</td>
                     </tr>';
        }

        $rowspan = (count($arrUnassignedInvoicePaymentsPrepared) > 0 ? count($arrUnassignedInvoicePaymentsPrepared) + 1 : 0) + 1;

        $html .= '<tr>
                     <td width="' . $balance_cel_1_width . '" rowspan="' . $rowspan . '">Deduct:</td>';

        if (count($arrUnassignedInvoicePaymentsPrepared) == 0) {
            $html .= '<td width="' . $balance_cel_2_width . '">Outstanding Cheques</td>
                      <td width="' . $balance_cel_3_width . '" align="right">' . $this->_parent::formatPrice($unassignedInvoicePaymentsSum, $currency) . '</td>
                 </tr>';
        } else {
            $html .= '<td width="' . ($balance_cel_2_width + $balance_cel_3_width) . '" colspan="2">Outstanding Cheques</td>
                 </tr>';

            foreach ($arrUnassignedInvoicePaymentsPrepared as $unassigned_invoice) {
                $html .= '<tr>
                        <td width="' . ($balance_cel_2_width - 50) . '">&nbsp;&nbsp;&nbsp;&nbsp;Invoice#: ' . $unassigned_invoice['invoice_number'] . ' (' . $unassigned_invoice['date_of_creation'] . ') - Cheque#: ' . $unassigned_invoice['cheque_num'] . ' - Client: ' . $unassigned_invoice['client'] . '</td>
                        <td width="' . $balance_cel_3_width . '" align="right">' . $unassigned_invoice['amount'] . '</td>
                    </tr>';
            }

            $html .= '<tr>
                         <td width="' . $balance_cel_2_width . '">&nbsp;</td>
                         <td width="' . $balance_cel_3_width . '" align="right">' . $this->_parent::formatPrice($unassignedInvoicePaymentsSum, $currency) . '</td>
                     </tr>';
        }

        //Client Account Summary Balance
        $trust_ac_sub_total_sum = $this->_parent->calculateTrustAccountSubTotal($clients, $companyTaId, $startDate, $endDate);

        $true_cash_balance_per_statement = floatval($balance_per_bank_statement) + floatval($deposit_in_transit) + floatval($bank_error) + floatval($unassignedInvoicePaymentsSum);

        $interest_earned = $this->getSpecialTypeValue('interest');

        //get unassigned deposits
        $arrUnassignedDeposits   = $this->_parent->calculateUnassignedDeposits($companyTaId, $startDate, $endDate);
        $unassigned_deposits     = array();
        $unassigned_deposits_sum = 0;
        foreach ($arrUnassignedDeposits as $deposit) {
            $unassigned_deposits_sum += $deposit['deposit'];

            $unassigned_deposits[] = array(
                'date_from_bank' => $deposit['date_from_bank'],
                'description'    => $deposit['description'],
                'price'          => $this->_parent::formatPrice($deposit['deposit'], $currency)
            );
        }

        $rowspan = (count($unassigned_deposits) > 0 ? count($unassigned_deposits) + 1 : 0) + 2;

        $html .= '<tr>
                     <td width="' . ($balance_cel_1_width + $balance_cel_2_width) . '" colspan="2"><br />True cash balance, ' . $rr_period . '</td>
                     <td width="' . $balance_cel_3_width . '" align="right"><br />' . $this->_parent::formatPrice($true_cash_balance_per_statement, $currency) . '</td>
                 </tr>
                 </table>

                 <br>

                 <table border="0" cellspacing="0" cellpadding="0">
                 <tr>
                     <td width="' . ($balance_cel_1_width + $balance_cel_2_width) . '" colspan="2">Client Balances per books, ' . $rr_period . '</td>
                     <td width="' . $balance_cel_3_width . '" align="right">' . $this->_parent::formatPrice($trust_ac_sub_total_sum, $currency) . '</td>
                 </tr>
                 <tr>
                     <td width="' . $balance_cel_1_width . '" rowspan="' . $rowspan . '">Add:</td>
                     <td width="' . $balance_cel_2_width . '">Interest earned on account</td>
                     <td width="' . $balance_cel_3_width . '" align="right">' . $this->_parent::formatPrice($interest_earned, $currency) . '</td>
                 </tr>';

        if (count($unassigned_deposits) == 0) {
            $html .= '<tr>
                         <td width="' . $balance_cel_2_width . '">Deposits not assigned to clients, ' . $rr_period . '</td>
                         <td width="' . $balance_cel_3_width . '" align="right">' . $this->_parent::formatPrice($unassigned_deposits_sum, $currency) . '</td>
                     </tr>';
        } else {
            $html .= '<tr>
                     <td width="' . ($balance_cel_2_width + $balance_cel_3_width) . '" colspan="2">Deposits not assigned to clients, ' . $rr_period . '</td>
                 </tr>';

            foreach ($unassigned_deposits as $unassigned_deposit) {
                $html .= '<tr>
                         <td width="' . ($balance_cel_2_width - 50) . '">&nbsp;&nbsp;&nbsp;&nbsp;' . $this->_settings->formatDate($unassigned_deposit['date_from_bank']) . ' - ' . $unassigned_deposit['description'] . '</td>
                         <td width="' . $balance_cel_3_width . '" align="right">' . $unassigned_deposit['price'] . '</td>
                     </tr>';
            }

            $html .= '<tr>
                         <td width="' . $balance_cel_2_width . '">&nbsp;</td>
                         <td width="' . $balance_cel_3_width . '" align="right">' . $this->_parent::formatPrice($unassigned_deposits_sum, $currency) . '</td>
                     </tr>';
        }


        $return_payments_arr = $this->getReturnPayments($companyTaId, $startDate, $endDate);
        $return_payments     = array();
        $return_payments_sum = 0;
        foreach ($return_payments_arr as $payment) {
            $return_payments_sum -= $payment['withdrawal'];
            list($caseAndClientName,) = $this->getParent()->getParent()->getCaseAndClientName($payment['returned_payment_member_id']);

            $return_payments[] = array(
                'client'         => $caseAndClientName,
                'date_from_bank' => $payment['date_from_bank'],
                'description'    => $payment['description'],
                'withdrawal'     => $this->_parent::formatPrice($payment['withdrawal'] * -1, $currency)
            );
        }

        $bank_charges = $this->getSpecialTypeValue('fees');

        $rowspan = (count($return_payments) > 0 ? count($return_payments) + 1 : 0) + 3;

        $html .= '<tr>
                     <td width="' . $balance_cel_1_width . '" rowspan="' . $rowspan . '">Deduct:</td>
                     <td width="' . $balance_cel_2_width . '">Bank Charges</td>
                     <td width="' . $balance_cel_3_width . '" align="right">' . $this->_parent::formatPrice($bank_charges, $currency) . '</td>
                 </tr>';

        if (count($return_payments) == 0) {
            $html .= '<tr>
                         <td width="' . $balance_cel_2_width . '">NSF Cheques</td>
                         <td width="' . $balance_cel_3_width . '" align="right">' . $this->_parent::formatPrice($return_payments_sum, $currency) . '</td>
                     </tr>';
        } else {
            $html .= '<tr>
                         <td width="' . ($balance_cel_2_width + $balance_cel_3_width) . '" colspan="2">NSF Cheques</td>
                 </tr>';

            foreach ($return_payments as $payment) {
                $html .= '<tr>
                         <td width="' . ($balance_cel_2_width - 50) . '">&nbsp;&nbsp;&nbsp;&nbsp;' . $this->_settings->formatDate($payment['date_from_bank']) . ' - ' . $payment['description'] . ' - Client: ' . $payment['client'] . '</td>
                         <td width="' . $balance_cel_3_width . '" align="right">' . $payment['withdrawal'] . '</td>
                     </tr>';
            }

            $html .= '<tr>
                         <td width="' . $balance_cel_2_width . '">&nbsp;</td>
                         <td width="' . $balance_cel_3_width . '" align="right">' . $this->_parent::formatPrice($return_payments_sum, $currency) . '</td>
                     </tr>';
        }

        $company_error               = 0;
        $true_cash_balance_per_books = $trust_ac_sub_total_sum + $interest_earned + $unassigned_deposits_sum + $bank_charges + $return_payments_sum + $company_error;

        $html .= '<tr>
                     <td width="' . $balance_cel_2_width . '">Company Error</td>
                     <td width="' . $balance_cel_3_width . '" align="right">' . $this->_parent::formatPrice($company_error, $currency) . '</td>
                 </tr>
                 <tr>
                     <td width="' . ($balance_cel_1_width + $balance_cel_2_width) . '" colspan="2"><br />True cash balance, ' . $rr_period . '</td>
                     <td width="' . $balance_cel_3_width . '" align="right"><br />' . $this->_parent::formatPrice($true_cash_balance_per_books, $currency) . '</td>
                 </tr>
                 </table>

                 <br />
                 *Company or client has made the deposit, but it was not recorded by the bank as of the date of bank statement.';

        return $html;
    }

    public function generateICCRCReconciliationReportContent($companyTaId, $endDate, $balanceDate, $balanceNotes, $booReturnBalanceOnly = false)
    {
        $startDate = date('Y-m-01', strtotime($endDate));

        $currency = $this->_parent->getCurrency($companyTaId);

        $rr_period = $this->_settings->formatDate($endDate);
        $ta_info   = $this->_parent->getTAInfo($companyTaId);

        $header = '<span align="center">' . sprintf($this->_tr->translate('Bank Reconciliation - %s<br />' . $this->_company->getCurrentCompanyDefaultLabel('trust_account') . ' - %s'), $rr_period, $ta_info['name']) . '</span>';
        $html   = $header . '<br /><br />';

        $col1Width     = 'width="60"';
        $col2Width     = 'width="150"';
        $col1And2Width = 'width="210"';
        $col3Width     = 'width="50"';
        $col4Width     = 'width="50"';
        $col5Width     = 'width="50"';
        $col6Width     = 'width="150"';

        $fontBold    = 'style="font-weight: bold;"';
        $alignRight  = 'align="right"';
        $alignCenter = 'align="center"';

        $html .= '<h4>' . sprintf($this->_tr->translate('Verified Transactions Per Bank Statement %s'), $rr_period) . '</h4><br />';
        $html .= '<table border="1" cellspacing="0" cellpadding="2">
                     <tr>
                         <th ' . $fontBold . $col1Width . $alignCenter . '>' . $this->_tr->translate('Date from Bank') . '</th>
                         <th ' . $fontBold . $col2Width . '>' . $this->_tr->translate('Description') . '</th>
                         <th ' . $fontBold . $col3Width . $alignRight . '>' . $this->_tr->translate('Deposit') . '</th>
                         <th ' . $fontBold . $col4Width . $alignRight . '>' . $this->_tr->translate('Withdrawal') . '</th>
                         <th ' . $fontBold . $col5Width . $alignCenter . '>' . $this->_tr->translate('Balance') . '</th>
                         <th ' . $fontBold . $col6Width . '>' . $this->_tr->translate('Assigned to') . '</th>
                     </tr>';

        $arrParams = array(
            'sort'       => 'date_from_bank',
            'dir'        => 'ASC',
            'filter'     => 'period',
            'start_date' => date('m/01/Y', strtotime($endDate)),
            'end_date'   => date('m/d/Y', strtotime($endDate)),
        );

        // In the new version - we show shortened clients names + "zero" records
        $booICCRCNewReport = (bool)$this->_config['site_version']['iccrc_reconciliation_hide_names'];

        $arrAllTransactions = $this->getTransactionsGrid($companyTaId, $arrParams, false, 'm/d/Y', false, $booICCRCNewReport);

        $openingBalance          = 0;
        $totalDepositsCleared    = 0;
        $totalWithdrawalsCleared = 0;

        if (empty($arrAllTransactions['rows'])) {
            // Nothing found in this month, so try to load/use the last record
            $arrParams = array(
                'sort'       => 'date_from_bank',
                'dir'        => 'DESC',
                'filter'     => 'period',
                'start_date' => '01/01/1971',
                'end_date'   => date('m/d/Y', strtotime($endDate)),
            );

            $arrAllPreviousTransactions = $this->getTransactionsGrid($companyTaId, $arrParams, false, 'm/d/Y', false, $booICCRCNewReport);

            if (count($arrAllPreviousTransactions['rows'])) {
                $arrAllTransactions['rows'] = array(
                    array(
                        'purpose'     => $this->_parent->startBalanceTransactionId,
                        'deposit'     => $arrAllPreviousTransactions['rows'][0]['balance'] + $arrAllPreviousTransactions['rows'][0]['deposit'] - $arrAllPreviousTransactions['rows'][0]['withdrawal'],
                        'client_name' => $arrAllPreviousTransactions['rows'][0]['client_name']
                    )
                );
            }
        }


        if (count($arrAllTransactions['rows'])) {
            if ($arrAllTransactions['rows'][0]['purpose'] == $this->_parent->startBalanceTransactionId) {
                $openingBalance = $arrAllTransactions['rows'][0]['deposit'];
            } else {
                $openingBalance = $arrAllTransactions['rows'][0]['balance'];
            }
            $html .= '<tr>' .
                "<td $col1Width>&nbsp;</td>" .
                "<td $col2Width>" . $this->_tr->translate('Opening balance') . '</td>' .
                "<td $col3Width>&nbsp;</td>" .
                "<td $col4Width>&nbsp;</td>" .
                "<td $col5Width $fontBold $alignCenter>" . $this->_parent::formatPrice($openingBalance, $currency) . '</td>' .
                "<td $col6Width>" . $this->filterTags($arrAllTransactions['rows'][0]['client_name']) . "</td>" .
                '</tr>';

            foreach ($arrAllTransactions['rows'] as $arrTransactionInfo) {
                // Skip starting balance because it will be showed in a separate row (look above)
                if ($arrTransactionInfo['purpose'] == $this->_parent->startBalanceTransactionId) {
                    continue;
                }

                $totalDepositsCleared    += (float)$arrTransactionInfo['deposit'];
                $totalWithdrawalsCleared += (float)$arrTransactionInfo['withdrawal'];

                $html .= '<tr>' .
                    "<td $col1Width $alignCenter>" . $this->filterTags($arrTransactionInfo['date_from_bank']) . '</td>' .
                    "<td $col2Width>" . $this->filterTags($arrTransactionInfo['description']) . '</td>' .
                    "<td $col3Width $alignRight>" . (((float)$arrTransactionInfo['deposit'] == 0) ? '&nbsp;' : $this->_parent::formatPrice($arrTransactionInfo['deposit'], $currency)) . '</td>' .
                    "<td $col4Width $alignRight>" . ((float)$arrTransactionInfo['withdrawal'] == 0 ? '&nbsp;' : $this->_parent::formatPrice($arrTransactionInfo['withdrawal'], $currency)) . '</td>' .
                    "<td $col5Width>&nbsp;</td>" .
                    "<td $col6Width>" . $this->filterTags($arrTransactionInfo['client_name']) . '</td>
                 </tr>';
            }
        } else {
            $html .= '<tr>' .
                "<td $col1And2Width colspan=\"2\">" . $this->_tr->translate('There are no records.') . '</td>' .
                "<td $col3Width>&nbsp;</td>" .
                "<td $col4Width>&nbsp;</td>" .
                "<td $col5Width>&nbsp;</td>" .
                "<td $col6Width>&nbsp;</td>" .
                '</tr>';
        }

        $html .= '</table>';

        $html .= '<table border="0" cellspacing="0" cellpadding="2">';
        $html .= '<tr>' .
            '<td ' . $col1And2Width . $fontBold . '>' . sprintf($this->_tr->translate('Total deposits made and verified as of %s:'), $rr_period) . '</td>' .
            "<td $col3Width $fontBold $alignRight>" . $this->_parent::formatPrice($totalDepositsCleared, $currency) . '</td>' .
            "<td $col4Width $fontBold>&nbsp;</td>" .
            "<td $col5Width $fontBold>&nbsp;</td>" .
            '</tr>' .
            '<tr>' .
            '<td ' . $col1And2Width . $fontBold . '>' . sprintf($this->_tr->translate('Total withdrawals made and verified as of %s:'), $rr_period) . '</td>' .
            "<td $col3Width $fontBold>&nbsp;</td>" .
            "<td $col4Width $fontBold $alignRight>" . $this->_parent::formatPrice($totalWithdrawalsCleared, $currency) . '</td>' .
            "<td $col5Width $fontBold>&nbsp;</td>" .
            '</tr>' .
            '<tr>' .
            '<td ' . $col1And2Width . $fontBold . '>' . sprintf($this->_tr->translate('Balance of verified transactions as of %s:'), $rr_period) . '</td>' .
            "<td $col3Width $fontBold>&nbsp;</td>" .
            "<td $col4Width $fontBold>&nbsp;</td>" .
            "<td $col5Width $fontBold $alignCenter>" . $this->_parent::formatPrice($openingBalance + $totalDepositsCleared - $totalWithdrawalsCleared, $currency) . '</td>' .
            '</tr>';
        $html .= '</table><br /><br />';


        // Unverified Transactions
        $html .= '<h4>' . sprintf($this->_tr->translate('Unverified Transactions %s'), $rr_period) . '</h4><br />';
        $html .= '<table border="1" cellspacing="0" cellpadding="2">
                     <tr>
                         <th ' . $fontBold . $col1Width . $alignCenter . '>' . $this->_tr->translate('Date') . '</th>
                         <th ' . $fontBold . $col2Width . '>' . $this->_tr->translate('Description') . '</th>
                         <th ' . $fontBold . $col3Width . $alignRight . '>' . $this->_tr->translate('Deposit') . '</th>
                         <th ' . $fontBold . $col4Width . $alignRight . '>' . $this->_tr->translate('Withdrawal') . '</th>
                         <th ' . $fontBold . $col5Width . $alignCenter . '>' . $this->_tr->translate('Balance') . '</th>
                         <th ' . $fontBold . $col6Width . '>' . $this->_tr->translate('Assigned to') . '</th>
                     </tr>';


        $arrUnclearedTransactions = $this->getUnverifiedTransactions($companyTaId, $startDate, $endDate, $booICCRCNewReport);

        $totalDepositsUncleared    = 0;
        $totalWithdrawalsUncleared = 0;
        foreach ($arrUnclearedTransactions as $arrTransactionInfo) {
            $totalDepositsUncleared    += (float)$arrTransactionInfo['deposit'];
            $totalWithdrawalsUncleared += (float)$arrTransactionInfo['withdrawal'];
        }
        $trueCashBalance = $openingBalance + $totalDepositsCleared - $totalWithdrawalsCleared + $totalDepositsUncleared - $totalWithdrawalsUncleared;

        // Show Offset record if "clients' total" is more than zero
        $arrClientsWithBalance = $this->getClientsBalances($companyTaId, $endDate, $booICCRCNewReport);
        $offsetBalance         = 0;
        $booRequireSorting     = false;
        if ($arrClientsWithBalance['total'] > 0) {
            $deposit = $withdrawal = 0;
            if ($arrClientsWithBalance['total'] > $trueCashBalance) {
                $deposit = $arrClientsWithBalance['total'] - $trueCashBalance;
            } else {
                $withdrawal = $trueCashBalance - $arrClientsWithBalance['total'];
            }

            if ($deposit > 0 || $withdrawal > 0) {
                $offsetBalance              = $deposit > 0 ? $deposit : -1 * $withdrawal;
                $arrUnclearedTransactions[] = array(
                    'date'        => $balanceDate ?: $endDate,
                    'description' => $balanceNotes ?: $this->_tr->translate('Offset'),
                    'deposit'     => $deposit,
                    'withdrawal'  => $withdrawal,
                    'assigned_to' => ''
                );

                $booRequireSorting = true;
            }
        }

        if ($booRequireSorting) {
            $arrDataSorted = array();
            foreach ($arrUnclearedTransactions as $arrTransactionInfo) {
                $arrDataSorted[strtotime($arrTransactionInfo['date'])] = $arrTransactionInfo;
            }

            ksort($arrDataSorted);
            $arrUnclearedTransactions = $arrDataSorted;
        }

        $totalDepositsUncleared    = 0;
        $totalWithdrawalsUncleared = 0;
        foreach ($arrUnclearedTransactions as $arrTransactionInfo) {
            $totalDepositsUncleared    += (float)$arrTransactionInfo['deposit'];
            $totalWithdrawalsUncleared += (float)$arrTransactionInfo['withdrawal'];
            $html                      .= '<tr>' .
                "<td $col1Width $alignCenter>" . $this->_settings->formatDate($arrTransactionInfo['date']) . '</td>' .
                "<td $col2Width>" . $this->filterTags($arrTransactionInfo['description']) . '</td>' .
                "<td $col3Width $alignRight>" . ((float)$arrTransactionInfo['deposit'] == 0 ? '&nbsp;' : $this->_parent::formatPrice($arrTransactionInfo['deposit'], $currency)) . '</td>' .
                "<td $col4Width $alignRight>" . ((float)$arrTransactionInfo['withdrawal'] == 0 ? '&nbsp;' : $this->_parent::formatPrice($arrTransactionInfo['withdrawal'], $currency)) . '</td>' .
                "<td $col5Width>&nbsp;</td>" .
                "<td $col6Width>" . $arrTransactionInfo['assigned_to'] . '</td>
             </tr>';
        }

        $html .= '</table>';

        $html .= '<table border="0" cellspacing="0" cellpadding="2">';
        $html .= '<tr>' .
            '<td ' . $col1And2Width . $fontBold . '>' . sprintf($this->_tr->translate('Total deposits made but not verified as of %s:'), $rr_period) . '</td>' .
            "<td $col3Width $fontBold $alignRight>" . $this->_parent::formatPrice($totalDepositsUncleared, $currency) . '</td>' .
            "<td $col4Width $fontBold>&nbsp;</td>" .
            "<td $col5Width $fontBold>&nbsp;</td>" .
            '</tr>' .
            '<tr>' .
            '<td ' . $col1And2Width . $fontBold . '>' . sprintf($this->_tr->translate('Total withdrawals made but not verified as of %s:'), $rr_period) . '</td>' .
            "<td $col3Width $fontBold>&nbsp;</td>" .
            "<td $col4Width $fontBold $alignRight>" . $this->_parent::formatPrice($totalWithdrawalsUncleared, $currency) . '</td>' .
            "<td $col5Width $fontBold>&nbsp;</td>" .
            '</tr>' .
            '<tr>' .
            '<td ' . $col1And2Width . $fontBold . '>' . sprintf($this->_tr->translate('Balance of unverified transactions as of %s:'), $rr_period) . '</td>' .
            "<td $col3Width $fontBold>&nbsp;</td>" .
            "<td $col4Width $fontBold>&nbsp;</td>" .
            "<td $col5Width $fontBold $alignCenter>" . $this->_parent::formatPrice($totalDepositsUncleared - $totalWithdrawalsUncleared, $currency) . '</td>' .
            '</tr>' .

            '<tr>' .
            '<td ' . $col1And2Width . $fontBold . '><br/><br/>' . sprintf($this->_tr->translate('True cash balance as of %s:'), $rr_period) . '</td>' .
            "<td $col3Width $fontBold>&nbsp;</td>" .
            "<td $col4Width $fontBold>&nbsp;</td>" .
            "<td $col5Width $fontBold $alignCenter><br/><br/>" . $this->_parent::formatPrice($openingBalance + $totalDepositsCleared - $totalWithdrawalsCleared + $totalDepositsUncleared - $totalWithdrawalsUncleared, $currency) . '</td>' .
            '</tr>';
        $html .= '</table>';

        // Force page break
        $html .= '<br pagebreak="true"/>';

        // Money Held On Behalf of Each Client
        $col1Width = 'width="430"';
        $col2Width = 'width="80"';
        $html      .= $header . '<br/><span align="center">' . $this->_tr->translate('Money Held On Behalf of Each Client') . '</span><br/><br/>';
        $html      .= '<table border="1" cellspacing="0" cellpadding="2">
                     <tr>
                         <th ' . $fontBold . $col1Width . '>' . $this->_tr->translate('Client') . '</th>
                         <th ' . $fontBold . $col2Width . $alignCenter . '>' . $this->_tr->translate('Amount') . '</th>
                     </tr>';

        foreach ($arrClientsWithBalance['rows'] as $arrClientInfo) {
            $html .= '<tr>' .
                "<td $col1Width>" . $arrClientInfo['name'] . '</td>' .
                "<td $col2Width $alignCenter>" . $this->_parent::formatPrice($arrClientInfo['amount'], $currency) . '</td>' .
                '</tr>';
        }

        $html .= '<tr>' .
            '<td ' . $col1Width . $fontBold . '>' . $this->_tr->translate('TOTAL: This number MUST EQUAL the Client Liability Account balance (component 7), as of the end of this particular month.') . '</td>' .
            "<td $col2Width $fontBold $alignCenter>" . $this->_parent::formatPrice($arrClientsWithBalance['total'], $currency) . '</td>' .
            '</tr>';

        $html .= '</table>';

        $html .= '<br/><br/>' .
            '<span ' . $fontBold . $alignCenter . '>' .
            $this->_tr->translate('If no monies were deposited into the CA for this month, please provide an explanation on the reverse side of this form.') .
            '</span>';

        return $booReturnBalanceOnly ? $offsetBalance : $html;
    }

    /**
     * @param int $companyTaId
     * @param $endDate
     * @return int|float
     */
    public function getCheckReconcileBalance($companyTaId, $endDate)
    {
        $arrParams          = array(
            'sort'       => 'date_from_bank',
            'dir'        => 'ASC',
            'filter'     => 'period',
            'start_date' => date('m/01/Y', strtotime($endDate)),
            'end_date'   => date('m/d/Y', strtotime($endDate)),
        );
        $arrAllTransactions = $this->getTransactionsGrid($companyTaId, $arrParams, false, 'm/d/Y');

        $openingBalance          = 0;
        $totalDepositsCleared    = 0;
        $totalWithdrawalsCleared = 0;
        if (count($arrAllTransactions['rows'])) {
            if ($arrAllTransactions['rows'][0]['purpose'] == $this->_parent->startBalanceTransactionId) {
                $openingBalance = $arrAllTransactions['rows'][0]['deposit'];
            } else {
                $openingBalance = $arrAllTransactions['rows'][0]['balance'];
            }

            foreach ($arrAllTransactions['rows'] as $arrTransactionInfo) {
                // Skip starting balance because it will be showed in a separate row (look above)
                if ($arrTransactionInfo['purpose'] == $this->_parent->startBalanceTransactionId) {
                    continue;
                }

                $totalDepositsCleared    += (float)$arrTransactionInfo['deposit'];
                $totalWithdrawalsCleared += (float)$arrTransactionInfo['withdrawal'];
            }
        } else {
            // Nothing found in this month, so try to load/use the last record
            $arrParams = array(
                'sort'       => 'date_from_bank',
                'dir'        => 'DESC',
                'filter'     => 'period',
                'start_date' => '01/01/1971',
                'end_date'   => date('m/d/Y', strtotime($endDate)),
            );

            $arrAllPreviousTransactions = $this->getTransactionsGrid($companyTaId, $arrParams, false, 'm/d/Y');

            if (count($arrAllPreviousTransactions['rows'])) {
                if ($arrAllPreviousTransactions['rows'][0]['purpose'] == $this->_parent->startBalanceTransactionId) {
                    $openingBalance = $arrAllPreviousTransactions['rows'][0]['deposit'];
                } else {
                    $openingBalance          = $arrAllPreviousTransactions['rows'][0]['balance'];
                    $totalDepositsCleared    = $arrAllPreviousTransactions['rows'][0]['deposit'];
                    $totalWithdrawalsCleared = $arrAllPreviousTransactions['rows'][0]['withdrawal'];
                }
            }
        }

        $arrUnclearedTransactions = $this->getUnverifiedTransactions($companyTaId, date('Y-m-01', strtotime($endDate)), $endDate);

        $totalDepositsUncleared    = 0;
        $totalWithdrawalsUncleared = 0;
        foreach ($arrUnclearedTransactions as $arrTransactionInfo) {
            $totalDepositsUncleared    += (float)$arrTransactionInfo['deposit'];
            $totalWithdrawalsUncleared += (float)$arrTransactionInfo['withdrawal'];
        }
        $trueCashBalance = $openingBalance + $totalDepositsCleared - $totalWithdrawalsCleared + $totalDepositsUncleared - $totalWithdrawalsUncleared;
        $trueCashBalance = round($trueCashBalance, 2);

        $arrClientsWithBalance = $this->getClientsBalances($companyTaId, $endDate);

        $offsetBalance = 0;
        if ($arrClientsWithBalance['total'] > 0) {
            $arrClientsWithBalance['total'] = round($arrClientsWithBalance['total'], 2);

            $deposit = $withdrawal = 0;
            if ($arrClientsWithBalance['total'] > $trueCashBalance) {
                $deposit = $arrClientsWithBalance['total'] - $trueCashBalance;
            } else {
                $withdrawal = $trueCashBalance - $arrClientsWithBalance['total'];
            }

            if ($deposit > 0 || $withdrawal > 0) {
                $offsetBalance = $deposit > 0 ? $deposit : -1 * $withdrawal;
            }
        }

        return $offsetBalance;
    }

    /**
     * @param $companyTaId
     * @param $endDate
     * @param bool $booLoadEmptyRecordsForThisMonth
     * @return array
     * @throws Exception
     */
    public function getClientsBalances($companyTaId, $endDate, $booLoadEmptyRecordsForThisMonth = false)
    {
        $arrClientBalancesResult = array(
            'rows'  => array(),
            'total' => 0
        );

        $select = (new Select())
            ->from(array('ta' => 'members_ta'))
            ->columns(['member_id'])
            ->where(['ta.company_ta_id' => (int)$companyTaId]);

        $arrClientIds = $this->_db2->fetchCol($select);

        if (is_array($arrClientIds) && count($arrClientIds)) {
            // Get assigned deposits
            $select = (new Select())
                ->from(array('a' => 'u_assigned_deposits'))
                ->columns(array('deposit', 'member_id'))
                ->join(array('ta' => 'u_trust_account'), 'ta.trust_account_id = a.trust_account_id', 'date_from_bank', Select::JOIN_LEFT)
                ->where([
                    (new Where())
                        ->equalTo('a.company_ta_id', (int)$companyTaId)
                        ->in('a.member_id', $arrClientIds)
                        ->lessThanOrEqualTo('ta.date_from_bank', $endDate)
                ]);

            $arrResult = $this->_db2->fetchAll($select);

            $arrClientDeposits = array();
            foreach ($arrResult as $r) {
                $arrClientDeposits[$r['member_id']][] = $r;
            }

            // Get assigned withdrawals
            $arrMembersInvoicePaymentIds = $this->_parent->getMembersInvoicePayments($arrClientIds, $companyTaId);
            $arrMembersInvoicePaymentIds = empty($arrMembersInvoicePaymentIds) ? array(0) : $arrMembersInvoicePaymentIds;

            $select = (new Select())
                ->from(['a' => 'u_assigned_withdrawals'])
                ->columns(
                    [
                        'withdrawal',
                        'returned_payment_member_id',
                        'member_id'
                    ]
                )
                ->join(['ta' => 'u_trust_account'], 'ta.trust_account_id = a.trust_account_id', ['date_from_bank'], Select::JOIN_LEFT)
                ->join(['ip' => 'u_invoice_payments'], 'ip.invoice_payment_id = a.invoice_payment_id', [], Select::JOIN_LEFT)
                ->join(['i' => 'u_invoice'], 'i.invoice_id = ip.invoice_id', ['invoice_member_id' => 'member_id'], Select::JOIN_LEFT)
                ->where(
                    [
                        (new Where())
                            ->nest()
                            ->isNull('a.returned_payment_member_id')
                            ->isNull('a.special_transaction')
                            ->isNull('a.special_transaction_id')
                            ->unnest()
                            ->in('a.invoice_payment_id', $arrMembersInvoicePaymentIds)
                            ->or
                            ->in('a.returned_payment_member_id', $arrClientIds)
                            ->or
                            ->in('a.member_id', $arrClientIds)
                    ]
                )
                ->where([
                    (new Where())
                        ->equalTo('a.company_ta_id', (int)$companyTaId)
                        ->lessThanOrEqualTo('ta.date_from_bank', $endDate)
                ]);

            $arrResult = $this->_db2->fetchAll($select);

            $arrClientWithdrawals = array();
            foreach ($arrResult as $r) {
                if (isset($r['returned_payment_member_id'])) {
                    $arrClientWithdrawals[$r['returned_payment_member_id']][] = $r;
                } elseif (isset($r['member_id'])) {
                    $arrClientWithdrawals[$r['member_id']][] = $r;
                } else {
                    $arrClientWithdrawals[$r['invoice_member_id']][] = $r;
                }
            }

            $arrUnassignedInvoicePayments = $this->_parent->getUnassignedInvoicePayments($companyTaId, false, $endDate, $arrClientIds);
            $arrClientNotClearedDeposits  = $this->_parent->getNotClearedDeposits($companyTaId, $arrClientIds, false, $endDate, true);

            $startDate = date('Y-m-01', strtotime($endDate));
            foreach ($arrClientIds as $caseId) {
                $deposit          = 0;
                $depositThisMonth = 0;
                if (isset($arrClientDeposits[$caseId])) {
                    foreach ($arrClientDeposits[$caseId] as $d) {
                        $deposit += $d['deposit'];

                        if (Settings::isDateBetweenDates($d['date_from_bank'], $startDate, $endDate)) {
                            $depositThisMonth += $d['deposit'];
                        }
                    }
                }

                $withdrawal          = 0;
                $withdrawalThisMonth = 0;
                if (isset($arrClientWithdrawals[$caseId])) {
                    foreach ($arrClientWithdrawals[$caseId] as $w) {
                        $withdrawal += $w['withdrawal'];

                        if (Settings::isDateBetweenDates($w['date_from_bank'], $startDate, $endDate)) {
                            $withdrawalThisMonth += $w['withdrawal'];
                        }
                    }
                }

                $unassignedInvoicePayments          = 0;
                $unassignedInvoicePaymentsThisMonth = 0;
                if (isset($arrUnassignedInvoicePayments[$caseId])) {
                    foreach ($arrUnassignedInvoicePayments[$caseId] as $invoicePaymentInfo) {
                        $invoiceAmount = 0;
                        if (!empty($invoicePaymentInfo['transfer_from_company_ta_id'])) {
                            if ($invoicePaymentInfo['transfer_from_company_ta_id'] == $companyTaId) {
                                $invoiceAmount = $invoicePaymentInfo['transfer_from_amount'];
                            }
                        } else {
                            $invoiceAmount = $invoicePaymentInfo['invoice_payment_amount'];
                        }
                        $unassignedInvoicePayments += $invoiceAmount;

                        if (Settings::isDateBetweenDates($invoicePaymentInfo['invoice_payment_date'], $startDate, $endDate)) {
                            $unassignedInvoicePaymentsThisMonth += $invoiceAmount;
                        }
                    }
                }

                $notClearedDeposits          = 0;
                $notClearedDepositsThisMonth = 0;
                if (isset($arrClientNotClearedDeposits[$caseId])) {
                    foreach ($arrClientNotClearedDeposits[$caseId] as $d) {
                        $notClearedDeposits += $d['deposit'];

                        if (Settings::isDateBetweenDates($d['date_of_event'], $startDate, $endDate)) {
                            $notClearedDepositsThisMonth += $d['deposit'];
                        }
                    }
                }

                $amount          = round($deposit - $withdrawal - $unassignedInvoicePayments + $notClearedDeposits, 2);
                $amountThisMonth = round($depositThisMonth - $withdrawalThisMonth - $unassignedInvoicePaymentsThisMonth + $notClearedDepositsThisMonth, 2);

                if ($amount > 0 || (empty($amountThisMonth) && $booLoadEmptyRecordsForThisMonth && (!empty($depositThisMonth) || !empty($withdrawalThisMonth) || !empty($unassignedInvoicePaymentsThisMonth) || !empty($notClearedDepositsThisMonth)))) {
                    $arrClientBalancesResult['rows'][] = array(
                        'name'   => $this->generateClientLink($caseId, true, false, true),
                        'amount' => $amount,
                    );

                    $arrClientBalancesResult['total'] += $amount;
                }
            }
        }

        return $arrClientBalancesResult;
    }

    /**
     * Load a list of unassigned deposits and invoice payments
     *
     * @param int $companyTaId
     * @param string $startDate
     * @param string $endDate
     * @param bool $booShortClientName
     * @return array
     */
    public function getUnverifiedTransactions($companyTaId, $startDate = '', $endDate = '', $booShortClientName = false)
    {
        $arrResult = array();

        // Load unverified deposits
        $select = (new Select())
            ->from(array('d' => 'u_assigned_deposits'))
            ->where(
                [
                    (new Where())
                        ->equalTo('d.company_ta_id', (int)$companyTaId)
                        ->isNull('d.trust_account_id')
                ]
            );

        if (!empty($startDate)) {
            $select->where->greaterThanOrEqualTo('d.date_of_event', $startDate);
        }
        if (!empty($endDate)) {
            $select->where->lessThanOrEqualTo('d.date_of_event', $endDate);
        }

        $arrUnverifiedDeposits = $this->_db2->fetchAll($select);

        foreach ($arrUnverifiedDeposits as $arrUnverifiedDepositInfo) {
            list($caseAndClientName,) = $this->getParent()->getParent()->getCaseAndClientName($arrUnverifiedDepositInfo['member_id'], $booShortClientName);

            $arrResult[] = array(
                'date'        => date('Y-m-d', strtotime($arrUnverifiedDepositInfo['date_of_event'])),
                'description' => $arrUnverifiedDepositInfo['description'],
                'deposit'     => $arrUnverifiedDepositInfo['deposit'],
                'withdrawal'  => 0,
                'assigned_to' => $caseAndClientName
            );
        }

        // Load unverified invoice payments
        $select = (new Select())
            ->from(array('ip' => 'u_invoice_payments'))
            ->join(array('i' => 'u_invoice'), 'ip.invoice_id = i.invoice_id', ['member_id'])
            ->join(array('w' => 'u_assigned_withdrawals'), 'w.invoice_payment_id = ip.invoice_payment_id', [], Select::JOIN_LEFT)
            ->where(
                [
                    (new Where())
                        ->isNull('w.date_of_event')
                        ->equalTo('ip.company_ta_id', (int)$companyTaId)
                ]
            );
        if (!empty($startDate)) {
            $select->where->greaterThanOrEqualTo('ip.invoice_payment_date', $startDate);
        }
        if (!empty($endDate)) {
            $select->where->lessThanOrEqualTo('ip.invoice_payment_date', $endDate);
        }
        $arrUnverifiedInvoices = $this->_db2->fetchAll($select);

        $taLabel = $this->_company->getCurrentCompanyDefaultLabel('trust_account');
        foreach ($arrUnverifiedInvoices as $arrUnverifiedInvoiceInfo) {
            list($caseAndClientName,) = $this->getParent()->getParent()->getCaseAndClientName($arrUnverifiedInvoiceInfo['member_id'], $booShortClientName);

            $arrResult[] = array(
                'date'        => $arrUnverifiedInvoiceInfo['invoice_payment_date'],
                'description' => $this->_tr->translate('Transferred from ') . $taLabel,
                'deposit'     => 0,
                'withdrawal'  => $arrUnverifiedInvoiceInfo['invoice_payment_amount'],
                'assigned_to' => $caseAndClientName
            );
        }

        // Sort by date
        $arrDates = array();
        foreach ($arrResult as $arrInfo) {
            $arrDates[] = strtotime($arrInfo['date']);
        }
        array_multisort($arrDates, SORT_ASC, $arrResult);

        return $arrResult;
    }

    private function filterTags($string)
    {
        return preg_replace('/<(.*?)>/i', '', $string);
    }

    /**
     * Load company T/A ids for specific company
     *
     * @param int $companyTAId
     * @return string
     */
    public function getCompanyIdByTAId($companyTAId)
    {
        $select = (new Select())
            ->from('company_ta')
            ->columns(['company_id'])
            ->where(['company_ta_id' => (int)$companyTAId]);

        return $this->_db2->fetchOne($select);
    }

    public function createReconcileReport($companyTaId, $endDate, $reconcileType, $booDraft, $balanceDate, $balanceNotes)
    {
        $lastReconcileId = 0;
        $tmpPdfFile      = '';

        $curr_member = $this->getParent()->getParent()->getMemberInfo();

        if (!$booDraft) {
            // Create history log record
            $lastReconcileId = $this->_db2->insert(
                'reconciliation_log',
                [
                    'reconciliation_type' => $reconcileType == 'iccrc' ? 'iccrc' : 'general',
                    'ta_id'               => $companyTaId,
                    'author_id'           => $curr_member['member_id'],
                    'recon_date'          => $endDate,
                    'create_date'         => date('Y-m-d')
                ]
            );
        }

        $filePath = '';
        if ($booDraft || !empty($lastReconcileId)) {
            $companyId      = $this->getCompanyIdByTAId($companyTaId);
            $arrCompanyInfo = $this->_company->getCompanyInfo($companyId);

            $country = $this->_country->getCountryName($arrCompanyInfo['country']);
            $company = $arrCompanyInfo['address'] . ',' . PHP_EOL . $arrCompanyInfo['city'] . ' ' . $arrCompanyInfo['zip'] . ' ' . $country;

            //save pdf
            $filePath = $this->_files->getReconciliationReportsPath() . '/' . $lastReconcileId;

            $arrParams = array(
                'header_title'  => $arrCompanyInfo['companyName'],
                'header_string' => $company,
                'setHeaderFont' => array('helvetica', '', 7),
                'SetAuthor'     => $curr_member['full_name']
            );

            $booLocal = $this->_company->isCompanyStorageLocationLocal($companyId);

            if (!empty($arrCompanyInfo['companyLogo'])) {
                // Use logo only if it is created for company
                $logoPath = $this->_files->getCompanyLogoPath($companyId, $booLocal);

                // Download company logo to the temp file if it is in the cloud
                if (!$booLocal && $this->_files->getCloud()->checkObjectExists($logoPath)) {
                    $logoPath = $this->_files->getCloud()->downloadFileContent($logoPath);
                }

                if (is_file($logoPath)) {
                    $arrParams['PDF_HEADER_LOGO_URL']   = $logoPath;
                    $arrParams['PDF_HEADER_LOGO_WIDTH'] = 13;
                }
            }

            if ($reconcileType == 'iccrc') {
                $html = $this->generateICCRCReconciliationReportContent($companyTaId, $endDate, $balanceDate, $balanceNotes);
            } else {
                $html = $this->generateGeneralReconciliationReportContent($companyTaId, $endDate, $reconcileType);
            }

            $tmpPdfFile = tempnam($this->_config['directory']['tmp'], 'pdf');
            if ($booDraft) {
                $arrParams['watermark'] = 'public/images/draft.png';

                $this->_pdf->htmlToPdf($html, $tmpPdfFile, 'F', $arrParams);
                $success = is_file($tmpPdfFile);
            } else {
                // Save generated pdf to file
                $this->_pdf->htmlToPdf($html, $tmpPdfFile, 'F', $arrParams);

                // Save generated file on server or S3 (in relation to the company settings)
                if ($booLocal) {
                    $success = rename($tmpPdfFile, $filePath);
                } else {
                    $success = $this->_files->getCloud()->uploadFile($tmpPdfFile, $filePath);
                }
                $tmpPdfFile = '';
            }
        } else {
            $success = false;
        }

        if ($success) {
            //update trust a/c
            if (!$booDraft) {
                $fieldToUpdate = $reconcileType == 'iccrc' ? 'last_reconcile_iccrc' : 'last_reconcile';
                $this->_db2->update('company_ta', [$fieldToUpdate => $endDate], ['company_ta_id' => $companyTaId]);
            }
        } else {
            //remove record from db
            $this->_db2->delete('reconciliation_log', ['reconciliation_id' => $lastReconcileId]);
            $lastReconcileId = 0;

            //save error message
            $this->_log->debugErrorToFile('Can\'t create Reconciliation Report (file "' . $filePath . '" does not exist).', '', 'pdf');
        }

        return [$lastReconcileId, $tmpPdfFile];
    }

    public function getCompanyTAReconciliationRecords($companyTaId)
    {
        $select = (new Select())
            ->from(array('r' => 'reconciliation_log'))
            ->join(array('m' => 'members'), 'm.member_id = r.author_id', Select::SQL_STAR, Select::JOIN_LEFT)
            ->where(['r.ta_id' => (int)$companyTaId]);

        return $this->_db2->fetchAll($select);
    }

    public function getCompanyTAReconciliationRecordsDates($companyTaId, $reconciliationType)
    {
        $select = (new Select())
            ->from(array('r' => 'reconciliation_log'))
            ->columns(array('res' => new Expression('DISTINCT(SUBSTR(recon_date, 1, 7))')))
            ->where([
                'r.ta_id'               => (int)$companyTaId,
                'r.reconciliation_type' => $reconciliationType
            ])
            ->order('recon_date');

        return $this->_db2->fetchCol($select);
    }

    /**
     * Get T/A records which were created after a rec. date or ICCRC rec. date
     *
     * @param int $companyTaId
     * @param string $reconDate
     * @param string $reconDateIccrc
     * @return array
     */
    public function getCompanyTAReconciliationRecordsAfterDates($companyTaId, $reconDate = null, $reconDateIccrc = null)
    {
        if (is_null($reconDate)) {
            $reconDate = '1950-01-01';
        }

        if (is_null($reconDateIccrc)) {
            $reconDateIccrc = '1950-01-01';
        }

        $select = (new Select())
            ->from(array('r' => 'reconciliation_log'))
            ->columns(array('reconciliation_id'))
            ->where(
                [
                    (new Where())
                        ->equalTo('r.ta_id', $companyTaId)
                        ->nest()
                        ->nest()
                        ->equalTo('r.reconciliation_type', 'general')
                        ->and
                        ->greaterThan('recon_date', \DateTime::createFromFormat('Y-m-d', $reconDate)->format('Y-m-t'))
                        ->unnest()
                        ->or
                        ->nest()
                        ->equalTo('r.reconciliation_type', 'iccrc')
                        ->and
                        ->greaterThan('recon_date', \DateTime::createFromFormat('Y-m-d', $reconDateIccrc)->format('Y-m-t'))
                        ->unnest()
                        ->unnest()
                ]
            )
            ->order('recon_date');

        return $this->_db2->fetchCol($select);
    }

    public function deleteCompanyTAReconciliationRecords($arrIdsToDelete)
    {
        if (is_array($arrIdsToDelete) && count($arrIdsToDelete)) {
            $this->_db2->delete('reconciliation_log', ['reconciliation_id' => $arrIdsToDelete]);

            // delete PDF files also
            $strPath  = $this->_files->getReconciliationReportsPath();
            $booLocal = $this->_auth->isCurrentUserCompanyStorageLocal();
            foreach ($arrIdsToDelete as $id) {
                $this->_files->deleteFile($strPath . '/' . $id, $booLocal);
            }
        }
    }

    public function getReconciliationRecordInfo($id)
    {
        $select = (new Select())
            ->from(array('log' => 'reconciliation_log'))
            ->columns(['ta_id'])
            ->where(['reconciliation_id' => (int)$id]);

        return $this->_db2->fetchOne($select);
    }

    public function deleteCompanyTAReconciliation($arrCompanyTaIds)
    {
        $select = (new Select())
            ->from('reconciliation_log')
            ->columns(['reconciliation_id'])
            ->where(['ta_id' => $arrCompanyTaIds]);

        $arrIds = $this->_db2->fetchCol($select);

        // We try to delete files located on server and on S3
        $localPath  = $this->_files->getReconciliationReportsPath('local');
        $remotePath = $this->_files->getReconciliationReportsPath('remote');
        foreach ($arrIds as $reconciliationRecordId) {
            $this->_files->deleteFile($localPath . '/' . $reconciliationRecordId);
            $this->_files->deleteFile($remotePath . '/' . $reconciliationRecordId, false);
        }

        $this->_db2->delete('reconciliation_log', ['ta_id' => $arrCompanyTaIds]);
    }

    /**
     * Load max receipt number in DB (use the last one saved + 1)
     *
     * @return int|null
     */
    public function getNewAssignedDepositReceiptNumber()
    {
        $newReceiptNumber = null;

        try {
            $companyId = $this->_auth->getCurrentUserCompanyId();

            $select = (new Select())
                ->from('company_details')
                ->columns(['max_receipt_number'])
                ->where(['company_id' => $companyId]);

            $maxReceiptNumber = $this->_db2->fetchOne($select);

            if (empty($maxReceiptNumber) || $maxReceiptNumber < 10000000) {
                $newReceiptNumber = 10000000;
            } else {
                $newReceiptNumber = (int)$maxReceiptNumber + 1;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $newReceiptNumber;
    }

    /**
     * Update Max receipt number in DB (use the last one saved + 1)
     */
    public function updateMaxReceiptNumber()
    {
        try {
            $companyId        = $this->_auth->getCurrentUserCompanyId();
            $newReceiptNumber = $this->getNewAssignedDepositReceiptNumber();

            if (!empty($companyId) && !empty($newReceiptNumber)) {
                $this->_db2->update(
                    'company_details',
                    ['max_receipt_number' => $newReceiptNumber],
                    ['company_id' => $companyId]
                );
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }

    /**
     * Load list of options for "Payment made by" combobox/column
     *
     * @return array
     */
    public function getPaymentMadeByOptions()
    {
        $arrOptions = array(
            array(
                'option_id'   => 'electronic_transfer',
                'option_name' => $this->_tr->translate('Electronic Funds Transfer')
            ),

            array(
                'option_id'   => 'swift_transfer',
                'option_name' => $this->_tr->translate('International (SWIFT) Transfer')
            ),

            array(
                'option_id'   => 'credit_card',
                'option_name' => $this->_tr->translate('Credit Card')
            ),

            array(
                'option_id'   => 'direct_debit',
                'option_name' => $this->_tr->translate('Direct Debit')
            ),

            array(
                'option_id'   => 'cash_deposit',
                'option_name' => $this->_tr->translate('Cash Deposit')
            ),

            array(
                'option_id'   => 'bank_draft',
                'option_name' => $this->_tr->translate('Bank Draft')
            ),

            array(
                'option_id'   => 'cheque',
                'option_name' => $this->_tr->translate('Cheque')
            )
        );

        $arrMapper = array();
        foreach ($arrOptions as $arrOption) {
            $arrMapper[$arrOption['option_id']] = $arrOption['option_name'];
        }

        return array(
            'arrOptions' => $arrOptions,
            'arrMapper'  => $arrMapper
        );
    }
}
