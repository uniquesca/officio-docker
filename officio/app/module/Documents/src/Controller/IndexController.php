<?php

namespace Documents\Controller;

use Clients\Service\Clients;
use Documents\Service\Documents;
use Exception;
use Files\Model\FileInfo;
use Files\Service\Files;
use Laminas\Filter\StripTags;
use Laminas\Http\Client;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Mailer\Service\Mailer;
use Notes\Service\Notes;
use Officio\BaseController;
use Uniques\Php\StdLib\FileTools;
use Officio\Common\Service\AccessLogs;
use Officio\Email\Models\MailAccount;
use Officio\Email\Storage\Message;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Service\Letterheads;
use Officio\Service\SystemTriggers;
use Prospects\Service\CompanyProspects;
use Templates\Service\Templates;
use Uniques\Php\StdLib\StringTools;

/**
 * Documents Index Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class IndexController extends BaseController
{

    /** @var Notes */
    protected $_notes;
    /** @var Clients */
    protected $_clients;
    /** @var Company */
    protected $_company;
    /** @var Letterheads */
    protected $_letterheads;
    /** @var Files */
    protected $_files;
    /** @var CompanyProspects */
    protected $_companyProspects;
    /** @var Templates */
    protected $_templates;
    /** @var Mailer */
    protected $_mailer;
    /** @var SystemTriggers */
    protected $_triggers;
    /** @var Encryption */
    protected $_encryption;
    /** @var Documents */
    private $_documents;
    /** @var AccessLogs */
    protected $_accessLogs;

    public function initAdditionalServices(array $services)
    {
        $this->_company          = $services[Company::class];
        $this->_notes            = $services[Notes::class];
        $this->_clients          = $services[Clients::class];
        $this->_files            = $services[Files::class];
        $this->_documents        = $services[Documents::class];
        $this->_letterheads      = $services[Letterheads::class];
        $this->_companyProspects = $services[CompanyProspects::class];
        $this->_templates        = $services[Templates::class];
        $this->_mailer           = $services[Mailer::class];
        $this->_triggers         = $services[SystemTriggers::class];
        $this->_encryption       = $services[Encryption::class];
        $this->_accessLogs       = $services[AccessLogs::class];
    }

    public function indexAction()
    {
        $view = new ViewModel(
            ['content' => null]
        );
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        return $view;
    }

    public function getTreeAction()
    {
        $view = new JsonModel();

        set_time_limit(5 * 60); // 5 minutes
        ini_set('memory_limit', '512M');
        session_write_close();

        $strError  = '';
        $arrResult = array();
        try {
            $member_id = (int)$this->findParam('member_id');
            $member_id = empty($member_id) ? $this->_auth->getCurrentUserId() : $member_id;

            if ($this->_members->hasCurrentMemberAccessToMember($member_id)) {
                // cookie to check, whether a user hide the shared workspace
                $arrCookies    = $this->_serviceManager->get('Request')->getCookie();
                $booShowShared = !isset($arrCookies['ys-hide_shared_workspace']) || $arrCookies['ys-hide_shared_workspace'] !== '1';

                // Don't return top level folders (and all their inner content) if this is not allowed by access rights based on the office
                $noTopLevelAccess = false;
                $memberInfo       = $this->_clients->getMemberInfo($member_id);
                $isSuperadmin     = $this->_auth->isCurrentUserSuperadmin();
                $isClient         = $this->_clients->isMemberClient($this->_clients->getMemberTypeByMemberId($member_id));
                $arrRoles         = $this->_clients->getMemberRoles($this->_auth->getCurrentUserId());

                if (!$this->_auth->isCurrentUserAdmin()) {
                    $arrMemberOffices = $this->_clients->getApplicantOffices(array($member_id), $memberInfo['division_group_id']);
                    $noTopLevelAccess = $this->_company->getCompanyDivisions()->getFoldersByAccessToDivision($arrMemberOffices, '');
                }

                $booLocal = $this->_company->isCompanyStorageLocationLocal($memberInfo['company_id']);

                // Get default folder access
                $allowedTypes      = ($isClient || $this->_auth->isCurrentUserClient()) ? array('C', 'F', 'CD', 'SD', 'SDR') : array('D', 'SD', 'SDR');
                $arrDefaultFolders = $this->_files->getDefaultFoldersAccess($memberInfo['company_id'], $member_id, $isClient, $booLocal, $arrRoles, $allowedTypes);

                $arrResult = $this->_files->loadMemberFoldersAndFilesList($memberInfo['company_id'], $member_id, $booLocal, $isClient, $arrDefaultFolders, $isSuperadmin);
                if ($booShowShared) {
                    // Get Shared docs folder
                    $arrSharedDocsInfo = $this->_files->getCompanySharedDocsPath($memberInfo['company_id'], true, $booLocal);
                    $pathToShared      = $arrSharedDocsInfo[0] . '/' . $arrSharedDocsInfo[1];
                    $access            = $this->_company->getCompanyDivisions()->getFolderPathAndAccess($arrDefaultFolders, $pathToShared, $member_id, '');

                    if ($access) {
                        $arrMemberOffices           = $this->_clients->getApplicantOffices(array($member_id), $memberInfo['division_group_id']);
                        $arrThisUserNoAccessFolders = $this->_company->getCompanyDivisions()->getFoldersByAccessToDivision($arrMemberOffices, '');
                        $sharedList                 = $this->_files->loadSharedFolderAndFiles($memberInfo['company_id'], $member_id, $booLocal, $arrDefaultFolders, $arrThisUserNoAccessFolders, $noTopLevelAccess, $access);
                        $arrResult                  = array_merge($arrResult, $sharedList);
                    }
                }

                if (!is_array($arrResult)) {
                    $strError = $booLocal ?
                        $this->_tr->translate('An error happened. Please refresh files/folders list.') :
                        $this->_tr->translate('Connection to Amazon S3 lost. Please refresh files/folders list.');
                }
            } else {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = empty($strError) ? $arrResult : array('error' => $strError);
        return $view->setVariables($arrResult);
    }

    public function moveFilesAction()
    {
        $view = new JsonModel();

        $success = false;
        try {
            $filter    = new StripTags();
            $files     = Json::decode($this->findParam('files'), Json::TYPE_ARRAY);
            $folder_id = $filter->filter($this->_encryption->decode($this->findParam('folder_id')));
            $member_id = (int)$this->findParam('member_id');
            $member_id = empty($member_id) ? $this->_auth->getCurrentUserId() : $member_id;

            $memberInfo = $this->_members->getMemberInfo($member_id);
            $companyId  = $memberInfo['company_id'];
            $booLocal   = $this->_company->isCompanyStorageLocationLocal($companyId);

            if ($this->_members->hasCurrentMemberAccessToMember($member_id) &&
                ($this->_company->getCompanyDivisions()->canCurrentMemberEditClient($member_id) || $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled())) {
                $allowedTypes = array('C', 'F', 'CD', 'SD', 'SDR');
                $arrRoles     = $this->_clients->getMemberRoles($this->_auth->getCurrentUserId());
                $memberType   = $this->_clients->getMemberTypeByMemberId($member_id);
                $isClient     = $this->_clients->isMemberClient($memberType);
                $arrFolders   = $this->_files->getDefaultFoldersAccess($companyId, $member_id, $isClient, $booLocal, $arrRoles, $allowedTypes);

                if ($this->_company->getCompanyDivisions()->getFolderPathAndAccess($arrFolders, $folder_id, $member_id, '') == 'RW') {
                    //decode files
                    $access = true;
                    foreach ($files as &$file) {
                        $file   = $this->_encryption->decode($file);
                        $access = $this->_clients->checkClientFolderAccess($member_id, $file);
                        if (!$access) {
                            break;
                        }
                    }

                    if ($access && $this->_clients->checkClientFolderAccess($member_id, $folder_id)) {
                        $success = $this->_documents->moveFiles($files, $folder_id);
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => $success));
    }


    public function copyFilesAction()
    {
        $strError = '';

        try {
            $arrFiles   = Json::decode($this->params()->fromPost('files'), Json::TYPE_ARRAY);
            $folderPath = $this->_encryption->decode($this->params()->fromPost('folder_id'));
            $memberId   = (int)$this->params()->fromPost('member_id');
            $memberId   = empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId;
            $memberInfo = $this->_members->getMemberInfo($memberId);
            $companyId  = $memberInfo['company_id'];
            $booLocal   = $this->_company->isCompanyStorageLocationLocal($companyId);

            if ($this->_members->hasCurrentMemberAccessToMember($memberId) && ($this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId) || $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled())) {
                $allowedTypes = array('C', 'F', 'CD', 'SD', 'SDR');
                $arrRoles     = $this->_clients->getMemberRoles($this->_auth->getCurrentUserId());
                $memberType   = $this->_clients->getMemberTypeByMemberId($memberId);
                $booIsClient  = $this->_clients->isMemberClient($memberType);
                $arrFolders   = $this->_files->getDefaultFoldersAccess($companyId, $memberId, $booIsClient, $booLocal, $arrRoles, $allowedTypes);

                if ($this->_company->getCompanyDivisions()->getFolderPathAndAccess($arrFolders, $folderPath, $memberId, '') != 'RW') {
                    $strError = $this->_tr->translate('Insufficient access rights to the folder.');
                }

                if (empty($strError) && !$this->_clients->checkClientFolderAccess($memberId, $folderPath)) {
                    $strError = $this->_tr->translate('Insufficient access rights to the folder.');
                }

                if (empty($strError) && (empty($arrFiles) || !is_array($arrFiles))) {
                    $strError = $this->_tr->translate('Please select a file to copy.');
                }

                if (empty($strError)) {
                    foreach ($arrFiles as &$file) {
                        $file = $this->_encryption->decode($file);
                        if (!$this->_clients->checkClientFolderAccess($memberId, $file)) {
                            $strError = $this->_tr->translate('Insufficient access rights to the file.');
                            break;
                        }
                    }
                }
            } else {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && !$this->_documents->copyFiles($arrFiles, $folderPath)) {
                $strError = $this->_tr->translate('File(s) was not copied. Please try again later.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }


    public function deleteAction()
    {
        try {
            $strError = '';
            $memberId = (int)$this->params()->fromPost('member_id');
            $arrNodes = Json::decode($this->params()->fromPost('nodes'), Json::TYPE_ARRAY);

            if (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_clients->isAlowedClient($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $companyId = 0;
            $booLocal  = false;
            $isClient  = false;
            if (empty($strError)) {
                $memberInfo = $this->_clients->getMemberInfo($memberId);
                $companyId  = $memberInfo['company_id'];
                $booLocal   = $this->_company->isCompanyStorageLocationLocal($companyId);
                $memberType = $this->_clients->getMemberTypeByMemberId($memberId);
                $isClient   = $this->_clients->isMemberClient($memberType);
            }

            if (empty($strError) && empty($arrNodes)) {
                $strError = $this->_tr->translate('Nothing to delete.');
            }

            $arrDirsToCheck = [];
            $arrDeleted     = [];
            if (empty($strError)) {
                // Decode nodes
                $pathToClientDocs = $this->_files->getMemberFolder($companyId, $memberId, $isClient, $booLocal);
                foreach ($arrNodes as &$node) {
                    $node = $this->_encryption->decode($node);
                    // Check if we have access to the file/folder
                    if (!$this->_clients->checkClientFolderAccess($memberId, $node)) {
                        $strError = $this->_tr->translate('Insufficient access rights to the file/folder.');
                        break;
                    }

                    $arrDeleted[] = str_replace($pathToClientDocs, '', $node);
                    if ($booLocal) {
                        if (is_dir($node)) {
                            $arrDirsToCheck[] = $node;
                        }
                    } elseif ($this->_files->getCloud()->isFolder($node)) {
                        $arrDirsToCheck[] = $node;
                    }
                }
            }

            if (empty($strError)) {
                $booAccess = false;
                if ($this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId) || $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled()) {
                    $allowedTypes = array('C', 'F', 'CD', 'SD', 'SDR');
                    $arrRoles     = $this->_clients->getMemberRoles($this->_auth->getCurrentUserId());
                    $arrFolders   = $this->_files->getDefaultFoldersAccess($companyId, $memberId, $isClient, $booLocal, $arrRoles, $allowedTypes);
                    foreach ($arrNodes as $objectPath) {
                        if ($this->_company->getCompanyDivisions()->getFolderPathAndAccess($arrFolders, $objectPath, $memberId, '') != 'RW') {
                            $booAccess = false;
                            break;
                        } else {
                            $booAccess = true;
                        }
                    }
                }

                if (!$booAccess) {
                    $strError = $this->_tr->translate('Insufficient access rights to the folder/file.');
                }
            }

            if (empty($strError)) {
                if ($this->_files->delete($arrNodes, $booLocal)) {
                    // Delete top level folders from the DB if they were used in the "office access rights"
                    $topSharedDocsPath = empty($companyId) ? '' : $this->_files->getCompanySharedDocsPath($companyId, false, $booLocal);
                    foreach ($arrDirsToCheck as $fullFolderPath) {
                        if (strpos($fullFolderPath, $topSharedDocsPath) === 0) {
                            $folderName = trim(substr($fullFolderPath, -1 * (strlen($fullFolderPath) - strlen($topSharedDocsPath))), '\/');
                            if (strpos($folderName, '/') === false) {
                                $this->_company->getCompanyDivisions()->deleteFolderBasedOnDivisionAccess($companyId, $folderName);
                            }
                        }
                    }

                    // Log this action
                    if ($isClient && !empty($arrDeleted)) {
                        $arrLog = array(
                            'log_section'           => 'client',
                            'log_action'            => 'file_or_folder_deleted',
                            'log_description'       => trim('In Case Documents - Deleted: ' . implode(', ', $arrDeleted)),
                            'log_company_id'        => $companyId,
                            'log_created_by'        => $this->_auth->getCurrentUserId(),
                            'log_action_applied_to' => $memberId,
                        );
                        $this->_accessLogs->saveLog($arrLog);
                    }
                } else {
                    $strError = $this->_tr->translate('Internal error.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }

    public function filesUploadAction()
    {
        set_time_limit(5 * 60); // 5 minutes
        session_write_close();

        $strError = '';
        try {
            //get params
            $memberId = (int)$this->findParam('member_id');
            $memberId   = empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId;
            $folderId   = $this->findParam('folder_id');
            $folderId   = $folderId == 'root' ? 'root' : $this->_encryption->decode($folderId);
            $filesCount = (int)$this->findParam('files');

            $memberInfo = $this->_members->getMemberInfo($memberId);
            $companyId  = $memberInfo['company_id'];
            $booLocal   = $this->_company->isCompanyStorageLocationLocal($companyId);
            $memberType = $this->_clients->getMemberTypeByMemberId($memberId);
            $isClient   = $this->_clients->isMemberClient($memberType);

            if (!$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $fileNewPath = $folderId == 'root'
                ? $this->_files->getMemberFolder($companyId, $memberId, $isClient, $booLocal)
                : $folderId;

            if (empty($strError) && !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
                if ($this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled()) {
                    $allowedTypes = array('C', 'F', 'CD', 'SD', 'SDR');
                    $arrRoles     = $this->_clients->getMemberRoles($this->_auth->getCurrentUserId());
                    $arrFolders   = $this->_files->getDefaultFoldersAccess($companyId, $memberId, $isClient, $booLocal, $arrRoles, $allowedTypes);
                    if ($this->_company->getCompanyDivisions()->getFolderPathAndAccess($arrFolders, $fileNewPath, $memberId, '') != 'RW') {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                } else {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }
            }

            $arrFiles = array();
            if (empty($strError)) {
                //get files info and size
                for ($i = 0; $i < $filesCount; $i++) {
                    $id = 'docs-upload-file-' . $i;
                    if (!empty($_FILES[$id]['name']) && !empty($_FILES[$id]['tmp_name'])) {
                        $arrFiles[$i] = $_FILES[$id];
                    }
                }

                // When drag and drop method was used - receive data in other format
                if (empty($arrFiles) && isset($_FILES['docs-upload-file']) && isset($_FILES['docs-upload-file']['tmp_name'])) {
                    if (is_array($_FILES['docs-upload-file']['tmp_name'])) {
                        for ($i = 0; $i < $filesCount; $i++) {
                            if (isset($_FILES['docs-upload-file']['tmp_name'][$i]) && !empty($_FILES['docs-upload-file']['tmp_name'][$i])) {
                                $arrFiles[$i] = array(
                                    'name'     => $_FILES['docs-upload-file']['name'][$i],
                                    'type'     => $_FILES['docs-upload-file']['type'][$i],
                                    'tmp_name' => $_FILES['docs-upload-file']['tmp_name'][$i],
                                    'error'    => $_FILES['docs-upload-file']['error'][$i],
                                    'size'     => $_FILES['docs-upload-file']['size'][$i],
                                );
                            }
                        }
                    } elseif (!empty($_FILES['docs-upload-file']['tmp_name'])) {
                        $arrFiles[$i] = $_FILES['docs-upload-file'];
                    }
                }

                $booSuccess = false;
                $folderName = '';

                foreach ($arrFiles as $file) {
                    $extension = FileTools::getFileExtension($file['name']);
                    if (!$this->_files->isFileFromWhiteList($extension)) {
                        $strError = $this->_tr->translate('File type is not from whitelist.');
                        break;
                    }
                }

                if (empty($strError) && !$this->_clients->checkClientFolderAccess($memberId, $fileNewPath)) {
                    $strError = $this->_tr->translate('Insufficient access rights (to folder).');
                }

                $arrUploadedFiles = [];
                if (empty($strError)) {
                    if ($booLocal) {
                        $this->_files->createFTPDirectory($fileNewPath);
                        $folderName = dirname($fileNewPath);
                    } else {
                        $folderName = $this->_files->getCloud()->getFolderNameByPath($fileNewPath);
                    }

                    foreach ($arrFiles as $file) {
                        if ($booLocal) {
                            $booSuccess = move_uploaded_file($file['tmp_name'], $fileNewPath . '/' . FileTools::cleanupFileName($file['name']));
                        } else {
                            $fileNewPath = $this->_files->getCloud()->isFolder($fileNewPath) ? $fileNewPath : $fileNewPath . '/';
                            $filePath    = $this->_files->getCloud()->preparePath($fileNewPath) . FileTools::cleanupFileName($file['name']);

                            $booSuccess = $this->_files->getCloud()->uploadFile($file['tmp_name'], $filePath);
                        }

                        if (!$booSuccess) {
                            break;
                        }

                        $arrUploadedFiles[] = $file['name'];
                    }
                }

                if ($booSuccess && empty($strError)) {
                    $booEnableNotesCreation = (bool)$this->_config['site_version']['create_note_on_file_upload'];
                    if ($booEnableNotesCreation && !empty($arrUploadedFiles)) {
                        $note = sprintf(
                            'User %s has uploaded %s %s',
                            $this->_members->getCurrentMemberName(true),
                            count($arrUploadedFiles) == 1 ? 'file' : 'files:',
                            implode(', ', $arrUploadedFiles)
                        );

                        if ($this->_notes->updateNote(0, $memberId, $note, true)) {
                            $this->_company->updateLastField(false, 'last_note_written');
                        }
                    }

                    // Update last_doc_uploaded field for company
                    $this->_company->updateLastField($memberId, 'last_doc_uploaded');

                    // TRIGGER: When a client uploaded document(s)
                    if ($this->_auth->isCurrentUserClient()) {
                        $this->_triggers->triggerUploadedDocuments(
                            $this->_auth->getCurrentUserCompanyId(),
                            $memberId
                        );
                    }

                    if ($this->_auth->isCurrentUserAuthorizedAgent() && $folderName == 'Additional Documents') {
                        $arrParents  = $this->_clients->getParentsForAssignedApplicants(array($memberId));
                        $applicantId = $arrParents[$memberId]['parent_member_id'] ?? 0;

                        if ($this->_company->getCompanyDivisions()->isClientSubmittedToGovernment($applicantId)) {
                            $this->_triggers->triggerUploadedAdditionalDocuments(
                                $this->_auth->getCurrentUserCompanyId(),
                                $memberId
                            );
                        }
                    }
                }

                if (!$booSuccess && empty($strError)) {
                    $strError = $this->_tr->translate('File(s) was not provided or was not uploaded.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $this->getResponse()->setStatusCode(500);
        }

        return new JsonModel(array('success' => empty($strError), 'error' => $strError));
    }

    /**
     * Upload files from a Dropbox URL
     *
     * Can also be used for any URL.
     */
    public function filesUploadFromDropboxAction()
    {
        set_time_limit(5 * 60); // 5 minutes
        session_write_close();

        $strError = '';
        try {
            //get params
            $memberId = (int)$this->params()->fromPost('member_id');
            $memberId   = empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId;
            $folderId   = $this->params()->fromPost('folder_id');
            $folderId   = $folderId == 'root' ? 'root' : $this->_encryption->decode($folderId);

            $memberInfo = $this->_members->getMemberInfo($memberId);
            $companyId  = $memberInfo['company_id'];
            $booLocal   = $this->_company->isCompanyStorageLocationLocal($companyId);
            $memberType = $this->_clients->getMemberTypeByMemberId($memberId);
            $isClient   = $this->_clients->isMemberClient($memberType);

            if (!$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $fileNewPath = $folderId == 'root'
                ? $this->_files->getMemberFolder($companyId, $memberId, $isClient, $booLocal)
                : $folderId;

            if (empty($strError) && !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
                if ($this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled()) {
                    $allowedTypes = array('C', 'F', 'CD', 'SD', 'SDR');
                    $arrRoles     = $this->_clients->getMemberRoles($this->_auth->getCurrentUserId());
                    $arrFolders   = $this->_files->getDefaultFoldersAccess($companyId, $memberId, $isClient, $booLocal, $arrRoles, $allowedTypes);
                    if ($this->_company->getCompanyDivisions()->getFolderPathAndAccess($arrFolders, $fileNewPath, $memberId, '') != 'RW') {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                } else {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }
            }

            $fileUrl  = $this->params()->fromPost('file_url', '');
            $fileName = urldecode(basename($fileUrl));
            $fileName = explode('?', $fileName)[0];
            $fileExt  = pathinfo($fileName, PATHINFO_EXTENSION);
            $dontOverwrite = false;

            // Special handling for dropbox/google drive sharing links
            if (empty($strError)
                && (strpos($fileUrl, 'https://www.dropbox.com') === 0
                    || strpos($fileUrl, 'https://drive.google.com') === 0)){

                list($strError, $fileUrl, $fileName, $fileExt) = $this->_files->parseDropboxGoogleShareLink($fileUrl);

                // Filenames for these links are not processed on client side, so there isn't any warning for an overwrite.
                $dontOverwrite = true;
            }

            if (empty($strError) && !$this->_files->isFileFromWhiteList($fileExt)) {
                $strError = $this->_tr->translate('File type is not from whitelist.');
            }

            if(empty($strError)) {
                $fileContentMaxLenInMemory = 1024 * 1024 * 21; // 21MB
                $fileContentMaxLen         = 1024 * 1024 * 20; // 20MB
                $fileContent               = file_get_contents($fileUrl, false, null, 0, $fileContentMaxLenInMemory);

                if($fileContent === false){
                    $strError = $this->_tr->translate('Invalid file URL.');
                } elseif(strlen($fileContent) > $fileContentMaxLen){
                    $strError = $this->_tr->translate('File size too large (exceeded 20MB).');
                }
            }

            if (empty($strError)) {
                if ($this->_clients->checkClientFolderAccess($memberId, $fileNewPath)) {
                    $booLocal   = $this->_auth->isCurrentUserCompanyStorageLocal();

                    // Download file to temp
                    $tempName = tempnam($this->_config['directory']['tmp'], 'TMP_');
                    file_put_contents($tempName, $fileContent);

                    if ($booLocal) {

                        if($dontOverwrite){
                            if (file_exists($fileNewPath . '/' . FileTools::cleanupFileName($fileName))) {
                                $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '_' . date('Y-m-d_H-i-s') . '.' . $fileExt;
                            }
                        }

                        $booSuccess = rename($tempName, $fileNewPath . '/' . FileTools::cleanupFileName($fileName));
                        if (!$booSuccess) {
                            $strError = $this->_tr->translate('File move error.');
                        }
                    } else {
                        $fileNewPath = $this->_files->getCloud()->isFolder($fileNewPath) ? $fileNewPath : $fileNewPath . '/';
                        $filePath    = $this->_files->getCloud()->preparePath($fileNewPath) . FileTools::cleanupFileName($fileName);

                        if($dontOverwrite){
                            if($this->_files->getCloud()->checkObjectExists($filePath)){
                                $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '_' . date('Y-m-d_H-i-s') . '.' . $fileExt;
                                $filePath = $this->_files->getCloud()->preparePath($fileNewPath) . FileTools::cleanupFileName($fileName);
                            }
                        }

                        $booSuccess = $this->_files->getCloud()->uploadFile($tempName, $filePath);
                        if (!$booSuccess) {
                            $strError = $this->_tr->translate('File move error (cloud).');
                        }
                    }

                    // Remove temp file
                    unlink($tempName);
                } else {
                    $strError = $this->_tr->translate('Insufficient access rights (to folder).');
                }
            }

            if (empty($strError)) {
                // Update last_doc_uploaded field for company
                $this->_company->updateLastField($memberId, 'last_doc_uploaded');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $this->getResponse()->setStatusCode(500);
        }

        return new JsonModel(array('success' => empty($strError), 'error' => $strError));
    }

    public function filesUploadFromGoogleDriveAction()
    {
        set_time_limit(5 * 60); // 5 minutes
        session_write_close();

        $strError = '';
        try {
            //get params
            $memberId = (int)$this->params()->fromPost('member_id');
            $memberId = empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId;
            $folderId = $this->params()->fromPost('folder_id');
            $folderId = $folderId == 'root' ? 'root' : $this->_encryption->decode($folderId);

            $memberInfo = $this->_members->getMemberInfo($memberId);
            $companyId  = $memberInfo['company_id'];
            $booLocal   = $this->_company->isCompanyStorageLocationLocal($companyId);
            $memberType = $this->_clients->getMemberTypeByMemberId($memberId);
            $isClient   = $this->_clients->isMemberClient($memberType);

            if (!$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $fileNewPath = $folderId == 'root'
                ? $this->_files->getMemberFolder($companyId, $memberId, $isClient, $booLocal)
                : $folderId;

            if (empty($strError) && !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
                if ($this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled()) {
                    $allowedTypes = array('C', 'F', 'CD', 'SD', 'SDR');
                    $arrRoles     = $this->_clients->getMemberRoles($this->_auth->getCurrentUserId());
                    $arrFolders   = $this->_files->getDefaultFoldersAccess($companyId, $memberId, $isClient, $booLocal, $arrRoles, $allowedTypes);
                    if ($this->_company->getCompanyDivisions()->getFolderPathAndAccess($arrFolders, $fileNewPath, $memberId, '') != 'RW') {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                } else {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }
            }

            $booSuccess = false;
            $booLocal   = $this->_auth->isCurrentUserCompanyStorageLocal();

            $fileName = $this->params()->fromPost('file_name');
            $fileExt  = pathinfo($fileName, PATHINFO_EXTENSION);

            if (empty($strError) && !$this->_files->isFileFromWhiteList($fileExt)) {
                $strError = $this->_tr->translate('File type is not from whitelist.');
            }

            $googleDriveApiKey = $this->_config['google_drive']['api_key'];
            if (empty($strError) && empty($googleDriveApiKey)) {
                $strError = $this->_tr->translate('Google Drive is not enabled in the config.');
            }

            if (empty($strError)) {
                if ($this->_clients->checkClientFolderAccess($memberId, $fileNewPath)) {
                    // Download file to temp
                    $tempName = tempnam($this->_config['directory']['tmp'], 'TMP_');

                    $googleDriveFileId     = $this->params()->fromPost('google_drive_file_id');
                    $googleDriveOauthToken = $this->params()->fromPost('google_drive_oauth_token');

                    $client = new Client();
                    $client->setUri('https://www.googleapis.com/drive/v3/files/' . $googleDriveFileId);
                    $client->setParameterGet([
                        'key' => $googleDriveApiKey,
                        'alt' => 'media',
                    ]);
                    $client->setHeaders([
                        'Authorization' => 'Bearer ' . $googleDriveOauthToken
                    ]);
                    $client->setStream($tempName);
                    $client->send();

                    if ($booLocal) {
                        $booSuccess = rename($tempName, $fileNewPath . '/' . FileTools::cleanupFileName($fileName));
                        if (!$booSuccess) {
                            $strError = $this->_tr->translate('File move error.');
                        }
                    } else {
                        $fileNewPath = $this->_files->getCloud()->isFolder($fileNewPath) ? $fileNewPath : $fileNewPath . '/';
                        $filePath    = $this->_files->getCloud()->preparePath($fileNewPath) . FileTools::cleanupFileName($fileName);

                        $booSuccess = $this->_files->getCloud()->uploadFile($tempName, $filePath);
                        if (!$booSuccess) {
                            $strError = $this->_tr->translate('File move error (cloud).');
                        }
                    }

                    // Remove temp file
                    unlink($tempName);
                } else {
                    $strError = $this->_tr->translate('Insufficient access rights (to folder).');
                }
            }

            if ($booSuccess && empty($strError)) {
                // Update last_doc_uploaded field for company
                $this->_company->updateLastField($memberId, 'last_doc_uploaded');
            }

            if (!$booSuccess && empty($strError)) {
                $strError = $this->_tr->translate('File(s) was not provided or was not uploaded.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $this->getResponse()->setStatusCode(500);
        }

        return new JsonModel(array('success' => empty($strError), 'error' => $strError));
    }

    public function saveFileToGoogleDriveAction()
    {
        $strError = '';

        try {
            if (!$this->_auth->hasIdentity()) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $filePath = '';
            $fileName = '';
            $params   = $this->params()->fromPost();
            if (empty($strError)) {
                $filePath        = $this->_encryption->decode($params['id']);
                $memberId        = (int)$params['member_id'];
                $currentMemberId = $this->_auth->getCurrentUserId();
                $memberId     = empty($memberId) ? $currentMemberId : $memberId;

                list($booLocal, $filePath) = $this->_files->getCorrectFilePathAndLocationByPath($filePath);
                if (!$this->_clients->checkClientFolderAccess($memberId, $filePath)) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                } else {
                    $fileName = basename($filePath);

                    if (!$booLocal) {
                        $filePath = $this->_files->getCloud()->generateFileDownloadLink($filePath, false, true, $fileName, false);

                        if (empty($filePath)) {
                            $strError = $this->_tr->translate('Error during file downloading from the Cloud.');
                        }
                    }
                }
            }

            $googleDriveApiKey = $this->_config['google_drive']['api_key'];
            if (empty($strError) && empty($googleDriveApiKey)) {
                $strError = $this->_tr->translate('Google Drive is not enabled in the config.');
            }

            if (empty($strError)) {
                $googleDriveFolderId   = $params['google_drive_folder_id'];
                $googleDriveOauthToken = $params['google_drive_oauth_token'];

                $client = new Client();
                $client->setUri('https://www.googleapis.com/upload/drive/v3/files');
                $client->setMethod('post');
                $client->setParameterGet([
                    'key'        => $googleDriveApiKey,
                    'uploadType' => 'multipart',
                ]);

                // Construct body for multipart/related
                $boundaryStr = 'officio_google_drive_aSNjjYQnncNsMJEZpPKgWunaxHDsxSyq';
                $body        = '';

                // Part 1
                $body .= '--' . $boundaryStr . "\n";
                $body .= 'Content-Type: application/json; charset=UTF-8' . "\n";
                $body .= "\n";
                $meta = [
                    'name' => FileTools::cleanupFileName($fileName)
                ];
                if (!empty($googleDriveFolderId)) {
                    $meta['parents'] = [$googleDriveFolderId];
                }
                $body .= json_encode($meta) . "\n";

                // Part 2
                $body     .= '--' . $boundaryStr . "\n";
                $mimeType = FileTools::getMimeByFileName($fileName);
                $body     .= 'Content-Type: ' . $mimeType . "\n";
                $body     .= "\n";
                $data     = file_get_contents($filePath);
                $body     .= $data . "\n";
                $body     .= '--' . $boundaryStr . '--';

                $client->setRawBody($body);

                $client->setHeaders([
                    'Authorization'  => 'Bearer ' . $googleDriveOauthToken,
                    'Content-Type'   => 'multipart/related; boundary=' . $boundaryStr,
                    'Content-Length' => strlen($body),
                ]);

                $response = $client->send();

                if ($response->getStatusCode() != 200) {
                    throw new Exception("Google Drive API v3 multipart/related upload failed. Reason: " . $response->getBody());
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $this->getResponse()->setStatusCode(500);
        }

        return new JsonModel(array('success' => empty($strError), 'error' => $strError));
    }

    public function newFileAction()
    {
        $view = new JsonModel();

        $fileId = '';
        try {
            $filter    = new StripTags();
            $folder_id = $filter->filter($this->_encryption->decode($this->findParam('folder_id')));
            $name      = $filter->filter(Json::decode(stripslashes($this->findParam('name', '')), Json::TYPE_ARRAY));
            $name      = FileTools::cleanupFileName($name);

            $type      = $filter->filter($this->findParam('type'));
            $member_id = (int)$this->findParam('member_id');
            $member_id = empty($member_id) ? $this->_auth->getCurrentUserId() : $member_id;

            $memberInfo = $this->_members->getMemberInfo($member_id);
            $companyId  = $memberInfo['company_id'];
            $booLocal   = $this->_company->isCompanyStorageLocationLocal($companyId);

            $strError = '';
            if (!in_array($type, array('doc', 'docx', 'html', 'rtf', 'sxw', 'txt'))) {
                $strError = $this->_tr->translate('Incorrectly selected file type.');
            }

            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($member_id)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($member_id)) {
                if ($this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled()) {
                    $allowedTypes = array('C', 'F', 'CD', 'SD', 'SDR');
                    $arrRoles     = $this->_clients->getMemberRoles($this->_auth->getCurrentUserId());
                    $memberType   = $this->_clients->getMemberTypeByMemberId($member_id);
                    $isClient     = $this->_clients->isMemberClient($memberType);
                    $arrFolders   = $this->_files->getDefaultFoldersAccess($companyId, $member_id, $isClient, $booLocal, $arrRoles, $allowedTypes);
                    if ($this->_company->getCompanyDivisions()->getFolderPathAndAccess($arrFolders, $folder_id, $member_id, '') != 'RW') {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                } else {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }
            }


            if (empty($strError)) {
                if ($folder_id == 'root') {
                    $memberType = $this->_clients->getMemberTypeByMemberId($member_id);
                    $isClient   = $this->_clients->isMemberClient($memberType);
                    $folder_id  = $this->_files->getMemberFolder($companyId, $member_id, $isClient, $booLocal);
                }
                $fileId = $this->_documents->newFile($folder_id, $name, $type);
                if ($fileId === false) {
                    $strError = $this->_tr->translate('File was not created. Please try again later.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success'   => empty($strError),
            'msg'       => $strError,
            'file_id'   => empty($strError) ? $this->_encryption->encode($fileId) : '',
            'path_hash' => empty($strError) ? $this->_files->getHashForThePath($fileId) : ''
        );

        return $view->setVariables($arrResult);
    }

    public function addDefaultFoldersAction()
    {
        $view = new JsonModel();

        $booSuccess = false;
        try {
            $memberId = (int)$this->findParam('member_id', 0);
            if ($this->_members->hasCurrentMemberAccessToMember($memberId) && $this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
                $memberInfo = $this->_members->getMemberInfo($memberId);
                $this->_files->mkNewMemberFolders($memberId, $memberInfo['company_id'], $this->_company->isCompanyStorageLocationLocal($memberInfo['company_id']));
                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => $booSuccess,
            'message' => $booSuccess ? $this->_tr->translate('Default folders were created successfully.') : $this->_tr->translate('Default folders were not created. Please try again later.')
        );
        return $view->setVariables($arrResult);
    }


    public function addFolderAction()
    {
        $view = new JsonModel();

        $booCreated = false;
        try {
            $filter = new StripTags();
            $name   = $filter->filter(trim(Json::decode(stripslashes($this->findParam('name', '')), Json::TYPE_ARRAY)));
            $name   = StringTools::stripInvisibleCharacters($name, false);

            $parentPath = $filter->filter($this->findParam('parent_id', ''));
            $parentPath = empty($parentPath) ? '' : $this->_encryption->decode($parentPath);

            $memberId    = (int)$this->findParam('member_id', 0);
            $memberInfo  = $this->_members->getMemberInfo($memberId);
            $companyId   = $memberInfo['company_id'];
            $booLocal    = $this->_company->isCompanyStorageLocationLocal($companyId);
            $memberType  = $this->_clients->getMemberTypeByMemberId($memberId);
            $booIsClient = $this->_clients->isMemberClient($memberType);

            if (empty($parentPath) && !empty($memberId)) {
                $parentPath = $this->_files->getMemberFolder($companyId, $memberId, $booIsClient, $booLocal);
            }

            if ($this->_members->hasCurrentMemberAccessToMember($memberId) &&
                ($this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId) || $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled())) {
                $allowedTypes = array('C', 'F', 'CD', 'SD', 'SDR');
                $arrRoles     = $this->_clients->getMemberRoles($this->_auth->getCurrentUserId());
                $arrFolders   = $this->_files->getDefaultFoldersAccess($companyId, $memberId, $booIsClient, $booLocal, $arrRoles, $allowedTypes);

                if ($this->_company->getCompanyDivisions()->getFolderPathAndAccess($arrFolders, $parentPath, $memberId, '') == 'RW') {
                    //decode files
                    $path = rtrim($parentPath, '/') . '/' . $name;
                    if ($this->_auth->isCurrentUserCompanyStorageLocal()) {
                        $booCreated = $this->_files->createFTPDirectory($path);
                    } else {
                        $booCreated = $this->_files->createCloudDirectory($path);
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => $booCreated,
            'message' => $booCreated ? $this->_tr->translate('Folder was created successfully.') : $this->_tr->translate('Folder was not created. Please try again later.')
        );
        return $view->setVariables($arrResult);
    }

    public function renameFolderAction()
    {
        $view = new JsonModel();

        $strError = '';

        try {
            $filter        = new StripTags();
            $oldPath       = $filter->filter($this->_encryption->decode($this->findParam('folder_id', '')));
            $newFolderName = $filter->filter(Json::decode($this->findParam('folder_name'), Json::TYPE_ARRAY));
            $newFolderName = StringTools::stripInvisibleCharacters($newFolderName, false);

            $memberId = $this->findParam('member_id');
            $memberId = empty($memberId) ? 0 : (int)Json::decode($memberId, Json::TYPE_ARRAY);

            $memberInfo = $this->_members->getMemberInfo($memberId);
            $companyId  = $memberInfo['company_id'];
            $booLocal   = $this->_company->isCompanyStorageLocationLocal($companyId);

            $type = $this->findParam('type');
            $type = empty($type) ? '' : $filter->filter(Json::decode($type, Json::TYPE_ARRAY));

            switch ($type) {
                case 'clients':
                case 'mydocs':
                    if (!$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                        $strError = $this->_tr->translate('Insufficient access rights to clients.');
                    }

                    if (empty($strError) && !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
                        if ($this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled()) {
                            $allowedTypes = array('C', 'F', 'CD', 'SD', 'SDR');
                            $arrRoles     = $this->_clients->getMemberRoles($this->_auth->getCurrentUserId());
                            $memberType   = $this->_clients->getMemberTypeByMemberId($memberId);
                            $isClient     = $this->_clients->isMemberClient($memberType);
                            $arrFolders   = $this->_files->getDefaultFoldersAccess($companyId, $memberId, $isClient, $booLocal, $arrRoles, $allowedTypes);

                            if ($this->_company->getCompanyDivisions()->getFolderPathAndAccess($arrFolders, $oldPath, $memberId, '') != 'RW') {
                                $strError = $this->_tr->translate('Insufficient access rights.');
                            }
                        } else {
                            $strError = $this->_tr->translate('Insufficient access rights.');
                        }
                    }
                    break;

                case 'prospects':
                    if (empty($memberId) || !$this->_companyProspects->allowAccessToProspect($memberId)) {
                        $strError = $this->_tr->translate('Insufficient access rights to prospects.');
                    }

                    break;

                case 'templates':
                default:
                    break;
            }

            if (empty($strError)) {
                $booLocal       = $this->_auth->isCurrentUserCompanyStorageLocal();
                $companyId      = $this->_auth->getCurrentUserCompanyId();
                $sharedDocsPath = $this->_files->getCompanySharedDocsPath($companyId, false, $booLocal);

                if ($booLocal) {
                    $oldPath      = str_replace('\\', '/', $oldPath);
                    $parentFolder = substr($oldPath, 0, strrpos($oldPath, '/'));
                } else {
                    $parentFolder = substr($oldPath, 0, strlen($oldPath) - strlen($this->_files->getCloud()->getFolderNameByPath($oldPath) ?? '') - 1);
                }

                // Don't allow 'Shared Workspace' folder renaming
                if (rtrim($oldPath, '/') == rtrim($sharedDocsPath, '/')) {
                    $strError = $this->_tr->translate('This folder cannot be renamed.');
                }

                if (empty($strError)) {
                    if ($booLocal) {
                        $booSuccess = $this->_files->renameFolder($oldPath, $parentFolder . '/' . $newFolderName);
                    } else {
                        $booSuccess = $this->_files->getCloud()->renameObject($oldPath, $parentFolder . $newFolderName . '/');
                    }

                    if (!$booSuccess) {
                        $strError = $this->_tr->translate('Internal error.');
                    }

                    if ($booSuccess && $type == 'mydocs') {
                        // Make sure that access rights will be updated for all sub folders in the 'Shared Workspace'
                        if (rtrim($parentFolder, '/') == $sharedDocsPath) {
                            $oldFolderName = trim(substr($oldPath, -1 * (strlen($oldPath) - strlen($parentFolder))), '/');

                            $this->_company->getCompanyDivisions()->renameFoldersBasedOnDivisionAccess($companyId, $oldFolderName, $newFolderName);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => empty($strError), 'message' => $strError));
    }

    public function getFileAction()
    {
        $params          = $this->findParams();
        $booAsAttachment = !is_null($this->findParam('attachment')) ? (bool)$params['attachment'] : true;

        $enc = $this->findParam('enc');
        if (!is_null($enc)) {
            $arrInfo         = unserialize($this->_encryption->decode($enc));
            $filePath        = $arrInfo['id'];
            $memberId        = $arrInfo['mem'];
            $currentMemberId = $arrInfo['c_mem'];
            $booExpired      = (int)$arrInfo['exp'] < time();

            if ($booExpired) {
                return $this->renderError($this->_tr->translate('File link already expired.'));
            }
        } elseif ($this->_auth->hasIdentity()) {
            $filePath        = $this->_encryption->decode($params['id']);
            $memberId        = (int)$params['member_id'];
            $currentMemberId = $this->_auth->getCurrentUserId();
        } else {
            return $this->renderError($this->_tr->translate('Insufficient access rights.'));
        }

        list($booLocal, $filePath) = $this->_files->getCorrectFilePathAndLocationByPath($filePath);

        /**
         * We need check access for such cases:
         * 1. Logged in user tries to get a file - check access to the file
         * 2. Not logged in user (must be Zoho) tries to get a file - expiration will be checked
         */
        if ($this->_auth->hasIdentity()) {
            $memberId     = empty($memberId) ? $currentMemberId : $memberId;
            $booHasAccess = $this->_clients->checkClientFolderAccess($memberId, $filePath);
            if (!$booHasAccess) {
                return $this->renderError($this->_tr->translate('Insufficient access rights.'));
            }
        }

        if (empty($filePath)) {
            return $this->renderError($this->_tr->translate('Incorrect params.'));
        }

        if ($booLocal) {
            return $this->downloadFile($filePath, $this->_files::extractFileName($filePath), FileTools::getMimeByFileName($filePath), false, $booAsAttachment);
        } else {
            if ($url = $this->_files->getCloud()->getFile($filePath, $this->_files::extractFileName($filePath), false, $booAsAttachment)) {
                return $this->redirect()->toUrl($url);
            } else {
                return $this->renderError($this->_tr->translate('File not found.'));
            }
        }
    }

    public function getFileDownloadUrlAction()
    {
        $view       = new JsonModel();
        $strError   = "";
        $url        = "";
        $fileName   = "";

        $params          = $this->findParams();

        if ($this->_auth->hasIdentity()) {
            $filePath        = $this->_encryption->decode($params['id']);
            $memberId        = (int)$params['member_id'];
            $currentMemberId = $this->_auth->getCurrentUserId();
        } else {
            $strError = $this->_tr->translate('Insufficient access rights.');
        }

        if(empty($strError)) {
            list($booLocal, $filePath) = $this->_files->getCorrectFilePathAndLocationByPath($filePath);
        }

        if (empty($strError)) {
            $memberId     = empty($memberId) ? $currentMemberId : $memberId;
            $booHasAccess = $this->_clients->checkClientFolderAccess($memberId, $filePath);
            if (!$booHasAccess) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }
        }

        if (empty($strError) && empty($filePath)) {
            $strError = $this->_tr->translate('Incorrect params.');
        }

        if (empty($strError)) {

            $url = $this->_files->generateDownloadUrlForBrowser($memberId, false, $filePath, $booLocal, true);
            $fileName = basename($filePath);

        }

        $arrResult = array(
            'success'   => empty($strError),
            'url'       => $url,
            'file_name' => $fileName,
        );

        return $view->setVariables($arrResult);
    }

    //save edited by Zoho file
    public function saveFileAction()
    {
        $strError = '';

        try {
            $fileId         = $this->params()->fromPost('id');
            $file           = $_FILES['content'] ?? '';
            $arrCheckParams = unserialize($this->_encryption->decode($this->params()->fromPost('enc')));

            $currentMemberId = $arrCheckParams['c_mem'];
            $memberId        = $arrCheckParams['member_id'];
            $filePath        = $this->_encryption->decode($fileId);

            if (!$this->_clients->checkClientFolderAccess($memberId, $filePath, $currentMemberId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && empty($fileId) || empty($file)) {
                $this->_log->debugErrorToFile('', sprintf('Save-file action with params: file_id = %s, file_path = %s', $fileId, $filePath));
                $strError = $this->_tr->translate('No file to save');
            }

            if (empty($strError) && !$this->_files->saveFile($fileId, $file)) {
                // Show a message if something wrong
                $strError = $this->_tr->translate('File was not saved.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);
        $view->setVariable('content', $strError);

        return $view;
    }

    public function getPdfAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        $strError = '';

        try {
            $filter   = new StripTags();
            $realPath = $this->_encryption->decode($this->findParam('id'));
            $booTmp   = (bool)$this->findParam('boo_tmp', 0);
            $fileName = $filter->filter($this->findParam('file'));
            $memberId = (int)$this->findParam('member_id');
            $memberId = empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId;

            if (!$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                if ($booTmp) {
                    $attachMemberId = 0;
                    // File path is in such format: path/to/file#client_id
                    if (preg_match('/(.*)#(\d+)/', $realPath, $regs)) {
                        $realPath       = $regs[1];
                        $attachMemberId = $regs[2];
                    }

                    if (!empty($attachMemberId) && $attachMemberId == $memberId) {
                        return $this->downloadFile($realPath, $fileName);
                    } else {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                } else {
                    list($booLocal, $filePath) = $this->_files->getCorrectFilePathAndLocationByPath($realPath);
                    if ($this->_clients->checkClientFolderAccess(
                        $memberId,
                        $filePath
                    )) {
                        if ($booLocal) {
                            return $this->downloadFile($filePath, $fileName, '', true, false);
                        } else {
                            $url = $this->_files->getCloud()->getFile($filePath, $fileName, true, false);
                            if ($url) {
                                return $this->redirect()->toUrl($url);
                            } else {
                                return $this->fileNotFound();
                            }
                        }
                    } else {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('content', $strError);

        return $view;
    }

    public function previewAction()
    {
        try {
            $fileId   = $this->_encryption->decode($this->params()->fromPost('file_id'));
            $memberId = (int)$this->params()->fromPost('member_id');

            $result = $this->_documents->preview($fileId, $memberId);
        } catch (Exception $e) {
            $result['success'] = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel($result);
    }

    public function renameFileAction()
    {
        $strError = '';

        try {
            $filter = new StripTags();

            $filePath = $filter->filter($this->_encryption->decode($this->params()->fromPost('file_id')));
            $filename = $filter->filter(Json::decode($this->params()->fromPost('filename'), Json::TYPE_ARRAY));
            $filename = FileTools::cleanupFileName($filename);

            $memberId = $this->params()->fromPost('member_id');
            $memberId = empty($memberId) ? 0 : (int)Json::decode($memberId, Json::TYPE_ARRAY);

            $memberInfo = $this->_members->getMemberInfo($memberId);
            $companyId  = $memberInfo['company_id'];
            $booLocal   = $this->_company->isCompanyStorageLocationLocal($companyId);

            $type = $this->params()->fromPost('type');
            $type = empty($type) ? '' : $filter->filter(Json::decode($type, Json::TYPE_ARRAY));

            switch ($type) {
                case 'clients':
                case 'mydocs':
                    if (!$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                        $strError = $this->_tr->translate('Insufficient access rights to clients.');
                    }

                    if (empty($strError) && !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
                        if ($this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled()) {
                            $allowedTypes = array('C', 'F', 'CD', 'SD', 'SDR');
                            $arrRoles     = $this->_clients->getMemberRoles($this->_auth->getCurrentUserId());
                            $memberType   = $this->_clients->getMemberTypeByMemberId($memberId);
                            $isClient     = $this->_clients->isMemberClient($memberType);
                            $arrFolders   = $this->_files->getDefaultFoldersAccess($companyId, $memberId, $isClient, $booLocal, $arrRoles, $allowedTypes);
                            $path         = dirname($filePath);
                            if ($this->_company->getCompanyDivisions()->getFolderPathAndAccess($arrFolders, $path, $memberId, '') != 'RW') {
                                $strError = $this->_tr->translate('Insufficient access rights.');
                            }
                        } else {
                            $strError = $this->_tr->translate('Insufficient access rights.');
                        }
                    }

                    break;

                case 'prospects':
                    // Cannot be here, because it should go to the Prospects->Index->renameFileAction
                    if (empty($memberId) || !$this->_companyProspects->allowAccessToProspect($memberId)) {
                        $strError = $this->_tr->translate('Insufficient access rights to prospects.');
                    }
                    break;

                case 'templates':
                default:
                    break;
            }

            if (empty($strError) && !$this->_documents->renameFile($booLocal, $filePath, $filename)) {
                $strError = $this->_tr->translate('File was not renamed. Please try again later.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError
        );

        return new JsonModel($arrResult);
    }

    public function dragAndDropAction()
    {
        $view = new JsonModel();

        $success = false;

        try {
            $memberId = (int)$this->findParam('member_id');
            $memberId = empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId;

            $memberInfo = $this->_members->getMemberInfo($memberId);
            $companyId  = $memberInfo['company_id'];
            $booLocal   = $this->_company->isCompanyStorageLocationLocal($companyId);

            $memberType = $this->_clients->getMemberTypeByMemberId($memberId);
            $isClient   = $this->_clients->isMemberClient($memberType);

            if ($this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $filter = new StripTags();
                $fileId = $filter->filter($this->_encryption->decode($this->findParam('file_id')));

                $folderId   = $this->findParam('folder_id');
                $folderPath = $folderId == 'root'
                    ? $this->_files->getMemberFolder($companyId, $memberId, $isClient, $booLocal)
                    : $this->_encryption->decode($folderId);

                if ($this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId) ||
                    $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled()) {
                    $allowedTypes = array('C', 'F', 'CD', 'SD', 'SDR');
                    $arrRoles     = $this->_clients->getMemberRoles($this->_auth->getCurrentUserId());
                    $arrFolders   = $this->_files->getDefaultFoldersAccess($companyId, $memberId, $isClient, $booLocal, $arrRoles, $allowedTypes);

                    if ($this->_company->getCompanyDivisions()->getFolderPathAndAccess($arrFolders, $folderPath, $memberId, '') == 'RW') {
                        if (!empty($fileId)) {
                            if ($this->_clients->checkClientFolderAccess($memberId, $folderPath)) {
                                $success = $this->_files->dragAndDropFTPFile($fileId, $folderPath);
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => $success));
    }

    public function createLetterAction()
    {
        $fileId   = '';
        $filename = '';

        try {
            $filter = new StripTags();

            $templateId = $filter->filter($this->findParam('template_id'));
            if (!empty($templateId)) {
                $arrLetterTemplates = $this->_templates->getTemplatesList(true, 0, false, 'Letter');

                $booFoundTemplate = false;
                foreach ($arrLetterTemplates as $arrLetterTemplateInfo) {
                    if ($arrLetterTemplateInfo['templateId'] == $templateId) {
                        $booFoundTemplate = true;
                        break;
                    }
                }

                if (!$booFoundTemplate) {
                    $strError = $this->_tr->translate('Incorrectly selected template.');
                }
            }

            $memberId = 0;
            $folderId = $this->findParam('folder_id');
            $folderId = $folderId == 'root' ? $folderId : $filter->filter($this->_encryption->decode($folderId));
            if (empty($strError)) {
                if (!empty($templateId)) {
                    // Check access to the client
                    $memberId = (int)$this->findParam('member_id');
                    if ($this->_members->hasCurrentMemberAccessToMember($memberId) &&
                        ($this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId) || $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled())) {
                    } else {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                } else {
                    // If template isn't selected - means that no client was selected too
                    $memberId = $this->_auth->getCurrentUserId();
                }
            }

            if (empty($strError) && empty($memberId)) {
                // Cannot be here
                $strError = $this->_tr->translate('Insufficient access rights.');
            }


            if (empty($strError)) {
                $filename = $filter->filter(trim(Json::decode(stripslashes($this->findParam('filename', '')), Json::TYPE_ARRAY)));
                $filename = FileTools::cleanupFileName($filename);

                $memberInfo = $this->_members->getMemberInfo($memberId);
                $companyId  = $memberInfo['company_id'];

                $booLocal    = $this->_company->isCompanyStorageLocationLocal($companyId);
                $arrRoles    = $this->_clients->getMemberRoles($this->_auth->getCurrentUserId());
                $memberType  = $this->_clients->getMemberTypeByMemberId($memberId);
                $booIsClient = $this->_clients->isMemberClient($memberType);
                $arrFolders  = $this->_files->getDefaultFoldersAccess($companyId, $memberId, $booIsClient, $booLocal, $arrRoles, array('C', 'F', 'CD', 'SD', 'SDR'));
                $path        = $folderId == 'root' ? $this->_files->getMemberFolder($companyId, $memberId, $booIsClient, $booLocal) : $folderId;

                if ($this->_company->getCompanyDivisions()->getFolderPathAndAccess($arrFolders, $path, $memberId, '') != 'RW') {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }
            }

            if (empty($strError)) {
                if (!empty($templateId) && empty($filename)) {
                    $template = $this->_templates->getTemplate($templateId);
                    $filename = $template['name'];
                }
                $filename .= '.docx';

                //create letter
                $fileId = $this->_templates->createLetterFromLetterTemplate($templateId, $memberId, $folderId, $filename);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $booSuccess = !empty($fileId) && empty($strError);
        $arrResult  = array(
            'success'   => $booSuccess,
            'path_hash' => $booSuccess ? $this->_files->getHashForThePath($fileId) : '',
            'file_id'   => $booSuccess ? $this->_encryption->encode($fileId) : '',
            'filename'  => $filename
        );

        return new JsonModel($arrResult);
    }

    public function getLetterheadsListAction()
    {
        $view = new JsonModel();

        $letterheads = array();
        try {
            $companyId   = $this->_auth->getCurrentUserCompanyId();
            $letterheads = $this->_letterheads->getLetterheadsList($companyId);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'    => $booSuccess,
            'rows'       => $letterheads,
            'totalCount' => count($letterheads)
        );
        return $view->setVariables($arrResult);
    }

    public function createLetterOnLetterheadAction()
    {
        $view = new JsonModel();

        try {
            $filter        = new StripTags();
            $member_id     = (int)$this->findParam('member_id');
            $letterhead_id = $filter->filter($this->findParam('letterhead_id'));
            $folder_id     = $filter->filter($this->_encryption->decode($this->findParam('folder_id')));
            $filename      = $filter->filter(trim(Json::decode(stripslashes($this->findParam('filename', '')), Json::TYPE_ARRAY)));
            $filename      = FileTools::cleanupFileName($filename);
            $message       = $this->_settings->getHTMLPurifier(false)->purify(Json::decode($this->findParam('message'), Json::TYPE_ARRAY));
            $booPreview    = (bool)$this->findParam('preview');

            $memberInfo = $this->_members->getMemberInfo($member_id);
            $companyId  = $memberInfo['company_id'];
            $booLocal   = $this->_company->isCompanyStorageLocationLocal($companyId);

            $arrPageNumberSettings['location']    = $filter->filter(Json::decode($this->findParam('location'), Json::TYPE_ARRAY));
            $arrPageNumberSettings['alignment']   = $filter->filter(Json::decode($this->findParam('alignment'), Json::TYPE_ARRAY));
            $arrPageNumberSettings['distance']    = $filter->filter($this->findParam('distance'));
            $arrPageNumberSettings['wording']     = $filter->filter(Json::decode($this->findParam('wording'), Json::TYPE_ARRAY));
            $arrPageNumberSettings['skip_number'] = (bool)$this->findParam('skip_number');

            if ($this->_members->hasCurrentMemberAccessToMember($member_id) &&
                ($this->_company->getCompanyDivisions()->canCurrentMemberEditClient($member_id) ||
                    $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled())) {
                $allowedTypes = array('C', 'F', 'CD', 'SD', 'SDR');
                $arrRoles     = $this->_clients->getMemberRoles($this->_auth->getCurrentUserId());
                $memberType   = $this->_clients->getMemberTypeByMemberId($member_id);
                $isClient     = $this->_clients->isMemberClient($memberType);
                $arrFolders   = $this->_files->getDefaultFoldersAccess($companyId, $member_id, $isClient, $booLocal, $arrRoles, $allowedTypes);

                if ($this->_company->getCompanyDivisions()->getFolderPathAndAccess($arrFolders, $folder_id, $member_id, '') == 'RW') {
                    //create letter
                    if ($folder_id === 'root') {
                        $folder_id = $this->_files->getMemberFolder($companyId, $member_id, $isClient, $booLocal);
                    }
                    $letterheadsPath = $this->_files->getCompanyLetterheadsPath(
                        $this->_auth->getCurrentUserCompanyId(),
                        $this->_company->isCompanyStorageLocationLocal($this->_auth->getCurrentUserCompanyId())
                    );
                    $arrResult       = $this->_documents->createLetterOnLetterhead($message, $folder_id, $filename, $letterhead_id, $letterheadsPath, $booPreview, $arrPageNumberSettings);
                    if (isset($arrResult['filename'])) {
                        $arrResult['filename'] = $this->_encryption->encode($arrResult['filename'] . '#' . $member_id);
                    }
                }
            } else {
                $strError  = $this->_tr->translate('Insufficient access rights.');
                $arrResult = array('success' => false, 'error' => $strError);
            }
        } catch (Exception $e) {
            $strError  = $this->_tr->translate('Internal error.');
            $arrResult = array('success' => false, 'error' => $strError);
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables($arrResult);
    }

    public function openPdfAction()
    {
        $filter    = new StripTags();
        $name      = $filter->filter($this->findParam('file'));
        $member_id = (int)$this->findParam('member_id');
        if (empty($member_id)) {
            $title = $name;
        } else {
            if (!$this->_clients->isAlowedClient($member_id)) {
                $view = new ViewModel(
                    ['content' => 'Insufficient access rights']
                );
                $view->setTemplate('layout/plain');
                $view->setTerminal(true);

                return $view;
            }

            $arrMemberInfo = $this->_clients->getClientInfo($member_id);
            $title         = $arrMemberInfo['full_name'] . ' - ' . $name;
        }

        $file_id = $filter->filter($this->findParam('id'));

        $member_id = empty($member_id) ? $this->_auth->getCurrentUserId() : $member_id;
        $embedUrl  = $this->layout()->getVariable('baseUrl') . '/documents/index/get-pdf?file=' . $name . '&id=' . urlencode($file_id) . '&member_id=' . urlencode((string)$member_id);

        $view = new ViewModel();
        $view->setTerminal(true);
        return $view->setVariables(
            [
                'pageTitle' => $title,
                'embedUrl'  => $embedUrl
            ]
        );
    }

    public function downloadEmailAction()
    {
        try {
            $filter             = new StripTags();
            $realPath           = $filter->filter($this->_encryption->decode($this->params()->fromPost('id')));
            $attachmentFileName = $filter->filter($this->params()->fromPost('file_name'));

            if (!empty($realPath) && !empty($attachmentFileName)) {
                $fileInfo = $this->_files->getFileEmail($realPath, $attachmentFileName);

                if ($fileInfo instanceof FileInfo) {
                    return $this->file($fileInfo->content, $fileInfo->name, $fileInfo->mime);
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view = new ViewModel(
            ['content' => $this->_tr->translate('File not found.')]
        );
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);
        return $view;
    }

    public function saveDocFileAction()
    {
        $strError = '';
        try {
            // Get and check incoming info
            $filter     = new StripTags();
            $memberId   = (int)Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY);
            $templateId = (int)Json::decode($this->params()->fromPost('template_id'), Json::TYPE_ARRAY);
            $invoiceId  = (int)Json::decode($this->params()->fromPost('invoice_id'), Json::TYPE_ARRAY);
            $title      = $filter->filter(Json::decode($this->params()->fromPost('title'), Json::TYPE_ARRAY));

            if (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights to the client');
            }

            if (empty($strError) && (empty($templateId) || !is_numeric($templateId))) {
                $strError = $this->_tr->translate('Insufficient access rights to the template');
            }

            if (empty($strError)) {
                $memberInfo = $this->_clients->getMemberInfo($memberId);
                $companyId  = $memberInfo['company_id'];
                $isLocal    = $this->_company->isCompanyStorageLocationLocal($companyId);

                $arrExtraFields = [];
                if (!empty($invoiceId) && $this->_clients->getAccounting()->hasAccessToInvoice($invoiceId)) {
                    $arrExtraFields['invoice_id'] = $invoiceId;
                }

                $message = $this->_templates->getMessage($templateId, $memberId, '', false, false, false, $arrExtraFields);
                $content = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /><body>' . $message['message'] . '</body></html>';
                $dir     = $this->_files->getClientCorrespondenceFTPFolder($companyId, $memberId, $isLocal);
                $path    = $dir . '/' . FileTools::cleanupFileName($title . ' ' . date('Y-m-d H-i') . '.doc');

                if ($isLocal) {
                    $this->_files->createFTPDirectory($dir);
                    $booSuccess = $this->_files->createFile($path, $content);
                } else {
                    $this->_files->getCloud()->createFolder($dir);
                    $booSuccess = $this->_files->getCloud()->createObject($path, $content);
                }

                if (!$booSuccess) {
                    $strError = $this->_tr->translate('File was not created');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'doc_creation');
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
        );

        return new JsonModel($arrResult);
    }

    public function downloadDocFileAction()
    {
        $strError = '';

        try {
            $filter     = new StripTags();
            $memberId   = (int)$this->params()->fromQuery('member_id');
            $templateId = (int)$this->params()->fromQuery('template_id');
            $invoiceId  = $this->params()->fromQuery('invoice_id');
            $title      = $filter->filter($this->params()->fromQuery('title'));

            if (!$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Internal error');
            }

            if (empty($strError)) {
                $arrExtraFields = [];
                if (!empty($invoiceId) && $this->_clients->getAccounting()->hasAccessToInvoice($invoiceId)) {
                    $arrExtraFields['invoice_id'] = $invoiceId;
                }

                $message = $this->_templates->getMessage($templateId, $memberId, '', false, false, false, $arrExtraFields);
                $content = '<html><head></head><body>' . $message['message'] . '</body></html>';

                $fileExtension = '.docx';
                $fileName      = FileTools::cleanupFileName($title . ' ' . date('Y-m-d H-i') . $fileExtension);
                $filePath      = $this->_files->fixFilePath($this->_config['directory']['tmp'] . '/' . $fileName);

                if (!$this->_documents->getPhpDocx()->createDocxFromHtml($content, substr($filePath, 0, strlen($filePath ?? '') - strlen($fileExtension)))) {
                    $strError = $this->_tr->translate('Internal error during docx creation');
                }

                if (empty($strError)) {
                    return $this->downloadFile($filePath, $fileName);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // If we are here - something is wrong
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);
        $view->setVariable('content', $strError);

        return $view;
    }

    public function saveToInboxAction()
    {
        $view = new JsonModel();

        try {
            $memberId      = (int)$this->findParam('member_id');
            $ids           = Json::decode($this->findParam('ids'), Json::TYPE_ARRAY);
            $booIsProspect = Json::decode($this->findParam('is_prospect'), Json::TYPE_ARRAY);

            $strError = '';
            if (!is_array($ids) || !count($ids)) {
                $strError = $this->_tr->translate('Please select emails.');
            }

            if (empty($strError)) {
                if (empty($memberId)) {
                    $booIsProspect = false;
                    $memberId      = $this->_auth->getCurrentUserId();
                }

                $memberInfo = $this->_members->getMemberInfo($memberId);
                $companyId  = $memberInfo['company_id'];
                $booLocal   = $this->_company->isCompanyStorageLocationLocal($companyId);

                if ($booIsProspect) {
                    $booHasAccess = $this->_companyProspects->allowAccessToProspect($memberId);
                } else {
                    $booHasAccess = false;
                    if ($this->_members->hasCurrentMemberAccessToMember($memberId) &&
                        ($this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId) ||
                            $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled())) {
                        $allowedTypes = array('C', 'F', 'CD', 'SD', 'SDR');
                        $arrRoles     = $this->_clients->getMemberRoles($this->_auth->getCurrentUserId());
                        $memberType   = $this->_clients->getMemberTypeByMemberId($memberId);
                        $isClient     = $this->_clients->isMemberClient($memberType);
                        $arrFolders   = $this->_files->getDefaultFoldersAccess($companyId, $memberId, $isClient, $booLocal, $arrRoles, $allowedTypes);

                        foreach ($ids as $encodedFilePath) {
                            $path = $this->_encryption->decode($encodedFilePath);
                            if ($this->_company->getCompanyDivisions()->getFolderPathAndAccess($arrFolders, dirname($path), $memberId, '') != 'RW') {
                                $booHasAccess = false;
                                break;
                            } else {
                                $booHasAccess = true;
                            }
                        }
                    }
                }

                if (!$booHasAccess) {
                    $strError = $this->_tr->translate('Insufficient access rights');
                }
            }

            $accountId = 0;
            if (empty($strError)) {
                $accountId = MailAccount::getDefaultAccount($this->_auth->getCurrentUserId());
                if (empty($accountId)) {
                    $strError = $this->_tr->translate('There is no email account to save emails to.');
                }
            }

            if (empty($strError)) {
                $booLocal = $this->_auth->isCurrentUserCompanyStorageLocal();
                foreach ($ids as $encodedFilePath) {
                    $path = $this->_encryption->decode($encodedFilePath);

                    $content = '';
                    if ($booLocal) {
                        if (is_file($path) && is_readable($path)) {
                            $content = file_get_contents($path);
                        }
                    } else {
                        $content = $this->_files->getCloud()->getFileContent($path);
                    }

                    if (!empty($content)) {
                        $msg     = new Message(array('raw' => $content));
                        $rand = rand();
                        $success = $this->_mailer->saveMessageFromServerToFolder($msg, $this->_mailer->getUniquesEmailPrefix() . md5(uniqid((string)$rand, true)), $accountId);
                        if (!$success) {
                            $strError = $this->_tr->translate('Email was not saved.');
                        }
                    } else {
                        $strError = $this->_tr->translate('Incorrect email path.');
                    }

                    if (!empty($strError)) {
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('Email was saved successfully.') : $strError
        );

        return $view->setVariables($arrResult);
    }

    public function printEmailAction()
    {
        $view = new JsonModel();

        $content = '';
        try {
            $filter   = new StripTags();
            $path     = $filter->filter($this->_encryption->decode($this->findParam('file_id')));
            $memberId = (int)$this->findParam('member_id');

            if (!empty($path) && $this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $content = $this->_files->showEmail($path, 'documents', false);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success' => !empty($content),
            'content' => $content
        );

        return $view->setVariables($arrResult);
    }

    public function convertToPdfAction()
    {
        $view = new JsonModel();

        set_time_limit(60); // 1 minute
        ini_set('memory_limit', '512M');
        session_write_close();

        $fileId   = 0;
        $fileSize = 0;
        try {
            $filter   = new StripTags();
            $filePath = $filter->filter($this->_encryption->decode($this->findParam('file_id')));
            $filePath = StringTools::stripInvisibleCharacters($filePath, false);
            $memberId = (int)$this->findParam('member_id');
            $memberId = empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId;
            $folderId = $filter->filter($this->findParam('folder_id'));
            $booTemp  = (bool)$this->findParam('boo_temp', 0);

            if ($folderId == 'root') {
                $memberInfo  = $this->_members->getMemberInfo($memberId);
                $companyId   = $memberInfo['company_id'];
                $booLocal    = $this->_company->isCompanyStorageLocationLocal($companyId);
                $memberType  = $this->_clients->getMemberTypeByMemberId($memberId);
                $booIsClient = $this->_clients->isMemberClient($memberType);
                $folderPath  = $this->_files->getMemberFolder($companyId, $memberId, $booIsClient, $booLocal);
            } elseif ($folderId == 'tmp') {
                $folderPath = '';
            } else {
                $folderPath = $this->_encryption->decode($folderId);
            }

            $fileName = $filter->filter(Json::decode(stripslashes($this->findParam('filename', '')), Json::TYPE_ARRAY));

            if ($this->_members->hasCurrentMemberAccessToMember($memberId)) {
                // File path can be in a such format: path/to/file#client_id
                if (preg_match('/(.*)#(\d+)/', $filePath, $regs)) {
                    $filePath = $regs[1];
                }

                $arrConvertingResult = $this->_documents->convertToPdf($folderPath, $filePath, $fileName, $booTemp);

                $strError = $arrConvertingResult['error'];

                if (empty($strError)) {
                    $fileId   = $this->_encryption->encode($arrConvertingResult['file_id'] . '#' . $memberId);
                    $fileSize = $arrConvertingResult['file_size'];
                }
            } else {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'   => empty($strError),
            'error'     => $strError,
            'file_id'   => $fileId,
            'file_size' => $fileSize
        );
        return $view->setVariables($arrResult);
    }

    public function saveFileToClientDocumentsAction()
    {
        $view = new JsonModel();

        $booSuccess = false;
        $strError   = '';
        try {
            $filter     = new StripTags();
            $fileId     = $filter->filter($this->findParam('file_id'));
            $memberId   = (int)$this->findParam('member_id');
            $folderPath = '';

            $memberInfo = $this->_clients->getMemberInfo($memberId);
            $companyId  = $memberInfo['company_id'];
            $isLocal    = $this->_company->isCompanyStorageLocationLocal($companyId);

            if (!$this->_members->hasCurrentMemberAccessToMember($memberId) || !$this->_company->getCompanyDivisions()->canCurrentMemberEditClient($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $folderPath = $this->_files->getClientCorrespondenceFTPFolder($companyId, $memberId, $isLocal);
                if (!$this->_clients->checkClientFolderAccess($memberId, $folderPath)) {
                    $strError = $this->_tr->translate('Insufficient access rights to the Clients Correspondence folder.');
                }
            }

            if (empty($strError)) {
                $filePath = $this->_encryption->decode($fileId);

                $booLocal = $this->_auth->isCurrentUserCompanyStorageLocal();
                $fileName = $this->_files::extractFileName($filePath);
                $fileName = FileTools::cleanupFileName($fileName);

                $newPath = rtrim($folderPath, '/') . '/' . $fileName;

                if ($booLocal) {
                    if (file_exists($newPath)) {
                        unlink($newPath);
                    }

                    $booSuccess = $this->_files->moveLocalFile($filePath, $newPath);
                } else {
                    $booSuccess = $this->_files->getCloud()->uploadFile($filePath, $newPath);
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError) && $booSuccess,
            'error'   => $strError
        );

        return $view->setVariables($arrResult);
    }

    public function uploadFileAction()
    {
        $strError      = '';
        $tmpName       = '';
        $tmpPath       = '';
        $fileName      = '';
        $fileExtension = '';
        $fileSize      = '';

        try {
            if (empty($strError) && (empty($_FILES['qqfile']['tmp_name']) || $_FILES['qqfile']['tmp_name'] == 'none')) {
                $strError = $this->_tr->translate('No file was uploaded.');
            }

            if (empty($strError)) {
                $fileName      = FileTools::cleanupFileName($_FILES['qqfile']['name']);
                $fileSize      = $_FILES['qqfile']['size'];
                $fileExtension = FileTools::getFileExtension($fileName);

                if (!$this->_files->isFileFromWhiteList($fileExtension)) {
                    $strError = $this->_tr->translate('File type is not from whitelist.');
                }
            }

            if (empty($strError)) {
                $tmpName    = hrtime(true);
                $targetPath = $this->_config['directory']['tmp'] . '/uploads/';
                $tmpPath    = str_replace('//', '/', $targetPath) . $tmpName;
                $tmpPath    = $this->_files->generateFileName($tmpPath, true);

                if (move_uploaded_file($_FILES['qqfile']['tmp_name'], $tmpPath)) {
                    $tmpName = $this->_encryption->encode($tmpName . '#' . $this->_auth->getCurrentUserId());
                    $tmpPath = $this->_encryption->encode($tmpPath);
                } else {
                    $strError = $this->_tr->translate('Internal error.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'   => empty($strError),
            'msg'       => $strError,
            'tmpName'   => empty($strError) ? $tmpName : '',
            'tmpPath'   => empty($strError) ? $tmpPath : '',
            'name'      => empty($strError) ? $fileName : '',
            'extension' => empty($strError) ? $fileExtension : '',
            'size'      => empty($strError) ? $fileSize : '',
        );

        return new JsonModel($arrResult);
    }


    public function createZipAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        set_time_limit(5 * 60); // 5 minutes
        ini_set('memory_limit', '512M');
        session_write_close();

        try {
            $strFileType = $this->findParam('type');
            if (!in_array($strFileType, ['clients', 'mydocs'])) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $memberId = (int)$this->findParam('memberId');
            if (empty($strError)) {
                if ($strFileType === 'mydocs') {
                    $memberId = $this->_auth->getCurrentUserId();
                } elseif (!$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }
            }

            // Decode and check access rights to folders/files paths
            $arrObjects = Json::decode($this->findParam('filesAndFolders'), Json::TYPE_ARRAY);
            if (empty($strError) && empty($arrObjects)) {
                $strError = $this->_tr->translate('Nothing selected.');
            }

            if (empty($strError)) {
                $access = true;
                foreach ($arrObjects as &$node) {
                    $node   = $this->_encryption->decode($node);
                    $access = $this->_clients->checkClientFolderAccess($memberId, $node);
                    if (!$access) {
                        break;
                    }
                }

                if (!$access) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }
            }

            if (empty($strError)) {
                $companyId     = 0;
                $strMemberName = '';
                switch ($strFileType) {
                    case 'clients':
                        $strMemberName = $this->_clients->generateClientAndCaseName($memberId);
                        $companyId     = $this->_company->getMemberCompanyId($memberId);
                        break;

                    case 'mydocs':
                        $arrMemberInfo = $this->_clients->getMemberInfo($memberId);
                        $companyId     = $arrMemberInfo['company_id'] ?? '';
                        $strMemberName = $arrMemberInfo['full_name'] ?? '';
                        break;

                    default:
                        break;
                }
                $booLocal = $this->_company->isCompanyStorageLocationLocal($companyId);

                $strMemberName = empty($strMemberName) ? 'zipped_files' : $strMemberName;
                $zipFileName   = $strMemberName . '.zip';

                $memberType       = $this->_clients->getMemberTypeByMemberId($memberId);
                $isClient         = $this->_clients->isMemberClient($memberType);
                $pathToClientDocs = $this->_files->getMemberFolder($companyId, $memberId, $isClient, $booLocal) . '/';
                $pathToClientDocs = str_replace('\\', '/', $pathToClientDocs);

                $pathToSharedDocs = $this->_files->getCompanySharedDocsPath($companyId, false, $booLocal);
                $pathToSharedDocs = str_replace('\\', '/', $pathToSharedDocs);

                $result = $this->_documents->createZip($arrObjects, $booLocal, $zipFileName, $pathToClientDocs, $pathToSharedDocs);
                if ($result instanceof FileInfo) {
                    return $this->downloadFile($result->path, $result->name, $result->mime);
                } else {
                    $strError = $result;
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('content', $strError);

        return $view;
    }
}
