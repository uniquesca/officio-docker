<?php
namespace Phpdocx\Transform;

use Exception;
use Phpdocx\Logger\PhpdocxLogger;
use Phpdocx\Utilities\PhpdocxUtilities;

/**
 * Transform documents using LibreOffice
 *
 * @category   Phpdocx
 * @package    trasform
 * @copyright  Copyright (c) Narcea Producciones Multimedia S.L.
 *             (http://www.2mdc.com)
 * @license    phpdocx LICENSE
 * @link       https://www.phpdocx.com
 */

require_once dirname(__FILE__) . '/TransformDocAdv.php';

class TransformDocAdvLibreOffice extends TransformDocAdv
{
    /**
     * Calculate and return the document statistics
     * 
     * @param string $source DOCX document
     * @return array
     */
    public function getStatistics($source)
    {
        if (!file_exists($source)) {
            PhpdocxLogger::logger('The file not exist', 'fatal');
        }

        $phpdocxconfig   = PhpdocxUtilities::parseConfig();
        $libreOfficePath = @$phpdocxconfig['transform']['path'];

        // storage the output as ASCII text file
        $tempFile = realpath($source) . uniqid('_txt');

        // run the statistics macro
        passthru($libreOfficePath . ' --invisible "macro:///Standard.Module1.GetStatistics(' . realpath($source) . ',' . $tempFile . ')" ');

        // parse the statistics and return them
        $statistics = array();
        $statisticsFile = fopen($tempFile, 'r');
        if (!$statisticsFile) {
            throw new Exception('Unable to open the stats file.');
        }
        while (($statistic = fgets($statisticsFile)) !== false) {
            $dataStatistic = explode(': ', $statistic);
            $statistics[$dataStatistic[0]] = $dataStatistic[1];
        }
        fclose($statisticsFile);

        return $statistics;
    }

