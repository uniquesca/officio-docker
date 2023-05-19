<?php
// replace placeholders by WordFragments using bulk methods, generate a single DOCX output

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$bulk = new Phpdocx\Utilities\BulkProcessing('../../files/bulk.docx');

$nameA = new Phpdocx\Elements\WordFragment();
$nameA->addText('John', array('bold' => true, 'font' => 'Arial'));

$nameB = new Phpdocx\Elements\WordFragment();
$nameB->addText('Mary', array('bold' => true, 'font' => 'Arial'));

$nameC = new Phpdocx\Elements\WordFragment();
$nameC->addText('Tom', array('bold' => true, 'font' => 'Arial'));

$linkA = new Phpdocx\Elements\WordFragment();
$linkA->addLink('link to phpdocx', array('url'=> 'http://www.phpdocx.com', 'color' => '0000FF', 'u' => 'single'));

$linkB = new Phpdocx\Elements\WordFragment();
$linkB->addLink('link to javadocx', array('url'=> 'http://www.javadocx.com', 'color' => '0000FF', 'u' => 'single'));

$linkC = new Phpdocx\Elements\WordFragment();
$linkC->addLink('link to xmldocx', array('url'=> 'http://www.xmldocx.com', 'color' => '0000FF', 'u' => 'single'));

$variables = 
array(
    // first DOCX
	array('url' => $linkA, 'name' => $nameA),
    // second DOCX
    array('url' => $linkB, 'name' => $nameB),
    // third DOCX
    array('url' => $linkC, 'name' => $nameC),
);
$options = array(
	'type' => 'inline',
);

$bulk->replaceWordFragment($variables, $options);
$documents = $bulk->getDocuments();

for ($i = 0; $i < count($documents); $i++) {
    $documents[$i]->saveDocx('example_replaceWordFragment_2_' . ($i + 1));
}