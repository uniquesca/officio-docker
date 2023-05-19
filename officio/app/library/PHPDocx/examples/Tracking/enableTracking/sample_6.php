<?php
// add tracked and not tracked contents to the DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$docx->addText('Not tracked paragraph');

$docx->addPerson(array('author' => 'phpdocx'));

$docx->enableTracking(array('author' => 'phpdocx'));

$docx->addText('First tracked paragraph');

$paragraphOptions = array(
    'bold' => true,
    'font' => 'Arial'
);

$docx->addText('Second tracked paragraph', $paragraphOptions);

// create a Word fragment with an image
$image = new Phpdocx\Elements\WordFragment($docx);
$imageOptions = array(
    'src' => '../../img/image.png',
    'scaling' => 50, 
    'float' => 'right',
    'textWrap' => 1,
);
$image->addImage($imageOptions);

// create a Word fragment with a link
$link = new Phpdocx\Elements\WordFragment($docx);
$linkOptions = array(
    'url' => 'http://www.google.es', 
    'color' => '0000FF', 
    'underline' => 'single',
);
$link->addLink('link to Google', $linkOptions);

$text = array();

$text[] = $image;
$text[] = array(
    'text' => 'I am going to write a link: ',
    'bold' => true
);
$text[] = $link;
$text[] = array(
    'text' => ' to illustrate how to include links. '
);
$text[] = array(
    'text' => ' As you may see it is extremely simple to do so and it can be done with any other Word element.',
);

$docx->addText($text);

$docx->disableTracking();

$docx->addText('Other paragraph');

$docx->createDocx('example_enableTracking_6');