<?php
// insert new contents before the first table in an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/tables.docx');

// create the new contents
$content = new Phpdocx\Elements\WordFragment($docx, 'document');
$content->addText('New text');

$valuesTable = array(
    array(
        'AAA',
        'BBB',
    ),
    array(
        'Text',
        'Text: More text',
    ),

);
$paramsTable = array(
    'border' => 'single',
    'tableAlign' => 'center',
    'borderWidth' => 10,
    'borderColor' => 'B70000',
    'textProperties' => array('bold' => true),
);
$content->addTable($valuesTable, $paramsTable);

// get the reference of the node
$referenceNode = array(
    'type' => 'table',
    'occurrence' => 1,
);

$docx->insertWordFragment($content, $referenceNode, 'before');

$docx->createDocx('example_insertWordFragment_7');