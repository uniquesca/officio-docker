<?php
// search and highlight a string in an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Utilities\DocxUtilities();
$options = array(
    'highlightColor' => 'green',
    'document' => true,
    'endnotes' => true,
    'comments' => true,
    'headersAndFooters' => true,
    'footnotes' => true,
);
$docx->searchAndHighlight('../../files/second.docx', 'example_highlightedDocx.docx', 'data', $options);