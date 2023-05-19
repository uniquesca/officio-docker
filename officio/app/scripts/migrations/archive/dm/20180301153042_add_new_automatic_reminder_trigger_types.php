<?php

use Phinx\Migration\AbstractMigration;

class AddNewAutomaticReminderTriggerTypes extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `automatic_reminder_trigger_types` (`automatic_reminder_trigger_type_id`, `automatic_reminder_trigger_type_internal_id`, `automatic_reminder_trigger_type_name`, `automatic_reminder_trigger_type_order`) VALUES 
                              (9, 'upload_additional_documents', 'New documents uploaded to the Additional Documents Folder', 8),
                              (10, 'payments_have_received', 'New payments have received', 9);");
    }

    public function down()
    {
        $this->execute("DELETE FROM `automatic_reminder_trigger_types` WHERE  `automatic_reminder_trigger_type_id` IN (9, 10);");
    }
}
