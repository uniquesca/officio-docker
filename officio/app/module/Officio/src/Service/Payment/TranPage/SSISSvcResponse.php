<?php

namespace Officio\Service\Payment\TranPage;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class SSISSvcResponse
{

    /**
     * @var string
     * The result code for the operation.
     * This is the primary result of the overall operation, and does not indicate financial success.
     * Check this value first, then if the Result Code is OK, check further using the Financial Result Code (FinancialResultCode) to determine the financial results of the transaction.
     * This is an enumerated type of type SSISResultCode:
     *  - ParameterError: Parameter Error
     *  - Unavailable: Service is temporarily unavailable
     *  - OK: Transaction was processed, does not mean the transaction was approved. See FinancialResultCode
     *  - DuplicateTransaction: Duplicate transaction ID, this transaction ID was already used.
     *  - TooBusy: The authorization cannot handle the request, try again later
     *  - GeneralError: General Error (see Error Message)
     *  - SecurityError: Security Related Error Message.
     *  - MerchantError: The merchant account is not permitted to process. Contact the bank to resolve the issue affecting the account
     */
    public $ResultCode;

    /**
     * @var string
     * Meaningful only if ResultCode=OK, otherwise may be an arbitrary value or empty.
     * This is an enumerated type of SSISFinancialResultCode:
     * - Approved: Transaction was approved, ApprovalCode will contain approval code from card issuer
     * - Declined: Transaction was declined.
     * - Incomplete: Unable to complete transaction.  This is not the same as a DECLINE, it may be a network or other issue which is preventing the transaction from occurring.
     * Either a DECLINED or INCOMPLETE result should generally be treated the same, the net result is that payment was not available with this card at this time
     */
    public $FinancialResultCode;

    /** @var string Amount = Payment Amount – DDDDDDDDDD.CC */
    public $Amount;

    /** @var string Currency, same as in request */
    public $Currency;

    /** @var string Approval code / Authorisation number */
    public $ApprovalCode;

    /** @var string Invoice number, same as in request */
    public $Invoice;

    /** @var string Merchant Transaction ID, same as in request */
    public $TranId;

    /** @var string 16 character unique identifier for retrieval and reference */
    public $HostReference;

    /** @var string Error Message, generated in the event that ResultCode is one of the error types (GeneralError, MerchantError, ParameterError, SecurityError). */
    public $ErrorMessage;

    /** @var string Address Verification Result (where available) */
    public $AVSResult;

    /**
     * @var string
     * Batch Id Reference number, filled for APPROVED and DECLINED transactions.
     * For AUTH only transactions, the batch id will never be filled (ie. only posted transactions will have a batch reference)
     */
    public $BatchId;
}