<?php
namespace Phpdocx\Utilities;

use DomDocument;
use DOMXPath;
use Phpdocx\Create\CreateDocx;
use Phpdocx\Create\CreateDocxFromTemplate;
use Phpdocx\Elements\WordFragment;
use Phpdocx\Logger\PhpdocxLogger;

/**
 * Bulk processing
 *
 * @category   Phpdocx
 * @package    processing
 * @copyright  Copyright (c) Narcea Producciones Multimedia S.L.
 *             (http://www.2mdc.com)
 * @license    phpdocx LICENSE
 * @link       https://www.phpdocx.com
 */
class BulkProcessing
{
    /**
     * @access protected
     * @var array
     */
    protected $cachedContents;

    /**
     * @access protected
     * @var array
     */
    protected $cachedContentsTemplate;

    /**
     * @access protected
     * @var DOCXStructure
     */
    protected $template;

    /**
     * @access protected
     * @var string
     */
    protected $templateSymbolStart;

    /**
     * @access protected
     * @var string
     */
    protected $templateSymbolEnd;

    /**
     * Constructor
     *
     * @access public
     * @param DOCXStructure $source
     */
    public function __construct($template, $templateSymbolStart = '$', $templateSymbolEnd = '$')
    {
        if ($template instanceof DOCXStructure) {
            // in memory DOCX
            $this->template = $template;
        } else {
            // file DOCX
            $this->template = new DOCXStructure();
            $this->template->parseDocx($template);
        }

        // keep template symbol
        $this->templateSymbolStart = $templateSymbolStart;
        $this->templateSymbolEnd   = $templateSymbolEnd;

        // clean all placeholders to remove extra unwanted tags
        $docx = new CreateDocxFromTemplate($this->template);
        $docx->setTemplateSymbol($this->templateSymbolStart, $this->templateSymbolEnd);
        CreateDocx::$returnDocxStructure = true;
        $variables                       = $docx->getTemplateVariables();
        $docx->processTemplate($variables);
        $this->template                  = $docx->createDocx();
        CreateDocx::$returnDocxStructure = false;

        // fill the cached contents from the template
        // cache document
        $this->cachedContentsTemplate['document'] = $this->generateDomDocument($this->template->getContent('word/document.xml'));

        // init rels contents
        $this->cachedContentsTemplate['Content_Types'] = $this->generateDomDocument($this->template->getContent('[Content_Types].xml'));

        // init rels contents
        $this->cachedContentsTemplate['rels'] = array(
            'document' => array(),
            'headers'  => array(),
            'footers'  => array(),
            'images'   => array(),
        );

        // cache document rels
        if ($this->template->getContent('word/_rels/document.xml.rels')) {
            $this->cachedContentsTemplate['rels']['document']['document.xml'] = array(
                'content' => $this->generateDomDocument($this->template->getContent('word/_rels/document.xml.rels')),
                'path'    => 'word/_rels/document.xml.rels',
            );
        }

        // cache headers
        $this->cachedContentsTemplate['headers'] = array();
        $headers                                 = $this->template->getContentByType('headers');
        foreach ($headers as $header) {
            // content
            $this->cachedContentsTemplate['headers'][$header['name']] = $this->generateDomDocument($header['content']);
            // rels
            if ($this->template->getContent(str_replace('word/', 'word/_rels/', $header['name']) . '.rels')) {
                $this->cachedContentsTemplate['rels']['headers'][$header['name']] = array(
                    'content' => $this->generateDomDocument($this->template->getContent(str_replace('word/', 'word/_rels/', $header['name']) . '.rels')),
                    'path'    => str_replace('word/', 'word/_rels/', $header['name'] . '.rels'),
                );
            }
        }
        // cache footers
        $this->cachedContentsTemplate['footers'] = array();
        $footers                                 = $this->template->getContentByType('footers');
        foreach ($footers as $footer) {
            // content
            $this->cachedContentsTemplate['footers'][$footer['name']] = $this->generateDomDocument($footer['content']);
            // rels
            if ($this->template->getContent(str_replace('word/', 'word/_rels/', $footer['name']) . '.rels')) {
                $this->cachedContentsTemplate['rels']['footers'][$footer['name']] = array(
                    'content' => $this->generateDomDocument($this->template->getContent(str_replace('word/', 'word/_rels/', $footer['name']) . '.rels')),
                    'path'    => str_replace('word/', 'word/_rels/', $footer['name'] . '.rels'),
                );
            }
        }

        // WordFragments
        $this->cachedContentsTemplate['images'] = array();

        // WordFragments
        $this->cachedContentsTemplate['wordfragments'] = array();
    }

