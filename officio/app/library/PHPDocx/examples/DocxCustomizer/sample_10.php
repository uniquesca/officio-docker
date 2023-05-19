<?php
// generate a DOCX with table contents, and change styles in a row and a cell

require_once '../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

// create a simple Word fragment to insert into the table
$textFragment = new Phpdocx\Elements\WordFragment($docx);
$text = array();
$text[] = array('text' => 'Fit text and ');
$text[] = array('text' => 'Word fragment', 'bold' => true);
$textFragment->addText($text);

// establish some row properties for the first row
$trProperties = array();
$trProperties[0] = array(
    'minHeight' => 1000, 
    'tableHeader' => true,
);

$col_1_1 = array(
    'rowspan' => 4,
    'value' => '1_1', 
    'backgroundColor' => 'cccccc',
    'borderColor' => 'b70000',
    'border' => 'single',
    'borderTopColor' => '0000FF',
    'borderWidth' => 16,
    'cellMargin' => 200,
);

$col_2_2 = array(
    'rowspan' => 2, 
    'colspan' => 2, 
    'width' => 200,
    'value' => $textFragment, 
    'backgroundColor' => 'ffff66',
    'borderColor' => 'b70000',
    'border' => 'single',
    'cellMargin' => 200,
    'fitText' => 'on',
    'vAlign' => 'bottom',
);

$col_2_4 = array(
    'rowspan' => 3,
    'value' => 'Some rotated text', 
    'backgroundColor' => 'eeeeee',
    'borderColor' => 'b70000',
    'border' => 'single',
    'borderWidth' => 16,
    'textDirection' => 'tbRl',
);

// set the global table properties
$options = array(
    'columnWidths' => array(400,1400,400,400,400), 
    'border' => 'single', 
    'borderWidth' => 4, 
    'borderColor' => 'cccccc', 
    'borderSettings' => 'inside', 
    'float' => array('align' => 'right', 
        'textMargin_top' => 300, 
        'textMargin_right' => 400, 
        'textMargin_bottom' => 300, 
        'textMargin_left' => 400
    ),
);
$values = array(
    array($col_1_1, '1_2', '1_3', '1_4', '1_5'),
    array($col_2_2, $col_2_4, '2_5'),
    array('3_5'),
    array('4_2', '4_3', '4_5'),
);

$docx->addTable($values, $options, $trProperties);

// get the content to be changed
$referenceNode = array(
    'type' => 'table-row',
    'occurrence' => 1,
    'parent' => 'w:tbl[1]/',
);

$docx->customizeWordContent($referenceNode, 
    array(
        'height' => 2500,
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
        'backgroundColor' => 'FF0000',
        'borderTopWidth' => 30,
        'cellMargin' => array('top' => 300, 'left' => 20),
    )
);

$docx->createDocx('example_customizer_10');