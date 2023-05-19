<?php
// generate a digest value from an existing DOCX using only document, headers and footers contents to generate it

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$digest = new Phpdocx\Utilities\Blockchain();
echo $digest->generateDigestDOCX('../../files/Text.docx', array('document', 'headers', 'footers'));	