<?php
// add tracked and not tracked contents to the DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$docx->addText('Not tracked paragraph');

$docx->addPerson(array('author' => 'phpdocx'));

$docx->enableTracking(array('author' => 'phpdocx'));

$docx->addText('First tracked paragraph');

$docx->addText('Second tracked paragraph');

$docx->disableTracking();

$docx->addText('Other paragraph');

$docx->createDocx('example_enableTracking_1');