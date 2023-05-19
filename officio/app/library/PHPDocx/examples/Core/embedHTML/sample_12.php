<?php
// convert HTML to DOCX using HTML Extended with custom list styles that override item types

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

// custom options
$latinListOptions = array();
$latinListOptions[0]['type'] = 'lowerLetter';
$latinListOptions[0]['format'] = '%1.';
$latinListOptions[1]['type'] = 'lowerRoman';
$latinListOptions[1]['format'] = '%1.%2.';

// override
$overrideListStyleOptionsFirst = array();
$overrideListStyleOptionsFirst[0]['type'] = 'upperRoman';
$overrideListStyleOptionsFirst[0]['format'] = '%1.';
$overrideListStyleOptionsFirst[1]['type'] = 'lowerRoman';
$overrideListStyleOptionsFirst[1]['format'] = '%2.';

$overrideListStyleOptionsSecond = array();
$overrideListStyleOptionsSecond[0]['type'] = 'upperLetter';
$overrideListStyleOptionsSecond[0]['format'] = '%1.';

$overrideListStyle = array();
$overrideListStyle[0] = array(
    'listOptions' => $overrideListStyleOptionsFirst,
    'name' => 'latinUR',
);
$overrideListStyle[1] = array(
    'listOptions' => $overrideListStyleOptionsSecond,
    'name' => 'latinUL',
);

// create the list style with name: latin
$docx->createListStyle('latin', $latinListOptions, $overrideListStyle);

$html = '
<ul class="latin">
    <li>First item.</li>
    <li>Second item with subitems:
        <ul class="latinUR">
            <li>First subitem.</li>
            <li>Second subitem.</li>
            <li>Third subitem.
        </ul>
    </li>
    <li>
    <li>Third item with subitems and other styles:
        <ul class="latinUL">
            <li>First subitem.</li>
            <li>Second subitem.</li>
        </ul>
    </li>
    <li>Last item.</li>
</ul>';
$docx->embedHTML($html, array('customListStyles' => true, 'useHTMLExtended' => true));

$docx->createDocx('example_embedHTML_12');