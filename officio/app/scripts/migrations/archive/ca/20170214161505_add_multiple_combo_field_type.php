<?php

use Officio\Migration\AbstractMigration;

class AddMultipleComboFieldType extends AbstractMigration
{
    public function up()
    {
        $this->execute("INSERT INTO `field_types` (`field_type_id`, `field_type_text_id`, `field_type_label`, `field_type_can_be_used_in_search`, `field_type_with_options`) VALUES
            (40, 'multiple_combo', 'Multiple Select Box', 'N', 'Y');");

        $this->execute("ALTER TABLE `applicant_form_fields`
            CHANGE COLUMN `type` `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields','html_editor','kskeydid','case_internal_id','applicant_internal_id','multiple_combo') NOT NULL DEFAULT 'text' AFTER `applicant_field_unique_id`;");

        $this->execute("UPDATE `applicant_form_fields` SET `type`='multiple_combo' WHERE applicant_field_unique_id = 'employer_organization_type'");
    }

    public function down()
    {
        $this->execute("UPDATE `applicant_form_fields` SET `type`='combo' WHERE applicant_field_unique_id = 'employer_organization_type'");

        $this->execute("ALTER TABLE `applicant_form_fields`
            CHANGE COLUMN `type` `type` ENUM('text','password','number','email','phone','memo','combo','country','agents','office','office_multi','assigned_to','radio','checkbox','date','date_repeatable','photo','file','office_change_date_time','multiple_text_fields','html_editor','kskeydid','case_internal_id','applicant_internal_id') NOT NULL DEFAULT 'text' AFTER `applicant_field_unique_id`;");

        $this->execute("DELETE FROM `field_types` WHERE  `field_type_id`=40");
    }
}
