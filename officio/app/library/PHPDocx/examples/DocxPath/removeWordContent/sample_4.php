<?php
// remove the second chart from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/charts.docx');

// get the reference node to be removed
$referenceNode = array(
    'type' => 'chart',
    'occurrence' => 2,
);

$docx->removeWordContent($referenceNode);

$docx->createDocx('example_removeWordContent_4');