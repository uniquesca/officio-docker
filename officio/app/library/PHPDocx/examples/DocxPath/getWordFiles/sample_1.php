<?php
// get the styles file from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/DOCXPathTemplate.docx');

$contents = $docx->getWordFiles('word/styles.xml');

echo $contents;