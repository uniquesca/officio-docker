<?php
// import contents from two existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/Text.docx');

$paragraphOptions = array(
    'bold' => true,
    'font' => 'Arial',
);

$docx->addText('Import images:', $paragraphOptions);

// import images
$referenceNode = array(
    'type' => 'image',
);

$docx->importContents('../../files/SimpleExample.docx', $referenceNode);

$docx->importContents('../../files/placeholderImage.docx', $referenceNode);

$paragraphOptions = array(
    'bold' => true,
    'font' => 'Arial',
);

$docx->addText('Import tables:', $paragraphOptions);

// import tables
$referenceNode = array(
    'type' => 'table',
);

$docx->importContents('../../files/SimpleExample.docx', $referenceNode);

$docx->importContents('../../files/TemplateSimpleTable.docx', $referenceNode);

$docx->createDocx('example_importContents_2');