<?php
// clone the first table after the first chart in an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/DOCXPathTemplate.docx');

// get the reference of the node to be cloned
$referenceToBeCloned = array(
    'type' => 'table',
    'occurrence' => 1,
);

// get the reference of the target node
$referenceNodeTo = array(
    'type' => 'chart',
    'occurrence' => 1,
);

$docx->cloneWordContent($referenceToBeCloned, $referenceNodeTo, 'before');

$docx->createDocx('example_cloneWordContent_6');