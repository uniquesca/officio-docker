<?php
// add and online video content

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$docx->addText('Add an online video:');

$docx->addOnlineVideo('https://www.youtube.com/embed/S-nHYzK-BVg');

$docx->addText('This is a closing paragraph.');

// set MS Word compatibility from MS Word 2013
$settings = array(
    'compat' => array(
        'compatibilityMode' => array('val' => '15'),
    )
);
$docx->docxSettings($settings);

$docx->createDocx('example_addOnlineVideo_1');