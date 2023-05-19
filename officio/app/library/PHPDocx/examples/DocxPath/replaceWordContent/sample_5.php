<?php
// import headers and footers from an external DOCX and replace the images by new contents

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$docx->importHeadersAndFooters('../../files/TemplateHeaderAndFooter.docx');

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

// create the new content to be added
$content = new Phpdocx\Elements\WordFragment($docx, 'document');
$content->addText(' New text.', array('fontSize' => 20, 'color' => '#0000ff'));

// get the reference nodes to be replaced
$referenceNode = array(
    'target' => 'header',
    'type' => 'image',
    'parent' => '/',
);

$docx->replaceWordContent($content, $referenceNode);

$docx->createDocx('example_replaceWordContent_5');