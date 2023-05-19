<?php
// replace text placeholders using bulk methods, generate a DOCX for each array value

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$bulk = new Phpdocx\Utilities\BulkProcessing('../../files/bulk.docx');

$variables = 
array(
    // first DOCX
    array(
        'date' => date('Y/m/d'),
        'name' => 'John',
        'address' => 'Acme Street 1',
        'url' => 'https://www.phpdocx.com',
        'signature' => 'phpdocx',
        'footer' => 'phpdocx\nfooter',
    ),
    // second DOCX
    array(
        'date' => date('Y/m/d'),
        'name' => 'Mary',
        'address' => 'Acme Street 2',
        'url' => 'https://www.phpdocx.com',
        'signature' => 'phpdocx',
        'footer' => 'phpdocx\nfooter',
    ),
    // third DOCX
    array(
        'date' => date('Y/m/d'),
        'name' => 'Tom',
        'address' => 'Acme Street 3',
        'url' => 'https://www.phpdocx.com',
        'signature' => 'phpdocx',
        'footer' => 'phpdocx\nfooter',
    ),
);
$options = array(
	'parseLineBreaks' => true,
);

$bulk->replaceText($variables, $options);
$documents = $bulk->getDocuments();

for ($i = 0; $i < count($documents); $i++) {
    $documents[$i]->saveDocx('example_replaceText_2_' . ($i + 1));
}