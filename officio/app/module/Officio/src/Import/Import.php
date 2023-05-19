<?php

namespace Officio\Import;

use Clients\Service\Members;
use Officio\Comms\Service\Mailer;
use Officio\Service\Company;
use Officio\Common\Service\Settings;

class Import
{

    /** @var Settings */
    protected $_settings;

    /** @var Company */
    protected $_company;

    /** @var Members */
    protected $_members;

    /** @var Mailer */
    protected $_mailer;

    public function __construct(Settings $settings, Mailer $mailer, Company $company, Members $members)
    {
        $this->_settings = $settings;
        $this->_mailer   = $mailer;
        $this->_company  = $company;
        $this->_members  = $members;
    }

    //get start and end date
    public function getAttr($rxls)
    {
        $dtstart = $dtend = $rxls[0]['date'];
        foreach ($rxls as $r) {
            if ($r['date'] < $dtstart) {
                $dtstart = $r['date'];
            } else {
                if ($r['date'] > $dtend) {
                    $dtend = $r['date'];
                }
            }
        }

        return array('dtstart' => $dtstart, 'dtend' => $dtend);
    }


    public function returnError($msg)
    {
        exit("<div class='error'>$msg</div>");
    }

    /*
    The function of reader data bank transaction formats XLS, CSV, QBO
    Input: $file - path to xls/csv/qbo file
    Output: array with readed data
    */
    public function importFile($file, &$attr = '')
    {
        if (!is_file($file)) {
            $this->returnError('The file ' . $file . ' does not exist.');
        }
        if (filesize($file) == 0) {
            $this->returnError('The file ' . $file . ' is empty.');
        }

        $data = array();

        $extension = strtolower(trim(strrchr($file, ".")));
        switch ($extension) {
            case '.xls' :
                $xlsIntercace = new XlsInterface();
                $data         = @$xlsIntercace->readXLS($file);
                $attr         = $this->getAttr($data);
                break;

            case '.csv' :
                $csvInterface = new CsvInterface();
                $data         = @$csvInterface->readCSV($file);
                $attr         = $this->getAttr($data);
                break;

            case '.qbo' :
            case '.qfx' :
            case '.ofx' :
            $qboInterface = new QboInterface($this->_mailer, $this->_company, $this->_members);
            $data         = @$qboInterface->readQBO($file, $attr);
                break;

            case '.qif' :
                $qifInterface = new QifInterface($this->_mailer, $this->_company, $this->_members);
                $data         = @$qifInterface->readQIF($file, $attr);
                break;

            default :
                $this->returnError('Unknown file format!');
        }

        if (is_array($data)) {
            if (count($data) == 0) {
                $this->returnError('Cannot read the file');
            }
        } else {
            $this->returnError($data);
        }

        return $data;
    }

}