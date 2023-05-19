<?php
// add a column chart applying theme options to the text contents

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$docx->addText('Chart with theme properties:');

$data = array(
    'legend' => array('Series 1', 'Series 2', 'Series 3'),
    'data' => array(
        array(
            'name' => 'data 1',
            'values' => array(10, 7, 5),
        ),
        array(
            'name' => 'data 2',
            'values' => array(20, 60, 3),
        ),
        array(
            'name' => 'data 3',
            'values' => array(50, 33, 7),
        ),
        array(
            'name' => 'data 4',
            'values' => array(25, 0, 14),
        ),
    ),
);
$paramsChart = array(
    'data' => $data,
    'type' => 'colChart',
    'color' => '3',
    'perspective' => '10',
    'rotX' => '10',
    'rotY' => '10',
    'chartAlign' => 'center',
    'sizeX' => '10',
    'sizeY' => '10',
    'legendPos' => 'b',
    'legendOverlay' => '0',
    'border' => '1',
    'hgrid' => '3',
    'vgrid' => '0',
    'groupBar' => 'stacked',
    'theme' => array(
        'horizontalAxis' => array(
            'textBold' => true,
            'textDirection' => 'rotate270',
            'textSize' => 14,
        ),
        'verticalAxis' => array(
            'textDirection' => 'rotate90',
            'textItalic' => true,
            'textSize' => 14,
        ),
    ),
);
$docx->addChart($paramsChart);

$docx->createDocx('example_addChart_20');