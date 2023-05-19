<?php
// generate a DOCX with text contents, and change text styles from a content

require_once '../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, ' .
    'sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut ' .
    'enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut' .
    'aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit ' .
    'in voluptate velit esse cillum dolore eu fugiat nulla pariatur. ' .
    'Excepteur sint occaecat cupidatat non proident, sunt in culpa qui ' .
    'officia deserunt mollit anim id est laborum.';

$paragraphOptions = array(
    'font' => 'Arial',
);

$docx->addText($text, $paragraphOptions);

$text = 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem ' . 
    'accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ' . 
    'ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt ' . 
    'explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut ' . 
    'odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem ' . 
    'sequi nesciunt.';

$docx->addText($text, $paragraphOptions);

// get the content to be changed
$referenceNode = array(
    'type' => 'paragraph',
    'contains' => 'Sed ut',
);

$docx->customizeWordContent($referenceNode, 
    array(
        'backgroundColor' => 'FFFF00',
        'bold' => true,
        'caps' => true,
        'em' => 'circle',
        'font' => 'Times New Roman',
        'fontSize' => 36,
        'headingLevel' => 2,
        'italic' => true,
        'lineSpacing' => 360,
        'spacingBottom' => 480,
        'spacingTop' => 480,
        'pageBreakBefore' => true,
        'textAlign' => 'right',
        'underline' => 'dotted',
    )
);

$docx->createDocx('example_customizer_1');