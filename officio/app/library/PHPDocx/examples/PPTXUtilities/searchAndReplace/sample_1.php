<?php
// replace string values in the first slide from an existing PPTX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$newPPTX = new Phpdocx\Utilities\PPTXUtilities();

$data = array('Welcome to PowerPoint' => 'Welcome to Phpdocx');

$newPPTX->searchAndReplace('../../files/data_powerpoint.pptx', 'example_searchAndReplace_1.pptx', $data, array('slideNumber' => 1));