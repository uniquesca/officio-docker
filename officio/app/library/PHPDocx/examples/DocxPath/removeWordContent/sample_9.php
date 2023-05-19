<?php
// remove the second row of a table from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/tables.docx');

// get the reference node to be removed
$referenceNode = array(
    'customQuery' => '//w:tbl/w:tr[2]',
);

$docx->removeWordContent($referenceNode);

$docx->createDocx('example_removeWordContent_9');