<?php
// remove block placeholders from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/TemplateBlocks_symbols.docx');
$docx->setTemplateSymbol('${', '}');

$docx->clearBlocks();

$docx->createDocx('example_clearBlocks_2');