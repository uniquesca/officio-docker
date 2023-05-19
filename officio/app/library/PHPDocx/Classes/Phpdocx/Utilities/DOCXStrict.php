<?php

namespace Phpdocx\Utilities;

use DOMDocument;
use DOMXPath;

/**
 * Handle strict DOCX
 *
 * @category   Phpdocx
 * @package    utilities
 * @copyright  Copyright (c) Narcea Producciones Multimedia S.L.
 *             (http://www.2mdc.com)
 * @license    phpdocx LICENSE
 * @link       https://www.phpdocx.com
 */
class DOCXStrict
{
    const STRICT_VARIANT = 'http://purl.oclc.org/ooxml/officeDocument';
    const STRICT_VARIANT_DOCUMENT = 'http://purl.oclc.org/ooxml/wordprocessingml';
    const TRANSITIONAL_VARIANT = 'http://schemas.openxmlformats.org/officeDocument/2006';
    const TRANSITIONAL_VARIANT_DOCUMENT = 'http://schemas.openxmlformats.org/wordprocessingml/2006';

    /**
     * Check if the DOCX uses transitional or strict variant
     * @param string or DOCXStructure $source path to the docx
     * @return string transitional or strict
     */
    public function checkVariant($source)
    {
        $docxStructure = $this->parseDocx($source);
        $docxContents  = $docxStructure->getDocx('array');

        $documentContents = $docxContents['word/document.xml'];

        $documentDOM = new DOMDocument();
        if (PHP_VERSION_ID < 80000) {
            $optionEntityLoader = libxml_disable_entity_loader(true);
        }
        $documentDOM->loadXML($documentContents);
        if (PHP_VERSION_ID < 80000) {
            libxml_disable_entity_loader($optionEntityLoader);
        }

        $documentXpath = new DOMXPath($documentDOM);
        $documentXpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $xmlnsNodeDocument = $documentXpath->query('//w:document')->item(0);

        if ($xmlnsNodeDocument->hasAttribute('w:conformance') && $xmlnsNodeDocument->getAttribute('w:conformance') == 'strict') {
            return 'strict';
        } else {
            return 'transitional';
        }
    }

    /**
     * Generate a strict DOCX
     *
     * @param string or DOCXStructure $source path to the docx
     * @param string $target path to the DOCX output. If null don't generate it
     * @return DOCXStructure
     */
    public function generateStrictVariant($source, $target = null)
    {
        $docxStructure = $this->parseDocx($source);
        $docxContents  = $docxStructure->getDocx('array');

        foreach ($docxContents as $key => $value) {
            if (substr_compare($value, 'xml', -strlen('xml') == 0) || substr_compare($value, 'rels', -strlen('rels') == 0)) {
                $content = str_replace(self::TRANSITIONAL_VARIANT, self::STRICT_VARIANT, $value);
                $content = str_replace(self::TRANSITIONAL_VARIANT_DOCUMENT, self::STRICT_VARIANT_DOCUMENT, $value);

                $docxContents[$key] = $content;
            }
        }

        // add w:conformance from w:document
        $documentContents = $docxContents['word/document.xml'];
        $documentDOM      = new DOMDocument();
        if (PHP_VERSION_ID < 80000) {
            $optionEntityLoader = libxml_disable_entity_loader(true);
        }
        $documentDOM->loadXML($documentContents);
        if (PHP_VERSION_ID < 80000) {
            libxml_disable_entity_loader($optionEntityLoader);
        }

        $documentXpath = new DOMXPath($documentDOM);
        $documentXpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $xmlnsNodeDocument = $documentXpath->query('//w:document')->item(0);
        if (!$xmlnsNodeDocument->hasAttribute('w:conformance') || $xmlnsNodeDocument->getAttribute('w:conformance') != 'strict') {
            $xmlnsNodeDocument->setAttribute('w:conformance', 'strict');
            $docxContents['word/document.xml'] = $xmlnsNodeDocument->ownerDocument->saveXML($xmlnsNodeDocument);
        }

        $docxStructure->setDocx($docxContents);

        if ($target != null) {
            $docxStructure->saveDocx($target);
        }

        return $docxStructure;
    }

    /**
     * Generate a transitional DOCX
     *
     * @param string or DOCXStructure $source path to the docx
     * @param string $target path to the DOCX output. If null don't generate it
     * @return DOCXStructure
     */
    public function generateTransitionalVariant($source, $target = null)
    {
        $docxStructure = $this->parseDocx($source);
        $docxContents  = $docxStructure->getDocx('array');

        foreach ($docxContents as $key => $value) {
            if (substr_compare($value, 'xml', -strlen('xml') == 0) || substr_compare($value, 'rels', -strlen('rels') == 0)) {
                $content = str_replace(self::STRICT_VARIANT, self::TRANSITIONAL_VARIANT, $value);
                $content = str_replace(self::STRICT_VARIANT_DOCUMENT, self::TRANSITIONAL_VARIANT_DOCUMENT, $value);

                $docxContents[$key] = $content;
            }
        }

        // remove w:conformance from w:document
        $documentContents = $docxContents['word/document.xml'];
        $documentDOM      = new DOMDocument();
        if (PHP_VERSION_ID < 80000) {
            $optionEntityLoader = libxml_disable_entity_loader(true);
        }
        $documentDOM->loadXML($documentContents);
        if (PHP_VERSION_ID < 80000) {
            libxml_disable_entity_loader($optionEntityLoader);
        }

        $documentXpath = new DOMXPath($documentDOM);
        $documentXpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $xmlnsNodeDocument = $documentXpath->query('//w:document')->item(0);
        if ($xmlnsNodeDocument->hasAttribute('w:conformance')) {
            $xmlnsNodeDocument->removeAttribute('w:conformance');
            $docxContents['word/document.xml'] = $xmlnsNodeDocument->ownerDocument->saveXML($xmlnsNodeDocument);
        }

        $docxStructure->setDocx($docxContents);

        if ($target != null) {
            $docxStructure->saveDocx($target);
        }

        return $docxStructure;
    }

    /**
     * Parse the DOCX source
     *
     * @param string or DOCXStructure $source path to the docx
     * @return array
     */
    private function parseDocx($source) {
        if ($source instanceof DOCXStructure) {
            return $source;
        } else {
            $docxStructure = new DOCXStructure();
            $docxStructure->parseDocx($source);

            return $docxStructure;
        }
    }
}