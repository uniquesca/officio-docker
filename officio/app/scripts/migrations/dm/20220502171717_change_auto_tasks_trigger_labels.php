<?php

use Phinx\Migration\AbstractMigration;

class ChangeAutoTasksTriggerLabels extends AbstractMigration
{
    protected $clearCache = true;

    public function up()
    {
        $this->execute("UPDATE `automatic_reminder_trigger_types` SET `automatic_reminder_trigger_type_name`='When Client Profile/Case Details is saved' WHERE `automatic_reminder_trigger_type_internal_id`='client_or_case_profile_update';");
        $this->execute("UPDATE `automatic_reminder_trigger_types` SET `automatic_reminder_trigger_type_name`='When a payment becomes due' WHERE `automatic_reminder_trigger_type_internal_id`='payment_due';");
        $this->execute("UPDATE `automatic_reminder_trigger_types` SET `automatic_reminder_trigger_type_name`='When a client completes a form' WHERE `automatic_reminder_trigger_type_internal_id`='case_mark_form_as_complete';");
        $this->execute("UPDATE `automatic_reminder_trigger_types` SET `automatic_reminder_trigger_type_name`='When a client uploads a document' WHERE `automatic_reminder_trigger_type_internal_id`='case_uploads_documents';");
        $this->execute("UPDATE `automatic_reminder_trigger_types` SET `automatic_reminder_trigger_type_name`='When a file status is changed' WHERE `automatic_reminder_trigger_type_internal_id`='case_file_status_changed';");
        $this->execute("UPDATE `automatic_reminder_trigger_types` SET `automatic_reminder_trigger_type_name`='When a new case is created' WHERE `automatic_reminder_trigger_type_internal_id`='case_creation';");
        $this->execute("UPDATE `automatic_reminder_trigger_types` SET `automatic_reminder_trigger_type_name`='When a field value on Client Profile/Case Details is changed' WHERE `automatic_reminder_trigger_type_internal_id`='field_value_change';");
        $this->execute("UPDATE `automatic_reminder_trigger_types` SET `automatic_reminder_trigger_type_name`='Run it once a day' WHERE  `automatic_reminder_trigger_type_internal_id`='cron';");
    }

    public function down()
    {
        $this->execute("UPDATE `automatic_reminder_trigger_types` SET `automatic_reminder_trigger_type_name`='Client/Case profile update' WHERE `automatic_reminder_trigger_type_internal_id`='client_or_case_profile_update';");
        $this->execute("UPDATE `automatic_reminder_trigger_types` SET `automatic_reminder_trigger_type_name`='Payment is due from Retainer Schedule' WHERE `automatic_reminder_trigger_type_internal_id`='payment_due';");
        $this->execute("UPDATE `automatic_reminder_trigger_types` SET `automatic_reminder_trigger_type_name`='Case mark a form as Complete' WHERE `automatic_reminder_trigger_type_internal_id`='case_mark_form_as_complete';");
        $this->execute("UPDATE `automatic_reminder_trigger_types` SET `automatic_reminder_trigger_type_name`='Case uploads Documents' WHERE `automatic_reminder_trigger_type_internal_id`='case_uploads_documents';");
        $this->execute("UPDATE `automatic_reminder_trigger_types` SET `automatic_reminder_trigger_type_name`='Case File Status changed' WHERE `automatic_reminder_trigger_type_internal_id`='case_file_status_changed';");
        $this->execute("UPDATE `automatic_reminder_trigger_types` SET `automatic_reminder_trigger_type_name`='Case creation' WHERE `automatic_reminder_trigger_type_internal_id`='case_creation';");
        $this->execute("UPDATE `automatic_reminder_trigger_types` SET `automatic_reminder_trigger_type_name`='Field value change' WHERE `automatic_reminder_trigger_type_internal_id`='field_value_change';");
        $this->execute("UPDATE `automatic_reminder_trigger_types` SET `automatic_reminder_trigger_type_name`='Check Daily' WHERE `automatic_reminder_trigger_type_internal_id`='cron';");
    }
}