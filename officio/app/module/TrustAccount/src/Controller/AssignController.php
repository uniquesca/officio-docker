<?php

namespace TrustAccount\Controller;

use Clients\Service\Clients;
use Clients\Service\Clients\Accounting;
use Clients\Service\Clients\TrustAccount;
use Exception;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Officio\BaseController;
use Officio\Service\Company;
use Templates\Service\Templates;

/**
 * TrustAccount AssignController - this controller is used when assign
 * transaction on Client Account page
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class AssignController extends BaseController
{
    /** @var Company */
    protected $_company;

    /** @var Clients */
    protected $_clients;

    /** @var Templates */
    protected $_templates;

    /** @var Accounting */
    protected $_accounting;

    /** @var TrustAccount */
    protected $_trustAccount;

    public function initAdditionalServices(array $services)
    {
        $this->_company      = $services[Company::class];
        $this->_clients      = $services[Clients::class];
        $this->_templates    = $services[Templates::class];
        $this->_accounting   = $this->_clients->getAccounting();
        $this->_trustAccount = $this->_accounting->getTrustAccount();
    }

    public function unassignAction()
    {
        $strError             = '';
        $arrUnassignMemberIds = [];

        try {
            $trustAccountId = (int)$this->params()->fromPost('trust_account_id');

            if (!$this->_trustAccount->isCorrectTrustAccountId($trustAccountId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $arrTrustAccountInfo = [];
            if (empty($strError)) {
                // Load information about transaction
                $arrTrustAccountInfo = $this->_trustAccount->getTransactionInfo($trustAccountId);

                // Check if user has access to this transaction (by Company T/A id)
                if (!$this->_clients->hasCurrentMemberAccessToTA($arrTrustAccountInfo['company_ta_id'])) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }
            }

            if (empty($strError)) {
                // Check if this transaction can be unassigned -
                // un-assign option is not available for transactions that are 'reconciled'
                $booCanUnassign = $this->_trustAccount->canUnassignTransaction($arrTrustAccountInfo['company_ta_id'], $arrTrustAccountInfo['date_from_bank']);
                if (!$booCanUnassign) {
                    $strError = $this->_tr->translate('This transaction is part of a reconciled period and cannot be un-assigned.');
                }
            }

            if (empty($strError)) {
                $arrUnassignMemberIds = $this->_accounting->findMemberIdsByTransactionId($trustAccountId);
                if (!$this->_trustAccount->unassignTransaction($trustAccountId)) {
                    $strError = $this->_tr->translate('This transaction cannot be un-assigned.');

                    $arrUnassignMemberIds = [];
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel([
                'success'          => empty($strError),
                'message'          => $strError,
                'unassign_members' => $arrUnassignMemberIds
            ]
        );
    }

    public function indexAction()
    {
        $strError          = '';
        $strSuccessMessage = '';

        try {
            $filter = new StripTags();
            $act    = $filter->filter(Json::decode($this->params()->fromPost('act'), Json::TYPE_ARRAY));

            switch ($act) {
                case 'assign-withdrawal' :
                    /*
                     * Withdrawal can be assigned to:
                     * 1. One Invoice Payment
                     * 2. Multiple Invoice Payment(s) AND/OR Special Withdrawal(s)
                     * 2. Special Withdrawal
                     * 3. Returned Payment
                     *
                     */
                    $assignTo                = $filter->filter($this->params()->fromPost('assign_to'));
                    $trustAccountId          = $filter->filter(Json::decode($this->params()->fromPost('transaction_id'), Json::TYPE_ARRAY));
                    $destinationAccountId    = $filter->filter(Json::decode($this->params()->fromPost('destination_account_id'), Json::TYPE_ARRAY));
                    $destinationAccountOther = $filter->filter(Json::decode($this->params()->fromPost('destination_account_other'), Json::TYPE_ARRAY));

                    // Load information about transaction
                    $arrTrustAccountInfo = $this->_trustAccount->getTransactionInfo($trustAccountId);

                    if (!$this->_trustAccount->isCorrectTrustAccountId($trustAccountId) || !$this->_clients->hasCurrentMemberAccessToTA($arrTrustAccountInfo['company_ta_id'])) {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }

                    if (empty($strError)) {
                        $strSuccessMessage = $this->_tr->translate('Your transaction is completed successfully.');

                        switch ($assignTo) {
                            case 'single-invoice-payment' :
                                $invoicePaymentId = $filter->filter(Json::decode($this->params()->fromPost('invoice_payment_id'), Json::TYPE_ARRAY));

                                if (!$this->_accounting->hasAccessToInvoicePayment($invoicePaymentId)) {
                                    $strError = $this->_tr->translate('Insufficient Access rights');
                                } else {
                                    $result = $this->_trustAccount->assignWithdrawal(
                                        array(
                                            'company_ta_id'             => $arrTrustAccountInfo['company_ta_id'],
                                            'trust_account_id'          => $trustAccountId,
                                            'withdrawal'                => $arrTrustAccountInfo['withdrawal'],
                                            'invoice_payment_id'        => $invoicePaymentId,
                                            'destination_account_id'    => $destinationAccountId,
                                            'date_from_bank'            => $arrTrustAccountInfo['date_from_bank'],
                                            'destination_account_other' => $destinationAccountOther
                                        )
                                    );

                                    if (!$result) {
                                        $strError = $this->_tr->translate('Can\'t assign withdrawal. Please try again later.');
                                    }
                                }
                                break;

                            case 'multiple-invoice-payments' :
                                // Save to multiple Invoice Payments AND/OR Special Withdrawals
                                $booAtLeastOneRecord = false;

                                $arrInvoicePayments = Json::decode($this->params()->fromPost('arr_invoice_payment_ids'), Json::TYPE_ARRAY);
                                if (!empty($arrInvoicePayments)) {
                                    foreach ($arrInvoicePayments as $arrInvoicePaymentInfo) {
                                        if (!$this->_accounting->hasAccessToInvoicePayment($arrInvoicePaymentInfo['invoicePaymentId'])) {
                                            $strError = $this->_tr->translate('Insufficient access rights to the invoice payment(s)');
                                            break;
                                        } else {
                                            $booAtLeastOneRecord = true;

                                            $result = $this->_trustAccount->assignWithdrawal(
                                                array(
                                                    'company_ta_id'             => $arrTrustAccountInfo['company_ta_id'],
                                                    'trust_account_id'          => $trustAccountId,
                                                    'withdrawal'                => $arrInvoicePaymentInfo['amount'],
                                                    'invoice_payment_id'        => $arrInvoicePaymentInfo['invoicePaymentId'],
                                                    'destination_account_id'    => $destinationAccountId,
                                                    'date_from_bank'            => $arrTrustAccountInfo['date_from_bank'],
                                                    'destination_account_other' => $destinationAccountOther
                                                )
                                            );

                                            if (!$result) {
                                                $strError = $this->_tr->translate("Can't assign withdrawals. Please try again later.");
                                            }
                                        }
                                    }
                                }

                                $arrSpecialWithdrawals = Json::decode($this->params()->fromPost('arr_special_withdrawals'), Json::TYPE_ARRAY);
                                if (empty($strError) && !empty($arrSpecialWithdrawals)) {
                                    $arrWithdrawalTypes    = $this->_trustAccount->getTypeOptions('withdrawal');
                                    $arrWithdrawalTypesIds = array_column($arrWithdrawalTypes, 'transactionId');
                                    foreach ($arrSpecialWithdrawals as $arrSpecialWithdrawalInfo) {
                                        if (!in_array($arrSpecialWithdrawalInfo['specialWithdrawalTransaction'], $arrWithdrawalTypesIds)) {
                                            $strError = $this->_tr->translate('Incorrectly selected Special Withdrawal');
                                            break;
                                        } else {
                                            $booAtLeastOneRecord = true;

                                            $result = $this->_trustAccount->assignWithdrawal(
                                                array(
                                                    'company_ta_id'             => $arrTrustAccountInfo['company_ta_id'],
                                                    'trust_account_id'          => $trustAccountId,
                                                    'withdrawal'                => $arrSpecialWithdrawalInfo['amount'],
                                                    'special_transaction'       => $arrSpecialWithdrawalInfo['specialWithdrawalTransactionCustom'],
                                                    'special_transaction_id'    => $arrSpecialWithdrawalInfo['specialWithdrawalTransaction'],
                                                    'destination_account_id'    => $destinationAccountId,
                                                    'destination_account_other' => $destinationAccountOther
                                                )
                                            );

                                            if (!$result) {
                                                $strError = $this->_tr->translate("Can't assign withdrawals. Please try again later.");
                                            }
                                        }
                                    }
                                }

                                if (!$booAtLeastOneRecord) {
                                    $strError = $this->_tr->translate("Can't assign withdrawals. Incorrect incoming info.");
                                }
                                break;

                            case 'special-transaction' :
                                $specialTransactionId = $filter->filter(Json::decode($this->params()->fromPost('special_transaction_id'), Json::TYPE_ARRAY));
                                $customTransaction    = $filter->filter(Json::decode($this->params()->fromPost('custom_transaction'), Json::TYPE_ARRAY));

                                // Assign withdrawal to transaction
                                $result = $this->_trustAccount->assignWithdrawal(array(
                                    'company_ta_id'             => $arrTrustAccountInfo['company_ta_id'],
                                    'trust_account_id'          => $trustAccountId,
                                    'withdrawal'                => $arrTrustAccountInfo['withdrawal'],
                                    'special_transaction'       => $customTransaction,
                                    'special_transaction_id'    => $specialTransactionId,
                                    'destination_account_id'    => $destinationAccountId,
                                    'destination_account_other' => $destinationAccountOther
                                ));

                                if (!$result) {
                                    $strError = $this->_tr->translate('Cannot assign withdrawal to special transaction. Please try again later.');
                                }
                                break;

                            case 'returned-payment' :
                                $returnedPaymentClientId = $filter->filter(Json::decode($this->params()->fromPost('returned_payment_member_id'), Json::TYPE_ARRAY));

                                // Assign withdrawal to transaction
                                $result = $this->_trustAccount->assignWithdrawal(array(
                                    'company_ta_id'              => $arrTrustAccountInfo['company_ta_id'],
                                    'trust_account_id'           => $trustAccountId,
                                    'withdrawal'                 => $arrTrustAccountInfo['withdrawal'],
                                    'returned_payment_member_id' => $returnedPaymentClientId,
                                    'destination_account_id'     => $destinationAccountId,
                                    'destination_account_other'  => $destinationAccountOther
                                ));

                                if (!$result) {
                                    $strError = $this->_tr->translate('Cannot assign withdrawal to returned payment. Please try again later.');
                                } else {
                                    $strSuccessMessage = $this->_tr->translate('Please adjust invoices and transactions related to this returned fee for this case.');
                                }
                                break;

                            default:
                                $strError = $this->_tr->translate('Incorrect incoming info.');
                                break;
                        }

                        if (empty($strError)) {
                            // Update notes if there are no errors
                            $notes = $filter->filter(Json::decode($this->params()->fromPost('notes'), Json::TYPE_ARRAY));
                            $this->_trustAccount->updateTransactionInfo(array(
                                'id'    => $trustAccountId,
                                'notes' => $notes
                            ));
                        }
                    }

                    break;

                case 'assign-deposit' :
                    $strSuccessMessage = $this->_tr->translate('Your transaction is completed successfully.');

                    /*
                     * Deposit can be assigned to:
                     * 1. One client
                     * 2. Several clients
                     * 3. Special transaction
                     *
                     */
                    $assignTo       = $filter->filter($this->params()->fromPost('assign_to'));
                    $trustAccountId = $filter->filter(Json::decode($this->params()->fromPost('transaction_id'), Json::TYPE_ARRAY));

                    // Load T/A info
                    $arrTrustAccountInfo = $this->_trustAccount->getTransactionInfo($trustAccountId);

                    if (empty($arrTrustAccountInfo) || !$this->_clients->hasCurrentMemberAccessToTA($arrTrustAccountInfo['company_ta_id'])) {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }

                    if (empty($strError)) {
                        switch ($assignTo) {
                            case 'one-client' :
                                $member_id   = $filter->filter(Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY));
                                $deposit_id  = $filter->filter(Json::decode($this->params()->fromPost('deposit_id'), Json::TYPE_ARRAY));
                                $template_id = $filter->filter(Json::decode($this->params()->fromPost('template_id'), Json::TYPE_ARRAY));

                                // Assign deposit to client
                                if (empty($deposit_id)) {
                                    $result = $this->_trustAccount->assignDeposit(array(
                                        'company_ta_id'    => $arrTrustAccountInfo['company_ta_id'],
                                        'trust_account_id' => $trustAccountId,
                                        'deposit'          => $arrTrustAccountInfo['deposit'],
                                        'member_id'        => $member_id,
                                        'receipt_number'   => $this->_trustAccount->getNewAssignedDepositReceiptNumber(),
                                        'template_id'      => $template_id
                                    ));
                                } else {
                                    // Assign deposit for already created deposit
                                    $result = $this->_trustAccount->assignDepositToAlreadyCreated(array(
                                        'company_ta_id'    => $arrTrustAccountInfo['company_ta_id'],
                                        'trust_account_id' => $trustAccountId,
                                        'deposit_id'       => $deposit_id,
                                        'deposit'          => $arrTrustAccountInfo['deposit'],
                                        'member_id'        => $member_id
                                    ));
                                }

                                if ($result) {
                                    $this->_trustAccount->updateMaxReceiptNumber();
                                } else {
                                    $strError = $this->_tr->translate('Can not assign deposit to transaction. Please try again later.');
                                }
                                break;

                            case 'multiple-clients' :
                                // Save to multiple clients
                                $arrClients = Json::decode($this->params()->fromPost('arrClients'), Json::TYPE_ARRAY);
                                if (!is_array($arrClients) || empty($arrClients)) {
                                    $strError = $this->_tr->translate('Incorrect incoming info.');
                                }

                                if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember(array_column($arrClients, 'clientId'))) {
                                    $strError = $this->_tr->translate('Insufficient access rights.');
                                }

                                if (empty($strError)) {
                                    foreach ($arrClients as $clientInfo) {
                                        $member_id   = $clientInfo['clientId'];
                                        $deposit_id  = $clientInfo['deposit_id'];
                                        $template_id = $clientInfo['templateId'];

                                        if (empty($deposit_id)) {
                                            // Assign deposit to client
                                            $result = $this->_trustAccount->assignDeposit(
                                                array(
                                                    'company_ta_id'    => $arrTrustAccountInfo['company_ta_id'],
                                                    'trust_account_id' => $trustAccountId,
                                                    'deposit'          => $clientInfo['clientAmount'],
                                                    'member_id'        => $member_id,
                                                    'receipt_number'   => $this->_trustAccount->getNewAssignedDepositReceiptNumber(),
                                                    'template_id'      => $template_id
                                                )
                                            );

                                            if ($result) {
                                                $this->_trustAccount->updateMaxReceiptNumber();
                                            } else {
                                                $strError = $this->_tr->translate('Can not assign deposit to transaction. Please try again later.');
                                                break;
                                            }
                                        } else {
                                            // Assign deposit for already created deposit
                                            $result = $this->_trustAccount->assignDepositToAlreadyCreated(
                                                array(
                                                    'company_ta_id'    => $arrTrustAccountInfo['company_ta_id'],
                                                    'trust_account_id' => $trustAccountId,
                                                    'deposit_id'       => $deposit_id,
                                                    'deposit'          => $clientInfo['clientAmount'],
                                                    'member_id'        => $member_id
                                                )
                                            );

                                            if (!$result) {
                                                $strError = $this->_tr->translate('Can not assign deposit to transaction. Please try again later.');
                                                break;
                                            }
                                        }
                                    }
                                }
                                break;

                            case 'custom-transaction' :
                                $specialTransactionId = $filter->filter(Json::decode($this->params()->fromPost('special_transaction_id'), Json::TYPE_ARRAY));
                                $customTransaction    = $filter->filter(Json::decode($this->params()->fromPost('custom_transaction'), Json::TYPE_ARRAY));

                                // Assign deposit to client
                                $result = $this->_trustAccount->assignDeposit(
                                    array(
                                        'company_ta_id'          => $arrTrustAccountInfo['company_ta_id'],
                                        'trust_account_id'       => $trustAccountId,
                                        'deposit'                => $arrTrustAccountInfo['deposit'],
                                        'special_transaction_id' => $specialTransactionId,
                                        'special_transaction'    => $customTransaction,
                                        'receipt_number'         => $this->_trustAccount->getNewAssignedDepositReceiptNumber()
                                    )
                                );

                                if ($result) {
                                    $this->_trustAccount->updateMaxReceiptNumber();
                                } else {
                                    $strError = $this->_tr->translate('Can not assign deposit to transaction. Please try again later.');
                                }
                                break;

                            default:
                                $strError = $this->_tr->translate('Incorrect incoming info.');
                                break;
                        } // switch $assignTo

                        if (empty($strError)) {
                            // Update notes if there are no errors
                            $notes           = $filter->filter(Json::decode($this->params()->fromPost('notes'), Json::TYPE_ARRAY));
                            $payment_made_by = $filter->filter(Json::decode($this->params()->fromPost('payment_made_by'), Json::TYPE_ARRAY));
                            $this->_trustAccount->updateTransactionInfo(
                                array(
                                    'id'              => $trustAccountId,
                                    'notes'           => $notes,
                                    'payment_made_by' => $payment_made_by
                                )
                            );
                        }
                    }
                    break;

                default:
                    $strError = $this->_tr->translate('Incorrect incoming info.');
                    break;
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = [
            'success' => empty($strError),
            'message' => empty($strError) ? $strSuccessMessage : $strError,
        ];

        return new JsonModel($arrResult);
    }

    public function getAssignDepositDataAction()
    {
        $arrClients       = array();
        $arrDeposits      = array();
        $arrTemplates     = array();
        $arrDepositTypes  = array();
        $depositVal       = '';
        $arrSendAsOptions = array();

        try {
            $filter = new StripTags();

            $companyTaId = $filter->filter($this->params()->fromPost('ta_id'));
            $depositVal  = $filter->filter(Json::decode($this->params()->fromPost('depositVal'), Json::TYPE_ARRAY));

            // Check if user has access to this Company T/A id
            if ($this->_clients->hasCurrentMemberAccessToTA($companyTaId)) {
                //get clients list
                $arrTAClients = $this->_clients->getClientsList((int)$companyTaId);
                $arrTAClients = $this->_clients->getCasesListWithParents($arrTAClients);

                if ($arrTAClients) {
                    foreach ($arrTAClients as $clientInfo) {
                        $arrClients[] = array(
                            'clientId'   => $clientInfo['clientId'],
                            'clientName' => $clientInfo['clientFullName']
                        );
                    }
                }

                // Load not cleared deposits
                $arrDeposits = $this->_accounting->getCompanyNotClearedDepositsList($companyTaId);

                //get templates list
                $arrTemplates = $this->_templates->getTemplatesList(false, 0, 'Payment', 'Email');

                //get send as options
                $arrSendAsOptions = $this->_templates->getSendAsOptions();

                //get deposit types
                $arrDepositTypes = $this->_trustAccount->getTypeOptions('deposit');

                //format deposit value
                $currency   = $this->_accounting->getCompanyTACurrency($companyTaId);
                $depositVal = $this->_accounting::formatPrice($depositVal, $currency);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel([
            'clients'       => $arrClients,
            'deposits'      => $arrDeposits,
            'templates'     => $arrTemplates,
            'deposit_types' => $arrDepositTypes,
            'depositVal'    => $depositVal,
            'sendAsOptions' => $arrSendAsOptions
        ]);
    }

    public function getAssignWithdrawalDataAction()
    {
        $strError           = '';
        $currency           = '';
        $clients            = array();
        $arrInvoicePayments = array();
        $arrWithdrawalTypes = array();
        $arrDestinationList = array();
        $withdrawalVal      = 0;

        try {
            $companyTaId = Json::decode($this->params()->fromPost('company_ta_id'));
            $taId        = Json::decode($this->params()->fromPost('ta_id'));

            // Check if user has access to this Company T/A
            if (!$this->_clients->hasCurrentMemberAccessToTA($companyTaId)) {
                $taLabel  = $this->_company->getCurrentCompanyDefaultLabel('trust_account');
                $strError = $this->_tr->translate('Insufficient access rights for this ' . $taLabel);
            } else {
                $arrCompanyTAInfo = $this->_accounting->getCompanyTAbyId($companyTaId);
                $currency         = $arrCompanyTAInfo['currency'];
            }

            if (empty($strError)) {
                $arrTAInfo = $this->_accounting->getCompanyTARecordByTrustAccountId($taId);

                // Check access to the selected T/A record
                if (!isset($arrTAInfo['company_ta_id']) || $arrTAInfo['company_ta_id'] != $companyTaId) {
                    $strError = $this->_tr->translate('Insufficient access');
                } else {
                    $withdrawalVal = $arrTAInfo['withdrawal'];
                }

                // Make sure that transaction wasn't assigned yet
                if (empty($strError)) {
                    $arrAssignedWithdrawals = $this->_accounting->getAssignedWithdrawalsByTransactionId($taId);
                    if (!empty($arrAssignedWithdrawals)) {
                        $strError = $this->_tr->translate('This transaction is already assigned');
                    }
                }
            }

            if (empty($strError)) {
                // get clients list
                $arrClients = $this->_clients->getClientsList($companyTaId);
                $arrClients = $this->_clients->getCasesListWithParents($arrClients);

                if ($arrClients) {
                    foreach ($arrClients as $clientInfo) {
                        $clients[] = array(
                            'clientId'   => $clientInfo['clientId'],
                            'clientName' => $clientInfo['clientFullName']
                        );
                    }
                }

                //get invoices list
                $arrInvoicePayments = $this->_trustAccount->getInvoicesList($companyTaId);

                //get withdrawal types
                $arrWithdrawalTypes = $this->_trustAccount->getTypeOptions('withdrawal');

                //get destination account list
                $arrDestinationList = $this->_trustAccount->getTypeOptions('destination');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? '' : $strError,

            'clients'           => $clients,
            'invoices_payments' => $arrInvoicePayments,
            'withdrawal_types'  => $arrWithdrawalTypes,
            'withdrawalVal'     => $withdrawalVal,
            'ta_currency'       => $currency,
            'destinations'      => $arrDestinationList
        );

        return new JsonModel($arrResult);
    }

}
