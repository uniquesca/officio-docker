<?php
// move a link after other link in an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/links.docx');

// get the reference nodes to be moved
$referenceNodeFrom = array(
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

$docx->moveWordContent($referenceNodeFrom, $referenceNodeTo, 'after');

$docx->createDocx('example_moveWordContent_3');