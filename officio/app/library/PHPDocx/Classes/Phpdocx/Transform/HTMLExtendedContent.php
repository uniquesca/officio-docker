<?php

namespace Phpdocx\Transform;

use Phpdocx\Create\CreateDocx;
use Phpdocx\Elements\WordFragment;

/**
 * Create Word contents from phpdocx methods
 *
 * @category   Phpdocx
 * @package    trasform
 * @copyright  Copyright (c) Narcea Producciones Multimedia S.L.
 *             (http://www.2mdc.com)
 * @license    phpdocx LICENSE
 * @link       https://www.phpdocx.com
 */
class HTMLExtendedContent
{
    /**
     * @var CreateDocx
     */
    protected $docx;

    /**
     * Constructor
     */
    public function __construct(CreateDocx $docx)
    {
        $this->docx = $docx;
    }

    /**
     * Get content transformed to OOXML
     *
     * @param array $node Node content and attributes
     * @param string $method phpdocx method
     * @param bool $openP if there's open P tags
     * @param array $attributesContent
     * @return string
     */
    public function getContent($node, $method, $openP, $attributesContent = array())
    {
        if (isset($attributesContent['target'])) {
            $wordFragment = new WordFragment($this->docx, $attributesContent['target']);
        } else {
            $wordFragment = new WordFragment($this->docx);
        }

        // clean data- prefix in keys before calling the method
        $attributes = array();
        foreach ($node['attributes'] as $key => $value ) {
            $attributes[str_replace('data-', '', $key)] = $value;
        }

        // add inherited styles
        if (isset($node['properties']) && count($node['properties']) > 0) {
            $contentProperties = $this->transformHTMLToArrayStyles($node['properties'], $method);

            $attributes = array_merge($contentProperties, $attributes);
        }

        // normalize attribute names
        $attributes = $this->normalizeAttributesNames($attributes);

        // normalize attribute values
        $attributes = $this->normalizeAttributesValues($attributes);

        switch ($method) {
            case 'addBookmark':
                $wordFragment->$method($attributes);
                break;
            case 'addComment':
                break;
            case 'addCrossReference':
                $wordFragment->$method($attributes['text'], $attributes);
                break;
            case 'addDateAndHour':
                $wordFragment->$method($attributes);
                break;
            case 'addEndnote':
                break;
            case 'addFooter':
                break;
            case 'addFootnote':
                break;
            case 'addFormElement':
                $wordFragment->$method($attributes['type'], $attributes);
                break;
            case 'addHeading':
                $wordFragment->$method($attributes['text'], (int)$attributes['level'], $attributes);
                break;
            case 'addHeader':
                break;
            case 'addImage':
                $wordFragment->$method($attributes);
                break;
            case 'addLink':
                $wordFragment->$method($attributes['text'], $attributes);
                break;
            case 'addMathEquation':
                $wordFragment->$method($attributes['equation'], $attributes['type'], $attributes);
                break;
            case 'addMergeField':
                $wordFragment->$method($attributes['name'], $attributes);
                break;
            case 'addOnlineVideo':
                $wordFragment->$method($attributes['src'], $attributes);
                break;
            case 'addSection':
                $this->docx->addSection($attributes['sectionType'], $attributes['paperType'], $attributes);
                break;
            case 'addShape':
                $wordFragment->$method($attributes['type'], $attributes);
                break;
            case 'addSimpleField':
                $wordFragment->$method($attributes['fieldName'], $attributes['type'], $attributes['format'], $attributes);
                break;
            case 'addStructuredDocumentTag':
                $wordFragment->$method($attributes['type'], $attributes);
                break;
            case 'addTableContents':
                $stylesTOC = '';
                if (isset($attributes['stylestoc'])) {
                    $stylesTOC = $attributes['stylestoc'];
                    unset($attributes['stylestoc']);
                }
                $options = array();
                if (isset($attributes['autoupdate'])) {
                    $options['autoUpdate'] = (bool)$attributes['autoupdate'];
                    unset($attributes['autoupdate']);
                }
                if (isset($attributes['displaylevels'])) {
                    $options['displayLevels'] = $attributes['displaylevels'];
                    unset($attributes['displaylevels']);
                }
                $wordFragment->$method($options, $attributes, $stylesTOC);
                break;
            case 'addTableFigures':
                $options = array();
                if (isset($attributes['autoupdate'])) {
                    $options['autoUpdate'] = (bool)$attributes['autoupdate'];
                    unset($attributes['autoupdate']);
                }
                if (isset($attributes['scope'])) {
                    $options['scope'] = $attributes['scope'];
                    unset($attributes['scope']);
                }
                $wordFragment->$method($options, $attributes);
                break;
            case 'addTextBox':
                $wordFragment->$method($attributes['content'], $attributes);
                break;
            case 'addText':
                $wordFragment->$method(array($attributes));
                break;
            case 'addWordFragment':
                $wordFragment = unserialize(base64_decode($attributes['content']));
                break;
            case 'addWordML':
                $attributes['wordML'] = str_replace('\\', '', $attributes['wordML']);
                $wordFragment->$method($attributes['wordML']);
                break;
            case 'modifyPageLayout':
                break;
            default:
                break;
        }

        // clean phpdocx internal placeholders
        if ($openP && $method != 'addWordFragment') {
            $content = preg_replace('/__[A-Z]+__/', '', (string)$wordFragment->inlineWordML());
        } else {
            $content = preg_replace('/__[A-Z]+__/', '', (string)$wordFragment);
        }

        return $content;
    }

