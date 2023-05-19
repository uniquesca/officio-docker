<?php
// split an existing DOCX without keeping sections and return the output as DOCXStructure

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docxPathUtilities = new Phpdocx\Utilities\DOCXPathUtilities();

Phpdocx\Create\CreateDocx::$returnDocxStructure = true;

$docxStructures = $docxPathUtilities->splitDocx('../../files/document_sections.docx', null, array('keepSections' => false));

Phpdocx\Create\CreateDocx::$returnDocxStructure = false;