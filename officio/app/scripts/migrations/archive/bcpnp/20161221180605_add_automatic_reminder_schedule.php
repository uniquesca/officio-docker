<?php

use Phinx\Migration\AbstractMigration;

class AddAutomaticReminderSchedule extends AbstractMigration
{
    public function up()
    {
        $this->execute("CREATE TABLE `automatic_reminder_schedule` (
        	`automatic_reminder_schedule_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        	`automatic_reminder_id` INT(11) UNSIGNED NULL DEFAULT NULL,
        	`automatic_reminder_settings` TEXT,
        	`automatic_reminder_trigger_type_id` INT(11) UNSIGNED NOT NULL,
        	`automatic_reminder_schedule_message` TEXT,
        	`automatic_reminder_schedule_due_on_date` DATE NULL DEFAULT NULL,
        	PRIMARY KEY (`automatic_reminder_schedule_id`),
        	INDEX `FK_automatic_reminder_schedule_automatic_reminders` (`automatic_reminder_id`),
        	INDEX `FK_automatic_reminder_schedule_trigger_types` (`automatic_reminder_trigger_type_id`),
        	CONSTRAINT `FK_automatic_reminder_schedule_trigger_types` FOREIGN KEY (`automatic_reminder_trigger_type_id`) REFERENCES `automatic_reminder_trigger_types` (`automatic_reminder_trigger_type_id`) ON UPDATE CASCADE ON DELETE CASCADE,
        	CONSTRAINT `FK_automatic_reminder_schedule_automatic_reminders` FOREIGN KEY (`automatic_reminder_id`) REFERENCES `automatic_reminders` (`automatic_reminder_id`) ON UPDATE CASCADE ON DELETE CASCADE
        )
        COMMENT='Reminders, that must be processed in the future will be saved in this table. When the date is due - all assigned actions will be processed and record will be deleted from this table.'
        COLLATE='utf8_general_ci'
        ENGINE=InnoDB;");
    }

    public function down()
    {
        $this->execute("DROP TABLE IF EXISTS `automatic_reminder_schedule`;");
    }
}