    /**
     * Get style transformed to OOXML
     *
     * @param string $styleName
     * @param string $value
     * @return string
     */
    public function getStyle($styleName, $value)
    {
        $style = '';

        // normalize value
        $value = $this->normalizeStyleValues($value);

        switch ($styleName) {
            case 'data-em':
                $style = '<w:em w:val="' . $value . '"/>';
                break;
            case 'data-heading-level':
                $style = '<w:outlineLvl w:val="' . $value . '"/>';
                break;
            default:
                break;
        }

        return $style;
    }

    /**
     * Normalize attribute names
     *
     * @param array $attributes
     * @return array
     */
    public function normalizeAttributesNames($attributes)
    {
        $attributesNormalized = array();

        // get the correct attribute setting upper and lower cases
        foreach ($attributes as $key => $value) {
            $keyInitial = $key;

            if ($keyInitial === 'backgroundcolor') {
                $keyInitial = 'backgroundColor';
            }

            if ($keyInitial === 'bordercolor') {
                $keyInitial = 'borderColor';
            }

            if ($keyInitial === 'borderspacing') {
                $keyInitial = 'borderSpacing';
            }

            if ($keyInitial === 'borderstyle') {
                $keyInitial = 'borderStyle';
            }

            if ($keyInitial === 'borderwidth') {
                $keyInitial = 'borderWidth';
            }

            if ($keyInitial === 'characterborder') {
                $keyInitial = 'characterBorder';
            }

            if ($keyInitial === 'columnbreak') {
                $keyInitial = 'columnBreak';
            }

            if ($keyInitial === 'contentverticalalign') {
                $keyInitial = 'contentVerticalAlign';
            }

            if ($keyInitial === 'contextualspacing') {
                $keyInitial = 'contextualSpacing';
            }

            if ($keyInitial === 'dateformat') {
                $keyInitial = 'dateFormat';
            }

            if ($keyInitial === 'defaultvalue') {
                $keyInitial = 'defaultValue';
            }

            if ($keyInitial === 'donotshadeformdata') {
                $keyInitial = 'doNotShadeFormData';
            }

            if ($keyInitial === 'doublestrikethrough') {
                $keyInitial = 'doubleStrikeThrough';
            }

            if ($keyInitial === 'endangle') {
                $keyInitial = 'endAngle';
            }

            if ($keyInitial === 'fieldname') {
                $keyInitial = 'fieldName';
            }

            if ($keyInitial === 'fillcolor') {
                $keyInitial = 'fillColor';
            }

            if ($keyInitial === 'firstlineindent') {
                $keyInitial = 'firstLineIndent';
            }

            if ($keyInitial === 'fontsize') {
                $keyInitial = 'fontSize';
            }

            if ($keyInitial === 'headinglevel') {
                $keyInitial = 'headingLevel';
            }

            if ($keyInitial === 'highlightcolor') {
                $keyInitial = 'highlightColor';
            }

            if ($keyInitial === 'horizontaloffset') {
                $keyInitial = 'horizontalOffset';
            }

            if ($keyInitial === 'imagealign') {
                $keyInitial = 'imageAlign';
            }

            if ($keyInitial === 'indentleft') {
                $keyInitial = 'indentLeft';
            }

            if ($keyInitial === 'indentright') {
                $keyInitial = 'indentRight';
            }

            if ($keyInitial === 'keeplines') {
                $keyInitial = 'keepLines';
            }

            if ($keyInitial === 'keepnext') {
                $keyInitial = 'keepNext';
            }

            if ($keyInitial === 'linebreak') {
                $keyInitial = 'lineBreak';
            }

            if ($keyInitial === 'linespacing') {
                $keyInitial = 'lineSpacing';
            }

            if ($keyInitial === 'listitems') {
                $keyInitial = 'listItems';
            }

            if ($keyInitial === 'mappedfield') {
                $keyInitial = 'mappedField';
            }

            if ($keyInitial === 'marginbottom') {
                $keyInitial = 'marginBottom';
            }

            if ($keyInitial === 'marginfooter') {
                $keyInitial = 'marginFooter';
            }

            if ($keyInitial === 'marginheader') {
                $keyInitial = 'marginHeader';
            }

            if ($keyInitial === 'marginleft') {
                $keyInitial = 'marginLeft';
            }

            if ($keyInitial === 'marginright') {
                $keyInitial = 'marginRight';
            }

            if ($keyInitial === 'margintop') {
                $keyInitial = 'marginTop';
            }

            if ($keyInitial === 'numbercols') {
                $keyInitial = 'numberCols';
            }

            if ($keyInitial === 'numid') {
                $keyInitial = 'numId';
            }

            if ($keyInitial === 'outlinelvl') {
                $keyInitial = 'outlineLvl';
            }

            if ($keyInitial === 'paddingbottom') {
                $keyInitial = 'paddingBottom';
            }

            if ($keyInitial === 'paddingleft') {
                $keyInitial = 'paddingLeft';
            }

            if ($keyInitial === 'paddingright') {
                $keyInitial = 'paddingRight';
            }

            if ($keyInitial === 'paddingtop') {
                $keyInitial = 'paddingTop';
            }

            if ($keyInitial === 'pagebreakbefore') {
                $keyInitial = 'pageBreakBefore';
            }

            if ($keyInitial === 'pagenumbertype') {
                $keyInitial = 'pageNumberType';
            }

            if ($keyInitial === 'papertype') {
                $keyInitial = 'paperType';
            }

            if ($keyInitial === 'paraid') {
                $keyInitial = 'paraId';
            }

            if ($keyInitial === 'placeholdertext') {
                $keyInitial = 'placeholderText';
            }

            if ($keyInitial === 'preserveformat') {
                $keyInitial = 'preserveFormat';
            }

            if ($keyInitial === 'pstyle') {
                $keyInitial = 'pStyle';
            }

            if ($keyInitial === 'referencename') {
                $keyInitial = 'referenceName';
            }

            if ($keyInitial === 'referenceto') {
                $keyInitial = 'referenceTo';
            }

            if ($keyInitial === 'relativetohorizontal') {
                $keyInitial = 'relativeToHorizontal';
            }

            if ($keyInitial === 'relativetovertical') {
                $keyInitial = 'relativeToVertical';
            }

            if ($keyInitial === 'rstyle') {
                $keyInitial = 'rStyle';
            }

            if ($keyInitial === 'sectiontype') {
                $keyInitial = 'sectionType';
            }

            if ($keyInitial === 'selectoptions') {
                $keyInitial = 'selectOptions';
            }

            if ($keyInitial === 'smallcaps') {
                $keyInitial = 'smallCaps';
            }

            if ($keyInitial === 'spacingbottom') {
                $keyInitial = 'spacingBottom';
            }

            if ($keyInitial === 'spacingleft') {
                $keyInitial = 'spacingLeft';
            }

            if ($keyInitial === 'spacingright') {
                $keyInitial = 'spacingRight';
            }

            if ($keyInitial === 'spacingtop') {
                $keyInitial = 'spacingTop';
            }

            if ($keyInitial === 'startangle') {
                $keyInitial = 'startAngle';
            }

            if ($keyInitial === 'streammode') {
                $keyInitial = 'streamMode';
            }

            if ($keyInitial === 'strikethrough') {
                $keyInitial = 'strikeThrough';
            }

            if ($keyInitial === 'styletype') {
                $keyInitial = 'styleType';
            }

            if ($keyInitial === 'tabpositions') {
                $keyInitial = 'tabPositions';
            }

            if ($keyInitial === 'textafter') {
                $keyInitial = 'textAfter';
            }

            if ($keyInitial === 'textalign') {
                $keyInitial = 'textAlign';
            }

            if ($keyInitial === 'textbefore') {
                $keyInitial = 'textBefore';
            }

            if ($keyInitial === 'textdirection') {
                $keyInitial = 'textDirection';
            }

            if ($keyInitial === 'textcomment') {
                $keyInitial = 'textComment';
            }

            if ($keyInitial === 'textdocument') {
                $keyInitial = 'textDocument';
            }

            if ($keyInitial === 'textwrap') {
                $keyInitial = 'textWrap';
            }

            if ($keyInitial === 'underlinecolor') {
                $keyInitial = 'underlineColor';
            }

            if ($keyInitial === 'updatefields') {
                $keyInitial = 'updateFields';
            }

            if ($keyInitial === 'usewordfragmentstyles') {
                $keyInitial = 'useWordFragmentStyles';
            }

            if ($keyInitial === 'verticalalign') {
                $keyInitial = 'verticalAlign';
            }

            if ($keyInitial === 'verticalformat') {
                $keyInitial = 'verticalFormat';
            }

            if ($keyInitial === 'verticaloffset') {
                $keyInitial = 'verticalOffset';
            }

            if ($keyInitial === 'widowcontrol') {
                $keyInitial = 'widowControl';
            }

            if ($keyInitial === 'wordml') {
                $keyInitial = 'wordML';
            }

            if ($keyInitial === 'wordwrap') {
                $keyInitial = 'wordWrap';
            }

            $attributesNormalized[$keyInitial] = $value;
        }

        return $attributesNormalized;
    }

