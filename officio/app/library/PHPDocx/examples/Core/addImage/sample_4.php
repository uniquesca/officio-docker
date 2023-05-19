<?php
// add an image using a stream source

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$options = array(
    'src'        => 'http://www.2mdc.com/PHPDOCX/logo_badge.png',
    'imageAlign' => 'center',
    'streamMode' => true,
);

$docx->addImage($options);

$docx->createDocx('example_addImage_4');