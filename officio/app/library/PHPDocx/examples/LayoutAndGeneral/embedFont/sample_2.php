<?php
// embed two fonts and add HTML applying the embedded fonts

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

// embed TTF fonts
$docx->embedFont('../../files/fonts/Pacifico.ttf', 'Pacifico');
$docx->embedFont('../../files/fonts/KOMIKRAK.ttf', 'Komika Krak');

// import HTML applying the new fonts to some contents
$hml = '
	<style>
		.nf {
			font-family: "Pacifico";
		}
		.nfs {
			font-family: "Komika Krak";
		}
	</style>
	<p>Text content using the default font family when importing HTML.</p>
	<p class="nf">Text content using Pacifico font.</p>
	<p class="nf">My text content <span class="nfs">Komika Krak</span></p>
';

$docx->embedHTML($hml);

// generate the DOCX
$docx->createDocx('example_embedFont_2');