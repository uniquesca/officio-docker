<?php
// add a pie chart applying custom colors

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$docx->addText('A pie chart:');

$data = array(
    'data' => array(
        array(
            'name' => 'data 1',
            'values' => array(10),
        ),
        array(
            'name' => 'data 2',
            'values' => array(20),
        ),
        array(
            'name' => 'data 3',
            'values' => array(50),
        ),
        array(
            'name' => 'data 4',
            'values' => array(25),
        ),
    ),
);

$paramsChart = array(
    'data' => $data,
    'type' => 'pieChart',
    'color' => 3,
    'sizeX' => 10,
    'sizeY' => 5,
    'chartAlign' => 'center',
);
$docx->addChart($paramsChart);

$docx->addText('The same chart with custom colors:');

$data = array(
    'data' => array(
        array(
            'name' => 'data 1',
            'values' => array(10),
        ),
        array(
            'name' => 'data 2',
            'values' => array(20),
        ),
        array(
            'name' => 'data 3',
            'values' => array(50),
        ),
        array(
            'name' => 'data 4',
            'values' => array(25),
        ),
    ),
);

$paramsChart = array(
    'data' => $data,
    'type' => 'pieChart',
    'color' => 3,
    'sizeX' => 10,
    'sizeY' => 5,
    'chartAlign' => 'center',
    'theme' => array(
        'valueRgbColors' => array(
            array('99C099', '678CB3', 'ACA42E'),
        ),
    ),
);
$docx->addChart($paramsChart);

$docx->createDocx('example_addChart_18');