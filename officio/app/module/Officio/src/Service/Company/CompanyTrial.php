<?php

namespace Officio\Service\Company;


use Exception;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;
use Officio\Service\Company;
use Officio\Common\Service\Settings;
use Officio\Common\SubServiceInterface;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class CompanyTrial extends BaseService implements SubServiceInterface
{

    /** @var Company */
    private $_parent;

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent() {
        return $this->_parent;
    }

    /**
     * Parse trial key
     *
     * @param  string $trialKey in format:
     * 1. XXXnnnnn (a 3-digit character followed by a X-digit number),
     *    nnnnn is the number of days since 1970/01/01
     * 2. XXX7271 to XXX7276
     * @return array - empty, if key was not parsed, otherwise filled array
     */
    public function parseTrialKey($trialKey)
    {
        $arrResult = array();
        try {
            $trialKey = trim($trialKey);
            if (preg_match('/^([a-zA-Z]{3})([0-9]{4,5})$/i', $trialKey, $regs)) {
                $regId  = $regs[1];
                $number = $regs[2];

                if (preg_match('/^727([1-6])$/i', $number, $regs2)) {
                    // Second format
                    $checkTime = strtotime(sprintf('+%d month', $regs2[1]));
                } else {
                    // First format
                    $checkTime = $number * 24 * 60 * 60;
                }

                if (!empty($checkTime)) {
                    $now     = mktime(0, 0, 0, (int)date('m'), (int)date('d'), (int)date('Y'));
                    $maxTime = strtotime('+2 years');

                    // Between now and 2 years
                    if ($checkTime >= $now && $checkTime <= $maxTime) {
                        $arrResult = array(
                            'regId'   => $regId,
                            'expDate' => date('c', $checkTime)
                        );
                    }
                }
            }
        } catch (Exception $e) {
            $arrResult = array();
        }

        return $arrResult;
    }


    /**
     * Check if current user already registered X companies
     *
     * @return bool true if key can be used
     */
    private function _canCurrentUserProceed()
    {
        $select = (new Select())
            ->from(array('t' => 'company_trial'))
            ->columns(['used_count' => new Expression('COUNT(*)')])
            ->where(['t.ip' => Settings::getCurrentIp()]);

        $usedCount = $this->_db2->fetchOne($select);

        return ($usedCount < 5);
    }


    /**
     * Check if trial key correct and return error message if it is not
     *
     * @param  string $strTrialKey
     * @return string (empty on success, otherwise with error details)
     */
    public function checkKeyCorrect($strTrialKey)
    {
        $strMessage = '';

        // Check if jey is correct
        $arrParsedKey = $this->parseTrialKey($strTrialKey);
        if (!count($arrParsedKey)) {
            $arrEmailSettings = $this->_settings->getOfficioSupportEmail();
            $strMessage       = $this->_tr->translate(
                'We could not locate you as a registered member.' .
                ' This could be because you typed your Key incorrectly,' .
                ' or we may not have received your membership confirmation yet.<br/><br/>' .
                'If you need assistance, please email us at: ' . $arrEmailSettings['email']
            );
        }

        // Check if this user created X times companies
        if (empty($strMessage) && !$this->_canCurrentUserProceed()) {
            $strMessage = $this->_tr->translate('Your trial account privileges have already been used.');
        }

        return $strMessage;
    }


    /**
     * Save trial key as used (with company id, date and ip)
     *
     * @param  string $strTrialKey
     * @param  int $companyId
     * @return bool true on success, otherwise false
     */
    public function saveTrialKey($strTrialKey, $companyId)
    {
        try {
            $arrInsert = array(
                'company_id' => (int)$companyId,
                'key'        => $strTrialKey,
                'ip'         => Settings::getCurrentIp(),
                'date_used'  => date('c')
            );

            $this->_db2->insert('company_trial', $arrInsert);

            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            $booSuccess = false;
        }

        return $booSuccess;
    }
}
