<?php
// generate a new DOCX and return it as a stream

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

// the stream mode can also be enabled in config/phpdocxconfig.ini
Phpdocx\Create\CreateDocx::$streamMode = true;

$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, ' .
    'sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut ' .
    'enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut' .
    'aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit ' .
    'in voluptate velit esse cillum dolore eu fugiat nulla pariatur. ' .
    'Excepteur sint occaecat cupidatat non proident, sunt in culpa qui ' .
    'officia deserunt mollit anim id est laborum.';

$paragraphOptions = array(
    'bold' => true,
    'font' => 'Arial'
);

$docx->addText($text, $paragraphOptions);

$docx->createDocx('example_zipStream_1');