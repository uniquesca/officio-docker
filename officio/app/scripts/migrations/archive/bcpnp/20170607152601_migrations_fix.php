<?php

use Phinx\Migration\AbstractMigration;

class MigrationsFix extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "ALTER TABLE `applicant_form_fields`
	        CHANGE COLUMN `type` `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields','html_editor','kskeydid','case_internal_id','applicant_internal_id','multiple_combo') NOT NULL DEFAULT 'text' AFTER `applicant_field_unique_id`;"
        );
    }

    public function down()
    {
    }
}