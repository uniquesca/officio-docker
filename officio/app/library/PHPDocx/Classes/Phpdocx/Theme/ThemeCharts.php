<?php

namespace Phpdocx\Theme;

use DOMDocument;
use DOMXPath;

/**
 * Theme chart methods
 *
 * @category   Phpdocx
 * @package    theme
 * @copyright  Copyright (c) Narcea Producciones Multimedia S.L.
 *             (http://www.2mdc.com)
 * @license    phpdocx LICENSE
 * @link       https://www.phpdocx.com
 */
class ThemeCharts
{
    /**
     * Chart DOM
     */
    private $chartDom;

    /**
     * Chart DOM Xpath
     */
    private $chartDomXPath;

    /**
     * Theme charts
     *
     * @access public
     * @param string $chartContent
     * @param array $theme Theme options
     * @return string
     */
    public function theme($chartContent, $theme)
    {
        // load the chart to change the attributes from $theme values
        $this->chartDom = new DOMDocument();
        if (PHP_VERSION_ID < 80000) {
            $optionEntityLoader = libxml_disable_entity_loader(true);
        }
        $this->chartDom->loadXML($chartContent);
        if (PHP_VERSION_ID < 80000) {
            libxml_disable_entity_loader($optionEntityLoader);
        }
        $this->chartDomXPath = new DOMXPath($this->chartDom);
        $this->chartDomXPath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $this->chartDomXPath->registerNamespace('c', 'http://schemas.openxmlformats.org/drawingml/2006/chart');

        if (isset($theme['horizontalAxis'])) {
            $this->themeTextAxis($chartContent, $theme['horizontalAxis'], '//c:plotArea//c:catAx');
        }

        if (isset($theme['verticalAxis'])) {
            $this->themeTextAxis($chartContent, $theme['verticalAxis'], '//c:plotArea//c:valAx');
        }

        if (isset($theme['chartArea'])) {
            $this->themeSppr($chartContent, $theme['chartArea'], '//c:chartSpace');
        }

        if (isset($theme['legendArea'])) {
            $this->themeSppr($chartContent, $theme['legendArea'], '//c:legend');
        }

        if (isset($theme['plotArea'])) {
            $this->themeSppr($chartContent, $theme['plotArea'], '//c:plotArea', false);
        }

        if (isset($theme['majorGridlines'])) {
            $this->themeMajorGridlines($chartContent, $theme['majorGridlines'], '//c:valAx');
        }

        return $this->chartDom->saveXML();
    }

    /**
     * Theme majorGridlines tags
     *
     * @access public
     * @param string $chartContent
     * @param array $theme Theme options
     * @param array $query Query string
     * @return string
     */
    private function themeMajorGridlines($chartContent, $theme, $query)
    {
        // apply styles and properties creating new DOM elements if needed
        $chartNodes = $this->chartDomXPath->query($query . '/c:majorGridlines');
        if ($chartNodes->length > 0) {
            $elementMajorGridlines = $chartNodes->item(0);
        } else {
            $chartNodesPlotArea    = $this->chartDomXPath->query($query);
            $elementMajorGridlines = $chartNodesPlotArea->item(0)->ownerDocument->createElementNS('http://schemas.openxmlformats.org/drawingml/2006/chart', 'majorGridlines');

            $chartNodesPlotArea->item(0)->appendChild($elementMajorGridlines);
        }
        $chartNodesSpPr = $elementMajorGridlines->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'spPr');
        if ($chartNodesSpPr->length > 0) {
            $elementSpPr = $chartNodesSpPr->item(0);
        } else {
            $elementSpPr = $elementMajorGridlines->ownerDocument->createElementNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'spPr');

