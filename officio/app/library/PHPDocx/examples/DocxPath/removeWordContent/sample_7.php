<?php
// remove the last list element from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/lists.docx');

// get the reference node to be removed
$referenceNode = array(
    'type' => 'list',
    'occurrence' => -1,
);

$docx->removeWordContent($referenceNode);

$docx->createDocx('example_removeWordContent_7');