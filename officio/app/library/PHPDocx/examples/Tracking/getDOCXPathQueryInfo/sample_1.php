<?php

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/tracking_content.docx');

$referenceNode = array(
    'type' => 'tracking-insert',
    'parent' => '/',
);

$queryInfo = $docx->getDocxPathQueryInfo($referenceNode);

var_dump($queryInfo);