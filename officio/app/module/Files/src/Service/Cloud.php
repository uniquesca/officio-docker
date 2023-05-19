<?php

namespace Files\Service;

use Aws\Api\DateTimeResult;
use Aws\CommandPool;
use Aws\Exception\AwsException;
use Aws\ResultInterface;
use Aws\ResultPaginator;
use Aws\S3\S3Client;
use Exception;
use Laminas\View\Helper\Layout;
use Laminas\View\HelperPluginManager;
use Officio\Common\Service\BaseService;
use Uniques\Php\StdLib\FileTools;
use Officio\Common\Service\Encryption;
use Officio\Common\Service\Settings;
use Officio\Common\SubServiceInterface;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class Cloud extends BaseService implements SubServiceInterface
{

    /** @var Files */
    protected $_parent;

    /** @var HelperPluginManager */
    protected $_viewHelperManager;

    // Custom Uniques header used for last modification date of the file saving
    public const HEADER_LAST_MODIFIED = 'uniques-last-modified';

    // Custom header created/used by s3cmd tool - saves a lot of info (e.g. modified time, created time, etc.)
    public const HEADER_S3CMD_ATTRS = 's3cmd-attrs';

    // How many curl connections create at once
    public const MAX_REQUESTS_COUNT = 5;

    // How many times we try to connect to S3
    public const MAX_CONNECTION_RETRY_COUNT = 2;

    // AWS API version
    // Has to be updated each time AWS SDK is updated
    // Find version here: https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-s3-2006-03-01.html
    public const AWS_API_VERSION = '2006-03-01';

    /** @var S3Client */
    protected $_client;

    /** @var Encryption */
    protected $_encryption;

    /** @var string */
    protected $_bucket;

    /** @var bool */
    protected $_connected;

    /** @var bool */
    protected $_isImagesS3 = false;

    public function setParent($parent)
    {
        $this->_parent = $parent;
        return $this;
    }

    public function getParent()
    {
        return $this->_parent;
    }

    public function setImagesS3($imagesS3)
    {
        $this->_isImagesS3 = $imagesS3;
        return $this;
    }

    public function getImagesS3()
    {
        return $this->_isImagesS3;
    }

    public function initAdditionalServices(array $services)
    {
        $this->_viewHelperManager = $services[HelperPluginManager::class];
        $this->_encryption = $services[Encryption::class];
    }

    public function init()
    {
        try {
            $config = $this->getConfig();

            if (!$config['is_online']) {
                throw new Exception('Cloud turned off in the config.');
            }

            // Try to connect to S3
            $this->_client = new S3Client(
                array(
                    'version'     => self::AWS_API_VERSION,
                    'region'      => $config['aws_region'],
                    'debug'       => false,
                    'retries'     => self::MAX_CONNECTION_RETRY_COUNT,
                    'scheme'      => (!$config['check_ssl']) ? 'http' : 'https',
                    'credentials' => array(
                        'key'    => $config['aws_accesskey'],
                        'secret' => $config['aws_secretkey'],
                    )
                )
            );

            if (empty($this->_client->listBuckets())) {
                throw new Exception('Connection to Amazon S3 was not established.');
            }

            // Try to check if bucket exists
            $this->_bucket   = $config['bucket_name'];
            $booBucketExists = $this->_client->doesBucketExist($this->getBucket());
            if (!$booBucketExists) {
                throw new Exception('Cannot find bucket in Amazon S3.');
            }

            $this->_connected = true;
        } catch (Exception $e) {
            $this->_client    = null;
            $this->_bucket    = null;
            $this->_connected = false;
            if ($e->getMessage() != 'Cloud turned off in the config.') {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 's3');
            }
        }
    }

    /**
     * Load settings and if encryption is enabled - use it in all create/copy methods
     *
     * @return array
     */
    public function getEncryptionOptions()
    {
        $config = $this->getConfig();

        return isset($config['encryption']) && !empty($config['encryption']) ? array('ServerSideEncryption' => $config['encryption']) : array();
    }

    /**
     * Get Amazon client
     *
     * @return S3Client|null
     */
    public function getClient()
    {
        return $this->_client;
    }

    /**
     * Get bucket
     *
     * @return null|String
     */
    public function getBucket()
    {
        return $this->_bucket;
    }

    /**
     * Get config
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->_isImagesS3 ? $this->_config['html_editor']['remote'] : $this->_config['storage'];
    }

    /**
     * Check if S3 connection was established
     *
     * @return bool
     */
    public function isConnected()
    {
        return $this->_connected;
    }

    /**
     * Check if connection to S3 was established and if bucket exists
     *
     * @return bool true if everything is okay
     */
    public function isAlive()
    {
        return !is_null($this->getBucket()) && $this->isConnected();
    }

    /**
     * Prepare the path and remove the first '/' symbol
     *
     * @param string $path
     * @param false $booUrlEncode
     * @return array|string|string[]|null
     */
    public function preparePath($path, $booUrlEncode = false)
    {
        $path = str_replace('\\', '/', preg_replace('%^/%', '', $path));

        if ($booUrlEncode) {
            $path = urlencode($path);
        }

        return $path;
    }

    /**
     * Check if the provided path is a file or a folder (ends with the "/")
     *
     * @param $strItemName
     * @return false|int
     */
    public function isFolder($strItemName)
    {
        return preg_match('%^(.*)/$%i', $strItemName);
    }

    /**
     * Extracts a file name from the path
     *
     * @param $strPath
     * @return mixed|string
     */
    public function getFileNameByPath($strPath)
    {
        $fileName = '';
        if (!$this->isFolder($strPath)) {
            if (preg_match('%^(.*)/(.*)$%i', $strPath)) {
                preg_match_all('%^(.*)/(.*)$%i', $strPath, $result, PREG_PATTERN_ORDER);
                $fileName = $result[2][0];
            } else {
                $fileName = $strPath;
            }
        }

        return $fileName;
    }

    /**
     * Extracts a folder name from the path
     *
     * @param string $strPath
     * @return mixed|string
     */
    public function getFolderNameByPath($strPath)
    {
        return $this->getFileNameByPath(rtrim($strPath, '/'));
    }

    /**
     * Load a list of parent folders for a path
     *
     * @param string $strPath
     * @return array|string[]
     */
    public function getFolderParentFolders($strPath)
    {
        $folderName = $this->getFolderNameByPath($strPath);

        $arrFolders = array();
        if ($folderName . '/' !== $strPath) {
            $strPath    = substr($strPath ?? '', 0, strlen($strPath ?? '') - (strlen($folderName ?? '') + 2));
            $arrFolders = explode('/', $strPath);
        }

        return $arrFolders;
    }

    /**
     * Loads a parent folder for the path
     *
     * @param string $strPath
     * @return string
     */
    public function getFolderParent($strPath)
    {
        $folderName = $this->getFolderNameByPath($strPath);

        $strParent = '';
        if ($folderName . '/' !== $strPath) {
            $strParent = substr($strPath ?? '', 0, strlen($strPath ?? '') - (strlen($folderName ?? '') + 2));
        }

        return $strParent . '/';
    }

    /**
     * Generate unique file path by provided path
     * i.e. will be added (1)... to the end of the file (before extension)
     *
     * @param string $path
     * @return string unique path
     */
    public function fixFilePath($path)
    {
        if (!$this->isFolder($path)) {
            $last_dot_pos = strrpos($path, '.');

            $file = substr($path ?? '', 0, $last_dot_pos);
            $ext  = substr($path ?? '', $last_dot_pos + 1);
            $i    = 1;

            while ($this->checkObjectExists($path)) {
                $path = $file . ' (' . $i++ . ').' . $ext;
            }
        }

        return $path;
    }

    /**
     * Calculate folder size
     *
     * @param $path
     * @return int used bytes
     */
    public function getFolderSize($path)
    {
        if (!$this->isAlive()) {
            return 0;
        }

        $size       = 0;
        $arrResults = $this->getList($path, false, false);
        foreach ($arrResults as $arrObject) {
            $size += (int)$arrObject['Size'];
        }

        return $size;
    }

    /**
     * Get a list of parent folders for the path
     *
     * @param string $strPath
     * @return array|string[]
     */
    public function getFileParentFolders($strPath)
    {
        $fileName = $this->getFileNameByPath($strPath);

        $arrFolders = array();
        if ($fileName !== $strPath) {
            $strPath    = substr($strPath ?? '', 0, strlen($strPath ?? '') - (strlen($fileName ?? '') + 1));
            $arrFolders = explode('/', $strPath);
        }

        return $arrFolders;
    }

    /**
     * Lists objects
     *
     * @param $prefix
     * @return array
     */
    public function getDirectorySubDirectoriesList($prefix)
    {
        if (!$this->isAlive()) {
            return array();
        }

        $args = array(
            'Bucket'    => $this->getBucket(),
            'Delimiter' => '/',
        );

        if (strlen($prefix ?? '')) {
            $args['Prefix'] = $prefix;
        }

        $paginator  = $this->getClient()->getPaginator('ListObjects', $args);
        $arrResults = array();
        foreach ($paginator as $object) {
            $arrResults[] = $object;
        }

        return $arrResults;
    }

    /**
     * Returns a list of objects
     *
     * @param string $prefix
     * @param bool $booOneLevelOnly
     * @return ResultPaginator|false
     */
    private function _getBucketObjects($prefix = '', $booOneLevelOnly = false)
    {
        if (!$this->isAlive()) {
            return false;
        }

        $args = array(
            'Bucket'  => $this->getBucket(),
        );

        if (strlen($prefix ?? '')) {
            $args['Prefix'] = $prefix;
        }

        if ($booOneLevelOnly) {
            $args['Delimiter'] = '/';
        }

        return $this->getClient()->getPaginator('ListObjects', $args);
    }

    /**
     * Load objects list for a specific path
     *
     * @param string $path
     * @param bool $booOneLevelOnly
     * @param bool $booLoadHeaders
     * @return array
     */
    public function getList($path = '', $booOneLevelOnly = false, $booLoadHeaders = true)
    {
        if (!$this->isAlive()) {
            return array();
        }

        $prefix = $this->preparePath($path) . '/';
        try {
            $arrItems = array();

            $arrHeaders    = array();
            $bucketObjects = $this->_getBucketObjects($prefix, $booOneLevelOnly);
            if ($booLoadHeaders) {
                $batch = array();
                foreach ($bucketObjects as $object) {
                    if ($object instanceof AwsException) {
                        continue;
                    }

                    if (($object instanceof ResultInterface) && isset($object['Contents'])) {
                        foreach ($object['Contents'] as $result) {
                            if (!$this->isFolder((string)$result['Key'])) {
                                $batch[] = $this->getClient()->getCommand(
                                    'HeadObject',
                                    array(
                                        'Bucket'  => $this->getBucket(),
                                        'Key'     => $this->preparePath((string)$result['Key'])
                                    )
                                );
                            }
                        }
                    }
                }

                $headersResults = CommandPool::batch(
                    $this->getClient(),
                    $batch,
                    [
                        'concurrency' => self::MAX_REQUESTS_COUNT
                    ]
                );
                foreach ($headersResults as $headersResult) {
                    if ($headersResult instanceof AwsException) {
                        continue;
                    }

                    if ($headersResult instanceof ResultInterface) {
                        $headersResult = $headersResult->toArray();
                        // Headers don't include Key, so we use ETag to identify which objects headers belong to
                        $arrHeaders[$headersResult['ETag']] = $headersResult['Metadata'];
                    }
                }
            }

            foreach ($bucketObjects as $object) {
                if (isset($object['Contents'])) {
                    foreach ($object['Contents'] as $contents) {
                        $key  = $contents['Key'];
                        $etag = $contents['ETag'];
                        // Use custom header to identify 'file changed date'
                        $lastModified = (string)$contents['LastModified'];
                        if (array_key_exists($etag, $arrHeaders)) {
                            if (array_key_exists(self::HEADER_LAST_MODIFIED, $arrHeaders[$etag])) {
                                // This can be our (uniques) custom meta tag
                                $lastModified = $arrHeaders[$etag][self::HEADER_LAST_MODIFIED];
                            } elseif (array_key_exists(self::HEADER_S3CMD_ATTRS, $arrHeaders[$etag])) {
                                // Or if our tag is missing - use created by s3cmd
                                $strAttributes = $arrHeaders[$etag][self::HEADER_S3CMD_ATTRS] ?? '';
                                $arrAttributes = explode('/', $strAttributes);
                                foreach ($arrAttributes as $strAttribute) {
                                    $arrAttributeParsed = explode(':', $strAttribute ?? '');
                                    if ($arrAttributeParsed[0] == 'mtime') {
                                        $lastModified = date('c', (int)$arrAttributeParsed[1]);
                                        break;
                                    }
                                }
                            }
                        }

                        $arrObject        = array(
                            'Key'          => $key,
                            'FullPath'     => $key,
                            'LastModified' => $lastModified,
                            'Size'         => (string)$contents['Size'],
                        );
                        $arrObject['Key'] = substr($arrObject['Key'], strlen($prefix), strlen($arrObject['Key']));
                        if (!empty($arrObject['Key'])) {
                            $arrItems[] = $arrObject;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 's3');
            return array();
        }

        return $arrItems;
    }

    /**
     * Encode file's name when we output it in headers
     *
     * @param string $filename
     * @param bool $booEncodeUrl
     * @return string
     */
    private function _encodeFileName($filename, $booEncodeUrl = true)
    {
        // With love to IE + IE11
        if (!$booEncodeUrl || strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false || strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') !== false || preg_match('/Trident\/7.0/i', $_SERVER['HTTP_USER_AGENT'])) {
            $encodedFileName = urlencode($filename);
        } else {
            $encodedFileName = '=?UTF-8?B?' . base64_encode($filename) . '?=';
        }

        return $encodedFileName;
    }

    // this func doesn't work with folders, which contain subfolders
    // example: there folders: /a/b
    // checkObjectExists('/a/') returns false, because only object '/a/b/' exists, there is no object '/a/'
    // checkObjectExists('/a/b/') returns true
    // TODO: check all places, where this func used to check folder existence
    public function checkObjectExists($path)
    {
        if (!$this->isAlive()) {
            return false;
        }

        try {
            $result = $this->getClient()->doesObjectExist(
                $this->getBucket(),
                $this->preparePath($path)
            );
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 's3');
            $result = false;
        }

        return $result;
    }

    /**
     * Check if file exists and generate link for it
     * (this link will expire soon, so must be used immediately)
     *
     * @param string $path to the file
     * @param bool $booEnableCache
     * @param bool $booAsAttachment
     * @param null $filename
     * @param bool $booEncodeUrl
     * @return string generated url, empty on error
     */
    public function generateFileDownloadLink($path, $booEnableCache = false, $booAsAttachment = true, $filename = null, $booEncodeUrl = true)
    {
        if (!$this->isAlive()) {
            return '';
        }

        $url = '';

        if ($this->checkObjectExists($path)) {
            // Try to detect the file name if wasn't provided
            // This is needed to output correct headers
            if (is_null($filename)) {
                $filename = Files::extractFileName($path);
            }

            // Amazon S3 has limitations:
            // Signature Version 4 has a max expiration of 7 days and Signature Version 2 has a max expiration of a year.
            $maxExpirationDate = date('Y-m-d', strtotime('+7 days'));

            try {
                $command = $this->getClient()->getCommand(
                    'GetObject',
                    array(
                        'Bucket'                     => $this->getBucket(),
                        'Key'                        => $this->preparePath($path),
                        'ResponseContentDisposition' => ($booAsAttachment ? 'attachment' : 'inline') . (empty($filename) ? '' : "; filename=\"" . $this->_encodeFileName($filename, $booEncodeUrl) . "\";"),
                        'ResponseExpires'            => gmdate(DATE_RFC2822, $booEnableCache ? strtotime($maxExpirationDate) : strtotime('1 January 1980')),
                        'ResponseContentType'        => (!empty($filename)) ? FileTools::getMimeByFileName($filename) : '',
                    )
                );

                $request = $this->getClient()->createPresignedRequest($command, $booEnableCache ? $maxExpirationDate : '+30 seconds');
                $url     = (string)$request->getUri();
            } catch (Exception $e) {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 's3');
                $url = false;
            }

            if (!empty($url) && $booEncodeUrl) {
                // Use our "proxy" url to hide the real S3 url, so no extra info (like bucket name, etc.) will be visible to the end user
                /** @var Layout $layout */
                $layout = $this->_viewHelperManager->get('layout');
                if (empty($filename)) {
                    $url = $layout()->getVariable('topBaseUrl') . '/api/remote/get-file?enc=' . urlencode($this->_encryption->encode($url));
                } else {
                    // Add the file name, so it can be recognized by stupid Zoho
                    $url = $layout()->getVariable('topBaseUrl') . '/api/remote/get-file?' . urlencode($filename) . '&enc=' . urlencode($this->_encryption->encode($url));
                }
            }
        }

        return $url;
    }

    /**
     * Generate PUBLIC link for a specific file path
     * No any checks are done here, please make sure that file
     *
     * @param string $path
     * @param bool $booCheckIfExists
     * @return string
     */
    public function generateFilePublicLink($path, $booCheckIfExists = true)
    {
        $url = '';
        if ($this->isAlive()) {
            $booExists = $booCheckIfExists ? $this->checkObjectExists($path) : true;
            if ($booExists) {
                $url = Settings::formatUrl($this->getClient()->getObjectUrl($this->getBucket(), $this->preparePath($path)));
            }
        }

        return $url;
    }

    /**
     * Download file by provided path
     * (redirect to the Amazon url, instead of file downloading)
     *
     * @param string $path to the file
     * @param string $filename file name to save with
     * @param bool $booEnableCache true to enable file caching
     * @param bool $booAsAttachment true to send file as attachment
     * @return string|bool File download URL
     */
    public function getFile($path, $filename = null, $booEnableCache = false, $booAsAttachment = true)
    {
        if (!$this->isAlive()) {
            return false;
        }

        try {
            return $this->generateFileDownloadLink($path, $booEnableCache, $booAsAttachment, $filename);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 's3');
        }

        return false;
    }


    /**
     * Get file's content
     *
     * @param string $path to the file in the cloud
     * @return false|string file's content (false on error or if the file doesn't exist)
     */
    public function getFileContent($path)
    {
        $content = false;

        try {
            if (!$this->isAlive()) {
                return false;
            }

            if ($this->checkObjectExists($path)) {
                $file = $this->getClient()->getObject(
                    array(
                        'Bucket'  => $this->getBucket(),
                        'Key'     => $this->preparePath($path),
                    )
                );

                $content = (string)$file->get('Body');
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 's3');
        }

        return $content;
    }


    /**
     * Download file to the temp file
     *
     * @param string $path to the file in the cloud
     * @return string path to the temp file
     */
    public function downloadFileContent($path)
    {
        if (!$this->isAlive()) {
            return '';
        }

        $tempFileName = tempnam($this->_config['directory']['tmp'], 'aws');
        try {
            $this->getClient()->getObject(
                array(
                    'Bucket'  => $this->getBucket(),
                    'Key'     => $this->preparePath($path),
                    'SaveAs'  => $tempFileName,
                )
            );
        } catch (Exception $e) {
            $tempFileName = '';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 's3');
        }

        return $tempFileName;
    }

    /**
     * Delete folder's content
     *
     * @param ResultPaginator $paginator
     * @return bool
     */
    private function _deleteFolderContent(ResultPaginator $paginator)
    {
        if (!$this->isAlive()) {
            return false;
        }

        $booSuccess = true;
        foreach ($paginator as $objectContainer) {
            $objectsToDelete = [];
            $batch           = [];
            if (isset($objectContainer['Contents'])) {
                foreach ($objectContainer['Contents'] as $object) {
                    $objectsToDelete[] = array(
                        'Key' => $this->preparePath((string)$object['Key'])
                    );
                }
            }

            if (!empty($objectsToDelete)) {
                $batch[] = $this->getClient()->getCommand(
                    'DeleteObjects',
                    array(
                        'Bucket'  => $this->getBucket(),
                        'Delete'  => array(
                            'Objects' => $objectsToDelete
                        ),
                    )
                );

                try {
                    $results = CommandPool::batch(
                        $this->getClient(),
                        $batch,
                        [
                            'concurrency' => self::MAX_REQUESTS_COUNT
                        ]
                    );
                    if (isset($results['Contents'])) {
                        foreach ($results['Contents'] as $contents) {
                            foreach ($contents as $content) {
                                if ($content instanceof AwsException) {
                                    $this->_log->debugErrorToFile(
                                        $content->getMessage(),
                                        $content->getTraceAsString(),
                                        's3'
                                    );
                                    $booSuccess = false;
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 's3');
                    $booSuccess = false;
                }
            }
        }

        return $booSuccess;
    }

    /**
     * Delete a folder or a file
     *
     * @param string $path to the object
     * @return bool true on success
     */
    public function deleteObject($path)
    {
        if (!$this->isAlive()) {
            return false;
        }

        $booSuccess = false;
        if ($this->isFolder($path)) {
            $objectsPaginator = $this->_getBucketObjects($this->preparePath($path));
            $booSuccess       = $this->_deleteFolderContent($objectsPaginator);
        } else {
            try {
                $this->getClient()->deleteObject(
                    array(
                        'Bucket' => $this->getBucket(),
                        'Key'    => $this->preparePath($path),
                    )
                );
                $booSuccess = true;
            } catch (Exception $e) {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 's3');
            }
        }

        return $booSuccess;
    }

    /**
     * Copy a folder or a file
     *
     * @param string $fromPath to the object
     * @param string $toPath to the object
     * @return bool true on success
     */
    public function copyObject($fromPath, $toPath)
    {
        if (!$this->isAlive()) {
            return false;
        }

        try {
            $this->getClient()->copyObject(
                array(
                    'Bucket'     => $this->getBucket(),
                    'CopySource' => $this->getBucket() . '/' . $this->preparePath($fromPath, true),
                    'Key'        => $this->preparePath($toPath),
                )
            );
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 's3');
            return false;
        }

        return true;
    }

    /**
     * Rename a folder or a file
     *
     * @param string $oldName to the object
     * @param string $newName to the object
     * @return bool true on success
     */
    public function renameObject($oldName, $newName)
    {
        if (!$this->isAlive()) {
            return false;
        }

        try {
            $booSuccess = $oldName === $newName;

            if (!$booSuccess) {
                $booSuccess = true;
                if ($this->isFolder($newName)) {
                    $prefix           = $this->preparePath($oldName);
                    $objectsPaginator = $this->_getBucketObjects($prefix);
                    foreach ($objectsPaginator as $objectContainer) {
                        $batch = array();
                        if (isset($objectContainer['Contents'])) {
                            foreach ($objectContainer['Contents'] as $object) {
                                $oldPlace = (string)$object['Key'];
                                $newPlace = str_replace($prefix, $newName, $oldPlace);
                                $batch[]  = $this->getClient()->getCommand(
                                    'CopyObject',
                                    array_merge(
                                        array(
                                            'Bucket'     => $this->getBucket(),
                                            'CopySource' => $this->getBucket() . '/' . $this->preparePath($oldPlace, true),
                                            'Key'        => $this->preparePath($newPlace),
                                        ),
                                        $this->getEncryptionOptions()
                                    )
                                );
                            }
                        }
                        $results = CommandPool::batch(
                            $this->getClient(),
                            $batch,
                            [
                                'concurrency' => self::MAX_REQUESTS_COUNT
                            ]
                        );
                        foreach ($results as $result) {
                            if ($result instanceof AwsException) {
                                $booSuccess = false;
                                break;
                            }
                        }
                    }

                    if ($booSuccess) {
                        $booSuccess = $this->_deleteFolderContent($objectsPaginator);
                    }

                    if ($booSuccess) {
                        // Don't check for result - can be a situation when this top dir not existed
                        // but were sub directories/files
                        $this->deleteObject($prefix);
                    }
                } else {
                    $booSuccess = $this->copyObject($oldName, $newName);
                    if ($booSuccess) {
                        $booSuccess = $this->deleteObject($oldName);
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 's3');
            $booSuccess = false;
        }

        return $booSuccess;
    }

    /**
     * Create a folder by a specified path
     *
     * @param string $path to the folder
     * @return bool true on success
     */
    public function createFolder($path)
    {
        if (!$this->isAlive()) {
            return false;
        }

        try {
            $this->getClient()->putObject(
                array_merge(
                    array(
                        'Bucket'  => $this->getBucket(),
                        'Key'     => $this->preparePath($path) . '/',
                        'Body'    => '',
                    ),
                    $this->getEncryptionOptions()
                )
            );
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 's3');
            return false;
        }

        return true;
    }

    /**
     * Create an object by specified path
     *
     * @param string $path to the object
     * @param string $contents contents of the file
     * @return bool true on success
     */
    public function createObject($path, $contents)
    {
        if (!$this->isAlive()) {
            return false;
        }

        try {
            $this->getClient()->putObject(
                array_merge(
                    array(
                        'Bucket' => $this->getBucket(),
                        'Key' => $this->preparePath($path),
                        'ContenType' => FileTools::getMimeByFileName($path),
                        'Body' => $contents,
                        'Metadata' => array(
                            self::HEADER_LAST_MODIFIED => date('r')
                        ),
                    ),
                    $this->getEncryptionOptions()
                )
            );
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 's3');
            return false;
        }

        return true;
    }

    /**
     * Upload a file by provided local path
     *
     * @param string $localPath
     * @param string $remotePath
     * @param bool $booPublicRead
     * @return bool true on success
     */
    public function uploadFile($localPath, $remotePath, $booPublicRead = false)
    {
        if (!$this->isAlive()) {
            return false;
        }

        try {
            $arrParams = array_merge(
                array(
                    'Bucket' => $this->getBucket(),
                    'Key' => $this->preparePath($remotePath),
                    'SourceFile' => $localPath,
                    'ContentType' => FileTools::getMimeByFileName($remotePath),
                    'ACL' => $booPublicRead ? 'public-read' : 'private',
                    'Metadata' => array(
                        self::HEADER_LAST_MODIFIED => date('r')
                    ),
                ),
                $this->getEncryptionOptions()
            );

            // If file exists, but there is no content in it -
            // we need to be sure that file will be created in S3.
            // That's why we set body
            if (!file_exists($localPath) || filesize($localPath) == 0) {
                unset($arrParams['SourceFile']);
                $arrParams['Body'] = ' ';
            }

            $this->getClient()->putObject($arrParams);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 's3');
            return false;
        }

        return true;
    }

    /**
     * Load the list of object headers
     *
     * @param string $path to the object
     * @return array|bool object headers
     */
    public function getObjectHeaders($path)
    {
        if (!$this->isAlive()) {
            return false;
        }

        $arrResult = array();
        try {
            $arrResult = $this->getClient()->headObject(
                array(
                    'Bucket'  => $this->getBucket(),
                    'Key'     => $this->preparePath($path),
                )
            );
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 's3');
        }

        return $arrResult;
    }

    /**
     * Return human readable file sizes.
     *
     * @param int $size (Required) Filesize in bytes.
     * @param string $unit (Optional) The maximum unit to use. Defaults to the largest appropriate unit.
     * @param string $default (Optional) The format for the return string. Defaults to `%01.2f %s`.
     * @return string The human-readable file size.
     * @author Ryan Parman <ryan@getcloudfusion.com>
     * @license http://www.php.net/license/3_01.txt PHP License
     * @author Aidan Lister <aidan@php.net>
     * @link http://aidanlister.com/repos/v/function.size_readable.php Original Function
     */
    public function sizeReadable($size, $unit = null, $default = null)
    {
        // Units
        $sizes = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB');
        $mod   = 1024;
        $ii    = count($sizes) - 1;

        // Max unit
        $unit = array_search((string)$unit, $sizes);
        if ($unit === false) {
            $unit = $ii;
        }

        // Return string
        if ($default === null) {
            $default = '%01.2f %s';
        }

        // Loop
        $i = 0;
        while ($unit != $i && $size >= 1024 && $i < $ii) {
            $size /= $mod;
            $i++;
        }

        return sprintf($default, $size, $sizes[$i]);
    }

    /**
     * Get object's size
     *
     * @param string $path to the object
     * @param bool $friendly_format return in a friendly format
     * @return int object filesize in bytes or the friendly format as a string
     * @throws Exception|null
     */
    public function getObjectFilesize($path, $friendly_format = false)
    {
        if (!$this->isAlive()) {
            return -1;
        }

        try {
            $result = $this->getObjectHeaders($path);
            $size   = !empty($result['ContentLength']) ? $result['ContentLength'] : false;
            if ($size !== false && $friendly_format) {
                $size = $this->sizeReadable($size);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 's3');
            return -1;
        }

        return $size;
    }

    /**
     * Returns object's last modified date + time
     *
     * @param string $path
     * @return false|int Unix timestamp
     */
    public function getObjectLastModifiedTime($path)
    {
        $lastModified = false;

        $headers = $this->getObjectHeaders($path);
        if ($headers) {
            // Use from S3
            if (isset($headers['LastModified'])) {
                /** @var DateTimeResult $lastModified */
                $lastModified = $headers['LastModified'];
                $lastModified = strtotime((string)$lastModified);
            }

            if (isset($headers['Metadata'])) {
                if (isset($headers['Metadata'][self::HEADER_LAST_MODIFIED])) {
                    // This can be our (uniques) custom meta tag
                    $lastModified = strtotime($headers['Metadata'][self::HEADER_LAST_MODIFIED]);
                } elseif (isset($headers['Metadata'][self::HEADER_S3CMD_ATTRS])) {
                    // Or if our tag is missing - use created by s3cmd
                    $arrAttributes = explode('/', $headers['Metadata'][self::HEADER_S3CMD_ATTRS]);
                    foreach ($arrAttributes as $strAttribute) {
                        $arrAttributeParsed = explode(':', $strAttribute ?? '');
                        if ($arrAttributeParsed[0] == 'mtime') {
                            $lastModified = $arrAttributeParsed[1];
                            break;
                        }
                    }
                }
            }
        }

        return $lastModified;
    }

    /**
     * Updates file's creation time
     *
     * @param string $path to the object
     * @param int $timestamp to set
     * @return bool true on success
     */
    public function updateObjectCreationTime($path, $timestamp)
    {
        if (!$this->isAlive()) {
            return false;
        }

        try {
            if (!is_numeric($timestamp) || empty($timestamp)) {
                $timestamp = time();
            }

            $this->getClient()->copyObject(
                array(
                    'Bucket'            => $this->getBucket(),
                    'CopySource'        => $this->getBucket() . '/' . $this->preparePath($path, true),
                    'Key'               => $this->preparePath($path),
                    'MetadataDirective' => 'REPLACE',
                    'Metadata'          => array(
                        self::HEADER_LAST_MODIFIED => date('r', $timestamp)
                    ),
                )
            );
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 's3');
            return false;
        }

        return true;
    }

    /**
     * Updates files creation time
     *
     * @param array $arrObjects (string $path to the object, int $timestamp to set)
     * @return bool true on success
     */
    public function updateObjectsTimestamps($arrObjects)
    {
        if (!$this->isAlive()) {
            return false;
        }

        $booSuccess = true;

        try {
            foreach (array_chunk($arrObjects, 1000) as $arrObjectSet) {
                $batch = array();
                foreach ($arrObjectSet as $object) {
                    $timestamp = $object['timestamp'] ?? null;
                    if (!is_numeric($timestamp) || empty($timestamp)) {
                        $timestamp = time();
                    }

                    $batch[] = $this->getClient()->getCommand(
                        'CopyObject',
                        array(
                            'Bucket'            => $this->getBucket(),
                            'CopySource'        => $this->getBucket() . '/' . $this->preparePath($object['path'], true),
                            'Key'               => $this->preparePath($object['path']),
                            'MetadataDirective' => 'REPLACE',
                            'Metadata'          => array(self::HEADER_LAST_MODIFIED => date('r', $timestamp)),
                        )
                    );
                }
                $results = CommandPool::batch(
                    $this->getClient(),
                    $batch,
                    [
                        'concurrency' => self::MAX_REQUESTS_COUNT
                    ]
                );
                foreach ($results as $result) {
                    if ($result instanceof AwsException) {
                        $booSuccess = false;
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 's3');
            $booSuccess = false;
        }

        return $booSuccess;
    }

    /**
     * Create folders in a batch
     *
     * @param array $arrFolders - path to each folder
     * @return bool true on success
     */
    public function createFolders($arrFolders)
    {
        if (!$this->isAlive()) {
            return false;
        }

        $booSuccess = true;
        try {
            foreach (array_chunk($arrFolders, 1000) as $arrFolderSet) {
                $batch = array();
                foreach ($arrFolderSet as $path) {
                    $batch[] = $this->getClient()->getCommand(
                        'CreateObject',
                        array_merge(
                            array(
                                'Bucket'  => $this->getBucket(),
                                'Key'     => $this->preparePath($path) . '/',
                                'Body'    => '',
                            ),
                            $this->getEncryptionOptions()
                        )
                    );
                }

                $results = CommandPool::batch(
                    $this->getClient(),
                    $batch,
                    [
                        'concurrency' => self::MAX_REQUESTS_COUNT
                    ]
                );
                foreach ($results as $result) {
                    if ($result instanceof AwsException) {
                        $booSuccess = false;
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 's3');
            $booSuccess = false;
        }

        return $booSuccess;
    }

}