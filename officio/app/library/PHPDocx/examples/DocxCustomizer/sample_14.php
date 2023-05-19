<?php
// import headers and footers from a DOCX, and change the styles of the footer

require_once '../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();
$docx->importHeadersAndFooters('../files/TemplateHeaderAndFooter.docx');

// get the content to be changed
$referenceNode = array(
    'target' => 'footer',
    'type' => 'paragraph',
    'reference' => array(
        'types' => array('default'),
    ),
);

$docx->customizeWordContent($referenceNode, 
    array(
        'backgroundColor' => 'FFFF00',
    )
);

$docx->createDocx('example_customizer_14');