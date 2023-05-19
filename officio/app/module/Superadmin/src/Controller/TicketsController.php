<?php

namespace Superadmin\Controller;

use Exception;
use Laminas\Filter\StripTags;
use Officio\Common\Json;
use Laminas\View\Model\JsonModel;
use Officio\BaseController;
use Officio\Service\Company;
use Officio\Service\Tickets;

/**
 * Tickets Controller
 *
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class TicketsController extends BaseController
{
    /** @var Tickets */
    protected $_tickets;

    /** @var Company */
    protected $_company;

    public function initAdditionalServices(array $services)
    {
        $this->_company = $services[Company::class];
        $this->_tickets = $services[Tickets::class];
    }

    public function indexAction(){}

    public function getTicketAction()
    {
        $view = new JsonModel();
        $booSuccess = false;
        $ticket = '';
        try {
            $ticketId = $this->findParam('ticket_id');

            if($this->_tickets->isAllowAccess($ticketId)) {
                $ticket = $this->_tickets->getTicket($ticketId);
                $booSuccess = true;
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        $arrResult = array('success' => $booSuccess, 'ticket' => $ticket);
        return $view->setVariables($arrResult);

    }

    private function updateTickets($action)
    {
        try {
            $filter = new StripTags();

            //get variables
            $ticketId          = $this->findParam('ticket_id');
            $companyId         = $this->findParam('company_id');
            $companyMemberId  = $this->findParam('company_member_id');
            $status             = $filter->filter(Json::decode($this->findParam('status'), Json::TYPE_ARRAY));
            $contactedBy       = $filter->filter(Json::decode($this->findParam('contacted_by'), Json::TYPE_ARRAY));
            $ticket             = trim($filter->filter(Json::decode($this->findParam('ticket', ''), Json::TYPE_ARRAY)));
            // Check ticket id
            if($action == 'edit' && (empty($ticketId) || !is_numeric($ticketId) || !$this->_tickets->isAllowAccess($ticketId))) {
                return false;
            }
            return $this->_tickets->updateTicket($action, (int) $ticketId, $ticket, $status, $contactedBy, $companyId, (int) $companyMemberId);
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return false;
    }

    public function addAction()
    {
        $view = new JsonModel();
        $booSuccess = false;
        try {
            $booSuccess = $this->updateTickets('add');
            if ($booSuccess)
            {
                $this->_company->updateLastField(false, 'last_note_written');
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return $view->setVariables(array('success' => $booSuccess));
    }

    public function deleteAction()
    {
        $view = new JsonModel();
        $booSuccess = false;
        try {
            $tickets = Json::decode($this->findParam('tickets'), Json::TYPE_ARRAY);

            $tickets = (array)$tickets;
            if ($tickets) {
                // Check if current user can delete each note
                $booCheckedSuccess = true;
                foreach ($tickets as $ticketId) {
                    if (empty($ticketId) || !is_numeric($ticketId) || !$this->_tickets->isAllowAccess($ticketId)) {
                        $booCheckedSuccess = false;
                        break;
                    }
                }
                // If can delete - delete them!
                if ($booCheckedSuccess) {
                    $booSuccess = $this->_tickets->deleteTickets($tickets);
                }
            }

        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return $view->setVariables(array('success' => $booSuccess));
    }


    public function getTicketsAction()
    {
        $view = new JsonModel();
        $tickets = array();
        try {
            $companyId = $this->findParam('company_id');
            $start = (int) $this->findParam('start');
            $limit = (int) $this->findParam('limit');

            $dir = $this->findParam('dir');
            if (!in_array($dir, array('ASC', 'DESC'))) {
                $dir = 'DESC';
            }

            $sort = $this->findParam('sort');
            $arrFilterData = array(
                'filter_status' => Json::decode($this->findParam('filter_status'), Json::TYPE_ARRAY)
            );

            // Check if current user can edit notes for this client/user
            if ($this->_auth->isCurrentUserSuperadmin()) {
                $tickets = $this->_tickets->getTickets($start, $limit, $sort, $dir, $companyId, $arrFilterData);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return $view->setVariables($tickets);
    }

    public function changeStatusAction()
    {
        $view = new JsonModel();
        $booSuccess = false;
        try {
            $ticketId = $this->findParam('ticket_id');
            // Check if current user can edit notes for this client/user
            if ($this->_auth->isCurrentUserSuperadmin()) {
                $booSuccess = $this->_tickets->changeStatus($ticketId);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }
        return $view->setVariables(array('success' => $booSuccess));
    }
}