<?php
// add line numbering to an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$newDocx = new Phpdocx\Utilities\DocxUtilities();
$newDocx->setLineNumbering('../../files/second.docx', 'example_setLineNumbering.docx', array('start' => 25));