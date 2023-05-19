<?php
// accept a tracking content from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/tracking_content.docx');

$referenceNode = array(
	'type' => 'paragraph',
    'contains' => 'xmldocx',
);

$docx->acceptTracking($referenceNode);

$docx->createDocx('example_acceptTracking_2');