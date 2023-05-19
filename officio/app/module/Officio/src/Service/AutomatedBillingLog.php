<?php

namespace Officio\Service;

use Exception;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\BaseService;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class AutomatedBillingLog extends BaseService
{

    /**
     * Load all log records related to session
     * Sample: Company ABC  $110.97  May 30, 2011  June 30, 2011   completed
     *
     * @param  int $intSessionId
     * @return array logs
     */
    public function loadSessionLogDetails($intSessionId)
    {
        $select = (new Select())
            ->from(array('l' => 'automated_billing_log'))
            ->join(array('s' => 'automated_billing_log_sessions'), 'l.log_session_id = s.log_session_id', 'log_session_date', Select::JOIN_LEFT_OUTER)
            ->join(array('i' => 'company_invoice'), 'l.log_invoice_id = i.company_invoice_id', array('invoice_date', 'company_id'), Select::JOIN_LEFT_OUTER)
            ->where(['l.log_session_id' => (int)$intSessionId]);

        return $this->_db2->fetchAll($select);
    }


    /**
     * Load all sessions
     * Group them by month and days
     *
     * @return array of all sessions
     */
    public function loadSessions()
    {
        $select = (new Select())
            ->from('automated_billing_log_sessions')
            ->group('log_session_date')
            ->order('log_session_date DESC');

        $arrSavedSessions = $this->_db2->fetchAll($select);

        $arrFilteredDates = array();
        foreach ($arrSavedSessions as $arrSessionInfo) {
            $time = strtotime($arrSessionInfo['log_session_date']);

            $arrFilteredDates[date('F Y', $time)][$arrSessionInfo['log_session_id']] = date('F d, H:i:s', $time);
        }

        $sessionRow  = 0;
        $arrSessions = array();

        /**
         * @var string $strSessionMonth
         * @var array $arrSessionInfo
         */
        foreach ($arrFilteredDates as $strSessionMonth => $arrSessionInfo) {
            // Collect all days
            $arrDays = array();
            foreach ($arrSessionInfo as $sessionId => $sessionDate) {
                $arrDays[] = array(
                    'session_id' => $sessionId,
                    'text'       => $sessionDate,
                    'leaf'       => true
                );
            }

            // Collect all months
            $arrSessions[] = array(
                'text'     => $strSessionMonth,
                'expanded' => $sessionRow == 0, // will be expanded only for first month
                'children' => $arrDays
            );
            $sessionRow++;
        }

        return $arrSessions;
    }


    /**
     * Save session details
     *
     * @param  array $arrSessions
     * @param int|string $sessionId
     * @return int $sessionId
     */
    public function saveSession($arrSessions, $sessionId = 0)
    {
        try {
            if (empty($sessionId)) {
                // Create session and save all related log rows
                $sessionId = $this->_db2->insert('automated_billing_log_sessions', ['log_session_date' => date('c')]);
            }

            foreach ($arrSessions as $arrSessionDetails) {
                $arrInsert = array(
                    'log_session_id'               => $sessionId,
                    'log_invoice_id'               => $arrSessionDetails['invoice_id'],
                    'log_retry'                    => $arrSessionDetails['retry'] ? 'Y' : 'N',
                    'log_company'                  => $arrSessionDetails['company_name'],
                    'log_company_show_dialog_date' => empty($arrSessionDetails['company_show_dialog_date']) ? null : $arrSessionDetails['company_show_dialog_date'],
                    'log_amount'                   => $arrSessionDetails['amount'],
                    'log_old_billing_date'         => empty($arrSessionDetails['old_billing_date']) ? null : $arrSessionDetails['old_billing_date'],
                    'log_new_billing_date'         => $arrSessionDetails['new_billing_date'],
                    'log_status'                   => $arrSessionDetails['status'],
                    'log_error_code'               => empty($arrSessionDetails['error_code']) ? null : $arrSessionDetails['error_code'],
                    'log_error_message'            => empty($arrSessionDetails['error_message']) ? null : $arrSessionDetails['error_message'],
                );

                $this->_db2->insert('automated_billing_log', $arrInsert);
            }
        } catch (Exception $e) {
            $sessionId = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $sessionId;
    }


    /**
     * Delete session by provided session id
     *
     * @param int $intSessionId
     * @return bool true on success
     */
    public function deleteSession($intSessionId)
    {
        $booSuccess = false;
        if (!empty($intSessionId)) {
            $this->_db2->delete('automated_billing_log', ['log_session_id' => $intSessionId]);

            $booSuccess = (bool)$this->_db2->delete('automated_billing_log_sessions', ['log_session_id' => $intSessionId]);
        }
        return $booSuccess;
    }
}
