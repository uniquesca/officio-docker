<?php

use Officio\Migration\AbstractMigration;

class RenameSpecificCaseFields extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `client_form_fields` SET `company_field_id`='LMIA_revocation_suspension_date', `label`='LMIA Revocation/Suspension' WHERE `company_field_id`='LMIA_revocation_suspention_date'");
        $this->execute("UPDATE `client_form_fields` SET `company_field_id`='LMIA_revocation_suspension_reason', `label`='LMIA Revocation/Suspension Reason' WHERE `company_field_id`='LMIA_revocation_suspention_reason'");
    }

    public function down()
    {
        $this->execute("UPDATE `client_form_fields` SET `company_field_id`='LMIA_revocation_suspention_date', `label`='LMIA Revocation/Suspention' WHERE `company_field_id`='LMIA_revocation_suspension_date'");
        $this->execute("UPDATE `client_form_fields` SET `company_field_id`='LMIA_revocation_suspention_reason', `label`='LMIA Revocation/Suspention Reason' WHERE `company_field_id`='LMIA_revocation_suspension_reason'");
    }
}
