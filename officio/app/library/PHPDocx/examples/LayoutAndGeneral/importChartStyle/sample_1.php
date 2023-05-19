<?php
// import a chart style from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

// import chart styles
$docx->importChartStyle('../../files/ChartStyles.docx', '2', 'myChartStyle');

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
    'data'        => $data,
    'type'        => 'pieChart',
    'rotX'        => 20,
    'rotY'        => 20,
    'perspective' => 30,
    'color'       => 2,
    'sizeX'       => 10,
    'sizeY'       => 5,
    'chartAlign'  => 'center',
    'showPercent' => 1,
    'customStyle' => 'myChartStyle', // choose the style to be used
    'theme'       => array(
        'valueRgbColors' => array(
            array('ED7D31', 'A5A5A5', 'FFC000', 'C5E0B4'),
        ),
    ),
);
$docx->addChart($paramsChart);

$docx->createDocx('example_importChartStyle_1');