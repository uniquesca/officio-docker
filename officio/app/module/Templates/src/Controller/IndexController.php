<?php

namespace Templates\Controller;

use Clients\Service\Clients;
use Documents\Service\Documents;
use Exception;
use Files\Service\Files;
use Forms\Service\Forms;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Uniques\Php\StdLib\FileTools;
use Officio\Service\Company;
use Officio\Common\Service\Encryption;
use Officio\Common\Service\Settings;
use Officio\Service\ZohoKeys;
use Officio\Templates\SystemTemplates;
use Prospects\Service\CompanyProspects;
use Templates\Service\Templates;
use Laminas\Validator\EmailAddress;

/**
 * Templates page Index Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class IndexController extends BaseController
{

    /** @var Templates */
    private $_templates;

    /** @var Documents */
    private $_documents;

    /** @var Clients */
    protected $_clients;

    /** @var Company */
    protected $_company;

    /** @var Files */
    protected $_files;

    /** @var CompanyProspects */
    protected $_companyProspects;

    /** @var Forms */
    protected $_forms;

    /** @var Encryption */
    protected $_encryption;

    /** @var SystemTemplates */
    protected $_systemTemplates;

    public const MAX_UPLOAD_FILE_SIZE = 1048576; // 1Mb

    public function initAdditionalServices(array $services)
    {
        $this->_company          = $services[Company::class];
        $this->_clients          = $services[Clients::class];
        $this->_templates        = $services[Templates::class];
        $this->_documents        = $services[Documents::class];
        $this->_files            = $services[Files::class];
        $this->_companyProspects = $services[CompanyProspects::class];
        $this->_forms            = $services[Forms::class];
        $this->_encryption       = $services[Encryption::class];
        $this->_systemTemplates  = $services[SystemTemplates::class];
    }

    public function indexAction()
    {
        $view = new ViewModel(
            [
                'content' => null
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }

    public function getFieldsAction()
    {
        $view = new JsonModel();
        return $view->setVariables($this->_templates->getTemplateFilterFields($this->findParam('filter_by')));
    }

    public function getFieldsFilterAction()
    {
        return new JsonModel($this->_templates->getTemplateFilterGroups());
    }

    public function saveAction()
    {
        $templateId              = 0;
        $folderName              = '';
        $strError                = '';
        $booAutomaticallyOpenTab = true;
        $arrFileAttachments      = array();

        try {
            if ($this->getRequest()->isPost()) {
                $arrParams         = $this->params()->fromPost();
                $folderName        = $arrParams['templates_name'];
                $booCreateTemplate = $arrParams['act'] == 'add';
                $tempFileId        = 'template-upload';

                if ($arrParams['templates_type'] == 'Letter') {
                    $fileType = $booCreateTemplate ? $arrParams['file-type'] : '';
                } else {
                    $fileType = '';
                }

                if (empty($strError) && !$booCreateTemplate) {
                    // This is edit, lets check if user can edit this template
                    $templateId    = $arrParams['template_id'];
                    $access_rights = $this->_templates->getAccessRightsToTemplate($templateId);

                    if ($access_rights != 'edit') {
                        // This user cannot edit template
                        $strError = $this->_tr->translate('Insufficient access rights.');
                    }
                }

                if ($arrParams['templates_type'] == 'Letter' && $fileType == 'upload' && !empty($_FILES[$tempFileId]['name']) && !empty($_FILES[$tempFileId]['tmp_name'])) {
                    $extension = FileTools::getFileExtension($_FILES[$tempFileId]['name']);

                    if (empty($strError) && !$this->_files->isFileFromWhiteList($extension)) {
                        $strError = $this->_tr->translate('File type is not from whitelist.');
                    }

                    if (empty($strError) && filesize($_FILES[$tempFileId]['tmp_name']) > self::MAX_UPLOAD_FILE_SIZE) {
                        $strError = $this->_tr->translate(sprintf('Uploaded file is too large. (Max %s).', Settings::formatSize(self::MAX_UPLOAD_FILE_SIZE / 1024)));
                    }
                }

                $companyId = $this->_auth->getCurrentUserCompanyId();
                if (empty($strError)) {
                    $arrFolderInfo = $this->_files->getFolders()->getFolderInfo($arrParams['folder_id'], $companyId);
                    if (empty($arrFolderInfo['folder_id'])) {
                        $strError = $this->_tr->translate('Incorrectly selected folder. Maybe it was already deleted?');
                    }
                }

                if (empty($strError)) {
                    if ($arrParams['templates_type'] == 'Email') {
                        $arrParams['templates_attachments']      = array_filter(explode(',', $arrParams['templates_attachments'] ?? ''));
                        $arrParams['templates_file_attachments'] = Json::decode($arrParams['templates_file_attachments'], Json::TYPE_ARRAY);
                    }

                    $templateId = $this->_templates->saveTemplate($arrParams);
                    if (empty($templateId)) {
                        $strError = $this->_tr->translate('Internal error.');
                    }
                }

                if (empty($strError) && !empty($templateId) && $arrParams['templates_type'] == 'Letter' && $booCreateTemplate) {
                    $strError = $this->_documents->saveCompanyLetterTemplate(
                        $this->_auth->getCurrentUserCompanyId(),
                        $templateId,
                        $fileType,
                        $tempFileId
                    );

                    if (!empty($strError)) {
                        $this->_templates->deleteTemplateById($templateId);
                    }
                }

                if (empty($strError) && !empty($templateId) && $arrParams['templates_type'] == 'Email') {
                    $fileAttachments = $this->_templates->getTemplateFileAttachments($templateId);

                    foreach ($fileAttachments as $fileAttachment) {
                        $filePath = $this->_files->getCompanyTemplateAttachmentsPath($companyId, $this->_company->isCompanyStorageLocationLocal($companyId)) . '/' . $templateId . '/' . $fileAttachment['id'];
                        $fileId   = $this->_encryption->encode($filePath);
                        $fileSize = Settings::formatSize($fileAttachment['size'] / 1024);

                        $arrFileAttachments[] = array(
                            'id'      => $fileAttachment['id'],
                            'file_id' => $fileId,
                            'size'    => $fileSize,
                            'link'    => '#',
                            'name'    => $fileAttachment['name']
                        );
                    }
                }

                /** @var ZohoKeys $zohoKeys */
                $zohoKeys = $this->_serviceManager->get(ZohoKeys::class);

                // Prevent automatically "edit template" tab opening if Zoho is disabled
                $booAutomaticallyOpenTab = $zohoKeys->isZohoEnabled();
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'            => empty($strError),
            'error'              => $strError,
            'template_id'        => $templateId,
            'folder_name'        => $folderName,
            'automatically_open' => $booAutomaticallyOpenTab,
            'file_attachments'   => $arrFileAttachments
        );

        // When extjs form is submitted - content type json generates error there...
        $view = new ViewModel([
            'content' => Json::encode($arrResult)
        ]);
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }

    public function getFileAction()
    {
        $params = $this->findParams();

        if (array_key_exists('enc', $params)) {
            $arrInfo    = unserialize($this->_encryption->decode($params['enc']));
            $filePath   = $arrInfo['id'];
            $booExpired = (int)$arrInfo['exp'] < time();
            if ($booExpired) {
                return $this->renderError($this->_tr->translate('File link already expired.'));
            }
        } elseif ($this->_auth->hasIdentity()) {
            $filePath = $this->_encryption->decode($params['id']);
        } else {
            return $this->renderError($this->_tr->translate('Insufficient access rights.'));
        }

        list($booLocal, $filePath) = $this->_files->getCorrectFilePathAndLocationByPath($filePath);

        /**
         * We need check access for such cases:
         * 1. Logged-in user tries to get a file - check access to the file
         * 2. Not logged-in user (must be Zoho) tries to get a file - expiration will be checked
         */
        if ($this->_auth->hasIdentity()) {
            $booHasAccess = $this->_files->checkTemplateFTPFolderAccess($filePath);
            if (!$booHasAccess) {
                return $this->renderError($this->_tr->translate('Insufficient access rights.'));
            }
        }

        if (empty($filePath)) {
            return $this->renderError($this->_tr->translate('Incorrect params.'));
        }

        if ($booLocal) {
            return $this->downloadFile($filePath, 'template.docx');
        } else {
            $url = $this->_files->getCloud()->getFile($filePath, 'template.docx');
            if ($url) {
                return $this->redirect()->toUrl($url);
            } else {
                return $this->renderError($this->_tr->translate('File not found.'));
            }
        }
    }

    public function getLetterTemplateFileAction()
    {
        $strError         = '';
        $fileDownloadLink = '';
        $arrTemplateInfo  = array();

        try {
            $templateId      = (int)$this->params()->fromPost('template_id');
            $booDownload     = (bool)$this->params()->fromPost('download');
            $strAccessRights = $this->_templates->getAccessRightsToTemplate($templateId);
            if ($strAccessRights) {
                $arrTemplateInfo = $this->_templates->getTemplate($templateId);

                $companyId = $this->_auth->getCurrentUserCompanyId();
                $memberId  = $this->_auth->getCurrentUserId();
                $booLocal  = $this->_company->isCompanyStorageLocationLocal($companyId);
                $filePath  = $this->_files->getCompanyLetterTemplatesPath($companyId, $booLocal) . '/' . $templateId;
                $fileName  = $arrTemplateInfo['name'] . '.docx';

                /** @var ZohoKeys $zohoKeys */
                $zohoKeys = $this->_serviceManager->get(ZohoKeys::class);
                $booZohoSupported = $zohoKeys->isZohoEnabled();

                $booFileExists = $booLocal ? is_file($filePath) : $this->_files->getCloud()->checkObjectExists($filePath);
                if ($booFileExists) {
                    if (!$booLocal) {
                        $fileDownloadLink = $this->_files->getCloud()->generateFileDownloadLink($filePath, false, true, $fileName, !$booZohoSupported);
                    } else {
                        $arrParams = array(
                            'id'    => $filePath,
                            'mem'   => $memberId,
                            'c_mem' => $memberId,
                            'exp'   => strtotime('+2 minutes')
                        );

                        $fileDownloadLink = $this->layout()->getVariable('topBaseUrl') . '/templates/index/get-file?enc=' . $this->_encryption->encode(serialize($arrParams));
                    }

                    if ($booZohoSupported && !$booDownload) {
                        $arrEncCheckParams = array(
                            'template_id' => $templateId,
                            'c_mem'       => $memberId,
                            'company_id'  => $companyId
                        );

                        $strEncCheckParams = $this->_encryption->encode(serialize($arrEncCheckParams));

                        $fileSaveLink = $this->layout()->getVariable('topBaseUrl') . '/templates/index/save-letter-template-file';

                        list($strError, $zohoDocumentUrl) = $this->_files->getZohoDocumentUrl($fileName, $this->_encryption->encode($filePath), 'docx', $fileDownloadLink, $fileSaveLink, $strEncCheckParams);
                        if (empty($strError)) {
                            $fileDownloadLink = $zohoDocumentUrl;
                        }
                    }

                    list($arrTemplateInfo,) = $this->getTemplateInfo($templateId);
                } else {
                    throw new Exception(
                        $this->_tr->translate('Letter template file does not exists: ') . $filePath
                    );
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'   => empty($strError),
            'message'   => $strError,
            'template'  => $arrTemplateInfo,
            'file_path' => $fileDownloadLink,
        );

        return new JsonModel($arrResult);
    }

    public function saveLetterTemplateFileAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        try {
            $filter   = new StripTags();
            $fileId   = $this->findParam('id');
            $filename = $filter->filter($this->findParam('filename'));
            $file     = $_FILES['content'] ?? '';

            $strError = '';

            $arrCheckParams = unserialize($this->_encryption->decode($this->findParam('enc')));

            $currentMemberId = $arrCheckParams['c_mem'];
            $templateId      = $arrCheckParams['template_id'];
            $companyId       = $arrCheckParams['company_id'];

            $accessRights = $this->_templates->getAccessRightsToTemplate($templateId, $currentMemberId);

            if ($accessRights != 'edit') {
                // This user cannot edit template
                $strError = $this->_tr->translate('Insufficient access rights to template.');
            }

            if (empty($strError)) {
                $filePath = $this->_files->getCompanyLetterTemplatesPath($companyId, $this->_company->isCompanyStorageLocationLocal($companyId)) . '/' . $templateId;
                if ($filePath != $this->_encryption->decode($fileId)) {
                    $strError = $this->_tr->translate('Insufficient access rights to file.');
                }
            }

            if (empty($strError) && empty($fileId) || empty($filename) || empty($file)) {
                $this->_log->debugErrorToFile('', sprintf('Save-file action with params: file_id = %s, filename = %s', $fileId, $filename));
                $strError = $this->_tr->translate('No file to save');
            }

            if (empty($strError) && !$this->_files->saveFile($fileId, $file)) {
                // Show message if something wrong
                $strError = $this->_tr->translate('File was not saved.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        return $view->setVariable('content', $strError);
    }

    private function getTemplateInfo($id)
    {
        $access_rights = $this->_templates->getAccessRightsToTemplate($id);

        $template = array();

        if ($access_rights) {
            $template        = $this->_templates->getTemplate((int)$id);
            $attachments     = $this->_templates->getTemplateAttachments((int)$id);
            $fileAttachments = $this->_templates->getTemplateFileAttachments((int)$id);

            $template['attachments'] = array();
            foreach ($attachments as $key => $attachment) {
                if ($this->_templates->hasAccessToTemplate($attachment['letter_template_id'])) {
                    $template['attachments'][] = $attachments[$key];
                }
            }

            $template['file_attachments'] = array();
            $companyId                    = $this->_auth->getCurrentUserCompanyId();

            foreach ($fileAttachments as $fileAttachment) {
                $filePath = $this->_files->getCompanyTemplateAttachmentsPath($companyId, $this->_company->isCompanyStorageLocationLocal($companyId)) . '/' . $id . '/' . $fileAttachment['id'];
                $fileId   = $this->_encryption->encode($filePath);
                $fileSize = Settings::formatSize($fileAttachment['size'] / 1024);

                $template['file_attachments'][] = array(
                    'id'      => $fileAttachment['id'],
                    'file_id' => $fileId,
                    'size'    => $fileSize,
                    'link'    => '#',
                    'name'    => $fileAttachment['name']
                );
            }
        }

        return array($template, $access_rights);
    }

    public function getTemplateAction()
    {
        $id = $this->params()->fromPost('id');

        list($template, $access_rights) = $this->getTemplateInfo($id);

        return new JsonModel(array('template' => $template, 'access' => $access_rights));
    }

    public function getMessageAction()
    {
        $result = array();

        try {
            $filter             = new StripTags();
            $templateId         = (int)$this->params()->fromPost('template_id');
            $memberId           = (int)$this->params()->fromPost('member_id');
            $invoiceId          = (int)$this->params()->fromPost('invoice_id');
            $parentMemberId     = (int)$this->params()->fromPost('parentMemberId', 0);
            $email              = $filter->filter(Json::decode($this->params()->fromPost('email'), Json::TYPE_ARRAY));
            $booProspect        = Json::decode($this->params()->fromPost('booProspect'), Json::TYPE_ARRAY);
            $parseToField       = $filter->filter($this->params()->fromPost('parse_to_field', ''));
            $booUseParseToField = $this->params()->fromPost('use_parse_to_field');
            $booSaveToProspect  = Json::decode($this->params()->fromPost('save_to_prospect'));
            $decodedPassword    = Json::decode($this->params()->fromPost('encoded_password'), Json::TYPE_ARRAY);
            if (!empty($decodedPassword)) {
                $decodedPassword = $this->_encryption->decode($decodedPassword);
            }

            if (!is_numeric($memberId) && $booUseParseToField) {
                preg_match_all('/[<](.*)[>]/U', $parseToField, $matches);

                $clients = $this->_clients->getClientsList();

                if (!empty($matches[1]))
                    $parse_array = $matches[1];
                else
                    $parse_array = explode(',', $parseToField);

                foreach ($parse_array as $pa) {
                    foreach ($clients as $c)
                        if ($c['emailAddress'] != '' && $c['emailAddress'] == $pa) {
                            $memberId = $c['member_id'];
                            break 2;
                        }
                }
            }

            $prospectId = 0;
            if ($booProspect) {
                $prospectId = $memberId;
                if (!$this->_companyProspects->allowAccessToProspect($prospectId)) {
                    $prospectId = 0;
                }

                $memberId = $this->_auth->getCurrentUserId();
            } elseif (empty($memberId) && !empty($parentMemberId)) {
                $memberId = $parentMemberId;
            }

            if ($booSaveToProspect) {
                $message = $to = $from = $cc = $bcc = $subject = '';
                if ($this->_companyProspects->getCompanyQnr()->hasAccessToTemplate($templateId)) {
                    $templateInfo = $this->_companyProspects->getCompanyQnr()->getTemplate($templateId);

                    if (!empty($templateInfo)) {
                        $memberInfo  = $this->_clients->getMemberInfo($memberId);
                        $companyId   = $this->_company->getMemberCompanyId($memberId);
                        $companyInfo = $this->_company->getCompanyAndDetailsInfo($companyId);
                        $adminInfo   = empty($companyInfo['admin_id']) ? [] : $this->_clients->getMemberInfo((int)$companyInfo['admin_id']);

                        $replacements = $this->_company->getTemplateReplacements($companyInfo, $adminInfo);
                        if (!empty($prospectId)) {
                            $replacements += $this->_companyProspects->getTemplateReplacements((int)$prospectId);
                        }
                        $replacements += $this->_clients->getTemplateReplacements($memberInfo);
                        list($message, $to, $cc, $bcc, $subject) = $this->_systemTemplates->processText(
                            [
                                $templateInfo['message'],
                                $templateInfo['to'],
                                $templateInfo['cc'],
                                $templateInfo['bcc'],
                                $templateInfo['subject']
                            ],
                            $replacements
                        );

                        $from = $templateInfo['from'];
                    }

                    if (empty($to) && !empty($prospectId)) {
                        $prospectInfo = $this->_companyProspects->getProspectInfo($prospectId, null);
                        $to           = $prospectInfo['email'];
                    }
                }

                $result = [
                    'message' => $message,
                    'from'    => $from,
                    'email'   => $to,
                    'cc'      => $cc,
                    'bcc'     => $bcc,
                    'subject' => $subject
                ];
            } elseif ($this->_templates->getAccessRightsToTemplate($templateId)) {
                // Not always member id is provided, e.g. new email dialog from the My Email tab
                if (!empty($memberId) && !$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                    $memberId = 0;
                }

                $arrExtraFields = [];
                if (!empty($invoiceId) && $this->_clients->getAccounting()->hasAccessToInvoice($invoiceId)) {
                    $arrExtraFields['invoice_id'] = $invoiceId;
                }

                if (!empty($decodedPassword)) {
                    $arrExtraFields['decoded_password'] = $decodedPassword;
                }

                $result                = $this->_templates->getMessage($templateId, (int)$memberId, $email, (int)$prospectId, false, false, $arrExtraFields, $parentMemberId);
                $result['attachments'] = $this->_templates->parseTemplateAttachments($templateId, (int)$memberId, false, true);
            }

            //Handling CC field to remove empty subfields in it
            $emailValidator = new EmailAddress();
            $addresses      = isset($result['cc']) ? explode(",", $result['cc']) : array();
            foreach ($addresses as $key => $value) {
                if ($value == '-' || !$emailValidator->isValid($value)) {
                    unset($addresses[$key]);
                }
            }
            $result['cc'] = implode(',', $addresses);

            //Handling BCC field
            $addresses = isset($result['bcc']) ? explode(",", $result['bcc']) : array();
            foreach ($addresses as $key => $value) {
                if ($value == '-' || !$emailValidator->isValid($value)) {
                    unset($addresses[$key]);
                }
            }
            $result['bcc'] = implode(',', $addresses);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel($result);
    }

    public function getTemplatesListAction()
    {
        $view = new JsonModel();

        try {
            $filter            = new StripTags();
            $booWithoutOther   = Json::decode($this->findParam('withoutOther'), Json::TYPE_ARRAY);
            $msgType           = $filter->filter(Json::decode($this->findParam('msg_type'), Json::TYPE_ARRAY));
            $template_for      = $filter->filter(Json::decode($this->findParam('template_for'), Json::TYPE_ARRAY));
            $templates_type    = $filter->filter(Json::decode($this->findParam('templates_type'), Json::TYPE_ARRAY));
            $booLoadOnlyShared = (bool)($this->findParam('only_shared', 0));
            $templateId        = (int)($this->findParam('template_id', 0));

            $booSharedTemplate = $this->_templates->isSharedTemplate($templateId);

            $templates  = $this->_templates->getTemplatesList($booWithoutOther, $msgType, $template_for, $templates_type, $booLoadOnlyShared && $booSharedTemplate);
            $booSuccess = true;
        } catch (Exception $e) {
            $templates  = array();
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'    => $booSuccess,
            'rows'       => $templates,
            'totalCount' => count($templates)
        );
        return $view->setVariables($arrResult);
    }

    public function getTemplatesAction()
    {
        return new JsonModel($this->_templates->getTemplatesContent());
    }

    public function dragAndDropAction()
    {
        $view = new JsonModel();

        $booSuccess = false;

        try {
            $templateId = (int)$this->findParam('file_id');
            $folderId   = (int)$this->findParam('folder_id');
            $order      = (int)$this->findParam('order');

            $accessRights = $this->_templates->getAccessRightsToTemplate($templateId);
            if ($accessRights === 'edit' && $this->_files->getFolders()->hasCurrentMemberAccessToFolder($folderId, 'create')) {
                $booSuccess = $this->_templates->dragAndDrop($templateId, $folderId, $order);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => $booSuccess));
    }

    public function deleteAction()
    {
        $view = new JsonModel();

        $filter    = new StripTags();
        $templates = Json::decode(stripslashes($filter->filter($this->findParam('templates', ''))), Json::TYPE_ARRAY);
        $success   = $this->_templates->delete($templates);

        return $view->setVariables(array('success' => $success));
    }

    public function duplicateAction()
    {
        $view = new JsonModel();

        $templates = Json::decode(stripslashes($this->findParam('templates', '')), Json::TYPE_ARRAY);
        $strError  = $this->_templates->duplicate($templates);
        return $view->setVariables(array(
                                       'success' => empty($strError),
                                       'message' => $strError
                                   ));
    }

    public function renameAction()
    {
        $view = new JsonModel();

        $strError = '';

        try {
            $filter = new StripTags();

            $templateName = $filter->filter(trim(Json::decode($this->findParam('template_name', ''), Json::TYPE_ARRAY)));
            $templateId   = (int)$this->findParam('template_id');

            $accessRights = $this->_templates->getAccessRightsToTemplate($templateId);

            if ($accessRights !== 'edit') {
                // This user cannot edit template
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $this->_templates->renameTemplate($templateId, $templateName);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResponse = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('Done!') : $strError
        );

        return $view->setVariables($arrResponse);
    }

    public function addFolderAction()
    {
        $view = new JsonModel();

        $strError = '';

        try {
            $filter         = new StripTags();
            $folderName     = $filter->filter(trim(Json::decode($this->findParam('name', ''), Json::TYPE_ARRAY)));
            $parentFolderId = (int)$this->findParam('parent_id');

            if (empty($strError) && !strlen($folderName)) {
                $strError = $this->_tr->translate('Please enter a correct folder name.');
            }

            if (empty($strError) && !empty($parentFolderId) && !$this->_files->getFolders()->hasCurrentMemberAccessToFolder($parentFolderId, 'create')) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                $booSuccess = $this->_templates->addFolder(
                    array(
                        'folder_name' => $folderName,
                        'parent_id'   => $parentFolderId
                    )
                );

                if (!$booSuccess) {
                    $strError = $this->_tr->translate('Folder was not created.');
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResponse = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('Done!') : $strError
        );

        return $view->setVariables($arrResponse);
    }

    public function renameFolderAction()
    {
        $view = new JsonModel();

        $strError = '';
        try {
            $filter     = new StripTags();
            $folderId   = $this->findParam('folder_id');
            $folderName = $filter->filter(trim(Json::decode($this->findParam('folder_name', ''), Json::TYPE_ARRAY)));
            $type       = $filter->filter($this->findParam('type'));
            $type       = !empty($type) ? Json::decode($filter->filter($this->findParam('type')), Json::TYPE_ARRAY) : '';

            if ($type != 'templates' || !$this->_files->getFolders()->hasCurrentMemberAccessToFolder($folderId, 'rename')) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError) && !$this->_templates->renameFolder($folderId, $folderName)) {
                $strError = $this->_tr->translate('The folder was not renamed. Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => empty($strError), 'message' => $strError));
    }

    public function deleteFolderAction()
    {
        $view = new JsonModel();

        $booSuccess = false;

        try {
            $folderId = $this->findParam('folder_id');

            if ($this->_files->getFolders()->hasCurrentMemberAccessToFolder($folderId, 'delete')) {
                $booSuccess = $this->_templates->deleteFolder($folderId);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => $booSuccess));
    }

    public function getEmailTemplateAction()
    {
        try {
            $filter           = new StripTags();
            $memberId         = (int)$this->params()->fromPost('member_id');
            $parentMemberId   = (int)$this->params()->fromPost('parent_member_id');
            $templatesType    = $filter->filter($this->params()->fromPost('templates_type'));
            $booShowTemplates = (bool)Json::decode($this->params()->fromPost('show_templates'), Json::TYPE_ARRAY);

            //get templates list
            $arrTemplates = array();
            if ($booShowTemplates) {
                if (!empty($templatesType)) {
                    $arrTemplates = $this->_templates->getTemplatesList(true, 0, $templatesType, 'Email');
                } else {
                    $arrTemplates = $this->_templates->getTemplatesList(true, 0, false, 'Email');
                }
            }

            //get client and user info
            $memberIdToLoad = empty($memberId) && !empty($parentMemberId) ? $parentMemberId : $memberId;
            $clientInfo     = !empty($memberIdToLoad) && $this->_members->hasCurrentMemberAccessToMember($memberIdToLoad) ? $this->_members->getMemberInfo($memberIdToLoad) : array();
            $userInfo       = $this->_members->getMemberInfo();

            //get default template id
            if (!empty($templatesType)) {
                $defaultTemplateId = isset($arrTemplates[0]) ? $arrTemplates[0]['templateId'] : 0;
            } else {
                $defaultTemplateId = $this->_templates->getDefaultTemplateId();

                // Make sure that this default template is in the list
                // If not - select the first one
                $booFoundTemplate = false;
                foreach ($arrTemplates as $arrTemplateInfo) {
                    if ($arrTemplateInfo['templateId'] == $defaultTemplateId) {
                        $booFoundTemplate = true;
                        break;
                    }
                }

                if (!$booFoundTemplate) {
                    $defaultTemplateId = isset($arrTemplates[0]) ? $arrTemplates[0]['templateId'] : 0;
                }
            }

            $arrResult = array(
                'title'               => 'Send Email from ' . $userInfo['full_name'] .
                    (empty($userInfo['emailAddress']) ? '' : ' (' . $userInfo['emailAddress'] . ')') .
                    (empty($clientInfo['full_name']) ? '' : ' to ' . $clientInfo['full_name']) .
                    (empty($clientInfo['emailAddress']) ? '' : ' (' . $clientInfo['emailAddress'] . ')'),
                'from'                => $userInfo['emailAddress'],
                'to'                  => empty($clientInfo['emailAddress']) ? '' : $clientInfo['emailAddress'],
                'default_template_id' => $defaultTemplateId,
                'templates'           => $arrTemplates
            );
        } catch (Exception $e) {
            $arrResult = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel($arrResult);
    }

    public function viewPdfAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);

        $file      = $this->_encryption->decode($this->findParam('file'));
        $checkType = $this->findParam('check_type');
        $checkId   = (int)$this->findParam('check_id');

        if (in_array($checkType, array('member', 'prospect', 'form'))) {
            switch ($checkType) {
                case 'member':
                    if (!$this->_members->hasCurrentMemberAccessToMember($checkId)) {
                        $view->setVariable('content', 'Insufficient access rights.');
                        $view->setTemplate('layout/plain');

                        return $view;
                    }
                    break;
                case 'prospect':
                    if (!$this->_companyProspects->allowAccessToProspect($checkId)) {
                        $view->setVariable('content', 'Insufficient access rights.');
                        $view->setTemplate('layout/plain');

                        return $view;
                    }
                    break;
                case 'form':
                    $assignedFormInfo = $this->_forms->getFormAssigned()->getAssignedFormInfo($checkId);
                    if (!$assignedFormInfo) {
                        $view->setVariable('content', 'There is no form with this assigned id.');
                        $view->setTemplate('layout/plain');

                        return $view;
                    }

                    if (empty($strMessage)) {
                        $memberId = $assignedFormInfo['client_member_id'];
                        if (!$this->_clients->isAlowedClient($memberId)) {
                            $view->setVariable('content', 'Insufficient access rights.');
                            $view->setTemplate('layout/plain');

                            return $view;
                        }
                    }
                    break;
            }
        }

        $attachCheckId = 0;

        if (preg_match('/(.*)#(\d+)/', $file, $regs)) {
            $attachCheckId = $regs[2];
        }

        if (!empty($attachCheckId) && $attachCheckId == $checkId) {
            $view->setVariable('file', $file);
            $view->setVariable('embedUrl', $this->layout()->getVariable('baseUrl') . '/templates/index/show-pdf?file=' . $this->_encryption->encode($file) . '&check_id=' . $checkId . '&check_type=' . $checkType);
        } else {
            $view->setVariable('content', 'Insufficient access rights.');
            $view->setTemplate('layout/plain');
        }

        return $view;
    }

    public function showPdfAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        $file      = $this->_encryption->decode($this->findParam('file'));
        $checkType = $this->findParam('check_type');
        $checkId   = (int)$this->findParam('check_id');

        if (in_array($checkType, array('member', 'prospect', 'form'))) {
            switch ($checkType) {
                case 'member':
                    if (!$this->_members->hasCurrentMemberAccessToMember($checkId)) {
                        $view->setVariable('content', 'Insufficient access rights.');

                        return $view;
                    }
                    break;
                case 'prospect':
                    if (!$this->_companyProspects->allowAccessToProspect($checkId)) {
                        $view->setVariable('content', 'Insufficient access rights.');

                        return $view;
                    }
                    break;
                case 'form':
                    $assignedFormInfo = $this->_forms->getFormAssigned()->getAssignedFormInfo($checkId);
                    if (!$assignedFormInfo) {
                        $view->setVariable('content', 'There is no form with this assigned id.');

                        return $view;
                    }

                    if (empty($strMessage)) {
                        $memberId = $assignedFormInfo['client_member_id'];
                        if (!$this->_clients->isAlowedClient($memberId)) {
                            $view->setVariable('content', 'Insufficient access rights.');

                            return $view;
                        }
                    }
                    break;
            }
        }

        $attachCheckId = 0;
        $fileName      = '';
        // Filename is in such format: filename#client_id
        if (preg_match('/(.*)#(\d+)/', $file, $regs)) {
            $fileName      = $regs[1];
            $attachCheckId = $regs[2];
        }

        if (!empty($attachCheckId) && $attachCheckId == $checkId && !empty($fileName)) {
            $config   = $this->_config['directory'];
            $filePath = $config['pdf_temp'] . DIRECTORY_SEPARATOR . $fileName;
            return $this->downloadFile($filePath, $fileName, 'application/pdf', true, false);
        }

        $view->setVariable('content', 'Insufficient access rights.');
        return $view;
    }

    public function showEmlAsPdfAction()
    {
        $view = new JsonModel();

        try {
            $filter = new StripTags();

            $memberId   = (int)$this->findParam('member_id');
            $templateId = (int)$this->findParam('template_id');
            $option     = $filter->filter($this->findParam('option'));
            $title      = $filter->filter(trim(Json::decode(stripslashes($this->findParam('title', '')), Json::TYPE_ARRAY)));

            if (!$this->_templates->getAccessRightsToTemplate($templateId)) {
                $strError = $this->_tr->translate('Insufficient access rights to the selected template.');
            }

            if (empty($strError) && !$this->_templates->createEmlAsPdf($memberId, $templateId, $option, $title)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => empty($strError)));
    }

    public function setDefaultAction()
    {
        $view = new JsonModel();

        $strError = '';
        try {
            $arrOldTemplateIds = Json::decode($this->findParam('arrOldTemplateIds'), Json::TYPE_ARRAY);
            $newTemplateId     = (int)Json::decode($this->findParam('newTemplateId'), Json::TYPE_ARRAY);

            // Check access to the new selected template
            if (!$this->_templates->getAccessRightsToTemplate($newTemplateId)) {
                $strError = $this->_tr->translate('Insufficient access rights to the selected template.');
            }

            // Check access to the previously selected template(s)
            if (empty($strError) && is_array($arrOldTemplateIds) && count($arrOldTemplateIds)) {
                foreach ($arrOldTemplateIds as $oldTemplateId) {
                    if (!$this->_templates->getAccessRightsToTemplate($oldTemplateId)) {
                        $strError = $this->_tr->translate('Incorrectly selected previous default template.');
                        break;
                    }
                }
            }

            // Try to set a new default template
            if (empty($strError) && !$this->_templates->setTemplateAsDefault($arrOldTemplateIds, $newTemplateId)) {
                $strError = $this->_tr->translate('Internal error.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return $view->setVariables($arrResult);
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
            $templateId = (int)$this->params()->fromPost('template_id');
            $filesCount = (int)$this->params()->fromPost('files');
            $act        = $filter->filter($this->params()->fromPost('act'));

            $accessRights = $this->_templates->getAccessRightsToTemplate($templateId);

            if ($accessRights != 'edit' && $act != 'add') {
                // This user cannot edit template
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                //get files info and size
                for ($i = 0; $i < $filesCount; $i++) {
                    $id = 'template-attachment-' . $i;
                    if (!empty($_FILES[$id]['name']) && !empty($_FILES[$id]['tmp_name'])) {
                        $arrFiles[$i] = $_FILES[$id];
                    }
                }

                // When drag and drop method was used - receive data in other format
                if (empty($arrFiles) && isset($_FILES['template-attachment']) && isset($_FILES['template-attachment']['tmp_name'])) {
                    if (is_array($_FILES['template-attachment']['tmp_name'])) {
                        for ($i = 0; $i < $filesCount; $i++) {
                            if (isset($_FILES['template-attachment']['tmp_name'][$i]) && !empty($_FILES['template-attachment']['tmp_name'][$i])) {
                                $arrFiles[$i] = array(
                                    'name'     => $_FILES['template-attachment']['name'][$i],
                                    'type'     => $_FILES['template-attachment']['type'][$i],
                                    'tmp_name' => $_FILES['template-attachment']['tmp_name'][$i],
                                    'error'    => $_FILES['template-attachment']['error'][$i],
                                    'size'     => $_FILES['template-attachment']['size'][$i],
                                );
                            }
                        }
                    } else {
                        $arrFiles[$i] = $_FILES['template-attachment'];
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
                    $config     = $this->_config['directory'];
                    $targetPath = $config['tmp'] . '/uploads/';

                    foreach ($arrFiles as $key => $file) {
                        $checkId = !empty($templateId) ? $templateId : $this->_auth->getCurrentUserId();

                        $tmpName = md5(time() . rand(0, 99999));
                        $tmpPath = str_replace('//', '/', $targetPath) . $tmpName;
                        $tmpPath = $this->_files->generateFileName($tmpPath, true);

                        $arrFiles[$key]['tmp_name']  = $this->_encryption->encode($tmpName . '#' . $checkId);
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

        return new JsonModel(
            array(
                'success' => empty($strError),
                'error'   => $strError,
                'files'   => $arrFiles
            )
        );
    }

    public function downloadAttachAction()
    {
        set_time_limit(5 * 60); // 5 minutes
        session_write_close();
        ini_set('memory_limit', '512M');

        $strError = '';

        try {
            $filter     = new StripTags();
            $attachId   = $this->params()->fromPost('attach_id');
            $type       = $filter->filter($this->params()->fromPost('type', ''));
            $fileName   = $filter->filter($this->params()->fromPost('name'));
            $templateId = (int)$this->params()->fromPost('template_id', 0);

            if (!empty($templateId) && !$this->_templates->getAccessRightsToTemplate($templateId)) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            if (empty($strError)) {
                switch ($type) {
                    case 'uploaded':
                        $tmpFileName = $this->_encryption->decode($attachId);

                        $checkId = 0;
                        // File path is in such format: path/to/file#check_id
                        if (preg_match('/(.*)#(\d+)/', $tmpFileName, $regs)) {
                            $tmpFileName = $regs[1];
                            $checkId     = $regs[2];
                        }

                        $booHasAccess = false;
                        if (empty($templateId)) {
                            $booHasAccess = $this->_members->hasCurrentMemberAccessToMember($checkId);
                        } elseif ($templateId == $checkId) {
                            $booHasAccess = true;
                        }

                        if ($booHasAccess) {
                            return $this->downloadFile(
                                $this->_config['directory']['tmp'] . '/uploads/' . $tmpFileName,
                                $fileName,
                                'application/force-download',
                                true
                            );
                        } else {
                            $strError = $this->_tr->translate('Insufficient access rights.');
                        }
                        break;

                    case 'template_file_attachment':
                        $strError = $this->_tr->translate('File not found.');

                        if (!empty($templateId)) {
                            $path = $this->_encryption->decode($attachId);

                            $booLocal   = $this->_auth->isCurrentUserCompanyStorageLocal();
                            $folderPath = $this->_files->getCompanyTemplateAttachmentsPath($this->_auth->getCurrentUserCompanyId(), $booLocal) . '/' . $templateId;

                            if (!empty($path)) {
                                if ($booLocal) {
                                    $filePath = $folderPath . '/' . $this->_files::extractFileName($path);
                                    if ($filePath == $path) {
                                        return $this->downloadFile(
                                            $path,
                                            $fileName,
                                            'application/force-download',
                                            true
                                        );
                                    }
                                } else {
                                    $filePath = $folderPath . '/' . $this->_files->getCloud()->getFileNameByPath($path);

                                    if ($filePath == $path) {
                                        $url = $this->_files->getCloud()->getFile(
                                            $path,
                                            $fileName
                                        );

                                        if ($url) {
                                            return $this->redirect()->toUrl($url);
                                        } else {
                                            return $this->fileNotFound();
                                        }
                                    }
                                }
                            }
                        }
                        break;
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $this->getResponse()->setStatusCode(500);
        }

        $view = new ViewModel(
            [
                'content' => $strError
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        return $view;
    }
}