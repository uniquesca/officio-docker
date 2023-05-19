<?php
// generate a DOCX with list contents, and change list styles

require_once '../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$itemList = array(
    'Line 1',
    'Line 2',
    'Line 3',
    'Line 4',
    'Line 5',
);

// establish some global run properties for each list item
$options = array(
    'color' => 'B70000',
    'font' => 'Arial',
    'fontSize' => 14,
    'italic' => true,
    );

// set the style type to 1: unordered list
$docx->addList($itemList, 1, $options);

// get the content to be changed
$referenceNode = array(
    'type' => 'list',
    'occurrence' => 1,
);

$docx->customizeWordContent($referenceNode, 
    array(
        'italic' => false,
    )
);

// get the content to be changed
$referenceNode = array(
    'type' => 'list',
    'occurrence' => -1,
);

$docx->customizeWordContent($referenceNode, 
    array(
        'bold' => true,
        'color' => '0000FF',
        'depthLevel' => 1,
        'fontSize' => 12,
        'type' => 2,
    )
);

$docx->createDocx('example_customizer_5');