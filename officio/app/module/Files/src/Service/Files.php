<?php

namespace Files\Service;

use Clients\Service\Members;
use DateTime;
use DateTimeZone;
use Exception;
use Files\ImageManager;
use Files\Model\FileInfo;
use FilesystemIterator;
use Laminas\Db\Sql\Select;
use Laminas\Filter\File\RenameUpload;
use Laminas\Filter\FilterChain;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\InputFilter\FileInput;
use Officio\Common\Json;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\File\Extension;
use Laminas\Validator\File\Size;
use Laminas\View\Helper\Layout;
use Laminas\View\HelperPluginManager;
use Uniques\Php\StdLib\FileTools;
use Officio\Email\FileManagerInterface;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\ZohoKeys;
use Officio\Common\SubServiceOwner;
use PhpMimeMailParser\Parser;
use Prospects\Service\CompanyProspects;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Uniques\Php\StdLib\StringTools;
use ZipArchive;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Files extends SubServiceOwner implements FileManagerInterface
{

    /** @var array SUPPORTED_IMAGE_FROMATS an array of supported extensions */
    public const SUPPORTED_IMAGE_FROMATS = array('jpg', 'jpeg', 'png', 'gif');

    /** @var int MAX_UPLOAD_IMAGE_SIZE max image size that is allowed to upload */
    public const MAX_UPLOAD_IMAGE_SIZE = 5242880;  // 5Mb

    /** @var Folders */
    protected $_folders;

    /** @var Cloud */
    protected $_cloud;

    /** @var Cloud */
    protected $_imagesCloud;

    /** @var array */
    protected $_dirConfig;


    /** @var HelperPluginManager */
    protected $_viewHelperManager;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_encryption        = $services[Encryption::class];
        $this->_viewHelperManager = $services[HelperPluginManager::class];
    }

    public function init()
    {
        $this->_dirConfig = $this->_config['directory'];
    }

    /**
     * @return Cloud
     */
    public function getCloud($booImagesS3 = false)
    {
        $oCloud = $booImagesS3 ? $this->_imagesCloud : $this->_cloud;
        if (is_null($oCloud)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                /** @var Cloud $oCloud */
                $oCloud = $this->_serviceContainer->build(Cloud::class,
                    [
                        'parent' => $this,
                        'isImagesS3' => $booImagesS3
                    ]);
            } else {
                /** @var Cloud $oCloud */
                $oCloud = $this->_serviceContainer->get(Cloud::class);
                $oCloud->setParent($this)
                    ->setImagesS3($booImagesS3);
            }
            if ($booImagesS3) {
                $this->_imagesCloud = $oCloud;
            } else {
                $this->_cloud = $oCloud;
            }
        }

        return $oCloud;
    }


    /**
     * @return Folders
     */
    public function getFolders()
    {
        if (is_null($this->_folders)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_folders = $this->_serviceContainer->build(Folders::class, ['parent' => $this]);
            } else {
                $this->_folders = $this->_serviceContainer->get(Folders::class);
                $this->_folders->setParent($this);
            }
        }
        return $this->_folders;
    }

    /**
     * Generate unique hash for the file/folder path for the current user.
     * Will be used on the client side only to distinguish files/folders.
     *
     * @param string $path
     * @return string
     */
    public function getHashForThePath($path)
    {
        $path .= 'this is some long salt unique for each user: ' . $this->_auth->getCurrentUserId();
        return hash('sha512', $path);
    }

    /**
     * Check if a specific folder is a default folder
     *
     * @param string $folderPath
     * @param array $arrDefaultFolders
     * @return bool
     */
    private function isDefaultFolder($folderPath, $arrDefaultFolders)
    {
        $booIsDefault = false;

        if (!empty($folderPath) && !empty($arrDefaultFolders)) {
            $booIsDefault = in_array(rtrim($folderPath, '/'), array_column($arrDefaultFolders, 'path'));
        }

        return $booIsDefault;
    }

    /**
     * Generate a folder record for extjs
     *
     * @param string $path
     * @param string $file
     * @param array $arrChildren
     * @param string $type
     * @param string $access
     * @param array $arrDefaultFolders
     * @return array
     */
    private function _generateFolderArray($path, $file, $arrChildren, $type = 'D', $access = 'RW', $arrDefaultFolders = [])
    {
        $allowRW           = ($access == 'RW');
        $allowEdit         = $allowRW && (in_array($type, array('D', 'C', 'F')) || $this->_auth->isCurrentUserAdmin() || $this->_auth->isCurrentUserSuperadmin());
        $allowDeleteFolder = $allowEdit && !in_array($type, ['SDR', 'RF']);

        return array(
            'el_id'             => $this->_encryption->encode($path),
            'path_hash'         => $this->getHashForThePath($path),
            'filename'          => $file,
            'uiProvider'        => 'col',
            'iconCls'           => $this->getFolderIconCls($type == 'D' ? 'CD' : ($type == 'SDR' ? 'SDR' : 'SD')),
            'allowDrag'         => $allowDeleteFolder,
            'allowDrop'         => $allowRW,
            'allowChildren'     => true,
            // all folders will be collapsed,
            // but this check is needed to avoid 'plus' icon for empty folders
            'expanded'          => !count($arrChildren),
            'leaf'              => false,
            'folder'            => true,
            'isDefaultFolder'   => $this->isDefaultFolder($path, $arrDefaultFolders),
            'checked'           => false,
            'allowRW'           => $allowRW,
            'allowEdit'         => $allowEdit,
            'allowDeleteFolder' => $allowDeleteFolder,
            'children'          => $arrChildren
        );
    }

    public function _loadFTPFoldersAndFilesList($dir, $arrDefaultFolders, $type, $memberId, $booProspect, $parentAccess = 'RW')
    {
        $arrResult = array();

        // Get next folder levels and current level files
        if (is_dir($dir)) {
            if (DIRECTORY_SEPARATOR == '\\') {
                $dir = str_replace('\\', '/', $dir);
            }
            $dir_iterator = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
            $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST, FilesystemIterator::SKIP_DOTS);
            $iterator->setMaxDepth(0);

            $booEmailEnabled = $this->_config['mail']['enabled'];
            $booCanAccessEmail = $this->_acl->isAllowed('mail-view');

            try {
                $arrDirs = array();
                $arrFiles = array();
                foreach ($iterator as $path => $file) {
                    $fileName = $file->getFilename();
                    if ($file->isDir()) {
                        if (DIRECTORY_SEPARATOR == '\\') {
                            $path = str_replace('\\', '/', $path);
                        }

                        // Get access to folder
                        /** @var Company $company */
                        $company      = $this->_serviceContainer->get(Company::class);
                        $folderAccess = $company->getCompanyDivisions()->getFolderPathAndAccess($arrDefaultFolders, $path, $memberId, $parentAccess);

                        // load sub folders only if allow access (for default folders) or is not default folder
                        if ($folderAccess) {
                            $arrChildren = $this->_loadFTPFoldersAndFilesList($path, $arrDefaultFolders, $type, $memberId, $booProspect, $folderAccess);

                            $arrDirs[] = $this->_generateFolderArray($path, $fileName, $arrChildren, $type, $folderAccess, $arrDefaultFolders);
                        }
                    } else {
                        // This is a file
                        $mime        = FileTools::getMimeByFileName($file);
                        $fileModTime = $file->getMTime();

                        $cur_encoding = mb_detect_encoding($fileName ?? '');
                        if ($cur_encoding != 'UTF-8' || !mb_check_encoding($fileName, 'UTF-8')) {
                            $fileName = utf8_encode($fileName);
                        }

                        $arrFiles[] = array(
                            'el_id'                 => $this->_encryption->encode($dir . '/' . $fileName),
                            'downloadUrl'           => $this->generateDownloadUrlForBrowser($memberId, $booProspect, $dir . '/' . $fileName, true),
                            'path_hash'             => $this->getHashForThePath($dir . '/' . $fileName),
                            'filename'              => $fileName,
                            'filesize'              => $this->formatFileSize($file->getSize(), 0),
                            'filesize_bytes'        => $file->getSize(),
                            'date'                  => $this->_settings->formatDateTime($fileModTime),
                            'time'                  => $fileModTime,
                            'uiProvider'            => 'col',
                            'allowDrag'             => true,
                            'leaf'                  => true,
                            'iconCls'               => $this->getFileIcon($mime),
                            'libreoffice_supported' => $this->isSupportedLibreOffice($fileName, $mime),
                            'folder'                => false,
                            'checked'               => false,
                            'allowSaveToInbox'      => $booEmailEnabled && $booCanAccessEmail && strtolower(FileTools::getFileExtension($fileName)) == 'eml'
                        );
                    }
                }

                $arrResult = array_merge($arrDirs, $arrFiles);
                $arrResult = $this->prepareResult($arrResult, true);
            } catch (Exception $e) {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'files');
            }
        }


        // And now unite folders list with files list
        return $arrResult;
    }

    public function generateDownloadUrlForBrowser($memberId, $booProspect, $filePath, $booLocal, $booForPublic = false)
    {
        if ($booLocal) {
            $fileName = static::extractFileName($filePath);
        } else {
            $fileName = $this->getCloud()->getFileNameByPath($filePath);
        }

        $mime = FileTools::getMimeByFileName($fileName);
        $args = array(
            'mime'       => $mime,
            'file_name'  => $fileName,
            'base'       => $this->_config['urlSettings']['baseUrl'],
            'controller' => $booProspect ? 'prospects' : 'documents',
            'member_id'  => $memberId,
            'file_id'    => urlencode($this->_encryption->encode($filePath))
        );

        if($booForPublic){

            $enc  = array(
                'id'    => $filePath,
                'mem'   => empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId,
                'c_mem' => $this->_auth->getCurrentUserId(),
                'exp'   => strtotime('+2 minutes')
            );

            $args['enc'] = $this->_encryption->encode(serialize($enc));

            return $this->_settings->_sprintf('%base%/%controller%/index/get-file?enc=%enc%', $args);
        } else {
            return $this->_settings->_sprintf('%mime%:%file_name%:%base%/%controller%/index/get-file?member_id=%member_id%&id=%file_id%', $args);
        }
    }


    public function _loadMemberCloudFoldersAndFilesList($dir, $arrDefaultFolders, $type, $memberId, $booProspect, $parentAccess = 'RW')
    {
        $arrResult = array();

        try {
            $arrItems = $this->getCloud()->getList($dir);

            if (count($arrItems)) {
                $booEmailEnabled = $this->_config['mail']['enabled'];
                $booCanAccessEmail = $this->_acl->isAllowed('mail-view');

                foreach ($arrItems as $object) {
                    $fullPath = rtrim($object['FullPath'] ?? '', '/');
                    $fullPath = ltrim($fullPath ?? '', '/');
                    $fullPath = '/' . $fullPath;

                    $name = $object['Key'] ?? '';
                    $arrItemInfo = array();
                    $arrParentFolders = array();

                    if ($this->getCloud()->isFolder($name)) {
                        // If for some reason there was created a folder with empty name -> ignore it
                        $name       = rtrim($name, '/') . '/';
                        $folderName = $this->getCloud()->getFolderNameByPath($name);
                        $objectMd5  = md5($folderName);

                        // Get access to folder
                        /** @var Company $company */
                        $company      = $this->_serviceContainer->get(Company::class);
                        $folderAccess = $company->getCompanyDivisions()->getFolderPathAndAccess($arrDefaultFolders, $fullPath, $memberId, $parentAccess);

                        // load sub folders only if allow access (for default folders) or is not default folder
                        if ($folderAccess) {
                            $arrParentFolders = $this->getCloud()->getFolderParentFolders($name);
                            $arrItemInfo = $this->_generateFolderArray($dir . '/' . $name, $folderName, array(), $type, $folderAccess, $arrDefaultFolders);
                        }
                    } else {
                        // This is a file
                        $fileName = $this->getCloud()->getFileNameByPath($name);
                        $objectMd5 = md5($fileName);

                        $arrParentFolders = $this->getCloud()->getFileParentFolders($name);

                        if (!is_array($arrParentFolders)) {
                            throw new Exception('Internal error.');
                        }

                        $mime = FileTools::getMimeByFileName($fileName);
                        $fileModTime = $object['LastModified'];

                        $arrItemInfo = array(
                            'el_id'                 => $this->_encryption->encode($dir . '/' . $name),
                            'downloadUrl'           => $this->generateDownloadUrlForBrowser($memberId, $booProspect, $dir . '/' . $name, false),
                            'path_hash'             => $this->getHashForThePath($dir . '/' . $name),
                            'filename'              => $fileName,
                            'filesize'              => $this->formatFileSize($object['Size'], 0),
                            'filesize_bytes'        => $object['Size'],
                            'date'                  => $this->_settings->formatDateTime($fileModTime),
                            'time'                  => strtotime($fileModTime),
                            'uiProvider'            => 'col',
                            'allowDrag'             => true,
                            'leaf'                  => true,
                            'iconCls'               => $this->getFileIcon($mime),
                            'libreoffice_supported' => $this->isSupportedLibreOffice($fileName, $mime),
                            'folder'                => false,
                            'checked'               => false,
                            'allowSaveToInbox' => $booEmailEnabled && $booCanAccessEmail && strtolower(FileTools::getFileExtension($fileName)) == 'eml'
                        );
                    }

                    if (count($arrItemInfo)) {
                        $strEval = '$arrResult';

                        $countParentFolders = count($arrParentFolders);
                        for ($i = 0; $i < $countParentFolders; $i++) {
                            $parentFolderMd5 = md5($arrParentFolders[$i] ?? '');

                            $strEval .= "['$parentFolderMd5']";

                            // Don't add the "children" key for the last item
                            if ($i != $countParentFolders - 1) {
                                $strEval .= "['children']";
                            }
                        }

                        if (!$this->getCloud()->isFolder($name)) {
                            // if this is file, only add to array, if we have access to parent folder
                            $strEval = "if (isset($strEval)) $strEval";
                        }

                        $strEval .= ($countParentFolders ? "['children']" : '') . "['$objectMd5'] = \$arrItemInfo;";

                        eval($strEval);
                    }
                }

                // Remove unnecessary info,
                // Sort by type and by time
                $arrResult = $this->prepareResult($arrResult, true);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $arrResult;
    }

    public function prepareResult($arrFilesFolders, $booDateInTimeFormat = false)
    {
        list($sort_by, $sort_where) = isset($_COOKIE['ys-sort_files']) ? explode('_', $_COOKIE['ys-sort_files'] ?? '') : array('date', 'desc');

        // Collect files and folders in separate arrays for later sorting
        $arrFiles = $arrFolders = array();
        foreach ($arrFilesFolders as $row) {
            if (isset($row['folder']) && $row['folder']) {
                $arrFolders[] = $row;
            } else {
                $arrFiles[] = $row;
            }
        }

        // Sort directories
        $arrDirNames = array();
        foreach ($arrFolders as $key => $row) {
            $arrDirNames[$key] = isset($row['filename']) ? strtolower($row['filename']) : '';
        }
        array_multisort($arrDirNames, SORT_ASC, $arrFolders);

        // Sort files
        $arrToSort = array();
        foreach ($arrFiles as $key => $row) {
            switch ($sort_by) {
                case 'date':
                    $arrToSort[$key] = isset($row['time']) ? ($booDateInTimeFormat ? $row['time'] : strtotime($row['time'])) : '';
                    break;

                case 'filesize':
                    $arrToSort[$key] = $row['filesize_bytes'] ?? '';
                    break;

                default:
                    $arrToSort[$key] = isset($row['filename']) ? strtolower($row['filename']) : '';
                    break;
            }
        }
        array_multisort($arrToSort, $sort_where == 'desc' ? SORT_DESC : SORT_ASC, $arrFiles);

        // Unite folders and files
        $arrFilesFolders = array_merge($arrFolders, $arrFiles);

        // Also do same things for inner folders
        foreach ($arrFilesFolders as $k => $val) {
            if (array_key_exists('children', $val) && is_array($val['children'])) {
                if (count($val['children'])) {
                    $arrFilesFolders[$k]['children'] = $this->prepareResult($val['children'], $booDateInTimeFormat);
                    $arrFilesFolders[$k]['expanded'] = false;
                } else {
                    // all folders will be collapsed,
                    // but this check is needed to avoid 'plus' icon for empty folders
                    $arrFilesFolders[$k]['expanded'] = true;
                }
            }
        }

        // Remove keys - needed for extjs
        return array_values($arrFilesFolders);
    }

    /**
     * Generate folder path from this folder name and all parent folder names
     *
     * @param int $folderId
     * @return string
     */
    public function getParentFolderPath($folderId)
    {
        $folderPath = '';

        if (!empty($folderId)) {
            $arrFolderInfo = $this->getFolders()->getFolderInfo($folderId);

            if (is_array($arrFolderInfo) && count($arrFolderInfo)) {
                $folderPath = $arrFolderInfo['folder_name'] . '/';

                if (!empty($arrFolderInfo['parent_id'])) {
                    $folderPath = $this->getParentFolderPath($arrFolderInfo['parent_id']) . $folderPath;
                }
            }
        }

        return $folderPath;
    }


    /**
     * Load default folders list and their access rights
     *
     * @param int $memberId
     * @param array $allowedTypes
     * @return array
     */
    public function getDefaultFoldersAccess($companyId, $memberId, $booIsClient, $booLocal, $arrRoles, $allowedTypes = array())
    {
        // Get path to Shared Docs folder
        $arrSharedDocsInfo = $this->getCompanySharedDocsPath($companyId, true, $booLocal);
        $pathToShared = $arrSharedDocsInfo[0] . '/' . $arrSharedDocsInfo[1];

        // Create "Shared Docs" folder if not exists
        if ($booLocal) {
            $this->createFTPDirectory($pathToShared);
        } else {
            $this->createCloudDirectory($pathToShared);
        }

        // Get member FTP files path
        $pathToFTPFolder = $this->getMemberFolder($companyId, $memberId, $booIsClient, $booLocal);

        // Get default folders
        $defaultFolders = $this->getFolders()->getCompanyFolders($companyId, 0, $allowedTypes, false, true);

        // Get default folder access
        $arrFoldersAccess = $this->getFolders()->getFolderAccess()->getFoldersAccessByRoles($arrRoles);

        // Group accesses by folders
        $arrFoldersAccessGrouped = array();
        foreach ($arrFoldersAccess as $fa) {
            $folderId = $fa['folder_id'];

            $arrFoldersAccessGrouped[$folderId] = isset($arrFoldersAccessGrouped[$folderId]) ? ($arrFoldersAccessGrouped[$folderId]['access'] == 'R' ? $fa : $arrFoldersAccessGrouped[$folderId]) : $fa;
        }

        //get default folders paths and access
        $arrFolders = array();
        foreach ($defaultFolders as $folder) {
            $access = '';
            $folderId = $folder['folder_id'];

            //get path
            if (in_array($folder['type'], array('SD', 'SDR'))) { //shared
                $path = $pathToShared;
                $folderName = $arrSharedDocsInfo[1];
                if ($folder['type'] == 'SD') { //is not root shared folder
                    $path .= '/' . $this->getParentFolderPath($folder['parent_id']) . $folder['folder_name'];
                    $folderName = $folder['folder_name'];
                }
            } else { //non shared
                if ($folder['type'] == 'D') {// My Docs
                    $access = 'RW';
                }
                $path = $pathToFTPFolder . '/' . $this->getParentFolderPath($folder['parent_id']) . $folder['folder_name'];
                $folderName = $folder['folder_name'];
            }

            if (empty($access)) {
                $access = isset($arrFoldersAccessGrouped[$folderId]) ? $arrFoldersAccessGrouped[$folderId]['access'] : false;
            }

            $arrFolders[] = array(
                'path' => $path,
                'access' => $access,
                'name' => $folderName
            );
        }

        return $arrFolders;
    }

    public function loadMemberFoldersAndFilesList($companyId, $memberId, $booLocal, $isClient, $arrDefaultFolders, $isSuperadmin = false)
    {
        // For superadmin automatically create folders which have RW access
        if ($isSuperadmin) {
            foreach ($arrDefaultFolders as $arrFolderInfo) {
                if ($arrFolderInfo['access'] == 'RW' && !empty($arrFolderInfo['path'])) {
                    if ($booLocal) {
                        $this->createFTPDirectory($arrFolderInfo['path']);
                    } else {
                        $this->getCloud()->createFolder($arrFolderInfo['path']);
                    }
                }
            }
        }

        // Get list of 'My docs'
        $memberFolderPath = $this->getMemberFolder($companyId, $memberId, $isClient, $booLocal);

        // We need to be sure that all default directories are in the place
        // $this->mkNewMemberFolders($memberId, $companyId, $booLocal, $isClient);

        if ($booLocal) {
            $this->createFTPDirectory($memberFolderPath);
            $userDataArr = $this->_loadFTPFoldersAndFilesList($memberFolderPath, $arrDefaultFolders, 'D', $memberId, false, '');
        } else {
            $userDataArr = $this->_loadMemberCloudFoldersAndFilesList($memberFolderPath, $arrDefaultFolders, 'D', $memberId, false, '');
        }

        return $userDataArr;
    }

    public function loadSharedFolderAndFiles($companyId, $memberId, $booLocal, $arrDefaultFolders, $arrThisUserNoAccessFolders, $noTopLevelAccess, $folderAccess)
    {
        //get Shared docs folder
        $arrSharedDocsInfo = $this->getCompanySharedDocsPath($companyId, true, $booLocal);
        $pathToShared = $arrSharedDocsInfo[0] . '/' . $arrSharedDocsInfo[1];

        //get shared workspace data
        if ($booLocal) {
            $this->createFTPDirectory($pathToShared);
            $list = $this->_loadFTPFoldersAndFilesList($pathToShared, $arrDefaultFolders, 'SD', $memberId, false, '');
        } else {
            $list = $this->_loadMemberCloudFoldersAndFilesList($pathToShared, $arrDefaultFolders, 'SD', $memberId, false, '');
        }
        $sharedDataArr = array($this->_generateFolderArray($pathToShared, $arrSharedDocsInfo[1], $list, 'SDR', $folderAccess, $arrDefaultFolders));


        // Don't return top level folders (and all their inner content) if this is not allowed by access rights based on the office
        if ($noTopLevelAccess && isset($sharedDataArr[0], $sharedDataArr[0]['children']) && !empty($sharedDataArr[0]['children'])) {
            $booResetKeys = false;
            foreach ($sharedDataArr[0]['children'] as $key => $arrSubFolderInfo) {
                if (!empty($arrSubFolderInfo['folder']) && in_array($arrSubFolderInfo['filename'], $arrThisUserNoAccessFolders)) {
                    unset($sharedDataArr[0]['children'][$key]);
                    $booResetKeys = true;
                }
            }

            // Reset keys if some folders were removed from the result
            if ($booResetKeys) {
                $sharedDataArr[0]['children'] = array_values($sharedDataArr[0]['children']);
            }
        }

        return $sharedDataArr;
    }

    /**
     * Move file or folder to another location
     * if current user has access to that client and has access to the folder
     *
     * @param $fromPath
     * @param $folderToPath
     * @return bool true if moved correctly, false otherwise
     */
    public function dragAndDropFTPFile($fromPath, $folderToPath)
    {
        $booSuccess = false;
        try {
            $fromPath     = str_replace('\\', '/', $fromPath ?? '');
            $folderToPath = str_replace('\\', '/', $folderToPath);
            if ($this->_auth->isCurrentUserCompanyStorageLocal()) {
                if (file_exists($fromPath) && file_exists($folderToPath)) {
                    if (is_file($fromPath)) {
                        $filename = FileTools::cleanupFileName(static::extractFileName($fromPath));
                        $newPath  = $folderToPath . '/' . $filename;
                    } else {
                        $arrExploded = explode('/', $fromPath);
                        $folderName  = array_pop($arrExploded);
                        $newPath     = $folderToPath . '/' . StringTools::stripInvisibleCharacters($folderName, false);
                    }

                    $booSuccess = $this->moveLocalFile($fromPath, $newPath);
                }
            } else {
                $folderToPath = $this->getCloud()->isFolder($folderToPath) ? $folderToPath : $folderToPath . '/';
                if ($this->getCloud()->isFolder($fromPath)) {
                    $newPath = $folderToPath . StringTools::stripInvisibleCharacters($this->getCloud()->getFolderNameByPath($fromPath), false);
                    $newPath = rtrim($newPath, '/') . '/';
                } else {
                    $newPath = $folderToPath . FileTools::cleanupFileName($this->getCloud()->getFileNameByPath($fromPath));
                }

                $booSuccess = $this->getCloud()->renameObject($fromPath, $newPath);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    public function moveLocalFile($fromPath, $toPath, $booUpdateTime = false)
    {
        $booSuccess = false;
        if (is_file($fromPath)) {
            $this->createFTPDirectory(dirname($toPath));

            @rename($fromPath, $toPath);

            if ($booUpdateTime) {
                @touch($toPath, filemtime($fromPath));
            }

            $booSuccess = is_file($toPath);
        }

        return $booSuccess;
    }

    /**
     * Get client FTP/Cloud path
     *
     * @param int $companyId
     * @param int $memberId
     * @param bool $booIsClient
     * @param null|bool $booIsLocal - if null - load settings from company settings, otherwise use provided value
     * @return string path to member's FTP/Cloud folder
     */
    public function getMemberFolder($companyId, $memberId, $booIsClient, $booIsLocal)
    {
        return $this->getCompanyPath($companyId, $booIsLocal) . '/' . ($booIsClient ? '.clients' : '.users') . '/' . $memberId;
    }

    /**
     * Load path to client's correspondence folder
     * @param int $member_id
     * @param int $companyId
     * @return string path
     */
    public function getClientCorrespondenceFTPFolder($companyId, $member_id, $booLocal)
    {
        $folderName = $this->getFolders()->getCompanyFolderName($companyId);
        return $this->getMemberFolder($companyId, $member_id, true, $booLocal) . '/' . ($folderName ?: 'Correspondence');
    }

    /**
     * Load path to client's invoice documents folder
     * @param int $memberId
     * @param int $companyId
     * @return string path
     */
    public function getClientInvoiceDocumentsFolder($memberId, $companyId, $booLocal)
    {
        return $this->getCompanyInvoiceDocumentsPath($companyId, $booLocal) . '/' . $memberId;
    }

    /**
     * Load path to client's xfdf folder
     * @param int $memberId
     * @param int $companyId
     * @return string path
     */
    public function getClientXFDFFTPFolder($memberId, $companyId = null)
    {
        if (is_null($companyId)) {
            /** @var Members $members */
            $members    = $this->_serviceContainer->get(Members::class);
            $memberInfo = $members->getMemberInfo($memberId);
            $companyId  = $memberInfo['company_id'];
        }

        return $this->getCompanyXFDFPath($companyId) . '/' . $memberId;
    }

    public function getClientXdpFilePath($memberId, $familyMemberId, $formId)
    {
        $fileName = sprintf('%s_%d.xdp', $familyMemberId, $formId);

        return $this->getClientXdpFolder($memberId) . '/' . $fileName;
    }

    public function getClientJsonFilePath($memberId, $familyMemberId, $formId)
    {
        if (preg_match('/^other\d*$/', $familyMemberId)) {
            $fileName = 'other' . '_' . $formId . '.json';
        } else {
            $fileName = sprintf('%s.json', $familyMemberId);
        }

        return $this->getClientJsonFolder($memberId) . '/' . $fileName;
    }

    /**
     * Load path to client's xdp folder
     * @param int $memberId
     * @param int $companyId
     * @return string path
     */
    public function getClientXdpFolder($memberId, $companyId = null)
    {
        if (is_null($companyId)) {
            /** @var Members $members */
            $members    = $this->_serviceContainer->get(Members::class);
            $memberInfo = $members->getMemberInfo($memberId);
            $companyId  = $memberInfo['company_id'];
        }

        return $this->getCompanyXdpPath($companyId) . '/' . $memberId;
    }

    /**
     * Load path to client's json folder
     *
     * @param int $memberId
     * @param int $companyId
     * @return string path
     */
    public function getClientJsonFolder($memberId, $companyId = null)
    {
        if (is_null($companyId)) {
            /** @var Members $members */
            $members    = $this->_serviceContainer->get(Members::class);
            $memberInfo = $members->getMemberInfo($memberId);
            $companyId  = $memberInfo['company_id'];
        }

        return $this->getCompanyJsonPath($companyId) . '/' . $memberId;
    }

    public function getClientBarcodedPDFFilePath($member_id, $revisionId)
    {
        $fileName = sprintf('%d.pdf', $revisionId);
        $pathToPdfDir = $this->createFTPDirectory($this->getClientBarcodedPDFFolder($member_id));

        return $pathToPdfDir . '/' . $fileName;
    }

    /**
     * Load path to client's Barcoded PDF folder
     * @param int $memberId
     * @param int $companyId
     * @return string path
     */
    public function getClientBarcodedPDFFolder($memberId, $companyId = null)
    {
        if (is_null($companyId)) {
            /** @var Members $members */
            $members    = $this->_serviceContainer->get(Members::class);
            $memberInfo = $members->getMemberInfo($memberId);
            $companyId  = $memberInfo['company_id'];
        }

        return $this->getCompanyBarcodedPDFPath($companyId) . '/' . $memberId;
    }

    public function getCompanyDefaultFilesPath($companyId)
    {
        return $this->getCompanyPath($companyId) . '/' . $this->_dirConfig['company_default_files'];
    }

    public function getClientSubmissionsFolder($companyId, $memberId, $booLocal)
    {
        $folderName = $this->getFolders()->getCompanyFolderName($companyId, 'F');
        return $this->getMemberFolder($companyId, $memberId, true, $booLocal) . '/' . ($folderName ?: 'Submissions');
    }

    public function getMemberMyDocsFTPFolder($companyId, $memberId, $isClient, $booLocal, $booReturnSeparate = false)
    {
        $folderName = $this->getFolders()->getCompanyFolderName($companyId, 'D');
        if ($booReturnSeparate) {
            return array($this->getMemberFolder($companyId, $memberId, $isClient, $booLocal), $folderName ?: 'My Documents');
        } else {
            return $this->getMemberFolder($companyId, $memberId, $isClient, $booLocal) . '/' . ($folderName ?: 'My Documents');
        }
    }

    public function getCompanyInvoiceDocumentsPath($companyId, $booLocal)
    {
        return $this->getCompanyPath($companyId, $booLocal) . '/' . $this->_dirConfig['company_invoice_documents'];
    }

    public function getCompanyDependantsPath($companyId, $booLocal)
    {
        return $this->getCompanyPath($companyId, $booLocal) . '/' . $this->_dirConfig['company_dependants'];
    }

    public function getCompanyDependantsChecklistPath($companyId, $memberId, $dependentId, $booLocal)
    {
        return $this->getCompanyDependantsPath($companyId, $booLocal) . '/' . $memberId . '/' . $dependentId . '/checklist';
    }

    public function getCompanyXFDFPath($companyId)
    {
        return $this->getCompanyPath($companyId) . '/' . $this->_dirConfig['company_xfdf'];
    }

    public function getCompanyXdpPath($companyId)
    {
        return $this->getCompanyPath($companyId) . '/' . $this->_dirConfig['company_xdp'];
    }

    public function getCompanyJsonPath($companyId)
    {
        return $this->getCompanyPath($companyId) . '/' . $this->_dirConfig['company_json'];
    }

    public function getCompanyBarcodedPDFPath($companyId)
    {
        return $this->getCompanyPath($companyId) . '/' . $this->_dirConfig['company_barcoded_pdf'];
    }

    public function getCompanyTemplateAttachmentsPath($companyId, $booLocal)
    {
        return $this->getCompanyPath($companyId, $booLocal) . '/' . $this->_dirConfig['template_attachments'];
    }

    public function getClientNoteAttachmentsPath($companyId, $memberId, $booLocal)
    {
        return $this->getCompanyPath($companyId, $booLocal) . '/' . $this->_dirConfig['client_notes_attachments'] . '/' . $memberId;
    }

    public function getProspectNoteAttachmentsPath($companyId, $prospectId, $booLocal)
    {
        return $this->getCompanyPath($companyId, $booLocal) . '/' . $this->_dirConfig['prospect_notes_attachments'] . '/' . $prospectId;
    }

    /**
     * Load path to the PUA record
     *
     * @param int $companyId
     * @param bool $booLocal
     * @param int $puaRecordId
     * @return string
     */
    public function getPuaRecordPath($companyId, $booLocal, $puaRecordId)
    {
        return $this->getCompanyPath($companyId, $booLocal) . '/.users_pua/' . (int)$puaRecordId;
    }

    /**
     * Load company path (where company files/folders are stored)
     *
     * @param int $companyId
     * @param bool|true $booLocal - true, if company storage location is local (OR specific folder is saved locally only)
     * @return string
     */
    public function getCompanyPath($companyId, $booLocal = true)
    {
        $root = $booLocal ? $this->_dirConfig['companyfiles'] : '';

        return $root . '/' . $companyId;
    }

    public function getCompanySharedDocsPath($companyId, $booReturnSeparate, $booLocal)
    {
        $folderName = $this->getFolders()->getCompanyFolderName($companyId, 'SDR');

        if ($booReturnSeparate) {
            return array($this->getCompanyPath($companyId, $booLocal), $folderName ?: 'Shared Workspace');
        } else {
            return $this->getCompanyPath($companyId, $booLocal) . '/' . ($folderName ?: 'Shared Workspace');
        }
    }

    public function getCompanyLogoFolderPath($companyId, $booLocal = null)
    {
        if (is_null($booLocal)) {
            $booLocal = $this->_auth->isCurrentUserCompanyStorageLocal();
        }

        return $this->getCompanyPath($companyId, $booLocal) . '/' . $this->_dirConfig['company_logo'];
    }

    public function getFolderBlankPath()
    {
        $root = $this->_auth->isCurrentUserCompanyStorageLocal() ? $this->_dirConfig['companyfiles'] : '';

        return $root . $this->_dirConfig['blankfiles'];
    }

    public function getAgentLogoPath($companyId, $booLocal)
    {
        return $this->getCompanyPath($companyId, $booLocal) . '/' . $this->_dirConfig['agent_logo'];
    }

    public function getCompanyLetterheadsPath($companyId, $booLocal)
    {
        return $this->getCompanyPath($companyId, $booLocal) . '/' . $this->_dirConfig['letterheads'];
    }

    public function getCompanyLetterTemplatesPath($companyId, $booLocal)
    {
        return $this->getCompanyPath($companyId, $booLocal) . '/' . $this->_dirConfig['letter_templates'];
    }

    public function getClientsXLSPath($companyId)
    {
        $booLocal = $this->_auth->isCurrentUserCompanyStorageLocal();

        return $this->getCompanyPath($companyId, $booLocal) . '/' . $this->_dirConfig['clients_xls'];
    }

    public function getBcpnpXLSPath($companyId)
    {
        $booLocal = $this->_auth->isCurrentUserCompanyStorageLocal();

        return $this->getCompanyPath($companyId, $booLocal) . '/' . $this->_dirConfig['bcpnp_xls'];
    }

    public function getReconciliationReportsPath($booUseLocation = null)
    {
        switch ($booUseLocation) {
            case 'remote':
                $root = '';
                break;

            case 'local':
                $root = $this->_dirConfig['companyfiles'];
                break;

            default:
                $root = $this->_auth->isCurrentUserCompanyStorageLocal() ? $this->_dirConfig['companyfiles'] : '';
                break;
        }

        return $root . $this->_dirConfig['reconciliation_reports'];
    }

    /**
     * Get path to the editor images
     *
     * @param int $companyId
     * @param bool $booIsLocal
     * @return string
     */
    public function getHTMLEditorImagesPath($companyId, $booIsLocal)
    {
        $path = '/' . $this->_config['html_editor']['location'] . '/' . $companyId;
        if ($booIsLocal) {
            $path = 'public' . $path;
        }

        return $path;
    }

    /**
     * Get images url that will be used in different places:
     * - In the Froala editor if we need to send a request to the server
     * - After image was uploaded - use the generated url
     * - Check if a file can be deleted
     *
     * @param $baseUrl
     * @param $companyId
     * @param string $fileName
     * @return string
     */
    public function getHTMLEditorImagesUrl($baseUrl, $companyId, $fileName = '')
    {
        // We don't need the 'public' dir in the path
        $pathToRemoteDir = $this->getHTMLEditorImagesPath($companyId, false);

        $booIsLocal = $this->_config['html_editor']['storage'] === 'local';
        if ($booIsLocal) {
            $fileUrl = $baseUrl . '/' . trim($pathToRemoteDir, '/') . '/' . $fileName;
        } else {
            // Make sure that we'll have a full url (with https protocol) for S3 links
            $fileUrl = 'https:' . $this->getCloud(true)->generateFilePublicLink($pathToRemoteDir . '/' . $fileName, !empty($fileName));
        }

        return $fileUrl;
    }

    public static function getUniquesPDFLogoPath()
    {
        return 'public/images/uniques-gray.jpg';
    }

    public function mkNewCompanyFolders($companyId)
    {
        $sharedPath = $this->getCompanySharedDocsPath($companyId, false, $this->_auth->isCurrentUserCompanyStorageLocal());
        if ($this->_auth->isCurrentUserCompanyStorageLocal()) {
            $this->createFTPDirectory($sharedPath);
        } else {
            $this->createCloudDirectory($sharedPath);
        }

        $this->createFTPDirectory($this->getCompanyXFDFPath($companyId));
    }

    public function getPathToNewMemberFolder($arrFolders, $folderId, $path)
    {
        $pathToFolder = '';
        foreach ($arrFolders as $folder) {
            if ($folder['folder_id'] == $folderId) {
                $folderName = StringTools::stripInvisibleCharacters($folder['folder_name'], false);
                if ($folder['parent_id'] == 0) {
                    $pathToFolder = $path . '/' . $folderName;
                } else {
                    $pathToFolder = $this->getPathToNewMemberFolder($arrFolders, $folder['parent_id'], $path) . '/' . $folderName;
                }
            }
        }

        return $pathToFolder;
    }

    /**
     * Create default folders for a client or admin/user
     *
     * @param int $memberId
     * @param int $companyId
     * @param bool $booLocal
     * @param bool $booClient
     * @return void
     */
    public function mkNewMemberFolders($memberId, $companyId, $booLocal, $booClient = true)
    {
        // This works for cyrillic (e.g. Ukrainian) names
        // $path = iconv("UTF-8", "cp1251", $path);
        $path = $this->getMemberFolder($companyId, $memberId, $booClient, $booLocal);
        $arrPathCreate = array($path);

        // Folders list is different for clients and admins/users
        if ($booClient) {
            $arrFolderTypes = array('CD', 'F', 'C');
        } else {
            $arrFolderTypes = array('D');
        }

        $arrFolders = $this->getFolders()->getCompanyFolders($companyId, 0, $arrFolderTypes, false, true);

        $arrDefaultFiles = array();
        if (is_array($arrFolders)) {
            foreach ($arrFolders as $folder) {
                $pathToFolder = $this->getPathToNewMemberFolder($arrFolders, $folder['folder_id'], $path);
                $arrPathCreate[] = $pathToFolder;

                $arrFiles = $this->getCompanyDefaultFiles($companyId, $folder['folder_id']);
                foreach ($arrFiles as $arrFileInfo) {
                    $arrDefaultFiles[$pathToFolder][] = array(
                        'old_path' => $arrFileInfo['file_path'],
                        'new_path' => $pathToFolder . '/' . FileTools::cleanupFileName(static::extractFileName($arrFileInfo['file_path'])),
                    );
                }
            }
        }

        foreach ($arrPathCreate as $strPath) {
            if ($booLocal) {
                $this->createFTPDirectory($strPath);
            } else {
                $this->createCloudDirectory($strPath);
            }

            // Create/upload default files for this folder
            if (array_key_exists($strPath, $arrDefaultFiles)) {
                foreach ($arrDefaultFiles[$strPath] as $arrFile) {
                    $this->copyFile($arrFile['old_path'], $arrFile['new_path'], $booLocal);
                }
            }
        }
    }

    public function copyFile($oldPath, $newPath, $booLocal, $booImagesS3 = false)
    {
        $booSuccess = false;
        if ($oldPath != $newPath) {
            if ($booLocal) {
                if (is_file($oldPath)) {
                    copy($oldPath, $newPath);
                    touch($newPath, filemtime($oldPath));
                    $booSuccess = is_file($newPath);
                }
            } elseif (is_file($oldPath)) {
                $booSuccess = $this->getCloud($booImagesS3)->uploadFile($oldPath, $newPath);
            } else {
                $booSuccess = $this->getCloud($booImagesS3)->copyObject($oldPath, $newPath);
            }
        }

        return $booSuccess;
    }

    public function createFTPDirectory($dir)
    {
        try {
            $booCreated = true;
            if (!is_dir($dir)) {
                $booCreated = mkdir($dir, $this->_config['security']['new_directories_mode'], true);
            }

            return $booCreated ? $dir : false;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'files');

            return false;
        }
    }

    public function createCloudDirectory($dir)
    {
        try {
            $booResult = $this->getCloud()->createFolder($dir);

            return $booResult ? $dir : false;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'files');

            return false;
        }
    }

    public function renameFolder($oldPath, $newPath)
    {
        try {
            if (file_exists($oldPath)) {
                $booSuccess = rename($oldPath, $newPath);
            } else {
                $booSuccess = $this->createCloudDirectory($newPath);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'files');
            $booSuccess = false;
        }

        return $booSuccess;
    }

    public function moveCloudFolder($fromPath, $newPath)
    {
        $fromPath = rtrim($fromPath ?? '', '/') . '/';
        $newPath = rtrim($newPath ?? '', '/') . '/';

        return $this->getCloud()->renameObject($fromPath, $newPath);
    }

    public function moveFTPFolder($src, $dst)
    {
        $result = $this->copyFTPFolder($src, $dst);
        if ($result) {
            $result = $this->deleteFTPFolder($src);
        }

        return $result;
    }

    public function deleteFolder($dirPath, $booLocal)
    {
        $booSuccess = false;
        if (!empty($dirPath)) {
            $booSuccess = $booLocal ? $this->deleteFTPFolder($dirPath) : $this->getCloud()->deleteObject(rtrim($dirPath, '/') . '/');
        }

        return $booSuccess;
    }

    /**
     * Delete a file from S3 or from a local storage
     *
     * @param string $path
     * @param bool $local
     * @param bool $booImagesS3
     * @return bool
     */
    public function deleteFile($path, $local = true, $booImagesS3 = false)
    {
        $result = false;

        if (!empty($path)) {
            if ($local) {
                if (!file_exists($path)) {
                    $result = true;
                } else {
                    if (is_file($path)) {
                        $result = unlink($path);
                    }
                }
            } else {
                $oCloud = $this->getCloud($booImagesS3);
                if (!$oCloud->isFolder($path)) {
                    if ($oCloud->checkObjectExists($path)) {
                        $result = $oCloud->deleteObject($path);
                    } else {
                        $result = true;
                    }
                }
            }
        }

        return $result;
    }

    public function deleteFTPFolder($dirName)
    {
        $booSuccess = false;
        try {
            if (is_dir($dirName)) {
                $dir_handle = opendir($dirName);

                if (!$dir_handle) {
                    return false;
                }
                while (($file = readdir($dir_handle)) !== false) {
                    if ($file != '.' && $file != '..') {
                        $path = realpath($dirName . '/' . $file);
                        if (!is_dir($path)) {
                            //remove file
                            if (file_exists($path)) {
                                unlink($path);
                            }
                        } else {
                            $this->deleteFTPFolder($path);
                        }
                    }
                }

                closedir($dir_handle);
                $dirName = realpath($dirName);

                //remove folder
                if (file_exists($dirName)) {
                    $booSuccess = rmdir($dirName);
                } else {
                    $booSuccess = true;
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'files');
        }

        return $booSuccess;
    }

    public function copyFTPFolder($src, $dst)
    {
        try {
            if ($dir = @opendir($src)) {
                $this->createFTPDirectory($dst);
                while (false !== ($file = readdir($dir))) {
                    if (($file != '.') && ($file != '..')) {
                        if (is_dir($src . '/' . $file)) {
                            $this->moveFTPFolder($src . '/' . $file, $dst . '/' . $file);
                        } else {
                            $srcFile = $src . '/' . $file;
                            $destinationFile = $dst . '/' . $file;

                            copy($srcFile, $destinationFile);
                            @touch($destinationFile, filemtime($srcFile));
                        }
                    }
                }
                closedir($dir);
            }

            return $src;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'files');

            return false;
        }
    }

    /**
     * Get icon name by file mime type
     *
     * @param string $mime
     * @return string
     */
    public function getFileIcon($mime)
    {
        if ($this->isImage($mime)) {
            $strIcon = 'lar la-file-image';
        } elseif ($this->isPDF($mime)) {
            $strIcon = 'lar la-file-pdf';
        } elseif ($this->isZipArchive($mime)) {
            $strIcon = 'las la-file-archive';
        } else {
            switch ($mime) {
                case 'message/rfc822':
                    $strIcon = 'lar la-envelope';
                    break;

                case 'application/msword':
                case 'application/vnd.oasis.opendocument.text':
                case 'application/vnd.sun.xml.writer':
                case 'application/vnd.openxmlformats-':
                case 'officedocument.wordprocessingml.document':
                case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                case 'application/rtf':
                    $strIcon = 'lar la-file-word';
                    break;

                case 'text/plain':
                case 'text/comma-separated-values':
                case 'application/csv':
                case 'text/anytext':
                    $strIcon = 'lar la-file-alt';
                    break;

                case 'text/html':
                    $strIcon = 'lar la-file-code';
                    break;

                case 'text/csv':
                    $strIcon = 'las la-file-csv';
                    break;

                case 'application/vnd.ms-excel':
                case 'application/vnd.oasis.opendocument.spreadsheet':
                    $strIcon = 'lar la-file-excel';
                    break;

                default :
                    $strIcon = 'lar la-file';
            }
        }

        return $strIcon;
    }

    /**
     * Generate Zoho url to edit a specific file
     *
     * @param $fileName
     * @param $filePath
     * @param $fileFormat
     * @param $fileDownloadLink
     * @param $fileSaveLink
     * @param $strEncCheckParams
     * @return array
     */
    public function getZohoDocumentUrl($fileName, $filePath, $fileFormat, $fileDownloadLink, $fileSaveLink, $strEncCheckParams)
    {
        $strError        = '';
        $zohoDocumentUrl = '';

        /** @var ZohoKeys $zohoKeys */
        $zohoKeys = $this->_serviceContainer->get(ZohoKeys::class);
        if (!$zohoKeys->isZohoEnabled()) {
            $strError = $this->_tr->translate('Communication with Zoho is turned off in the config.');
        }

        $apiKey = '';
        if (empty($strError)) {
            $apiKey = $zohoKeys->getActiveApiKey();
            if (empty($apiKey)) {
                $strError = $this->_tr->translate('No correct/active Zoho API key was found.');
            }
        }

        $zohoUrl     = '';
        $zohoService = '';
        if (empty($strError)) {
            list($zohoUrl, $zohoService) = $this->getZohoUrlByFormat($fileFormat);
            if (empty($zohoUrl)) {
                $strError = $this->_tr->translate('Unsupported file format.');
            }
        }

        if (empty($strError)) {
            $settings = $this->_config['zoho'];

            $arrPost = [
                'apikey' => $apiKey,
                'url'    => $fileDownloadLink,
            ];

            // This is an optional parameter, but Zoho glitches without it...
            // $arrPost['document_info'] = Json::encode([
            //     'document_name' => $fileName,
            //     'document_id'   => ''
            // ]);

            if ($zohoService != 'zoho_show') {
                $arrPost['callback_settings'] = Json::encode([
                    'save_format' => $fileFormat,
                    'save_url'    => $fileSaveLink,


                    'save_url_params' => [
                        'content'   => '$content',
                        'extension' => '$format',
                        'filename'  => '$filename',
                        'id'        => $filePath,
                        'enc'       => $strEncCheckParams,
                    ]
                ]);

                $arrPost['user_info'] = Json::encode([
                    'user_id'      => $this->_auth->getCurrentUserId(),
                    'display_name' => $this->_auth->getIdentity()->full_name
                ]);
            }


            // setup cURL request
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
            curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $arrPost);

            // Don't check for SSL certificates (e.g. self signed)
            if (empty($settings['check_ssl'])) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            }

            // specified endpoint
            curl_setopt($ch, CURLOPT_URL, $zohoUrl);

            // execute cURL request
            $r = curl_exec($ch);
            if ($z = curl_error($ch)) {
                $errorNumber = curl_errno($ch);
                if ($errorNumber == 28) {
                    $strError = $this->_tr->translate('Operation timeout. The specified time-out period was reached according to the conditions.');
                } else {
                    $strError = $this->_tr->translate('Internal error');
                }
                $this->_log->debugErrorToFile('', 'Curl error: ' . $z . ' Url: ' . $zohoUrl, 'zoho');
            } else {
                try {
                    $arrDecodedResult = Json::decode($r, Json::TYPE_ARRAY);
                } catch (Exception $e) {
                }

                $booSavedToLog  = false;
                $documentUrlKey = $zohoService == 'zoho_show' ? 'preview_url' : 'document_url';
                if (isset($arrDecodedResult[$documentUrlKey])) {
                    $zohoDocumentUrl = $arrDecodedResult[$documentUrlKey];
                } elseif (isset($arrDecodedResult['code']) && isset($arrDecodedResult['message'])) {
                    $strError = $arrDecodedResult['message'];
                } else {
                    $strError = $this->_tr->translate('Internal error');

                    $details = $this->_tr->translate('URL: ') . $zohoUrl . PHP_EOL;
                    $details .= $this->_tr->translate('Params: ') . print_r($arrPost, true) . PHP_EOL;
                    $details .= $this->_tr->translate('Response: ') . print_r($r, true) . PHP_EOL;
                    $this->_log->debugErrorToFile('', $details, 'zoho');
                    $booSavedToLog = true;
                }

                if (!empty($settings['log_enabled']) && !$booSavedToLog) {
                    $details = $this->_tr->translate('URL: ') . $zohoUrl . PHP_EOL;
                    $details .= $this->_tr->translate('Params: ') . print_r($arrPost, true) . PHP_EOL;
                    $details .= $this->_tr->translate('Response: ') . print_r($r, true) . PHP_EOL;

                    $this->_log->debugToFile($details, 1, 2, 'zoho-' . date('Y_m_d') . '.log');
                }
            }

            curl_close($ch);
        }


        return array($strError, $zohoDocumentUrl);
    }

    /**
     * Get Zoho Url for a provided file format (extension)
     *
     * @param string $fileFormat
     * @return array
     * string zoho url: empty, if not supported by Zoho otherwise a correct Url
     * string zoho service: empty or one of "zoho_writer", "zoho_sheet", "zoho_show"
     */
    public function getZohoUrlByFormat($fileFormat)
    {
        $zohoUrl     = '';
        $zohoService = '';

        if (in_array($fileFormat, ['doc', 'docx', 'rtf', 'odt', 'htm', 'html', 'txt'])) {
            $zohoUrl     = 'https://writer.zoho.com/writer/officeapi/v1/documents';
            $zohoService = 'zoho_writer';
        } elseif (in_array($fileFormat, ['xls', 'xlsx', 'ods', 'csv', 'tsv'])) {
            $zohoUrl     = 'https://sheet.zoho.com/sheet/officeapi/v1/spreadsheet';
            $zohoService = 'zoho_sheet';
        } elseif (in_array($fileFormat, ['ppt', 'pptx', 'pps', 'ppsx', 'odp', 'sxi'])) {
            // We don't support Zoho View in the Edit mode -
            // they cannot pass the data/params in our accepted format (for saving)
            // $zohoUrl = 'https://show.zoho.com/show/officeapi/v1/presentation';

            // So, we'll use the "Preview" mode
            $zohoUrl     = 'https://show.zoho.com/show/officeapi/v1/presentation/preview';
            $zohoService = 'zoho_show';
        }

        return array($zohoUrl, $zohoService);
    }


    /**
     * Check if file is supported by Zoho (by file format)
     * @Note The check will be done only if Zoho support is enabled in the config file
     *
     * @param $fileFormat
     * @return bool true if supported, otherwise false
     */
    public function isSupportedZoho($fileFormat)
    {
        /** @var ZohoKeys $zohoKeys */
        $zohoKeys = $this->_serviceContainer->get(ZohoKeys::class);

        $booSupported = false;
        if ($zohoKeys->isZohoEnabled()) {
            list($zohoUrl,) = $this->getZohoUrlByFormat($fileFormat);
            $booSupported = !empty($zohoUrl);
        }

        return $booSupported;
    }

    public function isSupportedLibreOffice($fileName, $mimeType)
    {
        //LibreOffice allowed types
        $mime = array(
            'application/msword',
            'application/vnd.ms-excel',
            'application/vnd.ms-powerpoint',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.presentation',
            'application/vnd.sun.xml.writer',
            'application/vnd.sun.xml.calc',
            'application/vnd.sun.xml.impress',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/rtf',
            'text/rtf',
            'text/plain',
            'text/comma-separated-values',
            'text/csv',
            'application/csv',
            'text/anytext'
        );

        $formats = array('doc', 'docx', 'xls', 'xlsx', 'ppt', 'pps', 'odt', 'ods', 'odp', 'sxw', 'sxc', 'sxi', 'txt', 'rtf', 'csv');
        $ext     = strtolower(FileTools::getFileExtension($fileName));

        return in_array($mimeType, $mime) && in_array($ext, $formats);
    }

    public function isEmail($mimeType)
    {
        return $mimeType == 'message/rfc822';
    }

    public function isPDF($mimeType)
    {
        return ($mimeType == 'application/pdf');
    }

    /**
     * Check if file's extension is in the whitelist - allowed for uploading
     *
     * @param string $fileExtension
     * @return bool true if in the whitelist
     */
    public function isFileFromWhiteList($fileExtension)
    {
        $arrWhiteListExtensions = explode(',', $this->_config['site_version']['whitelist_files_for_uploading'] ?? '');
        $arrWhiteListExtensions = array_map('strtolower', $arrWhiteListExtensions);
        $arrWhiteListExtensions = array_map('trim', $arrWhiteListExtensions);

        return in_array(strtolower($fileExtension), $arrWhiteListExtensions);
    }

    public function isImage($mimeType)
    {
        $mime = array(
            'image',
            'image/jpeg',
            'image/jpg',
            'image/jp_',
            'application/jpg',
            'application/x-jpg',
            'image/pjpeg',
            'image/pipeg',
            'image/vnd.swiftview-jpeg',
            'image/x-xbitmap',
            'image/bmp',
            'image/x-bmp',
            'image/x-bitmap',
            'image/x-xbitmap',
            'image/x-win-bitmap',
            'image/x-windows-bmp',
            'image/ms-bmp',
            'image/x-ms-bmp',
            'application/bmp',
            'application/x-bmp',
            'application/x-win-bitmap',
            'application/preview',
            'image/gif',
            'image/gi_',
            'image/png',
            'application/png',
            'application/x-png'
        );

        return in_array($mimeType, $mime);
    }

    /**
     * Extract file name from file path
     *
     * @param string $filePath
     * @return string
     */
    public static function extractFileName($filePath)
    {
        $arrFilePath = explode('/', $filePath);

        return ((is_array($arrFilePath) && count($arrFilePath) > 1) ? $arrFilePath[count($arrFilePath) - 1] : '');
    }

    public function getPathToClientFiles($companyId, $memberId, $booIsLocal)
    {
        return $this->getMemberFolder($companyId, $memberId, true, $booIsLocal) . '/' . '.profile_files';
    }

    public function getPathToClientImages($companyId, $memberId, $booIsLocal)
    {
        return $this->getMemberFolder($companyId, $memberId, true, $booIsLocal) . '/' . '.images';
    }

    public function duplicateCompanyLetterTemplate($companyId, $templateId, $newTemplateId, $booLocal)
    {
        $strError = '';

        try {
            if (empty($strError)) {
                $companyLetterTemplatesPath = $this->getCompanyLetterTemplatesPath($companyId, $booLocal) ?? '';

                if ($booLocal) {
                    $pathToFile = rtrim($companyLetterTemplatesPath, '/') . '/' . $templateId;
                    $pathToNewFile = rtrim($companyLetterTemplatesPath, '/') . '/' . $newTemplateId;
                } else {
                    $companyLetterTemplatesPath = $this->getCloud()->isFolder($companyLetterTemplatesPath) ? $companyLetterTemplatesPath : $companyLetterTemplatesPath . '/';
                    $pathToFile = $this->getCloud()->preparePath($companyLetterTemplatesPath) . $templateId;
                    $pathToNewFile = $this->getCloud()->preparePath($companyLetterTemplatesPath) . $newTemplateId;
                }

                if (!$this->copyFile($pathToFile, $pathToNewFile, $booLocal)) {
                    $strError = $this->_tr->translate('File was not uploaded.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $strError;
    }

    public function saveClientImage($companyId, $memberId, $booLocal, $tempFileName, $size)
    {
        return $this->saveImage($this->getPathToClientImages($companyId, $memberId, $booLocal), $tempFileName, $tempFileName, $size, $booLocal);
    }

    public function getClientsXLSFilesList($companyId, $booImportBcpnp = false)
    {
        $select = (new Select())
            ->from('clients_import')
            ->columns(array('id', 'file_name'))
            ->where(
                [
                    'company_id' => $companyId,
                    'is_bcpnp_import' => $booImportBcpnp ? 'Y' : 'N'
                ]
            );

        $result = $this->_db2->fetchAll($select);
        $ret = [];
        foreach ($result as $row) {
            $ret[$row['id']] = $row['file_name'];
        }

        return empty($ret) ? array() : $ret;
    }

    public function deleteClientsXLSFile($fileId, $booImportBcpnp = false)
    {
        try {
            $companyId = $this->_auth->getCurrentUserCompanyId();
            $pathToDir = $booImportBcpnp ? $this->getBcpnpXLSPath($companyId) : $this->getClientsXLSPath($companyId);
            $path      = $pathToDir . '/' . $fileId;

            $booSuccess = $this->deleteFile($path, $this->_auth->isCurrentUserCompanyStorageLocal());
            if ($booSuccess) {
                $this->_db2->delete('clients_import', ['id' => $fileId]);
            }
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Get xls file, check it and save to the correct location
     *
     * @param int $companyId
     * @param int $memberId
     * @param string $tempFile
     * @param bool $booImportBcpnp
     * @return array
     */
    public function saveClientsXLS($companyId, $memberId, $tempFile, $booImportBcpnp = false)
    {
        $strError = '';

        try {
            if (!isset($_FILES[$tempFile]) || empty($_FILES[$tempFile]['name'])) {
                $strError = $this->_tr->translate('Nothing to save');
            }

            $pathToDir          = $booImportBcpnp ? $this->getBcpnpXLSPath($companyId) : $this->getClientsXLSPath($companyId);
            $tempFileLocation   = $this->_config['directory']['tmp'];
            $fullPathToTempFile = '';

            $fileInput   = null;
            $xlsFileId   = 0;
            $xlsFileName = '';
            if (empty($strError)) {
                $fileInput = new FileInput('file');
                $fileInput->getValidatorChain()
                    ->attach(new Extension(array('xls', 'xlsx')));
                $fileInput->getFilterChain()
                    ->attach(
                        new RenameUpload(
                            [
                                'target'               => $tempFileLocation,
                                'randomize'            => false,
                                'overwrite'            => true,
                                'use_upload_name'      => true,
                                'use_upload_extension' => true
                            ]
                        )
                    );
                $fileInput->setBreakOnFailure(false);
                $fileInput->setValue($_FILES[$tempFile]);
                $xls         = $fileInput->getValue();
                $xlsFileName = $xls['name'];

                $select = (new Select())
                    ->from('clients_import')
                    ->columns(['id'])
                    ->where(
                        [
                            'company_id' => $companyId,
                            'is_bcpnp_import'=> $booImportBcpnp ? 'Y' : 'N',
                            'file_name' => $xlsFileName
                        ]
                    );

                $fileExistsInDb = $this->_db2->fetchCol($select);

                if (is_array($fileExistsInDb) && !empty($fileExistsInDb)) {
                    $strError = sprintf($this->_tr->translate('File with name <i>%s</i> already exists'), $xlsFileName);
                }
            }

            // allow only .xls files (not .xlsx)
            //if (empty($strError) && !(strtolower($this->getFileExtension($xlsFileName)) == 'xls' || strtolower($this->getFileExtension($xlsFileName)) == 'xlsx')) {
            //    $strError = $this->_tr->translate('Only .xls or .xlsx files allowed');
            //}

            if (empty($strError) && !in_array(strtolower(FileTools::getFileExtension($xlsFileName)), ['xlsx', 'xls'])) {
                $strError = $this->_tr->translate('Only .xls and .xlsx files allowed');
            }

            if (empty($strError)) {
                $xlsFileId = $this->_db2->insert(
                    'clients_import',
                    [
                        'company_id'      => $companyId,
                        'file_name'       => $xlsFileName,
                        'creator_id'      => $memberId,
                        'is_bcpnp_import' => $booImportBcpnp ? 'Y' : 'N'
                    ]
                );

                $fullPathToFile     = $pathToDir . '/' . $xlsFileId;
                $fullPathToTempFile = $tempFileLocation . '/' . $xlsFileId;

                $fileInput->setFilterChain(new FilterChain())
                    ->getFilterChain()
                    ->attach(
                        new RenameUpload(
                            [
                                'target'               => $fullPathToTempFile,
                                'randomize'            => false,
                                'overwrite'            => true,
                                'use_upload_name'      => false,
                                'use_upload_extension' => false
                            ]
                        )
                    );

                // receive the file
                if (!$fileInput->isValid() || !$fileInput->getValue()) {
                    $strError = $this->_tr->translate('Cannot receive file');
                } else {
                    // Move temp file to the correct location
                    $booSuccess = false;
                    if ($this->_auth->isCurrentUserCompanyStorageLocal()) {
                        $dir = dirname($fullPathToFile);
                        if (!file_exists($dir) && !$this->createFTPDirectory($dir)) {
                            $strError = $this->_tr->translate('Cannot create directory.');
                        }

                        if (empty($strError) && rename($fullPathToTempFile, $fullPathToFile)) {
                            $booSuccess = file_exists($fullPathToFile);
                        }
                    } else {
                        $booSuccess = $this->getCloud()->uploadFile($fullPathToTempFile, $fullPathToFile);
                    }

                    if (!$booSuccess && empty($strError)) {
                        $strError = $this->_tr->translate('File was not uploaded.');
                    }
                }
            }

            if (!empty($strError)) {
                $this->_db2->delete('clients_import', ['id' => (int)$xlsFileId]);
            } elseif (!empty($fullPathToTempFile) && file_exists($fullPathToTempFile)) {
                unlink($fullPathToTempFile);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'error'  => !empty($strError),
            'result' => !empty($strError) ? $strError : $this->_tr->translate('File uploaded')
        );
    }


    public function saveLetterheadImage($fileNewPath, $name, $letterheadFileId, $booLocal = false)
    {
        $strError = '';

        try {
            $tmpName = $_FILES[$name]['tmp_name'];
            if ($booLocal) {
                $this->createFTPDirectory($fileNewPath);

                $filePath = $fileNewPath . '/' . $letterheadFileId;
                if (!move_uploaded_file($tmpName, $filePath)) {
                    $strError = $this->_tr->translate('File was not uploaded.');
                }
            } else {
                $fileNewPath = $this->getCloud()->isFolder($fileNewPath) ? $fileNewPath : $fileNewPath . '/';
                $filePath = $this->getCloud()->preparePath($fileNewPath) . $letterheadFileId;
                if (!$this->getCloud()->uploadFile($tmpName, $filePath)) {
                    $strError = $this->_tr->translate('File was not uploaded.');
                }
            }

            if (empty($strError)) {
                $smallImageFilePath = $correctPathToSmallImage = $fileNewPath . '/' . $letterheadFileId . '_small';
                if ($booLocal) {
                    if (!copy($filePath, $smallImageFilePath)) {
                        $strError = $this->_tr->translate('File was not duplicated.');
                    }
                } else {
                    $smallImageFilePath = $this->getCloud()->downloadFileContent($filePath);
                    if (empty($smallImageFilePath)) {
                        $strError = $this->_tr->translate('File was not duplicated (from cloud).');
                    }
                }

                if (empty($strError)) {
                    // get image size
                    $size = array(80, 120);
                    // save image
                    $imageConfig['image_library'] = 'GD2';
                    $imageConfig['source_image'] = $smallImageFilePath;
                    $imageConfig['create_thumb'] = false;
                    $imageConfig['maintain_ratio'] = true;
                    $imageConfig['quality'] = 100;
                    $imageConfig['width'] = $size[0];
                    $imageConfig['height'] = $size[1];

                    // resize image
                    $imageManager = new ImageManager($this->_tr, $imageConfig);
                    if (!$imageManager->resize()) {
                        throw new Exception($imageManager->display_errors());
                    }

                    // Upload image to the cloud
                    if (!$booLocal && !$this->getCloud()->uploadFile($smallImageFilePath, $correctPathToSmallImage)) {
                        $strError = $this->_tr->translate('File (tiny version) was not uploaded to the cloud.');
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array('success' => empty($strError), 'error' => $strError);
    }

    /**
     * Save uploaded image to a specific location
     *
     * @param string $pathToDir
     * @param string $tempFileName
     * @param string $destinationFileName
     * @param array $size
     * @param bool $booLocalSave
     * @param null $row
     * @param bool $booDependent
     * @param bool $booProfile
     * @param bool $booImagesS3 true to use images S3 client
     * @return array
     */
    public function saveImage($pathToDir, $tempFileName, $destinationFileName, $size = array(), $booLocalSave = false, $row = null, $booDependent = false, $booProfile = false, $booImagesS3 = false)
    {
        $booFileReceived = false;
        $fileId = $tempFileName;

        if (isset($_FILES[$tempFileName])) {
            if (is_array($_FILES[$tempFileName]['name'])) {
                foreach ($_FILES[$tempFileName]['name'] as $key => $filename) {
                    if (empty($filename)) {
                        foreach ($_FILES[$tempFileName] as &$file) {
                            unset($file[$key]);
                        }
                        unset($file);
                    }
                }

                foreach ($_FILES[$tempFileName] as &$file) {
                    $file = array_values($file);
                }
                unset($file);
            } elseif (empty($_FILES[$tempFileName]['name'])) {
                return array('error' => 0, 'result' => '');
            }


            if (is_null($row)) {
                $booFileReceived = !empty($_FILES[$tempFileName]['name']);
            } else {
                $booFileReceived = !empty($_FILES[$tempFileName]['name'][$row]);
            }
        }

        if ($booFileReceived) {
            try {
                $logoNewFileName = '';

                $tmpDir = $this->_config['directory']['tmp'];

                $fileInput = new FileInput('file');
                $fileInput->getValidatorChain()
                    ->attach(new Extension(implode(',', self::SUPPORTED_IMAGE_FROMATS)))
                    ->attach(new Size(['max' => self::MAX_UPLOAD_IMAGE_SIZE]));
                $fileInput->getFilterChain()
                    ->attach(
                        new RenameUpload(
                            [
                                'target' => $tmpDir,
                                'randomize' => false,
                                'overwrite' => true
                            ]
                        )
                    );
                $fileInput->setBreakOnFailure(false);
                $fileInput->setValue($_FILES[$tempFileName]);

                $pathToRenamedImage = $pathToDir . '/' . FileTools::cleanupFileName($destinationFileName);
                // receive file
                if (is_null($row)) {
                    $fileInput->setValue($_FILES[$tempFileName]);
                } else {
                    $request = new Request();
                    $postData = array_merge_recursive(
                        $request->getPost()->toArray(),
                        $request->getFiles()->toArray()
                    );
                    $fileInput->setValue($postData[$tempFileName][$row]);
                }

                if (!$fileInput->isValid() || !$fileInput->getValue()) {
                    throw new Exception('Logo validation failure, Cannot receive file');
                }

                $logo = $fileInput->getValue();
                $pathToOriginalImage = $logo['tmp_name'];

                // Now we want resize image
                $imageConfig['image_library'] = 'GD2';
                $imageConfig['source_image'] = $pathToOriginalImage;
                $imageConfig['create_thumb'] = false;
                $imageConfig['maintain_ratio'] = true;
                $imageConfig['quality'] = 100;

                if (!empty($size)) {
                    if (is_array($size) && count($size) == 2) {
                        $imageConfig['width'] = $size[0];
                        $imageConfig['height'] = $size[1];
                    } elseif (is_numeric($size)) {
                        $imageConfig['width'] = $size;
                        $imageConfig['height'] = $size;
                    }
                }

                // resize image
                $imageManager = new ImageManager($this->_tr, $imageConfig);
                if (!$imageManager->resize()) {
                    throw new Exception($imageManager->display_errors());
                }

                // Rename file now
                if (file_exists($pathToOriginalImage)) {
                    if ($booDependent) {
                        $pathToThumbnail = $pathToDir . '/thumbnail';
                        $pathToTempThumbnail = $pathToOriginalImage . '_thumbnail';

                        $this->copyFile($pathToOriginalImage, $pathToTempThumbnail, true, $booImagesS3);
                        $imageConfig['source_image'] = $pathToTempThumbnail;
                        $imageConfig['width']        = 80;
                        $imageConfig['height']       = 40;
                        $imageManager                = new ImageManager($this->_tr, $imageConfig);
                        if (!$imageManager->resize()) {
                            throw new Exception($imageManager->display_errors());
                        }

                        if ($booLocalSave) {
                            if (file_exists($pathToThumbnail)) {
                                unlink($pathToThumbnail);
                            }

                            $this->createFTPDirectory(dirname($pathToThumbnail));
                        }
                        $this->copyFile($pathToTempThumbnail, $pathToThumbnail, $booLocalSave, $booImagesS3);
                        unlink($pathToTempThumbnail);
                    }

                    if ($booProfile) {
                        $pathToOriginal = $pathToRenamedImage . '-original';
                        $pathToTempOriginal = $pathToOriginalImage . '-original';

                        $this->copyFile($pathToOriginalImage, $pathToTempOriginal, true, $booImagesS3);
                        $imageConfig['source_image'] = $pathToOriginalImage;
                        $imageConfig['width']        = 120;
                        $imageConfig['height']       = 120;
                        $imageManager                = new ImageManager($this->_tr, $imageConfig);
                        if (!$imageManager->resize()) {
                            throw new Exception($imageManager->display_errors());
                        }

                        if ($booLocalSave) {
                            if (file_exists($pathToOriginal)) {
                                unlink($pathToOriginal);
                            }

                            $this->createFTPDirectory(dirname($pathToOriginal));
                        }
                        $this->copyFile($pathToTempOriginal, $pathToOriginal, $booLocalSave, $booImagesS3);
                        unlink($pathToTempOriginal);
                    }

                    if ($booLocalSave) {
                        if (file_exists($pathToRenamedImage)) {
                            unlink($pathToRenamedImage);
                        }

                        $booSuccess = $this->createFTPDirectory(dirname($pathToRenamedImage));
                        if ($booSuccess) {
                            $booSuccess = rename($pathToOriginalImage, $pathToRenamedImage);
                        }
                    } else {
                        $booSuccess = $this->getCloud($booImagesS3)->uploadFile($pathToOriginalImage, $pathToRenamedImage, $booImagesS3);

                        if (file_exists($pathToOriginalImage)) {
                            unlink($pathToOriginalImage);
                        }
                    }

                    if (is_array($_FILES[$fileId])) {
                        foreach ($_FILES[$fileId] as &$file) {
                            if (is_array($file)) {
                                array_shift($file);
                            }
                        }
                        unset($file);
                    }

                    if ($booSuccess) {
                        $logoNewFileName = $logo['name'];
                    } else {
                        throw new Exception('Error happened during file upload.');
                    }
                }
            } catch (Exception $e) {
                return array('error' => 1, 'result' => $e->getMessage());
            }
        }

        return array('error' => 0, 'result' => empty($logoNewFileName) ? '' : $logoNewFileName);
    }

    public function saveClientFile($pathToDir, $tempFileName, $destinationFileName, $booLocal = false, $row = null)
    {
        $booSuccess = false;
        $booFileReceived = false;
        $uploadFile = '';
        $extension = '';

        if (isset($_FILES[$tempFileName])) {
            foreach ($_FILES[$tempFileName]['name'] as $key => $filename) {
                if (empty($filename)) {
                    foreach ($_FILES[$tempFileName] as &$file) {
                        unset($file[$key]);
                    }
                    unset($file);
                }
            }

            foreach ($_FILES[$tempFileName] as &$file) {
                $file = array_values($file);
            }
            unset($file);

            if (is_null($row)) {
                $booFileReceived = !empty($_FILES[$tempFileName]['name']);
                $uploadFile      = $_FILES[$tempFileName]['tmp_name'];
                $extension       = FileTools::getFileExtension($_FILES[$tempFileName]['name']);
            } else {
                $booFileReceived = !empty($_FILES[$tempFileName]['name'][$row]);
                $uploadFile      = $_FILES[$tempFileName]['tmp_name'][$row];
                $extension       = FileTools::getFileExtension($_FILES[$tempFileName]['name'][$row]);
            }
        }

        if ($booFileReceived) {
            try {
                if (!$this->isFileFromWhiteList($extension)) {
                    throw new Exception('File type is not from whitelist.');
                }
                $pathToSave = $pathToDir . '/' . FileTools::cleanupFileName($destinationFileName);

                if ($booLocal) {
                    $this->createFTPDirectory($pathToDir);
                    $booSuccess = move_uploaded_file($uploadFile, $pathToSave);
                } else {
                    $this->createCloudDirectory($pathToDir);
                    $booSuccess = $this->getCloud()->uploadFile($uploadFile, $pathToSave);
                }

                foreach ($_FILES[$tempFileName] as &$file) {
                    array_shift($file);
                }
                unset($file);

                if (!$booSuccess) {
                    throw new Exception('Error happened during file upload.');
                }
            } catch (Exception $e) {
                return array('error' => 1, 'result' => $e->getMessage());
            }
        }

        return array('error' => 0, 'success' => $booSuccess);
    }

    public function getCompanyLogoPath($companyId, $booLocal)
    {
        return $this->getCompanyLogoFolderPath($companyId, $booLocal) . '/' . 'logo';
    }

    public function getCompanyLogo($companyId, $fileRealName, $booLocal)
    {
        return new FileInfo($fileRealName, $this->getCompanyLogoPath($companyId, $booLocal), $booLocal);
    }

    public function getClientImage($companyId, $memberId, $booLocal, $fileName, $fileRealName)
    {
        return new FileInfo(
            $fileRealName,
            $this->getPathToClientImages($companyId, $memberId, $booLocal) . '/' . $fileName,
            $booLocal
        );
    }

    public function getDependentImage($memberId, $companyId, $fileName, $fileRealName, $booLocal, $dependentId = null)
    {
        if ($dependentId != null) {
            $path = $this->getCompanyDependantsPath($companyId, $booLocal) . '/' . $memberId . '/' . (int)$dependentId . '/' . $fileName;
            $booExists = $booLocal ? file_exists($path) : $this->getCloud()->checkObjectExists($path);

            if ($booExists) {
                return new FileInfo($fileRealName, $path, $booLocal);
            }
        }

        return false;
    }

    public function deleteClientFile($companyId, $memberId, $booIsLocal, $fileName)
    {
        return $this->deleteFile(
            $this->getPathToClientFiles($companyId, $memberId, $booIsLocal) . '/' . $fileName,
            $booIsLocal
        );
    }

    public function deleteClientImage($companyId, $memberId, $booIsLocal, $fileName)
    {
        return $this->deleteFile(
            $this->getPathToClientImages($companyId, $memberId, $booIsLocal) . '/' . $fileName,
            $booIsLocal
        );
    }

    public function deleteDependentPhoto($companyId, $memberId, $dependentId, $booLocal)
    {
        $filePath = $this->getCompanyDependantsPath($companyId, $booLocal) . '/' . $memberId . '/' . $dependentId . '/';

        $arrFiles = array('original', 'thumbnail');
        foreach ($arrFiles as $fileName) {
            $booSuccess = $this->deleteFile($filePath . $fileName, $booLocal);
            if (!$booSuccess) {
                break;
            }
        }

        return $booSuccess;
    }

    /**
     * Delete resume file for specific prospect
     * @param int $prospectId
     * @param string $fileName (job id)
     * @return bool true on success
     */
    public function deleteProspectResume($prospectId, $fileName)
    {
        /** @var CompanyProspects $companyProspects * */
        $companyProspects = $this->_serviceContainer->get(CompanyProspects::class);
        return $this->deleteFile(
            $companyProspects->getPathToCompanyProspectJobFiles($prospectId) . '/' . $fileName,
            $this->_auth->isCurrentUserCompanyStorageLocal()
        );
    }

    /**
     * Create file with provided content
     *
     * @param string $path to the file
     * @param string $content of the file
     * @return bool true if file was created
     */
    public function createFile($path, $content)
    {
        $booSuccess = false;
        try {
            $this->createFTPDirectory(dirname($path));

            $fp = fopen($path, 'w+b');
            if ($fp) {
                fwrite($fp, $content);
                fclose($fp);

                $booSuccess = file_exists($path);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Load correct path and storage location by string path
     *
     * File path can be sent in 3 formats:
     * 1. Path to local file without 'path to company files location'
     * 2. Path to local file with 'path to company files location'
     * 3. Path to file located in S3
     *
     * For #1 and #2 cases we try to remove root path and add it.
     *
     * @param $filePath
     * @return array
     */
    public function getCorrectFilePathAndLocationByPath($filePath)
    {
        $booLocal = false;
        $rootPath = str_replace('\\', '/', $this->_dirConfig['companyfiles'] ?? '');
        $filePath = str_replace('\\', '/', $filePath ?? '');
        if (preg_match('%^/(\d+).*$%', $filePath, $regs)) {
            /** @var Company $company */
            $company  = $this->_serviceContainer->get(Company::class);
            $booLocal = $company->isCompanyStorageLocationLocal($regs[1]);
            if ($booLocal) {
                $filePath = str_replace($rootPath, '', $filePath);
                $localPath = $rootPath . '/' . ltrim($filePath, '/');

                if (!is_file($localPath)) {
                    $filePath = realpath($localPath);
                    $booLocal = false;
                }
            }
        } elseif (substr($filePath, 0, strlen($rootPath)) == $rootPath && is_file($filePath)) {
            $booLocal = true;
        }

        return array($booLocal, $filePath);
    }

    // Save edited by Zoho file
    public function saveFile($fileId, $file)
    {
        $booSuccess = false;
        try {
            $filePath = $this->_encryption->decode($fileId);
            if ($filePath) {
                list($booLocal, $filePath) = $this->getCorrectFilePathAndLocationByPath($filePath);

                if ($booLocal) {
                    if ($filePath) {
                        $this->createFTPDirectory(dirname($filePath));
                        $booSuccess = move_uploaded_file($file['tmp_name'], $filePath);
                    }
                } else {
                    $strContent = file_get_contents($file['tmp_name']);
                    $booSuccess = $this->getCloud()->createObject($filePath, $strContent);
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Check if folder is located in folder where phantomjs generates pdf
     *
     * @param $checkFolder
     * @return bool
     */
    public function checkAccessToPhantomJsFolder($checkFolder)
    {
        $booHasAccess = false;

        try {
            $pathToPhantomJsFolder = $this->_dirConfig['pdf_temp'];
            $pathToPhantomJsFolder = str_replace('\\', '/', $pathToPhantomJsFolder);
            $parsedPhantomJsPath = substr($checkFolder ?? '', 0, strlen($pathToPhantomJsFolder));

            if ($parsedPhantomJsPath == $pathToPhantomJsFolder) {
                $booHasAccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booHasAccess;
    }

    /**
     * Check if file is located in template's folder
     *
     * @param string $checkFolder - ftp folder which is checked
     * @return bool true if user has access, otherwise false
     */
    public function checkTemplateFTPFolderAccess($checkFolder)
    {
        $booCorrect = false;
        $checkFolder = str_replace('\\', '/', $checkFolder ?? '');

        $companyId                  = $this->_auth->getCurrentUserCompanyId();
        $companyLetterTemplatesPath = $this->getCompanyLetterTemplatesPath($companyId, true);
        $companyLetterTemplatesPath = str_replace('\\', '/', $companyLetterTemplatesPath);

        $parsedPath = substr($checkFolder, 0, strlen($companyLetterTemplatesPath));

        if ($parsedPath == $companyLetterTemplatesPath) {
            $booCorrect = true;
        }

        return $booCorrect;
    }


    /**
     * Delete a list of folders/files
     *
     * @param array $arrFilesAndFoldersList
     * @param bool $booLocal
     * @return bool true on success
     */
    public function delete($arrFilesAndFoldersList, $booLocal)
    {
        $booSuccess = false;
        try {
            // We expect to have a list of files/folders to delete
            if (empty($arrFilesAndFoldersList)) {
                return false;
            }

            $arrFilesAndFoldersList = (array)$arrFilesAndFoldersList;
            if ($booLocal) {
                foreach ($arrFilesAndFoldersList as $objectPath) {
                    if (file_exists($objectPath)) {
                        if (is_dir($objectPath)) {
                            $booSuccess = $this->deleteFTPFolder($objectPath);
                        } else {
                            $booSuccess = $this->deleteFile($objectPath);
                        }

                        if (!$booSuccess) {
                            break;
                        }
                    }
                }
            } else {
                foreach ($arrFilesAndFoldersList as $objectPath) {
                    $this->getCloud()->deleteObject($objectPath);
                }
                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    public function isZipArchive($mimeType)
    {
        $mime = array(
            'application/zip',
            'application/x-zip',
            'application/x-zip-compressed',
            'application/x-compress',
            'application/x-compressed',
            'multipart/x-zip',
            'application/x-octetstream',
            'application/x-download',
            'application/force-download'
        );

        return in_array($mimeType, $mime);
    }


    /**
     * Zip a specific directory
     *
     * @param string $dirPath path to directory to zip
     * @param array $arrFoldersAndFilesToZip the list of sub folders and files to zip, skip others
     * @param string $tempZipFilePath path to zip file location
     * @return bool true on success
     */
    public function zipDirectory($dirPath, $arrFoldersAndFilesToZip, $tempZipFilePath)
    {
        $booSuccess = false;

        if (is_dir($dirPath)) {
            // Get real path for our folder
            $rootPath = realpath($dirPath);

            // Initialize archive object
            $zip = new ZipArchive();
            $res = $zip->open($tempZipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            if ($res === true) {
                // Create recursive directory iterator
                /** @var SplFileInfo[] $files */
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($rootPath),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $file) {
                    // Skip directories (they would be added automatically)
                    if (!$file->isDir()) {
                        // Get real and relative path for current file
                        $filePath       = $file->getRealPath();
                        $relativePath   = substr($filePath, strlen($rootPath) + 1);
                        $fileFolderPath = $file->getPathInfo()->getRealPath();

                        if (in_array($filePath, $arrFoldersAndFilesToZip) || in_array($fileFolderPath, $arrFoldersAndFilesToZip)) {
                            // Add current file to archive
                            $zip->addFile($filePath, $relativePath);
                        }
                    }
                }

                // Zip archive will be created only after closing object
                $zip->close();

                $booSuccess = true;
            }
        }

        return $booSuccess;
    }

    public function unzipFile($realPath)
    {
        $zip = new ZipArchive();
        $zip->open($realPath);

        $files = array();
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $files[$i]['index'] = $i;
            $files[$i]['filename'] = $zip->getNameIndex($i);
            $files[$i]['filesize'] = strlen($zip->getFromIndex($i));
        }

        $zip->close();

        return $files;
    }

    /**
     * Converts a string to a valid UNIX filename.
     * @param string $string The filename to be converted
     * @return string The filename converted
     */
    public function convertToFilename($string)
    {
        $patterns[0] = '/\s/';
        $patterns[1] = '/\.\./';
        $patterns[2] = '/[^0-9A-Za-z_.()-]/';
        $replacements[0] = '_';
        $replacements[1] = '.';
        $replacements[2] = '';

        // Replace any character that is not in our white list
        ksort($patterns);
        ksort($replacements);

        return preg_replace($patterns, $replacements, $string);
    }


    /**
     * Load file path to converted pdf form.
     *
     * @param int $formId
     * @return string path
     */
    public function getConvertedPDFFormPath($formId)
    {
        return $this->_dirConfig['converted_pdf_forms'] . '/' . $formId;
    }

    /**
     * Load file path to converted pdf form.
     *
     * @param string $pdfFormVersionFilePath
     * @return string path
     */
    public function getConvertedXodFormPath($pdfFormVersionFilePath)
    {
        $pdfFormVersionFilePath = str_replace('.pdf', '.xod', $pdfFormVersionFilePath);

        return empty($pdfFormVersionFilePath) ? '' : $this->_dirConfig['converted_xod_forms'] . '/' . $pdfFormVersionFilePath;
    }

    /**
     * Check if file exists and if exists - generate file name that does not exist yet
     *
     * @param string $fullFilePath
     * @param bool $booLocal true if file is saved on local storage
     * @return string
     */
    public function generateFileName($fullFilePath, $booLocal)
    {
        $i             = 0;
        $fileExtension = FileTools::getFileExtension($fullFilePath);
        if (empty($fileExtension)) {
            $filePathAndName = $fullFilePath;
        } else {
            $fileExtension = '.' . $fileExtension;
            $filePathAndName = mb_substr($fullFilePath, 0, mb_strlen($fullFilePath) - mb_strlen($fileExtension));
        }

        if ($booLocal) {
            while (file_exists($fullFilePath)) {
                $i++;
                $fullFilePath = $filePathAndName . "($i)" . $fileExtension;
            }
        } else {
            while ($this->getCloud()->checkObjectExists($fullFilePath)) {
                $i++;
                $fullFilePath = $filePathAndName . "($i)" . $fileExtension;
            }
        }

        return $fullFilePath;
    }

    /**
     * Check if folder exists and if exists - generate folder name that does not exist yet
     *
     * @param string $fullFolderPath
     * @param bool $booLocal true if folder is saved on local storage
     * @return string
     */
    public function generateFolderName($fullFolderPath, $booLocal)
    {
        $i = 0;
        $originalFolderPath = $fullFolderPath;

        if ($booLocal) {
            while (is_dir($fullFolderPath)) {
                $i++;
                $fullFolderPath = $originalFolderPath . "($i)";
            }
        } else {
            while ($this->getCloud()->checkObjectExists($fullFolderPath . '/')) {
                $i++;
                $fullFolderPath = $originalFolderPath . "($i)";
            }
        }

        return $fullFolderPath;
    }

    /**
     * Return parsed eml file
     *
     * @param $realPath
     * @return array of parsed email file parts
     */
    public function getEmail($realPath)
    {
        // By default return error
        $return = array();

        $file     = false;
        $booLocal = $this->_auth->isCurrentUserCompanyStorageLocal();
        if ($booLocal) {
            if (file_exists($realPath)) {
                $file = $realPath;
            }
        } else {
            $file = $this->getCloud()->downloadFileContent($realPath);
        }

        if ($file && file_exists($file)) {
            $filename = static::extractFileName($realPath);
            $fileMime = FileTools::getMimeByFileName($filename);

            if ($this->isEmail($fileMime)) {
                if ($booLocal) {
                    $fileModificationTime = filemtime($file);
                } else {
                    $fileModificationTime = $this->getCloud()->getObjectLastModifiedTime($realPath);
                }

                $parser = new Parser();
                $parser->setPath($file);

                $return = [
                    'filename'    => $filename,
                    'to'          => explode(',', $parser->getHeader('to')),
                    'cc'          => empty($parser->getHeader('cc')) ? '' : explode(',', $parser->getHeader('cc')),
                    'bcc'         => empty($parser->getHeader('bcc')) ? '' : explode(',', $parser->getHeader('bcc')),
                    'from'        => $parser->getHeader('from'),
                    'subject'     => $parser->getHeader('subject'),
                    'date'        => $fileModificationTime ?: strtotime($parser->getHeader('date')),
                    'body'        => $parser->getMessageBody('htmlEmbedded'),
                    'attachments' => [],
                ];

                $attachments = $parser->getAttachments(false);
                foreach ($attachments as $attachment) {
                    $return['attachments'][] = [
                        'filename' => $attachment->getFilename(),
                    ];
                }
            }
        }

        return $return;
    }

    public function showEmail($path, $destination, $booShowExtraOptions = true)
    {
        // Get parsed email
        $mail = $this->getEmail($path);

        $html = '';
        if ($mail) {
            $strAttachments = '';
            if (isset($mail['attachments']) && is_array($mail['attachments'])) {
                /** @var Layout $layout */
                $layout     = $this->_viewHelperManager->get('layout');
                $topBaseUrl = $layout()->getVariable('topBaseUrl');

                foreach ($mail['attachments'] as $arrAttachmentInfo) {
                    $extension = FileTools::getFileExtension($arrAttachmentInfo['filename']);
                    $pathToIcon = 'public/images/mime/' . $extension . '.png';
                    $booIconExists = file_exists($pathToIcon);
                    $img = $booIconExists ? sprintf('<img border="0"  style="padding: 0 1px 0 3px;" align="absmiddle" src="%s/images/mime/%s.png" alt="Mime">', $topBaseUrl, $extension) : '';
                    $strAttachments .= sprintf(
                        $img .
                        '<a href="#" class="' . ($booIconExists ? '' : 'attachment-icon ') . 'bluelink" target="_blank" onclick="thisSaveAttachments(\'%s\', \'%s\', \'%s\'); return false;">%s</a>',
                        str_replace("'", "\'", $this->_encryption->encode($path)),
                        str_replace("'", "\'", $destination),
                        str_replace("'", "\'", $arrAttachmentInfo['filename']),
                        htmlentities($arrAttachmentInfo['filename'] ?? '', version_compare(phpversion(), '5.4', '<') ? ENT_COMPAT : (ENT_COMPAT | ENT_HTML401), 'UTF-8')
                    );
                }
            }

            if ($booShowExtraOptions) {
                $html .= '<div style="float:right; right:5px; padding:5px;">' .
                    sprintf(
                        '<a class="blulinkunb print-icon" href="#" onclick="thisPrintEml(this); return false;">%s</a>',
                        $this->_tr->translate('Print')
                    ) .
                    '</div>';

                $booCanAccessEmail = $this->_acl->isAllowed('mail-view');
                if ($booCanAccessEmail) {
                    $html .= '<div style="float:right; right:5px; padding:5px;">' .
                        sprintf(
                            '<a class="blulinkunb forward-icon" href="#" onclick="thisForwardEmlFile(this); return false;">%s</a>',
                            $this->_tr->translate('Forward')
                        ) .
                        '</div>';

                    $html .= '<div style="float:right; right:5px; padding:5px;">' .
                        sprintf(
                            '<a class="blulinkunb reply-all-icon" href="#" onclick="thisReplyEmlFile(this, true); return false;">%s</a>',
                            $this->_tr->translate('Reply All')
                        ) .
                        '</div>';

                    $html .= '<div style="float:right; right:5px; padding:5px;">' .
                        sprintf(
                            '<a class="blulinkunb reply-icon" href="#" onclick="thisReplyEmlFile(this, false); return false;">%s</a>',
                            $this->_tr->translate('Reply')
                        ) .
                        '</div>';
                }
            }

            $html .= '<div class="eml-filename" style="display:none;">' . $mail['filename'] . '</div>

                      <div class="eml-content" scrolling="auto">

                      <table class="eml-table" cellpadding="0" cellspacing="0" width="100%">
                      <tr>
                        <td class="eml-label">From</td>
                        <td class="eml-label-from eml-address">' . str_replace('<', '&lt;', $mail['from']) . '</td>
                      </tr>
                      <tr>
                        <td class="eml-label">To</td>
                        <td class="eml-label-to eml-address">' . implode('; ', str_replace('<', '&lt;', $mail['to'])) . '</td>
                      </tr>';

            if (!empty($mail['cc'])) {
                $html .= '<tr>
                            <td class="eml-label">Cc</td>
                            <td class="eml-label-cc eml-address">' . implode('; ', str_replace('<', '&lt;', $mail['cc'])) . '</td>
                          </tr>';
            }

            if (!empty($mail['bcc'])) {
                $html .= '<tr>
                            <td class="eml-label">Bcc</td>
                            <td class="eml-label-bcc eml-address">' . implode('; ', str_replace('<', '&lt;', $mail['bcc'])) . '</td>
                          </tr>';
            }

            if (!empty($mail['date'])) {
                $tz = $this->_auth->getCurrentMemberTimezone();

                $dt = new DateTime();
                $dt->setTimestamp($mail['date']);
                if ($tz instanceof DateTimeZone) {
                    $dt->setTimezone($tz);
                }

                $html .= '<tr>
                        <td class="eml-label">Date</td>
                        <td class="eml-label-date eml-address">' . substr($dt->format('r'), 0, 25) . '</td>
                      </tr>';
            }


            if (!empty($mail['subject'])) {
                $mail['subject'] = is_array($mail['subject']) ? implode('', $mail['subject']) : $mail['subject'];

                $html .= '<tr>
                        <td class="eml-label">Subject</td>
                        <td class="eml-label-subject eml-address">' . $mail['subject'] . '</td>
                      </tr>';
            }

            if (!empty($strAttachments)) {
                $html .= '<tr>
                            <td class="eml-label">Attachments</td>
                            <td class="eml-address">' . $strAttachments . '</td>
                          </tr>';
            }

            $html .= '</table>
                      <hr class="eml-divider" />
                      <div class="eml-body">' . $mail['body'] . '</div>
                      </div>';
        } else {
            $html = '<div>Can\'t load Email</div>';
        }

        return $html;
    }

    /**
     * Return attachment from eml file
     * @param $realPath - path to eml file
     * @param string $attachmentFileName - attachment file name which we want return
     * @return FileInfo|bool
     */
    public function getFileEmail($realPath, $attachmentFileName)
    {
        $file = false;
        if ($this->_auth->isCurrentUserCompanyStorageLocal()) {
            if (file_exists($realPath)) {
                $file = $realPath;
            }
        } else {
            $file = $this->getCloud()->downloadFileContent($realPath);
        }

        if ($file) {
            $parser = new Parser();
            $parser->setPath($file);

            $attachments = $parser->getAttachments(false);
            foreach ($attachments as $attachment) {
                if ($attachment->getFilename() == $attachmentFileName) {
                    $file = new FileInfo(
                        $attachmentFileName,
                        null,
                        true,
                        FileTools::getMimeByFileName($attachmentFileName),
                        $attachment->getContent()
                    );
                    break;
                }
            }
        }

        return $file;
    }

    public function formatFileSize($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);

        // We don't want to show bytes, so minimum will be 1 Kb
        $bytes = max($bytes, 1024);

        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }


    /**
     * Creating temporary file in our temporary folder
     *
     * @param string $extension
     * @return string
     */
    public function createTempFile($extension)
    {
        $isDirCreated = true;
        $tempDir = $this->_dirConfig['tmp'];
        if (!is_dir(realpath($tempDir))) {
            $isDirCreated = $this->createFTPDirectory($tempDir);
        }

        $fileCreationResult = false;
        if ($isDirCreated) {
            do {
                $fileCreationResult = tempnam($tempDir, '') . '.' . $extension;
            } while (@file_exists($fileCreationResult));
        }

        return $fileCreationResult;
    }

    public function getFolderIconCls($type)
    {
        switch ($type) {
            case 'C' :
            case 'F' :
                return 'las la-exclamation-triangle';
            case 'SDR' :
                return 'las la-share-alt';
            case 'SD' :
            case 'D' :
            case 'T' :
            case 'ST' :
            case 'STR' :
            case 'CD' :
            case 'FTP' :
            default :
                return 'fas fa-folder';
        }
    }

    public function fixFilePath($path)
    {
        if (is_file($path)) {
            $last_dot_pos = strrpos($path, '.');
            $file = substr($path, 0, $last_dot_pos);
            $ext = substr($path, $last_dot_pos + 1);
            $i = 1;

            while (file_exists($path)) {
                $path = $file . ' (' . $i++ . ').' . $ext;
            }
        }

        return $path;
    }

    /**
     * Load files list from specific company default folder
     *
     * @param int $companyId
     * @param int $folderId
     * @return array of files
     */
    public function getCompanyDefaultFiles($companyId, $folderId)
    {
        $arrFiles = array();

        $path = $this->getCompanyDefaultFilesPath($companyId);
        $fullPath = $path . '/' . $folderId;

        if (is_dir($fullPath)) {
            $dir_iterator = new RecursiveDirectoryIterator($fullPath, FilesystemIterator::SKIP_DOTS);
            $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST, FilesystemIterator::SKIP_DOTS);
            $iterator->setMaxDepth(0);

            foreach ($iterator as $path => $file) {
                if (!$file->isDir()) {
                    $arrFiles[] = array(
                        'file_name' => $file->getFilename(),
                        'file_path' => str_replace('\\', '/', $path)
                    );
                }
            }
        }

        return $arrFiles;
    }

    /**
     * Download company default file
     *
     * @param int $companyId
     * @param string $filePath
     * @return FileInfo|bool false on error
     */
    public function downloadCompanyDefaultFile($companyId, $filePath)
    {
        $path = getcwd() . '/' . $this->getCompanyDefaultFilesPath($companyId);
        $fullPath = realpath($path . '/' . $filePath);
        $fullPath = str_replace('\\', '/', $fullPath);
        $path = str_replace('\\', '/', $path);

        if (is_file($fullPath) && substr($fullPath, 0, strlen($path)) == $path) {
            $fileName = static::extractFileName($fullPath);
            return new FileInfo($fileName, $fullPath, true);
        }

        return false;
    }

    /**
     * Upload passed array of files to specific default company folder
     *
     * @param int $companyId
     * @param int $folderId
     * @param array $arrFiles
     * @return bool true on success
     */
    public function uploadCompanyDefaultFiles($companyId, $folderId, $arrFiles)
    {
        $booSuccess = false;

        $path = $this->getCompanyDefaultFilesPath($companyId);
        $fullPath = $path . '/' . $folderId;
        $this->createFTPDirectory($fullPath);

        foreach ($arrFiles as $file) {
            $booSuccess = move_uploaded_file($file['tmp_name'], $fullPath . '/' . FileTools::cleanupFileName($file['name']));
            if (!$booSuccess) {
                break;
            }
        }

        return $booSuccess;
    }

    /**
     * Delete company default file
     *
     * @param int $companyId
     * @param string $filePath
     * @return bool true on success
     */
    public function deleteCompanyDefaultFile($companyId, $filePath)
    {
        $path = $this->getCompanyDefaultFilesPath($companyId);

        $fullPath = realpath($path . '/' . $filePath);
        $fullPath = str_replace('\\', '/', $fullPath);

        $path = realpath($path);
        $path = str_replace('\\', '/', $path);

        $booSuccess = false;
        if (is_file($fullPath) && substr($fullPath, 0, strlen($path)) == $path) {
            $booSuccess = unlink($fullPath);
        }

        return $booSuccess;
    }

    /**
     * Rename company default file
     *
     * @param int $companyId
     * @param string $filePath
     * @param string $newFileName
     * @return bool true on success
     */
    public function renameCompanyDefaultFile($companyId, $filePath, $newFileName)
    {
        $path = $this->getCompanyDefaultFilesPath($companyId);

        $fullPath = realpath($path . '/' . $filePath);
        $fullPath = str_replace('\\', '/', $fullPath);

        $path = realpath($path);
        $path = str_replace('\\', '/', $path);

        $newFileName = str_replace('..', '.', $newFileName);

        $booSuccess = false;
        if (substr($fullPath, 0, strlen($path)) == $path && is_file($fullPath)) {
            $oldName = static::extractFileName($fullPath);
            $newPath = rtrim(substr($fullPath, 0, strlen($fullPath) - strlen($oldName)), '/') . '/' . FileTools::cleanupFileName($newFileName);

            $booSuccess = rename($fullPath, $newPath);
        }

        return $booSuccess;
    }

    public static function formatSizeInGb($size, $booRound = false)
    {
        // we cannot use (int)$size because it is incorrectly converted on 32bit systems!
        $inGb = ($size + 0) / 1024 / 1024 / 1024;

        return $booRound ? round($inGb, 2) : ceil($inGb);
    }

    /**
     * Get path where company email attachments are saved
     * @param int $companyId
     * @param bool $booLocal
     * @return string
     */
    public function getCompanyEmailAttachmentsPath($companyId, $booLocal)
    {
        return $this->getCompanyPath($companyId, $booLocal) . '/.emails';
    }

    /**
     * Get path where company email attachments are saved
     *
     * @param array $attachmentInfo
     * @param bool $local
     * @return string
     */
    public function getFilePathPrefix($attachmentInfo, $local = true)
    {
        return $this->getCompanyEmailAttachmentsPath($attachmentInfo['company_id'], ($attachmentInfo['storage_location'] == 'local'));
    }

    /**
     * Get path where member's company email attachments are saved
     *
     * @param int $companyId
     * @param int $memberId
     * @param bool $booLocal
     * @return string
     */
    public function getMemberEmailAttachmentsPath($companyId, $memberId, $booLocal)
    {
        return $this->getCompanyEmailAttachmentsPath($companyId, $booLocal) . '/' . $memberId;
    }

    /**
     * Move LOCAL file to the new location (local or in the cloud)
     *
     * @param string $oldPath
     * @param string $newPath
     * @param bool $booLocal
     * @return bool true on success
     */
    public function moveLocalFileToCloudOrLocalStorage($oldPath, $newPath, $booLocal)
    {
        $booSuccess = false;
        if (is_file($oldPath)) {
            if ($booLocal) {
                $booSuccess = $this->moveLocalFile($oldPath, $newPath, true);
            } else {
                $booSuccess = $this->getCloud()->uploadFile($oldPath, $newPath);
            }
        }

        return $booSuccess;
    }

    /**
     * Move local/remote file to the new location
     *
     * @param string $oldPath
     * @param string $newPath
     * @param bool $booLocal
     * @return bool
     */
    public function moveFile($oldPath, $newPath, $booLocal)
    {
        if ($booLocal) {
            $booSuccess = $this->moveLocalFile($oldPath, $newPath, true);
        } else {
            $booSuccess = $this->getCloud()->copyObject($oldPath, $newPath);
            if ($booSuccess) {
                $this->getCloud()->deleteObject($oldPath);
            }
        }

        return $booSuccess;
    }

    public static function checkPhpExcelFileName($title)
    {
        $arrDisallowedChars = array('*', ':', '/', '\\', '?', '[', ']');

        return substr(str_replace($arrDisallowedChars, '', $title), 0, 31);
    }

    /**
     * Load list of top folders in the Shared Workspace
     *
     * @param int $companyId
     * @return array
     */
    public function getSharedTopFolders($companyId, $booLocal)
    {
        $arrTopLevelFolders = array();

        $arrSharedDocsInfo = $this->getCompanySharedDocsPath($companyId, true, $booLocal);
        $pathToShared = $arrSharedDocsInfo[0] . '/' . $arrSharedDocsInfo[1];

        if ($booLocal) {
            if (is_dir($pathToShared)) {
                if (DIRECTORY_SEPARATOR == '\\') {
                    $pathToShared = str_replace('\\', '/', $pathToShared);
                }
                $dir_iterator = new RecursiveDirectoryIterator($pathToShared, FilesystemIterator::SKIP_DOTS);
                $iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST, FilesystemIterator::SKIP_DOTS);
                $iterator->setMaxDepth(0);

                foreach ($iterator as $file) {
                    $fileName = $file->getFilename();
                    if ($file->isDir()) {
                        $arrTopLevelFolders[] = $fileName;
                    }
                }
            }
        } else {
            $arrItems = $this->getCloud()->getList($pathToShared);

            foreach ($arrItems as $object) {
                $name = $object['Key'];
                if ($this->getCloud()->isFolder($name)) {
                    $arrTopLevelFolders[] = $this->getCloud()->getFolderNameByPath($name);
                }
            }
        }

        return $arrTopLevelFolders;
    }

    public function parseDropboxGoogleShareLink($fileUrl)
    {
        $strError         = '';
        $convertedFileUrl = '';
        $fileName         = '';
        $fileExt          = '';

        // Dropbox link
        // Convert share link into direct download link by setting the dl=0 query parameter to 1
        if(strpos($fileUrl ?? '', 'https://www.dropbox.com') === 0){
            $convertedFileUrl = str_replace('dl=0', 'dl=1', $fileUrl);

            $fileName = urldecode(basename($fileUrl));
            $fileName = explode('?', $fileName)[0];
            $fileExt  = pathinfo($fileName, PATHINFO_EXTENSION);

            if(empty($fileName)){
                $strError = $this->_tr->translate('Invalid Dropbox URL (file name).');
            }

            if(strpos($convertedFileUrl ?? '', 'dl=1') === false){
                $strError = $this->_tr->translate('Invalid Dropbox URL.');
            }
        } // Google drive link
        elseif (strpos($fileUrl ?? '', 'https://drive.google.com') === 0) {
            // Convert the sharing link into a direct download link
            if (preg_match('/^https:\/\/drive.google.com\/file\/d\/(.+)\/view\?usp=sharing$/', $fileUrl, $matches) === 1) {
                $convertedFileUrl = 'https://drive.google.com/uc?export=download&id=' . $matches[1];
            } elseif (preg_match('/^https:\/\/drive.google.com\/uc\?export=download&id=(.+)$/', $fileUrl, $matches) === 1) {
                // Url already in converted format, do nothing
                $convertedFileUrl = $fileUrl;
            } else {
                $strError = $this->_tr->translate('Invalid Google Drive URL.');
            }

            if (empty($strError)) {
                // Parse headers to get file information
                $r = get_headers($convertedFileUrl);
                if ($r !== false) {
                    $googleDriveFileContentType                = null;
                    $googleDriveFileContentDisposition         = null;
                    $googleDriveFileContentDispositionFileName = null;
                    $googleDriveFileContentLength              = null;

                    foreach ($r as $index => $headerLine) {
                        // Look for headers after the 200 OK since get_headers may follow one or more redirects
                        if ($headerLine == 'HTTP/1.1 200 OK') {
                            // Google headers for a successful response will include something like below:
                            // "Content-Type: image/jpeg"
                            // "Content-Disposition: attachment;filename=\"companylogo.jpg\";filename*=UTF-8''companylogo.jpg"
                            // "Content-Length: 80627"

                            $totalLines = count($r);
                            for ($c = $index; $c < $totalLines; $c++) {
                                $headerLine2 = $r[$c];
                                if (strpos($headerLine2 ?? '', 'Content-Type:') === 0) {
                                    $googleDriveFileContentType = explode("Content-Type: ", $headerLine2 ?? '')[1];
                                } elseif (strpos($headerLine2 ?? '', 'Content-Disposition:') === 0) {
                                    $googleDriveFileContentDisposition = explode("Content-Disposition: ", $headerLine2 ?? '')[1];
                                    if (preg_match("/^attachment;filename=\"(.+)\";filename\*=UTF-8''(.+)$/", $googleDriveFileContentDisposition, $matches2) === 1) {
                                        $googleDriveFileContentDispositionFileName = urldecode($matches2[2]);
                                    }
                                } elseif (strpos($headerLine2 ?? '', 'Content-Length:') === 0) {
                                    $googleDriveFileContentLength = explode("Content-Length: ", $headerLine2 ?? '')[1];
                                }
                            }
                        }
                    }

                    if (!empty($googleDriveFileContentDispositionFileName)) {
                        $fileName          = $googleDriveFileContentDispositionFileName;
                        $fileContentMaxLen = 1024 * 1024 * 20; // 20MB
                        $fileExt           = pathinfo($googleDriveFileContentDispositionFileName, PATHINFO_EXTENSION);
                        if ($googleDriveFileContentLength > $fileContentMaxLen) {
                            $strError = $this->_tr->translate('File size too large (exceeded 20MB).');
                        } elseif (!$this->isFileFromWhiteList($fileExt)) {
                            $strError = $this->_tr->translate('File type is not from whitelist.');
                        }
                    } else {
                        $strError = $this->_tr->translate('Invalid Google Drive URL (file error).');
                    }
                } else {
                    $strError = $this->_tr->translate('Invalid Google Drive URL (headers).');
                }
            }
        } else {
            $strError = $this->_tr->translate('Invalid Dropbox or Google Drive URL.');
        }

        return [$strError, $convertedFileUrl, $fileName, $fileExt];
    }
}
