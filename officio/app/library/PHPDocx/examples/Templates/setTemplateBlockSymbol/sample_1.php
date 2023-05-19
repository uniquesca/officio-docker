<?php
// change the symbol used to wrap blocks and delete MYBLOCK_1 from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/TemplateBlocksCustomSymbol.docx');

$docx->setTemplateBlockSymbol('MYBLOCK');

$docx->deleteTemplateBlock('1');

$docx->createDocx('example_setTemplateBlockSymbol_1');