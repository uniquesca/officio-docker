<?php
// insert math equations from MathML applying align styles

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

$docx->addMathEquation($mathML, 'mathml', array('align' => 'center'));

$docx->addMathEquation($mathML, 'mathml', array('align' => 'right'));

$docx->createDocx('example_addMathML_4');