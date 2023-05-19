<?php
// clone a row in a table in an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/tables.docx');

// get the reference of the node to be cloned
$referenceToBeCloned = array(
    'customQuery' => '//w:tbl/w:tr[2]',
);

// get the reference of the target node
$referenceNodeTo = array(
    'customQuery' => '//w:tbl/w:tr[2]',
);

$docx->cloneWordContent($referenceToBeCloned, $referenceNodeTo, 'after');

$docx->createDocx('example_cloneWordContent_7');