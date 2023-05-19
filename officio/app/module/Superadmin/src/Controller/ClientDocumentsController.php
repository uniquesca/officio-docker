<?php

namespace Superadmin\Controller;

use Documents\Service\Documents;
use Exception;
use Files\Model\FileInfo;
use Files\Service\Files;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Uniques\Php\StdLib\FileTools;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;

/**
 * Client Documents Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ClientDocumentsController extends BaseController
{

    /** @var Files */
    protected $_files;

    /** @var Documents */
    protected $_documents;

    /** @var Company */
    protected $_company;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_files      = $services[Files::class];
        $this->_documents  = $services[Documents::class];
        $this->_company    = $services[Company::class];
        $this->_encryption = $services[Encryption::class];
    }

    public function indexAction()
    {
        $view = new ViewModel();

        $title = "Client Document Folders";
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);
        $view->setVariable('members', $this->_members);
        
        $companyId = $this->_auth->getCurrentUserCompanyId();
        $view->setVariable('arrFieldsInfo', array('company_id' => $companyId));
        $view->setVariable('isAuthorizedAgentsManagementEnabled', $this->_company->getCompanyDivisions()->isAuthorizedAgentsManagementEnabled());

        return $view;
    }
    
    private function getDefaultClientDocuments($parent_id = 0)
    {
        $companyId = $this->_auth->isCurrentUserSuperadmin() ? null : $this->_auth->getCurrentUserCompanyId();

        // Get folders list
        // 'SDR' (Shared Workspace) will be not returned
        $arrType = array('CD', 'C', 'F', 'SD');
        $folders = $this->_files->getFolders()->getCompanyFolders($companyId, $parent_id, $arrType);

        // @note: company id must be numeric because company id is used in path
        $companyId = empty($companyId) ? 0 : $companyId;

        $arr = array();
        foreach ($folders as $folder) {
            $user           = $this->_members->getMemberInfo($folder['author_id']);
            $children       = $this->getDefaultClientDocuments($folder['folder_id']);
            $special_folder = in_array($folder['type'], array('C', 'F', 'SD', 'SDR'));

            // Load default files
            $arrFiles = $this->_files->getCompanyDefaultFiles($companyId, $folder['folder_id']);
            if(count($arrFiles)) {
                foreach ($arrFiles as $arrFileInfo) {
                    $children[] = array(
                        'el_id'       => $this->_encryption->encode($folder['folder_id'] . '/' . $arrFileInfo['file_name']),
                        'downloadUrl' => $this->_files->generateDownloadUrlForBrowser(
                            $this->_auth->getCurrentUserId(),
                            false,
                            $folder['folder_id'] . '/' . $arrFileInfo['file_name'],
                            $this->_company->isCompanyStorageLocationLocal($companyId)
                        ),
                        'filename' => $arrFileInfo['file_name'],
                        'path_hash' => $this->_files->getHashForThePath($folder['folder_id'] . '/' . $arrFileInfo['file_name']),
                        'type' => 'file',
                        'folder' => false,
                        'locked' => false,
                        'author' => '',
                        'uiProvider' => 'col',
                        'expanded' => true,
                        'iconCls' => $this->_files->getFileIcon(FileTools::getMimeByFileName($arrFileInfo['file_name'])),
                        'allowDrag' => false,
                        'leaf' => true,
                        'children' => array()
                    );
                }
            }
            
            //generate folder
            $arr[] = array(
                'el_id'      => $folder['folder_id'],
                'filename'   => $folder['folder_name'],
                'type'       => 'folder',
                'folder'     => true,
                'allowRW'    => true,
                'locked'     => $special_folder,
                'author'     => $user['full_name'],
                'uiProvider' => 'col',
                'expanded'   => !empty($children),
                'iconCls'    => $this->_files->getFolderIconCls($folder['type']),
                'allowDrag'  => false,
                'leaf'       => (count($children) == 0),
                'children'   => $children
            );
        }
        
        return $arr;
    }
    
    public function getFoldersTreeAction()
    {
        $view = new JsonModel();
        try {
            $folders = $this->getDefaultClientDocuments();
        } catch (Exception $e) {
            $folders = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables($folders);
    }
    
    public function addFolderAction()
    {
        $view = new JsonModel();
        try {
            $strError = '';
            $folderId = 0;

            $filter         = new StripTags();
            $name           = $filter->filter(Json::decode($this->findParam('name'), Json::TYPE_ARRAY));
            $parentFolderId = $filter->filter(Json::decode($this->findParam('parent'), Json::TYPE_ARRAY));

            if(!empty($parentFolderId) && !$this->_files->getFolders()->hasAccessCurrentMemberToDefaultFolders($parentFolderId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if(empty($strError)) {
                $companyId = $this->_auth->isCurrentUserSuperadmin() ? null : $this->_auth->getCurrentUserCompanyId();
                $authorId  = $this->_auth->getCurrentUserId();

                $folderId = $this->_files->getFolders()->createFolder($companyId, $authorId, $parentFolderId, $name, 'CD');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => !empty($folderId), 'msg' => $strError));
    }

    public function renameAction()
    {
        $view = new JsonModel();
        try {
            $filter   = new StripTags();
            $strError = '';

            $objectId   = $this->findParam('object_id');
            $objectName = $filter->filter(Json::decode(stripslashes($this->findParam('object_name', '')), Json::TYPE_ARRAY));
            if (is_numeric($objectId)) {
                if(!$this->_files->getFolders()->hasAccessCurrentMemberToDefaultFolders($objectId)) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }

                if (empty($strError) && !$this->_files->getFolders()->updateFolder($objectId, $objectName, $this->_auth->getCurrentUserId())) {
                    $strError = $this->_tr->translate('Folder was not renamed. Please try again later.');
                }
            } else {
                $filePath = $this->_encryption->decode($objectId);
                if (!$this->_files->renameCompanyDefaultFile($this->_auth->getCurrentUserCompanyId(), $filePath, $objectName)) {
                    $strError = $this->_tr->translate('File was not renamed. Please try again later.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => empty($strError), 'msg' => $strError));
    }

    public function deleteAction()
    {
        $view = new JsonModel();
        $strError = '';
        try {
            // Folder id is numeric, file path is encoded
            $objectId = $this->findParam('object_id');
            if (is_numeric($objectId)) {
                if (!$this->_files->getFolders()->hasAccessCurrentMemberToDefaultFolders($objectId)) {
                    $strError = $this->_tr->translate('Insufficient access rights.');
                }

                // User can delete folder and subfolders only for own company
                $companyId = $this->_auth->isCurrentUserSuperadmin()
                    ? null
                    : $this->_auth->getCurrentUserCompanyId();

                if (empty($strError) && !$this->_documents->deleteFolder($objectId, $companyId)) {
                    $strError = $this->_tr->translate('Folder was not deleted. Please try again later.');
                }
            } else {
                $filePath = $this->_encryption->decode($objectId);
                if (!$this->_files->deleteCompanyDefaultFile($this->_auth->getCurrentUserCompanyId(), $filePath)) {
                    $strError = $this->_tr->translate('File was not deleted. Please try again later.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => empty($strError), 'msg' => $strError));
    }

    public function downloadAction() {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        try {
            $fileId   = $this->findParam('id');
            $filePath = $this->_encryption->decode($fileId);

            $fileInfo = $this->_files->downloadCompanyDefaultFile($this->_auth->getCurrentUserCompanyId(), $filePath);
            if ($fileInfo instanceof FileInfo) {
                return $this->downloadFile($fileInfo->path, $fileInfo->name, $fileInfo->mime);
            } else {
                $strError = $this->_tr->translate('File not found.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariable('content', $strError);
    }


    public function uploadAction() {
        $view = new JsonModel();
        try {
            $strError = '';
            $folderId = $this->findParam('folder_id');
            if(!$this->_files->getFolders()->hasAccessCurrentMemberToDefaultFolders($folderId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $filesCount = (int)$this->findParam('files');
            if(empty($strError) && !is_numeric($filesCount)) {
                $strError = $this->_tr->translate('Incorrectly selected files.');
            }

            $arrFiles = array();
            if(empty($strError)) {
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
                    } else {
                        $arrFiles[$i] = $_FILES['docs-upload-file'];
                    }
                }

                if(!count($arrFiles)) {
                    $strError = $this->_tr->translate('Incorrectly selected files.');
                }

                foreach ($arrFiles as $file) {
                    $extension = FileTools::getFileExtension($file['name']);
                    if (!$this->_files->isFileFromWhiteList($extension)) {
                        $strError = $this->_tr->translate('File type is not from whitelist.');
                        break;
                    }
                }
            }

            if (empty($strError) && !$this->_files->uploadCompanyDefaultFiles($this->_auth->getCurrentUserCompanyId(), $folderId, $arrFiles)) {
                $strError = $this->_tr->translate('File(s) was not uploaded.');
            }

        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $this->getResponse()->setStatusCode(500);
        }

        return $view->setVariables(array('success' => empty($strError), 'error' => $strError));
    }
}