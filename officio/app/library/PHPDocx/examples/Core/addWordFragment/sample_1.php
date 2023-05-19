<?php
// add a WordFragment to the end of the document. DOCXPath available in Advanced and Premium licenses allows inserting WordFragments to any position

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$wordFragment = new Phpdocx\Elements\WordFragment($docx);

// a WordFragment may include one or more contents

$imageOptions = array(
    'src'      => '../../img/image.png',
    'scaling'  => 50,
    'float'    => 'right',
    'textWrap' => 1,
);
$wordFragment->addImage($imageOptions);

$linkOptions = array(
    'url' => 'http://www.google.es', 
    'color' => '0000FF', 
    'underline' => 'single',
);
$wordFragment->addLink('link to Google', $linkOptions);

$docx->addWordFragment($wordFragment);

$docx->createDocx('example_addWordFragment_1');