<?php
// encrypt a XLSX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Crypto\CryptoPHPDOCX();
$source = '../../files/Book.xlsx';
$target = 'Crypted.xlsx';
$docx->encryptXLSX($source, $target, array('password' => 'phpdocx'));
