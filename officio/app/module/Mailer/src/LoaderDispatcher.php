<?php

namespace Mailer;

use Officio\Common\Json;
use Officio\Email\LoaderDispatcherInterface;
use Officio\Email\Models\MailAccount;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class LoaderDispatcher implements LoaderDispatcherInterface
{

    /** @var MailAccount */
    protected $_mailAccount;

    public function __construct(MailAccount $account)
    {
        $this->_mailAccount = $account;
    }

    public function changeStatus($currentFetchedEmail, $newEmailsAmount)
    {
        $strStatus = sprintf(
            'Downloading %d %s from %d...',
            $currentFetchedEmail,
            $currentFetchedEmail == 1 ? 'email' : 'emails',
            $newEmailsAmount
        );

        $progressPercent = ($currentFetchedEmail / $newEmailsAmount) * 100;
        if ($progressPercent == 100) {
            $progressPercent = 99;
        }

        $this->change(
            $strStatus,
            $progressPercent
        );
    }

    /**
     * @param string $status
     * @param int $progress
     * @param null $unreadEmailsCount
     * @param bool $booError
     * @param bool $booRefreshFoldersList
     */
    public function change($status, $progress = 0, $unreadEmailsCount = null, $booError = false, $booRefreshFoldersList = false)
    {
        $arrResult = array(
            's'           => $status,
            'p'           => $progress,
            'c'           => $unreadEmailsCount,
            'e'           => (int)$booError,
            'r'           => (int)$booRefreshFoldersList,
            'update_time' => time()
        );

        $this->_mailAccount->updateAccountDetails(array('checking_status' => serialize($arrResult)));

        self::outputResult($arrResult);
    }

    public static function outputResult($arrResult)
    {
        if (isset($arrResult['update_time'])) {
            unset($arrResult['update_time']);
        }

        echo '<script>parent.MailChecker.updateStatus(' . Json::encode($arrResult) . ')</script>';
        echo str_repeat(" ", 1024), "\n";
    }
}