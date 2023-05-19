<?php
// delete a block content from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/TemplateBlocks_symbols.docx');
$docx->setTemplateSymbol('${', '}');

$docx->deleteTemplateBlock('FIRST');

$docx->createDocx('example_deleteTemplateBlock_2');