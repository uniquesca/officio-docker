<?php

use Officio\Migration\AbstractMigration;
use Officio\Common\Service\Log;

class ReencryptMembersPasswords extends AbstractMigration
{
    public function up()
    {
        try {
            $table = $this->table('members');
            if (!$table->hasColumn('password_hash')) {
                throw new Exception('Is the previous migration done?');
            }

            $this->execute('ALTER TABLE `members` DROP COLUMN `password`;');
            $this->execute('ALTER TABLE `members` DROP COLUMN `password_used_for_hash`;');
            $this->execute('ALTER TABLE `members` CHANGE COLUMN `password_hash` `password` TEXT NULL DEFAULT NULL AFTER `username`;');
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