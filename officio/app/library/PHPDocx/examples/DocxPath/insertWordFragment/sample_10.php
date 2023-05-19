<?php
// insert a new content before all paragraphs that contain the Lorem string using an inline insertion

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

$docx->addText($text, $paragraphOptions);

$content = new Phpdocx\Elements\WordFragment($docx, 'document');

$content->addText(' New text.', array('fontSize' => 20, 'color' => '#0000ff'));

// get the reference of the node
$referenceNode = array(
    'type' => 'paragraph',
    'contains' => 'Lorem',
);

$docx->insertWordFragment($content, $referenceNode, 'inlineBefore');

$docx->createDocx('example_insertWordFragment_10');