<?php

namespace Officio\Controller;

use Clients\Service\Clients;
use Exception;
use Officio\Common\Json;
use Laminas\View\Model\ViewModel;
use Laminas\View\Model\JsonModel;
use Officio\BaseController;

/**
 * WithdrawalTypesController - this controller is used in several cases
 * in Ajax requests
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class WithdrawalTypesController extends BaseController
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

    public function getWithdrawalTransactionsListAction()
    {
        $view = new JsonModel();

        try {
            $booWithoutOther = Json::decode($this->findParam('withoutOther'), Json::TYPE_ARRAY);
            $arrTransactions = $this->_clients->getAccounting()->getTrustAccount()->getTypeOptions('withdrawal', (bool)$booWithoutOther);

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

    public function deleteWithdrawalFromTransactionsListAction()
    {
        $view = new JsonModel();

        try {
            $wtl_id     = $this->findParam('wtl_id');
            $booSuccess = $this->_clients->getAccounting()->getTrustAccount()->deleteTypeOption('withdrawal', (int)$wtl_id);
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array("success" => $booSuccess));
    }

    public function saveWithdrawalTransactionsListAction()
    {
        $view = new JsonModel();

        try {
            $data       = Json::decode(stripslashes($this->findParam('data', '')), Json::TYPE_ARRAY);
            $booSuccess = $this->_clients->getAccounting()->getTrustAccount()->saveTypeOption('withdrawal', $data);
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array("success" => $booSuccess));
    }
}