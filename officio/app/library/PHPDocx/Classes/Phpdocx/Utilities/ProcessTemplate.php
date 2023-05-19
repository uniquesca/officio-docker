<?php

namespace Phpdocx\Utilities;

use DOMDocument;
use DOMXPath;

/**
 * Process a DOCX to get the best performance when using the document as a template.
 *
 * @category   Phpdocx
 * @package    performance
 * @copyright  Copyright (c) Narcea Producciones Multimedia S.L.
 *             (http://www.2mdc.com)
 * @license    phpdocx LICENSE
 * @link       https://www.phpdocx.com
 */
class ProcessTemplate
{
    /**
     *
     * @access public
     * @static
     * @var string
     */
    public static $regExprVariableSymbols = '\$(?:\{|[^{$]*\>\{)[^}$]*\}';

    /**
     * Process the template to optimize the performance
     *
     * @access public
     * @param string $source DOCX Source path of the template to optimize
     * @param string $target DOCX Destination path of the optimized template.
     * @param array $variables Array of variables to optimize.
     * @param string $templateSymbolStart Template symbol
     * @param string $templateSymbolEnd use $templateSymbolStart if null
     * @return void
     */
    public function optimizeTemplate($source, $target, $variables = array(), $templateSymbolStart = '$', $templateSymbolEnd = null)
    {
        $zipDocx = new DOCXStructure();
        $zipDocx->parseDocx($source);

        if (is_null($templateSymbolEnd)) {
            $templateSymbolEnd = $templateSymbolStart;
        }

        $contentTypeT = $zipDocx->getContent('[Content_Types].xml');

        // main document
        $loadContent = $zipDocx->getContent('word/document.xml');
        $stringDoc   = $this->repairVariables($variables, $loadContent, $templateSymbolStart, $templateSymbolEnd);
        $stringDoc   = $this->removeExtraTags($stringDoc);
        $zipDocx->addContent('word/document.xml', $stringDoc);

        // headers
        $xpathHeaders = simplexml_load_string($contentTypeT);
        $xpathHeaders->registerXPathNamespace('ns', 'http://schemas.openxmlformats.org/package/2006/content-types');
        $xpathHeadersResults = $xpathHeaders->xpath('ns:Override[@ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"]');
        foreach ($xpathHeadersResults as $headersResults) {
            $header      = substr($headersResults['PartName'], 1);
            $loadContent = $zipDocx->getContent($header);
            $dom         = $this->repairVariables($variables, $loadContent, $templateSymbolStart, $templateSymbolEnd);
            $stringDoc   = $this->removeExtraTags($dom);
            $zipDocx->addContent($header, $stringDoc);
        }

        // footers
        $xpathFooters = simplexml_load_string($contentTypeT);
        $xpathFooters->registerXPathNamespace('ns', 'http://schemas.openxmlformats.org/package/2006/content-types');
        $xpathFootersResults = $xpathFooters->xpath('ns:Override[@ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"]');
        foreach ($xpathFootersResults as $footersResults) {
            $footer      = substr($footersResults['PartName'], 1);
            $loadContent = $zipDocx->getContent($footer);
            $dom         = $this->repairVariables($variables, $loadContent, $templateSymbolStart, $templateSymbolEnd);
            $stringDoc   = $this->removeExtraTags($dom);
            $zipDocx->addContent($footer, $stringDoc);
        }

        $zipDocx->saveDocx($target);
    }

    /**
     * Removes extra tags
     *
     * @access private
     * @param array $variables
     * @param string $content
     * @return string
     */
    private function removeExtraTags($content)
    {
        $tagsToRemove = array('<w:proofErr w:type="spellStart"/>', '<w:proofErr w:type="spellEnd"/>');

        return str_replace($tagsToRemove, '', $content);
    }

    /**
     * Removes tags in string contents
     */
    private function removeTagsInContent($matches)
    {
        return strip_tags($matches[0]);
    }

