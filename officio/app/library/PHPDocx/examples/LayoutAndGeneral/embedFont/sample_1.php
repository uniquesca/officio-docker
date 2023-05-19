<?php
// embed a font and add new text contents applying the embedded font

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

// embed a TTF font
$docx->embedFont('../../files/fonts/Pacifico.ttf', 'Pacifico');

// add a text content using the default font
$docx->addText('Text using the default font.');

// add a text content using the embedded font
$paragraphOptions = array(
    'font' => 'Pacifico',
);
$docx->addText('Text using the new font.', $paragraphOptions);

// generate the DOCX
$docx->createDocx('example_embedFont_1');