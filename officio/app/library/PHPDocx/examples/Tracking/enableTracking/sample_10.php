<?php
// add tracked and not tracked contents to the DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/TemplateWordFragment_1.docx');

$docx->addPerson(array('author' => 'phpdocx'));

$docx->enableTracking(array('author' => 'phpdocx'));

$wf = new Phpdocx\Elements\WordFragment($docx, 'document');

$image = new Phpdocx\Elements\WordFragment($docx, 'document');
$image->addImage(array('src' => '../../img/image.png' , 'scaling' => 50, 'float' => 'right', 'textWrap' => 1));

$link = new Phpdocx\Elements\WordFragment($docx, 'document');
$link->addLink('link to Google', array('url'=> 'http://www.google.es', 'color' => '0000FF', 'u' => 'single'));

$footnote = new Phpdocx\Elements\WordFragment($docx, 'document');
$footnote->addFootnote(
    array(
        'textDocument' => 'here it is',
        'textFootnote' => 'This is the footnote text.',
    )
);

$text = array();

$text[] = $image;
$text[] = array(
    'text' => 'I am going to write a link: ',
    'b' => 'on',
);
$text[] = $link;
$text[] = array(
    'text' => ' to illustrate how to include links. '
);
$text[] = array(
    'text' => ' As you may see is extremely simple to do so and can be done with any other Word element. For example to include  a footnote is also as simple as this: ',
);
$text[] = $footnote;
$text[] = array(
    'text' => ' , as you see there is a footnote at the bottom of the page. ',
    'color' => 'B70000',
);

$wf->addText($text);

$docx->replaceVariableByWordFragment(array('WORDFRAGMENT' => $wf), array('type' => 'block'));

$docx->disableTracking();

$docx->createDocx('example_enableTracking_10');