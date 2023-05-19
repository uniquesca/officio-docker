<?php
// embed a font to a template and use it with a WordFragment

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/TemplateWordFragment_1.docx');

// embed a TTF font
$docx->embedFont('../../files/fonts/Pacifico.ttf', 'Pacifico');

// generate a new WordFragment with the new font
$paragraphOptions = array(
	'font' => 'Pacifico',
);
$wordFragment = new Phpdocx\Elements\WordFragment($docx);
$wordFragment->addText('Text using the new font.', $paragraphOptions);

// replace the placeholder 
$docx->replaceVariableByWordFragment(array('WORDFRAGMENT' => $wordFragment), array('type' => 'block'));

// generate the DOCX
$docx->createDocx('example_embedFont_3');