<?php
// sign a PDF adding image and signature options

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$sign = new Phpdocx\Sign\SignPDF();

$sign->setPDF('../../files/Test.pdf');
$sign->setPrivateKey('../../files/Test.pem', 'phpdocx_pass');
$sign->setX509Certificate('../../files/Test.pem');

$optionsSignature = array(
    'x' => 0,
    'y' => 10,
    'w' => 20,
    'h' => 20,
    'page' => 1,
);

$optionsImage = array(
    'src' => '../../files/image.png',
    'x' => 0,
    'y' => 200,
    'w' => 50,
    'h' => 50,
    'link' => 'https://www.phpdocx.com',
);

$sign->sign('Test_signed_2.pdf', $optionsSignature, $optionsImage);