    /**
     * Returns the generated documents
     *
     * @return array Generated documents
     */
    public function getDocuments()
    {
        // generate the documents from the cached contents
        $documentOutputs = array();

        $i = 0;
        foreach ($this->cachedContents as $cacheContent) {
            $templateCloned      = clone $this->template;
            $documentOutputs[$i] = $templateCloned;

            // add the Content_Types
            $documentOutputs[$i]->addContent('[Content_Types].xml', $cacheContent['Content_Types']->saveXML());

            // add the main content
            $documentOutputs[$i]->addContent('word/document.xml', $cacheContent['document']->saveXML());

            // add the headers
            foreach ($cacheContent['headers'] as $cacheContentHeaderName => $cacheContentHeaderValue) {
                $documentOutputs[$i]->addContent($cacheContentHeaderName, $cacheContentHeaderValue->saveXML());
            }

            // add the footers
            foreach ($cacheContent['footers'] as $cacheContentFooterName => $cacheContentFooterValue) {
                $documentOutputs[$i]->addContent($cacheContentFooterName, $cacheContentFooterValue->saveXML());
            }

            // add the rels
            if (isset($cacheContent['rels']['document']['document.xml'])) {
                $documentOutputs[$i]->addContent('word/_rels/document.xml.rels', $cacheContent['rels']['document']['document.xml']['content']->saveXML());
            }
            if (count($cacheContent['rels']['headers']) > 0) {
                foreach ($cacheContent['rels']['headers'] as $relsHeader) {
                    if ($relsHeader['content']) {
                        $documentOutputs[$i]->addContent($relsHeader['path'], $relsHeader['content']->saveXML());
                    }
                }
            }
            if (count($cacheContent['rels']['footers']) > 0) {
                foreach ($cacheContent['rels']['footers'] as $relsFooter) {
                    if ($relsFooter['content']) {
                        $documentOutputs[$i]->addContent($relsFooter['path'], $relsFooter['content']->saveXML());
                    }
                }
            }

            // add the images
            if (count($cacheContent['images']) > 0) {
                foreach ($cacheContent['images'] as $images) {
                    $documentOutputs[$i]->addFile($images['path'], $images['content']);
                }
            }

            // add the WordFragments
            if (count($cacheContent['wordfragments']) > 0) {
                foreach ($cacheContent['wordfragments'] as $wordfragmentKey => $wordfragmentValue) {
                    $docxTemplate = new CreateDocxFromTemplate($documentOutputs[$i]);
                    $docxTemplate->setTemplateSymbol($this->templateSymbolStart, $this->templateSymbolEnd);
                    $docxTemplate->replaceVariableByWordFragment(array($wordfragmentKey => $wordfragmentValue['wordfragment']), array('type' => $wordfragmentValue['options']['type'], 'target' => 'document'));

                    CreateDocx::$returnDocxStructure = true;
                    $documentOutputs[$i]             = $docxTemplate->createDocx();
                    CreateDocx::$returnDocxStructure = false;
                }
            }

            $i++;
        }

        return $documentOutputs;
    }

