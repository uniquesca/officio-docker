<?php
// process and existing template cleaning variables in it

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

// get all placeholders and optimize them
$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/template_not_optimized.docx');
$documentVariables = $docx->getTemplateVariables();

$docx = new Phpdocx\Utilities\ProcessTemplate();
$docx->optimizeTemplate('../../files/template_not_optimized.docx', 'template_optimized.docx', $documentVariables['document']);