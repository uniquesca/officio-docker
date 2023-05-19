<?php

namespace Superadmin\Controller;

use Exception;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Laminas\Filter\StripTags;

/**
 * System Variables Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class SystemVariablesController extends BaseController
{

    public function initAdditionalServices(array $services)
    {

    }

    public function indexAction()
    {
        $view = new ViewModel();

        $title = $this->_tr->translate('System variables');
        $this->layout()->setVariable('title', $title);
        $this->_serviceManager->get('ViewHelperManager')->get('HeadTitle')->append($title);

        $arrVariables     = $this->_settings->getSystemVariables()->getListOfVariablesToUpdateInGUI();
        foreach ($arrVariables as $groupKey => $arrGroup) {
            foreach ($arrGroup['group_items'] as $variableKey => $arrGroupVariable) {
                $arrVariables[$groupKey]['group_items'][$variableKey]['variable_value'] = $this->_settings->getSystemVariables()->getVariable($arrGroupVariable['variable_name']);
            }
        }

        $view->setVariable('arrVariables', $arrVariables);

        return $view;
    }

    public function saveAction()
    {
        $view = new JsonModel();
        $strError = '';

        try {
            $filter = new StripTags();
            $this->_db2->getDriver()->getConnection()->beginTransaction();

            $arrVariables = $this->_settings->getSystemVariables()->getListOfVariablesToUpdateInGUI();
            foreach ($arrVariables as $arrGroup) {
                foreach ($arrGroup['group_items'] as $arrGroupVariable) {
                    $variableValue = trim($filter->filter($this->findParam($arrGroupVariable['variable_name'], '')));

                    if ($variableValue === '') {
                        $strError = sprintf(
                            $this->_tr->translate('<i>%s</i> is a required field.'),
                            $arrGroupVariable['variable_label']
                        );
                        break 2;
                    } else {
                        $this->_settings->getSystemVariables()->setVariable($arrGroupVariable['variable_name'], $variableValue);
                    }
                }
            }

            // If all is okay - apply changes
            if (empty($strError)) {
                $this->_db2->getDriver()->getConnection()->commit();
            } else {
                $this->_db2->getDriver()->getConnection()->rollback();
            }

        } catch (Exception $e) {
            $this->_db2->getDriver()->getConnection()->rollback();

            $strError = $this->_tr->translate('Internal error.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $this->_tr->translate('Saved successfully.') : $strError
        );

        return $view->setVariables($arrResult);
    }
}