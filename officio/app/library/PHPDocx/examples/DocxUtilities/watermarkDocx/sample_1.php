<?php
// add an image watermark to an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Utilities\DocxUtilities();

$source = '../../files/Text.docx';
$target = 'example_watermarkImage.docx';

$docx->watermarkDocx($source, $target, 'image', array('image' => '../../files/image.png', 'decolorate' => false));