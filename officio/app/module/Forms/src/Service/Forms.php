<?php

namespace Forms\Service;

use Exception;
use Files\Service\Files;
use Forms\Service\Forms\FormAssigned;
use Forms\Service\Forms\FormFolder;
use Forms\Service\Forms\FormLanding;
use Forms\Service\Forms\FormMap;
use Forms\Service\Forms\FormProcessed;
use Forms\Service\Forms\FormRevision;
use Forms\Service\Forms\FormSynField;
use Forms\Service\Forms\FormTemplates;
use Forms\Service\Forms\FormUpload;
use Forms\Service\Forms\FormVersion;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use Laminas\Http\Client;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Uri\UriFactory;
use Laminas\View\Helper\Layout;
use Laminas\View\HelperPluginManager;
use Officio\Common\SubServiceOwner;
use Officio\PdfTron\Service\PdfTronPython;

/**
 * @author     Uniques Software Corp.
 * @copyright  Uniques
 */
class Forms extends SubServiceOwner
{

    /** @var Files */
    protected $_files;

    /** @var PdfTronPython */
    protected $_pdfTron;

    /** @var FormAssigned */
    protected $_formAssigned;

    /** @var FormVersion */
    protected $_formVersion;

    /** @var FormUpload */
    protected $_formUpload;

    /** @var FormFolder */
    protected $_formFolder;

    /** @var FormLanding */
    protected $_formLanding;

    /** @var FormMap */
    protected $_formMap;

    /** @var FormProcessed */
    protected $_formProcessed;

    /** @var FormRevision */
    protected $_formRevision;

    /** @var FormSynField */
    protected $_formSynField;

    /** @var FormTemplates */
    protected $_formTemplates;

    /** @var HelperPluginManager */
    protected $_viewHelperManager;

    public $intShowFormsPerPage = 25;

    public function initAdditionalServices(array $services)
    {
        $this->_files             = $services[Files::class];
        $this->_viewHelperManager = $services[HelperPluginManager::class];
        $this->_pdfTron           = $services[PdfTronPython::class] ?? null;
    }

    /**
     * @return FormAssigned
     */
    public function getFormAssigned()
    {
        if (is_null($this->_formAssigned)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_formAssigned = $this->_serviceContainer->build(FormAssigned::class, ['parent' => $this]);
            } else {
                $this->_formAssigned = $this->_serviceContainer->get(FormAssigned::class);
                $this->_formAssigned->setParent($this);
            }
        }

