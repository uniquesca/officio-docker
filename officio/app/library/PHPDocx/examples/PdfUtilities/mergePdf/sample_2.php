<?php
// merge three PDF and return the output as stream

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

// the stream mode can also be enabled in config/phpdocxconfig.ini
Phpdocx\Create\CreateDocx::$streamMode = true;

$merge = new Phpdocx\Utilities\MultiMerge();
$merge->mergePdf(array('../../files/Test.pdf', '../../files/Test2.pdf', '../../files/Test3.pdf'), 'example_merge_pdf.pdf');