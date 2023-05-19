<?php
// replace list placeholders using bulk methods, generate a DOCX for each array value

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$bulk = new Phpdocx\Utilities\BulkProcessing('../../files/bulk.docx');

$variables =
array(
    // first DOCX
	array(
        array('LIST_A' => array('First item', 'Second item', 'Third item')),
        array('LIST_B' => array('Item 1', 'Item 2', 'Item 3')),
    ),
    // second DOCX
    array(
        array('LIST_A' => array('1st item', '2nd item', '3rd item')),
        array('LIST_B' => array('Item A', 'Item B', 'Item C')),
    ),
    // third DOCX
    array(
        array('LIST_A' => array('I item', 'II item', 'III item')),
        array('LIST_B' => array('Item I', 'Item II', 'Item III')),
    ),
);

$bulk->replaceList($variables);
$documents = $bulk->getDocuments();

for ($i = 0; $i < count($documents); $i++) {
    $documents[$i]->saveDocx('example_replaceList_2_' . ($i + 1));
}