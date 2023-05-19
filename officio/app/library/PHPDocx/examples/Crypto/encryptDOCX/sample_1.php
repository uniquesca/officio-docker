<?php
// encrypt a DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Crypto\CryptoPHPDOCX();
$source = '../../files/Text.docx';
$target = 'Crypted.docx';
$docx->encryptDOCX($source, $target, array('password' => 'phpdocx'));
