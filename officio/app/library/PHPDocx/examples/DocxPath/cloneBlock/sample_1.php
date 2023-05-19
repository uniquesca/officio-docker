<?php
// clone a block and remove block placeholders in an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/TemplateBlocks_2.docx');

// clone block
$docx->cloneBlock('FIRST');

// remove block placeholders
$docx->clearBlocks();

$docx->createDocx('example_cloneBlock_1');