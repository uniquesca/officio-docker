<?php

/** @noinspection PhpUndefinedClassInspection */

/** @noinspection PhpUndefinedNamespaceInspection */

namespace Officio\Service\Payment;

use Exception;
use Officio\Common\Service\BaseService;
use Stripe\Charge;
use Stripe\Token;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Stripe extends BaseService
{

    /**
     * Init Stripe config/settings
     */
    private function _initStripe()
    {
        //        $curl = new \Stripe\HttpClient\CurlClient(array(CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2));
        //        \Stripe\ApiRequestor::setHttpClient($curl);

        $key = $this->_config['payment']['stripe']['secret'] ?? '';
        \Stripe\Stripe::setApiKey($key);
    }

    /**
     * Charge user
     *
     * @param string $paymentDescription
     * @param int $memberId
     * @param float $amount
     * @param string $ccNumber
     * @param string $ccExpMonth
     * @param string $ccExpYear
     * @param string $ccCvc
     * @return array of string error and transaction id
     */
    public function payWithCard($paymentDescription, $memberId, $amount, $ccNumber, $ccExpMonth, $ccExpYear, $ccCvc)
    {
        $transactionId = 0;
        $strError      = '';

        try {
            $this->_initStripe();

            $tok = Token::create(array(
                'card' => array(
                    'number'    => $ccNumber,
                    'exp_month' => $ccExpMonth,
                    'exp_year'  => $ccExpYear,
                    'cvc'       => $ccCvc
                )
            ));

            $result = Charge::create(array(
                'amount'      => $amount * 100,
                'currency'    => $this->_config['payment']['stripe']['currency'],
                'source'      => $tok->id,
                'description' => $paymentDescription,

                'metadata' => array(
                    'member_id' => $memberId
                ),
            ));

            if (empty($result->id)) {
                $strError = $this->_tr->translate('Internal error.');
            } else {
                $transactionId = $result->id;
            }
        } catch (Exception $e) {
            $strError = $e->getMessage();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($strError, $transactionId);
    }

    /**
     * Check if specific transaction was processed successfully
     *
     * @param $transactionId
     * @return bool
     */
    public function checkTransactionCompletedSuccessfully($transactionId)
    {
        $booSuccess = false;

        try {
            if ($transactionId) {
                $this->_initStripe();

                $response   = Charge::retrieve($transactionId);
                $booSuccess = isset($response['status']) && $response['status'] == 'succeeded';
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }
}
