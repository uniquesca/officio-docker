<?php

namespace Phpdocx\Transform;

/**
 * Relate HTML tags to phpdocx methods and CSS styles to Word styles
 *
 * @category   Phpdocx
 * @package    trasform
 * @copyright  Copyright (c) Narcea Producciones Multimedia S.L.
 *             (http://www.2mdc.com)
 * @license    phpdocx LICENSE
 * @link       https://www.phpdocx.com
 */
class HTMLExtended
{
    /**
     * CSS extended => style
     * @var array
     */
    public static $cssExtendedStyles = array(
        'data-em'            => 'data-em',
        'data-label'         => 'data-label',
        'data-heading-level' => 'data-heading-level',
        "data-listppr"       => '0',
        "data-listrpr"       => '0',
        "data-ppr"           => '0',
        "data-rpr"           => '0',
        "data-tblpr"         => '0',
        "data-tcpr"          => '0',
        "data-trpr"          => '0',
        "data-style"         => '',
        "src"                => 'src',
    );

    /**
     * CSS extended inherited styles
     * @var array
     */
    public static $cssExtendedStylesInherited = array(
        'data-em',
    );

    /**
     * HTML inline tags => phpdocx method
     * @var array
     */
    public static $tagsInline = array(
        'meta'                          => 'meta',
        'phpdocx_bookmark'              => 'addBookmark',
        'phpdocx_break'                 => 'addBreak',
        'phpdocx_comment_textdocument'  => 'addCommentTextDocument',
        'phpdocx_crossreference'        => 'addCrossReference',
        'phpdocx_dateandhour'           => 'addDateAndHour',
        'phpdocx_endnote_textdocument'  => 'addEndnoteTextDocument',
        'phpdocx_footnote_textdocument' => 'addFootnoteTextDocument',
        'phpdocx_formelement'           => 'addFormElement',
        'phpdocx_heading'               => 'addHeading',
        'phpdocx_image'                 => 'addImage',
        'phpdocx_link'                  => 'addLink',
        'phpdocx_mathequation'          => 'addMathEquation',
        'phpdocx_mergefield'            => 'addMergeField',
        'phpdocx_modifypagelayout'      => 'modifyPageLayout',
        'phpdocx_onlinevideo'           => 'addOnlineVideo',
        'phpdocx_pagenumber'            => 'addPageNumber',
        'phpdocx_section'               => 'addSection',
        'phpdocx_shape'                 => 'addShape',
        'phpdocx_simplefield'           => 'addSimpleField',
        'phpdocx_structureddocumenttag' => 'addStructuredDocumentTag',
        'phpdocx_tablecontents'         => 'addTableContents',
        'phpdocx_tablefigures'          => 'addTableFigures',
        'phpdocx_text'                  => 'addText',
        'phpdocx_textbox'               => 'addTextBox',
        'phpdocx_wordfragment'          => 'addWordFragment',
        'phpdocx_wordml'                => 'addWordML',
        'title'                         => 'title',
    );

    /**
     * HTML block tags => phpdocx method
     * @var array
     */
    public static $tagsBlock = array(
        'phpdocx_comment'               => 'addComment',
        'phpdocx_comment_textcomment'   => 'addCommentTextComment',
        'phpdocx_endnote'               => 'addEndnote',
        'phpdocx_endnote_textendnote'   => 'addEndnoteTextEndnote',
        'phpdocx_footer'                => 'addFooter',
        'phpdocx_footnote_textfootnote' => 'addFootnoteTextFootnote',
        'phpdocx_footnote'              => 'addFootnote',
        'phpdocx_header'                => 'addHeader',
        'svg'                           => 'svg',
    );

    /**
     * Getter $cssExtendedStyles
     * @return array
     */
    public static function getCSSExtendedStyles()
    {
        return self::$cssExtendedStyles;
    }

    /**
     * Getter $cssExtendedStyles
     * @return array
     */
    public static function getCSSExtendedStylesInherited()
    {
        return self::$cssExtendedStylesInherited;
    }

    /**
     * Getter $tagsInline
     * @return array
     */
    public static function getTagsInline()
    {
        return self::$tagsInline;
    }

    /**
     * Getter $tagsBlock
     * @return array
     */
    public static function getTagsBlock()
    {
        return self::$tagsBlock;
    }
}
