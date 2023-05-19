<?php
namespace Phpdocx\Transform;

use DOMDocument;
use DOMXPath;
use Imagick;
use Phpdocx\Create\CreateDocx;
use Phpdocx\Utilities\DOCXStructure;

/**
 * Transform DOCX to PDF using native PHP classes and DOMPDF
 *
 * @category   Phpdocx
 * @package    transform
 * @copyright  Copyright (c) Narcea Producciones Multimedia S.L.
 *             (http://www.2mdc.com)
 * @license    phpdocx LICENSE
 * @link       https://www.phpdocx.com
 */

require_once dirname(__FILE__) . '/../Create/CreateDocx.php';

class TransformDocAdvDOMPDF
{
    /**
     *
     * @access protected
     * @var string
     */
    protected $commentsContent = null;

    /**
     *
     * @access protected
     * @var array
     */
    protected $commentsIndex = array();

    /**
     *
     * @access protected
     * @var array
     */
    protected $complexField = null;

    /**
     *
     * @access protected
     * @var array
     */
    protected $css = array();

    /**
     *
     * @access protected
     * @var string
     */
    protected $cssDefaultStyles;

    /**
     *
     * @access protected
     * @var array
     */
    protected $currentList;

    /**
     *
     * @access protected
     * @var int
     */
    protected $currentSection;

    /**
     *
     * @access protected
     * @var array
     */
    protected $currentStylesPpr;

    /**
     *
     * @access protected
     * @var array
     */
    protected $currentStylesRpr;

    /**
     *
     * @access protected
     * @var array
     */
    protected $currentStylesSection;

    /**
     *
     * @access protected
     * @var array
     */
    protected $defaultStyles;

    /**
     *
     * @access protected
     * @var DOMDocument
     */
    protected $documentXmlRelsDOM;

    /**
     *
     * @access protected
     * @var DOCXStructure
     */
    protected $docxStructure;

    /**
     *
     * @access protected
     * @var string
     */
    protected $endnotesContent = null;

    /**
     *
     * @access protected
     * @var array
     */
    protected $endnotesIndex = array();

    /**
     *
     * @access protected
     * @var array
     */
    protected $footersContent = array();

    /**
     *
     * @access protected
     * @var string
     */
    protected $footnotesContent = null;

    /**
     *
     * @access protected
     * @var array
     */
    protected $footnotesIndex = array();

    /**
     *
     * @access protected
     * @var string
     */
    protected $headersContent = array();

    /**
     *
     * @access protected
     * @var string
     */
    protected $html;

    /**
     *
     * @access protected
     * @var TransformDocAdvHTMLPlugin
     */
    protected $htmlPlugin;

    /**
     *
     * @access protected
     * @var string
     */
    protected $link = '';

    /**
     *
     * @access protected
     * @var array
     */
    protected $listStartValues = array();

    /**
     *
     * @access protected
     * @var array
     */
    protected $numberingParagraph = null;

    /**
     *
     * @access protected
     * @var null
     */
    protected $prependTValue = null;

    /**
     *
     * @access protected
     * @var DOMPDF
     */
    protected $pdf;

    /**
     *
     * @access protected
     * @var array
     */
    protected $sectionsStructure = array();

    /**
     *
     * @access protected
     * @var DOMDocument
     */
    protected $stylesDocxDOM;

    /**
     *
     * @access protected
     * @var string
     */
    protected $target = 'document';

    /**
     *
     * @access protected
     * @var DOMDocument
     */
    protected $xmlBody;

    /**
     *
     * @access protected
     * @var DOMXpath
     */
    protected $xmlXpathBody;

    /**
     * Constructor
     *
     * @access public
     * @param mixed (DOCXStructure or file path). DOCX to be transformed
     */
    public function __construct($docxDocument)
    {
        if ($docxDocument instanceof DOCXStructure) {
            $this->docxStructure = $docxDocument;
        } else {
            $this->docxStructure = new DOCXStructure();
            $this->docxStructure->parseDocx($docxDocument);
        }
    }

    public function setDOMPDF($dompdf)
    {
        $this->pdf = $dompdf;
    }

    /**
     * Transform the DOCX content
     *
     * @param string target
     * @param TransformDocAdvHTMLPlugin $htmlPlugin Plugin to be used to transform the contents
     * @param array $options
     *  Values:
     *    'numberingAsParagraphs' => default as true. If true add list numberings as paragraphs
     * @return string
     */
    public function transform($target, $options = array())
    {
        $this->htmlPlugin  = new TransformDocAdvHTMLDOMPDFPlugin();
        $this->css['body'] = '';

        // create new PDF document
        $stylesDocxFile      = $this->docxStructure->getContent('word/styles.xml');
        $this->stylesDocxDOM = new DOMDocument();
        if (PHP_VERSION_ID < 80000) {
            $optionEntityLoader = libxml_disable_entity_loader(true);
        }
        $this->stylesDocxDOM->loadXML($stylesDocxFile);
        if (PHP_VERSION_ID < 80000) {
            libxml_disable_entity_loader($optionEntityLoader);
        }

        $bodyContent   = $this->docxStructure->getContent('word/document.xml');
        $this->xmlBody = new DOMDocument();
        if (PHP_VERSION_ID < 80000) {
            $optionEntityLoader = libxml_disable_entity_loader(true);
        }
        $this->xmlBody->loadXML($bodyContent);
        if (PHP_VERSION_ID < 80000) {
            libxml_disable_entity_loader($optionEntityLoader);
        }

        // get the first section to generate the initial page
        $this->xmlXpathBody = new DOMXPath($this->xmlBody);
        $this->xmlXpathBody->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        // first section size
        $querySection   = '//w:sectPr/w:pgSz';
        $secptPgszNodes = $this->xmlXpathBody->query($querySection);

        $firstSectionSize        = array((int)$secptPgszNodes->item(0)->getAttribute('w:w') / 20, (int)$secptPgszNodes->item(0)->getAttribute('w:h') / 20);
        $firstSectionOrientation = 'portrait';
        if ($secptPgszNodes->item(0)->hasAttribute('w:orient')) {
            if ($secptPgszNodes->item(0)->getAttribute('w:orient') == 'landscape') {
                $firstSectionOrientation = 'landscape';
            }
        }

        $this->pdf->setPaper(array(0, 0, $firstSectionSize[0], $firstSectionSize[1]), $firstSectionOrientation);

        // get meta values
        $metaValues = $this->getMetaValues();

        // add default styles from the styles file
        $this->cssDefaultStyles = $this->addDefaultStyles();

        // numberings as paragraphs
        if (isset($options['numberingAsParagraphs']) && $options['numberingAsParagraphs']) {
            $this->numberingParagraph = array();
        }

        // preload styles.xml
        $this->addStyles();

        // add first section
        $this->currentSection = 0;
        $this->addSection();

        // keep section values
        //$this->pdf->setInfoPhpdocx('section', 'orientation', $firstSectionOrientation);
        //$this->pdf->setInfoPhpdocx('section', 'units', 'pt');
        //$this->pdf->setInfoPhpdocx('section', 'size', $firstSectionSize);

        $documentXmlRelsDocxFile  = $this->docxStructure->getContent('word/_rels/document.xml.rels');
        $this->documentXmlRelsDOM = new DOMDocument();
        $this->documentXmlRelsDOM->loadXML($documentXmlRelsDocxFile);

        $this->target = 'document';
        $this->transformXml($this->xmlBody);

        // insert the section content transforming the generated CSS and HTML to PDF
        $cssContent = '<style>';
        $cssContent .= $this->cssDefaultStyles;
        foreach ($this->css as $key => $value) {
            if ($key == 'body' || $key == '@page') {
                $cssContent .= $key . '{' . $value . '}';
            } else {
                // avoid adding empty CSS
                if (!empty($value)) {
                    $cssContent .= '.' . $key . '{' . $value . '}';
                }
            }
        }
        $cssContent .= '</style>';

        // clean CSS
        $cssContent = str_replace('##', '#', $cssContent);
        $cssContent = str_replace('._span', 'span', $cssContent);
        $this->html = $metaValues . $cssContent . $this->html;
        $this->pdf->load_html($this->html);

        $this->pdf->render();

        if (file_exists(dirname(__FILE__) . '/../Utilities/ZipStream.php') && (CreateDocx::$streamMode === true || (isset($options['stream']) && $options['stream']))) {
            $this->pdf->stream();
        } else {
            $output = $this->pdf->output();
            file_put_contents($target, $output);
        }
    }

    /**
     * Iterate the contents and transform them
     *
     * @param $xml XML string to be transformed
     * @return string
     */
    public function transformXml($xml)
    {
        foreach ($xml->childNodes as $childNode) {
            $nodeClass             = $this->htmlPlugin->generateClassName();
            $this->css[$nodeClass] = '';

            // open tag
            switch ($childNode->nodeName) {
                // block elements
                case 'w:p':
                    $this->transformW_P($childNode, $nodeClass);
                    break;
                case 'w:sectPr':
                    $this->transformW_SECTPR($childNode, $nodeClass);
                    break;
                case 'w:tbl':
                    $this->transformW_TBL($childNode, $nodeClass);
                    break;

                // inline elements
                case 'w:drawing':
                    $this->transformW_DRAWING($childNode, $nodeClass);
                    break;
                case 'w:hyperlink':
                    $this->transformW_HYPERLINK($childNode, $nodeClass);
                    break;
                case 'w:r':
                    $this->transformW_R($childNode, $nodeClass);
                    break;
                case 'w:t':
                    $this->transformW_T($childNode, $nodeClass);
                    break;

                // complex fields
                case 'w:fldChar':
                    $this->transformW_FLDCHAR($childNode, $nodeClass);
                    break;
                case 'w:instrText':
                    $this->transformW_INSTRTEXT($childNode, $nodeClass);
                    break;

                // other elements
                case 'w:br':
                    $this->transformW_BR($childNode, $nodeClass);
                    break;
                case 'w:bookmarkStart':
                    $this->transformW_BOOKMARKSTART($childNode, $nodeClass);
                    break;
                case 'w:comment':
                    $this->transformW_COMMENT($childNode, $nodeClass);
                    break;
                case 'w:commentReference':
                    $this->transformW_COMMENTREFERENCE($childNode, $nodeClass);
                    break;
                case 'w:endnote':
                    $this->transformW_ENDNOTE($childNode, $nodeClass);
                    break;
                case 'w:endnoteReference':
                    $this->transformW_ENDNOTEREFERENCE($childNode, $nodeClass);
                    break;
                case 'w:footnote':
                    $this->transformW_FOOTNOTE($childNode, $nodeClass);
                    break;
                case 'w:footnoteReference':
                    $this->transformW_FOOTNOTEREFERENCE($childNode, $nodeClass);
                    break;
                default:
                    $this->transformDEFAULT_TAG($childNode, $nodeClass);
                    break;
            }
        }
    }

    /**
     * Body styles
     *
     * @param DOMElement $node
     * @return string Styles
     */
    protected function addBodyStyles($node)
    {
        $styles          = '';
        $backgroundColor = $node->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'background');
        if ($backgroundColor->length > 0) {
            // background color
            $styles .= 'background-color: #' . $this->htmlPlugin->transformColors($backgroundColor->item(0)->getAttribute('w:color')) . ';';

            // background image
            $backgroundImageTag = $node->getElementsByTagNameNS('urn:schemas-microsoft-com:vml', 'background');
            if ($backgroundImageTag->length > 0) {
                $backgroundImage = $backgroundImageTag->item(0)->getElementsByTagNameNS('urn:schemas-microsoft-com:vml', 'fill');
                if ($backgroundImage->length > 0) {
                    $target      = $this->getRelationshipContent($backgroundImage->item(0)->getAttribute('r:id'));
                    $imageString = $this->docxStructure->getContent('word/' . $target);

                    $fileInfo = pathinfo($target);
                    file_put_contents($this->htmlPlugin->getOutputFilesPath(). $fileInfo['basename'], $imageString);
                    $styles .= 'background-image: url("' . $this->htmlPlugin->getOutputFilesPath(). $fileInfo['basename'] . '");';
                }
            }
        }

