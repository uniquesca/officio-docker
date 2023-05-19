<?php
// replace list placeholders using bulk methods, generate a single DOCX output

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$bulk = new Phpdocx\Utilities\BulkProcessing('../../files/bulk.docx');

$variables =
array(
	array(
    // lists
        array('LIST_A' => array('First item', 'Second item', 'Third item')),
        array('LIST_B' => array('Item 1', 'Item 2', 'Item 3')),
    ),
);

$bulk->replaceList($variables);
$documents = $bulk->getDocuments();

$documents[0]->saveDocx('example_replaceList_1');