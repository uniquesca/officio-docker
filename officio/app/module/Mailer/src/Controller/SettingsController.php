<?php

namespace Mailer\Controller;

use Clients\Service\Clients;
use Exception;
use Files\Service\Files;
use Laminas\Filter\StripTags;
use Laminas\Mail\Address;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Mailer\Service\Mailer;
use Officio\BaseController;
use Officio\Email\Models\Folder;
use Officio\Email\Models\MailAccount;
use Officio\Email\ServerSuggestions;
use Officio\Common\Service\Encryption;
use Laminas\Validator\EmailAddress;
use Officio\Comms\Service\Mailer as CommsMailer;
use Officio\Service\OAuth2Client;

/**
 * Mail settings controller
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class SettingsController extends BaseController
{

    /** @var Clients */
    protected $_clients;

    /** @var Files */
    protected $_files;

    /** @var Mailer */
    protected $_mailer;

    /** @var CommsMailer */
    protected $_commsMailer;

    /** @var Encryption */
    protected $_encryption;

    /** @var ServerSuggestions */
    protected $_serverSuggestions;

    /** @var OAuth2Client */
    protected $_oauth2Client;

    public function initAdditionalServices(array $services)
    {
        $this->_clients           = $services[Clients::class];
        $this->_files             = $services[Files::class];
        $this->_mailer            = $services[Mailer::class];
        $this->_commsMailer       = $services[CommsMailer::class];
        $this->_encryption        = $services[Encryption::class];
        $this->_oauth2Client      = $services[OAuth2Client::class];
        $this->_serverSuggestions = $services[ServerSuggestions::class];
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

    public function getAction()
    {
        $arrAccounts          = array();
        $booManageOAuthTokens = false;

        try {
            $memberId = (int)Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY);
            $memberId = empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId;
            if ($this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $arrAccounts = MailAccount::getAccounts($memberId);
                foreach ($arrAccounts as $key => $accountInfo) {
                    $arrAccounts[$key]['inc_fetch_from_date'] = empty($accountInfo['inc_fetch_from_date']) ? '' : date('Y-m-d', $accountInfo['inc_fetch_from_date']);
                    $arrAccounts[$key]['inc_password']        = empty($accountInfo['inc_password']) ? '' : $this->_encryption->decode($accountInfo['inc_password']);
                    $arrAccounts[$key]['out_password']        = empty($accountInfo['out_password']) ? '' : $this->_encryption->decode($accountInfo['out_password']);
                }

                // Show 'manage oAuth tokens' button if there are saved tokens
                $arrAccessTokens      = $this->_oauth2Client->getAccessTokens($memberId);
                $booManageOAuthTokens = !empty($arrAccessTokens);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResponse = array(
            'rows'                 => array_merge($arrAccounts),
            'totalCount'           => count($arrAccounts),
            'booManageOAuthTokens' => $booManageOAuthTokens,
        );

        return new JsonModel($arrResponse);
    }

    public function getImapFoldersAction()
    {
        set_time_limit(60 * 60); // 1 hour
        ini_set('memory_limit', '512M');

        $strError              = '';
        $arrFolders            = array();
        $inboxMappingFolderId  = 0;
        $sentMappingFolderId   = 0;
        $draftsMappingFolderId = 0;
        $trashMappingFolderId  = 0;

        try {
            // Only ajax request is allowed
            if (!$this->getRequest()->isXmlHttpRequest()) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            $memberId  = (int)Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY);
            $accountId = (int)Json::decode($this->params()->fromPost('account_id'), Json::TYPE_ARRAY);

            $memberId = empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId;
            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights to client.');
            }

            if (empty($strError) && !MailAccount::hasAccessToAccount($memberId, $accountId)) {
                $strError = $this->_tr->translate('Insufficient access rights to mail account.');
            }

            if (empty($strError)) {
                $mailAccountManager = new MailAccount($accountId);
                $arrFolders         = Folder::getFoldersList($accountId, true);
                if (empty($arrFolders)) {
                    try {
                        $mailAccountManager->setIsChecking(1);
                        $this->_mailer->sync($accountId, null, false, false, true);
                        $mailAccountManager->setIsChecking(0);
                    } catch (Exception $e) {
                        $mailAccountManager->setIsChecking(0);

                        // rethrow it
                        throw $e;
                    }

                    $arrFolders = Folder::getFoldersList($accountId, true);
                }

                $inboxFolder           = new Folder(Folder::getFolderIdByName($accountId, Folder::INBOX));
                $inboxMappingFolderId  = $inboxFolder->getMappingFolderId();
                $sentFolder            = new Folder(Folder::getFolderIdByName($accountId, Folder::SENT));
                $sentMappingFolderId   = $sentFolder->getMappingFolderId();
                $draftsFolder          = new Folder(Folder::getFolderIdByName($accountId, Folder::DRAFTS));
                $draftsMappingFolderId = $draftsFolder->getMappingFolderId();
                $trashFolder           = new Folder(Folder::getFolderIdByName($accountId, Folder::TRASH));
                $trashMappingFolderId  = $trashFolder->getMappingFolderId();

                array_unshift(
                    $arrFolders,
                    array(
                        'folder_id' => 0,
                        'level'     => 0,
                        'name'      => 'No mapping'
                    )
                );
            }
        } catch (Exception $e) {
            if ($e->getMessage() == 'The check was cancelled by user') {
                $strError = $this->_tr->translate('Load cancelled by user.');
            } else {
                $strError = $this->_tr->translate('Internal error.');

                if ($e->getMessage() != "Can't connect to email account.") {
                    $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
                }
            }
        }

        $arrResponse = array(
            'success' => empty($strError),
            'message' => $strError,
            'folders' => $arrFolders,
            'inbox'   => $inboxMappingFolderId,
            'sent'    => $sentMappingFolderId,
            'drafts'  => $draftsMappingFolderId,
            'trash'   => $trashMappingFolderId,
        );

        return new JsonModel($arrResponse);
    }

    public function saveImapFoldersAction()
    {
        $strError = '';

        try {
            $filter = new StripTags();
            // Only ajax request is allowed
            if (!$this->getRequest()->isXmlHttpRequest()) {
                $strError = $this->_tr->translate('Insufficient access rights');
            }

            $memberId                 = (int)Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY);
            $memberId                 = empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId;
            $accountId                = (int)Json::decode($this->params()->fromPost('account_id'), Json::TYPE_ARRAY);
            $inboxMappingFolderId     = $filter->filter(Json::decode($this->params()->fromPost('inbox'), Json::TYPE_ARRAY));
            $sentMappingFolderId      = $filter->filter(Json::decode($this->params()->fromPost('sent'), Json::TYPE_ARRAY));
            $draftsMappingFolderId    = $filter->filter(Json::decode($this->params()->fromPost('drafts'), Json::TYPE_ARRAY));
            $trashMappingFolderId     = $filter->filter(Json::decode($this->params()->fromPost('trash'), Json::TYPE_ARRAY));
            $arrImapFoldersVisibility = Json::decode($this->params()->fromPost('imap_folders_visibility'), Json::TYPE_ARRAY);

            if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights to client.');
            }

            if (empty($strError)) {
                $mailAccountManager = new MailAccount($memberId);
                if (!$mailAccountManager::hasAccessToAccount($memberId, $accountId)) {
                    $strError = $this->_tr->translate('Insufficient access rights to mail account.');
                }
            }

            if (empty($strError)) {
                $inboxFolder  = new Folder(Folder::getFolderIdByName($accountId, Folder::INBOX));
                $sentFolder   = new Folder(Folder::getFolderIdByName($accountId, Folder::SENT));
                $draftsFolder = new Folder(Folder::getFolderIdByName($accountId, Folder::DRAFTS));
                $trashFolder  = new Folder(Folder::getFolderIdByName($accountId, Folder::TRASH));

                $inboxFolder->updateMappingFolderId($inboxMappingFolderId);
                $sentFolder->updateMappingFolderId($sentMappingFolderId);
                $draftsFolder->updateMappingFolderId($draftsMappingFolderId);
                $trashFolder->updateMappingFolderId($trashMappingFolderId);

                foreach ($arrImapFoldersVisibility as $folderInfo) {
                    $foldersModel = new Folder($folderInfo['folder_id']);
                    $foldersModel->updateIsFolderVisible($folderInfo['folder_id'], $folderInfo['checked']);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResponse = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResponse);
    }

    public function saveAction()
    {
        try {
            $booSuccess = $booRefreshEmailTab = false;
            $strMessage = '';
            $filter     = new StripTags();

            $member_id = (int)Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY);
            $member_id = empty($member_id) ? $this->_auth->getCurrentUserId() : $member_id;

            // Collect incoming info
            $arrUpdateInfo = array(
                'id'               => (int)$this->params()->fromPost('email_account_id'),
                'email'            => $filter->filter($this->params()->fromPost('email')),
                'auto_check'       => Json::decode($this->params()->fromPost('auto_check'), Json::TYPE_ARRAY),
                'auto_check_every' => Json::decode($this->params()->fromPost('auto_check_every'), Json::TYPE_ARRAY),
                'signature'        => $this->_settings->getHTMLPurifier(false)->purify($this->params()->fromPost('signature')),
                'friendly_name'    => $filter->filter($this->params()->fromPost('name')),

                'inc_enabled'         => Json::decode($this->params()->fromPost('inc_enabled'), Json::TYPE_ARRAY),
                'inc_type'            => $filter->filter($this->params()->fromPost('inc_type')),
                'inc_host'            => $filter->filter($this->params()->fromPost('inc_host')),
                'inc_port'            => $filter->filter($this->params()->fromPost('inc_port')),
                'inc_ssl'             => $filter->filter($this->params()->fromPost('inc_ssl')),
                'inc_login'           => $filter->filter($this->params()->fromPost('inc_login')),
                'inc_password'        => $this->_encryption->encode($filter->filter($this->params()->fromPost('inc_password'))),
                'inc_leave_messages'  => $filter->filter(Json::decode($this->params()->fromPost('inc_leave_messages'), Json::TYPE_ARRAY)),
                'inc_only_headers'    => $filter->filter(Json::decode($this->params()->fromPost('inc_headers_only'), Json::TYPE_ARRAY)),
                'inc_fetch_from_date' => $this->params()->fromPost('inc_fetch_from_date') ? strtotime($filter->filter($this->params()->fromPost('inc_fetch_from_date'))) : 0,
                'inc_login_type'      => $filter->filter($this->params()->fromPost('inc_login_type')),

                'out_use_own'       => Json::decode($this->params()->fromPost('out_use_own'), Json::TYPE_ARRAY),
                'out_host'          => $filter->filter($this->params()->fromPost('out_host')),
                'out_port'          => $filter->filter($this->params()->fromPost('out_port')),
                'out_auth_required' => $filter->filter(Json::decode($this->params()->fromPost('out_auth_required'), Json::TYPE_ARRAY)),
                'out_login'         => $filter->filter($this->params()->fromPost('out_login')),
                'out_password'      => $this->_encryption->encode($filter->filter($this->params()->fromPost('out_password'))),
                'out_ssl'           => $this->params()->fromPost('out_ssl'),
                'out_save_sent'     => $this->params()->fromPost('out_save_sent'),
                'out_login_type'    => $filter->filter($this->params()->fromPost('out_login_type')),

                'per_page' => (int)$this->params()->fromPost('per_page'),
                'timezone' => $filter->filter($this->params()->fromPost('timezone')),
            );

            if (empty($strMessage) && !$this->_members->hasCurrentMemberAccessToMember($member_id)) {
                $strMessage = $this->_tr->translate('Insufficient access rights');
            }

            // Check access to email account
            if (empty($strMessage)) {
                if (empty($arrUpdateInfo['id'])) {
                    $arrUpdateInfo['id'] = 0;
                } elseif (!MailAccount::hasAccessToAccount($member_id, $arrUpdateInfo['id'])) {
                    $strMessage = $this->_tr->translate('Insufficient access rights');
                }
            }

            // Check if Email address is correct
            $validator = new EmailAddress();
            if (empty($strMessage) && !$validator->isValid($arrUpdateInfo['email'])) {
                $strMessage = $this->_tr->translate('Incorrect email address');
            }

            // Check if POP3/IMAP Authentication is correct
            if (empty($strMessage) && !in_array($arrUpdateInfo['inc_login_type'], ['', 'oauth2'])) {
                $strMessage = $this->_tr->translate('Incorrect authentication');
            }

            // Check if SMTP Authentication is correct
            if (empty($strMessage) && !in_array($arrUpdateInfo['out_login_type'], ['', 'oauth2'])) {
                $strMessage = $this->_tr->translate('Incorrect authentication');
            }

            // Check if Own SMTP settings are correct
            if (empty($strMessage) && $arrUpdateInfo['out_use_own']) {
                $strMessage = $this->_testOutgoingSettings();
            }

            // Check if incoming mail server settings are correct
            if (empty($strMessage) && $arrUpdateInfo['inc_enabled']) {
                $strMessage = $this->_testIncomingSettings();
            }

            // If all is okay - save data
            if (empty($strMessage)) {
                try {
                    if (empty($arrUpdateInfo['id'])) {
                        MailAccount::createAccount($member_id, $arrUpdateInfo);
                    } else {
                        $mailAccount = new MailAccount($arrUpdateInfo['id']);
                        $mailAccount->updateAccount($arrUpdateInfo);
                    }
                    $booSuccess         = true;
                    $booRefreshEmailTab = true;
                } catch (Exception $e) {
                    $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
                }
            }

            if (!$booSuccess && empty($strMessage)) {
                $strMessage = $this->_tr->translate('Error happened during mail account settings saving.');
            }
        } catch (Exception $e) {
            $booSuccess         = false;
            $booRefreshEmailTab = false;
            $strMessage         = $this->_tr->translate('Internal error.');

            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success'         => $booSuccess,
            'refreshEmailTab' => $booRefreshEmailTab,
            'message'         => $strMessage
        );

        return new JsonModel($arrResult);
    }

    public function setAsDefaultAction()
    {
        $booSuccess = false;
        $strMessage = '';

        try {
            $accountId = (int)$this->params()->fromPost('email_account_id');
            $memberId  = (int)Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY);

            $mailAccountManager = new MailAccount($accountId, $memberId);
            if ($this->_members->hasCurrentMemberAccessToMember($memberId) && $mailAccountManager::hasAccessToAccount($memberId, $accountId)) {
                $booSuccess = $mailAccountManager->setAccountAsDefault();
                if ($booSuccess) {
                    $this->_clients->setPrimaryEmailAsDefaultMail($memberId);
                }
            } else {
                $strMessage = $this->_tr->translate('Insufficient access rights');
            }
        } catch (Exception $e) {
            $booSuccess = false;
            $strMessage = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        if (!$booSuccess && empty($strMessage)) {
            $strMessage = $this->_tr->translate('Error happened during setting mail account as default.');
        }

        $arrResponse = array(
            'success' => $booSuccess,
            'message' => $strMessage
        );

        return new JsonModel($arrResponse);
    }

    public function deleteEmailAccountAction()
    {
        $strError = '';

        // Get and check incoming info
        $arrAccounts = Json::decode($this->params()->fromPost('accounts'), Json::TYPE_ARRAY);
        if (!is_array($arrAccounts) || count($arrAccounts) == 0) {
            $strError = $this->_tr->translate('Incorrectly selected email account(s)');
        }

        $memberId = (int)Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY);
        if (empty($strError) && !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
            $strError = $this->_tr->translate('Insufficient access rights');
        }

        if (empty($strError)) {
            foreach ($arrAccounts as $accountId) {
                if (!MailAccount::hasAccessToAccount($memberId, $accountId)) {
                    $strError = $this->_tr->translate('Insufficient access rights');
                    break;
                }

                try {
                    $mailAccount       = new MailAccount($accountId);
                    $arrAccountDetails = $mailAccount->getAccountDetails();

                    $mailAccount->deleteAccount($this->_files);

                    // Check if there are other email accounts with the same email address
                    $booExistsAccount = false;

                    $arrAccounts = MailAccount::getAccounts($memberId);
                    foreach ($arrAccounts as $accountInfo) {
                        if ($accountInfo['email'] == $arrAccountDetails['email']) {
                            $booExistsAccount = true;
                            break;
                        }
                    }

                    if (!$booExistsAccount) {
                        $this->_oauth2Client->deleteTokensForMemberAndAccount($memberId, $arrAccountDetails['email']);
                    }
                } catch (Exception $e) {
                    $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString(), 'account-model');
                    $strError = $this->_tr->translate('Error happened on mail account delete.');
                }
            }
        }

        $arrResponse = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResponse);
    }


    /**
     * Check Outgoing (SMTP) Settings
     * @return string error description
     */
    private function _testOutgoingSettings()
    {
        $strError = '';

        try {
            $filter = new StripTags();
            $email  = $this->params()->fromPost('email');

            $arrTestSMTPSettings = array(
                'out_use_own'    => Json::decode($this->params()->fromPost('out_use_own'), Json::TYPE_ARRAY),
                'out_host'       => $filter->filter($this->params()->fromPost('out_host')),
                'out_port'       => $filter->filter($this->params()->fromPost('out_port')),
                'out_login'      => $filter->filter($this->params()->fromPost('out_login')),
                'out_password'   => $filter->filter($this->params()->fromPost('out_password')),
                'out_ssl'        => $filter->filter($this->params()->fromPost('out_ssl')),
                'out_login_type' => $filter->filter($this->params()->fromPost('out_login_type'))
            );

            // Check incoming params
            $validator = new EmailAddress();
            if (!$validator->isValid($email)) {
                // email is invalid; print the reasons
                foreach ($validator->getMessages() as $message) {
                    $strError .= "$message\n";
                }
            }

            if (empty($strError) && !in_array($arrTestSMTPSettings['out_login_type'], array('', 'oauth2'))) {
                $strError = $this->_tr->translate('Incorrectly selected Authentication');
            }

            $accessToken = '';
            if (empty($strError) && $arrTestSMTPSettings['out_use_own'] && $arrTestSMTPSettings['out_login_type'] == 'oauth2') {
                list($strError, $accessToken) = $this->_oauth2Client->getAccessToken(
                    $this->_auth->getCurrentUserId(),
                    $arrTestSMTPSettings['out_host'],
                    $email,
                    'smtp'
                );
            }

            if (empty($strError)) {
                try {
                    $support = $this->_settings->getOfficioSupportEmail();
                    if ($arrTestSMTPSettings['out_use_own']) {
                        $transport = MailAccount::createOutboundTransport($arrTestSMTPSettings, $accessToken);
                        $from      = $email;
                    } else {
                        // Default Officio SMTP transport
                        $from      = $support['email'];
                        $transport = $this->_commsMailer->getOfficioSmtpTransport();
                    }

                    $body = "This is an e-mail message sent automatically by Officio while testing SMTP settings.";
                    $from = new Address($from, $support['label']);
                    $this->_commsMailer->processAndSendMail($email, 'Officio Test Message', $body, $from, null, null, [], true, $transport);
                } catch (Exception $e) {
                    $resultMessage = $e->getMessage();

                    // Remove not correct text (all after 'Learn more at')
                    $pos = strpos($resultMessage, 'Learn more at');
                    if ($pos) {
                        $resultMessage = substr($resultMessage, 0, $pos);
                    }

                    // Return result
                    $strError = 'Incorrect server or user information.<br/><i>Technical Details: ' . htmlentities($resultMessage) . '</i>';
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');

            if (!in_array($e->getMessage(), array("Can't connect to email account.", 'cannot read - connection closed?')) && mb_stripos($e->getMessage(), 'Could not read from') === false) {
                $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
            }
        }


        return $strError;
    }

    /**
     * Check Incoming (Imap/POP3) Settings
     * @return string error description
     */
    private function _testIncomingSettings()
    {
        $strError = '';
        $filter   = new StripTags();

        $email = $this->params()->fromPost('email');

        // Check incoming params
        $validator = new EmailAddress();
        if (!$validator->isValid($email)) {
            // email is invalid; print the reasons
            foreach ($validator->getMessages() as $message) {
                $strError .= "$message\n";
            }
        }

        $arrTestPop3Settings = array(
            'inc_enabled'    => Json::decode($this->params()->fromPost('inc_enabled'), Json::TYPE_ARRAY),
            'inc_type'       => $filter->filter($this->params()->fromPost('inc_type')),
            'inc_host'       => $filter->filter($this->params()->fromPost('inc_host')),
            'inc_port'       => $filter->filter($this->params()->fromPost('inc_port')),
            'inc_ssl'        => $filter->filter($this->params()->fromPost('inc_ssl', '')),
            'inc_login'      => $filter->filter($this->params()->fromPost('inc_login')),
            'inc_password'   => $filter->filter($this->params()->fromPost('inc_password')),
            'inc_login_type' => $filter->filter($this->params()->fromPost('inc_login_type'))
        );


        if ($arrTestPop3Settings['inc_enabled']) {
            if (empty($strError) && !in_array($arrTestPop3Settings['inc_type'], array('pop3', 'imap'))) {
                $strError = $this->_tr->translate('Incorrectly selected server type');
            }

            if (empty($strError) && !in_array($arrTestPop3Settings['inc_login_type'], array('', 'oauth2'))) {
                $strError = $this->_tr->translate('Incorrectly selected Authentication');
            }

            $accessToken = '';
            if (empty($strError) && $arrTestPop3Settings['inc_login_type'] == 'oauth2') {
                list($strError, $accessToken) = $this->_oauth2Client->getAccessToken(
                    $this->_auth->getCurrentUserId(),
                    $arrTestPop3Settings['inc_host'],
                    $email,
                    $arrTestPop3Settings['inc_type']
                );
            }

            if (empty($strError)) {
                try {
                    $storage = MailAccount::createStorage($arrTestPop3Settings, $accessToken);
                    unset($storage);
                } catch (Exception $e) {
                    $strError = sprintf(
                        $this->_tr->translate('Incorrect server or user information.<br/><i>Technical Details: %s</i>'),
                        htmlentities($e->getMessage())
                    );
                }
            }
        }

        return $strError;
    }

    /**
     * Check smtp or pop3/imap settings
     */
    public function testMailSettingsAction()
    {
        session_write_close();

        switch ($this->params()->fromPost('test_action')) {
            case 'smtp':
                $strReturnMessage = $this->_testOutgoingSettings();
                break;

            case 'pop3':
                $strReturnMessage = $this->_testIncomingSettings();
                break;

            default:
                $strReturnMessage = $this->_tr->translate('Incorrect action');
                break;
        }

        return new JsonModel(array('message' => $strReturnMessage));
    }

    public function getMailServerSuggestionsAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        $arrRows      = array();
        $arrSearch    = array();
        $totalRecords = 0;
        $strCallback  = '';

        try {
            $filter      = new StripTags();
            $strCallback = $filter->filter($this->params()->fromPost('callback'));

            list($totalRecords, $arrRows, $arrSearch) = $this->_serverSuggestions->getServerSuggestions(
                trim($filter->filter($this->params()->fromPost('query', ''))),
                $filter->filter($this->params()->fromPost('type', 'pop3')),
                (int)$this->params()->fromPost('start', 0),
                (int)$this->params()->fromPost('limit', 10)
            );
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'totalCount' => $totalRecords,
            'rows'       => $arrRows,
            'search'     => $arrSearch
        );

        $view->setVariable('content', $strCallback . '(' . Json::encode($arrResult) . ')');
        return $view;
    }

    public function oauthLoginAction()
    {
        $provider = $this->params()->fromQuery('provider');
        $email    = $this->params()->fromQuery('email');
        $type     = $this->params()->fromQuery('type');

        if (!empty($provider) && !empty($email)) {
            $oProvider = $this->_oauth2Client->getOAuthProvider($provider, 'mail');
            if (!is_null($oProvider)) {
                $authUrl = $oProvider->getAuthorizationUrl($this->_oauth2Client->getOAuthProviderScopesByProvider($provider, $type));

                $_SESSION['mailoauth2state']    = $oProvider->getState();
                $_SESSION['mailoauth2provider'] = $provider;
                $_SESSION['mailoauth2email']    = $email;
                $_SESSION['mailoauth2type']     = $type;

                return $this->redirect()->toUrl($authUrl);
            }
        }

        $response = $this->getResponse();
        $response->setStatusCode(400);
        $response->setReasonPhrase('Bad Request');
        return $response;
    }

    public function oauthCallbackAction()
    {
        $strError   = '';
        $strSuccess = '';

        try {
            $state = $this->params()->fromQuery('state', '');
            $code  = $this->params()->fromQuery('code', '');
            if (empty($state) || ($state !== $_SESSION['mailoauth2state']) || empty($code)) {
                $error    = $this->params()->fromQuery('error_description', '');
                $strError = empty($error) ? $this->_tr->translate('Incorrect setting details or expired link.') : $error;
            } else {
                $provider = $this->_oauth2Client->getOAuthProvider($_SESSION['mailoauth2provider'], 'mail');

                // Try to get an access token using the authorization code grant.
                $token = $provider->getAccessToken('authorization_code', [
                    'code' => $code
                ]);

                $accessToken  = $token->getToken();
                $refreshToken = $token->getRefreshToken();
                if (!empty($accessToken)) {
                    $this->_oauth2Client->createUpdateAccessToken(
                        $this->_auth->getCurrentUserId(),
                        $_SESSION['mailoauth2email'],
                        $_SESSION['mailoauth2provider'],
                        $_SESSION['mailoauth2type'],
                        $accessToken,
                        $refreshToken
                    );

                    $strSuccess = sprintf(
                        $this->_tr->translate('You have successfully set up the access token for %s.<br/><br/>You can now close this browser tab and continue.'),
                        $_SESSION['mailoauth2email']
                    );
                } else {
                    // Cannot be here, but...
                    $strError = $this->_tr->translate('Access token was not generated.');
                }
            }

            unset($_SESSION['mailoauth2state'], $_SESSION['mailoauth2provider'], $_SESSION['mailoauth2email'], $_SESSION['mailoauth2type']);
        } catch (Exception $e) {
            // Failed to get the access token or user details.
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = [
            'showTopLogo' => true,
            'strMessage'  => empty($strError) ? $strSuccess : $this->_tr->translate('Error: ') . $strError . '<br/><br/>' . $this->_tr->translate('Please close this browser tab.')
        ];

        return new ViewModel($arrResult);
    }

    public function getTokensAction()
    {
        $arrTokens = [];

        try {
            $memberId = (int)Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY);
            $memberId = empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId;
            if ($this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $arrSavedTokens = $this->_oauth2Client->getAccessTokens($memberId);
                foreach ($arrSavedTokens as $arrTokenInfo) {
                    $arrTokens[] = [
                        'token_id'       => $arrTokenInfo['id'],
                        'token_email'    => $arrTokenInfo['remote_account_id'],
                        'token_provider' => ucfirst($arrTokenInfo['provider']),
                        'token_type'     => mb_strtoupper($arrTokenInfo['additional_data'])
                    ];
                }
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResponse = array(
            'rows'       => array_merge($arrTokens),
            'totalCount' => count($arrTokens),
        );

        return new JsonModel($arrResponse);
    }

    public function deleteTokensAction()
    {
        $strError = '';

        try {
            $memberId = (int)Json::decode($this->params()->fromPost('member_id'), Json::TYPE_ARRAY);
            $memberId = empty($memberId) ? $this->_auth->getCurrentUserId() : $memberId;
            if (!$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $arrTokens = Json::decode($this->params()->fromPost('tokens'), Json::TYPE_ARRAY);
            if (empty($strError) && empty($arrTokens)) {
                $strError = $this->_tr->translate('Please select at least one token to delete.');
            }

            if (empty($strError)) {
                $arrSavedTokens    = $this->_oauth2Client->getAccessTokens($memberId);
                $arrSavedTokensIds = array_column($arrSavedTokens, 'id');
                foreach ($arrTokens as $tokenIdToDelete) {
                    if (!in_array($tokenIdToDelete, $arrSavedTokensIds)) {
                        $strError = $this->_tr->translate('Incorrectly selected token.');
                        break;
                    }
                }
            }

            if (empty($strError)) {
                $this->_oauth2Client->deleteTokens($arrTokens);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $this->getResponse()->setStatusCode(400);
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }
}
