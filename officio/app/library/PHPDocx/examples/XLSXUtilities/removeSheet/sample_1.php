<?php
// remove sheets 1 and 2 from an existing XLSX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$newXLSX = new Phpdocx\Utilities\XLSXUtilities();

$newXLSX->removeSheet('../../files/data_excel.xlsx', 'example_removeSheet_1.xlsx', array('sheetNumber' => array(1, 2)));