<?php
// replace image variables (placeholders) in headers from an existing DOCX using a stream source. The placeholder has been added to the alt text content

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/placeholderImage.docx');

$image_1 = array(
    'height' => 'auto',
    'streamMode' => true,
    'width' => 'auto',
    'target' => 'header',
);

$docx->replacePlaceholderImage('HEADERIMG', 'http://www.2mdc.com/PHPDOCX/logo_badge.png', $image_1);

$docx->createDocx('example_replacePlaceholderImage_3');