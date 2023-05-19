<?php
// add an image watermark to an existing DOCX using footer as scope

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Utilities\DocxUtilities();

$source = '../../files/Text.docx';
$target = 'example_watermarkImage_footer.docx';

$docx->watermarkDocx($source, $target, 'image', array('image' => '../../files/image.png', 'decolorate' => false, 'scope' => 'footer'));