    /**
     * Prepares a single PHPDocX variable for substitution
     *
     * @access private
     * @param array $variables
     * @param string $content
     * @param string $templateSymbolStart
     * @param string $templateSymbolEnd
     * @return string
     */
    private function repairVariables($variables, $content, $templateSymbolStart, $templateSymbolEnd)
    {
        if ($templateSymbolStart == $templateSymbolEnd && strlen($templateSymbolStart) == 1) {
            // old repair code, using the same symbol for start and end
            $documentSymbol = explode($templateSymbolStart, $content);
            foreach ($variables as $var => $value) {
                foreach ($documentSymbol as $documentSymbolValue) {
                    $tempSearch = trim(strip_tags($documentSymbolValue));
                    if ($tempSearch == $value) {
                        $pos = strpos($content, $documentSymbolValue);
                        if ($pos !== false) {
                            $content = substr_replace($content, $value, $pos, strlen($documentSymbolValue));
                        }
                    }
                    if (strpos($documentSymbolValue, 'xml:space="preserve"')) {
                        $preserve = true;
                    }
                }
                if (isset($preserve) && $preserve) {
                    $query  = '//w:t[text()[contains(., "' . $templateSymbolStart . $value . $templateSymbolStart . '")]]';
                    $docDOM = new DOMDocument();
                    if (PHP_VERSION_ID < 80000) {
                        $optionEntityLoader = libxml_disable_entity_loader(true);
                    }
                    $docDOM->loadXML($content);
                    if (PHP_VERSION_ID < 80000) {
                        libxml_disable_entity_loader($optionEntityLoader);
                    }
                    $docXPath = new DOMXPath($docDOM);
                    $docXPath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                    $affectedNodes = $docXPath->query($query);
                    foreach ($affectedNodes as $node) {
                        $space = $node->getAttribute('xml:space');
                        if (isset($space) && $space == 'preserve') {
                            //Do nothing 
                        } else {
                            $str       = $node->nodeValue;
                            $firstChar = $str[0];
                            if ($firstChar == ' ') {
                                $node->nodeValue = substr($str, 1);
                            }
                            $node->setAttribute('xml:space', 'preserve');
                        }
                    }
                    $content = $docDOM->saveXML($docDOM->documentElement);
                    //$content = html_entity_decode($content, ENT_NOQUOTES, 'UTF-8');
                }
            }

            return $content;
        } else {
            // new repair code, using distinct symbols for start and end
            $content = $this->repairVariablesDistinctSymbols($variables, $content, $templateSymbolStart, $templateSymbolEnd);

            // force using preserve settings
            $preserve = true;
            if (isset($preserve) && $preserve) {
                foreach ($variables as $var => $value) {
                    $query  = '//w:t[text()[contains(., "' . self::$_templateSymbolStart . $var . self::$_templateSymbolEnd . '")]]';
                    $docDOM = new DOMDocument();
                    if (PHP_VERSION_ID < 80000) {
                        $optionEntityLoader = libxml_disable_entity_loader(true);
                    }
                    $docDOM->loadXML($content);
                    if (PHP_VERSION_ID < 80000) {
                        libxml_disable_entity_loader($optionEntityLoader);
                    }
                    $docXPath = new DOMXPath($docDOM);
                    $docXPath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                    $affectedNodes = $docXPath->query($query);
                    foreach ($affectedNodes as $node) {
                        $space = $node->getAttribute('xml:space');
                        if (isset($space) && $space == 'preserve') {
                            //Do nothing 
                        } else {
                            $str       = $node->nodeValue;
                            $firstChar = $str[0];
                            if ($firstChar == ' ') {
                                $node->nodeValue = substr($str, 1);
                            }
                            $node->setAttribute('xml:space', 'preserve');
                        }
                    }
                    $content = $docDOM->saveXML($docDOM->documentElement);
                }
            }

            return $content;
        }
    }

    /**
     * Prepares a single PHPDocX variable for substitution using distinct symbols
     *
     * @access private
     * @param array $variables
     * @param string $content
     * @return string
     */
    private function repairVariablesDistinctSymbols($variables, $content, $templateSymbolStart, $templateSymbolEnd)
    {
        $content = preg_replace_callback('/' . self::$regExprVariableSymbols . '/msiU', array($this, 'removeTagsInContent'), $content);

        return $content;
    }
}