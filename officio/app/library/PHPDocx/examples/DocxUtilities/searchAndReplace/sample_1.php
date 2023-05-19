<?php
// search and replace a string in an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$newDocx = new Phpdocx\Utilities\DocxUtilities();

$options = array(
    'document' => true,
    'endnotes' => true,
    'comments' => true,
    'headersAndFooters' => true,
    'footnotes' => true,
);
$newDocx->searchAndReplace('../../files/second.docx', 'example_replacedDocx.docx', 'data', 'required data', $options);