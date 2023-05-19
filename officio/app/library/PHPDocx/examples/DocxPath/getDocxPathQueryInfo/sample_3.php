<?php
// generate a DOCX adding two paragraphs, get the paragraph contents and replace a text string in it. Useful for complex replacements

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$text = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, ' .
    'sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut ' .
    'enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut' .
    'aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit ' .
    'in voluptate velit esse cillum dolore eu fugiat nulla pariatur. ' .
    'Excepteur sint occaecat cupidatat non proident, sunt in culpa qui ' .
    'officia deserunt mollit anim id est laborum.';

$paragraphOptions = array(
    'bold' => true,
    'font' => 'Arial',
);

$docx->addText($text, $paragraphOptions);

$text = 'Sed ut perspiciatis unde omnis iste natus error sit voluptatem ' . 
    'accusantium doloremque laudantium, totam rem aperiam, eaque ipsa quae ' . 
    'ab illo inventore veritatis et quasi architecto beatae vitae dicta sunt ' . 
    'explicabo. Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut ' . 
    'odit aut fugit, sed quia consequuntur magni dolores eos qui ratione voluptatem ' . 
    'sequi nesciunt.';

$docx->addText($text, $paragraphOptions);

// get the reference of the nodes
$referenceNode = array(
    'type' => 'paragraph',
);

$queryInfo = $docx->getDocxPathQueryInfo($referenceNode);

// change the content of second element and add it to the DOCX replacing the existing one
$secondElementContent = $queryInfo['elements'][1]->ownerDocument->saveXml($queryInfo['elements'][1]);
$secondElementChanged = str_replace('unde omnis', 'other text', $secondElementContent);

$wordML = new Phpdocx\Elements\WordFragment();
$wordML->addWordML($secondElementChanged);

// get the reference of the node to be replaced
$referenceNode = array(
    'type' => 'paragraph',
    'occurrence' => 2,
);

$docx->replaceWordContent($wordML, $referenceNode);

$docx->createDocx('example_getDocxPathQueryInfo_3');