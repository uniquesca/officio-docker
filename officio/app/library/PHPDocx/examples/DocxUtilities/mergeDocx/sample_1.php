<?php
// merge three DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$merge = new Phpdocx\Utilities\MultiMerge();
$merge->mergeDocx('../../files/Text.docx', array('../../files/second.docx', '../../files/SimpleExample.docx'), 'example_merge_docx_1.docx', array());