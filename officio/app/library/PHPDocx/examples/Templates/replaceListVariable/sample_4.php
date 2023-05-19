<?php
// replace list variables (placeholders) from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/TemplateList_symbols.docx');
$docx->setTemplateSymbol('${', '}');

$items = array('First item', 'Second item', 'Third item');

$docx->replaceListVariable('LISTVAR', $items);

$docx->createDocx('example_replaceListVariable_4');