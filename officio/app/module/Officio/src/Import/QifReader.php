<?php

namespace Officio\Import;

class QifReader
{
    public $rows;
    public $data;
    public $balance;
    public $ledgerbalance;
    public $dtstart;
    public $dtend;
    public $bankid;

    public function __construct()
    {
        $this->rows          = 0;
        $this->balance       = 0.00;
        $this->ledgerbalance = 0.00;
        $this->data          = array();
        $this->dtstart       = '';
        $this->dtend         = '';
    }

    public function read($file)
    {
        $lines = file($file);

        $record = array();
        $end    = 0;

        foreach ($lines as $line) {
            /*
            For each line in the QIF file we will loop through it
            */

            $line = trim($line ?? '');

            if ($line === "^") {
                /*
                We have found a ^ which indicates the end of a transaction
                we now set $end as true to add this record array to the master records array
                */

                $end = 1;
            } elseif (preg_match("#^!Type:(.*)$#", $line, $match)) {
                /*
                This will be matched for the first line.
                You can get the type by using - $type = trim($match[1]);
                We dont want to do anything with the first line so we will just ignore it
                */
            } else {
                switch (substr($line, 0, 1)) {
                    case 'D':
                        /*
                        Date. Leading zeroes on month and day can be skipped. Year can be either 4 digits or 2 digits or '6 (=2006).
                        */
                        $record['date'] = trim(substr($line, 1));
                        break;
                    case 'T':
                        /*
                        Amount of the item. For payments, a leading minus sign is required.
                        */
                        $record['amount'] = trim(substr($line, 1));
                        break;
                    case 'P':
                        /*
                        Payee. Or a description for deposits, transfers, etc.
                        */
                        $line                  = htmlentities($line);
                        $line                  = str_replace("  ", "", $line);
                        $record['description'] = trim(substr($line, 1));
                        break;
                    case 'N':
                        /*
                        Investment Action (Buy, Sell, etc.).
                        */
                        $record['investment'] = trim(substr($line, 1));
                        break;
                }
            }


            if ($end == 1) {
                // We have reached the end of a transaction so add the record to the master records array
                $this->data[] = $record;
                $this->rows++;

                // Rest the $record array
                $record = array();

                // Set $end to false so that we can now build the new record for the next transaction
                $end = 0;
            }
        }

        $this->dtstart = $this->data[0]['date'];
        $this->dtend   = $this->data[$this->rows - 1]['date'];
    }
}
