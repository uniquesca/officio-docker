<?php

namespace Officio\Controller;

use Clients\Service\Clients;
use Exception;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;

/**
 * DepositTypesController - this controller is used in several cases
 * in Ajax requests
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class DepositTypesController extends BaseController
{
    /** @var Clients */
    private $_clients;

    public function initAdditionalServices(array $services)
    {
        $this->_clients = $services[Clients::class];
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

    public function getDepositTransactionsListAction()
    {
        $view = new JsonModel();

        try {
            $booWithoutOther = Json::decode($this->findParam('withoutOther'), Json::TYPE_ARRAY);
            $arrTransactions = $this->_clients->getAccounting()->getTrustAccount()->getTypeOptions('deposit', (bool)$booWithoutOther);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess      = false;
            $arrTransactions = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }


        $arrResult = array(
            'success'    => $booSuccess,
            'rows'       => $arrTransactions,
            'totalCount' => count($arrTransactions)
        );

        return $view->setVariables($arrResult);
    }

    public function deleteDepositFromTransactionsListAction()
    {
        $view = new JsonModel();

        try {
            $dtl_id     = $this->findParam('dtl_id');
            $booSuccess = $this->_clients->getAccounting()->getTrustAccount()->deleteTypeOption('deposit', (int)$dtl_id);
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array("success" => $booSuccess));
    }

    public function saveDepositTransactionsListAction()
    {
        $view = new JsonModel();

        try {
            $data       = Json::decode(stripslashes($this->findParam('data', '')), Json::TYPE_ARRAY);
            $booSuccess = $this->_clients->getAccounting()->getTrustAccount()->saveTypeOption('deposit', $data);
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array("success" => $booSuccess));
    }
}