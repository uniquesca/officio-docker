<?php

use Officio\Common\Service\Encryption;
use Officio\Common\Service\Log;
use Officio\Migration\AbstractMigration;

class ReencryptSmtpPasswords extends AbstractMigration
{
    public function up()
    {
        // Full unlim
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        try {
            // this table isn't used anymore
            $this->execute('DROP TABLE IF EXISTS `user_smtp`;');

            /** @var Encryption $encryption */
            $encryption = self::getService(Encryption::class);

            $this->execute("ALTER TABLE `superadmin_smtp` CHANGE COLUMN `smtp_password` `smtp_password` TEXT NULL DEFAULT NULL AFTER `smtp_username`;");

            $statement = $this->getQueryBuilder()
                ->select('*')
                ->from('superadmin_smtp')
                ->whereNotNull('smtp_password')
                ->where(['smtp_password !=' => ''])
                ->execute();

            $arrSmtpUsers = $statement->fetchAll('assoc');

            foreach ($arrSmtpUsers as $arrSmtpUser) {
                $this->getQueryBuilder()
                    ->update('superadmin_smtp')
                    ->set(['smtp_password' => $encryption->encode($arrSmtpUser['smtp_password'])])
                    ->where(['smtp_id' => $arrSmtpUser['smtp_id']])
                    ->execute();

                // Ping, so phinx connection will be alive
                $this->fetchRow('SELECT 1');
            }
        } catch (\Exception $e) {
            /** @var Log $log */
            $log = self::getService('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
    }
}
