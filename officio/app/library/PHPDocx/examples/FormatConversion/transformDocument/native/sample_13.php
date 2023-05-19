<?php
// generate a DOCX from a template replacing contents and transform it to PDF using the conversion plugin based on DOMPDF

require_once '../../../../Classes/Phpdocx/Create/CreateDocx.php';
// include DOMPDF and create an object. DOMPDF isn't bundled in phpdocx
require_once 'dompdf/autoload.inc.php';
$dompdf = new Dompdf\Dompdf();

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../../files/TemplateSimpleTable.docx');

$data = array(
            array(
                'ITEM' => 'Product A',
                'REFERENCE' => '107AW3',
            ),
            array(
                'ITEM' => 'Product B',
                'REFERENCE' => '204RS67O',
            ),
            array(
                'ITEM' => 'Product C',
                'REFERENCE' => '25GTR56',
            )
        );

$docx->replaceTableVariable($data, array('parseLineBreaks' => true));

$docx->createDocx('transformDocument_native_13.docx');

$transform = new Phpdocx\Transform\TransformDocAdvDOMPDF('transformDocument_native_13.docx');
$transform->setDOMPDF($dompdf);
$transform->transform('transformDocument_native_13.pdf');