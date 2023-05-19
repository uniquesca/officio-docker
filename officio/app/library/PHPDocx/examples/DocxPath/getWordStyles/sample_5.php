<?php
// get the styles of an image

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/SimpleExample.docx');

// get the reference node
$referenceNode = array(
    'type' => 'image',
    'occurrence' => 1,
);

$contents = $docx->getWordStyles($referenceNode);

print_r($contents);