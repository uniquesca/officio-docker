<?php
// remove block placeholders from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/TemplateBlocks.docx');

$docx->clearBlocks();

$docx->createDocx('example_clearBlocks_1');