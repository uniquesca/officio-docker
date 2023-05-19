<?php
// convert HTML to DOCX using HTML Extended adding a TOC, headings, sections, images and WordFragments

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

// add a table of contents
$html = '<phpdocx_tablecontents data-autoUpdate="true" data-stylesTOC="../../files/crazyTOC.docx"/>';
$docx->embedHTML($html, array('useHTMLExtended' => true));

// add HTML headings
$html = '
    <h1 style="color: #b70000">1st level heading</h1>
    <h2 style="color: #b70000">2nd level heading</h2>
    <h3 style="color: #b70000">3rd level heading</h3>
';
$docx->embedHTML($html, array('useHTMLExtended' => true));

// add phpdocx headings
$html = '
    <phpdocx_heading data-text="Custom heading" data-level="2" />
';
$docx->embedHTML($html, array('useHTMLExtended' => true));

// add contents and a WordFragment
$listFragment = new Phpdocx\Elements\WordFragment($docx);

$itemList = array(
    'Line 1',
    array(
        'Line A',
        'Line B',
        'Line C'
    ),
    'Line 2',
    'Line 3',
);

// set the style type to 2: ordered list
$listFragment->addList($itemList, 2);

$html = '
    <phpdocx_section data-sectionType="nextPage" data-paperType="A3-landscape" />
    <phpdocx_image data-src="../../img/image.png" data-imageAlign="center" data-scaling="50" />
    <phpdocx_text data-text="Lorem ipsum dolor sit amet." data-underline="single" data-bold="true" data-doubleStrikeThrough="true" />
    <phpdocx_wordfragment data-content="'.base64_encode(serialize($listFragment)).'" />
';
$docx->embedHTML($html, array('useHTMLExtended' => true));

$docx->createDocx('example_embedHTML_6');