    /**
     * Transform documents:
     *     DOCX to PDF, HTML, DOC, ODT, PNG, RTF, TXT
     *     DOC to DOCX, PDF, HTML, ODT, PNG, RTF, TXT
     *     ODT to DOCX, PDF, HTML, DOC, PNG, RTF, TXT
     *     RTF to DOCX, PDF, HTML, DOC, ODT, PNG, TXT
     *
     * @access public
     * @param $source
     * @param $target
     * @param array $options :
     *   'comments' (bool) : false (default) or true. Exports the comments
     *   'debug' (bool) : false (default) or true. Shows debug information about the plugin conversion
     *   'extraOptions' (string) : extra parameters to be used when doing the conversion
     *   'formsfields' (bool) : false (default) or true. Exports the form fields
     *   'homeFolder' (string) : set a custom home folder to be used for the conversions
     *   'lossless' (bool) : false (default) or true. Lossless compression
     *   'method' (string) : 'direct' (default), 'script' ; 'direct' method uses passthru and 'script' uses a external script
     *   'outdir' (string) : set the outdir path. Useful when the PDF output path is not the same than the running script
     *   'pdfa1' (bool) : false (default) or true. Generates the TOC before exporting the document
     *   'toc' (bool) : false (default) or true. Generates the TOC before transforming the document
     * @return void
     */
    public function transformDocument($source, $target, $options = array())
    {
        $allowedExtensionsSource = array('doc', 'docx', 'html', 'odt', 'rtf', 'txt', 'xhtml');
        $allowedExtensionsTarget = array('doc', 'docx', 'html', 'odt', 'pdf', 'png', 'rtf', 'txt', 'xhtml');

        $filesExtensions = $this->checkSupportedExtension($source, $target, $allowedExtensionsSource, $allowedExtensionsTarget);

        if (!isset($options['method'])) {
            $options['method'] = 'direct';
        }
        if (!isset($options['debug'])) {
            $options['debug'] = false;
        }
        if (!isset($options['toc'])) {
            $options['toc'] = false;
        }
        // rename the output file to target as default
        $renameFiles = true;

        // get the file info
        $sourceFileInfo  = pathinfo($source);
        $sourceExtension = $sourceFileInfo['extension'];

        $phpdocxconfig   = PhpdocxUtilities::parseConfig();
        $libreOfficePath = $phpdocxconfig['transform']['path'];

        $customHomeFolder = false;
        if (isset($options['homeFolder'])) {
            $currentHomeFolder = getenv("HOME");
            putenv("HOME=" . $options['homeFolder']);
            $customHomeFolder = true;
        } else {
            if (isset($phpdocxconfig['transform']['home_folder'])) {
                $currentHomeFolder = getenv("HOME");
                putenv("HOME=" . $phpdocxconfig['transform']['home_folder']);
                $customHomeFolder = true;
            }
        }

        $extraOptions = '';
        if (isset($options['extraOptions'])) {
            $extraOptions = $options['extraOptions'];
        }

        // set outputstring for debugging
        $outputDebug = '';
        if (PHP_OS == 'Linux' || PHP_OS == 'Darwin' || PHP_OS == ' FreeBSD') {
            if (!$options['debug']) {
                $outputDebug = ' > /dev/null 2>&1';
            }
        } elseif (substr(PHP_OS, 0, 3) == 'Win' || substr(PHP_OS, 0, 3) == 'WIN') {
            if (!$options['debug']) {
                $outputDebug = ' > nul 2>&1';
            }
        }

        // if the outdir option is set use it as target path, instead use the dir path 
        if (isset($options['outdir'])) {
            $outdir = $options['outdir'];
        } else {
            $outdir = $sourceFileInfo['dirname'];
        }

        if ($options['method'] == 'script') {
            passthru('php ' . dirname(__FILE__) . '/../lib/convertSimple.php -s ' . $source . ' -e ' . $filesExtensions['targetExtension'] . ' -p ' . $libreOfficePath . ' -t ' . $options['toc'] . ' -o ' . $outdir . $outputDebug);
        } else {
            if ((isset($options['toc']) && $options['toc'] === true) && (!isset($options['pdfa1']) || (isset($options['pdfa1']) && $options['pdfa1'] === false))) {
                if ($filesExtensions['targetExtension'] == 'docx') {
                    // TOC DOCX
                    passthru($libreOfficePath . ' ' . $extraOptions . ' --invisible "macro:///Standard.Module1.SaveToDocxToc(' . realpath($source) . ',' . $target . ')" ' . $outputDebug);
                    // LibreOffice saves the output file to the chosen target, avoid renaming it
                    $renameFiles = false;
                } else {
                    // TOC PDF
                    passthru($libreOfficePath . ' ' . $extraOptions . ' --invisible "macro:///Standard.Module1.SaveToPdfToc(' . realpath($source) . ')" ' . $outputDebug);
                }
            } elseif ((isset($options['toc']) && $options['toc'] === true) && (isset($options['pdfa1']) && $options['pdfa1'] === true)) {
                // TOC and PDFA-1
                passthru($libreOfficePath . ' ' . $extraOptions . ' --invisible "macro:///Standard.Module1.SaveToPdfA1Toc(' . realpath($source) . ')" ' . $outputDebug);
            } elseif ((isset($options['pdfa1']) && $options['pdfa1'] === true) && (!isset($options['toc']) || (!isset($options['toc']) || $options['toc'] === false))) {
                // PDFA-1
                passthru($libreOfficePath . ' ' . $extraOptions . ' --invisible "macro:///Standard.Module1.SaveToPdfA1(' . realpath($source) . ')" ' . $outputDebug);
            } elseif ((isset($options['comments']) && $options['comments'] === true)) {
                // comments
                passthru($libreOfficePath . ' ' . $extraOptions . ' --invisible "macro:///Standard.Module1.ExportNotesToPdf(' . realpath($source) . ')" ' . $outputDebug);
            } elseif ((isset($options['lossless']) && $options['lossless'] === true)) {
                // lossless
                passthru($libreOfficePath . ' ' . $extraOptions . ' --invisible "macro:///Standard.Module1.LosslessPdf(' . realpath($source) . ')" ' . $outputDebug);
            } elseif ((isset($options['formsfields']) && $options['formsfields'] === true)) {
                // forms fields
                passthru($libreOfficePath . ' ' . $extraOptions . ' --invisible "macro:///Standard.Module1.ExportFormFieldsToPdf(' . realpath($source) . ')" ' . $outputDebug);
            } else {
                // default
                passthru($libreOfficePath . ' ' . $extraOptions . ' --invisible --convert-to ' . $filesExtensions['targetExtension'] . ' ' . $source . ' --outdir ' . $outdir . $outputDebug);
            }
        }

        // get the converted document, this is the name of the source and the extension
        $newDocumentPath = $outdir . '/' . $sourceFileInfo['filename'] . '.' . $filesExtensions['targetExtension'];

        // move the document to the guessed destination
        if ($renameFiles) {
            rename($newDocumentPath, $target);
        }

        // restore the previous HOME value if a custom one has been set
        if ($customHomeFolder) {
            putenv("HOME=" . $currentHomeFolder);
        }
    }

}
