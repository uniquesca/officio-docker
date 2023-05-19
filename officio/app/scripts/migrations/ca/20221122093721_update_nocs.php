<?php

use Officio\Migration\AbstractMigration;

class UpdateNocs extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE company_questionnaires_fields_templates 
            SET q_field_label = 'NOC TEER/Code', q_field_prospect_profile_label = 'NOC TEER/Code'
            WHERE q_field_id = (
                SELECT q_field_id FROM company_questionnaires_fields where q_field_unique_id = 'qf_work_noc'
            );");
        $sql = file_get_contents('scripts/db/noc_2021.sql');
        $this->execute($sql);
    }

    public function down()
    {
        $this->execute("UPDATE company_questionnaires_fields_templates 
            SET q_field_label = 'NOC', q_field_prospect_profile_label = 'NOC'
            WHERE q_field_id = (
                SELECT q_field_id FROM company_questionnaires_fields where q_field_unique_id = 'qf_work_noc'
            );");
        $sql = file_get_contents('scripts/db/noc.sql');
        $this->execute($sql);
    }
}
