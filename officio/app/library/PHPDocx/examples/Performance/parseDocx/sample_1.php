<?php
// use a DOCXStructure as template and change its contents

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

// this object can be serialized (in memory, database, file system...) to be reused later
$docxStructure = new Phpdocx\Utilities\DOCXStructure();
$docxStructure->parseDocx('../../files/TemplateSimpleText.docx');

$docx = new Phpdocx\Create\CreateDocxFromTemplate($docxStructure);

$first = 'PHPDocX';
$multiline = 'This is the first line.\nThis is the second line of text.';

$variables = array('FIRSTTEXT' => $first, 'MULTILINETEXT' => $multiline);
$options = array('parseLineBreaks' => true);

$docx->replaceVariableByText($variables, $options);

$docx->createDocx('example_parseDocx_1');