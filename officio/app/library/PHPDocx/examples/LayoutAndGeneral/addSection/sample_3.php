<?php
// add a new section applying a page number type

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

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

$docx->addSection('nextPage', 'A4', array('numberCols' => 3, 'space' => 150));

$docx->addText($text);
$docx->addText($text);
$docx->addText($text);
$docx->addText($text);

$paramsText = array(
    'b' => true
);

$docx->addText($text, $paramsText);

$columns = array(
    array(
        'width' => 5760,
        'space' => 720,
    ),
    array(
        'width' => 2880,
    ),
);
$docx->addSection('nextPage', 'A4', array('numberCols' => 2, 'space' => 720, 'columns' => $columns));

$docx->addText($text);
$docx->addText($text);
$docx->addText($text);
$docx->addText($text);
$docx->addText($text);
$docx->addText($text);
$docx->addText($text);
$docx->addText($text);

$docx->createDocx('example_addSection_3');