<?php
// replace text placeholders using bulk methods, generate a single DOCX output

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$bulk = new Phpdocx\Utilities\BulkProcessing('../../files/bulk.docx');

$variables = 
array(
	array(
		'date' => date('Y/m/d'),
		'name' => 'John',
		'address' => 'Acme Street 1',
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

$documents[0]->saveDocx('example_replaceText_1');