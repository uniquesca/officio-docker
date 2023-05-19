<?php
// import headers and footers from an external DOCX and get existing tables in headers

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();
$docx->importHeadersAndFooters('../../files/TemplateHeaderAndFooter.docx');

// get the reference of the nodes to be returned
$referenceNode = array(
    'target' => 'header',
    'type' => 'table',
);

$contents = $docx->getWordContents($referenceNode);

print_r($contents);