    /**
     * Normalize attribute values
     *
     * @param array $attributes
     * @return array
     */
    public function normalizeAttributesValues($attributes)
    {
        // replace true and false strings by boolean values and JSON
        $attributesNormalized = array();

        $booleanTags = array('bidi', 'bold', 'caps', 'doNotShadeFormData', 'italic', 'keepLines', 'keepNext', 'mappedField', 'pageBreakBefore', 'preserveFormat', 'rtl', 'smallCaps', 'streamMode', 'temporary', 'updateFields', 'vanish', 'verticalFormat', 'widowControl', 'wordWrap');
        $mixedTags   = array('border', 'defaultValue');
        $jsonTags    = array('caption', 'data', 'listItems', 'pageNumberType', 'selectOptions');

        foreach ($attributes as $key => $value) {
            $valueInitial = $value;

            if (in_array($key, $booleanTags)) {
                $valueInitial = ($value == 'true') ? true : false;
            }

            if (in_array($key, $jsonTags)) {
                $valueInitial = json_decode($value, true);
            }

            if (in_array($key, $mixedTags)) {
                if ($value == 'true') {
                    $valueInitial = true;
                } else if ($value == 'false') {
                    $valueInitial = false;
                } else {
                    $valueInitial = $value;
                }
            }

            $attributesNormalized[$key] = $valueInitial;
        }

        return $attributesNormalized;
    }

