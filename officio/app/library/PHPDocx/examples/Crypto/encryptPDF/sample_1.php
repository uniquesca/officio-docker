<?php
// encrypt a PDF

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$pdf = new Phpdocx\Crypto\CryptoPHPDOCX();
$source = '../../files/Test.pdf';
$target = 'crypted.pdf';
$pdf->encryptPDF($source, $target, array('password' => 'phpdocx'));