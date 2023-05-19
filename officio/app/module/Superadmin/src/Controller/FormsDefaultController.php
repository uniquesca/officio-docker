<?php

namespace Superadmin\Controller;

use Clients\Service\Members;
use Exception;
use Files\Service\Files;
use Forms\Service\Forms;
use Forms\Service\Pdf;
use Laminas\Db\Sql\Select;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Common\Service\Encryption;
use Officio\Common\Service\Settings;

/**
 * Forms Default Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class FormsDefaultController extends BaseController
{
    /** @var Forms */
    private $_forms;

    /** @var StripTags */
    private $_filter;

    /** @var Files */
    protected $_files;

    /** @var Pdf */
    protected $_pdf;

    /** @var Encryption */
    protected $_encryption;

    public function initAdditionalServices(array $services)
    {
        $this->_files      = $services[Files::class];
        $this->_forms      = $services[Forms::class];
        $this->_pdf        = $services[Pdf::class];
        $this->_encryption = $services[Encryption::class];

        $this->_filter = new StripTags();
    }

    /**
     * The default action - show forms grid
     */
    public function indexAction()
    {
        $view = new ViewModel();
        // The same title as in the navigation menu
        $title = $this->_tr->translate('Default Forms');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $view->setVariable('intShowFormsPerPage', $this->_forms->intShowFormsPerPage);

        return $view;
    }

    /**
     * Load forms list and return them in json format
     */
    public function listAction () {
        $view = new JsonModel();
        try {
            // Get params
            $sort  = $this->_filter->filter($this->findParam('sort'));
            $dir   = $this->findParam('dir');
            $start = (int)$this->findParam('start');
            $limit = (int)$this->findParam('limit');

            $arrFormsList = $this->_forms->getDefaultFormsList($this->_auth->getCurrentUserCompanyId(), $sort, $dir, $start, $limit);
        } catch (Exception $e) {
            $arrFormsList = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables($arrFormsList);
    }


    public function openPdfAction () {
        $view = new ViewModel(
            [
                'content' => null
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        try {
            $strError = '';
            $formId = (int) $this->findParam('formId');
            if ($this->_forms->hasAccessToDefaultForm($formId, $this->_auth->getCurrentUserCompanyId())) {
                $arrInfo = $this->_forms->getDefaultFormInfo($formId);
                if (is_array($arrInfo) && array_key_exists('form_version_id', $arrInfo)) {
                    list($realPath, $fileName) = $this->_forms->getFormVersion()->getPdfFilePathByVersionId($arrInfo['form_version_id']);
                    if (!empty($realPath)) {
                        return $this->downloadFile($realPath, $fileName, 'application/pdf', true, false);
                    } else {
                        $strError = $this->_tr->translate('Incorrect path to the file');
                    }
                }
            }
        } catch (Exception $e) {
            $strError = 'Internal error.';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariable('content', $strError);
    }


    public function openXodAction()
    {
        $view = new ViewModel(
            [
                'content' => null
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        try {
            $strError = '';

            $formId = (int)$this->findParam('formId');
            if (!$this->_forms->hasAccessToDefaultForm($formId, $this->_auth->getCurrentUserCompanyId())) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            $formVersionId = 0;
            if (empty($strError)) {
                $arrInfo = $this->_forms->getDefaultFormInfo($formId);
                if (isset($arrInfo['form_version_id'])) {
                    $formVersionId = $arrInfo['form_version_id'];
                } else {
                    $strError = $this->_tr->translate('Incorrect incoming info.');
                }
            }

            if (empty($strError)) {
                $arrFormVersionInfo = $this->_forms->getFormVersion()->getFormVersionInfo($formVersionId);
                if (isset($arrFormVersionInfo['file_path'])) {
                    $realPath = $this->_files->getConvertedXodFormPath($arrFormVersionInfo['file_path']);
                    if (!empty($realPath) && file_exists($realPath)) {
                        return $this->downloadFile($realPath, 'form.xod', '', true);
                    }
                    else {
                        $strError = $this->_tr->translate('Internal error.');
                    }
                }
                else {
                    $strError = $this->_tr->translate('Internal error.');
                }
            }
        } catch (Exception $e) {
            $strError = 'Internal error.';
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $this->getResponse()->setStatusCode(404);
        }

        return $view->setVariable('content', $strError);
    }


    public function openXfdfAction () {
        $view = new ViewModel(
            [
                'content' => null
            ]
        );
        $view->setTerminal(true);
        $view->setTemplate('layout/plain');

        $strError = '';
        try {
            $formId = (int) $this->findParam('formId');
            if ($this->_forms->hasAccessToDefaultForm($formId, $this->_auth->getCurrentUserCompanyId())) {
                $arrFormInfo = $this->_forms->getDefaultFormInfo($formId);

                $pathToXfdf = $this->_forms->getDefaultXfdfPath($this->_auth->getCurrentUserCompanyId());
                if (file_exists($pathToXfdf)) {
                    $xml = $this->_pdf->readXfdfFromFile($pathToXfdf);
                    if ($xml === false) {
                        // Not in xml format, return empty doc
                        $emptyXfdf = $this->_pdf->getEmptyXfdf();
                        $oXml      = simplexml_load_string($emptyXfdf);
                    } else {
                        $oXml = $xml;
                    }
                } else {
                    $emptyXfdf = $this->_pdf->getEmptyXfdf();
                    $oXml = simplexml_load_string($emptyXfdf);
                }

                $this->_pdf->updateFieldInXfdf('server_form_version', '', $oXml);
                $this->_pdf->updateFieldInXfdf('server_url', $this->layout()->getVariable('baseUrl') . '/forms-default/sync#FDF', $oXml);
                $this->_pdf->updateFieldInXfdf('server_assigned_id', $formId, $oXml);
                $this->_pdf->updateFieldInXfdf('server_xfdf_loaded', 1, $oXml);
                $this->_pdf->updateFieldInXfdf('server_locked_form', 0, $oXml);
                $this->_pdf->updateFieldInXfdf('server_time_stamp', $arrFormInfo['updated_on'], $oXml);

                $strParsedResult = preg_replace('%<value>(\r|\n|\r\n)</value>%', '<value></value>', $oXml->asXML());
                return $this->file($strParsedResult, 'default.xfdf', 'application/vnd.adobe.xfdf', false, false);
            } else {
                $strError = $this->_tr->translate('Insufficient access rights');
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $strError = empty($strError) ? 'Internal error.' : $strError;
        return $view->setVariable('content', $strError);
    }

    public function saveXodAction()
    {
        $strUpdatedOn   = '';
        $xfdfLoadedCode = 0;

        try {
            // Get incoming xfdf
            $XFDFData = $this->findParam('xfdf', '');

            // Fix: sometimes enter is passed incorrectly - replace it to the correct one
            $XFDFData     = str_replace('&#xD;', "\n", $XFDFData);
            $incomingXfdf = $this->_pdf->getIncomingXfdf($XFDFData);

            // Run sync process
            $strError = '';
            $currentMemberCompanyId = 0;
            list($login, $pass, $formId, $formTimeStamp) = $this->_pdf->parsePdfForCredentials($incomingXfdf);
            if (empty($login) || empty($pass) || empty($formId)) {
                $currentMemberId        = $this->_auth->getCurrentUserId();
                $currentMemberCompanyId = $this->_auth->getCurrentUserCompanyId();
                if (!$this->_forms->hasAccessToDefaultForm($formId, $currentMemberCompanyId)) {
                    $strError = $this->_pdf->getCodeResultById(Pdf::XFDF_INCORRECT_INCOMING_XFDF);
                }
            }
            else {
                $arrAllowedTypes = Members::getMemberType('admin_and_user');

                $select = (new Select())
                    ->from('members')
                    ->columns(['password', 'member_id', 'company_id'])
                    ->where([
                        'userType' => $arrAllowedTypes,
                        'status'   => 1,
                        'username' => $login
                    ]);

                $arrMemberInfo = $this->_db2->fetchRow($select);

                if (is_array($arrMemberInfo) && !empty($arrMemberInfo['password'])) {
                    $currentMemberId        = $arrMemberInfo['member_id'];
                    $currentMemberCompanyId = $arrMemberInfo['company_id'];
                    if (!$this->_encryption->checkPasswords($pass, $arrMemberInfo['password']) && $this->_forms->hasAccessToDefaultForm($formId, $currentMemberCompanyId)) {
                        $strError = $this->_pdf->getCodeResultById(Pdf::XFDF_INCORRECT_INCOMING_XFDF);
                    }
                }
                else {
                    $strError = $this->_pdf->getCodeResultById(Pdf::XFDF_INCORRECT_INCOMING_XFDF);
                }
            }

            if (empty($strError)) {
                $arrFormInfo = $this->_forms->getDefaultFormInfo($formId);
                // Timestamp must be same as in DB
                if (!empty($formTimeStamp) && $formTimeStamp != $arrFormInfo['updated_on']) {
                    $strError = $this->_pdf->getCodeResultById(Pdf::XFDF_INCORRECT_TIME_STAMP);
                }
            }

            if (empty($strError)) {
                $code = $this->_pdf->syncDefaultXfdf($incomingXfdf, $currentMemberCompanyId);
                if ($code != Pdf::XFDF_SAVED_CORRECTLY) {
                    $strError = $this->_pdf->getCodeResultById($code);
                }
                $strMessage = $this->_pdf->getCodeResultById($code);
                $xfdfLoadedCode = ($code == Pdf::XFDF_CLIENT_LOCKED) ? 2 : 1;
            }
            else {
                $strMessage = $strError;
            }

            if (empty($strError)) {
                // Update 'Updated On', 'Updated By' columns
                if (!empty($currentMemberId)) {
                    $strUpdatedOn = date('Y-m-d H:i:s');
                    $this->_forms->updateDefaultForm($formId, array('updated_by' => $currentMemberId, 'updated_on' => $strUpdatedOn));
                }
            }
        } catch (Exception $e) {
            // Save to log exception info
            $strMessage = $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        if (!empty($strError)) {
            $this->getResponse()->setStatusCode(400);
        }


        // return json result
        $arrResult = array(
            'message'           => $strMessage,
            'arrFieldsToUpdate' => array(
                'server_time_stamp'  => $strUpdatedOn,
                'server_xfdf_loaded' => $xfdfLoadedCode
            )
        );
        return new JsonModel($arrResult);
    }


    public function printXodAction()
    {
        try {
            $formId = (int)$this->findParam('formId');
            if (!$this->_forms->hasAccessToDefaultForm($formId, $this->_auth->getCurrentUserCompanyId())) {
                $strError = $this->_tr->translate('Insufficient access rights.');
            }

            // Get path to PDF form by id
            $pdfFormPath = '';
            $pdfFileName = '';
            if (empty($strError)) {
                $arrInfo = $this->_forms->getDefaultFormInfo($formId);
                if (!isset($arrInfo['form_version_id'])) {
                    $strError = $this->_tr->translate('Incorrect incoming info.');
                } else {
                    list($pdfFormPath, $pdfFileName) = $this->_forms->getFormVersion()->getPdfFilePathByVersionId($arrInfo['form_version_id']);

                    if (!file_exists($pdfFormPath)) {
                        $strError = $this->_tr->translate('PDF file does not exists.');
                    }
                }
            }

            // Get path to XFDF file, can be empty
            $xfdfPath = '';
            if (empty($strError)) {
                $pathToXfdf = $this->_forms->getDefaultXfdfPath($this->_auth->getCurrentUserCompanyId());

                if (file_exists($pathToXfdf)) {
                    $oXml = $this->_pdf->readXfdfFromFile($pathToXfdf);
                    if ($oXml !== false) {
                        $xfdfPath = $pathToXfdf;
                    }
                }
            }

            // Create a flatten pdf from pdf and xfdf
            if (empty($strError)) {
                $config     = $this->_config['directory'];
                $tmpPdfPath = $this->_files->createFTPDirectory($config['pdf_temp']);

                // Path, where flatten pdf file will be created
                $flattenPdfPath = $tmpPdfPath . '/' . 'flatten_form_' . uniqid(rand() . time(), true) . '.pdf';

                // Make sure that there is no such file created yet
                if (file_exists($flattenPdfPath)) {
                    unlink($flattenPdfPath);
                }

                if (!$this->_pdf->createFlattenPdf($pdfFormPath, $xfdfPath, $flattenPdfPath)) {
                    $strError = $this->_tr->translate('Cannot print pdf');
                } else {
                    return $this->downloadFile($flattenPdfPath, $pdfFileName, 'application/pdf', false, false);
                }
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $viewModel = new ViewModel(['content' => $strError]);
        $viewModel->setTerminal(true);
        $viewModel->setTemplate('layout/plain');
        return $viewModel;
    }


    public function syncAction()
    {
        try {
            // Get incoming xfdf
            $XFDFData     = file_get_contents('php://input');
            $incomingXfdf = $this->_pdf->getIncomingXfdf($XFDFData);

            // Run sync process
            $code = false;
            $currentMemberCompanyId = 0;
            list($login, $pass, $formId, $formTimeStamp) = $this->_pdf->parsePdfForCredentials($incomingXfdf);
            if (empty($login) || empty($pass) || empty($formId)) {
                $currentMemberId        = $this->_auth->getCurrentUserId();
                $currentMemberCompanyId = $this->_auth->getCurrentUserCompanyId();
                if (!$this->_forms->hasAccessToDefaultForm($formId, $currentMemberCompanyId)) {
                    $code = Pdf::XFDF_INCORRECT_INCOMING_XFDF;
                }
            }
            else {
                $arrAllowedTypes = Members::getMemberType('admin_and_user');

                $select = (new Select())
                    ->from('members')
                    ->columns(['password', 'member_id', 'company_id'])
                    ->where([
                        'userType' => $arrAllowedTypes,
                        'status'   => 1,
                        'username' => $login
                    ]);

                $arrMemberInfo = $this->_db2->fetchRow($select);

                if (is_array($arrMemberInfo) && !empty($arrMemberInfo['password'])) {
                    $currentMemberId        = $arrMemberInfo['member_id'];
                    $currentMemberCompanyId = $arrMemberInfo['company_id'];
                    if (!$this->_encryption->checkPasswords($pass, $arrMemberInfo['password']) && $this->_forms->hasAccessToDefaultForm($formId, $currentMemberCompanyId)) {
                        $code = Pdf::XFDF_INCORRECT_INCOMING_XFDF;
                    }
                }
                else {
                    $code = Pdf::XFDF_INCORRECT_INCOMING_XFDF;
                }
            }

            if (!$code) {
                $arrFormInfo = $this->_forms->getDefaultFormInfo($formId);
                // Timestamp must be same as in DB
                if (!empty($formTimeStamp) && $formTimeStamp != $arrFormInfo['updated_on']) {
                    $code = Pdf::XFDF_INCORRECT_TIME_STAMP;
                }
            }

            if (!$code) {
                $code = $this->_pdf->syncDefaultXfdf($incomingXfdf, $currentMemberCompanyId);
            }

            $strUpdatedOn = '';
            if ($code && $code == Pdf::XFDF_SAVED_CORRECTLY) {
                // Update 'Updated On', 'Updated By' columns
                if (!empty($currentMemberId)) {
                    $strUpdatedOn = date('Y-m-d H:i:s');
                    $this->_forms->updateDefaultForm($formId, array('updated_by' => $currentMemberId, 'updated_on' => $strUpdatedOn));
                }
            }

            $xfdf = $this->_pdf->getEmptyXfdf();
            $oXml = simplexml_load_string($xfdf);

            // Generate return values
            $this->_pdf->updateFieldInXfdf('server_result', $this->_pdf->getCodeResultById($code), $oXml);
            $this->_pdf->updateFieldInXfdf('server_result_code', $code, $oXml);
            $this->_pdf->updateFieldInXfdf('server_xfdf_loaded', 1, $oXml);

            if ($code == Pdf::XFDF_SAVED_CORRECTLY && !empty($strUpdatedOn)) {
                $this->_pdf->updateFieldInXfdf('server_time_stamp', $strUpdatedOn, $oXml);
            }

            $outputXfdf = $oXml->asXML();

            // Return result xfdf
            return $this->file($outputXfdf, 'default.xfdf', 'application/vnd.adobe.xfdf', false, false);
        } catch (Exception $e) {
            // Save to log exception info
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            // Return empty xfdf
            $xfdf = $this->_pdf->getEmptyXfdf();
            $oXml = simplexml_load_string($xfdf);
            return $this->file($oXml->asXML(), 'default.xfdf', 'application/vnd.adobe.xfdf', false, false);
        }
    }

    public function deleteAction()
    {
        $view = new JsonModel();
        $strError = '';
        try {
            $ids = Settings::filterParamsArray(Json::decode($this->findParam('ids'), Json::TYPE_ARRAY), $this->_filter);

            if (!$this->_forms->deleteForm($ids, $this->_auth->getCurrentUserCompanyId())) {
                $strError = $this->_tr->translate('Insufficient access rights');
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

    public function addAction()
    {
        $view = new JsonModel();
        $strError = '';
        try {
            /** @var array $arrForms */
            $arrForms = Json::decode($this->findParam('forms'), Json::TYPE_ARRAY);
            if (!is_array($arrForms)) {
                $strError = $this->_tr->translate('Incorrect forms.');
            }

            // Check if such combination (company_id+form_version_id) doesn't already exist in DB
            if (count($arrForms))
                foreach ($arrForms as $formId)
                    if ($this->_forms->exists($this->_auth->getCurrentUserCompanyId(), substr($formId, 5))) {
                        $strError = $this->_tr->translate('One of these forms is already assigned to the company');
                        break;
                    }

            // Check pdf version id
            if (!$strError && count($arrForms)) {
                foreach ($arrForms as $formId) {
                    if (!$this->_forms->getFormVersion()->formVersionExists(substr($formId, 5))) {
                        $strError = $this->_tr->translate('Incorrectly selected pdf form');
                        break;
                    }
                }
            }

            if (!$strError && count($arrForms)) {
                foreach ($arrForms as $formId) {
                    $this->_forms->addForm($this->_auth->getCurrentUserCompanyId(), substr($formId, 5));
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

        return $view->setVariables($arrResult);
    }
}
