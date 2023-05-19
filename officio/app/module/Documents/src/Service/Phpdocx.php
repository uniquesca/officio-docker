<?php

namespace Documents\Service;
use Documents\PhpDocxImage;
use Documents\PhpDocxTable;
use Exception;
use Officio\Common\Service\BaseService;
use Phpdocx\AutoLoader;
use Phpdocx\Create\CreateDocx;
use Phpdocx\Create\CreateDocxFromTemplate;
use Phpdocx\Elements\WordFragment;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Phpdocx extends BaseService
{
    /** @var CreateDocx */
    protected $_docx = null;

    public function init()
    {
        try {
            AutoLoader::load();
            $this->_docx = new CreateDocx();
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'phpdocx');
        }
    }

    /**
     * Set specific settings to the docx file, e.g. paper type, page margins
     *
     * @param string $paperType
     * @param array $arrOptions
     * @param string $defaultFont
     * @return bool
     */
    public function modifyPageLayout($paperType, $arrOptions = array(), $defaultFont = 'Arial')
    {
        $booSuccess = false;
        try {
            if ($this->_docx instanceof CreateDocx) {
                $this->_docx->setDefaultFont($defaultFont);
                $this->_docx->modifyPageLayout($paperType, $arrOptions);

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Generate docx file from html content
     *
     * @param string $html content
     * @param string $filePath - path to the docx file location (note without docx extension)
     * @param bool $booDownload - true to automatically output to browser
     * @return bool true on success
     */
    public function createDocxFromHtml($html, $filePath, $booDownload = false)
    {
        $booSuccess = false;
        try {
            if ($this->_docx instanceof CreateDocx) {
                $this->_docx->embedHTML(
                    $html,
                    array(
                        'downloadImages' => true
                    )
                );

                if ($booDownload) {
                    $this->_docx->createDocxAndDownload($filePath);
                } else {
                    $this->_docx->createDocx($filePath);
                }

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    public function createDocx($filePath)
    {
        $booSuccess = false;
        try {
            if ($this->_docx instanceof CreateDocx) {
                $this->_docx->createDocx($filePath);
                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Checks multidimensional array of various variables and normalize them into an array
     * of either scalar values, or WordFragment objects
     * @param string|int|PhpDocxImage|PhpDocxTable|array<string|int|PhpDocxImage|PhpDocxTable> $variable
     * @param CreateDocx $docx
     * @param string $target
     * @return array{string,WordFragment|null}
     */
    public function normalizeReplacementVariable($variable, CreateDocx $docx, $target)
    {
        $textual      = '';
        $wordFragment = null;
        if (!is_array($variable)) {
            $variable = [$variable];
        }
        foreach ($variable as $value) {
            if (is_scalar($value)) {
                // Remove Html tags - they will be not visible in the docx file
                $textual .= strip_tags($value);
            } elseif ($value instanceof PhpDocxTable) {
                if (is_null($wordFragment)) {
                    $wordFragment = new WordFragment($docx, $target);
                }
                $wordFragment->addTable($value->values, $value->properties);
            } elseif ($value instanceof PhpDocxImage) {
                if (is_null($wordFragment)) {
                    $wordFragment = new WordFragment($docx, $target);
                }
                $wordFragment->addImage($value->properties);
            }
        }

        return [$textual, $wordFragment];
    }

    /**
     * Processes passed variables in a specified part of the DOCX document.
     * @param CreateDocxFromTemplate $docx
     * @param string $target
     * @param string|int|PhpDocxImage|PhpDocxTable|array<PhpDocxImage|PhpDocxTable> $variables
     * @param array $options
     * @return CreateDocxFromTemplate
     */
    public function processVariablesInDocx(CreateDocxFromTemplate &$docx, $target, $variables, $options)
    {
        $wordFragments    = [];
        $textualVariables = [];

        foreach ($variables as $key => &$variable) {
            list($textualPart, $wordFragment) = $this->normalizeReplacementVariable($variable, $docx, $target);
            if (!is_null($wordFragment)) {
                $wordFragments[$key] = $wordFragment;
            }
            if (!empty($textualPart)) {
                $textualVariables[$key] = $textualPart;
            }
        }

        if (!empty($wordFragments)) {
            $docx->replaceVariableByWordFragment($wordFragments, $options);
        }

        if (!empty($textualVariables)) {
            $docx->replaceVariableByText($textualVariables, $options);
        }

        return $docx;
    }

}