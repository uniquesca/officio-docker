<?php
// generate a DOCX with a text and a section, and change the styles of the last section

require_once '../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, ' .
    'sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut ' .
    'enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut' .
    'aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit ' .
    'in voluptate velit esse cillum dolore eu fugiat nulla pariatur. ' .
    'Excepteur sint occaecat cupidatat non proident, sunt in culpa qui ' .
    'officia deserunt mollit anim id est laborum.';

$docx->addText($text);

$paramsText = array(
    'b' => true
);

$docx->addText($text, $paramsText);

// get the content to be changed
$referenceNode = array(
    'target' => 'lastSection',
    'type' => 'section',
);

$docx->customizeWordContent($referenceNode, 
    array(
        'gutter' => 10,
        'height' => 15000,
        'marginBottom' => 3000,
        'marginFooter' => 3000,
        'marginHeader' => 3000,
        'marginLeft' => 3000,
        'marginRight' => 3000,
        'marginTop' => 3000,
        'numberCols' => 3,
        'orient' => 'landspace',
        'width' => 15000,
    )
);

$docx->createDocx('example_customizer_11');