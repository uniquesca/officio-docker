<?php

namespace Officio\Import;

class CsvInterface
{

    public function readCSV($file)
    {
        $data = new CsvReader();
        $data->read($file);
        $rxls = array();

        for ($j = 0; $j < $data->rows; $j++) {
            $rxls[$j]['id']          = $j + 1;
            $rxls[$j]['date']        = strtotime($data->cell[$j][0]);
            $rxls[$j]['description'] = $data->cell[$j][1];
            $rxls[$j]['debit']       = $data->cell[$j][2];
            $rxls[$j]['credit']      = $data->cell[$j][3];
            $rxls[$j]['balance']     = $data->cell[$j][4];
            $rxls[$j]['type']        = (empty($rxls[$j]['debit']) ? 'CREDIT' : 'DEBIT');
        }

        return $rxls;
    }

}

