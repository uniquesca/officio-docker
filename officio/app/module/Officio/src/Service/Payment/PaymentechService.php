<?php

namespace Officio\Service\Payment;

use Exception;
use Officio\Common\Service\Log;
use Officio\Service\Payment\Paymentech\ServerExceptions;
use Officio\Service\Payment\Paymentech\XMLRequest;

class PaymentechService implements PaymentServiceInterface
{

    /** @var XMLRequest */
    private $_xmlRequest;

    /** @var ServerExceptions */
    private $_oServerExceptions;

    /** @var Log */
    protected $_log;

    /** @var array */
    protected $_config;

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
     * Generate profile id for PT
     *
     * @param int $id - id of already created prospect or company
     * @param bool $booProspect - true if prospect id is used,
     * otherwise false means that company id is used
     *
     * @return string generated PT profile id
     */
    public function generatePaymentProfileId($id, $booProspect = true) {
        $id = round(microtime(true) * 100);
        return 'PT' . $id;//$booProspect ? 'Prospect ' . $id : 'Company - ' . $id;
    }


    /**
     * Generate order id for PT
     *
     * @param int $orderId - id of already created invoice/order in Officio
     *
     * @return string generated PT order id
     */
    public function generatePaymentOrderId($orderId) {
        return 'Inv ' . $orderId;
    }


    /**
     * Generate trace number for PT, which will be used in retry logic
     * Valid Values are from 1-9999999999999999
     *
     * @return float|int trace number
     */
    public function generatePaymentTraceNumber() {
        return microtime(true) * 100;
    }


    /**
     * Parse response from PT
     *
     * @param $arrResponse -  array or string $response
     * @param string $action
     * @return array
     */
    private function _parseResponse($arrResponse, $action) {
        $resCode         = -1;
        $strErrorMessage = '';

        if(is_array($arrResponse) && array_key_exists('response', $arrResponse)) {
            $arrResponse = $arrResponse['response'];
        }

        if(is_array($arrResponse) && array_key_exists('Response', $arrResponse) && is_array($arrResponse['Response'])) {
            // Check for error
            if(isset($arrResponse['Response']['QuickResp']['StatusMsg'])) {
                $strErrorMessage = $arrResponse['Response']['QuickResp']['StatusMsg'];
            } elseif (isset($arrResponse['Response']['QuickResponse']['StatusMsg'])) {
                $strErrorMessage = $arrResponse['Response']['QuickResponse']['StatusMsg'];
            }

            // Get code result
            if(empty($strErrorMessage)) {
                switch ($action) {
                    case 'profile':
                        if(isset($arrResponse['Response']['ProfileResp']['ProfileProcStatus'])) {
                            $resCode         = $arrResponse['Response']['ProfileResp']['ProfileProcStatus'];
                            $strErrorMessage = $arrResponse['Response']['ProfileResp']['CustomerProfileMessage'];
                        }
                        break;

                    case 'order':
                        if(isset($arrResponse['Response']['NewOrderResp']['RespCode'])) {
                            $resCode         = $arrResponse['Response']['NewOrderResp']['RespCode'];
                            $strErrorMessage = $arrResponse['Response']['NewOrderResp']['RespMsg'];
                        }
                        break;

                    default:
                        break;
                }
            }
        } else {
            $strErrorMessage = $arrResponse;
        }


        return array('code' => $resCode, 'message' => $strErrorMessage);
    }

