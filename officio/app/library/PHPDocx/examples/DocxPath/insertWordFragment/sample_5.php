<?php
// insert a new content after the last list in an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/lists.docx');

// create the new content
$content = new Phpdocx\Elements\WordFragment($docx, 'document');
$content->addText('New text');

// get the reference of the node
$referenceNode = array(
	'type' => 'list',
    'occurrence' => -1,
);

$docx->insertWordFragment($content, $referenceNode, 'after');

$docx->createDocx('example_insertWordFragment_5');