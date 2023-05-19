<?php
// get the styles of a custom style

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/DOCXPathTemplate.docx');

// get the reference node from styles
$referenceNode = array(
    'type' => 'style',
    'contains' => 'ListParagraph',
);

$contents = $docx->getWordStyles($referenceNode);

print_r($contents);