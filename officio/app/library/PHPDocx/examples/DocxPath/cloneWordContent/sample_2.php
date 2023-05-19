<?php
// clone an image in an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/images.docx');

// get the reference of the nodes to be cloned
$referenceToBeCloned = array(
    'type' => 'image',
);

// get the reference of the target node
$referenceNodeTo = array(
    'type' => 'paragraph',
    'contains' => 'closing paragraph',
);

$docx->cloneWordContent($referenceToBeCloned, $referenceNodeTo, 'after');

$docx->createDocx('example_cloneWordContent_2');