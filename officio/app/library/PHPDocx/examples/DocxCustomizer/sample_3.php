<?php
// generate a DOCX with an image, and change image styles

require_once '../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$docx->addText('This is a paragraph.');

$options = array(
    'src' => '../files/image.png',
    'imageAlign' => 'center',
    'scaling' => 50,
    'spacingTop' => 10,
    'spacingBottom' => 0,
    'spacingLeft' => 0,
    'spacingRight' => 20,
    'textWrap' => 0,
    'borderStyle' => 'lgDash',
    'borderWidth' => 6,
    'borderColor' => 'FF0000',
    'hyperlink' => 'http://www.google.es',
);

$docx->addImage($options);

// get the content to be changed
$referenceNode = array(
    'type' => 'image',
    'occurrence' => 1,
);

$docx->customizeWordContent($referenceNode, 
    array(
        'borderColor' => '00FF00',
        'borderStyle' => 'solid',
        'borderWidth' => 2,
        'height' => 1562038,
        'imageAlign' => 'right',
        'spacingTop' => 395250,
        'width' => 2066775,
    )
);

$docx->addText('This is a closing paragraph.');

$docx->createDocx('example_customizer_3');