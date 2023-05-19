<?php
// move the cell contents to other cell and insert a new content into other cell

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/tables.docx');

// get the reference nodes to be moved
$referenceNodeFrom = array(
    'type' => 'paragraph',
    'parent' => '/w:tc/',
    'occurrence' => 4,
);

// get the reference of the target node
$referenceNodeTo = array(
    'type' => 'paragraph',
    'parent' => '/w:tc/',
    'occurrence' => 8,
);

$docx->moveWordContent($referenceNodeFrom, $referenceNodeTo, 'after');

// create the new content to be added
$content = new Phpdocx\Elements\WordFragment($docx, 'document');
$content->addText('New text to avoid empty cell');

// get the reference node
$referenceNode = array(
    'parent' => '/w:tc/',
    'occurrence' => 7,
);

$docx->insertWordFragment($content, $referenceNode, 'after');

$docx->createDocx('example_moveWordContent_5');