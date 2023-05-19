<?php
// get the paragraphs that contains the text heading from an existing DOCX and insert a new content after each result

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/DOCXPathTemplate.docx');

// get the reference of the nodes
$referenceNode = array(
    'type' => 'paragraph',
    'contains' => 'heading',
);

$queryInfo = $docx->getDocxPathQueryInfo($referenceNode);

// iterate the results to insert a new content after each result
for ($i = 1; $i <= $queryInfo['length']; $i++) {
    $content = new Phpdocx\Elements\WordFragment($docx, 'document');

    // get the reference of the specific node occurrence
    $referenceNode = array(
        'type' => 'paragraph',
        'contains' => 'heading',
        'occurrence' => $i,
    );

    $content->addText('New text', array('sz' => 18));

    $docx->insertWordFragment($content, $referenceNode, 'after');
}

$docx->createDocx('example_getDocxPathQueryInfo_2');