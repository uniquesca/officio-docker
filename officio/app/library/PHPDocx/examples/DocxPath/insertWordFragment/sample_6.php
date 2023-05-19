<?php
// insert new contents after and before sections in an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/sections.docx');

$contentA = new Phpdocx\Elements\WordFragment($docx, 'document');
$contentA->addText('New text at the beginning');
// get the reference of the node
$referenceNode = array(
    'type' => '*',
    'occurrence' => 1,
);
$docx->insertWordFragment($contentA, $referenceNode, 'before');

$contentB = new Phpdocx\Elements\WordFragment($docx, 'document');
$contentB->addText('New text second page');
// get the reference of the node
$referenceNode = array(
    'type' => 'section',
    'occurrence' => 1,
);
$docx->insertWordFragment($contentB, $referenceNode, 'after');

$contentC = new Phpdocx\Elements\WordFragment($docx, 'document');
$contentC->addText('New text first page');
// get the reference of the node
$referenceNode = array(
    'type' => 'section',
    'occurrence' => 1,
);
$docx->insertWordFragment($contentC, $referenceNode, 'before', true);

$contentD = new Phpdocx\Elements\WordFragment($docx, 'document');
$contentD->addText('New text at the end');
// get the reference of the node
$referenceNode = array(
    'type' => '*',
    'occurrence' => -1,
);
$docx->insertWordFragment($contentD, $referenceNode, 'after');

$docx->createDocx('example_insertWordFragment_6');