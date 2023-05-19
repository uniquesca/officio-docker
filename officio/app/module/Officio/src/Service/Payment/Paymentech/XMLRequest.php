<?php

namespace Officio\Service\Payment\Paymentech;

use Exception;
use Officio\Common\Service\Log;
use Officio\Service\Payment\XMLRequestInterface;

class XMLRequest implements XMLRequestInterface
{

    /** @var Log */
    protected $_log;

    /** @var array */
    protected $_config;

    /** @var XmlHelper */
    private $_xmlHelper;

    private $_SubmissionUrl;
    private $_MerchantID;
    private $_CustomerBin;
    private $_TerminalID;
    private $_CurrencyCode;
    private $_CurrencyExponent;

    private $_retryCount = '';
    private $_lastRetryAttempt = '';

    public function __construct(array $config, Log $log)
    {
        $this->_log    = $log;
        $this->_config = $config['payment'];

        $booTestServer = $this->_config['use_test_server'];

        // Define login details
        $this->_SubmissionUrl = $booTestServer ? $this->_config['testSubmissionUrl'] : $this->_config['submissionUrl'];

        $this->_MerchantID       = $booTestServer ? $this->_config['testMerchantID'] : $this->_config['merchantID'];
        $this->_CustomerBin      = $this->_config['customerBin'];
        $this->_TerminalID       = $this->_config['terminalID'];
        $this->_CurrencyCode     = $this->_config['currencyCode'];     // Canadian Dollar
        $this->_CurrencyExponent = $this->_config['currencyExponent']; // Related to currency, 2 for Canadian Dollar
        $this->_xmlHelper        = new XmlHelper();
    }

    /**
     * CREATE PROFILE ON PAYMENT SERVER
     *
     * @param $arrProfileInfo - array with all profile params
     * @return array|string
     */
    public function createProfileRequest($arrProfileInfo)
    {
        // Prepare data
        $arrSendInfo = array(
            'Request' => array(
                'Profile' => $this->_prepareProfileFields($arrProfileInfo, 'C')
            )
        );

        $xmlRequestData = $this->_xmlHelper->XML_serialize($arrSendInfo);

        // Send request
        return $this->_processRequest($xmlRequestData);
    }

    private function _prepareProfileFields($arrProfileInfo, $action)
    {
        $arrPreparedFields = array(
            'CustomerBin'        => $this->_CustomerBin,
            'CustomerMerchantID' => $this->_MerchantID,
            'CustomerName'       => $this->_getArrValue('customerName', $arrProfileInfo, 30),
            'CustomerRefNum'     => $this->_getArrValue('customerRefNum', $arrProfileInfo, 22),

            'CustomerAddress1' => $this->_getArrValue('customerAddress1', $arrProfileInfo, 30),
            'CustomerAddress2' => $this->_getArrValue('customerAddress2', $arrProfileInfo, 30),
            'CustomerCity'     => $this->_getArrValue('customerCity', $arrProfileInfo, 20),
            'CustomerState'    => $this->_getArrValue('customerState', $arrProfileInfo, 2),
            'CustomerZIP'      => $this->_getArrValue('customerZIP', $arrProfileInfo, 10),
            'CustomerEmail'    => $this->_getArrValue('customerEmail', $arrProfileInfo, 50),
            'CustomerPhone'    => $this->_getArrValue('customerPhone', $arrProfileInfo, 14),

            'CustomerProfileAction'           => $action,
            'CustomerProfileOrderOverrideInd' => 'NO',
            'CustomerProfileFromOrderInd'     => 'S',

            'OrderDefaultDescription' => $this->_getArrValue('OrderDefaultDescription', $arrProfileInfo, 64),
            'OrderDefaultAmount'      => $this->_getArrValue('orderDefaultAmount', $arrProfileInfo),

            'CustomerAccountType' => 'CC',
            'Status'              => 'A',
            'CCAccountNum'        => $this->_getArrValue('creditCardNum', $arrProfileInfo, 19),
            'CCExpireDate'        => $this->_getArrValue('creditCardExpDate', $arrProfileInfo, 4)
        );

        return $this->_removeNullValues($arrPreparedFields);
    }

