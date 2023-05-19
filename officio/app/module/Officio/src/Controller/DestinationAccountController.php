<?php

namespace Officio\Controller;

use Clients\Service\Clients;
use Exception;
use Officio\Common\Json;
use Laminas\View\Model\ViewModel;
use Laminas\View\Model\JsonModel;
use Officio\BaseController;

/**
 * DestinationAccountController - this controller is used in several cases
 * in Ajax requests
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class DestinationAccountController extends BaseController
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

    public function getDestinationAccountListAction()
    {
        $view = new JsonModel();

        try {
            $booWithoutOther       = Json::decode($this->findParam('withoutOther'), Json::TYPE_ARRAY);
            $arrDestinationAccount = $this->_clients->getAccounting()->getTrustAccount()->getTypeOptions('destination', (bool)$booWithoutOther);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess            = false;
            $arrDestinationAccount = array();
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        $arrResult = array(
            'success'    => $booSuccess,
            'rows'       => $arrDestinationAccount,
            'totalCount' => count($arrDestinationAccount)
        );

        return $view->setVariables($arrResult);
    }

    public function deleteDestinationAccountListAction()
    {
        $view = new JsonModel();

        try {
            $destinationAccountId = $this->findParam('destination_account_id');
            $booSuccess           = $this->_clients->getAccounting()->getTrustAccount()->deleteTypeOption('destination', (int)$destinationAccountId);
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array("success" => $booSuccess));
    }

    public function saveDestinationAccountListAction()
    {
        $view = new JsonModel();

        try {
            $data       = Json::decode(stripslashes($this->findParam('data', '')), Json::TYPE_ARRAY);
            $booSuccess = $this->_clients->getAccounting()->getTrustAccount()->saveTypeOption('destination', $data);
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $view->setVariables(array("success" => $booSuccess));
    }
}