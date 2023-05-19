<?php
// import contents from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, ' .
    'sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut ' .
    'enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut' .
    'aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit ' .
    'in voluptate velit esse cillum dolore eu fugiat nulla pariatur. ' .
    'Excepteur sint occaecat cupidatat non proident, sunt in culpa qui ' .
    'officia deserunt mollit anim id est laborum.';

$paragraphOptions = array(
    'bold' => true,
    'font' => 'Arial',
);

$docx->addText($text, $paragraphOptions);

// import the first paragraph that contains 'heading'
$referenceNode = array(
    'type' => 'paragraph',
    'occurrence' => 1,
    'contains' => 'heading',
);

$docx->importContents('../../files/DOCXPathTemplate.docx', $referenceNode);

$docx->addText($text, $paragraphOptions);

// import charts
$referenceNode = array(
    'type' => 'chart',
);

$docx->importContents('../../files/DOCXPathTemplate.docx', $referenceNode);

$docx->createDocx('example_importContents_1');