<?php
// transform DOCX to HTML using the conversion plugin based on native PHP classes

require_once '../../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();
$docx->transformDocument('../../../files/Text.docx', 'transformDocument_native_2.html', 'native');