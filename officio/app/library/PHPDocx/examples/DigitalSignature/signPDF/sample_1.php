<?php
// sign a PDF

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$sign = new Phpdocx\Sign\SignPDF();

$sign->setPDF('../../files/Test.pdf');
$sign->setPrivateKey('../../files/Test.pem', 'phpdocx_pass');
$sign->setX509Certificate('../../files/Test.pem');
$sign->sign('Test_signed.pdf');