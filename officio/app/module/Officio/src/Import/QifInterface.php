<?php

namespace Officio\Import;

use Clients\Service\Members;
use Officio\Comms\Service\Mailer;
use Officio\Service\Company;
use Officio\Common\Service\Settings;

class QifInterface
{

    /** @var Mailer */
    protected $_mailer;

    /** @var Company */
    protected $_company;

    /** @var Members */
    protected $_members;

    public function __construct(Mailer $mailer, Company $company, Members $members)
    {
        $this->_mailer  = $mailer;
        $this->_company = $company;
        $this->_members = $members;
    }

    /**
     * Parse received qif file and return collected info
     *
     * @param $file
     * @param $attr
     * @return array|string
     */
    public function readQIF($file, &$attr)
    {
        $strError = '';
        $data     = new QifReader();
        $data->read($file);

        $balance    = $data->balance;
        $arrRecords = array();

        for ($j = 0; $j < $data->rows; $j++) {
            $amount = $data->data[$j]['amount'];

            $arrRecords[$j]['id']          = $j + 1;
            $arrRecords[$j]['date']        = strtotime(substr($data->data[$j]['date'], 3, 2) . '/' . substr($data->data[$j]['date'], 0, 2) . '/' . substr($data->data[$j]['date'], 6));
            $arrRecords[$j]['description'] = $data->data[$j]['description'];

            if ($amount > 0) {
                $arrRecords[$j]['credit'] = $amount;
                $arrRecords[$j]['type']   = 'CREDIT';
            } else {
                $arrRecords[$j]['debit'] = abs($amount);
                $arrRecords[$j]['type']  = 'DEBIT';
            }
        }

        // Send email with details
        // TODO Fix this as $strError is always empty here
        if (!empty($strError)) {
            $arrCurrentMemberInfo = $this->_members->getMemberInfo();
            $arrCompanyInfo       = $this->_company->getCompanyInfo($arrCurrentMemberInfo['company_id']);

            $strHtmlError = $strError;
            $strHtmlError .= '<h2>Transaction Info:</h2><pre>' . print_r($data->data[$j], true) . '</pre>';
            $strHtmlError .= '<h2>Company:</h2>' . $arrCompanyInfo['companyName'];
            $strHtmlError .= '<h2>User:</h2>' . $arrCurrentMemberInfo['full_name'];

            $this->_mailer->sendEmailToSupport('Error during importing file', $strHtmlError, null, null, null, null, true, array($file));
        }

        if (empty($strError) && $data->rows == 0 && $data->balance == $data->ledgerbalance) {
            $strError = 'There are no records to import';
        }

        if (!empty($strError)) {
            // Disallow importing
            return $strError;
        } else {
            if ($arrRecords[0]['date'] > $arrRecords[count($arrRecords) - 1]['date']) {
                // Sort the data by date because records are in reverse order
                foreach ($arrRecords as $key => $row) {
                    $arrDate[$key] = $row['date'];
                    $arrIds[$key]  = $row['id'];
                }
                array_multisort($arrDate, SORT_ASC, $arrIds, SORT_DESC, $arrRecords);
            }

            // Calculate balance for each transaction
            for ($j = 0; $j < $data->rows; $j++) {
                if ($j != 0) {
                    $balance = $balance - $arrRecords[$j - 1]['credit'] + $arrRecords[$j - 1]['debit'];
                }
                $arrRecords[$j]['balance'] = $balance;
            }

            $attr = array(
                'dtstart' => strtotime($data->dtstart),
                'dtend'   => strtotime($data->dtend),
                'dbankid' => $data->bankid
            );

            return $arrRecords;
        }
    }
}