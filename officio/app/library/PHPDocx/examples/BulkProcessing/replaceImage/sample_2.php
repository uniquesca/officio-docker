<?php
// replace image placeholders using bulk methods, generate a DOCX for each array value

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$bulk = new Phpdocx\Utilities\BulkProcessing('../../files/bulk.docx');

$variables = 
array(
    // first DOCX
	array(
		'LOGO' => '../../img/imageP1.png',
	),
    // second DOCX
    array(
        'LOGO' => '../../img/imageP2.png',
    ),
    // third DOCX
    array(
        'LOGO' => '../../img/imageP3.png',
    ),
);
$bulk->replaceImage($variables);
$documents = $bulk->getDocuments();

for ($i = 0; $i < count($documents); $i++) {
    $documents[$i]->saveDocx('example_replaceImage_2_' . ($i + 1));
}