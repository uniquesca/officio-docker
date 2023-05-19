<?php

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/tracking_content.docx');

$referenceNode = array(
	'type' => 'paragraph',
    'contains' => 'xmldocx',
);

$docx->rejectTracking($referenceNode);

$docx->createDocx('example_rejectTracking_2');