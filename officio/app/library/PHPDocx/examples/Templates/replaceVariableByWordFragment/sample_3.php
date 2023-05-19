<?php
// replace a text variable (placeholder) with a WordFragment in headers, footers and document from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/TemplateWordFragment_3.docx');

// create the Word fragment that is going to replace the variable
$wf = new Phpdocx\Elements\WordFragment($docx, 'document');

// create an image fragment
$image = new Phpdocx\Elements\WordFragment($docx, 'document');
$image->addImage(array('src' => '../../img/image.png' , 'scaling' => 50, 'float' => 'right', 'textWrap' => 1));
// and also a link fragment
$link = new Phpdocx\Elements\WordFragment($docx, 'document');
$link->addLink('link to Google', array('url'=> 'http://www.google.es', 'color' => '0000FF', 'u' => 'single'));

// combine them to create a paragraph
$text = array();

$text[] = $image;
$text[] = array(
    'text' => 'I am going to write a link: ',
    'bold' => true,
);
$text[] = $link;
$text[] = array(
    'text' => ' to illustrate how to include links. '
);
$text[] = array(
    'text' => ' As you may see is extremely simple to do so and can be done with any other Word element.',
);
// insert all the content in the Word fragment we are going to use for replacement
$wf->addText($text);

// replace the text variable in headers
$docx->replaceVariableByWordFragment(array('WORDFRAGMENT_HEADER' => $wf), array('type' => 'block', 'target' => 'header'));
// replace the text variable in footers
$docx->replaceVariableByWordFragment(array('WORDFRAGMENT_FOOTER' => $wf), array('type' => 'block', 'target' => 'footer'));
// replace the text variable in the document
$docx->replaceVariableByWordFragment(array('WORDFRAGMENT_BODY' => $wf), array('type' => 'block'));

$docx->createDocx('example_replaceVariableByWordFragment_3');