<?php
// generate a DOCX from a TXT file

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$docx->txt2docx('../../files/Text.txt');

$docx->createDocx('example_txt2docx');