<?php
// create two DOCX using DOCXStructure (in-memory DOCX) and merge them generating a DOCX output

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

Phpdocx\Create\CreateDocx::$returnDocxStructure = true;

// create the first document to be merged and return it as DOCX structure
$docx_a = new Phpdocx\Create\CreateDocx();

$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, ' .
    'sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut ' .
    'enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut' .
    'aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit ' .
    'in voluptate velit esse cillum dolore eu fugiat nulla pariatur. ' .
    'Excepteur sint occaecat cupidatat non proident, sunt in culpa qui ' .
    'officia deserunt mollit anim id est laborum.';

$paragraphOptions = array(
    'bold' => true,
    'font' => 'Arial'
);

$docx_a->addText($text, $paragraphOptions);

$document1 = $docx_a->createDocx();

// create the second document to be merged and return it as DOCX structure
$docx_b = new Phpdocx\Create\CreateDocx();

$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, ' .
    'sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut ' .
    'enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut' .
    'aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit ' .
    'in voluptate velit esse cillum dolore eu fugiat nulla pariatur. ' .
    'Excepteur sint occaecat cupidatat non proident, sunt in culpa qui ' .
    'officia deserunt mollit anim id est laborum.';

$paragraphOptions = array(
    'font' => 'Arial'
);

$docx_b->addText($text, $paragraphOptions);

$footnote = new Phpdocx\Elements\WordFragment($docx_b, 'document');
$footnote->addFootnote(
  array(
    'textDocument' => 'footnote',
    'textFootnote' => 'The footnote we want to insert.',
    'referenceMark' => array('b' => 'on'),
  )
);

$textFragment = new Phpdocx\Elements\WordFragment($docx_b, 'document');

$text = array();
$text[] = array('text' => 'Other text ');
$text[] = $footnote;

$paragraphOptions = array(
  'textAlign' => 'center',
  'bold' => true,
);
$textFragment->addText($text, $paragraphOptions);

$htmlFragment = new Phpdocx\Elements\WordFragment($docx_b, 'document');

$htmlFragmentString = new Phpdocx\Elements\WordFragment($docx_b, 'document');
$htmlFragmentString->embedHtml('<p style="font-family: verdana; font-size: 11px">HTML tags <b>bold</b></p>');

$textHtml = array();
$textHtml[] = $htmlFragmentString;

$htmlFragment->addText($textHtml);

$valuesTable = array(
  array(
    $textFragment,
  ),
  array(
    '2',
  ),
  array(
    $htmlFragment,
  ),
);

$docx_b->addTable($valuesTable);

$document2 = $docx_b->createDocx();

Phpdocx\Create\CreateDocx::$returnDocxStructure = false;

$merge = new Phpdocx\Utilities\MultiMerge();
$merge->mergeDocx($document1, array($document2), 'example_merge_docx_2.docx', array());