<?php

namespace Mailer\Controller;

use Clients\Service\Clients;
use Exception;
use Files\Service\Files;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Where;
use Laminas\Filter\StripTags;
use Laminas\Http\Client;
use Notes\Service\Notes;
use Officio\Common\Json;
use Laminas\Session\Container;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Mailer\LoaderDispatcher;
use Mailer\Service\Mailer;
use Officio\BaseController;
use Officio\Email\Models\Attachment;
use Officio\Email\Models\Folder;
use Officio\Email\Models\MailAccount;
use Officio\Email\Models\Message;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Common\Service\Settings;
use Officio\Templates\SystemTemplates;
use Prospects\Service\CompanyProspects;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Templates\Service\Templates;
use Laminas\Validator\EmailAddress;

/**
 * Main mail controller
 *
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class IndexController extends BaseController
{

    /** @var Clients */
    protected $_clients;

    /** @var Mailer */
    protected $_mailer;

    /** @var Company */
    protected $_company;

    /** @var Files */
    protected $_files;

    /** @var CompanyProspects */
    protected $_companyProspects;

    /** @var Templates */
    protected $_templates;

    /** @var Encryption */
    protected $_encryption;

    /** @var SystemTemplates */
    protected $_systemTemplates;

    /** @var Notes */
    private $_notes;

    public function initAdditionalServices(array $services)
    {
        $this->_company          = $services[Company::class];
        $this->_clients          = $services[Clients::class];
        $this->_files            = $services[Files::class];
        $this->_companyProspects = $services[CompanyProspects::class];
        $this->_templates        = $services[Templates::class];
        $this->_mailer           = $services[Mailer::class];
        $this->_encryption       = $services[Encryption::class];
        $this->_systemTemplates  = $services[SystemTemplates::class];
        $this->_notes            = $services[Notes::class];
    }

    public function indexAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);

        $accounts = MailAccount::getAccounts($this->_auth->getCurrentUserId());
        $view->setVariable('hasUserEmailAccount', count($accounts) > 0);
        $view->setVariable('currentMemberId', $this->_auth->getCurrentUserId());
        $view->setVariable('mailEnabled', $this->_config['mail']['enabled']);

        return $view;
    }

    public function deleteAction()
    {
        $view = new JsonModel();

        $strError = '';

        try {
            $mailId = Json::decode($this->findParam('mail_id'), Json::TYPE_ARRAY);
            $filter = new StripTags();
            $accountId = (int)Json::decode($filter->filter($this->findParam('account_id')), Json::TYPE_ARRAY);

            // Only ajax request is allowed
            if (!$this->getRequest()->isXmlHttpRequest()) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            // Check if current user can access this account
            if (empty($strError) && !MailAccount::hasAccessToAccount($this->_auth->getCurrentUserId(), $accountId)) {
                $strError = $this->_tr->translate('Incorrectly selected account');
            }

            // Check if current member can access this/these mail(s)
            if (empty ($strError) && !$this->_mailer->hasAccessToMail($this->_auth->getCurrentUserId(), $mailId)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            // All is okay, delete specific email(s)
            if (empty ($strError)) {
                $strError = $this->_mailer->delete($mailId, $accountId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal server error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
        }

        return $view->setVariables(array("success" => empty ($strError), 'msg' => $strError));
    }

    public function moveMailsAction()
    {
        $view = new JsonModel();

        $strError = '';

        try {
            $mails_array = Json::decode($this->findParam('mails_array'), Json::TYPE_ARRAY);
            $folder_id = (int)Json::decode($this->findParam('folder_id'), Json::TYPE_ARRAY);
            $accountId = (int)Json::decode($this->findParam('account_id'), Json::TYPE_ARRAY);

            // Only ajax request is allowed
            if (!$this->getRequest()->isXmlHttpRequest()) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            // Check if current user can access this account
            if (empty($strError) && !MailAccount::hasAccessToAccount($this->_auth->getCurrentUserId(), $accountId)) {
                $strError = $this->_tr->translate('Incorrectly selected account');
            }

            // Check if current member can access this/these mail(s)
            if (empty($strError) && !$this->_mailer->hasAccessToMail($this->_auth->getCurrentUserId(), $mails_array)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            // All is okay, move specific email(s)
            if (empty ($strError)) {
                $res = $this->_mailer->moveToFolder($mails_array, $folder_id, $accountId);
                $strError = $res === true ? '' : $res;
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal server error.');
            if ($e->getMessage() != "Can't connect to email account.") {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
            }
        }


        return $view->setVariables(array("success" => empty ($strError), 'msg' => $strError));
    }

    /**
     * @deprecated
     */
    public function clearTrashAction()
    {
        $view = new JsonModel();

        $strError = '';
        $accountId = (int)Json::decode($this->findParam('account_id'), Json::TYPE_ARRAY);

        // Only ajax request is allowed
        if (!$this->getRequest()->isXmlHttpRequest()) {
            $strError = $this->_tr->translate('Insufficient access rights');
        }

        // Check if current user can access this account
        if (empty($strError) && !MailAccount::hasAccessToAccount($this->_auth->getCurrentUserId(), $accountId)) {
            $strError = $this->_tr->translate('Incorrectly selected account');
        }


        if (empty($strError)) {
            try {
                // Clear trash folder (all emails and subfolders will be deleted)
                $this->_mailer->clearTrash($this->_files, $accountId);
            } catch (Exception $e) {
                $strError = $this->_tr->translate('Internal server error.');
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }
        }

        return $view->setVariables(array("success" => empty ($strError), 'msg' => $strError));
    }

    public function getEmailByAction()
    {
        $strError = '';
        $mail     = array();

        try {
            $mailId    = (int)Json::decode($this->params()->fromPost('mail_id'), Json::TYPE_ARRAY);
            $accountId = (int)Json::decode($this->params()->fromPost('account_id'), Json::TYPE_ARRAY);

            $mailAccount = new MailAccount($accountId);

            // Only ajax request is allowed
            if (!$this->getRequest()->isXmlHttpRequest()) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            // Check if current user can access this account
            if (empty($strError) && !$mailAccount::hasAccessToAccount($this->_auth->getCurrentUserId(), $accountId)) {
                $strError = $this->_tr->translate('Incorrectly selected account');
            }

            if (empty($strError) && !$mailAccount->hasAccessToEmail($accountId, $mailId)) {
                $strError = $this->_tr->translate('Incorrectly selected account');
            }

            if (empty($strError)) {
                $booLocal    = $this->_company->isCompanyStorageLocationLocal($this->_auth->getCurrentUserCompanyId());
                $mailDetails = $this->_mailer->getEmailDetailById($mailId, $booLocal);
                $dbEmail     = $mailDetails['email'];
                if (empty($dbEmail['is_downloaded'])) {
                    $acc_details = $mailAccount->getAccountDetails();

                    if ($acc_details['inc_type'] == 'imap') {
                        $mailDetails = $this->_mailer->getEmailFromImap($dbEmail['uid'], $dbEmail['id'], $accountId);
                        if (!empty($mailDetails)) {
                            $this->_mailer->appendBodyAndAttachmentsToEmailInDb($mailDetails, $dbEmail['id']);
                        }

                        $mailDetails = $this->_mailer->getEmailDetailById($mailId, $booLocal);
                    }
                }
                $dbEmail = $mailDetails['email'];

                $mail = array(
                    'mail_body'      => $dbEmail['body_html'],
                    'has_attachment' => (int)$dbEmail['has_attachments'],
                    'attachments'    => $mailDetails['attachments'],
                    'is_downloaded'  => $dbEmail['is_downloaded']
                );
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal server error.');

            if ($e->getMessage() != "Can't connect to email account.") {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
            }
        }

        $arrResult = [
            'success' => empty ($strError),
            'message' => $strError,
            'mail'    => $mail
        ];

        return new JsonModel($arrResult);
    }

    public function mailsListAction()
    {
        $view = new JsonModel();

        set_time_limit(10 * 60); // 10 minutes
        ini_set('memory_limit', '1024M');

        // Close session for writing - so next requests can be done
        session_write_close();


        // Default values
        $strError    = '';
        $resultMails = [];
        $total       = 0;
        $totalUnread = 0;

        try {
            $filter = new StripTags();
            $start = (int)$this->findParam('start', 0);
            $dir = $filter->filter(strtoupper($this->findParam('dir', '')));
            $sort = $filter->filter($this->findParam('sort'));
            $query = $filter->filter($this->findParam('query'));
            $limit = (int)$this->findParam('limit', 0);
            $folderId = $filter->filter(Json::decode($this->findParam('folder_id'), Json::TYPE_ARRAY));
            $accountId = $filter->filter(Json::decode($this->findParam('account_id'), Json::TYPE_ARRAY));

            if (empty($accountId)) {
                $strError = 'Incorrectly selected account';
            }

            // Only ajax request is allowed
            if (empty($strError) && !$this->getRequest()->isXmlHttpRequest()) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            // Check if current user can access this account
            if (empty($strError) && ($mailAccount = new MailAccount($accountId)) && !$mailAccount::hasAccessToAccount($this->_auth->getCurrentUserId(), $accountId)) {
                $strError = $this->_tr->translate('Incorrectly selected account');
            }


            // If all is correct - load data from DB
            if (empty($strError)) {
                $arrAccountDetails = $mailAccount->getAccountDetails();

                if (!in_array($dir, array('ASC', 'DESC'))) {
                    $dir = 'DESC';
                }

                if (!is_numeric($start) || $start < 0 || $start > 50000) {
                    $start = 0;
                }

                $limit = empty($limit) ? (int)$arrAccountDetails['per_page'] : $limit;
                if (!in_array($limit, array(10, 15, 20, 25, 50, 100))) {
                    $limit = 20;
                }

                $orderBy = array(
                    'mail_flag' => 'flag',
                    'mail_date' => 'sent_date',
                    'mail_subject' => 'subject',
                    'mail_to' => 'to',
                    'mail_from' => 'from',
                    'has_attachment' => 'has_attachments'
                );

                // Use sorting by default if needed
                $sortBy = array_key_exists($sort, $orderBy) ? $orderBy[$sort] : 'sent_date';

                $phpTimeZone = date_default_timezone_get();
                $sqlTimeZone = $this->_mailer->getSqlTimeZone();

                date_default_timezone_set($arrAccountDetails['timezone']);

                $this->_mailer->setSqlTimeZone(date('P'));

                $folderModel = new Folder($folderId);
                $mappingFolderId = $folderModel->getMappingFolderId();

                list($filter, $imapSearchResults) = $this->prepareMailFilter($accountId, $folderId, $query);
                // Load mails list in relation to selected account!
                $mailsList = Message::getEmailsList($accountId, $folderId, $sortBy, $dir, $limit, $start, false, $filter, $imapSearchResults, $mappingFolderId);

                date_default_timezone_set($phpTimeZone);
                $this->_mailer->setSqlTimeZone($sqlTimeZone);

                $total       = $mailAccount->getNumberOfMessagesInFolder($folderId, $query, array(), $mappingFolderId);
                $totalUnread = $mailAccount->getTotalUnreadCount($folderId, '', $mappingFolderId);

                $strFolderId = $folderModel->getFolderMachineName();

                $booLocal          = $this->_company->isCompanyStorageLocationLocal($this->_auth->getCurrentUserCompanyId());
                $companyEmailsPath = $this->_files->getCompanyEmailAttachmentsPath($this->_auth->getCurrentUserCompanyId(), $booLocal);
                foreach ($mailsList as $email) {
                    $resultMails[] = [
                        'mail_id'        => $email['id'],
                        'mail_id_folder' => $strFolderId,
                        'mail_from'      => str_replace(">", "&gt;", str_replace("<", "&lt;", $email['from'] ?? '')),
                        'mail_to'        => str_replace(">", "&gt;", str_replace("<", "&lt;", htmlspecialchars_decode($email['to'] ?? '', ENT_QUOTES))),
                        'mail_cc'        => str_replace(">", "&gt;", str_replace("<", "&lt;", htmlspecialchars_decode($email['cc'] ?? '', ENT_QUOTES))),
                        'mail_bcc'       => str_replace(">", "&gt;", str_replace("<", "&lt;", htmlspecialchars_decode($email['bcc'] ?? '', ENT_QUOTES))),
                        'mail_subject'   => $email['subject'],
                        'mail_date'      => $email['sent_date'],
                        'mail_body'      => $email['body_html'],
                        'mail_unread'    => $email['seen'] == 0,
                        'mail_replied'   => !empty($email['replied']),
                        'mail_forwarded' => !empty($email['forwarded']),
                        'mail_flag'      => $email['flag'],
                        'has_attachment' => (bool)$email['has_attachments'],
                        'attachments'    => (!empty($email['has_attachments'])) ? $this->_mailer->getMailAttachments($email['id'], $booLocal, $companyEmailsPath) : [],
                        'is_downloaded'  => $email['is_downloaded']
                    ];
                }
            }
        } catch (Exception $e) {
            if (!in_array($e->getMessage(), array("Can't connect to email account.", 'cannot read - connection closed?'))) {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
            }
        }

        $arrResult = array(
            "mails"       => $resultMails,
            "totalCount"  => (int)$total,
            "totalUnread" => (int)$totalUnread
        );

        return $view->setVariables($arrResult);
    }

    /**
     * @param $accountId
     * @param $folderId
     * @param bool $isImap
     * @param string $filter
     * @return array
     */
    private function prepareMailFilter($accountId, $folderId, $filter = '', $isImap = false)
    {
        $imapSearchResults = array();
        if ($isImap && !empty($filter)) {
            $this->_mailer->connect($accountId);
            $mailAccount         = new MailAccount($accountId);
            $accDetails          = $mailAccount->getAccountDetails();
            $preparedSearchQuery = array();
            if ((int)$accDetails['inc_fetch_from_date'] > 0) {
                $preparedSearchQuery[] = array(
                    'field' => 'since',
                    'value' => (int)$accDetails['inc_fetch_from_date']
                );
            }

            $words = explode(' ', $filter);
            foreach ($words as $word) {
                $preparedSearchQuery[] = array(
                    'field' => 'body',
                    'value' => $word
                );
            }

            $mailFolderInstance = new Folder($folderId);
            $imapFolderList = $this->_mailer->getFoldersListForIMAP();

            $folderInfo = $mailFolderInstance->getFolderInfo();
            $folder = $this->_mailer->findStorageFolderByGlobalName($imapFolderList, $folderInfo['full_path']);

            if ($folder && $folder->isSelectable()) {
                $this->_mailer->_storage->selectFolder($folder);

                $searchResults = $this->_mailer->_storage->search($preparedSearchQuery);
                if (is_array($searchResults)) {
                    foreach ($searchResults as $uid) {
                        $imapSearchResults[] = $uid['UID'];
                    }
                }
            }
        }

        if (!empty($filter)) {
            $filter = [(new Where())
                ->nest()
                ->like('m.from', "%$filter%")
                ->or
                ->like('m.to', "%$filter%")
                ->or
                ->like('m.subject', "%$filter%")
                ->or
                ->like('m.body_html', "%$filter%")
                ->unnest()];
        }
        return array($filter, $imapSearchResults);
    }

    /**
     * Load levels + folders list in each level
     * @param bool $booImapFoldersSubscribe
     * @return array(levelsAmount, array sorted by `levels` and `order`);
     */
    private function getFoldersHierarchyList($accountId, $booImapFoldersSubscribe = false)
    {
        $selectLevels = (new Select())
            ->from('eml_folders')
            ->columns(['level' => new Expression('MAX(`level`)')])
            ->where(['id_account' => (int)$accountId]);

        $maxLevel = (int)$this->_db2->fetchOne($selectLevels);

        $foldersByLevels = array();
        for ($i = 0; $i <= $maxLevel; $i++) {
            $arrWhere = [
                'fldr.id_account' => (int)$accountId,
                'fldr.level'      => $i

            ];

            if ($booImapFoldersSubscribe) {
                $arrWhere['fldr.id_folder'] = '0';
            } else {
                $arrWhere['fldr.visible'] = 1;
            }

            $selectFolders = (new Select())
                ->from(array('fldr' => 'eml_folders'))
                ->columns(array(Select::SQL_STAR, 'total_count' => new Expression('0'), 'unread_count' => new Expression('0')))
                ->where($arrWhere)
                ->order(array('fldr.id_parent ASC', 'fldr.order ASC'));

            $arrFolders = $this->_db2->fetchAll($selectFolders);

            if (!empty($arrFolders)) {
                // Calculate "total" and "unread" messages in each folder
                $arrFolderIds = array();
                foreach ($arrFolders as $arrFolderInfo) {
                    $arrFolderIds[] = $arrFolderInfo['id'];
                }

                $selectMessages = (new Select())
                    ->from(array('msg' => 'eml_messages'))
                    ->columns(array('id_folder', 'total_count' => new Expression('COUNT(msg.id)'), 'unread_count' => new Expression('COUNT(msg.id) - SUM(msg.seen)')))
                    ->where(['msg.id_folder' => $arrFolderIds])
                    ->group('msg.id_folder');

                $arrFoldersMessages = $this->_db2->fetchAssoc($selectMessages);

                foreach ($arrFolders as $key => $arrFolderInfo) {
                    if (isset($arrFoldersMessages[$arrFolderInfo['id']])) {
                        $arrFolders[$key]['total_count'] = $arrFoldersMessages[$arrFolderInfo['id']]['total_count'];
                        $arrFolders[$key]['unread_count'] = $arrFoldersMessages[$arrFolderInfo['id']]['unread_count'];
                    }
                }
            }

            $foldersByLevels[] = $arrFolders;
        }

        return array($maxLevel, $foldersByLevels);
    }

    /**
     * TODO Move out of here
     * @param int $accountId
     * @param bool $booImapFoldersSubscribe
     * @return array
     */
    private function getHierarchyForExtJs($accountId, $booImapFoldersSubscribe = false)
    {
        list($levels, $foldersByLevels) = $this->getFoldersHierarchyList($accountId, $booImapFoldersSubscribe);

        // In some cases there is empty account, so skip it
        if (is_null($levels)) {
            return array();
        }

        for ($i = $levels; $i >= 0; $i--) {
            foreach ($foldersByLevels[$i] as &$folder) {
                $folder['folder_id'] = $folder['id_folder'];
                $folder['text'] = $folder['label'];
                $folder['folder_label'] = $folder['label'];
                $folder['cls'] = Mailer::getExtJsFolderClass($folder['id_folder']);
                $folder['leaf'] = false;
                $folder['expanded'] = false;
                $folder['real_folder_id'] = $folder['id'];
                $folder['isTarget'] = $folder['selectable'] > 0;

                if ($booImapFoldersSubscribe) {
                    $folder['checked'] = $folder['visible'] > 0;
                }

                $folder['draggable'] = $folder['selectable'];
                $folder['is_default'] = preg_match(
                        '/' .
                        Folder::INBOX . '|' .
                        Folder::SENT . '|' .
                        Folder::DRAFTS . '|' .
                        Folder::TRASH . '/',
                        $folder['id_folder'],
                        $matches
                    ) != 0;

                #unset unusable elements:
                unset($folder['id_folder'], $folder['order'], $folder['label'], $folder['id_account']);

                if (!isset($folder ['children'])) {
                    $folder ['leaf'] = false;
                    $folder['children'] = array();
                    $folder['expanded'] = true;
                }

                $child = null;
                if (isset($foldersByLevels[$i - 1])) {
                    foreach ($foldersByLevels[$i - 1] as $k => &$value) {
                        if ($foldersByLevels[$i - 1][$k]['id'] == $folder['id_parent']) {
                            $child = $folder;
                            if (isset($folder['children']) && empty($folder['children']) || !isset($folder['children'])) {
                                $child['expanded'] = true;
                            } else {
                                $child['expanded'] = false;
                            }
                            $child['leaf'] = false;
                            $value['children'][] = $child;
                            break;
                        }
                    }
                }
            }
        }
        unset($child);
        unset($folder);

        if (!$booImapFoldersSubscribe) {
            for ($i = $levels; $i >= 0; $i--) {
                foreach ($foldersByLevels[0] as $key => $folder) {
                    if (isset($folder['id_mapping_folder']) && $folder['id_mapping_folder'] != 0) {
                        $mappingFolder = array();
                        $mappingFolderKey = $mappingFolderChildKey = '';

                        $count = count($foldersByLevels[0]);
                        for ($c = 0; $c < $count; $c++) {
                            if ($foldersByLevels[0][$c]['id'] == $folder['id_mapping_folder']) {
                                $mappingFolder = $foldersByLevels[0][$c];
                                $mappingFolderKey = $c;
                            }
                            foreach ($foldersByLevels[0][$c]['children'] as $j => $child) {
                                if ($child['id'] == $folder['id_mapping_folder']) {
                                    $mappingFolder = $child;
                                    $mappingFolderKey = $c;
                                    $mappingFolderChildKey = $j;
                                }
                            }
                        }

                        if (isset($mappingFolder['children']) && !empty($mappingFolder['children'])) {
                            foreach ($mappingFolder['children'] as $child) {
                                $foldersByLevels[$i][$key]['children'][] = $child;
                            }
                        }

                        if (is_numeric($mappingFolderKey)) {
                            if (is_numeric($mappingFolderChildKey)) {
                                unset($foldersByLevels[0][$mappingFolderKey]['children'][$mappingFolderChildKey]);
                                if (!empty($foldersByLevels[0][$mappingFolderKey]['children'])) {
                                    sort($foldersByLevels[0][$mappingFolderKey]['children']);
                                } else {
                                    $foldersByLevels[0][$mappingFolderKey]['expanded'] = true;
                                }
                            } else {
                                unset($foldersByLevels[0][$mappingFolderKey]);
                                sort($foldersByLevels[0]);
                            }
                        }
                    }
                }
            }
        }

        return $foldersByLevels[0];
    }


    public function foldersListAction()
    {
        $view = new JsonModel();

        set_time_limit(60 * 60); // 1 hour
        ini_set('memory_limit', '512M');

        $arrFolders = array();
        $strError = '';

        // Only ajax request is allowed
        if (!$this->getRequest()->isXmlHttpRequest()) {
            $strError = $this->_tr->translate('Insufficient access rights');
        }

        try {
            // Check if current user can access this account
            $filter = new StripTags();
            $accountId = $filter->filter(Json::decode($this->findParam('account_id'), Json::TYPE_ARRAY));
            $booImapFoldersSubscribe = (bool)Json::decode($this->findParam('imap_folders_subscribe', 0), Json::TYPE_ARRAY);
            if (empty ($strError)) {
                if (!MailAccount::hasAccessToAccount($this->_auth->getCurrentUserId(), $accountId)) {
                    $strError = $this->_tr->translate('Incorrectly selected account');
                }
            }

            if (empty ($strError)) {
                $arrFolders = $this->getHierarchyForExtJs($accountId, $booImapFoldersSubscribe);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
        }

        // Show json result
        return $view->setVariables($arrFolders);
    }

    public function checkEmailsInFolderAction()
    {
        $view = new JsonModel();

        ignore_user_abort(false);

        set_time_limit(60 * 60); // 1 hour
        ini_set('memory_limit', '512M');
        session_write_close();

        $newEmailsCount = 0;
        $strError = '';
        $arrFolders = array();

        try {
            $folderId = Json::decode($this->findParam('folder_id'), Json::TYPE_ARRAY);
            $accountId = (int)Json::decode($this->findParam('account_id'), Json::TYPE_ARRAY);

            // Only ajax request is allowed
            if (empty($strError) && !$this->getRequest()->isXmlHttpRequest()) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            // Check if current user can access this account
            if (empty($strError) && (empty($accountId) || !MailAccount::hasAccessToAccount($this->_auth->getCurrentUserId(), $accountId))) {
                $strError = $this->_tr->translate('Incorrectly selected account');
            }


            // Check for new emails for the current folder
            $mailAccount = new MailAccount($accountId);
            if (empty($strError) && !$mailAccount->isCheckInProgress()) {
                try {
                    $mailAccount->setIsChecking(1);
                    list($newEmailsCount, $arrFolders) = $this->_mailer->syncIMAPFolder($accountId, $folderId);
                    $mailAccount->setIsChecking(0);
                } catch (Exception $e) {
                    $mailAccount->setIsChecking(0);

                    // rethrow it
                    throw $e;
                }
            }
        } catch (Exception $e) {
            $newEmailsCount = 0;
            if ($e->getMessage() != "Can't connect to email account.") {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }
        }

        $arrResult = array(
            'newEmailsCount' => $newEmailsCount,
            'folders'        => $arrFolders
        );

        return $view->setVariables($arrResult);
    }

    public function checkEmailAction()
    {
        // Required to be sure that all requests will be done/finished
        // Especially important for emails checking
        ignore_user_abort(true);

        set_time_limit(60 * 60); // 1 hour
        ini_set('memory_limit', -1);
        session_write_close();

        // We try turn off buffering at all and only respond with correct data
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_implicit_flush();


        // Required to be sure that gzip will not break our partial output
        // And a user will see all statuses in the status bar
        header("Content-Encoding: none");
        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);


        $strError = '';
        echo "<html><body>";
        echo str_repeat(" ", 1024), "\n";

        $booManual         = $this->params()->fromQuery('manual') == 1;
        $booCheckInboxOnly = $this->params()->fromQuery('check_inbox_only') == 1;

        $accountId = (int)Json::decode($this->params()->fromQuery('account_id'), Json::TYPE_ARRAY);
        if (!MailAccount::hasAccessToAccount($this->_auth->getCurrentUserId(), $accountId)) {
            // Reset it, because we need to update status in DB
            // And we don't want update account status if there is no access to it
            $accountId = 0;
            $strError  = $this->_tr->translate('Insufficient access rights');
        }

        $mailAccount    = new MailAccount($accountId);
        $loadDispatcher = new LoaderDispatcher($mailAccount);
        if (empty($strError)) {
            try {
                if (!$mailAccount->isCheckInProgress()) {
                    try {
                        $mailAccount->setIsChecking(1);
                        //Send accountId to RabbitMQ to user_queue id for future update
                        //$this->mail_account->sendAccountToRabbitMQ($accountId);
                        $loaderDispatcher = new LoaderDispatcher($mailAccount);
                        $this->_mailer->sync($accountId, $loaderDispatcher, $booManual, $booCheckInboxOnly);
                        $mailAccount->setIsChecking(0);
                    } catch (Exception $e) {
                        $mailAccount->setIsChecking(0);

                        // rethrow it
                        throw $e;
                    }
                } else {
                    // Load saved status and show
                    $booRunCheckDB = true;
                    do {
                        $arrAccountInfo = $mailAccount->getAccountDetails();
                        if (!empty($arrAccountInfo['is_checking'])) {
                            if (!empty($arrAccountInfo['checking_status'])) {
                                $arrCheckingStatus = unserialize($arrAccountInfo['checking_status']);
                                if (is_array($arrCheckingStatus) && isset($arrCheckingStatus['s'])) {
                                    // If last change was more than 15 minutes - mark it as stopped
                                    if (time() - $arrCheckingStatus['update_time'] > 15 * 60) {
                                        $mailAccount->setIsChecking(0);
                                        $strError      = $this->_tr->translate('Time out. Forced stop.');
                                        $booRunCheckDB = false;
                                    } else {
                                        $loadDispatcher::outputResult($arrCheckingStatus);
                                        // Try again with delay in 1 second
                                        sleep(1);
                                    }
                                } else {
                                    $strError      = $this->_tr->translate('Fetching in progress...');
                                    $booRunCheckDB = false;
                                }
                            } else {
                                $strError      = $this->_tr->translate('Fetching in progress...');
                                $booRunCheckDB = false;
                            }
                        } else {
                            if (!empty($arrAccountInfo['checking_status'])) {
                                $arrCheckingStatus = unserialize($arrAccountInfo['checking_status']);
                                if (is_array($arrCheckingStatus) && isset($arrCheckingStatus['s'])) {
                                    $loadDispatcher::outputResult($arrCheckingStatus);
                                }
                            } else {
                                $strError = $this->_tr->translate('Fetching in progress...');
                            }
                            $booRunCheckDB = false;
                        }
                    } while ($booRunCheckDB);
                }
            } catch (Exception $e) {
                if ($e->getMessage() != 'The check was cancelled by user') {
                    $strError = $this->_tr->translate('Error.');

                    if (!in_array($e->getMessage(), array("Can't connect to email account.", 'cannot read - connection closed?'))) {
                        $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
                    }
                }
            }
        }
        if (!empty($strError)) {
            $loadDispatcher->change($strError, 100, null, true);
        }

        echo "</body></html>";
        exit();
    }


    public function markMailAsReadAction()
    {
        $strError = '';

        try {
            // Get and check incoming params
            $mailId        = Json::decode($this->params()->fromPost('mail_id'), Json::TYPE_ARRAY);
            $booMailAsRead = Json::decode($this->params()->fromPost('mail_as_read'), Json::TYPE_ARRAY);
            $accountId     = (int)Json::decode($this->params()->fromPost('account_id'), Json::TYPE_ARRAY);
            $folderId      = Json::decode($this->params()->fromPost('folder_id'), Json::TYPE_ARRAY);
            $arrMailIds    = is_array($mailId) ? $mailId : array($mailId);

            // Only ajax request is allowed
            if (!$this->getRequest()->isXmlHttpRequest()) {
                $strError = $this->_tr->translate('Incorrectly selected mail.');
            }

            if (empty($strError) && !count($arrMailIds)) {
                $strError = $this->_tr->translate('Please select at least one mail and try again.');
            }

            if (empty($strError)) {
                foreach ($arrMailIds as $m) {
                    if (!is_numeric($m)) {
                        $strError = $this->_tr->translate('Incorrectly selected mail.');
                        break;
                    }
                }
            }

            // Check if current user can access this account
            if (empty($strError) && !MailAccount::hasAccessToAccount($this->_auth->getCurrentUserId(), $accountId)) {
                $strError = $this->_tr->translate('Incorrectly selected account');
            }

            // That means, that all emails must be marked
            $booAllEmails = count($arrMailIds) == 1 && $arrMailIds[0] == 0;
            if (empty($strError) && !$booAllEmails && !$this->_mailer->hasAccessToMail($this->_auth->getCurrentUserId(), $arrMailIds)) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            if (empty ($strError)) {
                if ($booAllEmails) {
                    $strError = $this->_mailer->toggleAllMailRead($accountId, $folderId, (int)$booMailAsRead);
                } else {
                    $strError = $this->_mailer->markMailAsReadOrNot($arrMailIds, (int)$booMailAsRead, $accountId);
                }
            }
        } catch (Exception $e) {
            if ($e->getMessage() != "Can't connect to email account.") {
                $strError = $this->_tr->translate('Internal error');
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
            } else {
                $strError = $e->getMessage();
            }
        }

        return new JsonModel(array("success" => empty ($strError), 'msg' => $strError));
    }

    public function downloadAttachAction()
    {
        set_time_limit(10 * 60); // 10 minutes
        ini_set('memory_limit', '512M');

        try {
            $filter       = new StripTags();
            $attachId     = $this->params()->fromQuery('attach_id');
            $type         = $filter->filter($this->params()->fromQuery('type', ''));
            $templateId   = (int)$this->params()->fromQuery('template_id', 0);
            $fileRealName = $filter->filter($this->params()->fromQuery('name'));

            $path     = '';
            $booLocal = $this->_auth->isCurrentUserCompanyStorageLocal();
            switch ($type) {
                case 'uploaded':
                    // File path is in such format: path/to/file#client_id
                    $fileName = $this->_encryption->decode($attachId);
                    if (preg_match('/(.*)#(\d+)/', $fileName, $regs)) {
                        $path = $this->_members->hasCurrentMemberAccessToMember($regs[2]) ? $this->_config['directory']['tmp'] . '/uploads/' . $regs[1] : '';

                        // uploaded files are in the tmp dir
                        $booLocal = true;
                    }
                    break;

                case 'letter_template':
                    if (!empty($templateId) && $this->_templates->getAccessRightsToTemplate($templateId)) {
                        // File path is in such format: path/to/file#client_id
                        $fileName = $this->_encryption->decode($attachId);
                        if (preg_match('/(.*)#(\d+)/', $fileName, $regs)) {
                            // generated pdf files from letter templates are in the tmp dir
                            $booLocal = true;

                            // client id can be empty - means we opened file not for the client, but e.g. from Email dialog
                            if (empty($regs[2]) || $this->_members->hasCurrentMemberAccessToMember($regs[2])) {
                                $path = $regs[1];
                            }
                        }
                    }
                    break;

                case 'template_file_attachment':
                    if (!empty($templateId) && $this->_templates->getAccessRightsToTemplate($templateId)) {
                        $path = $this->_encryption->decode($attachId);
                    }

                    if (!empty($path)) {
                        $folderPath = $this->_files->getCompanyTemplateAttachmentsPath($this->_auth->getCurrentUserCompanyId(), $booLocal) . '/' . $templateId;
                        if ($booLocal) {
                            $filePath = $folderPath . '/' . $this->_files::extractFileName($path);
                        } else {
                            $filePath = $folderPath . '/' . $this->_files->getCloud()->getFileNameByPath($path);
                        }
                        if ($filePath != $path) {
                            $path = '';
                        }
                    }
                    break;

                case 'phantomjs':
                    // generated pdf files from assigned pdf/xod forms are in the tmp dir
                    $booLocal = true;

                    $path = $this->_encryption->decode($attachId);
                    if (!$this->_files->checkAccessToPhantomJsFolder($path)) {
                        $path = '';
                    }
                    break;

                default:
                    if (!empty($attachId) && is_numeric($attachId)) {
                        $memberId    = $this->_auth->getCurrentUserId();
                        $companyId   = $this->_auth->getCurrentUserCompanyId();
                        $companyPath = $this->_files->getCompanyEmailAttachmentsPath($companyId, $booLocal);
                        $attachment  = new Attachment($attachId);

                        if (!empty($attachment)) {
                            $attachInfo = $attachment->getInfo($companyPath);
                            if ($this->_mailer->hasAccessToMail($memberId, $attachInfo['id_message'])) {
                                if ($attachInfo['is_downloaded'] == '0') {
                                    $attachment = $this->_mailer->fetchAttachment($attachInfo['id_message'], array_merge(unserialize($attachInfo['part_info']), array('id' => $attachId)));
                                    if ($attachment) {
                                        $this->_mailer->saveAttachmentsToDb(array($attachment), $attachInfo['id_message'], '');
                                    }

                                    // refetch attachment info from already populated db
                                    $attachment = new Attachment($attachId);
                                    $attachInfo = $attachment->getInfo($companyPath);
                                }

                                $path         = $attachInfo['path'];
                                $fileRealName = $attachInfo['original_file_name'];
                                // We are not sure if path in DB is 'full' or 'trimmed'
                                if ($booLocal && !is_file($path)) {
                                    $path = $this->_config['directory']['companyfiles'] . $path;
                                }
                            }
                        }
                    }
                    break;
            }

            if (!empty($path)) {
                if ($booLocal) {
                    return $this->downloadFile(
                        $path,
                        $fileRealName,
                        'application/force-download',
                        true
                    );
                } else {
                    $url = $this->_files->getCloud()->getFile(
                        $path,
                        empty($fileRealName) ? null : $fileRealName
                    );

                    if ($url) {
                        return $this->redirect()->toUrl($url);
                    }
                }
            }
        } catch (Exception $e) {
            if ($e->getMessage() !== 'Failed to init attachment with the provided ID.') {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }
        }

        return $this->fileNotFound();
    }

    public function deleteFolderAction()
    {
        $view = new JsonModel();

        $strError = '';

        try {
            // Get and check incoming params
            $folderId = (int)Json::decode($this->findParam('real_folder_id'), Json::TYPE_ARRAY);
            $accountId = (int)Json::decode($this->findParam('account_id'), Json::TYPE_ARRAY);

            // Only ajax request is allowed
            if (!$this->getRequest()->isXmlHttpRequest()) {
                $strError = $this->_tr->translate('Incorrectly selected folder.');
            }

            if (empty ($strError) && !is_numeric($folderId)) {
                $strError = $this->_tr->translate('Incorrectly selected folder.');
            }

            // Check if current user can access this account
            if (empty ($strError) && !MailAccount::hasAccessToAccount($this->_auth->getCurrentUserId(), $accountId)) {
                $strError = $this->_tr->translate('Incorrectly selected account');
            }

            $foldersModel = new Folder($folderId);
            if (empty ($strError)) {
                // now check if this folder corresponds to current user, otherwise - restrict access
                if (!$foldersModel->isBelongingTo($accountId)) {
                    $strError = $this->_tr->translate('Incorrectly selected folder.');
                }
            }

            // We can't delete basic folders with (Inbox, Trash, etc.)!
            if (empty ($strError) && preg_match(
                    '/' .
                    Folder::INBOX . '|' .
                    Folder::SENT . '|' .
                    Folder::DRAFTS . '|' .
                    Folder::TRASH . '/',
                    $folderId,
                    $matches
                )) {
                $strError = $this->_tr->translate('Incorrectly selected folder.');
            }

            if (empty ($strError)) {
                $this->_mailer->connect($accountId);

                $rootParentFolder = new Folder($foldersModel->getRootParentFolderId());
                $arrRootParentInfo = $rootParentFolder->getFolderInfo();
                if ($arrRootParentInfo['id_folder'] !== 'trash') {
                    // We are going to move folder in to trash instead of removing it
                    $trashFolderId = Folder::getFolderIdByName($accountId, Folder::TRASH);
                    $order         = null;
                    if ($trashFolderId) {
                        $select = (new Select())
                            ->from('eml_folders')
                            ->columns(['order' => new Expression('MAX(`order`)')])
                            ->where(['id_parent' => $trashFolderId, 'level' => 1]);

                        $order = $this->_db2->fetchOne($select);
                    }

                    $order = ($order !== null) ? ((int)$order + 1) : 0;
                    $result = $this->_mailer->moveFolder($folderId, $trashFolderId, $order);
                } else {
                    $result = $foldersModel->deleteFolder($this->_files, true, $this->_mailer->_storage);
                }

                if (!$result) {
                    $strError = $this->_tr->translate('Folder was not removed.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
        }

        return $view->setVariables(array("success" => empty ($strError), 'msg' => $strError));
    }

    public function renameFolderAction()
    {
        $view = new JsonModel();

        $strError = '';

        try {
            // Get and check incoming params
            $filter = new StripTags();
            $accountId = (int)Json::decode($this->findParam('account_id'), Json::TYPE_ARRAY);
            $folderId = (int)Json::decode($this->findParam('real_folder_id'), Json::TYPE_ARRAY);
            $new_name = $filter->filter(trim(Json::decode($this->findParam('new_name', ''), Json::TYPE_ARRAY)));

            // Only ajax request is allowed
            if (!$this->getRequest()->isXmlHttpRequest()) {
                $strError = $this->_tr->translate('Incorrectly selected folder.');
            }

            if (empty ($strError) && !is_numeric($folderId)) {
                $strError = $this->_tr->translate('Incorrectly selected folder.');
            }

            // Check if current user can access this account
            if (empty ($strError) && !MailAccount::hasAccessToAccount($this->_auth->getCurrentUserId(), $accountId)) {
                $strError = $this->_tr->translate('Incorrectly selected account');
            }

            // Validate new name
            if (empty($strError) && (empty($new_name) || preg_match('/[\^#%@\/"\'\\\]/', $new_name) || strlen($new_name) > 255)) {
                $strError = $this->_tr->translate('Incorrect folder name (forbidden symbols: ^ # % @ / \ " \')');
            }

            $folderModel = new Folder($folderId);
            if (empty ($strError)) {
                // now check if this folder corresponds to current user, otherwise - restrict access
                if (!$folderModel->isBelongingTo($accountId)) {
                    $strError = $this->_tr->translate('Incorrectly selected folder.');
                }
            }

            if (empty($strError)) {
                $folderInnerName = $folderModel->getFolderMachineName();
                if ($folderInnerName == Folder::INBOX) {
                    $strError = $this->_tr->translate("You cannot rename Inbox folder.");
                }
            }

            if (empty ($strError)) {
                $this->_mailer->connect($accountId);
                if (!$folderModel->renameFolder($new_name, $this->_mailer->_storage)) {
                    $strError = $this->_tr->translate("Server error. Can't rename folder.");
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
        }

        return $view->setVariables(array("success" => empty($strError), 'msg' => $strError));
    }

    public function createFolderAction()
    {
        $view = new JsonModel();

        $strError = '';

        try {
            // Get and check incoming params
            $filter = new StripTags();
            $accountId = (int)Json::decode($this->findParam('account_id'), Json::TYPE_ARRAY);
            $parent_folder_id = (int)Json::decode($this->findParam('parent_folder_id'), Json::TYPE_ARRAY);
            $new_name = $filter->filter(Json::decode($this->findParam('new_name', ''), Json::TYPE_ARRAY));
            $level = (int)Json::decode($this->findParam('level'), Json::TYPE_ARRAY);

            // Only ajax request is allowed
            if (!$this->getRequest()->isXmlHttpRequest()) {
                $strError = $this->_tr->translate('Incorrectly selected folder.');
            }

            if (empty($strError) && !is_numeric($parent_folder_id)) {
                $strError = $this->_tr->translate('Incorrectly selected folder.');
            }

            // Check if current user can access this account
            if (empty($strError) && !MailAccount::hasAccessToAccount($this->_auth->getCurrentUserId(), $accountId)) {
                $strError = $this->_tr->translate('Incorrectly selected account');
            }

            // Validate new name
            if (empty($strError) && (empty($new_name) || preg_match('/[\^#%@\/"\'\\\]/', $new_name) || strlen($new_name) > 255)) {
                $strError = $this->_tr->translate('Incorrect folder name (forbidden symbols: ^ # % @ / \ " \')');
            }

            if (empty($strError)) {
                //if $parent_folder_id==0 => create folder in root, so no need to check if parent folder corresponds to current user
                if ($parent_folder_id != 0) {
                    $parentFolderModel = new Folder($parent_folder_id);
                    // now check if this folder corresponds to current user, otherwise - restrict access
                    if (!$parentFolderModel->isBelongingTo($accountId)) {
                        $strError = $this->_tr->translate('Incorrectly selected folder.');
                    }
                }
            }

            if (empty($strError)) {
                $this->_mailer->connect($accountId);
                $folderId = Folder::createFolder($accountId, $parent_folder_id, $new_name, $level, '', true, $this->_mailer->_storage, true);
                if (empty($folderId)) {
                    $strError = $this->_tr->translate('Folder was not created successfully.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'mail');
        }

        return $view->setVariables(array('success' => empty ($strError), 'msg' => $strError));
    }

    public function sendAction()
    {
        $strError = '';

        try {
            $filter            = new StripTags();
            $sendSave          = $this->params()->fromPost('send_save') == '1';
            $clientId          = $this->params()->fromPost('save_to_client');
            $panelType         = $filter->filter($this->params()->fromPost('panel_type'));
            $booSaveToProspect = (bool)$this->params()->fromPost('save_to_prospect');

            $form = array(
                'mail-create-template' => $filter->filter(Json::decode($this->params()->fromPost('mail-create-template'), Json::TYPE_ARRAY)),
                'from'                 => (int)$this->params()->fromPost('account_id'),
                'email'                => trim(Json::decode(trim($this->params()->fromPost('email', '')), Json::TYPE_ARRAY)),
                'cc'                   => trim(Json::decode(trim($this->params()->fromPost('cc', '')), Json::TYPE_ARRAY)),
                'bcc'                  => trim(Json::decode(trim($this->params()->fromPost('bcc', '')), Json::TYPE_ARRAY)),
                'subject'              => $filter->filter(Json::decode($this->params()->fromPost('subject'), Json::TYPE_ARRAY)),
                'message'              => $this->_settings->getHTMLPurifier(false)->purify(Json::decode($this->params()->fromPost('message'), Json::TYPE_ARRAY)),
                'forwarded'            => $filter->filter($this->params()->fromPost('forwarded')),
                'replied'              => $filter->filter($this->params()->fromPost('replied')),
                'attached'             => Json::decode($this->params()->fromPost('attached'), Json::TYPE_ARRAY),
                'attachments_array'    => Settings::filterParamsArray(Json::decode($this->params()->fromPost('attachments_array'), Json::TYPE_ARRAY), $filter),
                'draft_id'             => (int)$this->params()->fromPost('draft_id'),
                'original_mail_id'     => (int)$this->params()->fromPost('original_mail_id'),
                'save_to_prospect'     => $booSaveToProspect,
            );

            if ($panelType == 'marketplace') {
                $form['message'] = sprintf(
                        '<table>
                      <tr>
                        <td valign="top" style="vertical-align:top;"><img src="%s" width="160" height="49" alt="Immigrationsquare Logo" /></td>
                        <td align="left" valign="middle">You are receiving the following correspondence because of your submission on <a style="color: blue" href="https://immigrationsquare.com/">ImmigrationSquare.com</a></td>
                      </tr>
                    </table><br><br>',
                        'https://www.immigrationsquare.com/assets/base/img/layout/logos/logo2a-transparent-black.png'
                    ) . $form['message'];
            }

            // Check incoming params
            if (empty($form['email'])) {
                $strError = $this->_tr->translate("Please enter the recipient's email");
            }

            if (empty($strError)) {
                $form['email'] = implode(',', \Officio\Comms\Service\Mailer::parseEmails($form['email'], true));

                if (!Settings::_isCorrectMail($form['email'])) {
                    $strError = $this->_tr->translate('Incorrect "TO" Email Address');
                }
            }

            if (empty($strError) && !empty($form['cc'])) {
                $form ['cc'] = implode(',', \Officio\Comms\Service\Mailer::parseEmails($form['cc'], true));
                if (!Settings::_isCorrectMail($form['cc'])) {
                    $strError = $this->_tr->translate('Incorrect "CC" Email Address');
                }
            }

            if (empty($strError) && !empty($form['bcc'])) {
                $form ['bcc'] = implode(',', \Officio\Comms\Service\Mailer::parseEmails($form['bcc'], true));
                if (!Settings::_isCorrectMail($form['bcc'])) {
                    $strError = $this->_tr->translate('Incorrect "BCC" Email Address');
                }
            }

            // Check if current user can access this account
            if (empty($strError) && !empty($form['from']) && !MailAccount::hasAccessToAccount($this->_auth->getCurrentUserId(), $form['from'])) {
                $strError = $this->_tr->translate('Incorrectly selected account');
            }

            $booHasAccess = $booSaveToProspect ? $this->_companyProspects->allowAccessToProspect($clientId) : $this->_members->hasCurrentMemberAccessToMember($clientId);
            if (empty($strError) && $sendSave && !$booHasAccess) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $booShowSenderName = true;

            // If account wasn't selected, or it was disabled -
            // We try use 'from' field from template
            // and use default email account
            $arrTemplateInfo = [];
            if (empty($strError) && empty($form['from']) && is_numeric($form['mail-create-template'])) {
                // Load 'From' field from template
                if ($booSaveToProspect) {
                    // Load prospect's template
                    $arrTemplateInfo = $this->_companyProspects->getCompanyQnr()->getTemplate($form['mail-create-template']);
                } else {
                    // Load client's template
                    $arrTemplateInfo = $this->_templates->getTemplate($form['mail-create-template']);
                }

                if (is_array($arrTemplateInfo) && array_key_exists('from', $arrTemplateInfo)) {
                    $form['from_email'] = $arrTemplateInfo['from'];
                    $booShowSenderName = false;
                    // Load default account id
                    $arrMailAccount = MailAccount::getDefaultAccount($this->_auth->getCurrentUserId());
                    if (is_array($arrMailAccount) && array_key_exists('id', $arrMailAccount)) {
                        $form['from'] = $arrMailAccount['id'];
                    } else {
                        // Because default account wasn't found -
                        // try to load all accounts and use the first one
                        $arrAllMailAccountsIds = MailAccount::getAccounts($this->_auth->getCurrentUserId(), ['id']);
                        if (is_array($arrAllMailAccountsIds) && count($arrAllMailAccountsIds)) {
                            $form['from'] = $arrAllMailAccountsIds[0];
                        }
                    }
                }
            }

            // Send email
            $email = '';
            if (empty($strError)) {
                foreach ($form['attachments_array'] as $key => $arrAttachment) {
                    if (isset($arrAttachment['tmp_name'])) {
                        $form['attachments_array'][$key]['tmp_name'] = $this->_encryption->decode($arrAttachment['tmp_name']);
                    }
                }

                $senderInfo = $this->_clients->getMemberInfo();
                list($res, $email) = $this->_mailer->send($form, $form['attachments_array'], $senderInfo, true, true, $booShowSenderName);

                if ($res !== true) {
                    $strError = $res;
                } elseif (is_numeric($clientId) && !empty($clientId) && !$booSaveToProspect) {
                    $this->_notes->updateNote(
                        0,
                        $clientId,
                        sprintf('Sent email "%s" subject "%s"', isset($arrTemplateInfo['name']) ? $arrTemplateInfo['name'] : 'no template', $form['subject']),
                        true
                    );
                }
            }

            // Save email if needed
            if (empty($strError) && $sendSave) {
                $arrMemberInfo = $booSaveToProspect
                    ? $this->_companyProspects->getProspectInfo($clientId, null)
                    : $this->_clients->getMemberInfo($clientId);

                $companyId = $arrMemberInfo['company_id'];
                if ($booSaveToProspect && empty($companyId)) {
                    $companyId = $this->_auth->getCurrentUserCompanyId();
                }

                $booLocal     = $this->_company->isCompanyStorageLocationLocal($companyId);
                $clientFolder = $booSaveToProspect
                    ? $this->_companyProspects->getPathToProspect($clientId, $companyId, $booLocal)
                    : $this->_files->getClientCorrespondenceFTPFolder($companyId, $clientId, $booLocal);

                $this->_mailer->saveRawEmailToClient(
                    $email,
                    $form['subject'],
                    $form['original_mail_id'],
                    $companyId,
                    $clientId,
                    $this->_members->getMemberInfo(),
                    (int)$this->params()->fromPost('account_id'),
                    $clientFolder,
                    $booLocal,
                    $this->params()->fromPost('save_this_mail') == 'true',
                    $this->params()->fromPost('save_original_mail') == 'true',
                    $this->params()->fromPost('remove_original_mail') == 'true',
                    false,
                    '',
                    $booSaveToProspect
                );
            }

            if (empty($strError) && is_numeric($clientId) && !empty($clientId) && $booHasAccess && $booSaveToProspect) {
                $this->_companyProspects->updateProspectSettings(
                    $this->_auth->getCurrentUserCompanyId(),
                    $clientId,
                    array('email_sent' => 'Y')
                );

                if ($panelType == 'marketplace') {
                    $prospectId = $this->params()->fromPost('member_id_for_activity');
                    if ($this->_companyProspects->allowAccessToProspect($prospectId)) {
                        $this->_companyProspects->addProspectActivity(
                            $this->_auth->getCurrentUserCompanyId(),
                            $prospectId,
                            $this->_auth->getCurrentUserId(),
                            'email'
                        );
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');

            if (!in_array($e->getMessage(), array("Can't connect to email account.", 'cannot read - connection closed?')) && mb_stripos($e->getMessage(), 'Could not read from') === false) {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }
        }

        // Return json result
        return new JsonModel(array('success' => empty ($strError), 'msg' => $strError));
    }

    public function saveAction()
    {
        $view = new JsonModel();

        $strError = '';

        try {
            $filter = new StripTags();
            $emailIds = Json::decode($this->findParam('email_ids'), Json::TYPE_ARRAY);
            $removeOriginalMail = Json::decode($this->findParam('remove_original_mail'), Json::TYPE_ARRAY);
            $booSaveAttachSeparately = Json::decode($this->findParam('save_attach_separately'), Json::TYPE_ARRAY);
            $accountId = (int)Json::decode($this->findParam('account_id'), Json::TYPE_ARRAY);
            $memberId = (int)Json::decode($this->findParam('save_to_client'), Json::TYPE_ARRAY);
            $saveTo = $filter->filter($this->findParam('save_to_type', 'client'));

            // Check if current user can access this account
            if (empty($strError) && !MailAccount::hasAccessToAccount($this->_auth->getCurrentUserId(), $accountId)) {
                $strError = $this->_tr->translate('Incorrectly selected account.');
            }

            if (empty($strError) && !in_array($saveTo, array('client', 'prospect'))) {
                $strError = $this->_tr->translate('Incorrectly selected saving type.');
            }

            // Check if user has access to the client
            if (empty($strError) && $saveTo == 'client' && !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Incorrectly selected case.');
            }

            if (empty($strError) && $saveTo == 'prospect') {
                if (!$this->_companyProspects->allowAccessToProspect($memberId)) {
                    $strError = $this->_tr->translate('Incorrectly selected prospect.');
                }
            }

            // Check if user has access to emails
            if (empty($strError) && !$this->_mailer->hasAccessToMail($this->_auth->getCurrentUserId(), $emailIds)) {
                $strError = $this->_tr->translate('Incorrectly selected email(s).');
            }

            if (empty($strError)) {
                $booSaveToProspect = $saveTo == 'prospect';
                $arrMemberInfo = $booSaveToProspect
                    ? $this->_companyProspects->getProspectInfo($memberId, null)
                    : $this->_clients->getMemberInfo($memberId);
                $companyId = $arrMemberInfo['company_id'];
                if ($booSaveToProspect && empty($companyId)) {
                    $companyId = $this->_auth->getCurrentUserCompanyId();
                }
                $booLocal = $this->_company->isCompanyStorageLocationLocal($companyId);
                $clientFolder = $booSaveToProspect
                    ? $this->_companyProspects->getPathToProspect($memberId, $companyId, $booLocal)
                    : $this->_files->getClientCorrespondenceFTPFolder($companyId, $memberId, $booLocal);
                foreach ($emailIds as $emailId) {
                    $this->_mailer->saveRawEmailToClient(
                        null,
                        null,
                        $emailId,
                        $companyId,
                        $memberId,
                        $this->_members->getMemberInfo(),
                        $accountId,
                        $clientFolder,
                        $booLocal,
                        false,
                        true,
                        $removeOriginalMail,
                        $booSaveAttachSeparately,
                        '',
                        $booSaveToProspect
                    );
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $view->setVariables(array("success" => empty ($strError), 'msg' => $strError));
    }

    public function saveDraftAction()
    {
        $strError     = '';
        $newMessageId = 0;
        $att          = array();

        try {
            $accountId = (int)$this->params()->fromPost('account_id');

            // Check if current user can access this account
            if (empty($strError) && !MailAccount::hasAccessToAccount($this->_auth->getCurrentUserId(), $accountId)) {
                $strError = $this->_tr->translate('Incorrectly selected account');
            }

            if (empty($strError)) {
                $draftId = (int)$this->params()->fromPost('draft_id');
                $filter  = new StripTags();

                $form = array(
                    'mail-create-template' => $filter->filter(Json::decode($this->params()->fromPost('mail-create-template'), Json::TYPE_ARRAY)),
                    'from'                 => $accountId,
                    'email'                => trim(Json::decode(trim($this->params()->fromPost('email', '')), Json::TYPE_ARRAY)),
                    'cc'                   => $filter->filter(trim(Json::decode(trim($this->params()->fromPost('cc', '')), Json::TYPE_ARRAY))),
                    'bcc'                  => $filter->filter(trim(Json::decode(trim($this->params()->fromPost('bcc', '')), Json::TYPE_ARRAY))),
                    'subject'              => $filter->filter(Json::decode($this->params()->fromPost('subject'), Json::TYPE_ARRAY)),
                    'message'              => Json::decode($this->params()->fromPost('message'), Json::TYPE_ARRAY),
                    'forwarded'            => $filter->filter($this->params()->fromPost('forwarded')),
                    'replied'              => $filter->filter($this->params()->fromPost('replied')),
                    'attached'             => Json::decode($this->params()->fromPost('attached'), Json::TYPE_ARRAY),
                    'attachments_array'    => Settings::filterParamsArray(Json::decode($this->params()->fromPost('attachments_array'), Json::TYPE_ARRAY), $filter),
                    'draft_id'             => $draftId
                );

                foreach ($form['attachments_array'] as $key => $arrAttachment) {
                    if (isset($arrAttachment['tmp_name'])) {
                        $form['attachments_array'][$key]['tmp_name'] = $this->_encryption->decode($arrAttachment['tmp_name']);
                    }
                }

                foreach ($form['attached'] as $prev_att) {
                    if (isset($prev_att['link'])) {
                        // came not from the Mail tab: from MyDocs or other places like this
                        if (empty($prev_att['id']) && !empty($prev_att['path'])) {
                            $path = $this->_encryption->decode($prev_att['path']);
                        } else {
                            $path = $this->_encryption->decode($prev_att['id']);
                        }
                    } else {
                        // came from the Mail tab
                        $path = str_replace(getcwd(), '', $prev_att['path']);
                    }

                    $form['attachments_array'][] = array(
                        'tmp_name' => $path,
                        'name'     => $prev_att['original_file_name'],
                    );
                }

                if (!empty($form['from'])) {
                    $fromMailAccount = new MailAccount($form['from']);
                    $account         = $fromMailAccount->getAccountDetails(); # $form['from'] = contains account id
                    $form['from']    = trim($account['email'] ?? '');
                }

                $rand                    = rand();
                $form['uid']             = $this->_mailer->getUniquesEmailPrefix() . md5(uniqid((string)$rand, true));
                $form['id_account']      = $accountId;
                $form['has_attachments'] = intval(count($form['attachments_array']) > 0);

                // create new draft
                $newMessageId = $this->_mailer->saveJustCreatedMessageToFolder($form, $form['attachments_array'], Folder::DRAFTS);
                if (!empty($newMessageId)) {
                    $booLocal          = $this->_company->isCompanyStorageLocationLocal($this->_auth->getCurrentUserCompanyId());
                    $companyEmailsPath = $this->_files->getCompanyEmailAttachmentsPath($this->_auth->getCurrentUserCompanyId(), $booLocal);
                    $att               = $this->_mailer->getMailAttachments($newMessageId, $booLocal, $companyEmailsPath);
                    // Delete previously created
                    if ($draftId > 0) {
                        $this->_mailer->delete($draftId, $accountId, true);
                    }
                } else {
                    $strError = $this->_tr->translate('Draft was not saved successfully.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'     => empty($strError),
            'msg'         => $strError,
            'draft_id'    => $newMessageId,
            'attachments' => Json::encode($att)
        );
        return new JsonModel($arrResult);
    }

    public function moveFolderAction()
    {
        $strError = '';

        try {
            // Get and check incoming params
            $filter         = new StripTags();
            $accountId      = (int)Json::decode($this->params()->fromPost('account_id'), Json::TYPE_ARRAY);
            $parentFolderId = (int)Json::decode($this->params()->fromPost('parent_folder_id'), Json::TYPE_ARRAY);
            $folderId       = Json::decode($this->params()->fromPost('folder_id'), Json::TYPE_ARRAY);
            $order          = (int)$filter->filter(Json::decode($this->params()->fromPost('order'), Json::TYPE_ARRAY));

            // Only ajax request is allowed
            if (!$this->getRequest()->isXmlHttpRequest()) {
                $strError = $this->_tr->translate('Incorrectly selected folder.');
            }

            if (empty($strError) && (!is_numeric($parentFolderId) || !is_numeric($folderId) || !is_numeric($order))) {
                $strError = $this->_tr->translate('Incorrectly selected folder.');
            }

            // Check if current user can access this account
            if (empty($strError) && !MailAccount::hasAccessToAccount($this->_auth->getCurrentUserId(), $accountId)) {
                $strError = $this->_tr->translate('Incorrectly selected account');
            }

            $folderModel = new Folder($folderId);
            if (empty($strError)) {
                // now check if these folders correspond to current user, otherwise - restrict access
                if (!$folderModel->isBelongingTo($accountId)) {
                    $strError = $this->_tr->translate('Incorrectly selected folder.');
                }

                if (empty($strError) && !empty($parentFolderId)) {
                    $parentFolderModel = new Folder($parentFolderId);
                    if (!$parentFolderModel->isBelongingTo($accountId)) {
                        $strError = $this->_tr->translate('Incorrectly selected parent folder.');
                    }
                }
            }

            if (empty($strError)) {
                $this->_mailer->connect($accountId);
                $this->_mailer->moveFolder($folderId, $parentFolderId, $order);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('IMAP server error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel(array("success" => empty($strError), 'msg' => $strError));
    }

    /**
     * @param $strQuery
     * @param $start
     * @param $limit
     * @param $accountId
     * @param $accessToModules
     * @return array
     */
    private function _getToMails($strQuery, $start, $limit, $accountId, $accessToModules)
    {
        $totalRecords = 0;
        $arrMails = $arrAgents = $arrProspects = $arrClients = $arrMailsFrom = $arrMailsTo = $arrUsers = array();

        try {
            $strQuery_array = preg_split('/,|;/', $strQuery);
            $strQuery = trim(end($strQuery_array));

            $booCorrectQuery = !empty($strQuery);

            // Contacts:
            if ($booCorrectQuery && in_array('contacts', $accessToModules)) {
                $allAgents = $this->_clients->getAgents();

                foreach ($allAgents as $a) {
                    $email1 = $a['email1'] ?? '';
                    $email2 = $a['email2'] ?? '';
                    $email3 = $a['email3'] ?? '';

                    // If any email matches
                    $booAddEmail1 = (!empty($email1) && stristr($email1, $strQuery) !== false);
                    $booAddEmail2 = (!empty($email2) && stristr($email2, $strQuery) !== false);
                    $booAddEmail3 = (!empty($email3) && stristr($email3, $strQuery) !== false);

                    // Or if first/last name matches
                    if ((isset($a['fName']) && stristr($a['fName'], $strQuery) !== false) ||
                        (isset($a['lName']) && stristr($a['lName'], $strQuery) !== false)) {
                        $booAddEmail1 = !empty($email1);
                        $booAddEmail2 = !empty($email2);
                        $booAddEmail3 = !empty($email3);
                    }

                    $name = $a['fName'] . ' ' . $a['lName'];

                    if ($booAddEmail1) {
                        $arrAgents[] = array('to' => $email1, 'name' => $name, 'type' => 'agents');
                    }

                    if ($booAddEmail2) {
                        $arrAgents[] = array('to' => $email2, 'name' => $name, 'type' => 'agents');
                    }

                    if ($booAddEmail3) {
                        $arrAgents[] = array('to' => $email3, 'name' => $name, 'type' => 'agents');
                    }
                }
            }

            // Prospects: http://goo.gl/4V2ou
            if ($booCorrectQuery && in_array('prospects', $accessToModules)) {
                $prospects = $this->_companyProspects->getProspectsList('prospects', 0, 0, 'all-prospects', '');
                foreach ($prospects['rows'] as $a) {
                    if (!empty($a['email'])) {
                        $prospectName = $this->_companyProspects->generateProspectName($a);

                        if (stristr($a['email'], $strQuery) !== false || stristr($prospectName, $strQuery) !== false) {
                            $arrProspects[] = array(
                                'to'   => $a['email'],
                                'name' => $prospectName,
                                'type' => 'prospects'
                            );
                        }
                    }
                }
            }

            // Clients: http://goo.gl/3vkfJ
            if ($booCorrectQuery && in_array('clients', $accessToModules)) {
                $clients = $this->_clients->getClientsList();
                $clients = $this->_clients->getCasesListWithParents($clients);

                foreach ($clients as $arrClientInfo) {
                    if (!empty($arrClientInfo['emailAddresses']) && (stristr($arrClientInfo['emailAddresses'], $strQuery) !== false || stristr($arrClientInfo['clientName'], $strQuery) !== false)) {
                        $arrClients[] = array(
                            'to'   => $arrClientInfo['emailAddresses'],
                            'name' => $arrClientInfo['clientName'],
                            'type' => 'clients'
                        );
                    }
                }
            }

            // mails
            if ($booCorrectQuery && in_array('mail', $accessToModules)) {
                // from
                $select = (new Select())
                    ->from('eml_messages')
                    ->columns(['to' => new Expression('DISTINCT(`from`)'), 'type' => new Expression("'mails'")])
                    ->where(
                        [
                            'id_account' => (int)$accountId,
                            (new Where())->like('from', '%' . $strQuery . '%')
                        ]
                    )
                    ->limit($limit)
                    ->offset($start);

                $arrMailsFrom = $this->_db2->fetchAll($select);

                // to
                $select = (new Select())
                    ->from('eml_messages')
                    ->columns(['to' => new Expression('DISTINCT(`to`)'), 'type' => new Expression("'mails'")])
                    ->where(
                        [
                            'id_account' => (int)$accountId,
                            (new Where())->like('to', '%' . $strQuery . '%')
                        ]
                    )
                    ->limit($limit)
                    ->offset($start);

                $arrMailsTo = $this->_db2->fetchAll($select);
            }

            // users
            if ($booCorrectQuery) {
                $arrAllowedMembers = $this->_clients->getMembersWhichICanAccess($this->_members::getMemberType('admin_and_user'));
                $arrAllowedMembers = is_array($arrAllowedMembers) && count($arrAllowedMembers) ? $arrAllowedMembers : array(0);

                $select = (new Select())
                    ->from(['m' => 'members'])
                    ->columns(['emailAddress', 'fName', 'lName'])
                    ->join(array('u' => 'users'), 'u.member_id = m.member_id', [])
                    ->where([
                        'm.status'     => 1,
                        'm.company_id' => $this->_auth->getCurrentUserCompanyId(),
                        'm.member_id'  => $arrAllowedMembers,
                    ]);

                $arrCompanyUsers = $this->_db2->fetchAll($select);

                foreach ($arrCompanyUsers as $arrCompanyUserInfo) {
                    $arrCompanyUserInfo = $this->_clients::generateMemberName($arrCompanyUserInfo);

                    if (!empty($arrCompanyUserInfo['emailAddress']) && (stristr($arrCompanyUserInfo['emailAddress'], $strQuery) !== false || stristr($arrCompanyUserInfo['full_name'], $strQuery) !== false)) {
                        $arrUsers[] = array(
                            'to'   => $arrCompanyUserInfo['emailAddress'],
                            'name' => $arrCompanyUserInfo['full_name'],
                            'type' => 'users'
                        );
                    }
                }
            }

            $arrMails = array_merge($arrMailsFrom, $arrMailsTo, $arrAgents, $arrClients, $arrProspects, $arrUsers);

            // now we need to get name for mails type (and also split by ',')
            // so, we need to make next transformation: "Vasil" <va@sya.com>, "Masha" <ma@sha.com> => array of 2 elements with keys 'name' ("Vasya", "Masha") and 'to' ("va@sya.com", "ma@sha.com")
            // 5 possible formats: ma@sha.com, <ma@sha.com>, "Masha" <ma@sha.com>, 'Masha' <ma@sha.com>, Masha <ma@sha.com>
            $arrMails2 = array();
            foreach ($arrMails as $m) {
                if ($m['type'] != 'mails') {
                    $arrMails2[] = $m;
                    continue;
                }

                $matches = explode(',', $m['to'] ?? '');

                foreach ($matches as $match) {
                    $match = trim($match);

                    $search = empty($strQuery) ? $match : strstr($match, $strQuery);
                    if ($search !== false) // sorry, mate, but we need to filter 1 more time :*(
                    {
                        if (strstr($match, '<') === false) // ma@sha.com
                        {
                            $name = '';
                            $mail = $match;
                        } else {
                            $exploded_match = preg_split("/[<>]/", $match);
                            if (!is_array($exploded_match)) {
                                throw new Exception('Incorrect matches.');
                            }

                            if (count($exploded_match) == 1) // <ma@sha.com>
                            {
                                $name = '';
                                $mail = $exploded_match[0];
                            } else {
                                $name = trim($exploded_match[0] ?? '');
                                $mail = $exploded_match[1];

                                if (substr($name, 0, 1) == "'" || substr($name, 0, 1) == "\"") // "Masha" <ma@sha.com>, 'Masha' <ma@sha.com>
                                {
                                    $name = trim($name, substr($name, 0, 1));
                                }
                            }
                        }

                        $arrMails2[] = array(
                            'to'   => $mail,
                            'name' => $name,
                            'type' => $m['type']
                        );
                    }
                }
            }

            $validator = new EmailAddress();
            $arrMails  = $tmp = array();
            foreach ($arrMails2 as $m) {
                if (!in_array($m['to'], $tmp) && $validator->isValid($m['to'])) {
                    $tmp[]      = $m['to'];
                    $arrMails[] = $m;
                }
            }

            $totalRecords = count($arrMails);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array($arrMails, $totalRecords, $strQuery);
    }

    /**
     * Search for email addresses
     *
     * output string in specific format (not simple json)
     */
    public function getToMailsAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        try {
            ini_set('memory_limit', '-1');

            // Get incoming params
            $filter      = new StripTags();
            $strQuery    = $filter->filter($this->params()->fromPost('query'));
            $strCallback = $filter->filter($this->params()->fromPost('callback'));
            $start       = (int)$this->params()->fromPost('start', 0);
            $limit       = (int)$this->params()->fromPost('limit', 10);
            $accountId   = (int)$this->params()->fromPost('account_id');

            // if account id is not provided, set it to default user account id
            if (empty($accountId)) {
                $mailAccount = MailAccount::getDefaultAccount($this->_auth->getCurrentUserId());
                $accountId   = $mailAccount['id'];
            }

            $booIsSuperAdmin = $this->_auth->isCurrentUserSuperadmin();

            $arrAccessToModules = array();

            if (!$booIsSuperAdmin && $this->_acl->isAllowed('contacts-view')) {
                $arrAccessToModules[] = 'contacts';
            }

            if (!$booIsSuperAdmin && $this->_acl->isAllowed('clients-view')) {
                $arrAccessToModules[] = 'clients';
            }

            if (!$booIsSuperAdmin && $this->_acl->isAllowed('prospects-view')) {
                $arrAccessToModules[] = 'prospects';
            }

            if (!empty($accountId) && $this->_acl->isAllowed('mail-view')) {
                $arrAccessToModules[] = 'mail';
            }

            list($arrRows, $totalCount, $query) = $this->_getToMails($strQuery, $start, $limit, $accountId, $arrAccessToModules);
        } catch (Exception $e) {
            $totalCount  = 0;
            $arrRows     = array();
            $query       = '';
            $strCallback = '';

            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return result in specific format (not simple Json!)
        $view->setVariable('content', $strCallback . '(' . Json::encode(array('totalCount' => $totalCount, 'rows' => $arrRows, 'query' => $query)) . ')');

        return $view;
    }

    /**
     * @param int $memberId
     * @param int $templateId
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function _getProspectProcessedTemplate($memberId, $templateId)
    {
        // Get template
        $templateInfo = $this->_companyProspects->getCompanyQnr()->getTemplate($templateId);

        $message = $to = $from = $cc = $bcc = $subject = '';
        if (is_array($templateInfo) && count($templateInfo)) {
            $replacements = $this->_systemTemplates->getGlobalTemplateReplacements();
            $replacements += $this->_companyProspects->getTemplateReplacements((int)$memberId);
            list($message, $to, $subject) = $this->_systemTemplates->processText(
                [
                    $templateInfo['message'],
                    $templateInfo['to'],
                    $templateInfo['subject']
                ],
                $replacements
            );

            $from = $templateInfo['from'];
        }

        if (empty($to)) {
            $prospectInfo = $this->_companyProspects->getProspectInfo($memberId, null);
            $to = $prospectInfo['email'];
        }

        return [
            'message' => $message,
            'from' => $from,
            'email' => $to,
            'cc' => $cc,
            'bcc' => $bcc,
            'subject' => $subject
        ];
    }

    public function sendMailerAction()
    {
        $view = new JsonModel();

        try {
            set_time_limit(60 * 60); // 1 hour
            ini_set('memory_limit', '512M');

            $strError = '';
            $textStatus = '';

            // Get and check incoming info
            $accountId = (int)Json::decode($this->findParam('accountId'), Json::TYPE_ARRAY);
            $booProspects = Json::decode($this->findParam('booProspects'), Json::TYPE_ARRAY);
            $arrMemberIds = Json::decode($this->findParam('arrMemberIds'), Json::TYPE_ARRAY);
            $templateId = (int)Json::decode($this->findParam('templateId'), Json::TYPE_ARRAY);
            $processedCount = (int)Json::decode($this->findParam('processedCount'), Json::TYPE_ARRAY);
            $totalCount = (int)Json::decode($this->findParam('totalCount'), Json::TYPE_ARRAY);

            if (empty($strError) && !empty($accountId) && !MailAccount::hasAccessToAccount($this->_auth->getCurrentUserId(), $accountId)) {
                $strError = $this->_tr->translate('Incorrectly selected account');
            }

            if (empty($strError) && (!is_array($arrMemberIds) || !count($arrMemberIds))) {
                $strError = $this->_tr->translate('Clients were selected incorrectly.');
            }

            if (empty($strError) && (!is_numeric($processedCount) || $processedCount < 0)) {
                $strError = $this->_tr->translate('Incoming data [processed count] is incorrect.');
            }

            if (empty($strError) && (!is_numeric($totalCount) || empty($totalCount))) {
                $strError = $this->_tr->translate('Incoming data [total count] is incorrect.');
            }

            if (empty($strError) && (!is_numeric($templateId) || empty($templateId))) {
                $strError = $this->_tr->translate('Template was selected incorrectly.');
            }

            if (empty($strError)) {
                $memberId = array_shift($arrMemberIds);

                // Check access for member
                $booHasAccess = $booProspects ? $this->_companyProspects->allowAccessToProspect($memberId) : $this->_members->hasCurrentMemberAccessToMember($memberId);
                if (!$booHasAccess) {
                    $strColor = '#9E0F0F';
                    $receiverName = sprintf($this->_tr->translate('Client (id #%d)'), $memberId);
                    $strStatus = $this->_tr->translate('Skipped (no access rights)');
                } else {
                    // Load template, parse it
                    $template_data = $booProspects
                        ? $this->_getProspectProcessedTemplate($memberId, $templateId)
                        : $this->_templates->getMessage($templateId, $memberId);

                    $attachments = array();

                    if ($booProspects) {
                        $receiver_info = $this->_companyProspects->getProspectInfo($memberId);
                    } else {
                        $receiver_info = $this->_members->getMemberInfo($memberId);
                        $memberType = $this->_clients->getMemberTypeNameById($receiver_info['userType']);
                        if ($memberType == 'case') {
                            $parent         = $this->_clients->getParentsForAssignedApplicants(array($memberId));
                            $parentMemberId = $parent[$memberId]['parent_member_id'];
                            $receiver_info  = $this->_members->getMemberInfo($parentMemberId);
                        }
                        $attachments = $this->_templates->parseTemplateAttachments((int)$templateId, (int)$memberId);
                    }
                    $receiverName = $booProspects ? $receiver_info['fName'] . ' ' . $receiver_info['lName'] : $receiver_info['full_name'];

                    $additional_emails = $booProspects ? array($receiver_info['email']) : array_filter($this->_clients->getFields()->getEmailFields($memberId), 'trim');

                    $to  = $template_data['email'] ?: array_shift($additional_emails);
                    $cc  = $template_data['cc'] ?: '';
                    $bcc = $template_data['bcc'] ?: '';

                    if (!$to) {
                        $strColor  = '#9E0F0F';
                        $strStatus = $this->_tr->translate('Skipped (no email address is set)');
                    } else {
                        $form = array(
                            'mail-create-template' => $templateId,
                            'from'                 => $accountId,
                            'email'                => $to,
                            'cc'                   => $cc,
                            'bcc' => $bcc,
                            'subject' => $template_data['subject'],
                            'message' => $template_data['message'],
                            'replied' => 0,
                            'forwarded' => 0,
                            'draft_id' => 0,
                            'attached' => $attachments
                        );

                        if (!empty($template_data['from'])) {
                            $form['from_email'] = $template_data['from'];
                            $booShowSenderName = false;
                        } else {
                            $booShowSenderName = true;
                        }

                        // Send email
                        $senderInfo = $this->_clients->getMemberInfo();
                        list($res, $email) = $this->_mailer->send($form, array(), $senderInfo, false, true, $booShowSenderName);

                        if ($res === true) {
                            $arrMemberInfo = $booProspects
                                ? $this->_companyProspects->getProspectInfo($memberId, null)
                                : $this->_clients->getMemberInfo($memberId);
                            $companyId = $arrMemberInfo['company_id'];
                            if ($booProspects && empty($companyId)) {
                                $companyId = $this->_auth->getCurrentUserCompanyId();
                            }
                            $booLocal = $this->_company->isCompanyStorageLocationLocal($companyId);
                            $clientFolder = $booProspects
                                ? $this->_companyProspects->getPathToProspect($memberId, $companyId, $booLocal)
                                : $this->_files->getClientCorrespondenceFTPFolder($companyId, $memberId, $booLocal);
                            // Save email to client's/prospect's docs folder
                            $this->_mailer->saveRawEmailToClient($email, $form['subject'], 0, $companyId, $memberId, $this->_members->getMemberInfo(), $accountId, $clientFolder, $booLocal, true, false, false, false, '', $booProspects);

                            // save status (email sent)
                            if ($booProspects) {
                                $this->_companyProspects->updateProspectSettings(
                                    $this->_auth->getCurrentUserCompanyId(),
                                    $memberId,
                                    array('email_sent' => 'Y')
                                );
                            }

                            $strColor = '#006600';
                            $strStatus = $this->_tr->translate('Sent to ' . $to);
                        } else {
                            $strColor = 'red';
                            $strStatus = $this->_tr->translate('Error: ') . $res;
                        }
                    }
                }
                $processedCount++;

                $textStatus = sprintf('<div style="color: %s">%s - %s</div>', $strColor, $receiverName, $strStatus);
                if (!empty($accountId)) {
                    $mailAccount = new MailAccount($accountId);
                    $mailAccount->updateLastMassMail();
                }
            }
        } catch (Exception $e) {
            $strError = $textStatus = $this->_tr->translate('Internal Error');
            $arrMemberIds = array();
            $processedCount = $totalCount = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        // Return json result
        $arrResult = array(
            'success' => empty($strError),
            'msg' => $strError,
            'arrMemberIds' => $arrMemberIds,
            'processedCount' => $processedCount,
            'totalCount' => $totalCount,
            'textStatus' => $textStatus

        );
        return $view->setVariables($arrResult);
    }

    public function sendMailerToCompaniesAction()
    {
        $view = new JsonModel();

        $strError = '';
        $arrCompaniesIds = array();
        $arrUnsuccessful = array();

        try {
            $arrIds = $this->findParam('selected_users_ids');
            if (empty($arrIds)) {
                $strError = $this->_tr->translate('Incorrectly selected companies');
            } else {
                $arrCompaniesIds = array_filter(explode(',', $arrIds), 'trim');
            }

            $templateId = $this->findParam('template_id');
            if (empty($strError) && (empty($templateId) || !is_numeric($templateId))) {
                $strError = $this->_tr->translate('Incorrectly selected template');
            }


            if (empty($strError)) {
                error_reporting(E_ERROR);
                foreach ($arrCompaniesIds as $companyId) {
                    $template_data = $this->_templates->getMessage($templateId, 0, '', false, $companyId);

                    $receiver_info = $this->_company->getCompanyInfo($companyId);

                    $to = isset($receiver_info['companyEmail']) && !empty($receiver_info['companyEmail']) ? $receiver_info['companyEmail'] : '';
                    if (!$to) {
                        $arrUnsuccessful[] = $receiver_info['companyName'];
                        continue;
                    }

                    $form = array(
                        'mail-create-template' => $templateId,
                        'from' => 0, // Default email account
                        'email' => $to,
                        'cc' => '',
                        'bcc' => '',
                        'subject' => $template_data['subject'],
                        'message' => $template_data['message'],
                        'replied' => 0,
                        'forwarded' => 0,
                        'draft_id' => 0,
                    );

                    $senderInfo = $this->_clients->getMemberInfo();
                    list($res,) = $this->_mailer->send($form, $_FILES, $senderInfo, false);

                    // Delete temp files
                    foreach ($_FILES as $file) {
                        if (file_exists($file ['tmp_name'])) {
                            @unlink($file ['tmp_name']);
                        }
                    }

                    if ($res !== true) {
                        $arrUnsuccessful[] = $receiver_info['companyName'];
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal Error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        /*
         * We send such text result:
         * 1. Error
         * 2. "X out of Y email(s) were successfully sent. The following client(s) failed: ..."
         * 3. "X out of X mail(s) were successfully sent."
         */
        $msg = $strError;
        if (empty($msg)) {
            $allUsersCount = count($arrCompaniesIds);
            if (count($arrUnsuccessful) > 0) {
                $msg = sprintf(
                    '%d out of %d email(s) were successfully sent. The following company(s) failed: <br /><br /> %s',
                    $allUsersCount - count($arrUnsuccessful),
                    $allUsersCount,
                    implode('<br />', $arrUnsuccessful)
                );
            } else {
                $msg = sprintf(
                    '%d out of %d email(s) were successfully sent.',
                    $allUsersCount,
                    $allUsersCount
                );
            }
        }

        $arrResult = array(
            'success' => empty($strError),
            'msg' => $msg,
            'unsuccessfull_count' => count($arrUnsuccessful)
        );
        return $view->setVariables($arrResult);
    }


    public function updateMailFlagAction()
    {
        $view = new JsonModel();

        $strError = '';

        try {
            $emailId = (int)Json::decode($this->findParam('mail_id'), Json::TYPE_ARRAY);
            if (empty($strError) && !$this->_mailer->hasAccessToMail($this->_auth->getCurrentUserId(), $emailId)) {
                $strError = $this->_tr->translate('Incorrectly selected email');
            }

            $filter = new StripTags();
            $emailFlag = $filter->filter(Json::decode($this->findParam('mail_flag'), Json::TYPE_ARRAY));
            $arrFlags = $this->_mailer->getMailFlags();
            if (empty($strError) && !in_array($emailFlag, array_values($arrFlags))) {
                $strError = $this->_tr->translate('Incorrectly selected flag');
            }

            if (empty($strError)) {
                $this->_mailer->updateMailFlag($emailId, $this->_mailer->getMailFlagId($emailFlag));
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success' => empty($strError),
            'msg' => $strError
        );
        return $view->setVariables($arrResult);
    }

    public function calendlyAuthorizeAction()
    {
        if (!$this->_config['calendly']['enabled']) {
            $view = new ViewModel(['content' => $this->_tr->translate('Calendly disabled.')]);
            $view->setTerminal(true);
            $view->setTemplate('layout/plain');

            return $view;
        }

        $clientId    = $this->_config['calendly']['client_id'];
        $redirectUri = $this->_config['urlSettings']['baseUrl'] . '/mailer/index/calendly-oauth';
        $url         = "https://auth.calendly.com/oauth/authorize?client_id=$clientId&response_type=code&redirect_uri=$redirectUri";

        return $this->redirect()->toUrl($url);
    }

    public function calendlyOauthAction()
    {
        $strError = '';
        try {
            $filter = new StripTags();
            $code   = $filter->filter($this->params()->fromQuery('code'));

            if (!$this->_config['calendly']['enabled']) {
                $strError = $this->_tr->translate('Calendly disabled.');
            }

            if (empty($strError)) {
                // Get access token
                $client       = new Client();
                $clientId     = $this->_config['calendly']['client_id'];
                $clientSecret = $this->_config['calendly']['client_secret'];
                $redirectUri  = $this->_config['urlSettings']['baseUrl'] . '/mailer/index/calendly-oauth';

                $client->setMethod('post');
                $client->setUri('https://auth.calendly.com/oauth/token');
                $client->setHeaders([
                    'Content-Type' => 'application/json'
                ]);
                $client->setRawBody(json_encode([
                    'grant_type'    => 'authorization_code',
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'code'          => $code,
                    'redirect_uri'  => $redirectUri,
                ]));
                $response = $client->send();

                $jsonResponse = json_decode($response->getBody(), true);
                $accessToken  = $jsonResponse['access_token'];

                if (empty($accessToken)) {
                    throw new Exception("Calendly /oauth/token failed.");
                }

                $container               = new Container('calendly_container');
                $container->access_token = $accessToken;
                $container->created_at   = $jsonResponse['created_at'];
                $container->expires_in   = $jsonResponse['expires_in'];
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal Error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setVariable('strError', $strError);

        return $view;
    }

    public function calendlyLinksAction()
    {
        $strError         = '';
        $loginToCalendly  = false;
        $arrCalendlyLinks = [];

        try {
            $container = new Container('calendly_container');

            if (!$this->_config['calendly']['enabled']) {
                $strError = $this->_tr->translate('Calendly disabled.');
            }

            if (empty($strError) && empty($container->access_token)) {
                $strError        = $this->_tr->translate('Calendly access token not found.');
                $loginToCalendly = true;
            }

            if (empty($strError) && (time() - 5 >= ((int)$container->created_at + (int)$container->expires_in))) {
                // Expired, clear container
                $container->access_token = null;
                $container->created_at   = null;
                $container->expires_in   = null;

                $strError        = 'Calendly access token expired.';
                $loginToCalendly = true;
            }

            if (empty($strError)) {
                // 1) Get current user
                $client = new Client();
                $client->setMethod('get');
                $client->setUri('https://api.calendly.com/users/me');
                $client->setHeaders([
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $container->access_token
                ]);
                $response = $client->send();

                $response_json_body = json_decode($response->getBody(), true);
                $user_uri           = $response_json_body['resource']['uri'];

                if (empty($user_uri)) {
                    throw new Exception("Calendly /users/me failed.");
                }

                // 2) Get user's event types
                $client->setUri('https://api.calendly.com/event_types');
                $client->setParameterGet([
                    'user' => $user_uri
                ]);
                $response2 = $client->send();

                $response_json_body2 = json_decode($response2->getBody(), true);
                $collection          = $response_json_body2['collection'];

                if (!is_array($collection)) {
                    throw new Exception("Calendly /event_types failed.");
                }

                foreach ($collection as $item) {
                    if ($item['active']) {
                        $arrCalendlyLinks[] = [
                            'name'           => $item['name'],
                            'scheduling_url' => $item['scheduling_url']
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal Error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = [
            'success'        => empty ($strError),
            'message'        => $strError,
            'init_login'     => $loginToCalendly,
            'calendly_links' => $arrCalendlyLinks
        ];

        return new JsonModel($arrResult);
    }
}
