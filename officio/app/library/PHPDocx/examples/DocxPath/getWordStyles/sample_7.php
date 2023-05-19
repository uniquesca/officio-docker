<?php
// get the table styles, table-row styles and table-cell styles

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/SimpleExample.docx');

// get the reference nodes
$referenceNode = array(
    'type' => 'table',
);

$contents = $docx->getWordStyles($referenceNode);

//print_r($contents);

// get the reference node
$referenceNode = array(
    'type' => 'table-row',
    'parent' => 'w:tbl/',
    'occurrence' => 1,
);

$contents = $docx->getWordStyles($referenceNode);

print_r($contents);

// get the reference node
$referenceNode = array(
    'type' => 'table-cell',
    'parent' => 'w:tbl/w:tr/',
    'occurrence' => '1..2',
);

$contents = $docx->getWordStyles($referenceNode);

print_r($contents);