<?php

namespace Officio\Service\Payment\PayWay;

use Exception;
use Officio\Common\Service\Log;
use Officio\Service\Payment\XMLRequestInterface;

class XMLRequest implements XMLRequestInterface
{

    /** @var Log */
    private $_log;

    /** @var array */
    private $_config;

    /** @var Qvalent_PayWayAPI */
    private $_payWayApi;

    public function __construct(array $config, Log $log)
    {
        $this->_log = $log;

        // Load config
        $this->_config = $config['payment'];

        // Prepare connection params
        $arrParams = array(
            'certificateFile' => $this->_config['payway']['certificate_crt'],
            'caFile'          => $this->_config['payway']['certificate_ca'],
            'logDirectory'    => $this->_config['log_directory'],
            'socketTimeout'   => $this->_config['timeout'] * 1000, // must be in ms
        );

        $this->_payWayApi = new Qvalent_PayWayAPI();
        $this->_payWayApi->initialise(http_build_query($arrParams));
    }

    /**
     * Create profile on PayWay server
     *
     * @param $arrProfileInfo - array with all profile params
     * @return array
     */
    public function createProfileRequest($arrProfileInfo)
    {
        $requestParameters = array(
            'order.type'                       => 'registerAccount',
            'customer.username'                => $this->_config['payway']['username'],
            'customer.password'                => $this->_config['payway']['password'],
            'customer.merchant'                => $this->_config['payway']['merchant'],
            'customer.customerReferenceNumber' => substr($arrProfileInfo['customerRefNum'], 0, 20),

            'card.PAN'                         => substr($arrProfileInfo['creditCardNum'], 0, 19),
            'card.expiryMonth'                 => substr($arrProfileInfo['creditCardExpDate'], 0, 2),
            'card.expiryYear'                  => substr($arrProfileInfo['creditCardExpDate'], 2, 2),
            'card.cardHolderName'              => substr($arrProfileInfo['customerName'], 0, 60),
        );

        return $this->_processRequest($requestParameters);
    }


    /**
     * Update profile on PayWay server
     *
     * @param $arrProfileInfo - array with all profile params
     * @return array
     */
    public function updateProfileRequest($arrProfileInfo)
    {
        // The same functionality/params as with 'create account'
        // But we provide an existing customer reference number
        $requestParameters = array(
            'order.type'                       => 'registerAccount',
            'customer.username'                => $this->_config['payway']['username'],
            'customer.password'                => $this->_config['payway']['password'],
            'customer.merchant'                => $this->_config['payway']['merchant'],
            'customer.customerReferenceNumber' => substr($arrProfileInfo['customerRefNum'], 0, 20),

            'card.PAN'                         => substr($arrProfileInfo['creditCardNum'], 0, 19),
            'card.expiryMonth'                 => substr($arrProfileInfo['creditCardExpDate'], 0, 2),
            'card.expiryYear'                  => substr($arrProfileInfo['creditCardExpDate'], 2, 2),
            'card.cardHolderName'              => substr($arrProfileInfo['customerName'], 0, 60),
        );

        return $this->_processRequest($requestParameters);
    }


    /**
     * Read profile info from PayWay server
     *
     * @param $customerRefNum - string Customer Reference Number
     * @return array|string
     */
    public function readProfileRequest($customerRefNum)
    {
        $requestParameters = array(
            'order.type'                       => 'query',
            'customer.username'                => $this->_config['payway']['username'],
            'customer.password'                => $this->_config['payway']['password'],
            'customer.merchant'                => $this->_config['payway']['merchant'],
            'customer.customerReferenceNumber' => substr($customerRefNum, 0, 20),
        );

        return $this->_processRequest($requestParameters);
    }


    /**
     * Delete profile from PayWay server
     *
     * @param $customerRefNum - string Customer Reference Number
     * @return array
     */
    public function deleteProfileRequest($customerRefNum)
    {
        $requestParameters = array(
            'order.type'                       => 'deregisterAccount',
            'customer.username'                => $this->_config['payway']['username'],
            'customer.password'                => $this->_config['payway']['password'],
            'customer.merchant'                => $this->_config['payway']['merchant'],
            'customer.customerReferenceNumber' => substr($customerRefNum, 0, 20),
        );

        return $this->_processRequest($requestParameters);
    }


