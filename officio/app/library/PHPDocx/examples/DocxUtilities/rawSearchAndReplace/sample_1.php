<?php
// do a raw replacement searching in all XML and rels files of an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$newDocx = new Phpdocx\Utilities\DocxUtilities();

$newDocx->rawSearchAndReplace('../../files/linked_image.docx', 'example_rawSearchAndReplace.docx', '$URL$', 'http://www.google.es');