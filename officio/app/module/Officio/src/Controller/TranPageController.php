<?php

namespace Officio\Controller;

use Clients\Service\Clients;
use Exception;
use Laminas\Filter\StripTags;
use Laminas\View\Model\JsonModel;
use Officio\BaseController;
use Officio\Common\Service\Encryption;
use Officio\Service\Company;
use Officio\Service\Payment\Stripe;
use Officio\Service\Payment\TranPage;
use Officio\Service\SystemTriggers;

/**
 * Communication with TranPage payment system
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class TranPageController extends BaseController
{

    /** @var TranPage */
    private $_tranPage;

    /** @var Stripe */
    protected $_stripe;

    /** @var Clients */
    protected $_clients;

    /** @var Company */
    protected $_company;

    /** @var SystemTriggers */
    protected $_triggers;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_clients = $services[Clients::class];
        $this->_triggers = $services[SystemTriggers::class];
        $this->_tranPage = $services[TranPage::class];
        $this->_stripe = $services[Stripe::class];
    }

    public function processPaymentAction()
    {
        $view = new JsonModel();

        $strError = '';

        try {
            $companyId       = $this->_auth->getCurrentUserCompanyId();
            $currentMemberId = $this->_auth->getCurrentUserId();
            $memberId        = (int)$this->findParam('member_id');
            $companyTAId     = (int)$this->findParam('ta_id');
            $invoiceAmount   = (double)$this->findParam('amount');

            $filter             = new StripTags();
            $creditCardName     = trim($filter->filter($this->findParam('cc_name', '')));
            $creditCardNum      = $filter->filter($this->findParam('cc_num'));
            $creditCardCVN      = $filter->filter($this->findParam('cc_cvn'));
            $creditCardExpMonth = $filter->filter($this->findParam('cc_month'));
            $creditCardExpYear  = $filter->filter($this->findParam('cc_year'));
            $creditCardExpDate  = $creditCardExpMonth . '/' . $creditCardExpYear;

            // NOTE: temporary disable
            $strError = $this->_tr->translate('We are undergoing system maintenance.<br>This feature is not available at this time.<br>To prevent any delays, you can continue to submit your application, by clicking on <b>Submit to CBIU Dominica</b> button.');

            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $caseId     = 0;
            $clientName = '';
            if (empty($strError)) {
                if ($this->_members->isMemberCaseById($memberId)) {
                    // Get the parent of the case
                    $caseId     = $memberId;
                    $arrParents = $this->_clients->getParentsForAssignedApplicants(array($caseId));
                    $memberId   = $arrParents[$caseId]['parent_member_id'] ?? 0;
                } else {
                    // Get the first assigned case
                    $arrCases = $this->_clients->getAssignedCases($memberId);
                    $caseId   = count($arrCases) ? $arrCases[0] : 0;
                }

                $arrClientInfo = $this->_clients->getClientShortInfo($memberId);
                $clientName    = $arrClientInfo['full_name_with_file_num'] ?? 'Not recognized client with id #' . $memberId;
            }

            // Check incoming CC info
            if (empty($strError)) {
                $strError = $this->_clients->getAccounting()->checkCCInfo($companyId, $caseId, $creditCardName, $creditCardNum, $creditCardExpDate, $creditCardCVN, false);
            }

            // Check if current user has access to this T/A
            if (empty($strError) && (empty($companyTAId) || (!$this->_clients->hasCurrentMemberAccessToTA($companyTAId)))) {
                $strError = $this->_tr->translate('Insufficient access rights for this ') . $this->_company->getCurrentCompanyDefaultLabel('trust_account');
            }

            // Check if amount is correct
            if (empty($strError) && (!is_numeric($invoiceAmount) || empty($invoiceAmount))) {
                $strError = $this->_tr->translate('Incorrect amount.');
            }

            if (empty($strError)) {
                $arrAccountingResult = $this->_clients->getAccounting()->getClientsTransactionsInfoPaged($caseId, $companyTAId, 0, 1);
                if ($arrAccountingResult['balance'] <= 0) {
                    $strError = $this->_tr->translate('Balance is less or equal to 0.');
                }

                if (empty($strError) && $invoiceAmount > $arrAccountingResult['balance']) {
                    $strError = $this->_tr->translate('Amount cannot be more than balance.');
                }
            }

            // If everything is ok - create new invoice
            if (empty($strError)) {
                $invoiceId = $this->_tranPage->createInvoice($caseId, $companyTAId, $invoiceAmount);
                if (empty($invoiceId)) {
                    $strError = $this->_tr->translate('Internal error');
                } else {
                    $booUseTranPage     = false; // TODO: switch to TranPage when ready
                    $paymentDescription = 'TranPage';

                    if ($booUseTranPage) {
                        list($booSuccess, $strResult) = $this->_tranPage->processTransaction(
                            $invoiceAmount,
                            $this->_config['payment']['tranPage']['currency'],
                            'Invoice' . $invoiceId,
                            $invoiceId,
                            $creditCardNum,
                            $creditCardExpMonth . $creditCardExpYear,
                            $creditCardCVN,
                            $creditCardName,
                            ''
                        );
                    } else {
                        if (!empty($this->_config['payment']['stripe']['enabled'])) {
                            list ($strResult, $transactionId) = $this->_stripe->payWithCard(
                                $paymentDescription . ' (' . $clientName . ')',
                                $caseId,
                                $invoiceAmount,
                                $creditCardNum,
                                $creditCardExpMonth,
                                $creditCardExpYear,
                                $creditCardCVN
                            );

                            if (empty($strResult) && empty($transactionId)) {
                                // Something went wrong
                                $strResult = $this->_tr->translate('Internal error. Incorrect transaction id.');
                            }

                            // Check again if that transaction was successful
                            if (empty($strResult) && !$this->_stripe->checkTransactionCompletedSuccessfully($transactionId)) {
                                // Something is wrong
                                $strResult = $this->_tr->translate('Internal error. Transaction not found.');
                            }

                            $booSuccess = empty($strResult);
                            if ($booSuccess) {
                                // Use transaction id, so we can map this fee due with payment done on payment system
                                $strResult = $transactionId;
                            }
                        } else {
                            try {
                                $this->_db2->insert(
                                    'cc_tmp',
                                    [
                                        'case_id'     => $caseId,
                                        'name'        => $this->_encryption->encode($clientName),
                                        'number'      => $this->_encryption->encode($creditCardNum),
                                        'exp_month'   => $this->_encryption->encode($creditCardExpMonth),
                                        'exp_year'    => $this->_encryption->encode($creditCardExpYear),
                                        'amount'      => $invoiceAmount,
                                        'description' => $this->_encryption->encode($paymentDescription)
                                    ]
                                );

                                $booSuccess = true;
                                $strResult = '';
                            } catch (Exception $e) {
                                $booSuccess = false;
                                $strResult  = $this->_tr->translate('Internal error.');

                                $this->_log->debugErrorToFile(
                                    $e->getFile() . '@' . $e->getLine() . ': ' .
                                    $e->getMessage(),
                                    $e->getTraceAsString()
                                );
                            }
                        }
                    }

                    $strError = $booSuccess ? '' : $strResult;

                    if (empty($strError)) {
                        // Create record in our FT table
                        $gst           = 0;
                        $gstProvinceId = 0;
                        $gstTaxLabel   = '';

                        $this->_clients->getAccounting()->addFee(
                            $companyTAId,
                            $caseId,
                            $invoiceAmount,
                            $paymentDescription . ' (' . $clientName . ')',
                            'add-fee-received',
                            date('c'),
                            '',
                            $gst,
                            $gstProvinceId,
                            $gstTaxLabel,
                            sprintf(
                                $this->_tr->translate('Fee received via CC %s exp %s/%s'),
                                $this->_settings->maskCreditCardNumber($creditCardNum),
                                $creditCardExpMonth,
                                substr(date('Y'), 0, 2) . $creditCardExpYear
                            ),
                            $currentMemberId,
                            true,
                            $strResult
                        );

                        if ($this->_auth->isCurrentUserAuthorizedAgent()) {
                            $arrParents  = $this->_clients->getParentsForAssignedApplicants(array($caseId));
                            $applicantId = $arrParents[$caseId]['parent_member_id'] ?? 0;

                            if ($this->_company->getCompanyDivisions()->isClientSubmittedToGovernment($applicantId)) {
                                $this->_triggers->triggerTranpagePaymentReceived(
                                    $this->_auth->getCurrentUserCompanyId(),
                                    $caseId
                                );
                            }
                        }

                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );
        return $view->setVariables($arrResult);
    }
}