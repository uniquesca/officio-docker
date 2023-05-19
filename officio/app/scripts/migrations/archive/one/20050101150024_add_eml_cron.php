<?php

use Phinx\Migration\AbstractMigration;

class AddEmlCron extends AbstractMigration
{
    public function up()
    {
        // Took 189.3168s on local server

        /* ACHTUNG !!!!!!! */
        $this->execute("UPDATE eml_attachments SET path = substring_index(path, '.emails/', -1);");
        /* ACHTUNG !!!!!!! */

        $this->execute("ALTER TABLE `eml_folders` ADD COLUMN `id_mapping_folder` INT(11) UNSIGNED NULL DEFAULT '0' AFTER `selectable`;");
        $this->execute("ALTER TABLE `eml_folders` ADD COLUMN `visible` TINYINT(1) UNSIGNED NOT NULL DEFAULT '1' AFTER `id_mapping_folder`;");

        $this->execute("ALTER TABLE `applicant_form_fields` CHANGE COLUMN `type` `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields') NOT NULL DEFAULT 'text' AFTER `applicant_field_unique_id`;");

        $this->execute("CREATE TABLE `eml_cron` (
                    `id` INT(11) UNSIGNED AUTO_INCREMENT NOT NULL,
                    `accounts_count` INT(11) UNSIGNED NOT NULL,
                    `start` INT(11) UNSIGNED NOT NULL,
                    PRIMARY KEY (`Id`)
                )
                COLLATE='utf8_general_ci'
                ENGINE=InnoDB;");

        $this->execute("CREATE TABLE `eml_cron_accounts` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `cron_id` INT(11) UNSIGNED NULL DEFAULT NULL,
                    `account_id` INT(11) UNSIGNED NULL DEFAULT NULL,
                    `start` INT(11) UNSIGNED NULL DEFAULT NULL,
                    `end` INT(11) UNSIGNED NULL DEFAULT NULL,
                    `status` TEXT NULL,
                    PRIMARY KEY (`id`),
                    INDEX `account_id` (`account_id`),
                    INDEX `FK_eml_cron_accounts_eml_cron` (`cron_id`),
                    CONSTRAINT `FK_eml_cron_accounts_eml_cron` FOREIGN KEY (`cron_id`) REFERENCES `eml_cron` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
                    CONSTRAINT `FK_eml_cron_accounts_eml_accounts` FOREIGN KEY (`account_id`) REFERENCES `eml_accounts` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
                )
                COLLATE='utf8_general_ci'
                ENGINE=InnoDB;");

        $this->execute('ALTER TABLE `eml_accounts` ADD COLUMN `last_rabbit_push` INT(11) UNSIGNED NULL AFTER `timezone`;');
        $this->execute('ALTER TABLE `eml_accounts` ADD COLUMN `last_rabbit_pull` INT(11) UNSIGNED NULL AFTER `last_rabbit_push`;');
        $this->execute("ALTER TABLE `eml_accounts` ADD COLUMN `is_checking` INT(1) UNSIGNED NULL DEFAULT '0' AFTER `last_rabbit_pull`;");
        $this->execute('ALTER TABLE `eml_attachments` ADD COLUMN `size` INT(11) NULL AFTER `original_file_name`;');

        $this->execute('ALTER TABLE `eml_attachments` ADD COLUMN `part_info` TEXT NOT NULL AFTER `size`;');
        $this->execute('ALTER TABLE `eml_attachments` ADD COLUMN `is_downloaded` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER `part_info`;');
        
        $this->execute('ALTER TABLE `eml_accounts` ADD COLUMN `delimiter` VARCHAR(1) NULL DEFAULT NULL AFTER `is_checking`;');
        $this->execute("ALTER TABLE `client_form_groups` ADD COLUMN `collapsed` ENUM('Y','N') NOT NULL DEFAULT 'Y' AFTER `cols_count`;");
        $this->execute("UPDATE `client_form_groups` SET `collapsed` = 'N' where `order` = 0;");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_use_in_search`='N' WHERE  `q_field_type` = 'label';");
        $this->execute('ALTER TABLE `eml_accounts` ADD COLUMN `checking_status` VARCHAR(255) NULL DEFAULT NULL AFTER `is_checking`;');
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES
                (70,'templates','index','view-pdf',1),
                (70,'templates','index','show-pdf',1),
                (140,'templates','index','view-pdf',1),
                (140,'templates','index','show-pdf',1);");

        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) SELECT r.role_parent_id, 210 FROM `acl_roles` as r WHERE r.role_type IN ('superadmin');");
        $this->execute("INSERT IGNORE INTO `acl_role_access` (`role_id`, `rule_id`) VALUES ('superadmin', 100);");
        $this->execute("ALTER TABLE `company_details` ADD COLUMN `advanced_search_rows_max_count` INT(11) NOT NULL DEFAULT '3' AFTER `case_number_settings`;");
        $this->execute("ALTER TABLE `applicant_form_fields` CHANGE COLUMN `type` `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields','auto_calculated') NOT NULL DEFAULT 'text' AFTER `applicant_field_unique_id`;");
        $this->execute("ALTER TABLE `company_details` ADD COLUMN `log_client_changes_enabled` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Toggle to save client profile changes in log' AFTER `employers_module_enabled`;");
    }

    public function down()
    {
    }
}
