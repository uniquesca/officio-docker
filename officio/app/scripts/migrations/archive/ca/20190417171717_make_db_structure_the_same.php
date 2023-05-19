<?php

use Officio\Migration\AbstractMigration;

class MakeDbStructureTheSame extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_details` DROP FOREIGN KEY `FK_company_details_company`;");
        $this->execute("ALTER TABLE `company_details` ALTER `company_id` DROP DEFAULT;");
        $this->execute("ALTER TABLE `company_details` CHANGE COLUMN `company_id` `company_id` BIGINT(20) NOT NULL FIRST;");
        $this->execute('ALTER TABLE `company_details` ADD CONSTRAINT `FK_company_details_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;');

        $this->execute("ALTER TABLE `company_details`
            CHANGE COLUMN `remember_default_fields` `remember_default_fields` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `purged_details`,
            CHANGE COLUMN `marketplace_module_enabled` `marketplace_module_enabled` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Toggle for the Marketplace module' AFTER `time_tracker_enabled`;");
    }

    public function down()
    {
    }
}