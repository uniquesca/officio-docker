<?php
// replace a text variable (placeholder) with HTML keeping placeholder styles from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/TemplateHTML.docx');

$docx->replaceVariableByHTML('ADDRESS', 'inline', '<p>C/ Matías Turrión 24, Madrid 28043 <b>Spain</b></p>', array('stylesReplacementType' => 'usePlaceholderStyles'));

$docx->createDocx('example_replaceTemplateVariableByHTML_2');