    private function _getArrValue($key, $arr, $valLength = 0)
    {
        $val = in_array($key, array('amount', 'orderDefaultAmount')) ?
            (array_key_exists($key, $arr) ? round((double)$arr[$key], 2) * 100 : null) :
            (array_key_exists($key, $arr) ? trim($arr[$key]) : null);

        if (!is_null($val) && !empty($valLength)) {
            $val = substr($val, 0, $valLength);
        }

        return $val;
    }

    private function _removeNullValues($arr)
    {
        if (!is_array($arr) || empty($arr)) {
            return array();
        }

        foreach ($arr as $key => $val) {
            if (is_null($val)) {
                unset($arr[$key]);
            }
        }

        return $arr;
    }

    private function _processRequest($xmlData, $traceNum = '')
    {
        try {
            $booDone       = false;
            $retryAttempts = 0;
            $response      = '';

            // Execute the transaction
            while (!$booDone) {
                $response = $this->_sendRequestTo($xmlData, $traceNum);

                if (!($response['booError'])) {
                    // Response was received, no error was generated
                    $booDone = true;
                } else {
                    // This means that we failed to connect or some other error
                    // was generated, so we may need to retry.

                    // If the retry trace is not set then we are not even going
                    // to chance a retry of any kind
                    if (empty($traceNum)) {
                        throw new Exception("Connecting to Paymentech gateway has failed.");
                    }


                    $retryAttempts++;
                    if ($retryAttempts > $this->_config['max_retry_attempts']) {
                        // we have meet the limit for trying to connect
                        throw new Exception("Transaction failed - please check log for details.");
                    }
                }
            }
        } catch (Exception $e) {
            $response = $e->getMessage();
        }

        return $response;
    }

