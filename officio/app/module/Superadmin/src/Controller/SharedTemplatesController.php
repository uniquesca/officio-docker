<?php

namespace Superadmin\Controller;

use Exception;
use Files\Service\Files;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Templates\Service\Templates;

/**
 * Shared Templates Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class SharedTemplatesController extends BaseController
{
    /** @var Templates */
    protected $_templates;

    /** @var Files */
    protected $_files;

    public function initAdditionalServices(array $services)
    {
        $this->_templates = $services[Templates::class];
        $this->_files = $services[Files::class];
    }

    public function indexAction()
    {
        $view = new ViewModel();

        $title = 'Default Shared Templates';
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        return $view;
    }

    public function getTemplatesAction()
    {
        $view = new JsonModel();
        $arrTemplates = array();

        try {
            $arrSavedTemplates = $this->_templates->getTemplates(
                (int) $this->_files->getFolders()->getDefaultSharedFolderId(),
                $this->_auth->getCurrentUserId()
            );

            foreach ($arrSavedTemplates as $template) {
                $arrTemplates[] = array(
                    'template_id'   => $template['template_id'],
                    'name'          => $template['name'],
                    'is_default'    => $template['default'] == 'Y',
                    'size'          => $this->_files->formatFileSize(strlen($template['length'] ?? '')),
                    'templates_for' => $this->_templates->getTemplatesForName($template['templates_for']),
                    'create_date'   => $this->_settings->formatDate($template['create_date'])
                );
            }

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            "success"    => $booSuccess,
            "rows"       => $arrTemplates,
            "totalCount" => count($arrTemplates)
        );
        return $view->setVariables($arrResult);
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
        $view = new JsonModel();
        try {
            $arrParams = $this->findParams();

            $arrParams['templates_type'] = 'Email';
            $arrParams['folder_id']      =  $this->_files->getFolders()->getDefaultSharedFolderId();

            $booSuccess = $this->_templates->saveTemplate($arrParams);
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => $booSuccess));
    }

    public function getTemplateAction()
    {
        $view = new JsonModel();
        try {
            $templateId  = $this->findParam('id');
            $arrTemplate = $this->_templates->getTemplate((int)$templateId);
        } catch (Exception $e) {
            $arrTemplate = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables($arrTemplate);
    }

    public function deleteAction()
    {
        $view = new JsonModel();
        try {
            $templates  = Json::decode($this->findParam('templates'), Json::TYPE_ARRAY);
            $booSuccess = $this->_templates->delete($templates);
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => $booSuccess));
    }

    public function setDefaultAction()
    {
        $view = new JsonModel();
        try {
            $oldTemplateId = $this->findParam('old_template_id');
            $newTemplateId = $this->findParam('new_template_id');

            $this->_templates->setTemplateAsDefault($oldTemplateId, $newTemplateId);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array('success' => $booSuccess));
    }
}