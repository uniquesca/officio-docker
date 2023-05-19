<?php

use Phinx\Migration\AbstractMigration;

class AllowSearchForProspectJobNoc extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_use_in_search`='Y' WHERE `q_field_unique_id` IN ('qf_job_noc', 'qf_job_spouse_noc');");
    }

    public function down()
    {
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_use_in_search`='N' WHERE `q_field_unique_id` IN ('qf_job_noc', 'qf_job_spouse_noc');");
    }
}