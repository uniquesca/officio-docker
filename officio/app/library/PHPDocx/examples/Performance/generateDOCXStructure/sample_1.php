<?php
// generate a DOCXStructure from a stream and change its contents

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docxStructureStream = new Phpdocx\Utilities\DOCXStructureFromStream();
$docxStructure = $docxStructureStream->generateDOCXStructure('http://www.phpdocx.com/files/samples/TemplateSimpleText.docx');

$docx = new Phpdocx\Create\CreateDocxFromTemplate($docxStructure);

$variables = array('FIRSTTEXT' => 'PHPDocX', 'MULTILINETEXT' => 'This is the first line.\nThis is the second line of text.');
$options = array('parseLineBreaks' => true);

$docx->replaceVariableByText($variables, $options);

$docx->createDocx('example_generateDocxStructureFromStream_1');