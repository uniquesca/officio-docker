<?php
// get Word styles from paragraphs that contain a specific text string

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/DOCXPathTemplate.docx');

// get the reference of the nodes to be returned
$referenceNode = array(
    'type' => 'paragraph',
    'contains' => 'level 2 heading',
);

$contents = $docx->getWordStyles($referenceNode);

print_r($contents);