<?php
// generate a DOCX with text contents, and change styles to a run-of-text content

require_once '../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$text = array();
$text[] =
    array(
        'text' => 'We know this looks ugly',
        'underline' => 'single'
);
$text[] =
    array(
        'text' => ' but we only want to illustrate some of the functionality of the addText method.',
        'bold' => true
);

$paragraphOptions = array( 'border' => 'double',
    'borderColor' => 'b70000',
    'borderWidth' => 12,
    'borderSpacing' => 8,
    'borderTopColor' => '000000',
);

$docx->addText($text, $paragraphOptions);

// get the content to be changed
$referenceNode = array(
    'type' => 'run',
    'contains' => 'but we only',
);

$docx->customizeWordContent($referenceNode, 
    array(
        'bold' => false,
        'highlight' => 'red',
        'italic' => true,
    )
);

$docx->createDocx('example_customizer_8');