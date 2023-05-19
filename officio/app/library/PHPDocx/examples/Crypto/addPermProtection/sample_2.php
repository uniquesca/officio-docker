<?php
// add protected sections in a DOCX template

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/DOCXPathTemplate.docx');

$referenceNode = array(
    'type' => 'paragraph',
    'occurrence' => 1,
    'contains' => 'A level 2 heading',
);

$startProtection = new Phpdocx\Elements\WordFragment($docx, 'document');
$startProtection->addPermProtection('start');

$docx->insertWordFragment($startProtection, $referenceNode, 'before');

$endProtection = new Phpdocx\Elements\WordFragment($docx, 'document');
$endProtection->addPermProtection('end');

$referenceNode = array(
    'type' => 'image',
    'occurrence' => 1,
);

$docx->insertWordFragment($endProtection, $referenceNode, 'after');

$docx->createDocx('example_addPermProtection_2');

$docx = new Phpdocx\Crypto\CryptoPHPDOCX();
$docx->protectDocx('example_addPermProtection_2.docx', 'example_addPermProtection_protected_2.docx', array('password' => 'phpdocx'));