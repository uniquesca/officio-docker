<?php

namespace Superadmin\Controller;

use Exception;
use Files\Service\Files;
use Forms\Service\Forms;
use Forms\Service\Pdf;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Filter\File\Rename;
use Laminas\Filter\FilterChain;
use Laminas\Filter\StripTags;
use Laminas\InputFilter\FileInput;
use Officio\Common\Json;
use Laminas\Validator\File\Extension;
use Laminas\Validator\File\FilesSize;
use Laminas\Validator\ValidatorChain;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Common\Service\Settings;

/**
 * Forms Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class FormsController extends BaseController
{
    /** @var StripTags */
    private $_filter;

    /** @var Files */
    protected $_files;

    /** @var Forms */
    protected $_forms;

    /** @var Pdf */
    protected $_pdf;

    public function initAdditionalServices(array $services)
    {
        $this->_filter = new StripTags();
        $this->_files = $services[Files::class];
        $this->_forms = $services[Forms::class];
        $this->_pdf = $services[Pdf::class];
    }

    /**
     * The default action - show the home page
     */
    public function indexAction()
    {
        $view = new ViewModel();

        $this->layout()->setVariable('title', $this->_tr->translate("Manage Forms"));
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($this->_tr->translate("Manage Forms"));
        $view->setVariable('booShowLandingTab', $this->_acl->isAllowed('manage-forms-view-landing-pages'));

        return $view;
    }

    public function deleteAction()
    {
        $view = new JsonModel();
        $errMsg = '';

        $arrFormIds = Settings::filterParamsArray(Json::decode($this->params()->fromPost('arr_form_id', '[]'), Json::TYPE_ARRAY), $this->_filter);
        // Check if this user has access to these forms
        if (empty($errMsg) && (!is_array($arrFormIds) || count($arrFormIds) == 0)) {
            $errMsg = $this->_tr->translate('Incorrectly selected forms');
        }

        if (empty($errMsg)) {
            // Check if any form is used any where
            $booUsed = $this->_forms->getFormVersion()->isFormUsed($arrFormIds);

            if ($booUsed) {
                // The form(s) is used - show error
                if (count($arrFormIds) > 1) {
                    $errMsg = $this->_tr->translate('At least one of the selected form templates is used by cases. You cannot delete these form templates.');
                } else {
                    $errMsg = $this->_tr->translate('The selected form template is used by cases.<br/>You cannot delete this form template.');
                }
            } else {
                try {
                    $this->_forms->getFormVersion()->deleteVersionForms($arrFormIds);
                } catch (Exception $e) {
                    $errMsg = $this->_tr->translate('Selected form(s) cannot be deleted. Please contact to web site support.');
                    $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
                }
            }
        }

        // Return json result
        $booSuccess = empty($errMsg);
        $arrResult  = array('success' => $booSuccess, 'message' => $errMsg);
        return $view->setVariables($arrResult);
    }

    private function _badFieldsMessage($arrBadFields)
    {
        return empty($arrBadFields)
            ? ''
            : $this->_tr->translate('PDF form was successfully saved.<br><br>But such fields with incorrect names were found:<br><br>') .
            implode('<br>', $arrBadFields);
    }

    public function manageAction()
    {
        set_time_limit(5 * 60); // 5 minutes
        ini_set('memory_limit', '512M');

        $errMsg     = '';
        $booSuccess = false;

        try {
            // Check received fields
            $action = Json::decode($this->params()->fromPost('doaction'), Json::TYPE_ARRAY);
            if (!in_array($action, array('add', 'edit', 'new-version'))) {
                $errMsg = $this->_tr->translate('Incorrect action');
            }

            $versionId = Json::decode($this->params()->fromPost('version_id'), Json::TYPE_ARRAY);
            if (empty($errMsg) && !is_numeric($versionId)) {
                $errMsg = $this->_tr->translate('Incorrectly selected form');
            }

            $fileName = $this->_filter->filter(Json::decode($this->params()->fromPost('file_name'), Json::TYPE_ARRAY));
            if (empty($errMsg) && empty($fileName)) {
                $errMsg = $this->_tr->translate('File name can not be empty');
            }

            $folderId = Json::decode($this->params()->fromPost('folder_id'), Json::TYPE_ARRAY);
            if (empty($errMsg) && ($action == 'add') && (empty($folderId) || !is_numeric($folderId))) {
                $errMsg = $this->_tr->translate('Incorrectly selected folder');
            }

            // Load pdf id by version
            $formVersionInfo = array();
            if (empty($errMsg) && !empty($versionId) && $action != 'add') {
                $formVersionInfo = $this->_forms->getFormVersion()->getFormVersionInfo($versionId);
            }

            $convertToHtml = $this->params()->fromPost('convert-to-html');
            $convertToXod  = $this->params()->fromPost('convert-to-xod');

            $fileNote1 = $this->_filter->filter(Json::decode($this->params()->fromPost('note1'), Json::TYPE_ARRAY));
            $fileNote2 = $this->_filter->filter(Json::decode($this->params()->fromPost('note2'), Json::TYPE_ARRAY));

            $formType = Json::decode($this->params()->fromPost('form_type'), Json::TYPE_ARRAY);
            if (!in_array($formType, array('', 'bar'))) {
                $formType = '';
            }

            $fileDate    = date("Y-m-d H-i-s");
            $fileNewName = $fileDate . '.pdf';
            $pdfPath     = $this->_config['directory']['pdfpath_physical'] . '/';


            // Get info about the file
            $fileInput = new FileInput('file');
            $fileInput->setBreakOnFailure(false);
            $pdfFieldName = 'superadmin-form-field-file';
            if (empty($errMsg) && $action != 'edit' && $fileInput->isEmptyFile($_FILES[$pdfFieldName])) {
                $errMsg = $this->_tr->translate('Please select a pdf file');
            }

            $booUpdateFile = false;
            $fileSize      = "0kb";
            if (empty($errMsg) && !$fileInput->isEmptyFile($_FILES[$pdfFieldName])) {
                // Generate file name and
                // Save received pdf form in specific location
                $fileInput->getValidatorChain()
                    ->attach(new FilesSize(['min' => 1]))
                    // TODO The following line is temporarily commented out due to a bug: https://github.com/laminas/laminas-validator/issues/24
                    // ->attach(new Count(1))
                    ->attach(new Extension('pdf'));

                $fileInput->getFilterChain()->attach(new Rename($pdfPath . $fileNewName));
                $fileInput->setValue($_FILES[$pdfFieldName]);
                try {
                    if (!$fileInput->isValid()) {
                        $errMsg = implode('<br/>', $fileInput->getMessages());
                    } else {
                        $booUpdateFile = true;
                        $fileInfo = $fileInput->getValue();
                        $fileSize = (int)($fileInfo['size'] / 1024) . "kb";
                    }

                    @chmod($pdfPath . $fileNewName, 0660);
                } catch (Exception $e) {
                    $errMsg = $e->getMessage();
                }
            }

            if (empty($errMsg)) {
                $oldFileName = '';
                if ($action == 'add') {
                    // Save info about the file in db
                    $pdfFormId = $this->_db2->insert('form_upload', ['folder_id' => $folderId]);
                } else {
                    $pdfFormId   = $formVersionInfo['form_id'];
                    $oldFileName = $formVersionInfo['file_path'];
                }

                $data = array(
                    'form_id'       => $pdfFormId,
                    'form_type'     => $formType,
                    'version_date'  => date("Y-m-d", strtotime($this->_filter->filter(Json::decode($this->params()->fromPost('version_date'), Json::TYPE_ARRAY)))),
                    'uploaded_date' => date('c'),
                    'uploaded_by'   => $this->_auth->getCurrentUserId(),
                    'file_path'     => $fileNewName,
                    'file_name'     => $fileName,
                    'size'          => $fileSize,
                    'note1'         => $fileNote1,
                    'note2'         => $fileNote2
                );

                $formIdToConvert = null;
                $pdfFileName     = '';
                switch ($action) {
                    case 'add':
                        $formIdToConvert = $this->_db2->insert('form_version', $data);
                        $pdfFileName     = $fileNewName;

                        if ($formType != 'bar') {
                            // Save sync fields in db
                            $arrResult  = $this->_pdf->manageSynFields($pdfPath . $fileNewName);
                            $booSuccess = empty($arrResult['strError']);
                            $errMsg     = $booSuccess ? $this->_badFieldsMessage($arrResult['arrBadFields']) : $arrResult['strError'];
                        } else {
                            // Don't parse barcoded pdf
                            $booSuccess = true;
                        }
                        break;

                    case 'edit':
                        // Update only required fields
                        if (!$booUpdateFile) {
                            unset($data['file_path'], $data['size'], $data['uploaded_date'], $data['uploaded_by']);
                            $booSuccess = true;

                            $pdfFileName = $formVersionInfo['file_path'];
                        } else {
                            $pdfFileName = $fileNewName;

                            if ($formType != 'bar') {
                                // Save sync fields in db
                                $arrResult  = $this->_pdf->manageSynFields($pdfPath . $fileNewName);
                                $booSuccess = empty($arrResult['strError']);
                                $errMsg     = $booSuccess ? $this->_badFieldsMessage($arrResult['arrBadFields']) : $arrResult['strError'];
                            } else {
                                // Don't parse barcoded pdf
                                $booSuccess = true;
                            }

                            // Delete previous file
                            if (!empty($oldFileName) && file_exists($pdfPath . $oldFileName)) {
                                unlink($pdfPath . $oldFileName);
                            }

                            // Rename previously uploaded xod too, must have the same name as pdf
                            if (!empty($oldFileName) && !empty($pdfFileName)) {
                                $xodOldPath = $this->_files->getConvertedXodFormPath($oldFileName);
                                $xodNewPath = $this->_files->getConvertedXodFormPath($pdfFileName);

                                if (file_exists($xodOldPath)) {
                                    rename($xodOldPath, $xodNewPath);
                                }
                            }
                        }

                        $formIdToConvert = $versionId;

                        unset($data['form_id']);
                        $this->_db2->update('form_version', $data, ['form_version_id' => $versionId]);

                        $this->updateTemplates($pdfFormId, $formVersionInfo['file_name'], $data['file_name']);
                        break;

                    case 'new-version':
                        $pdfFileName = $fileNewName;

                        // Get old file name
                        $arrOldFormInfo  = $this->_forms->getFormVersion()->getLatestFormInfo($pdfFormId);
                        $formIdToConvert = $this->_db2->insert('form_version', $data);

                        if ($formType != 'bar') {
                            // Save sync fields in db
                            $arrResult  = $this->_pdf->manageSynFields($pdfPath . $fileNewName);
                            $booSuccess = empty($arrResult['strError']);
                            $errMsg     = $booSuccess ? $this->_badFieldsMessage($arrResult['arrBadFields']) : $arrResult['strError'];
                        } else {
                            // Don't parse barcoded pdf
                            $booSuccess = true;
                        }

                        $this->updateTemplates($pdfFormId, $arrOldFormInfo['file_name'], $fileName);
                        break;

                    default:

                        break;
                }

                // Use uploaded XOD file or convert from PDF if needed
                if ($booSuccess) {
                    $xodFieldName = 'superadmin-xod-form-field-file';
                    $fileInput->setFilterChain(new FilterChain());
                    $fileInput->setValidatorChain(new ValidatorChain());
                    $fileInput->resetValue();
                    if (!$fileInput->isEmptyFile($_FILES[$xodFieldName]) && !empty($pdfFileName)) {
                        $xodPath = $this->_files->getConvertedXodFormPath($pdfFileName);

                        if (empty($xodPath)) {
                            $strError = 'Path to XOD is empty.';
                            $errMsg   = empty($errMsg) ? $strError : $errMsg . '<br/>' . $strError;
                        }

                        if (empty($errMsg)) {
                            $fileInput->getValidatorChain()
                                ->attach(new FilesSize(['min' => 1]))
                                // TODO The following line is temporarily commented out due to a bug: https://github.com/laminas/laminas-validator/issues/24
                                // ->attach(new Count(1))
                                ->attach(new Extension('xod'));
                            $fileInput->getFilterChain()->attach(new Rename($xodPath));
                            $fileInput->setValue($_FILES[$xodFieldName]);
                            // Delete previous xod file
                            $this->_files->deleteFile($xodPath);

                            try {
                                if (!$fileInput->isValid() || !$fileInput->getValue()) {
                                    $errMsg = implode('<br/>', $fileInput->getMessages());
                                }
                            } catch (Exception $e) {
                                $errMsg = $e->getMessage();
                            }
                        }
                    } elseif (!empty($convertToXod) && !empty($formIdToConvert)) {
                        list($strError,) = $this->_forms->pdf2xodConvert($formIdToConvert);
                        if (!empty($strError)) {
                            $strError = 'Error during converting to XOD: ' . $strError;
                            $errMsg   = empty($errMsg) ? $strError : $errMsg . '<br/>' . $strError;
                        }
                    }
                }

                // Convert PDF to HTML if needed
                if ($booSuccess && !empty($convertToHtml) && !empty($formIdToConvert)) {
                    list($strError,) = $this->_forms->pdf2htmlConvert($formIdToConvert);
                    if (!empty($strError)) {
                        $strError = 'Error during converting to HTML: ' . $strError;
                        $errMsg   = empty($errMsg) ? $strError : $errMsg . '<br/>' . $strError;
                    }
                }
            }
        } catch (Exception $e) {
            $errMsg = $this->_tr->translate('Internal Error. Please contact to web site support.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        // Return json result
        $arrResult = array(
            'success' => $booSuccess,
            'message' => $errMsg
        );

        $view = new ViewModel();
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);
        $view->setVariable('content', Json::encode($arrResult));
        return $view;
    }

    public function checkFormsAction()
    {
        $view = new JsonModel();
        $arrResult = array();
        $totalCount = 0;

        try {
            list($arrResult, $totalCount) = $this->_forms->getFormVersion()->checkFormPdfFilesExist();
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return $view->setVariables(array('rows' => $arrResult, 'totalCount' => $totalCount));
    }

    public function loadInfoAction()
    {
        $view = new JsonModel();
        $errMsg = '';

        // If user can access to this method - can update any form
        $pdfVersionId = (int)Json::decode($this->findParam('pdf_version_id'), Json::TYPE_ARRAY);
        $booLatest    = Json::decode($this->findParam('latest'), Json::TYPE_ARRAY);

        if (!$booLatest) {
            $arrVersionInfo = $this->_forms->getFormVersion()->getFormVersionInfo($pdfVersionId);
        } else {
            $arrVersionInfo = $this->_forms->getFormVersion()->getLatestFormVersionInfo($pdfVersionId);
        }

        // Return json result
        $booSuccess = empty($errMsg);
        $arrResult  = array('success' => $booSuccess, 'message' => $errMsg, 'arrResult' => $arrVersionInfo);
        return $view->setVariables($arrResult);
    }

    public function folderAddAction()
    {
        $name      = $this->_filter->filter(Json::decode($this->findParam('name'), Json::TYPE_ARRAY));
        $parent_id = $this->_filter->filter($this->findParam('parent_id'));


        $arrToInsert = array(
            'parent_id'   => (int)$parent_id,
            'folder_name' => $name
        );

        // Create record in DB
        $result = $this->_db2->insert('form_folder', $arrToInsert);

        return new JsonModel(array('success' => $result));
    }

    public function folderRenameAction()
    {
        $name      = $this->_filter->filter(Json::decode($this->findParam('name'), Json::TYPE_ARRAY));
        $folder_id = $this->findParam('folder_id');

        // Update info in db
        $count = $this->_db2->update('form_folder', ['folder_name' => $name], ['folder_id' => $folder_id]);

        return new JsonModel(array('success' => $count));
    }

    public function folderDeleteAction()
    {
        $folder_id  = $this->findParam('folder_id');
        $booSuccess = false;

        //we can't delete non-empty folders
        $select = (new Select())
            ->from('form_upload')
            ->columns(['count' => new Expression('COUNT(form_id)')])
            ->where(['folder_id' => $folder_id]);

        $files  = $this->_db2->fetchOne($select);
        if (empty($files) && !empty($folder_id)) {
            $booSuccess = $this->_db2->delete('form_folder', ['folder_id' => $folder_id]);
        }

        return new JsonModel(array('success' => $booSuccess));
    }

    public function landingAddAction()
    {
        $view = new JsonModel();
        try {
            $name      = $this->_filter->filter(Json::decode($this->findParam('name'), Json::TYPE_ARRAY));
            $parent_id = $this->_filter->filter($this->findParam('parent_id'));

            $arrToInsert = array(
                'parent_id' => (int)$parent_id,
                'folder_name'     => $name
            );

            // Create record in DB
            $result = $this->_db2->insert('form_landing', $arrToInsert);
        } catch (Exception $e) {
            $result = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => $result));
    }

    public function landingRenameAction()
    {
        $view = new JsonModel();
        try {
            $name     = $this->_filter->filter(Json::decode($this->findParam('name'), Json::TYPE_ARRAY));
            $folderId = $this->findParam('folder_id');

            // Update info in db
            $count = $this->_db2->update('form_landing', ['folder_name' => $name], ['folder_id' => $folderId]);
        } catch (Exception $e) {
            $count = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => $count));
    }

    public function landingDeleteAction()
    {
        $strError = '';
        try {
            $folderId = $this->findParam('id');

            //we can't delete non-empty folders
            $files = $this->_forms->getFormTemplates()->getFormsCountInFolder($folderId);
            if ((empty($files) || $files == 0) && $folderId != 0) {
                $deleteResult = $this->_db2->delete('form_landing', ['folder_id' => $folderId]);
                if (!$deleteResult) {
                    $strError = $this->_tr->translate('Internal error.');
                }
            } else {
                $strError = $this->_tr->translate('Folder must be empty. Please remove all landing pages and try again.');
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $this->getResponse()->setStatusCode(500);
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => $strError
        );

        return new JsonModel($arrResult);
    }

    public function listAction()
    {
        $view = new JsonModel();
        try {
            $version   = $this->_filter->filter(Json::decode(stripslashes($this->params()->fromPost('version', '')), Json::TYPE_ARRAY));
            $searchStr = $this->_filter->filter(Json::decode(stripslashes($this->params()->fromPost('search_form', '')), Json::TYPE_ARRAY));

            $booWithFiles = Json::decode($this->params()->fromPost('with_files'), Json::TYPE_ARRAY);
            if ($booWithFiles === null) {
                $booWithFiles = true;
            }

            if ($version == 'all') {
                $booLoadAll = true;
            } else {
                $booLoadAll = false;
            }

            if ($booWithFiles) {
                $arrFolders = $this->_forms->getFormUpload()->getFormsAndFolders($booLoadAll, $searchStr);
            } else {
                $arrFolders = $this->_forms->getFormUpload()->getFoldersOnly();
            }
        } catch (Exception $e) {
            $arrFolders = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Show json result
        return $view->setVariables($arrFolders);
    }

    private function getLandingFilesAndFolders($parentId = 0)
    {
        $arrFolders = array();
        $i          = 0;

        $folders = $this->_forms->getFormLanding()->getLandingByParentId($parentId);
        foreach ($folders as $folder) {
            $arrFolders[$i] = array(
                'text'      => $folder['folder_name'],
                'folder_id' => $folder['folder_id'],
                'cls'       => 'folder-icon',
                'allowDrag' => false,
                'type'      => 'folder',
                'children'  => $this->getLandingFilesAndFolders($folder['folder_id'])
            );

            $files = $this->_forms->getFormTemplates()->getFormsInFolder($folder['folder_id']);
            foreach ($files as $file) {
                $arrFolders[$i]['children'][] = array(
                    'text'    => $file['name'],
                    'file_id' => $file['template_id'],
                    'cls'     => 'landing-file',
                    'leaf'    => true,
                    'type'    => 'file'
                );
            }

            ++$i;
        }

        return $arrFolders;
    }

    public function getLandingViewAction()
    {
        $view = new JsonModel();
        $arrFolders = $this->getLandingFilesAndFolders();
        return $view->setVariables($arrFolders);
    }

    public function templateSaveAction()
    {
        $view = new JsonModel();
        try {
            $action      = $this->_filter->filter($this->findParam('act'));
            $template_id = $this->_filter->filter($this->findParam('template_id'));
            $folder_id   = $this->_filter->filter($this->findParam('folder_id'));
            $body        = $this->_settings->getHTMLPurifier(false)->purify(Json::decode($this->findParam('body'), Json::TYPE_ARRAY));
            $name        = $this->_filter->filter(Json::decode($this->findParam('name'), Json::TYPE_ARRAY));

            //processed templates
            $processed_full = $this->parseAssignedForm($body);
            $processed_last = $this->parseAssignedForm($body, false);

            if ($action == 'add') {
                // Save template
                $template_id = $this->_forms->getFormTemplates()->createTemplate($folder_id, $name, $body);

                // Save processed full template
                $this->_db2->insert(
                    'form_processed',
                    [
                        'template_id' => (int)$template_id,
                        'version'     => 'FULL',
                        'content'     => $processed_full
                    ]
                );

                // Save processed last template
                $this->_db2->insert(
                    'form_processed',
                    [
                        'template_id' => (int)$template_id,
                        'version'     => 'LAST',
                        'content'     => $processed_last
                    ]
                );
            } else //edit
            {
                // Update template
                $this->_db2->update(
                    'form_templates',
                    [
                        'name' => $name,
                        'body' => $body
                    ],
                    ['template_id' => $template_id]
                );

                // Update processed full template
                $this->_db2->update(
                    'form_processed',
                    ['content' => $processed_full],
                    [
                        'version'     => 'FULL',
                        'template_id' => $template_id
                    ]
                );

                // Update processed last template
                $this->_db2->update(
                    'form_processed',
                    ['content' => $processed_last],
                    [
                        'version'     => 'LAST',
                        'template_id' => $template_id
                    ]
                );
            }
            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => $booSuccess));
    }

    public function getTemplateInfoAction()
    {
        $view = new JsonModel();
        try {
            $templateId  = (int)$this->findParam('id');
            $arrTemplate = $this->_forms->getFormTemplates()->getTemplateById($templateId);
        } catch (Exception $e) {
            $arrTemplate = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables($arrTemplate);
    }

    public function templateDeleteAction()
    {
        $view = new JsonModel();
        try {
            $templateId = (int)$this->findParam('id');

            $this->_db2->delete('form_templates', ['template_id' => $templateId]);
            $this->_db2->delete('form_processed', ['template_id' => $templateId]);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => $booSuccess));
    }

    public function ddAction()
    {
        $view = new JsonModel();
        try {
            $template_id = (int)$this->findParam('template_id');
            $folder_id   = (int)$this->findParam('folder_id');

            $count = $this->_db2->update(
                'form_templates',
                ['folder_id' => $folder_id],
                ['template_id' => $template_id]
            );
        } catch (Exception $e) {
            $count = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => $count));
    }

    private function parseAssignedForm($content, $all_versions = true)
    {
        //get forms
        $select = (new Select())
            ->from(array('u' => 'form_upload'))
            ->columns(['folder_id'])
            ->join(array('v' => 'form_version'), 'v.form_id = u.form_id')
            ->order(array('u.form_id ASC', 'v.version_date DESC', 'v.uploaded_date DESC'));

        $formsArr = $this->_db2->fetchAll($select);

        //group forms by form_id
        $forms = array();
        foreach ($formsArr as $form) {
            $forms[$form['form_id']][] = $form;
        }

        //replace < % text % >
        foreach ($forms as $form) {
            $text = '';
            $i    = 1;

            foreach ($form as $version) {
                $text .= '<span><input type="checkbox" id="pform' . $version['form_version_id'] . '" class="pform" style="vertical-align:middle;" />&nbsp;' . $version['file_name'] . '</span>';

                //show only latest version
                if (!$all_versions) {
                    break;
                }

                //add break and show date
                if (count($form) > $i) {
                    $text .= '&nbsp;(' . $this->_settings->formatDate($version['version_date']) . ')<br />';
                }

                ++$i;
            }

            //replace
            $unique_id = $this->_forms->getFormUpload()->getUniqueFormId($form[0]['form_id'], $form[0]['file_name']);
            $content   = str_replace($unique_id, $text, $content);
        }

        //replace hyperlinks
        return preg_replace('/<a[^>]*?href=[\'"](.*?)[\'"][^>]*?>(.*?)<\/a>/si', '<a href="$1" target="_blank" class="blulinkun" >$2</a>', $content);
    }

    public function getPreviewAction()
    {
        $view = new ViewModel(
            [
                'content' => null
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        try {
            $templateId = (int)$this->findParam('id');
            $version    = $this->_filter->filter($this->findParam('version'));

            $content = $this->_forms->getFormProcessed()->getContent($templateId, $version);
        } catch (Exception $e) {
            $content = '';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $view->setVariable('content', $content);
        return $view;
    }

    public function updateLandingAction()
    {
        $view = new ViewModel();
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        $templates = $this->_forms->getFormTemplates()->fetchAllRecords();
        foreach ($templates as $template) {
            //process templates again
            $processed_full = $this->parseAssignedForm($template['body']);
            $processed_last = $this->parseAssignedForm($template['body'], false);

            //update processed full template
            $this->_db2->update(
                'form_processed',
                ['content' => $processed_full],
                [
                    'version'     => 'FULL',
                    'template_id' => $template['template_id']
                ]
            );

            //update processed last template
            $this->_db2->update(
                'form_processed',
                ['content' => $processed_last],
                [
                    'version'     => 'LAST',
                    'template_id' => $template['template_id']
                ]
            );
        }

        return $view->setVariable('content', 'Done');
    }

    private function updateTemplates($formId, $oldName, $newName)
    {
        //get old and name unique id
        $old_unique_id = $this->_forms->getFormUpload()->getUniqueFormId($formId, $oldName);
        $new_unique_id = $this->_forms->getFormUpload()->getUniqueFormId($formId, $newName);

        //each templates
        $arrTemplates = $this->_forms->getFormTemplates()->getTemplatesByFormId($formId);

        foreach ($arrTemplates as $template) {
            $body = $template['body'];

            //if form name was edited or new version added
            if ($oldName != $newName) {
                $body = str_replace($old_unique_id, $new_unique_id, $template['body']);
                $this->_db2->update('form_templates', ['body' => $body], ['template_id' => $template['template_id']]);
            }

            //process templates again
            $processedFull = $this->parseAssignedForm($body);
            $processedLast = $this->parseAssignedForm($body, false);

            //update processed full template
            $this->_db2->update(
                'form_processed',
                ['content' => $processedFull],
                [
                    'version'     => 'FULL',
                    'template_id' => $template['template_id']
                ]
            );

            //update processed last template
            $this->_db2->update(
                'form_processed',
                ['content' => $processedLast],
                [
                    'version'     => 'LAST',
                    'template_id' => $template['template_id']
                ]
            );
        }
    }

    public function pdf2xodAction()
    {
        $view = new JsonModel();
        set_time_limit(5 * 60); // 5 minutes
        ini_set('memory_limit', '512M');

        $strError       = '';
        $textStatus     = '';
        $arrPdfIds      = array();
        $processedCount = 0;
        $totalCount     = 0;

        try {
            // Get and check incoming info
            $arrPdfIds      = Settings::filterParamsArray(Json::decode($this->findParam('arr_ids'), Json::TYPE_ARRAY), $this->_filter);
            $processedCount = (int)Json::decode($this->findParam('processed_count'), Json::TYPE_ARRAY);
            $totalCount     = (int)Json::decode($this->findParam('total_count'), Json::TYPE_ARRAY);
            $strMode        = $this->_filter->filter(Json::decode($this->findParam('mode'), Json::TYPE_ARRAY));

            if (empty($strError) && !in_array($strMode, array('convert_all', 'revert_all', 'convert_selected', 'revert_selected'))) {
                $strError = $this->_tr->translate('Incoming data [mode] is incorrect.');
            }

            if (empty($strError) && in_array($strMode, array('convert_all', 'revert_all')) && is_array($arrPdfIds) && isset($arrPdfIds[0]) && empty($arrPdfIds[0])) {
                $arrPdfIds  = $this->_forms->getFormVersion()->getAllFormVersionsIds();
                $totalCount = count($arrPdfIds);
            }

            if (!is_array($arrPdfIds) || !count($arrPdfIds)) {
                $strError = $this->_tr->translate('Pdf forms were selected incorrectly.');
            }

            if (empty($strError) && (!is_numeric($processedCount) || $processedCount < 0)) {
                $strError = $this->_tr->translate('Incoming data [processed count] is incorrect.');
            }

            if (empty($strError) && (!is_numeric($totalCount) || empty($totalCount))) {
                $strError = $this->_tr->translate('Incoming data [total count] is incorrect.');
            }

            if (empty($strError)) {
                $pdfId = array_shift($arrPdfIds);

                if (in_array($strMode, array('convert_all', 'convert_selected'))) {
                    list($strError, $textStatus) = $this->_forms->pdf2xodConvert($pdfId);
                } else {
                    list($strError, $textStatus) = $this->_forms->pdf2xodRevert($pdfId);
                }
                $processedCount++;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'         => empty($strError),
            'msg'             => $strError,
            'arr_ids'         => $arrPdfIds,
            'processed_count' => $processedCount,
            'total_count'     => $totalCount,
            'text_status'     => $textStatus

        );

        return $view->setVariables($arrResult);
    }

    public function pdf2htmlConvertAction()
    {
        $this->convertOrRevertPDF2HTML(true);
    }

    public function pdf2htmlRevertAction()
    {
        $this->convertOrRevertPDF2HTML(false);
    }

    protected function convertOrRevertPDF2HTML($convert)
    {
        $view = new JsonModel();
        set_time_limit(60 * 60); // 1 hour
        ini_set('memory_limit', '512M');

        $strError   = '';
        $textStatus = '';

        // Get and check incoming info
        $arrPdfIds      = Settings::filterParamsArray(Json::decode($this->findParam('arr_ids'), Json::TYPE_ARRAY), $this->_filter);
        $processedCount = (int)Json::decode($this->findParam('processed_count'), Json::TYPE_ARRAY);
        $totalCount     = (int)Json::decode($this->findParam('total_count'), Json::TYPE_ARRAY);

        if (!is_array($arrPdfIds) || !count($arrPdfIds)) {
            $strError = $this->_tr->translate('Pdf forms were selected incorrectly.');
        }

        if (empty($strError) && (!is_numeric($processedCount) || $processedCount < 0)) {
            $strError = $this->_tr->translate('Incoming data [processed count] is incorrect.');
        }

        if (empty($strError) && (!is_numeric($totalCount) || empty($totalCount))) {
            $strError = $this->_tr->translate('Incoming data [total count] is incorrect.');
        }

        if (empty($strError)) {
            $pdfId = array_shift($arrPdfIds);
            if ($convert) {
                list($strError, $textStatus) = $this->_forms->pdf2htmlConvert($pdfId);
            } else {
                list($strError, $textStatus) = $this->_forms->pdf2htmlRevert($pdfId);
            }
            $processedCount++;
        }

        $arrResult = array(
            'success'         => empty($strError),
            'msg'             => $strError,
            'arr_ids'         => $arrPdfIds,
            'processed_count' => $processedCount,
            'total_count'     => $totalCount,
            'text_status'     => $textStatus
        );

        return $view->setVariables($arrResult);
    }
}
