<?php

use Phinx\Migration\AbstractMigration;

class MakeDbStructureTheSame extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `applicant_form_fields_access` ALTER `role_id` DROP DEFAULT;");
        $this->execute("ALTER TABLE `applicant_form_fields_access` CHANGE COLUMN `role_id` `role_id` INT(11) NOT NULL FIRST;");
        $this->execute("ALTER TABLE `company_prospects_data_categories` DROP FOREIGN KEY `FK_company_prospects_categories2`;");
        $this->execute("ALTER TABLE `company_prospects_data_categories` ADD CONSTRAINT `FK_company_prospects_categories2` FOREIGN KEY (`prospect_category_id`) REFERENCES `company_prospects_categories` (`prospect_category_id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        $this->execute("ALTER TABLE `company_questionnaires` DROP FOREIGN KEY `FK_company_questionnaires_3`;");
        $this->execute("ALTER TABLE `company_questionnaires` ADD CONSTRAINT `FK_company_questionnaires_3` FOREIGN KEY (`q_updated_by`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;");

        $this->execute("ALTER TABLE `form_default` DROP FOREIGN KEY `FK_form_default_members`;");
        $this->execute("ALTER TABLE `form_default` ADD CONSTRAINT `FK_form_default_members` FOREIGN KEY (`updated_by`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;");

        $this->execute("ALTER TABLE `prospects` DROP FOREIGN KEY `FK_prospects_company`;");
        $this->execute("ALTER TABLE `prospects` ADD CONSTRAINT `FK_prospects_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE SET NULL;");

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

        $this->execute('DROP TABLE IF EXISTS `agents`');
    }

    public function down()
    {
    }
}