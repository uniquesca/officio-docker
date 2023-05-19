<?php

use Phinx\Migration\AbstractMigration;

class ChangeProspectStatus extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_prospects` ADD COLUMN `new_status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active' AFTER `status`;");
        $this->execute("UPDATE `company_prospects` SET `new_status` = 'inactive' WHERE `status` = 0;");
        $this->execute("ALTER TABLE `company_prospects` DROP COLUMN `status`;");
        $this->execute("ALTER TABLE `company_prospects` CHANGE COLUMN `new_status` `status` ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active' AFTER `notes`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `company_prospects` ADD COLUMN `old_status` TINYINT(1) NOT NULL DEFAULT '1' AFTER `status`;");
        $this->execute("UPDATE `company_prospects` SET `old_status` = 0 WHERE `status` IN ('inactive', 'suspended');");
        $this->execute("ALTER TABLE `company_prospects` DROP COLUMN `status`;");
        $this->execute("ALTER TABLE `company_prospects` CHANGE COLUMN `old_status` `status` TINYINT(1) NOT NULL DEFAULT '1' AFTER `notes`;");
    }
}