    /**
     * CREATE PROFILE ON PAYMENT GATEWAYS SERVER
     *
     * @param $arrProfileInfo
     *
     * @return array result (first element - boolean error, second - string description)
     */
    public function createProfile ($arrProfileInfo) {
        $booError = true;
        try {
            // Send request
            $response  = $this->_xmlRequest->createProfileRequest($arrProfileInfo);
            $arrResult = $this->_parseResponse($response, 'profile');


            $resCode  = $arrResult['code'];
            $strError = $arrResult['message'];

            switch ($resCode) {
                case '0':
                    // Created successfully
                    $booError        = false;
                    $strErrorMessage = '';
                    break;

                // Known exceptions    
                case 841:
                case 9569: // DATE VALIDATION EXCEPTION
                default:
                    $strErrorMessage = $this->_oServerExceptions->getProfileException($resCode, $strError);
                    break;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $strErrorMessage = "Server Error.";
        }


        return array('error' => $booError, 'message' => $strErrorMessage, 'response' => $response);
    }

    /**
     * UPDATE PROFILE ON PAYMENT GATEWAYS SERVER.
     *
     * @param $arrProfileInfo
     *
     * @return array result (first element - boolean error, second - string description)
     */
    public function updateProfile($arrProfileInfo){
        try {
            // Send request
            $response  = $this->_xmlRequest->updateProfileRequest($arrProfileInfo);
            $arrResult = $this->_parseResponse($response, 'profile');


            $resCode  = $arrResult['code'];
            $strError = $arrResult['message'];

            switch ($resCode) {
                case '0':
                    // Created successfully
                    $strErrorMessage = '';
                    break;

                // Known exceptions    
                case 841:  // CREDIT CARD IS NOT VALID / NUMBER RANGE
                case 9569: // DATE IS VALIDATION EXCEPTION
                case 9581: // PROFILE IS NOT EXIST ON THE GIVEN REF NUM.
                default:
                    $strErrorMessage = $this->_oServerExceptions->getProfileException($resCode, $strError);
                    break;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $strErrorMessage = "Server Error.";
        }


        return array('error' => !empty($strErrorMessage), 'message' => $strErrorMessage, 'response' => $response);
    }


    /**
     * READ PROFILE FROM PAYMENT GATEWAY SERVER.
     *
     * @param $customerRefNum
     *
     * @return array result (first element - boolean error, second - string description)
     */
    public function readProfile($customerRefNum){
        try {
            $response  = $this->_xmlRequest->readProfileRequest($customerRefNum);
            $arrResult = $this->_parseResponse($response, 'profile');


            $resCode  = $arrResult['code'];
            $strError = $arrResult['message'];

            switch ($resCode) {
                case '0':
                    // Created successfully
                    $strErrorMessage = '';
                    break;

                // Known exceptions    
                default:
                    $strErrorMessage = $this->_oServerExceptions->getProfileException($resCode, $strError);
                    break;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $strErrorMessage = "Server Error.";
        }

        return array('error' => !empty($strErrorMessage), 'message' => $strErrorMessage, 'response' => $response);
    }

    /**
     * DELETE PROFILE FROM PAYMENT GATEWAY SERVER.
     *
     * @param $customerRefNum
     *
     * @return array result (first element - boolean error, second - string description)
     */
    public function deleteProfile($customerRefNum){
        try {
            $response  = $this->_xmlRequest->deleteProfileRequest($customerRefNum);
            $arrResult = $this->_parseResponse($response, 'profile');


            $resCode  = $arrResult['code'];
            $strError = $arrResult['message'];

            switch ($resCode) {
                case '0':
                    // Deleted successfully
                    $strErrorMessage = '';
                    break;

                // Known exceptions    
                default:
                    $strErrorMessage = $this->_oServerExceptions->getProfileException($resCode, $strError);
                    break;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $strErrorMessage = "Server Error.";
        }

        return array('error' => !empty($strErrorMessage), 'message' => $strErrorMessage, 'response' => $response);
    }

    /**
     * RAISE PAYMENT ON PAYMENT SERVER BASED ON PROFILE
     *
     * @param $arrOrderInfo
     *
     * @return array result (first element - boolean error, second - string description)
     */
    public function chargeAmountBasedOnProfile($arrOrderInfo){
        try {
            // Send request
            $response  = $this->_xmlRequest->chargeAmountBasedOnProfileRequest($arrOrderInfo);
            $arrResult = $this->_parseResponse($response, 'order');


            $resCode  = $arrResult['code'];
            $strError = $arrResult['message'];

            switch ($resCode) {
                case '00':
                    // Created successfully
                    $strErrorMessage = '';
                    break;

                // Known exceptions    
                case 14:
                case 74:
                case 778:
                case 841:
                default:
                    $strErrorMessage = $this->_oServerExceptions->getExceptionName($resCode, $strError);
                    break;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $strErrorMessage = "Server Error.";
            $response        = array();
            $resCode         = false;
        }


        return array('error' => !empty($strErrorMessage), 'message' => $strErrorMessage, 'response' => $response, 'code' => $resCode);
    }

    /**
     * CHARGE AMOUNT WITHOUT USING PROFILE.
     *
     * @param $arrOrderInfo
     *
     * @return array result (first element - boolean error, second - string description)
     */
    public function chargeAmount($arrOrderInfo){
        try {
            // Send request
            $response  = $this->_xmlRequest->chargeAmountRequest($arrOrderInfo);
            $arrResult = $this->_parseResponse($response, 'order');


            $resCode  = $arrResult['code'];
            $strError = $arrResult['message'];

            switch ($resCode) {
                case '00':
                    // Created successfully
                    $strErrorMessage = '';
                    break;

                // Known exceptions    
                case 14:
                case 74:
                case 778:
                case 841:
                default:
                    $strErrorMessage = $this->_oServerExceptions->getExceptionName($resCode, $strError);
                    break;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $strErrorMessage = "Server Error.";
        }


        return array('error' => !empty($strErrorMessage), 'message' => $strErrorMessage, 'response' => $response);
    }
}