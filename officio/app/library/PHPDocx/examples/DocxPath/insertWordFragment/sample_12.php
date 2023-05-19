<?php
// create a DOCX with footers and add new contents after default and first footer types

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$imageOptions = array(
    'src' => '../../img/image.png', 
    'dpi' => 300,  
);

$default = new Phpdocx\Elements\WordFragment($docx, 'defaultFooter');
$default->addImage($imageOptions);
$first = new Phpdocx\Elements\WordFragment($docx, 'firstFooter');
$first->addText('first page footer.');
$even = new Phpdocx\Elements\WordFragment($docx, 'evenFooter');
$even->addText('even page footer.');

$docx->addFooter(array('default' => $default, 'first' => $first, 'even' => $even));

$docx->addText('This is the first page of a document with different footers for the first and even pages.');
$docx->addBreak(array('type' => 'page'));
$docx->addText('This is the second page.');
$docx->addBreak(array('type' => 'page'));
$docx->addText('This is the third page.');

// create the new contents
$content = new Phpdocx\Elements\WordFragment($docx);
$content->addText('New text.', array('fontSize' => 20, 'color' => '#0000ff'));
$content->addImage(array('src' => '../../img/image.png' , 'scaling' => 10));

// get the reference of the node
$referenceNode = array(
    'target' => 'footer',
    'type' => '*',
    'reference' => array(
        'types' => array('default', 'first'),
    ),
);

$docx->insertWordFragment($content, $referenceNode, 'after', false);

$docx->createDocx('example_insertWordFragment_12');