<?php
// encrypt a PPTX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Crypto\CryptoPHPDOCX();
$source = '../../files/sample.pptx';
$target = 'Crypted.pptx';
$docx->encryptPPTX($source, $target, array('password' => 'phpdocx'));
