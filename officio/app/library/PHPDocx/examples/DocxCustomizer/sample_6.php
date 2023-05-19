<?php
// generate a DOCX with table contents, and change table, row and cell styles

require_once '../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$valuesTable = array(
    array(
        11,
        12,
        13,
        14,
    ),
    array(
        21,
        22,
        23,
        24,
    ),
    array(
        31,
        32,
        33,
        34,
    ),

);

$paramsTable = array(
    'border' => 'single',
    'borderColor' => 'B70000',
    'borderWidth' => 10,
    'cellMargin' => array('top' => 200, 'left' => 100),
    'cellSpacing' => 120,
    'columnWidths' => array(100, 100, 100, 100),
    'indent' => 200,
    'tableAlign' => 'center',
    'tableWidth' => array('type' => 'dxa', 'value' => 900),
    'textProperties' => array('bold' => true, 'font' => 'Algerian', 'fontSize' => 18),
);

$docx->addTable($valuesTable, $paramsTable);

// get the content to be changed
$referenceNode = array(
    'type' => 'table',
    'occurrence' => 1,
);

$docx->customizeWordContent($referenceNode, 
    array(
        'borderColor' => '0000FF',
        'borderBottom' => 'dashed',
        'borderTop' => 'dashed',
        'borderInsideHColor' => '00FF00',
        'borderInsideVColor' => '00FF00',
        'borderWidth' => 15,
        'cellMargin' => array('top' => 900, 'left' => 200),
        'cellSpacing' => 240,
        'columnWidths' => array(1900, 3400, 1700, 1900),
        'indent' => 300,
        'tableAlign' => 'left',
        'tableStyle' => 'NormalTablePHPDOCX',
        'tableWidth' => array('type' => 'dxa', 'value' => 9000),
    )
);

// get the content to be changed
$referenceNode = array(
    'type' => 'table-cell',
    'occurrence' => 1,
    'parent' => 'w:tbl[1]/w:tr[1]/',
);

$docx->customizeWordContent($referenceNode, 
    array(
        'width' => 1900,
    )
);

// get the content to be changed
$referenceNode = array(
    'type' => 'table-cell',
    'occurrence' => 2,
    'parent' => 'w:tbl[1]/w:tr[1]/',
);

$docx->customizeWordContent($referenceNode, 
    array(
        'width' => 3400,
    )
);

// get the content to be changed
$referenceNode = array(
    'type' => 'table-cell',
    'occurrence' => 3,
    'parent' => 'w:tbl[1]/w:tr[1]/',
);

$docx->customizeWordContent($referenceNode, 
    array(
        'width' => 1700,
    )
);

// get the content to be changed
$referenceNode = array(
    'type' => 'table-cell',
    'occurrence' => 4,
    'parent' => 'w:tbl[1]/w:tr[1]/',
);

$docx->customizeWordContent($referenceNode, 
    array(
        'width' => 1900,
    )
);

$docx->createDocx('example_customizer_6');