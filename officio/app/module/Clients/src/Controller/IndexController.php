<?php

namespace Clients\Controller;

use Clients\Service\Clients;
use Exception;
use Files\Service\Files;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;
use Officio\BaseController;
use Officio\Service\Company;

/**
 * Clients Index Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class IndexController extends BaseController
{

    /** @var Clients */
    protected $_clients;

    /** @var Company */
    protected $_company;

    /** @var Files */
    protected $_files;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_clients = $services[Clients::class];
        $this->_files   = $services[Files::class];
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

    public function duplicateAction()
    {
        $strError = '';

        try {
            $memberId = (int)$this->findParam('client_id');

            if (!$this->_members->hasCurrentMemberAccessToMember($memberId)) {
                $strError = 'Insufficient access rights';
            }

            if (empty($strError)) {
                $this->_clients->duplicateClient($memberId);
            }
        } catch (Exception $e) {
            $strError = $this->_tr->translate('Internal error. Please try again later.');
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return new JsonModel(array('success' => empty($strError), 'error' => $strError));
    }
}
