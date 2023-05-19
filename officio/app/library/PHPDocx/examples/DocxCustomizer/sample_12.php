<?php
// change the styles of an existing DOCX

require_once '../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../files/DOCXPathTemplate.docx');

$referenceNode = array(
    'type' => 'paragraph',
    'contains' => 'simple Word document',
);

$docx->customizeWordContent($referenceNode, 
    array(
        'bold' => true,
        'caps' => true,
    )
);

// get the content to be changed
$referenceNode = array(
    'type' => 'paragraph',
    'contains' => 'Now a chart',
);

$docx->customizeWordContent($referenceNode, 
    array(
        'backgroundColor' => 'FFFF00',
        'bold' => true,
        'italic' => true,
    )
);

$docx->createDocx('example_customizer_12');