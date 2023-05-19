<?php
// add a column chart applying custom colors

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$docx->addText('Chart with custom colors:');

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
        'serRgbColors' => array(null, 'FF00FF', '00FFFF'),
        'valueRgbColors' => array(
            array('FF0000'),
            null,
            array(null, null, '00FF00'),
        ),
    ),
);
$docx->addChart($paramsChart);

$docx->createDocx('example_addChart_17');