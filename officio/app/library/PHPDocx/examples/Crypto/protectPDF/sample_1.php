<?php
// protect a PDF setting print and annot-forms permissions

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$pdf = new Phpdocx\Crypto\CryptoPHPDOCX();
$source = '../../files/Test.pdf';
$target = 'protected.pdf';
$pdf->protectPDF($source, $target, array('permissionsBlocked' => array('print', 'annot-forms')));