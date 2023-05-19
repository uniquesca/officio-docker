<?php
// add protected sections in a new DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$text = 'Content protected.';

$docx->addText($text);

$docx->addPermProtection('start');

$text = 'Content not protected.';
$docx->addText($text);

$docx->addPermProtection('end');

$text = 'Content protected.';

$docx->addText($text);

$docx->addPermProtection('start');

$itemList= array(
    'Line 1',
    array(
        'Line A',
        'Line B',
        'Line C'
    ),
    'Line 2',
    'Line 3',
);

$docx->addList($itemList, 2);

$docx->addPermProtection('end');

$docx->createDocx('example_addPermProtection_1');

$docx = new Phpdocx\Crypto\CryptoPHPDOCX();
$docx->protectDocx('example_addPermProtection_1.docx', 'example_addPermProtection_protected_1.docx', array('password' => 'phpdocx'));