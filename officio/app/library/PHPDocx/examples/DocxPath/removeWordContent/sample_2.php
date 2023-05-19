<?php
// remove the first paragraph from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/bookmarks.docx');

// get the reference node to be removed
$referenceNode = array(
    'type' => 'paragraph',
    'occurrence' => 1,
);

$docx->removeWordContent($referenceNode);

$docx->createDocx('example_removeWordContent_2');