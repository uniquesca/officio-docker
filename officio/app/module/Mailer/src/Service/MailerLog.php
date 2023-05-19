<?php

namespace Mailer\Service;

use Exception;
use Officio\Common\Service\BaseService;
use Officio\Common\SubServiceInterface;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class MailerLog extends BaseService implements SubServiceInterface
{

    /** @var Mailer */
    protected $_parent;

    public function setParent($parent)
    {
        $this->_parent = $parent;
    }

    public function getParent()
    {
        return $this->_parent;
    }

    public function insertIntoEmlCronTable($accountsCount, $start)
    {
        try {
            $cronDetails = array(
                'accounts_count' => $accountsCount,
                'start'          => $start
            );

            $cronId = $this->_db2->insert('eml_cron', $cronDetails);
        } catch (Exception $e) {
            $cronId = 0;
            $this->_log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());
        }

        return $cronId;
    }

    public function insertIntoEmlCronAccountsTable($cronId, $accountId, $start)
    {
        $accountDetails = array(
            'cron_id'    => $cronId,
            'account_id' => $accountId,
            'start'      => $start,
            'status'     => 'in progress'
        );

        return $this->_db2->insert('eml_cron_accounts', $accountDetails);
    }

    public function updateEmlCronAccountsTable($id)
    {
        $data = array(
            'end'    => time(),
            'status' => 'complete'
        );

        $this->_db2->update('eml_cron_accounts', $data, ['id' => $id]);
    }

}