    /**
     * Charge amount on a PayWay server (one time payment)
     *
     * @param $arrOrderInfo - array with order info
     * @return array|string
     */
    public function chargeAmountRequest($arrOrderInfo)
    {
        // Generate PayWay order id from order id and trace number -
        // we need to be sure that order id is unique
        $orderId = $arrOrderInfo['orderId'];
        $orderId .= array_key_exists('traceNumber', $arrOrderInfo) && strlen($arrOrderInfo['traceNumber'] ?? '') ? '_' . $arrOrderInfo['traceNumber'] : '';
        $orderId = substr($orderId, 0, 20);

        // Check if this order was already sent and if we can charge
        $requestParameters = array(
            'order.type'           => 'query',
            'customer.username'    => $this->_config['payway']['username'],
            'customer.password'    => $this->_config['payway']['password'],
            'customer.merchant'    => $this->_config['payway']['merchant'],

            'customer.orderNumber' => $orderId
        );
        $arrCheckOrderResult = $this->_processRequest($requestParameters);

        switch ($arrCheckOrderResult['response.responseCode']) {
            case 'QG':
                // 'Order number supplied is unknown' - means that such order wasn't created in PayWay,
                // so we can charge

                $requestParameters = array(
                    'order.type'           => 'capture',
                    'customer.username'    => $this->_config['payway']['username'],
                    'customer.password'    => $this->_config['payway']['password'],
                    'customer.merchant'    => $this->_config['payway']['merchant'],

                    'customer.orderNumber' => $orderId,
                    'card.PAN'             => substr($arrOrderInfo['creditCardNum'], 0, 19),
                    'card.expiryMonth'     => substr($arrOrderInfo['creditCardExpDate'], 0, 2),
                    'card.expiryYear'      => substr($arrOrderInfo['creditCardExpDate'], 2, 2),

                    'order.ECI'            => 'SSL',
                    'card.currency'        => $this->_config['payway']['currency_code'], // 3 chars
                    'order.amount'         => number_format((float)$arrOrderInfo['amount'] * 100, 0, '.', ''),
                );

                if(array_key_exists('customerName', $arrOrderInfo)) {
                    $requestParameters['card.cardHolderName'] = substr($arrOrderInfo['customerName'], 0, 60);
                }

                if(array_key_exists('CardSecVal', $arrOrderInfo)) {
                    $requestParameters['card.CVN'] = substr($arrOrderInfo['CardSecVal'], 0, 4);
                }

                // Send request
                $result = $this->_processRequest($requestParameters);
                break;

            default:
                $result = $arrCheckOrderResult;
                break;
        }

        return $result;
    }


    /**
     * Charge amount on a PayWay server (based on already created profile)
     *
     * @param $arrOrderInfo - array with order info
     * @return array|string
     */
    public function chargeAmountBasedOnProfileRequest($arrOrderInfo)
    {
        // Generate PayWay order id from order id and trace number -
        // we need to be sure that order id is unique
        $orderId = $arrOrderInfo['orderId'];
        $orderId .= array_key_exists('traceNumber', $arrOrderInfo) && strlen($arrOrderInfo['traceNumber'] ?? '') ? '_' . $arrOrderInfo['traceNumber'] : '';
        $orderId = substr($orderId, 0, 20);

        // Check if this order was already sent and if we can charge
        $requestParameters = array(
            'order.type'           => 'query',
            'customer.username'    => $this->_config['payway']['username'],
            'customer.password'    => $this->_config['payway']['password'],
            'customer.merchant'    => $this->_config['payway']['merchant'],

            'customer.orderNumber' => $orderId
        );
        $arrCheckOrderResult = $this->_processRequest($requestParameters);

        switch ($arrCheckOrderResult['response.responseCode']) {
            case 'QG':

                // 'Order number supplied is unknown' - means that such order wasn't created in PayWay,
                // so we can charge
                $requestParameters = array(
                    'order.type'                       => 'capture',
                    'customer.username'                => $this->_config['payway']['username'],
                    'customer.password'                => $this->_config['payway']['password'],
                    'customer.merchant'                => $this->_config['payway']['merchant'],
                    'customer.customerReferenceNumber' => substr($arrOrderInfo['customerRefNum'], 0, 20),

                    'order.ECI'                        => 'SSL',
                    'card.currency'                    => $this->_config['payway']['currency_code'], // 3 chars
                    'customer.orderNumber'             => $orderId,
                    'order.amount'                     => number_format((float)$arrOrderInfo['amount'] * 100, 0, '.', ''),
                );

                $result = $this->_processRequest($requestParameters);
                break;

            default:
                $result = $arrCheckOrderResult;
                break;
        }

        return $result;
    }

    /**
     * Send request to PayWay server several times
     * (max count is set in the config file)
     *
     * @param array $arrRequestParameters
     * @param string $traceNum
     * @return array|string
     */
    private function _processRequest($arrRequestParameters, $traceNum = '') {
        try {
            $booDone = false;
            $retryAttempts = 0;
            $response = '';

            // Execute the transaction
            while (!$booDone) {
                try {
                    // Prepare params
                    $requestText = $this->_payWayApi->formatRequestParameters($arrRequestParameters);

                    // Send request to server
                    $responseText = $this->_payWayApi->processCreditCard($requestText);

                    // Parse response
                    $response = $this->_payWayApi->parseResponseParameters($responseText);

                    $booError = false;
                } catch (Exception $e) {
                    $response = $e->getMessage();
                    $booError = true;
                    $this->_log->debugPaymentErrorToFile($response);
                }

                if(!$booError) {
                    // Response was received, no error was generated
                    $booDone = true;

                } else {
                    // This means that we failed to connect or some other error
                    // was generated, so we may need to retry.

                    // If the retry trace is not set then we are not even going
                    // to chance a retry of any kind
                    if(empty($traceNum)) {
                        throw new Exception("Connecting to PayWay gateway has failed.");
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
}