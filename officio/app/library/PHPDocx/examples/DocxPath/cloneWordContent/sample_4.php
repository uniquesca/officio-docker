<?php
// clone a specific paragraph before a section in an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/sections.docx');

// get the reference of the node to be cloned
$referenceToBeCloned = array(
    'type' => 'paragraph',
    'occurrence' => 2,
    'contains' => 'This is',
);

// get the reference of the target node
$referenceNodeTo = array(
    'type' => 'section',
    'occurrence' => 1,
);

$docx->cloneWordContent($referenceToBeCloned, $referenceNodeTo, 'before');

$docx->createDocx('example_cloneWordContent_4');