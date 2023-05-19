<?php

namespace Officio\Service;

use Clients\Service\Members;
use Exception;
use Laminas\Db\Sql\Predicate\Expression;
use Laminas\Db\Sql\Select;
use Officio\Common\Service\Country;
use Officio\Common\Service\BaseService;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class Tickets extends BaseService
{
    /** @var Members */
    private $_members;

    /** @var Company */
    protected $_company;

    /** @var Country */
    protected $_country;

    public function initAdditionalServices(array $services)
    {
        $this->_members = $services[Members::class];
        $this->_company = $services[Company::class];
        $this->_country = $services[Country::class];
    }

    /**
     * Check if ticket can be accessed
     * i.e. if ticket's company is same to provided
     *
     * @param int $ticketId
     * @param int|null $companyId , if null -
     * company id of current user will be used
     *
     * @return bool true if ticket can be accessed
     */
    public function isAllowAccess($ticketId, $companyId = null)
    {
        $booHasAccess = false;

        // Check access rights if ticket id is correct
        if (is_numeric($ticketId) && !empty($ticketId)) {
            $companyId = is_null($companyId) ? $this->_auth->getCurrentUserCompanyId() : $companyId;

            $select = (new Select())
                ->from(['m' => 'members'])
                ->columns(['company_id'])
                ->join(['t' => 'tickets'], new Expression('m.member_id = t.author_id AND t.ticket_id =' . $ticketId), []);

            $booHasAccess = $companyId == $this->_db2->fetchOne($select);
        }

        return $booHasAccess;
    }

    public function getTicket($ticketId)
    {
        $select = (new Select())
            ->from(array('t' => 'tickets'))
            ->where(['ticket_id' => (int)$ticketId]);

        $ticket = $this->_db2->fetchRow($select);

        return array(
            'ticket_id'         => $ticket['ticket_id'],
            'ticket'            => $ticket['ticket'],
            'date'              => $ticket['create_date'],
            'contacted_by'      => $ticket['contacted_by'],
            'status'            => $ticket['status'],
            'company_member_id' => $ticket['company_member_id']
        );
    }

    public function getTickets($start, $limit, $sort = '', $dir = '', $companyId = 0, $arrFilterData = array())
    {
        $arrRecords = array();
        $totalCount = 0;
        try {
            $arrWhere = [];
            if ($companyId != 'all') {
                $arrWhere['t.company_id'] = (int)$companyId;
            }

            // Apply filters if needed
            if (!empty($arrFilterData['filter_status'])) {
                $arrWhere['t.status'] = $arrFilterData['filter_status'];
            }

            $select = (new Select())
                ->from(array('t' => 'tickets'))
                ->join(array('c' => 'company'), 'c.company_id = t.company_id', array('companyName'), Select::JOIN_LEFT_OUTER)
                ->join(array('m' => 'members'), 'm.member_id = t.author_id', array('fName', 'lName'), Select::JOIN_LEFT_OUTER)
                ->where($arrWhere);

            $tickets = $this->_db2->fetchAll($select);

            foreach ($tickets as $ticket) {
                $author       = $this->_members::generateMemberName($ticket);
                $arrUserInfo  = $this->_members->getMembersInfo(array($ticket['company_member_id']), false);
                $user         = !empty($arrUserInfo) ? $arrUserInfo[0][1] : '';
                $status       = ucwords(str_replace('_', ' ', $ticket['status'] ?? ''));
                $contactedBy  = ucwords(str_replace('_', ' ', $ticket['contacted_by'] ?? ''));
                $arrRecords[] = array(
                    'ticket_id'    => $ticket['ticket_id'],
                    'company_name' => $ticket['companyName'],
                    'ticket'       => nl2br($ticket['ticket'] ?? ''),
                    'real_ticket'  => $ticket['ticket'],
                    'date'         => $this->_settings->formatDate($ticket['create_date'], true, 'Y-m-d H:i:s'),
                    'real_date'    => strtotime($ticket['create_date']),
                    'author'       => $author['full_name'],
                    'user'         => $user,
                    'contacted_by' => $contactedBy,
                    'status'       => $status
                );
            }

            $totalCount = count($arrRecords);

            // Sort collected data
            $dir     = strtoupper($dir) == 'ASC' ? SORT_ASC : SORT_DESC;
            $sort    = empty($sort) ? 'real_date' : $sort;
            $sort    = $sort == 'ticket' ? 'real_ticket' : $sort;
            $sort    = $sort == 'date' ? 'real_date' : $sort;
            $arrSort = array();
            foreach ($arrRecords as $key => $row) {
                $arrSort[$key] = strtolower($row[$sort] ?? '');
            }
            array_multisort($arrSort, $dir, SORT_STRING, $arrRecords);

            // Return only one page
            $arrRecords = array_slice($arrRecords, $start, $limit);

            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return array('success' => $booSuccess, 'rows' => $arrRecords, 'totalCount' => $totalCount);
    }

    public function updateTicket($action, $ticketId, $ticket, $status, $contactedBy, $companyId, $companyMemberId = 0, $authorId = 0)
    {
        //get author ID
        $authorId = (empty($authorId) ? $this->_auth->getCurrentUserId() : $authorId);

        $arrData = array(
            'ticket'       => $ticket,
            'status'       => $status,
            'contacted_by' => $contactedBy
        );
        if (!empty($companyMemberId)) {
            $arrData['company_member_id'] = (int)$companyMemberId;
        } else {
            $arrData['company_member_id'] = null;
        }

        if ($action == 'add') {
            $arrData['author_id']   = (int)$authorId;
            $arrData['create_date'] = date('c');

            if (is_numeric($companyId)) {
                $arrData['company_id'] = (int)$companyId;
            }

            $result = $this->_db2->insert('tickets', $arrData);
        } else {
            $result = $this->_db2->update('tickets', $arrData, ['ticket_id' => $ticketId]);
        }

        return $result > 0;
    }

    public function deleteTickets($tickets)
    {
        try {
            $tickets = (array) $tickets;
            if ($tickets) {
                $this->_db2->delete('tickets', ['ticket_id' => $tickets]);
            }
            $booSuccess = true;
        } catch (Exception $e) {
            $booSuccess = false;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    /**
     * Save tickets on company update action
     * @param $arrChangesData
     * @param $companyId
     * @return bool
     */
    public function addTicketsWithCompanyChanges($arrChangesData, $companyId)
    {
        $booSuccess = false;
        try {
            if (!is_array($arrChangesData) || !count($arrChangesData)) {
                return false;
            }
            foreach ($arrChangesData as $arrChangeInfo) {
                $column   = $arrChangeInfo['log_changed_column'];
                $oldValue = $arrChangeInfo['log_column_old_value'];
                $newValue = $arrChangeInfo['log_column_new_value'] ?? '';
                switch ($column) {
                    case 'company_template_id':
                    case 'password_change_date':
                        continue 2;

                    case 'next_billing_date':
                        $newValue = explode('T', $newValue);
                        $newValue = $newValue[0];
                        if ($newValue == $oldValue) {
                            continue 2;
                        }
                        break;

                    case 'subscription':
                        $oPackages = $this->_company->getPackages();
                        $oldValue  = $oPackages->getSubscriptionNameById($oldValue);
                        $newValue  = $oPackages->getSubscriptionNameById($newValue);
                        break;

                    case 'payment_term':
                        $oSubscriptions = $this->_company->getCompanySubscriptions();
                        $oldValue       = $oSubscriptions->getPaymentTermNameById($oldValue);
                        $newValue       = $oSubscriptions->getPaymentTermNameById($newValue);
                        break;

                    case 'country':
                        $oldValue = $this->_country->getCountryName($oldValue);
                        $newValue = $this->_country->getCountryName($newValue);
                        break;

                    case 'Status':
                        $oldValue = $this->_company->getCompanyStringStatusById($oldValue);
                        $newValue = $this->_company->getCompanyStringStatusById($newValue);
                        break;

                    default:
                        break;
                }

                if ($oldValue == '') {
                    $oldValue = '*blank*';
                }
                if ($newValue == '') {
                    $newValue = '*blank*';
                }

                $companyMemberId = 0;
                $username        = '';
                $userText        = '';

                if (!empty($arrChangeInfo['log_username'])) {
                    $companyMemberId = $arrChangeInfo['log_company_member_id'];
                    $username        = $arrChangeInfo['log_username'];
                    $userText        = ' of user with username "' . $username . '"';
                }

                if ($column == 'password' || $column == 'vevo_password') {
                    $ticket = $column . ' of user with username "' . $username . '" was changed';
                } else {
                    $ticket = $column . $userText . ' was changed from "' . $oldValue . '" to "' . $newValue . '"';
                }
                $status      = 'resolved';
                $contactedBy = 'system';
                $booSuccess  = $this->updateTicket('add', null, $ticket, $status, $contactedBy, $companyId, $companyMemberId);
            }
        } catch (Exception $e) {
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $booSuccess;
    }

    public function changeStatus($ticketId)
    {
        $newStatus = 'resolved';

        $select = (new Select())
            ->from(array('t' => 'tickets'))
            ->where(['ticket_id' => (int)$ticketId]);

        $ticket = $this->_db2->fetchRow($select);

        if ($ticket['status'] == 'resolved') {
            $newStatus = 'not_resolved';
        }

        return $this->_db2->update('tickets', ['status' => $newStatus], ['ticket_id' => $ticketId]);
    }
}