    /**
     * Replaces image placeholders
     *
     * @access public
     * @param array $variables Allows setting an array of array to generate more than one document output
     * @param array $options
     *        'dpi' (int): dots per inch. This parameter is only taken into account if width or height are set to auto
     *        'firstMatch' (boolean) if true it only replaces the first variable match. Default is set to false
     *        'height' (mixed): the value in cm (float) or 'auto' (use image size), 0 to not change the previous size
     *        'mime' (string) forces a mime (image/jpg, image/jpeg, image/png, image/gif)
     *        'streamMode' (bool) if true, uses src path as stream. PHP 5.4 or greater needed to autodetect the mime type; otherwise set it using mime option. Default is false
     *        'target': all (default), document, header, footer (all as default)
     *        'width' (mixed): the value in cm (float) or 'auto' (use image size), 0 to not change the previous size
     * @return array
     */
    public function replaceImage($variables, $options = array())
    {
        if (!isset($options['target'])) {
            $options['target'] = array('document', 'header', 'footer');
        }

        // generate as many document as needed based on the variables count
        $this->generateDocumentOutputs($variables);

        for ($i = 0; $i < count($variables); $i++) {
            // iterate chosen targets
            foreach ($options['target'] as $target) {
                if ($target == 'document') {
                    // keep the target and rels to add the image to the correct rels file
                    $contentImage = $this->replaceImageContents($variables[$i], $this->cachedContents[$i]['document'], $this->cachedContents[$i]['rels']['document']['document.xml']['content'], $this->cachedContents[$i]['Content_Types'], $options);

                    $this->cachedContents[$i]['document'] = $contentImage['content'];
                    foreach ($contentImage['imagesPaths'] as $imagePath) {
                        $this->cachedContents[$i]['images'][] = array(
                            'content' => $imagePath['from'],
                            'path'    => $imagePath['to'],
                        );
                    }
                } else if ($target == 'header') {
                    foreach ($this->cachedContents[$i]['headers'] as $headerName => $headerContent) {
                        // keep the target and rels to add the image to the correct rels file
                        $contentImage = $this->replaceImageContents($variables[$i], $headerContent, $this->cachedContents[$i]['rels']['headers'][$headerName]['content'], $this->cachedContents[$i]['Content_Types'], $options);

                        $this->cachedContents[$i]['headers'][$headerName] = $contentImage['content'];
                        foreach ($contentImage['imagesPaths'] as $imagePath) {
                            $this->cachedContents[$i]['images'][] = array(
                                'content' => $imagePath['from'],
                                'path'    => $imagePath['to'],
                            );
                        }
                    }
                } else if ($target == 'footer') {
                    foreach ($this->cachedContents[$i]['footers'] as $footerName => $footerContent) {
                        // keep the target and rels to add the image to the correct rels file
                        $contentImage = $this->replaceImageContents($variables[$i], $footerContent, $this->cachedContents[$i]['rels']['footers'][$footerName]['content'], $this->cachedContents[$i]['Content_Types'], $options);

                        $this->cachedContents[$i]['footers'][$footerName] = $contentImage['content'];
                        foreach ($contentImage['imagesPaths'] as $imagePath) {
                            $this->cachedContents[$i]['images'][] = array(
                                'content' => $imagePath['from'],
                                'path'    => $imagePath['to'],
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * Replaces list placeholders
     *
     * @access public
     * @param array $variables Allows setting an array of array to generate more than one document output
     * @param array $options
     *        'firstMatch' (boolean) if true it only replaces the first variable match. Default is set to false
     *        'target': all (default), document, header, footer (all as default)
     * @return array
     */
    public function replaceList($variables, $options = array())
    {
        if (!isset($options['target'])) {
            $options['target'] = array('document', 'header', 'footer');
        }
        if (!isset($options['parseLineBreaks'])) {
            $options['parseLineBreaks'] = false;
        }
        if (!isset($options['type'])) {
            $options['type'] = 'inline';
        }

        // generate as many document as needed based on the variables count
        $this->generateDocumentOutputs($variables);

        for ($i = 0; $i < count($variables); $i++) {
            // iterate chosen targets
            foreach ($options['target'] as $target) {
                $options['index'] = $i;
                if ($target == 'document') {
                    $stringDoc = $this->replaceListContents($variables[$i], $this->cachedContents[$i]['document'], $options);

                    $this->cachedContents[$i]['document'] = $stringDoc;
                } else if ($target == 'header') {
                    foreach ($this->cachedContents[$i]['headers'] as $headerName => $headerContent) {
                        $stringDoc = $this->replaceListContents($variables[$i], $headerContent, $options);

                        $this->cachedContents[$i]['headers'][$headerName] = $stringDoc;
                    }
                } else if ($target == 'footer') {
                    foreach ($this->cachedContents[$i]['footers'] as $footerName => $footerContent) {
                        $stringDoc = $this->replaceListContents($variables[$i], $footerContent, $options);

                        $this->cachedContents[$i]['footers'][$footerName] = $stringDoc;
                    }
                }
            }
        }
    }

    /**
     * Replaces table placeholders
     *
     * @access public
     * @param array $variables Allows setting an array of array to generate more than one document output
     * @param array $options
     *        'firstMatch' (boolean) if true it only replaces the first variable match. Default is set to false
     *        'target': all (default), document, header, footer (all as default)
     * @return array
     */
    public function replaceTable($variables, $options = array())
    {
        if (!isset($options['parseLineBreaks'])) {
            $options['parseLineBreaks'] = false;
        }
        if (!isset($options['target'])) {
            $options['target'] = array('document', 'header', 'footer');
        }
        if (!isset($options['type'])) {
            $options['type'] = 'block';
        }

        // generate as many document as needed based on the variables count
        $this->generateDocumentOutputs($variables);

        for ($i = 0; $i < count($variables); $i++) {
            // iterate chosen targets
            foreach ($options['target'] as $target) {
                $options['index'] = $i;
                if ($target == 'document') {
                    $stringDoc = $this->replaceTableContents($variables[$i], $this->cachedContents[$i]['document'], $options);

                    $this->cachedContents[$i]['document'] = $stringDoc;
                } else if ($target == 'header') {
                    foreach ($this->cachedContents[$i]['headers'] as $headerName => $headerContent) {
                        $stringDoc = $this->replaceTableContents($variables[$i], $headerContent, $options);

                        $this->cachedContents[$i]['headers'][$headerName] = $stringDoc;
                    }
                } else if ($target == 'footer') {
                    foreach ($this->cachedContents[$i]['footers'] as $footerName => $footerContent) {
                        $stringDoc = $this->replaceTableContents($variables[$i], $footerContent, $options);

                        $this->cachedContents[$i]['footers'][$footerName] = $stringDoc;
                    }
                }
            }
        }
    }

    /**
     * Replaces text placeholders
     *
     * @access public
     * @param array $variables
     *        variable names: text values. Allows setting an array of array to generate more than one document output
     * @param array $options
     *        'firstMatch' (boolean) if true it only replaces the first variable match. Default is set to false
     *        'parseLineBreaks' (boolean) if true (default is false) parses the line breaks to include them in the Word document
     *        'target' (array): document, header, footer (all as default)
     * @return array
     */
    public function replaceText($variables, $options = array())
    {
        if (!isset($options['parseLineBreaks'])) {
            $options['parseLineBreaks'] = false;
        }
        if (!isset($options['target'])) {
            $options['target'] = array('document', 'header', 'footer');
        }

        // generate as many document as needed based on the variables count
        $this->generateDocumentOutputs($variables);

        for ($i = 0; $i < count($variables); $i++) {
            // iterate chosen targets
            foreach ($options['target'] as $target) {
                if ($target == 'document') {
                    // replace the values
                    $stringDoc = $this->replaceTextContents($variables[$i], $this->cachedContents[$i]['document'], $options);

                    $this->cachedContents[$i]['document'] = $stringDoc;
                } else if ($target == 'header') {
                    foreach ($this->cachedContents[$i]['headers'] as $headerName => $headerContent) {
                        // replace the values
                        $stringDoc = $this->replaceTextContents($variables[$i], $headerContent, $options);

                        $this->cachedContents[$i]['headers'][$headerName] = $stringDoc;
                    }
                } else if ($target == 'footer') {
                    foreach ($this->cachedContents[$i]['footers'] as $footerName => $footerContent) {
                        // replace the values
                        $stringDoc = $this->replaceTextContents($variables[$i], $footerContent, $options);

                        $this->cachedContents[$i]['footers'][$footerName] = $stringDoc;
                    }
                }
            }
        }
    }

    /**
     * Replaces placeholders by WordFragments
     *
     * @access public
     * @param array $variables
     *        variable names: WordFragment values . Allows setting an array of array to generate more than one document output
     * @param array $options
     *        'firstMatch' (boolean) if true it only replaces the first variable match. Default is set to false
     *        'target' (array): document (default), header, footer
     *        'type': inline (only replaces the variable) or block (removes the variable and its containing paragraph)
     * @return array
     */
    public function replaceWordFragment($variables, $options = array())
    {
        if (!isset($options['target'])) {
            $options['target'] = 'document';
        }
        if (!isset($options['type'])) {
            $options['type'] = 'block';
        }

        // generate as many document as needed based on the variables count
        $this->generateDocumentOutputs($variables);

        for ($i = 0; $i < count($variables); $i++) {
            foreach ($variables[$i] as $variableKey => $variableValue) {
                $this->cachedContents[$i]['wordfragments'][$variableKey] = array('wordfragment' => $variableValue, 'options' => $options);
            }
        }
    }

    /**
     * Clones an array that contains objects
     *
     * @access protected
     * @param array $clonedContent
     */
    protected function cloneArrayContent($contents) {
        $clonedContent = array();

        foreach ($contents as $contentKey => $contentValue) {
            if (is_array($contentValue)) {
                $clonedContent[$contentKey] = $this->cloneArrayContent($contentValue);
            } elseif (is_object($contentValue)) {
                $clonedContent[$contentKey] = clone $contentValue;
            } else {
                $clonedContent[$contentKey] = $contentValue;
            }
        }

        return $clonedContent;
    }

    /**
     * Generates documentOutputs from the current template
     *
     * @access protected
     * @param array $variables
     */
    protected function generateDocumentOutputs(&$variables)
    {
        // generate as many documents as needed.
        // Add a new document only if the current position doesn't exist
        for ($i = 0; $i < count($variables); $i++) {
            if (!isset($this->cachedContents[$i])) {
                $this->cachedContents[] = $this->cloneArrayContent($this->cachedContentsTemplate);
            }
        }
    }

    /**
     * Generates a DOM document from a string
     *
     * @access protected
     * @param string $content
     */
    protected function generateDomDocument($content)
    {
        $dom = new DomDocument();
        if (PHP_VERSION_ID < 80000) {
            $optionEntityLoader = libxml_disable_entity_loader(true);
        }
        $dom->loadXML($content);
        if (PHP_VERSION_ID < 80000) {
            libxml_disable_entity_loader($optionEntityLoader);
        }

        return $dom;
    }

    /**
     * Replaces list contents
     *
     * @param array $variables
     * @param DOMDocument $dom
     * @param DOMDocument $domRels
     * @param array $options
     * @return string
     */
    protected function replaceImageContents($variables, $dom, &$domRels, &$domContentTypes, $options)
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $imagesPaths = array();

        foreach ($variables as $variable => $value) {
            if (!file_exists($value) && (!isset($options['streamMode']) || !$options['streamMode'])) {
                PhpdocxLogger::logger('The' . $value . ' path seems not to be correct. Unable to get the image file.', 'fatal');
            }

            $cx = 0;
            $cy = 0;

            // file image
            if (!isset($options['streamMode']) || !$options['streamMode']) {
                if (file_exists($value) == 'true') {
                    // get the name and extension of the replacement image
                    $imageNameArray = explode('/', $value);
                    if (count($imageNameArray) > 1) {
                        $imageName = array_pop($imageNameArray);
                    } else {
                        $imageName = $value;
                    }
                    $imageExtensionArray = explode('.', $value);
                    $extension           = strtolower(array_pop($imageExtensionArray));
                } else {
                    PhpdocxLogger::logger('Image ' . $value . 'does not exist.', 'fatal');
                }
            }

            // stream image
            if (isset($options['streamMode']) && $options['streamMode'] == true) {
                if (function_exists('getimagesizefromstring')) {
                    $imageStream = file_get_contents($value);
                    $attrImage   = getimagesizefromstring($imageStream);
                    $mimeType    = $attrImage['mime'];

                    switch ($mimeType) {
                        case 'image/gif':
                            $extension = 'gif';
                            break;
                        case 'image/jpg':
                            $extension = 'jpg';
                            break;
                        case 'image/jpeg':
                            $extension = 'jpeg';
                            break;
                        case 'image/png':
                            $extension = 'png';
                            break;
                        default:
                            break;
                    }
                } else {
                    if (!isset($options['mime'])) {
                        PhpdocxLogger::logger('getimagesizefromstring function is not available. Set the mime option or use the file mode.', 'fatal');
                    }
                }
            }

            if (isset($options['mime']) && !empty($options['mime'])) {
                $mimeType = $options['mime'];
            }

            $wordScaleFactor = 360000;
            if (isset($options['dpi'])) {
                $dpiX = $options['dpi'];
                $dpiY = $options['dpi'];
            } else {
                if ((isset($options['width']) && $options['width'] == 'auto') ||
                    (isset($options['height']) && $options['height'] == 'auto')) {
                    if ($extension == 'jpg' || $extension == 'jpeg') {
                        list($dpiX, $dpiY) = $this->getDpiJpg($value);
                    } else if ($extension == 'png') {
                        list($dpiX, $dpiY) = $this->getDpiPng($value);
                    } else {
                        $dpiX = 96;
                        $dpiY = 96;
                    }
                }
            }

            // check if a width and height have been set
            $width  = 0;
            $height = 0;
            if (isset($options['width']) && $options['width'] != 'auto') {
                $cx = (int) round($options['width'] * $wordScaleFactor);
            }
            if (isset($options['height']) && $options['height'] != 'auto') {
                $cy = (int) round($options['height'] * $wordScaleFactor);
            }
            //Proceed to compute the sizes if the width or height are set to auto
            if ((isset($options['width']) && $options['width'] == 'auto') ||
                (isset($options['height']) && $options['height'] == 'auto')) {
                if (!isset($options['streamMode']) || !$options['streamMode']) {
                    // file mode
                    $realSize = getimagesize($value);
                } else {
                    // stream mode
                    if (function_exists('getimagesizefromstring')) {
                        $imageStream = file_get_contents($value);
                        $realSize    = getimagesizefromstring($imageStream);
                    } else {
                        if (!isset($data['width']) || !isset($data['height'])) {
                            PhpdocxLogger::logger('getimagesizefromstring function is not available. Set width and height options or use the file mode.', 'fatal');

                            $realSize = array($options['width'], $options['height']);
                        }
                    }
                }
            }
            if (isset($options['width']) && $options['width'] == 'auto') {
                $cx = (int) round($realSize[0] * 2.54 / $dpiX * $wordScaleFactor);
            }
            if (isset($options['height']) && $options['height'] == 'auto') {
                $cy = (int) round($realSize[1] * 2.54 / $dpiY * $wordScaleFactor);
            }

            $domImages = $dom->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing', 'docPr');

            $imageCounter = 0;
            // create a new Id
            $id  = uniqid(rand(99,9999999), true);
            $ind = 'rId' . $id;
            for ($i = 0; $i < $domImages->length; $i++) {
                if ($domImages->item($i)->getAttribute('descr') == $this->templateSymbolStart . $variable . $this->templateSymbolEnd && $imageCounter == 0) {
                    // generate new relationship
                    $relString = '<Relationship Id="' . $ind . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/img' . $id . '.' . $extension . '" />';
                    // add the Relationship if the ID doesn't exist
                    if (@!strstr($relsNode, $ind)) {
                        $relsNode = $domRels->createDocumentFragment();
                        $relsNode->appendXML($relString);
                        $domRels->documentElement->appendChild($relsNode);
                    }
                    // generate content type if it does not exist yet
                    $strContent = $domContentTypes->saveXML();
                    if (
                        strpos($strContent, 'Extension="' . strtolower($extension)) === false
                    ) {
                        $strContentTypes = '<Default Extension="' . $extension . '" ContentType="image/' . $extension . '"> </Default>';
                        $tempNode        = $domContentTypes->createDocumentFragment();
                        $tempNode->appendXML($strContentTypes);
                        $domContentTypes->documentElement->appendChild($tempNode);
                    }
                    // modify the image data to modify the r:embed attribute
                    $domImages->item($i)->parentNode
                        ->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'blip')
                        ->item(0)->setAttribute('r:embed', $ind);
                    if ($cx != 0) {
                        $domImages->item($i)->parentNode
                            ->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing', 'extent')
                            ->item(0)->setAttribute('cx', $cx);
                        $xfrmNode = $domImages->item($i)->parentNode
                            ->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'xfrm')->item(0);
                        $xfrmNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'ext')
                            ->item(0)->setAttribute('cx', $cx);
                    }
                    if ($cy != 0) {
                        $domImages->item($i)->parentNode
                            ->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing', 'extent')
                            ->item(0)->setAttribute('cy', $cy);
                        $xfrmNode = $domImages->item($i)->parentNode
                            ->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'xfrm')->item(0);
                        $xfrmNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'ext')
                            ->item(0)->setAttribute('cy', $cy);
                    }
                    if (isset($options['firstMatch']) && $options['firstMatch']) {
                        $imageCounter++;
                        $domImages->item($i)->setAttribute('descr', '');
                    }

                    $imagesPaths[] = array(
                        'from' => $value,
                        'to'   => 'word/media/img' . $id . '.' . $extension,
                    );
                }
            }
        }

        return array('content' => $dom, 'imagesPaths' => $imagesPaths);
    }

    /**
     * Replaces list contents
     *
     * @param array $variables
     * @param DOMDocument $dom
     * @param array $options
     * @return string
     */
    protected function replaceListContents($variables, $dom, $options)
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        if (isset($options['firstMatch'])) {
            $firstMatch = $options['firstMatch'];
        } else {
            $firstMatch = false;
        }

        // iterate the array to get the list to be replaced
        foreach ($variables as $valueList) {
            $keyList = array_keys($valueList);
            $search  = $this->templateSymbolStart . $keyList[0] . $this->templateSymbolEnd;
            $query   = '//w:p[w:r/w:t[text()[contains(., "' . $search . '")]]]';
            if ($firstMatch) {
                $query = '(' . $query . ')[1]';
            }

            // if the content has WordFragments, replace each WordFragment by a plain placeholder. This holder
            // is replaced by the WordFragment value using the replaceVariableByWordFragment method
            $wordFragmentsValues = array();
            foreach ($valueList as &$varsRow) {
                foreach ($varsRow as $varKeyRow => $varValueRow) {
                    if ($varValueRow instanceof WordFragment) {
                        $uniqueId                       = uniqid(mt_rand(999, 9999));
                        $uniqueKey                      = $this->templateSymbolStart . $uniqueId . $this->templateSymbolEnd;
                        $wordFragmentsValues[$uniqueId] = $varsRow[$varKeyRow];
                        $varsRow[$varKeyRow]            = $uniqueKey;
                    }
                }
            }

            foreach ($wordFragmentsValues as $wordFragmentsValueKey => $wordFragmentsValues) {
                $this->cachedContents[$options['index']]['wordfragments'][$wordFragmentsValueKey] = array('wordfragment' => $wordFragmentsValues, 'options' => $options);
            }

            $foundNodes = $xpath->query($query);
            foreach ($foundNodes as $node) {
                $domNode    = dom_import_simplexml($node);
                $valuesList = array_values($valueList);
                foreach ($valuesList[0] as $key => $value) {
                    $newNode   = $domNode->cloneNode(true);
                    $textNodes = $newNode->getElementsBytagName('t');
                    foreach ($textNodes as $text) {
                        $sxText  = simplexml_import_dom($text);
                        $strNode = (string) $sxText;
                        if (isset($options['parseLineBreaks']) && $options['parseLineBreaks']) {
                            //parse $val for \n\r, \r\n, \n or \r and carriage returns
                            $value = str_replace(array('\n\r', '\r\n', '\n', '\r', "\n\r", "\r\n", "\n", "\r"), '__LINEBREAK__', $value);
                        }
                        $strNodeReplaced = str_replace($search, $value, $strNode);
                        $sxText[0]       = $strNodeReplaced;
                    }
                    $domNode->parentNode->insertBefore($newNode, $domNode);
                }
                $domNode->parentNode->removeChild($domNode);
            }
        }

        // replace line breaks
        if ($options['parseLineBreaks']) {
            $stringDoc = str_replace('__LINEBREAK__', '</w:t><w:br/><w:t>', $dom->saveXML());
            $dom       = $this->generateDomDocument($stringDoc);
        }

        return $dom;
    }

