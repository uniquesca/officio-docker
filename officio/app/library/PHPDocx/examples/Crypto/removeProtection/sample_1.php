<?php
// remove protection from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Crypto\CryptoPHPDOCX();
$source = '../../files/protectedDocument.docx';
$target = 'unprotected.docx';
$docx->removeProtection($source, $target);
