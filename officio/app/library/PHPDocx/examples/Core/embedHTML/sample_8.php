<?php
// convert HTML to DOCX using HTML Extended adding comments, endnotes and footnotes

require_once '../../../Classes/Phpdocx/Create/CreateDocx.php';

$docx = new Phpdocx\Create\CreateDocx();

$html = '
    <head>
        <style>
            p.italicc {font-style: italic;}
        </style>
    </head>
    <phpdocx_comment>
        <p>Here comes the </p>
        <phpdocx_comment_textdocument data-text="&nbsp;comment&nbsp;" data-italic="true" />
        <phpdocx_comment_textcomment>
            <p class="italicc">Text <strong>comment</strong></p>
        </phpdocx_comment_textcomment>
        <p>and some other text.</p>
    </phpdocx_comment>
    <h1 style="color: #b70000">A HTML Extended example.</h1>
    <phpdocx_endnote>
        <p>Here comes the </p>
        <phpdocx_endnote_textdocument data-text="&nbsp;endnote" data-italic="true" />
        <phpdocx_endnote_textendnote>
            <p class="italicc">Text <strong>endnote</strong></p>
        </phpdocx_endnote_textendnote>
        <p>and some other text.</p>
    </phpdocx_endnote>
    <phpdocx_comment>
        <phpdocx_comment_textdocument data-text="Here comes another comment&nbsp;" data-italic="true" data-bold="true" />
        <phpdocx_comment_textcomment>
            <p>This is some HTML code with a link to <a href="https://www.phpdocx.com">phpdocx.com</a> and a random image: 
            <img src="../../img/image.png" width="35" height="35" style="vertical-align: -15px"></p>
        </phpdocx_comment_textcomment>
        <p>&nbsp;and some other text.</p>
    </phpdocx_comment>
    <phpdocx_footnote>
        <p>Here comes the </p>
        <phpdocx_footnote_textdocument data-text="&nbsp;footnote" data-bold="true" />
        <phpdocx_footnote_textfootnote>
            <p>Text <em>footnote</em></p>
        </phpdocx_footnote_textfootnote>
        <p>&nbsp;and some other text.</p>
    </phpdocx_footnote>
';
 

$docx->embedHTML($html, array('useHTMLExtended' => true));

$docx->createDocx('example_embedHTML_8');