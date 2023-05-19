<?php

namespace Officio\Service\Payment;

use DOMDocument;
use Exception;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;
use Officio\Service\Payment\TranPage\SSISSvcRequest;
use Officio\Service\Payment\TranPage\SSISSvcResponse;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class TranPage extends BaseService
{

    /**
     * Load readable error message by its code
     *
     * @param $resultCode
     * @param $errorMessage
     * @return string
     */
    public function requestResultError($resultCode, $errorMessage)
    {
        $resultErrors = array(
            'ParameterError'       => $this->_tr->translate('Parameter Error. Please check request parameters.'),
            'Unavailable'          => $this->_tr->translate('TranPage Service is temporarily unavailable.'),
            'TooBusy'              => $this->_tr->translate('TranPage Service is too busy.'),
            'DuplicateTransaction' => $this->_tr->translate('Duplicated transaction ID.'),
            'GeneralError'         => ($errorMessage) ?: $this->_tr->translate('Unknown error.'),
            'SecurityError'        => $this->_tr->translate('Security problem.'),
            'MerchantError'        => $this->_tr->translate('The merchant account is not permitted to process.'),
            'other'                => $this->_tr->translate('Unknown TranPage request error.'),
        );

        return $resultErrors[$resultCode] ?? $resultErrors['other'];
    }

    /**
     * Load readable error message by its code
     *
     * @param $financialCode
     * @return string
     */
    public function financialErrorMessages($financialCode)
    {
        $financialErrors = array(
            'Declined'   => $this->_tr->translate('Transaction was declined.'),
            'Incomplete' => $this->_tr->translate('Transaction could not be completed.'),
            'other'      => $this->_tr->translate('Unknown TranPage transaction status.'),
        );

        return $financialErrors[$financialCode] ?? $financialErrors['other'];
    }

    protected function _prepareRequest($merchantKey, $tranType, $amount, $currency, $invoice, $tranId, $cardNumber, $expiryMMYY, $verificationValue, $cardholderName = null, $address = null)
    {
        $request                    = new SSISSvcRequest();
        $request->MerchantKey       = $merchantKey;
        $request->TranType          = $tranType;
        $request->Amount            = $amount;
        $request->Currency          = $currency;
        $request->Invoice           = $invoice;
        $request->TranId            = $tranId;
        $request->CardNumber        = $cardNumber;
        $request->ExpiryMMYY        = $expiryMMYY;
        $request->VerificationValue = $verificationValue;
        $request->CardholderName    = $cardholderName;
        $request->Address           = $address;

        return $request;
    }

    /**
     * Processes TranPage response and updates Officio transaction.
     *
     * @param SSISSvcResponse $response
     * @param $originalResponse
     * @return array of:
     * bool true if success, false if error
     * string error description or approval code on success
     */
    public function processResponse($response, $originalResponse)
    {
        $strError = '';
        if (('OK' == $response->ResultCode) && ('Approved' == $response->FinancialResultCode)) {
            if (!$this->approveTransaction($response->TranId, $response->ApprovalCode)) {
                $this->_log->debugPaymentErrorToFile(print_r($originalResponse, true));
                $strError = $this->_tr->translate('Internal error');
            }
        } else {
            $this->_log->debugPaymentErrorToFile(print_r($originalResponse, true));

            if ('OK' == $response->ResultCode) {
                $strError = $this->financialErrorMessages($response->FinancialResultCode);
            } else {
                $strError = $this->requestResultError($response->ResultCode, $response->ErrorMessage);
            }

            if (!$this->cancelTransaction($response->TranId, $response->ErrorMessage)) {
                $strError = $this->_tr->translate('Internal error');
            }
        }

        return array(empty($strError), empty($strError) ? $response->ApprovalCode : $strError);
    }

    /**
     * Sends request to TranPage, processes response and returns operation result.
     *
     * @param $amount
     * @param $currency
     * @param $invoice
     * @param $tranId
     * @param $cardNumber
     * @param $expiryMMYY
     * @param $verificationValue
     * @param null $cardholderName
     * @param null $address
     * @return array of:
     * bool true if success, false if error
     * string error description or approval code on success
     */
    public function processTransaction($amount, $currency, $invoice, $tranId, $cardNumber, $expiryMMYY, $verificationValue, $cardholderName = null, $address = null)
    {
        try {
            $request                    = new SSISSvcRequest();
            $request->MerchantKey       = $this->_config['payment']['tranPage']['merchant_key'];
            $request->TranType          = 'AuthAndPost';
            $request->Amount            = $amount;
            $request->Currency          = $currency;
            $request->Invoice           = $invoice;
            $request->TranId            = $tranId;
            $request->CardNumber        = $cardNumber;
            $request->ExpiryMMYY        = $expiryMMYY;
            $request->VerificationValue = $verificationValue;
            $request->CardholderName    = $cardholderName;
            $request->Address           = $address;

            // This specific xml must be sent to the payment system
            $xml = '<?xml version="1.0" encoding="utf-8"?>
            <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
            <soap:Body>
                <SSISProcessTransaction xmlns="http://equament.com/Schemas/Fmx/ssis">
                    <request>
                    </request>
                </SSISProcessTransaction>
            </soap:Body>
            </soap:Envelope>';

            // Replace/use the real params
            $doc = new DOMDocument();
            $doc->loadXML($xml);

            $xmlRequest = $doc->getElementsByTagName('request')->item(0);
            foreach ($request as $key => $val) {
                $newNode = $doc->createElement($key, $val);
                $xmlRequest->appendChild($newNode);
            }
            $strRequest = $doc->saveXML();

            // Send CURL request with specific headers
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->_config['payment']['tranPage']['url_transaction_processing']);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $strRequest);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml; charset=utf-8', 'Content-Length: ' . strlen($strRequest), 'SOAPAction: "http://equament.com/Schemas/Fmx/ssis/SSISProcessTransaction"'));

            $result = curl_exec($ch);

            // Check/parse result
            if ($result === false) {
                throw new Exception('Curl error: ' . curl_error($ch));
            }

            $result           = str_ireplace(array('SOAP-ENV:', 'SOAP:'), '', $result);
            $originalResponse = simplexml_load_string($result);
            if ($originalResponse === false) {
                throw new Exception('Incorrect response');
            }

            $result    = (string)$originalResponse->Body->SSISProcessTransactionResponse->SSISProcessTransactionResult;
            $oResponse = $originalResponse->Body->SSISProcessTransactionResponse->response;

            $response                      = new SSISSvcResponse();
            $response->ResultCode          = (string)$oResponse->ResultCode;
            $response->FinancialResultCode = (string)$oResponse->FinancialResultCode;
            $response->Amount              = (string)$oResponse->Amount;
            $response->Currency            = (string)$oResponse->Currency;
            $response->ApprovalCode        = (string)$oResponse->ApprovalCode;
            $response->Invoice             = (string)$oResponse->Invoice;
            $response->TranId              = $tranId;
            $response->HostReference       = (string)$oResponse->HostReference;
            $response->ErrorMessage        = (string)$oResponse->ErrorMessage;
            $response->AVSResult           = (string)$oResponse->AVSResult;
            $response->BatchId             = (string)$oResponse->BatchId;

//            $request = $this->_prepareRequest($amount, $currency, $invoice, $tranId, $cardNumber, $expiryMMYY, $verificationValue, $cardholderName, $address);
//            $response = new SSISSvcResponse();
//            $result = $this->getClient()->SSISProcessTransaction($request, $response);
        } catch (Exception $exception) {
            $this->_log->debugPaymentErrorToFile($exception->getMessage());

            return array(false, $this->_tr->translate('Internal Error'));
        }

        switch ($result) {
            case 'OK':
                return $this->processResponse($response, $originalResponse);
                break;

            case 'NotAcceptingRequests':
                $strError = $this->_tr->translate('Payment service is not accepting requests. Please try again later.');
                break;

            case 'UnabletoProcessInternalProblem':
                $strError = $this->_tr->translate('Internal error occurred in payment service. Please try again later.');
                break;

            case 'Timeout':
                $strError = $this->_tr->translate('Payment service has timed out trying to process the request. Please try again later.');
                break;

            default:
                $strError = $this->_tr->translate('Unknown response from the payment system.');
                $this->_log->debugPaymentErrorToFile($this->_tr->translate('Unknown response from TranPage: ') . $result);
                break;
        }

        $this->_log->debugPaymentErrorToFile(print_r($originalResponse, true));

        if (!$this->cancelTransaction($tranId, $strError)) {
            $strError = $this->_tr->translate('Internal error');
        }

        return array(false, $strError);
    }

    /**
     * Cancels transaction
     *
     * @param int $invoiceId ID of an invoice/transaction to cancel
     * @param string $errorMessage Error message
     * @return bool
     */
    public function cancelTransaction($invoiceId, $errorMessage)
    {
        $arrUpdate = array(
            'status'  => 'F',
            'message' => $errorMessage,
        );

        return $this->updateInvoice($invoiceId, $arrUpdate);
    }

    /**
     * Approves transaction
     *
     * @param int $invoiceId ID of an invoice/transaction to cancel
     * @param mixed $approvalCode FOR THE MOMENT ONLY GOD KNOWS WHAT THIS IS - NOT DOCUMENTED PARAM
     * @return bool
     */
    public function approveTransaction($invoiceId, $approvalCode)
    {
        $arrUpdate = array(
            'status'                 => 'C',
            'tranpage_approval_code' => $approvalCode,
        );

        return $this->updateInvoice($invoiceId, $arrUpdate);
    }

    /**
     * Create invoice record in DB
     *
     * @param int $memberId
     * @param int $companyTAId
     * @param float $invoiceAmount
     * @return int invoice id, empty on error
     */
    public function createInvoice($memberId, $companyTAId, $invoiceAmount)
    {
        try {
            $invoiceId = $this->_db2->insert(
                'u_payment_invoices',
                [
                    'member_id'     => $memberId,
                    'company_ta_id' => $companyTAId,
                    'amount'        => $invoiceAmount,
                    'invoice_date'  => date('c'),
                    'status'        => 'Q',
                ]
            );
        } catch (Exception $e) {
            $invoiceId = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $invoiceId;
    }

    /**
     * Update invoice record
     *
     * @param int $invoiceId
     * @param array $arrInvoiceInfo
     * @return bool true on success, otherwise false
     */
    public function updateInvoice($invoiceId, $arrInvoiceInfo)
    {
        $booSuccess = false;

        try {
            if (is_numeric($invoiceId) && !empty($invoiceId) && !empty($arrInvoiceInfo)) {
                $this->_db2->update('u_payment_invoices', $arrInvoiceInfo, ['u_payment_invoice_id' => (int)$invoiceId]);

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Load invoice details by id
     *
     * @param int $invoiceId
     * @return bool|array, false on error
     */
    public function getInvoiceById($invoiceId)
    {
        $arrInvoiceInfo = false;

        try {
            if (is_numeric($invoiceId) && !empty($invoiceId)) {
                $select = (new Select())
                    ->from('u_payment_invoices')
                    ->where(['u_payment_invoice_id' => (int)$invoiceId]);

                $arrInvoiceInfo = $this->_db2->fetchRow($select);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrInvoiceInfo;
    }
}
