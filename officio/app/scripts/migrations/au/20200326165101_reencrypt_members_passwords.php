<?php

use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class ReencryptMembersPasswords extends AbstractMigration
{
    public function up()
    {
        try {
            $table = $this->table('members');
            if (!$table->hasColumn('password_new')) {
                throw new Exception('Is the previous migration done');
            }

            $this->execute('ALTER TABLE `members` DROP COLUMN `password`;');
            $this->execute('ALTER TABLE `members` CHANGE COLUMN `password_new` `password` TEXT NULL DEFAULT NULL AFTER `username`;');
        } catch (\Exception $e) {
            $application    = self::getApplication();
            $serviceManager = $application->getServiceManager();
            /** @var Log $log */
            $log =$serviceManager->get('log');
            $log->debugErrorToFile($e->getMessage(), $e->getTraceAsString());

            throw $e;
        }
    }

    public function down()
    {
    }
}
