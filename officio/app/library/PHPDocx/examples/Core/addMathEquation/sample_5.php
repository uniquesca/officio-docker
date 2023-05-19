<?php
// insert math equations from MathML applying styles

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$docx->addText('We write a math equation using MathMML:');

$mathML = '<math xmlns="http://www.w3.org/1998/Math/MathML">
	<mrow>
		<mi>A</mi> 
		<mo>=</mo>
		<mfenced open="[" close="]">
			<mtable>
				<mtr>
					<mtd>
						<mi>x</mi>
					</mtd> 
					<mtd>
						<mn>2</mn>
					</mtd>
				</mtr>
				<mtr>
					<mtd>
						<mn>3</mn>
					</mtd>
					<mtd>
						<mi>w</mi>
					</mtd>
				</mtr>
			</mtable>
		</mfenced>
	</mrow>
</math>';

$docx->addMathEquation($mathML, 'mathml', array('align' => 'left'));

$docx->addMathEquation($mathML, 'mathml', array('align' => 'left', 'bold' => true, 'italic' => false));

$docx->addMathEquation($mathML, 'mathml', array('align' => 'center', 'fontSize' => 18, 'underline' => 'single'));

$docx->addMathEquation($mathML, 'mathml', array('align' => 'right', 'color' => 'FF0000', 'fontSize' => 22, 'bold' => true));

$docx->createDocx('example_addMathML_5');