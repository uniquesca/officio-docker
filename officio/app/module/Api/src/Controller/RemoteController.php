<?php

namespace Api\Controller;

use Clients\Service\Clients;
use Exception;
use Files\Service\Files;
use Laminas\Filter\StripTags;
use Laminas\Http\Client;
use Laminas\Http\Header\ContentDisposition;
use Laminas\Http\Headers;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Uniques\Php\StdLib\FileTools;
use Officio\Service\AuthHelper;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;

/**
 * API Outlook Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class RemoteController extends BaseController
{

    /** @var Clients */
    protected $_clients;

    /** @var AuthHelper */
    protected $_authHelper;

    /** @var Files */
    protected $_files;

    /** @var Company */
    protected $_company;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_clients    = $services[Clients::class];
        $this->_company    = $services[Company::class];
        $this->_authHelper = $services[AuthHelper::class];
        $this->_files      = $services[Files::class];
        $this->_encryption = $services[Encryption::class];
    }

    public function indexAction()
    {
        // Do nothing...
        $view = new ViewModel(
            ['content' => null]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');
        return $view;
    }


    /**
     * This is a simple function to check if current website is online
     * (used in Outlook plugin)
     */
    public function isonlineAction()
    {
        $result = 'Online';
        if ($this->_config['security']['session_timeout'] > 0 && $this->_auth->hasIdentity()) {
            $identity = $this->_auth->getIdentity();

            $expirationMinutes = 6;
            if (isset($identity->timeout) && ($identity->timeout > 0) && (time() > ($identity->timeout - $expirationMinutes * 60))) {
                $expirationIn = $identity->timeout - time();
                $expirationIn = max($expirationIn, 1);

                $result = 'Expiration in ' . $expirationIn . ' second(s)';
            }
        }

        $view = new ViewModel(
            ['content' => $result]
        );
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        return $view;
    }

    public function extendSessionAction()
    {
        // Session will be automatically extended in checkTimeoutHandler
        $view = new ViewModel(
            ['content' => 'Done']
        );
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        return $view;
    }

    /**
     *
     * This function decodes info encoded in Outlook
     *
     * @param string $string - encoded in Outlook
     * @return string - decoded
     */
    private function _decode($string)
    {
        $newString = '';

        for ($i = 0; $i < strlen($string ?? ''); $i++) {
            $ch     = $string[$i] ?? '';
            $chCode = ord($ch);

            if ($chCode >= 32 && $chCode <= 126) {
                if ($chCode > 79) {
                    $chCode = $chCode - 47;
                } else {
                    $chCode = $chCode + 47;
                }
            }

            $newString = $newString . chr($chCode);
        }

        return $newString;
    }


    /**
     * Remote login from Outlook
     *
     */
    public function loginAction()
    {
        try {
            $filter    = new StripTags();
            $strDecode = $filter->filter($this->findParam('decode'));
            $username  = $filter->filter($this->findParam('act', ''));
            $password  = $filter->filter($this->findParam('Id', ''));

            if ($strDecode != 'false') {
                $username = $this->_decode($username);
                $password = $this->_decode($password);
            }

            $username = substr(trim($username), 0, 50);
            $password = substr(trim($password), 0, $this->_settings->passwordMaxLength);

            $this->_authHelper->login($username, $password);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'outlook');
        }

        return $this->redirect()->toRoute('home');
    }

    /**
     * *** Start *** These methods are used in ODrive
     */
    public function loginAppAction()
    {
        $loginInfo = array();
        try {
            $filter    = new StripTags();
            $strDecode = $filter->filter($this->findParam('decode'));
            $username  = $filter->filter($this->findParam('act', ''));
            $password  = $filter->filter($this->findParam('Id', ''));

            if ($strDecode != 'false') {
                $username = $this->_decode($username);
                $password = $this->_decode($password);
            }

            $username = substr(trim($username), 0, 50);
            $password = substr(trim($password), 0, $this->_settings->passwordMaxLength);

            $loginInfo = $this->_authHelper->login($username, $password);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'outlook');
        }

        return new JsonModel($loginInfo);
    }


    public function recursiveUnsetKeys(&$array, $unwantedKeys)
    {
        foreach ($unwantedKeys as $key) {
            unset($array[$key]);
        }


        if (isset($array['time'])) {
            $array = array('lastModified' => date('r', $array['time'])) + $array;
            unset($array['time']);
        }

        if (isset($array['folder'])) {
            $array = array('type' => $array['folder'] == 1 ? 'd' : 'f') + $array;
            unset($array['folder']);
        }

        if (isset($array['filename'])) {
            $array = array('name' => $array['filename']) + $array;
            unset($array['filename']);
        }

        if (isset($array['el_id'])) {
            $array = array('id' => $array['el_id']) + $array;
            unset($array['el_id']);
        }

        if (isset($array['children']) && empty($array['children'])) {
            unset($array['children']);
        }

        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->recursiveUnsetKeys($value, $unwantedKeys);
            }
        }
    }

    protected function getEntryByPath($path, $structure)
    {
        $explodedPath = explode('/', $path ?? '');
        $result       = null;
        foreach ($structure as $entry) {
            if ($entry['filename'] == $explodedPath[0]) {
                if (isset($explodedPath[1])) {
                    $result = $this->getEntryByPath(substr($path ?? '', strlen($explodedPath[0] ?? '') + 1), $entry['children']);
                } elseif ($entry['folder']) {
                    $result = $entry['children'];
                } else {
                    $result = $entry;
                }
                break;
            }
        }
        return $result;
    }

    public function getDirStructureAction()
    {
        try {
            $filter    = new StripTags();
            $memberId  = $this->_auth->getCurrentUserId();
            $companyId = $this->_auth->getCurrentUserCompanyId();
            $path      = $filter->filter($this->findParam('path'));

            $memberType  = $this->_clients->getMemberTypeByMemberId($memberId);
            $booIsClient = $this->_clients->isMemberClient($memberType);
            $booLocal    = $this->_auth->isCurrentUserCompanyStorageLocal();

            $memberFolderPath = $this->_files->getMemberFolder($companyId, $memberId, $booIsClient, $booLocal);

            // get default folder access
            $allowedTypes      = $this->_auth->isCurrentUserClient() ? array('C', 'F', 'CD', 'SD', 'SDR') : array('D', 'SD', 'SDR');
            $arrRoles          = $this->_clients->getMemberRoles($memberId);
            $arrDefaultFolders = $this->_files->getDefaultFoldersAccess($companyId, $memberId, $booIsClient, $booLocal, $arrRoles, $allowedTypes);

            if ($booLocal) {
                $this->_files->createFTPDirectory($memberFolderPath);
                $userDataArr = $this->_files->_loadFTPFoldersAndFilesList($memberFolderPath, $arrDefaultFolders, 'D', $memberId, false);
            } else {
                $userDataArr = $this->_files->_loadMemberCloudFoldersAndFilesList($memberFolderPath, $arrDefaultFolders, 'D', $memberId, false);
            }

            if (!empty($path)) {
                $userDataArr = $this->getEntryByPath(trim($path, '/'), $userDataArr);
            }

            $this->recursiveUnsetKeys(
                $userDataArr,
                array(
                    'uiProvider',
                    'leaf',
                    'allowDrag',
                    'allowDrop',
                    'allowChildren',
                    'date',
                    'cls',
                    'expanded',
                    'filesize',
                    'iconCls',
                    'path_hash',
                    'checked',
                    'allowRW',
                    'allowEdit',
                    'isDefaultFolder',
                    'allowDeleteFolder',
                    'allowSaveToInbox',
                )
            );
        } catch (Exception $e) {
            $userDataArr = [];
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return new JsonModel(array('struct' => $userDataArr));
    }
    /**
     * *** END *** These methods are used in ODrive
     */


    /**
     * Load clients list for user
     * identified by login and password
     *
     * Result will be echoed in xml format
     */
    public function getClientsListAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        $arrClients = array();
        $booSuccess = false;

        try {
            $username = substr(trim($this->findParam('Login', '')), 0, 50);
            $password = substr(trim($this->findParam('Password', '')), 0, $this->_settings->passwordMaxLength);

            $arrResult = $this->_authHelper->login($username, $password);

            if ($arrResult['success']) {
                // Load clients list for this user
                $arrMemberClients = $this->_clients->getClientsList();

                if (is_array($arrMemberClients) && count($arrMemberClients)) {
                    $arrClientsIds = array();
                    foreach ($arrMemberClients as $arrClientInfo) {
                        $arrClientsIds[] = $arrClientInfo['member_id'];
                    }

                    // Get only active clients
                    $arrActiveClients = $this->_clients->getActiveClientsList($arrClientsIds);
                    foreach ($arrActiveClients as $arrClientInfo) {
                        $arrClients[] = array(
                            'id'           => $arrClientInfo['member_id'],
                            'cn_Mandate'   => $arrClientInfo['fileNumber'],
                            'cn_Last_Name' => $arrClientInfo['lName'],
                            'cn_FirstName' => $arrClientInfo['fName'],
                            'cn_Email'     => $arrClientInfo['emailAddress'],
                        );
                    }
                }

                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'outlook');
        }

        // Generate and return result
        $headers = new Headers();
        $headers->addHeaders(
            [
                "Content-Type"  => "application/xml; charset=UTF-8",
                "Cache-Control" => "no-store",
                "Pragma"        => "no-cache",
            ]
        );
        $this->getResponse()->setHeaders($headers);

        $XMLENCODING = 'UTF-8';

        $xmlResult = "<?xml version='1.0' encoding='" . $XMLENCODING . "'?>\n";
        $xmlResult .= "<TIMC>\n";
        $strResult = $booSuccess ? 'OK' : 'ERROR';
        $xmlResult .= "<Select Result=\"$strResult\"/>\n";
        $xmlResult .= "<UsersNum>" . count($arrClients) . "</UsersNum>\n";
        $xmlResult .= "<Users>\n";

        // $xmlResult .= "<!-- U - Users; M - Mandate; L - LastName; F - FirstName; E - Email -->\n";
        foreach ($arrClients as $arrClientInfo) {
            $xmlResult .= "\t<U>\n";
            $xmlResult .= "\t\t<Id>" . $arrClientInfo['id'] . "</Id>\n";
            $xmlResult .= "\t\t<M>" . $arrClientInfo['cn_Mandate'] . "</M>\n";
            $xmlResult .= "\t\t<L>" . $arrClientInfo['cn_Last_Name'] . "</L>\n";
            $xmlResult .= "\t\t<F>" . $arrClientInfo['cn_FirstName'] . "</F>\n";
            $xmlResult .= "\t\t<E>" . $arrClientInfo['cn_Email'] . "</E>\n";
            $xmlResult .= "\t</U>\n";
        }

        $xmlResult .= "</Users>\n";
        $xmlResult .= "</TIMC>\n";

        $view->setVariable('content', $xmlResult);

        return $view;
    }


    /**
     * Save received email (msg format) from Outlook.
     * User will be identified by login and password.
     *
     * Text result will be echoed.
     */
    public function parkEmailAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        $booSuccess = false;
        try {
            $username = substr(trim($this->findParam('Login', '')), 0, 50);
            $password = substr(trim($this->findParam('Password', '')), 0, $this->_settings->passwordMaxLength);

            $memberId   = (int)$this->findParam('nClientId');
            $memberInfo = $this->_clients->getMemberInfo($memberId);
            $companyId  = $memberInfo['company_id'];
            $isLocal    = $this->_company->isCompanyStorageLocationLocal($companyId);

            if (!empty($memberId)) {
                $arrResult = $this->_authHelper->login($username, $password);

                if ($arrResult['success']) {
                    if ($this->_clients->isAlowedClient($memberId) && array_key_exists('file', $_FILES) && array_key_exists('tmp_name', $_FILES['file'])) {
                        $file = $_FILES['file'];
                        // FTP file
                        $fileNewPath = $this->_files->getClientCorrespondenceFTPFolder($companyId, $memberId, $isLocal);

                        // Create directory if it is not created or deleted
                        if ($this->_auth->isCurrentUserCompanyStorageLocal()) {
                            $this->_files->createFTPDirectory($fileNewPath);
                        } else {
                            $this->_files->createCloudDirectory($fileNewPath);
                        }

                        // Copy received file in client's communication folder
                        $clearFileName = $file['name'] ?? '';

                        // In some cases encoding is incorrect
                        $cur_encoding = mb_detect_encoding($clearFileName);
                        if ($cur_encoding != "UTF-8" || !mb_check_encoding($clearFileName, "UTF-8")) {
                            $clearFileName = utf8_encode($clearFileName);
                        }


                        $filePath = $fileNewPath . '/' . FileTools::cleanupFileName($clearFileName);

                        // Check if file already exists - add (x)
                        $i = 1;
                        if ($this->_auth->isCurrentUserCompanyStorageLocal()) {
                            while (file_exists($filePath)) {
                                $fileExt = FileTools::getFileExtension($clearFileName);

                                if (!empty($fileExt)) {
                                    $newFileName = mb_substr($clearFileName, 0, mb_strlen($clearFileName) - mb_strlen($fileExt) - 1) . "($i)." . $fileExt;
                                } else {
                                    $newFileName = $clearFileName . "($i)";
                                }
                                $i++;

                                $filePath = $fileNewPath . '/' . FileTools::cleanupFileName($newFileName);
                            }

                            $this->_files->createFTPDirectory(dirname($filePath));
                            $booSuccess = @copy($file['tmp_name'], $filePath);
                        } else {
                            while ($this->_files->getCloud()->checkObjectExists($filePath)) {
                                $fileExt = FileTools::getFileExtension($clearFileName);

                                if (!empty($fileExt)) {
                                    $newFileName = mb_substr($clearFileName, 0, mb_strlen($clearFileName) - mb_strlen($fileExt) - 1) . "($i)." . $fileExt;
                                } else {
                                    $newFileName = $clearFileName . "($i)";
                                }
                                $i++;

                                $filePath = $fileNewPath . '/' . FileTools::cleanupFileName($newFileName);
                            }

                            $booSuccess = $this->_files->getCloud()->uploadFile($file['tmp_name'], $filePath);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'outlook');
        }

        $view->setVariable('content', $booSuccess ? 'YES' : 'No');

        return $view;
    }

    /**
     * Just a proxy method, so we'll not show the real url
     */
    public function getFileAction()
    {
        ini_set('memory_limit', '512M');

        // Flushing and ending all buffers, so no extra output it messed into the files, including CSRF
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        $response = $this->getResponse();
        // Clear previously sent headers
        $response->setHeaders(new Headers());

        // TODO PHP7 We need to add additional check so this cannot be exploited to DDOS external sites
        $url = '';
        try {
            $encUrl = $this->findParam('enc');
            $url    = empty($encUrl) ? '' : $this->_encryption->decode($encUrl);

            if (!empty($url)) {
                $config = array(
                    'adapter'     => 'Laminas\Http\Client\Adapter\Curl',
                    'curloptions' => array(CURLOPT_SSL_VERIFYPEER => false),
                );

                $client = new Client($url, $config);
                $client->setMethod('GET');
                $result = $client->send();

                // Files names from S3 come urlencoded, let's decode them
                $headers           = $result->getHeaders();
                $dispositionHeader = $headers->get('Content-Disposition');
                if ($dispositionHeader) {
                    $disposition = $dispositionHeader->getFieldValue();
                    if (preg_match('/(inline|attachment)(?>;\s*filename=["\'](.+)["\'])?/', $disposition, $matches)) {
                        if (sizeof($matches) > 2) {
                            $type     = $matches[1];
                            $filename = urldecode($matches[2]);

                            // We encode file name here: _encodeFileName, so try to decode now
                            if (preg_match('/=\?UTF-8\?B\?(.*)\?=/', $filename, $regs)) {
                                $filenameDecoded = base64_decode($regs[1]);
                                if ($filenameDecoded !== false) {
                                    $filename = $filenameDecoded;
                                }
                            }

                            $filename       = FileTools::cleanupFileName($filename);
                            $newDisposition = "$type; filename=\"$filename\";";
                        } else {
                            $newDisposition = $disposition;
                        }
                        $newDispositionHeader = new ContentDisposition($newDisposition);
                        $headers->removeHeader($dispositionHeader);
                        $headers->addHeader($newDispositionHeader);
                    }
                }
                $response->setHeaders($headers);
                $response->setContent($result->getBody());
            } else {
                $response->setStatusCode(404);
                $response->setContent('File not found.');
            }
        } catch (Exception $e) {
            $details = $e->getTraceAsString();

            if (!empty($url)) {
                $details .= PHP_EOL . 'URL: ' . $url;
            }

            $this->_log->debugErrorToFile($e->getMessage(), $details);

            $response->setStatusCode(500);
            $response->setContent('Application error');
        }

        return $response;
    }
}