        $this->css['@page'] .= $styles;
    }

    /**
     * pPr styles
     *
     * @param DOMElement $node
     * @param bool $defaultStyles
     * @return string Styles
     */
    protected function addPprStyles($node, $defaultStyles = false)
    {
        if ($node) {
            $styles = '';

            // reset margins to keep the page values
            $pprStyles = $node->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'pPr');
            if ($pprStyles->length > 0 && $pprStyles->item(0)->hasChildNodes()) {
                foreach ($pprStyles->item(0)->childNodes as $pprStyle) {
                    if (isset($pprStyle->tagName)) {
                        switch ($pprStyle->tagName) {
                            case 'w:ind':
                                if ($pprStyle->hasAttribute('w:firstLine')) {
                                    $styles .= 'text-indent: ' . $this->htmlPlugin->transformSizes($pprStyle->getAttribute('w:firstLine'), 'twips') . ';';
                                }
                                break;
                            case 'w:jc':
                                if ($pprStyle->hasAttribute('w:val')) {
                                    switch ($pprStyle->getAttribute('w:val')) {
                                        case 'left':
                                        case 'start':
                                            $styles .= 'text-align: left;';
                                            break;
                                        case 'both':
                                        case 'distribute':
                                            $styles .= 'text-align: justify;';
                                            break;
                                        case 'center':
                                            $styles .= 'text-align: center;';
                                            break;
                                        case 'right':
                                        case 'end':
                                            $styles .= 'text-align: right;';
                                            break;
                                        default:
                                            break;
                                    }
                                }
                                break;
                            case 'w:pageBreakBefore':
                                if ($pprStyle->getAttribute('w:val') == 'on'  || !$pprStyle->hasAttribute('w:val')) {
                                    $styles .= 'page-break-before: always;';
                                }
                                break;
                            case 'w:pBdr':
                                foreach ($pprStyle->childNodes as $pbdrStyle) {
                                    // iterate each border
                                    $borderPosition = explode(':', $pbdrStyle->nodeName);
                                    if (isset($borderPosition[1])) {
                                        // add outline as option
                                        if ($pbdrStyle->hasAttribute('w:color')) {
                                            $styles .= 'border-' . $borderPosition[1] . '-color: #' . $this->htmlPlugin->transformColors($pbdrStyle->getAttribute('w:color')) . ';';
                                        }
                                        if ($pbdrStyle->hasAttribute('w:space')) {
                                            if (is_numeric($pbdrStyle->getAttribute('w:space'))) {
                                                $styles .= 'padding-' . $borderPosition[1] . ': ' . $this->htmlPlugin->transformSizes($pbdrStyle->getAttribute('w:space'), 'eights') . ';';
                                            }
                                        }
                                        if ($pbdrStyle->hasAttribute('w:sz')) {
                                            $styles .= 'border-' . $borderPosition[1] . '-width: ' . $this->htmlPlugin->transformSizes($pbdrStyle->getAttribute('w:sz'), 'eights') . ';';
                                        }
                                        if ($pbdrStyle->hasAttribute('w:val')) {
                                            $borderStyle = $this->getBorderStyle($pbdrStyle->getAttribute('w:val'));
                                            $styles      .= 'border-' . $borderPosition[1] . '-style: ' . $borderStyle . ';';
                                        }
                                    }
                                }
                                break;
                            case 'w:shd':
                                if ($pprStyle->hasAttribute('w:fill') && $pprStyle->getAttribute('w:fill') != 'auto') {
                                    $styles .= 'background-color: #' . $pprStyle->getAttribute('w:fill') . ';';
                                }
                                break;
                            case 'w:spacing':
                                if ($pprStyle->hasAttribute('w:after')) {
                                    $styles .= 'margin-bottom: ' . $this->htmlPlugin->transformSizes($pprStyle->getAttribute('w:after'), 'twips') . ';';
                                } else {
                                    $styles .= 'margin-bottom: ' . $this->htmlPlugin->transformSizes(20, 'twips') . ';';
                                }
                                if ($pprStyle->hasAttribute('w:before')) {
                                    $styles .= 'margin-top: ' . $this->htmlPlugin->transformSizes($pprStyle->getAttribute('w:before'), 'twips') . ';';
                                } else {
                                    $styles .= 'margin-top: ' . $this->htmlPlugin->transformSizes(20, 'twips') . ';';
                                }
                                if ($pprStyle->hasAttribute('w:line')) {
                                    if (!$pprStyle->hasAttribute('w:lineRule')) {
                                        $styles .= 'line-height: ' . $this->htmlPlugin->transformSizes($pprStyle->getAttribute('w:line'), 'twips') . ';';
                                    }
                                }
                                break;
                            case 'w:wordWrap':
                                if ($pprStyle->getAttribute('w:val') == 'on'  || !$pprStyle->hasAttribute('w:val')) {
                                    $styles .= 'word-wrap: break-word;';
                                }
                                break;
                            default:
                                break;
                        }
                    }
                }
            }

            return $styles;
        }
    }

    /**
     * rPr styles
     *
     * @param DOMElement $node
     */
    protected function addRprStyles($node)
    {
        if ($node) {
            $styles = '';

            // if the previous w:pPr/w:rPr style has a bold style but not the current w:rPr element, set a normal font-weight
            if ($node->parentNode && $node->parentNode->tagName == 'w:p') {
                $rprParentpPrStyles = $node->parentNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'pPr');
                if ($rprParentpPrStyles->length > 0) {
                    $rprParentpPrrPrStyles = $rprParentpPrStyles->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'rPr');
                    if ($rprParentpPrrPrStyles->length > 0) {
                        $rprParentpPrrPrBStyles = $rprParentpPrStyles->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'b');
                        if ($rprParentpPrrPrBStyles->length > 0 && ($rprParentpPrrPrBStyles->item(0)->getAttribute('w:val') == 'on' || !$rprParentpPrrPrBStyles->item(0)->hasAttribute('w:val'))) {
                            $styles .= 'font-weight: normal;';
                        }
                    }
                }
            }

            $rprStyles = $node->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'rPr');
            if ($rprStyles->length > 0 && $rprStyles->item(0)->hasChildNodes()) {
                foreach ($rprStyles->item(0)->childNodes as $rprStyle) {
                    if (isset($rprStyle->tagName)) {
                        switch ($rprStyle->tagName) {
                            case 'w:b':
                                if ($rprStyle->getAttribute('w:val') == 'on' || !$rprStyle->hasAttribute('w:val')) {
                                    $styles .= 'font-weight: bold;';
                                }
                                if ($rprStyle->getAttribute('w:val') == '0') {
                                    $styles .= 'font-weight: normal;';
                                }
                                break;
                            case 'w:caps':
                                if ($rprStyle->getAttribute('w:val') == 'on'  || !$rprStyle->hasAttribute('w:val')) {
                                    $styles .= 'text-transform: uppercase;';
                                }
                                break;
                            case 'w:color':
                                $styles .= 'color: #' . $this->htmlPlugin->transformColors($rprStyle->getAttribute('w:val')) . ';';
                                break;
                            case 'w:dstrike':
                                if ($rprStyle->getAttribute('w:val') == 'on'  || !$rprStyle->hasAttribute('w:val')) {
                                    if (strstr($styles, 'text-decoration: ')) {
                                        $styles .= str_replace('text-decoration: ', 'text-decoration: line-through ', $styles);
                                    } else {
                                        $styles .= 'text-decoration: line-through;';
                                    }
                                    $styles .= 'text-decoration-style: double;';
                                }
                                break;
                            case 'w:highlight':
                                $styles .= 'background-color: ' . $rprStyle->getAttribute('w:val') . ';';
                                break;
                            case 'w:i':
                                if ($rprStyle->getAttribute('w:val') == 'on'  || !$rprStyle->hasAttribute('w:val')) {
                                    $styles .= 'font-style: italic;';
                                }
                                if ($rprStyle->getAttribute('w:val') == '0') {
                                    $styles .= 'font-style: normal;';
                                }
                                break;
                            case 'w:rFonts':
                                $fontFamily = '';
                                if ($rprStyle->hasAttribute('w:ascii')) {
                                    $fontFamily = $rprStyle->getAttribute('w:ascii');
                                } else if ($rprStyle->hasAttribute('w:cs')) {
                                    $fontFamily = $rprStyle->getAttribute('w:cs');
                                }

                                // TCPDF doesn't use Symbol fonts
                                if ($fontFamily != 'Symbol') {
                                    $styles .= 'font-family: "' . $fontFamily. '";';
                                }
                                break;
                            case 'w:shd':
                                if ($rprStyle->hasAttribute('w:fill') && $rprStyle->getAttribute('w:fill') != 'auto') {
                                    $styles .= 'background-color: #' . $rprStyle->getAttribute('w:fill') . ';';
                                }
                                break;
                            case 'w:strike':
                                if ($rprStyle->getAttribute('w:val') == 'on'  || !$rprStyle->hasAttribute('w:val')) {
                                    if (strstr($styles, 'text-decoration: ')) {
                                        $styles = str_replace('text-decoration: ', 'text-decoration: line-through ', $styles);
                                    } else {
                                        $styles .= 'text-decoration: line-through;';
                                    }
                                }
                                break;
                            case 'w:sz':
                                // if it's a super or sub text
                                if ($rprStyle->parentNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'vertAlign')->length > 0) {
                                    $styles .= 'font-size: ' . $this->htmlPlugin->transformSizes((int)$rprStyle->getAttribute('w:val') / 1.7, 'half-points') . ';';
                                } else {
                                    $styles .= 'font-size: ' . $this->htmlPlugin->transformSizes($rprStyle->getAttribute('w:val'), 'half-points') . ';';
                                }
                                break;
                            case 'w:u':
                                // default value
                                $textDecorationValue = 'underline';

                                // if none, change text decoration value
                                if ($rprStyle->hasAttribute('w:val') && $rprStyle->getAttribute('w:val') == 'none') {
                                    $textDecorationValue = 'none';
                                }

                                if (!$rprStyle->hasAttribute('w:val')) {
                                    $textDecorationValue = 'none';
                                }

                                // concat other text-decoration styles such as w:strike and w:dstrike
                                if (strstr($styles, 'text-decoration: ')) {
                                    $styles = str_replace('text-decoration: ', 'text-decoration: ' . $textDecorationValue . ' ', $styles);
                                } else {
                                    $styles .= 'text-decoration: ' . $textDecorationValue . ';';
                                }

                                // handle text decoration style
                                if ($rprStyle->hasAttribute('w:val')) {
                                    switch ($rprStyle->getAttribute('w:val')) {
                                        case 'dash':
                                            $styles .= 'text-decoration-style: dashed;';
                                            break;
                                        case 'dotted':
                                            $styles .= 'text-decoration-style: dotted;';
                                            break;
                                        case 'double':
                                            $styles .= 'text-decoration-style: double;';
                                            break;
                                        case 'single':
                                            $styles .= 'text-decoration-style: solid;';
                                            break;
                                        case 'wave':
                                            $styles .= 'text-decoration-style: wavy;';
                                            break;
                                        case 'none':
                                            // avoid adding a text-decoration-style property
                                            break;
                                        default:
                                            $styles .= 'text-decoration-style: solid;';
                                            break;
                                    }
                                }
                                break;
                            case 'w:vertAlign':
                                if ($rprStyle->hasAttribute('w:val')) {
                                    switch ($rprStyle->getAttribute('w:val')) {
                                        case 'subscript':
                                            $styles .= 'vertical-align: sub;';
                                            break;
                                        case 'superscript':
                                            $styles .= 'vertical-align: super;';
                                            break;
                                    }
                                }
                                break;
                            default:
                                break;
                        }
                    }
                }
            }

            return $styles;
        }
    }

    /**
     * Default styles
     */
    protected function addDefaultStyles()
    {
        $xpathStyles = new DOMXPath($this->stylesDocxDOM);
        $xpathStyles->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        // docDefaults styles
        $docDefaultsStylesPpr = $xpathStyles->query('//w:docDefaults/w:pPrDefault')->item(0);
        $docDefaultsStylesRpr = $xpathStyles->query('//w:docDefaults/w:rPrDefault')->item(0);

        $css = '';

        // addPprStyles
        if ($docDefaultsStylesPpr) {
            $css .= 'p, h1, h2, h3, h4, h5, h6, ul, ol {' . $this->addPprStyles($docDefaultsStylesPpr, true) . '}';
        }

        // addRprStyles
        if ($docDefaultsStylesRpr) {
            $css .= 'span {' . $this->addRprStyles($docDefaultsStylesRpr) . '}';
        }

        // default styles query by w:default="1"
        $docDefaultsStyles = $xpathStyles->query('//w:style[@w:default="1"]');
        foreach ($docDefaultsStyles as $docDefaultsStyle) {
            switch ($docDefaultsStyle->getAttribute('w:type')) {
                case 'paragraph':
                    $css .= 'p, h1, h2, h3, h4, h5, h6, ul, ol {' . $this->addPprStyles($docDefaultsStyle) . '}';
                    $css .= 'span {' . $this->addRprStyles($docDefaultsStyle) . '}';
                    break;
                case 'table':
                    $stylesTable = $this->getTableStyles($docDefaultsStyle);
                    $css         .= 'table {' . $stylesTable['tableStyles'] . $stylesTable['borderStylesTable'] . $stylesTable['borderInsideStylesTable'] . $stylesTable['cellPadding'] . '}';
                    break;
                default:
                    break;
            }
        }

        return $css;
    }

    /**
     * Adds a new section
     */
    protected function addSection()
    {
        // page margins
        $querySection    = '//w:sectPr/w:pgMar';
        $secptPgMarNodes = $this->xmlXpathBody->query($querySection);

        $sectionMarginTop    = $secptPgMarNodes->item($this->currentSection)->getAttribute('w:top') / 20;
        $sectionMarginRight  = $secptPgMarNodes->item($this->currentSection)->getAttribute('w:right') / 20;
        $sectionMarginBottom = $secptPgMarNodes->item($this->currentSection)->getAttribute('w:bottom') / 20;
        $sectionMarginLeft   = $secptPgMarNodes->item($this->currentSection)->getAttribute('w:left') / 20;

        $this->css['@page'] .= 'margin:' . $sectionMarginTop . 'px ' . $sectionMarginRight . 'px ' . $sectionMarginBottom . 'px ' . $sectionMarginLeft . 'px;';

        $this->currentStylesSection['sectpr_margin_top']    = $sectionMarginTop;
        $this->currentStylesSection['sectpr_margin_right']  = $sectionMarginRight;
        $this->currentStylesSection['sectpr_margin_bottom'] = $sectionMarginBottom;
        $this->currentStylesSection['sectpr_margin_left']   = $sectionMarginLeft;

        // section size
        $querySection   = '//w:sectPr/w:pgSz';
        $secptPgszNodes = $this->xmlXpathBody->query($querySection);

        $sectionSize        = array((int)$secptPgszNodes->item($this->currentSection)->getAttribute('w:w') / 20, (int)$secptPgszNodes->item($this->currentSection)->getAttribute('w:h') / 20);
        $sectionOrientation = 'P';
        if ($secptPgszNodes->item(0)->hasAttribute('w:orient')) {
            if ($secptPgszNodes->item(0)->getAttribute('w:orient') == 'landscape') {
                $sectionOrientation = 'L';
            }
        }

        $this->currentStylesSection['sectpr_orientation'] = $sectionOrientation;
        $this->currentStylesSection['sectpr_size']        = $sectionSize;

        $this->html .= '<div class="page_break"></div>';

        // add a page
        //$this->pdf->AddPage($sectionOrientation, $sectionSize, true);

        // keep section values
        //$this->pdf->setInfoPhpdocx('section', 'orientation', $sectionOrientation);
        //$this->pdf->setInfoPhpdocx('section', 'size', $sectionSize);

        // body background color
        $this->addBodyStyles($this->xmlBody);

        // page borders
        $this->addSectionBorders();
    }

    /**
     * Adds section borders
     */
    protected function addSectionBorders()
    {
        $querySection       = '//w:sectPr/w:pgBorders';
        $secptPgBorderNodes = $this->xmlXpathBody->query($querySection);
        if ($secptPgBorderNodes->length > 0 && $this->currentSection < $secptPgBorderNodes->length) {
            $elementWTcBordersTop = $secptPgBorderNodes->item($this->currentSection)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'top');
            $borderMarginTop      = 24;
            if ($elementWTcBordersTop->length > 0) {
                $lineStyleTop = array();
                if ($elementWTcBordersTop->item(0)->hasAttribute('w:color')) {
                    $color                 = $elementWTcBordersTop->item(0)->getAttribute('w:color');
                    $colorRGB              = str_split($color, 2);
                    $lineStyleTop['color'] = array(hexdec($colorRGB[0]), hexdec($colorRGB[1]), hexdec($colorRGB[2]));
                }
                if ($elementWTcBordersTop->item(0)->hasAttribute('w:sz')) {
                    $lineStyleTop['width'] = $elementWTcBordersTop->item(0)->getAttribute('w:sz') / 8;
                }
                if ($elementWTcBordersTop->item(0)->hasAttribute('w:space')) {
                    $borderMarginTop = $elementWTcBordersTop->item(0)->getAttribute('w:space');
                }
                $lineStyleTop['dash'] = 0;
                if ($elementWTcBordersTop->item(0)->hasAttribute('w:val')) {
                    if ($elementWTcBordersTop->item(0)->getAttribute('w:val') == 'dashed') {
                        $lineStyleTop['dash'] = 6;
                    } else if ($elementWTcBordersTop->item(0)->getAttribute('w:val') == 'dotted') {
                        $lineStyleTop['dash'] = 1;
                    } else if ($elementWTcBordersTop->item(0)->getAttribute('w:val') == 'nil' || $elementWTcBordersTop->item(0)->getAttribute('w:val') == 'none') {
                        $lineStyleTop = null;
                    }
                }
            }

            $elementWTcBordersRight = $secptPgBorderNodes->item($this->currentSection)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'right');
            $borderMarginRight      = 24;
            if ($elementWTcBordersRight->length > 0) {
                $lineStyleRight = array();
                if ($elementWTcBordersRight->item(0)->hasAttribute('w:color')) {
                    $color                   = $elementWTcBordersRight->item(0)->getAttribute('w:color');
                    $colorRGB                = str_split($color, 2);
                    $lineStyleRight['color'] = array(hexdec($colorRGB[0]), hexdec($colorRGB[1]), hexdec($colorRGB[2]));
                }
                if ($elementWTcBordersRight->item(0)->hasAttribute('w:sz')) {
                    $lineStyleRight['width'] = $elementWTcBordersRight->item(0)->getAttribute('w:sz') / 8;
                }
                if ($elementWTcBordersRight->item(0)->hasAttribute('w:space')) {
                    $borderMarginRight = $elementWTcBordersRight->item(0)->getAttribute('w:space');
                }
                $lineStyleRight['dash'] = 0;
                if ($elementWTcBordersRight->item(0)->hasAttribute('w:val')) {
                    if ($elementWTcBordersRight->item(0)->getAttribute('w:val') == 'dashed') {
                        $lineStyleRight['dash'] = 6;
                    } else if ($elementWTcBordersRight->item(0)->getAttribute('w:val') == 'dotted') {
                        $lineStyleRight['dash'] = 1;
                    } else if ($elementWTcBordersRight->item(0)->getAttribute('w:val') == 'nil' || $elementWTcBordersRight->item(0)->getAttribute('w:val') == 'none') {
                        $lineStyleRight = null;
                    }
                }
            }

            $elementWTcBordersBottom = $secptPgBorderNodes->item($this->currentSection)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'bottom');
            $borderMarginBottom      = 24;
            if ($elementWTcBordersBottom->length > 0) {
                $lineStyleBottom = array();
                if ($elementWTcBordersBottom->item(0)->hasAttribute('w:color')) {
                    $color                    = $elementWTcBordersBottom->item(0)->getAttribute('w:color');
                    $colorRGB                 = str_split($color, 2);
                    $lineStyleBottom['color'] = array(hexdec($colorRGB[0]), hexdec($colorRGB[1]), hexdec($colorRGB[2]));
                }
                if ($elementWTcBordersBottom->item(0)->hasAttribute('w:sz')) {
                    $lineStyleBottom['width'] = $elementWTcBordersBottom->item(0)->getAttribute('w:sz') / 8;
                }
                if ($elementWTcBordersBottom->item(0)->hasAttribute('w:space')) {
                    $borderMarginBottom = $elementWTcBordersBottom->item(0)->getAttribute('w:space');
                }
                $lineStyleBottom['dash'] = 0;
                if ($elementWTcBordersBottom->item(0)->hasAttribute('w:val')) {
                    if ($elementWTcBordersBottom->item(0)->getAttribute('w:val') == 'dashed') {
                        $lineStyleBottom['dash'] = 6;
                    } else if ($elementWTcBordersBottom->item(0)->getAttribute('w:val') == 'dotted') {
                        $lineStyleTop['dash'] = 1;
                    } else if ($elementWTcBordersBottom->item(0)->getAttribute('w:val') == 'nil' || $elementWTcBordersBottom->item(0)->getAttribute('w:val') == 'none') {
                        $lineStyleBottom = null;
                    }
                }
            }

            $elementWTcBordersLeft = $secptPgBorderNodes->item($this->currentSection)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'left');
            $borderMarginLeft      = 24;
            if ($elementWTcBordersLeft->length > 0) {
                $lineStyleLeft = array();
                if ($elementWTcBordersLeft->item(0)->hasAttribute('w:color')) {
                    $color                  = $elementWTcBordersLeft->item(0)->getAttribute('w:color');
                    $colorRGB               = str_split($color, 2);
                    $lineStyleLeft['color'] = array(hexdec($colorRGB[0]), hexdec($colorRGB[1]), hexdec($colorRGB[2]));
                }
                if ($elementWTcBordersLeft->item(0)->hasAttribute('w:sz')) {
                    $lineStyleLeft['width'] = $elementWTcBordersLeft->item(0)->getAttribute('w:sz') / 8;
                }
                if ($elementWTcBordersLeft->item(0)->hasAttribute('w:space')) {
                    $borderMarginLeft = $elementWTcBordersLeft->item(0)->getAttribute('w:space');
                }
                $lineStyleLeft['dash'] = 0;
                if ($elementWTcBordersLeft->item(0)->hasAttribute('w:val')) {
                    if ($elementWTcBordersLeft->item(0)->getAttribute('w:val') == 'dashed') {
                        $lineStyleLeft['dash'] = 6;
                    } else if ($elementWTcBordersLeft->item(0)->getAttribute('w:val') == 'dotted') {
                        $lineStyleTop['dash'] = 1;
                    } else if ($elementWTcBordersLeft->item(0)->getAttribute('w:val') == 'nil' || $elementWTcBordersLeft->item(0)->getAttribute('w:val') == 'none') {
                        $lineStyleLeft = null;
                    }
                }
            }

            // top border
            if ($elementWTcBordersTop->length > 0 && $lineStyleTop) {
                $this->pdf->SetLineStyle($lineStyleTop);
                $this->pdf->Line($borderMarginLeft, $borderMarginTop, $this->pdf->getPageWidth() - $borderMarginRight, $borderMarginTop);

                $this->pdf->setInfoPhpdocx('section', 'borderTop', array('lineStyle' => $lineStyleTop, 'line' => array($borderMarginLeft, $borderMarginTop, $this->pdf->getPageWidth() - $borderMarginRight, $borderMarginTop)));
            }
            // right border
            if ($elementWTcBordersRight->length > 0 && $lineStyleRight) {
                $this->pdf->SetLineStyle($lineStyleRight);
                $this->pdf->Line($this->pdf->getPageWidth() - $borderMarginLeft, $borderMarginTop, $this->pdf->getPageWidth() - $borderMarginRight, $this->pdf->getPageHeight() - $borderMarginBottom);

                $this->pdf->setInfoPhpdocx('section', 'borderRight', array('lineStyle' => $lineStyleRight, 'line' => array($this->pdf->getPageWidth() - $borderMarginLeft, $borderMarginTop, $this->pdf->getPageWidth() - $borderMarginRight, $this->pdf->getPageHeight() - $borderMarginBottom)));
            }
            // bottom border
            if ($elementWTcBordersBottom->length > 0 && $lineStyleBottom) {
                $this->pdf->SetLineStyle($lineStyleBottom);
                $this->pdf->Line($borderMarginLeft, $this->pdf->getPageHeight() - $borderMarginBottom, $this->pdf->getPageWidth() - $borderMarginRight, $this->pdf->getPageHeight() - $borderMarginBottom);

                $this->pdf->setInfoPhpdocx('section', 'borderBottom', array('lineStyle' => $lineStyleBottom, 'line' => array($borderMarginLeft, $this->pdf->getPageHeight() - $borderMarginBottom, $this->pdf->getPageWidth() - $borderMarginRight, $this->pdf->getPageHeight() - $borderMarginBottom)));
            }
            // left border
            if ($elementWTcBordersLeft->length > 0 && $lineStyleLeft) {
                $this->pdf->SetLineStyle($lineStyleLeft);
                $this->pdf->Line($borderMarginLeft, $borderMarginTop, $borderMarginLeft, $this->pdf->getPageHeight() - $borderMarginBottom);

                $this->pdf->setInfoPhpdocx('section', 'borderLeft', array('lineStyle' => $lineStyleLeft, 'line' => array($borderMarginLeft, $borderMarginTop, $borderMarginLeft, $this->pdf->getPageHeight() - $borderMarginBottom)));
            }
        }
    }

    /**
     * Styles file
     *
     * @return string Styles
     */
    protected function addStyles()
    {
        $xpathStyles = new DOMXPath($this->stylesDocxDOM);
        $xpathStyles->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        // tag styles
        $styles = $xpathStyles->query('//w:style');
        if ($styles->length > 0) {
            foreach ($styles as $style) {
                $nodeClass             = $style->getAttribute('w:type') . '_' . $style->getAttribute('w:styleId');
                $this->css[$nodeClass] = '';

                // open tag
                switch ($style->getAttribute('w:type')) {
                    case 'character':
                        $this->css[$nodeClass] .= $this->addRprStyles($style);
                        break;
                    case 'paragraph':
                        $this->css[$nodeClass] .= $this->addPprStyles($style);
                        $this->css[$nodeClass] .= $this->addRprStyles($style);
                        break;
                    default:
                        break;
                }
            }
        }

        return $this->css;
    }

    /**
     * Normalize border styles
     *
     * @param String $style Border style
     * @return string Styles
     */
    protected function getBorderStyle($style)
    {
        $borderStyle = 'solid';
        switch ($style) {
            case 'dashed':
                $borderStyle ='dashed';
                break;
            case 'dotted':
                $borderStyle ='dotted';
                break;
            case 'double':
                $borderStyle ='double';
                break;
            case 'nil':
            case 'none':
                $borderStyle = 'none';
                break;
            case 'single':
                $borderStyle = 'solid';
                break;
            default:
                $borderStyle = 'solid';
                break;
        }

        return $borderStyle;
    }

    /**
     * Cell styles
     *
     * @param String styles
     * @return string Styles
     */
    protected function getCellStyles($styles)
    {
        $cellStyles       = '';
        $borderStylesCell = '';
        // cell style properties
        $elementWTcPr = $styles->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tcPr');
        if ($elementWTcPr->length > 0) {
            // cell borders
            $elementWTcBorders = $elementWTcPr->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tcBorders');
            if ($elementWTcBorders->length > 0) {
                // top
                $elementWTcBordersTop = $elementWTcBorders->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'top');
                if ($elementWTcBordersTop->length > 0) {
                    $borderStyle      = $this->getBorderStyle($elementWTcBordersTop->item(0)->getAttribute('w:val'));
                    $cellStyles       .= 'border-top: ' . $elementWTcBordersTop->item(0)->getAttribute('w:sz') . ' ' . $borderStyle . ' #' . $elementWTcBordersTop->item(0)->getAttribute('w:color') . ';';
                    $borderStylesCell .= 'border-top: ' . $elementWTcBordersTop->item(0)->getAttribute('w:sz') . ' ' . $borderStyle . ' #' . $elementWTcBordersTop->item(0)->getAttribute('w:color') . ';';
                }

                // right
                $elementWTcBordersRight = $elementWTcBorders->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'right');
                if ($elementWTcBordersRight->length > 0) {
                    $borderStyle      = $this->getBorderStyle($elementWTcBordersRight->item(0)->getAttribute('w:val'));
                    $cellStyles       .= 'border-right: ' . $elementWTcBordersRight->item(0)->getAttribute('w:sz') . ' ' . $borderStyle . ' #' . $elementWTcBordersRight->item(0)->getAttribute('w:color') . ';';
                    $borderStylesCell .= 'border-right: ' . $elementWTcBordersRight->item(0)->getAttribute('w:sz') . ' ' . $borderStyle . ' #' . $elementWTcBordersRight->item(0)->getAttribute('w:color') . ';';
                }

                // bottom
                $elementWTcBordersBottom = $elementWTcBorders->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'bottom');
                if ($elementWTcBordersBottom->length > 0) {
                    $borderStyle      = $this->getBorderStyle($elementWTcBordersBottom->item(0)->getAttribute('w:val'));
                    $cellStyles       .= 'border-bottom: ' . $elementWTcBordersBottom->item(0)->getAttribute('w:sz') . ' ' . $borderStyle . ' #' . $elementWTcBordersBottom->item(0)->getAttribute('w:color') . ';';
                    $borderStylesCell .= 'border-bottom: ' . $elementWTcBordersBottom->item(0)->getAttribute('w:sz') . ' ' . $borderStyle . ' #' . $elementWTcBordersBottom->item(0)->getAttribute('w:color') . ';';
                }

                // left
                $elementWTcBordersLeft = $elementWTcBorders->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'left');
                if ($elementWTcBordersLeft->length > 0) {
                    $borderStyle      = $this->getBorderStyle($elementWTcBordersLeft->item(0)->getAttribute('w:val'));
                    $cellStyles       .= 'border-left: ' . $elementWTcBordersLeft->item(0)->getAttribute('w:sz') . ' ' . $borderStyle . ' #' . $elementWTcBordersLeft->item(0)->getAttribute('w:color') . ';';
                    $borderStylesCell .= 'border-left: ' . $elementWTcBordersLeft->item(0)->getAttribute('w:sz') . ' ' . $borderStyle . ' #' . $elementWTcBordersLeft->item(0)->getAttribute('w:color') . ';';
                }
            }
        }

        return array('cellStyles' => $cellStyles, 'borderStylesCell' => $borderStylesCell);
    }

    /**
     * Meta values
     *
     * @return string metas
     */
    protected function getMetaValues()
    {
        $documentCoreContent = $this->docxStructure->getContent('docProps/core.xml');

        $tags = '';

        if ($documentCoreContent) {
            $xmlCoreContent = new DOMDocument();
            if (PHP_VERSION_ID < 80000) {
                $optionEntityLoader = libxml_disable_entity_loader(true);
            }
            $xmlCoreContent->loadXML($documentCoreContent);
            if (PHP_VERSION_ID < 80000) {
                libxml_disable_entity_loader($optionEntityLoader);
            }
            foreach ($xmlCoreContent->childNodes->item(0)->childNodes as $prop) {
                switch ($prop->tagName) {
                    case 'dc:title':
                        $tags .= '<title>' . $prop->nodeValue . '</title>';
                        break;
                    case 'dc:creator':
                        $tags .= '<meta name="author" content="' . $prop->nodeValue . '">';
                        break;
                    case 'cp:keywords':
                        $tags .= '<meta name="keywords" content="' . $prop->nodeValue . '">';
                        break;
                    case 'dc:description':
                        $tags .= '<meta name="description" content="' . $prop->nodeValue . '">';
                        break;
                    default:
                        break;
                }
            }
        }

        return $tags;
    }

    /**
     * Numbering lvlText
     *
     * @param string $id
     * @param string $level
     * @return string start value
     */
    protected function getNumberingLvlText($id, $level)
    {
        $documentNumbering = $this->docxStructure->getContent('word/numbering.xml');

        $xmlNumbering = new DOMDocument();
        if (PHP_VERSION_ID < 80000) {
            $optionEntityLoader = libxml_disable_entity_loader(true);
        }
        $xmlNumbering->loadXML($documentNumbering);
        if (PHP_VERSION_ID < 80000) {
            libxml_disable_entity_loader($optionEntityLoader);
        }

        // get w:num by Id
        $xpathNumbering = new DOMXPath($xmlNumbering);
        $xpathNumbering->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $elementNum = $xpathNumbering->query('//w:num[@w:numId="' . $id . '"]')->item(0);

        if ($elementNum != '') {
            // get w:abstractNumId used to set the numbering styles
            $abstractNumId = $elementNum->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'abstractNumId')->item(0)->getAttribute('w:val');

            // get the style of the w:abstractNum related to w:abstractNumId
            $elementAbstractNumStart = $xpathNumbering->query(
                '//w:abstractNum[@w:abstractNumId="' . $abstractNumId . '"]' .
                '/w:lvl[@w:ilvl="' . $level . '"]' .
                '/w:lvlText'
            )->item(0);

            return $elementAbstractNumStart->getAttribute('w:val');
        }

        // style not found, return 1 as default value
        return '1';
    }

    /**
     * Numbering type
     *
     * @param string $id
     * @param string $level
     * @return string start value
     */
    protected function getNumberingStart($id, $level)
    {
        $documentNumbering = $this->docxStructure->getContent('word/numbering.xml');

        $xmlNumbering = new DOMDocument();
        if (PHP_VERSION_ID < 80000) {
            $optionEntityLoader = libxml_disable_entity_loader(true);
        }
        $xmlNumbering->loadXML($documentNumbering);
        if (PHP_VERSION_ID < 80000) {
            libxml_disable_entity_loader($optionEntityLoader);
        }

        // get w:num by Id
        $xpathNumbering = new DOMXPath($xmlNumbering);
        $xpathNumbering->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $elementNum = $xpathNumbering->query('//w:num[@w:numId="' . $id . '"]')->item(0);

        if ($elementNum != '') {
            // get w:abstractNumId used to set the numbering styles
            $abstractNumId = $elementNum->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'abstractNumId')->item(0)->getAttribute('w:val');

            // get the style of the w:abstractNum related to w:abstractNumId
            $elementAbstractNumStart = $xpathNumbering->query(
                '//w:abstractNum[@w:abstractNumId="' . $abstractNumId . '"]' .
                '/w:lvl[@w:ilvl="' . $level . '"]' .
                '/w:start'
            )->item(0);

            if ($elementAbstractNumStart) {
                return $elementAbstractNumStart->getAttribute('w:val');
            } else {
                return '1';
            }
        }

        // style not found, return 1 as default value
        return '1';
    }

    /**
     * Numbering styles
     *
     * @param string $id
     * @param string $level
     * @return string Styles or null
     */
    protected function getNumberingStyles($id, $level)
    {
        $documentNumbering = $this->docxStructure->getContent('word/numbering.xml');

        $xmlNumbering = new DOMDocument();
        $xmlNumbering->loadXML($documentNumbering);

        // get w:num by Id
        $xpathNumbering = new DOMXPath($xmlNumbering);
        $xpathNumbering->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $elementNum = $xpathNumbering->query('//w:num[@w:numId="' . $id . '"]')->item(0);

        if ($elementNum != '') {
            // get w:abstractNumId used to set the numbering styles
            $abstractNumId = $elementNum->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'abstractNumId')->item(0)->getAttribute('w:val');

            // get the level content of the w:abstractNum related to w:abstractNumId
            $elementAbstractNumLvl = $xpathNumbering->query(
                '//w:abstractNum[@w:abstractNumId="' . $abstractNumId . '"]' .
                '/w:lvl[@w:ilvl="' . $level . '"]'
            )->item(0);


            return $elementAbstractNumLvl;
        }

        // style not found
        return null;
    }

    /**
     * Numbering type
     *
     * @param string $id
     * @param string $level
     * @return string Styles or null
     */
    protected function getNumberingType($id, $level)
    {
        $documentNumbering = $this->docxStructure->getContent('word/numbering.xml');

        $xmlNumbering = new DOMDocument();
        $xmlNumbering->loadXML($documentNumbering);

        // get w:num by Id
        $xpathNumbering = new DOMXPath($xmlNumbering);
        $xpathNumbering->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $elementNum = $xpathNumbering->query('//w:num[@w:numId="' . $id . '"]')->item(0);

        if ($elementNum != '') {
            // get w:abstractNumId used to set the numbering styles
            $abstractNumId = $elementNum->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'abstractNumId')->item(0)->getAttribute('w:val');

            // get the style of the w:abstractNum related to w:abstractNumId
            $elementAbstractNumFmt = $xpathNumbering->query(
                '//w:abstractNum[@w:abstractNumId="' . $abstractNumId . '"]' .
                '/w:lvl[@w:ilvl="' . $level . '"]' .
                '/w:numFmt'
            )->item(0);

            return $elementAbstractNumFmt->getAttribute('w:val');
        }

        // style not found
        return null;
    }

    /**
     * Table styles
     *
     * @param string $styles
     * @return array Styles
     */
    protected function getTableStyles($styles)
    {
        $tableStyles             = '';
        $borderStylesTable       = '';
        $borderInsideStylesTable = '';
        $cellPadding             = '';
        $firstLastStyles         = array();

        // table style properties
        $elementWTblPr = $styles->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tblPr');
        if ($elementWTblPr->length > 0) {
            // table width
            $elementWTblW = $styles->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tblPr')->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tblW');
            if ($elementWTblW->length > 0) {
                if ($elementWTblW->item(0)->getAttribute('w:type') == 'pct') {
                    // MS Word allows to set width pct using two formats: int (5000 is 100%) or %
                    if (strpos($elementWTblW->item(0)->getAttribute('w:w'), '%') !== false) {
                        // int value
                        $tableStyles .= 'width: ' . $elementWTblW->item(0)->getAttribute('w:w') . ';';
                    } else {
                        // percent value
                        $tableStyles .= 'width: ' . $this->htmlPlugin->transformSizes($elementWTblW->item(0)->getAttribute('w:w'), 'fifths-percent', '%') . ';';
                    }
                } elseif ($elementWTblW->item(0)->getAttribute('w:type') == 'dxa') {
                    $tableStyles .= 'width: ' . $this->htmlPlugin->transformSizes($elementWTblW->item(0)->getAttribute('w:w'), 'twips') . ';';
                }
            }

            // table align
            $elementWTblJc = $styles->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tblPr')->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'jc');
            if ($elementWTblJc->length > 0) {
                if ($elementWTblJc->item(0)->getAttribute('w:val') == 'center') {
                    $tableStyles .= 'align: center;';
                }

                if ($elementWTblJc->item(0)->getAttribute('w:val') == 'right') {
                    $tableStyles .= 'align: right;';
                }
            }

            // table layout
            $elementWTblLayout = $styles->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tblPr')->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tblLayout');
            if ($elementWTblLayout->length > 0) {
                if ($elementWTblLayout->item(0)->getAttribute('w:type') == 'fixed') {
                    $tableStyles .= 'table-layout: auto;';
                }
            }

            // table indent
            $elementWTblInd = $styles->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tblPr')->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tblInd');
            if ($elementWTblInd->length > 0) {
                $tableStyles .= 'margin-left: ' . $this->htmlPlugin->transformSizes($elementWTblInd->item(0)->getAttribute('w:w'), 'twips') . ';';
            }

            // table padding
            $elementWTblCellMar = $styles->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tblPr')->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tblCellMar');
            if ($elementWTblCellMar->length > 0) {
                $elementWTblCellMarTop    = $elementWTblCellMar->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'top');
                $elementWTblCellMarRight  = $elementWTblCellMar->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'right');
                $elementWTblCellMarBottom = $elementWTblCellMar->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'bottom');
                $elementWTblCellMarLeft   = $elementWTblCellMar->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'left');
                $cellPadding              .= 'padding: ';
                if ($elementWTblCellMarTop->item(0)) {
                    $cellPadding .= $this->htmlPlugin->transformSizes($elementWTblCellMarTop->item(0)->getAttribute('w:w'), 'twips');
                }
                if ($elementWTblCellMarRight->item(0)) {
                    $cellPadding .= ' ' . $this->htmlPlugin->transformSizes($elementWTblCellMarRight->item(0)->getAttribute('w:w'), 'twips');
                }
                if ($elementWTblCellMarBottom->item(0)) {
                    $cellPadding .= ' ' . $this->htmlPlugin->transformSizes($elementWTblCellMarBottom->item(0)->getAttribute('w:w'), 'twips');
                }
                if ($elementWTblCellMarLeft->item(0)) {
                    $cellPadding .= ' ' . $this->htmlPlugin->transformSizes($elementWTblCellMarLeft->item(0)->getAttribute('w:w'), 'twips');
                }
                $cellPadding .= ';';
            }

            // table borders
            $elementWTblBorders = $styles->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tblPr')->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tblBorders');
            if ($elementWTblBorders->length > 0) {
                // keep border styles to be used if tr or td doesn't overwrite them

                // top
                $elementWTblBordersTop = $elementWTblBorders->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'top');
                if ($elementWTblBordersTop->length > 0) {
                    $borderStyle       = $this->getBorderStyle($elementWTblBordersTop->item(0)->getAttribute('w:val'));
                    $tableStyles       .= 'border-top: ' . $this->htmlPlugin->transformSizes($elementWTblBordersTop->item(0)->getAttribute('w:sz'), 'eights') . ' ' . $borderStyle . ' #' . $this->htmlPlugin->transformColors($elementWTblBordersTop->item(0)->getAttribute('w:color')) . ';';
                    $borderStylesTable .= 'border-top: ' . $this->htmlPlugin->transformSizes($elementWTblBordersTop->item(0)->getAttribute('w:sz'), 'eights') . ' ' . $borderStyle . ' #' . $this->htmlPlugin->transformColors($elementWTblBordersTop->item(0)->getAttribute('w:color')) . ';';
                }

                // right
                $elementWTblBordersRight = $elementWTblBorders->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'right');
                if ($elementWTblBordersRight->length > 0) {
                    $borderStyle       = $this->getBorderStyle($elementWTblBordersRight->item(0)->getAttribute('w:val'));
                    $tableStyles       .= 'border-right: ' . $this->htmlPlugin->transformSizes($elementWTblBordersRight->item(0)->getAttribute('w:sz'), 'eights') . ' ' . $borderStyle . ' #' . $this->htmlPlugin->transformColors($elementWTblBordersRight->item(0)->getAttribute('w:color')) . ';';
                    $borderStylesTable .= 'border-right: ' . $this->htmlPlugin->transformSizes($elementWTblBordersRight->item(0)->getAttribute('w:sz'), 'eights') . ' ' . $borderStyle . ' #' . $this->htmlPlugin->transformColors($elementWTblBordersRight->item(0)->getAttribute('w:color')) . ';';
                }

                // bottom
                $elementWTblBordersBottom = $elementWTblBorders->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'bottom');
                if ($elementWTblBordersBottom->length > 0) {
                    $borderStyle       = $this->getBorderStyle($elementWTblBordersBottom->item(0)->getAttribute('w:val'));
                    $tableStyles       .= 'border-bottom: ' . $this->htmlPlugin->transformSizes($elementWTblBordersBottom->item(0)->getAttribute('w:sz'), 'eights') . ' ' . $borderStyle . ' #' . $this->htmlPlugin->transformColors($elementWTblBordersBottom->item(0)->getAttribute('w:color')) . ';';
                    $borderStylesTable .= 'border-bottom: ' . $this->htmlPlugin->transformSizes($elementWTblBordersBottom->item(0)->getAttribute('w:sz'), 'eights') . ' ' . $borderStyle . ' #' . $this->htmlPlugin->transformColors($elementWTblBordersBottom->item(0)->getAttribute('w:color')) . ';';
                }

                // left
                $elementWTblBordersLeft = $elementWTblBorders->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'left');
                if ($elementWTblBordersLeft->length > 0) {
                    $borderStyle       = $this->getBorderStyle($elementWTblBordersLeft->item(0)->getAttribute('w:val'));
                    $tableStyles       .= 'border-left: ' . $this->htmlPlugin->transformSizes($elementWTblBordersLeft->item(0)->getAttribute('w:sz'), 'eights') . ' ' . $borderStyle . ' #' . $this->htmlPlugin->transformColors($elementWTblBordersLeft->item(0)->getAttribute('w:color')) . ';';
                    $borderStylesTable .= 'border-left: ' . $this->htmlPlugin->transformSizes($elementWTblBordersLeft->item(0)->getAttribute('w:sz'), 'eights') . ' ' . $borderStyle . ' #' . $this->htmlPlugin->transformColors($elementWTblBordersLeft->item(0)->getAttribute('w:color')) . ';';
                }

                // insideH
                $elementWTblBordersInsideH = $elementWTblBorders->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'insideH');
                if ($elementWTblBordersInsideH->length > 0) {
                    $borderStyle = $this->getBorderStyle($elementWTblBordersInsideH->item(0)->getAttribute('w:val'));
                    //$tableStyles .= 'border-left: ' . $this->htmlPlugin->transformSizes($elementWTblBordersInsideH->item(0)->getAttribute('w:sz'), 'eights') . ' ' . $borderStyle . ' #' . $this->htmlPlugin->transformColors($elementWTblBordersInsideH->item(0)->getAttribute('w:color')) . ';';
                    //$borderStylesTable .= 'border-left: ' . $this->htmlPlugin->transformSizes($elementWTblBordersInsideH->item(0)->getAttribute('w:sz'), 'eights') . ' ' . $borderStyle . ' #' . $this->htmlPlugin->transformColors($elementWTblBordersInsideH->item(0)->getAttribute('w:color')) . ';';

                    $borderInsideStylesTable .= 'border-bottom: ' . $this->htmlPlugin->transformSizes($elementWTblBordersInsideH->item(0)->getAttribute('w:sz'), 'eights') . ' ' . $borderStyle . ' #' . $this->htmlPlugin->transformColors($elementWTblBordersInsideH->item(0)->getAttribute('w:color')) . ';';
                    $borderInsideStylesTable .= 'border-top: ' . $this->htmlPlugin->transformSizes($elementWTblBordersInsideH->item(0)->getAttribute('w:sz'), 'eights') . ' ' . $borderStyle . ' #' . $this->htmlPlugin->transformColors($elementWTblBordersInsideH->item(0)->getAttribute('w:color')) . ';';
                }

                // insideV
                $elementWTblBordersInsideV = $elementWTblBorders->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'insideV');
                if ($elementWTblBordersInsideV->length > 0) {
                    $borderStyle = $this->getBorderStyle($elementWTblBordersInsideV->item(0)->getAttribute('w:val'));
                    //$tableStyles .= 'border-right: ' . $this->htmlPlugin->transformSizes($elementWTblBordersInsideV->item(0)->getAttribute('w:sz'), 'eights') . ' ' . $borderStyle . ' #' . $this->htmlPlugin->transformColors($elementWTblBordersInsideV->item(0)->getAttribute('w:color')) . ';';
                    //$borderStylesTable .= 'border-right: ' . $this->htmlPlugin->transformSizes($elementWTblBordersInsideV->item(0)->getAttribute('w:sz'), 'eights') . ' ' . $borderStyle . ' #' . $this->htmlPlugin->transformColors($elementWTblBordersInsideV->item(0)->getAttribute('w:color')) . ';';

                    $borderInsideStylesTable .= 'border-left: ' . $this->htmlPlugin->transformSizes($elementWTblBordersInsideV->item(0)->getAttribute('w:sz'), 'eights') . ' ' . $borderStyle . ' #' . $this->htmlPlugin->transformColors($elementWTblBordersInsideV->item(0)->getAttribute('w:color')) . ';';
                    $borderInsideStylesTable .= 'border-right: ' . $this->htmlPlugin->transformSizes($elementWTblBordersInsideV->item(0)->getAttribute('w:sz'), 'eights') . ' ' . $borderStyle . ' #' . $this->htmlPlugin->transformColors($elementWTblBordersInsideV->item(0)->getAttribute('w:color')) . ';';
                }
            }

            // floating
            $elementWTblTblpPr = $styles->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tblPr')->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tblpPr');
            if ($elementWTblTblpPr->length > 0) {
                if ($elementWTblTblpPr->item(0)->hasAttribute('w:bottomFromText')) {
                    $tableStyles .= 'margin-bottom: ' . $this->htmlPlugin->transformSizes($elementWTblTblpPr->item(0)->getAttribute('w:bottomFromText'), 'twips') . ';';
                }
                if ($elementWTblTblpPr->item(0)->hasAttribute('w:topFromText')) {
                    $tableStyles .= 'margin-top: ' . $this->htmlPlugin->transformSizes($elementWTblTblpPr->item(0)->getAttribute('w:topFromText'), 'twips') . ';';
                }
            }
        }

        $elementWTblStylePrs = $styles->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tblStylePr');
        if ($elementWTblStylePrs->length > 0) {
            // keep the right HTML order. Save the CSS styles to be added in the correct order
            $firstLastStylesValues = array('band1Horz', 'band2Horz', 'firstCol', 'firstRow', 'lastCol', 'lastRow');
            foreach ($elementWTblStylePrs as $elementWTblStylePr) {
                $selectorCell = '';
                switch ($elementWTblStylePr->getAttribute('w:type')) {
                    case 'band1Horz':
                        $firstLastStylesValues['band1Horz']['__CLASSNAMETABLE__ tr:nth-child(even)'] = $this->addPprStyles($elementWTblStylePr);

                        // rPr styles
                        $firstLastStylesValues['band1Horz']['__CLASSNAMETABLE__ tr:nth-child(even) td'] = $this->addRprStyles($elementWTblStylePr);
                        $firstLastStylesValues['band1Horz']['__CLASSNAMETABLE__ tr:nth-child(even) td'] .= $this->getTcPrStyles($elementWTblStylePr);

                        break;
                    case 'band2Horz':
                        // pPr styles
                        $firstLastStylesValues['band2Horz']['__CLASSNAMETABLE__ tr:nth-child(odd)'] = $this->addPprStyles($elementWTblStylePr);

                        // rPr styles
                        $firstLastStylesValues['band2Horz']['__CLASSNAMETABLE__ tr:nth-child(odd) td'] .= $this->addRprStyles($elementWTblStylePr);
                        $firstLastStylesValues['band2Horz']['__CLASSNAMETABLE__ tr:nth-child(odd) td'] .= $this->getTcPrStyles($elementWTblStylePr);

                        break;
                    case 'firstCol':
                        // rPr styles
                        $firstLastStylesValues['firstCol']['__CLASSNAMETABLE__ tr td:first-child'] = $this->addRprStyles($elementWTblStylePr);
                        $firstLastStylesValues['firstCol']['__CLASSNAMETABLE__ tr td:first-child'] .= $this->getTcPrStyles($elementWTblStylePr);
                        break;
                    case 'firstRow':
                        // pPr styles
                        $firstLastStylesValues['firstRow']['__CLASSNAMETABLE__ tr:first-child'] = $this->addPprStyles($elementWTblStylePr);

                        // rPr styles
                        $firstLastStylesValues['firstRow']['__CLASSNAMETABLE__ tr:first-child td'] = $this->addRprStyles($elementWTblStylePr);
                        $firstLastStylesValues['firstRow']['__CLASSNAMETABLE__ tr:first-child td'] .= $this->getTcPrStyles($elementWTblStylePr);
                        break;
                    case 'lastCol':
                        // rPr styles
                        $firstLastStylesValues['lastCol']['__CLASSNAMETABLE__ tr td:last-child'] = $this->addRprStyles($elementWTblStylePr);
                        $firstLastStylesValues['lastCol']['__CLASSNAMETABLE__ tr td:last-child'] .= $this->getTcPrStyles($elementWTblStylePr);
                        break;
                    case 'lastRow':
                        // pPr styles
                        $firstLastStylesValues['lastRow']['__CLASSNAMETABLE__ tr:last-child'] = $this->addPprStyles($elementWTblStylePr);

                        // rPr styles
                        $firstLastStylesValues['lastRow']['__CLASSNAMETABLE__ tr:last-child td'] = $this->addRprStyles($elementWTblStylePr);
                        $firstLastStylesValues['lastRow']['__CLASSNAMETABLE__ tr:last-child td'] .= $this->getTcPrStyles($elementWTblStylePr);
                        break;
                    default:
                        break;
                }
            }
            // get the correct order for $firstLastStyles
            if (isset($firstLastStylesValues['band1Horz']) && count($firstLastStylesValues['band1Horz']) > 0) {
                foreach ($firstLastStylesValues['band1Horz'] as $key => $value) {
                    $firstLastStyles[$key] = $value;
                }
            }
            if (isset($firstLastStylesValues['band2Horz']) && count($firstLastStylesValues['band2Horz']) > 0) {
                foreach ($firstLastStylesValues['band2Horz'] as $key => $value) {
                    $firstLastStyles[$key] = $value;
                }
            }
            if (isset($firstLastStylesValues['firstCol']) && count($firstLastStylesValues['firstCol']) > 0) {
                foreach ($firstLastStylesValues['firstCol'] as $key => $value) {
                    $firstLastStyles[$key] = $value;
                }
            }
            if (isset($firstLastStylesValues['lastCol']) && count($firstLastStylesValues['lastCol']) > 0) {
                foreach ($firstLastStylesValues['lastCol'] as $key => $value) {
                    $firstLastStyles[$key] = $value;
                }
            }
            if (isset($firstLastStylesValues['firstRow']) && count($firstLastStylesValues['firstRow']) > 0) {
                foreach ($firstLastStylesValues['firstRow'] as $key => $value) {
                    $firstLastStyles[$key] = $value;
                }
            }
            if (isset($firstLastStylesValues['lastRow']) && count($firstLastStylesValues['lastRow']) > 0) {
                foreach ($firstLastStylesValues['lastRow'] as $key => $value) {
                    $firstLastStyles[$key] = $value;
                }
            }
        }

        return array('tableStyles' => $tableStyles, 'borderStylesTable' => $borderStylesTable, 'borderInsideStylesTable' => $borderInsideStylesTable, 'cellPadding' => $cellPadding, 'firstLastStyles' => $firstLastStyles);
    }

    /**
     * TcPr styles
     *
     * @param string $styles
     * @return string Styles
     */
    protected function getTcPrStyles($styles)
    {
        $stylesTcPr = '';

        // cell properties
        $elementWTblTrTcTcpr = $styles->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tcPr');
        if ($elementWTblTrTcTcpr->length > 0) {
            // width
            $elementWTblTrTcTcprTcW = $elementWTblTrTcTcpr->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tcW');
            if ($elementWTblTrTcTcprTcW->length > 0) {
                $stylesTcPr .= 'width: ' . $this->htmlPlugin->transformSizes($elementWTblTrTcTcprTcW->item(0)->getAttribute('w:w'), 'twips') . ';';
            }

            // borders
            $borderCells = $this->getCellStyles($styles);
            $stylesTcPr  .= $borderCells['borderStylesCell'];

            // background
            $elementWTblTrTcTcprShd = $elementWTblTrTcTcpr->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'shd');
            if ($elementWTblTrTcTcprShd->length > 0) {
                if ($elementWTblTrTcTcprShd->item(0)->hasAttribute('w:fill') && $elementWTblTrTcTcprShd->item(0)->getAttribute('w:fill') != 'auto') {
                    $stylesTcPr .= 'background-color: #' . $elementWTblTrTcTcprShd->item(0)->getAttribute('w:fill') . ';';
                }
            }

            // paddings
            $elementWTblTrTcTcprTcMar = $elementWTblTrTcTcpr->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tcMar');
            if ($elementWTblTrTcTcprTcMar->length > 0) {
                // top
                $elementWTblTrTcTcprTcMarTop = $elementWTblTrTcTcprTcMar->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'top');
                if ($elementWTblTrTcTcprTcMarTop->length > 0) {
                    $stylesTcPr .= 'padding-top: ' . $this->htmlPlugin->transformSizes($elementWTblTrTcTcprTcMarTop->item(0)->getAttribute('w:w'), 'twips') . ';';
                }
                // right
                $elementWTblTrTcTcprTcMarRight = $elementWTblTrTcTcprTcMar->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'right');
                if ($elementWTblTrTcTcprTcMarRight->length > 0) {
                    $stylesTcPr .= 'padding-right: ' . $this->htmlPlugin->transformSizes($elementWTblTrTcTcprTcMarRight->item(0)->getAttribute('w:w'), 'twips') . ';';
                }
                // bottom
                $elementWTblTrTcTcprTcMarBottom = $elementWTblTrTcTcprTcMar->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'bottom');
                if ($elementWTblTrTcTcprTcMarBottom->length > 0) {
                    $stylesTcPr .= 'padding-bottom: ' . $this->htmlPlugin->transformSizes($elementWTblTrTcTcprTcMarBottom->item(0)->getAttribute('w:w'), 'twips') . ';';
                }
                // left
                $elementWTblTrTcTcprTcMarLeft = $elementWTblTrTcTcprTcMar->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'left');
                if ($elementWTblTrTcTcprTcMarLeft->length > 0) {
                    $stylesTcPr .= 'padding-left: ' . $this->htmlPlugin->transformSizes($elementWTblTrTcTcprTcMarLeft->item(0)->getAttribute('w:w'), 'twips') . ';';
                }
            }

            // vertical align
            $elementWTblTrTcTcprVAlign = $elementWTblTrTcTcpr->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'vAlign');
            if ($elementWTblTrTcTcprVAlign->length > 0) {
                $vAlign = 'middle';
                switch ($elementWTblTrTcTcprVAlign->item(0)->getAttribute('w:val')) {
                    case 'top':
                        $vAlign = 'top';
                        break;
                    case 'bottom':
                        $vAlign = 'bottom';
                        break;
                    case 'both':
                    case 'center':
                        $vAlign = 'middle';
                        break;
                    default:
                        $vAlign = 'top';
                        break;
                }

                $stylesTcPr .= 'vertical-align: ' . $vAlign . ';';
            }
        }

        return $stylesTcPr;
    }

    /**
     * Get target value of a relationship
     *
     * @param string $id
     * @return string target or null
     */
    protected function getRelationshipContent($id)
    {
        if ($this->target == 'comments') {
            $relsContent = $this->docxStructure->getContent('word/_rels/comments.xml.rels');
        } else if ($this->target == 'endnotes') {
            $relsContent = $this->docxStructure->getContent('word/_rels/endnotes.xml.rels');
        } else if ($this->target == 'footnotes') {
            $relsContent = $this->docxStructure->getContent('word/_rels/footnotes.xml.rels');
        } else if ($this->target == 'headers') {
            $relsContent = $this->docxStructure->getContent('word/_rels/'.$this->targetExtra.'.rels');
        } else if ($this->target == 'footers') {
            $relsContent = $this->docxStructure->getContent('word/_rels/'.$this->targetExtra.'.rels');
        } else {
            $relsContent = $this->docxStructure->getContent('word/_rels/document.xml.rels');
        }


        $xmlDocumentRels = new DOMDocument();
        $xmlDocumentRels->loadXML($relsContent);
        $xpath = new DOMXPath($xmlDocumentRels);
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $elementId = $xpath->query('//r:Relationships/r:Relationship[@Id="'.$id.'"]')->item(0);


        if (!$elementId || !$elementId->hasAttribute('Target')) {
            return null;
        }

        return $elementId->getAttribute('Target');
    }

    /**
     * Set current rPr styles
     */
    protected function setCurrentStylesRpr() {
        $this->currentStylesRpr = array();

        // document default styles
        $this->currentStylesRpr = $this->defaultStyles;

        // pPr styles
        if (isset($this->currentStylesPpr['ppr_background_color'])) {
            $this->currentStylesRpr['rpr_background_color'] = $this->currentStylesPpr['ppr_background_color'];
        }
    }

    /**
     * Transform default tag (not supported tag)
     *
     * @param DOMElement $childNode
     * @param String $nodeClass
     */
    protected function transformDEFAULT_TAG($childNode, $nodeClass)
    {
        // handle child elements
        if ($childNode->hasChildNodes()) {
            $this->transformXml($childNode);
        }
    }

    /**
     * Transform w:bookmarkstart tag
     *
     * @param DOMElement $childNode
     * @param String $nodeClass
     */
    protected function transformW_BOOKMARKSTART($childNode, $nodeClass)
    {
        if ($childNode->hasAttribute('w:name')) {
            $this->html .= '<a class="'.$nodeClass.'" name="'.$childNode->getAttribute('w:name').'"></a>';
        }
    }

    /**
     * Transform w:br tag
     *
     * @param DOMElement $childNode
     * @param String $nodeClass
     */
    protected function transformW_BR($childNode, $nodeClass)
    {
        if ($childNode->hasAttribute('w:type') && $childNode->getAttribute('w:type') == 'page') {
            $this->html .= '<' . $this->htmlPlugin->getTag('br') . ' type="page">';
        } else {
            $this->html .= '<' . $this->htmlPlugin->getTag('br') . '>';
        }
    }

    /**
     * Transform w:comment tag
     *
     * @param DOMElement $childNode
     * @param String $nodeClass
     */
    protected function transformW_COMMENT($childNode, $nodeClass)
    {
        if (trim($childNode->nodeValue) != '') {
            $this->commentsContent = '<span id="comment-' . $childNode->getAttribute('w:id') . '">' . $this->commentsIndex['PHPDOCX_COMMENTREFERENCE_' . $childNode->getAttribute('w:id')] . '</span> ';

            // handle child elements
            if ($childNode->hasChildNodes()) {
                $this->transformXml($childNode);
            }
        }
    }

    /**
     * Transform w:commentreference tag
     *
     * @param DOMElement $childNode
     * @param String $nodeClass
     */
    protected function transformW_COMMENTREFERENCE($childNode, $nodeClass)
    {
        // if the reference already has a custom mark do not add the placeholder
        if (!$childNode->hasAttribute('w:customMarkFollows')) {
            $this->commentsIndex['PHPDOCX_COMMENTREFERENCE_' . $childNode->getAttribute('w:id')] = '[COMMENT ' . (count($this->commentsIndex) + 1) . ']';

            $this->html .= '<a href="#comment-' . $childNode->getAttribute('w:id') . '">' . '[COMMENT ' . count($this->commentsIndex) . ']' . '</a> ';
        }
    }

    /**
     * Transform w:drawing tag
     *
     * @param DOMElement $childNode
     * @param String $nodeClass
     */
    protected function transformW_DRAWING($childNode, $nodeClass)
    {
        $elementABlip  = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'blip')->item(0);
        $elementCChart = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/chart', 'chart')->item(0);

        if ($elementABlip) {
            // image drawing
            $target      = $this->getRelationshipContent($elementABlip->getAttribute('r:embed'));
            $imageString = $this->docxStructure->getContent('word/' . $target);

            if (!$target) {
                // external images
                $src = $this->getRelationshipContent($elementABlip->getAttribute('r:link'));
            } else {
                // embedded images
                $fileInfo = pathinfo($target);
                $ext      = $fileInfo['extension'];
                if (!empty($ext)) {
                    // handle WMF images converting them to PNG
                    if ($ext == 'wmf' && extension_loaded('imagick')) {
                        $im = new Imagick();
                        $im->readImageBlob($imageString);
                        $im->setImageFormat('png');
                        $ext         = 'png';
                        $imageString = $im->getImageBlob();
                    }
                    if ($this->htmlPlugin->getImagesAsBase64()) {
                        $src = 'data:image/' . $ext . ';base64,' . base64_encode($imageString);
                    } else {
                        $src = $this->htmlPlugin->getOutputFilesPath() . $fileInfo['filename'] . '.' . $ext;
                        file_put_contents($src, $imageString);
                    }
                }
            }

            // width and height
            $elementWPExtent = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing', 'extent')->item(0);
            $width           = round((float)$elementWPExtent->getAttribute('cx') / 9525);
            $height          = round((float)$elementWPExtent->getAttribute('cy') / 9525);

            // spacing
            $elementWPEffectExtent = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing', 'effectExtent')->item(0);
            if ($elementWPEffectExtent) {
                $this->css[$nodeClass] .= 'margin-top: ' . round((float)$elementWPEffectExtent->getAttribute('t') / 9525) . 'px;';
                $this->css[$nodeClass] .= 'margin-right: ' . round((float)$elementWPEffectExtent->getAttribute('r') / 9525) . 'px;';
                $this->css[$nodeClass] .= 'margin-bottom: ' . round((float)$elementWPEffectExtent->getAttribute('b') / 9525) . 'px;';
                $this->css[$nodeClass] .= 'margin-left: ' . round((float)$elementWPEffectExtent->getAttribute('l') / 9525) . 'px;';
            }

            // used for float and text wrapping. If true, don't use text wrapping
            $alignMode = false;

            // float and horizontal position
            $elementWPPositionH = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing', 'positionH');
            if ($elementWPPositionH->length > 0) {
                $elementWPAlign = $elementWPPositionH->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing', 'align');
                if ($elementWPAlign->length > 0) {
                    $alignMode = true;
                    if ($elementWPAlign->item(0)->nodeValue == 'right') {
                        $this->css[$nodeClass] .= 'float: right;';
                    } elseif ($elementWPAlign->item(0)->nodeValue == 'left') {
                        $this->css[$nodeClass] .= 'float: left;';
                    } elseif ($elementWPAlign->item(0)->nodeValue == 'center') {
                        $this->css[$nodeClass] .= 'display:block; margin-left: auto; margin-right: auto;';
                    }
                }

                $elementWPPosOffset = $elementWPPositionH->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing', 'posOffset');
                if ($elementWPPosOffset->length > 0) {
                    $this->css[$nodeClass] .= 'margin-left: ' . round((float)$elementWPPosOffset->item(0)->nodeValue / 9525) . 'px;';
                }
            }

            // vertical position
            $elementWPPositionV = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing', 'positionV');
            if ($elementWPPositionV->length > 0) {
                $elementWPPosOffset = $elementWPPositionV->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing', 'posOffset');
                if ($elementWPPosOffset->length > 0) {
                    $this->css[$nodeClass] .= 'margin-top: ' . round((float)$elementWPPosOffset->item(0)->nodeValue / 9525) . 'px;';
                }
            }

            // border
            $elementALn = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'ln');
            if ($elementALn->length > 0) {
                // if no fill avoid adding the border
                $elementALnNoFill = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'noFill');
                if ($elementALnNoFill->length <= 0) {
                    // color
                    $borderColor    = '#000000';
                    $elementSrgbClr = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'srgbClr');
                    if ($elementSrgbClr->length > 0) {
                        $borderColor = '#' . $elementSrgbClr->item(0)->getAttribute('val');
                    }
                    // style
                    $borderStyle     = 'solid';
                    $elementPrstDash = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'prstDash');
                    if ($elementPrstDash->length > 0) {
                        switch ($elementPrstDash->item(0)->getAttribute('val')) {
                            case 'dash':
                            case 'dashDot':
                            case 'lgDash':
                            case 'lgDashDot':
                            case 'lgDashDotDot':
                            case 'sysDash':
                            case 'sysDashDot':
                            case 'sysDashDotDot':
                                $borderStyle ='dashed';
                                break;
                            case 'dot':
                            case 'sysDot':
                                $borderStyle ='dotted';
                                break;
                            case 'solid':
                                $borderStyle = 'solid';
                                break;
                            default:
                                $borderStyle = 'solid';
                                break;
                        }
                    }
                    // width
                    $borderWidth = 1;
                    if ($elementALn->item(0)->hasAttribute('w')) {
                        $borderWidth = round((float)$elementALn->item(0)->getAttribute('w') / 9525);
                    }

                    $this->css[$nodeClass] .= 'border: ' . $borderWidth . 'px ' . $borderStyle . ' ' . $borderColor . ';';
                }
            }

            // text wrap
            if ($childNode->childNodes->item(0)->tagName == 'wp:inline') {
                // inline tag
                $this->css[$nodeClass] .= 'display: inline;';
            } else {
                // anchor tag

                // wrapSquare
                $elementWrapSquare = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing', 'wrapSquare');
                if ($elementWrapSquare->length > 0) {
                    if ($alignMode === false) {
                        $this->css[$nodeClass] .= 'float: left;';
                    }
                }

                // wrapNone
                $elementWrapNone = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing', 'wrapNone');
                if ($elementWrapNone->length > 0) {
                    if ($alignMode === false) {
                        if ($childNode->childNodes->item(0)->hasAttributes() && $childNode->childNodes->item(0)->hasAttribute('behindDoc')) {
                            $this->css[$nodeClass] .= 'position: absolute;';
                            // image is set as back
                            if ($childNode->childNodes->item(0)->getAttribute('behindDoc') == '1') {
                                $this->css[$nodeClass] .= 'z-index: -1;';
                            }
                        }
                    }
                }
            }

            // link
            $linkTag            = false;
            $elementAHlinkClick = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'hlinkClick');
            if ($elementAHlinkClick->length > 0) {
                $targetLink = $this->getRelationshipContent($elementAHlinkClick->item(0)->getAttribute('r:id'));
                $this->html .= '<a href="' . $targetLink . '" target="_blank">';

                $linkTag = true;
            }

            $this->html .= '<' . $this->htmlPlugin->getTag('image') . ' class="' . $nodeClass . ' ' . ($this->htmlPlugin->getExtraClass('img')==null?'':$this->htmlPlugin->getExtraClass('img')) . '" src="' . $src . '" width="' . $width . '" height="' . $height . '">';

            if ($linkTag === true) {
                $this->html .= '</a>';
            }
        }
    }

    /**
     * Transform w:endnote tag
     *
     * @param DOMElement $childNode
     * @param String $nodeClass
     */
    protected function transformW_ENDNOTE($childNode, $nodeClass)
    {
        if (trim($childNode->nodeValue) != '') {
            $this->endnotesContent = '<span id="endnote-' . $childNode->getAttribute('w:id') . '">' . $this->endnotesIndex['PHPDOCX_ENDNOTEREFERENCE_' . $childNode->getAttribute('w:id')] . '</span> ';

            // handle child elements
            if ($childNode->hasChildNodes()) {
                $this->transformXml($childNode);
            }
        }
    }

    /**
     * Transform w:endnotereference tag
     *
     * @param DOMElement $childNode
     * @param String $nodeClass
     */
    protected function transformW_ENDNOTEREFERENCE($childNode, $nodeClass)
    {
        // if the reference already has a custom mark do not add the placeholder
        if (!$childNode->hasAttribute('w:customMarkFollows')) {
            $table  = array('m' => 1000, 'cm' => 900, 'd' => 500, 'cd' => 400, 'c' => 100, 'xc' => 90, 'l' => 50, 'xl' => 40, 'x' => 10, 'ix' => 9, 'v' => 5, 'iv' => 4, 'i' => 1);
            $return = '';

            $endnotesIndex = count($this->endnotesIndex) + 1;
            while ($endnotesIndex > 0)  {
                foreach ($table as $rom => $arb) {
                    if ($endnotesIndex >= $arb) {
                        $endnotesIndex -= $arb;
                        $return        .= $rom;
                        break;
                    }
                }
            }

            $this->endnotesIndex['PHPDOCX_ENDNOTEREFERENCE_' . $childNode->getAttribute('w:id')] = $return;

            $this->html .= '<a href="#endnote-' . $childNode->getAttribute('w:id') . '">' . strtolower($return) . '</a> ';
        }
    }

    /**
     * Transform w:fldchar tag
     *
     * @param DOMElement $childNode
     * @param String $nodeClass
     */
    protected function transformW_FLDCHAR($childNode, $nodeClass)
    {
        if ($childNode->hasAttribute('w:fldCharType') && $childNode->getAttribute('w:fldCharType') == 'end') {
            if ($this->complexField && $this->complexField['type'] == 'FORMCHECKBOX') {
                $this->complexField = null;
            }

            if ($this->complexField && $this->complexField['type'] == 'FORMDROPDOWN') {
                $this->complexField = null;
            }

            if ($this->complexField && $this->complexField['type'] == 'FORMTEXT') {
                $this->complexField = null;
            }

            if ($this->complexField && $this->complexField['type'] == 'HYPERLINK') {
                // end complex field
                $this->html .= '</a>';

                // clear pending CLASS_COMPLEX_FIELD placeholders
                $this->html = str_replace('{{ CLASS_COMPLEX_FIELD }}', '', $this->html);

                $this->complexField = null;
            }

            if ($this->complexField && $this->complexField['type'] == 'PAGEREF') {
                // end complex field
                $this->html .= '</a>';

                $this->complexField = null;
            }

            if ($this->complexField && $this->complexField['type'] == 'TIME') {
                $this->complexField = null;
            }
        }
    }

    /**
     * Transform w:footnote tag
     *
     * @param DOMElement $childNode
     * @param String $nodeClass
     */
    protected function transformW_FOOTNOTE($childNode, $nodeClass)
    {
        if (trim($childNode->nodeValue) != '') {
            $this->footnotesContent = '<span id="footnote-' . $childNode->getAttribute('w:id') . '">' . $this->footnotesIndex['PHPDOCX_FOOTNOTEREFERENCE_' . $childNode->getAttribute('w:id')] . '</span> ';

            // handle child elements
            if ($childNode->hasChildNodes()) {
                $this->transformXml($childNode);
            }
        }
    }

    /**
     * Transform w:footnotereference tag
     *
     * @param DOMElement $childNode
     * @param String $nodeClass
     */
    protected function transformW_FOOTNOTEREFERENCE($childNode, $nodeClass)
    {
        // if the reference already has a custom mark do not add the placeholder
        if (!$childNode->hasAttribute('w:customMarkFollows')) {
            $this->footnotesIndex['PHPDOCX_FOOTNOTEREFERENCE_' . $childNode->getAttribute('w:id')] = (count($this->footnotesIndex) + 1);

            $this->html .= '<a href="#footnote-' . $childNode->getAttribute('w:id') . '">' . count($this->footnotesIndex) . '</a> ';
        }
    }

    /**
     * Transform w:hyperlink tag
     *
     * @param DOMElement $childNode
     * @param String $nodeClass
     */
    protected function transformW_HYPERLINK($childNode, $nodeClass)
    {
        $target = $this->getRelationshipContent($childNode->getAttribute('r:id'));

        $this->html .= '<' . $this->htmlPlugin->getTag('hyperlink') . ' class="'.$nodeClass.' ' . ($this->htmlPlugin->getExtraClass('hyperlink')==null?'':$this->htmlPlugin->getExtraClass('hyperlink')) . '" href="'.$target.'" target="_blank">';

        // handle child elements
        if ($childNode->hasChildNodes()) {
            $this->transformXml($childNode);
        }

        $this->html .= '</' . $this->htmlPlugin->getTag('hyperlink') . '>';
    }

    /**
     * Transform w:instrtext tag
     *
     * @param DOMElement $childNode
     * @param String $nodeClass
     */
    protected function transformW_INSTRTEXT($childNode, $nodeClass)
    {
        // get element type
        $contentComplexField = explode(' ', ltrim($childNode->nodeValue));
        if (is_array($contentComplexField) && isset($contentComplexField[0])) {
            if ($contentComplexField[0] == 'FORMCHECKBOX') {
                // get the parent w:p to know if the checkbox is enabled or not
                $nodePInstrText  = $childNode->parentNode->parentNode;
                $xpathPInstrText = new DOMXPath($nodePInstrText->ownerDocument);
                $xpathPInstrText->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                $defaultNodes = $xpathPInstrText->query('//w:checkBox/w:default', $nodePInstrText);
                if ($defaultNodes->length > 0) {
                    if ($defaultNodes->item(0)->hasAttribute('w:val') && $defaultNodes->item(0)->getAttribute('w:val') == '1') {
                        $this->html .= '<input type="checkbox" name="checkbox_'.rand().'" value="checkbox_'.rand().'" checked="checked">';
                    } else {
                        $this->html .= '<input type="checkbox" name="checkbox_'.rand().'" value="checkbox_'.rand().'">';
                    }
                } else {
                    $this->html .= '<input type="checkbox" name="checkbox_'.rand().'" value="checkbox_'.rand().'">';
                }

                $this->complexField = array('type' => 'FORMCHECKBOX');
            }

            if ($contentComplexField[0] == 'FORMDROPDOWN') {
                $this->html .= '<select name="select_'.rand().'" class="{{ CLASS_COMPLEX_FIELD }}">';

                // get and add the select items
                $nodePInstrText  = $childNode->parentNode->parentNode;
                $xpathPInstrText = new DOMXPath($nodePInstrText->ownerDocument);
                $xpathPInstrText->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                $listEntryNodes = $xpathPInstrText->query('//w:ddList/w:listEntry', $nodePInstrText);

                foreach ($listEntryNodes as $listEntryNode) {
                    $this->html .= '<option value="'.$listEntryNode->getAttribute('w:val').'">'.$listEntryNode->getAttribute('w:val').'</option>';
                }

                $this->html         .= '</select>';
                $this->complexField = array('type' => 'FORMDROPDOWN');
            }

            if ($contentComplexField[0] == 'FORMTEXT') {
                $this->html .= '<input name="input_'.rand().'" class="{{ CLASS_COMPLEX_FIELD }}" type="text" value="{{ VALUE_COMPLEX_FIELD }}" size="{{ VALUE_COMPLEX_FIELD_SIZE }}">';

                $this->complexField = array('type' => 'FORMTEXT');
            }

            if ($contentComplexField[0] == 'HYPERLINK') {
                $this->html .= '<a class="{{ CLASS_COMPLEX_FIELD }}" href="'.str_replace(array('&quot;', '"'), '', $contentComplexField[1]).'" target="_blank">';

                $this->complexField = array('type' => 'HYPERLINK');
            }

            if ($contentComplexField[0] == 'PAGEREF') {
                $this->html .= '<a class="{{ CLASS_COMPLEX_FIELD }}" href="#'.$contentComplexField[1].'">';

                $this->complexField = array('type' => 'PAGEREF');
            }

            if ($contentComplexField[0] == 'TIME') {
                // remove TIME \@ values and get the date
                array_shift($contentComplexField);
                array_shift($contentComplexField);

                // transform OOXML date format to PHP format
                // join the content of the complex field
                $date = join(' ', $contentComplexField);
                // split by symbol
                $dateElements = preg_split('/(\w+)/', $date, -1, PREG_SPLIT_DELIM_CAPTURE);
                // iterate each content to transform the date
                $dateTransformed = '';
                foreach ($dateElements as $dateElement) {
                    switch ($dateElement) {
                        case 'yyyy':
                            $dateTransformed .= date('Y');
                            break;
                        case 'yy':
                            $dateTransformed .= date('Y');
                            break;
                        case 'MMMM':
                            $dateTransformed .= date('F');
                            break;
                        case 'MM':
                            $dateTransformed .= date('m');
                            break;
                        case 'dd':
                            $dateTransformed .= date('d');
                            break;
                        case 'H':
                            $dateTransformed .= date('H');
                            break;
                        case 'mm':
                            $dateTransformed .= date('i');
                            break;
                        case 'ss':
                            $dateTransformed .= date('s');
                            break;
                        default:
                            // remove extra characters from the DATE
                            $dateElementTransformed = str_replace(array('"', '\''), '', $dateElement);
                            $dateTransformed        .= $dateElementTransformed;
                    }
                }

                $this->html .= '<span>' . $dateTransformed . '</span>';

                $this->complexField = array('type' => 'TIME');
            }
        }
    }

    /**
     * Transform w:p tag
     *
     * @param DOMElement $childNode
     * @param String $nodeClass
     */
    protected function transformW_P($childNode, $nodeClass)
    {
        // if it's an internal section avoid adding the paragraph
        $sectPrTag = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'sectPr');
        if ($sectPrTag->length > 0) {
            $this->transformXml($childNode);
            return;
        }

        // handle tag

        // default element
        $elementTag = $this->htmlPlugin->getTag('paragraph');

        // heading tag
        $outlineLvlTag = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'outlineLvl');
        if ($outlineLvlTag->length > 0 && $outlineLvlTag->item(0)->hasAttribute('w:val')) {
            $elementTag = $this->htmlPlugin->getTag('heading') . ((int)$outlineLvlTag->item(0)->getAttribute('w:val') + 1);
        }

        // numbering tag
        if (is_array($this->numberingParagraph)) {
            // handle as p tags

            // handle numbering in paragraph
            $numPrTag = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'numPr');
            // handle numbering in pStyle
            if ($numPrTag->length == 0) {
                $pStyle = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'pStyle');
                if ($pStyle->length > 0) {
                    $pStyleId    = $pStyle->item(0)->getAttribute('w:val');
                    $xpathStyles = new DOMXPath($this->stylesDocxDOM);
                    $xpathStyles->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                    $numPrTag = $xpathStyles->query('//w:style[@w:styleId="' . $pStyleId . '"]/w:pPr/w:numPr');
                }
            }

            if ($numPrTag->length > 0) {
                $numIdTag = $numPrTag->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'numId');
                if ($numIdTag->length > 0 && $numIdTag->item(0)->hasAttribute('w:val') && $numIdTag->item(0)->getAttribute('w:val') != '') {
                    $numPrIlvlTag = $numPrTag->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'ilvl');
                    if ($numPrIlvlTag->length > 0) {
                        $numberingLevel = (int)$numPrTag->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'ilvl')->item(0)->getAttribute('w:val');
                    } else {
                        // if there's no level tag, such as numbering in paragraph styles, set it as 0
                        $numberingLevel = 0;
                    }
                    $listStartValue = $this->getNumberingStart($numIdTag->item(0)->getAttribute('w:val'), $numberingLevel);
                    $listLvlText    = $this->getNumberingLvlText($numIdTag->item(0)->getAttribute('w:val'), $numberingLevel);

                    // get the numbering style based on the numbering ID and its level
                    $numberingStyle = $this->getNumberingType($numIdTag->item(0)->getAttribute('w:val'), $numberingLevel);
                    if (!$numberingStyle && $numIdTag->item(0)->getAttribute('w:val') == '0') {
                        $numberingStyle = 'none';
                    }

                    // restart the value if it's the first time it appears or the level has changed and the new level is higher than the olf one
                    if (!isset($this->numberingParagraph['value'][$numberingLevel . $numIdTag->item(0)->getAttribute('w:val')]) || ($this->numberingParagraph['level'] != $numberingLevel && (int)$this->numberingParagraph['level'] < (int)$numberingLevel)) {
                        $this->numberingParagraph['value'][$numberingLevel . $numIdTag->item(0)->getAttribute('w:val')] = $listStartValue;
                    } else {
                        $this->numberingParagraph['value'][$numberingLevel . $numIdTag->item(0)->getAttribute('w:val')]++;
                    }

                    $this->numberingParagraph['level'] = $numberingLevel;
                    $this->numberingParagraph['numId'] = $numIdTag->item(0)->getAttribute('w:val');

                    switch ($numberingStyle) {
                        case 'bullet':
                            // default value
                            $this->prependTValue = '•'  . ' ';
                            if ($listLvlText == 'o') {
                                $this->prependTValue = '◦'  . ' ';
                            }
                            break;
                        case 'decimal':
                            // iterate numberLevel to handle level list when displaying sublevels such as 1.1. 1.2
                            for ($i = $numberingLevel; $i >= 0; $i--) {
                                $listLvlText = str_replace('%' . ($i + 1), $this->numberingParagraph['value'][$i . $numIdTag->item(0)->getAttribute('w:val')], $listLvlText) . ' ';
                            }
                            $this->prependTValue = $listLvlText;
                            break;
                        case 'lowerLetter':
                            for ($i = $numberingLevel; $i >= 0; $i--) {
                                $listLvlText = str_replace('%' . ($i + 1), chr((ord('a') + ($this->numberingParagraph['value'][$i . $numIdTag->item(0)->getAttribute('w:val')] - 1))), $listLvlText) . ' ';
                            }
                            $this->prependTValue = $listLvlText;
                            break;
                        case 'lowerRoman':
                            for ($i = $numberingLevel; $i >= 0; $i--) {
                                $listLvlText = str_replace('%' . ($i + 1), strtolower($this->transformIntegerToRoman($this->numberingParagraph['value'][$i . $numIdTag->item(0)->getAttribute('w:val')])), $listLvlText) . ' ';
                            }
                            $this->prependTValue = $listLvlText;
                            break;
                        case 'upperLetter':
                            for ($i = $numberingLevel; $i >= 0; $i--) {
                                $listLvlText = str_replace('%' . ($i + 1), chr((ord('A') + ($this->numberingParagraph['value'][$i . $numIdTag->item(0)->getAttribute('w:val')] - 1))), $listLvlText) . ' ';
                            }
                            $this->prependTValue = $listLvlText;
                            break;
                        case 'upperRoman':
                            for ($i = $numberingLevel; $i >= 0; $i--) {
                                $listLvlText = str_replace('%' . ($i + 1), strtoupper($this->transformIntegerToRoman($this->numberingParagraph['value'][$i . $numIdTag->item(0)->getAttribute('w:val')])), $listLvlText) . ' ';
                            }
                            $this->prependTValue = $listLvlText;
                            break;
                        case 'none':
                            $this->prependTValue = '';
                            break;
                        default:
                            break;
                    }
                }
            }
        } else {
            // handle as ul or or tags
            $numPrTag = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'numPr');
            if ($numPrTag->length > 0) {
                // get w:numId to know the ID of the list
                $numIdTag = $numPrTag->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'numId');
                if ($numIdTag->length > 0 && $numIdTag->item(0)->hasAttribute('w:val') && $numIdTag->item(0)->getAttribute('w:val') != '') {
                    // handle start list number
                    if (!isset($this->listStartValues[$numIdTag->item(0)->getAttribute('w:val')])) {
                        $this->listStartValues[$numIdTag->item(0)->getAttribute('w:val')] = array();
                    }
                    $numberingLevel = (int)$numPrTag->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'ilvl')->item(0)->getAttribute('w:val');
                    // handle start list number
                    if (!isset($this->listStartValues[$numIdTag->item(0)->getAttribute('w:val')][$numberingLevel])) {
                        // check if there's a start value in the numbering style
                        $startValue                                                                        = $this->getNumberingStart($numIdTag->item(0)->getAttribute('w:val'), $numberingLevel);
                        $this->listStartValues[$numIdTag->item(0)->getAttribute('w:val')][$numberingLevel] = $startValue;
                    } else {
                        $this->listStartValues[$numIdTag->item(0)->getAttribute('w:val')][$numberingLevel] = $this->listStartValues[$numIdTag->item(0)->getAttribute('w:val')][$numberingLevel] + 1;
                    }

                    // get the numbering style based on the numbering ID and its level
                    $numberingStyle = $this->getNumberingType($numIdTag->item(0)->getAttribute('w:val'), $numberingLevel);
                    if (!$numberingStyle && $numIdTag->item(0)->getAttribute('w:val') == '0') {
                        $numberingStyle = 'none';
                    }

                    // check if the previous sibling is a numbering.
                    // If there's no previous sibling or the ID or level aren't the same starts a new list
                    $previousSiblingElement = $numPrTag->item(0)->parentNode->parentNode->previousSibling;
                    $initNewList            = false;
                    if ($previousSiblingElement === null) {
                        $initNewList = true;
                    }

                    if ($previousSiblingElement !== null) {
                        $numPrPreviousSiblingElement = $previousSiblingElement->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'numPr');
                        if ($numPrPreviousSiblingElement->length > 0) {
                            // the previous element is a numbering
                            $numIdPreviousSiblingElementTag = $numPrPreviousSiblingElement->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'numId');
                            if ($numIdPreviousSiblingElementTag->length > 0 && $numIdPreviousSiblingElementTag->item(0)->hasAttribute('w:val') && $numIdPreviousSiblingElementTag->item(0)->getAttribute('w:val') != '') {
                                $numberingLevelPreviousSiblingElementTag = (int)$numPrPreviousSiblingElement->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'ilvl')->item(0)->getAttribute('w:val');
                                // get the numbering style based on the numbering ID and its level
                                $numberingStylePreviousSiblingElementTag = $this->getNumberingType($numIdPreviousSiblingElementTag->item(0)->getAttribute('w:val'), $numberingLevelPreviousSiblingElementTag);

                                if ($numIdPreviousSiblingElementTag->item(0)->getAttribute('w:val') != $numIdTag->item(0)->getAttribute('w:val')) {
                                    $initNewList = true;
                                }

                                if ($numberingLevelPreviousSiblingElementTag < $numberingLevel) {
                                    $initNewList = true;
                                }
                            }
                        } else {
                            // the previous element is not a numbering, then create a new list
                            $initNewList = true;
                        }
                    }

                    // create the new list
                    if ($initNewList === true) {
                        if (in_array($numberingStyle, array('decimal', 'upperRoman', 'lowerRoman', 'upperLetter', 'lowerLetter'))) {
                            $tagTypeList = $this->htmlPlugin->getTag('orderedList');
                        } else {
                            $tagTypeList = $this->htmlPlugin->getTag('unorderedList');
                        }
                        switch ($numberingStyle) {
                            case 'bullet':
                                $this->css[$nodeClass] .= 'list-style-type: disc;';
                                break;
                            case 'decimal':
                                $this->css[$nodeClass] .= 'list-style-type: decimal;';
                                break;
                            case 'lowerLetter':
                                $this->css[$nodeClass] .= 'list-style-type: lower-alpha;';
                                break;
                            case 'lowerRoman':
                                $this->css[$nodeClass] .= 'list-style-type: lower-roman;';
                                break;
                            case 'upperLetter':
                                $this->css[$nodeClass] .= 'list-style-type: upper-alpha;';
                                break;
                            case 'upperRoman':
                                $this->css[$nodeClass] .= 'list-style-type: upper-roman;';
                                break;
                            case 'none':
                                $this->css[$nodeClass] .= 'list-style-type: none;';
                                break;
                            default:
                                break;
                        }
                        $this->html .= '<'.$tagTypeList.' class="'.$nodeClass.' ' . ($this->htmlPlugin->getExtraClass('list')==null?'':$this->htmlPlugin->getExtraClass('list')) . '" ' . 'start="' . $this->listStartValues[$numIdTag->item(0)->getAttribute('w:val')][$numberingLevel] . '">';
                    }
                }

                $elementTag = $this->htmlPlugin->getTag('itemList');
            }
        }

        // paragraph style
        $pStyle   = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'pStyle');
        $pStyleId = null;
        if ($pStyle->length > 0) {
            $pStyleId = $pStyle->item(0)->getAttribute('w:val');
            if (!empty($pStyleId)) {
                $this->css[$nodeClass . ' .paragraph_' . $pStyleId] = '';
            }
        }

        // handle styles
        if ($childNode->hasChildNodes()) {
            if ($elementTag == $this->htmlPlugin->getTag('itemList')) {
                // numbering styles
                $numberingLevelTags = $this->getNumberingStyles($numIdTag->item(0)->getAttribute('w:val'), $numberingLevel);

                // pPr styles
                if ($pStyleId) {
                    $this->css[$nodeClass . ' .paragraph_' . $pStyleId] .= $this->addPprStyles($numberingLevelTags);
                }
                $this->css[$nodeClass] .= $this->addPprStyles($numberingLevelTags);

                if ($pStyleId) {
                    $this->css[$nodeClass . ' .paragraph_' . $pStyleId] .= 'text-indent: 0px; margin-left: 0px; margin-right: 0px;';
                }
                $this->css[$nodeClass] .= 'text-indent: 0px; margin-left: 0px; margin-right: 0px;';

                // rPr styles
                if ($pStyleId) {
                    $this->css[$nodeClass . ' .paragraph_' . $pStyleId] .= $this->addRprStyles($numberingLevelTags);
                }
                $this->css[$nodeClass] .= $this->addRprStyles($numberingLevelTags);
            } else {
                $pPrTag = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'pPr');
                // check if there's a pPr tag
                if ($pPrTag->length > 0) {
                    if (is_array($this->numberingParagraph)) {
                        // numbering styles
                        if ($numIdTag && $numIdTag->length > 0) {
                            $numberingLevelTags    = $this->getNumberingStyles($numIdTag->item(0)->getAttribute('w:val'), $numberingLevel);
                            $this->css[$nodeClass] .= $this->addPprStyles($numberingLevelTags, 'numberingStyleParagraph');
                        }

                        // numbering styles in custom paragraph styles
                        $pStyle = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'pStyle');
                        if ($pStyle->length > 0) {
                            $pStyleId    = $pStyle->item(0)->getAttribute('w:val');
                            $xpathStyles = new DOMXPath($this->stylesDocxDOM);
                            $xpathStyles->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                            $numIdTag = $xpathStyles->query('//w:style[@w:styleId="' . $pStyleId . '"]/w:pPr/w:numPr/w:numId');
                            if ($numIdTag->length > 0) {
                                $numberingLevelTags = $this->getNumberingStyles($numIdTag->item(0)->getAttribute('w:val'), $numberingLevel);
                                if ($pStyleId) {
                                    $this->css[$nodeClass . ' .paragraph_' . $pStyleId] .= $this->addPprStyles($numberingLevelTags, 'numberingStyleParagraph');
                                }
                                $this->css[$nodeClass] .= $this->addPprStyles($numberingLevelTags, 'numberingStyleParagraph');
                            }
                        }
                    }

                    // pPr styles
                    if ($pStyleId) {
                        $this->css[$nodeClass . ' .paragraph_' . $pStyleId] .= $this->addPprStyles($childNode);
                    }
                    $this->css[$nodeClass] .= $this->addPprStyles($childNode);

                    // rPr styles
                    if ($pStyleId) {
                        $this->css[$nodeClass . ' .paragraph_' . $pStyleId] .= $this->addRprStyles($pPrTag->item(0));
                    }
                    $this->css[$nodeClass] .= $this->addRprStyles($pPrTag->item(0));
                }
            }
        }

        // remove extra , and . before adding it to the HTML
        $nodeClassHTML = str_replace(array(',', '.'), '', $nodeClass);

        $this->html .= '<'.$elementTag.' class="'.$nodeClassHTML.' ' . ($this->htmlPlugin->getExtraClass('list')==null?'':$this->htmlPlugin->getExtraClass('paragraph')) . '">';

        // handle child elements
        if ($childNode->hasChildNodes()) {
            $this->transformXml($childNode);
        }

        $this->html .= '</'.$elementTag.'>';

        // numbering tag
        if (!is_array($this->numberingParagraph)) {
            // handle as ul or or tags
            $numPrTag = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'numPr');
            if ($numPrTag->length > 0) {
                // check if the next sibling is a numbering.
                // If there's no next sibling or the ID isn't the same or level is lower close the list
                $nextSiblingElement = $numPrTag->item(0)->parentNode->parentNode->nextSibling;
                $closeNewList       = false;
                if ($nextSiblingElement === null) {
                    $closeNewList = true;
                }

                // sets how many list levels must be closed
                $iterationListClose = 1;

                if ($nextSiblingElement !== null) {
                    $numPrNextSiblingElement = $nextSiblingElement->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'numPr');
                    if ($numPrNextSiblingElement->length > 0) {
                        // the next element is a numbering
                        $numIdNextSiblingElementTag = $numPrNextSiblingElement->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'numId');
                        if ($numIdNextSiblingElementTag->length > 0 && $numIdNextSiblingElementTag->item(0)->hasAttribute('w:val') && $numIdNextSiblingElementTag->item(0)->getAttribute('w:val') != '') {
                            $numberingLevelNextSiblingElementTag = (int)$numPrNextSiblingElement->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'ilvl')->item(0)->getAttribute('w:val');
                            // get the numbering style based on the numbering ID and its level
                            $numberingStyleNextSiblingElementTag = $this->getNumberingType($numIdNextSiblingElementTag->item(0)->getAttribute('w:val'), $numberingLevelNextSiblingElementTag);

                            // handle close list levels
                            if ($numberingLevel > 0 && $numIdNextSiblingElementTag->item(0)->getAttribute('w:val') != $numIdTag->item(0)->getAttribute('w:val')) {
                                $closeNewList       = true;
                                $iterationListClose += $numberingLevel;
                            }

                            if ($numIdNextSiblingElementTag->item(0)->getAttribute('w:val') != $numIdTag->item(0)->getAttribute('w:val')) {
                                $closeNewList = true;
                            }

                            if ($numberingLevelNextSiblingElementTag < $numberingLevel) {
                                $closeNewList = true;
                                if ($numberingLevel > 1) {
                                    $iterationListClose = $numberingLevel - $numberingLevelNextSiblingElementTag;
                                }
                            }
                        }
                    } else {
                        // the next element is not a numbering, then close the list
                        $closeNewList = true;

                        // handle close list levels
                        if ($numberingLevel > 0) {
                            $iterationListClose += $numberingLevel;
                        }
                    }
                }

                // get the numbering style based on the numbering ID and its level
                $numberingStyle = $this->getNumberingType($numIdTag->item(0)->getAttribute('w:val'), $numberingLevel);
                if (in_array($numberingStyle, array('decimal', 'upperRoman', 'lowerRoman', 'upperLetter', 'lowerLetter'))) {
                    $tagTypeList = $this->htmlPlugin->getTag('orderedList');
                } else {
                    $tagTypeList = $this->htmlPlugin->getTag('unorderedList');
                }
                if ($closeNewList === true) {
                    unset($this->listStartValues[$numIdTag->item(0)->getAttribute('w:val')]);
                    for ($iClose = 0; $iClose < $iterationListClose; $iClose++) {
                        $this->html .= '</'.$tagTypeList.'>';
                    }
                }
            }
        }
    }

    /**
     * Transform w:r tag
     *
     * @param DOMElement $childNode
     * @param String $nodeClass
     */
    protected function transformW_R($childNode, $nodeClass)
    {
        // default element
        $elementTag = $this->htmlPlugin->getTag('span');

        // sup or sub element
        $vertAlignTag = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'vertAlign');
        if ($vertAlignTag->length > 0) {
            if ($vertAlignTag->item(0)->getAttribute('w:val') == 'superscript') {
                $elementTag = $this->htmlPlugin->getTag('superscript');
            } elseif ($vertAlignTag->item(0)->getAttribute('w:val') == 'subscript') {
                $elementTag = $this->htmlPlugin->getTag('subscript');
            }
        }

        // bidi element
        $bidiTag = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'bidi');
        if ($bidiTag->length > 0 && $bidiTag->item(0)->getAttribute('w:val') == 'on') {
            $elementTag = $this->htmlPlugin->getTag('bidi');
        }

        // character style
        $rStyle   = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'rStyle');
        $rStyleId = null;
        if ($rStyle->length > 0) {
            $rStyleId = $rStyle->item(0)->getAttribute('w:val');
            if (!empty($rStyleId)) {
                $this->css[$nodeClass . ' .character_' . $rStyleId]            = '';
                $this->css['_span.' . $nodeClass . ' .character_' . $rStyleId] = '';
            }
        }
        $this->css['_span.' . $nodeClass] = '';

        // handle styles
        if ($childNode->hasChildNodes()) {
            if ($rStyleId) {
                $this->css[$nodeClass . ' .character_' . $rStyleId]            .= $this->addRprStyles($childNode);
                $this->css['_span.' . $nodeClass . ' .character_' . $rStyleId] .= $this->addRprStyles($childNode);
            }
            // rPr styles
            $this->css[$nodeClass]            .= $this->addRprStyles($childNode);
            $this->css['_span.' . $nodeClass] .= $this->addRprStyles($childNode);
        }

        // if it's a text in a complex field, reuse the CSS class in the complex tag.
        // Use a CSS only if there's a w:t in it to avoid other complex field tags
        if ($this->complexField !== null) {
            $tTag = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 't');
            if ($tTag->length > 0) {
                $this->html = str_replace('{{ CLASS_COMPLEX_FIELD }}', $nodeClass, $this->html);
            }
        }

        // remove extra , and . before adding it to the HTML
        $nodeClassHTML = str_replace(array(',', '.'), '', $nodeClass);

        $this->html .= '<'.$elementTag.' class="'.$nodeClassHTML.' ' . ($this->htmlPlugin->getExtraClass('span')==null?'':$this->htmlPlugin->getExtraClass('span')) . '">';

        // get endnote contents if any exist. This avoid adding the endnote content out of the <p> tag
        if ($this->endnotesContent != null) {
            $this->html            .= $this->endnotesContent;
            $this->endnotesContent = null;
        }

        // get footnote contents if any exist. This avoid adding the footnote content out of the <p> tag
        if ($this->footnotesContent != null) {
            $this->html             .= $this->footnotesContent;
            $this->footnotesContent = null;
        }

        // get comment contents if any exist. This avoid adding the comment content out of the <p> tag
        if ($this->commentsContent != null) {
            $this->html            .= $this->commentsContent;
            $this->commentsContent = null;
        }

        // handle child elements
        if ($childNode->hasChildNodes()) {
            $this->transformXml($childNode);
        }

        $this->html .= '</'.$elementTag.'>';
    }

    /**
     * Transform w:sectpr tag
     *
     * @param DOMElement $childNode
     * @param String $nodeClass
     */
    protected function transformW_SECTPR($childNode, $nodeClass)
    {
        // keep headers and footers to be added to the section
        $headerContentSection = '';
        $footerContentSection = '';

        // parse sectPr tags and add the CSS values to the current section CSS class
        $sectionCSS = '';
        /*foreach ($childNode->childNodes as $childNodesSection) {
            switch ($childNodesSection->nodeName) {
                case 'w:headerReference':
                    $target = $this->getRelationshipContent($childNodesSection->getAttribute('r:id'));

                    $headerContentSection = $this->headersContent['word/' . $target];
                    break;
                case 'w:footerReference':
                    $target = $this->getRelationshipContent($childNodesSection->getAttribute('r:id'));

                    $footerContentSection = $this->footersContent['word/' . $target];
                    break;
                default:
                    break;
            }
        }*/

        // add headers and footers
        if (!empty($headerContentSection)) {
            // remove the headaer placeholder to add the header contents to the correct place
            $this->html = str_replace('__HEADERCONTENTSECTION__', $headerContentSection, $this->html);
        } else {
            // as there's no header contents, remove the placeholder
            $this->html = str_replace('__HEADERCONTENTSECTION__', '', $this->html);
        }
        if (!empty($footerContentSection)) {
            $this->html .= $footerContentSection;
        }

        $this->currentSection++;

        // if there're more sections create them before contents are added
        // get the first section to generate the initial page
        $this->xmlXpathBody = new DOMXPath($this->xmlBody);
        $this->xmlXpathBody->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        // first section size
        $querySection = '//w:sectPr';
        $secptPrNodes = $this->xmlXpathBody->query($querySection);
        if ($secptPrNodes->length > $this->currentSection) {
            $this->addSection();
        }
    }

    /**
     * Transform w:tbl tag
     *
     * @param DOMElement $childNode
     * @param String $nodeClass
     */
    protected function transformW_TBL($childNode, $nodeClass)
    {
        $borderStylesTable       = '';
        $cellPadding             = '';
        $borderInsideStylesTable = '';

        // table styles tblStyle
        $elementsWTblprTblStyle = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tblStyle');
        if ($elementsWTblprTblStyle->length > 0) {
            $tableStyleId = $elementsWTblprTblStyle->item(0)->getAttribute('w:val');
            if (!empty($tableStyleId)) {
                // get table styles
                $xpathStyles = new DOMXPath($this->stylesDocxDOM);
                $xpathStyles->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                $stylesTbl = $xpathStyles->query('//w:style[@w:styleId="' . $tableStyleId . '"]');
                if ($stylesTbl->length > 0) {
                    $stylesTable           = $this->getTableStyles($stylesTbl->item(0));
                    $this->css[$nodeClass] .= $stylesTable['tableStyles'];

                    // add extra properties replacing pending __CLASSNAMETABLE__ placeholders by the class name
                    if (isset($stylesTable['firstLastStyles']) && is_array($stylesTable['firstLastStyles'])) {
                        foreach ($stylesTable['firstLastStyles'] as $keyFirstLastStyles => $valueFirstLastStyles) {
                            if (!isset($this->css[str_replace('__CLASSNAMETABLE__', $nodeClass, $keyFirstLastStyles)])) {
                                $this->css[str_replace('__CLASSNAMETABLE__', $nodeClass, $keyFirstLastStyles)] = $valueFirstLastStyles;
                            } else {
                                $this->css[str_replace('__CLASSNAMETABLE__', $nodeClass, $keyFirstLastStyles)] .= $valueFirstLastStyles;
                            }
                        }
                    }

                    $borderStylesTable       .= $stylesTable['borderStylesTable'];
                    $cellPadding             .= $stylesTable['cellPadding'];
                    $borderInsideStylesTable .= $stylesTable['borderInsideStylesTable'];
                }
            }
        }

        // table properties
        $elementWTblPr = $childNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tblPr');
        if ($elementWTblPr->length > 0) {
            $stylesTable           = $this->getTableStyles($childNode);
            $this->css[$nodeClass] .= $stylesTable['tableStyles'];

            // add extra properties replacing pending __CLASSNAMETABLE__ placeholders by the class name
            if (isset($stylesTable['firstLastStyles']) && is_array($stylesTable['firstLastStyles'])) {
                foreach ($stylesTable['firstLastStyles'] as $keyFirstLastStyles => $valueFirstLastStyles) {
                    $this->css[str_replace('__CLASSNAMETABLE__', $nodeClass, $keyFirstLastStyles)] .= $valueFirstLastStyles;
                }
            }

            $borderStylesTable .= $stylesTable['borderStylesTable'];
            $cellPadding .= $stylesTable['cellPadding'];
            $borderInsideStylesTable .= $stylesTable['borderInsideStylesTable'];
        }

        // default values
        $this->css[$nodeClass] .= 'border-spacing: 0; border-collapse: collapse;';

        $this->html .= '<table class="' . $nodeClass . '">';

        // rows
        $xpathDOMXPathWTblTr = new DOMXPath($childNode->ownerDocument);
        $xpathDOMXPathWTblTr->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $elementsWTblTr = $xpathDOMXPathWTblTr->query('w:tr', $childNode);

        // needed to set tblStylePr by tr position
        $indexTr = 0;

        // keep rowspan values
        $rowspan = array();
        foreach ($elementsWTblTr as $elementWTblTr) {
            // row class
            $nodeTrClass = $this->htmlPlugin->generateClassName();
            $this->css[$nodeTrClass] = '';
            $this->html              .= '<' . $this->htmlPlugin->getTag('tr') . ' class="' . $nodeTrClass . ' ' . ($this->htmlPlugin->getExtraClass('tr')==null?'':$this->htmlPlugin->getExtraClass('tr')) . '">';

            // row styles tblStylePr
            $elementWTblTrPr = $elementWTblTr->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'trPr');
            if ($elementWTblTrPr->length > 0) {
                // height
                $elementWTblTrHeight = $elementWTblTr->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'trHeight');
                if ($elementWTblTrHeight->length > 0) {
                    $this->css[$nodeTrClass] = 'height: ' . $this->htmlPlugin->transformSizes($elementWTblTrHeight->item(0)->getAttribute('w:val'), 'twips') . ';';
                }
            }

            // cells   
            $xpathDOMXPathWTblTrTc = new DOMXPath($elementWTblTr->ownerDocument);
            $xpathDOMXPathWTblTrTc->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $elementsWTblTrTc = $xpathDOMXPathWTblTrTc->query('w:tc|w:sdt', $elementWTblTr);
            // needed to set tblStylePr by td position
            $indexTd = 0;
            // colspan
            $colspan                 = 1;
            foreach ($elementsWTblTrTc as $elementWTblTrTc) {
                // cell class
                $nodeTdClass                    = $this->htmlPlugin->generateClassName();
                $this->css[$nodeTdClass]        = '';
                $this->css[$nodeTdClass . ' p'] = '';

                // avoid adding td if there're pending colspans
                if ($colspan > 1) {
                    $colspan--;
                }

                // colspan property
                $elementWTblTrTcTcprGridSpan = $elementWTblTrTc->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'gridSpan');
                if ($elementWTblTrTcTcprGridSpan->length > 0) {
                    $colspan = $elementWTblTrTcTcprGridSpan->item(0)->getAttribute('w:val');
                } else {
                    $colspan = 1;
                }

                // rowspan property
                $elementWTblTrTcTcprVMerge = $elementWTblTrTc->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'vMerge');
                $rowspanValue              = null;
                if ($elementWTblTrTcTcprVMerge->length > 0) {
                    $rowspanValue = $elementWTblTrTcTcprVMerge->item(0)->getAttribute('w:val');
                    if ($rowspanValue == 'restart') {
                        $rowspan['__ROWSPANVALUE__' . $indexTr . '__' . $indexTd . '__'] = 1;
                    } else {
                        $activeRowspan    = true;
                        $maxActiveRowspan = 1;
                        foreach ($rowspan as $rowspanKeys => $rowspanValues) {
                            if ($rowspanKeys == '__ROWSPANVALUE__' . ($indexTr - 1) . '__' . $indexTd . '__') {
                                $rowSpanValueRow = explode('__', $rowspanKeys);
                                if ((@(int)$rowSpanValueRow[2] - @(int)$rowSpanValueRowPrevious[2]) > 1) {
                                    $activeRowspan = false;
                                }
                                $maxActiveRowspan = @(int)$rowSpanValueRow[2];
                            }
                            $rowSpanValueRowPrevious = explode('__', $rowspanKeys);
                        }

                        if ($activeRowspan) {
                            $continue = true;
                            for ($i = 1; $i < 9; $i++) {
                                if (isset($rowspan['__ROWSPANVALUE__' . ($indexTr - $i) . '__' . $indexTd . '__']) && $maxActiveRowspan > 0) {
                                    $rowspan['__ROWSPANVALUE__' . ($indexTr - $i) . '__' . $indexTd . '__'] += 1;
                                    $maxActiveRowspan--;
                                }
                            }
                        } else {
                            $rowspan['__ROWSPANVALUE__' . ($indexTr - 1) . '__' . $indexTd . '__'] += 1;
                        }

                        // MS Word avoid adding tc tags when there're colspan.
                        // Sum the current indexTD to colspan and remove one value as it's added later
                        if ($colspan > 1) {
                            $indexTd = $indexTd + $colspan - 1;
                        }

                        $indexTd++;
                        continue;
                    }
                }

                // add td tag
                $this->html .= '<' . $this->htmlPlugin->getTag('tc') . ' class="' . $nodeTdClass . ' ' . ($this->htmlPlugin->getExtraClass('td')==null?'':$this->htmlPlugin->getExtraClass('td')) . '" ';

                // add colspan value
                if ($colspan > 1) {
                    $this->html .= 'colspan="' . $colspan . '" ';
                }

                // add rowspan value
                if ($rowspanValue !== null && $rowspanValue == 'restart') {
                    $this->html .= 'rowspan="__ROWSPANVALUE__' . $indexTr . '__' . $indexTd . '__"';
                }

                // add td end >
                $this->html .= '>';

                // default values
                $this->css[$nodeTdClass] .= 'vertical-align: top;';

                // tr styles
                if (!empty($this->css[$nodeTrClass])) {
                    $this->css[$nodeTdClass] .= $this->css[$nodeTrClass];
                }

                // table border styles
                if (!empty($borderStylesTable)) {
                    $this->css[$nodeTdClass] .= $borderStylesTable;
                }

                // inside border styles
                if (!empty($borderInsideStylesTable)) {
                    $this->css[$nodeTdClass] .= $borderInsideStylesTable;
                }

                //  table padding properties
                if (!empty($cellPadding)) {
                    $this->css[$nodeTdClass] .= $cellPadding;
                }

                // cell properties
                $elementWTblTrTcTcpr = $elementWTblTrTc->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tcPr');
                if ($elementWTblTrTcTcpr->length > 0) {
                    // width
                    $elementWTblTrTcTcprTcW = $elementWTblTrTcTcpr->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tcW');
                    if ($elementWTblTrTcTcprTcW->length > 0) {
                        $this->css[$nodeTdClass] .= 'width: ' . $this->htmlPlugin->transformSizes($elementWTblTrTcTcprTcW->item(0)->getAttribute('w:w'), 'twips') . ';';
                    }

                    // borders
                    $borderCells             = $this->getCellStyles($elementWTblTrTc);
                    $this->css[$nodeTdClass] .= $borderCells['borderStylesCell'];

                    // background
                    $elementWTblTrTcTcprShd = $elementWTblTrTcTcpr->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'shd');
                    if ($elementWTblTrTcTcprShd->length > 0) {
                        if ($elementWTblTrTcTcprShd->item(0)->hasAttribute('w:fill') && $elementWTblTrTcTcprShd->item(0)->getAttribute('w:fill') != 'auto') {
                            $this->css[$nodeTdClass] .= 'background-color: #' . $elementWTblTrTcTcprShd->item(0)->getAttribute('w:fill') . ';';
                        }
                    }

                    // paddings
                    $elementWTblTrTcTcprTcMar = $elementWTblTrTcTcpr->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'tcMar');
                    if ($elementWTblTrTcTcprTcMar->length > 0) {
                        // top
                        $elementWTblTrTcTcprTcMarTop = $elementWTblTrTcTcprTcMar->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'top');
                        if ($elementWTblTrTcTcprTcMarTop->length > 0) {
                            $this->css[$nodeTdClass] .= 'padding-top: ' . $this->htmlPlugin->transformSizes($elementWTblTrTcTcprTcMarTop->item(0)->getAttribute('w:w'), 'twips') . ';';
                        } else {
                            $this->css[$nodeTdClass]        .= 'padding-top: ' . $this->htmlPlugin->transformSizes(0, 'twips') . ';';
                            $this->css[$nodeTdClass . ' p'] .= 'margin-top: ' . $this->htmlPlugin->transformSizes(0, 'twips') . ';';
                        }
                        // right
                        $elementWTblTrTcTcprTcMarRight = $elementWTblTrTcTcprTcMar->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'right');
                        if ($elementWTblTrTcTcprTcMarRight->length > 0) {
                            $this->css[$nodeTdClass] .= 'padding-right: ' . $this->htmlPlugin->transformSizes($elementWTblTrTcTcprTcMarRight->item(0)->getAttribute('w:w'), 'twips') . ';';
                        }
                        // bottom
                        $elementWTblTrTcTcprTcMarBottom = $elementWTblTrTcTcprTcMar->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'bottom');
                        if ($elementWTblTrTcTcprTcMarBottom->length > 0) {
                            $this->css[$nodeTdClass] .= 'padding-bottom: ' . $this->htmlPlugin->transformSizes($elementWTblTrTcTcprTcMarBottom->item(0)->getAttribute('w:w'), 'twips') . ';';
                        } else {
                            $this->css[$nodeTdClass]        .= 'padding-bottom: ' . $this->htmlPlugin->transformSizes(0, 'twips') . ';';
                            $this->css[$nodeTdClass . ' p'] .= 'margin-bottom: ' . $this->htmlPlugin->transformSizes(0, 'twips') . ';';
                        }
                        // left
                        $elementWTblTrTcTcprTcMarLeft = $elementWTblTrTcTcprTcMar->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'left');
                        if ($elementWTblTrTcTcprTcMarLeft->length > 0) {
                            $this->css[$nodeTdClass] .= 'padding-left: ' . $this->htmlPlugin->transformSizes($elementWTblTrTcTcprTcMarLeft->item(0)->getAttribute('w:w'), 'twips') . ';';
                        }
                    } else {
                        // default values
                        $this->css[$nodeTdClass]        .= 'padding-top: ' . $this->htmlPlugin->transformSizes(0, 'twips') . ';';
                        $this->css[$nodeTdClass]        .= 'padding-bottom: ' . $this->htmlPlugin->transformSizes(0, 'twips') . ';';
                        $this->css[$nodeTdClass . ' p'] .= 'margin-top: ' . $this->htmlPlugin->transformSizes(0, 'twips') . ';';
                        $this->css[$nodeTdClass . ' p'] .= 'margin-bottom: ' . $this->htmlPlugin->transformSizes(0, 'twips') . ';';
                    }
                }

                // handle contents
                if ($elementWTblTrTc->hasChildNodes()) {
                    $this->transformXml($elementWTblTrTc);
                }

                $this->html .= '</' . $this->htmlPlugin->getTag('tc') . '>';

                // MS Word avoid adding tc tags when there're colspan-
                // Sum the current indexTD to colspan and remove one value as it's added later
                if ($colspan > 1) {
                    $indexTd = $indexTd + $colspan - 1;
                }

                // increment td index
                $indexTd++;
            }

            $this->html .= '</' . $this->htmlPlugin->getTag('tr') . '>';

            // increment tr index
            $indexTr++;
        }

        // replace ROWSPAN_ placeholders by their values
        if (is_array($rowspan) && count($rowspan) > 0) {
            foreach ($rowspan as $keyRowspan => $valueRowspan) {
                $this->html = str_replace($keyRowspan, $valueRowspan, $this->html);
            }
        }

        $this->html .= '</' . $this->htmlPlugin->getTag('table') . '>';
    }

    /**
     * Transform w:t tag
     *
     * @param DOMElement $childNode
     * @param String $nodeClass
     */
    protected function transformW_T($childNode, $nodeClass)
    {
        // avoid adding complex field text contents such as date with complex field TIME
        if ($this->complexField !== null && $this->complexField['type'] == 'TIME') {
            return;
        }

        $childNodeValue = trim($childNode->nodeValue);
        if ($this->complexField !== null && $this->complexField['type'] == 'FORMTEXT' && !empty($childNodeValue)) {
            $this->html = str_replace('{{ VALUE_COMPLEX_FIELD }}', $childNode->nodeValue, $this->html);
            $this->html = str_replace('{{ VALUE_COMPLEX_FIELD_SIZE }}', strlen($childNode->nodeValue), $this->html);
            return;
        }

        $nodeValue = $childNode->nodeValue;

        // prepend a text value if some exists
        if ($this->prependTValue != null) {
            $nodeValue           = $this->prependTValue . $nodeValue;
            $this->prependTValue = null;
        }

        $this->html .= $nodeValue;
    }

    /**
     * Transform an integer into its roman value
     *
     * @param integer $value
     */
    protected function transformIntegerToRoman($value) {
        $table  = array('m' => 1000, 'cm' => 900, 'd' => 500, 'cd' => 400, 'c' => 100, 'xc' => 90, 'l' => 50, 'xl' => 40, 'x' => 10, 'ix' => 9, 'v' => 5, 'iv' => 4, 'i' => 1);
        $return = '';

        while ($value > 0)  {
            foreach ($table as $rom => $arb) {
                if ($value >= $arb) {
                    $value  -= $arb;
                    $return .= $rom;
                    break;
                }
            }
        }

        return $return;
    }

}
