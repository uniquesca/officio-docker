<?php
// generate a transitional variant DOCX from a strict variant DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Utilities\DOCXStrict();
var_dump($docx->checkVariant('../../files/strict.docx'));

$docx->generateTransitionalVariant('../../files/strict.docx', 'transitional_variant_output.docx');