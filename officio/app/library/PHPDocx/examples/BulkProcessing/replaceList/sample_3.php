<?php
// replace list placeholders by WordFragments using bulk methods, generate a single DOCX output

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$bulk = new Phpdocx\Utilities\BulkProcessing('../../files/bulk.docx');

$itemA = new Phpdocx\Elements\WordFragment();
$itemA->addText('1A45A', array('italic' => true, 'font' => 'Arial'));

$itemB = new Phpdocx\Elements\WordFragment();
$itemB->addText('EA78A', array('italic' => true, 'font' => 'Arial'));

$itemC = new Phpdocx\Elements\WordFragment();
$itemC->addText('YA99A', array('italic' => true, 'font' => 'Arial'));

$linkA = new Phpdocx\Elements\WordFragment();
$linkA->addLink('link to phpdocx', array('url'=> 'http://www.phpdocx.com', 'color' => '0000FF', 'u' => 'single'));

$linkB = new Phpdocx\Elements\WordFragment();
$linkB->addLink('link to javadocx', array('url'=> 'http://www.javadocx.com', 'color' => '0000FF', 'u' => 'single'));

$linkC = new Phpdocx\Elements\WordFragment();
$linkC->addLink('link to xmldocx', array('url'=> 'http://www.xmldocx.com', 'color' => '0000FF', 'u' => 'single'));

$variables =
array(
	array(
    // lists
        array('LIST_A' => array($itemA, $itemB, $itemC)),
        array('LIST_B' => array($linkA, $linkB, $linkC)),
    ),
);

$bulk->replaceList($variables);
$documents = $bulk->getDocuments();

$documents[0]->saveDocx('example_replaceList_3');