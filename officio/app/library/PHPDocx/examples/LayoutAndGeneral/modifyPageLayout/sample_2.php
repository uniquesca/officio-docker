<?php
// modify page layout to A4-landscape and 2 columns

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/MultipleSections.docx');

// using the sectionNumers option one may choose the sections that one wishes to modify
$docx->modifyPageLayout('A4-landscape', array('numberCols' => '2', 'sectionNumbers' => array(2)));

$docx->createDocx('example_modifyPageLayout_2');