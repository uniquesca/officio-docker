<?php
// generate a DOCX with text contents and custom paragraph styles, and change styles to a custom style

require_once '../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$docx->addText('Lorem ipsum');

// style options
$style = array(
    'bold' => true,
    'italic' => true,
    'backgroundColor' => 'FFFF00',
    'caps' => true,
    'em' => 'circle',
    'firstLineIndent' => 1200,
    'font' => 'Times New Roman',
    'fontSize' => 36,
    'headingLevel' => 2,
    'textAlign' => 'right',
    'lineSpacing' => 120,
    'spacingBottom' => 480,
    'spacingTop' => 480,
    'pageBreakBefore' => true,
    'underline' => 'dotted',
);
// create a custom style
$docx->createParagraphStyle('myStyle', $style);

// insert a paragraph with that style
$text = 'A paragraph in grey color with borders. All borders are red but the right one that is blue. ';
$text .= 'The general border style is single but the left border that is double. The top border is also thicker. ';
$text .= 'We also include big left indentation.';
$docx->addText($text, array('pStyle' => 'myStyle'));

// get the style to be changed
$referenceNode = array(
    'target' => 'style',
    'type' => 'style',
    'attributes' => array('w:styleId' => 'myStyle'),
);

$docx->customizeWordContent($referenceNode, 
    array(
        'bold' => false,
        'italic' => false,
        'backgroundColor' => 'FF0000',
        'caps' => false,
        'lineSpacing' => 240,
    )
);

$docx->createDocx('example_customizer_9');