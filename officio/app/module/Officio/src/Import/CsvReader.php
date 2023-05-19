<?php

namespace Officio\Import;

class CsvReader
{
    public $cell;
    public $rows;

    public function __construct()
    {
        $this->cell = array();
        $this->rows = 0;
    }

    public function read($file, $delimiter = ";")
    {
        //set varibles
        $this->rows = 0;
        $handle     = fopen($file, "r");
        $csv_rows   = array();

        //read csv file
        while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
            if (!empty($data)) {
                foreach ($data as &$val) {
                    $val = trim($val);
                }
                $csv_rows[] = $data;
                $this->rows++;
            }
        }
        fclose($handle);

        $this->cell = $csv_rows;
    }
}
