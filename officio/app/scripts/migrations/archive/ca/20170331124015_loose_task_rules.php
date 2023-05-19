<?php

use Officio\Migration\AbstractMigration;

class LooseTaskRules extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_details` ADD COLUMN `loose_task_rules` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `enable_case_management`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_details` DROP COLUMN `loose_task_rules`;");
    }
}
