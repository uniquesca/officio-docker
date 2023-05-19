<?php
// clone a block and remove block placeholders in an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/DOCXPathBlocks.docx');

// clone block
$docx->cloneBlock('EXAMPLE');

// clone block
$docx->cloneBlock('EXAMPLE');

// remove block placeholders
$docx->clearBlocks();

$docx->createDocx('example_cloneBlock_2');