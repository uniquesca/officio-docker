<?php

namespace Officio\Import;

class QboReader
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
        $qbofile = implode('', file($file));

        // Remove new lines + spaces
        $qbofile = preg_replace("/\r?\n(\s*)</", "\n<", $qbofile);
        $qbofile = preg_replace("/\r?\n/", '', $qbofile);

        $stmtrn = array();
        preg_match_all("/<STMTTRN>(.+)<\/STMTTRN>/iU", $qbofile, $out);
        if (isset($out[1]) && !empty($out[1])) {
            $stmtrn = $out[1];
        }

        //search transactions
        if (!empty($stmtrn)) {
            foreach ($stmtrn as $line) {
                if ($line) {
                    $line = trim($line) . '<';

                    preg_match("/<DTPOSTED>(.+)</iU", $line, $out);
                    if (trim($out[1])) {
                        $this->data[$this->rows]['date'] = substr(trim($out[1] ?? ''), 0, 8);
                    }

                    preg_match("/<TRNTYPE>(.*)</iU", $line, $out);
                    if (trim($out[1] ?? '')) {
                        $this->data[$this->rows]['type'] = trim($out[1] ?? '');
                    }

                    preg_match("/<TRNAMT>([-]?\s*[0-9.]*)</iU", $line, $out);
                    if (trim($out[1] ?? '')) {
                        $this->data[$this->rows]['amount'] = str_replace(' ', '', $out[1]);
                    }

                    preg_match("/<NAME>(.*)</iU", $line, $out);
                    if (trim($out[1] ?? '')) {
                        $this->data[$this->rows]['description'] = trim($out[1] ?? '');
                    }

                    preg_match("/<MEMO>(.*)</iU", $line, $out);
                    if (trim($out[1] ?? '')) {
                        if (isset($this->data[$this->rows]['description']) && strlen($this->data[$this->rows]['description'] ?? '')) {
                            $this->data[$this->rows]['description'] .= '/' . trim($out[1] ?? '');
                        } else {
                            $this->data[$this->rows]['description'] = trim($out[1] ?? '');
                        }
                    }

                    preg_match("/<FITID>(.*)</iU", $line, $out);
                    if (trim($out[1] ?? '')) {
                        $this->data[$this->rows]['fit'] = trim($out[1] ?? '');
                    }

                    ++$this->rows;
                }
            }
        }

        if ($this->balance == 0) {
            preg_match("/<AVAILBAL>(.+)<\/AVAILBAL>/iU", $qbofile, $out);
            if (trim($out[1] ?? '')) {
                preg_match("/<BALAMT>([0-9.]*)</iU", $out[1] . '<', $out);
                if (trim($out[1] ?? '')) {
                    $this->balance = trim($out[1] ?? '');
                }
            }
        }

        if ($this->ledgerbalance == 0) {
            preg_match("/<LEDGERBAL>(.+)<\/LEDGERBAL>/iU", $qbofile, $out);
            if (trim($out[1] ?? '')) {
                preg_match("/<BALAMT>([0-9.\-]*)</iU", $out[1] . '<', $out);
                if (trim($out[1] ?? '')) {
                    $this->ledgerbalance = trim($out[1] ?? '');
                }
            }
        }

        if ($this->dtstart == '') {
            preg_match("/<DTSTART>(.*)</iU", $qbofile, $out);
            if (trim($out[1] ?? '')) {
                $this->dtstart = substr(trim($out[1] ?? ''), 0, 8);
            }
        }

        if ($this->dtend == '') {
            preg_match("/<DTEND>(.*)</iU", $qbofile, $out);
            if (trim($out[1] ?? '')) {
                $this->dtend = substr(trim($out[1] ?? ''), 0, 8);
            }
        }

        if ($this->bankid == '') {
            preg_match("/<BANKID>(.*)</iU", $qbofile, $out);
            if (trim($out[1] ?? '')) {
                $this->bankid = trim($out[1] ?? '');
            }
        }
    }
}
