<?php
// move all images after a paragraph in an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/images.docx');

// get the reference nodes to be moved
$referenceNodeFrom = array(
    'type' => 'image',
);

// get the reference of the target nodes
$referenceNodeTo = array(
    'type' => 'paragraph',
    'contains' => 'closing paragraph',
);

$docx->moveWordContent($referenceNodeFrom, $referenceNodeTo, 'after');

$docx->createDocx('example_moveWordContent_2');