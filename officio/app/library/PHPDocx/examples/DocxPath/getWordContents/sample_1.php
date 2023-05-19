<?php
// get the paragraph contents that contain a text from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/DOCXPathTemplate.docx');

// get the reference of the nodes to be returned
$referenceNode = array(
    'type' => 'paragraph',
    'contains' => 'heading',
);

$contents = $docx->getWordContents($referenceNode);

print_r($contents);