    /**
     * Send request to PT server
     *
     * @param $xmlData - string xml request
     * @param $traceNum - unique number to identify transaction
     * @return array
     */
    private function _sendRequestTo($xmlData, $traceNum)
    {
        try {
            $arrHeaders = array(
                "MIME-Version: 1.1",
                "Content-type: application/PTI47",
                "Content-transfer-encoding: text",
                "Request-number: 1",
                "Document-type: Request",
                "Interface-Version: Officio 1.0"
            );

            if (!empty($traceNum)) {
                $arrHeaders[] = sprintf("Merchant-id: %s", $this->_MerchantID);
                $arrHeaders[] = sprintf("Trace-number: %d", $traceNum);
            }
            $arrHeaders[] = "Connection: close";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->_SubmissionUrl);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->_config['timeout']);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $arrHeaders);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLINFO_HEADER_OUT, false);

            // Force to use TLS v1.2
            curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);

            // Parse response headers - to know retry values if any
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, '_curlHeaderCallback'));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);


            // Run request
            $data = curl_exec($ch);

            // Check for errors
            $strError = curl_error($ch);
            $booError = !empty($strError);
            if ($booError) {
                $response = $strError;
            } else {
                $data     = preg_replace('/&(?!amp;|quot;|nbsp;|gt;|lt;|laquo;|raquo;|copy;|reg;|bul;|rsquo;)/', '&amp;', $data);
                $response = $this->_xmlHelper->XML_unserialize($data);
            }


            if (!$booError) {
                if (!empty($this->_retryCount)) {
                    $response['RetryCount'] = $this->_retryCount;
                }

                if (!empty($this->_lastRetryAttempt)) {
                    $response['LastRetryAttempt'] = $this->_lastRetryAttempt;
                }
            }

            // Save incoming/outgoing xml
            if ($this->_config['save_log'] || $booError) {
                $this->_log->saveInputOutputXml(curl_getinfo($ch), $this->_xml_remove_ccinfo(implode(PHP_EOL, $arrHeaders)), $this->_xml_remove_ccinfo($data), $strError);
            }

            @curl_close($ch);
        } catch (Exception $e) {
            $response = $e->getMessage();
            $booError = true;
            $this->_log->debugPaymentErrorToFile($response);
        }

        return array('booError' => $booError, 'response' => $response);
    }

    /**
     * Remove CC info from xml string
     *
     * E.g.:
     *
     * <CCAccountNum>4111111111111111</CCAccountNum>
     * ->
     * <CCAccountNum>4111********1111</CCAccountNum>
     *
     * <CardSecVal>1234</CardSecVal>
     * ->
     * <CardSecVal>1**4</CardSecVal>
     *
     * @param $strXml - string xml we need update
     * @return string $strXml
     */
    private function _xml_remove_ccinfo($strXml)
    {
        $arrReplaceFields = array(
            'CCAccountNum',
            'CCExpireDate',
            'AccountNum',
            'Exp',
            'CardSecVal'
        );

        foreach ($arrReplaceFields as $strReplaceField) {
            preg_match_all("%(<$strReplaceField>)(.*)(</$strReplaceField>)%i", $strXml, $all_matches);

            foreach ($all_matches[2] as $matchn => $updateVal) {
                $updateVal       = trim($updateVal);
                $updateValLength = strlen($updateVal);

                $beginning = $end = '';
                if ($updateValLength >= 4 && $updateValLength <= 10) {
                    $beginning = substr($updateVal, 0, 1);
                    $end       = substr($updateVal, -1);
                } elseif ($updateValLength > 10) {
                    $beginning = substr($updateVal, 0, 4);
                    $end       = substr($updateVal, -4);
                }

                $replaceVal = $beginning . str_repeat("*", $updateValLength - strlen($beginning) - strlen($end)) . $end;
                $strXml     = str_replace(
                    $all_matches[0][$matchn],
                    $all_matches[1][$matchn] . $replaceVal . $all_matches[3][$matchn],
                    $strXml
                );
            }
        }

        return $strXml;
    }

    /**
     * UPDATE PROFILE ON PAYMENT SERVER
     *
     * @param $arrProfileInfo - array with all profile params
     * @return array|string
     */
    public function updateProfileRequest($arrProfileInfo)
    {
        // Prepare data
        $arrSendInfo = array(
            'Request' => array(
                'Profile' => $this->_prepareProfileFields($arrProfileInfo, 'U')
            )
        );

        $xmlRequestData = $this->_xmlHelper->XML_serialize($arrSendInfo);

        // Send request
        return $this->_processRequest($xmlRequestData);
    }

    /**
     * Read profile info from PT server
     *
     * @param $customerRefNum - string Customer Reference Number
     * @return array|string
     */
    public function readProfileRequest($customerRefNum)
    {
        // Prepare data
        $arrSendInfo    = array(
            'Request' => array(
                'Profile' => array(
                    'CustomerBin'           => $this->_CustomerBin,
                    'CustomerMerchantID'    => $this->_MerchantID,
                    'CustomerRefNum'        => substr($customerRefNum ?? '', 0, 22),
                    'CustomerProfileAction' => 'R'
                )
            )
        );
        $xmlRequestData = $this->_xmlHelper->XML_serialize($arrSendInfo);

        // Send request
        return $this->_processRequest($xmlRequestData);
    }

    /**
     * Delete profile from PT server
     *
     * @param $customerRefNum - string Customer Reference Number
     * @return array|string
     */
    public function deleteProfileRequest($customerRefNum)
    {
        // Prepare data
        $arrSendInfo    = array(
            'Request' => array(
                'Profile' => array(
                    'CustomerBin'           => $this->_CustomerBin,
                    'CustomerMerchantID'    => $this->_MerchantID,
                    'CustomerRefNum'        => $customerRefNum,
                    'CustomerProfileAction' => 'D'
                )
            )
        );
        $xmlRequestData = $this->_xmlHelper->XML_serialize($arrSendInfo);

        // Send request
        return $this->_processRequest($xmlRequestData);
    }

    /**
     * CHARGE AMOUNT ON PAYMENT SERVER,
     * ONE TIME PAYMENT
     *
     * @param $arrOrderInfo - array with order info
     * @return array|string
     */
    public function chargeAmountRequest($arrOrderInfo)
    {
        // Prepare data
        $arrSendInfo = array(
            'Request' => array(
                'NewOrder' => $this->_prepareOrderFields($arrOrderInfo)
            )
        );

        // Prepare additional fields
        $xmlRequestData = $this->_xmlHelper->XML_serialize($arrSendInfo);

        // Send request
        $traceNum = array_key_exists('traceNumber', $arrOrderInfo) ? $arrOrderInfo['traceNumber'] : '';
        return $this->_processRequest($xmlRequestData, $traceNum);
    }

    private function _prepareOrderFields($arrOrderInfo)
    {
        // Optional fields
        $arrPreparedFields = array(
            'IndustryType'     => 'EC',
            'MessageType'      => 'AC',
            'BIN'              => $this->_CustomerBin,
            'MerchantID'       => $this->_MerchantID,
            'TerminalID'       => $this->_TerminalID,
            'AccountNum'       => $this->_getArrValue('creditCardNum', $arrOrderInfo, 19),
            'Exp'              => $this->_getArrValue('creditCardExpDate', $arrOrderInfo, 4),
            'CurrencyCode'     => $this->_CurrencyCode,
            'CurrencyExponent' => $this->_CurrencyExponent,

            'CardSecVal' => $this->_getArrValue('CardSecVal', $arrOrderInfo, 4),

            'AVSzip'      => $this->_getArrValue('AVSzip', $arrOrderInfo, 10),
            'AVSaddress1' => $this->_getArrValue('AVSaddress1', $arrOrderInfo, 30),
            'AVSaddress2' => $this->_getArrValue('AVSaddress2', $arrOrderInfo, 30),
            'AVSphoneNum' => $this->_getArrValue('AVSphoneNum', $arrOrderInfo, 14),

            'CustomerProfileFromOrderInd'     => $this->_getArrValue('customerProfileFromOrderInd', $arrOrderInfo, 5),
            'CustomerRefNum'                  => $this->_getArrValue('customerRefNum', $arrOrderInfo, 22),
            'CustomerProfileOrderOverrideInd' => $this->_getArrValue('customerProfileOrderOverrideInd', $arrOrderInfo, 2),

            'OrderID'  => $this->_getArrValue('orderId', $arrOrderInfo, 22),
            'Amount'   => $this->_getArrValue('amount', $arrOrderInfo),
            'Comments' => $this->_getArrValue('comments', $arrOrderInfo, 64)
        );

        return $this->_removeNullValues($arrPreparedFields);
    }

    /**
     * RAISE PAYMENT ON PAYMENT SERVER BASED ON PROFILE
     *
     * @param $arrOrderInfo - array with order info
     * @return array|string
     */
    public function chargeAmountBasedOnProfileRequest($arrOrderInfo)
    {
        // Prepare data
        $arrSendInfo = array(
            'Request' => array(
                'NewOrder' => $this->_prepareOrderFields($arrOrderInfo)
            )
        );

        $xmlRequestData = $this->_xmlHelper->XML_serialize($arrSendInfo);

        // Send request
        $traceNum = array_key_exists('traceNumber', $arrOrderInfo) ? $arrOrderInfo['traceNumber'] : '';
        return $this->_processRequest($xmlRequestData, $traceNum);
    }

    private function _curlHeaderCallback($ch, $strHeader)
    {
        if (preg_match('/^retry-count:(.*)$/i', $strHeader, $regs)) {
            $this->_retryCount = trim($regs[1] ?? '');
        }

        if (preg_match('/^last-retry-attempt:(.*)$/i', $strHeader, $regs)) {
            $this->_lastRetryAttempt = trim($regs[1] ?? '');
        }

        return strlen($strHeader ?? '');
    }
}