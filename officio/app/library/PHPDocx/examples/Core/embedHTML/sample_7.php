<?php
// convert HTML to DOCX using HTML Extended adding headers and footers

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$html = '
    <head>
        <style>
            p {font-style: italic;}
            p.header {font-size: 20px; font-weight: bold;}
            div.footer {font-size: 10px;}
        </style>
    </head>
    <phpdocx_header data-type="default">
        <p class="header">
            Custom header <strong>with strong style</strong>
            <phpdocx_link data-text="External link" data-url="https://www.phpdocx.com" />
        </p>
    </phpdocx_header>
    <p>Lorem ipsum dolor sit amet.</p>
    <phpdocx_footer>
        <div class="footer">
            <table border="1" style="border-collapse: collapse" width="600">
                <tbody>
                    <tr width="600">
                        <td>Cell A</td>
                        <td><img src="../../img/image.png" width="35" height="35" style="vertical-align: -15px"></td>
                        <td><phpdocx_pagenumber data-target="defaultFooter" data-type="page-of" data-textAlign="right" /></td>
                    </tr>
                </tbody>
            </table>
            <phpdocx_image data-src="../../img/image.png" data-imageAlign="center" data-scaling="50" data-target="defaultFooter" />
        </div>
    </phpdocx_footer>
';

$docx->embedHTML($html, array('useHTMLExtended' => true));

$docx->createDocx('example_embedHTML_7');