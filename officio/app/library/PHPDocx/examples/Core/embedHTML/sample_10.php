<?php
// convert HTML to DOCX using HTML Extended adding custom styles to paragraphs, rows and cells

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$html = '
    <p>HTML content.</p>
    <p data-ppr="'.htmlentities('<w:pageBreakBefore val="on"/>').'">HTML with <span data-rpr="'.htmlentities('<w:strike/>').'">custom</span> <span data-rpr="'.htmlentities('<w:dstrike/>').'">attributes</span> in tags.</p>
';

$html .= '<table border="1" style="border-collapse: collapse" width="600">
            <tbody>
                <tr width="600" data-trpr="'.htmlentities('<w:trHeight w:val="1200" w:hRule="exact"/>').'">
                    <td style="background-color: yellow" data-tcpr="'.htmlentities('<w:tcFitText w:val="on"/>').'">1_1</td>
                    <td rowspan="3" colspan="2">1_2</td>
                </tr>
                <tr width="600">
                    <td>Some random text.</td>
                </tr>
                <tr width="600">
                    <td>
                        <ul>
                            <li>One</li>
                            <li>Two <b>and a half</b></li>
                        </ul>
                    </td>
                </tr>
                <tr width="600">
                    <td>3_2</td>
                    <td>3_3</td>
                    <td>3_3</td>
                </tr>
            </tbody>
        </table>';

$docx->embedHTML($html, array('useHTMLExtended' => true));

$docx->createDocx('example_embedHTML_10');