<?php
// create a DOCX adding an image in a header, and replace it by a new image and a paragraph

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/tables.docx');

// add the image in a header
$imageOptions = array(
    'src' => '../../img/image.png', 
    'dpi' => 300,  
);
$headerImage = new Phpdocx\Elements\WordFragment($docx, 'defaultHeader');
$headerImage->addImage($imageOptions);
$docx->addHeader(array('default' => $headerImage));

$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, ' .
    'sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut ' .
    'enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut' .
    'aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit ' .
    'in voluptate velit esse cillum dolore eu fugiat nulla pariatur. ' .
    'Excepteur sint occaecat cupidatat non proident, sunt in culpa qui ' .
    'officia deserunt mollit anim id est laborum.';

$docx->addText($text);

// create the new content to be added
$content = new Phpdocx\Elements\WordFragment($docx);
$content->addText('New text.', array('fontSize' => 20, 'color' => '#0000ff'));
$content->addImage(array('src' => '../../img/image.png' , 'scaling' => 10));

// get the reference node
$referenceNode = array(
    'target' => 'header',
    'type' => 'image',
    'occurrence' => 1,
);

$docx->replaceWordContent($content, $referenceNode);

$docx->createDocx('example_replaceWordContent_3');