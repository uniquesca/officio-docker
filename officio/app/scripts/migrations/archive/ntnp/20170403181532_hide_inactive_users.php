<?php

use Phinx\Migration\AbstractMigration;

class HideInactiveUsers extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_details` ADD COLUMN `hide_inactive_users` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `loose_task_rules`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_details` DROP COLUMN `hide_inactive_users`;");
    }
}
