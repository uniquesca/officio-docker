<?php
// add tracked and not tracked contents to the DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$valuesTable = array(
    array(
        11,
        12,
        13,
        14
    ),
    array(
        21,
        22,
        23,
        24
    ),
    array(
        31,
        32,
        33,
        34
    ),

);

$paramsTable = array(
    'border' => 'single',
    'tableAlign' => 'center',
    'borderWidth' => 10,
    'borderColor' => 'B70000',
    'columnWidths' => array(1000, 1500, 1500, 1500),
    'textProperties' => array('bold' => true, 'font' => 'Algerian', 'fontSize' => 18),
);

$docx->addTable($valuesTable, $paramsTable);

$docx->addPerson(array('author' => 'phpdocx'));

$docx->enableTracking(array('author' => 'phpdocx'));

$referenceNode = array(
    'type' => 'table',
);

$docx->removeWordContent($referenceNode);

$docx->disableTracking();

$docx->createDocx('example_enableTracking_9');