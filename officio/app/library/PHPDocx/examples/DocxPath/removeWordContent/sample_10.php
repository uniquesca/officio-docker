<?php
// remove charts from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/charts.docx');

// get the reference node to be removed
$referenceNode = array(
    'type' => 'chart',
);

$docx->removeWordContent($referenceNode);

$docx->createDocx('example_removeWordContent_10');