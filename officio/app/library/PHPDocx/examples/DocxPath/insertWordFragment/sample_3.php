<?php
// insert new contents after a paragraph with an outline level (heading style) in an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/headings.docx');

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
	'type' => 'paragraph',
    'occurrence' => 1,
    'attributes' => array('w:outlineLvl' => array('w:val' => 2)),
);

$docx->insertWordFragment($content, $referenceNode, 'after');

$docx->createDocx('example_insertWordFragment_3');