<?php
// generate a DOCX from a template replacing contents and transform it to PDF using the conversion plugin based on native PHP classes

require_once '../../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../../files/TemplateSimpleTable.docx');

$data = array(
    array(
        'ITEM'      => 'Product A',
        'REFERENCE' => '107AW3',
    ),
    array(
        'ITEM'      => 'Product B',
        'REFERENCE' => '204RS67O',
    ),
    array(
        'ITEM'      => 'Product C',
        'REFERENCE' => '25GTR56',
    )
);

$docx->replaceTableVariable($data, array('parseLineBreaks' => true));

$docx->createDocx('transformDocument_native_8.docx');

$transform = new Phpdocx\Transform\TransformDocAdvNative();
$transform->transformDocument('transformDocument_native_8.docx', 'transformDocument_native_8.pdf');