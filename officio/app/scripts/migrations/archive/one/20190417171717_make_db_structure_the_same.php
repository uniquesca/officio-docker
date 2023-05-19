<?php

use Phinx\Migration\AbstractMigration;

class MakeDbStructureTheSame extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `company_questionnaires` DROP FOREIGN KEY `FK_company_questionnaires_3`;");
        $this->execute("ALTER TABLE `company_questionnaires` ADD CONSTRAINT `FK_company_questionnaires_3` FOREIGN KEY (`q_updated_by`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;");

        $this->execute("ALTER TABLE `form_default` DROP FOREIGN KEY `FK_form_default_members`;");
        $this->execute("ALTER TABLE `form_default` ADD CONSTRAINT `FK_form_default_members` FOREIGN KEY (`updated_by`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;");

        $this->execute("ALTER TABLE `searches` DROP FOREIGN KEY `FK_searches_members`;");
        $this->execute("ALTER TABLE `searches` ADD CONSTRAINT `FK_searches_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;");

        $this->execute("ALTER TABLE `u_assigned_deposits` DROP FOREIGN KEY `FK_u_assigned_deposits_members`;");
        $this->execute("ALTER TABLE `u_assigned_deposits` ADD CONSTRAINT `FK_u_assigned_deposits_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;");

        $this->execute("ALTER TABLE `u_assigned_withdrawals` DROP FOREIGN KEY `FK_u_assigned_withdrawals_members`;");
        $this->execute("ALTER TABLE `u_assigned_withdrawals` ADD CONSTRAINT `FK_u_assigned_withdrawals_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;");

        $this->execute("ALTER TABLE `u_deposit_types` DROP FOREIGN KEY `FK_u_deposit_types_members`;");
        $this->execute("ALTER TABLE `u_deposit_types` ADD CONSTRAINT `FK_u_deposit_types_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;");

        $this->execute("ALTER TABLE `u_destination_types` DROP FOREIGN KEY `FK_u_destination_types_members`;");
        $this->execute("ALTER TABLE `u_destination_types` ADD CONSTRAINT `FK_u_destination_types_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;");

        $this->execute("ALTER TABLE `company_details` ALTER `company_id` DROP DEFAULT;");
        $this->execute("ALTER TABLE `company_details` CHANGE COLUMN `company_id` `company_id` BIGINT(20) NOT NULL FIRST;");
        $this->execute("ALTER TABLE `company_details`
        	CHANGE COLUMN `remember_default_fields` `remember_default_fields` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `purged_details`,
        	CHANGE COLUMN `marketplace_module_enabled` `marketplace_module_enabled` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Toggle for the Marketplace module' AFTER `time_tracker_enabled`;");

    }

    public function down()
    {
    }
}