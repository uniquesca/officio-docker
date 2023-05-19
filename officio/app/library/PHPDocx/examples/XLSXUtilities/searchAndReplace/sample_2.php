<?php
// replace chart values from an existing XLSX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$newXLSX = new Phpdocx\Utilities\XLSXUtilities();

$data = array(
    array(
        'row' => 2,
        'col' => 1,
        'value' => 30,
    ),
    array(
        'row' => 2,
        'col' => 2,
        'value' => 10,
    ),
    array(
        'row' => 2,
        'col' => 3,
        'value' => 5,
    ),
    array(
        'row' => 4,
        'col' => 2,
        'value' => 3,
    ),
);

$newXLSX->searchAndReplace('../../files/data_excel.xlsx', 'example_searchAndReplace_2.xlsx', $data, 'sheet', array('sheetName' => 'Chart'));
//$newXLSX->searchAndReplace('../../files/data_excel.xlsx', 'example_searchAndReplace_2.xlsx', $data, 'sheet', array('sheetNumber' => 3));