        return $this->_formAssigned;
    }

    /**
     * @return FormVersion
     */
    public function getFormVersion()
    {
        if (is_null($this->_formVersion)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_formVersion = $this->_serviceContainer->build(FormVersion::class, ['parent' => $this]);
            } else {
                $this->_formVersion = $this->_serviceContainer->get(FormVersion::class);
                $this->_formVersion->setParent($this);
            }
        }

        return $this->_formVersion;
    }

    /**
     * @return FormUpload
     */
    public function getFormUpload()
    {
        if (is_null($this->_formUpload)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_formUpload = $this->_serviceContainer->build(FormUpload::class, ['parent' => $this]);
            } else {
                $this->_formUpload = $this->_serviceContainer->get(FormUpload::class);
                $this->_formUpload->setParent($this);
            }
        }

        return $this->_formUpload;
    }

    /**
     * @return FormFolder
     */
    public function getFormFolder()
    {
        if (is_null($this->_formFolder)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_formFolder = $this->_serviceContainer->build(FormFolder::class, ['parent' => $this]);
            } else {
                $this->_formFolder = $this->_serviceContainer->get(FormFolder::class);
                $this->_formFolder->setParent($this);
            }
        }

        return $this->_formFolder;
    }

    /**
     * @return FormLanding
     */
    public function getFormLanding()
    {
        if (is_null($this->_formLanding)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_formLanding = $this->_serviceContainer->build(FormLanding::class, ['parent' => $this]);
            } else {
                $this->_formLanding = $this->_serviceContainer->get(FormLanding::class);
                $this->_formLanding->setParent($this);
            }
        }

        return $this->_formLanding;
    }

    /**
     * @return FormMap
     */
    public function getFormMap()
    {
        if (is_null($this->_formMap)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_formMap = $this->_serviceContainer->build(FormMap::class, ['parent' => $this]);
            } else {
                $this->_formMap = $this->_serviceContainer->get(FormMap::class);
                $this->_formMap->setParent($this);
            }
        }

        return $this->_formMap;
    }

    /**
     * @return FormProcessed
     */
    public function getFormProcessed()
    {
        if (is_null($this->_formProcessed)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_formProcessed = $this->_serviceContainer->build(FormProcessed::class, ['parent' => $this]);
            } else {
                $this->_formProcessed = $this->_serviceContainer->get(FormProcessed::class);
                $this->_formProcessed->setParent($this);
            }
        }

        return $this->_formProcessed;
    }

    /**
     * @return FormRevision
     */
    public function getFormRevision()
    {
        if (is_null($this->_formRevision)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_formRevision = $this->_serviceContainer->build(FormRevision::class, ['parent' => $this]);
            } else {
                $this->_formRevision = $this->_serviceContainer->get(FormRevision::class);
                $this->_formRevision->setParent($this);
            }
        }

        return $this->_formRevision;
    }

    /**
     * @return FormSynField
     */
    public function getFormSynField()
    {
        if (is_null($this->_formSynField)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_formSynField = $this->_serviceContainer->build(FormSynField::class, ['parent' => $this]);
            } else {
                $this->_formSynField = $this->_serviceContainer->get(FormSynField::class);
                $this->_formSynField->setParent($this);
            }
        }

        return $this->_formSynField;
    }

    /**
     * @return FormTemplates
     */
    public function getFormTemplates()
    {
        if (is_null($this->_formTemplates)) {
            if ($this->_serviceContainer instanceof ServiceManager) {
                $this->_formTemplates = $this->_serviceContainer->build(FormTemplates::class, ['parent' => $this]);
            } else {
                $this->_formTemplates = $this->_serviceContainer->get(FormTemplates::class);
                $this->_formTemplates->setParent($this);
            }
        }

        return $this->_formTemplates;
    }

    /**
     * Check if user can access default form
     * @param int $defaultFormId
     * @param int $companyId
     * @return bool true on success
     */
    public function hasAccessToDefaultForm($defaultFormId, $companyId)
    {
        $formsCount = 0;
        if (is_numeric($defaultFormId) && !empty($defaultFormId)) {
            $select = (new Select())
                ->from(['f' => 'form_default'])
                ->columns(['forms_count' => new Expression('COUNT(*)')])
                ->where([
                    'f.company_id'      => (int)$companyId,
                    'f.form_default_id' => (int)$defaultFormId
                ]);

            $formsCount = $this->_db2->fetchOne($select);
        }

        return $formsCount > 0;
    }


    /**
     * Load form info
     *
     * @param int $defaultFormId
     * @return array
     */
    public function getDefaultFormInfo($defaultFormId)
    {
        $select = (new Select())
            ->from(['f' => 'form_default'])
            ->where(['f.form_default_id' => (int)$defaultFormId]);

        return $this->_db2->fetchRow($select);
    }


    /**
     * Get default xfdf file path for the current user's company
     *
     * @param int $companyId
     * @return string path to xfdf file
     */
    public function getDefaultXfdfPath($companyId)
    {
        $xfdfFolder = $this->_files->getCompanyXFDFPath($companyId);

        // Generate path to xfdf
        return $xfdfFolder . '/' . 'default.xfdf';
    }


    /**
     * Update default form details
     *
     * @param $formId
     * @param $arrToUpdate
     * @return void
     */
    public function updateDefaultForm($formId, $arrToUpdate)
    {
        $this->_db2->update('form_default', $arrToUpdate, ['form_default_id' => (int)$formId]);
    }


    /**
     * Load default forms list
     *
     * @param $companyId
     * @param $sort
     * @param $dir
     * @param $start
     * @param $limit
     * @return array
     */
    public function getDefaultFormsList($companyId, $sort, $dir, $start, $limit)
    {
        try {
            if (!in_array($dir, array('ASC', 'DESC'))) {
                $dir = 'DESC';
            }

            switch ($sort) {
                case 'default_form_name':
                    $sort = 'v.file_name';
                    break;

                case 'default_form_updated_by':
                    $sort = 'updated_by_name';
                    break;

                case 'default_form_updated_on':
                    $sort = 'f.updated_on';
                    break;

                case 'default_form_id':
                default:
                    $sort = 'f.form_default_id';
                    break;
            }

            if (!is_numeric($start) || $start <= 0) {
                $start = 0;
            }

            if (!is_numeric($limit) || $limit <= 0) {
                $limit = $this->intShowFormsPerPage;
            }

            $select        = (new Select())
                ->from(array('f' => 'form_default'))
                ->join(array('v' => 'form_version'), 'v.form_version_id = f.form_version_id', array('file_name', 'form_type', 'form_version_id'), Select::JOIN_LEFT_OUTER)
                ->join(array('m' => 'members'), 'f.updated_by = m.member_id', array('updated_by_name' => new Expression('CONCAT(m.fName, " ", m.lName)')), Select::JOIN_LEFT_OUTER)
                ->where(['f.company_id' => (int)$companyId])
                ->limit($limit)
                ->offset($start)
                ->order(array($sort . ' ' . $dir));

            $arrFoundForms = $this->_db2->fetchAll($select);
            $totalRecords  = $this->_db2->fetchResultsCount($select);

            $arrForms = array();
            foreach ($arrFoundForms as $arrFormInfo) {
                $formType = $arrFormInfo['form_type'];
                if ($formType != 'bar') {
                    $formType = $this->getFormVersion()->getFormFormat($arrFormInfo);
                }

                $arrForms[] = array(
                    'default_form_id'         => $arrFormInfo['form_default_id'],
                    'default_form_name'       => $arrFormInfo['file_name'],
                    'default_form_type'       => $formType,
                    'default_form_updated_by' => $arrFormInfo['updated_by_name'],
                    'default_form_updated_on' => $arrFormInfo['updated_on'],
                );
            }
        } catch (Exception $e) {
            $arrForms = array();
            $totalRecords = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array(
            'rows' => $arrForms,
            'totalCount' => $totalRecords
        );
    }


    /**
     * Get default xfdf file, if it exists -
     * copy to client's folder
     * @NOTE if file already exists - it will be not overwritten
     *
     * @param int $companyId
     * @param int $memberId
     * @param array $arrNewFormData
     * @return bool true on success
     */
    public function copyDefaultXfdfToClient($companyId, $memberId, $arrNewFormData)
    {
        $booResult = false;

        $defaultXfdfPath = $this->getDefaultXfdfPath($companyId);

        if (file_exists($defaultXfdfPath)) {
            // Generate path to new xfdf file we want copy to
            $fileName = Pdf::getXfdfFileName(
                $arrNewFormData['family_member_type'],
                $arrNewFormData['client_form_id']
            );

            $pathToXfdfDir = $this->_files->getClientXFDFFTPFolder($memberId, $companyId);

            // Create a folder if it wasn't created yet
            $this->_files->createFTPDirectory($pathToXfdfDir);

            $pathToXfdf = $pathToXfdfDir . '/' . $fileName;
            if (!file_exists($pathToXfdf)) {
                $booResult = copy($defaultXfdfPath, $pathToXfdf);
            }
        }

        return $booResult;
    }

    /**
     * Delete form(s)
     * @param array $arrFormIds
     * @param int $companyId
     * @return bool true on success
     */
    public function deleteForm($arrFormIds, $companyId)
    {
        $booSuccess = false;
        if (is_array($arrFormIds) && count($arrFormIds)) {
            $booHasAccess = true;
            foreach ($arrFormIds as $formId) {
                if (!$this->hasAccessToDefaultForm($formId, $companyId)) {
                    $booHasAccess = false;
                    break;
                }
            }

            if ($booHasAccess) {
                $this->_db2->delete('form_default', ['form_default_id' => $arrFormIds]);
                $booSuccess = true;
            }
        }

        return $booSuccess;
    }

    /**
     * Create form with specific form version id for current user's company
     * @param $companyId
     * @param $formVersionId
     */
    public function addForm($companyId, $formVersionId)
    {
        $this->_db2->insert(
            'form_default',
            [
                'company_id'      => $companyId,
                'form_version_id' => $formVersionId,
            ]
        );
    }

    /**
     * Check if form exists by form version id
     * @param int $formVersionId
     * @return bool true if exists
     */
    public function exists($companyId, $formVersionId)
    {
        $select = (new Select())
            ->from('form_default')
            ->columns(['form_default_id'])
            ->where([
                'company_id'      => (int)$companyId,
                'form_version_id' => (int)$formVersionId
            ]);

        return $this->_db2->fetchOne($select) > 0;
    }

    /**
     * Create/update form json file for specific member
     *
     * @param int $memberId
     * @param string $familyMemberId
     * @param int $formId
     * @param array $arrNewJsonData
     * @param bool $booMergeData
     * @return bool true on success
     */
    public function updateJson($memberId, $familyMemberId, $formId, $arrNewJsonData, $booMergeData = false)
    {
        try {
            $filePath = $this->_files->getClientJsonFilePath($memberId, $familyMemberId, $formId);
            if (file_exists($filePath)) {
                if ($booMergeData) {
                    $savedJson = file_get_contents($filePath);
                    $arrSavedJsonData = (array)json_decode($savedJson);
                    $arrNewJsonData = array_merge($arrSavedJsonData, $arrNewJsonData);
                }
            }
            $booSuccess = $this->_files->createFile($filePath, json_encode($arrNewJsonData));
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    public function pdf2htmlConvert($pdfId)
    {
        $strError = '';

        $arrFormInfo = $this->getFormVersion()->getFormVersionInfo($pdfId);
        $realPath    = $this->_config['directory']['pdfpath_physical'] . '/' . $arrFormInfo['file_path'];
        if (file_exists($realPath)) {
            try {
                $pathToPdf2HtmlUtil = 'library' . DIRECTORY_SEPARATOR . 'Pdf2Html/';

                /** @var Layout $layout */
                $layout     = $this->_viewHelperManager->get('layout');
                $topBaseUrl = $layout()->getVariable('topBaseUrl');

                // Usage: java -jar pdf2html.jar <pathToFileOrFolder> <outPath> <pdfId> <title> <jsCssInjectCode>
                $title           = $arrFormInfo['file_name'];
                $jsCssInjectCode =
                    '<link rel="stylesheet" href="' . $topBaseUrl . '/min?g=pdf2_css">' .
                    '<script type="text/javascript" src="' . $topBaseUrl . '/min?g=pdf2_js"></script>';
                $strParams       = sprintf('"%s" "%s" %d "%s" "%s"', $realPath, $this->_files->getConvertedPDFFormPath(''), $pdfId, $title, $jsCssInjectCode);

                // collect the command to run the jar
                $cmd = sprintf(
                    'java -Duser.dir="%s" -jar "%s" %s  2>&1',
                    $pathToPdf2HtmlUtil,
                    $pathToPdf2HtmlUtil . 'pdf2html.jar',
                    $strParams
                );

                exec($cmd, $arrResult, $returnVar);

                if ($returnVar != 0) {
                    $strError = sprintf('<div style="color: red;">%s<br/>%s</div><hr/>', $this->_tr->translate('Internal Error'), implode("<br/>", $arrResult));
                    $textStatus = sprintf('<div><b>%s</b> in progress...</div>', $arrFormInfo['file_name']) . $strError;
                } else {
                    $textStatus = sprintf('<div>%s converted to HTML successfully.</div>', $arrFormInfo['file_name']);
                }
            } catch (Exception $e) {
                $this->_log->debugErrorToFile('Error when run java:', $e->getMessage());
                $strError = $textStatus = $this->_tr->translate('Internal Error') . '!';
            }
        } else {
            $strError = $textStatus = $this->_tr->translate('PDF file does not exist');
        }

        return array($strError, $textStatus);
    }

    public function pdf2htmlRevert($pdfId)
    {
        $strError = '';

        $arrFormInfo = $this->getFormVersion()->getFormVersionInfo($pdfId);
        $pdfFolder   = $this->_files->getConvertedPDFFormPath($pdfId) . '/';

        if (file_exists($pdfFolder . 'index.html')) {
            try {
                $arrFailureFiles = array();
                $files = glob($pdfFolder . '/*'); // get all file names

                foreach ($files as $file) {
                    if (is_file($file)) {
                        if (!@unlink($file)) {
                            $arrFailureFiles [] = $file;
                        }
                    }
                }

                if (!empty($arrFailureFiles)) {
                    $strError = sprintf('<div style="color: red;">%s<br/>%s</div><hr/>', $this->_tr->translate('Internal Error. Cannot delete these files: '), implode("<br/>", $arrFailureFiles));
                    $textStatus = sprintf('<div><b>%s</b> in progress...</div>', $arrFormInfo['file_name']) . $strError;
                } else {
                    $textStatus = sprintf('<div>%s reverted to PDF successfully.</div>', $arrFormInfo['file_name']);
                }
            } catch (Exception $e) {
                $this->_log->debugErrorToFile('Error when run java:', $e->getMessage());
                $strError = $textStatus = $this->_tr->translate('Internal Error') . '!';
            }
        } else {
            $strError = $textStatus = $this->_tr->translate('HTML file does not exist');
        }


        return array($strError, $textStatus);
    }

    /**
     * Convert specific pdf form version to xod format
     *
     * @param $pdfFormVersionId
     * @return array
     */
    public function pdf2xodConvert($pdfFormVersionId)
    {
        $strError = $textStatus = $formName = '';

        try {
            if ($this->_config['pdf2xod']['use_local'] && is_null($this->_pdfTron)) {
                throw new Exception('Officio\PdfTron module is not installed.');
            }

            $arrFormInfo = $this->getFormVersion()->getFormVersionInfo($pdfFormVersionId);
            if (!isset($arrFormInfo['file_name'])) {
                $strError = $this->_tr->translate('Incorrect form version.');
            } else {
                $formName = $arrFormInfo['file_name'];
            }

            $pathToPdf = $this->_config['directory']['pdfpath_physical'] . '/' . $arrFormInfo['file_path'];
            if (empty($strError) && !file_exists($pathToPdf)) {
                $strError = $this->_tr->translate('Pdf file does not exists.');
            }

            if (empty($strError)) {
                $pathToXod = $this->_files->getConvertedXodFormPath($arrFormInfo['file_path']);
                if (!empty($pathToXod) && file_exists($pathToXod)) {
                    unlink($pathToXod);
                }

                if ($this->_config['pdf2xod']['use_local']) {
                    $this->_pdfTron->convertToXod(
                        getcwd() . '/' . str_replace('\\', '/', $pathToPdf),
                        getcwd() . '/' . str_replace('\\', '/', $pathToXod)
                    );
                } else {
                    $url = $this->_config['pdf2xod']['remote_url'];
                    if (!UriFactory::factory($url)->isValid()) {
                        $strError = $this->_tr->translate('A correct url to conversion web site must be set in the config file.');
                    }

                    if (empty($strError)) {
                        $client = new Client();
                        $client->setUri($url);
                        $client->setOptions(
                            array(
                                'maxredirects' => 0,
                                'timeout' => 10
                            )
                        );

                        // Custom header, will be checked during auth
                        $client->setHeaders(['X-Officio' => '1.0']);

                        // Requirements to the file:
                        // 1. File size less than 10Mb
                        // 2. File extension should be pdf
                        // 3. Mime file type should be application/pdf
                        $client->setFileUpload($pathToPdf, 'pdf_file');
                        $client->setMethod('POST');
                        $response = $client->send();

                        // If 200 was returned - means that response body content is XOD file content
                        // Otherwise that is an error
                        if ($response->isSuccess()) {
                            $fp = fopen($pathToXod, 'a');
                            fwrite($fp, $response->getBody());
                            fclose($fp);
                        } else {
                            $strError = $this->_tr->translate('Conversion failed.');
                            $this->_log->debugErrorToFile('Error during remote conversion', $response->getBody());
                        }
                    }
                }

                // Check if file was created
                if (!file_exists($pathToXod)) {
                    $strError = $this->_tr->translate('Conversion failed.');
                } else {
                    $textStatus = sprintf(
                        '<div style="color: green;"><i>%s</i> converted to XOD successfully.</div>',
                        $arrFormInfo['file_name']
                    );
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal Error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $textStatus = sprintf(
                '<div style="color: red;"><i>%s</i> %s.</div>',
                $formName,
                $strError
            );
        }

        return array($strError, $textStatus);
    }

    /**
     * Revert (delete) already created XOD file
     *
     * @param int $pdfFormVersionId
     * @return array
     */
    public function pdf2xodRevert($pdfFormVersionId)
    {
        $strError = $textStatus = $formName = '';
        try {
            $arrFormInfo = $this->getFormVersion()->getFormVersionInfo($pdfFormVersionId);

            $booDone = false;
            if (isset($arrFormInfo['file_path'])) {
                $formName = $arrFormInfo['file_name'];
                $pathToXod = $this->_files->getConvertedXodFormPath($arrFormInfo['file_path']);

                if (!empty($pathToXod) && is_file($pathToXod)) {
                    $this->_files->deleteFile($pathToXod);
                    $booDone = true;
                }
            }


            if ($booDone) {
                $textStatus = sprintf(
                    '<div style="color: green;"><i>%s</i> reverted to PDF successfully.</div>',
                    $arrFormInfo['file_name']
                );
            } else {
                $strError = $this->_tr->translate('XOD file does not exist');
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile('Error during reverting', $e->getMessage());
            $strError = $textStatus = $this->_tr->translate('Internal Error');
        }

        if (!empty($strError)) {
            $textStatus = sprintf(
                '<div style="color: red;"><i>%s</i> %s.</div>',
                $formName,
                $strError
            );
        }

        return array($strError, $textStatus);
    }

}
