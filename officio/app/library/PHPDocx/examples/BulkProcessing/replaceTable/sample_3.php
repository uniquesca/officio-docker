<?php
// replace table placeholders by WordFragments using bulk methods, generate a single DOCX output

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$bulk = new Phpdocx\Utilities\BulkProcessing('../../files/bulk.docx');

$textA = new Phpdocx\Elements\WordFragment();
$textA->addText('1A45A', array('italic' => true, 'font' => 'Arial'));

$textB = new Phpdocx\Elements\WordFragment();
$textB->addText('EA78A', array('italic' => true, 'font' => 'Arial'));

$textC = new Phpdocx\Elements\WordFragment();
$textC->addText('YA99A', array('italic' => true, 'font' => 'Arial'));

$variables =
array(
    array(
        array(
            array(
                'TABLE_1_PROJECT' => 'Project A',
                'TABLE_1_DATE' => date('Y/m/d'),
                'TABLE_1_ID' => $textA,
            ),
            array(
                'TABLE_1_PROJECT' => 'Project B',
                'TABLE_1_DATE' => date('Y/m/d'),
                'TABLE_1_ID' => $textB,
            ),
            array(
                'TABLE_1_PROJECT' => 'Project C',
                'TABLE_1_DATE' => date('Y/m/d'),
                'TABLE_1_ID' => $textC,
           ),
        ),
        array(
            array(
                'TABLE_2_ID' => 'ID122',
                'TABLE_2_NAME' => 'Name A',
                'TABLE_2_PROJECT' => 'Project 2A',
                'TABLE_2_DATE_START' => date('Y/m/d'),
                'TABLE_2_DATE_END' => date('Y/m/d'),
            ),
            array(
                'TABLE_2_ID' => 'ID123',
                'TABLE_2_NAME' => 'Name B',
                'TABLE_2_PROJECT' => 'Project 2B',
                'TABLE_2_DATE_START' => date('Y/m/d'),
                'TABLE_2_DATE_END' => date('Y/m/d'),
            ),
            array(
                'TABLE_2_ID' => 'ID124',
                'TABLE_2_NAME' => 'Name C',
                'TABLE_2_PROJECT' => 'Project 2C',
                'TABLE_2_DATE_START' => date('Y/m/d'),
                'TABLE_2_DATE_END' => date('Y/m/d'),
            ),
            array(
                'TABLE_2_ID' => 'ID125',
                'TABLE_2_NAME' => 'Name D',
                'TABLE_2_PROJECT' => 'Project 2D',
                'TABLE_2_DATE_START' => date('Y/m/d'),
                'TABLE_2_DATE_END' => date('Y/m/d'),
            ),
            array(
                'TABLE_2_ID' => 'ID126',
                'TABLE_2_NAME' => 'Name E',
                'TABLE_2_PROJECT' => 'Project 2E',
                'TABLE_2_DATE_START' => date('Y/m/d'),
                'TABLE_2_DATE_END' => date('Y/m/d'),
            ),
        ),
    ),
);

$bulk->replaceTable($variables);
$documents = $bulk->getDocuments();

$documents[0]->saveDocx('example_replaceTable_3');