<?php
// replace image variables (placeholders) in footnotes from an existing DOCX. The placeholder has been added to the alt text content

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/placeholderImageFootnote.docx');

$image_1 = array(
	'height' => 2,
	'width' => 2,
	'target' => 'footnote',
);

$docx->replacePlaceholderImage('FOOTNOTEIMG','../../img/logo_header.jpg', $image_1);

$docx->createDocx('example_replacePlaceholderImage_2');