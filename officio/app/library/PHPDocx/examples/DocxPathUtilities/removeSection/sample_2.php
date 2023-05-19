<?php
// remove the second section in an existing DOCX and return the output as DOCXStructure

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docxPathUtilities = new Phpdocx\Utilities\DOCXPathUtilities();

Phpdocx\Create\CreateDocx::$returnDocxStructure = true;

$docxStructure = $docxPathUtilities->removeSection('../../files/document_sections.docx', null, 2);

Phpdocx\Create\CreateDocx::$returnDocxStructure = false;