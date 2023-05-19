<?php
// add a macro from an existing DOCM

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx('docm');

$docx->addMacroFromDoc('../../files/fileMacros.docm');

$docx->createDocx('example_addMacroFromDoc_1');
// documents with macros use docm as extension
rename('example_addMacroFromDoc_1.docx', 'example_addMacroFromDoc_1.docm');