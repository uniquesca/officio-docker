<?php

use Officio\Migration\AbstractMigration;

class ChangeCronTriggerLabel extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `automatic_reminder_trigger_types` SET `automatic_reminder_trigger_type_name`='Check Daily' WHERE  `automatic_reminder_trigger_type_internal_id`='cron';");
    }

    public function down()
    {
        $this->execute("UPDATE `automatic_reminder_trigger_types` SET `automatic_reminder_trigger_type_name`='Cron' WHERE  `automatic_reminder_trigger_type_internal_id`='cron';");
    }
}