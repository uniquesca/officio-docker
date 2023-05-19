<?php
// remove all contents between paragraphs that contain A level 2 heading and Another heading from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/DOCXPathTemplate.docx');

// get the reference node to be removed
$referenceNode = array(
    'customQuery' => '//*[preceding-sibling::w:p[w:r/w:t[text()[contains(.,"A level 2 heading")]]] and following-sibling::w:p[w:r/w:t[text()[contains(.,"Another heading")]]]]',
);

$docx->removeWordContent($referenceNode);

$docx->createDocx('example_removeWordContent_11');