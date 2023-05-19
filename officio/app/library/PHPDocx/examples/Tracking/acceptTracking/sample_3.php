<?php
// accept tracking contents from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/tracking_tables.docx');

$referenceNode = array(
	'type' => 'table',
    'occurrence' => '1..2',
);

$docx->acceptTracking($referenceNode);

$referenceNode = array(
    'type' => 'table',
    'occurrence' => -1,
);

$docx->acceptTracking($referenceNode);

$docx->createDocx('example_acceptTracking_3');