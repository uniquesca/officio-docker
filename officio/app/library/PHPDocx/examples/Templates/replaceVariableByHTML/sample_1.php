<?php
// replace text variables (placeholders) with HTML from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/TemplateHTML.docx');

// do an inline replacement
$docx->replaceVariableByHTML('ADDRESS', 'inline', '<p style="font-family: verdana; font-size: 11px">C/ Matías Turrión 24, Madrid 28043 <b>Spain</b></p>', array('isFile' => false, 'parseDivsAsPs' => true, 'downloadImages' => false));
// do a block replacement
$docx->replaceVariableByHTML('CHUNK_1', 'block', 'http://www.2mdc.com/PHPDOCX/example.html', array('isFile' => true, 'parseDivsAsPs' => true,  'filter' => '#capa_bg_bottom', 'downloadImages' => true));
// do a block replacement
$docx->replaceVariableByHTML('CHUNK_2', 'block', 'http://www.2mdc.com/PHPDOCX/example.html', array('isFile' => true, 'parseDivsAsPs' => false,  'filter' => '#lateral', 'downloadImages' => true));

$docx->createDocx('example_replaceTemplateVariableByHTML_1');