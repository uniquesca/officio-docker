<?php
// add tracked and not tracked contents to the DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$docx->addText('Not tracked paragraph');

$docx->addPerson(array('author' => 'phpdocx'));

$docx->enableTracking(array('author' => 'phpdocx'));

// create a Word fragment with an image to be inserted in the header of the document
$imageOptions = array(
    'src' => '../../img/image.png', 
    'dpi' => 300,  
);

$image = new Phpdocx\Elements\WordFragment($docx, 'defaultHeader');
$image->addImage($imageOptions);

$docx->addHeader(array('default' => $image));

$textFooter = new Phpdocx\Elements\WordFragment($docx, 'firstFooter');
$textFooter->addText('page footer.');

$docx->addFooter(array('default' => $textFooter));

$docx->addSection('nextPage', 'A3');

$docx->addText('Other text');

$paramsText = array(
    'b' => true
);

$docx->addText('New section', $paramsText);

$docx->disableTracking();

$docx->addText('Other paragraph');

$docx->createDocx('example_enableTracking_5');