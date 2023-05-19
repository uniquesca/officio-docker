<?php

use Phinx\Migration\AbstractMigration;

class AddAutomaticReminderConditions extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (1120, 'superadmin', 'automatic-reminder-conditions', '', 1);");

        $this->execute("CREATE TABLE `automatic_reminder_condition_types` (
                            `automatic_reminder_condition_type_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                            `automatic_reminder_condition_type_internal_id` VARCHAR(255) NOT NULL,
                            `automatic_reminder_condition_type_name` VARCHAR(255) NULL DEFAULT NULL,
                            `automatic_reminder_condition_type_order` TINYINT(3) UNSIGNED NULL DEFAULT NULL,
                            PRIMARY KEY (`automatic_reminder_condition_type_id`)
                        )
                        COMMENT='List of supported condition types to know how to process/check the conditions.'
                        COLLATE='utf8_general_ci'
                        ENGINE=InnoDB;");
        
        $this->execute("INSERT INTO `automatic_reminder_condition_types` (`automatic_reminder_condition_type_internal_id`, `automatic_reminder_condition_type_name`, `automatic_reminder_condition_type_order`) VALUES ('CLIENT_PROFILE', 'Based on a Date on the Client Profiles (i.e. Employers, or Individuals)', 0);");
        $this->execute("INSERT INTO `automatic_reminder_condition_types` (`automatic_reminder_condition_type_internal_id`, `automatic_reminder_condition_type_name`, `automatic_reminder_condition_type_order`) VALUES ('PROFILE', 'Based on a date on \"Case Details\"', 1);");
        $this->execute("INSERT INTO `automatic_reminder_condition_types` (`automatic_reminder_condition_type_internal_id`, `automatic_reminder_condition_type_name`, `automatic_reminder_condition_type_order`) VALUES ('FILESTATUS', 'Based on the Case Status on \"Case Details\"', 2);");
        $this->execute("INSERT INTO `automatic_reminder_condition_types` (`automatic_reminder_condition_type_internal_id`, `automatic_reminder_condition_type_name`, `automatic_reminder_condition_type_order`) VALUES ('BASED_ON_FIELD', 'Based on the field', 3);");
        $this->execute("INSERT INTO `automatic_reminder_condition_types` (`automatic_reminder_condition_type_internal_id`, `automatic_reminder_condition_type_name`, `automatic_reminder_condition_type_order`) VALUES ('CHANGED_FIELD', 'Changed field is', 4);");
        $this->execute("INSERT INTO `automatic_reminder_condition_types` (`automatic_reminder_condition_type_internal_id`, `automatic_reminder_condition_type_name`, `automatic_reminder_condition_type_order`) VALUES ('CASE_TYPE', 'Based on the case type', 5);");

        $this->execute("CREATE TABLE `automatic_reminder_conditions` (
                            `automatic_reminder_condition_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                            `company_id` BIGINT(20) NOT NULL,
                            `automatic_reminder_id` INT(11) UNSIGNED NULL DEFAULT NULL,
                            `automatic_reminder_condition_type_id` INT(11) UNSIGNED NOT NULL,
                            `automatic_reminder_condition_settings` TEXT NULL,
                            `automatic_reminder_condition_create_date` DATE NULL DEFAULT NULL,
                            PRIMARY KEY (`automatic_reminder_condition_id`),
                            INDEX `FK_automatic_reminder_conditions_automatic_reminders` (`automatic_reminder_id`),
                            CONSTRAINT `FK_automatic_reminder_conditions_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                            CONSTRAINT `FK_automatic_reminder_conditions_automatic_reminders` FOREIGN KEY (`automatic_reminder_id`) REFERENCES `automatic_reminders` (`automatic_reminder_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                            CONSTRAINT `FK_automatic_reminder_conditions_condition_types` FOREIGN KEY (`automatic_reminder_condition_type_id`) REFERENCES `automatic_reminder_condition_types` (`automatic_reminder_condition_type_id`) ON UPDATE CASCADE ON DELETE CASCADE
                        )
                        COMMENT='List of conditions that must be true to run automatic task\'s actions.'
                        COLLATE='utf8_general_ci'
                        ENGINE=InnoDB;");
    }

    public function down()
    {
        $this->execute("DROP TABLE IF EXISTS `automatic_reminder_conditions`;");
        $this->execute("DROP TABLE IF EXISTS `automatic_reminder_condition_types`;");

        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1120 AND `module_id`='superadmin' AND `resource_id`='automatic-reminder-conditions' AND `resource_privilege`='';");
    }
}
