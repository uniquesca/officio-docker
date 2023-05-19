<?php

namespace Documents\Service;

use Exception;
use Files\Model\FileInfo;
use Files\Service\Files;
use FilesystemIterator;
use Forms\Service\Pdf;
use Laminas\Db\Sql\Select;
use Laminas\View\Helper\Layout;
use Laminas\View\HelperPluginManager;
use Officio\Common\Service\BaseService;
use Uniques\Php\StdLib\FileTools;
use Officio\Common\Service\Encryption;
use Officio\Service\Letterheads;
use Officio\Common\Service\Settings;
use Officio\Common\ServiceContainerHolder;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Templates\Service\Templates;
use Uniques\Php\StdLib\StringTools;
use ZipArchive;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Documents extends BaseService
{

    use ServiceContainerHolder;

    /** @var Files */
    protected $_files;

    /** @var Phpdocx */
    protected $_phpdocx;

    /** @var Letterheads */
    protected $_letterheads;

    /** @var Pdf */
    protected $_pdf;

    /** @var HelperPluginManager */
    protected $_viewHelperManager;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_files             = $services[Files::class];
        $this->_letterheads       = $services[Letterheads::class];
        $this->_pdf               = $services[Pdf::class];
        $this->_phpdocx           = $services[Phpdocx::class];
        $this->_viewHelperManager = $services[HelperPluginManager::class];
        $this->_encryption        = $services[Encryption::class];
    }

    public function getPhpDocx() {
        if (is_null($this->_phpdocx)) {
            $this->_phpdocx = new Phpdocx($this->_config, $this->_db2, $this->_auth, $this->_acl, $this->_cache, $this->_log, $this->_tr, $this->_settings, $this->_tools);
            $this->_phpdocx->initAdditionalServices([]);
            $this->_phpdocx->init();
        }
        return $this->_phpdocx;
    }

    public function saveCompanyLetterTemplate($companyId, $letterTemplateId, $fileType, $tempFileId)
    {
        $strError = '';

        try {
            if ($fileType == 'upload' && !isset($_FILES[$tempFileId])) {
                $strError = $this->_tr->translate('File was not uploaded.');
            }

            if (empty($strError)) {
                $fileTmpPath = $fileType == 'upload' ? $_FILES[$tempFileId]['tmp_name'] : $this->_files->fixFilePath($this->_config['directory']['tmp'] . '/' . microtime() . '-' . $letterTemplateId . '.docx');
                if ($fileType != 'upload') {
                    $fileExtension = '.docx';
                    if (!$this->_phpdocx->createDocx(substr($fileTmpPath, 0, strlen($fileTmpPath) - strlen($fileExtension)))) {
                        $strError = $this->_tr->translate('Blank docx file was not created.');
                    }
                }

                if (empty($strError)) {
                    $booLocal                   = $this->_auth->isCurrentUserCompanyStorageLocal();
                    $companyLetterTemplatesPath = $this->_files->getCompanyLetterTemplatesPath($companyId, $booLocal) ?? '';

                    if ($booLocal) {
                        $filePath = rtrim($companyLetterTemplatesPath, '/') . '/' . $letterTemplateId;
                        if (!$this->_files->moveLocalFile($fileTmpPath, $filePath)) {
                            $strError = $this->_tr->translate('File was not uploaded.');
                        }
                    } else {
                        $companyLetterTemplatesPath = $this->_files->getCloud()->isFolder($companyLetterTemplatesPath) ? $companyLetterTemplatesPath : $companyLetterTemplatesPath . '/';
                        $filePath                   = $this->_files->getCloud()->preparePath($companyLetterTemplatesPath) . $letterTemplateId;
                        if (!$this->_files->getCloud()->uploadFile($fileTmpPath, $filePath)) {
                            $strError = $this->_tr->translate('File was not uploaded.');
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

    /**
     * Move file from one folder to another
     *
     * @param string|array $files
     * @param string $moveToFolder
     * @return bool true if all files/folders were moved
     */
    public function moveFiles($files, $moveToFolder)
    {
        $booSuccess = false;
        $files      = (array) $files;

        if ($moveToFolder !== false) {
            $booLocal = $this->_auth->isCurrentUserCompanyStorageLocal();

            //update all files
            foreach ($files as $realPath) {
                // Check if it is possible to move the file
                $moveToFolder = rtrim($moveToFolder, '/') . '/';

                if ($booLocal) {
                    $fileName = FileTools::cleanupFileName($this->_files::extractFileName($realPath));
                    $newPath  = $moveToFolder . $fileName;

                    if ($realPath === $newPath) {
                        $booSuccess = true;
                    } else {
                        if (file_exists($newPath)) {
                            unlink($newPath);
                        }

                        $this->_files->createFTPDirectory(dirname($newPath));
                        $booSuccess = rename($realPath, $newPath);
                    }
                } else {
                    if ($this->_files->getCloud()->isFolder($realPath)) {
                        $newPath = $moveToFolder . $this->_files->getCloud()->getFolderNameByPath($realPath);
                    } else {
                        $newPath = $moveToFolder . $this->_files->getCloud()->getFileNameByPath($realPath);
                    }

                    if ($realPath === $newPath) {
                        $booSuccess = true;
                    } else {
                        $booSuccess = $this->_files->getCloud()->renameObject($realPath, $newPath);
                    }
                }

                if (!$booSuccess) {
                    break;
                }
            }
        }

        return $booSuccess;
    }

    /**
     * Copy files from one folder to another
     *
     * @param array $arrFiles
     * @param string $copyToFolder
     * @return bool
     */
    public function copyFiles($arrFiles, $copyToFolder)
    {
        $booSuccess = false;

        if (!empty($copyToFolder)) {
            $booLocal = $this->_auth->isCurrentUserCompanyStorageLocal();

            $copyToFolder = rtrim($copyToFolder, '/') . '/';

            if ($booLocal) {
                $oCloud = null;
                $this->_files->createFTPDirectory($copyToFolder);
            } else {
                $oCloud = $this->_files->getCloud();
            }

            $arrFiles = (array)$arrFiles;
            foreach ($arrFiles as $realPath) {
                if ($booLocal) {
                    $fileName = FileTools::cleanupFileName($this->_files::extractFileName($realPath));
                    $newPath  = $copyToFolder . '/' . $fileName;

                    if ($realPath == $newPath) {
                        $booSuccess = true;
                    } else {
                        if (file_exists($newPath)) {
                            unlink($newPath);
                        }

                        $this->_files->createFTPDirectory(dirname($newPath));
                        $booSuccess = is_file($realPath) && copy($realPath, $newPath);
                    }
                } else {
                    if ($oCloud->isFolder($realPath)) {
                        $newPath = $copyToFolder . StringTools::stripInvisibleCharacters($oCloud->getFolderNameByPath($realPath), false);
                    } else {
                        $newPath = $copyToFolder . FileTools::cleanupFileName($oCloud->getFileNameByPath($realPath));
                    }

                    if ($realPath == $newPath) {
                        $booSuccess = true;
                    } else {
                        $booSuccess = $oCloud->checkObjectExists($realPath) ? $oCloud->copyObject($realPath, $newPath) : false;
                    }
                }

                if (!$booSuccess) {
                    break;
                }
            }
        }

        return $booSuccess;
    }

    /**
     * Check how the file can be previewed
     * If the file is supported by Zoho and Zoho is turned on - generate a link to preview/edit
     *
     * @param string $filePath
     * @param int $memberId
     * @param bool $booProspect
     * @return array
     */
    public function preview($filePath, $memberId, $booProspect = false)
    {
        $strError         = '';
        $fileType         = '';
        $fileName         = '';
        $fileDownloadLink = '';
        $fileContent      = '';

        try {
            $fileName   = $this->_files::extractFileName($filePath);
            $fileFormat = strtolower(FileTools::getFileExtension($fileName) ?? '');
            $fileMime   = FileTools::getMimeByFileName($fileName);

            // If this is some 'bad' file
            if ($fileFormat == 'php') {
                $fileType = 'file';
            }

            if (empty($fileType) && $this->_files->isImage($fileMime)) {
                $fileType = 'image';
            }

            if (empty($fileType) && $this->_files->isPDF($fileMime)) {
                $fileType = 'pdf';
            }

            if (empty($fileType) && $this->_files->isSupportedZoho($fileFormat)) {
                $fileType = 'zoho';
            }

            if (empty($fileType) && $this->_files->isEmail($fileMime)) {
                $fileType    = 'email';
                $fileContent = $this->_files->showEmail($filePath, 'documents');
            }

            if (empty($fileType)) {
                $fileType = 'file';
            }

            /** @var Layout $layout */
            $layout = $this->_viewHelperManager->get('layout');
            $url    = $booProspect ? '/prospects/index/' : '/documents/index/';

            if (!$this->_auth->isCurrentUserCompanyStorageLocal()) {
                $fileDownloadLink = $fileType == 'image' ?
                    $this->_files->getCloud()->generateFileDownloadLink($filePath, true, false, $fileName) :
                    $this->_files->getCloud()->generateFileDownloadLink($filePath, false, true, $fileName, $fileType != 'zoho');
            } else {
                $arrParams = array(
                    'id'    => $filePath,
                    'mem'   => empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId,
                    'c_mem' => $this->_auth->getCurrentUserId(),
                    'exp'   => strtotime('+2 minutes')
                );

                $fileDownloadLink = $layout()->getVariable('topBaseUrl') . $url . 'get-file?enc=' . $this->_encryption->encode(serialize($arrParams));
            }

            if ($fileType == 'zoho') {
                $arrEncCheckParams = array(
                    'c_mem'     => $this->_auth->getCurrentUserId(),
                    'member_id' => empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId
                );

                $strEncCheckParams = $this->_encryption->encode(serialize($arrEncCheckParams));
                $fileSaveLink      = $layout()->getVariable('topBaseUrl') . $url . 'save-file';

                list($strError, $fileDownloadLink) = $this->_files->getZohoDocumentUrl($fileName, $this->_encryption->encode($filePath), $fileFormat, $fileDownloadLink, $fileSaveLink, $strEncCheckParams);
                if (!empty($strError)) {
                    $strError = $this->_tr->translate('Zoho error: ') . $strError;
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return [
            'success'   => empty($strError),
            'message'   => $strError,
            'type'      => $fileType,
            'filename'  => $fileName,
            'file_path' => $fileDownloadLink,
            'content'   => $fileContent,
        ];
    }

    /**
     * Generate short path (that we show in the grid) for the file
     *
     * @param string $fullFilePath
     * @param string $clientTopPath
     * @param string $sharedDocsTopPath
     * @return string
     */
    private function prepareFilePathForZip($fullFilePath, $clientTopPath, $sharedDocsTopPath)
    {
        $fullFilePath = str_replace('\\', '/', $fullFilePath ?? '');

        $clientTopPath = str_replace('\\', '/', $clientTopPath);
        $parsedTopPath = substr($fullFilePath, 0, strlen($clientTopPath));

        $sharedDocsTopPath = str_replace('\\', '/', $sharedDocsTopPath);
        $parsedSharedPath  = substr($fullFilePath, 0, strlen($sharedDocsTopPath));

        if ($parsedTopPath == $clientTopPath) {
            $res = str_replace($clientTopPath, '', $fullFilePath);
        } elseif ($parsedSharedPath == $sharedDocsTopPath) {
            $res = str_replace($sharedDocsTopPath, '', $fullFilePath);
        } else {
            $res = $fullFilePath;
        }

        return $res;
    }

    /**
     * Generate and output zip file
     *
     * @param array $arrObjects (can be folders/files paths)
     * @param bool $booLocal
     * @param string $zipFileName
     * @return FileInfo|string FileInfo on success, error on fail
     */
    public function createZip($arrObjects, $booLocal, $zipFileName, $pathToClientDocs, $pathToSharedDocs)
    {
        $strError = '';

        try {
            $arrTempFiles = array();
            if (empty($strError)) {
                $arrFilesToZip = array();
                if ($booLocal) {
                    // Load list of files/folders on local server
                    foreach ($arrObjects as $objectPath) {
                        if (is_file($objectPath)) {
                            $filePathInZip                 = $this->prepareFilePathForZip($objectPath, $pathToClientDocs, $pathToSharedDocs);
                            $arrFilesToZip[$filePathInZip] = $objectPath;
                        } elseif (is_dir($objectPath)) {
                            // Load all files from this directory
                            $iterator = new RecursiveIteratorIterator(
                                new RecursiveDirectoryIterator($objectPath, FilesystemIterator::SKIP_DOTS),
                                RecursiveIteratorIterator::SELF_FIRST,
                                RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
                            );

                            foreach ($iterator as $file) {
                                if (!$file->isDir()) {
                                    $filePathInZip                 = $this->prepareFilePathForZip($file->getPathname(), $pathToClientDocs, $pathToSharedDocs);
                                    $arrFilesToZip[$filePathInZip] = $file->getPathname();
                                }
                            }
                        }
                    }
                } else {
                    // Load list of files/folders on the cloud server, download files to the local server before zipping
                    foreach ($arrObjects as $objectPath) {
                        $objectPath = str_replace('\\', '/', $objectPath);

                        // For some reason path to the Shared workspace is passed without the '/' at the end.
                        $objectPath = $objectPath == $pathToSharedDocs ? rtrim($objectPath, '/') . '/' : $objectPath;

                        if (!$this->_files->getCloud()->isFolder($objectPath)) {
                            $filePathInZip = $this->prepareFilePathForZip($objectPath, $pathToClientDocs, $pathToSharedDocs);

                            if(!isset($arrFilesToZip[$filePathInZip])) {
                                $tempFileName = $this->_files->getCloud()->downloadFileContent($objectPath);
                                if(file_exists($tempFileName)) {
                                    $arrFilesToZip[$filePathInZip] = $tempFileName;

                                    $arrTempFiles[] = $tempFileName;
                                }
                            }
                        } else {
                            $arrItems = $this->_files->getCloud()->getList(rtrim($objectPath, '/'));

                            foreach ($arrItems as $object) {
                                $filePathInZip = $this->prepareFilePathForZip('/' . $object['FullPath'], $pathToClientDocs, $pathToSharedDocs);

                                if (!$this->_files->getCloud()->isFolder($object['Key']) && !isset($arrFilesToZip[$filePathInZip])) {
                                    $tempFileName = $this->_files->getCloud()->downloadFileContent($object['FullPath']);
                                    if (file_exists($tempFileName)) {
                                        $arrFilesToZip[$filePathInZip] = $tempFileName;

                                        $arrTempFiles[] = $tempFileName;
                                    }
                                }
                            }
                        }
                    }
                }

                // After we downloaded/prepared all files - create a zip archive from them
                if(count($arrFilesToZip)) {
                    $zip = new ZipArchive();

                    $tempZipFilePath = tempnam($this->_config['directory']['tmp'], 'zip');
                    $res             = $zip->open($tempZipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

                    if ($res === true) {
                        foreach ($arrFilesToZip as $fileName => $filePath) {
                            $zip->addFile($filePath, ltrim($fileName, '/'));
                        }
                        $zip->close();
                        $fileInfo = new FileInfo($zipFileName, $tempZipFilePath, true);

                        // Delete temp files after all
                        foreach ($arrTempFiles as $tempFilePath) {
                            unlink($tempFilePath);
                        }

                        return $fileInfo;
                    } else {
                        $strError = $this->_tr->translate('Cannot create zip.');
                    }
                } else {
                    $strError = $this->_tr->translate('Nothing to zip.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

    /**
     * Rename the file
     *
     * @param bool $booLocal
     * @param string $oldPathToFile
     * @param string $newFilename
     * @return bool true on success, otherwise false
     */
    public function renameFile($booLocal, $oldPathToFile, $newFilename)
    {
        $booSuccess = false;
        try {
            $oldPathToFile = str_replace('\\', '/', $oldPathToFile);
            $oldFileName   = $this->_files::extractFileName($oldPathToFile);
            $pathToFile    = str_replace($oldFileName, '', $oldPathToFile);
            $newPathToFile = $pathToFile . $newFilename;

            if ($oldPathToFile == $newPathToFile) {
                // Don't try to rename if the file name/path is the same
                $booSuccess = true;
            } elseif ($booLocal) {
                if (is_file($oldPathToFile)) {
                    $this->_files->createFTPDirectory(dirname($newPathToFile));
                    $booSuccess = rename($oldPathToFile, $newPathToFile);
                }
            } elseif ($this->_files->getCloud()->checkObjectExists($oldPathToFile)) {
                $booSuccess = $this->_files->getCloud()->renameObject($oldPathToFile, $newPathToFile);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    public function newFile($folderId, $name, $type)
    {
        $blankFile = $this->_files->getFolderBlankPath() . '/blank.' . $type;

        // This is ftp folder
        $copyToPath     = str_replace('\\', '/', $folderId ?? '');
        $copyToFullPath = rtrim($copyToPath, '/') . '/' . $name . '.' . $type;

        // Create blank file
        if ($this->_auth->isCurrentUserCompanyStorageLocal()) {
            if (file_exists($copyToFullPath)) {
                unlink($copyToFullPath);
            }

            $this->_files->createFTPDirectory(dirname($copyToFullPath));
            $booSuccess = copy($blankFile, $copyToFullPath);
        } else {
            $booSuccess = $this->_files->getCloud()->copyObject($blankFile, $copyToFullPath) &&
                $this->_files->getCloud()->updateObjectCreationTime($copyToFullPath, time());
        }

        return $booSuccess ? $copyToFullPath : false;
    }

    /**
     * Create a docx from the provided html message
     *
     * @param string $message
     * @param string $folderPath
     * @param string $fileName
     * @return false|string encoded path to the file on success
     */
    public function createLetter($message, $folderPath, $fileName)
    {
        $booSuccess     = false;
        $copyToFullPath = '';

        try {
            $fileExtension = '.' . FileTools::getFileExtension($fileName);
            $fileTmpPath = $this->_files->fixFilePath($this->_config['directory']['tmp'] . '/' . microtime() . '-' . $fileName);

            if (empty($message)) {
                if (!$this->_phpdocx->createDocx(substr($fileTmpPath, 0, strlen($fileTmpPath) - strlen($fileExtension)))) {
                    return false;
                }
            } else {
                $content = '<html><head></head><body>' . $message . '</body></html>';
                if (!$this->_phpdocx->createDocxFromHtml($content, substr($fileTmpPath, 0, strlen($fileTmpPath) - strlen($fileExtension)))) {
                    return false;
                }
            }

            $copyToFullPath = rtrim($folderPath, '/') . '/' . $fileName;
            if ($this->_auth->isCurrentUserCompanyStorageLocal()) {
                $booSuccess = $this->_files->moveLocalFile($fileTmpPath, $copyToFullPath);
            } else {
                $booSuccess = $this->_files->getCloud()->uploadFile($fileTmpPath, $copyToFullPath);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $booSuccess ? $copyToFullPath : false;
    }

    /**
     * Convert a specific file to the pdf
     *
     * @param string $folderPath
     * @param string $filePath
     * @param string $fileName
     * @param bool $booTemp
     * @return array
     */
    public function convertToPdf($folderPath, $filePath, $fileName, $booTemp = false)
    {
        $fileId   = '';
        $fileSize = '';
        $strError = '';

        try {
            if (empty($filePath) || empty($fileName)) {
                $strError = $this->_tr->translate('File cannot be converted (empty path).');
            }

            $booLocal = $this->_auth->isCurrentUserCompanyStorageLocal();
            if (empty($strError) && ($booTemp || $booLocal) && !file_exists($filePath)) {
                $strError = $this->_tr->translate('File cannot be converted (file does not exist).');
            }

            if (empty($strError)) {
                $fileExtension = FileTools::getFileExtension($fileName);

                if ($booTemp) {
                    $fileTmpPath = $filePath;
                } else {
                    $fileTmpPath = $this->_files->fixFilePath($this->_config['directory']['tmp'] . '/' . microtime() . '.' . $fileExtension);
                    if ($booLocal) {
                        if (!copy($filePath, $fileTmpPath)) {
                            $strError = $this->_tr->translate('File cannot be converted (cannot copy).');
                        }
                    } else {
                        $filePath = $this->_files->getCloud()->preparePath($filePath);
                        if ($this->_files->getCloud()->checkObjectExists($filePath)) {
                            $fileTmpPath2 = $this->_files->getCloud()->downloadFileContent($filePath);
                            if (empty($fileTmpPath2) || !copy($fileTmpPath2, $fileTmpPath)) {
                                $strError = $this->_tr->translate('File (cloud) cannot be converted (cannot copy).');
                            }
                        } else {
                            $strError = $this->_tr->translate('File (cloud) does not exist.');
                        }
                    }
                }

                if (empty($strError) && !is_file($fileTmpPath)) {
                    $strError = $this->_tr->translate('File cannot be converted (deleted?).');
                }

                if (empty($strError)) {
                    $convertedFileTmpPath = $this->_pdf->_runConvertCommand($fileTmpPath, $this->_config['directory']['tmp'] . '/');
                    if ($convertedFileTmpPath) {
                        if ($folderPath) {
                            $copyToFullPath = rtrim($folderPath, '/') . '/' . substr($fileName, 0, strlen($fileName) - strlen($fileExtension)) . 'pdf';

                            if ($booLocal) {
                                $copyToFullPath = $this->_files->fixFilePath($copyToFullPath);
                                $booSuccess     = $this->_files->moveLocalFile($convertedFileTmpPath, $copyToFullPath);
                            } else {
                                $copyToFullPath = $this->_files->getCloud()->fixFilePath($copyToFullPath);
                                $booSuccess     = $this->_files->getCloud()->uploadFile($convertedFileTmpPath, $copyToFullPath);
                            }

                            if ($booSuccess) {
                                $fileId   = $copyToFullPath;
                                $fileSize = $booLocal ? filesize($copyToFullPath) : $this->_files->getCloud()->getObjectFilesize($copyToFullPath);
                                $fileSize = Settings::formatSize($fileSize / 1024);
                            } else {
                                $strError = $this->_tr->translate('Internal error.');
                            }
                        } else { // for files converted from "Email" dialog
                            $fileId   = $convertedFileTmpPath;
                            $fileSize = Settings::formatSize(filesize($convertedFileTmpPath) / 1024);
                        }
                    } else {
                        $strError = $this->_tr->translate('File was not converted.');
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array('success' => empty($strError), 'error' => $strError, 'file_id' => $fileId, 'file_size' => $fileSize);
    }

    public function createLetterOnLetterhead($message, $folderId, $fileName, $letterheadId, $letterheadsPath, $booPreview = false, $arrPageNumberSettings = array())
    {
        $booSuccess = false;
        $filename   = '';
        $strError   = '';

        try {
            $content = $message;

            if ($booPreview) {
                $filename          = $this->_files->convertToFilename('letter_preview_(' . date('Y-m-d H-i-s') . ').pdf');
                $tempPdfFolderPath = $this->_config['directory']['pdf_temp'] . DIRECTORY_SEPARATOR;
                $copyToFullPath    = $tempPdfFolderPath . $filename;
            } else {
                $filename       = $fileName . '.pdf';
                $copyToFullPath = rtrim($folderId ?? '', '/') . '/' . FileTools::cleanupFileName($filename);
            }
            if ($letterheadId == 0) {
                $letterhead = array();
            } else {
                $letterhead = $this->_letterheads->getLetterhead($letterheadId);
                if (!$letterhead) {
                    $strError = $this->_tr->translate('Selected letterhead does not exist.');
                }
            }
            if (empty($strError)) {
                $fileTmpPath = $this->_files->fixFilePath($this->_config['directory']['tmp'] . '/' . microtime() . '-' . $fileName);
                $this->_pdf->letterOnLetterheadToPdf(
                    $content,
                    $letterheadsPath,
                    $fileTmpPath,
                    'F',
                    array(
                        'PDF_PAGE_FORMAT'      => $letterheadId == 0 ? 'A4' : ucwords($letterhead['type'] ?? ''),
                        'letterhead_settings'  => $letterhead,
                        'page_number_settings' => $arrPageNumberSettings,
                        'header_title'         => '',
                        'SetFont'              => array('name' => 'helvetica', 'style' => '', 'size' => 8),
                        'setHeaderFont'        => array('helvetica', '', 10)
                    )
                );

                if ($this->_auth->isCurrentUserCompanyStorageLocal()) {
                    $booSuccess = $this->_files->moveLocalFile($fileTmpPath, $copyToFullPath);
                } else {
                    $booSuccess = $this->_files->getCloud()->uploadFile($fileTmpPath, $copyToFullPath);
                }
            }

            if (empty($strError) && file_exists($copyToFullPath)) {
                $booSuccess = true;
            } else {
                $strError = $this->_tr->translate('Cannot create PDF file.');
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array('success' => $booSuccess, 'filename' => $filename, 'error' => $strError);
    }


    /**
     * Delete folder and its sub folders by id
     * @note user can delete folder only for own company
     *
     * @param int $folderId
     * @param $companyId
     * @return bool true if folder was deleted, otherwise false
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function deleteFolder($folderId, $companyId)
    {
        $booSuccess = false;
        try {
            $arrFolderInfo = $this->_files->getFolders()->getFolderInfo($folderId, $companyId, true);

            if (!empty($arrFolderInfo['folder_id'])) {
                $arrSubFolderIds = array();

                $this->_files->getFolders()->getSubFolderIds($folderId, $arrSubFolderIds);
                if (is_array($arrSubFolderIds)) {
                    $arrSubFolderIds = array_unique($arrSubFolderIds);
                }

                if (is_array($arrSubFolderIds) && count($arrSubFolderIds)) {
                    // We can delete these folders
                    $select = (new Select())
                        ->from(array('t' => 'templates'))
                        ->columns(['template_id'])
                        ->where(['folder_id' => $arrSubFolderIds]);

                    $arrTemplateIds = $this->_db2->fetchCol($select);

                    /** @var Templates $templates */
                    $templates = $this->_serviceContainer->get(Templates::class);
                    $templates->delete($arrTemplateIds);

                    // Delete access rights for this folder and all subfolders
                    $this->_files->getFolders()->getFolderAccess()->deleteByFolderIds($arrSubFolderIds);

                    $this->_files->getFolders()->deleteFolders($arrSubFolderIds);

                    $booSuccess = true;
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

}
