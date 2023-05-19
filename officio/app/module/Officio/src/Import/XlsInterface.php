<?php

namespace Officio\Import;

class XlsInterface
{

    public function readXLS($file)
    {
        $data = new SpreadsheetExcelReader();
        $data->read($file);
        $rxls = array();
        for ($j = 0; $j < $data->sheets[0]['numRows']; $j++) {
            $rxls[$j]['id']          = $j + 1;
            $rxls[$j]['date']        = strtotime($data->sheets[0]['cells'][$j + 1][1]);
            $rxls[$j]['description'] = $data->sheets[0]['cells'][$j + 1][2];
            $rxls[$j]['debit']       = $data->sheets[0]['cells'][$j + 1][3];
            $rxls[$j]['credit']      = $data->sheets[0]['cells'][$j + 1][4];
            $rxls[$j]['balance']     = $data->sheets[0]['cells'][$j + 1][5];
            $rxls[$j]['type']        = (empty($rxls[$j]['debit']) ? 'CREDIT' : 'DEBIT');
        }

        return $rxls;
    }
}