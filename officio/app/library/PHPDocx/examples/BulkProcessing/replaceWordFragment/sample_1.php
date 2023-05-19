<?php
// replace placeholders by WordFragments using bulk methods, generate a single DOCX output

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$bulk = new Phpdocx\Utilities\BulkProcessing('../../files/bulk.docx');

$name = new Phpdocx\Elements\WordFragment();
$name->addText('John', array('bold' => true, 'font' => 'Arial'));

$link = new Phpdocx\Elements\WordFragment();
$link->addLink('link to phpdocx', array('url'=> 'http://www.phpdocx.com', 'color' => '0000FF', 'u' => 'single'));

$variables = 
array(
	array('url' => $link, 'name' => $name),
);
$options = array(
	'type' => 'inline',
);

$bulk->replaceWordFragment($variables, $options);
$documents = $bulk->getDocuments();

$documents[0]->saveDocx('example_replaceWordFragment_1');