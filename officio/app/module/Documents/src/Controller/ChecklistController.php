<?php

namespace Documents\Controller;

use Clients\Service\Clients;
use DateTime;
use Exception;
use Files\Service\Files;
use Files\Model\FileInfo;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Uniques\Php\StdLib\FileTools;

/**
 * Documents Checklist Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ChecklistController extends BaseController
{
    /** @var Clients */
    protected $_clients;

    /** @var Files */
    protected $_files;

    public function initAdditionalServices(array $services)
    {
        $this->_clients = $services[Clients::class];
        $this->_files = $services[Files::class];
    }

    public function getListAction()
    {
        $view = new JsonModel();

        session_write_close();

        $strError  = '';
        $arrResult = array();
        try {
            $memberId = (int)Json::decode($this->findParam('clientId'), Json::TYPE_ARRAY);
            if (!$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($strError)) {
                $arrResult = $this->_clients->getClientDependents()->getListForCase($memberId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = empty($strError) ? $arrResult : array('error' => $strError);
        return $view->setVariables($arrResult);
    }

    public function deleteAction()
    {
        $view = new JsonModel();

        $strError = '';

        try {
            $clientId       = (int)Json::decode($this->findParam('clientId'), Json::TYPE_ARRAY);
            $uploadedFileId = (int)Json::decode($this->findParam('fileId'), Json::TYPE_ARRAY);

            if (!$this->_members->hasCurrentMemberAccessToMember($clientId)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($strError)) {
                $arrUploadedFileInfo = $this->_clients->getClientDependents()->getUploadedFileInfo($uploadedFileId);
                if (!isset($arrUploadedFileInfo['member_id']) || $arrUploadedFileInfo['member_id'] != $clientId) {
                    $strError = $this->_tr->translate('Insufficient access rights');
                }
            }

            // All is okay, delete the file
            if (empty($strError) && !$this->_clients->getClientDependents()->deleteUploadedFile($uploadedFileId)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('Done!') : $strError
        );

        return $view->setVariables($arrResult);
    }

    public function uploadAction()
    {
        $view = new JsonModel();

        session_write_close();

        try {
            $strError = '';

            $memberId = (int)Json::decode($this->findParam('member_id'), Json::TYPE_ARRAY);

            if (!$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($strError) && empty($_FILES)) {
                $strError = $this->_tr->translate('Incorrectly selected files.');
            }

            $requiredFileId = (int)Json::decode($this->findParam('required_file_id'), Json::TYPE_ARRAY);

            if (empty($strError)) {
                $arrRequiredFileInfo = $this->_clients->getClientDependents()->getRequiredFileInfo($requiredFileId);
                if (empty($arrRequiredFileInfo)) {
                    $strError = $this->_tr->translate('Incorrect Required File Info.');
                }
            }

            $arrDependentIds = $this->findParam('dependent_ids');
            if (empty($strError)) {
                if (!is_array($arrDependentIds) || empty($arrDependentIds)) {
                    $strError = $this->_tr->translate('Incorrect dependant params.');
                }
            }

            $filesCount = $this->findParam('files');
            if (empty($strError) && !is_numeric($filesCount)) {
                $strError = $this->_tr->translate('Incorrectly selected files.');
            }

            $arrFiles = array();
            if (empty($strError)) {
                if (isset($_FILES['docs-upload-file']['tmp_name'])) {
                    if (is_array($_FILES['docs-upload-file']['tmp_name'])) {
                        for ($i = 0; $i < $filesCount; $i++) {
                            if (isset($_FILES['docs-upload-file']['tmp_name'][$i]) && !empty($_FILES['docs-upload-file']['tmp_name'][$i]) && isset($arrDependentIds[$i])) {
                                $dependentId = $arrDependentIds[$i];

                                if (!empty($dependentId) && !$this->_clients->hasCurrentMemberAccessToDependent($dependentId)) {
                                    $strError = $this->_tr->translate('Incorrect params.');
                                }

                                if (empty($strError)) {
                                    $extension = FileTools::getFileExtension($_FILES['docs-upload-file']['name'][$i]);
                                    if (!$this->_files->isFileFromWhiteList($extension)) {
                                        $strError = $this->_tr->translate('File type is not from whitelist.');
                                    }
                                }

                                if ($strError) {
                                    break;
                                } else {
                                    $arrFiles[$i] = array(
                                        'name'         => $_FILES['docs-upload-file']['name'][$i],
                                        'type'         => $_FILES['docs-upload-file']['type'][$i],
                                        'tmp_name'     => $_FILES['docs-upload-file']['tmp_name'][$i],
                                        'error'        => $_FILES['docs-upload-file']['error'][$i],
                                        'size'         => $_FILES['docs-upload-file']['size'][$i],
                                        'dependent_id' => $dependentId
                                    );

                                }

                            }
                        }
                    }
                }
            }

            if (empty($strError) && !count($arrFiles)) {
                $strError = $this->_tr->translate('Incorrectly selected files.');
            }

            if (empty($strError)) {
                foreach ($arrFiles as $file) {
                    if (!$this->_clients->getClientDependents()->uploadChecklistFiles($memberId, $file['dependent_id'], $requiredFileId, array($file))) {
                        $strError = $this->_tr->translate('Internal error');
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'error'   => $strError
        );

        return $view->setVariables($arrResult);
    }

    public function downloadAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        try {
            $fileId = (int)$this->findParam('id');

            $fileInfo = $this->_clients->getClientDependents()->downloadFile($fileId);
            if ($fileInfo instanceof FileInfo) {
                if ($fileInfo->local) {
                    return $this->downloadFile($fileInfo->path, $fileInfo->name, $fileInfo->mime);
                }
                else {
                    $url = $this->_files->getCloud()->getFile($fileInfo->path, $fileInfo->name);
                    if ($url) {
                        return $this->redirect()->toUrl($url);
                    }
                    else {
                        $strError = 'File not found.';
                    }
                }
            }
            else {
                $strError = $fileInfo;
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view->setVariable('content', $strError);

        return $view;
    }

    public function getAllTagsAction()
    {
        $view = new JsonModel();

        session_write_close();

        $strError   = '';
        $arrAllTags = array();
        try {
            $clientId = (int)Json::decode($this->findParam('clientId'), Json::TYPE_ARRAY);
            if (!$this->_members->hasCurrentMemberAccessToMember($clientId)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($strError)) {
                $arrAllSavedTags = $this->_clients->getClientDependents()->getGroupedTags($clientId);
                foreach ($arrAllSavedTags as $tag) {
                    $arrAllTags[] = array('tag' => $tag);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg'     => $strError,
            'items'   => $arrAllTags,
            'count'   => count($arrAllTags),
        );

        return $view->setVariables($arrResult);
    }

    public function setTagsAction()
    {
        $view = new JsonModel();

        session_write_close();

        $strError = '';
        try {
            $clientId       = (int)Json::decode($this->findParam('clientId'), Json::TYPE_ARRAY);
            $uploadedFileId = (int)Json::decode($this->findParam('fileId'), Json::TYPE_ARRAY);
            $arrTags        = Json::decode($this->findParam('tags'), Json::TYPE_ARRAY);

            if (!$this->_members->hasCurrentMemberAccessToMember($clientId)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($strError)) {
                $arrUploadedFileInfo = $this->_clients->getClientDependents()->getUploadedFileInfo($uploadedFileId);
                if (!isset($arrUploadedFileInfo['member_id']) || $arrUploadedFileInfo['member_id'] != $clientId) {
                    $strError = $this->_tr->translate('Insufficient access rights');
                }
            }

            if (empty($strError) && !$this->_clients->getClientDependents()->setUploadedFileTags($clientId, $uploadedFileId, $arrTags)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('Record was saved successfully.') : $strError,
        );

        return $view->setVariables($arrResult);
    }

    /**
     * Load family members list for specific client
     *
     */
    public function getFamilyMembersAction() {
        $view = new JsonModel();

        $strError = '';
        $arrRows = array();

        try {
            $memberId       = (int)$this->findParam('member_id', 0);
            $requiredFileId = (int)$this->findParam('required_file_id', 0);

            if (!$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            // Get family members list for this client
            // (if current user has access to this client)
            $arrRequiredFileInfo = array();

            if (empty($strError)) {
                $arrRequiredFileInfo = $this->_clients->getClientDependents()->getRequiredFileInfo($requiredFileId);
                if (empty($arrRequiredFileInfo)) {
                    $strError = $this->_tr->translate('Insufficient access rights');
                }
            }

            if (empty($strError)) {

                $arrFamilyMembers = array();
                if ($this->_clients->isAlowedClient($memberId)) {
                    $arrFamilyMembers = $this->_clients->getFamilyMembersForClient($memberId);
                }

                foreach ($arrFamilyMembers as $key => $arrFamilyMemberInfo) {
                    $booShow = false;
                    if (in_array($arrFamilyMemberInfo['id'], array('sponsor', 'employer'))) {
                        unset($arrFamilyMembers[$key]);
                    }

                    if ($arrFamilyMemberInfo['id'] === 'main_applicant') {
                        $booShow = $arrRequiredFileInfo['main_applicant_show'] === 'Y';
                    } elseif (!empty($arrFamilyMemberInfo['DOB'])) {
                        $birthDate = new DateTime($arrFamilyMemberInfo['DOB']);
                        $currentDate = new DateTime();

                        $diff = $birthDate->diff($currentDate);

                        if ($diff->y >= 18) {
                            $showKey = 'adult_show';
                        } elseif ($diff->y >= 16) {
                            $showKey = 'minor_16_and_above_show';
                        } else {
                            $showKey = 'minor_less_16_show';
                        }

                        $booShow = $arrRequiredFileInfo[$showKey] === 'Y';
                    }

                    if ($booShow) {
                        $arrRows[] = $arrFamilyMemberInfo;
                    }
                }
            }

        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'        => empty($strError),
            'family_members' => $arrRows,
            'totalCount'     => count($arrRows),
            'message'        => empty($strError) ? $this->_tr->translate('Done!') : $strError
        );

        return $view->setVariables($arrResult);
    }

    public function reassignAction()
    {
        $view = new JsonModel();

        session_write_close();

        $strError = '';
        try {
            $clientId       = (int)Json::decode($this->findParam('clientId'), Json::TYPE_ARRAY);
            $uploadedFileId = (int)Json::decode($this->findParam('fileId'), Json::TYPE_ARRAY);
            $dependentId    = (int)Json::decode($this->findParam('dependentId'), Json::TYPE_ARRAY);
            $dependentId    = empty($dependentId) ? 0 : $dependentId;

            if (!$this->_members->hasCurrentMemberAccessToMember($clientId)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty($strError)) {
                $arrUploadedFileInfo = $this->_clients->getClientDependents()->getUploadedFileInfo($uploadedFileId);
                if (!isset($arrUploadedFileInfo['member_id']) || $arrUploadedFileInfo['member_id'] != $clientId) {
                    $strError = $this->_tr->translate('Insufficient access rights');
                }
            }

            if (empty($strError) && !empty($dependentId) && !$this->_clients->hasCurrentMemberAccessToDependent($dependentId)) {
                $strError = $this->_tr->translate('Incorrect params.');
            }

            if (empty($strError) && !$this->_clients->getClientDependents()->reassignFile($clientId, $dependentId, $uploadedFileId)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('File was assigned successfully.') : $strError,
        );

        return $view->setVariables($arrResult);
    }
}
