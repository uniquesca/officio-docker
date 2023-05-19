<?php
// clone block and subblocks and remove block placeholders in an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/DOCXPathBlocks_2.docx');

// clean internal variables
$variables = $docx->getTemplateVariables();
$docx->processTemplate($variables);

// clone block
$docx->cloneBlock('EXAMPLE');

// clone block
$docx->cloneBlock('EXAMPLE');

// clone block, 1st occurrence
$docx->cloneBlock('SUB_1');

// clone block, 2nd occurrence
$docx->cloneBlock('SUB_1', 2);

// clone block, 4th occurrence
$docx->cloneBlock('SUB_1', 4);

// clone block, 2nd occurrence
$docx->cloneBlock('SUB_2', 2);

// clone block, 1st occurrence
$docx->cloneBlock('SUB_2');

// remove block placeholders
$docx->clearBlocks();

$docx->createDocx('example_cloneBlock_3');