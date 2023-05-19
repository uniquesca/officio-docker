<?php
// replace text variables (placeholder) with WordFragments in headers, footers and document doing inline replacements from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/TemplateWordFragmentsTarget_symbols.docx');
$docx->setTemplateSymbol('${', '}');

$imageHeader = new Phpdocx\Elements\WordFragment($docx);
$imageHeader->addImage(array('src' => '../../files/image.png', 'scaling' => 20));

$textHeader       = new Phpdocx\Elements\WordFragment($docx);
$paragraphOptions = array(
    'bold' => true,
    'font' => 'Arial',
);
$textHeader->addText('Lorem ipsum dolor sit amet', $paragraphOptions);

$textOther = new Phpdocx\Elements\WordFragment($docx);
$textOther->addText('Other text');

$textBody1 = new Phpdocx\Elements\WordFragment($docx);
$textBody1->addText('Body text');

$textBody2 = new Phpdocx\Elements\WordFragment($docx);
$textBody2->addText('Body text 2');

$imageBody = new Phpdocx\Elements\WordFragment($docx);
$imageBody->addImage(array('src' => '../../files/image.png'));

$imageFooter = new Phpdocx\Elements\WordFragment($docx);
$imageFooter->addImage(array('src' => '../../files/image.png', 'scaling' => 50));

$textFooter       = new Phpdocx\Elements\WordFragment($docx);
$paragraphOptions = array(
    'bold' => true,
    'font' => 'Arial',
);
$textFooter->addText('Text footer', $paragraphOptions);

// replace the text variable in headers
$docx->replaceVariableByWordFragment(array('VAR_HEADER_1' => $imageHeader, 'VAR_HEADER_2' => $textHeader, 'VAR_HEADER_3' => $textOther), array('type' => 'inline', 'target' => 'header'));
// replace the text variable in the document
$docx->replaceVariableByWordFragment(array('VAR_BODY_1' => $textBody1, 'VAR_BODY_2' => $textBody2, 'VAR_BODY_3' => $imageBody));
// replace the text variable in footers
$docx->replaceVariableByWordFragment(array('VAR_FOOTER_1' => $textFooter, 'VAR_FOOTER_2' => $imageFooter), array('type' => 'inline', 'target' => 'footer'));

$docx->createDocx('example_replaceVariableByWordFragment_7');