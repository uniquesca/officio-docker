<?php
// remove chapter from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Utilities\DocxUtilities();
$docx->removeChapter('../../files/headings.docx', 'example_removeChapter.docx', 'First Heading');