    /**
     * Normalize styles values
     *
     * @param string $value
     * @return string
     */
    public function normalizeStyleValues($value)
    {
        switch ($value) {
            case 'underdot':
                $value = 'underDot';
                break;
        }

        return $value;
    }

    /**
     * Transform HTML to array styles
     *
     * @param array $properties
     * @param string $method
     * @return array Transformed styles
     */
    protected function transformHTMLToArrayStyles($properties, $method)
    {
        $styles = array();

        if (isset($properties['font_weight']) && ($properties['font_weight'] == 'bold' || $properties['font_weight'] == 'bolder' || $properties['font_weight'] == '700' || $properties['font_weight'] == '800' || $properties['font_weight'] == '900')) {
            $styles['bold'] = true;
        }

        if (isset($properties['font_style']) && ($properties['font_style'] == 'italic' || $properties['font_style'] == 'oblique')) {
            $styles['italic'] = true;
        }

        if (isset($properties['text_transform']) && $properties['text_transform'] == 'uppercase') {
            $styles['caps'] = true;
        }

        if (isset($properties['font_variant']) && $properties['font_variant'] == 'small-caps') {
            $styles['smallCaps'] = true;
        }

        if (isset($properties['text_decoration']) && $properties['text_decoration'] == 'line-through') {
            $styles['strikeThrough'] = true;
        }

        // avoid overwriting the default addLink and addCrossReference colors
        if ($method != 'addLink' && $method != 'addCrossReference') {
            if (isset($properties['color']) && is_array($properties['color']) && count($properties['color']) > 0 && $properties['color']['hex'] != 'transparent') {
                $styles['color'] = strtoupper(str_replace('#', '', $properties['color']['hex']));
            }
        }

        if (isset($properties['font_size']) && $properties['font_size'] != '') {
            $styles['fontSize'] = $properties['font_size'];
        }

        if (isset($properties['text_decoration']) && $properties['text_decoration'] == 'underline') {
            $styles['underline'] = true;
        }

        if (isset($properties['font_family']) && $properties['font_family'] != 'serif') {
            $arrayCSSFonts  = explode(',', $properties['font_family']);
            $font           = trim($arrayCSSFonts[0]);
            $styles['font'] = str_replace('"', '', $font);
        }

        return $styles;
    }
}
