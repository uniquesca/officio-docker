<?php

namespace Officio\Service\Payment\TranPage;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class SSISSvcRequest
{
    /** @var string A unique assigned code identifying the merchant under which transactions will be processed */
    public $MerchantKey;

    /**
     * @var string
     * Type of transaction requested.
     * Currently authorization and manual posting is not supported by Officio.
     */
    public $TranType = 'AuthAndPost';

    /** @var string Payment Amount – DDDDDDDD.CC (no spaces or commas allowed). */
    public $Amount;

    /** @var string The currency for payments, in ISO 4217 3 character alpha, such as ‘USD’, ‘XCD’, ‘CAD’ etc */
    public $Currency;

    /** @var string An invoice number reference for this transaction.  May contain A-Za-z0-9 */
    public $Invoice;

    /** @var string A unique per message Transaction ID. */
    public $TranId;

    /** @var string Card Number */
    public $CardNumber;

    /** @var string Expiry Date MMYY. Must be properly padded with zeroes. For example, May 2004 would be 0504. */
    public $ExpiryMMYY;

    /** @var string Card Verification Value (3 digits for MC & Visa) from the back of the card */
    public $VerificationValue;

    /** @var string Optional Cardholder name as it appears on the card.  Must not contain the characters ‘<’, ‘>’ */
    public $CardholderName;

    /** @var string Optional Address of customer if Address Verification is required. Must not contain the characters ‘<’, ‘>’ */
    public $Address;
}