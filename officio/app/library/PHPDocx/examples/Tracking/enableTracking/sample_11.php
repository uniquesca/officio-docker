<?php
// add tracked and not tracked contents to the DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/TemplateWordFragment_2.docx');

$docx->addPerson(array('author' => 'phpdocx'));

$docx->enableTracking(array('author' => 'phpdocx'));

$wf = new Phpdocx\Elements\WordFragment($docx, 'footnote');

$image = new Phpdocx\Elements\WordFragment($docx, 'footnote');

$image->addImage(array('src' => '../../img/image.png' , 'scaling' => 10));

$link = new Phpdocx\Elements\WordFragment($docx, 'footnote');
$link->addLink('link to Google', array('url'=> 'http://www.google.es', 'color' => '0000FF', 'u' => 'single'));

$text = array();

$text[] = $image;
$text[] = array(
    'text' => 'I am going to write a link: ',
    'b' => 'on',
);
$text[] = $link;
$text[] = array(
    'text' => ' to illustrate how to include links in a footnote. '
);
$text[] = array(
    'text' => ' As you may see it is extremely simple to do so and can be done with any other Word element.',
);

$wf->addText($text);

$docx->replaceVariableByWordFragment(array('INLINEFRAGMENT' => $wf), array('type' => 'inline', 'target' => 'footnote'));

$docx->disableTracking();

$docx->createDocx('example_enableTracking_11');