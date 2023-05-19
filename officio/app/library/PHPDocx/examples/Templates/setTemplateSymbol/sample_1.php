<?php
// change the symbol used to wrap variables (placehoders) and replace a text variable (placeholder) with new text from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/TemplatePipeSymbol.docx');
$docx->setTemplateSymbol('|');

$docx->replaceVariableByText(array('FIRST' => 'Hello World!'));

$docx->createDocx('example_setTemplateSymbol_1');