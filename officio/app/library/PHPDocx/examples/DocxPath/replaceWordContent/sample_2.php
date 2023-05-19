<?php
// replace a table content from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/tables.docx');

// create a new table
$content = new Phpdocx\Elements\WordFragment($docx, 'document');
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

// get the reference node to be replaced
$referenceNode = array(
    'type' => 'table',
    'occurrence' => 1,
);

$docx->replaceWordContent($content, $referenceNode);

$docx->createDocx('example_replaceWordContent_2');