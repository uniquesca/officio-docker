<?php
// replace table variables (placeholders) in headers, footers and document from an existing DOCX using WordFragments

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocxFromTemplate('../../files/TemplateSimpleTable_header_footer.docx');

$link1 = new Phpdocx\Elements\WordFragment($docx);
$linkOptions = array('url'=> 'http://www.google.es', 
    'color' => '0000FF', 
    'underline' => 'single',
);
$link1->addLink('link to product A', $linkOptions);

$link2 = new Phpdocx\Elements\WordFragment($docx);
$linkOptions = array('url'=> 'http://www.google.es', 
    'color' => '0000FF', 
    'underline' => 'single',
);
$link2->addLink('link to product B', $linkOptions);

$link3 = new Phpdocx\Elements\WordFragment($docx);
$linkOptions = array('url'=> 'http://www.google.es', 
    'color' => '0000FF', 
    'underline' => 'single',
);
$link3->addLink('link to product C', $linkOptions);

$image = new Phpdocx\Elements\WordFragment($docx);
$imageOptions = array(
    'src' => '../../img/image.png',
    'scaling' => 30,
    );
$image->addImage($imageOptions);

$dataHeader = array(
	        array(
	            'ITEM_HEADER' => $link1,
	            'REFERENCE_HEADER' => $image,
	        ),
	        array(
	            'ITEM_HEADER' => $link2,
	            'REFERENCE_HEADER' => $image,
	        ),
	        array(
	            'ITEM_HEADER' => $link3,
	            'REFERENCE_HEADER' => $image,
	        )
        );

$dataBody = array(
	        array(
	            'ITEM' => $link1,
	            'REFERENCE' => $image,
	        ),
	        array(
	            'ITEM' => $link2,
	            'REFERENCE' => $image,
	        ),
	        array(
	            'ITEM' => $link3,
	            'REFERENCE' => $image,
	        )
        );

$dataFooter = array(
	        array(
	            'ITEM_FOOTER' => $link1,
	            'REFERENCE_FOOTER' => $image,
	        ),
	        array(
	            'ITEM_FOOTER' => $link2,
	            'REFERENCE_FOOTER' => $image,
	        ),
	        array(
	            'ITEM_FOOTER' => $link3,
	            'REFERENCE_FOOTER' => $image,
	        )
        );

// replace the table variable in headers
$docx->replaceTableVariable($dataHeader, array('parseLineBreaks' => true, 'target' => 'header'));
// replace the table variable in the document
$docx->replaceTableVariable($dataBody, array('parseLineBreaks' => true));
// replace the table variable in footers
$docx->replaceTableVariable($dataFooter, array('parseLineBreaks' => true, 'target' => 'footer'));

$docx->createDocx('example_replaceTableVariable_4');