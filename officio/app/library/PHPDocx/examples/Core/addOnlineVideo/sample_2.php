<?php
// add and online video content using a custom image as preview

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$docx->addText('Add an online video with a custom image:');
$options = array(
    'image' => '../../img/image.png',
    'width' => 400,
);

$docx->addOnlineVideo('https://www.youtube.com/embed/S-nHYzK-BVg', $options);

$docx->addText('This is a closing paragraph.');

// set MS Word compatibility from MS Word 2013
$settings = array(
    'compat' => array(
        'compatibilityMode' => array('val' => '15'),
    )
);
$docx->docxSettings($settings);

$docx->createDocx('example_addOnlineVideo_2');