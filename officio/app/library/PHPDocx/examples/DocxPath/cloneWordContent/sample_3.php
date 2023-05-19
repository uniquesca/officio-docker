<?php
// clone a link in an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/links.docx');

// get the reference of the node to be cloned
$referenceToBeCloned = array(
    'type' => 'paragraph',
    'occurrence' => 1,
    'contains' => 'HYPERLINK',
);

// get the reference of the target node
$referenceNodeTo = array(
    'type' => 'paragraph',
    'occurrence' => 2,
    'contains' => 'HYPERLINK',
);

$docx->cloneWordContent($referenceToBeCloned, $referenceNodeTo, 'after');

$docx->createDocx('example_cloneWordContent_3');