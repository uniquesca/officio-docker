<?php
// remove the first section tag from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/sections.docx');

// get the reference node to be removed
$referenceNode = array(
    'type' => 'section',
    'occurrence' => 1,
);

$docx->removeWordContent($referenceNode);

$docx->createDocx('example_removeWordContent_8');