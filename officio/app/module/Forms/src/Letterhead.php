<?php

namespace Forms;

use TCPDF;

class Letterhead extends TCPDF
{

    /**
     * Letterhead Settings.
     * @protected
     */
    protected $letterheadSettings;

    /**
     * Page Number Settings.
     * @protected
     */
    protected $pageNumberSettings;

    /** @var string */
    protected $_letterheadsPath;

    public function SetLetterheadsPath($letterheadsPath)
    {
        $this->_letterheadsPath = $letterheadsPath;
        return $this;
    }

    public function SetLetterheadSettings($letterheadSettings)
    {
        $this->letterheadSettings = $letterheadSettings;
    }

    public function SetPageNumberSettings($pageNumberSettings)
    {
        $this->pageNumberSettings = $pageNumberSettings;
    }

    public function Header()
    {
        // get current auto-page-break mode
        $auto_page_break = $this->AutoPageBreak;
        if (!empty($this->letterheadSettings)) {
            // disable auto-page-break
            $this->setAutoPageBreak(false);
            if ($this->page == 1) {
                $path    = $this->_letterheadsPath . '/' . $this->letterheadSettings['first_file_id'];
                $bMargin = $this->letterheadSettings['first_margin_bottom'];
                $tMargin = $this->letterheadSettings['first_margin_top'];
            } else {
                $bMargin = $this->letterheadSettings['second_margin_bottom'];
                $tMargin = $this->letterheadSettings['second_margin_top'];
                $path    = $this->_letterheadsPath . '/' . $this->letterheadSettings['second_file_id'];
            }
            if ($this->letterheadSettings['type'] == 'a4') {
                $w = 210;
                $h = 297;
            } else {
                $w = 215.9;
                $h = 279.4;
            }
            $this->Image($path, 0, 0, $w, $h);
            $this->setTopMargin($tMargin);
        } else {
            $bMargin = PDF_MARGIN_BOTTOM;
        }

        // restore auto-page-break status
        $this->setAutoPageBreak($auto_page_break, $bMargin);
        // set the starting point for the page content
        $this->setPageMark();

        if ($this->pageNumberSettings['location'] == 'top') {
            $this->setY($this->pageNumberSettings['distance']);
            $this->SetPageNumber();
        }
    }

    public function Footer() {

        if ($this->pageNumberSettings['location'] == 'bottom') {
            $this->setY($this->pageNumberSettings['distance'] * (-1));
            $this->SetPageNumber();
        }
    }

    public function SetPageNumber() {
        $pageNumber = $this->pageNumberSettings['skip_number'] ? 1 : 0;
        $alignment = 'R';
        $wording = $this->pageNumberSettings['wording'] . ' ';
        switch ($this->pageNumberSettings['alignment']) {
            case 'left':
                $alignment = 'L';
                break;
            case 'centre':
                $alignment = 'C';
                break;
        }
        // Page number
        if ($this->page > $pageNumber) {
            $this->Cell(0, 10, $wording . $this->getAliasNumPage(), 0, false, $alignment, 0);
        }
    }
}