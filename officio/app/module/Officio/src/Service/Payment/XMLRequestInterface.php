<?php
namespace Officio\Service\Payment;

interface XMLRequestInterface
{

    public function createProfileRequest($arrProfileInfo);

    public function updateProfileRequest($arrProfileInfo);

    public function readProfileRequest($customerRefNum);

    public function deleteProfileRequest($customerRefNum);

    public function chargeAmountRequest($arrOrderInfo);

    public function chargeAmountBasedOnProfileRequest ($arrOrderInfo);
}
