<?php

use Phinx\Migration\AbstractMigration;

class PrepareMembersTableForPasswordsRehash extends AbstractMigration
{
    public function up()
    {
        // No limits
        set_time_limit(0);
        ini_set('memory_limit', -1);

        $this->execute("UPDATE members SET `password` = '' WHERE `username` = '' AND `password` != ''");
        $this->execute('ALTER TABLE `members` ADD COLUMN `password_used_for_hash` VARCHAR(200) NULL DEFAULT NULL AFTER `password`');
        $this->execute('ALTER TABLE `members` ADD COLUMN `password_hash` TEXT NULL DEFAULT NULL AFTER `password_used_for_hash`');
        $this->execute('UPDATE members SET password_used_for_hash = password');
    }

    public function down()
    {
    }
}
