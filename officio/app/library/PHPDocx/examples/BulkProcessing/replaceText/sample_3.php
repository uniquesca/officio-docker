<?php
// replace text placeholders using bulk methods, generate a DOCXStructure to be used with CreateDocxFromTemplate

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

Phpdocx\Create\CreateDocx::$returnDocxStructure = true;
$docxStructure = $documents[0]->saveDocx(null);
Phpdocx\Create\CreateDocx::$returnDocxStructure = false;

$docx = new Phpdocx\Create\CreateDocxFromTemplate($docxStructure);

$referenceNode = array(
    'type' => 'paragraph',
    'contains' => 'Thank you',
);

$docx->removeWordContent($referenceNode);

$docx->createDocx('example_replaceText_3');