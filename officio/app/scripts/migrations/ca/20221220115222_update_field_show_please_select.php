<?php

use Officio\Migration\AbstractMigration;

class UpdateFieldShowPleaseSelect extends AbstractMigration
{
    public function up()
    {
        $this->execute(
            "UPDATE company_questionnaires_fields
            SET q_field_show_please_select = 'Y'
            WHERE q_field_unique_id IN (
              'qf_certificate_of_qualification',
              'qf_nomination_certificate'
            );");
    }

    public function down()
    {
        $this->execute(
            "UPDATE company_questionnaires_fields
            SET q_field_show_please_select = 'N'
            WHERE q_field_unique_id IN (
              'qf_certificate_of_qualification',
              'qf_nomination_certificate'
            );");
    }
}
