<?php

namespace Superadmin\Controller;

use Exception;
use Laminas\Filter\StripTags;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\Service\AutomatedBillingErrorCodes;
use Officio\BaseController;
use Officio\Common\Service\Settings;

/**
 * Manage PT Error Codes
 *
 * @author    Uniques Software Corp.
 * @copyright  Uniques
 */
class ManagePtErrorCodesController extends BaseController
{

    /** @var AutomatedBillingErrorCodes */
    protected $_automatedBillingErrorCodes;

    public function initAdditionalServices(array $services)
    {
        $this->_automatedBillingErrorCodes = $services[AutomatedBillingErrorCodes::class];
    }

    public function getCodesAction() {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            $view = new ViewModel(
                [
                    'content' => $this->_tr->translate('Insufficient access rights.')
                ]
            );
            $view->setTerminal(true);
            $view->setTemplate('layout/plain');

            return $view;
        }
        $view = new JsonModel();

        $arrCodes = array();
        $totalCount = 0;
        try {
            $start = $this->findParam('start');
            $limit = $this->findParam('limit');

            
            $arrCodesResult = $this->_automatedBillingErrorCodes->getErrorCodesList($start, $limit);
            $arrCodes   = $arrCodesResult['rows'];
            $totalCount = $arrCodesResult['totalCount'];

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        // Return Json result
        $arrResult = array(
            'success'    => $booSuccess,
            'rows'       => $arrCodes,
            'totalCount' => $totalCount
        );

        return $view->setVariables($arrResult);
    }

    public function saveCodeAction() {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            $view = new ViewModel(
                [
                    'content' => $this->_tr->translate('Insufficient access rights.')
                ]
            );
            $view->setTerminal(true);
            $view->setTemplate('layout/plain');

            return $view;
        }
        $view = new JsonModel();

        $strError = '';
        $strSuccess = '';

        try {
            $codeId      = $this->findParam('error-code-id', 0);
            $code        = trim($this->findParam('error-code', ''));
            $description = trim($this->findParam('error-description', ''));

            if(empty($code)) {
                $strError = $this->_tr->translate('Please enter correct code.');
            }

            if(empty($strError) && empty($description)) {
                $strError = $this->_tr->translate('Please enter correct description.');
            }

            $booCreate = empty($codeId);

            if(empty($strError)) {
                $booExists = $this->_automatedBillingErrorCodes->checkCodeExists($code, $codeId);
                if($booExists) {
                    $strError = $this->_tr->translate('Error code already exists in DB.');
                }
            }

            if(empty($strError)) {
                $filter = new StripTags();
                $arrUpdate = Settings::filterParamsArray(array(
                    'pt_error_id'          => $codeId,
                    'pt_error_code'        => $code,
                    'pt_error_description' => $description,
                ), $filter);
                $this->_automatedBillingErrorCodes->update($arrUpdate);
                $strSuccess = $booCreate ? $this->_tr->translate('Error code was successfully saved') : $this->_tr->translate('Error code was successfully updated');
            }

        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        // Return Json result
        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? $strSuccess : $strError
        );

        return $view->setVariables($arrResult);
    }

    public function deleteCodeAction() {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            $view = new ViewModel(
                [
                    'content' => $this->_tr->translate('Insufficient access rights.')
                ]
            );
            $view->setTerminal(true);
            $view->setTemplate('layout/plain');

            return $view;
        }
        $view = new JsonModel();

        $strError = '';

        $codeId = $this->findParam('pt_error_id', 0);
        if(empty($codeId) || !is_numeric($codeId)) {
            $strError = $this->_tr->translate('Error code was incorrectly selected.');
        }

        if(empty($strError)) {
            if(!$this->_automatedBillingErrorCodes->delete($codeId)) {
                $strError = $this->_tr->translate('Internal error');
            }
        }

        // Return Json result
        $arrResult = array(
            'success' => empty($strError),
            'message' => empty($strError) ? 'Error code was successfully deleted' : $strError
        );

        return $view->setVariables($arrResult);
    }
}