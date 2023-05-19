<?php
// insert new contents after the second paragraph with a hyperlink in an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/docxpath/links.docx');

// create the new contents
$content = new Phpdocx\Elements\WordFragment($docx, 'document');
$content->addText('New text');
$content->addImage(array('src' => '../../img/image.png' , 'scaling' => 50));

// get the reference of the node
$referenceNode = array(
	'type' => 'paragraph',
    'occurrence' => 2,
    'contains' => 'HYPERLINK',
);

$docx->insertWordFragment($content, $referenceNode, 'before');

$docx->createDocx('example_insertWordFragment_4');