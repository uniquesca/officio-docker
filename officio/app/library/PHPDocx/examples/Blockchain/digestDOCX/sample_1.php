<?php
// generate a digest value from an existing DOCX

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$digest = new Phpdocx\Utilities\Blockchain();
echo $digest->generateDigestDOCX('../../files/Text.docx');