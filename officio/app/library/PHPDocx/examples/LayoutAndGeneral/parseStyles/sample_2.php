<?php
// parse styles from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/simpleTemplate.docx');

// parse styles of the current template
// notice that besides the original template styles PHPDocX has included additional useful styles
$docx->parseStyles();

$docx->createDocx('example_parseStyles_2');
