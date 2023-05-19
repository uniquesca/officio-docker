<?php
// move a paragraph from a section to other in an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/sections.docx');

// get the reference nodes to be moved
$referenceNodeFrom = array(
    'type' => 'paragraph',
    'occurrence' => 1,
    'contains' => 'This is other section',
);

// get the reference of the target node
$referenceNodeTo = array(
    'type' => 'section',
    'occurrence' => 1,
);

$docx->moveWordContent($referenceNodeFrom, $referenceNodeTo, 'before');

$docx->createDocx('example_moveWordContent_4');