            $elementMajorGridlines->appendChild($elementSpPr);
        }

        // default values
        $styleCap   = 'flat';
        $styleColor = '000000';
        $styleDash  = null;
        $styleWidth = 9525;

        // text styles
        if (isset($theme['capType'])) {
            $styleCap = $theme['capType'];
        }
        if (isset($theme['color'])) {
            $styleColor = $theme['color'];
        }
        if (isset($theme['dashType'])) {
            $styleDash = $theme['dashType'];
        }
        if (isset($theme['width'])) {
            $styleWidth = (int)$theme['width'];
        }

        $xmlGridLines = '<a:ln algn="ctr" cap="'.$styleCap.'" cmpd="dbl" w="'.$styleWidth.'"><a:solidFill><a:srgbClr val="'.$styleColor.'"/></a:solidFill>';
        if ($styleDash != null) {
            $xmlGridLines .= '<a:prstDash val="'.$styleDash.'"/>';
        }
        $xmlGridLines .= '</a:ln><a:effectLst/>';
        $tempNode     = $elementSpPr->ownerDocument->createDocumentFragment();
        @$tempNode->appendXML($xmlGridLines);
        $elementSpPr->appendChild($tempNode);
    }

    /**
     * Theme sppr tags
     *
     * @access public
     * @param string $chartContent
     * @param array $theme Theme options
     * @param array $query Query string
     * @param bool $themeTextAxis Apply text axis
     * @return string
     */
    private function themeSppr($chartContent, $theme, $query, $themeTextAxis = true)
    {
        // apply styles and properties creating new DOM elements if needed
        $chartNodes = $this->chartDomXPath->query($query . '/c:spPr');
        if ($chartNodes->length > 0) {
            $elementSpPr = $chartNodes->item(0);
        } else {
            $chartNodesPlotArea = $this->chartDomXPath->query($query);
            $elementSpPr        = $chartNodesPlotArea->item(0)->ownerDocument->createElementNS('http://schemas.openxmlformats.org/drawingml/2006/chart', 'spPr');

            $chartNodesPlotArea->item(0)->appendChild($elementSpPr);
        }
        $chartNodesSolidFill = $elementSpPr->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'solidFill');
        if ($chartNodesSolidFill->length > 0) {
            $elementSolidFill = $chartNodesSolidFill->item(0);
        } else {
            $elementSolidFill = $elementSpPr->ownerDocument->createElementNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'solidFill');

            $elementSpPr->insertBefore($elementSolidFill, $elementSpPr->firstChild);
        }

        if (isset($theme['backgroundColor'])) {
            $chartNodesSrgbClr = $elementSolidFill->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'srgbClr');
            if ($chartNodesSrgbClr->length > 0) {
                $elementSrgbClr = $chartNodesSrgbClr->item(0);
            } else {
                $elementSrgbClr = $elementSolidFill->ownerDocument->createElementNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'srgbClr');

                $elementSolidFill->appendChild($elementSrgbClr);
            }
            $elementSrgbClr->setAttribute('val', $theme['backgroundColor']);
        }

        $chartNodesEffectLst = $elementSpPr->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'effectLst');
        if ($chartNodesEffectLst->length == 0) {
            $elementEffectLst = $elementSpPr->ownerDocument->createElementNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'effectLst');

            $elementSpPr->appendChild($elementEffectLst);
        }

        if ($themeTextAxis) {
            // apply text styles
            $this->themeTextAxis($chartContent, $theme, $query);
        }
    }

    /**
     * Theme text axis
     *
     * @access public
     * @param string $chartContent
     * @param array $theme Theme options
     * @param array $query Query string
     * @return string
     */
    private function themeTextAxis($chartContent, $theme, $query)
    {
        // default values
        $rot            = '0';
        $anchor         = 't';
        $anchorCtr      = '0';
        $styleBold      = '0';
        $styleItalic    = '0';
        $styleSize      = '900';
        $styleUnderline = 'none';

        // rotation
        if (isset($theme['textDirection'])) {
            if ($theme['textDirection'] == 'rotate90') {
                $rot       = '5400000';
                $anchor    = 'ctr';
                $anchorCtr = '1';
            } else if ($theme['textDirection'] == 'rotate270') {
                $rot       = '-5400000';
                $anchor    = 'ctr';
                $anchorCtr = '1';
            }
        }

        // text styles
        if (isset($theme['textBold']) && $theme['textBold'] == true) {
            $styleBold = '1';
        }
        if (isset($theme['textItalic']) && $theme['textItalic'] == true) {
            $styleItalic = '1';
        }
        if (isset($theme['textSize'])) {
            $styleSize = (int)$theme['textSize'] * 100;
        }
        if (isset($theme['textUnderline'])) {
            $styleUnderline = $theme['textUnderline'];
        }

        // apply styles and properties creating new DOM elements if needed
        $chartNodesAx = $this->chartDomXPath->query($query);
        if ($chartNodesAx->length > 0) {
            $chartNodesAxTxPr = $chartNodesAx->item(0)->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/chart', 'txPr');
            if ($chartNodesAxTxPr->length > 0) {
                $elementTxPr = $chartNodesAxTxPr->item(0);
            } else {
                $elementTxPr = $chartNodesAx->item(0)->ownerDocument->createElementNS('http://schemas.openxmlformats.org/drawingml/2006/chart', 'txPr');

                $chartNodesAx->item(0)->appendChild($elementTxPr);
            }
            $chartNodesAxTxPrBodyPr = $elementTxPr->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'bodyPr');
            if ($chartNodesAxTxPrBodyPr->length > 0) {
                $elementTxPrBodyPr = $chartNodesAxTxPrBodyPr->item(0);
            } else {
                $elementTxPrBodyPr = $elementTxPr->ownerDocument->createElementNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'bodyPr');

                $elementTxPr->appendChild($elementTxPrBodyPr);
            }
            $elementTxPrBodyPr->setAttribute('rot', $rot);
            $elementTxPrBodyPr->setAttribute('anchor', $anchor);
            $elementTxPrBodyPr->setAttribute('anchorCtr', $anchorCtr);
            $tempNode = $elementTxPr->ownerDocument->createDocumentFragment();
            @$tempNode->appendXML('<a:lstStyle/><a:p><a:pPr><a:defRPr b="'.$styleBold.'" baseline="0" i="'.$styleItalic.'" kern="1200" strike="noStrike" sz="'.$styleSize.'" u="'.$styleUnderline.'"><a:ln><a:noFill/></a:ln><a:solidFill><a:schemeClr val="tx1"><a:lumMod val="65000"/><a:lumOff val="35000"/></a:schemeClr></a:solidFill><a:latin typeface="+mn-lt"/><a:ea typeface="+mn-ea"/><a:cs typeface="+mn-cs"/></a:defRPr></a:pPr></a:p>');
            $elementTxPr->appendChild($tempNode);
        }
    }
}