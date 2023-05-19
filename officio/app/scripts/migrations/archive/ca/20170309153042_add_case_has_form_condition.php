<?php

use Officio\Migration\AbstractMigration;

class AddCaseHasFormCondition extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `automatic_reminder_condition_types` (`automatic_reminder_condition_type_internal_id`, `automatic_reminder_condition_type_name`, `automatic_reminder_condition_type_order`) VALUES ('CASE_HAS_FORM', 'Case has a form assigned', 6);");
    }

    public function down()
    {
        $this->execute("DELETE FROM `automatic_reminder_condition_types` WHERE  `automatic_reminder_condition_type_internal_id` = 'CASE_HAS_FORM';");
    }
}