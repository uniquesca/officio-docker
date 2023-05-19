<?php
// get the OOXML information of paragraphs in headers from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();
$docx->importHeadersAndFooters('../../files/TemplateHeaderAndFooter.docx');

// get the reference of the nodes to be returned
$referenceNode = array(
    'target' => 'header',
    'type' => 'paragraph',
);

$queryInfo = $docx->getDocxPathQueryInfo($referenceNode);

var_dump($queryInfo);