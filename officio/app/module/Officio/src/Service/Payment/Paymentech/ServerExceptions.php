<?php

namespace Officio\Service\Payment\Paymentech;

use Officio\Service\Payment\ServerExceptionsInterface;

class ServerExceptions implements ServerExceptionsInterface
{

    public function getExceptionName($errorResCode, $errorDetails = '')
    {
        // RespCode FROM PAYMENT SERVER i.e. EXCEPTION CODE
        switch ($errorResCode) {
            case '00':
                return PAYMENT_SERVICE_APPROVED;
            case 14:
            case 841:
                return PAYMENT_SERVICE_INVALID_CREDIT_CARD_NUM;
            case 74:
            case 778:
                return PAYMENT_SERVICE_INVALID_EXPIRATION_DATE;
            default:
                if (!empty($errorDetails)) {
                    return $errorDetails;
                } else {
                    return COMPLEX_ERROR;
                }
        }
    }

    /*
     * METHOD TO RETURN EXCEPTION NAME FOR RECURRING PAYMENT
     */
    public function getRecurringExceptionName($errorResCode)
    {
        /*
         * IF PROFILE TRANSACTION IS DONE SUCCESSFULLY
         * THEN RETURN SUCCESS MESSAGE / EXCEPTION FOR GIVEN CODE
         */
        if ($errorResCode == 00) {
            return PAYMENT_SERVICE_PROFILE_TRANSACTION;
        }
    }


    /*
     * METHOD TO RETURN PROFILE SUCCESS RESPONSE
     */
    public function getProfileException($errorResCode, $errorDetails = '')
    {
        switch ($errorResCode) {
            case 841:
                $strMessage = PAYMENT_SERVICE_PROFILE_CREATION_STATUS;
                break;

            case 9581:
                $strMessage = PAYMENT_SERVICE_PROFILE_UPDATE_STATUS;
                break;

            case 9582:
                $strMessage = PAYMENT_SERVICE_PROFILE_ALREADY_EXISTS;
                break;

            case 9569:
                $strMessage = PAYMENT_SERVICE_PROFILE_DATE_STATUS;
                break;

            default:
                $strMessage = empty($errorDetails) ? PAYMENT_SERVICE_OTHER_ERROR : $errorDetails;
                break;
        }

        return $strMessage;
    }

    /*
     * METHOD TO RETURN OTHER ERROR LIKE [ COMPLEX ]
     */
    public function getOtherException($errorResCode = '', $errorDetails = '')
    {
        if ($errorResCode != null) {
            if (!empty($errorDetails)) {
                return $errorDetails;
            } else {
                return PAYMENT_SERVICE_OTHER_ERROR;
            }
        }
    }
}