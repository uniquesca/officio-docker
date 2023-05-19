<?php
// get the contents in a cell using a custom XPath query from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/tables.docx');

// get the reference of the node to be returned
$referenceNode = array(
    'customQuery' => '//w:tbl/w:tr[2]/w:tc[1]',
);

$contents = $docx->getWordContents($referenceNode);

print_r($contents);