    /**
     * Replaces table contents
     *
     * @param array $variables
     * @param DOMDocument $dom
     * @param array $options
     * @return string
     */
    protected function replaceTableContents($variables, $dom, $options)
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        if (isset($options['firstMatch'])) {
            $firstMatch = $options['firstMatch'];
        } else {
            $firstMatch = false;
        }

        // iterate the array to get the tables to be replaced
        for ($iTable = 0; $iTable < count($variables); $iTable++) {
            $varKeys = array_keys($variables[$iTable][0]);
            $search  = array();
            for ($j = 0; $j < count($variables[$iTable][0]); $j++) {
                $search[$j] = $this->templateSymbolStart . $varKeys[$j] . $this->templateSymbolEnd;
            }
            $queryArray = array();
            for ($j = 0; $j < count($search); $j++) {
                $queryArray[$j] = '//w:tr[w:tc/w:p/w:r/w:t[text()[contains(., "' . $search[$j] . '")]]]';
            }
            $query         = join(' | ', $queryArray);
            $foundNodes    = $xpath->query($query);
            $tableCounter  = 0;
            $referenceNode = '';
            $parentNode    = '';

            // if the content has WordFragments, replace each WordFragment by a plain placeholder. This holder
            // is replaced by the WordFragment value using the replaceVariableByWordFragment method
            $wordFragmentsValues = array();
            foreach ($variables[$iTable] as &$varsRow) {
                foreach ($varsRow as $varKeyRow => $varValueRow) {
                    if ($varValueRow instanceof WordFragment) {
                        $uniqueId                       = uniqid(mt_rand(999, 9999));
                        $uniqueKey                      = $this->templateSymbolStart . $uniqueId . $this->templateSymbolEnd;
                        $wordFragmentsValues[$uniqueId] = $varsRow[$varKeyRow];
                        $varsRow[$varKeyRow]            = $uniqueKey;
                    }
                }
            }

            foreach ($wordFragmentsValues as $wordFragmentsValueKey => $wordFragmentsValues) {
                $this->cachedContents[$options['index']]['wordfragments'][$wordFragmentsValueKey] = array('wordfragment' => $wordFragmentsValues, 'options' => $options);
            }

            foreach ($variables[$iTable] as $key => $rowValue) {
                $tableCounter = 0;
                foreach ($foundNodes as $node) {
                    $domNode = dom_import_simplexml($node);
                    if (!is_object($referenceNode) || !$domNode->parentNode->isSameNode($parentNode)) {
                        $referenceNode = $domNode;
                        $parentNode    = $domNode->parentNode;
                        $tableCounter++;
                    }
                    if (!$firstMatch || ($firstMatch && $tableCounter < 2)) {
                        $newNode   = $domNode->cloneNode(true);
                        $textNodes = $newNode->getElementsBytagName('t');
                        foreach ($textNodes as $text) {
                            for ($k = 0; $k < count($search); $k++) {
                                $sxText  = simplexml_import_dom($text);
                                $strNode = (string) $sxText;
                                if (!empty($rowValue[$varKeys[$k]]) ||
                                    $rowValue[$varKeys[$k]] === 0 ||
                                    $rowValue[$varKeys[$k]] === "0") {
                                    if (isset($options['parseLineBreaks']) && $options['parseLineBreaks']) {
                                        //parse $val for \n\r, \r\n, \n or \r and carriage returns
                                        $rowValue[$varKeys[$k]] = str_replace(array('\n\r', '\r\n', '\n', '\r', "\n\r", "\r\n", "\n", "\r"), '__LINEBREAK__', $rowValue[$varKeys[$k]]);
                                    }
                                    $strNode = str_replace($search[$k], $rowValue[$varKeys[$k]], $strNode);
                                } else {
                                    $strNode = str_replace($search[$k], '', $strNode);
                                }
                                $sxText[0] = $strNode;
                            }
                        }
                        $parentNode->insertBefore($newNode, $referenceNode);
                    }
                }
            }

            // remove the original nodes
            $tableCounter2 = 0;
            foreach ($foundNodes as $node) {
                $domNode = dom_import_simplexml($node);
                if ($firstMatch && !$domNode->parentNode->isSameNode($parentNode)) {
                    $parentNode = $domNode->parentNode;
                    $tableCounter2++;
                }
                if ($tableCounter2 < 2) {
                    $domNode->parentNode->removeChild($domNode);
                }
            }
        }

