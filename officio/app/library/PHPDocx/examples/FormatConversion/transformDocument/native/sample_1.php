<?php
// transform HTML to DOCX using the conversion plugin based on native PHP classes

require_once '../../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();
$docx->transformDocument('../../../files/Test.html', 'transformDocument_native_1.docx', 'native');