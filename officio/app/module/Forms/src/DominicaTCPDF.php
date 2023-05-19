<?php

namespace Forms;

use TCPDF;

class DominicaTCPDF extends TCPDF
{

    public $htmlHeader;

    public function setHtmlHeader($htmlHeader)
    {
        $this->htmlHeader = $htmlHeader;
    }

    public function Header()
    {
        $this->writeHTML($this->htmlHeader, true, false, true);
    }
}