<?php
// generate a digest value from an existing PDF

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$digest = new Phpdocx\Utilities\Blockchain();
echo $digest->generateDigestPDF('../../files/Test.pdf');