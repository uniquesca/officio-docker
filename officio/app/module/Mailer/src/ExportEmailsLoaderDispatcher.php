<?php

namespace Mailer;

use Officio\Common\Json;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class ExportEmailsLoaderDispatcher
{

    public static function changeStatus($currentExportedEmail, $amountOfNewEmails)
    {
        $strStatus = sprintf(
            'Exporting %d %s from %d...',
            $currentExportedEmail,
            $currentExportedEmail == 1 ? 'email' : 'emails',
            $amountOfNewEmails
        );

        $progressPercent = ($currentExportedEmail / $amountOfNewEmails) * 100;
        if ($progressPercent == 100) {
            $progressPercent = 99;
        }

        self::change(
            $strStatus,
            $progressPercent
        );
    }

    /**
     * @param string $status
     * @param int $progress
     * @param bool $booError
     */
    public static function change($status, $progress = 0, $booError = false)
    {
        $arrResult = array(
            's' => $status,
            'p' => $progress,
            'e' => (int)$booError
        );

        self::outputResult($arrResult);
    }

    public static function outputResult($arrResult)
    {
        echo '<script>parent.ExportMailChecker.updateStatus(' . Json::encode($arrResult) . ')</script>';
    }
}