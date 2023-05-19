<?php

use Officio\Common\Json;
use Officio\Migration\AbstractMigration;

class ConvertAutomaticReminderConditions extends AbstractMigration
{
    public function up()
    {
        $statement = $this->getQueryBuilder()
            ->select('*')
            ->from('automatic_reminders')
            ->execute();

        $arrReminders = $statement->fetchAll('assoc');

        $statement = $this->getQueryBuilder()
            ->select('*')
            ->from('automatic_reminder_condition_types')
            ->execute();

        $arrReminderConditionTypes = $statement->fetchAll('assoc');

        $arrReminderConditionTypesGrouped = [];
        foreach ($arrReminderConditionTypes as $arrReminderConditionTypeInfo) {
            $arrReminderConditionTypesGrouped[$arrReminderConditionTypeInfo['automatic_reminder_condition_type_internal_id']] = $arrReminderConditionTypeInfo['automatic_reminder_condition_type_id'];
        }

        // Collect grouped ids for Client_file_status field for all companies
        $statement = $this->getQueryBuilder()
            ->select(['f.company_id', 'f.field_id'])
            ->from(['f' => 'client_form_fields'])
            ->where([
                'f.company_field_id' => 'Client_file_status'
            ])
            ->execute();

        $arrSavedCaseStatusFields = $statement->fetchAll('assoc');

        $arrSavedCaseStatusFieldsGrouped = [];
        foreach ($arrSavedCaseStatusFields as $arrSavedCaseStatusFieldInfo) {
            $arrSavedCaseStatusFieldsGrouped[$arrSavedCaseStatusFieldInfo['company_id']] = $arrSavedCaseStatusFieldInfo['field_id'];
        }

        $arrConditionsInsert = [];
        foreach ($arrReminders as $arrReminderInfo) {
            switch ($arrReminderInfo['type']) {
                case 'CLIENT_PROFILE':
                case 'PROFILE':
                    $arrSettings = array(
                        'number' => $arrReminderInfo['number'],
                        'days'   => $arrReminderInfo['days'],
                        'ba'     => $arrReminderInfo['ba'],
                        'prof'   => $arrReminderInfo['prof']
                    );
                    break;

                case 'FILESTATUS':
                    $arrSettings = array(
                        'number'      => $arrReminderInfo['number'],
                        'days'        => $arrReminderInfo['days'],
                        'ba'          => $arrReminderInfo['ba'],
                        'file_status' => $arrReminderInfo['file_status']
                    );
                    break;

                case 'TRIGGER':
                    $arrSettings = array(
                        'based_on_field_member_type' => 'case',
                        'based_on_field_field_id'    => $arrSavedCaseStatusFieldsGrouped[$arrReminderInfo['company_id']],
                        'based_on_field_condition'   => 'is_not_empty'
                    );

                    // This condition should be related to the field from case details
                    $arrReminderInfo['type'] = 'BASED_ON_FIELD';
                    break;

                default:
                    $arrSettings = array();
                    break;
            }

            $conditionTypeId = isset($arrReminderConditionTypesGrouped[$arrReminderInfo['type']]) ? $arrReminderConditionTypesGrouped[$arrReminderInfo['type']] : 0;
            if (!empty($arrSettings) && !empty($conditionTypeId)) {
                $arrConditionsInsert[] = [
                    'company_id'                               => $arrReminderInfo['company_id'],
                    'automatic_reminder_id'                    => $arrReminderInfo['automatic_reminder_id'],
                    'automatic_reminder_condition_type_id'     => $conditionTypeId,
                    'automatic_reminder_condition_settings'    => Json::encode($arrSettings),
                    'automatic_reminder_condition_create_date' => $arrReminderInfo['create_date'],
                ];
            }
        }

        // Add new conditions at once
        if (!empty($arrConditionsInsert)) {
            $this->table('automatic_reminder_conditions')
                ->insert($arrConditionsInsert)
                ->save();
        }

        $this->execute("ALTER TABLE `automatic_reminders` DROP COLUMN `type`, DROP COLUMN `number`, DROP COLUMN `days`, DROP COLUMN `ba`, DROP COLUMN `prof`, DROP COLUMN `file_status`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `type` ENUM('TRIGGER','CLIENT_PROFILE','PROFILE','FILESTATUS') NULL DEFAULT 'TRIGGER' AFTER `assign_to_member_id`;");
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `number` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `trigger`;");
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `days` ENUM('CALENDAR','BUSINESS') NULL DEFAULT 'CALENDAR' AFTER `number`;");
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `ba` ENUM('BEFORE','AFTER') NULL DEFAULT 'AFTER' AFTER `days`;");
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `prof` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `ba`;");
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `file_status` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `prof`;");

        $statement = $this->getQueryBuilder()
            ->select('*')
            ->from('automatic_reminder_conditions')
            ->execute();

        $arrReminderConditions = $statement->fetchAll('assoc');

        $statement = $this->getQueryBuilder()
            ->select('*')
            ->from('automatic_reminder_condition_types')
            ->execute();

        $arrReminderConditionTypes = $statement->fetchAll('assoc');

        $arrReminderConditionTypesGrouped = [];
        foreach ($arrReminderConditionTypes as $arrReminderConditionTypeInfo) {
            $arrReminderConditionTypesGrouped[$arrReminderConditionTypeInfo['automatic_reminder_condition_type_id']] = $arrReminderConditionTypeInfo['automatic_reminder_condition_type_internal_id'];
        }

        foreach ($arrReminderConditions as $arrReminderConditionInfo) {
            $arrConditionSettings = Json::decode($arrReminderConditionInfo['automatic_reminder_condition_settings'], Json::TYPE_ARRAY);

            $conditionType = isset($arrReminderConditionTypesGrouped[$arrReminderConditionInfo['automatic_reminder_condition_type_id']]) ? $arrReminderConditionTypesGrouped[$arrReminderConditionInfo['automatic_reminder_condition_type_id']] : '';
            if (in_array($conditionType, ['CLIENT_PROFILE', 'PROFILE', 'FILESTATUS'])) {
                $this->getQueryBuilder()
                    ->update('automatic_reminders')
                    ->set(
                        [
                            'type'        => $conditionType,
                            'number'      => $arrConditionSettings['number'],
                            'days'        => $arrConditionSettings['days'],
                            'ba'          => $arrConditionSettings['ba'],
                            'prof'        => $conditionType == 'FILESTATUS' ? 0 : $arrConditionSettings['prof'],
                            'file_status' => $conditionType == 'FILESTATUS' ? $arrConditionSettings['file_status'] : 0,
                        ]
                    )
                    ->where(['automatic_reminder_id' => $arrReminderConditionInfo['automatic_reminder_id']])
                    ->execute();
            }
        }

        $this->getQueryBuilder()
            ->delete('automatic_reminder_conditions')
            ->execute();
    }
}
