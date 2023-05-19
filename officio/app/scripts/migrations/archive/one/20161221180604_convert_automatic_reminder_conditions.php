<?php

use Clients\Service\Clients;
use Laminas\Json\Json;
use Phinx\Migration\AbstractMigration;
use Officio\Service\AutomaticReminders;

class ConvertAutomaticReminderConditions extends AbstractMigration
{
    public function up()
    {
        /* @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from(array('r' => 'automatic_reminders'));

        $arrReminders = $db->fetchAll($select);

        /** @var Clients $clients */
        $clients = Zend_Registry::get('serviceManager')->get(Clients::class);
        /** @var AutomaticReminders $oAutomaticReminders */
        $oAutomaticReminders = Zend_Registry::get('serviceManager')->get(AutomaticReminders::class);
        $arrCachedFieldIds            = array();
        foreach ($arrReminders as $arrReminderInfo) {
            // Create "active clients only" condition
            if ($arrReminderInfo['active_clients_only'] == 'Y') {
                if (isset($arrCachedFieldIds[$arrReminderInfo['company_id']])) {
                    $fieldId = $arrCachedFieldIds[$arrReminderInfo['company_id']];
                } else {
                    $fieldId = $arrCachedFieldIds[$arrReminderInfo['company_id']] = $clients->getFields()->getClientStatusFieldId($arrReminderInfo['company_id']);
                }

                if (!empty($fieldId)) {
                    $arrSettings = array(
                        'based_on_field_member_type' => 'case',
                        'based_on_field_field_id'    => $fieldId,
                        'based_on_field_condition'   => 'is_not_empty'
                    );

                    $db->insert(
                        'automatic_reminder_conditions',
                        array(
                            'company_id'                               => $arrReminderInfo['company_id'],
                            'automatic_reminder_id'                    => $arrReminderInfo['automatic_reminder_id'],
                            'automatic_reminder_condition_type_id'     => $oAutomaticReminders->getConditions()->getConditionTypeIdByTextId('BASED_ON_FIELD'),
                            'automatic_reminder_condition_settings'    => Json::encode($arrSettings),
                            'automatic_reminder_condition_create_date' => $arrReminderInfo['create_date'],
                        )
                    );
                }
            }

            // Create all other conditions
            $arrSettings     = array();
            $conditionTypeId = $oAutomaticReminders->getConditions()->getConditionTypeIdByTextId($arrReminderInfo['type']);
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

                default:
                    break;
            }

            if (!empty($arrSettings) && !empty($conditionTypeId)) {
                $db->insert(
                    'automatic_reminder_conditions',
                    array(
                        'company_id'                               => $arrReminderInfo['company_id'],
                        'automatic_reminder_id'                    => $arrReminderInfo['automatic_reminder_id'],
                        'automatic_reminder_condition_type_id'     => $conditionTypeId,
                        'automatic_reminder_condition_settings'    => Json::encode($arrSettings),
                        'automatic_reminder_condition_create_date' => $arrReminderInfo['create_date'],
                    )
                );
            }
        }

        $this->execute("ALTER TABLE `automatic_reminders` DROP COLUMN `type`, DROP COLUMN `number`, DROP COLUMN `days`, DROP COLUMN `ba`, DROP COLUMN `prof`, DROP COLUMN `file_status`, DROP COLUMN `active_clients_only`;");
    }

    public function down()
    {
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `type` ENUM('TRIGGER','CLIENT_PROFILE','PROFILE','FILESTATUS') NULL DEFAULT 'TRIGGER' AFTER `assign_to_member_id`;");
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `number` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `trigger`;");
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `days` ENUM('CALENDAR','BUSINESS') NULL DEFAULT 'CALENDAR' AFTER `number`;");
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `ba` ENUM('BEFORE','AFTER') NULL DEFAULT 'AFTER' AFTER `days`;");
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `prof` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `ba`;");
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `file_status` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `prof`;");
        $this->execute("ALTER TABLE `automatic_reminders` ADD COLUMN `active_clients_only` ENUM('Y','N') NULL DEFAULT 'Y' AFTER `message`;");
        $this->execute("UPDATE `automatic_reminders` SET `active_clients_only`='N';");


        /* @var $db Zend_Db_Adapter_Abstract */
        $db = Zend_Registry::get('serviceManager')->get('db');

        $select = $db->select()
            ->from(array('c' => 'automatic_reminder_conditions'));

        $arrReminderConditions = $db->fetchAll($select);

        $arrCachedFieldIds            = array();

        /** @var Clients $clients */
        $clients = Zend_Registry::get('serviceManager')->get(Clients::class);
        /** @var AutomaticReminders $oAutomaticReminders */
        $oAutomaticReminders = Zend_Registry::get('serviceManager')->get(AutomaticReminders::class);

        foreach ($arrReminderConditions as $arrReminderConditionInfo) {
            $arrConditionSettings = Json::decode($arrReminderConditionInfo['automatic_reminder_condition_settings'], Json::TYPE_ARRAY);

            $conditionType = $oAutomaticReminders->getConditions()->getConditionTypeTextIdById($arrReminderConditionInfo['automatic_reminder_condition_type_id']);
            if ($conditionType == 'BASED_ON_FIELD') {
                if (isset($arrCachedFieldIds[$arrReminderConditionInfo['company_id']])) {
                    $fieldId = $arrCachedFieldIds[$arrReminderConditionInfo['company_id']];
                } else {
                    $fieldId = $arrCachedFieldIds[$arrReminderConditionInfo['company_id']] = $clients->getFields()->getClientStatusFieldId($arrReminderConditionInfo['company_id']);
                }

                if (!empty($fieldId) && isset($arrConditionSettings['based_on_field_member_type']) && $arrConditionSettings['based_on_field_member_type'] == 'case'
                    && isset($arrConditionSettings['based_on_field_field_id']) && $arrConditionSettings['based_on_field_field_id'] == $fieldId
                ) {
                    $db->update(
                        'automatic_reminders',
                        array(
                            'active_clients_only' => 'Y',
                        ),
                        $db->quoteInto('automatic_reminder_id = ?', $arrReminderConditionInfo['automatic_reminder_id'])
                    );
                }
            } else {
                $db->update(
                    'automatic_reminders',
                    array(
                        'type'        => $conditionType,
                        'number'      => $arrConditionSettings['number'],
                        'days'        => $arrConditionSettings['days'],
                        'ba'          => $arrConditionSettings['ba'],
                        'prof'        => $conditionType == 'FILESTATUS' ? 0 : $arrConditionSettings['prof'],
                        'file_status' => $conditionType == 'FILESTATUS' ? $arrConditionSettings['file_status'] : 0,
                    ),
                    $db->quoteInto('automatic_reminder_id = ?', $arrReminderConditionInfo['automatic_reminder_id'])
                );
            }
        }

        $db->delete('automatic_reminder_conditions');
    }
}