        // replace line breaks
        if ($options['parseLineBreaks']) {
            $stringDoc = str_replace('__LINEBREAK__', '</w:t><w:br/><w:t>', $dom->saveXML());
            $dom       = $this->generateDomDocument($stringDoc);
        }

        return $dom;
    }

    /**
     * Replaces text contents
     *
     * @param array $variables
     * @param string $dom
     * @param array $options
     * @return string
     */
    protected function replaceTextContents($variables, $dom, $options)
    {
        $dom = $this->variable2Text($variables, $dom, $options);

        // replace line breaks
        if ($options['parseLineBreaks']) {
            $stringDoc = str_replace('__LINEBREAK__', '</w:t><w:br /><w:t xml:space="preserve">', $dom->saveXML());
            $dom       = $this->generateDomDocument($stringDoc);
        }

        return $dom;
    }

    /**
     * Replaces an array of variables by their values
     *
     * @access protected
     * @param array $variables
     *  keys: variable names
     *  values: text we want to insert
     * @param DOMDocument $dom
     * @param array $options
     * @return SimpleXML Object
     */
    protected function variable2Text($variables, $dom, $options)
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        if (isset($options['firstMatch'])) {
            $firstMatch = $options['firstMatch'];
        } else {
            $firstMatch = false;
        }
        foreach ($variables as $variable => $value) {
            $search = $this->templateSymbolStart . $variable . $this->templateSymbolEnd;
            $query  = '//w:t[text()[contains(., "' . $search . '")]]';
            if ($firstMatch) {
                $query = '(' . $query . ')[1]';
            }
            $foundNodes = $xpath->query($query);
            foreach ($foundNodes as $node) {
                $strNode = $node->ownerDocument->saveXML($node);
                if ($options['parseLineBreaks']) {
                    //  replace line breaks
                    $value = str_replace(array('\n\r', '\r\n', '\n', '\r', "\n\r", "\r\n", "\n", "\r"), '__LINEBREAK__', $value);
                }
                $strNode = str_replace($search, $value, $strNode);

                $newNode = $dom->createDocumentFragment();
                @$newNode->appendXML($strNode);
                $dom->importNode($newNode, true);
                $node->parentNode->replaceChild($newNode, $node);
            }
        }

        return $dom;
    }

    /**
     * Gets jpg image dpi
     *
     * @access private
     * @param string $filename
     * @return array
     */
    private function getDpiJpg($filename)
    {
        $a      = fopen($filename, 'r');
        $string = fread($a, 20);
        fclose($a);
        $type = hexdec(bin2hex(substr($string, 13, 1)));
        $data = bin2hex(substr($string, 14, 4));
        if ($type == 1) {
            $x = substr($data, 0, 4);
            $y = substr($data, 4, 4);
            return array(hexdec($x), hexdec($y));
        } else if ($type == 2) {
            $x = floor(hexdec(substr($data, 0, 4)) / 2.54);
            $y = floor(hexdec(substr($data, 4, 4)) / 2.54);
            return array($x, $y);
        } else {
            return array(96, 96);
        }
    }

    /**
     * Gets png image dpi
     *
     * @access private
     * @param string $filename
     * @return array
     */
    private function getDpiPng($filename)
    {
        $pngScaleFactor = 29.5;
        $a              = fopen($filename, 'r');
        $string         = fread($a, 1000);
        $aux            = strpos($string, 'pHYs');
        if ($aux > 0) {
            $type = hexdec(bin2hex(substr($string, $aux + strlen('pHYs') + 16, 1)));
        }
        if ($aux > 0 && $type = 1) {
            $data = bin2hex(substr($string, $aux + strlen('pHYs'), 16));
            fclose($a);
            $x = substr($data, 0, 8);
            $y = substr($data, 8, 8);
            return array(round(hexdec($x) / $pngScaleFactor), round(hexdec($y) / $pngScaleFactor));
        } else {
            return array(96, 96);
        }
    }

}
