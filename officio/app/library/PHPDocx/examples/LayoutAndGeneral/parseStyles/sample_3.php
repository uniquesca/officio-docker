<?php
// parse styles from an existing DOCX with character styles

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/TemplateCharacterStyles.docx');

$docx->parseStyles();

$docx->createDocx('example_parseStyles_3');