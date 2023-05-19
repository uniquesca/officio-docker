<?php

use Officio\Migration\AbstractMigration;

class LogoutLogsUpdates extends AbstractMigration
{
    public function up()
    {
        // Took 37s on the local server
        $this->execute("ALTER TABLE `members` ADD COLUMN `last_access` INT(11) NULL DEFAULT NULL AFTER `password_change_date`;");
        $this->execute("ALTER TABLE `members` ADD COLUMN `logged_in` ENUM('Y','N') NULL DEFAULT NULL AFTER `last_access`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `members` DROP COLUMN `last_access`;");
        $this->execute("ALTER TABLE `members` DROP COLUMN `logged_in`;");
    }
}