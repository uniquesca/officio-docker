<?php

namespace Notes\Controller;

use Clients\Service\Clients;
use Exception;
use Files\Service\Files;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Notes\Service\Notes;
use Officio\BaseController;
use Uniques\Php\StdLib\FileTools;
use Officio\Common\Service\AccessLogs;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Common\Service\Settings;

/**
 * Notes Index Controller - this controller is used in several cases
 * in Ajax requests
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class IndexController extends BaseController
{
    /** @var Notes */
    private $_notes;

    /** @var Clients */
    protected $_clients;

    /** @var AccessLogs */
    protected $_accessLogs;

    /** @var Company */
    protected $_company;

    /** @var Files */
    protected $_files;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_clients    = $services[Clients::class];
        $this->_company    = $services[Company::class];
        $this->_notes      = $services[Notes::class];
        $this->_files      = $services[Files::class];
        $this->_encryption = $services[Encryption::class];
        $this->_accessLogs = $services[AccessLogs::class];
    }

    public function indexAction()
    {
        $view = new ViewModel(
            ['content' => null]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');
        return $view;
    }

    public function getNoteAction()
    {
        $strError = '';
        $arrNote  = array();

        try {
            $filter = new StripTags();
            $noteId = (int)$this->params()->fromPost('note_id');
            $type   = $filter->filter(Json::decode($this->params()->fromPost('type'), Json::TYPE_ARRAY));
            $type   = empty($type) ? 'general' : $type;

            if ((empty($type) || !in_array($type, array('general', 'draft')))) {
                $strError = $this->_tr->translate('Incorrectly selected note type.');
            }

            if (empty($strError) && !$this->_notes->isAllowAccess($noteId, false, $type)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $arrNote = $this->_notes->getNote($noteId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
            'note'    => $arrNote
        );
        return new JsonModel($arrResult);
    }

    private function updateNotes($action)
    {
        $strError = '';

        $oPurifier        = $this->_settings->getHTMLPurifier();
        $noteId           = (int)$this->params()->fromPost('note_id');
        $memberId         = (int)$this->params()->fromPost('member_id');
        $companyId        = (int)$this->params()->fromPost('company_id');
        $noteColor        = $oPurifier->purify($this->params()->fromPost('note_color'));
        $note             = trim($oPurifier->purify(Json::decode($this->params()->fromPost('note', ''), Json::TYPE_ARRAY)));
        $visibleToClients = Json::decode($this->params()->fromPost('visible_to_clients'), Json::TYPE_ARRAY);
        $rtl              = Json::decode($this->params()->fromPost('rtl'), Json::TYPE_ARRAY);
        $type             = Json::decode($this->params()->fromPost('type'), Json::TYPE_ARRAY);
        $type             = empty($type) ? 'general' : $type;
        $attachments      = Json::decode($this->params()->fromPost('note_file_attachments'), Json::TYPE_ARRAY);

        // Check the type
        if ((empty($type) || !in_array($type, array('general', 'draft')))) {
            $strError = $this->_tr->translate('Incorrectly selected note type.');
        }

        // Check note id
        if (empty($strError) && $action == 'edit' && (empty($noteId) || !is_numeric($noteId) || !$this->_notes->isAllowAccess($noteId, $companyId, $type))) {
            $strError = $this->_tr->translate('Insufficient access to note.');
        }

        if (empty($strError) && $action == 'edit' && $this->_notes->isSystemNote($noteId)) {
            $strError = $this->_tr->translate('System notes cannot be changed.');
        }

        // Check if current user can edit notes for this client/user
        if (empty($strError) && !empty($memberId) && !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
            $strError = $this->_tr->translate('Insufficient access to case.');
        }

        if (!$this->_auth->isCurrentUserSuperadmin()) {
            $companyId = null;
        }

        if (empty($strError) && !$this->_notes->updateNote((int)$noteId, $memberId, $note, false, (bool)$visibleToClients, (int)$noteColor, '', (bool)$rtl, '', $companyId, $type, $attachments)) {
            $strError = $this->_tr->translate('Internal error.');
        }

        return $strError;
    }

    public function addAction()
    {
        try {
            $strError = $this->updateNotes('add');

            if (empty($strError)) {
                $this->_company->updateLastField(false, 'last_note_written');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
        );
        return new JsonModel($arrResult);
    }

    public function editAction()
    {
        try {
            $strError = $this->updateNotes('edit');
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError,
        );
        return new JsonModel($arrResult);
    }

    public function deleteAction()
    {
        $strError = '';

        try {
            $arrNotes = Json::decode($this->params()->fromPost('notes'), Json::TYPE_ARRAY);
            $arrNotes = is_array($arrNotes) ? $arrNotes : [$arrNotes];
            $type     = Json::decode($this->params()->fromPost('type'), Json::TYPE_ARRAY);
            $type     = empty($type) ? 'general' : $type;

            if ((empty($type) || !in_array($type, array('general', 'draft')))) {
                $strError = $this->_tr->translate('Incorrectly selected note type.');
            }

            if (empty($strError) && empty($arrNotes)) {
                $strError = $this->_tr->translate('Please select at least one note to delete.');
            }

            if (empty($strError)) {
                // Check if current user can delete each note
                foreach ($arrNotes as $noteId) {
                    if (empty($noteId) || !is_numeric($noteId) || !$this->_notes->isAllowAccess($noteId, false, $type)) {
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }

                    if (empty($strError) && $this->_notes->isSystemNote($noteId)) {
                        $strError = $this->_tr->translate('System notes cannot be deleted.');
                    }

                    if (!empty($strError)) {
                        break;
                    }
                }
            }

            // If we can delete - delete them!
            if (empty($strError)) {
                $arrNotesInfo = [];
                foreach ($arrNotes as $noteId) {
                    $arrNotesInfo[] = $this->_notes->getSimpleNoteInfo($noteId);
                }

                if ($this->_notes->deleteNotes($arrNotes)) {
                    foreach ($arrNotesInfo as $arrNoteInfo) {
                        $memberInfo = $this->_clients->getMemberInfo($arrNoteInfo['member_id']);

                        $arrLog = array(
                            'log_section'           => 'client',
                            'log_action'            => 'file_note_deleted',
                            'log_description'       => 'File Note Deleted',
                            'log_company_id'        => $memberInfo['company_id'],
                            'log_created_by'        => $this->_auth->getCurrentUserId(),
                            'log_action_applied_to' => $arrNoteInfo['member_id'],
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
            'message' => $strError,
        );
        return new JsonModel($arrResult);
    }

    //return list of notes for HOME page
    public function getNotesListAction()
    {
        try {
            $notes = $this->_notes->getNotesList();
        } catch (Exception $e) {
            $notes = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view->setVariable('content', $notes);
    }

    public function getNotesAction()
    {
        $notes = array();
        try {
            $filter = new StripTags();

            $memberId           = (int)$this->params()->fromPost('member_id');
            $start              = (int)$this->params()->fromPost('start');
            $limit              = (int)$this->params()->fromPost('limit');
            $type               = $filter->filter(Json::decode($this->params()->fromPost('type'), Json::TYPE_ARRAY));
            $type               = empty($type) ? 'general' : $type;
            $booShowSystemNotes = (bool)$this->params()->fromPost('show_system_records');

            $dir = $filter->filter($this->params()->fromPost('dir'));
            if (!in_array($dir, array('ASC', 'DESC'))) {
                $dir = 'DESC';
            }

            $sort = $filter->filter($this->params()->fromPost('sort'));

            // Check if current user can see notes for this client/user
            if (!($type == 'draft' && !$this->_company->isDecisionRationaleTabAllowedToCompany($this->_auth->getCurrentUserCompanyId())) &&
                ($this->_auth->isCurrentUserSuperadmin() || $this->_members->hasCurrentMemberAccessToMember($memberId))) {
                $notes = $this->_notes->getNotes($memberId, $start, $limit, $sort, $dir, $type, $booShowSystemNotes);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel($notes);
    }

    public function uploadAttachmentsAction()
    {
        set_time_limit(5 * 60); // 5 minutes
        session_write_close();

        $strError = '';
        $arrFiles = array();

        try {
            //get params
            $filter     = new StripTags();
            $noteId     = (int)$this->params()->fromPost('note_id');
            $filesCount = (int)$this->params()->fromPost('files');
            $act        = $filter->filter($this->params()->fromPost('act'));
            $type       = $filter->filter(Json::decode($this->params()->fromPost('type'), Json::TYPE_ARRAY));
            $type       = empty($type) ? 'general' : $type;
            $memberId   = (int)$this->params()->fromPost('member_id');

            if ($act == 'edit' && (empty($noteId) || !is_numeric($noteId) || !$this->_notes->isAllowAccess($noteId, false, $type))) {
                $strError = $this->_tr->translate('Insufficient access to note.');
            }

            if (empty($strError) && $this->_notes->isSystemNote($noteId)) {
                $strError = $this->_tr->translate('System notes cannot be updated.');
            }

            if (empty($strError) && (empty($type) || !in_array($type, array('general', 'draft')))) {
                $strError = $this->_tr->translate('Incorrectly selected note type.');
            }

            // Check if current user can edit notes for this client/user
            if (empty($strError) && !empty($memberId) && !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access to case.');
            }

            if (empty($strError)) {
                //get files info and size
                for ($i = 0; $i < $filesCount; $i++) {
                    $id = 'note-attachment-' . $i;
                    if (!empty($_FILES[$id]['name']) && !empty($_FILES[$id]['tmp_name'])) {
                        $arrFiles[$i] = $_FILES[$id];
                    }
                }

                // When drag and drop method was used - receive data in other format
                if (isset($_FILES['note-attachment']['tmp_name']) && empty($arrFiles)) {
                    if (is_array($_FILES['note-attachment']['tmp_name'])) {
                        for ($i = 0; $i < $filesCount; $i++) {
                            if (!empty($_FILES['note-attachment']['tmp_name'][$i])) {
                                $arrFiles[$i] = array(
                                    'name'     => $_FILES['note-attachment']['name'][$i],
                                    'type'     => $_FILES['note-attachment']['type'][$i],
                                    'tmp_name' => $_FILES['note-attachment']['tmp_name'][$i],
                                    'error'    => $_FILES['note-attachment']['error'][$i],
                                    'size'     => $_FILES['note-attachment']['size'][$i],
                                );
                            }
                        }
                    } else {
                        $arrFiles[$i] = $_FILES['note-attachment'];
                    }
                }

                foreach ($arrFiles as $file) {
                    $extension = FileTools::getFileExtension($file['name']);
                    if (!$this->_files->isFileFromWhiteList($extension)) {
                        $strError = $this->_tr->translate('File type is not from whitelist.');
                        break;
                    }
                }

                if (empty($strError)) {
                    $targetPath = $this->_config['directory']['tmp'] . '/uploads/';

                    foreach ($arrFiles as $key => $file) {
                        $tmpName = md5(time() . rand(0, 99999));
                        $tmpPath = str_replace('//', '/', $targetPath) . $tmpName;
                        $tmpPath = $this->_files->generateFileName($tmpPath, true);

                        $arrFiles[$key]['tmp_name']  = $this->_encryption->encode($tmpName . '#' . $memberId);
                        $arrFiles[$key]['file_size'] = Settings::formatSize($file['size'] / 1024);

                        if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
                            $strError = $this->_tr->translate('Internal error.');
                            break;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $this->getResponse()->setStatusCode(500);
        }

        return new JsonModel(array('success' => empty($strError), 'error' => $strError, 'files' => $arrFiles));
    }

    public function downloadAttachmentAction()
    {
        set_time_limit(30 * 60); // 30 minutes
        ini_set('memory_limit', '512M');

        try {
            $filter   = new StripTags();
            $attachId = $this->params()->fromPost('attach_id');
            $type     = $filter->filter($this->params()->fromPost('type', ''));
            $memberId = (int)$this->params()->fromPost('member_id');

            // Check if current user can edit notes for this client/user
            if (!$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                exit('Insufficient access rights.');
            }

            if (in_array($type, array('uploaded', 'note_file_attachment'))) {
                switch ($type) {
                    case 'uploaded':
                        $fileName = $this->_encryption->decode($attachId);

                        $attachMemberId = 0;
                        // File path is in such format: path/to/file#client_id
                        if (preg_match('/(.*)#(\d+)/', $fileName, $regs)) {
                            $fileName       = $regs[1];
                            $attachMemberId = $regs[2];
                        }

                        if (!empty($attachMemberId) && $attachMemberId == $memberId) {
                            $path = $this->_config['directory']['tmp'] . '/uploads/' . $fileName;
                            if (!empty($path)) {
                                return $this->downloadFile(
                                    $path,
                                    $filter->filter($this->params()->fromPost('name')),
                                    'application/force-download',
                                    true
                                );
                            }
                        }
                        break;

                    default:
                    case 'note_file_attachment':
                        $path = $this->_encryption->decode($attachId);
                        if (!empty($path)) {
                            $booLocal   = $this->_auth->isCurrentUserCompanyStorageLocal();
                            $noteId     = (int)$this->params()->fromPost('note_id');
                            $folderPath = $this->_files->getClientNoteAttachmentsPath($this->_auth->getCurrentUserCompanyId(), $memberId, $booLocal) . '/' . $noteId;
                            if ($booLocal) {
                                $filePath = $folderPath . '/' . $this->_files::extractFileName($path);

                                if ($filePath == $path) {
                                    return $this->downloadFile(
                                        $path,
                                        $filter->filter($this->params()->fromPost('name')),
                                        'application/force-download',
                                        true
                                    );
                                }
                            } else {
                                $filePath = $folderPath . '/' . $this->_files->getCloud()->getFileNameByPath($path);

                                if ($filePath == $path) {
                                    $url = $this->_files->getCloud()->getFile(
                                        $path,
                                        $filter->filter($this->params()->fromPost('name'))
                                    );

                                    if ($url) {
                                        return $this->redirect()->toUrl($url);
                                    } else {
                                        return $this->fileNotFound();
                                    }
                                }
                            }
                        }
                        break;
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');
        $view->setVariables(['content' => 'File not found.'], true);

        return $view;
    }
}