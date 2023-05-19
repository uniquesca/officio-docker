<?php

namespace Clients\Service;

use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class ClientsReferrals extends BaseService
{
    /**
     * Get client referrals info by ids
     *
     * @param int|array $clientReferralIds
     * @return array
     */
    public function getClientReferralsByIds($clientReferralIds)
    {
        $select = (new Select())
            ->from(array('r' => 'client_referrals'))
            ->where(['r.referral_id' => $clientReferralIds]);

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load list of client referral records for the member
     *
     * @param int $memberId
     * @return array
     */
    public function getClientReferralsRecords($memberId)
    {
        $select = (new Select())
            ->from(array('r' => 'client_referrals'))
            ->join(array('cp' => 'company_prospects'), 'cp.prospect_id = r.prospect_id', ['prospect_first_name' => 'fName', 'prospect_last_name' => 'lName'], Select::JOIN_LEFT)
            ->where(['r.member_id' => (int)$memberId]);

        return $this->_db2->fetchAll($select);
    }

    public function saveClientReferral($referralId, $arrReferralInfo)
    {
        if (empty($referralId)) {
            $this->_db2->insert(
                'client_referrals',
                $arrReferralInfo
            );
        } else {
            $this->_db2->update(
                'client_referrals',
                $arrReferralInfo,
                ['referral_id' => $referralId]
            );
        }
    }

    /**
     * Remove client referrals by provided ids
     *
     * @param int|array $clientReferralIds
     * @return int
     */
    public function removeClientReferrals($clientReferralIds)
    {
        return $this->_db2->delete('client_referrals', ['referral_id' => $clientReferralIds]);
    }

    /**
     * Get the list of saved Compensation Agreements for a specific company
     *
     * @param int $companyId
     * @return array
     */
    public function getCompanyCompensationAgreements($companyId)
    {
        $select = (new Select())
            ->from(array('r' => 'client_referrals'))
            ->columns(['referral_compensation_arrangement'])
            ->join(array('m' => 'members'), 'm.member_id = r.member_id', [])
            ->where(['m.company_id' => (int)$companyId])
            ->group('referral_compensation_arrangement')
            ->order('referral_compensation_arrangement');

        return $this->_db2->fetchCol($select);
    }

}
