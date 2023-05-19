<?php
// add a background image to an existing PDF

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Utilities\PdfUtilities();

$source = '../../files/Test.pdf';
$target = 'example_addBackgroundImage.pdf';

$docx->addBackgroundImage($source, $target, '../../files/image.png', array('height' => 'auto', 'width' => 'auto', 'opacity' => 0.3));