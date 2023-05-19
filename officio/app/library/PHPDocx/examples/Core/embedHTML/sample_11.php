<?php
// convert HTML to DOCX using HTML Extended adding a base CSS

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

// set a base CSS to be applied. These styles can be overwritten using style tags and inline styles
$docx->addBaseCSS('
        p {font-weight: bold;font-size: 18px;}
');

$html = '
    <p>HTML content.</p>
';
$docx->embedHTML($html);

$html = '
    <style>
        p {font-size: 10px;font-style: italic;}
    </style>
    <p>HTML content.</p>
';

$docx->embedHTML($html);

$html = '
    <p style="font-size: 14px;font-weight: normal;">HTML content.</p>
';
$docx->embedHTML($html);

$docx->createDocx('example_embedHTML_11');