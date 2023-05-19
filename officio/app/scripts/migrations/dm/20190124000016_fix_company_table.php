<?php

use Phinx\Migration\AbstractMigration;

class FixCompanyTable extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `client_form_fields` DROP FOREIGN KEY FK_client_form_fields_company;");
        $this->execute("ALTER TABLE `client_form_fields` ALTER `company_id` DROP DEFAULT;");
        $this->execute("ALTER TABLE `client_form_fields` CHANGE COLUMN `company_id` `company_id` BIGINT(20) NOT NULL AFTER `field_id`;");


        $this->execute("ALTER TABLE `clients` ROW_FORMAT=DEFAULT;");
        $this->execute("ALTER TABLE `members_divisions` ROW_FORMAT=DEFAULT;");
        $this->execute("ALTER TABLE `members_last_access` ROW_FORMAT=DEFAULT;");

        $this->execute("ALTER TABLE `company_prospects_templates` DROP FOREIGN KEY `FK_company_prospects_templates_members`;");
        $this->execute("ALTER TABLE `company_prospects_templates` CHANGE COLUMN `author_id` `author_id` BIGINT(20) NULL AFTER `company_id`, ADD CONSTRAINT `FK_company_prospects_templates_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;");

        $this->execute("ALTER TABLE `u_folders` DROP FOREIGN KEY `FK_u_folders_members`;");
        $this->execute("ALTER TABLE `u_folders` ADD CONSTRAINT `FK_u_folders_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE SET NULL;");

        $this->execute("ALTER TABLE `members_relations` CHANGE COLUMN `applicant_group_id` `applicant_group_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `child_member_id`, CHANGE COLUMN `row` `row` TINYINT(2) UNSIGNED NULL DEFAULT NULL AFTER `applicant_group_id`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `client_form_fields` ALTER `company_id` DROP DEFAULT;");
        $this->execute("ALTER TABLE `client_form_fields` CHANGE COLUMN `company_id` `company_id` BIGINT(20) NULL AFTER `field_id`;");

        $this->execute("ALTER TABLE `company_prospects_templates` DROP FOREIGN KEY `FK_company_prospects_templates_members`;");
        $this->execute("ALTER TABLE `company_prospects_templates` ALTER `author_id` DROP DEFAULT;");
        $this->execute("ALTER TABLE `company_prospects_templates` CHANGE COLUMN `author_id` `author_id` BIGINT(20) NOT NULL AFTER `company_id`, ADD CONSTRAINT `FK_company_prospects_templates_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        $this->execute("ALTER TABLE `u_folders` DROP FOREIGN KEY `FK_u_folders_members`;");
        $this->execute("ALTER TABLE `u_folders` ADD CONSTRAINT `FK_u_folders_members` FOREIGN KEY (`author_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE ON DELETE CASCADE;");

        $this->execute("ALTER TABLE `members_relations` CHANGE COLUMN `applicant_group_id` `applicant_group_id` INT(11) UNSIGNED NULL DEFAULT '0' AFTER `child_member_id`, CHANGE COLUMN `row` `row` TINYINT(2) UNSIGNED NULL DEFAULT '0' AFTER `applicant_group_id`;");
    }
}