<?php
// sign a DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$sign = new Phpdocx\Sign\SignDOCX();

copy('../../files/Text.docx', 'Text.docx');
$sign->setDocx('Text.docx');
$sign->setPrivateKey('../../files/Test.pem', 'phpdocx_pass');
$sign->setX509Certificate('../../files/Test.pem');
$sign->setSignatureComments('This document has been signed by me');
$sign->sign();