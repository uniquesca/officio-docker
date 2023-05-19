<?php
// generate a DOCX with tables and transform it to PDF using the conversion plugin based on DOMPDF

require_once '../../../../Classes/Phpdocx/Create/CreateDocx.php';
// include DOMPDF and create an object. DOMPDF isn't bundled in phpdocx
require_once 'dompdf/autoload.inc.php';
$dompdf = new Dompdf\Dompdf();

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
);

$docx->addTable($valuesTable, $paramsTable);

$link = new Phpdocx\Elements\WordFragment($docx);
$options = array(
    'url' => 'http://www.google.es'
);

$link->addLink('Link to Google', $options);

$image = new Phpdocx\Elements\WordFragment($docx);
$options = array(
    'src' => '../../../img/image.png'
);

$image->addImage($options);

$valuesTable = array(
    array(
        'Title A',
        'Title B',
        'Title C'
    ),
    array(
        'Line A',
        $link,
        $image
    )
);


$paramsTable = array(
    'columnWidths' => array(1000, 2500, 3000),
    'cellMargin' => array('top' => 90, 'right' => 90, 'bottom' => 120, 'left' => 190),
    );

$docx->addTable($valuesTable, $paramsTable);

$docx->createDocx('transformDocument_native_10.docx');

$transform = new Phpdocx\Transform\TransformDocAdvDOMPDF('transformDocument_native_10.docx');
$transform->setDOMPDF($dompdf);
$transform->transform('transformDocument_native_10.pdf');