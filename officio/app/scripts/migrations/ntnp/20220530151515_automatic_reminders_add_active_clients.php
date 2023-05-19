<?php

use Officio\Common\Json;
use Officio\Migration\AbstractMigration;

class AutomaticRemindersAddActiveClients extends AbstractMigration
{
    public function up()
    {
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `active_clients_only` ENUM('Y','N') NULL DEFAULT 'N' AFTER `reminder`;");

        // Search conditions that we'll analyze
        $statement = $this->getQueryBuilder()
            ->select('*')
            ->from('automatic_reminder_conditions')
            ->where(['automatic_reminder_condition_type_id' => 4])
            ->execute();

        $arrReminderConditions = $statement->fetchAll('assoc');

        // Find "case file status" fields, group by companies -> will be used to find the condition and parent auto task will be updated
        $statement = $this->getQueryBuilder()
            ->select('*')
            ->from('client_form_fields')
            ->where(['company_field_id' => 'Client_file_status'])
            ->execute();

        $arrCompanyFields = $statement->fetchAll('assoc');


        $arrCompanyFieldsGrouped = [];
        foreach ($arrCompanyFields as $arrCompanyFieldInfo) {
            $arrCompanyFieldsGrouped[$arrCompanyFieldInfo['company_id']] = $arrCompanyFieldInfo['field_id'];
        }

        $arrRemindersToUpdate  = [];
        $arrConditionsToDelete = [];
        foreach ($arrReminderConditions as $arrReminderConditionInfo) {
            if (isset($arrCompanyFieldsGrouped[$arrReminderConditionInfo['company_id']]) && !empty($arrCompanyFieldsGrouped[$arrReminderConditionInfo['company_id']])) {
                $arrConditionSettings = Json::decode($arrReminderConditionInfo['automatic_reminder_condition_settings'], Json::TYPE_ARRAY);

                if (isset($arrConditionSettings['based_on_field_member_type'])
                    && $arrConditionSettings['based_on_field_member_type'] == 'case'
                    && isset($arrConditionSettings['based_on_field_field_id'])
                    && $arrConditionSettings['based_on_field_field_id'] == $arrCompanyFieldsGrouped[$arrReminderConditionInfo['company_id']]
                    && isset($arrConditionSettings['based_on_field_condition'])
                    && $arrConditionSettings['based_on_field_condition'] == 'is_not_empty'
                ) {
                    $arrRemindersToUpdate[]  = $arrReminderConditionInfo['automatic_reminder_id'];
                    $arrConditionsToDelete[] = $arrReminderConditionInfo['automatic_reminder_condition_id'];
                }
            }
        }

        if (!empty($arrRemindersToUpdate)) {
            $this->getQueryBuilder()
                ->update('automatic_reminders')
                ->set(['active_clients_only' => 'Y'])
                ->whereInList('automatic_reminder_id', $arrRemindersToUpdate)
                ->execute();
        }

        // Delete conditions that were converted
        if (!empty($arrConditionsToDelete)) {
            $this->getQueryBuilder()
                ->delete('automatic_reminder_conditions')
                ->whereInList('automatic_reminder_condition_id', $arrConditionsToDelete)
                ->execute();
        }
    }

    public function down()
    {
        $this->execute("ALTER TABLE `automatic_reminders` DROP COLUMN `active_clients_only`;");
    }
}
