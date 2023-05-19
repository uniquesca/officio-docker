<?php
namespace Officio\Service\Payment;

define('PAYMENT_SERVICE_APPROVED', 'Approved');
define('PAYMENT_SERVICE_INVALID_CREDIT_CARD_NUM', 'Invalid credit card number. Please re-enter your credit card number.');
define('PAYMENT_SERVICE_INVALID_EXPIRATION_DATE', 'Invalid account expiry date');
define('COMPLEX_ERROR', 'Please try again & if the issue persists please contact our support team for assistance');

// CONSTANT FOR PROFILE
define('PAYMENT_SERVICE_PROFILE_PROC_STATUS', 'Profile Request Processed');
define('PAYMENT_SERVICE_PROFILE_TRANSACTION', 'Transaction completed successfully using a Profile');
define('PAYMENT_SERVICE_PROFILE_CREATION_STATUS', 'Invalid credit card number. Please re-enter your credit card number.');
define('PAYMENT_SERVICE_PROFILE_DATE_STATUS', 'Invalid Account Expiry Date');

    // CONSTANT FOR OTHER ERROR - PROFILE
    define('PAYMENT_SERVICE_OTHER_ERROR', 'Please try again & if the issue persists please contact our support team for assistance');
    define('PAYMENT_SERVICE_PROFILE_UPDATE_STATUS', 'This profile does not exist');
    define('PAYMENT_SERVICE_PROFILE_ALREADY_EXISTS', 'This profile already exist');

interface ServerExceptionsInterface
{

    public function getExceptionName($errorResCode, $errorDetails = '');

    public function getRecurringExceptionName($errorResCode);

    public function getProfileException($errorResCode, $errorDetails = '');

    public function getOtherException($errorResCode = '', $errorDetails = '');
}