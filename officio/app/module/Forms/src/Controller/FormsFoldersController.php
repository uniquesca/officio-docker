<?php

namespace Forms\Controller;

use Exception;
use Forms\Service\Forms;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;

/**
 * Forms Folders Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class FormsFoldersController extends BaseController
{

    /** @var Forms */
    protected $_forms;

    public function initAdditionalServices(array $services)
    {
        $this->_forms = $services[Forms::class];
    }

    /**
     * The default action - show the home page
     */
    public function indexAction()
    {
        $view = new ViewModel(
            ['content' => null]
        );
        $view->setTemplate('layout/plain');
        $view->setTerminal(true);

        return $view;
    }

    private function getProcessedFilesAndFolders($booWithFiles = true, $version = 'FULL', $parentId = 0)
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
                'children'  => $this->getProcessedFilesAndFolders($booWithFiles, $version, $folder['folder_id'])
            );

            if ($booWithFiles) {
                $arrFiles = $this->_forms->getFormProcessed()->getListByFolderAndVersion($folder['folder_id'], $version);

                foreach ($arrFiles as $file) {
                    $arrFolders[$i]['children'][] = array(
                        'text'        => $file['name'],
                        'description' => $file['content'],
                        'file_id'     => $file['form_processed_id'],
                        'cls'         => 'landing-file',
                        'leaf'        => true
                    );
                }
            }

            ++$i;
        }

        return $arrFolders;
    }

    /**
     * Return folders and files list
     * in json format
     *
     */
    public function listAction()
    {
        try {
            $version = Json::decode(stripslashes($this->params()->fromPost('version', '')), Json::TYPE_ARRAY);

            $booWithFiles = Json::decode($this->params()->fromPost('with_files'), Json::TYPE_ARRAY);
            if ($booWithFiles === null) {
                $booWithFiles = true;
            }

            $arrFolders = $this->getProcessedFilesAndFolders($booWithFiles, ($version == 'all' ? 'FULL' : 'LAST'));
        } catch (Exception $e) {
            $arrFolders = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel($arrFolders);
    }

    /**
     * Return files list for a specific folder
     * in json format
     *
     */
    public function filesAction()
    {
        $arrFormattedForms = array();
        $totalCount        = 0;

        try {
            $version    = Json::decode(stripslashes($this->params()->fromPost('version', '')), Json::TYPE_ARRAY);
            $booLoadAll = $version == 'all';


            $filter   = new StripTags();
            $sort     = $filter->filter($this->params()->fromPost('sort'));
            $dir      = $filter->filter($this->params()->fromPost('dir'));
            $start    = (int)$this->params()->fromPost('start', 0);
            $limit    = (int)$this->params()->fromPost('limit', 25);
            $folderId = (int)$this->params()->fromPost('folder_id', 0);


            // Get assigned forms for this folder
            $arrResult  = $this->_forms->getFormVersion()->getFormsByFolderId($folderId, $booLoadAll, $sort, $dir, $start, $limit);
            $totalCount = $arrResult['totalCount'];

            foreach ($arrResult['rows'] as $assignedFormInfo) {
                $arrFormattedForms[] = array(
                    'form_version_id' => (int)$assignedFormInfo['form_version_id'],
                    'form_type'       => $assignedFormInfo['form_type'],
                    'file_name'       => $assignedFormInfo['file_name'],
                    'date_uploaded'   => $assignedFormInfo['uploaded_date'],
                    'date_version'    => $assignedFormInfo['version_date'],
                    'size'            => $assignedFormInfo['size'],
                    'has_pdf'         => $this->_forms->getFormVersion()->isFormVersionPdf($assignedFormInfo['form_version_id']),
                    'has_html'        => $this->_forms->getFormVersion()->isFormVersionHtml($assignedFormInfo['form_version_id']),
                    'has_xod'         => $this->_forms->getFormVersion()->isFormVersionXod($assignedFormInfo['form_version_id'])
                );
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'rows'       => $arrFormattedForms,
            'totalCount' => $totalCount
        );

        return new JsonModel($arrResult);
    }
}