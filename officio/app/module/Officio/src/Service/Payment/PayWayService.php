<?php

namespace Officio\Service\Payment;

use Exception;
use Officio\Common\Service\Log;
use Officio\Service\Payment\PayWay\ServerExceptions;
use Officio\Service\Payment\PayWay\XMLRequest;

class PayWayService implements PaymentServiceInterface
{

    /** @var array */
    protected $_config;
    /** @var Log */
    protected $_log;
    /** @var XMLRequest */
    private $_xmlRequest;
    /** @var ServerExceptions */
    private $_oServerExceptions;

    /** @var bool */
    private $_initiated = false;

    public function __construct(array $config, Log $log)
    {
        $this->_config = $config;
        $this->_log    = $log;
    }

    public function init()
    {
        if (!$this->_initiated) {
            $this->_xmlRequest        = new XMLRequest($this->_config, $this->_log);
            $this->_oServerExceptions = new ServerExceptions();
        }
    }

    /**
     * Generate profile id for PayWay
     *
     * @param int $id - id of already created prospect or company
     * @param bool $booProspect - true if prospect id is used,
     * otherwise false means that company id is used
     *
     * @return string generated PayWay profile id
     */
    public function generatePaymentProfileId($id, $booProspect = true)
    {
        $id = round(microtime(true) * 100);
        return 'PW' . $id;//$booProspect ? 'Prospect ' . $id : 'Company - ' . $id;
    }


    /**
     * Generate order id for PayWay
     *
     * @param int $orderId - id of already created invoice/order in Officio
     *
     * @return string generated PayWay order id
     */
    public function generatePaymentOrderId($orderId)
    {
        return 'I_' . $orderId;
    }


    /**
     * Generate trace number for PayWay, which will be used in retry logic
     * This number will be concatenated with invoice number, this string must be less than 20 chars
     *
     * @return string trace number
     */
    public function generatePaymentTraceNumber()
    {
        // Generate random number 6 chars
        $time = microtime(true) * 10000;
        return substr((string)$time, -6);
    }

    /**
     * CREATE PROFILE ON PAYMENT GATEWAYS SERVER
     *
     * @param $arrProfileInfo
     *
     * @return array result (first element - boolean error, second - string description)
     */
    public function createProfile($arrProfileInfo)
    {
        try {
            // Send request and parse result
            $arrResult       = $this->_xmlRequest->createProfileRequest($arrProfileInfo);
            $strErrorMessage = !is_array($arrResult) ? $arrResult : $this->_oServerExceptions->getExceptionMessageByCode(
                $arrResult['response.responseCode'],
                $arrResult['response.text'],
                'profile_create'
            );
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $strErrorMessage = "Server Error.";
        }


        return array('error' => !empty($strErrorMessage), 'message' => $strErrorMessage);
    }

    /**
     * UPDATE PROFILE ON PAYMENT GATEWAYS SERVER.
     *
     * @param $arrProfileInfo
     *
     * @return array result (first element - boolean error, second - string description)
     */
    public function updateProfile($arrProfileInfo)
    {
        try {
            // Send request and parse result
            $arrResult       = $this->_xmlRequest->updateProfileRequest($arrProfileInfo);
            $strErrorMessage = !is_array($arrResult) ? $arrResult : $this->_oServerExceptions->getExceptionMessageByCode(
                $arrResult['response.responseCode'],
                $arrResult['response.text'],
                'profile_update'
            );
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $strErrorMessage = "Server Error.";
        }

        return array('error' => !empty($strErrorMessage), 'message' => $strErrorMessage);
    }


    /**
     * READ PROFILE FROM PAYMENT GATEWAY SERVER.
     *
     * @param $customerRefNum
     *
     * @return array result (first element - boolean error, second - string description)
     */
    public function readProfile($customerRefNum)
    {
        try {
            // In PayWay there is no 'read profile' method
            // so we assume that profile is always created
            // and in sources we'll try to update even if profile isn't created yet.
            // This will work because create and update requests are equal.
            $strErrorMessage = '';
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $strErrorMessage = "Server Error.";
        }

        return array('error' => !empty($strErrorMessage), 'message' => $strErrorMessage);
    }

    /**
     * DELETE PROFILE FROM PAYMENT GATEWAY SERVER.
     *
     * @param $customerRefNum
     *
     * @return array result (first element - boolean error, second - string description)
     */
    public function deleteProfile($customerRefNum)
    {
        try {
            // Send request and parse result
            $arrResult       = $this->_xmlRequest->deleteProfileRequest($customerRefNum);
            $strErrorMessage = !is_array($arrResult) ? $arrResult : $this->_oServerExceptions->getExceptionMessageByCode(
                $arrResult['response.responseCode'],
                $arrResult['response.text'],
                'profile_delete'
            );
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $strErrorMessage = "Server Error.";
        }

        return array('error' => !empty($strErrorMessage), 'message' => $strErrorMessage);
    }

    /**
     * RAISE PAYMENT ON PAYMENT SERVER BASED ON PROFILE
     *
     * @param $arrOrderInfo
     *
     * @return array result (first element - boolean error, second - string description)
     */
    public function chargeAmountBasedOnProfile($arrOrderInfo)
    {
        $resCode     = '';
        $arrResponse = array();
        try {
            // Send request and parse result
            $arrResponse     = $this->_xmlRequest->chargeAmountBasedOnProfileRequest($arrOrderInfo);
            $resCode         = !is_array($arrResponse) ? -1 : $arrResponse['response.responseCode'];
            $strErrorMessage = !is_array($arrResponse) ? $arrResponse : $this->_oServerExceptions->getExceptionMessageByCode(
                $arrResponse['response.responseCode'],
                $arrResponse['response.text'],
                'charge_based_on_profile'
            );
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $strErrorMessage = "Server Error.";
        }

        return array('error' => !empty($strErrorMessage), 'message' => $strErrorMessage, 'response' => $arrResponse, 'code' => $resCode);
    }

    /**
     * CHARGE AMOUNT WITHOUT USING PROFILE.
     *
     * @param $arrOrderInfo
     *
     * @return array result (first element - boolean error, second - string description)
     */
    public function chargeAmount($arrOrderInfo)
    {
        try {
            // Send request and parse result
            $arrResult       = $this->_xmlRequest->chargeAmountRequest($arrOrderInfo);
            $strErrorMessage = !is_array($arrResult) ? $arrResult : $this->_oServerExceptions->getExceptionMessageByCode(
                $arrResult['response.responseCode'],
                $arrResult['response.text'],
                'charge'
            );
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $strErrorMessage = "Server Error.";
        }

        return array('error' => !empty($strErrorMessage), 'message' => $strErrorMessage);
    }
}