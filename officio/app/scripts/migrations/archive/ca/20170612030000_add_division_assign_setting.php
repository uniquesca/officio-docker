<?php

use Officio\Migration\AbstractMigration;

class AddDivisionAssignSetting extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `divisions` ADD COLUMN `access_assign_to` ENUM('Y','N') NULL DEFAULT 'N' AFTER `access_permanent`");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `divisions` DROP COLUMN `access_assign_to`;");
    }
}
