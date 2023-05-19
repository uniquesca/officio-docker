<?php
// merge two DOCX after a specific position of an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

// get the specific position to merge the DOCX
$referenceNode = array(
    'type' => 'paragraph',
    'occurrence' => 1,
    'contains' => 'Another bookmark',
);

$merge = new Phpdocx\Utilities\MultiMerge();
$merge->mergeDocxAt('../../files/second.docx', array('../../files/Text.docx', '../../files/SimpleExample.docx'), 'example_merge_docx_3.docx', $referenceNode, array());