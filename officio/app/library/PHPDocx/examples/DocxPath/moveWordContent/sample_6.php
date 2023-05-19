<?php
// move a table before a chart in an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/DOCXPathTemplate.docx');

// get the reference node to be moved
$referenceNodeFrom = array(
    'type' => 'table',
    'occurrence' => 1,
);

// get the reference of the target node
$referenceNodeTo = array(
    'type' => 'chart',
    'occurrence' => 1,
);

$docx->moveWordContent($referenceNodeFrom, $referenceNodeTo, 'before');

$docx->createDocx('example_moveWordContent_6');