<?php

use Phinx\Migration\AbstractMigration;

class AddAutomaticReminderTriggers extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `acl_rule_details` (`rule_id`,`module_id`,`resource_id`,`resource_privilege`,`rule_allow`) VALUES (1120, 'superadmin', 'automatic-reminder-triggers', '', 1);");

        $this->execute(
            "CREATE TABLE `automatic_reminder_trigger_types` (
                            `automatic_reminder_trigger_type_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                            `automatic_reminder_trigger_type_internal_id` VARCHAR(255) NOT NULL,
                            `automatic_reminder_trigger_type_name` VARCHAR(255) NULL DEFAULT NULL,
                            `automatic_reminder_trigger_type_order` TINYINT(3) UNSIGNED NULL DEFAULT NULL,
                            PRIMARY KEY (`automatic_reminder_trigger_type_id`)
                        )
                        COMMENT='List of supported trigger types to know how to filter auto tasks.'
                        COLLATE='utf8_general_ci'
                        ENGINE=InnoDB;"
        );

        $this->execute(
            "INSERT INTO `automatic_reminder_trigger_types` (`automatic_reminder_trigger_type_id`, `automatic_reminder_trigger_type_internal_id`, `automatic_reminder_trigger_type_name`, `automatic_reminder_trigger_type_order`) VALUES (1, 'payment_due', 'Payment is due from Retainer Schedule', 1);"
        );
        $this->execute(
            "INSERT INTO `automatic_reminder_trigger_types` (`automatic_reminder_trigger_type_id`, `automatic_reminder_trigger_type_internal_id`, `automatic_reminder_trigger_type_name`, `automatic_reminder_trigger_type_order`) VALUES (2, 'case_mark_form_as_complete', 'Case mark a form as Complete', 2);"
        );
        $this->execute(
            "INSERT INTO `automatic_reminder_trigger_types` (`automatic_reminder_trigger_type_id`, `automatic_reminder_trigger_type_internal_id`, `automatic_reminder_trigger_type_name`, `automatic_reminder_trigger_type_order`) VALUES (3, 'case_uploads_documents', 'Case uploads Documents', 3);"
        );
        $this->execute(
            "INSERT INTO `automatic_reminder_trigger_types` (`automatic_reminder_trigger_type_id`, `automatic_reminder_trigger_type_internal_id`, `automatic_reminder_trigger_type_name`, `automatic_reminder_trigger_type_order`) VALUES (4, 'client_or_case_profile_update', 'Client/Case profile update', 0);"
        );
        $this->execute(
            "INSERT INTO `automatic_reminder_trigger_types` (`automatic_reminder_trigger_type_id`, `automatic_reminder_trigger_type_internal_id`, `automatic_reminder_trigger_type_name`, `automatic_reminder_trigger_type_order`) VALUES (5, 'case_file_status_changed', 'Case File Status changed', 4);"
        );
        $this->execute(
            "INSERT INTO `automatic_reminder_trigger_types` (`automatic_reminder_trigger_type_id`, `automatic_reminder_trigger_type_internal_id`, `automatic_reminder_trigger_type_name`, `automatic_reminder_trigger_type_order`) VALUES (6, 'case_creation', 'Case creation', 5);"
        );
        $this->execute(
            "INSERT INTO `automatic_reminder_trigger_types` (`automatic_reminder_trigger_type_id`, `automatic_reminder_trigger_type_internal_id`, `automatic_reminder_trigger_type_name`, `automatic_reminder_trigger_type_order`) VALUES (7, 'field_value_change', 'Field value change', 6);"
        );
        $this->execute(
            "INSERT INTO `automatic_reminder_trigger_types` (`automatic_reminder_trigger_type_id`, `automatic_reminder_trigger_type_internal_id`, `automatic_reminder_trigger_type_name`, `automatic_reminder_trigger_type_order`) VALUES (8, 'cron', 'Cron', 7);"
        );

        $this->execute(
            "CREATE TABLE `automatic_reminder_triggers` (
                            `automatic_reminder_trigger_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                            `company_id` BIGINT(20) NOT NULL,
                            `automatic_reminder_id` INT(11) UNSIGNED NULL DEFAULT NULL,
                            `automatic_reminder_trigger_type_id` INT(11) UNSIGNED NOT NULL,
                            `automatic_reminder_trigger_create_date` DATE NULL DEFAULT NULL,
                            PRIMARY KEY (`automatic_reminder_trigger_id`),
                            INDEX `FK_automatic_reminder_triggers_automatic_reminders` (`automatic_reminder_id`),
                            CONSTRAINT `FK_automatic_reminder_triggers_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                            CONSTRAINT `FK_automatic_reminder_triggers_automatic_reminders` FOREIGN KEY (`automatic_reminder_id`) REFERENCES `automatic_reminders` (`automatic_reminder_id`) ON UPDATE CASCADE ON DELETE CASCADE,
                            CONSTRAINT `FK_automatic_reminder_triggers_automatic_reminder_trigger_types` FOREIGN KEY (`automatic_reminder_trigger_type_id`) REFERENCES `automatic_reminder_trigger_types` (`automatic_reminder_trigger_type_id`) ON UPDATE CASCADE ON DELETE CASCADE
                        )
                        COMMENT='List of triggers that allow to filter auto tasks to be processed in specific situations (e.g. by cron).'
                        COLLATE='utf8_general_ci'
                        ENGINE=InnoDB;"
        );

        /* @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from(array('mr' => 'automatic_reminders'));

        $arrReminders = $db->fetchAll($select);
        foreach ($arrReminders as $arrReminderInfo) {
            if (empty($arrReminderInfo['trigger'])) {
                if ($arrReminderInfo['type'] == 'FILESTATUS') {
                    // case_file_status_changed
                    $arrTriggerTypes = array(5);
                } else {
                    // client_or_case_profile_update
                    $arrTriggerTypes = array(4, 8);
                }
            } else {
                $arrTriggerTypes = array($arrReminderInfo['trigger']);
            }

            foreach ($arrTriggerTypes as $triggerTypeId) {
                $db->insert(
                    'automatic_reminder_triggers',
                    array(
                        'company_id'                             => $arrReminderInfo['company_id'],
                        'automatic_reminder_id'                  => $arrReminderInfo['automatic_reminder_id'],
                        'automatic_reminder_trigger_type_id'     => $triggerTypeId,
                        'automatic_reminder_trigger_create_date' => $arrReminderInfo['create_date'],
                    )
                );
            }
        }

        // Delete `trigger` column from automatic_reminders table
        $this->execute("ALTER TABLE `automatic_reminders` DROP COLUMN `trigger`");
    }

    public function down()
    {
        // Restore `trigger` column in automatic_reminders table
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `trigger` TINYINT(3) UNSIGNED NULL DEFAULT NULL COMMENT '1 - Payment is due, 2 - Client mark a form as Complete, 3 - Client uploads Documents' AFTER `type`;");

        /* @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from(array('t' => 'automatic_reminder_triggers'));

        $arrReminderTriggers = $db->fetchAll($select);
        foreach ($arrReminderTriggers as $arrReminderTriggerInfo) {
            if (in_array($arrReminderTriggerInfo['automatic_reminder_trigger_type_id'], array(4, 5))) {
                $intTriggerType = 0;
            } else {
                $intTriggerType = $arrReminderTriggerInfo['automatic_reminder_trigger_type_id'];
            }

            $db->update(
                'automatic_reminders',
                array('trigger' => $intTriggerType),
                $db->quoteInto('automatic_reminder_id = ?', $arrReminderTriggerInfo['automatic_reminder_id'], 'INT')
            );
        }

        $this->execute("DROP TABLE IF EXISTS `automatic_reminder_triggers`;");
        $this->execute("DROP TABLE IF EXISTS `automatic_reminder_trigger_types`;");

        $this->execute("DELETE FROM `acl_rule_details` WHERE  `rule_id`=1120 AND `module_id`='superadmin' AND `resource_id`='automatic-reminder-triggers' AND `resource_privilege`='';");
    }
}
