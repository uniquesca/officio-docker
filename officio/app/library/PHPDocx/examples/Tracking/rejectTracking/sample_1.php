<?php

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/tracking_content.docx');

$referenceNode = array(
    'type' => 'paragraph',
    'contains' => 'phpdocx',
    'occurrence' => 1,
);

$docx->rejectTracking($referenceNode);

$referenceNode = array(
    'type' => 'paragraph',
    'contains' => 'for more information',
);

$docx->rejectTracking($referenceNode);

$docx->createDocx('example_rejectTracking_1');