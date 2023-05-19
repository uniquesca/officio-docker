<?php
// get the styles of the first list

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/DOCXPathTemplate.docx');

// get the reference of the node to be returned
$referenceNode = array(
    'type' => 'list',
    'occurrence' => 1,
);

$contents = $docx->getWordStyles($referenceNode);

print_r($contents);