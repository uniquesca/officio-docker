<?php

use Officio\Migration\AbstractMigration;

class AddSystemColumnToNotes extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `u_notes` ADD COLUMN `is_system` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `type`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `u_notes` DROP COLUMN `is_system`;");
    }
}
