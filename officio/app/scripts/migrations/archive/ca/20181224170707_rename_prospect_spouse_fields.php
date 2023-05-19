<?php

use Officio\Migration\AbstractMigration;

class RenameProspectSpouseFields extends AbstractMigration
{
    public function up()
    {
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_unique_id`='qf_spouse_last_name' WHERE  `q_field_id`=19");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_unique_id`='qf_spouse_first_name' WHERE  `q_field_id`=20");
    }

    public function down()
    {
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_unique_id`='qf_spouse_first_name' WHERE  `q_field_id`=19");
        $this->execute("UPDATE `company_questionnaires_fields` SET `q_field_unique_id`='qf_spouse_last_name' WHERE  `q_field_id`=20");
    }
}