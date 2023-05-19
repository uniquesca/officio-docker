<?php

namespace Officio\Import;

use Clients\Service\Members;
use Officio\Comms\Service\Mailer;
use Officio\Service\Company;
use Officio\Common\Service\Settings;

class QboInterface
{

    /** @var Company */
    protected $_company;

    /** @var Members */
    protected $_members;

    /** @var Mailer */
    protected $_mailer;

    public function __construct(Mailer $mailer, Company $company, Members $members)
    {
        $this->_company  = $company;
        $this->_members  = $members;
        $this->_mailer   = $mailer;
    }

    /**
     * Parse received qbo file and return collected info
     *
     * @param $file
     * @param $attr
     * @return array|string
     */
    public function readQBO($file, &$attr)
    {
        $strError = '';
        $data     = new QboReader();
        $data->read($file);

        // Use ledger balance if it exists, otherwise use available balance
        if (empty($data->ledgerbalance)) {
            $balance = $data->balance;

            $arrCurrentMemberInfo = $this->_members->getMemberInfo();
            $arrCompanyInfo       = $this->_company->getCompanyInfo($arrCurrentMemberInfo['company_id']);
            $strHtmlError         = '<h2>Ledger balance is not available in import file</h2>';
            $strHtmlError         .= '<h2>Company:</h2>' . $arrCompanyInfo['companyName'];
            $strHtmlError         .= '<h2>User:</h2>' . $arrCurrentMemberInfo['full_name'];
            $this->_mailer->sendEmailToSupport('Ledger balance is not available in import file', $strHtmlError, null, null, null, null, true, array($file));
        } else {
            $balance = $data->ledgerbalance;
        }

        $arrRecords = array();

        for ($j = 0; $j < $data->rows; $j++) {
            $amount = $data->data[$j]['amount'];
            $type   = $data->data[$j]['type'];

            $arrKnownTypes = array(
                array('type_id' => 'CREDIT', 'amount' => 'positive'),
                array('type_id' => 'DEBIT', 'amount' => 'negative'),
                array('type_id' => 'CHECK', 'amount' => 'negative'),
                array('type_id' => 'ATM', 'amount' => 'unknown')
            );

            foreach ($arrKnownTypes as $typeInfo) {
                if ($type == $typeInfo['type_id']) {
                    switch ($typeInfo['amount']) {
                        case 'positive':
                            if ($amount < 0) {
                                $strError = 'Incorrect amount sign (must be positive) for transaction in file.';
                            }
                            break;

                        case 'negative':
                            if ($amount > 0) {
                                $strError = 'Incorrect amount sign (must be negative) for transaction in file.';
                            }
                            break;

                        default:
                            break;
                    }

                    break;
                }
            }

            if (!empty($strError)) {
                break;
            } else {
                $arrRecords[$j]['id'] = $j + 1;
                // Date in format: 20080922020000[-5:EST] will be trimmed and received here as 20080922
                $arrRecords[$j]['date']        = strtotime(substr($data->data[$j]['date'], 4, 2) . '/' . substr($data->data[$j]['date'], 6) . '/' . substr($data->data[$j]['date'], 0, 4));
                $arrRecords[$j]['description'] = $data->data[$j]['description'];

                if ($amount > 0) {
                    $arrRecords[$j]['credit'] = $amount;
                } else {
                    $arrRecords[$j]['debit'] = abs($amount);
                }

                $arrRecords[$j]['type'] = $data->data[$j]['type'];
                $arrRecords[$j]['fit']  = $data->data[$j]['fit'];
            }
        }

        // Send email with details
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
            for ($j = $data->rows - 1; $j >= 0; $j--) {
                $balance                   = $balance - $arrRecords[$j]['credit'] + $arrRecords[$j]['debit'];
                $arrRecords[$j]['balance'] = $balance;
            }

            $attr = array(
                'dtstart' => strtotime(substr($data->dtstart, 0, 4) . '/' . substr($data->dtstart, 4, 2) . '/' . substr($data->dtstart, 6, 2)),
                'dtend'   => strtotime(substr($data->dtend, 0, 4) . '/' . substr($data->dtend, 4, 2) . '/' . substr($data->dtend, 6, 2)),
                'dbankid' => $data->bankid
            );

            return $arrRecords;
        }
    }
}