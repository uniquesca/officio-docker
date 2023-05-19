<?php
// get default Word styles

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/DOCXPathTemplate.docx');

// get the default styles
$referenceNode = array(
    'type' => 'default',
);

$contents = $docx->getWordStyles($referenceNode);

print_r($contents);