<?php
// replace text variables (placeholders) with new text using ${ and } as wrapping symbols from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/TemplateSimpleText_symbols.docx');
$docx->setTemplateSymbol('${', '}');

$first     = 'PHPDocX';
$multiline = 'This is the first line.\nThis is the second line of text.';

$variables = array('FIRSTTEXT' => $first, 'MULTILINETEXT' => $multiline);
// replace \n with line breaks
$options = array('parseLineBreaks' => true);

$docx->replaceVariableByText($variables, $options);

$docx->createDocx('example_replaceVariableByText_2');