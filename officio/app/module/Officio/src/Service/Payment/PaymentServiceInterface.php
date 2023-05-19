<?php

namespace Officio\Service\Payment;

use Officio\Common\Service\Log;

interface PaymentServiceInterface
{

    public function __construct(array $config, Log $log);

    public function init();

    public function generatePaymentProfileId($id, $booProspect = true);

    public function generatePaymentOrderId($orderId);

    public function generatePaymentTraceNumber();

    public function createProfile($arrProfileInfo);

    public function updateProfile($arrProfileInfo);

    public function readProfile($customerRefNum);

    public function deleteProfile($customerRefNum);

    public function chargeAmount($arrOrderInfo);

    public function chargeAmountBasedOnProfile($arrOrderInfo);

}