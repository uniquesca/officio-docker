<?php

namespace Notes\Service;

use Clients\Service\Clients;
use Clients\Service\Members;
use DateTime;
use DateTimeZone;
use Exception;
use Files\Service\Files;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\EventManager\EventInterface;
use Laminas\View\HelperPluginManager;
use Officio\Common\Service\BaseService;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Common\Service\Settings;
use Officio\Service\SystemTriggers;
use Officio\View\Helper\ImgUrl;
use Tasks\Service\Tasks;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Notes extends BaseService
{
    /** @var Clients */
    private $_clients;

    /** @var Company */
    protected $_company;

    /** @var Files */
    protected $_files;

    /** @var SystemTriggers */
    protected $_triggers;

    /** @var Tasks */
    protected $_tasks;

    /** @var HelperPluginManager */
    protected $_viewHelperManager;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_clients           = $services[Clients::class];
        $this->_company           = $services[Company::class];
        $this->_files             = $services[Files::class];
        $this->_triggers          = $services[SystemTriggers::class];
        $this->_tasks             = $services[Tasks::class];
        $this->_viewHelperManager = $services[HelperPluginManager::class];
        $this->_encryption        = $services[Encryption::class];
    }

    public function init()
    {
        // Automatic changes logging should run in the end, therefore low priority
        $this->_triggers->getEventManager()->attach(SystemTriggers::EVENT_FIELD_VALUES_CHANGED, [$this, 'onFieldValuesChanged'], -100);
    }

    /**
     * Create notes for a specific company + clients
     * @Note each note will be marked as a system note (so cannot be changed or deleted)
     *
     * @param EventInterface $event
     * @return void
     */
    public function onFieldValuesChanged(EventInterface $event)
    {
        try {
            $companyId              = $event->getParam('company_id');
            $arrLogGroupedByClients = $event->getParam('grouped_log');
            $automaticReminderName  = $event->getParam('triggered_by');

            if (!empty($arrLogGroupedByClients) && $this->_company->isClientLogEnabledToCompany($companyId)) {
                /*
                 * If this is a client:
                 * a. If this internal contact - find his parent client record
                 * b. For all clients - find all their cases and create the same notes for all of them
                 *
                 * If this is a case - just save note(s) for this case
                 */
                $arrGroupedNotes = array();
                foreach ($arrLogGroupedByClients as $clientId => $arrClientNotes) {
                    $arrMemberInfo = $this->_clients->getMemberInfo($clientId);
                    if (!is_array($arrMemberInfo) || !count($arrMemberInfo)) {
                        continue;
                    }

                    if (in_array($arrMemberInfo['userType'], Members::getMemberType('case'))) {
                        foreach ($arrClientNotes as $strNote) {
                            if (!isset($arrGroupedNotes[$clientId]) || !in_array($strNote['message'], $arrGroupedNotes[$clientId])) {
                                $arrGroupedNotes[$clientId][] = $strNote['message'];
                            }
                        }
                    } else {
                        if (in_array($arrMemberInfo['userType'], Members::getMemberType('internal_contact'))) {
                            // Get Parent Client of this internal contact
                            $arrParentIds = $this->_clients->getParentsForAssignedApplicant($clientId);
                        } else {
                            $arrParentIds = array($clientId);
                        }

                        // For this client find all cases and assign logs to all of them
                        foreach ($arrParentIds as $parentId) {
                            $arrAssignedCases = $this->_clients->getAssignedApplicants($parentId, Members::getMemberType('case'));
                            foreach ($arrAssignedCases as $caseId) {
                                foreach ($arrClientNotes as $strNote) {
                                    if (!isset($arrGroupedNotes[$caseId]) || !in_array($strNote['message'], $arrGroupedNotes[$caseId])) {
                                        $arrGroupedNotes[$caseId][] = $strNote['message'];
                                    }
                                }
                            }
                        }
                    }
                }

                $booNotesUpdated = true;
                foreach ($arrGroupedNotes as $caseId => $arrCaseNotes) {
                    if (!empty($automaticReminderName)) {
                        array_unshift($arrCaseNotes, 'Changes triggered by automatic task "' . $automaticReminderName . '":');
                    }
                    if (!$this->updateNote(0, (int)$caseId, implode(PHP_EOL, $arrCaseNotes), true)) {
                        $booNotesUpdated = false;
                        break;
                    }
                }

                if ($booNotesUpdated) {
                    $this->_company->updateLastField(false, 'last_note_written');
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
    }


    /**
     * Check if note can be accessed
     * i.e. if note's company is the same to the provided one
     *
     * @param int $noteId
     * @param bool|int $companyId , if false - company id of the current user will be used
     * @param string $type
     *
     * @return bool true if note can be accessed
     */
    public function isAllowAccess($noteId, $companyId = false, $type = 'general')
    {
        $booHasAccess = false;

        // Check access rights if note id is correct
        if (is_numeric($noteId) && !empty($noteId)) {
            if (!$companyId) {
                $companyId = $this->_auth->getCurrentUserCompanyId();
            }

            if ($type == 'draft' && !$this->_company->isDecisionRationaleTabAllowedToCompany($companyId)) {
                return false;
            }

            $select = (new Select())
                ->from(['n' => 'u_notes'])
                ->columns([])
                ->join(['m' => 'members'], 'm.member_id = n.author_id', 'company_id', Select::JOIN_LEFT_OUTER)
                ->where(['n.note_id' => (int)$noteId]);

            $noteCompanyId = $this->_db2->fetchOne($select);
            $booHasAccess  = ($companyId == $noteCompanyId);
        }

        return $booHasAccess;
    }

    /**
     * Load note's simple info
     *
     * @param int $noteId
     * @return array
     */
    public function getSimpleNoteInfo($noteId)
    {
        $note = [];

        if (!empty($noteId)) {
            $select = (new Select())
                ->from('u_notes')
                ->where(['note_id' => (int)$noteId]);

            $note = $this->_db2->fetchRow($select);
        }

        return $note;
    }

    /**
     * Check if a specific note is a system note (can be changed/deleted)
     *
     * @param int|array $note
     * @return bool
     */
    public function isSystemNote($note)
    {
        if (is_numeric($note)) {
            $note = $this->getSimpleNoteInfo($note);
        }

        $booIsSystemNote = isset($note['is_system']) && $note['is_system'] == 'Y';
        if ($this->_auth->isCurrentUserAdmin()) {
            if ($this->_acl->isAllowed('clients-notes-edit') && $this->_acl->isAllowed('clients-notes-delete')) {
                $booIsSystemNote = false;
            }
        } elseif (!$this->_auth->isCurrentUserClient() && $note['author_id'] == $this->_auth->getCurrentUserId()) {
            $booIsSystemNote = false;
        }

        return $booIsSystemNote;
    }

    /**
     * Load detailed note's info
     *
     * @param int $noteId
     * @return array
     */
    public function getNote($noteId)
    {
        $note = $this->getSimpleNoteInfo($noteId);

        $arrNoteAttachments = $this->getNoteAttachments($noteId);

        $companyId = $this->_auth->getCurrentUserCompanyId();
        $booLocal  = $this->_company->isCompanyStorageLocationLocal($companyId);
        $filePath  = $this->_files->getClientNoteAttachmentsPath($companyId, $note['member_id'], $booLocal) . '/' . $noteId;

        $arrResultFileAttachments = array();
        foreach ($arrNoteAttachments as $fileAttachment) {
            $fileId   = $this->_encryption->encode($filePath . '/' . $fileAttachment['id']);
            $fileSize = Settings::formatSize($fileAttachment['size'] / 1024);

            $arrResultFileAttachments[] = array(
                'id'      => $fileAttachment['id'],
                'file_id' => $fileId,
                'size'    => $fileSize,
                'link'    => '#',
                'name'    => $fileAttachment['name']
            );
        }

        return array(
            'note_id'            => $note['note_id'],
            'member_id'          => $note['member_id'],
            'note'               => html_entity_decode($note['note']),
            'date'               => $note['create_date'],
            'visible_to_clients' => $note['visible_to_clients'] == 'Y',
            'rtl'                => $note['rtl'] == 'Y',
            'is_system'          => $this->isSystemNote($note),
            'file_attachments'   => $arrResultFileAttachments
        );
    }

    /**
     * Get the list of notes for the current user/admin
     *
     * @return array
     */
    public function getUserNotes()
    {
        $select = (new Select())
            ->from('u_notes')
            ->where(['member_id' => $this->_auth->getCurrentUserId()])
            ->where(['`member_id` = `author_id`'])
            ->order('create_date DESC');

        return $this->_db2->fetchAll($select);
    }

    /**
     * Load notes list for the home page
     *
     * @return string
     */
    public function getNotesList()
    {
        $str = '<table cellpadding="0" cellspacing="0" width="100%">';

        $notesList = $this->getUserNotes();

        $count = count($notesList);
        if ($count == 0) {
            $str .= '<tr><td align="left" valign="middle" class="footertxt" style="padding-top:2px; padding-bottom:2px;">No Notes found.</td></tr>';
        } else {
            $booShowNotes = $this->_acl->isAllowed('user-notes-view');

            $i = 1;
            foreach ($notesList as $note) {
                $brgrbtm = ($i++ == $count ? '' : 'brgrbtm');

                $edit_note   = 'note({action: \'edit\', note_id: ' . $note['note_id'] . ', type: \'homepage\'});';
                $delete_note = 'note({action: \'delete\', note_id: ' . $note['note_id'] . ', type: \'homepage\'});';

                $note_text = (strlen($note['note'] ?? '') > 128 ? substr($note['note'] ?? '', 0, 128) . '...' : $note['note']);
                $strNote   = $booShowNotes ? '<a href="#" onclick="' . $edit_note . '" class="blulinkun">' . $note_text . '</a>' : $note_text;

                $str .= '<tr id="note_tr_' . $note['note_id'] . '">';
                $str .= '<td align="left" valign="top" style="padding-right:5px;" class="' . ($note['rtl'] == 'Y' ? 'rtl ' : '') . 'footertxt padtopbtm3 ' . $brgrbtm . '">' . $strNote . '</td>';
                $str .= $booShowNotes ? '<td align="right" valign="top" class="padtopbtm3 ' . $brgrbtm . '" width="14"><a href="#" onclick="' . $edit_note . '"><i class="las la-edit" title="Edit Note"></i></a></td>' : '';
                $str .= $booShowNotes ? '<td align="right" valign="top" class="padtopbtm3 ' . $brgrbtm . '" width="14"><a href="#" class="blulinkun" onclick="' . $delete_note . '"><i class="las la-trash" title="Delete Note" ></i></a></td>' : '';
                $str .= '</tr>';
            }
        }

        $str .= '</table>';

        return $str;
    }

    /**
     * Get the list of notes for a specific client/case
     *
     * @param int $memberId
     * @param int $start
     * @param int $limit
     * @param string $sort
     * @param string $dir
     * @param string $type
     * @param bool $booShowSystemNotes
     * @return array
     */
    public function getNotes($memberId, $start, $limit, $sort = '', $dir = '', $type = 'general', $booShowSystemNotes = false)
    {
        $arrRecords = array();
        $totalCount = 0;
        try {
            $booIsClient     = $this->_auth->isCurrentUserClient();
            $booIsAdmin      = $this->_auth->isCurrentUserAdmin();
            $currentMemberId = $this->_auth->getCurrentUserId();
            $companyId       = $this->_auth->getCurrentUserCompanyId();
            $booLocal        = $this->_company->isCompanyStorageLocationLocal($companyId);
            $attachmentsPath = $this->_files->getClientNoteAttachmentsPath($companyId, $memberId, $booLocal);
            $type            = empty($type) ? 'general' : $type;

            $arrWhere                = [];
            $arrWhere['n.type']      = $type;
            $arrWhere['n.member_id'] = (int)$memberId;

            if ($booIsClient) {
                $arrWhere['n.visible_to_clients'] = 'Y';
            }

            $skipMemberId = 0;
            if (!$booShowSystemNotes) {
                if (!empty($this->_config['site_version']['fe_api_username'])) {
                    $arrMemberInfo = $this->_clients->getMemberSimpleInfoByUsername($this->_config['site_version']['fe_api_username']);
                    if (!empty($arrMemberInfo['member_id'])) {
                        $skipMemberId = $arrMemberInfo['member_id'];
                        $arrWhere[]   = (new Where())->notEqualTo('n.author_id', $skipMemberId);
                    } else {
                        $arrWhere['n.is_system'] = 'N';
                    }
                } else {
                    $arrWhere['n.is_system'] = 'N';
                }
            }

            $select = (new Select())
                ->from(['n' => 'u_notes'])
                ->join(['m' => 'members'], 'm.member_id = n.author_id', ['fName', 'lName'], Select::JOIN_LEFT_OUTER)
                ->where($arrWhere);

            $notes = $this->_db2->fetchAll($select);

            // Time zone will be used when show the date
            $tz = $this->_auth->getCurrentMemberTimezone();

            /** @var ImgUrl $imgUrl */
            $imgUrl = $this->_viewHelperManager->get('imgUrl');
            foreach ($notes as $note) {
                $date = new DateTime($note['create_date']);
                $dt   = new DateTime('@' . $date->getTimestamp());
                if ($tz instanceof DateTimeZone) {
                    $dt->setTimezone($tz);
                }

                $author          = $this->_clients::generateMemberName($note);
                $noteAttachments = $this->getNoteAttachments($note['note_id']);

                $arrResultFileAttachments = array();
                foreach ($noteAttachments as $fileAttachment) {
                    $fileId   = $this->_encryption->encode($attachmentsPath . '/' . $note['note_id'] . '/' . $fileAttachment['id']);
                    $fileSize = Settings::formatSize($fileAttachment['size'] / 1024);

                    $arrResultFileAttachments[] = array(
                        'id'      => $fileAttachment['id'],
                        'file_id' => $fileId,
                        'size'    => $fileSize,
                        'link'    => '#',
                        'name'    => $fileAttachment['name']
                    );
                }

                $booIsSystemNote = $this->isSystemNote($note);

                $arrRecords[] = array(
                    'rec_type'            => 'note',
                    'rec_additional_info' => '',
                    'rec_id'              => $note['note_id'],
                    'message'             => nl2br($note['note'] ?? ''),
                    'real_note'           => $note['note'],
                    'date'                => $dt->format('Y-m-d H:i:s'),
                    'real_date'           => $dt->getTimestamp(),
                    'author'              => $author['full_name'],
                    'visible_to_clients'  => $note['visible_to_clients'] == 'Y' ? '<img src="' . $imgUrl('show-all.png') . '" alt="Yes" />' : '-',
                    'has_attachment'      => !empty($arrResultFileAttachments),
                    'file_attachments'    => $arrResultFileAttachments,
                    'rtl'                 => $note['rtl'] == 'Y',
                    'is_system'           => $booIsSystemNote,
                    'allow_edit'          => !(($booIsClient && $note['author_id'] != $memberId) || (!$booIsAdmin && $currentMemberId != $note['author_id'])) && !$booIsSystemNote
                );
            }

            if ($this->_acl->isAllowed('clients-tasks-view') && $type == 'general') {
                // Load tasks list for this client
                $arrTasks = $this->_tasks->getMemberTasks(
                    'client',
                    array(
                        'clientId' => $memberId
                    )
                );

                foreach ($arrTasks as $arrTaskInfo) {
                    if (!empty($skipMemberId) && $arrTaskInfo['task_created_by_id'] == $skipMemberId) {
                        continue;
                    }

                    $date = new DateTime($arrTaskInfo['task_create_date']);
                    $dt   = new DateTime('@' . $date->getTimestamp());
                    if ($tz instanceof DateTimeZone) {
                        $dt->setTimezone($tz);
                    }

                    $arrRecords[] = array_merge(
                        $arrTaskInfo,

                        // This info is general, same as for Note
                        array(
                            'rec_type'            => $arrTaskInfo['task_completed'] == 'Y' ? 'task_complete' : 'task',
                            'rec_additional_info' => '',
                            'rec_id'              => $arrTaskInfo['task_id'],
                            'message'             => $arrTaskInfo['task_subject'],
                            'real_note'           => $arrTaskInfo['task_subject'],
                            'date'                => $dt->format('Y-m-d H:i:s'),
                            'real_date'           => $dt->getTimestamp(),
                            'author'              => $arrTaskInfo['task_created_by'],
                            'visible_to_clients'  => '-',
                            'has_attachment'      => false,
                            'file_attachments'    => [],
                            'rtl'                 => false,
                            'is_system'           => false,
                            'allow_edit'          => false
                        )
                    );
                }
            }

            $totalCount = count($arrRecords);

            // Sort collected data
            $dir     = strtoupper($dir) == 'ASC' ? SORT_ASC : SORT_DESC;
            $sort    = empty($sort) ? 'real_date' : $sort;
            $sort    = $sort == 'note' ? 'real_note' : $sort;
            $sort    = $sort == 'date' ? 'real_date' : $sort;
            $arrSort = array();
            foreach ($arrRecords as $key => $row) {
                $arrSort[$key] = strtolower($row[$sort]);
            }
            array_multisort($arrSort, $dir, SORT_STRING, $arrRecords);

            // Return only one page
            $arrRecords = array_slice($arrRecords, $start, $limit);


            // Apply sorting for messages too
            $tasksDir = 'DESC';
            if ($sort == 'real_date' && $dir == SORT_ASC) {
                $tasksDir = 'ASC';
            }


            foreach ($arrRecords as &$arrRecInfo) {
                // Load messages for the task
                if ($arrRecInfo['rec_type'] == 'task' || $arrRecInfo['rec_type'] == 'task_complete') {
                    $arrMessages                     = $this->_tasks->getTaskMessages($arrRecInfo['rec_id'], 'timestamp', $tasksDir, false);
                    $arrRecInfo['rec_tasks_content'] = $this->_tasks->formatThreadContent($arrMessages);
                    $arrRecInfo['rec_tasks_author']  = $this->_tasks->formatThreadAuthor($arrMessages);
                    $arrRecInfo['rec_tasks_date']    = $this->_tasks->formatThreadDate($arrMessages);
                }

                // Don't return temp data to js
                unset($arrRecInfo['real_note'], $arrRecInfo['real_date']);
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array('success' => $booSuccess, 'rows' => $arrRecords, 'totalCount' => $totalCount);
    }

    /**
     * Create/update note
     *
     * @param int $noteId
     * @param int $memberId
     * @param string $note
     * @param bool $booIsSytem if true - this note cannot be changed or deleted, even if there is access to delete notes
     * @param bool $booVisibleToClients
     * @param int $noteColor
     * @param int $authorId
     * @param bool $rtl
     * @param string $createdOn
     * @param ?int $companyId
     * @param string $type
     * @param array $attachments
     * @return bool
     */
    public function updateNote($noteId, $memberId, $note, $booIsSytem = false, $booVisibleToClients = false, $noteColor = 0, $authorId = 0, $rtl = false, $createdOn = '', $companyId = null, $type = 'general', $attachments = array())
    {
        try {
            //get member ID
            $memberId = empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId;
            $authorId = empty($authorId) ? $this->_auth->getCurrentUserId() : $authorId;

            $arrData = array(
                'note'               => $note,
                'visible_to_clients' => $booVisibleToClients ? 'Y' : 'N',
                'rtl'                => $rtl ? 'Y' : 'N',
                'type'               => $type
            );

            if (empty($noteId)) {
                $arrData['member_id']   = (int)$memberId;
                $arrData['author_id']   = (int)$authorId;
                $arrData['create_date'] = empty($createdOn) ? date('Y-m-d H:i:s') : $createdOn;
                $arrData['note_color']  = (int)$noteColor;
                $arrData['is_system']   = $booIsSytem ? 'Y' : 'N';

                if (is_numeric($companyId)) {
                    $arrData['company_id'] = (int)$companyId;
                }

                $noteId = $this->_db2->insert('u_notes', $arrData);
            } else {
                $this->_db2->update('u_notes', $arrData, ['note_id' => $noteId]);
            }

            $targetPath    = $this->_config['directory']['tmp'] . '/uploads/';
            $arrMemberInfo = $this->_clients->getMemberInfo($memberId);
            $booLocal      = $this->_company->isCompanyStorageLocationLocal($arrMemberInfo['company_id']);
            $filePath      = $this->_files->getClientNoteAttachmentsPath($arrMemberInfo['company_id'], $memberId, $booLocal) . '/' . $noteId;

            $arrSavedFileAttachments = $this->getNoteAttachments($noteId);

            foreach ($arrSavedFileAttachments as $savedFileAttachment) {
                $booFound = false;
                foreach ($attachments as $attachment) {
                    if ($savedFileAttachment['id'] == $attachment['attach_id']) {
                        $booFound = true;
                    }
                }
                if (!$booFound) {
                    $this->_db2->delete('client_notes_attachments', ['id' => (int)$savedFileAttachment['id']]);
                    $this->_files->deleteFile($filePath . '/' . $savedFileAttachment['id'], $booLocal);
                }
            }

            foreach ($attachments as $attachment) {
                if (array_key_exists('file_id', $attachment)) {
                    continue;
                }

                $arrInsertAttachmentInfo = array(
                    'note_id'   => (int)$noteId,
                    'member_id' => (int)$memberId,
                    'name'      => $attachment['name'],
                    'size'      => $attachment['size']
                );

                $attachmentId = $this->_db2->insert('client_notes_attachments', $arrInsertAttachmentInfo);

                $attachment['tmp_name'] = $this->_encryption->decode($attachment['tmp_name']);
                // File path is in such format: path/to/file#check_id
                if (preg_match('/(.*)#(\d+)/', $attachment['tmp_name'], $regs)) {
                    $attachment['tmp_name'] = $regs[1];
                }

                $tmpPath = str_replace('//', '/', $targetPath) . $attachment['tmp_name'];

                if (file_exists($tmpPath)) {
                    // Get correct path to the file in the cloud
                    $pathToFile = $filePath . '/' . $attachmentId;

                    if ($booLocal) {
                        $this->_files->moveLocalFile($tmpPath, $this->_files->fixFilePath($pathToFile));
                    } else {
                        $this->_files->getCloud()->uploadFile($tmpPath, $this->_files->getCloud()->fixFilePath($pathToFile));
                    }
                    unlink($tmpPath);
                }
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Delete notes (system notes cannot be deleted)
     *
     * @param array|int $notes
     * @return false|int
     */
    public function deleteNotes($notes)
    {
        $notes = (array)$notes;
        if (!empty($notes)) {
            $companyId = $this->_auth->getCurrentUserCompanyId();
            $booLocal  = $this->_company->isCompanyStorageLocationLocal($companyId);

            foreach ($notes as $noteId) {
                $noteInfo = $this->getNote($noteId);
                if (isset($noteInfo['member_id']) && !empty($noteInfo['member_id'])) {
                    $attachmentsPath = $this->_files->getClientNoteAttachmentsPath($companyId, $noteInfo['member_id'], $booLocal);

                    $folderPath = $attachmentsPath . '/' . $noteId;
                    $this->_files->deleteFolder($folderPath, $booLocal);
                }
            }

            return $this->_db2->delete('u_notes', ['note_id' => $notes]);
        }

        return false;
    }

    /**
     * Load notes list for specific members
     *
     * @param array $arrMembersIds
     * @return array
     */
    public function getMembersNotes($arrMembersIds)
    {
        $select = (new Select())
            ->from(['n' => 'u_notes'])
            ->join(['m' => 'members'], 'm.member_id = n.author_id', ['fName', 'lName'], Select::JOIN_LEFT_OUTER)
            ->join(['m2' => 'members'], 'm2.member_id = n.member_id', ['clientFirstName' => 'fName', 'clientLastName' => 'lName', 'userType'], Select::JOIN_LEFT_OUTER)
            ->join(['c' => 'clients'], 'c.member_id = n.member_id', 'fileNumber', Select::JOIN_LEFT_OUTER)
            ->where(['n.member_id' => $arrMembersIds])
            ->order(['m2.lName ASC', 'n.create_date DESC']);

        return $this->_db2->fetchAll($select);
    }

    /**
     * Get the list of attachments for a specific note
     *
     * @param int $noteId
     * @return array
     */
    public function getNoteAttachments($noteId)
    {
        $select = (new Select())
            ->from('client_notes_attachments')
            ->where(['note_id' => (int)$noteId]);

        return $this->_db2->fetchAll($select);
    }

}
