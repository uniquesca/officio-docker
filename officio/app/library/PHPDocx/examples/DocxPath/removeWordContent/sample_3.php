<?php
// remove page breaks from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/breaks.docx');

// get the reference node to be removed
$referenceNode = array(
    'type' => 'break',
    'attributes' => array('w:type' => 'page'),
);

$docx->removeWordContent($referenceNode);

$docx->createDocx('example_removeWordContent_3');