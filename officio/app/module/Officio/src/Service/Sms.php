<?php

namespace Officio\Service;

use Clients\Service\Members;
use Exception;
use Laminas\Db\Sql\Select;
use Mailer\Service\Mailer;
use Officio\Common\Service\BaseService;
use Twilio\Rest\Client;

class Sms extends BaseService
{

    /** @var Members */
    protected $_members;

    /** @var Mailer */
    protected $_mailer;

    public function initAdditionalServices(array $services)
    {
        $this->_members = $services[Members::class];
        $this->_mailer  = $services[Mailer::class];
    }

    public function getSmsQueue($smsId = 0)
    {
        $arrWhere = [];

        if (!empty($smsId)) {
            $arrWhere['id'] = (int)$smsId;
        }

        $select = (new Select())
            ->from('u_sms')
            ->where($arrWhere);

        return empty($smsId) ? $this->_db2->fetchAll($select) : $this->_db2->fetchRow($select);
    }

    public function send()
    {
        try {
            $arrSmsToSend = $this->getSmsQueue();
            foreach ($arrSmsToSend as $arrSmsInfo) {
                $tel = $arrSmsInfo['number'];
                $msg = $arrSmsInfo['message'];

                // remove all symbols except numbers
                if ($tel) {
                    $tel        = preg_replace('/[^0-9+]/', '', $tel);
                    $accountSid = $this->_config['sms']['sid'];
                    $authToken  = $this->_config['sms']['token'];
                    $client     = new Client($accountSid, $authToken);
                }

                if (!$tel) {
                    $strStatus  = 'warning';
                    $strMessage = 'incorrect phone number';
                } else {
                    $success = $client->messages->create(
                        $tel,
                        array(
                            'from' => $this->_config['sms']['twilio_number'],
                            'body' => $msg
                        )
                    );
                    if ($success) {
                        $strStatus  = 'ok';
                        $strMessage = 'sms was sent to ' . $tel;
                    } else {
                        $strStatus  = 'error';
                        $strMessage = 'sms was NOT sent to ' . $tel;
                    }

                    if ($success || ($arrSmsInfo['attempts'] == $this->_config['sms']['retry_count'] - 1)) {
                        $this->_db2->delete('u_sms', ['id' => $arrSmsInfo['id']]);
                    } else {
                        $this->_db2->update(
                            'u_sms',
                            ['attempts' => $arrSmsInfo['attempts'] + 1],
                            ['id' => $arrSmsInfo['id']]
                        );
                    }

                    // notify user via mail, if failed
                    if (!$success && empty($arrSmsInfo['attempts'])) {
                        // ne uspeh :(
                        $mail_params = array(
                            'email'   => $arrSmsInfo['email'],
                            'subject' => 'SMS sending failed',
                            'message' => 'We tried to send you next SMS, but an error occurred in the system: <br><br>' . $arrSmsInfo['message'],
                        );
                        $senderInfo  = $this->_members->getMemberInfo();
                        $this->_mailer->send($mail_params, array(), $senderInfo, false);
                    }
                }

                $this->_log->saveToCronLog($strStatus . ': ' . $strMessage);
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

}
