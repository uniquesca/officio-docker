<?php
// generate a DOCX with text contents and breaks, and change page break type

require_once '../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, ' .
    'sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut ' .
    'enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut' .
    'aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit ' .
    'in voluptate velit esse cillum dolore eu fugiat nulla pariatur.';

$docx->addText($text);

$docx->addText($text);

$docx->addBreak(array('type' => 'line'));

$docx->addText($text);

$docx->addBreak(array('type' => 'line'));

$docx->addText($text);

$docx->addBreak(array('type' => 'page'));

$docx->addText($text);

// get the content to be changed
$referenceNode = array(
    'type' => 'break',
    'occurrence' => 2,
);

$docx->customizeWordContent($referenceNode, 
    array(
        'type' => 'page',
    )
);

$docx->createDocx('example_customizer_2');