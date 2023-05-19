<?php
// remove the second paragraph that contains HYPERLINK from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/links.docx');

// get the reference node to be removed
$referenceNode = array(
    'type' => 'paragraph',
    'occurrence' => 2,
    'contains' => 'HYPERLINK',
);

$docx->removeWordContent($referenceNode);

$docx->createDocx('example_removeWordContent_6');