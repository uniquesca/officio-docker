<?php

use Phinx\Migration\AbstractMigration;

class AddAutomaticReminderActions extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (1120, 'superadmin', 'automatic-reminder-actions', '', 1);");
        $this->execute("CREATE TABLE `automatic_reminder_action_types` (
                            `automatic_reminder_action_type_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                            `automatic_reminder_action_type_name` VARCHAR(255) NULL DEFAULT NULL,
                            PRIMARY KEY (`automatic_reminder_action_type_id`)
                        )
                        COLLATE='utf8_general_ci'
                        ENGINE=InnoDB;");
        $this->execute("INSERT INTO `automatic_reminder_action_types` (`automatic_reminder_action_type_id`, `automatic_reminder_action_type_name`) VALUES (1, 'Change field value');");
        $this->execute("CREATE TABLE `automatic_reminder_actions` (
                            `automatic_reminder_action_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                            `automatic_reminder_id` INT(11) UNSIGNED NULL DEFAULT NULL,
                            `automatic_reminder_action_type_id` INT(11) UNSIGNED NOT NULL,
                            `automatic_reminder_action_settings` TEXT NULL,
                            `automatic_reminder_action_create_date` DATE NULL DEFAULT NULL,
                            PRIMARY KEY (`automatic_reminder_action_id`),
                            INDEX `FK_automatic_reminder_actions_automatic_reminders` (`automatic_reminder_id`),
                            INDEX `FK_automatic_reminder_actions_automatic_reminder_action_types` (`automatic_reminder_action_type_id`),
                            CONSTRAINT `FK_automatic_reminder_actions_automatic_reminders` FOREIGN KEY (`automatic_reminder_id`) REFERENCES `automatic_reminders` (`automatic_reminder_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                            CONSTRAINT `FK_automatic_reminder_actions_automatic_reminder_action_types` FOREIGN KEY (`automatic_reminder_action_type_id`) REFERENCES `automatic_reminder_action_types` (`automatic_reminder_action_type_id`) ON UPDATE CASCADE ON DELETE CASCADE
                        )
                        COLLATE='utf8_general_ci'
                        ENGINE=InnoDB;");
    }

    public function down()
    {
        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1120 AND `module_id`='superadmin' AND `resource_id`='automatic-reminder-actions' AND `resource_privilege`='';");
        $this->execute("DROP TABLE IF EXISTS `automatic_reminder_actions`;");
        $this->execute("DROP TABLE IF EXISTS `automatic_reminder_action_types`;");
    }
}
