<?php
// insert an address value to an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$blockchain = new Phpdocx\Utilities\Blockchain();
$blockchain->insertAddress('../../files/Text.docx', 'example_insertAddress_1', '0xa2a43d3da953ea8cef568b7f1e3aac3efbfde80b');