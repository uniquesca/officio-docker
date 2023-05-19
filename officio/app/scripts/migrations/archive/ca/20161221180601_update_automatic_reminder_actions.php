<?php

use Officio\Common\Json;
use Officio\Migration\AbstractMigration;

class UpdateAutomaticReminderActions extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `automatic_reminder_action_types` ADD COLUMN `automatic_reminder_action_type_internal_name` VARCHAR(255) NULL DEFAULT NULL AFTER `automatic_reminder_action_type_id`;");
        $this->execute("UPDATE `automatic_reminder_action_types` SET `automatic_reminder_action_type_internal_name`='change_field_value' WHERE  `automatic_reminder_action_type_id`=1;");
        $this->execute("INSERT INTO `automatic_reminder_action_types` (`automatic_reminder_action_type_id`, `automatic_reminder_action_type_internal_name`, `automatic_reminder_action_type_name`) VALUES ('2', 'create_task', 'Create task');");
        $this->execute("INSERT INTO `automatic_reminder_action_types` (`automatic_reminder_action_type_id`, `automatic_reminder_action_type_internal_name`, `automatic_reminder_action_type_name`) VALUES ('3', 'send_email', 'Send email');");

        $this->execute("ALTER TABLE `automatic_reminder_actions` ADD COLUMN `company_id` BIGINT(20) NOT NULL AFTER `automatic_reminder_action_id`;");
        $this->execute("ALTER TABLE `automatic_reminder_actions` ADD CONSTRAINT `FK_automatic_reminder_actions_company` FOREIGN KEY (`company_id`) REFERENCES `company` (`company_id`) ON UPDATE CASCADE ON DELETE CASCADE;");
        $this->execute("UPDATE automatic_reminder_actions AS a INNER JOIN automatic_reminders AS r ON a.automatic_reminder_id = r.automatic_reminder_id SET a.company_id = r.company_id;");

        $statement = $this->getQueryBuilder()
            ->select('*')
            ->from(array('mr' => 'automatic_reminders'))
            ->execute();

        $arrReminders = $statement->fetchAll('assoc');

        foreach ($arrReminders as $arrReminderInfo) {
            // Create "Create task" new action
            switch ($arrReminderInfo['assigned_to']) {
                case 1:
                    $strAssignTo = 'role:' . $arrReminderInfo['assign_to_role_id'];
                    break;

                case 2:
                    $strAssignTo = 'user:' . $arrReminderInfo['assign_to_member_id'];
                    break;

                case 3:
                    $strAssignTo = 'user:all';
                    break;

                default:
                    $strAssignTo = 'assigned:' . $arrReminderInfo['assigned_to'];
                    break;
            }

            $arrTask = array(
                'task_subject'   => stripslashes($arrReminderInfo['reminder']),
                'task_assign_to' => $strAssignTo,
                'task_message'   => stripslashes($arrReminderInfo['message']),
            );

            $this->table('automatic_reminder_actions')
                ->insert(
                    [
                        [
                            'company_id'                            => $arrReminderInfo['company_id'],
                            'automatic_reminder_id'                 => $arrReminderInfo['automatic_reminder_id'],
                            'automatic_reminder_action_type_id'     => 2,
                            'automatic_reminder_action_settings'    => Json::encode($arrTask),
                            'automatic_reminder_action_create_date' => $arrReminderInfo['create_date'],
                        ]
                    ]
                )->save();


            // Create "send email" new action
            if (!empty($arrReminderInfo['template_id'])) {
                $this->table('automatic_reminder_actions')
                    ->insert(
                        [
                            [
                                'company_id'                            => $arrReminderInfo['company_id'],
                                'automatic_reminder_id'                 => $arrReminderInfo['automatic_reminder_id'],
                                'automatic_reminder_action_type_id'     => 3,
                                'automatic_reminder_action_settings'    => Json::encode(array('template_id' => $arrReminderInfo['template_id'])),
                                'automatic_reminder_action_create_date' => $arrReminderInfo['create_date'],
                            ]
                        ]
                    )->save();
            }
        }

        // Delete used columns from automatic_reminders table
        $this->execute("ALTER TABLE `automatic_reminders` DROP FOREIGN KEY `FK_automatic_reminders_template`;");
        $this->execute("ALTER TABLE `automatic_reminders` DROP COLUMN `template_id`, DROP COLUMN `assigned_to`, DROP COLUMN `assign_to_role_id`, DROP COLUMN `assign_to_member_id`, DROP COLUMN `message`, DROP COLUMN `notify_client`;");
    }

    public function down()
    {
        // Restore removed columns in automatic_reminders table
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `template_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `company_id`;");
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `assigned_to` TINYINT(3) NULL DEFAULT NULL AFTER `template_id`;");
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `assign_to_role_id` INT(11) NULL DEFAULT NULL AFTER `assigned_to`;");
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `assign_to_member_id` BIGINT(20) NULL DEFAULT NULL AFTER `assign_to_role_id`;");
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `message` TEXT NULL AFTER `reminder`;");
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `notify_client` ENUM('Y','N') NULL DEFAULT 'N' AFTER `message`;");

        $statement = $this->getQueryBuilder()
            ->select('*')
            ->from(array('a' => 'automatic_reminder_actions'))
            ->execute();

        $arrReminderActions = $statement->fetchAll('assoc');
        foreach ($arrReminderActions as $arrReminderActionInfo) {
            $arrActionSettings = Json::decode($arrReminderActionInfo['automatic_reminder_action_settings'], Json::TYPE_ARRAY);

            $arrUpdate = array();
            switch ($arrReminderActionInfo['automatic_reminder_action_type_id']) {
                case 2:
                    // Create task
                    if (preg_match('/^(.*):(.*)$/', $arrActionSettings['task_assign_to'], $regs)) {
                        $assignedTo         = null;
                        $assignedToRoleId   = null;
                        $assignedToMemberId = null;

                        $booUpdate = true;
                        switch ($regs[1]) {
                            case 'user':
                                if ($regs[2] == 'all') {
                                    $assignedTo = 3;
                                } else {
                                    $assignedTo         = 2;
                                    $assignedToMemberId = $regs[2];
                                }
                                break;

                            case 'role':
                                $assignedTo       = 1;
                                $assignedToRoleId = $regs[2];
                                break;

                            case 'assigned':
                                $assignedTo = $regs[2];
                                break;

                            default:
                                $booUpdate = false;
                                break;
                        }

                        if ($booUpdate) {
                            $arrUpdate = array(
                                'assigned_to'         => $assignedTo,
                                'assign_to_role_id'   => $assignedToRoleId,
                                'assign_to_member_id' => $assignedToMemberId,
                                'message'             => stripslashes($arrActionSettings['task_message']),
                            );
                        }
                    }

                    break;

                case 3:
                    // Email
                    $arrUpdate = array(
                        'template_id'   => $arrActionSettings['template_id'],
                        'notify_client' => 'Y',
                    );
                    break;

                default:
                    break;
            }

            if (count($arrUpdate)) {
                $this->getQueryBuilder()
                    ->update('automatic_reminders')
                    ->set($arrUpdate)
                    ->where(
                        [
                            'automatic_reminder_id' => (int)$arrReminderActionInfo['automatic_reminder_id']
                        ]
                    )
                    ->execute();
            }
        }

        $this->execute("ALTER TABLE `automatic_reminder_actions` DROP FOREIGN KEY `FK_automatic_reminder_actions_company`;");
        $this->execute("ALTER TABLE `automatic_reminder_actions` DROP COLUMN `company_id`;");
        $this->execute("DELETE FROM `automatic_reminder_action_types` WHERE  `automatic_reminder_action_type_id`=3;");
        $this->execute("DELETE FROM `automatic_reminder_action_types` WHERE  `automatic_reminder_action_type_id`=2;");
        $this->execute("ALTER TABLE `automatic_reminder_action_types` DROP COLUMN `automatic_reminder_action_type_internal_name`;");
    }
}
