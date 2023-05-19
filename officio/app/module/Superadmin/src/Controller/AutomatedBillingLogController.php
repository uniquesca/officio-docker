<?php

namespace Superadmin\Controller;

use Exception;
use Laminas\View\Model\JsonModel;
use Officio\Service\AutomatedBillingLog;
use Officio\BaseController;

/**
 * Automated Billing Log Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class AutomatedBillingLogController extends BaseController
{
    /** @var AutomatedBillingLog */
    protected $_automatedBillingLog;

    public function initAdditionalServices(array $services)
    {
        $this->_automatedBillingLog = $services[AutomatedBillingLog::class];
    }

    public function getSessionsAction() {
        $view = new JsonModel();
        if (!$this->getRequest()->isXmlHttpRequest()) {
            exit($this->_tr->translate('Insufficient access rights.'));
        }


        try {
            $arrSessions = $this->_automatedBillingLog->loadSessions();
        } catch (Exception $e) {
            $arrSessions = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables($arrSessions);
    }

    public function getSessionDetailsAction() {
        $view = new JsonModel();
        if (!$this->getRequest()->isXmlHttpRequest()) {
            exit($this->_tr->translate('Insufficient access rights.'));
        }

        $booSuccess = false;
        $arrLogRows = array();

        try {
            $intSessionId = (int)$this->findParam('session_id');

            $arrLogRows = $this->_automatedBillingLog->loadSessionLogDetails($intSessionId);
            $booSuccess = true;
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success'    => $booSuccess,
            'rows'       => $arrLogRows,
            'totalCount' => count($arrLogRows)
        );
        return $view->setVariables($arrResult);
    }


    public function deleteSessionAction() {
        $view = new JsonModel();
        if (!$this->getRequest()->isXmlHttpRequest()) {
            exit($this->_tr->translate('Insufficient access rights.'));
        }

        $booSuccess = false;
        try {
            $intSessionId = $this->findParam('session_id');

            if (is_numeric($intSessionId)) {
                $booSuccess = $this->_automatedBillingLog->deleteSession($intSessionId);
                if($booSuccess) {
                    $strMessage = $this->_tr->translate('Session was deleted successfully.');
                } else {
                    $strMessage = $this->_tr->translate('Internal error');
                }
            } else {
                $strMessage = $this->_tr->translate('Session was selected incorrectly');
            }
        } catch (Exception $e) {
            $strMessage = $this->_tr->translate('Internal error');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success' => $booSuccess,
            'message' => $strMessage
        );
        return $view->setVariables($arrResult);
    }
}