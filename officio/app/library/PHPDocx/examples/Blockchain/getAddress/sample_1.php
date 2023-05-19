<?php
// insert an address value to an existing DOCX and return it

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$blockchain = new Phpdocx\Utilities\Blockchain();
// insert the address value
$blockchain->insertAddress('../../files/Text.docx', 'example_getAddress_1', '0xa2a43d3da953ea8cef568b7f1e3aac3efbfde80b');

// get the address value
echo $blockchain->getAddress('example_getAddress_1.docx');