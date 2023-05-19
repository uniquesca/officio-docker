<?php
// clone a paragraph in a table cell in an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/tables.docx');

// get the reference of the node to be cloned
$referenceToBeCloned = array(
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

$docx->cloneWordContent($referenceToBeCloned, $referenceNodeTo, 'after');

$docx->createDocx('example_cloneWordContent_5');