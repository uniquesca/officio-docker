<?php

use Phinx\Migration\AbstractMigration;

class SetVisibleProfileForeignWorkerField extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_show_in_prospect_profile`='Y' WHERE  `q_field_unique_id`='qf_work_temporary_worker' AND `q_section_id`=6;");
    }

    public function down()
    {
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_show_in_prospect_profile`='N' WHERE  `q_field_unique_id`='qf_work_temporary_worker' AND `q_section_id`=6;");
    }
}