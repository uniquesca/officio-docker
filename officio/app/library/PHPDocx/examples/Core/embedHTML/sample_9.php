<?php
// convert HTML to DOCX using HTML Extended adding headers and footers setting custom tags for both contents

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

// set custom tags for header and footer contents (default tags are phpdocx_header and phpdocx_footer)
Phpdocx\Transform\HTMLExtended::$tagsBlock['header'] = 'addHeader';
Phpdocx\Transform\HTMLExtended::$tagsBlock['footer'] = 'addFooter';

$html = '
    <head>
        <style>
            p {font-style: italic;}
            p.header {font-size: 20px; font-weight: bold;}
            div.footer {font-size: 10px;}
        </style>
    </head>
    <header data-type="default">
        <p class="header">
            Custom header <strong>with strong style</strong>
            <phpdocx_link data-text="External link" data-url="https://www.phpdocx.com" />
        </p>
    </header>
    <p>Lorem ipsum dolor sit amet.</p>
    <math xmlns="http://www.w3.org/1998/Math/MathML">
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
    </math>
    <footer>
        <div class="footer">
            <table border="1" style="border-collapse: collapse" width="600">
                <tbody>
                    <tr width="600">
                        <td>Cell A</td>
                        <td><img src="../../img/image.png" width="35" height="35" style="vertical-align: -15px"></p></td>
                        <td><phpdocx_pagenumber data-target="defaultFooter" data-type="page-of" data-textAlign="right" /></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </footer>
';

$docx->embedHTML($html, array('useHTMLExtended' => true));

$docx->createDocx('example_embedHTML_9');